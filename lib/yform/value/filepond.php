<?php

/**
 * yform
 * @author Your Name
 */

class rex_yform_value_filepond extends rex_yform_value_abstract
{
    public function enterObject()
    {
        rex_login::startSession();

        // Unique Key für Upload-Verzeichnis
        if(rex::isBackend()) {
            $fields = array_column($this->obj, NULL, 'name');
            $uniqueKey = $fields['order_id']->value;                
        } else if (rex::isFrontend()) { 
            $uniqueKey = rex_session("filepond");
        }

        // Backend Download
        if (rex::isBackend() && rex_request('filepond_download', 'string', false) && in_array(rex_request('filepond_download', 'string'), explode(",",$this->getValue()))) {
            $this->filepond_download(rex_request('filepond_download', 'string'));
        }

        // Upload-Limits in Session speichern für API-Validierung
        $session[$this->params['form_wrap_id']][$this->getFieldId()][$uniqueKey]["allowed_types"] = $this->getElement('allowed_types');
        $session[$this->params['form_wrap_id']][$this->getFieldId()][$uniqueKey]["allowed_filesize"] = $this->getElement('allowed_filesize') * 1024 * 1024;
        rex_set_session('rex_yform_filepond', $session);

        // FilePond Feld ausgeben
        if(rex::isFrontend()) {
            $this->params['form_output'][$this->getId()] = $this->parse('value.filepond.tpl.php', ['uniqueKey' => $uniqueKey]);
        }

        // Hochgeladene Dateien verarbeiten
        $server_upload_path = rex_path::pluginData('yform', 'manager', 'upload/filepond/'.$this->params['form_wrap_id'].'/'.$this->getFieldId().'/'.$uniqueKey.'/');

        if(is_dir($server_upload_path)) {
            $uploaded_files = array_filter(scandir($server_upload_path), function($item) use ($server_upload_path) {
                $file = $server_upload_path . $item;
                return !is_dir($file);
            });

            $path = '/'.$this->params['form_wrap_id'].'/'.$this->getFieldId().'/'.$uniqueKey.'/';
            $value = $path.implode(",".$path,$uploaded_files); 
        }
        
        $this->params['value_pool']['email'][$this->getName()] = $value ?? "";

        if ($this->saveInDb()) {
            $this->params['value_pool']['sql'][$this->getName()] = $value ?? "";
        }
    }

    public function getDescription(): string
    {
        return 'filepond|name|label|allowed_types|allowed_filesize|allowed_max_files|required|notice';
    }

    public function getDefinitions(): array
    {
        return [
            'type' => 'value',
            'name' => 'filepond',
            'values' => [
                'name' => ['type' => 'name', 'label' => rex_i18n::msg('yform_values_defaults_name')],
                'label' => ['type' => 'text', 'label' => rex_i18n::msg('yform_values_defaults_label')],
                'allowed_types' => ['type' => 'text', 'default' => ".pdf", 'label' => rex_i18n::msg('yform_values_filepond_types')],
                'allowed_filesize' => ['type' => 'text', 'default' => "10", 'label' => rex_i18n::msg('yform_values_filepond_filesize')],
                'allowed_max_files' => ['type' => 'text', 'default' => "10", 'label' => rex_i18n::msg('yform_values_filepond_allowed_max_files')],
                'required' => ['type' => 'boolean', 'label' => rex_i18n::msg('yform_values_filepond_required')],
                'notice' => ['type' => 'text', 'label' => rex_i18n::msg('yform_values_defaults_notice')]
            ],
            'description' => rex_i18n::msg('yform_values_filepond_description'),
            'dbtype' => 'text'
        ];
    }

    public static function getListValue($params)
    {
        $files = explode(",",$params['subject']);
        $downloads = [];

        if (rex::isBackend()) {
            foreach ($files as $file) {
                $field = new rex_yform_manager_field($params['params']['field']);
                if ($files != []) {
                    $file_name = array_pop(explode("/",$file));
                    $downloads[] = '<a href="/redaxo/index.php?page=yform/manager/data_edit&table_name='.$field->getElement('table_name').'&data_id='.$params['list']->getValue('id').'&func=edit&filepond_download='.urlencode($file).'" title="'.rex_escape($file_name).'">'.rex_escape($file_name).'</a>';
                }
            }
            $return = implode("<br />", $downloads);
        } else {
            $return = $params['subject'];
        }

        return $return;
    }

    // Download Funktion
    public static function filepond_download($file)  
    {
        $filename = array_pop(explode("/", $file));
        $filepath = rex_path::pluginData('yform', 'manager', 'upload/filepond/'.$file);
        
        if (file_exists($filepath)) {
            ob_end_clean();
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename='.$filename);
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        }
    }
}
