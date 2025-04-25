<?php

class rex_yform_action_filepond2email extends rex_yform_action_abstract {

    public function executeAction(): void
    {
        $label_from = $this->getElement(2);

        foreach ($this->params['value_pool']['email'] as $key => $value) {
            if ($label_from == $key) {
                foreach (explode(',',$value) as $filename) {
                    $this->params['value_pool']['email_attachments'][] = [$filename, rex_path::media().$filename];
                }
                break;
            }
        }
    }

    public function getDescription(): string
    {
        return 'action|filepond2email|label_from';
    }

}

?>