<?php

if (rex::isBackend() && rex::getUser()?->isAdmin()) {

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
                $mediaTable->addColumn(new rex_sql_column('med_alt', 'text', true), 'med_description');
            }
            if (!$mediaTable->hasColumn('med_copyright')) {
                $mediaTable->addColumn(new rex_sql_column('med_copyright', 'text', true), 'med_description');
            }
            
            // Führe die Änderungen aus
            $mediaTable->alter();
            
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

            // Setze Standardkonfiguration
            if (!rex_config::has('filepond_uploader', 'settings')) {
                rex_config::set('filepond_uploader', 'settings', [
                    'default_category' => 0,
                    'allowed_types' => 'image/*,video/*,.pdf,.doc,.docx,.txt',
                    'max_filesize' => 10
                ]);
            }

        } catch (rex_sql_exception $e) {
            throw new rex_functional_exception($e->getMessage());
        }
    }
}

return true;