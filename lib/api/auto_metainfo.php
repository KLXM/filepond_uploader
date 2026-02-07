<?php

use FriendsOfRedaxo\MetaInfoLangFields\MetainfoLangHelper;

/**
 * Automatische MetaInfo-Feld-Erkennung für FilePond
 * Pragmatischer Ansatz: Vollautomatische Erkennung aller relevanten Felder.
 */
class rex_api_filepond_auto_metainfo extends rex_api_function
{
    protected $published = true;

    /**
     * Zentrale Methode für das Senden von JSON-Antworten.
     *
     * @param array<string, mixed> $data
     */
    protected function sendResponse(array $data, int $statusCode = 200): never
    {
        rex_response::cleanOutputBuffers();
        if (200 !== $statusCode) {
            http_response_code($statusCode);
        }
        rex_response::sendJson($data);
        exit;
    }

    public function execute(): rex_api_result
    {
        $action = rex_request('action', 'string');

        switch ($action) {
            case 'get_fields':
                $this->getMetaInfoFields();
                break;

            case 'save_metadata':
                $this->saveMetadata();
                break;

            case 'load_metadata':
                $this->loadMetadata();
                break;

            default:
                $this->sendResponse([
                    'success' => false,
                    'error' => 'Unbekannte Aktion',
                ], 400);
        }

        return new rex_api_result(true);
    }

    /**
     * Automatische Erkennung aller relevanten MetaInfo-Felder
     * Lädt dynamisch alle Felder, die mit "med_" beginnen, filtert aber ausgeblendete Felder.
     */
    private function getMetaInfoFields(): void
    {
        try {
            $fields = [];

            // Konfigurierte Blacklist laden
            $excludedFields = rex_config::get('filepond_uploader', 'excluded_metadata_fields', []);
            if (!is_array($excludedFields)) {
                // rex_config_form speichert Arrays oft als pipe-separierten String (|value|value|)
                if (is_string($excludedFields) && str_contains($excludedFields, '|')) {
                    $excludedFields = array_filter(explode('|', $excludedFields), static fn (string $v): bool => '' !== $v);
                } elseif (is_string($excludedFields)) {
                    // Fallback, falls CSV
                    $excludedFields = explode(',', $excludedFields);
                } else {
                    $excludedFields = [];
                }
            }

            // 1. Titel ist immer dabei (REDAXO Standard)
            // Nur hinzufügen wenn nicht ausgeschlossen
            if (!in_array('title', $excludedFields, true)) {
                $fields[] = $this->analyzeField('title');
            }

            // 2. Prüfen ob MetaInfo Addon verfügbar ist
            $hasMetaInfo = rex_addon::exists('metainfo') && rex_addon::get('metainfo')->isAvailable();

            if (!$hasMetaInfo) {
                // Fallback ohne MetaInfo: Nur die absoluten Standardfelder annehmen
                $defaults = ['med_alt', 'med_copyright', 'med_description'];
                foreach ($defaults as $f) {
                    if (!in_array($f, $excludedFields, true)) {
                        $fields[] = $this->analyzeField($f);
                    }
                }
            } else {
                // 3. Dynamisch ALLE med_ Felder aus MetaInfo laden
                $sql = rex_sql::factory();
                // Wir holen alle Felder, die mit med_ beginnen, sortiert nach Priorität
                $sql->setQuery('SELECT name FROM ' . rex::getTable('metainfo_field') . ' WHERE name LIKE "med_%" ORDER BY priority');

                foreach ($sql as $row) {
                    $name = (string) ($row->getValue('name') ?? '');
                    if (!in_array($name, $excludedFields, true)) {
                        $fields[] = $this->analyzeField($name);
                    }
                }
            }

            // Duplikate entfernen
            $uniqueFields = [];
            $seenNames = [];
            foreach ($fields as $field) {
                if (!in_array($field['name'], $seenNames, true)) {
                    $seenNames[] = $field['name'];
                    $uniqueFields[] = $field;
                }
            }

            $this->sendResponse([
                'success' => true,
                'fields' => $uniqueFields,
            ]);
        } catch (Exception $e) {
            rex_logger::logException($e);

            $this->sendResponse([
                'success' => false,
                'error' => 'Ein Fehler ist beim Laden der Metafelder aufgetreten',
            ], 500);
        }
    }

    /**
     * Prüft ob ein Feld existiert (in Standard-Tabelle oder MetaInfo).
     */
    private function fieldExists(string $fieldName): bool
    {
        // Standard-Felder existieren immer
        if (in_array($fieldName, ['title', 'med_alt', 'med_copyright'], true)) {
            return true;
        }

        // Prüfe in MetaInfo
        if (rex_addon::exists('metainfo') && rex_addon::get('metainfo')->isAvailable()) {
            try {
                $sql = rex_sql::factory();
                $sql->setQuery('SELECT id FROM rex_metainfo_field WHERE name = ?', [$fieldName]);
                return $sql->getRows() > 0;
            } catch (Exception $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * Prüft ob ein Feld ein Pflichtfeld ist.
     */
    private function isFieldRequired(string $fieldName): bool
    {
        // 1. Prüfe alten Schalter für Titel
        if ('title' === $fieldName && (bool) rex_config::get('filepond_uploader', 'title_required_default', 0)) {
            return true;
        }

        // 2. Prüfe neue kommaseparierte Liste
        $requiredFields = (string) rex_config::get('filepond_uploader', 'required_metadata_fields', '');
        if ('' === $requiredFields) {
            return false;
        }

        $fields = array_map('trim', explode(',', $requiredFields));
        return in_array($fieldName, $fields, true);
    }

    /**
     * Analysiert ein Feld auf Typ und Mehrsprachigkeit.
     *
     * @return array{name: string, label: string, type: string, multilingual: bool, required: bool, languages: list<array{code: string, name: string, id: int}>}
     */
    private function analyzeField(string $fieldName): array
    {
        // Standard-Feld-Informationen
        $fieldInfo = [
            'name' => $fieldName,
            'label' => $this->getFieldLabel($fieldName),
            'type' => $this->getFieldType($fieldName),
            'multilingual' => $this->isMultilingual($fieldName),
            'required' => $this->isFieldRequired($fieldName),
            'languages' => [],
        ];

        // Wenn mehrsprachig, alle verfügbaren Sprachen laden
        if ($fieldInfo['multilingual']) {
            $fieldInfo['languages'] = $this->getAvailableLanguages();
        }

        return $fieldInfo;
    }

    /**
     * Ermittelt das Label für ein Feld.
     */
    private function getFieldLabel(string $fieldName): string
    {
        $label = ucfirst($fieldName);

        // 1. Standard Labels (Fallback)
        $labels = [
            'title' => 'Titel',
            'med_alt' => 'Alt-Text',
            'med_copyright' => 'Copyright',
            'med_description' => 'Beschreibung',
        ];

        if (isset($labels[$fieldName])) {
            $label = $labels[$fieldName];
        }

        // 2. MetaInfo Labels (Database) - Hat Vorrang
        if (rex_addon::exists('metainfo') && rex_addon::get('metainfo')->isAvailable()) {
            try {
                $sql = rex_sql::factory();
                $sql->setQuery('SELECT title FROM ' . rex::getTable('metainfo_field') . ' WHERE name = ?', [$fieldName]);
                if ($sql->getRows() > 0) {
                    $customLabel = (string) ($sql->getValue('title') ?? '');
                    if ('' !== $customLabel) {
                        $label = $customLabel;
                    }
                }
            } catch (Exception $e) {
                // Ignore
            }
        }

        // 3. Translate if needed (rex_i18n)
        if (str_starts_with($label, 'translate:')) {
            $key = substr($label, 10);
            return rex_i18n::msg($key);
        }

        return $label;
    }

    /**
     * Ermittelt den Feldtyp.
     */
    private function getFieldType(string $fieldName): string
    {
        // 1. Hardcoded Standard-Types
        // Title ist speziell und fix
        if ('title' === $fieldName) {
            return 'text';
        }

        // 2. Datenbank-Lookup via MetaInfo
        if (rex_addon::exists('metainfo') && rex_addon::get('metainfo')->isAvailable()) {
            try {
                $sql = rex_sql::factory();
                // Wir holen Label UND ID vom Typ, um auch lang_textarea erkennen zu können
                $sql->setQuery('
                    SELECT mf.type_id, mt.label as type_label
                    FROM ' . rex::getTable('metainfo_field') . ' mf
                    LEFT JOIN ' . rex::getTable('metainfo_type') . ' mt ON mf.type_id = mt.id
                    WHERE mf.name = ?
                ', [$fieldName]);

                if ($sql->getRows() > 0) {
                    $typeId = (int) $sql->getValue('type_id');
                    $typeLabel = (string) ($sql->getValue('type_label') ?? '');

                    // 1=Text, 2=Textarea (Standard MetaInfo)
                    if (2 === $typeId) {
                        return 'textarea';
                    }

                    // Check for specialized types (like lang_textarea_all)
                    if ('' !== $typeLabel && str_contains($typeLabel, 'textarea')) {
                        return 'textarea';
                    }

                    // Default to text
                    return 'text';
                }
            } catch (Exception $e) {
                // Ignore errors
            }
        }

        // Fallbacks für Standard-Felder wenn MetaInfo-Lookup fehlschlägt/nicht existiert
        if ('med_description' === $fieldName) {
            return 'textarea';
        }

        return 'text';
    }

    /**
     * Prüft ob ein Feld mehrsprachig konfiguriert ist.
     */
    private function isMultilingual(string $fieldName): bool
    {
        // Prüfe MetaInfo Lang Fields AddOn
        if (!rex_addon::exists('metainfo_lang_fields') || !rex_addon::get('metainfo_lang_fields')->isAvailable()) {
            return false;
        }

        // Prüfe ob MetaInfo AddOn verfügbar ist
        if (!rex_addon::exists('metainfo') || !rex_addon::get('metainfo')->isAvailable()) {
            return false;
        }

        try {
            // Prüfe den Feldtyp in der MetaInfo-Konfiguration
            $sql = rex_sql::factory();
            $sql->setQuery('
                SELECT mt.label as type_label 
                FROM ' . rex::getTable('metainfo_field') . ' mf 
                LEFT JOIN ' . rex::getTable('metainfo_type') . ' mt ON mf.type_id = mt.id 
                WHERE mf.name = ?
            ', [$fieldName]);

            if ($sql->getRows() > 0) {
                $typeLabel = (string) ($sql->getValue('type_label') ?? '');

                // Prüfe ob es ein mehrsprachiger Feldtyp ist
                $multilingualTypes = ['lang_text', 'lang_textarea', 'lang_text_all', 'lang_textarea_all'];
                return in_array($typeLabel, $multilingualTypes, true);
            }

            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Lädt alle verfügbaren Sprachen.
     *
     * @return list<array{code: string, name: string, id: int}>
     */
    private function getAvailableLanguages(): array
    {
        $languages = [];
        foreach (rex_clang::getAll() as $clang) {
            $languages[] = [
                'code' => $clang->getCode(),
                'name' => $clang->getName(),
                'id' => $clang->getId(),
            ];
        }
        return $languages;
    }

    /**
     * Speichert Metadaten für eine Datei.
     */
    private function saveMetadata(): void
    {
        try {
            $fileId = rex_request('file_id', 'string');
            $metadata = rex_request('metadata', 'array');

            // Input validation
            if ('' === $fileId) {
                throw new Exception('Keine Datei-ID angegeben');
            }

            // Validate file_id format (filename pattern)
            if (1 !== preg_match('/^[a-zA-Z0-9._-]+$/', $fileId)) {
                throw new Exception('Ungültige Datei-ID');
            }

            if ([] === $metadata) {
                throw new Exception('Ungültige Metadaten');
            }

            // Prüfe ob Datei existiert
            $media = rex_media::get($fileId);
            if (null === $media) {
                throw new Exception('Mediendatei nicht gefunden');
            }

            // SQL für Update vorbereiten
            $sql = rex_sql::factory();
            $sql->setTable('rex_media');
            $sql->setWhere(['filename' => $fileId]);

            // Verarbeite jedes Feld mit Validierung
            foreach ($metadata as $fieldName => $fieldValue) {
                // Validiere Feldname gegen Whitelist
                if (!$this->isValidMetaInfoField($fieldName)) {
                    continue; // Skip invalid field names
                }

                if ($this->isMultilingual($fieldName)) {
                    // Mehrsprachiges Feld - konvertiere zu MetaInfo Lang Fields Format
                    $sanitizedValue = $this->sanitizeMetaInfoValue($fieldValue);
                    $langData = $this->convertToMetaInfoLangFormat($sanitizedValue);
                    $sql->setValue($fieldName, json_encode($langData));
                } else {
                    // Standard-Feld
                    $sanitizedValue = $this->sanitizeMetaInfoValue($fieldValue);
                    $sql->setValue($fieldName, $sanitizedValue);
                }
            }

            // SQL error handling
            try {
                $sql->update();
            } catch (rex_sql_exception $e) {
                throw new Exception('Fehler beim Speichern der Metadaten: ' . $e->getMessage());
            }

            $this->sendResponse([
                'success' => true,
                'message' => 'Metadaten erfolgreich gespeichert',
            ]);
        } catch (Exception $e) {
            // Log the exception internally for debugging
            rex_logger::logException($e);

            $this->sendResponse([
                'success' => false,
                'error' => 'Ein Fehler ist beim Speichern der Metadaten aufgetreten',
            ], 500);
        }
    }

    /**
     * Konvertiert Frontend-Daten ins MetaInfo Lang Fields Format
     * Frontend: {"de": "Text", "en": "Text"}
     * MetaInfo: [{"clang_id": 1, "value": "Text"}, {"clang_id": 2, "value": "Text"}].
     *
     * @return list<array{clang_id: int, value: string}>
     */
    private function convertToMetaInfoLangFormat(mixed $fieldValue): array
    {
        if (!is_array($fieldValue)) {
            return [];
        }

        $result = [];
        $languages = rex_clang::getAll();

        foreach ($fieldValue as $langCode => $value) {
            // Finde Sprach-ID anhand des Codes
            foreach ($languages as $clang) {
                if ($clang->getCode() === $langCode) {
                    $result[] = [
                        'clang_id' => $clang->getId(),
                        'value' => (string) $value,
                    ];
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Lädt bestehende Metadaten für eine Datei.
     */
    private function loadMetadata(): void
    {
        try {
            $fileId = rex_request('file_id', 'string');

            // Input validation
            if ('' === $fileId) {
                throw new Exception('Keine Datei-ID angegeben');
            }

            // Validate file_id format (filename pattern)
            if (1 !== preg_match('/^[a-zA-Z0-9._-]+$/', $fileId)) {
                throw new Exception('Ungültige Datei-ID');
            }

            $media = rex_media::get($fileId);
            if (null === $media) {
                throw new Exception('Mediendatei nicht gefunden');
            }

            $metadata = [];

            // Lade alle verfügbaren Felder wie in getMetaInfoFields
            $standardFields = ['title', 'med_alt', 'med_copyright'];

            if ($this->fieldExists('med_title_lang')) {
                $standardFields[] = 'med_title_lang';
            }

            $optionalFields = ['med_description', 'med_keywords', 'med_source'];
            foreach ($optionalFields as $field) {
                if ($this->fieldExists($field)) {
                    $standardFields[] = $field;
                }
            }

            foreach ($standardFields as $fieldName) {
                $fieldInfo = $this->analyzeField($fieldName);
                $fieldValue = $media->getValue($fieldName);

                if ($fieldInfo['multilingual']) {
                    // Mehrsprachiges Feld - konvertiere von MetaInfo Lang Fields Format
                    $metadata[$fieldName] = $this->convertFromMetaInfoLangFormat($fieldValue);
                } else {
                    // Standard-Feld
                    $metadata[$fieldName] = $fieldValue;
                }
            }

            $this->sendResponse([
                'success' => true,
                'metadata' => $metadata,
            ]);
        } catch (Exception $e) {
            // Log the exception internally for debugging
            rex_logger::logException($e);

            $this->sendResponse([
                'success' => false,
                'error' => 'Ein Fehler ist beim Laden der Metadaten aufgetreten',
            ], 500);
        }
    }

    /**
     * Konvertiert MetaInfo Lang Fields Format ins Frontend-Format
     * MetaInfo: [{"clang_id": 1, "value": "Text"}, {"clang_id": 2, "value": "Text"}]
     * Frontend: {"de": "Text", "en": "Text"}.
     *
     * @return array<string, string>
     */
    private function convertFromMetaInfoLangFormat(mixed $jsonData): array
    {
        if (null === $jsonData || '' === $jsonData || [] === $jsonData) {
            return [];
        }

        // Verwende MetaInfo Lang Fields Helper wenn verfügbar
        if (class_exists('\FriendsOfRedaxo\MetaInfoLangFields\MetainfoLangHelper')) {
            $normalized = MetainfoLangHelper::normalizeLanguageData($jsonData);
        } else {
            // Fallback: JSON selbst dekodieren
            $data = is_string($jsonData) ? json_decode($jsonData, true) : $jsonData;
            $normalized = is_array($data) ? $data : [];
        }

        $result = [];
        $languages = rex_clang::getAll();

        foreach ($normalized as $item) {
            if (isset($item['clang_id']) && isset($item['value'])) {
                $clangId = (int) $item['clang_id'];
                if (isset($languages[$clangId])) {
                    $langCode = $languages[$clangId]->getCode();
                    $result[$langCode] = $item['value'];
                }
            }
        }

        return $result;
    }

    /**
     * Validate if a field name is allowed for MetaInfo updates.
     */
    private function isValidMetaInfoField(string $fieldName): bool
    {
        // Get all valid MetaInfo fields from database
        static $validFields = null;

        if (null === $validFields) {
            $validFields = [];
            $sql = rex_sql::factory();
            $sql->setQuery('SELECT name FROM rex_metainfo_field WHERE table_name = "rex_media"');
            while ($sql->hasNext()) {
                $validFields[] = 'med_' . (string) ($sql->getValue('name') ?? '');
                $sql->next();
            }
        }

        return in_array($fieldName, $validFields, true);
    }

    /**
     * Sanitize a metadata value (string or array).
     */
    private function sanitizeMetaInfoValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $k => $v) {
                // Recursively sanitize for nested arrays (e.g., multilingual fields)
                $sanitized[$k] = $this->sanitizeMetaInfoValue($v);
            }
            return $sanitized;
        }
        // Sanitize string: trim, remove dangerous chars but keep basic formatting
        $sanitized = trim((string) $value);
        // Remove potential script tags and other dangerous content
        $sanitized = (string) preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $sanitized);
        $sanitized = (string) preg_replace('/javascript:/i', '', $sanitized);
        $sanitized = preg_replace('/on\w+\s*=/i', '', $sanitized);
        return $sanitized;
    }
}
