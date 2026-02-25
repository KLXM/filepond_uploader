<?php

/** @var rex_addon $this */

use FriendsOfRedaxo\FilePond\FilePondMediaCleanup;

rex_yform::addTemplatePath($this->getPath('ytemplates'));

// MEDIA_IS_IN_USE Extension Point registrieren für bessere Kontrolle
rex_extension::register('MEDIA_IS_IN_USE', [FilePondMediaCleanup::class, 'isMediaInUse']);

// Mediapool MIME-Types erweitern für Typen, die FilePond erlaubt aber der Mediapool nicht kennt
if (rex_addon::get('mediapool')->isAvailable()) {
    $filepondMimeMap = [
        // Bilder
        'image/jpeg' => ['ext' => 'jpg', 'alt' => ['image/pjpeg']],
        'image/png' => ['ext' => 'png'],
        'image/gif' => ['ext' => 'gif'],
        'image/webp' => ['ext' => 'webp'],
        'image/svg+xml' => ['ext' => 'svg'],
        'image/tiff' => ['ext' => 'tiff'],
        'image/bmp' => ['ext' => 'bmp'],
        'image/heic' => ['ext' => 'heic'],
        'image/avif' => ['ext' => 'avif'],
        'image/x-icon' => ['ext' => 'ico', 'alt' => ['image/vnd.microsoft.icon']],

        // Dokumente
        'application/pdf' => ['ext' => 'pdf'],
        'text/plain' => ['ext' => 'txt', 'alt' => ['application/octet-stream']],
        'text/csv' => ['ext' => 'csv', 'alt' => ['text/plain', 'application/octet-stream']],
        'text/calendar' => ['ext' => 'ics', 'alt' => ['text/plain', 'application/octet-stream']],
        'text/x-vcalendar' => ['ext' => 'vcal', 'alt' => ['text/calendar', 'text/plain', 'application/octet-stream']],
        'text/vcard' => ['ext' => 'vcf', 'alt' => ['text/x-vcard', 'text/plain', 'application/octet-stream']],
        'text/markdown' => ['ext' => 'md', 'alt' => ['text/plain', 'application/octet-stream']],
        'application/rtf' => ['ext' => 'rtf'],
        'application/json' => ['ext' => 'json', 'alt' => ['text/plain']],
        'text/xml' => ['ext' => 'xml', 'alt' => ['application/xml']],
        'text/vtt' => ['ext' => 'vtt'],
        'text/srt' => ['ext' => 'srt', 'alt' => ['text/plain']],

        // Archive
        'application/zip' => ['ext' => 'zip', 'alt' => ['application/x-zip-compressed']],
        'application/x-gzip' => ['ext' => 'gz', 'alt' => ['application/gzip']],
        'application/x-tar' => ['ext' => 'tar'],
        'application/x-rar-compressed' => ['ext' => 'rar', 'alt' => ['application/vnd.rar']],
        'application/x-7z-compressed' => ['ext' => '7z'],

        // Video
        'video/mp4' => ['ext' => 'mp4'],
        'video/mpeg' => ['ext' => 'mpeg'],
        'video/quicktime' => ['ext' => 'mov'],
        'video/webm' => ['ext' => 'webm'],
        'video/ogg' => ['ext' => 'ogv'],
        'video/x-msvideo' => ['ext' => 'avi'],
        'video/x-matroska' => ['ext' => 'mkv'],

        // Audio
        'audio/mpeg' => ['ext' => 'mp3'],
        'audio/wav' => ['ext' => 'wav', 'alt' => ['audio/x-wav']],
        'audio/ogg' => ['ext' => 'ogg'],
        'audio/aac' => ['ext' => 'aac'],
        'audio/midi' => ['ext' => 'midi', 'alt' => ['audio/x-midi']],
        'audio/flac' => ['ext' => 'flac'],
        'audio/mp4' => ['ext' => 'm4a'],
        'audio/webm' => ['ext' => 'weba'],

        // Office (Modern)
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['ext' => 'docx', 'alt' => ['application/octet-stream', 'application/encrypted']],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['ext' => 'xlsx', 'alt' => ['application/octet-stream', 'application/encrypted']],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['ext' => 'pptx'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.template' => ['ext' => 'dotx', 'alt' => ['application/octet-stream']],
        'application/vnd.openxmlformats-officedocument.presentationml.template' => ['ext' => 'potx'],
        'application/vnd.openxmlformats-officedocument.presentationml.slideshow' => ['ext' => 'ppsx'],

        // Office (Legacy)
        'application/msword' => ['ext' => 'doc', 'alt' => ['application/octet-stream', 'application/encrypted']],
        'application/vnd.ms-excel' => ['ext' => 'xls', 'alt' => ['application/octet-stream', 'application/encrypted']],
        'application/vnd.ms-powerpoint' => ['ext' => 'ppt'],

        // OpenDocument
        'application/vnd.oasis.opendocument.text' => ['ext' => 'odt'],
        'application/vnd.oasis.opendocument.spreadsheet' => ['ext' => 'ods'],
        'application/vnd.oasis.opendocument.presentation' => ['ext' => 'odp'],

        // Fonts
        'font/woff' => ['ext' => 'woff', 'alt' => ['application/font-woff']],
        'font/woff2' => ['ext' => 'woff2'],
        'font/ttf' => ['ext' => 'ttf', 'alt' => ['application/x-font-ttf']],
        'font/otf' => ['ext' => 'otf', 'alt' => ['application/x-font-opentype']],

        // Sonstige
        'application/postscript' => ['ext' => 'eps'],
        'application/epub+zip' => ['ext' => 'epub'],
    ];

    $allowedTypes = rex_config::get('filepond_uploader', 'allowed_types', '');
    if ('' !== $allowedTypes) {
        // FilePond nutzt Komma-getrennte MIME-Types, kann aber auch Wildcards (image/*) und Endungen (.pdf) enthalten
        $configuredTypes = array_map('trim', explode(',', $allowedTypes));
        $mediapoolMimes = rex_addon::get('mediapool')->getProperty('allowed_mime_types', []);
        $changed = false;

        foreach ($configuredTypes as $type) {
            // Wildcard-Typen wie "image/*" oder "video/*" auflösen
            if (str_contains($type, '/*')) {
                $prefix = explode('/*', $type)[0] . '/';
                foreach ($filepondMimeMap as $mime => $info) {
                    if (str_starts_with($mime, $prefix) && !isset($mediapoolMimes[$info['ext']])) {
                        $mediapoolMimes[$info['ext']] = array_merge([$mime], $info['alt'] ?? []);
                        $changed = true;
                    }
                }
            } elseif (isset($filepondMimeMap[$type])) {
                // Exakter MIME-Type
                $ext = $filepondMimeMap[$type]['ext'];
                if (!isset($mediapoolMimes[$ext])) {
                    $mediapoolMimes[$ext] = array_merge([$type], $filepondMimeMap[$type]['alt'] ?? []);
                    $changed = true;
                }
            } elseif (str_starts_with($type, '.')) {
                // Dateiendung wie ".pdf", ".docx" – passenden MIME-Type finden
                $extLookup = ltrim($type, '.');
                foreach ($filepondMimeMap as $mime => $info) {
                    if ($info['ext'] === $extLookup && !isset($mediapoolMimes[$extLookup])) {
                        $mediapoolMimes[$extLookup] = array_merge([$mime], $info['alt'] ?? []);
                        $changed = true;
                        break;
                    }
                }
            }
        }

        if ($changed) {
            rex_addon::get('mediapool')->setProperty('allowed_mime_types', $mediapoolMimes);
        }
    }
}

if (rex::isBackend() && rex::getUser()) {
    // Einbindung über Static-Properties sicherstellen
    static $filepondScriptsLoaded = false;
    
    if (!$filepondScriptsLoaded) {
        filepond_helper::getStyles();
        filepond_helper::getScripts();
        $filepondScriptsLoaded = true;
    }

    // Settings-Seite: JS für Dateitypen-Auswahl
    if ('filepond_uploader/settings' === rex_be_controller::getCurrentPage()) {
        rex_view::addJsFile($this->getAssetsUrl('filepond_settings.js'));
    }
}



if(rex_config::get('filepond_uploader', 'replace_mediapool', false))
{    
    rex_extension::register('PAGES_PREPARED', function (rex_extension_point $ep) {
        /** @var array<string, rex_be_page> $pages */
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
        
        /** @var array<string, rex_be_page> $pages */
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
