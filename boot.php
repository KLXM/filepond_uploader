<?php

use FriendsOfRedaxo\FilePond\FilePondMediaCleanup;

rex_yform::addTemplatePath($this->getPath('ytemplates'));

// MEDIA_IS_IN_USE Extension Point registrieren für bessere Kontrolle
rex_extension::register('MEDIA_IS_IN_USE', [FilePondMediaCleanup::class, 'isMediaInUse']);

if (rex::isBackend() && rex::getUser()) {
    // Einbindung über Static-Properties sicherstellen
    static $filepondScriptsLoaded = false;
    
    if (!$filepondScriptsLoaded) {
        filepond_helper::getStyles();
        filepond_helper::getScripts();
        $filepondScriptsLoaded = true;
    }
}



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

// Alt-Text-Checker als Medienpool-Unterseite registrieren
$enableAltChecker = rex_config::get('filepond_uploader', 'enable_alt_checker', '');
if ($enableAltChecker === '|1|' || $enableAltChecker === '1') {
    rex_extension::register('PAGES_PREPARED', function (rex_extension_point $ep) {
        $user = rex::getUser();
        if (!$user) return;
        
        // Nur für Admins oder Nutzer mit entsprechender Berechtigung
        if (!$user->isAdmin() && !$user->hasPerm('filepond_uploader[alt_checker]')) {
            return;
        }
        
        // Nur einbinden wenn med_alt Feld überhaupt vorhanden ist
        if (!filepond_alt_text_checker::checkAltFieldExists()) {
            return;
        }
        
        $pages = $ep->getSubject();
        
        if (isset($pages['mediapool'])) {
            $mediapoolPage = $pages['mediapool'];
            
            // Neue Unterseite erstellen
            $title = '<i class="fa-solid fa-universal-access"></i> ' . rex_i18n::msg('filepond_alt_checker_title');
            $altCheckerPage = new rex_be_page('alt_checker', $title);
            $altCheckerPage->setSubPath(rex_path::addon('filepond_uploader', 'pages/alt_checker.php'));
            $altCheckerPage->setRequiredPermissions('filepond_uploader[alt_checker]');
            
            // Als Unterseite hinzufügen
            $mediapoolPage->addSubpage($altCheckerPage);
        }
    });
}

// Info Center FilePond Upload Widget Integration
if (rex_addon::exists('info_center') && rex_addon::get('info_center')->isAvailable()) {
    rex_extension::register('PACKAGES_INCLUDED', function() {
        $infoCenter = \KLXM\InfoCenter\InfoCenter::getInstance();
        
        // Check if user has permission (only for logged-in users)
        if (rex::getUser()) {
            $widget = new \KLXM\InfoCenter\Widgets\FilePondUploadWidget();
            $widget->setPriority(0.5); // After TimeTracker (0), before Article (1)
            $infoCenter->registerWidget($widget);
        }
    });
}
