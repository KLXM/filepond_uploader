<?php

use rex_config;
use rex_extension;
use rex_extension_point;
use rex_logger;
use rex_media;
use rex_media_manager;
use rex_media_service;
use rex_sql;
use rex_yform_manager_table;

class rex_yform_value_filepond extends rex_yform_value_abstract
{
    protected static function cleanValue($value)
    {
        return implode(',', array_filter(array_map('trim', explode(',', str_replace('"', '', $value))), 'strlen'));
    }

    // Hilfsfunktion, die Original-Dateinamen zu Medienpool-Dateinamen zuordnet
    protected static function getMediapoolFilename($originalFilename) {
        $sql = rex_sql::factory();
        $result = $sql->getArray('SELECT filename FROM ' . rex::getTable('media') . ' WHERE originalname = ?', [$originalFilename]);
        
        if (count($result) > 0) {
            // Wenn mehrere Dateien mit dem gleichen Originalnamen existieren, 
            // nehmen wir die neueste (höchste ID)
            $sql->setQuery('SELECT filename FROM ' . rex::getTable('media') . ' WHERE originalname = ? ORDER BY id DESC LIMIT 1', [$originalFilename]);
            return $sql->getValue('filename');
        }
        
        return $originalFilename; // Fallback auf den Originalnamen
    }

    public function preValidateAction(): void
    {
        // Nur wenn Auto-Cleanup aktiviert ist
        if (!rex_config::get('filepond_uploader', 'auto_cleanup_enabled', 0)) {
            return;
        }
        
        if (!isset($this->params['send']) || !$this->params['send']) {
            return;
        }
        
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
            
            if (!empty($deletedFiles)) {
                if (rex::isDebugMode() && rex_config::get('filepond_uploader', 'enable_debug_logging', false)) {
                    rex_logger::factory()->log('debug', sprintf(
                        'FilePond Auto-Cleanup: %d Datei(en) gelöscht aus Feld "%s" in Tabelle "%s" (ID: %s)',
                        count($deletedFiles),
                        $this->getName(),
                        $this->params['main_table'] ?? 'unknown',
                        $this->params['main_id'] ?? 'unknown'
                    ));
                }
            }

            foreach ($deletedFiles as $filename) {
                try {
                    $media = rex_media::get($filename);
                    if (!$media) {
                        if (rex::isDebugMode() && rex_config::get('filepond_uploader', 'enable_debug_logging', false)) {
                            rex_logger::factory()->log('debug', sprintf(
                                'FilePond Auto-Cleanup: Datei "%s" nicht im Mediapool gefunden',
                                $filename
                            ));
                        }
                        continue;
                    }
                    
                    if (rex::isDebugMode() && rex_config::get('filepond_uploader', 'enable_debug_logging', false)) {
                        rex_logger::factory()->log('debug', sprintf(
                            'FilePond Auto-Cleanup: Prüfe Datei "%s" auf Verwendung',
                            $filename
                        ));
                    }
                    
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
                                            if (rex::isDebugMode() && rex_config::get('filepond_uploader', 'enable_debug_logging', false)) {
                                                rex_logger::factory()->log('debug', sprintf(
                                                    'FilePond Auto-Cleanup: Datei "%s" noch in Verwendung in Tabelle "%s"',
                                                    $filename,
                                                    $tableName
                                                ));
                                            }
                                            break 2;
                                        }
                                    } catch (Exception $e) {
                                        rex_logger::factory()->log('warning', sprintf(
                                            'FilePond Auto-Cleanup: Query-Fehler bei Tabelle "%s": %s',
                                            $tableName,
                                            $e->getMessage()
                                        ));
                                        continue;
                                    }
                                }
                            }
                        }

                        // Extension Point: MEDIA_IS_IN_USE prüfen
                        if (!$inUse) {
                            $warnings = rex_extension::registerPoint(new rex_extension_point(
                                'MEDIA_IS_IN_USE',
                                [],
                                [
                                    'filename' => $filename,
                                    'media' => $media,
                                    'ignore_table' => $this->params['main_table'] ?? '',
                                    'ignore_id' => (int)($this->params['main_id'] ?? 0),
                                    'ignore_field' => $this->getName(),
                                ]
                            ));
                            
                            if (is_array($warnings) && !empty($warnings)) {
                                $inUse = true;
                                rex_logger::factory()->log('debug', sprintf(
                                    'FilePond Auto-Cleanup: Datei "%s" noch in Verwendung (Extension Point)',
                                    $filename
                                ));
                            }
                        }
                        
                        // Datei löschen wenn sie nicht mehr verwendet wird
                        if (!$inUse && rex_media::get($filename)) {
                            rex_logger::factory()->log('debug', sprintf(
                                'FilePond Auto-Cleanup: Lösche Datei "%s"',
                                $filename
                            ));
                            
                            // Workaround: rex_media_service::deleteMedia() ruft intern mediaIsInUse() auf
                            // ohne ignore-Parameter. Daher setzen wir die Info in $GLOBALS
                            $GLOBALS['filepond_cleanup_ignore'] = [
                                'table' => $this->params['main_table'] ?? '',
                                'id' => (int)($this->params['main_id'] ?? 0),
                                'field' => $this->getName(),
                            ];
                            
                            try {
                                rex_media_service::deleteMedia($filename);
                                
                                rex_logger::factory()->log('info', sprintf(
                                    'FilePond Auto-Cleanup: Datei "%s" aus Tabelle "%s" (ID: %s) gelöscht.',
                                    $filename,
                                    $this->params['main_table'] ?? 'unknown',
                                    $this->params['main_id'] ?? 'unknown'
                                ));
                            } finally {
                                // Cleanup
                                unset($GLOBALS['filepond_cleanup_ignore']);
                            }
                        } else {
                            if (rex::isDebugMode() && rex_config::get('filepond_uploader', 'enable_debug_logging', false)) {
                                rex_logger::factory()->log('debug', sprintf(
                                    'FilePond Auto-Cleanup: Datei "%s" NICHT gelöscht (inUse: %s)',
                                    $filename,
                                    $inUse ? 'true' : 'false'
                                ));
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Fehler beim Löschen werden geloggt aber ignoriert
                    rex_logger::factory()->log('warning', sprintf(
                        'FilePond Auto-Cleanup: Fehler beim Löschen von "%s": %s',
                        $filename,
                        $e->getMessage()
                    ));
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

            // Hier konvertieren wir Original-Dateinamen in Medienpool-Dateinamen
            if ($value) {
                $fileNames = array_filter(explode(',', self::cleanValue($value)));
                $convertedFileNames = [];
                
                foreach ($fileNames as $fileName) {
                    // Prüfen ob es sich um einen Original-Dateinamen handelt,
                    // der im Medienpool anders heißt
                    if (!file_exists(rex_path::media($fileName))) {
                        $mediaFileName = self::getMediapoolFilename($fileName);
                        if ($mediaFileName !== $fileName) {
                            $convertedFileNames[] = $mediaFileName;
                            continue;
                        }
                    }
                    $convertedFileNames[] = $fileName;
                }
                
                // Wenn Dateinamen konvertiert wurden, setzen wir den neuen Wert
                if (count($convertedFileNames) > 0) {
                    $value = implode(',', $convertedFileNames);
                }
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
        
        // Verzögerter Upload-Modus
        $delayedUpload = $this->getElement('delayed_upload');

        $this->params['form_output'][$this->getId()] = $this->parse('value.filepond.tpl.php', [
            'category_id' => $this->getElement('category') ?: rex_config::get('filepond_uploader', 'category_id', 0),
            'value' => $this->getValue(),
            'files' => $files,
            'chunk_enabled' => $enableChunks,
            'chunk_size' => $chunkSize,
            'skip_meta' => $skipMeta,
            'delayed_upload' => $delayedUpload
        ]);
    }

    public function getDescription(): string
    {
        return 'filepond|name|label|category|allowed_types[MIME-Types oder Dateiendungen]|allowed_filesize|allowed_max_files|required|notice|error_msg_empty|skip_meta[0,1]|delayed_upload[0,1,2]|title_required[0,1]
        
        Parameter-Details:
        - title_required[0,1]: Wenn auf 1 gesetzt, muss der Benutzer für jede hochgeladene Datei einen Titel angeben. Bei 0 ist der Titel optional.
        - delayed_upload[0,1,2]: 0=Sofortiger Upload, 1=Upload-Button, 2=Upload beim Formular-Submit';
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
                    'notice' => 'MIME-Types (z.B.: image/*,video/*,application/pdf) oder Dateiendungen (.pdf,.doc,.docx) - beide Formate können gemischt werden',
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
                'skip_meta' => ['type' => 'checkbox',  'label' => 'Metaabfrage deaktivieren', 'default' => '0', 'options' => '0,1'],
                'delayed_upload' => [
                    'type' => 'choice',  
                    'label' => 'Verzögerter Upload-Modus',
                    'choices' => ['0' => 'Deaktiviert', '1' => 'Upload-Button', '2' => 'Submit-Button'], 
                    'notice' => 'Dateien werden erst nach Klick auf den Upload-Button oder nach Formular Übermittlung hochgeladen',
                    'default' => '0', 
                ],
                'title_required' => [
                    'type' => 'checkbox',  
                    'label' => 'Titel-Feld als Pflichtfeld',
                    'choices' => ['0' => 'Nein', '1' => 'Ja'], 
                    'notice' => 'Wenn aktiviert, wird das title Feld im Metadaten-Dialog als Pflichtfeld markiert',
                    'default' => '0'
                ]
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
        
        if (empty($files)) {
            return '-';
        }
        
        $fileCount = count($files);
        
        // Nur eine Datei: Zeige Icon/Thumbnail + Dateiname
        if ($fileCount === 1) {
            $filename = trim($files[0]);
            $media = rex_media::get($filename);
            
            if (!$media) {
                return '<span style="color: #999;"><i class="fa fa-ban"></i> ' . rex_escape($filename) . ' (nicht gefunden)</span>';
            }
            
            $url = rex_url::backendPage('mediapool/detail', ['file_name' => $filename]);
            
            // Bei Bildern: Thumbnail (SVG direkt, andere über Media Manager)
            if ($media->isImage()) {
                $extension = mb_strtolower($media->getExtension());
                
                if ($extension === 'svg') {
                    // SVG direkt ausgeben (Vektorgrafik benötigt keinen Media Manager)
                    $imageUrl = $media->getUrl();
                } elseif (rex_addon::get('media_manager')->isAvailable()) {
                    // Andere Bilder über Media Manager
                    $imageUrl = rex_media_manager::getUrl('rex_media_small', $filename);
                } else {
                    $imageUrl = null;
                }
                
                if ($imageUrl) {
                    $ext = mb_strtoupper($media->getExtension());
                    $title = $media->getTitle();
                    $displayText = !empty($title) ? $title : $ext . ' - 1 Datei';
                    return '<span style="display: inline-flex; align-items: center;" title="' . rex_escape($filename) . '">' .
                           '<img src="' . $imageUrl . '" class="img-thumbnail" style="width: 40px; height: 40px; margin-right: 5px;" />' .
                           '<span>' . rex_escape($displayText) . '</span>' .
                           '</span>';
                }
            }
            
            // Für andere Dateitypen: Font Awesome Icon
            $extension = $media->getExtension();
            $icon = self::getFileIcon($extension);
            $extUpper = mb_strtoupper($extension);
            $title = $media->getTitle();
            $displayText = !empty($title) ? $title : $extUpper . ' - 1 Datei';
            
            return '<span style="display: inline-flex; align-items: center;" title="' . rex_escape($filename) . '">' .
                   '<i class="fa ' . $icon . ' text-muted" style="font-size: 30px; width: 40px; text-align: center; margin-right: 5px;"></i>' .
                   '<span>' . rex_escape($displayText) . '</span>' .
                   '</span>';
        }
        
        // Mehrere Dateien: Kompakte Ansicht mit Badge
        $extensions = [];
        $imageCount = 0;
        $totalCount = 0;
        
        foreach ($files as $filename) {
            $filename = trim($filename);
            $media = rex_media::get($filename);
            
            if ($media) {
                $ext = mb_strtolower($media->getExtension());
                $extensions[$ext] = ($extensions[$ext] ?? 0) + 1;
                $totalCount++;
                
                if ($media->isImage()) {
                    $imageCount++;
                }
            }
        }
        
        // Extension-Liste erstellen
        $extList = [];
        foreach ($extensions as $ext => $count) {
            $extList[] = $count > 1 ? $ext . ' (×' . $count . ')' : $ext;
        }
        $extString = implode(', ', $extList);
        
        $title = $fileCount . ' Dateien: ' . $extString;
        
        // Icon basierend auf Datei-Typen wählen
        if ($imageCount > 0) {
            // Bilder enthalten (einzeln oder gemischt)
            $iconClass = 'fa-solid fa-images';
        } else {
            // Nur Dokumente
            $iconClass = 'fa-solid fa-folder';
        }
        
        $multiIcon = '<i class="' . $iconClass . ' text-muted" style="font-size: 30px; width: 40px; text-align: center; margin-right: 5px;"></i>';
        
        return '<span style="display: inline-flex; align-items: center;" title="' . rex_escape($title) . '">' .
               $multiIcon .
               '<strong>' . $fileCount . '</strong>&nbsp;' . ($fileCount === 1 ? 'Datei' : 'Dateien') .
               ' <small class="text-muted">(' . rex_escape($extString) . ')</small>' .
               '</span>';
    }
    
    /**
     * Gibt passendes Font Awesome Icon für Dateityp zurück
     */
    private static function getFileIcon(string $extension): string
    {
        $extension = mb_strtolower($extension);
        
        $iconMap = [
            // Dokumente
            'pdf' => 'fa-file-pdf-o',
            'doc' => 'fa-file-word-o',
            'docx' => 'fa-file-word-o',
            'xls' => 'fa-file-excel-o',
            'xlsx' => 'fa-file-excel-o',
            'ppt' => 'fa-file-powerpoint-o',
            'pptx' => 'fa-file-powerpoint-o',
            'txt' => 'fa-file-text-o',
            'rtf' => 'fa-file-text-o',
            'csv' => 'fa-file-text-o',
            
            // Bilder
            'jpg' => 'fa-file-image-o',
            'jpeg' => 'fa-file-image-o',
            'png' => 'fa-file-image-o',
            'gif' => 'fa-file-image-o',
            'svg' => 'fa-file-image-o',
            'webp' => 'fa-file-image-o',
            
            // Video
            'mp4' => 'fa-file-video-o',
            'mov' => 'fa-file-video-o',
            'avi' => 'fa-file-video-o',
            'webm' => 'fa-file-video-o',
            
            // Audio
            'mp3' => 'fa-file-audio-o',
            'wav' => 'fa-file-audio-o',
            'ogg' => 'fa-file-audio-o',
            
            // Archive
            'zip' => 'fa-file-archive-o',
            'rar' => 'fa-file-archive-o',
            'tar' => 'fa-file-archive-o',
            'gz' => 'fa-file-archive-o',
        ];
        
        return $iconMap[$extension] ?? 'fa-file-o';
    }
}
