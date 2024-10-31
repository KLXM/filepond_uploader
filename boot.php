<?php 
if (rex::isBackend() && rex::getUser()) {
    // Backend assets
    rex_view::addCssFile($this->getAssetsUrl('css/filepond-custom.css'));
    
    // FilePond core and plugins from CDN
    rex_view::addCssFile('https://unpkg.com/filepond/dist/filepond.css');
    rex_view::addCssFile('https://unpkg.com/filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css');
    
    rex_view::addJsFile('https://unpkg.com/filepond-plugin-file-validate-type/dist/filepond-plugin-file-validate-type.js');
    rex_view::addJsFile('https://unpkg.com/filepond-plugin-file-validate-size/dist/filepond-plugin-file-validate-size.js');
    rex_view::addJsFile('https://unpkg.com/filepond-plugin-image-preview/dist/filepond-plugin-image-preview.js');
    rex_view::addJsFile('https://unpkg.com/filepond/dist/filepond.js');
    
    // Custom initialization
    rex_view::addJsFile($this->getAssetsUrl('js/filepond-init.js'));
}
