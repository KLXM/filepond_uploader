<?php

if (rex::isBackend() && rex::getUser()?->isAdmin()) {

    // Prüfe ob die Metainfo-Tabelle existiert
    $sql = rex_sql::factory();
    $sql->setQuery('SHOW TABLES LIKE "' . rex::getTable('metainfo_field') . '"');
    if ($sql->getRows() > 0) {
        
        // Prüfe ob das Feld bereits existiert
        $sql->setQuery('SELECT * FROM ' . rex::getTable('metainfo_field') . ' WHERE name = "med_alt"');
        
        if ($sql->getRows() == 0) {
			
			rex_sql_table::get(rex::getTable('media'))
            ->ensureColumn(new rex_sql_column('med_alt', 'text', true))
            ->ensure();
			
			
            try {
                // Parameter basierend auf rex_api_metainfo_default_fields_create
                $field = [
                    'title' => 'Alternative Text',  // Label im Backend
                    'name' => 'med_alt',         // Technischer Name
                    'priority' => 2,               // Priorität (nach Copyright)
                    'attributes' => '',            // Zusätzliche Attribute
                    'type_id' => 1,               // 1 = Text Input
                    'params' => '',               // Zusätzliche Parameter
                    'validate' => '',             // Validierungsregeln
                    'restrictions' => '',          // Einschränkungen
                    'createuser' => rex::getUser()->getLogin(),
                    'createdate' => date('Y-m-d H:i:s'),
                    'updateuser' => rex::getUser()->getLogin(),
                    'updatedate' => date('Y-m-d H:i:s')
                ];

                // Feld in die Datenbank eintragen
                $insert = rex_sql::factory();
                $insert->setTable(rex::getTable('metainfo_field'));
                $insert->setValues($field);
                $insert->insert();

                // Erfolgsmeldung
                rex_logger::factory()->log('info', 'Meta field "med_alt" was created successfully');

            } catch (rex_sql_exception $e) {
                // Fehler loggen
                rex_logger::logError(E_WARNING, $e->getMessage(), $e->getFile(), $e->getLine());
                throw new rex_functional_exception('Error creating meta field "med_alt": ' . $e->getMessage());
            }
        }
    }
}

// Erfolgsmeldung für das AddOn
return true;