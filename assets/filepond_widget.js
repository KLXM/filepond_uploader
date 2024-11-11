(function() {
    // Initialize FilePond
    const initFilePond = () => {
        // Translation map
        const translations = {
            de_de: {
                labelIdle: 'Dateien hierher ziehen oder <span class="filepond--label-action">durchsuchen</span>',
                metaTitle: 'Metadaten für',
                titleLabel: 'Titel',
                altLabel: 'Alt-Text',
                altNotice: 'Alternativtext für Screenreader und SEO',
                copyrightLabel: 'Copyright',
                saveBtn: 'Speichern',
                cancelBtn: 'Abbrechen'
            },
            en_gb: {
                labelIdle: 'Drag & Drop your files or <span class="filepond--label-action">Browse</span>',
                metaTitle: 'Metadata for',
                titleLabel: 'Title',
                altLabel: 'Alt Text',
                altNotice: 'Alternative text for screen readers and SEO',
                copyrightLabel: 'Copyright',
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

        // Create metadata modal dialog
        const createMetadataDialog = (file, existingMetadata = null) => {
            return new Promise((resolve, reject) => {
                const modal = new SimpleModal();
                const lang = document.documentElement.lang || 'en_gb';
                const t = translations[lang] || translations['en_gb'];

                // Create form content with grid layout
                const form = document.createElement('div');
                form.style.display = 'grid';
                form.style.gridTemplateColumns = '1fr 2fr';
                form.style.gap = '20px';

                // Preview container
                const previewContainer = document.createElement('div');
                previewContainer.style.padding = '15px';
                previewContainer.style.background = '#f5f5f5';
                previewContainer.style.borderRadius = '4px';

                // Form fields container
                const fieldsContainer = document.createElement('div');
                fieldsContainer.innerHTML = `
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">${t.titleLabel}</label>
                        <input type="text" name="title" class="modal-input" required 
                               value="${existingMetadata?.title || ''}" 
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">${t.altLabel}</label>
                        <input type="text" name="alt" class="modal-input" required 
                               value="${existingMetadata?.alt || ''}"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <small style="color: #666; font-size: 0.8em;">${t.altNotice}</small>
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">${t.copyrightLabel}</label>
                        <input type="text" name="copyright" class="modal-input" 
                               value="${existingMetadata?.copyright || ''}"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                `;

                form.appendChild(previewContainer);
                form.appendChild(fieldsContainer);

                // Handle file preview
                const loadPreview = async () => {
                    try {
                        if (file instanceof File) {
                            if (file.type.startsWith('image/')) {
                                const img = document.createElement('img');
                                img.src = URL.createObjectURL(file);
                                img.style.maxWidth = '100%';
                                img.style.height = 'auto';
                                previewContainer.appendChild(img);
                            } else if (file.type.startsWith('video/')) {
                                const video = document.createElement('video');
                                video.src = URL.createObjectURL(file);
                                video.controls = true;
                                video.muted = true;
                                video.style.maxWidth = '100%';
                                previewContainer.appendChild(video);
                            } else {
                                previewContainer.innerHTML = '<div style="text-align: center; padding: 20px;">📄</div>';
                            }
                        } else {
                            const mediaUrl = '/media/' + file.source;
                            if (file.type?.startsWith('image/')) {
                                previewContainer.innerHTML = `<img src="${mediaUrl}" style="max-width: 100%; height: auto;">`;
                            } else if (file.type?.startsWith('video/')) {
                                previewContainer.innerHTML = `<video src="${mediaUrl}" controls muted style="max-width: 100%;">`;
                            } else {
                                previewContainer.innerHTML = '<div style="text-align: center; padding: 20px;">📄</div>';
                            }
                        }
                    } catch (error) {
                        console.error('Preview error:', error);
                        previewContainer.innerHTML = '<div style="text-align: center; padding: 20px;">⚠️</div>';
                    }
                };

                loadPreview();

                // Show modal with form
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
                                const titleInput = form.querySelector('input[name="title"]');
                                const altInput = form.querySelector('input[name="alt"]');
                                const copyrightInput = form.querySelector('input[name="copyright"]');

                                if (!titleInput.value || !altInput.value) {
                                    alert('Bitte füllen Sie alle erforderlichen Felder aus.');
                                    return;
                                }

                                const metadata = {
                                    title: titleInput.value,
                                    alt: altInput.value,
                                    copyright: copyrightInput.value
                                };

                                modal.close();
                                resolve(metadata);
                            }
                        }
                    ]
                });
            });
        };

        // Initialize FilePond on all matching inputs
        document.querySelectorAll('input[data-widget="filepond"]').forEach(input => {
            const lang = input.dataset.filepondLang || document.documentElement.lang || 'en_gb';
            const t = translations[lang] || translations['en_gb'];
            
            input.style.display = 'none';
            
            const fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.multiple = true;
            input.parentNode.insertBefore(fileInput, input.nextSibling);

            // Prepare existing files if any
            const existingFiles = input.value ? input.value.split(',')
                .filter(Boolean)
                .map(filename => ({
                    source: filename.trim().replace(/^"|"$/g, ''),
                    options: {
                        type: 'local',
                        metadata: {
                            poster: '/media/' + filename.trim().replace(/^"|"$/g, '')
                        }
                    }
                })) : [];

            // Initialize FilePond instance
            const pond = FilePond.create(fileInput, {
                files: existingFiles,
                allowMultiple: true,
                allowReorder: true,
                maxFiles: parseInt(input.dataset.filepondMaxfiles) || null,
                server: {
                    process: async (fieldName, file, metadata, load, error, progress, abort) => {
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
                                body: formData
                            });

                            if (!response.ok) {
                                const result = await response.json();
                                throw new Error(result.error || 'Upload failed');
                            }

                            const result = await response.json();
                            load(result);

                        } catch (err) {
                            error(err.message);
                            abort();
                        }
                    },
                    revert: async (uniqueFileId, load, error) => {
                        try {
                            const formData = new FormData();
                            formData.append('rex-api-call', 'filepond_uploader');
                            formData.append('func', 'delete');
                            formData.append('filename', uniqueFileId);

                            const response = await fetch('index.php', {
                                method: 'POST',
                                body: formData
                            });

                            if (!response.ok) {
                                throw new Error('Delete failed');
                            }

                            load();
                        } catch (err) {
                            error(err.message);
                        }
                    },
                    load: '/media/'
                },
                labelIdle: t.labelIdle,
                styleButtonRemoveItemPosition: 'right',
                styleButtonProcessItemPosition: 'right',
                styleLoadIndicatorPosition: 'right',
                styleProgressIndicatorPosition: 'right',
                imagePreviewHeight: 100,
                acceptedFileTypes: (input.dataset.filepondTypes || 'image/*').split(','),
                maxFileSize: (input.dataset.filepondMaxsize || '10') + 'MB'
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

    // Initialize when DOM is ready
    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('rex:ready', initFilePond);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFilePond);
    } else {
        initFilePond();
    }
})();
