<?php

/**
 * Automatische MetaInfo-Feld-Erkennung für FilePond
 * Pragmatischer Ansatz: Vollautomatische Erkennung aller relevanten Felder
 */
class rex_api_filepond_auto_metainfo extends rex_api_function
{
    protected $published = true;
    
    /**
     * Zentrale Methode für das Senden von JSON-Antworten
     */
    protected function sendResponse($data, $statusCode = 200)
    {
        rex_response::cleanOutputBuffers();
        if ($statusCode !== 200) {
            rex_response::setStatus($statusCode);
        }
        rex_response::sendJson($data);
        exit;
    }
    
    public function execute()
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
                    'error' => 'Unbekannte Aktion'
                ], 400);
        }
    }
    
    /**
     * Automatische Erkennung aller relevanten MetaInfo-Felder
     * Lädt dynamisch alle Felder, die mit "med_" beginnen, filtert aber ausgeblendete Felder.
     */
    private function getMetaInfoFields()
    {
        try {
            $fields = [];
            
            // Konfigurierte Blacklist laden
            $excludedFields = rex_config::get('filepond_uploader', 'excluded_metadata_fields', []);
            if (!is_array($excludedFields)) {
                // rex_config_form speichert Arrays oft als pipe-separierten String (|value|value|)
                if (is_string($excludedFields) && strpos($excludedFields, '|') !== false) {
                     $excludedFields = array_filter(explode('|', $excludedFields));
                } elseif (is_string($excludedFields)) {
                     // Fallback, falls CSV
                     $excludedFields = explode(',', $excludedFields);
                } else {
                     $excludedFields = [];
                }
            }
            
            // 1. Titel ist immer dabei (REDAXO Standard)
            // Nur hinzufügen wenn nicht ausgeschlossen
            if (!in_array('title', $excludedFields)) {
                $fields[] = $this->analyzeField('title');
            }
            
            // 2. Prüfen ob MetaInfo Addon verfügbar ist
            $hasMetaInfo = rex_addon::exists('metainfo') && rex_addon::get('metainfo')->isAvailable();

            if (!$hasMetaInfo) {
                // Fallback ohne MetaInfo: Nur die absoluten Standardfelder annehmen
                $defaults = ['med_alt', 'med_copyright', 'med_description'];
                foreach ($defaults as $f) {
                    if (!in_array($f, $excludedFields)) {
                        $fields[] = $this->analyzeField($f);
                    }
                }
            } else {
                // 3. Dynamisch ALLE med_ Felder aus MetaInfo laden
                $sql = rex_sql::factory();
                // Wir holen alle Felder, die mit med_ beginnen, sortiert nach Priorität
                $sql->setQuery('SELECT name FROM ' . rex::getTable('metainfo_field') . ' WHERE name LIKE "med_%" ORDER BY priority');
                
                foreach ($sql as $row) {
                    $name = $row->getValue('name');
                    if (!in_array($name, $excludedFields)) {
                        $fields[] = $this->analyzeField($name);
                    }
                }
            }

            // Duplikate entfernen
            $uniqueFields = [];
            $seenNames = [];
            foreach ($fields as $field) {
                if (!in_array($field['name'], $seenNames)) {
                    $seenNames[] = $field['name'];
                    $uniqueFields[] = $field;
                }
            }
            
            $this->sendResponse([
                'success' => true,
                'fields' => $uniqueFields
            ]);
            
        } catch (Exception $e) {
            rex_logger::logException($e);
            
            $this->sendResponse([
                'success' => false,
                'error' => 'Ein Fehler ist beim Laden der Metafelder aufgetreten'
            ], 500);
        }
    }
    
    /**
     * Prüft ob ein Feld existiert (in Standard-Tabelle oder MetaInfo)
     */
    private function fieldExists($fieldName)
    {
        // Standard-Felder existieren immer
        if (in_array($fieldName, ['title', 'med_alt', 'med_copyright'])) {
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
     * Prüft ob ein Feld ein Pflichtfeld ist
     */
    private function isFieldRequired($fieldName)
    {
        // 1. Prüfe alten Schalter für Titel
        if ($fieldName === 'title' && rex_config::get('filepond_uploader', 'title_required_default', 0)) {
            return true;
        }

        // 2. Prüfe neue kommaseparierte Liste
        $requiredFields = rex_config::get('filepond_uploader', 'required_metadata_fields', '');
        if (empty($requiredFields)) {
            return false;
        }

        $fields = array_map('trim', explode(',', $requiredFields));
        return in_array($fieldName, $fields);
    }

    /**
     * Analysiert ein Feld auf Typ und Mehrsprachigkeit
     */
    private function analyzeField($fieldName)
    {
        // Standard-Feld-Informationen
        $fieldInfo = [
            'name' => $fieldName,
            'label' => $this->getFieldLabel($fieldName),
            'type' => $this->getFieldType($fieldName),
            'multilingual' => $this->isMultilingual($fieldName),
            'required' => $this->isFieldRequired($fieldName),
            'languages' => []
        ];
        
        // Wenn mehrsprachig, alle verfügbaren Sprachen laden
        if ($fieldInfo['multilingual']) {
            $fieldInfo['languages'] = $this->getAvailableLanguages();
        }
        
        return $fieldInfo;
    }
    
    /**
     * Ermittelt das Label für ein Feld
     */
    private function getFieldLabel($fieldName)
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
                    $customLabel = $sql->getValue('title');
                    if (!empty($customLabel)) {
                        $label = $customLabel;
                    }
                }
            } catch (Exception $e) {
                // Ignore
            }
        }
        
        // 3. Translate if needed (rex_i18n)
        if (strpos($label, 'translate:') === 0) {
            $key = substr($label, 10);
            return rex_i18n::msg($key);
        }
        
        return $label;
    }
    
    /**
     * Ermittelt den Feldtyp
     */
    private function getFieldType($fieldName)
    {
        // 1. Hardcoded Standard-Types
        // Title ist speziell und fix
        if ($fieldName === 'title') {
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
                    $typeLabel = $sql->getValue('type_label');
                    
                    // 1=Text, 2=Textarea (Standard MetaInfo)
                    if ($typeId === 2) {
                        return 'textarea';
                    }
                    
                    // Check for specialized types (like lang_textarea_all)
                    if (!empty($typeLabel) && strpos($typeLabel, 'textarea') !== false) {
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
        if ($fieldName === 'med_description') {
            return 'textarea';
        }
        
        return 'text';
    }
    
    /**
     * Prüft ob ein Feld mehrsprachig konfiguriert ist
     */
    private function isMultilingual($fieldName)
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
                $typeLabel = $sql->getValue('type_label');
                
                // Prüfe ob es ein mehrsprachiger Feldtyp ist
                $multilingualTypes = ['lang_text', 'lang_textarea', 'lang_text_all', 'lang_textarea_all'];
                return in_array($typeLabel, $multilingualTypes);
            }
            
            return false;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Lädt alle verfügbaren Sprachen
     */
    private function getAvailableLanguages()
    {
        $languages = [];
        foreach (rex_clang::getAll() as $clang) {
            $languages[] = [
                'code' => $clang->getCode(),
                'name' => $clang->getName(),
                'id' => $clang->getId()
            ];
        }
        return $languages;
    }
    
    /**
     * Speichert Metadaten für eine Datei
     */
    private function saveMetadata()
    {
        try {
            $fileId = rex_request('file_id', 'string');
            $metadata = rex_request('metadata', 'array');
            
            // Input validation
            if (!$fileId) {
                throw new Exception('Keine Datei-ID angegeben');
            }
            
            // Validate file_id format (filename pattern)
            if (!preg_match('/^[a-zA-Z0-9._-]+$/', $fileId)) {
                throw new Exception('Ungültige Datei-ID');
            }
            
            if (!is_array($metadata) || empty($metadata)) {
                throw new Exception('Ungültige Metadaten');
            }
            
            // Prüfe ob Datei existiert
            $media = rex_media::get($fileId);
            if (!$media) {
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
                'message' => 'Metadaten erfolgreich gespeichert'
            ]);
            
        } catch (Exception $e) {
            // Log the exception internally for debugging
            rex_logger::logException($e);
            
            $this->sendResponse([
                'success' => false,
                'error' => 'Ein Fehler ist beim Speichern der Metadaten aufgetreten'
            ], 500);
        }
    }
    
    /**
     * Konvertiert Frontend-Daten ins MetaInfo Lang Fields Format
     * Frontend: {"de": "Text", "en": "Text"} 
     * MetaInfo: [{"clang_id": 1, "value": "Text"}, {"clang_id": 2, "value": "Text"}]
     */
    private function convertToMetaInfoLangFormat($fieldValue)
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
                        'value' => (string) $value
                    ];
                    break;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Lädt bestehende Metadaten für eine Datei
     */
    private function loadMetadata()
    {
        try {
            $fileId = rex_request('file_id', 'string');
            
            // Input validation
            if (!$fileId) {
                throw new Exception('Keine Datei-ID angegeben');
            }
            
            // Validate file_id format (filename pattern)
            if (!preg_match('/^[a-zA-Z0-9._-]+$/', $fileId)) {
                throw new Exception('Ungültige Datei-ID');
            }
            
            $media = rex_media::get($fileId);
            if (!$media) {
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
                if ($fieldInfo) {
                    $fieldValue = $media->getValue($fieldName);
                    
                    if ($fieldInfo['multilingual']) {
                        // Mehrsprachiges Feld - konvertiere von MetaInfo Lang Fields Format
                        $metadata[$fieldName] = $this->convertFromMetaInfoLangFormat($fieldValue);
                    } else {
                        // Standard-Feld
                        $metadata[$fieldName] = $fieldValue;
                    }
                }
            }
            
            $this->sendResponse([
                'success' => true,
                'metadata' => $metadata
            ]);
            
        } catch (Exception $e) {
            // Log the exception internally for debugging
            rex_logger::logException($e);
            
            $this->sendResponse([
                'success' => false,
                'error' => 'Ein Fehler ist beim Laden der Metadaten aufgetreten'
            ], 500);
        }
    }
    
    /**
     * Konvertiert MetaInfo Lang Fields Format ins Frontend-Format
     * MetaInfo: [{"clang_id": 1, "value": "Text"}, {"clang_id": 2, "value": "Text"}]
     * Frontend: {"de": "Text", "en": "Text"}
     */
    private function convertFromMetaInfoLangFormat($jsonData)
    {
        if (empty($jsonData)) {
            return [];
        }
        
        // Verwende MetaInfo Lang Fields Helper wenn verfügbar
        if (class_exists('\FriendsOfRedaxo\MetaInfoLangFields\MetainfoLangHelper')) {
            $normalized = \FriendsOfRedaxo\MetaInfoLangFields\MetainfoLangHelper::normalizeLanguageData($jsonData);
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
     * Validate if a field name is allowed for MetaInfo updates
     */
    private function isValidMetaInfoField($fieldName)
    {
        // Get all valid MetaInfo fields from database
        static $validFields = null;
        
        if ($validFields === null) {
            $validFields = [];
            $sql = rex_sql::factory();
            $sql->setQuery('SELECT name FROM rex_metainfo_field WHERE table_name = "rex_media"');
            while ($sql->hasNext()) {
                $validFields[] = 'med_' . $sql->getValue('name');
                $sql->next();
            }
        }
        
        return in_array($fieldName, $validFields, true);
    }

    /**
     * Sanitize a metadata value (string or array)
     */
    private function sanitizeMetaInfoValue($value)
    {
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $k => $v) {
                // Recursively sanitize for nested arrays (e.g., multilingual fields)
                $sanitized[$k] = $this->sanitizeMetaInfoValue($v);
            }
            return $sanitized;
        } else {
            // Sanitize string: trim, remove dangerous chars but keep basic formatting
            $sanitized = trim((string)$value);
            // Remove potential script tags and other dangerous content
            $sanitized = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $sanitized);
            $sanitized = preg_replace('/javascript:/i', '', $sanitized);
            $sanitized = preg_replace('/on\w+\s*=/i', '', $sanitized);
            return $sanitized;
        }
    }
}