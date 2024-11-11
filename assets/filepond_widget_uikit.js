// Initialize on jQuery rex:ready or DOMContentLoaded
(function() {
    const initFilePond = () => {
        // Core FilePond initialization code
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

        // Register FilePond plugins
        FilePond.registerPlugin(
            FilePondPluginFileValidateType,
            FilePondPluginFileValidateSize,
            FilePondPluginImagePreview
        );

        // Helper function to create elements with attributes and properties
        const createElement = (tag, attributes = {}, properties = {}) => {
            const element = document.createElement(tag);
            Object.entries(attributes).forEach(([key, value]) => {
                element.setAttribute(key, value);
            });
            Object.entries(properties).forEach(([key, value]) => {
                element[key] = value;
            });
            return element;
        };

        // Create metadata dialog using UIkit
        const createMetadataDialog = (file, existingMetadata = null) => {
            return new Promise((resolve, reject) => {
                const modalId = 'filepond-modal-' + Math.random().toString(36).substr(2, 9);
                const lang = document.documentElement.lang || 'en_gb';
                const t = translations[lang] || translations['en_gb'];
                
                const modalHtml = `
                    <div id="${modalId}" class="uk-modal" uk-modal>
                        <div class="uk-modal-dialog uk-modal-large">
                            <button class="uk-modal-close-default" type="button" uk-close></button>
                            <div class="uk-modal-header">
                                <h2 class="uk-modal-title">${t.metaTitle} ${file.name || file.filename}</h2>
                            </div>
                            <div class="uk-modal-body">
                                <form class="metadata-form uk-form-stacked">
                                    <div class="uk-grid-medium" uk-grid>
                                        <div class="uk-width-1-3@m">
                                            <div class="media-preview-container uk-margin">
                                                <!-- Media preview will be inserted here -->
                                            </div>
                                            <div class="file-info uk-text-small uk-text-muted"></div>
                                        </div>
                                        <div class="uk-width-2-3@m">
                                            <div class="uk-margin">
                                                <label class="uk-form-label" for="title-${modalId}">${t.titleLabel}</label>
                                                <div class="uk-form-controls">
                                                    <input class="uk-input" id="title-${modalId}" type="text" name="title" required>
                                                </div>
                                            </div>
                                            <div class="uk-margin">
                                                <label class="uk-form-label" for="alt-${modalId}">${t.altLabel}</label>
                                                <div class="uk-form-controls">
                                                    <input class="uk-input" id="alt-${modalId}" type="text" name="alt" required>
                                                    <span class="uk-text-small uk-text-muted">${t.altNotice}</span>
                                                </div>
                                            </div>
                                            <div class="uk-margin">
                                                <label class="uk-form-label" for="copyright-${modalId}">${t.copyrightLabel}</label>
                                                <div class="uk-form-controls">
                                                    <input class="uk-input" id="copyright-${modalId}" type="text" name="copyright">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="uk-modal-footer uk-text-right">
                                <button class="uk-button uk-button-default uk-modal-close" type="button">${t.cancelBtn}</button>
                                <button class="uk-button uk-button-primary submit-metadata" type="button">${t.saveBtn}</button>
                            </div>
                        </div>
                    </div>
                `;

                // Create modal element
                const modalElement = createElement('div', {}, { innerHTML: modalHtml });
                const modal = modalElement.firstElementChild;
                document.body.appendChild(modal);

                const uikitModal = UIkit.modal('#' + modalId);
                const previewContainer = modal.querySelector('.media-preview-container');

                // Preview media function
                const previewMedia = async () => {
                    try {
                        if (file instanceof File) {
                            if (file.type.startsWith('image/')) {
                                const img = createElement('img', {
                                    src: URL.createObjectURL(file),
                                    alt: file.name,
                                    class: 'uk-width-1-1'
                                });
                                previewContainer.appendChild(img);
                            } else if (file.type.startsWith('video/')) {
                                const video = createElement('video', {
                                    src: URL.createObjectURL(file),
                                    controls: '',
                                    muted: '',
                                    class: 'uk-width-1-1'
                                });
                                previewContainer.appendChild(video);
                            } else if (file.type.startsWith('application/pdf')) {
                                previewContainer.innerHTML = '<span uk-icon="icon: file-pdf; ratio: 3"></span>';
                            } else {
                                previewContainer.innerHTML = '<span uk-icon="icon: file-text; ratio: 3"></span>';
                            }
                        } else {
                            // Handle existing files
                            const mediaUrl = '/media/' + file.source;
                            if (file.type?.startsWith('image/')) {
                                const img = createElement('img', {
                                    src: mediaUrl,
                                    alt: file.source,
                                    class: 'uk-width-1-1'
                                });
                                previewContainer.appendChild(img);
                            } else if (file.type?.startsWith('video/')) {
                                const video = createElement('video', {
                                    src: mediaUrl,
                                    controls: '',
                                    muted: '',
                                    class: 'uk-width-1-1'
                                });
                                previewContainer.appendChild(video);
                            } else if (file.type?.startsWith('application/pdf')) {
                                previewContainer.innerHTML = '<span uk-icon="icon: file-pdf; ratio: 3"></span>';
                            } else {
                                previewContainer.innerHTML = '<span uk-icon="icon: file-text; ratio: 3"></span>';
                            }
                        }
                    } catch (error) {
                        console.error('Error loading preview:', error);
                        previewContainer.innerHTML = '';
                    }
                };

                previewMedia();

                // Set existing metadata if available
                if (existingMetadata) {
                    modal.querySelector('[name="title"]').value = existingMetadata.title || '';
                    modal.querySelector('[name="alt"]').value = existingMetadata.alt || '';
                    modal.querySelector('[name="copyright"]').value = existingMetadata.copyright || '';
                }

                // Handle form submission
                modal.querySelector('.submit-metadata').addEventListener('click', () => {
                    const form = modal.querySelector('form');
                    if (form.checkValidity()) {
                        const metadata = {
                            title: modal.querySelector('[name="title"]').value,
                            alt: modal.querySelector('[name="alt"]').value,
                            copyright: modal.querySelector('[name="copyright"]').value
                        };
                        uikitModal.hide();
                        resolve(metadata);
                    } else {
                        form.reportValidity();
                    }
                });

                // Handle modal close
                modal.addEventListener('hidden', (event) => {
                    if (!event.target.classList.contains('uk-modal')) return;
                    if (!modal.dataset.submitted) {
                        reject(new Error('Metadata input cancelled'));
                    }
                    modal.remove();
                });

                // Show modal
                uikitModal.show();
            });
        };

        // Initialize FilePond instances
        document.querySelectorAll('input[data-widget="filepond"]').forEach(input => {
            const getLang = () => {
                return input.dataset.filepondLang || document.documentElement.lang || 'en_gb';
            };

            const t = translations[getLang()] || translations['en_gb'];
            const initialValue = input.value.trim();
            
            // Hide original input
            input.style.display = 'none';

            // Create file input
            const fileInput = createElement('input', {
                type: 'file',
                multiple: ''
            });
            input.parentNode.insertBefore(fileInput, input.nextSibling);

            // Prepare existing files
            const existingFiles = initialValue ? initialValue.split(',')
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
                }) : [];

            // Initialize FilePond with UIkit notification integration
            const pond = FilePond.create(fileInput, {
                files: existingFiles,
                allowMultiple: true,
                allowReorder: true,
                maxFiles: parseInt(input.dataset.filepondMaxfiles) || null,
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
                                UIkit.notification({
                                    message: result.error || 'Upload failed',
                                    status: 'danger',
                                    pos: 'top-right',
                                    timeout: 3000
                                });
                                error(result.error || 'Upload failed');
                                return;
                            }

                            UIkit.notification({
                                message: 'Upload successful',
                                status: 'success',
                                pos: 'top-right',
                                timeout: 2000
                            });
                            load(result);
                        } catch (err) {
                            if (err.message !== 'Metadata input cancelled') {
                                UIkit.notification({
                                    message: 'Upload cancelled',
                                    status: 'warning',
                                    pos: 'top-right',
                                    timeout: 2000
                                });
                            }
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
                    load: async (source, load, error, progress, abort, headers) => {
                        try {
                            const url = '/media/' + source.replace(/^"|"$/g, '');
                            const response = await fetch(url);
                            if (!response.ok) throw new Error('HTTP error! status: ' + response.status);
                            const blob = await response.blob();
                            load(blob);
                        } catch (e) {
                            UIkit.notification({
                                message: e.message,
                                status: 'danger',
                                pos: 'top-right',
                                timeout: 3000
                            });
                            error(e.message);
                        }
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
                credits: false,
                onwarning: (error) => {
                    UIkit.notification({
                        message: error.body,
                        status: 'warning',
                        pos: 'top-right',
                        timeout: 3000
                    });
                }
            });

            // Event Handlers
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
                        
                        UIkit.notification({
                            message: 'File removed',
                            status: 'success',
                            pos: 'top-right',
                            timeout: 2000
                        });
                    }
                }
            });

            pond.on('reorderfiles', (files) => {
                const newValue = files
                    .map(file => file.serverId || file.source)
                    .filter(Boolean)
                    .join(',');
                input.value = newValue;
                
                UIkit.notification({
                    message: 'Order updated',
                    status: 'success',
                    pos: 'top-right',
                    timeout: 2000
                });
            });

            // Visual feedback for drag and drop
            pond.on('dragenter', () => {
                pond.element.classList.add('uk-box-shadow-medium');
            });

            pond.on('dragleave', () => {
                pond.element.classList.remove('uk-box-shadow-medium');
            });

            pond.on('drop', () => {
                pond.element.classList.remove('uk-box-shadow-medium');
            });
        });
    };

    // Initialize based on environment
    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('rex:ready', initFilePond);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFilePond);
    } else {
        initFilePond();
    }
})();
