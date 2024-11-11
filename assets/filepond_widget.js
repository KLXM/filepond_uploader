// Flag to track initialization
let isInitialized = false;

// Main initialization function
function initializeFilePond() {
    // Prevent multiple initializations
    if (isInitialized) {
        return;
    }
    isInitialized = true;

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

    document.querySelectorAll('input[data-widget="filepond"]').forEach(function(input) {
        const lang = input.dataset.filepondLang || 'en_gb';
        const t = translations[lang] || translations['en_gb'];
        
        let rawValue = input.value;
        let initialValue = rawValue.trim();
        
        input.style.display = 'none';

        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.multiple = true;
        input.parentNode.insertBefore(fileInput, input.nextSibling);

        // Create metadata dialog with preview
        const createMetadataDialog = (file, existingMetadata = null) => {
            return new Promise((resolve, reject) => {
                const dialog = document.createElement('div');
                dialog.className = 'modal fade';
                dialog.innerHTML = `
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
                `;

                const previewContainer = dialog.querySelector('.media-preview-container');

                const previewMedia = async () => {
                    try {
                        if (file instanceof File) {
                            if (file.type.startsWith('image/')) {
                                const img = document.createElement('img');
                                img.src = URL.createObjectURL(file);
                                img.alt = file.name;
                                img.style.maxHeight = '200px';
                                previewContainer.appendChild(img);
                            } else if (file.type.startsWith('video/')) {
                                const video = document.createElement('video');
                                video.src = URL.createObjectURL(file);
                                video.controls = true;
                                video.muted = true;
                                video.style.maxHeight = '200px';
                                previewContainer.appendChild(video);
                            } else if (file.type.startsWith('application/pdf')) {
                                const pdfIcon = document.createElement('i');
                                pdfIcon.className = 'fas fa-file-pdf fa-3x';
                                previewContainer.appendChild(pdfIcon);
                            } else {
                                const docIcon = document.createElement('i');
                                docIcon.className = 'fas fa-file fa-3x';
                                previewContainer.appendChild(docIcon);
                            }
                        } else {
                            if (file.type.startsWith('image/')) {
                                const img = document.createElement('img');
                                img.src = '/media/' + file.source;
                                img.alt = file.source;
                                img.style.maxHeight = '200px';
                                previewContainer.appendChild(img);
                            } else if (file.type.startsWith('video/')) {
                                const video = document.createElement('video');
                                video.src = '/media/' + file.source;
                                video.controls = true;
                                video.muted = true;
                                video.style.maxHeight = '200px';
                                previewContainer.appendChild(video);
                            } else if (file.type.startsWith('application/pdf')) {
                                const pdfIcon = document.createElement('i');
                                pdfIcon.className = 'fas fa-file-pdf fa-3x';
                                previewContainer.appendChild(pdfIcon);
                            } else {
                                const docIcon = document.createElement('i');
                                docIcon.className = 'fas fa-file fa-3x';
                                previewContainer.appendChild(docIcon);
                            }
                        }
                    } catch (error) {
                        console.error('Error loading preview:', error);
                        previewContainer.innerHTML = '';
                    }
                };

                previewMedia();

                if (existingMetadata) {
                    dialog.querySelector('[name="title"]').value = existingMetadata.title || '';
                    dialog.querySelector('[name="alt"]').value = existingMetadata.alt || '';
                    dialog.querySelector('[name="copyright"]').value = existingMetadata.copyright || '';
                }

                dialog.querySelector('form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    const metadata = {
                        title: dialog.querySelector('[name="title"]').value,
                        alt: dialog.querySelector('[name="alt"]').value,
                        copyright: dialog.querySelector('[name="copyright"]').value
                    };
                    dialog.dataset.submitted = 'true';
                    resolve(metadata);
                    $(dialog).modal('hide');  // Using jQuery for Bootstrap modal
                });

                dialog.addEventListener('hidden.bs.modal', function() {
                    if (!dialog.dataset.submitted) {
                        reject(new Error('Metadata input cancelled'));
                    }
                    dialog.remove();
                });

                dialog.querySelector('.btn-abort').addEventListener('click', function() {
                    $(dialog).modal('hide');  // Using jQuery for Bootstrap modal
                });

                document.body.appendChild(dialog);
                $(dialog).modal('show');  // Using jQuery for Bootstrap modal
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
        const pond = FilePond.create(fileInput, {
            files: existingFiles,
            allowMultiple: true,
            allowReorder: true,
            maxFiles: input.dataset.filepondMaxfiles || null,
            server: {
                url: 'index.php',
                process: async (fieldName, file, metadata, load, error, progress, abort, transfer, options) => {
                    try {
                        const fileMetadata = await createMetadataDialog(file);
                        
                        const formData = new FormData();
                        formData.append(fieldName, file);
                        formData.append('rex-api-call', 'filepond_uploader');
                        formData.append('func', 'upload');
                        formData.append('category_id', input.dataset.filepondCat || '0');
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
            acceptedFileTypes: (input.dataset.filepondTypes || 'image/*').split(','),
            maxFileSize: (input.dataset.filepondMaxsize || '10') + 'MB',
            credits: false
        });

        // Event handlers
        pond.on('processfile', (error, file) => {
            if (!error && file.serverId) {
                const currentValue = input.value ? input.value.split(',').filter(Boolean) : [];
                if (!currentValue.includes(file.serverId)) {
                    currentValue.push(file.serverId);
                    input.value = currentValue.join(',');
                }
            }
        });

        pond.on('removefile', (error, file) => {
            if (!error) {
                const currentValue = input.value ? input.value.split(',').filter(Boolean) : [];
                const removeValue = file.serverId || file.source;
                const index = currentValue.indexOf(removeValue);
                if (index > -1) {
                    currentValue.splice(index, 1);
                    input.value = currentValue.join(',');
                }
            }
        });

        // Handle reordering
        pond.on('reorderfiles', (files) => {
            const newValue = files
                .map(file => file.serverId || file.source)
                .filter(Boolean)
                .join(',');
            input.value = newValue;
        });
    });
}

// Initialize based on environment
if (typeof jQuery !== 'undefined') {
    // If jQuery is available, listen for rex:ready
    jQuery(document).on('rex:ready', initializeFilePond);
} else {
    // If no jQuery, use native DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeFilePond);
    } else {
        initializeFilePond();
    }
}
