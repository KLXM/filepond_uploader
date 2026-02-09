<?php

class rex_yform_action_filepond2email extends rex_yform_action_abstract
{
    public function executeAction(): void
    {
        $label_from = $this->getElement(2);

        // Suche zuerst in email pool, dann in sql pool (für Datenbankfelder)
        $value = null;
        
        if (isset($this->params['value_pool']['email'][$label_from])) {
            $value = $this->params['value_pool']['email'][$label_from];
        } elseif (isset($this->params['value_pool']['sql'][$label_from])) {
            $value = $this->params['value_pool']['sql'][$label_from];
        }

        if ($value) {
            // Unterstütze sowohl komma-separierte Strings als auch JSON-Arrays
            $filenames = [];
            
            if (is_array($value)) {
                $filenames = $value;
            } elseif (is_string($value)) {
                // Prüfe ob JSON
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $filenames = $decoded;
                } else {
                    // Komma-separierter String
                    $filenames = explode(',', $value);
                }
            }

            // Bereinige und füge Anhänge hinzu
            foreach ($filenames as $filename) {
                $filename = trim($filename);
                if ($filename === '') {
                    continue;
                }
                
                $mediaPath = rex_path::media($filename);
                if (file_exists($mediaPath)) {
                    $this->params['value_pool']['email_attachments'][] = [$filename, $mediaPath];
                }
            }
        }
    }

    public function getDescription(): string
    {
        return 'action|filepond2email|label_from';
    }
}

