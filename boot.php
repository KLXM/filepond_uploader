<?php

rex_yform::addTemplatePath($this->getPath('ytemplates'));

if (rex::isBackend() && rex::getUser()) {
    // Einbindung über Static-Properties sicherstellen
    static $filepondScriptsLoaded = false;
    
    if (!$filepondScriptsLoaded) {
        filepond_helper::getStyles();
        filepond_helper::getScripts();
        $filepondScriptsLoaded = true;
    }
}

// Register extension point to format MetaInfo Lang Fields in media pool
if (rex::isBackend() && rex_addon::exists('metainfo_lang_fields') && rex_addon::get('metainfo_lang_fields')->isAvailable()) {
    
    // Hook in OUTPUT_FILTER um die Beschreibungen zu formatieren
    rex_extension::register('OUTPUT_FILTER', function(rex_extension_point $ep) {
        $content = $ep->getSubject();
        
        // Nur auf MediaPool Seiten
        if (strpos(rex_be_controller::getCurrentPage(), 'mediapool') !== false) {
            
            // Verschiedene Patterns für escaped/unescaped JSON
            $patterns = [
                '/<p>\[(\{&quot;clang_id&quot;[^<]+)\]<\/p>/', // HTML-escaped
                '/<p>\[(\{"clang_id"[^<]+)\]<\/p>/', // Nicht escaped
                '/<p>(\[.*?clang_id.*?\])<\/p>/', // Allgemeiner
                '/<p>([^<]*clang_id[^<]*)<\/p>/' // Noch allgemeiner
            ];
            
            foreach ($patterns as $pattern) {
                $matches = [];
                if (preg_match_all($pattern, $content, $matches)) {
                    // Verwende das erste funktionierende Pattern
                    $content = preg_replace_callback($pattern, function($match) {
                        $jsonString = $match[1];
                        
                        // HTML-Entities dekodieren falls nötig
                        if (strpos($jsonString, '&quot;') !== false) {
                            $jsonString = html_entity_decode($jsonString);
                        }
                        
                        // Sicherstellen dass es mit [ beginnt
                        if (!str_starts_with($jsonString, '[')) {
                            $jsonString = '[' . $jsonString . ']';
                        }
                        
                        try {
                            $langData = json_decode($jsonString, true);
                            if (is_array($langData)) {
                                $currentLang = rex_clang::getCurrentId();
                                $langCodes = [1 => 'DE', 2 => 'EN', 3 => 'FR', 4 => 'IT', 5 => 'ES'];
                                
                                // Suche aktuelle Sprache
                                foreach ($langData as $entry) {
                                    if (isset($entry['clang_id']) && isset($entry['value']) && $entry['clang_id'] == $currentLang && !empty($entry['value'])) {
                                        $langCode = $langCodes[$entry['clang_id']] ?? 'L' . $entry['clang_id'];
                                        return '<p><strong>' . $langCode . ':</strong> ' . htmlspecialchars($entry['value']) . '</p>';
                                    }
                                }
                                
                                // Fallback: erste verfügbare Sprache
                                foreach ($langData as $entry) {
                                    if (isset($entry['clang_id']) && isset($entry['value']) && !empty($entry['value'])) {
                                        $langCode = $langCodes[$entry['clang_id']] ?? 'L' . $entry['clang_id'];
                                        return '<p><strong>' . $langCode . ':</strong> ' . htmlspecialchars($entry['value']) . '</p>';
                                    }
                                }
                            }
                        } catch (Exception $e) {
                            // Bei Fehlern das Original zurückgeben
                        }
                        return $match[0];
                    }, $content);
                    
                    break; // Verwende nur das erste funktionierende Pattern
                }
            }
        }
        
        return $content;
    });
}

// Register general OUTPUT_FILTER for med_description formatting (works without metainfo_lang_fields addon)
rex_extension::register('OUTPUT_FILTER', function(rex_extension_point $ep) {
    $content = $ep->getSubject();
    
    // Nur auf MediaPool Seiten
    if (strpos(rex_be_controller::getCurrentPage(), 'mediapool') !== false) {
        
        // Pattern für med_description JSON-Formatierung in Table Cells
        $pattern = '/<td[^>]*>\s*<p>\s*(\[.*?"clang_id".*?\])\s*<\/p>\s*<\/td>/';
        
        if (preg_match_all($pattern, $content, $matches)) {
            $content = preg_replace_callback($pattern, function($match) {
                $jsonString = $match[1];
                
                // HTML-Entities dekodieren falls nötig
                if (strpos($jsonString, '&quot;') !== false) {
                    $jsonString = html_entity_decode($jsonString);
                }
                
                try {
                    $langData = json_decode($jsonString, true);
                    if (is_array($langData)) {
                        $currentLang = rex_clang::getCurrentId();
                        $langCodes = [1 => 'DE', 2 => 'EN', 3 => 'FR', 4 => 'IT', 5 => 'ES'];
                        
                        // Suche aktuelle Sprache
                        foreach ($langData as $entry) {
                            if (isset($entry['clang_id']) && isset($entry['value']) && $entry['clang_id'] == $currentLang && !empty($entry['value'])) {
                                $langCode = $langCodes[$entry['clang_id']] ?? 'L' . $entry['clang_id'];
                                $formatted = '<p style="color: #666; font-style: italic;"><strong>' . $langCode . ':</strong> ' . htmlspecialchars($entry['value']) . '</p>';
                                return str_replace($match[1], $formatted, $match[0]);
                            }
                        }
                        
                        // Fallback: erste verfügbare Sprache
                        foreach ($langData as $entry) {
                            if (isset($entry['clang_id']) && isset($entry['value']) && !empty($entry['value'])) {
                                $langCode = $langCodes[$entry['clang_id']] ?? 'L' . $entry['clang_id'];
                                $formatted = '<p style="color: #666; font-style: italic;"><strong>' . $langCode . ':</strong> ' . htmlspecialchars($entry['value']) . '</p>';
                                return str_replace($match[1], $formatted, $match[0]);
                            }
                        }
                        
                        // Keine Werte gefunden
                        $noDesc = '<em style="color: #999;">Keine Beschreibung</em>';
                        return str_replace($match[1], $noDesc, $match[0]);
                    }
                } catch (Exception $e) {
                    // Bei Fehlern das Original zurückgeben
                }
                return $match[0];
            }, $content);
        }
    }
    
    return $content;
});

if(rex_config::get('filepond_uploader', 'replace_mediapool', false))
{    
    rex_extension::register('PAGES_PREPARED', function (rex_extension_point $ep) {
        $pages = $ep->getSubject();
        
        if (isset($pages['mediapool'])) {
            $mediapoolPage = $pages['mediapool'];
            if ($uploadPage = $mediapoolPage->getSubpage('upload')) {
                // Nur das subPath ändern, der Rest bleibt gleich
                $uploadPage->setSubPath(
                    rex_path::addon('filepond_uploader', 'pages/upload.php')
                );
            }
        }
    });
}

