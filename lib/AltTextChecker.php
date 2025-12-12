<?php

/**
 * Alt-Text-Checker - Findet Bilder ohne Alt-Text für Barrierefreiheit
 * 
 * @package filepond_uploader
 */
class filepond_alt_text_checker
{
    /**
     * Findet alle Bilder ohne Alt-Text
     * Dekorative Bilder (aus Negativ-Liste) werden ausgeschlossen
     */
    public static function findImagesWithoutAlt(array $filters = []): array
    {
        $where = ['filetype LIKE "image/%"'];
        
        // Alt-Text fehlt oder ist leer
        $where[] = '(med_alt IS NULL OR med_alt = "")';
        
        // Dekorative Bilder ausschließen
        $decorativeList = self::getDecorativeList();
        if (!empty($decorativeList)) {
            $escapedList = array_map(fn($f) => rex_sql::factory()->escape($f), $decorativeList);
            $where[] = 'filename NOT IN (' . implode(',', $escapedList) . ')';
        }
        
        // Zusätzliche Filter
        if (!empty($filters['filename'])) {
            $where[] = 'filename LIKE ' . rex_sql::factory()->escape('%' . $filters['filename'] . '%');
        }
        if (isset($filters['category_id']) && $filters['category_id'] >= 0) {
            $where[] = 'category_id = ' . intval($filters['category_id']);
        }
        
        $sql = rex_sql::factory();
        $sql->setQuery('
            SELECT id, filename, category_id, title, med_alt, med_description, createdate, createuser, width, height
            FROM ' . rex::getTable('media') . '
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY createdate DESC
        ');
        
        return $sql->getArray();
    }

    /**
     * Zählt Bilder mit und ohne Alt-Text
     * Dekorative Bilder (aus Negativ-Liste) zählen als "mit Alt-Text"
     */
    public static function getStatistics(): array
    {
        $sql = rex_sql::factory();
        
        // Gesamt Bilder
        $sql->setQuery('SELECT COUNT(*) as total FROM ' . rex::getTable('media') . ' WHERE filetype LIKE "image/%"');
        $total = (int)$sql->getValue('total');
        
        // Mit Alt-Text
        $sql->setQuery('SELECT COUNT(*) as with_alt FROM ' . rex::getTable('media') . ' WHERE filetype LIKE "image/%" AND med_alt IS NOT NULL AND med_alt != ""');
        $withAlt = (int)$sql->getValue('with_alt');
        
        // Dekorative Bilder zählen auch als "erledigt"
        $decorativeCount = count(self::getDecorativeList());
        $withAlt += $decorativeCount;
        
        // Ohne Alt-Text (minus dekorative)
        $withoutAlt = max(0, $total - $withAlt);
        
        // Prozent
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
     * 
     * @param string $filename Dateiname
     * @param string $altText Alt-Text
     */
    public static function updateAltText(string $filename, string $altText): array
    {
        try {
            $media = rex_media::get($filename);
            if (!$media) {
                return ['success' => false, 'error' => 'Medium nicht gefunden'];
            }
            
            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('media'));
            $sql->setWhere(['filename' => $filename]);
            $sql->setValue('med_alt', $altText);
            $sql->setValue('updatedate', date('Y-m-d H:i:s'));
            $sql->setValue('updateuser', rex::getUser() ? rex::getUser()->getLogin() : 'system');
            $sql->update();
            
            // Cache löschen
            rex_media_cache::delete($filename);
            
            return [
                'success' => true,
                'filename' => $filename,
                'alt_text' => $altText
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
            
            $result = self::updateAltText($update['filename'], $update['alt_text'] ?? '');
            
            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][$update['filename']] = $result['error'];
            }
        }
        
        return $results;
    }

    /**
     * Prüft ob das med_alt Feld existiert
     */
    public static function checkAltFieldExists(): bool
    {
        $sql = rex_sql::factory();
        try {
            $sql->setQuery('SHOW COLUMNS FROM ' . rex::getTable('media') . ' LIKE "med_alt"');
            return $sql->getRows() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Holt Kategorien mit Anzahl fehlender Alt-Texte
     */
    public static function getCategoriesWithMissingAlt(): array
    {
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
