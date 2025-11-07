<?php

// Standardwerte für neue Konfigurationsoptionen setzen, falls sie noch nicht existieren
$configDefaults = [
    'delayed_upload_mode' => false,
    'title_required_default' => false // Einfaches title-Feld ist standardmäßig optional
];

foreach ($configDefaults as $key => $defaultValue) {
    if (!rex_config::has('filepond_uploader', $key)) {
        rex_config::set('filepond_uploader', $key, $defaultValue);
    }
}

return true;