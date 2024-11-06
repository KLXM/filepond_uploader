<?php
if (rex::isBackend()) {
    rex_yform::addTemplatePath($this->getPath('ytemplates'));
}


if (rex::isBackend()) {
    rex_yform::addTemplatePath($this->getPath('ytemplates'));
}

// Assets für Backend und Frontend
if (rex::isBackend() && rex::getUser() || rex::isFrontend()) {
    // CSS
    rex_view::addCssFile('https://unpkg.com/filepond/dist/filepond.css');
    rex_view::addCssFile('https://unpkg.com/filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css');
    
    rex_view::addCssFile($this->getAssetsUrl('filepond_widget.css'));
    
    // JavaScript
    rex_view::addJsFile('https://unpkg.com/filepond-plugin-file-validate-type/dist/filepond-plugin-file-validate-type.js');
    rex_view::addJsFile('https://unpkg.com/filepond-plugin-file-validate-size/dist/filepond-plugin-file-validate-size.js');
    rex_view::addJsFile('https://unpkg.com/filepond-plugin-image-preview/dist/filepond-plugin-image-preview.js');
    rex_view::addJsFile('https://unpkg.com/filepond/dist/filepond.js');
    rex_view::addJsFile($this->getAssetsUrl('filepond_widget.js'));
}