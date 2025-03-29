<?php class rex_yform_value_filepond extends rex_yform_value_abstract
{
    protected static function cleanValue($value)
    {
        return implode(',', array_filter(array_map('trim', explode(',', str_replace('"', '', $value))), 'strlen'));
    }

    public function preValidateAction(): void
    {
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

            // Gelöschte Dateien ermitteln und verarbeiten
            $originalFiles = array_filter(explode(',', $originalValue));
            $newFiles = array_filter(explode(',', $newValue));
            $deletedFiles = array_diff($originalFiles, $newFiles);

            foreach ($deletedFiles as $filename) {
                try {
                    if ($media = rex_media::get($filename)) {
                        // Prüfen ob die Datei noch von anderen Datensätzen verwendet wird
                        $inUse = false;
                        $sql = rex_sql::factory();
                        
                        // Alle YForm Tabellen durchsuchen
                        $yformTables = rex_yform_manager_table::getAll();
                        foreach ($yformTables as $table) {
                            foreach ($table->getFields() as $field) {
                                if ($field->getType() === 'value' && $field->getTypeName() === 'filepond') {
                                    $tableName = $table->getTableName();
                                    $fieldName = $field->getName();
                                    $filePattern = '%' . str_replace(['%', '_'], ['\%', '\_'], $filename) . '%';
                                    $currentId = (int)$this->params['main_id'];

                                    $query = "SELECT id FROM $tableName WHERE $fieldName LIKE :filename AND id != :id";
                                    
                                    try {
                                        $result = $sql->getArray($query, [':filename' => $filePattern, ':id' => $currentId]);
                                        if (count($result) > 0) {
                                            $inUse = true;
                                            break 2;
                                        }
                                    } catch (Exception $e) {
                                        continue;
                                    }
                                }
                            }
                        }

                        // Datei löschen wenn sie nicht mehr verwendet wird
                        if (!$inUse && rex_media::get($filename)) {
                            rex_media_service::deleteMedia($filename);
                        }
                    }
                } catch (Exception $e) {
                    // Fehler beim Löschen werden ignoriert
                }
            }
        }
    }

    public function enterObject()
    {
        $this->setValue($this->getValue());

        if ($this->params['send']) {
            $value = '';
            
            if (isset($_REQUEST['FORM'])) {
                foreach ($_REQUEST['FORM'] as $form) {
                    if (isset($form[$this->getId()])) {
                        $value = $form[$this->getId()];
                        break;
                    }
                }
            } elseif ($this->params['real_field_names']) {
                if (isset($_REQUEST[$this->getName()])) {
                    $value = $_REQUEST[$this->getName()];
                    $this->setValue($value);
                }
            }


            $errors = [];
            if ($this->getElement('required') == 1 && $value == '') {
                $errors[] = $this->getElement('empty_value', 'Bitte wählen Sie eine Datei aus.');
            }

            if (count($errors) > 0) {
                $this->params['warning'][$this->getId()] = $this->params['error_class'];
                $this->params['warning_messages'][$this->getId()] = implode(', ', $errors);
            }

            $this->setValue($value);
            
            // Wert immer in die value_pools schreiben, auch wenn leer
            $this->params['value_pool']['email'][$this->getName()] = $value;
            if ($this->saveInDb()) {
                $this->params['value_pool']['sql'][$this->getName()] = $value;
            }
        }

        $files = [];
        $value = $this->getValue();
        
        if ($value) {
            $value = trim($value, '"');
            $fileNames = explode(',', $value);
            
            foreach ($fileNames as $fileName) {
                $fileName = trim($fileName);
                if ($fileName && file_exists(rex_path::media($fileName))) {
                    $files[] = $fileName;
                }
            }
        }

        // Globale Einstellung für Meta-Dialog prüfen
        $alwaysShowMeta = rex_config::get('filepond_uploader', 'always_show_meta', false);
        $skipMeta = false;
        
        // Element-Einstellung hat höhere Priorität als globale Einstellung
        if ($this->getElement('skip_meta') !== null && !$alwaysShowMeta) {
            $skipMeta = (bool)$this->getElement('skip_meta');
        }
        
        // Session-Wert prüfen (hat höchste Priorität, außer bei always_show_meta)
        if (rex_session('filepond_no_meta') && !$alwaysShowMeta) {
            $skipMeta = true;
        }
        
        // Chunk-Upload-Einstellungen
        $enableChunks = rex_config::get('filepond_uploader', 'enable_chunks', true);
        $chunkSize = rex_config::get('filepond_uploader', 'chunk_size', 5) * 1024 * 1024;

        $this->params['form_output'][$this->getId()] = $this->parse('value.filepond.tpl.php', [
            'category_id' => $this->getElement('category') ?: rex_config::get('filepond_uploader', 'category_id', 0),
            'value' => $this->getValue(),
            'files' => $files,
            'chunk_enabled' => $enableChunks,
            'chunk_size' => $chunkSize,
            'skip_meta' => $skipMeta
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
                    'notice' => 'ID der Medienkategorie in die die Dateien geladen werden sollen',
                    'default' => (string)rex_config::get('filepond_uploader', 'category_id', 0)
                ],
                'allowed_types' => [
                    'type' => 'text',   
                    'label' => 'Erlaubte Dateitypen',
                    'notice' => 'z.B.: image/*,video/*,application/pdf',
                    'default' => rex_config::get('filepond_uploader', 'allowed_types', 'image/*')
                ],
                'allowed_filesize' => [
                    'type' => 'text',   
                    'label' => 'Maximale Dateigröße (MB)',
                    'notice' => 'Größe in Megabyte',
                    'default' => (string)rex_config::get('filepond_uploader', 'max_filesize', 10)
                ],
                'allowed_max_files' => [
                    'type' => 'text',   
                    'label' => 'Maximale Anzahl Dateien',
                    'default' => (string)rex_config::get('filepond_uploader', 'max_files', 10)
                ],
                'required' => ['type' => 'boolean', 'label' => 'Pflichtfeld', 'default' => '0'],
                'notice'   => ['type' => 'text',    'label' => rex_i18n::msg('yform_values_defaults_notice')],
                'empty_value'  => [
                    'type' => 'text',    
                    'label' => 'Fehlermeldung wenn leer',
                    'default' => 'Bitte eine Datei auswählen.'
                ],
                'skip_meta' => ['type' => 'checkbox',  'label' => 'Metaabfrage deaktivieren', 'default' => '0', 'options' => '0,1']
            ],
            'description' => 'Filepond Dateiupload mit Medienpool-Integration und Chunk-Upload',
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
