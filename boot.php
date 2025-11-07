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
            
            // Only on mediapool pages
            if (rex_be_controller::getCurrentPage() === 'mediapool/media') {
                $currentLang = rex_clang::getCurrentId();
                $noDescMsg = rex_i18n::msg('filepond_no_description');
                
                $script = "
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    function formatMultilingualJson(jsonString) {
                        try {
                            const data = JSON.parse(jsonString);
                            if (!Array.isArray(data)) return jsonString;
                            
                            const currentLang = {$currentLang};
                            let descriptions = [];
                            
                            for (let entry of data) {
                                if (entry.clang_id == currentLang && entry.value && entry.value.trim()) {
                                    return '<i class=\"fa fa-globe\"></i> <strong>' + getLangCode(entry.clang_id) + ':</strong> ' + entry.value.trim();
                                }
                            }
                            
                            for (let entry of data) {
                                if (entry.value && entry.value.trim()) {
                                    let langCode = getLangCode(entry.clang_id);
                                    descriptions.push('<i class=\"fa fa-globe\"></i> <strong>' + langCode + ':</strong> ' + entry.value.trim());
                                }
                            }
                            
                            return descriptions.length > 0 ? descriptions.join(' &nbsp;&nbsp; ') : '{$noDescMsg}';
                        } catch (e) {
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
                    
                    const descParagraphs = document.querySelectorAll('td p');
                    descParagraphs.forEach(function(p) {
                        const text = p.textContent.trim();
                        if (text.startsWith('[{\"clang_id') || text.startsWith('{\"clang_id')) {
                            const formatted = formatMultilingualJson(text);
                            if (formatted !== text) {
                                p.innerHTML = formatted;
                                p.style.fontStyle = 'italic';
                                p.style.color = '#666';
                                p.title = 'Mehrsprachige Beschreibung';
                            }
                        }
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