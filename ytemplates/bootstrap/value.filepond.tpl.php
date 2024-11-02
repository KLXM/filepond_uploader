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

<div class="<?= $class_group ?>" id="<?= $this->getHTMLId() ?>">
    <label class="control-label" for="<?= $this->getFieldId() ?>"><?= $this->getLabel() ?></label>
    
    <!-- Hidden field für den aktuellen Wert -->
    <input type="hidden" 
           name="<?= $this->getFieldName() ?>" 
           value="<?= $value ?>" />
    
    <!-- FilePond Upload-Feld -->
    <input type="file" 
           class="filepond"
           name="filepond"
           id="<?= $this->getFieldId() ?>"
           multiple
    />

    <?php if ($notice = $this->getElement('notice')): ?>
        <p class="help-block"><?= rex_i18n::translate($notice, false) ?></p>
    <?php endif ?>

    <?php if (isset($this->params['warning_messages'][$this->getId()]) && !$this->params['hide_field_warning_messages']): ?>
        <p class="help-block text-warning"><?= rex_i18n::translate($this->params['warning_messages'][$this->getId()], false) ?></p>
    <?php endif ?>
</div>

<!-- Metadata Form Template -->
<template id="metadata-form-template-<?= $this->getFieldId() ?>">
    <form class="metadata-form">
        <div class="row">
            <div class="col-md-5">
                <!-- Bildvorschau Container -->
                <div class="image-preview-container">
                    <img src="" alt="" class="img-responsive">
                </div>
                <!-- Dateiinfo -->
                <div class="file-info small text-muted"></div>
            </div>
            <div class="col-md-7">
                <div class="form-group">
                    <label for="title">Titel:</label>
                    <input type="text" class="form-control" name="title">
                </div>
                <div class="form-group">
                    <label for="alt">Alt-Text:</label>
                    <input type="text" class="form-control" name="alt">
                    <small class="form-text text-muted">Alternativtext für Screenreader und SEO</small>
                </div>
                <div class="form-group">
                    <label for="copyright">Copyright:</label>
                    <input type="text" class="form-control" name="copyright">
                </div>
                <div class="form-group mt-4">
                    <button type="submit" class="btn btn-save">Speichern</button>
                    <button type="button" class="btn btn-abort" data-dismiss="modal">Abbrechen</button>
                </div>
            </div>
        </div>
    </form>
</template>

<!-- FilePond JavaScript Initialization -->
<script>
$(document).on('rex:ready', function() {
    FilePond.registerPlugin(
        FilePondPluginFileValidateType,
        FilePondPluginFileValidateSize,
        FilePondPluginImagePreview
    );

    const input = document.querySelector('#<?= $this->getFieldId() ?>');
    const hiddenInput = input.previousElementSibling;
    const metadataTemplate = document.querySelector('#metadata-form-template-<?= $this->getFieldId() ?>');

    // Create metadata dialog
    const createMetadataDialog = (file, existingMetadata = null) => {
        return new Promise((resolve, reject) => {
            const dialog = document.createElement('div');
            dialog.className = 'modal fade';
            dialog.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">Metadaten für ${file.name || file.filename}</h4>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            ${metadataTemplate.innerHTML}
                        </div>
                    </div>
                </div>
            `;

            document.body.appendChild(dialog);
            const $dialog = $(dialog);

            // Bild in Vorschau laden
            const previewImage = async () => {
                const imgContainer = dialog.querySelector('.image-preview-container img');
                const fileInfo = dialog.querySelector('.file-info');
                
                try {
                    // Für neue Uploads
                    if (file instanceof File) {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            imgContainer.src = e.target.result;
                        };
                        reader.readAsDataURL(file);
                        
                        fileInfo.innerHTML = `
                            <strong>Datei:</strong> ${file.name}<br>
                            <strong>Größe:</strong> ${(file.size / 1024 / 1024).toFixed(2)} MB
                        `;
                    }
                    // Für existierende Dateien
                    else {
                        imgContainer.src = '/media/' + file.source;
                        
                        const mediaFile = await fetch('/media/' + file.source);
                        const size = mediaFile.headers.get('content-length');
                        fileInfo.innerHTML = `
                            <strong>Datei:</strong> ${file.source}<br>
                            <strong>Größe:</strong> ${(size / 1024 / 1024).toFixed(2)} MB
                        `;
                    }
                } catch (error) {
                    console.error('Error loading preview:', error);
                    imgContainer.src = '';
                }
            };

            previewImage();

            // Fill existing metadata if available
            if (existingMetadata) {
                dialog.querySelector('[name="title"]').value = existingMetadata.title || '';
                dialog.querySelector('[name="alt"]').value = existingMetadata.alt || '';
                dialog.querySelector('[name="copyright"]').value = existingMetadata.copyright || '';
            }

            // Handle form submit
            dialog.querySelector('form').addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);
                const metadata = {
                    title: formData.get('title'),
                    alt: formData.get('alt'),
                    copyright: formData.get('copyright')
                };
                $dialog.data('submitted', true);
                resolve(metadata);
                $dialog.modal('hide');
            });

            // Bei Modal-Close oder Abbrechen Upload abbrechen
            $dialog.on('hidden.bs.modal', function() {
                if (!$dialog.data('submitted')) {
                    reject(new Error('Upload cancelled'));
                }
                document.body.removeChild(dialog);
            });

            $dialog.modal('show');
        });
    };

    // Create FilePond instance
    const pond = FilePond.create(input, {
        files: <?= json_encode($existingFiles) ?>,
        allowMultiple: true,
        allowReorder: true,
        server: {
            url: 'index.php',
            process: async (fieldName, file, metadata, load, error, progress, abort, transfer, options) => {
                try {
                    const fileMetadata = await createMetadataDialog(file);
                    
                    const formData = new FormData();
                    formData.append(fieldName, file);
                    formData.append('rex-api-call', 'filepond_uploader');
                    formData.append('func', 'upload');
                    formData.append('category_id', <?= $this->getElement('category') ?: 1 ?>);
                    formData.append('metadata', JSON.stringify(fileMetadata));

                    const response = await fetch('index.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });

                    if (!response.ok) throw new Error('Upload failed');

                    const result = await response.json();
                    load(result);
                } catch (err) {
                    console.error('Upload cancelled:', err);
                    error('Upload cancelled');
                    abort();
                }
            },
            revert: {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                ondata: (formData) => {
                    formData.append('rex-api-call', 'filepond_uploader');
                    formData.append('func', 'delete');
                    formData.append('filename', formData.get('serverId'));
                    return formData;
                }
            },
            load: (source, load) => {
                fetch('/media/' + source)
                    .then(response => response.blob())
                    .then(load);
            }
        },
        labelIdle: 'Dateien hierher ziehen oder <span class="filepond--label-action">durchsuchen</span>',
        maxFiles: <?= $this->getElement('allowed_max_files') ?: 'null' ?>,
        acceptedFileTypes: <?= json_encode(explode(',', $this->getElement('allowed_types') ?: 'image/*')) ?>,
        maxFileSize: '<?= $this->getElement('allowed_filesize') ?: 10 ?>MB'
    });

    // Update hidden input helper
    const updateHiddenInput = () => {
        const files = pond.getFiles().map(file => file.serverId || file.source);
        hiddenInput.value = files.join(',');
    };

    // Event handlers
    pond.on('processfile', (error, file) => {
        if (!error && file.serverId) {
            updateHiddenInput();
        }
    });

    pond.on('removefile', (error, file) => {
        if (!error) {
            updateHiddenInput();
        }
    });

    pond.on('reorderfiles', () => {
        updateHiddenInput();
    });
});
</script>

<!-- Custom CSS -->
<style>
/* Modal Styles */
.metadata-form {
    padding: 15px;
}
.metadata-form .form-group {
    margin-bottom: 15px;
}

.image-preview-container img {
    max-height: 300px;
    width: auto;
    object-fit: contain;
}
.file-info {
    text-align: center;
    color: #6c757d;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
    margin-top: 10px;
}
.modal-lg {
    max-width: 900px;
}

/* Drag & Drop Styles */
.filepond--item {
    cursor: grab;
}
.filepond--item:active {
    cursor: grabbing;
}
.filepond--item.is-drag-over {
    border: 2px solid #2196F3;
    box-shadow: 0 0 10px rgba(33, 150, 243, 0.3);
}
.filepond--item.is-dragging {
    opacity: 0.8;
}

/* Hover Effects */
.filepond--item:hover .filepond--file-info {
    opacity: 1;
}
.filepond--item:hover .filepond--image-preview-wrapper {
    transform: scale(1.02);
    transition: transform 0.2s ease;
}

/* REDAXO specific styles */
.btn-save {
    background: #3bb594;
    color: white;
}
.btn-save:hover {
    background: #318c73;
    color: white;
}
.btn-abort {
    background: #c74343;
    color: white;
}
.btn-abort:hover {
    background: #9c3535;
    color: white;
}
</style>