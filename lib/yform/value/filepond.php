<?php

class rex_yform_value_filepond extends rex_yform_value_abstract
{
    protected static function cleanValue($value)
    {
        return implode(',', array_filter(array_map('trim', explode(',', str_replace('"', '', $value))), 'strlen'));
    }

 public function preValidateAction(): void
    {
        // Wird nur ausgeführt, wenn das Formular gesendet wurde
        if ($this->params['send']) {
            // Original Value aus der Datenbank holen
            $originalValue = '';
            if (isset($this->params['main_id']) && $this->params['main_id'] > 0) {
                $sql = rex_sql::factory();
                $sql->setQuery('SELECT ' . $sql->escapeIdentifier($this->getName()) . 
                              ' FROM ' . $sql->escapeIdentifier($this->params['main_table']) . 
                              ' WHERE id = ' . (int)$this->params['main_id']);
                if ($sql->getRows() > 0) {
                    $originalValue = self::cleanValue($sql->getValue($this->getName()));
                }
            }

            // Neuen Wert aus dem Formular holen
            $newValue = '';
            if (isset($_REQUEST['FORM'])) {
                foreach ($_REQUEST['FORM'] as $form) {
                    if (isset($form[$this->getId()])) {
                        $newValue = self::cleanValue($form[$this->getId()]);
                        break;
                    }
                }
            }

            // Debug Logging
            rex_logger::factory()->log('debug', sprintf(
                "Original Value: %s\nNew Value: %s",
                $originalValue,
                $newValue
            ), [], __FILE__, __LINE__);

            // Gelöschte Dateien ermitteln
            $originalFiles = array_filter(explode(',', $originalValue));
            $newFiles = array_filter(explode(',', $newValue));
            $deletedFiles = array_diff($originalFiles, $newFiles);

            // Debug Logging für gelöschte Dateien
            if (!empty($deletedFiles)) {
                rex_logger::factory()->log('debug', sprintf(
                    "Deleted Files: %s",
                    implode(', ', $deletedFiles)
                ), [], __FILE__, __LINE__);
            }

            // Gelöschte Dateien verarbeiten
            foreach ($deletedFiles as $filename) {
                try {
                    if ($media = rex_media::get($filename)) {
                        // Prüfen ob die Datei noch von anderen Datensätzen verwendet wird
                        $inUse = false;

                        // Alle Tabellen mit diesem Feldtyp durchsuchen
                        $yformTables = rex_yform_manager_table::getAll();
                        foreach ($yformTables as $table) {
                            foreach ($table->getFields() as $field) {
                                if ($field->getType() === 'value' && $field->getTypeName() === 'filepond') {
                                    // Sicheres Erstellen der SQL-Abfrage
                                    $tableName = $sql->escapeIdentifier($table->getTableName());
                                    $fieldName = $sql->escapeIdentifier($field->getName());
                                    // Filename für LIKE ohne zusätzliche Anführungszeichen
                                    $filePattern = '%' . str_replace(['%', '_'], ['\%', '\_'], $filename) . '%';
                                    $currentId = (int)$this->params['main_id'];

                                    // Debug Logging vor der Ausführung
                                    rex_logger::factory()->log('debug', sprintf(
                                        "File check - Table: %s, Field: %s, Pattern: %s, ID: %d",
                                        $table->getTableName(),
                                        $field->getName(),
                                        $filePattern,
                                        $currentId
                                    ), [], __FILE__, __LINE__);

                                    $query = "SELECT id FROM $tableName WHERE $fieldName LIKE ? AND id != ?";

                                    try {
                                        // Neues SQL-Objekt für jede Abfrage
                                        $sql = rex_sql::factory();
                                        
                                        // Ausführen mit prepared statement
                                        $result = $sql->getArray($query, [$filePattern, $currentId]);
                                        
                                        // Debug Logging nach der Ausführung
                                        rex_logger::factory()->log('debug', sprintf(
                                            "SQL Result Count: %d",
                                            count($result)
                                        ), [], __FILE__, __LINE__);
                                        
                                        if (count($result) > 0) {
                                            $inUse = true;
                                            rex_logger::factory()->log('debug', sprintf(
                                                "File %s is still in use in table %s",
                                                $filename,
                                                $table->getTableName()
                                            ), [], __FILE__, __LINE__);
                                            break 2;
                                        }
                                    } catch (Exception $e) {
                                        rex_logger::logError(
                                            E_WARNING, 
                                            sprintf(
                                                "SQL Error checking file usage: %s (Query: %s, Params: %s)",
                                                $e->getMessage(),
                                                $query,
                                                json_encode([$filePattern, $currentId])
                                            ),
                                            $e->getFile(),
                                            $e->getLine()
                                        );
                                        continue;
                                    }
                                }
                            }
                        }

                        // Datei löschen wenn sie nicht mehr verwendet wird
                        if (!$inUse) {
                            try {
                                // Nochmal prüfen ob die Datei existiert
                                if ($media = rex_media::get($filename)) {
                                    // Debug Log vor dem Löschen
                                    rex_logger::factory()->log('debug', sprintf(
                                        "Attempting to delete file %s from mediapool",
                                        $filename
                                    ), [], __FILE__, __LINE__);

                                    // Versuche die Datei zu löschen
                                    $deleted = rex_media_service::deleteMedia($filename);
                                    
                                    if ($deleted) {
                                        // Erfolgreich gelöscht
                                        rex_logger::factory()->log('debug', sprintf(
                                            "Successfully deleted file %s from mediapool",
                                            $filename
                                        ), [], __FILE__, __LINE__);
                                    } else {
                                        // Fehler beim Löschen
                                        throw new Exception('Media delete returned false');
                                    }
                                } else {
                                    // Datei existiert nicht mehr
                                    rex_logger::factory()->log('debug', sprintf(
                                        "File %s no longer exists in mediapool",
                                        $filename
                                    ), [], __FILE__, __LINE__);
                                }
                            } catch (Exception $e) {
                                rex_logger::logError(
                                    E_WARNING,
                                    sprintf(
                                        "Failed to delete file %s from mediapool: %s",
                                        $filename,
                                        $e->getMessage()
                                    ),
                                    $e->getFile(),
                                    $e->getLine()
                                );
                            }
                        }
                    }
                } catch (Exception $e) {
                    rex_logger::logError(E_WARNING, $e->getMessage(), $e->getFile(), $e->getLine());
                }
            }
        }
    }

   public function enterObject()
    {
        // Initialize value without quotes
        $this->setValue($this->getValue());

        // Process form submission
        if ($this->params['send']) {
            $value = '';
            
            // Get value from request and clean it
            if (isset($_REQUEST['FORM'])) {
                foreach ($_REQUEST['FORM'] as $form) {
                    if (isset($form[$this->getId()])) {
                        $value = $form[$this->getId()];
                        break;
                    }
                }
            }

            // Validate
            $errors = [];
            if ($this->getElement('required') == 1 && $value == '') {
                $errors[] = $this->getElement('empty_value', 'Bitte wählen Sie eine Datei aus.');
            }

            // Set warnings if any
            if (count($errors) > 0) {
                $this->params['warning'][$this->getId()] = $this->params['error_class'];
                $this->params['warning_messages'][$this->getId()] = implode(', ', $errors);
            }

            // Store value
            $this->setValue($value);

            // Value pools
            if ($value != '') {
                $this->params['value_pool']['email'][$this->getName()] = $value;
                if ($this->saveInDb()) {
                    $this->params['value_pool']['sql'][$this->getName()] = $value;
                }
            }
        }

        // Prepare initial files
        $files = [];
        $value = $this->getValue();
        
        if ($value) {
            // Remove quotes and split
            $value = trim($value, '"');
            $fileNames = explode(',', $value);
            
            foreach ($fileNames as $fileName) {
                $fileName = trim($fileName);
                if ($fileName && file_exists(rex_path::media($fileName))) {
                    $files[] = $fileName;
                }
            }
        }

        // Output form with clean value and files
        $this->params['form_output'][$this->getId()] = $this->parse('value.filepond.tpl.php', [
            'category_id' => $this->getElement('category') ?: 1,
            'value' => $this->getValue(),
            'files' => $files
        ]);
    }


    public function getDescription(): string
    {
        return 'filepond|name|label|allowed_types|allowed_filesize|allowed_max_files|category|required|notice';
    }

    public function getDefinitions(): array
    {
        return [
            'type' => 'value',
            'name' => 'filepond',
            'values' => [
                'name'     => ['type' => 'name',   'label' => rex_i18n::msg('yform_values_defaults_name')],
                'label'    => ['type' => 'text',   'label' => rex_i18n::msg('yform_values_defaults_label')],
                'category' => [
                    'type' => 'text',   
                    'label' => 'Medienkategorie ID',
                    'notice' => 'ID der Medienkategorie in die die Dateien geladen werden sollen'
                ],
                'allowed_types' => [
                    'type' => 'text',   
                    'label' => 'Erlaubte Dateitypen',
                    'notice' => 'z.B.: image/*,.pdf',
                    'default' => 'image/*'
                ],
                'allowed_filesize' => [
                    'type' => 'text',   
                    'label' => 'Maximale Dateigröße (MB)',
                    'notice' => 'Größe in Megabyte',
                    'default' => '10'
                ],
                'allowed_max_files' => [
                    'type' => 'text',   
                    'label' => 'Maximale Anzahl Dateien',
                    'default' => '10'
                ],
                'required' => ['type' => 'boolean', 'label' => 'Pflichtfeld', 'default' => '0'],
                'notice'   => ['type' => 'text',    'label' => rex_i18n::msg('yform_values_defaults_notice')],
                'empty_value'  => [
                    'type' => 'text',    
                    'label' => 'Fehlermeldung wenn leer',
                    'default' => 'Bitte eine Datei auswählen.'
                ]
            ],
            'description' => 'Filepond Dateiupload mit Medienpool-Integration',
            'db_type' => ['text'],
            'multi_edit' => false
        ];
    }

    public static function getSearchField($params)
    {
        $params['searchForm']->setValueField('text', [
            'name' => $params['field']->getName(),
            'label' => $params['field']->getLabel(),
            'notice' => 'Dateiname eingeben'
        ]);
    }

    public static function getSearchFilter($params)
    {
        $sql = rex_sql::factory();
        $value = $params['value'];
        $field = $params['field']->getName();

        if ($value == '(empty)') {
            return ' (' . $sql->escapeIdentifier($field) . ' = "" or ' . $sql->escapeIdentifier($field) . ' IS NULL) ';
        }
        if ($value == '!(empty)') {
            return ' (' . $sql->escapeIdentifier($field) . ' <> "" and ' . $sql->escapeIdentifier($field) . ' IS NOT NULL) ';
        }

        $pos = strpos($value, '*');
        if ($pos !== false) {
            $value = str_replace('%', '\%', $value);
            $value = str_replace('*', '%', $value);
            return $sql->escapeIdentifier($field) . ' LIKE ' . $sql->escape($value);
        }
        return $sql->escapeIdentifier($field) . ' = ' . $sql->escape($value);
    }

    public static function getListValue($params)
    {
        // Clean value before processing
        $files = array_filter(explode(',', self::cleanValue($params['subject'])));
        $downloads = [];

        if (rex::isBackend()) {
            foreach ($files as $file) {
                if (!empty($file)) {
                    $media = rex_media::get($file);
                    if ($media) {
                        $fileName = $media->getFileName();
                        
                        if ($media->isImage()) {
                            $thumb = rex_media_manager::getUrl('rex_medialistbutton_preview', $fileName);
                            $downloads[] = sprintf(
                                '<div class="rex-yform-value-mediafile">
                                    <a href="%s" title="%s" target="_blank">
                                        <img src="%s" alt="%s" style="max-width: 100px;">
                                        <span class="filename">%s</span>
                                    </a>
                                </div>',
                                $media->getUrl(),
                                rex_escape($fileName),
                                $thumb,
                                rex_escape($fileName),
                                rex_escape($fileName)
                            );
                        } else {
                            $downloads[] = sprintf(
                                '<div class="rex-yform-value-mediafile">
                                    <a href="%s" title="%s" target="_blank">
                                        <span class="filename">%s</span>
                                    </a>
                                </div>',
                                $media->getUrl(),
                                rex_escape($fileName),
                                rex_escape($fileName)
                            );
                        }
                    }
                }
            }
            
            if (!empty($downloads)) {
                return '<div class="rex-yform-value-mediafile-list">' . implode('', $downloads) . '</div>';
            }
        }

        return self::cleanValue($params['subject']);
    }
}