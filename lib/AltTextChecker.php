<?php

/**
 * Alt-Text-Checker - Findet Bilder ohne Alt-Text für Barrierefreiheit
 * Unterstützt auch mehrsprachige Metafelder (metainfo_lang_fields)
 * 
 * @package filepond_uploader
 */
class filepond_alt_text_checker
{
    /**
     * Prüft ob das med_alt Feld mehrsprachig konfiguriert ist
     */
    public static function isMultiLangField(): bool
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
                SELECT mf.type_id, mt.label as type_label 
                FROM ' . rex::getTable('metainfo_field') . ' mf 
                LEFT JOIN ' . rex::getTable('metainfo_type') . ' mt ON mf.type_id = mt.id 
                WHERE mf.name = ?
            ', ['med_alt']);
            
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
     * Prüft ob ein Alt-Text Wert vorhanden ist (auch bei JSON-Format)
     */
    public static function hasAltText(?string $value): bool
    {
        if (empty($value)) {
            return false;
        }
        
        // Prüfen ob es ein JSON-Array ist (mehrsprachig)
        if (str_starts_with(trim($value), '[')) {
            $langData = json_decode($value, true);
            if (is_array($langData)) {
                foreach ($langData as $entry) {
                    if (!empty($entry['value'])) {
                        return true;
                    }
                }
                return false;
            }
        }
        
        // Einfacher String
        return true;
    }
    
    /**
     * Extrahiert den Alt-Text für eine bestimmte Sprache
     */
    public static function getAltTextForLang(?string $value, ?int $clangId = null): string
    {
        if (empty($value)) {
            return '';
        }
        
        if ($clangId === null) {
            $clangId = rex_clang::getCurrentId();
        }
        
        // Prüfen ob es ein JSON-Array ist (mehrsprachig)
        if (str_starts_with(trim($value), '[')) {
            $langData = json_decode($value, true);
            if (is_array($langData)) {
                foreach ($langData as $entry) {
                    if (isset($entry['clang_id']) && $entry['clang_id'] == $clangId) {
                        return $entry['value'] ?? '';
                    }
                }
                // Fallback: erste verfügbare Sprache
                foreach ($langData as $entry) {
                    if (!empty($entry['value'])) {
                        return $entry['value'];
                    }
                }
                return '';
            }
        }
        
        // Einfacher String
        return $value;
    }

    /**
     * Findet alle Bilder ohne Alt-Text
     * Dekorative Bilder (aus Negativ-Liste) werden ausgeschlossen
     */
    public static function findImagesWithoutAlt(array $filters = [], int $limit = 0, int $offset = 0): array
    {
        if (!self::checkAltFieldExists()) {
            return [];
        }

        // Dekorative Bilder ausschließen
        $decorativeList = self::getDecorativeList();
        
        // Zusätzliche Filter
        $where = ['filetype LIKE "image/%"'];
        
        if (!empty($decorativeList)) {
            $escapedList = array_map(fn($f) => rex_sql::factory()->escape($f), $decorativeList);
            $where[] = 'filename NOT IN (' . implode(',', $escapedList) . ')';
        }
        
        if (!empty($filters['filename'])) {
            $where[] = 'filename LIKE ' . rex_sql::factory()->escape('%' . $filters['filename'] . '%');
        }
        if (isset($filters['category_id']) && $filters['category_id'] >= 0) {
            $where[] = 'category_id = ' . intval($filters['category_id']);
        }
        
        // Sortierung
        $sortConfig = rex_config::get('filepond_uploader', 'alt_checker_sort', 'createdate_desc');
        $orderBy = 'createdate DESC';
        
        switch ($sortConfig) {
            case 'createdate_asc':
                $orderBy = 'createdate ASC';
                break;
            case 'filename_asc':
                $orderBy = 'filename ASC';
                break;
            case 'filename_desc':
                $orderBy = 'filename DESC';
                break;
            case 'createdate_desc':
            default:
                $orderBy = 'createdate DESC';
                break;
        }
        
        $sql = rex_sql::factory();
        $sql->setQuery('
            SELECT id, filename, category_id, title, med_alt, med_description, createdate, createuser, width, height
            FROM ' . rex::getTable('media') . '
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY ' . $orderBy . '
        ');
        
        $allImages = $sql->getArray();
        
        // Filter: Nur Bilder ohne Alt-Text (berücksichtigt JSON-Format)
        $imagesWithoutAlt = [];
        foreach ($allImages as $image) {
            if (!self::hasAltText($image['med_alt'])) {
                $imagesWithoutAlt[] = $image;
            }
        }
        
        // Pagination
        if ($limit > 0) {
            return array_slice($imagesWithoutAlt, $offset, $limit);
        }
        
        return $imagesWithoutAlt;
    }

    /**
     * Zählt alle Bilder ohne Alt-Text
     */
    public static function countImagesWithoutAlt(array $filters = []): int
    {
        return count(self::findImagesWithoutAlt($filters));
    }

    /**
     * Zählt Bilder mit und ohne Alt-Text
     * Dekorative Bilder (aus Negativ-Liste) zählen als "mit Alt-Text"
     */
    public static function getStatistics(): array
    {
        if (!self::checkAltFieldExists()) {
            return [
                'total' => 0,
                'with_alt' => 0,
                'without_alt' => 0,
                'decorative' => 0,
                'percent_complete' => 100
            ];
        }

        $sql = rex_sql::factory();
        
        // Alle Bilder laden und prüfen (wegen JSON-Format)
        $sql->setQuery('SELECT filename, med_alt FROM ' . rex::getTable('media') . ' WHERE filetype LIKE "image/%"');
        
        $total = 0;
        $withAlt = 0;
        $decorativeList = self::getDecorativeList();
        
        foreach ($sql as $row) {
            $total++;
            $filename = $row->getValue('filename');
            
            // Dekorative Bilder zählen als "mit Alt-Text"
            if (in_array($filename, $decorativeList)) {
                $withAlt++;
                continue;
            }
            
            // Prüfen ob Alt-Text vorhanden (auch JSON)
            if (self::hasAltText($row->getValue('med_alt'))) {
                $withAlt++;
            }
        }
        
        $withoutAlt = $total - $withAlt;
        $decorativeCount = count($decorativeList);
        $percentComplete = $total > 0 ? round(($withAlt / $total) * 100, 1) : 100;
        
        return [
            'total' => $total,
            'with_alt' => $withAlt,
            'without_alt' => $withoutAlt,
            'decorative' => $decorativeCount,
            'percent_complete' => min(100, $percentComplete)
        ];
    }

    /**
     * Aktualisiert den Alt-Text eines Bildes
     * Unterstützt mehrsprachiges Format wenn metainfo_lang_fields aktiv ist
     * 
     * @param string $filename Dateiname
     * @param string|array $altText Alt-Text (String oder Array mit clang_id => value)
     */
    public static function updateAltText(string $filename, string|array $altText): array
    {
        if (!self::checkAltFieldExists()) {
            return ['success' => false, 'error' => 'Feld med_alt existiert nicht'];
        }

        try {
            $media = rex_media::get($filename);
            if (!$media) {
                return ['success' => false, 'error' => 'Medium nicht gefunden'];
            }
            
            $valueToSave = $altText;
            
            // Wenn mehrsprachig aktiv und ein Array übergeben wurde
            if (is_array($altText)) {
                $langData = [];
                foreach ($altText as $clangId => $value) {
                    $langData[] = [
                        'clang_id' => (int)$clangId,
                        'value' => $value
                    ];
                }
                $valueToSave = json_encode($langData, JSON_UNESCAPED_UNICODE);
            }
            // Wenn mehrsprachig aktiv und ein String übergeben wurde, in aktuelle Sprache speichern
            elseif (self::isMultiLangField() && is_string($altText)) {
                // Bestehenden Wert laden und updaten
                $sql = rex_sql::factory();
                $sql->setQuery('SELECT med_alt FROM ' . rex::getTable('media') . ' WHERE filename = ?', [$filename]);
                $currentValue = $sql->getValue('med_alt');
                
                $langData = [];
                if ($currentValue && str_starts_with(trim($currentValue), '[')) {
                    $langData = json_decode($currentValue, true) ?: [];
                }
                
                // Aktuelle Sprache updaten oder hinzufügen
                $currentClangId = rex_clang::getCurrentId();
                $found = false;
                foreach ($langData as &$entry) {
                    if (isset($entry['clang_id']) && $entry['clang_id'] == $currentClangId) {
                        $entry['value'] = $altText;
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $langData[] = [
                        'clang_id' => $currentClangId,
                        'value' => $altText
                    ];
                }
                
                $valueToSave = json_encode($langData, JSON_UNESCAPED_UNICODE);
            }
            
            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('media'));
            $sql->setWhere(['filename' => $filename]);
            $sql->setValue('med_alt', $valueToSave);
            $sql->update();
            
            // Cache löschen
            rex_media_cache::delete($filename);
            
            return [
                'success' => true,
                'filename' => $filename,
                'alt_text' => $valueToSave
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Markiert ein Bild als dekorativ (Negativ-Liste)
     */
    public static function markAsDecorative(string $filename): array
    {
        try {
            $media = rex_media::get($filename);
            if (!$media) {
                return ['success' => false, 'error' => 'Medium nicht gefunden'];
            }
            
            $decorativeList = self::getDecorativeList();
            if (!in_array($filename, $decorativeList)) {
                $decorativeList[] = $filename;
                rex_config::set('filepond_uploader', 'decorative_images', json_encode($decorativeList));
            }
            
            return [
                'success' => true,
                'filename' => $filename,
                'decorative' => true
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Entfernt ein Bild aus der Dekorativ-Liste
     */
    public static function unmarkDecorative(string $filename): array
    {
        try {
            $decorativeList = self::getDecorativeList();
            $decorativeList = array_filter($decorativeList, fn($f) => $f !== $filename);
            rex_config::set('filepond_uploader', 'decorative_images', json_encode(array_values($decorativeList)));
            
            return [
                'success' => true,
                'filename' => $filename,
                'decorative' => false
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Prüft ob ein Bild als dekorativ markiert ist
     */
    public static function isDecorative(string $filename): bool
    {
        return in_array($filename, self::getDecorativeList());
    }
    
    /**
     * Holt die Liste der dekorativen Bilder
     */
    public static function getDecorativeList(): array
    {
        $json = rex_config::get('filepond_uploader', 'decorative_images', '[]');
        $list = json_decode($json, true);
        return is_array($list) ? $list : [];
    }

    /**
     * Bulk-Update für mehrere Bilder
     */
    public static function bulkUpdateAltText(array $updates): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        foreach ($updates as $update) {
            if (empty($update['filename'])) continue;
            
            // Mehrsprachige Updates
            if (isset($update['lang_texts']) && is_array($update['lang_texts'])) {
                // Konvertiere zu dem Format das updateAltText erwartet: [clang_id => value]
                $result = self::updateAltText($update['filename'], $update['lang_texts']);
                
                if ($result['success']) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                    $results['errors'][$update['filename']] = $result['error'];
                }
            } else {
                // Einsprachiges Update
                $result = self::updateAltText($update['filename'], $update['alt_text'] ?? '');
                
                if ($result['success']) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                    $results['errors'][$update['filename']] = $result['error'];
                }
            }
        }
        
        return $results;
    }

    private static $altFieldExists = null;

    /**
     * Prüft ob das med_alt Feld existiert
     */
    public static function checkAltFieldExists(): bool
    {
        if (self::$altFieldExists !== null) {
            return self::$altFieldExists;
        }

        $sql = rex_sql::factory();
        try {
            $sql->setQuery('SHOW COLUMNS FROM ' . rex::getTable('media') . ' LIKE "med_alt"');
            self::$altFieldExists = $sql->getRows() > 0;
        } catch (Exception $e) {
            self::$altFieldExists = false;
        }
        
        return self::$altFieldExists;
    }

    /**
     * Holt Kategorien mit Anzahl fehlender Alt-Texte
     */
    public static function getCategoriesWithMissingAlt(): array
    {
        if (!self::checkAltFieldExists()) {
            return [];
        }

        $sql = rex_sql::factory();
        $sql->setQuery('
            SELECT 
                m.category_id,
                COALESCE(c.name, "Keine Kategorie") as category_name,
                COUNT(*) as missing_count
            FROM ' . rex::getTable('media') . ' m
            LEFT JOIN ' . rex::getTable('media_category') . ' c ON m.category_id = c.id
            WHERE m.filetype LIKE "image/%" 
              AND (m.med_alt IS NULL OR m.med_alt = "")
            GROUP BY m.category_id, c.name
            ORDER BY missing_count DESC
        ');
        
        return $sql->getArray();
    }
}
