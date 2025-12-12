<?php

// Standardwerte für neue Konfigurationsoptionen setzen, falls sie noch nicht existieren
$configDefaults = [
    'delayed_upload_mode' => false,
    'title_required_default' => false, // Einfaches Title-Feld ist standardmäßig optional
    'fix_exif_orientation' => true // EXIF-Orientierung standardmäßig korrigieren
];

foreach ($configDefaults as $key => $defaultValue) {
    if (!rex_config::has('filepond_uploader', $key)) {
        rex_config::set('filepond_uploader', $key, $defaultValue);
    }
}

// Alte Konfigurationsoptionen entfernen (ab 1.14.0 nicht mehr verwendet)
$deprecatedConfigs = [
    'convert_format' // WebP/AVIF-Konvertierung wurde entfernt, MediaManager sollte dies übernehmen
];

foreach ($deprecatedConfigs as $key) {
    if (rex_config::has('filepond_uploader', $key)) {
        rex_config::remove('filepond_uploader', $key);
    }
}

return true;