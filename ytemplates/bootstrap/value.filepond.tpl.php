<?php
$class       = $this->getElement('required') ? 'form-is-required ' : '';
$class_group = trim('form-group ' . $class . $this->getWarningClass());

// Value bereinigen
$value = str_replace(['"', ' '], '', $this->getValue());
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
                        'copyright' => $media->getValue('med_copyright')
                    ]
                ]
            ];
        }
    }
}
?>

<?php 
if ($currentUser) {
    $langCode = $currentUser->getLanguage();
} 
?>

<div class="<?= $class_group ?>" id="<?= $this->getHTMLId() ?>">
    <label class="control-label" for="<?= $this->getFieldId() ?>"><?= $this->getLabel() ?></label>
    
    <input type="hidden" 
           name="<?= $this->getFieldName() ?>" 
           value="<?= $value ?>"
           data-widget="filepond"
           data-filepond-cat="<?= $this->getElement('category') ?: '1' ?>"
           data-filepond-maxfiles="<?= $this->getElement('allowed_max_files') ?: '20' ?>"
           data-filepond-types="<?= $this->getElement('allowed_types') ?: 'image/*' ?>"
           data-filepond-maxsize="<?= $this->getElement('allowed_filesize') ?: '20' ?>"
           data-filepond-lang="<?= $langCode ?>"
    />

    <?php if ($notice = $this->getElement('notice')): ?>
        <p class="help-block small"><?= rex_i18n::translate($notice, false) ?></p>
    <?php endif ?>

    <?php if (isset($this->params['warning_messages'][$this->getId()]) && !$this->params['hide_field_warning_messages']): ?>
        <p class="help-block text-warning small"><?= rex_i18n::translate($this->params['warning_messages'][$this->getId()], false) ?></p>
    <?php endif ?>
</div>