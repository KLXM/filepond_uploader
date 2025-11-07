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
     */
    private function getMetaInfoFields()
    {
        try {
            $fields = [];
            
            // Standard-Felder für Medien
            $standardFields = ['title', 'med_alt', 'med_copyright'];
            
            // Prüfe auch auf med_title_lang
            if ($this->fieldExists('med_title_lang')) {
                $standardFields[] = 'med_title_lang';
            }
            
            // Weitere häufige Felder
            $optionalFields = ['med_description', 'med_keywords', 'med_source'];
            
            foreach ($optionalFields as $field) {
                if ($this->fieldExists($field)) {
                    $standardFields[] = $field;
                }
            }
            
            // Für jedes Feld prüfen ob es existiert und mehrsprachig ist
            foreach ($standardFields as $fieldName) {
                $fieldInfo = $this->analyzeField($fieldName);
                if ($fieldInfo) {
                    $fields[] = $fieldInfo;
                }
            }
            
            $this->sendResponse([
                'success' => true,
                'fields' => $fields
            ]);
            
        } catch (Exception $e) {
            $this->sendResponse([
                'success' => false,
                'error' => $e->getMessage()
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
        if (rex_addon::get('metainfo')->isAvailable()) {
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
            'required' => false, // Required wird nur auf Upload-Seite über PHP gesteuert
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
        $labels = [
            'title' => 'Titel',
            'med_alt' => 'Alt-Text',
            'med_copyright' => 'Copyright',
            'med_description' => 'Beschreibung',
            'med_title_lang' => 'Mehrsprachiger Titel',
            'med_keywords' => 'Schlüsselwörter',
            'med_source' => 'Quelle'
        ];
        
        // Prüfe auch in MetaInfo für custom Labels
        if (rex_addon::get('metainfo')->isAvailable()) {
            try {
                $sql = rex_sql::factory();
                $sql->setQuery('SELECT title FROM rex_metainfo_field WHERE name = ?', [$fieldName]);
                if ($sql->getRows() > 0) {
                    $customLabel = $sql->getValue('title');
                    if (!empty($customLabel)) {
                        return $customLabel;
                    }
                }
            } catch (Exception $e) {
                // Fallback zu Standard-Label
            }
        }
        
        return $labels[$fieldName] ?? ucfirst($fieldName);
    }
    
    /**
     * Ermittelt den Feldtyp
     */
    private function getFieldType($fieldName)
    {
        // Standard-Typen
        $types = [
            'title' => 'text',
            'med_alt' => 'text',
            'med_copyright' => 'text',
            'med_title_lang' => 'text',
            'med_description' => 'textarea',
            'med_keywords' => 'text',
            'med_source' => 'text'
        ];
        
        return $types[$fieldName] ?? 'text';
    }
    
    /**
     * Prüft ob ein Feld mehrsprachig konfiguriert ist
     */
    private function isMultilingual($fieldName)
    {
        // Prüfe MetaInfo Lang Fields AddOn
        if (!rex_addon::get('metainfo_lang_fields')->isAvailable()) {
            return false;
        }
        
        // Prüfe ob MetaInfo AddOn verfügbar ist
        if (!rex_addon::get('metainfo')->isAvailable()) {
            return false;
        }
        
        try {
            // Prüfe den Feldtyp in der MetaInfo-Konfiguration
            $sql = rex_sql::factory();
            $sql->setQuery('
                SELECT mf.type_id, mt.label as type_label 
                FROM rex_metainfo_field mf 
                LEFT JOIN rex_metainfo_type mt ON mf.type_id = mt.id 
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
            
            if (!$fileId) {
                throw new Exception('Keine Datei-ID angegeben');
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
            
            // Verarbeite jedes Feld
            foreach ($metadata as $fieldName => $fieldValue) {
                if ($this->isMultilingual($fieldName)) {
                    // Mehrsprachiges Feld - konvertiere zu MetaInfo Lang Fields Format
                    $langData = $this->convertToMetaInfoLangFormat($fieldValue);
                    $sql->setValue($fieldName, json_encode($langData));
                } else {
                    // Standard-Feld
                    $sql->setValue($fieldName, $fieldValue);
                }
            }
            
            $sql->update();
            
            $this->sendResponse([
                'success' => true,
                'message' => 'Metadaten erfolgreich gespeichert'
            ]);
            
        } catch (Exception $e) {
            $this->sendResponse([
                'success' => false,
                'error' => $e->getMessage()
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
            
            if (!$fileId) {
                throw new Exception('Keine Datei-ID angegeben');
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
            $this->sendResponse([
                'success' => false,
                'error' => $e->getMessage()
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
}