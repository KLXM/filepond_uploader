<?php

if (rex::isBackend() && rex::getUser()?->isAdmin()) {

    $addon = rex_addon::get('filepond_uploader');
    $message = '';

    // Generate API token if not exists
    if (!rex_config::get('filepond_uploader', 'api_token')) {
        // Generate a secure random token
        $token = bin2hex(random_bytes(32));
        rex_config::set('filepond_uploader', 'api_token', $token);
        
        // Set success message with token
        $message = '<div class="alert alert-info">';
        $message .= '<p><strong>Installation erfolgreich!</strong></p>';
        $message .= '<p>Ihr API-Token wurde generiert. Bitte notieren Sie sich den Token, er wird aus Sicherheitsgründen nur einmal angezeigt:</p>';
        $message .= '<div class="input-group" style="margin: 10px 0;">';
        $message .= '<input type="text" class="form-control" id="initial-token" value="' . rex_escape($token) . '" readonly>';
        $message .= '<span class="input-group-btn">';
        $message .= '<clipboard-copy for="initial-token" class="btn btn-default"><i class="fa fa-clipboard"></i> Token kopieren</clipboard-copy>';
        $message .= '</span>';
        $message .= '</div>';
        $message .= '<p><strong>Wichtig:</strong> Bewahren Sie den Token sicher auf. Er wird später nur noch verschlüsselt angezeigt.</p>';
        $message .= '</div>';

        // Log token generation
        rex_logger::factory()->log('info', 'FilePond API: Generated new API token');
    }

    // Prüfe ob die Metainfo-Tabelle existiert
    $sql = rex_sql::factory();
    $sql->setQuery('SHOW TABLES LIKE "' . rex::getTable('metainfo_field') . '"');
    
    if ($sql->getRows() > 0) {
        // Prüfe ob die notwendigen Felder bereits existieren
        $fields = [
            'med_alt' => [
                'title' => 'Alternative Text',
                'priority' => 2,
                'type_id' => 1, // Text Input
                'params' => '',
                'validate' => '',
                'restrictions' => ''
            ],
            'med_copyright' => [
                'title' => 'Copyright',
                'priority' => 3,
                'type_id' => 1, // Text Input
                'params' => '',
                'validate' => '',
                'restrictions' => ''
            ]
        ];

        try {
            // Hole Medientabelle
            $mediaTable = rex_sql_table::get(rex::getTable('media'));
            
            // Füge Spalten hinzu nach med_description, wenn sie nicht existieren
            if (!$mediaTable->hasColumn('med_alt')) {
                $mediaTable->addColumn(new rex_sql_column('med_alt', 'text', true));
            }
            if (!$mediaTable->hasColumn('med_copyright')) {
                $mediaTable->addColumn(new rex_sql_column('med_copyright', 'text', true));
            }
            
            // Führe die Änderungen aus
            $mediaTable->ensure();
            
            // Erstelle Metainfo Felder
            foreach ($fields as $name => $field) {
                $sql->setQuery('SELECT * FROM ' . rex::getTable('metainfo_field') . ' WHERE name = :name', [':name' => $name]);
                
                if ($sql->getRows() == 0) {
                    $metaField = [
                        'title' => $field['title'],
                        'name' => $name,
                        'priority' => $field['priority'],
                        'attributes' => '',
                        'type_id' => $field['type_id'],
                        'params' => $field['params'],
                        'validate' => $field['validate'],
                        'restrictions' => $field['restrictions'],
                        'createuser' => rex::getUser()->getLogin(),
                        'createdate' => date('Y-m-d H:i:s'),
                        'updateuser' => rex::getUser()->getLogin(),
                        'updatedate' => date('Y-m-d H:i:s')
                    ];

                    $insert = rex_sql::factory();
                    $insert->setTable(rex::getTable('metainfo_field'));
                    $insert->setValues($metaField);
                    $insert->insert();
                }
            }

            // Prüfe ob der Upload-Ordner existiert
            $uploadPath = rex_path::pluginData('yform', 'manager', 'upload/filepond');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0775, true);
            }

            // Default-Konfiguration setzen
            $defaultConfigs = [
                'max_files' => 30,
                'max_filesize' => 10,
                'allowed_types' => 'image/*,video/*,.pdf,.doc,.docx,.txt',
                'category_id' => 0,
                'lang' => rex_i18n::getLanguage()
            ];

            foreach ($defaultConfigs as $key => $value) {
                if (!rex_config::has('filepond_uploader', $key)) {
                    rex_config::set('filepond_uploader', $key, $value);
                }
            }

            if ($message) {
                $addon->setProperty('successmsg', $message);
            }

        } catch (rex_sql_exception $e) {
            rex_logger::factory()->log('error', 'FilePond API: Installation error - ' . $e->getMessage());
            throw new rex_functional_exception($e->getMessage());
        }
    }
}

return true;
