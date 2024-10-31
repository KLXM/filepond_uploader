<?php
$class       = $this->getElement('required') ? 'form-is-required ' : '';
$class_group = trim('form-group ' . $class . $this->getWarningClass());
?>

<div class="<?php echo $class_group ?>" id="<?php echo $this->getHTMLId() ?>">
    <label for="<?= $this->getFieldName() ?>"><?= $this->getElement('label') ?></label>
    
    <!-- FilePond Input -->
    <input type="file" 
           class="filepond"
           name="<?= $this->getFieldName() ?>"
           id="<?= $this->getFieldId() ?>"
           multiple
           data-fp-form-id="<?= $this->params['form_wrap_id'] ?>"
           data-fp-field-id="<?= $this->getFieldId() ?>"
           data-fp-unique-key="<?= $uniqueKey ?>"
           data-fp-max-files="<?= $this->getElement('allowed_max_files') ?>"
           data-fp-accepted-file-types="<?= $this->getElement('allowed_types') ?>"
           data-fp-max-file-size="<?= $this->getElement('allowed_filesize') ?>MB"
    >

    <!-- Notice-Feld -->
    <?php
    $notice = [];
    if ($this->getElement('notice') != '') {
        $notice[] = rex_i18n::translate($this->getElement('notice'), false);
    }
    if (isset($this->params['warning_messages'][$this->getId()]) && !$this->params['hide_field_warning_messages']) {
        $notice[] = '<span class="text-warning">' . rex_i18n::translate($this->params['warning_messages'][$this->getId()], false) . '</span>';
    }
    if (count($notice) > 0) {
        $notice = '<p class="help-block">' . implode('<br />', $notice) . '</p>';
    } else {
        $notice = '';
    }
    echo $notice;
    ?>
</div>

<!-- FilePond CSS -->
<link href="https://unpkg.com/filepond/dist/filepond.css" rel="stylesheet">
<link href="https://unpkg.com/filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css" rel="stylesheet">

<!-- FilePond Plugins -->
<script src="https://unpkg.com/filepond-plugin-file-validate-type/dist/filepond-plugin-file-validate-type.js"></script>
<script src="https://unpkg.com/filepond-plugin-file-validate-size/dist/filepond-plugin-file-validate-size.js"></script>
<script src="https://unpkg.com/filepond-plugin-image-preview/dist/filepond-plugin-image-preview.js"></script>

<!-- FilePond Library -->
<script src="https://unpkg.com/filepond/dist/filepond.js"></script>

<!-- FilePond Initialisierung -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Register plugins
    FilePond.registerPlugin(
        FilePondPluginFileValidateType,
        FilePondPluginFileValidateSize,
        FilePondPluginImagePreview
    );

    // Get all filepond elements
    document.querySelectorAll('.filepond').forEach(function(element) {
        const pond = FilePond.create(element, {
            server: {
                process: {
                    url: 'index.php',
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    withCredentials: false,
                    ondata: (formData) => {
                        formData.append('rex-api-call', 'yform_filepond');
                        formData.append('func', 'upload');
                        formData.append('formId', element.dataset.fpFormId);
                        formData.append('fieldId', element.dataset.fpFieldId);
                        formData.append('uniqueKey', element.dataset.fpUniqueKey);
                        return formData;
                    }
                },
                revert: {
                    url: 'index.php',
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    ondata: (formData) => {
                        formData.append('rex-api-call', 'yform_filepond');
                        formData.append('func', 'delete');
                        formData.append('formId', element.dataset.fpFormId);
                        formData.append('fieldId', element.dataset.fpFieldId);
                        formData.append('uniqueKey', element.dataset.fpUniqueKey);
                        return formData;
                    }
                },
                load: null,
                restore: null,
                fetch: null
            },

            // Validation
            acceptedFileTypes: element.dataset.fpAcceptedFileTypes.split(','),
            maxFiles: parseInt(element.dataset.fpMaxFiles),
            maxFileSize: element.dataset.fpMaxFileSize,

            // Labels
            labelIdle: 'Dateien hierher ziehen oder <span class="filepond--label-action">durchsuchen</span>',
            labelInvalidField: 'Ungültiges Feld',
            labelFileWaitingForSize: 'Warte auf Größe',
            labelFileSizeNotAvailable: 'Größe nicht verfügbar',
            labelFileLoading: 'Laden',
            labelFileLoadError: 'Fehler beim Laden',
            labelFileProcessing: 'Hochladen',
            labelFileProcessingComplete: 'Upload abgeschlossen',
            labelFileProcessingAborted: 'Upload abgebrochen',
            labelFileProcessingError: 'Fehler beim Upload',
            labelFileProcessingRevertError: 'Fehler beim Entfernen',
            labelFileRemoveError: 'Fehler beim Entfernen',
            labelTapToCancel: 'zum Abbrechen antippen',
            labelTapToRetry: 'zum Wiederholen antippen',
            labelTapToUndo: 'zum Rückgängig machen antippen',
            labelButtonRemoveItem: 'Entfernen',
            labelButtonAbortItemLoad: 'Abbrechen',
            labelButtonRetryItemLoad: 'Wiederholen',
            labelButtonAbortItemProcessing: 'Abbrechen',
            labelButtonUndoItemProcessing: 'Rückgängig machen',
            labelButtonRetryItemProcessing: 'Wiederholen',
            labelButtonProcessItem: 'Upload'
        });
    });
});
</script>
