<?php 
// Auszug aus yform_value_filepond.php, wo die Existierenden Dateien geladen werden

// Diese Methode im Objekt selbst:
public static function getListValue($params)
{
    $files = array_filter(explode(',', self::cleanValue($params['subject'])));
    $downloads = [];

    if (rex::isBackend()) {
        foreach ($files as $file) {
            if (!empty($file)) {
                $media = rex_media::get($file);
                if ($media) {
                    $fileName = $media->getFileName();
                    
                    if ($media->isImage()) {
                        $thumb = rex_media_manager::getUrl('rex_medialistbutton_preview', $fileName);
                        $downloads[] = sprintf(
                            '<div class="rex-yform-value-mediafile">
                                <a href="%s" title="%s" target="_blank">
                                    <img src="%s" alt="%s" style="max-width: 100px;">
                                    <span class="filename">%s</span>
                                </a>
                            </div>',
                            $media->getUrl(),
                            rex_escape($fileName),
                            $thumb,
                            rex_escape($fileName),
                            rex_escape($fileName)
                        );
                    } else {
                        $downloads[] = sprintf(
                            '<div class="rex-yform-value-mediafile">
                                <a href="%s" title="%s" target="_blank">
                                    <span class="filename">%s</span>
                                </a>
                            </div>',
                            $media->getUrl(),
                            rex_escape($fileName),
                            rex_escape($fileName)
                        );
                    }
                }
            }
        }
        
        if (!empty($downloads)) {
            return '<div class="rex-yform-value-mediafile-list">' . implode('', $downloads) . '</div>';
        }
    }

    return self::cleanValue($params['subject']);
}
