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
rex_extension::register('PACKAGES_INCLUDED', function() {
    if (rex::isBackend() && rex_addon::exists('metainfo_lang_fields') && rex_addon::get('metainfo_lang_fields')->isAvailable()) {
        
        // Hook in OUTPUT_FILTER um die Beschreibungen zu formatieren
        rex_extension::register('OUTPUT_FILTER', function(rex_extension_point $ep) {
            $content = $ep->getSubject();
            
            // Null-Check für Content
            if (!$content) {
                return $content;
            }
            
            // Nur auf MediaPool Seiten
            $currentPage = rex_be_controller::getCurrentPage();
            if (!$currentPage || strpos($currentPage, 'mediapool') === false) {
                return $content;
            }
            
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
                        $jsonString = $match[1] ?? '';
                        
                        // Null/Empty-Check
                        if (!$jsonString) {
                            return $match[0];
                        }
                        
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
                                    if (isset($entry['clang_id']) && isset($entry['value']) && 
                                        $entry['clang_id'] == $currentLang && !empty($entry['value'])) {
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
            
            return $content;
        });
    }
});

// Register extension point specifically for MediaPool media list rendering
rex_extension::register('PAGES_PREPARED', function() {
    if (rex::isBackend() && rex_be_controller::getCurrentPage() === 'mediapool/media') {
        // Hook into MediaPool output rendering
        rex_extension::register('OUTPUT_FILTER', function(rex_extension_point $ep) {
            $content = $ep->getSubject();
            
            // Only process if we have table content and clang_id patterns
            if (strpos($content, '<table') !== false && strpos($content, 'clang_id') !== false) {
                // Pattern for JSON strings in the content
                $patterns = [
                    '/\[{"clang_id"[^\]]*}\]/',
                    '/\[{&quot;clang_id&quot;[^\]]*}\]/',
                ];
                
                foreach ($patterns as $pattern) {
                    if (preg_match_all($pattern, $content, $matches)) {
                        $content = preg_replace_callback($pattern, function($match) {
                            $jsonString = $match[0];
                            
                            // HTML-Entities dekodieren
                            $jsonString = html_entity_decode($jsonString);
                            
                            try {
                                $langData = json_decode($jsonString, true);
                                
                                if (is_array($langData)) {
                                    $currentLang = rex_clang::getCurrentId();
                                    $langCodes = [1 => 'DE', 2 => 'EN', 3 => 'FR', 4 => 'IT', 5 => 'ES'];
                                    
                                    // Suche aktuelle Sprache
                                    foreach ($langData as $entry) {
                                        if (isset($entry['clang_id']) && isset($entry['value']) && 
                                            $entry['clang_id'] == $currentLang && !empty($entry['value'])) {
                                            $langCode = $langCodes[$entry['clang_id']] ?? 'L' . $entry['clang_id'];
                                            return '<strong>' . $langCode . ':</strong> ' . htmlspecialchars($entry['value']);
                                        }
                                    }
                                    
                                    // Fallback: erste verfügbare Sprache
                                    foreach ($langData as $entry) {
                                        if (isset($entry['clang_id']) && isset($entry['value']) && !empty($entry['value'])) {
                                            $langCode = $langCodes[$entry['clang_id']] ?? 'L' . $entry['clang_id'];
                                            return '<strong>' . $langCode . ':</strong> ' . htmlspecialchars($entry['value']);
                                        }
                                    }
                                    
                                    // Keine Werte gefunden - leere JSON-Struktur
                                    return '<em>Keine Beschreibung</em>';
                                }
                            } catch (Exception $e) {
                                // Bei JSON-Fehlern Original beibehalten
                            }
                            
                            return $match[0];
                        }, $content);
                        
                        break; // Stop after first successful pattern
                    }
                }
            }
            
            return $content;
        }, rex_extension::LATE); // Use LATE priority to ensure it runs after other processing
    }
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
