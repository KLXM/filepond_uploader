(function() {
    // Tracking für bereits initialisierte Elemente
    const initializedElements = new Set();

    const initFilePond = () => {
        console.log('initFilePond function called');

        // Standardwerte für die Chunk-Größe (5MB)
        const CHUNK_SIZE = 5 * 1024 * 1024;
        // Translations
        const translations = {
            de_de: {
                labelIdle: 'Dateien hierher ziehen oder <span class="filepond--label-action">durchsuchen</span>',
                metaTitle: 'Metadaten für',
                titleLabel: 'Titel:',
                altLabel: 'Alt-Text:',
                altNotice: 'Alternativtext für Screenreader und SEO',
                copyrightLabel: 'Copyright:',
                descriptionLabel: 'Beschreibung:',
                fileInfo: 'Datei',
                fileSize: 'Größe',
                saveBtn: 'Speichern',
                cancelBtn: 'Abbrechen',
                chunkStatus: 'Chunk {current} von {total} hochgeladen',
                retry: 'Erneut versuchen',
                resumeUpload: 'Upload fortsetzen'
            },
            en_gb: {
                labelIdle: 'Drag & Drop your files or <span class="filepond--label-action">Browse</span>',
                metaTitle: 'Metadata for',
                titleLabel: 'Title:',
                altLabel: 'Alt Text:',
                altNotice: 'Alternative text for screen readers and SEO',
                copyrightLabel: 'Copyright:',
                descriptionLabel: 'Description:',
                fileInfo: 'File',
                fileSize: 'Size',
                saveBtn: 'Save',
                cancelBtn: 'Cancel',
                chunkStatus: 'Chunk {current} of {total} uploaded',
                retry: 'Retry',
                resumeUpload: 'Resume upload'
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
            return window.location.origin;
        };
        const basePath = getBasePath();
        // console.log('Basepath ermittelt:', basePath);

        document.querySelectorAll('input[data-widget="filepond"]').forEach(input => {
            // Prüfen, ob das Element bereits initialisiert wurde
            if (initializedElements.has(input)) {
               // console.log('FilePond element already initialized, skipping:', input);
                return;
            }

           // console.log('FilePond input element found:', input);
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
                        <div class="simple-modal-form-group">
                            <label for="description">${t.descriptionLabel}</label>
                            <textarea id="description" name="description" class="simple-modal-input" rows="3">${existingMetadata?.description || ''}</textarea>
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
                                    const descriptionInput = form.querySelector('[name="description"]');

                                    if (titleInput.value && altInput.value) {
                                        const metadata = {
                                            title: titleInput.value,
                                            alt: altInput.value,
                                            copyright: copyrightInput.value,
                                            description: descriptionInput.value
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
                            } : {})
                        }
                    };
                }) : [];

            // Funktion zum Verarbeiten des Chunk-Uploads mit verbesserter Fehlerbehandlung
            const processFileInChunks = async (fieldName, file, metadata, load, error, progress, abort, transfer, options) => {
                let fileId;
                const abortController = new AbortController();

                try {
                    // 1. Metadaten senden und Upload vorbereiten
                    const prepareFormData = new FormData();
                    prepareFormData.append('rex-api-call', 'filepond_uploader');
                    prepareFormData.append('func', 'prepare');
                    prepareFormData.append('fileName', file.name);
                    prepareFormData.append('fieldName', fieldName);
                    prepareFormData.append('metadata', JSON.stringify(metadata));

                    // Warten auf erfolgreiche Vorbereitung - mit Wiederholungsversuchen
                    let prepareSuccess = false;
                    let prepareAttempts = 0;
                    fileId = null;

                    while (!prepareSuccess && prepareAttempts < 3) {
                        try {
                            const prepareResponse = await fetch(basePath, {
                                method: 'POST',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: prepareFormData,
                                signal: abortController.signal
                            });

                            if (!prepareResponse.ok) {
                                throw new Error('Preparation failed');
                            }

                            const prepareResult = await prepareResponse.json();
                            fileId = prepareResult.fileId;
                            prepareSuccess = true;

                            // Kurze Pause nach erfolgreicher Vorbereitung, damit Metadaten gespeichert werden können
                            await new Promise(resolve => setTimeout(resolve, 500));
                        } catch (err) {
                            prepareAttempts++;
                            console.warn(`Preparation attempt ${prepareAttempts} failed: ${err.message}`);

                            if (prepareAttempts >= 3) {
                                throw new Error('Upload preparation failed after multiple attempts');
                            }

                            // Warten vor dem nächsten Versuch
                            await new Promise(resolve => setTimeout(resolve, 1000));
                        }
                    }

                    if (!fileId) {
                        throw new Error('Failed to prepare upload');
                    }

                    // 2. Datei in Chunks aufteilen und hochladen - SEQUENTIELL mit Promises
                    const fileSize = file.size;
                    const totalChunks = Math.ceil(fileSize / CHUNK_SIZE);
                    let uploadedBytes = 0;

                    const uploadChunk = (chunkIndex) => {
                        return new Promise(async (resolve, reject) => {
                            const start = chunkIndex * CHUNK_SIZE;
                            const end = Math.min(start + CHUNK_SIZE, fileSize);
                            const chunk = file.slice(start, end);

                            const formData = new FormData();
                            formData.append(fieldName, chunk);
                            formData.append('rex-api-call', 'filepond_uploader');
                            formData.append('func', 'chunk-upload');
                            formData.append('fileId', fileId);
                            formData.append('fieldName', fieldName);
                            formData.append('chunkIndex', chunkIndex);
                            formData.append('totalChunks', totalChunks);
                            formData.append('fileName', file.name);
                            formData.append('category_id', input.dataset.filepondCat || '0');

                            try {
                               // console.log(`Uploading chunk ${chunkIndex} of ${totalChunks}`);  // Chunk Index Logging
                                const chunkResponse = await fetch(basePath, {
                                    method: 'POST',
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest'
                                    },
                                    body: formData,
                                    signal: abortController.signal
                                });

                                if (!chunkResponse.ok) {
                                    throw new Error(`Chunk upload failed with status: ${chunkResponse.status}`);
                                }

                                const result = await chunkResponse.json();

                                if (result.status === 'chunk-success') {
                                    uploadedBytes += (end - start);
                                    progress(true, uploadedBytes, fileSize);
                                    resolve();  // Chunk erfolgreich hochgeladen
                                } else {
                                    throw new Error(`Unexpected response: ${JSON.stringify(result)}`);
                                }
                            } catch (err) {
                                console.error(`Chunk ${chunkIndex} upload failed: ${err.message}`);
                                reject(err);  // Fehler beim Hochladen des Chunks
                            }
                        });
                    };

                    // Sequentielles Hochladen der Chunks mit Promises
                    for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                        try {
                            await uploadChunk(chunkIndex);
                        } catch (err) {
                            console.error(`Upload failed at chunk ${chunkIndex}: ${err.message}`);
                            error(`Upload failed: ${err.message}`);
                            abort();
                            return;
                        }
                    }

                    // Wenn alle Chunks erfolgreich hochgeladen wurden
                    // console.log('All chunks uploaded successfully, finalizing upload');
                    // *** ACHTUNG: Verarbeite result.filename anstelle von file.name ***
                    load(file.name);

                } catch (err) {
                    if (err.name === 'AbortError') {
                        abort();
                    } else {
                        console.error('Chunk upload error:', err);
                        error('Upload failed: ' + err.message);
                    }
                }

                return {
                    abort: () => {
                        abortController.abort();
                        abort();
                    }
                };
            };

            // Initialize FilePond
            const pond = FilePond.create(fileInput, {
                files: existingFiles,
                allowMultiple: true,
                allowReorder: true,
                maxFiles: parseInt(input.dataset.filepondMaxfiles) || null,
                chunkSize: CHUNK_SIZE,
                chunkForce: input.dataset.filepondChunkEnabled !== 'false', // Standardmäßig aktiviert, außer explizit deaktiviert
                server: {
                    url: basePath,
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
                                    copyright: '',
                                    description: ''
                                };
                            }

                            // Entscheiden, ob normaler Upload oder Chunk-Upload
                            const useChunks = input.dataset.filepondChunkEnabled !== 'false' && file.size > CHUNK_SIZE;

                            if (useChunks) {
                                // Großer File - Chunk Upload
                                return processFileInChunks(fieldName, file, fileMetadata, load, error, progress, abort, transfer, options);
                            } else {
                                // Standard Upload für kleine Dateien
                                const formData = new FormData();
                                formData.append(fieldName, file);
                                formData.append('rex-api-call', 'filepond_uploader');
                                formData.append('func', 'prepare');
                                formData.append('fileName', file.name);
                                formData.append('fieldName', fieldName);
                                formData.append('metadata', JSON.stringify(fileMetadata));

                                // Vorbereitung für den Upload
                                const prepareResponse = await fetch(basePath, {
                                    method: 'POST',
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest'
                                    },
                                    body: formData
                                });

                                if (!prepareResponse.ok) {
                                    const result = await prepareResponse.json();
                                    error(result.error || 'Upload preparation failed');
                                    return;
                                }

                                const prepareResult = await prepareResponse.json();
                                const fileId = prepareResult.fileId;

                                // Eigentlicher Upload
                                const uploadFormData = new FormData();
                                uploadFormData.append(fieldName, file);
                                uploadFormData.append('rex-api-call', 'filepond_uploader');
                                uploadFormData.append('func', 'upload');
                                uploadFormData.append('fileId', fileId);
                                uploadFormData.append('fieldName', fieldName);
                                uploadFormData.append('category_id', input.dataset.filepondCat || '0');

                                const response = await fetch(basePath, {
                                    method: 'POST',
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest'
                                    },
                                    body: uploadFormData
                                });

                                if (!response.ok) {
                                    const result = await response.json();
                                    error(result.error || 'Upload failed');
                                    return;
                                }

                                const result = await response.json();
                                load(result);
                            }
                        } catch (err) {
                            if (err.message !== 'Metadata input cancelled') {
                                console.error('Upload error:', err);
                                error('Upload failed: ' + err.message);
                            } else {
                                error('Upload cancelled');
                                abort();
                            }
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
                        // console.log('FilePond load url:', url);

                        fetch(url)
                            .then(response => {
                                // console.log('FilePond load response:', response);
                                if (!response.ok) {
                                    throw new Error('HTTP error! status: ' + response.status);
                                }
                                return response.blob();
                            })
                            .then(blob => {
                                // console.log('FilePond load blob:', blob);
                                load(blob);
                            })
                            .catch(e => {
                                // console.error('FilePond load error:', e);
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

            // Element als initialisiert markieren
            initializedElements.add(input);
        });
    };

    // Initialize based on environment - Hier muss sichergestellt werden, dass nur einmal gestartet wird
    // Wir zählen die Initialisierungen
    let initCount = 0;
    const safeInitFilePond = () => {
        // Logging hinzufügen
        // console.log(`FilePond initialization attempt ${++initCount}`);
        initFilePond();
    };

    // jQuery hat höchste Priorität, wenn vorhanden
    if (typeof jQuery !== 'undefined') {
        jQuery(document).one('rex:ready', safeInitFilePond);
    } else {
        // Ansonsten einen normalen DOMContentLoaded-Listener verwenden
        if (document.readyState !== 'loading') {
            // DOM ist bereits geladen
            safeInitFilePond();
        } else {
            // Nur einmal initialisieren beim DOMContentLoaded
            document.addEventListener('DOMContentLoaded', safeInitFilePond, {once: true});
        }
    }

    // Event für manuelle Initialisierung - auch hier sicherstellen, dass es nur einmal ausgelöst wird
    document.addEventListener('filepond:init', safeInitFilePond);

    // Expose initFilePond globally if needed - auch hier die sichere Variante exportieren
    window.initFilePond = safeInitFilePond;
})();
