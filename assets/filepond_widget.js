(function() {
    const initFilePond = () => {
        console.log('initFilePond function called');
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

        // Register FilePond plugins
        FilePond.registerPlugin(
            FilePondPluginFileValidateType,
            FilePondPluginFileValidateSize,
            FilePondPluginImagePreview
        );

        // Funktion zum Ermitteln des Basepaths
        const getBasePath = () => {
            const baseElement = document.querySelector('base');
            if (baseElement && baseElement.href) {
                return baseElement.href.replace(/\/$/, ''); // Entferne optionalen trailing slash
            }
           // Fallback, wenn kein <base>-Tag vorhanden ist
           return  window.location.origin;
        };
        const basePath = getBasePath();
         console.log('Basepath ermittelt:', basePath);
        
         document.querySelectorAll('input[data-widget="filepond"]').forEach(input => {
            console.log('FilePond input element found:', input);
            const lang = input.dataset.filepondLang || document.documentElement.lang || 'de_de';
            const t = translations[lang] || translations['de_de'];
            
            const initialValue = input.value.trim();
            const skipMeta = input.dataset.filepondSkipMeta === 'true';
            
            input.style.display = 'none';

            const fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.multiple = true;
            input.parentNode.insertBefore(fileInput, input.nextSibling);

            // Create metadata dialog with SimpleModal
           const createMetadataDialog = (file, existingMetadata = null) => {
                return new Promise((resolve, reject) => {
                    const form = document.createElement('div');
                    form.className = 'simple-modal-grid';

                    // Preview Container
                    const previewCol = document.createElement('div');
                    previewCol.className = 'simple-modal-col-4';
                    const previewContainer = document.createElement('div');
                    previewContainer.className = 'simple-modal-preview';
                    previewCol.appendChild(previewContainer);

                    // Form Fields
                    const formCol = document.createElement('div');
                    formCol.className = 'simple-modal-col-8';
                    formCol.innerHTML = `
                        <div class="simple-modal-form-group">
                            <label for="title">${t.titleLabel}</label>
                            <input type="text" id="title" name="title" class="simple-modal-input" required value="${existingMetadata?.title || ''}">
                        </div>
                        <div class="simple-modal-form-group">
                            <label for="alt">${t.altLabel}</label>
                            <input type="text" id="alt" name="alt" class="simple-modal-input" required value="${existingMetadata?.alt || ''}">
                            <div class="help-text">${t.altNotice}</div>
                        </div>
                        <div class="simple-modal-form-group">
                            <label for="copyright">${t.copyrightLabel}</label>
                            <input type="text" id="copyright" name="copyright" class="simple-modal-input" value="${existingMetadata?.copyright || ''}">
                        </div>
                    `;

                    form.appendChild(previewCol);
                    form.appendChild(formCol);

                    const modal = new SimpleModal();

                    // Preview media
                    const previewMedia = async () => {
                        try {
                            if (file instanceof File) {
                                if (file.type.startsWith('image/')) {
                                    const img = document.createElement('img');
                                    img.src = URL.createObjectURL(file);
                                    img.alt = file.name;
                                    previewContainer.appendChild(img);
                                } else if (file.type.startsWith('video/')) {
                                    const video = document.createElement('video');
                                    video.src = URL.createObjectURL(file);
                                    video.controls = true;
                                    video.muted = true;
                                    previewContainer.appendChild(video);
                                } else if (file.type.startsWith('application/pdf')) {
                                    previewContainer.innerHTML = '<span class="simple-modal-file-icon">📄</span>';
                                } else {
                                    previewContainer.innerHTML = '<span class="simple-modal-file-icon">📁</span>';
                                }
                            } else {
                                const mediaUrl = '/media/' + file.source;
                                if (file.type?.startsWith('image/')) {
                                    const img = document.createElement('img');
                                    img.src = mediaUrl;
                                    img.alt = file.source;
                                    previewContainer.appendChild(img);
                                } else if (file.type?.startsWith('video/')) {
                                    const video = document.createElement('video');
                                    video.src = mediaUrl;
                                    video.controls = true;
                                    video.muted = true;
                                    previewContainer.appendChild(video);
                                } else if (file.type?.startsWith('application/pdf')) {
                                    previewContainer.innerHTML = '<span class="simple-modal-file-icon">📄</span>';
                                } else {
                                    previewContainer.innerHTML = '<span class="simple-modal-file-icon">📁</span>';
                                }
                            }
                        } catch (error) {
                            console.error('Error loading preview:', error);
                            previewContainer.innerHTML = '';
                        }
                    };

                    previewMedia();

                    modal.show({
                        title: `${t.metaTitle} ${file.filename || file.name}`,
                        content: form,
                        buttons: [
                            {
                                text: t.cancelBtn,
                                closeModal: true,
                                handler: () => reject(new Error('Metadata input cancelled'))
                            },
                            {
                                text: t.saveBtn,
                                primary: true,
                                handler: () => {
                                    const titleInput = form.querySelector('[name="title"]');
                                    const altInput = form.querySelector('[name="alt"]');
                                    const copyrightInput = form.querySelector('[name="copyright"]');

                                    if (titleInput.value && altInput.value) {
                                        const metadata = {
                                            title: titleInput.value,
                                            alt: altInput.value,
                                            copyright: copyrightInput.value
                                        };
                                        modal.close();
                                        resolve(metadata);
                                    } else {
                                        if (!titleInput.value) titleInput.reportValidity();
                                        if (!altInput.value) altInput.reportValidity();
                                    }
                                }
                            }
                        ]
                    });
                });
            };

            // Prepare existing files
            const existingFiles = initialValue ? initialValue.split(',')
                .filter(Boolean)
                .map(filename => {
                    const file = filename.trim().replace(/^"|"$/g, '');
                     return {
                        source: file,
                        options: {
                            type: 'local',
                           // poster nur bei videos setzen
                             ...(file.type?.startsWith('video/') ? {
                                    metadata: {
                                        poster: '/media/' + file
                                    }
                                } : {} )
                           }
                    };
                }) : [];

            // Initialize FilePond
            const pond = FilePond.create(fileInput, {
                files: existingFiles,
                allowMultiple: true,
                allowReorder: true,
                maxFiles: parseInt(input.dataset.filepondMaxfiles) || null,
                server: {
                     url: basePath, // Verwende den Basepath
                    process: async (fieldName, file, metadata, load, error, progress, abort, transfer, options) => {
                         try {
                            let fileMetadata = {};
                            
                            // Meta-Dialog nur anzeigen wenn nicht übersprungen
                            if (!skipMeta) {
                                fileMetadata = await createMetadataDialog(file);
                            } else {
                                // Standard-Metadaten wenn übersprungen
                                fileMetadata = {
                                    title: file.name,
                                    alt: file.name,
                                    copyright: ''
                                };
                            }
                            
                            const formData = new FormData();
                            formData.append(fieldName, file);
                            formData.append('rex-api-call', 'filepond_uploader');
                            formData.append('func', 'upload');
                            formData.append('category_id', input.dataset.filepondCat || '0');
                            formData.append('metadata', JSON.stringify(fileMetadata));

                            const response = await fetch(basePath, {  // Verwende den Basepath
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
                            if (err.message !== 'Metadata input cancelled') {
                                console.error('Upload error:', err);
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
                    load: (source, load, error, progress, abort, headers) => {
                         const url = '/media/' + source.replace(/^"|"$/g, '');
                         console.log('FilePond load url:', url);
                        
                        fetch(url)
                            .then(response => {
                                 console.log('FilePond load response:', response);
                                if (!response.ok) {
                                    throw new Error('HTTP error! status: ' + response.status);
                                }
                                return response.blob();
                            })
                             .then(blob => {
                                 console.log('FilePond load blob:', blob);
                                load(blob);
                            })
                            .catch(e => {
                                 console.error('FilePond load error:', e);
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

            pond.on('reorderfiles', (files) => {
                const newValue = files
                    .map(file => file.serverId || file.source)
                    .filter(Boolean)
                    .join(',');
                input.value = newValue;
            });
        });
    };

    // Initialize based on environment
    if (typeof jQuery !== 'undefined') {
       jQuery(document).on('rex:ready', initFilePond);
    } 
   
    // Expose initFilePond globally if needed
    window.initFilePond = initFilePond;
})();
