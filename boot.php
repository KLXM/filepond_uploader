<?php

rex_yform::addTemplatePath($this->getPath('ytemplates'));

// Assets für Backend
if (rex::isBackend() && rex::getUser()) {
    // CSS
    rex_view::addCssFile($this->getAssetsUrl('filepond/filepond.css'));
    rex_view::addCssFile($this->getAssetsUrl('filepond/plugins/filepond-plugin-image-preview.css'));
    rex_view::addCssFile($this->getAssetsUrl('filepond_widget.css'));
    
    // JavaScript
    rex_view::addJsFile($this->getAssetsUrl('filepond/plugins/filepond-plugin-file-validate-type.js'));
    rex_view::addJsFile($this->getAssetsUrl('filepond/plugins/filepond-plugin-file-validate-size.js'));
    rex_view::addJsFile($this->getAssetsUrl('filepond/plugins/filepond-plugin-image-preview.js'));
    rex_view::addJsFile($this->getAssetsUrl('filepond/filepond.js'));
    rex_view::addJsFile($this->getAssetsUrl('filepond_widget.js'));
}
