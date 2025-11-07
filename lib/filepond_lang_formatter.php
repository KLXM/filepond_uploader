<?php

/**
 * Helper class for formatting multilingual MetaInfo fields
 */
class filepond_lang_formatter
{
    /**
     * Format multilingual field data for display
     * 
     * @param array $langData
     * @return string
     */
    public static function formatMultilingualField($langData)
    {
        if (!is_array($langData)) {
            return '';
        }
        
        $currentLang = rex_clang::getCurrentId();
        $descriptions = [];
        
        // First try to get current language
        foreach ($langData as $entry) {
            if (isset($entry['clang_id'], $entry['value']) && 
                $entry['clang_id'] == $currentLang && 
                !empty(trim($entry['value']))) {
                return strip_tags(trim($entry['value']));
            }
        }
        
        // Fallback: collect all non-empty descriptions
        foreach ($langData as $entry) {
            if (isset($entry['clang_id'], $entry['value']) && !empty(trim($entry['value']))) {
                $clang = rex_clang::get($entry['clang_id']);
                $langName = $clang ? $clang->getName() : 'ID' . $entry['clang_id'];
                $descriptions[] = $langName . ': ' . strip_tags(trim($entry['value']));
            }
        }
        
        if (empty($descriptions)) {
            return rex_i18n::msg('filepond_no_description');
        }
        
        return implode(' | ', $descriptions);
    }
    
    /**
     * Check if a string looks like MetaInfo Lang Fields JSON
     * 
     * @param string $value
     * @return bool
     */
    public static function isMultilingualJson($value)
    {
        if (!is_string($value) || empty($value)) {
            return false;
        }
        
        // Quick check for JSON structure
        if (!str_starts_with($value, '[{') && !str_starts_with($value, '{')) {
            return false;
        }
        
        try {
            $data = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                return false;
            }
            
            // Check if it has the MetaInfo Lang Fields structure
            foreach ($data as $entry) {
                if (is_array($entry) && isset($entry['clang_id'], $entry['value'])) {
                    return true;
                }
            }
        } catch (Exception $e) {
            return false;
        }
        
        return false;
    }
}