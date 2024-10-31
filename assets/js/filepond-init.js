// Register FilePond plugins
FilePond.registerPlugin(
    FilePondPluginFileValidateType,
    FilePondPluginFileValidateSize,
    FilePondPluginImagePreview
);

// Set default German language
FilePond.setOptions({
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

class YFormFilePond {
    static init() {
        document.querySelectorAll('input[type="file"].filepond').forEach(element => {
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
                        ondata: (formData) => {
                            formData.append('rex-api-call', 'yform_filepond');
                            formData.append('func', 'delete');
                            formData.append('formId', element.dataset.fpFormId);
                            formData.append('fieldId', element.dataset.fpFieldId);
                            formData.append('uniqueKey', element.dataset.fpUniqueKey);
                            formData.append('serverId', formData.get('serverId'));
                            return formData;
                        }
                    }
                },
                acceptedFileTypes: element.dataset.fpAcceptedFileTypes?.split(',') || null,
                maxFiles: parseInt(element.dataset.fpMaxFiles) || null,
                maxFileSize: element.dataset.fpMaxFileSize || null,
            });
        });
    }
}

// Initialize on DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
    YFormFilePond.init();
});
