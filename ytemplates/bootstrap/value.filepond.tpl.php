<?php
/** @var rex_yform_value_filepond $this */

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
                        'copyright' => $media->getValue('med_copyright')
                        // med_description Referenz wurde hier entfernt
                    ]
                ]
            ];
        }
    }
}

$currentUser = rex::getUser();
$langCodeVal = $currentUser ? $currentUser->getLanguage() : rex_config::get('filepond_uploader', 'lang', 'en_gb');
$langCode = is_string($langCodeVal) ? $langCodeVal : 'en_gb';

// Von YForm-parse() via extract() gesetzte Variablen sicher deklarieren
$skip_meta = isset($skip_meta) ? (bool) $skip_meta : false;
$chunk_enabled = isset($chunk_enabled) ? (bool) $chunk_enabled : false;
$chunk_size = isset($chunk_size) && is_numeric($chunk_size) ? (int) $chunk_size : 0;
$delayed_upload = isset($delayed_upload) && is_numeric($delayed_upload) ? (int) $delayed_upload : 0;

// Config-Werte typsicher extrahieren
$cfgCatId = $this->getElement('category');
$dataCatId = ($cfgCatId === '0' || $cfgCatId) ? (string) $cfgCatId : '';
if ($dataCatId === '') {
    $cfgCatIdVal = rex_config::get('filepond_uploader', 'category_id', 0);
    $dataCatId = is_numeric($cfgCatIdVal) ? (string) (int) $cfgCatIdVal : '0';
}
$cfgMaxFiles = $this->getElement('allowed_max_files');
$dataMaxFiles = $cfgMaxFiles ? (string) $cfgMaxFiles : '';
if ($dataMaxFiles === '') {
    $cfgMaxFilesVal = rex_config::get('filepond_uploader', 'max_files', 30);
    $dataMaxFiles = is_numeric($cfgMaxFilesVal) ? (string) (int) $cfgMaxFilesVal : '30';
}
$cfgTypes = $this->getElement('allowed_types');
$dataTypes = is_string($cfgTypes) && $cfgTypes !== '' ? $cfgTypes : '';
if ($dataTypes === '') {
    $cfgTypesVal = rex_config::get('filepond_uploader', 'allowed_types', 'image/*');
    $dataTypes = is_string($cfgTypesVal) ? $cfgTypesVal : 'image/*';
}
$cfgMaxSize = $this->getElement('allowed_filesize');
$dataMaxSize = $cfgMaxSize ? (string) $cfgMaxSize : '';
if ($dataMaxSize === '') {
    $cfgMaxSizeVal = rex_config::get('filepond_uploader', 'max_filesize', 10);
    $dataMaxSize = is_numeric($cfgMaxSizeVal) ? (string) (int) $cfgMaxSizeVal : '10';
}
$cfgClientMaxPixel = rex_config::get('filepond_uploader', 'client_max_pixel', '');
$cfgMaxPixel = rex_config::get('filepond_uploader', 'max_pixel', 2100);
$dataMaxPixel = is_scalar($cfgClientMaxPixel) && $cfgClientMaxPixel !== '' ? (string) $cfgClientMaxPixel : (is_numeric($cfgMaxPixel) ? (string) (int) $cfgMaxPixel : '2100');
$cfgClientQuality = rex_config::get('filepond_uploader', 'client_image_quality', '');
$cfgQuality = rex_config::get('filepond_uploader', 'image_quality', 90);
$dataQuality = is_scalar($cfgClientQuality) && $cfgClientQuality !== '' ? (string) $cfgClientQuality : (is_numeric($cfgQuality) ? (string) (int) $cfgQuality : '90');
$cfgCreateThumbs = rex_config::get('filepond_uploader', 'create_thumbnails', '');
$dataClientResize = (is_string($cfgCreateThumbs) && $cfgCreateThumbs === '|1|') ? 'true' : 'false';
$dataTitleRequired = $this->getElement('title_required') ? 'true' : 'false';
?>
<div class="<?= $class_group ?>" id="<?= $this->getHTMLId() ?>">
    <label class="control-label" for="<?= $this->getFieldId() ?>"><?= $this->getLabel() ?></label>
    
    <input type="hidden" 
       name="<?= $this->getFieldName() ?>" 
       value="<?= $value ?>"
       data-widget="filepond"
       data-filepond-cat="<?= $dataCatId ?>"
       data-filepond-maxfiles="<?= $dataMaxFiles ?>"
       data-filepond-types="<?= $dataTypes ?>"
       data-filepond-maxsize="<?= $dataMaxSize ?>"
       data-filepond-lang="<?= $langCode ?>"
       data-filepond-skip-meta="<?= $skip_meta ? 'true' : 'false' ?>"
       data-filepond-chunk-enabled="<?= $chunk_enabled ? 'true' : 'false' ?>"
       data-filepond-chunk-size="<?= $chunk_size ?>"
       data-filepond-delayed-upload="<?= (1 === $delayed_upload || 2 === $delayed_upload) ? 'true' : 'false' ?>"
       data-filepond-delayed-type="<?= $delayed_upload ?>"
       data-filepond-title-required="<?= $dataTitleRequired ?>" 
       data-filepond-max-pixel="<?= $dataMaxPixel ?>" 
       data-filepond-image-quality="<?= $dataQuality ?>" 
       data-filepond-client-resize="<?= $dataClientResize ?>"
    />
    
    <?php if ($notice = $this->getElement('notice')): ?>
        <p class="help-block small"><?= rex_i18n::translate($notice, false) ?></p>
    <?php endif ?>

    <?php if (isset($this->params['warning_messages'][$this->getId()]) && !$this->params['hide_field_warning_messages']): ?>
        <p class="help-block text-warning small"><?= rex_i18n::translate($this->params['warning_messages'][$this->getId()], false) ?></p>
    <?php endif ?>
</div>
