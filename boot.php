<?php
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

// Download-Handler
if (rex_request('filepond', 'string', false)) {
    filepond_download(rex_request('filepond', 'string'));
}

function filepond_download($file)  
{
    $filename = basename($file);
    $filepath = rex_path::pluginData('yform', 'manager', 'upload/filepond/'.$filename);

    if (file_exists($filepath)) {
        $mimeType = mime_content_type($filepath) ?: 'application/octet-stream';
        ob_end_clean();
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mimeType);
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
}
