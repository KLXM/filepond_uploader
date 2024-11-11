$(document).on('rex:ready', function() {
    // Translations
    const translations = {
        de_de: {
            labelIdle: 'Dateien hierher ziehen oder <span class="filepond--label-action">durchsuchen</span>',
            metaTitle: 'Metadaten für',
            titleLabel: 'Titel:',
            altLabel: 'Alt-Text:',
            altNotice: 'Alternativtext für Screenreader und SEO',
            copyrightLabel: 'Copyright:',
            fileInfo: 'Datei',
            fileSize: 'Größe',
            saveBtn: 'Speichern',
            cancelBtn: 'Abbrechen'
        },
        en_gb: {
            labelIdle: 'Drag & Drop your files or <span class="filepond--label-action">Browse</span>',
            metaTitle: 'Metadata for',
            titleLabel: 'Title:',
            altLabel: 'Alt Text:',
            altNotice: 'Alternative text for screen readers and SEO',
            copyrightLabel: 'Copyright:',
            fileInfo: 'File',
            fileSize: 'Size',
            saveBtn: 'Save',
            cancelBtn: 'Cancel'
        }
    };

    FilePond.registerPlugin(
        FilePondPluginFileValidateType,
        FilePondPluginFileValidateSize,
        FilePondPluginImagePreview
    );

    $('input[data-widget="filepond"]').each(function() {
        const input = $(this);
        const lang = input.data('filepondLang') || 'en_gb';
        const t = translations[lang] || translations['en_gb'];
        
        let rawValue = input.val();
        let initialValue = rawValue.trim();
        
        input.hide();

        const fileInput = $('<input type="file" multiple/>');
        fileInput.insertAfter(input);
        // Create metadata dialog with preview
        const createMetadataDialog = (file, existingMetadata = null) => {
            return new Promise((resolve, reject) => {
                const dialog = $(`
                    <div class="modal fade">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h4 class="modal-title">${t.metaTitle} ${file.name || file.filename}</h4>
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                </div>
                                <div class="modal-body">
                                    <form class="metadata-form">
                                        <div class="row">
                                            <div class="col-md-5">
                                                <div class="media-preview-container">
                                                    <!-- Media preview will be inserted here -->
                                                </div>
                                                <div class="file-info small text-muted"></div>
                                            </div>
                                            <div class="col-md-7">
                                                <div class="form-group">
                                                    <label for="title">${t.titleLabel}</label>
                                                    <input type="text" class="form-control" name="title" required>
                                                </div>
                                                <div class="form-group">
                                                    <label for="alt">${t.altLabel}</label>
                                                    <input type="text" class="form-control" name="alt" required>
                                                    <small class="form-text text-muted">${t.altNotice}</small>
                                                </div>
                                                <div class="form-group">
                                                    <label for="copyright">${t.copyrightLabel}</label>
                                                    <input type="text" class="form-control" name="copyright">
                                                </div>
                                                <div class="form-group mt-4">
                                                    <button type="submit" class="btn btn-save">${t.saveBtn}</button>
                                                    <button type="button" class="btn btn-abort" data-dismiss="modal">${t.cancelBtn}</button>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                `);

                const previewContainer = dialog.find('.media-preview-container');

                const previewMedia = async () => {
                    try {
                        if (file instanceof File) {
                            if (file.type.startsWith('image/')) {
                                const img = document.createElement('img');
                                img.src = URL.createObjectURL(file);
                                img.alt = file.name;
                                img.style.maxHeight = '200px';
                                previewContainer.append(img);
                            } else if (file.type.startsWith('video/')) {
                                const video = document.createElement('video');
                                video.src = URL.createObjectURL(file);
                                video.controls = true;
                                video.muted = true;
                                video.style.maxHeight = '200px';
                                previewContainer.append(video);
                            } else if (file.type.startsWith('application/pdf')) {
                                const pdfIcon = $('<i class="fas fa-file-pdf fa-3x"></i>');
                                previewContainer.append(pdfIcon);
                            } else {
                                const docIcon = $('<i class="fas fa-file fa-3x"></i>');
                                previewContainer.append(docIcon);
                            }
                        } else {
                            if (file.type.startsWith('image/')) {
                                const img = document.createElement('img');
                                img.src = '/media/' + file.source;
                                img.alt = file.source;
                                img.style.maxHeight = '200px';
                                previewContainer.append(img);
                            } else if (file.type.startsWith('video/')) {
                                const video = document.createElement('video');
                                video.src = '/media/' + file.source;
                                video.controls = true;
                                video.muted = true;
                                video.style.maxHeight = '200px';
                                previewContainer.append(video);
                            } else if (file.type.startsWith('application/pdf')) {
                                const pdfIcon = $('<i class="fas fa-file-pdf fa-3x"></i>');
                                previewContainer.append(pdfIcon);
                            } else {
                                const docIcon = $('<i class="fas fa-file fa-3x"></i>');
                                previewContainer.append(docIcon);
                            }
                        }
                    } catch (error) {
                        console.error('Error loading preview:', error);
                        previewContainer.html('');
                    }
                };

                previewMedia();

                if (existingMetadata) {
                    dialog.find('[name="title"]').val(existingMetadata.title || '');
                    dialog.find('[name="alt"]').val(existingMetadata.alt || '');
                    dialog.find('[name="copyright"]').val(existingMetadata.copyright || '');
                }

                dialog.find('form').on('submit', function(e) {
                    e.preventDefault();
                    const metadata = {
                        title: dialog.find('[name="title"]').val(),
                        alt: dialog.find('[name="alt"]').val(),
                        copyright: dialog.find('[name="copyright"]').val()
                    };
                    dialog.data('submitted', true);
                    resolve(metadata);
                    dialog.modal('hide');
                });

                dialog.on('hidden.bs.modal', function() {
                    if (!dialog.data('submitted')) {
                        reject(new Error('Metadata input cancelled'));
                    }
                    dialog.remove();
                });

                dialog.find('.btn-abort').on('click', function() {
                    dialog.modal('hide');
                });

                dialog.modal('show');
            });
        };
        // Prepare existing files
        let existingFiles = [];
        if (initialValue) {
            existingFiles = initialValue.split(',')
                .filter(Boolean)
                .map(filename => {
                    const file = filename.trim().replace(/^"|"$/g, '');
                    return {
                        source: file,
                        options: {
                            type: 'local',
                            metadata: {
                                poster: '/media/' + file
                            }
                        }
                    };
                });
        }

        // Initialize FilePond
        const pond = FilePond.create(fileInput[0], {
            files: existingFiles,
            allowMultiple: true,
            allowReorder: true,
            maxFiles: input.data('filepondMaxfiles') || null,
            server: {
                url: 'index.php',
                process: async (fieldName, file, metadata, load, error, progress, abort, transfer, options) => {
                    try {
                        const fileMetadata = await createMetadataDialog(file);
                        
                        const formData = new FormData();
                        formData.append(fieldName, file);
                        formData.append('rex-api-call', 'filepond_uploader');
                        formData.append('func', 'upload');
                        formData.append('category_id', input.data('filepondCat') || '0');
                        formData.append('metadata', JSON.stringify(fileMetadata));

                        const response = await fetch('index.php', {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: formData
                        });

                        const result = await response.json();

                        if (!response.ok) {
                            error(result.error || 'Upload failed');
                            return;
                        }

                        load(result);
                    } catch (err) {
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
                load: (source, load, error, progress, abort, headers) => {
                    const url = '/media/' + source.replace(/^"|"$/g, '');
                    
                    fetch(url)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('HTTP error! status: ' + response.status);
                            }
                            return response.blob();
                        })
                        .then(load)
                        .catch(e => {
                            error(e.message);
                        });
                    
                    return {
                        abort
                    };
                }
            },
            labelIdle: t.labelIdle,
            styleButtonRemoveItemPosition: 'right',
            styleLoadIndicatorPosition: 'right',
            styleProgressIndicatorPosition: 'right',
            styleButtonProcessItemPosition: 'right',
            imagePreviewHeight: 100,
            itemPanelAspectRatio: 1,
            acceptedFileTypes: (input.data('filepondTypes') || 'image/*').split(','),
            maxFileSize: (input.data('filepondMaxsize') || '10') + 'MB',
            credits: false
        });

        // Event handlers
        pond.on('processfile', (error, file) => {
            if (!error && file.serverId) {
                const currentValue = input.val() ? input.val().split(',').filter(Boolean) : [];
                if (!currentValue.includes(file.serverId)) {
                    currentValue.push(file.serverId);
                    input.val(currentValue.join(','));
                }
            }
        });

        pond.on('removefile', (error, file) => {
            if (!error) {
                const currentValue = input.val() ? input.val().split(',').filter(Boolean) : [];
                const removeValue = file.serverId || file.source;
                const index = currentValue.indexOf(removeValue);
                if (index > -1) {
                    currentValue.splice(index, 1);
                    input.val(currentValue.join(','));
                }
            }
        });

        // Handle reordering
        pond.on('reorderfiles', (files) => {
            const newValue = files
                .map(file => file.serverId || file.source)
                .filter(Boolean)
                .join(',');
            input.val(newValue);
        });
    });
});
