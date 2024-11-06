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

    // File type icons mapping
    const fileIcons = {
        'image': 'fa-regular fa-image',
        'video': 'fa-regular fa-file-video',
        'pdf': 'fa-regular fa-file-pdf',
        'doc': 'fa-regular fa-file-word',
        'docx': 'fa-regular fa-file-word',
        'txt': 'fa-regular fa-file-lines',
        'default': 'fa-regular fa-file'
    };

    // Helper function to get file icon
    const getFileIcon = (file) => {
        const type = file.type.split('/')[0];
        const extension = file.name ? file.name.split('.').pop().toLowerCase() : '';
        return fileIcons[type] || fileIcons[extension] || fileIcons.default;
    };

    // Helper function to truncate filename
    const truncateFilename = (filename, maxLength = 20) => {
        if (filename.length <= maxLength) return filename;
        const extension = filename.split('.').pop();
        const name = filename.substring(0, filename.lastIndexOf('.'));
        const truncated = name.substring(0, maxLength - extension.length - 3) + '...';
        return `${truncated}.${extension}`;
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
                                    <h4 class="modal-title">${t.metaTitle} ${truncateFilename(file.name || file.filename)}</h4>
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                </div>
                                <div class="modal-body">
                                    <form class="metadata-form">
                                        <div class="row">
                                            <div class="col-md-5">
                                                <div class="preview-container"></div>
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

                // Preview content based on file type
                const previewContainer = dialog.find('.preview-container');
                const fileInfo = dialog.find('.file-info');
                const fileType = file.type ? file.type.split('/')[0] : '';
                
                const showPreview = async () => {
                    try {
                        if (file instanceof File) {
                            if (fileType === 'image') {
                                const reader = new FileReader();
                                reader.onload = (e) => {
                                    previewContainer.html(`<img src="${e.target.result}" alt="" class="img-fluid">`);
                                };
                                reader.readAsDataURL(file);
                            } else if (fileType === 'video') {
                                const reader = new FileReader();
                                reader.onload = (e) => {
                                    previewContainer.html(`
                                        <video controls class="img-fluid">
                                            <source src="${e.target.result}" type="${file.type}">
                                            Your browser does not support the video tag.
                                        </video>
                                    `);
                                };
                                reader.readAsDataURL(file);
                            } else {
                                // Show icon for other file types
                                const icon = getFileIcon(file);
                                previewContainer.html(`
                                    <div class="file-icon-preview">
                                        <i class="${icon}"></i>
                                    </div>
                                `);
                            }
                            
                            fileInfo.html(`
                                <strong>${t.fileInfo}:</strong> ${truncateFilename(file.name)}<br>
                                <strong>${t.fileSize}:</strong> ${(file.size / 1024 / 1024).toFixed(2)} MB
                            `);
                        } else {
                            // Handling existing files
                            const mediaUrl = '/media/' + file.source;
                            const extension = file.source.split('.').pop().toLowerCase();
                            
                            if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension)) {
                                previewContainer.html(`<img src="${mediaUrl}" alt="" class="img-fluid">`);
                            } else if (['mp4', 'webm', 'ogg'].includes(extension)) {
                                previewContainer.html(`
                                    <video controls class="img-fluid">
                                        <source src="${mediaUrl}" type="video/${extension}">
                                        Your browser does not support the video tag.
                                    </video>
                                `);
                            } else {
                                const fakeFile = { name: file.source, type: 'application/' + extension };
                                const icon = getFileIcon(fakeFile);
                                previewContainer.html(`
                                    <div class="file-icon-preview">
                                        <i class="${icon}"></i>
                                    </div>
                                `);
                            }

                            const mediaFile = await fetch(mediaUrl);
                            const size = mediaFile.headers.get('content-length');
                            fileInfo.html(`
                                <strong>${t.fileInfo}:</strong> ${truncateFilename(file.source)}<br>
                                <strong>${t.fileSize}:</strong> ${(size / 1024 / 1024).toFixed(2)} MB
                            `);
                        }
                    } catch (error) {
                        console.error('Error loading preview:', error);
                        previewContainer.html('');
                    }
                };

                showPreview();

                // Fill existing metadata if available
                if (existingMetadata) {
                    dialog.find('[name="title"]').val(existingMetadata.title || '');
                    dialog.find('[name="alt"]').val(existingMetadata.alt || '');
                    dialog.find('[name="copyright"]').val(existingMetadata.copyright || '');
                }

                // Handle form submit
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
                    const mediaUrl = '/media/' + file;
                    return {
                        source: file,
                        options: {
                            type: 'local',
                            metadata: {
                                poster: mediaUrl
                            }
                        }
                    };
                });
        }

        // Initialize FilePond
        const pond = FilePond.create(fileInput[0], {
            files: existingFiles,
            allowMultiple: true,
            server: {
                url: 'index.php',
                process: async (fieldName, file, metadata, load, error, progress, abort, transfer, options) => {
                    try {
                        const fileMetadata = await createMetadataDialog(file);
                        
                        const formData = new FormData();
                        formData.append(fieldName, file);
                        formData.append('rex-api-call', 'filepond_uploader');
                        formData.append('func', 'upload');
                        formData.append('category_id', input.data('filepondCat') || '1');
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
                        .then(blob => {
                            load(blob);
                        })
                        .catch(e => {
                            error(e.message);
                        });
                    
                    return {
                        abort: () => {
                            abort();
                        }
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
            acceptedFileTypes: (input.data('filepondTypes') || 'image/*,video/*,application/pdf').split(','),
            maxFileSize: (input.data('filepondMaxsize') || '1000') + 'MB',
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
    });
});