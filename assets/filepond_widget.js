(function() {
    // Tracking für bereits initialisierte Elemente
    const initializedElements = new Set();
    
    // Globale Variable für den aktuellen Dateityp
    let currentFileType = null;
    
    // Queue für Metadaten-Dialoge
    let metadataDialogQueue = Promise.resolve();

    const initFilePond = () => {

        // Translations
        const translations = {
            de_de: {
                labelIdle: 'Dateien hierher ziehen oder <span class="filepond--label-action">durchsuchen</span>',
                metaTitle: 'Metadaten für',
                titleLabel: 'Titel:',
                altLabel: 'Alt-Text:',
                altNotice: 'Alternativtext für Screenreader und SEO',
                decorativeLabel: 'Dekoratives Bild (kein Alt-Text erforderlich)',
                decorativeNotice: 'Nur für Bilder - alt-Text wird nicht benötigt',
                copyrightLabel: 'Copyright:',
                descriptionLabel: 'Beschreibung:',
                fileInfo: 'Datei',
                fileSize: 'Größe',
                saveBtn: 'Speichern',
                cancelBtn: 'Abbrechen',
                chunkStatus: 'Chunk {current} von {total} hochgeladen',
                retry: 'Erneut versuchen',
                resumeUpload: 'Upload fortsetzen',
                uploadButton: 'Dateien hochladen'
            },
            en_gb: {
                labelIdle: 'Drag & Drop your files or <span class="filepond--label-action">Browse</span>',
                metaTitle: 'Metadata for',
                titleLabel: 'Title:',
                altLabel: 'Alt Text:',
                altNotice: 'Alternative text for screen readers and SEO',
                decorativeLabel: 'Decorative Image (no alt text required)',
                decorativeNotice: 'For images only - alt text not needed',
                copyrightLabel: 'Copyright:',
                descriptionLabel: 'Description:',
                fileInfo: 'File',
                fileSize: 'Size',
                saveBtn: 'Save',
                cancelBtn: 'Cancel',
                chunkStatus: 'Chunk {current} of {total} uploaded',
                retry: 'Retry',
                resumeUpload: 'Resume upload',
                uploadButton: 'Upload files'
            }
        };

        // Register FilePond plugins
        // WICHTIG: EXIF-Orientierung muss zuerst registriert werden!
        FilePond.registerPlugin(
            FilePondPluginImageExifOrientation,
            FilePondPluginFileValidateType,
            FilePondPluginFileValidateSize,
            FilePondPluginImagePreview,
            FilePondPluginImageResize,
            FilePondPluginImageTransform
        );

        // Funktion zum Konvertieren von Dateiendungen zu MIME-Types
        const extensionToMimeType = (extension) => {
            // Entferne den führenden Punkt, falls vorhanden
            const ext = extension.toLowerCase().replace(/^\./, '');
            
            const mimeMap = {
                // Bilder
                'jpg': 'image/jpeg',
                'jpeg': 'image/jpeg',
                'png': 'image/png',
                'gif': 'image/gif',
                'webp': 'image/webp',
                'avif': 'image/avif',
                'svg': 'image/svg+xml',
                'bmp': 'image/bmp',
                'tiff': 'image/tiff',
                'tif': 'image/tiff',
                'ico': 'image/x-icon',
                
                // Videos
                'mp4': 'video/mp4',
                'webm': 'video/webm',
                'ogg': 'video/ogg',
                'ogv': 'video/ogg',
                'avi': 'video/x-msvideo',
                'mov': 'video/quicktime',
                'wmv': 'video/x-ms-wmv',
                'flv': 'video/x-flv',
                'mkv': 'video/x-matroska',
                
                // Audio
                'mp3': 'audio/mpeg',
                'wav': 'audio/wav',
                'ogg': 'audio/ogg',
                'oga': 'audio/ogg',
                'flac': 'audio/flac',
                'm4a': 'audio/mp4',
                'aac': 'audio/aac',
                
                // Dokumente
                'pdf': 'application/pdf',
                'doc': 'application/msword',
                'docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls': 'application/vnd.ms-excel',
                'xlsx': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'ppt': 'application/vnd.ms-powerpoint',
                'pptx': 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'odt': 'application/vnd.oasis.opendocument.text',
                'ods': 'application/vnd.oasis.opendocument.spreadsheet',
                'odp': 'application/vnd.oasis.opendocument.presentation',
                
                // Text
                'txt': 'text/plain',
                'csv': 'text/csv',
                'rtf': 'application/rtf',
                'html': 'text/html',
                'htm': 'text/html',
                'xml': 'application/xml',
                'json': 'application/json',
                
                // Archive
                'zip': 'application/zip',
                'rar': 'application/x-rar-compressed',
                '7z': 'application/x-7z-compressed',
                'tar': 'application/x-tar',
                'gz': 'application/gzip',
                'bz2': 'application/x-bzip2'
            };
            
            return mimeMap[ext] || null;
        };

        // Funktion zum Normalisieren von acceptedFileTypes
        // Konvertiert Dateiendungen (.pdf, .doc) in MIME-Types (application/pdf, application/msword)
        // Behält MIME-Types und Wildcards (image/*, video/*) bei
        const normalizeFileTypes = (typesString) => {
            if (!typesString) return [];
            
            const types = typesString.split(',').map(type => type.trim()).filter(Boolean);
            const normalized = [];
            
            types.forEach(type => {
                // Wenn es ein MIME-Type oder Wildcard ist (enthält '/'), direkt übernehmen
                if (type.includes('/')) {
                    normalized.push(type);
                }
                // Wenn es eine Dateiendung ist (beginnt mit '.'), konvertieren
                else if (type.startsWith('.')) {
                    const mimeType = extensionToMimeType(type);
                    if (mimeType) {
                        normalized.push(mimeType);
                    } else {
                        // Fallback: Behalte die Endung für FilePond's eigene Validierung
                        console.warn(`Unknown file extension: ${type}, keeping as-is`);
                        normalized.push(type);
                    }
                }
                // Wenn es weder '/' noch '.' enthält, versuche es als Endung
                else {
                    const mimeType = extensionToMimeType(type);
                    if (mimeType) {
                        normalized.push(mimeType);
                    } else {
                        console.warn(`Could not convert type: ${type}, keeping as-is`);
                        normalized.push(type);
                    }
                }
            });
            
            return normalized;
        };

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

            // Wiederverwendbare Funktion für File Preview
            const createFilePreview = (file, container) => {
                // Clear container
                container.innerHTML = '';
                
                // Unterstütze sowohl File als auch Blob Objekte
                // Das Image Transform Plugin kann Blobs zurückgeben
                if (file instanceof File || file instanceof Blob) {
                    const fileName = file.name || '';
                    const fileType = file.type || '';
                    const isVideo = fileType.startsWith('video/') || 
                                  /\.(mp4|webm|ogg|mov|avi|wmv|flv|mkv)$/i.test(fileName);
                    
                    if (fileType.startsWith('image/')) {
                        const img = document.createElement('img');
                        img.alt = '';
                        img.style.maxWidth = '100%';
                        img.style.maxHeight = '300px';
                        img.style.objectFit = 'contain';
                        const objectURL = URL.createObjectURL(file);
                        img.src = objectURL;
                        img.onload = () => URL.revokeObjectURL(objectURL);
                        container.appendChild(img);
                    } else if (isVideo) {
                        const video = document.createElement('video');
                        video.controls = true;
                        video.muted = true;
                        video.preload = 'metadata';
                        video.style.maxWidth = '100%';
                        video.style.maxHeight = '300px';
                        video.style.objectFit = 'contain';
                        video.style.backgroundColor = '#000';
                        video.style.borderRadius = '4px';
                        
                        // Safari hat Probleme mit Blob URLs für Videos
                        // Verwende FileReader als Alternative für Safari
                        if (navigator.userAgent.includes('Safari') && !navigator.userAgent.includes('Chrome')) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                video.src = e.target.result;
                            };
                            reader.onerror = function(e) {
                                console.error('FileReader error:', e);
                                createFileIcon(container, 'fa-file-video-o');
                            };
                            reader.readAsDataURL(file);
                        } else {
                            // Standard Blob URL für andere Browser
                            const objectURL = URL.createObjectURL(file);
                            video.src = objectURL;
                            
                            video.onloadedmetadata = () => {
                                URL.revokeObjectURL(objectURL);
                            };
                        }
                        
                        video.onerror = (e) => {
                            console.error('Video loading error:', e);
                            if (video.src.startsWith('blob:')) {
                                URL.revokeObjectURL(video.src);
                            }
                            createFileIcon(container, 'fa-file-video-o');
                        };
                        
                        container.appendChild(video);
                        
                        // Versuche das Video nach kurzer Zeit zu laden falls es nicht automatisch startet
                        setTimeout(() => {
                            if (video.readyState === 0) {
                                video.load();
                            }
                        }, 1000);
                    } else {
                        // Icon für andere Dateitypen basierend auf MIME-Type
                        createFileIconFromMimeType(container, fileType, fileName);
                    }
                } else if (typeof file.source === 'string') {
                    // Bereits hochgeladene Datei
                    const fileName = file.source || file.filename || 'unknown';
                    
                    if (/\.(jpe?g|png|gif|webp|bmp|svg)$/i.test(fileName)) {
                        const img = document.createElement('img');
                        img.alt = '';
                        img.style.maxWidth = '100%';
                        img.style.maxHeight = '300px';
                        img.style.objectFit = 'contain';
                        img.src = '/media/' + fileName;
                        container.appendChild(img);
                    } else if (/\.(mp4|webm|ogg|mov|avi|wmv|flv|mkv)$/i.test(fileName)) {
                        const video = document.createElement('video');
                        video.controls = true;
                        video.muted = true;
                        video.preload = 'metadata';
                        video.style.maxWidth = '100%';
                        video.style.maxHeight = '300px';
                        video.style.objectFit = 'contain';
                        video.style.backgroundColor = '#000';
                        video.style.borderRadius = '4px';
                        video.crossOrigin = 'anonymous'; // Für CORS falls nötig
                        video.src = '/media/' + fileName;
                        
                        video.onerror = (e) => {
                            console.error('Uploaded video loading error:', e);
                            createFileIcon(container, 'fa-file-video-o');
                        };
                        
                        container.appendChild(video);
                        
                        // Versuche das Video nach kurzer Zeit zu laden falls es nicht automatisch startet
                        setTimeout(() => {
                            if (video.readyState === 0) {
                                video.load();
                            }
                        }, 1000);
                    } else {
                        // Icon für andere Dateitypen basierend auf Dateiendung
                        createFileIconFromExtension(container, fileName);
                    }
                } else {
                    createFileIcon(container, 'fa-file');
                }
            };

            // Hilfsfunktion für File Icons
            const createFileIcon = (container, iconClass) => {
                // Container leeren, falls schon andere Inhalte drin sind
                container.innerHTML = '';
                
                const icon = document.createElement('div');
                icon.className = 'simple-modal-file-icon';
                icon.style.cssText = 'width: 80px; height: 80px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; display: flex; flex-direction: column; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);';
                icon.innerHTML = `<i class="fa fa-solid ${iconClass} fa-5x"></i>`;
                container.appendChild(icon);
            };

            // Icon basierend auf MIME-Type
            const createFileIconFromMimeType = (container, mimeType, fileName) => {
                let iconClass = 'fa-file';
                
                if (mimeType) {
                    if (mimeType.includes('pdf')) {
                        iconClass = 'fa-file-pdf';
                    } else if (mimeType.includes('excel') || mimeType.includes('spreadsheet') || mimeType.includes('csv')) {
                        iconClass = 'fa-file-excel';
                    } else if (mimeType.includes('word') || mimeType.includes('document')) {
                        iconClass = 'fa-file-word';
                    } else if (mimeType.includes('powerpoint') || mimeType.includes('presentation')) {
                        iconClass = 'fa-file-powerpoint';
                    } else if (mimeType.includes('zip') || mimeType.includes('archive') || mimeType.includes('compressed')) {
                        iconClass = 'fa-file-archive';
                    } else if (mimeType.includes('audio')) {
                        iconClass = 'fa-file-audio';
                    } else if (mimeType.includes('text') || mimeType.includes('plain')) {
                        iconClass = 'fa-file-alt';
                    } else if (mimeType.includes('code') || mimeType.includes('json') || mimeType.includes('javascript')) {
                        iconClass = 'fa-file-code';
                    }
                }
                
                // Fallback auf Dateiendung wenn MIME-Type nicht hilfreich ist
                if (iconClass === 'fa-file' && fileName) {
                    return createFileIconFromExtension(container, fileName);
                }
                
                createFileIcon(container, iconClass);
            };

            // Icon basierend auf Dateiendung
            const createFileIconFromExtension = (container, fileName) => {
                let iconClass = 'fa-file';
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
                
                createFileIcon(container, iconClass);
            };

            // Create metadata dialog with SimpleModal and MetaInfo integration
            const createMetadataDialog = (file, existingMetadata = null) => {
                // Einreihen in die Queue
                const dialogPromise = metadataDialogQueue.then(() => {
                    return new Promise(async (resolve, reject) => {
                        try {
                            // Lade MetaInfo-Felder über API
                            const metaInfoFields = await loadMetaInfoFields();
                            const result = await createEnhancedMetadataDialog(file, existingMetadata, metaInfoFields);
                            resolve(result);
                        } catch (error) {
                            console.warn('MetaInfo integration failed, using standard modal:', error);
                            try {
                                const result = await createStandardMetadataDialog(file, existingMetadata);
                                resolve(result);
                            } catch (stdError) {
                                reject(stdError);
                            }
                        }
                    });
                });
                
                // Queue aktualisieren, aber Fehler abfangen damit die Kette nicht bricht
                metadataDialogQueue = dialogPromise.catch(() => {});
                
                return dialogPromise;
            };
            
            // Cache für MetaInfo-Felder
            let cachedMetaInfoFields = null;

            // Lädt MetaInfo-Felder über API
            const loadMetaInfoFields = async () => {
                if (cachedMetaInfoFields) return cachedMetaInfoFields;

                const response = await fetch('/redaxo/index.php?rex-api-call=filepond_auto_metainfo&action=get_fields', {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.error || 'Fehler beim Laden der MetaInfo-Felder');
                }
                cachedMetaInfoFields = data.fields;
                return data.fields;
            };
            
            // Pre-Fetch MetaInfo Fields sofort starten
            loadMetaInfoFields().catch(() => {});
            
            // Erweiterte MetaInfo-Dialog
            const createEnhancedMetadataDialog = (file, existingMetadata, fields) => {
                return new Promise((resolve, reject) => {
                    // Generiere eindeutige Modal-ID für diese Instanz
                    const modalId = 'modal_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                    
                    // Aktuellen Dateityp für die Feldlogik setzen
                    currentFileType = file.type || (file.file ? file.file.type : null);
                    
                    const form = document.createElement('div');
                    form.className = 'simple-modal-grid';

                    // Preview Container (verwendet neue Preview-Funktion)
                    const previewCol = document.createElement('div');
                    previewCol.className = 'simple-modal-col-4';
                    const previewContainer = document.createElement('div');
                    previewContainer.className = 'simple-modal-preview';
                    
                    // Verwende die neue wiederverwendbare Preview-Funktion
                    createFilePreview(file, previewContainer);
                    
                    previewCol.appendChild(previewContainer);

                    // Form Container mit MetaInfo-Feldern
                    const formCol = document.createElement('div');
                    formCol.className = 'simple-modal-col-8';
                    
                    let formHTML = '';
                    
                    // Sortiere Felder: title, med_title_lang, med_alt, med_copyright, rest
                    const sortedFields = sortMetaInfoFields(fields);
                    
                    for (const field of sortedFields) {
                        formHTML += createFieldHTML(field, existingMetadata, input, modalId);
                    }
                    
                    formCol.innerHTML = formHTML;
                    
                    form.appendChild(previewCol);
                    form.appendChild(formCol);

                    const modal = new SimpleModal();
                    
                    // Setup nach DOM-Einfügung
                    setTimeout(() => {
                        setupEnhancedFieldEvents(form, fields, file);
                    }, 100);

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
                                const metadata = collectEnhancedFormData(form, fields);
                                if (validateEnhancedMetadata(metadata, fields, form, input)) {
                                    // Erweiterte Metadaten - sende an unsere API
                                    saveEnhancedMetadata(file, metadata, modal, resolve, reject);
                                }
                            }
                        }
                        ]
                    });
                });
            };
            
            // Standard-Dialog (Fallback)
            const createStandardMetadataDialog = (file, existingMetadata = null) => {
                return new Promise((resolve, reject) => {
                    const form = document.createElement('div');
                    form.className = 'simple-modal-grid';

                    // Preview Container (verwendet neue Preview-Funktion)
                    const previewCol = document.createElement('div');
                    previewCol.className = 'simple-modal-col-4';
                    const previewContainer = document.createElement('div');
                    previewContainer.className = 'simple-modal-preview';
                    
                    // Verwende die neue wiederverwendbare Preview-Funktion
                    createFilePreview(file, previewContainer);
                    
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
            
            // MetaInfo-Integration Hilfsfunktionen
            
            // Sortiert Felder in gewünschter Reihenfolge
            const sortMetaInfoFields = (fields) => {
                const order = ['title', 'med_title_lang', 'med_alt', 'med_copyright', 'med_description'];
                const sorted = [];
                
                for (const fieldName of order) {
                    const field = fields.find(f => f.name === fieldName);
                    if (field) sorted.push(field);
                }
                
                for (const field of fields) {
                    if (!order.includes(field.name)) sorted.push(field);
                }
                
                return sorted;
            };
            
            // Hilfsfunktion für Übersetzungen basierend auf aktueller Sprache
            const getFieldTranslation = (fieldName, lang = 'de_de') => {
                const translationMap = {
                    'title': translations[lang]?.titleLabel || 'Titel:',
                    'med_title_lang': 'Titel (Mehrsprachig):',
                    'med_alt': translations[lang]?.altLabel || 'Alt-Text:',
                    'med_copyright': translations[lang]?.copyrightLabel || 'Copyright:',
                    'med_description': translations[lang]?.descriptionLabel || 'Beschreibung:'
                };
                return translationMap[fieldName] || fieldName;
            };
            
            // Erstellt HTML für ein MetaInfo-Feld
            const createFieldHTML = (field, existingMetadata, currentInput, modalId = '') => {
                const fieldId = `field_${field.name}`;
                const uniqueFieldId = modalId ? `${field.name}_${modalId}` : field.name;
                const isImage = currentFileType && currentFileType.startsWith('image/');
                let html = '';
                
                // Widget-Referenz für data-Attribute (verwende das übergebene Element)
                const widget = currentInput;
                
                // ALT-Felder nur bei Bildern anzeigen
                if (field.name === 'med_alt' && !isImage) {
                    return '';
                }
                
                // Übersetztes Label verwenden
                const translatedLabel = getFieldTranslation(field.name) || field.label;
                
                if (field.multilingual && field.languages && field.languages.length > 0) {
                    // Mehrsprachiges Feld - zeige erste Sprache sichtbar, andere über Globus
                    const firstLang = field.languages[0];
                    const otherLangs = field.languages.slice(1);
                    const firstLangValue = existingMetadata?.[field.name]?.[firstLang.code] || '';
                    
                    html += `<div class="simple-modal-form-group" data-field="${field.name}">`;
                    html += `<label for="${fieldId}" class="simple-modal-label">${translatedLabel}</label>`;
                    
                    // Globale dekorative Checkbox für ALT-Felder bei Bildern (gilt für alle Sprachen)
                    if (field.name === 'med_alt' && isImage) {
                        const decorativeCheckboxId = `decorative_global`;
                        html += `<div class="decorative-checkbox-group">`;
                        html += `<label for="${decorativeCheckboxId}" class="simple-modal-checkbox-label">`;
                        html += `<input type="checkbox" id="${decorativeCheckboxId}" class="decorative-checkbox-global">`;
                        html += `${translations.de_de.decorativeLabel}`;
                        html += `</label>`;
                        html += `</div>`;
                    }
                    
                    // Erste Sprache (immer sichtbar)
                    // Required-Attribut bestimmen
                    let isRequired = '';
                    if (field.name === 'med_alt' && isImage) {
                        isRequired = 'required';
                    } else if (field.name === 'med_title_lang') {
                        // Mehrsprachige Titel-Felder sind immer required
                        isRequired = 'required';
                    }
                    
                    const isDisabled = (field.name === 'med_alt' && isImage) ? 'data-decorative-target="true"' : '';
                    
                    if (field.type === 'textarea') {
                        html += `<textarea id="${fieldId}" name="${field.name}[${firstLang.code}]" class="simple-modal-input" `;
                        html += `data-field="${field.name}" data-lang="${firstLang.code}" rows="3" ${isRequired} ${isDisabled}>${firstLangValue}</textarea>`;
                    } else {
                        html += `<input type="text" id="${fieldId}" name="${field.name}[${firstLang.code}]" class="simple-modal-input" `;
                        html += `data-field="${field.name}" data-lang="${firstLang.code}" value="${firstLangValue}" ${isRequired} ${isDisabled}>`;
                    }
                    
                    // Weitere Sprachen (über Globus einblendbar)
                    if (otherLangs.length > 0) {
                        html += `<div class="lang-field-container">`;
                        html += `<button type="button" class="btn btn-default btn-xs lang-toggle" data-target="${uniqueFieldId}">`;
                        html += `<i class="fa fa-globe"></i> Weitere Sprachen (${otherLangs.length})`;
                        html += `</button>`;
                        html += `<div class="lang-fields fp-lang-fields" id="lang-fields-${uniqueFieldId}">`;
                        
                        for (const lang of otherLangs) {
                            const langValue = existingMetadata?.[field.name]?.[lang.code] || '';
                            html += `<div class="form-group">`;
                            html += `<label class="control-label">${lang.name}</label>`;
                            
                            // Keine individuelle Checkbox mehr - nutze globale dekorative Checkbox
                            
                            // Required-Attribut für verschiedene Felder
                            let langRequired = '';
                            if (field.name === 'med_title_lang') {
                                langRequired = 'required'; // Mehrsprachige Titel sind immer required
                            } else if (field.name === 'med_alt' && isImage) {
                                langRequired = 'required';
                            }
                            const langDisabled = (field.name === 'med_alt' && isImage) ? 'data-decorative-target="true"' : '';
                            
                            if (field.type === 'textarea') {
                                html += `<textarea class="simple-modal-input" name="${field.name}[${lang.code}]" `;
                                html += `data-field="${field.name}" data-lang="${lang.code}" rows="3" ${langRequired} ${langDisabled}>${langValue}</textarea>`;
                            } else {
                                html += `<input type="text" class="simple-modal-input" name="${field.name}[${lang.code}]" `;
                                html += `data-field="${field.name}" data-lang="${lang.code}" value="${langValue}" ${langRequired} ${langDisabled}>`;
                            }
                            html += `</div>`;
                        }
                        
                        html += `</div></div>`;
                    }
                    
                    html += `</div>`;
                } else {
                    // Standard-Feld
                    html += `<div class="simple-modal-form-group" data-field="${field.name}">`;
                    html += `<label for="${fieldId}" class="simple-modal-label">${translatedLabel}`;
                    
                    if (field.name === 'title') {
                        html += ` <small class="text-muted">(nur für interne Verwaltung)</small>`;
                    }
                    
                    html += `</label>`;
                    
                    // Globale dekorative Checkbox wird nur einmal angezeigt (bei mehrsprachigen Feldern)
                    
                    const fieldValue = existingMetadata?.[field.name] || '';
                    
                    // Required-Attribut für verschiedene Felder
                    let isRequired = '';
                    if (field.name === 'med_title_lang') {
                        isRequired = 'required'; // Mehrsprachige Titel sind immer required
                    } else if (field.name === 'title') {
                        // Einfaches title Feld - basierend auf data-Attribut
                        const titleRequiredAttr = widget?.getAttribute('data-filepond-title-required');
                        if (titleRequiredAttr === 'true') {
                            isRequired = 'required';
                        }
                    } else if (field.name === 'med_alt' && isImage) {
                        isRequired = 'required';
                    }
                    
                    const isDisabled = (field.name === 'med_alt' && isImage) ? 'data-decorative-target="true"' : '';
                    
                    if (field.type === 'textarea') {
                        html += `<textarea id="${fieldId}" name="${field.name}" class="simple-modal-input" `;
                        html += `data-field="${field.name}" rows="3" ${isRequired} ${isDisabled}>${fieldValue}</textarea>`;
                    } else {
                        html += `<input type="text" id="${fieldId}" name="${field.name}" class="simple-modal-input" `;
                        html += `data-field="${field.name}" value="${fieldValue}" ${isRequired} ${isDisabled}>`;
                    }
                    
                    html += `</div>`;
                }
                
                return html;
            };
            
            // Setup Events für erweiterte Felder
            const setupEnhancedFieldEvents = (form, fields, file) => {
                // Auto-Titel-Generierung
                const titleField = form.querySelector('[data-field="title"]');
                if (titleField && !titleField.value) {
                    const filename = file.name || file.filename || '';
                    const nameWithoutExt = filename.replace(/\.[^/.]+$/, '');
                    titleField.value = nameWithoutExt;
                }
                
                // Toggle-Buttons für mehrsprachige Felder
                form.querySelectorAll('.lang-toggle').forEach(button => {
                    button.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation(); // Verhindere Event-Bubbling
                        const target = button.getAttribute('data-target');
                        
                        // Suche nur innerhalb des gleichen Modals
                        const modalContainer = button.closest('.simple-modal-content') || button.closest('.simple-modal');
                        const container = modalContainer ? 
                            modalContainer.querySelector(`#lang-fields-${target}`) : 
                            document.getElementById(`lang-fields-${target}`);
                        
                        const icon = button.querySelector('i');
                        
                        if (container) {
                            if (container.style.display === 'none') {
                                container.style.display = 'block';
                                icon.className = 'fa fa-globe';
                                // Text bleibt gleich
                            } else {
                                container.style.display = 'none';
                                icon.className = 'fa fa-globe';
                                // Text bleibt gleich
                            }
                        }
                    });
                });
                
                // Globale dekorative Bild-Checkbox für ALT-Felder
                const globalDecorativeCheckbox = form.querySelector('.decorative-checkbox-global');
                if (globalDecorativeCheckbox) {
                    globalDecorativeCheckbox.addEventListener('change', function() {
                        const isChecked = this.checked;
                        
                        // Alle ALT-Felder (mehrsprachig und einsprachig) finden und entsprechend aktivieren/deaktivieren
                        const altFields = form.querySelectorAll('[data-field="med_alt"]');
                        
                        altFields.forEach(field => {
                            if (isChecked) {
                                field.disabled = true;
                                field.value = '';
                                field.removeAttribute('required');
                            } else {
                                field.disabled = false;
                                field.setAttribute('required', 'required');
                            }
                        });
                    });
                }
                
                // Alte individuelle Checkbox-Handler entfernt - nutze nur noch globale Checkbox
            };
            
            // Sammelt Daten aus erweitertem Formular
            const collectEnhancedFormData = (form, fields) => {
                const metadata = {};
                const inputs = form.querySelectorAll('input, textarea');
                
                inputs.forEach(input => {
                    const fieldName = input.getAttribute('data-field');
                    const langCode = input.getAttribute('data-lang');
                    
                    if (fieldName) {
                        if (langCode) {
                            // Mehrsprachiges Feld
                            if (!metadata[fieldName]) metadata[fieldName] = {};
                            metadata[fieldName][langCode] = input.value;
                        } else {
                            // Standard-Feld
                            metadata[fieldName] = input.value;
                        }
                    }
                });
                
                return metadata;
            };
            
            // Validiert erweiterte Metadaten
            const validateEnhancedMetadata = (metadata, fields, form, currentInput) => {
                let isValid = true;
                let firstInvalidField = null;
                
                // Alle vorherigen Fehlermeldungen entfernen
                form.querySelectorAll('.field-error, .field-error-message').forEach(el => el.remove());
                form.querySelectorAll('.simple-modal-input').forEach(el => {
                    el.classList.remove('error');
                    el.style.borderColor = '';
                });
                
                for (const field of fields) {
                    let isFieldRequired = field.required;
                    
                    // ALT-Felder sind bei Bildern automatisch Pflicht (außer bei dekorativen Bildern)
                    if (field.name === 'med_alt') {
                        const isImage = currentFileType && currentFileType.startsWith('image/');
                        if (!isImage) continue; // ALT-Feld nicht erforderlich bei Nicht-Bildern
                        
                        isFieldRequired = true; // ALT ist bei Bildern immer Pflicht
                        
                        // Prüfen ob die globale dekorative Checkbox aktiviert ist
                        const globalDecorativeCheckbox = form.querySelector('.decorative-checkbox-global');
                        let hasDecorativeOverride = false;
                        
                        if (globalDecorativeCheckbox && globalDecorativeCheckbox.checked) {
                            hasDecorativeOverride = true;
                        }
                        
                        if (hasDecorativeOverride) {
                            continue; // ALT-Feld nicht erforderlich wenn dekorativ markiert
                        }
                    }
                    
                    // med_title_lang ist standardmäßig Pflicht (kann per Attribut überschrieben werden)
                    if (field.name === 'med_title_lang') {
                        // Mehrsprachige Titel-Felder sind immer required
                        isFieldRequired = true;
                    }
                    
                    // Einfaches title Feld - basierend auf data-Attribut
                    if (field.name === 'title') {
                        const titleRequiredAttr = currentInput?.getAttribute('data-filepond-title-required');
                        isFieldRequired = titleRequiredAttr === 'true';
                    }
                    
                    // Validierung für required Felder (inkl. ALT bei Bildern)
                    if (isFieldRequired) {
                        let fieldIsValid = true;
                        
                        if (field.multilingual && field.languages) {
                            // Mehrsprachige Felder: mindestens eine Sprache muss ausgefüllt sein
                            let hasAnyValue = false;
                            let requiredLanguages = [...field.languages]; // Kopie für Manipulation
                            
                            // Bei ALT-Feldern: Sprachen mit dekorativen Checkboxen ausschließen
                            if (field.name === 'med_alt') {
                                requiredLanguages = field.languages.filter(lang => {
                                    const decorativeCheckbox = form.querySelector(`.decorative-checkbox[data-lang="${lang.code}"]`);
                                    const isDecorative = decorativeCheckbox && decorativeCheckbox.checked;
                                    return !isDecorative;
                                });
                                
                                // Wenn alle Sprachen als dekorativ markiert sind, ist das Feld gültig
                                if (requiredLanguages.length === 0) {
                                    hasAnyValue = true;
                                }
                            }
                            
                            // Prüfe nur die erforderlichen Sprachen
                            for (const lang of requiredLanguages) {
                                const value = metadata[field.name]?.[lang.code];
                                if (value && value.toString().trim() !== '') {
                                    hasAnyValue = true;
                                    break;
                                }
                            }
                            
                            if (!hasAnyValue && requiredLanguages.length > 0) {
                                fieldIsValid = false;
                                // Erste erforderliche Sprache als Fehlerfeld markieren
                                const firstLangInput = form.querySelector(`[data-field="${field.name}"][data-lang="${requiredLanguages[0].code}"]`);
                                if (firstLangInput && !firstInvalidField) {
                                    firstInvalidField = firstLangInput;
                                }
                                markFieldAsError(form, field.name, requiredLanguages[0].code);
                            }
                        } else {
                            // Einsprachige Felder
                            const value = metadata[field.name];
                            if (!value || value.toString().trim() === '') {
                                fieldIsValid = false;
                                const input = form.querySelector(`[data-field="${field.name}"]:not([data-lang])`);
                                if (input && !firstInvalidField) {
                                    firstInvalidField = input;
                                }
                                markFieldAsError(form, field.name);
                            }
                        }
                        
                        if (!fieldIsValid) {
                            isValid = false;
                        }
                    }
                }
                
                // Erstes ungültiges Feld fokussieren
                if (!isValid && firstInvalidField) {
                    firstInvalidField.focus();
                    firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                
                return isValid;
            };
            
            // Hilfsfunktion zum Markieren von Feldern als fehlerhaft
            const markFieldAsError = (form, fieldName, langCode = null) => {
                const selector = langCode 
                    ? `[data-field="${fieldName}"][data-lang="${langCode}"]`
                    : `[data-field="${fieldName}"]:not([data-lang])`;
                
                const input = form.querySelector(selector);
                if (input) {
                    input.classList.add('error');
                    input.style.borderColor = '#dc3545';
                    
                    // Fehlermeldung hinzufügen wenn noch nicht vorhanden
                    const fieldGroup = input.closest('.simple-modal-form-group');
                    if (fieldGroup && !fieldGroup.querySelector('.field-error-message')) {
                        const errorMsg = document.createElement('div');
                        errorMsg.className = 'field-error-message';
                        errorMsg.style.color = '#dc3545';
                        errorMsg.style.fontSize = '12px';
                        errorMsg.style.marginTop = '4px';
                        errorMsg.textContent = 'Dieses Feld ist erforderlich';
                        fieldGroup.appendChild(errorMsg);
                    }
                }
            };
            
            // Speichert erweiterte Metadaten über unsere API
            const saveEnhancedMetadata = async (file, metadata, modal, resolve, reject) => {
                try {
                    // Wenn Datei bereits hochgeladen ist (serverId vorhanden)
                    if (file.serverId) {
                        const formData = new FormData();
                        formData.append('file_id', file.serverId);
                        formData.append('metadata', JSON.stringify(metadata));
                        
                        const response = await fetch('/redaxo/index.php?rex-api-call=filepond_auto_metainfo&action=save_metadata', {
                            method: 'POST',
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            body: formData
                        });
                        
                        const result = await response.json();
                        if (result.success) {
                            modal.close();
                            resolve(metadata);
                        } else {
                            throw new Error(result.error || 'Fehler beim Speichern');
                        }
                    } else {
                        // Datei noch nicht hochgeladen - speichere Metadaten für späteren Upload
                        file.metaInfo = metadata;
                        modal.close();
                        resolve(metadata);
                    }
                } catch (error) {
                    console.error('Fehler beim Speichern der erweiterten Metadaten:', error);
                    alert('Fehler beim Speichern: ' + error.message);
                }
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
                        //console.log('Delayed Upload Mode enabled:', isDelayed);
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
                                //console.log('Metadata dialog cancelled');
                                
                                // Statt direktem Abbruch: Zeige einen Status mit Retry-Button an
                                file.abortProcessing = true;
                                
                                // Speichere Original-Funktionen für späteren Aufruf
                                const originalLoad = load;
                                const originalProgress = progress;
                                const originalError = error;
                                const originalAbort = abort;
                                
                                // Abbrechen, aber mit Retry-Option durch speziellen Status
                                error('cancelled', {
                                    message: t.cancelBtn,
                                    buttonLabel: t.retry,
                                    buttonAction: () => {
                                        // Starte den Upload für diese Datei erneut
                                        // Der spezielle Status 'cancelled' mit Button wird von FilePond automatisch erkannt
                                        const retryFile = pond.getFiles().find(f => f.id === file.id);
                                        if (retryFile) {
                                            file.abortProcessing = false;
                                            pond.processFile(retryFile.id).then(
                                                successFile => {
                                                    //console.log('Retry successful:', successFile.filename || successFile.file.name);
                                                },
                                                failureReason => {
                                                    console.error('Retry failed:', failureReason);
                                                }
                                            );
                                        }
                                    }
                                });
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
                acceptedFileTypes: normalizeFileTypes(input.dataset.filepondTypes || 'image/*'),
                maxFileSize: (input.dataset.filepondMaxsize || '10') + 'MB',
                credits: false,
                
                // Clientseitige Bildverkleinerung
                // Standardmäßig deaktiviert, muss explizit mit data-filepond-client-resize="true" aktiviert werden
                allowImageResize: input.dataset.filepondClientResize === 'true',
                imageResizeTargetWidth: parseInt(input.dataset.filepondMaxPixel || '2100'),
                imageResizeTargetHeight: parseInt(input.dataset.filepondMaxPixel || '2100'),
                imageResizeMode: 'contain', // Bild wird in die Dimensionen eingepasst, behält Seitenverhältnis
                imageResizeUpscale: false, // Kleine Bilder nicht vergrößern
                
                // Clientseitige Bildtransformation
                // Standardmäßig deaktiviert, muss explizit mit data-filepond-client-resize="true" aktiviert werden
                allowImageTransform: input.dataset.filepondClientResize === 'true',
                imageTransformOutputQuality: parseInt(input.dataset.filepondImageQuality || '90'),
                imageTransformOutputQualityMode: 'optional', // Nur komprimieren wenn auch resize nötig
                imageTransformOutputStripImageHead: false, // EXIF-Daten behalten (Orientation wird separat gehandhabt)
                
                // EXIF-Orientierung
                allowImageExifOrientation: true
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
                
                // Default ist 1 (Upload-Button) wenn delayed upload aktiviert ist, sonst 0
                const delayedUploadType = input.hasAttribute('data-filepond-delayed-type') ? 
                                         input.getAttribute('data-filepond-delayed-type') : 
                                         (isDelayedUpload ? '1' : '0');
                
                if (isDelayedUpload) {

                    if (1 == delayedUploadType) {
                    // Eigener Upload Button

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

                    } else if (2 == delayedUploadType) {
                    // Upload via Formular Submit

                    const formEl = pondRoot.closest('form');

                    // Event-Listener für Submit
                    formEl.addEventListener('submit', function(e) {
                        e.preventDefault();

                        console.log('Upload triggered for input:', input.id);
                        if (pond && typeof pond.processFiles === 'function') {

                        pond.on('processfiles', () => {
                            console.log('All files uploaded');
                            formEl.submit();
                        });

                        pond.processFiles();
                        }  
                    });

                    }
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
