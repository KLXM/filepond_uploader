<?php
// Create upload directory
$uploadPath = rex_path::pluginData('yform', 'manager', 'upload/filepond');
rex_dir::create($uploadPath);

// Set directory permissions
chmod($uploadPath, rex_dir::getDefaultMode());
