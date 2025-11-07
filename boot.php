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
        
        rex_extension::register('OUTPUT_FILTER', function(rex_extension_point $ep) {
            $content = $ep->getSubject();
            
            // Only on mediapool pages or media-related pages
            if (strpos(rex_be_controller::getCurrentPage(), 'mediapool') !== false || 
                strpos(rex_be_controller::getCurrentPage(), 'media') !== false ||
                strpos($content, 'rex-page-mediapool') !== false) {
                $currentLang = rex_clang::getCurrentId();
                $noDescMsg = rex_i18n::msg('filepond_no_description');
                
                $script = "
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    console.log('FilePond: Multilingual formatter loaded');
                    
                    function formatMultilingualJson(jsonString) {
                        try {
                            const data = JSON.parse(jsonString);
                            if (!Array.isArray(data)) return jsonString;
                            
                            const currentLang = <?= json_encode($currentLang) ?>;
                            let descriptions = [];
                            
                            // Zeige aktuelle Sprache zuerst
                            for (let entry of data) {
                                if (entry.clang_id == currentLang && entry.value && entry.value.trim()) {
                                    return '<strong>' + getLangCode(entry.clang_id) + ':</strong> ' + entry.value.trim();
                                }
                            }
                            
                            // Dann alle anderen Sprachen
                            for (let entry of data) {
                                if (entry.value && entry.value.trim()) {
                                    let langCode = getLangCode(entry.clang_id);
                                    descriptions.push('<strong>' + langCode + ':</strong> ' + entry.value.trim());
                                }
                            }
                            
                            return descriptions.length > 0 ? descriptions.join(' &nbsp;&nbsp; ') : <?= json_encode($noDescMsg) ?>;
                        } catch (e) {
                            console.log('FilePond: JSON parse error:', e);
                            return jsonString;
                        }
                    }
                    
                    function getLangCode(clangId) {
                        // Map common language IDs to codes
                        const langMap = {
                            1: 'DE',
                            2: 'EN', 
                            3: 'FR',
                            4: 'IT',
                            5: 'ES'
                        };
                        return langMap[clangId] || 'L' + clangId;
                    }
                    
                    function processDescriptions() {
                        // Versuche verschiedene Selektoren
                        const selectors = [
                            'td p',           // Original
                            '.rex-table td p', // Mit Rex-Table-Klasse
                            'table td p',     // Allgemeine Tabelle
                            '.media-list td p', // Media-spezifisch
                            'td.rex-table-data-1 p', // Rex spezifische Spalte
                        ];
                        
                        let found = 0;
                        
                        for (const selector of selectors) {
                            const paragraphs = document.querySelectorAll(selector);
                            console.log('FilePond: Found', paragraphs.length, 'paragraphs with selector:', selector);
                            
                            paragraphs.forEach(function(p) {
                                const text = p.textContent.trim();
                                if (text.startsWith('[{\"clang_id') || text.startsWith('{\"clang_id')) {
                                    console.log('FilePond: Processing multilingual text:', text.substring(0, 50) + '...');
                                    const formatted = formatMultilingualJson(text);
                                    if (formatted !== text) {
                                        p.innerHTML = '';
                                        const tempDiv = document.createElement('div');
                                        tempDiv.innerHTML = formatted;
                                        while (tempDiv.firstChild) {
                                            p.appendChild(tempDiv.firstChild);
                                        }
                                        p.style.color = '#666';
                                        p.title = 'Mehrsprachige Beschreibung';
                                        found++;
                                        console.log('FilePond: Converted to:', formatted);
                                    }
                                }
                            });
                        }
                        
                        console.log('FilePond: Processed', found, 'multilingual descriptions');
                        return found;
                    }
                    
                    
                    // Initiale Verarbeitung
                    processDescriptions();
                    
                    // Überwache DOM-Änderungen für dynamisch geladene Inhalte
                    const observer = new MutationObserver(function(mutations) {
                        let shouldProcess = false;
                        mutations.forEach(function(mutation) {
                            if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                                shouldProcess = true;
                            }
                        });
                        
                        if (shouldProcess) {
                            setTimeout(() => processDescriptions(), 100);
                        }
                    });
                    
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                });
                </script>";
                
                $content = str_replace('</body>', $script . '</body>', $content);
            }
            
            return $content;
        });
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