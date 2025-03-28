<?php
$class       = $this->getElement('required') ? 'form-is-required ' : '';
$class_group = trim('form-group ' . $class . $this->getWarningClass());

// Value bereinigen
$value = str_replace(['"', ' '], '', $this->getValue() ?: '');
$fileNames = array_filter(explode(',', $value));

// Existierende Dateien für FilePond vorbereiten
$existingFiles = [];
foreach ($fileNames as $fileName) {
    if (file_exists(rex_path::media($fileName))) {
        $media = rex_media::get($fileName);
        if ($media) {
            $existingFiles[] = [
                'source' => $fileName,
                'options' => [
                    'type' => 'local',
                    'metadata' => [
                        'title' => $media->getValue('title'),
                        'alt' => $media->getValue('med_alt'),
                        'copyright' => $media->getValue('med_copyright'),
                        'description' => $media->getValue('med_description')
                    ]
                ]
            ];
        }
    }
}

$currentUser = rex::getUser();
$langCode = $currentUser ? $currentUser->getLanguage() : rex_config::get('filepond_uploader', 'lang', 'en_gb');

?>
<div class="<?= $class_group ?>" id="<?= $this->getHTMLId() ?>">
    <label class="control-label" for="<?= $this->getFieldId() ?>"><?= $this->getLabel() ?></label>
    
    <input type="hidden" 
       name="<?= $this->getFieldName() ?>" 
       value="<?= $value ?>"
       data-widget="filepond"
       data-filepond-cat="<?= ($this->getElement('category') === '0' || $this->getElement('category')) ? $this->getElement('category') : rex_config::get('filepond_uploader', 'category_id', 0) ?>"
       data-filepond-maxfiles="<?= $this->getElement('allowed_max_files') ?: rex_config::get('filepond_uploader', 'max_files', 30) ?>"
       data-filepond-types="<?= $this->getElement('allowed_types') ?: rex_config::get('filepond_uploader', 'allowed_types', 'image/*') ?>"
       data-filepond-maxsize="<?= $this->getElement('allowed_filesize') ?: rex_config::get('filepond_uploader', 'max_filesize', 10) ?>"
       data-filepond-lang="<?= $langCode ?>"
       data-filepond-skip-meta="<?= $skip_meta ? 'true' : 'false' ?>"
       data-filepond-chunk-enabled="<?= $chunk_enabled ? 'true' : 'false' ?>"
       data-filepond-chunk-size="<?= $chunk_size ?>"
    />

    <?php if ($notice = $this->getElement('notice')): ?>
        <p class="help-block small"><?= rex_i18n::translate($notice, false) ?></p>
    <?php endif ?>

    <?php if (isset($this->params['warning_messages'][$this->getId()]) && !$this->params['hide_field_warning_messages']): ?>
        <p class="help-block text-warning small"><?= rex_i18n::translate($this->params['warning_messages'][$this->getId()], false) ?></p>
    <?php endif ?>
</div>
