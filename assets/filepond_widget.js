(function() {
    // Tracking für bereits initialisierte Elemente
    const initializedElements = new Set();

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
                decorativeLabel: 'Dekoratives Bild',
                decorativeNotice: 'Nur für Bilder - alt-Text wird nicht benötigt',
                copyrightLabel: 'Copyright:',
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
                decorativeLabel: 'Decorative image',
                decorativeNotice: 'For images only - alt text not required',
                copyrightLabel: 'Copyright:',
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

            // Standardwerte für die Chunk-Größe 
            const CHUNK_SIZE = parseInt(input.dataset.filepondChunkSize || '1') * 1024 * 1024; // Konfigurierbare Größe (Default: 1MB)

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
                    
                    // Hier fügen wir eine Vorschau basierend auf dem Dateityp ein
                    if (file instanceof File) {
                        if (file.type.startsWith('image/')) {
                            // Bild-Vorschau
                            const img = document.createElement('img');
                            img.alt = '';
                            const objectURL = URL.createObjectURL(file);
                            img.src = objectURL;
                            img.onload = () => {
                                URL.revokeObjectURL(objectURL);
                            };
                            previewContainer.appendChild(img);
                        } else if (file.type.startsWith('video/')) {
                            // Video-Vorschau
                            const video = document.createElement('video');
                            video.controls = true;
                            video.muted = true;
                            const objectURL = URL.createObjectURL(file);
                            video.src = objectURL;
                            video.onload = () => {
                                URL.revokeObjectURL(objectURL);
                            };
                            previewContainer.appendChild(video);
                        } else {
                            // Passende Font Awesome 6 Icons basierend auf dem Dateityp
                            const icon = document.createElement('div');
                            icon.className = 'simple-modal-file-icon';
                            
                            // Icon basierend auf dem Dateityp bestimmen
                            let iconClass = 'fa-file'; // Standard-Icon
                            if (file.type) {
                                if (file.type.includes('pdf')) {
                                    iconClass = 'fa-file-pdf';
                                } else if (file.type.includes('excel') || file.type.includes('spreadsheet') || file.type.includes('csv') || file.name?.endsWith('.xlsx') || file.name?.endsWith('.xls')) {
                                    iconClass = 'fa-file-excel';
                                } else if (file.type.includes('word') || file.type.includes('document') || file.name?.endsWith('.docx') || file.name?.endsWith('.doc')) {
                                    iconClass = 'fa-file-word';
                                } else if (file.type.includes('powerpoint') || file.type.includes('presentation') || file.name?.endsWith('.pptx') || file.name?.endsWith('.ppt')) {
                                    iconClass = 'fa-file-powerpoint';
                                } else if (file.type.includes('zip') || file.type.includes('archive') || file.type.includes('compressed')) {
                                    iconClass = 'fa-file-archive';
                                } else if (file.type.includes('audio')) {
                                    iconClass = 'fa-file-audio';
                                } else if (file.type.includes('text') || file.type.includes('plain') || file.name?.endsWith('.txt')) {
                                    iconClass = 'fa-file-alt';
                                } else if (file.type.includes('code') || file.name?.endsWith('.json') || file.name?.endsWith('.js') || file.name?.endsWith('.html') || file.name?.endsWith('.css') || file.name?.endsWith('.php')) {
                                    iconClass = 'fa-file-code';
                                }
                            } else if (file.name) {
                                // Erkennung basierend auf dem Dateinamen als Fallback
                                const name = file.name.toLowerCase();
                                if (name.endsWith('.pdf')) {
                                    iconClass = 'fa-file-pdf';
                                } else if (name.endsWith('.xlsx') || name.endsWith('.xls') || name.endsWith('.csv')) {
                                    iconClass = 'fa-file-excel';
                                } else if (name.endsWith('.docx') || name.endsWith('.doc')) {
                                    iconClass = 'fa-file-word';
                                } else if (name.endsWith('.pptx') || name.endsWith('.ppt')) {
                                    iconClass = 'fa-file-powerpoint';
                                } else if (name.endsWith('.zip') || name.endsWith('.rar') || name.endsWith('.7z') || name.endsWith('.tar') || name.endsWith('.gz')) {
                                    iconClass = 'fa-file-archive';
                                } else if (name.endsWith('.mp3') || name.endsWith('.wav') || name.endsWith('.ogg') || name.endsWith('.flac')) {
                                    iconClass = 'fa-file-audio';
                                } else if (name.endsWith('.txt')) {
                                    iconClass = 'fa-file-alt';
                                } else if (name.endsWith('.json') || name.endsWith('.js') || name.endsWith('.html') || name.endsWith('.css') || name.endsWith('.php')) {
                                    iconClass = 'fa-file-code';
                                }
                            }
                            
                            icon.innerHTML = `<i class="fa fa-solid ${iconClass} fa-5x"></i>`;
                            previewContainer.appendChild(icon);
                        }
                    } else if (typeof file.source === 'string') {
                        // Bereits hochgeladene Datei
                        const fileName = file.source || file.filename || 'unknown';
                        if (/\.(jpe?g|png|gif|webp|bmp|svg)$/i.test(fileName)) {
                            // Bild-Vorschau für bereits hochgeladene Dateien
                            const img = document.createElement('img');
                            img.alt = '';
                            img.src = '/media/' + fileName;
                            previewContainer.appendChild(img);
                        } else if (/\.(mp4|webm|ogg|mov)$/i.test(fileName)) {
                            // Video-Vorschau für bereits hochgeladene Dateien
                            const video = document.createElement('video');
                            video.controls = true;
                            video.muted = true;
                            video.src = '/media/' + fileName;
                            previewContainer.appendChild(video);
                        } else {
                            // Icon basierend auf Dateiendung
                            const icon = document.createElement('div');
                            icon.className = 'simple-modal-file-icon';
                            
                            // Icon basierend auf der Dateiendung bestimmen
                            let iconClass = 'fa-file'; // Standard-Icon
                            const name = fileName.toLowerCase();
                            
                            if (name.endsWith('.pdf')) {
                                iconClass = 'fa-file-pdf';
                            } else if (name.endsWith('.xlsx') || name.endsWith('.xls') || name.endsWith('.csv')) {
                                iconClass = 'fa-file-excel';
                            } else if (name.endsWith('.docx') || name.endsWith('.doc')) {
                                iconClass = 'fa-file-word';
                            } else if (name.endsWith('.pptx') || name.endsWith('.ppt')) {
                                iconClass = 'fa-file-powerpoint';
                            } else if (name.endsWith('.zip') || name.endsWith('.rar') || name.endsWith('.7z') || name.endsWith('.tar') || name.endsWith('.gz')) {
                                iconClass = 'fa-file-archive';
                            } else if (name.endsWith('.mp3') || name.endsWith('.wav') || name.endsWith('.ogg') || name.endsWith('.flac')) {
                                iconClass = 'fa-file-audio';
                            } else if (name.endsWith('.txt')) {
                                iconClass = 'fa-file-alt';
                            } else if (name.endsWith('.json') || name.endsWith('.js') || name.endsWith('.html') || name.endsWith('.css') || name.endsWith('.php')) {
                                iconClass = 'fa-file-code';
                            }
                            
                            icon.innerHTML = `<i class="fa fa-solid ${iconClass} fa-5x"></i>`;
                            previewContainer.appendChild(icon);
                        }
                    }
                    
                    previewCol.appendChild(previewContainer);

                    // Form Fields
                    const formCol = document.createElement('div');
                    formCol.className = 'simple-modal-col-8';
                    
                    // Prüfen, ob es sich um ein Bild handelt
                    const isImage = file.type?.startsWith('image/') || 
                                    (file instanceof File && file.type.startsWith('image/'));
                    
                    formCol.innerHTML = `
                        <div class="simple-modal-form-group">
                            <label for="title">${t.titleLabel}</label>
                            <input type="text" id="title" name="title" class="simple-modal-input" required value="${existingMetadata?.title || ''}">
                        </div>
                        ${isImage ? `
                        <div class="simple-modal-form-group" id="alt-text-group">
                            <label for="alt">${t.altLabel}</label>
                            <input type="text" id="alt" name="alt" class="simple-modal-input" required value="${existingMetadata?.alt || ''}">
                            <div class="help-text">${t.altNotice}</div>
                        </div>
                        <div class="simple-modal-form-group">
                            <div class="simple-modal-checkbox-wrapper">
                                <input type="checkbox" id="decorative" name="decorative" class="simple-modal-checkbox" ${existingMetadata?.decorative ? 'checked' : ''}>
                                <label for="decorative">${t.decorativeLabel}</label>
                            </div>
                            <div class="help-text">${t.decorativeNotice}</div>
                        </div>
                        ` : ''}
                        <div class="simple-modal-form-group">
                            <label for="copyright">${t.copyrightLabel}</label>
                            <input type="text" id="copyright" name="copyright" class="simple-modal-input" value="${existingMetadata?.copyright || ''}">
                        </div>
                    `;

                    form.appendChild(previewCol);
                    form.appendChild(formCol);

                    const modal = new SimpleModal();

                    // Event-Handler für die "Dekorativ"-Checkbox, wenn vorhanden
                    if (isImage) {
                        setTimeout(() => {
                            const decorativeCheckbox = form.querySelector('#decorative');
                            const altInput = form.querySelector('#alt');
                            const altGroup = form.querySelector('#alt-text-group');
                            
                            if (decorativeCheckbox && altInput && altGroup) {
                                // Initialen Zustand setzen
                                if (decorativeCheckbox.checked) {
                                    altInput.removeAttribute('required');
                                    altGroup.classList.add('disabled');
                                    altInput.disabled = true;
                                }
                                
                                // Event-Handler für Änderungen
                                decorativeCheckbox.addEventListener('change', function() {
                                    if (this.checked) {
                                        // Wenn dekorativ, dann Alt-Text nicht erforderlich
                                        altInput.removeAttribute('required');
                                        altGroup.classList.add('disabled');
                                        altInput.disabled = true;
                                        // Alt-Text auf leer setzen (optional)
                                        altInput.value = '';
                                    } else {
                                        // Wenn nicht dekorativ, Alt-Text erforderlich
                                        altInput.setAttribute('required', 'required');
                                        altGroup.classList.remove('disabled');
                                        altInput.disabled = false;
                                    }
                                });
                            }
                        }, 100); // Kurze Verzögerung für DOM-Rendering
                    }

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
                                    const decorativeCheckbox = form.querySelector('#decorative');
                                    const isDecorative = decorativeCheckbox && decorativeCheckbox.checked;

                                    // Alt-Text ist nur für Bilder erforderlich, die nicht als dekorativ markiert sind
                                    // Bei anderen Dateitypen ist kein Alt-Text erforderlich
                                    let isValid = titleInput.value;
                                    
                                    if (isImage && altInput && !isDecorative) {
                                        // Nur bei Bildern, die nicht dekorativ sind, Alt-Text prüfen
                                        isValid = isValid && altInput.value;
                                    }

                                    if (isValid) {
                                        const metadata = {
                                            title: titleInput.value,
                                            alt: (isImage && altInput) ? (isDecorative ? '' : altInput.value) : '',
                                            copyright: copyrightInput.value,
                                            decorative: isDecorative || false
                                        };
                                        modal.close();
                                        resolve(metadata);
                                    } else {
                                        if (!titleInput.value) titleInput.reportValidity();
                                        if (isImage && !isDecorative && altInput && !altInput.value) altInput.reportValidity();
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
                            formData.append('skipMeta', skipMeta ? '1' : '0'); // skipMeta-Parameter für Chunks

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
                    
                    // Umstellung auf finale direkte Anfrage statt weiteren Chunk-Upload
                    const finalFormData = new FormData();
                    finalFormData.append('rex-api-call', 'filepond_uploader');
                    finalFormData.append('func', 'finalize-upload'); // Neue Funktion zum Finalisieren
                    finalFormData.append('fileId', fileId);
                    finalFormData.append('fieldName', fieldName);
                    finalFormData.append('fileName', file.name);
                    finalFormData.append('category_id', input.dataset.filepondCat || '0');
                    finalFormData.append('totalChunks', totalChunks);
                    finalFormData.append('skipMeta', skipMeta ? '1' : '0'); // skipMeta-Parameter für Chunks
                    
                    // Letzter Chunk gibt in result.filename den tatsächlichen Dateinamen zurück
                    const lastChunkResponse = await fetch(basePath, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: finalFormData
                    });
                    
                    if (!lastChunkResponse.ok) {
                        throw new Error(`Final upload failed with status: ${lastChunkResponse.status}`);
                    }
                    
                    const finalResult = await lastChunkResponse.json();
                    
                    // Den tatsächlichen Dateinamen aus dem Medienpool verwenden
                    if (finalResult.filename) {
                        load(finalResult.filename);
                    } else {
                        // Fallback auf den Originalnamen
                        load(file.name);
                    }

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
                
                // Verzögerter Upload-Modus
                instantUpload: function() {
                    const isDelayed = input.hasAttribute('data-filepond-delayed-upload') && 
                                     input.getAttribute('data-filepond-delayed-upload') === 'true';
                    console.log('Delayed Upload Mode enabled:', isDelayed);
                    return !isDelayed; // instantUpload ist das Gegenteil von delayed
                }(),
                
                // Wichtige Optionen für den verzögerten Upload-Modus
                allowRemove: true,        // Erlaube Entfernen von Dateien
                allowProcess: false,      // Deaktiviere automatisches Verarbeiten
                allowRevert: true,        // Erlaube Rückgängigmachen
                allowImagePreview: true,  // Erlaube Bildvorschau
                imagePreviewHeight: 100,  // Höhe der Vorschaubilder
                
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
                                    copyright: ''
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
                                uploadFormData.append('skipMeta', skipMeta ? '1' : '0'); // Direkt skipMeta-Parameter übergeben

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
                                // Wir verwenden den tatsächlichen Dateinamen aus dem Medienpool statt des Originalnamens
                                if (typeof result === 'object' && result.filename) {
                                    load(result.filename);
                                } else if (typeof result === 'string') {
                                    load(result);
                                } else {
                                    load(file.name);
                                }
                            }
                        } catch (err) {
                            if (err.message !== 'Metadata input cancelled') {
                                console.error('Upload error:', err);
                                error('Upload failed: ' + err.message);
                            } else {
                                console.log('Metadata dialog cancelled');
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
            
            // Speichere Referenz auf pond-Instanz im input-Element
            input.pondInstance = pond;
            
            // Speichere die Referenz auch im DOM-Element, um sie später leichter zu finden
            const pondRoot = pond.element.parentNode;
            if (pondRoot) {
                pondRoot.pondReference = pond;
                
                // Für verzögerten Upload-Modus: Füge einen Upload-Button hinzu
                const isDelayedUpload = input.hasAttribute('data-filepond-delayed-upload') && 
                                        input.getAttribute('data-filepond-delayed-upload') === 'true';
                
                if (isDelayedUpload) {
                    // Generiere eine eindeutige ID für den Button basierend auf der Input-ID
                    const buttonId = `filepond-upload-btn-${input.id || Math.random().toString(36).substring(2, 15)}`;
                    
                    // Erstelle einen Upload-Button mit eigenem Stil (ohne Bootstrap-Klassen)
                    const uploadBtn = document.createElement('button');
                    uploadBtn.type = 'button';
                    uploadBtn.className = 'filepond-upload-btn';
                    uploadBtn.id = buttonId;
                    uploadBtn.setAttribute('data-for', input.id || '');
                    const uploadButtonText = translations[lang]?.uploadButton || 'Dateien hochladen';
                    uploadBtn.textContent = uploadButtonText;
                    uploadBtn.setAttribute('aria-label', uploadButtonText);
                    
                    // Container für den Button
                    const buttonContainer = document.createElement('div');
                    buttonContainer.className = 'filepond-upload-button-container';
                    buttonContainer.appendChild(uploadBtn);
                    
                    // Button direkt nach dem FilePond-Element einfügen (nicht innerhalb)
                    pondRoot.insertAdjacentElement('afterend', buttonContainer);
                    
                    // Event-Listener für den Button
                    uploadBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        console.log('Upload button clicked for input:', input.id);
                        if (pond && typeof pond.processFiles === 'function') {
                            pond.processFiles();
                        }
                    });
                }
            }
            
            // Globales Objekt für alle FilePond-Instanzen, falls nicht vorhanden
            if (!window.FilePondGlobal) {
                window.FilePondGlobal = {
                    instances: {}
                };
            }
            
            // Speichere diese Instanz mit ihrer ID
            window.FilePondGlobal.instances[input.id] = pond;

            // Event handlers
            pond.on('processfile', (error, file) => {
                if (!error && file.serverId) {
                    // Prüfen, ob maxFiles=1 ist - in diesem Fall ersetzen wir den kompletten Wert
                    const maxFiles = parseInt(input.dataset.filepondMaxfiles) || null;
                    
                    if (maxFiles === 1) {
                        // Bei maxFiles=1 kompletten Wert ersetzen statt anzuhängen
                        input.value = file.serverId;
                    } else {
                        // Standardverhalten: An bestehenden Wert anhängen
                        const currentValue = input.value ? input.value.split(',').filter(Boolean) : [];
                        if (!currentValue.includes(file.serverId)) {
                            currentValue.push(file.serverId);
                            input.value = currentValue.join(',');
                        }
                    }
                    
                    // Versuchen, den Dateinamen in der FilePond-UI zu aktualisieren
                    try {
                        // Finde das DOM-Element für diese Datei über die FilePond-API
                        const fileElement = pond.getFiles().find(f => f.id === file.id)?.element;
                        
                        if (fileElement) {
                            // Aktualisieren der Dateiansicht im FilePond Widget
                            const fileInfo = fileElement.querySelector('.filepond--file-info-main');
                            if (fileInfo) {
                                // Dateiname anzeigen, aber Status "Uploaded" beibehalten
                                fileInfo.textContent = file.serverId;
                            }
                        }
                    } catch (err) {
                        console.warn('Failed to update file name in UI:', err);
                        // Fehler ignorieren, ist nur kosmetisch
                    }
                }
            });

            pond.on('removefile', (error, file) => {
                if (!error) {
                    // Sicherstellen, dass wir den aktuellsten Wert haben
                    const currentValue = input.value ? input.value.split(',').filter(Boolean) : [];
                    const removeValue = file.serverId || file.source;
                    
                    // Entferne alle Vorkommen dieses Wertes (für den Fall von Duplikaten)
                    const filteredValue = currentValue.filter(val => val !== removeValue);
                    
                    // Wenn sich die Anzahl geändert hat, wurde etwas entfernt
                    if (filteredValue.length !== currentValue.length) {
                        // Neuen Wert direkt setzen
                        input.value = filteredValue.join(',');
                        
                        // Explizit ein change-Event auslösen, damit Frameworks wie jQuery die Änderung erkennen
                        const event = new Event('change', { bubbles: true });
                        input.dispatchEvent(event);
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
