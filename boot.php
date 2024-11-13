<?php

rex_yform::addTemplatePath($this->getPath('ytemplates'));

if (rex::isBackend() && rex::getUser()) {
    filepond_helper::getStyles();
    filepond_helper::getScripts();
}
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
