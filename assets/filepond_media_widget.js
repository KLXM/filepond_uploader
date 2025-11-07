/**
 * FilePond Media Widget Integration
 * Handles media selection for REX_MEDIA and REX_MEDIALIST widgets
 */

class FilePondMediaWidget {
    constructor() {
        this.openerInputField = null;
        this.isMediaList = false;
        this.resultsContainer = null;
        
        this.init();
    }
    
    init() {
        // URL-Parameter auslesen
        const urlParams = new URLSearchParams(window.location.search);
        this.openerInputField = urlParams.get('opener_input_field');
        
        if (!this.openerInputField) {
            console.log('Kein opener_input_field Parameter - normale Upload-Seite');
            return;
        }
        
        console.log('=== FilePond Media Widget Integration ===');
        console.log('opener_input_field:', this.openerInputField);
        console.log('URL:', window.location.href);
        
        this.isMediaList = this.openerInputField.startsWith('REX_MEDIALIST_');
        
        document.addEventListener('DOMContentLoaded', () => {
            this.createInfoBanner();
            this.startUploadMonitoring();
        });
    }
    
    createInfoBanner() {
        const banner = document.createElement('div');
        banner.className = 'alert alert-info';
        banner.innerHTML = `
            <h4><i class="fa fa-info-circle"></i> Media Widget Modus</h4>
            <p>Nach dem Upload können Sie die Dateien direkt in Ihr Formularfeld übernehmen.</p>
            <p><small>Field: ${this.openerInputField}</small></p>
        `;
        
        const mainContent = document.querySelector('.rex-page-section');
        if (mainContent) {
            mainContent.insertBefore(banner, mainContent.firstChild);
        }
    }
    
    createResultsContainer() {
        if (this.resultsContainer) return this.resultsContainer;
        
        this.resultsContainer = document.createElement('div');
        this.resultsContainer.className = 'panel panel-success fp-results-container';
        this.resultsContainer.innerHTML = `
            <div class="panel-heading">
                <h4 class="panel-title">
                    <i class="fa fa-check-circle"></i> Hochgeladene Dateien
                </h4>
            </div>
            <div class="panel-body">
                <ul id="filepond-uploaded-files" class="list-unstyled"></ul>
            </div>
        `;
        
        const uploadPanel = document.querySelector('.panel-edit');
        if (uploadPanel && uploadPanel.parentNode) {
            uploadPanel.parentNode.appendChild(this.resultsContainer);
        }
        
        return this.resultsContainer;
    }
    
    handleUploadSuccess(filename) {
        console.log('=== Upload Success ===');
        console.log('Filename:', filename);
        
        const container = this.createResultsContainer();
        const filesList = container.querySelector('#filepond-uploaded-files');
        
        if (filesList) {
            const listItem = document.createElement('li');
            listItem.className = 'fp-media-upload-result-extended';
            
            const buttonText = this.isMediaList ? 'In Medienliste übernehmen' : 'Übernehmen';
            
            // Prüfe ob es ein Bild ist
            const isImage = this.isImageFile(filename);
            const previewHtml = isImage ? this.createImagePreview(filename) : this.createFileIcon(filename);
            
            // Build DOM tree safely to avoid XSS
            const rowDiv = document.createElement('div');
            rowDiv.className = 'row';
            
            const colPreview = document.createElement('div');
            colPreview.className = 'col-sm-2';
            colPreview.innerHTML = previewHtml; // previewHtml is created by safe methods
            
            const colInfo = document.createElement('div');
            colInfo.className = 'col-sm-6';
            
            const strongEl = document.createElement('strong');
            const iconEl = document.createElement('i');
            iconEl.className = 'fa fa-file';
            strongEl.appendChild(iconEl);
            strongEl.appendChild(document.createTextNode(' ' + filename));
            colInfo.appendChild(strongEl);
            colInfo.appendChild(document.createElement('br'));
            
            const smallEl = document.createElement('small');
            smallEl.className = 'text-muted';
            smallEl.textContent = 'Erfolgreich hochgeladen';
            colInfo.appendChild(smallEl);
            
            if (isImage) {
                colInfo.appendChild(document.createElement('br'));
                const imageInfo = document.createElement('small');
                imageInfo.className = 'text-info';
                const imageIcon = document.createElement('i');
                imageIcon.className = 'fa fa-image';
                imageInfo.appendChild(imageIcon);
                imageInfo.appendChild(document.createTextNode(' Bilddatei'));
                colInfo.appendChild(imageInfo);
            }
            
            const colButton = document.createElement('div');
            colButton.className = 'col-sm-4 text-right';
            
            const buttonEl = document.createElement('button');
            buttonEl.type = 'button';
            buttonEl.className = 'btn btn-success btn-sm filepond-select-media';
            buttonEl.setAttribute('data-filename', filename);
            const buttonIcon = document.createElement('i');
            buttonIcon.className = 'fa fa-check';
            buttonEl.appendChild(buttonIcon);
            buttonEl.appendChild(document.createTextNode(' ' + buttonText));
            colButton.appendChild(buttonEl);
            
            rowDiv.appendChild(colPreview);
            rowDiv.appendChild(colInfo);
            rowDiv.appendChild(colButton);
            
            listItem.appendChild(rowDiv);
            
            filesList.appendChild(listItem);
            
            // Button-Handler hinzufügen
            const selectButton = listItem.querySelector('.filepond-select-media');
            selectButton.addEventListener('click', (e) => {
                e.preventDefault();
                const filename = e.target.dataset.filename;
                
                console.log('=== Media Selection ===');
                console.log('Filename:', filename);
                console.log('Field:', this.openerInputField);
                console.log('Is MediaList:', this.isMediaList);
                
                if (this.isMediaList) {
                    this.selectMedialist(filename);
                } else {
                    this.selectMedia(filename, '');
                }
            });
        }
    }
    
    isImageFile(filename) {
        const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff'];
        const extension = filename.split('.').pop().toLowerCase();
        return imageExtensions.includes(extension);
    }
    
    createImagePreview(filename) {
        const mediaUrl = this.getMediaUrl(filename);
        
        // Build DOM tree safely to avoid XSS
        const container = document.createElement('div');
        container.className = 'fp-preview-container';
        
        const img = document.createElement('img');
        img.src = mediaUrl;
        img.alt = filename; // Safe: browser automatically escapes alt attribute
        img.className = 'fp-preview-image';
        
        const fallbackDiv = document.createElement('div');
        fallbackDiv.className = 'fp-image-preview-fallback';
        const fallbackIcon = document.createElement('i');
        fallbackIcon.className = 'fa fa-image text-muted';
        fallbackDiv.appendChild(fallbackIcon);
        
        img.onerror = function() {
            this.style.display = 'none';
            fallbackDiv.style.display = 'flex';
        };
        
        container.appendChild(img);
        container.appendChild(fallbackDiv);
        
        return container.outerHTML;
    }
    
    createFileIcon(filename) {
        const extension = filename.split('.').pop().toLowerCase();
        let iconClass = 'fa-file';
        let iconColor = '#6c757d';
        
        // Icon basierend auf Dateierweiterung
        switch(extension) {
            case 'pdf':
                iconClass = 'fa-file-pdf-o';
                iconColor = '#dc3545';
                break;
            case 'doc':
            case 'docx':
                iconClass = 'fa-file-word-o';
                iconColor = '#007bff';
                break;
            case 'xls':
            case 'xlsx':
                iconClass = 'fa-file-excel-o';
                iconColor = '#28a745';
                break;
            case 'ppt':
            case 'pptx':
                iconClass = 'fa-file-powerpoint-o';
                iconColor = '#fd7e14';
                break;
            case 'zip':
            case 'rar':
            case '7z':
                iconClass = 'fa-file-archive-o';
                iconColor = '#6f42c1';
                break;
            case 'mp4':
            case 'avi':
            case 'mov':
            case 'wmv':
                iconClass = 'fa-file-video-o';
                iconColor = '#e83e8c';
                break;
            case 'mp3':
            case 'wav':
            case 'flac':
                iconClass = 'fa-file-audio-o';
                iconColor = '#17a2b8';
                break;
            case 'txt':
                iconClass = 'fa-file-text-o';
                iconColor = '#6c757d';
                break;
        }
        
        return `
            <div class="fp-file-icon">
                <i class="fa ${iconClass}" style="color: ${iconColor};"></i>
                <small>${extension}</small>
            </div>
        `;
    }
    
    getMediaUrl(filename) {
        // REDAXO Media URL generieren
        const baseUrl = window.location.origin;
        const redaxoPath = window.location.pathname.split('/redaxo/')[0];
        return `${baseUrl}${redaxoPath}/media/${filename}`;
    }
    
    selectMedia(filename, alt = '') {
        console.log('=== selectMedia ===');
        console.log('Filename:', filename);
        console.log('Alt:', alt);
        console.log('Opener available:', !!window.opener);
        
        if (!window.opener) {
            alert('Opener-Fenster nicht gefunden! Bitte stellen Sie sicher, dass die Upload-Seite in einem Popup geöffnet wurde.');
            return;
        }
        
        try {
            console.log('Looking for input field:', this.openerInputField);
            
            const input = window.opener.document.getElementById(this.openerInputField);
            console.log('Input found:', !!input);
            
            if (input) {
                input.value = filename;
                console.log('Value set to:', filename);
                
                // Change-Event auslösen für jQuery/Framework-Kompatibilität
                if (window.opener.jQuery) {
                    console.log('Triggering jQuery change event');
                    window.opener.jQuery(input).trigger('change');
                } else {
                    console.log('Triggering native change event');
                    const event = new Event('change', { bubbles: true });
                    input.dispatchEvent(event);
                }
                
                alert('Datei erfolgreich übernommen: ' + filename);
                window.close();
            } else {
                alert('Input-Feld nicht gefunden: ' + this.openerInputField);
                console.error('Available inputs:', Array.from(window.opener.document.querySelectorAll('input')).map(i => i.id));
            }
        } catch (error) {
            console.error('Error in selectMedia:', error);
            alert('Fehler beim Übernehmen der Datei: ' + error.message);
        }
    }
    
    selectMedialist(filename) {
        console.log('=== selectMedialist ===');
        console.log('Filename:', filename);
        console.log('Opener available:', !!window.opener);
        
        if (!window.opener) {
            alert('Opener-Fenster nicht gefunden! Bitte stellen Sie sicher, dass die Upload-Seite in einem Popup geöffnet wurde.');
            return;
        }
        
        try {
            const openerId = this.openerInputField.slice('REX_MEDIALIST_'.length);
            const medialist = 'REX_MEDIALIST_SELECT_' + openerId;
            
            console.log('Looking for medialist:', medialist);
            
            const source = window.opener.document.getElementById(medialist);
            console.log('Medialist found:', !!source);
            
            if (source) {
                const option = window.opener.document.createElement('OPTION');
                option.text = filename;
                option.value = filename;
                
                source.options.add(option, source.options.length);
                console.log('Option added to medialist');
                
                // writeREXMedialist aufrufen, falls verfügbar
                if (window.opener.writeREXMedialist) {
                    console.log('Calling writeREXMedialist');
                    window.opener.writeREXMedialist(openerId);
                } else {
                    console.warn('writeREXMedialist function not found');
                }
                
                alert('Datei erfolgreich zur Medienliste hinzugefügt: ' + filename);
                window.close();
            } else {
                alert('Medienliste nicht gefunden: ' + medialist);
                console.error('Available selects:', Array.from(window.opener.document.querySelectorAll('select')).map(s => s.id));
            }
        } catch (error) {
            console.error('Error in selectMedialist:', error);
            alert('Fehler beim Hinzufügen zur Medienliste: ' + error.message);
        }
    }
    
    startUploadMonitoring() {
        console.log('=== Starting Upload Monitoring ===');
        
        // Methode 1: FilePond Input-Wert überwachen
        const fileInputs = document.querySelectorAll('input[data-widget="filepond"]');
        console.log('Found FilePond inputs:', fileInputs.length);
        
        fileInputs.forEach((input, index) => {
            console.log(`Monitoring input ${index}:`, input.name, input.id);
            
            let lastValue = input.value;
            const checkValue = () => {
                if (input.value !== lastValue) {
                    console.log(`Input ${index} value changed:`, lastValue, '->', input.value);
                    
                    if (input.value) {
                        const newFiles = input.value.split(',').filter(Boolean);
                        const oldFiles = lastValue ? lastValue.split(',').filter(Boolean) : [];
                        
                        // Neue Dateien finden
                        const addedFiles = newFiles.filter(file => !oldFiles.includes(file));
                        console.log('New files detected:', addedFiles);
                        
                        addedFiles.forEach(filename => {
                            if (filename.trim()) {
                                this.handleUploadSuccess(filename.trim());
                            }
                        });
                    }
                    
                    lastValue = input.value;
                }
            };
            
            // Überwachung starten
            setInterval(checkValue, 1000);
            
            // Auch bei direkten Input-Events
            input.addEventListener('change', checkValue);
            input.addEventListener('input', checkValue);
        });
        
        // Methode 2: DOM-Mutation-Observer für FilePond-Status-Änderungen
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' && mutation.attributeName === 'data-filepond-item-state') {
                    const element = mutation.target;
                    if (element.dataset.filepondItemState === 'processed') {
                        console.log('FilePond file processed via DOM observer');
                        
                        // Versuche Dateiname zu extrahieren
                        const fileInfo = element.querySelector('.filepond--file-info-main');
                        if (fileInfo) {
                            const filename = fileInfo.textContent.trim();
                            if (filename) {
                                console.log('Extracted filename from DOM:', filename);
                                this.handleUploadSuccess(filename);
                            }
                        }
                    }
                }
            });
        });
        
        observer.observe(document.body, {
            attributes: true,
            subtree: true,
            attributeFilter: ['data-filepond-item-state']
        });
        
        console.log('=== Media Widget Integration Ready ===');
    }
}

// Auto-Initialisierung
new FilePondMediaWidget();