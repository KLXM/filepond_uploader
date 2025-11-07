<?php
// Ausgewählte Kategorie hat Vorrang vor der Einstellung aus der Config
$selectedCategory = rex_request('category_id', 'int', 0);

// Opener Input Field für Media Widget Integration
$openerInputField = rex_request('opener_input_field', 'string', '');
$isMediaWidget = !empty($openerInputField);

$selMedia = new rex_media_category_select($checkPerm = true);
$selMedia->setId('rex-mediapool-category');
$selMedia->setName('category_id');
$selMedia->setSize(1);
$selMedia->setSelected($selectedCategory);
$selMedia->setAttribute('class', 'selectpicker');
$selMedia->setAttribute('data-live-search', 'true');
if (rex::getUser()->getComplexPerm('media')->hasAll()) {
    $selMedia->addOption(rex_i18n::msg('filepond_upload_no_category'), '0');
}

$currentUser = rex::getUser();
$langCode = $currentUser ? $currentUser->getLanguage() : rex_config::get('filepond_uploader', 'lang', 'en_gb');

// Prüfen, ob Metadaten übersprungen werden sollen (neue Einstellung)
$skipMeta = rex_config::get('filepond_uploader', 'upload_skip_meta', false);

// Prüfen, ob verzögerter Upload-Modus aktiviert ist
$delayedUpload = rex_config::get('filepond_uploader', 'delayed_upload_mode', false);

// Prüfen, ob das title-Feld required sein soll
$titleRequired = rex_config::get('filepond_uploader', 'title_required_default', false);

// Session-Wert setzen für die API
if ($skipMeta) {
    rex_set_session('filepond_no_meta', true);
} else {
    rex_set_session('filepond_no_meta', false);
}

$content = '
<div class="rex-form">
    <form action="' . rex_url::currentBackendPage() . '" method="post" class="form-horizontal">
        <div class="panel panel-default">
            <div class="panel-heading">
                <div class="panel-title">' . rex_i18n::msg('filepond_upload_title') . '</div>
            </div>
            
            <div class="panel-body">
                <div class="form-group">
                    <label class="col-sm-2 control-label">' . rex_i18n::msg('filepond_upload_category') . '</label>
                    <div class="col-sm-10">
                        '.$selMedia->get().'
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="col-sm-2 control-label">' . rex_i18n::msg('filepond_upload_files') . '</label>
                    <div class="col-sm-10">
                        <input type="hidden" 
                            id="filepond-upload"
                            data-widget="filepond"
                            data-filepond-cat="'.$selectedCategory.'"
                            data-filepond-maxfiles="'.rex_config::get('filepond_uploader', 'max_files', 30).'"
                            data-filepond-types="'.rex_config::get('filepond_uploader', 'allowed_types', 'image/*,video/*,.pdf,.doc,.docx,.txt').'"
                            data-filepond-maxsize="'.rex_config::get('filepond_uploader', 'max_filesize', 10).'"
                            data-filepond-lang="'.$langCode.'"
                            data-filepond-skip-meta="'.($skipMeta ? 'true' : 'false').'"
                            data-filepond-delayed-upload="'.($delayedUpload ? 'true' : 'false').'"
                            data-filepond-title-required="'.($titleRequired ? 'true' : 'false').'"
                            data-filepond-opener-field="'.rex_escape($openerInputField).'"
                            value=""
                        >
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
';?>
<script>
$(document).on("rex:ready", function() {
    
    // Media Widget Integration
    const openerInputField = "<?php echo rex_escape($openerInputField); ?>";
    const isMediaWidget = openerInputField.length > 0;
    
    if (isMediaWidget) {
        console.log("Media Widget Mode detected for:", openerInputField);
        
        // Container für Upload-Ergebnisse erstellen
        const createResultsContainer = () => {
            let container = document.getElementById('filepond-media-results');
            if (!container) {
                container = document.createElement('div');
                container.id = 'filepond-media-results';
                container.className = 'panel panel-info';
                container.style.marginTop = '20px';
                container.innerHTML = `
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <i class="fa fa-check-circle text-success"></i> 
                            ${openerInputField.startsWith('REX_MEDIALIST_') ? 
                                '<?php echo rex_i18n::msg('filepond_uploaded_files_medialist'); ?>' : 
                                '<?php echo rex_i18n::msg('filepond_uploaded_files_media'); ?>'}
                        </h4>
                    </div>
                    <div class="panel-body">
                        <ul id="filepond-uploaded-files" class="list-unstyled"></ul>
                    </div>
                `;
                
                // Nach dem Upload-Widget einfügen
                const uploadWidget = document.querySelector('.panel-default');
                if (uploadWidget && uploadWidget.parentNode) {
                    uploadWidget.parentNode.insertBefore(container, uploadWidget.nextSibling);
                }
            }
            return container;
        };
        
        // Erfolgreichen Upload behandeln
        const handleSuccessfulUpload = (filename) => {
            console.log("File uploaded successfully:", filename);
            
            const container = createResultsContainer();
            const filesList = document.getElementById('filepond-uploaded-files');
            
            if (filesList) {
                const listItem = document.createElement('li');
                listItem.className = 'media-upload-result';
                listItem.style.cssText = 'padding: 10px; border: 1px solid #d4edda; background-color: #f8fff9; margin-bottom: 10px; border-radius: 4px;';
                
                const isMediaList = openerInputField.startsWith('REX_MEDIALIST_');
                const buttonText = isMediaList ? 
                    '<?php echo rex_i18n::msg('filepond_select_for_medialist'); ?>' :
                    '<?php echo rex_i18n::msg('filepond_select_for_media'); ?>';
                
                // Build DOM tree safely to avoid XSS
                const rowDiv = document.createElement('div');
                rowDiv.className = 'row';
                
                const colLeft = document.createElement('div');
                colLeft.className = 'col-sm-6';
                
                const strongEl = document.createElement('strong');
                const iconEl = document.createElement('i');
                iconEl.className = 'fa fa-file';
                strongEl.appendChild(iconEl);
                strongEl.appendChild(document.createTextNode(' ' + filename));
                colLeft.appendChild(strongEl);
                colLeft.appendChild(document.createElement('br'));
                const smallEl = document.createElement('small');
                smallEl.className = 'text-muted';
                smallEl.textContent = 'Erfolgreich hochgeladen';
                colLeft.appendChild(smallEl);
                
                const colRight = document.createElement('div');
                colRight.className = 'col-sm-6 text-right';
                
                const buttonEl = document.createElement('button');
                buttonEl.type = 'button';
                buttonEl.className = 'btn btn-success btn-sm filepond-select-media';
                buttonEl.setAttribute('data-filename', filename);
                buttonEl.setAttribute('data-is-medialist', isMediaList);
                const buttonIcon = document.createElement('i');
                buttonIcon.className = 'fa fa-check';
                buttonEl.appendChild(buttonIcon);
                buttonEl.appendChild(document.createTextNode(' ' + buttonText));
                colRight.appendChild(buttonEl);
                
                rowDiv.appendChild(colLeft);
                rowDiv.appendChild(colRight);
                
                listItem.appendChild(rowDiv);
                
                filesList.appendChild(listItem);
                
                // Button-Handler hinzufügen
                const selectButton = listItem.querySelector('.filepond-select-media');
                selectButton.addEventListener('click', function() {
                    const filename = this.getAttribute('data-filename');
                    const isMediaList = this.getAttribute('data-is-medialist') === 'true';
                    
                    if (isMediaList) {
                        selectMedialist(filename);
                    } else {
                        selectMedia(filename, '');
                    }
                });
            }
        };
        
        // Media Widget Funktionen
        const selectMedia = (filename, alt) => {
            if (!window.opener) {
                alert('Fehler: Übergeordnetes Fenster nicht gefunden');
                return;
            }
            
            try {
                const input = window.opener.document.getElementById(openerInputField);
                if (input) {
                    input.value = filename;
                    
                    // Change-Event auslösen für jQuery/Framework-Kompatibilität
                    if (window.opener.jQuery) {
                        window.opener.jQuery(input).trigger('change');
                    } else {
                        const event = new Event('change', { bubbles: true });
                        input.dispatchEvent(event);
                    }
                    
                    // Fenster schließen
                    window.close();
                } else {
                    alert('Fehler: Media-Eingabefeld nicht gefunden: ' + openerInputField);
                }
            } catch (error) {
                console.error('Error in selectMedia:', error);
                alert('Fehler beim Übernehmen der Datei');
            }
        };
        
        const selectMedialist = (filename) => {
            if (!window.opener) {
                alert('Fehler: Übergeordnetes Fenster nicht gefunden');
                return;
            }
            
            try {
                const openerId = openerInputField.slice('REX_MEDIALIST_'.length);
                const medialist = 'REX_MEDIALIST_SELECT_' + openerId;
                
                const source = window.opener.document.getElementById(medialist);
                if (source) {
                    const option = window.opener.document.createElement('OPTION');
                    option.text = filename;
                    option.value = filename;
                    
                    source.options.add(option, source.options.length);
                    
                    // writeREXMedialist aufrufen, falls verfügbar
                    if (window.opener.writeREXMedialist) {
                        window.opener.writeREXMedialist(openerId);
                    }
                    
                    // Fenster schließen
                    window.close();
                } else {
                    alert('Fehler: Medialist-Select nicht gefunden: ' + medialist);
                }
            } catch (error) {
                console.error('Error in selectMedialist:', error);
                alert('Fehler beim Übernehmen der Datei in die Medienliste');
            }
        };
        
        // FilePond Upload-Events abfangen (experimentell)
        // Da das System event-basiert funktioniert, versuchen wir verschiedene Ansätze
        document.addEventListener('filepond:fileprocessed', function(e) {
            if (e.detail && e.detail.filename) {
                handleSuccessfulUpload(e.detail.filename);
            }
        });
        
        // Alternative: MutationObserver für DOM-Änderungen
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            // Suche nach erfolgreichen Upload-Indikatoren
                            const successElements = node.querySelectorAll ? 
                                node.querySelectorAll('.filepond--file-status-main[data-filepond-file-status="Uploaded"]') : [];
                            
                            successElements.forEach(function(element) {
                                // Versuche Dateiname zu extrahieren
                                const fileElement = element.closest('.filepond--file');
                                if (fileElement) {
                                    const nameElement = fileElement.querySelector('.filepond--file-info-main');
                                    if (nameElement && nameElement.textContent) {
                                        const filename = nameElement.textContent.trim();
                                        if (filename && !document.querySelector(`[data-filename="${filename}"]`)) {
                                            setTimeout(() => handleSuccessfulUpload(filename), 100);
                                        }
                                    }
                                }
                            });
                        }
                    });
                }
            });
        });
        
        // Observer starten
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    console.log("FilePond Uploader page initialized");
    
    $("#rex-mediapool-category").on("change", function() {
        const newCategory = $(this).val();
        const $input = $("#filepond-upload");
        $input.attr("data-filepond-cat", newCategory);
        
        const pondElement = document.querySelector("#filepond-upload");
        if (pondElement && pondElement.FilePond) {
            pondElement.FilePond.removeFiles();
            // FilePond neu initialisieren
            document.dispatchEvent(new Event('filepond:init'));
        }
    });
    
    // Upload-Button für verzögerten Modus
    $("#filepond-upload-btn").on("click", function() {
        console.log("Upload button clicked");
        
        // Verwende die neue globale Referenz
        const uploadElement = document.getElementById("filepond-upload");
        
        if (window.FilePondGlobal && window.FilePondGlobal.instances && window.FilePondGlobal.instances["filepond-upload"]) {
            const pond = window.FilePondGlobal.instances["filepond-upload"];
            console.log("FilePond instance found via global reference, processing files...", pond.getFiles().length);
            
            // Alle Dateien verarbeiten
            pond.processFiles();
        } 
        else if (uploadElement && uploadElement.pondInstance) {
            console.log("FilePond instance found via direct reference, processing files...", uploadElement.pondInstance.getFiles().length);
            uploadElement.pondInstance.processFiles();
        }
        else {
            console.error("FilePond instance not found! The element might not be initialized correctly.");
        }
    });
});
</script>

<?php 
// Media Widget Integration JavaScript mit Nonce
if ($isMediaWidget): ?>
<script nonce="<?= rex_response::getNonce() ?>">
// FilePond Media Widget Integration
(function() {
    'use strict';
    
    const MediaWidget = {
        openerInputField: '<?= rex_escape($openerInputField, 'js') ?>',
        isMediaList: <?= $openerInputField && str_starts_with($openerInputField, 'REX_MEDIALIST_') ? 'true' : 'false' ?>,
        resultsContainer: null,
        
        init() {
            console.log('=== FilePond Media Widget Integration ===');
            console.log('opener_input_field:', this.openerInputField);
            console.log('isMediaList:', this.isMediaList);
            
            document.addEventListener('DOMContentLoaded', () => {
                this.createInfoBanner();
                this.startUploadMonitoring();
            });
        },
        
        createInfoBanner() {
            const banner = document.createElement('div');
            banner.className = 'alert alert-info';
            banner.innerHTML = `
                <h4><i class="fa fa-info-circle"></i> <?= rex_i18n::msg('filepond_uploader_media_widget_mode') ?></h4>
                <p><?= rex_i18n::msg('filepond_uploader_media_widget_info') ?></p>
            `;
            
            const mainContent = document.querySelector('.rex-page-section');
            if (mainContent) {
                mainContent.insertBefore(banner, mainContent.firstChild);
            }
        },
        
        createResultsContainer() {
            if (this.resultsContainer) return this.resultsContainer;
            
            this.resultsContainer = document.createElement('div');
            this.resultsContainer.className = 'panel panel-success';
            this.resultsContainer.style.marginTop = '20px';
            this.resultsContainer.innerHTML = `
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="fa fa-check-circle"></i> Hochgeladene Dateien
                    </h4>
                </div>
                <div class="panel-body">
                    <ul id="filepond-uploaded-files" class="list-unstyled"></ul>
                    ${this.isMediaList ? `
                        <div id="filepond-bulk-actions" style="display: none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #d4edda;">
                            <button type="button" class="btn btn-primary btn-sm" id="filepond-select-all">
                                <i class="fa fa-download"></i> Alle Dateien in Medienliste übernehmen
                            </button>
                            <small class="text-muted" style="margin-left: 10px;">
                                Übernimmt alle hochgeladenen Dateien auf einmal
                            </small>
                        </div>
                    ` : ''}
                </div>
            `;
            
            const uploadPanel = document.querySelector('.panel-edit');
            if (uploadPanel && uploadPanel.parentNode) {
                uploadPanel.parentNode.appendChild(this.resultsContainer);
            }
            
            // "Alle übernehmen" Button-Handler für Medialists
            if (this.isMediaList) {
                const selectAllButton = this.resultsContainer.querySelector('#filepond-select-all');
                if (selectAllButton) {
                    selectAllButton.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.selectAllMedia();
                    });
                }
            }
            
            return this.resultsContainer;
        },
        
        isImageFile(filename) {
            const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff'];
            const extension = filename.split('.').pop().toLowerCase();
            return imageExtensions.includes(extension);
        },
        
        getMediaUrl(filename) {
            const baseUrl = window.location.origin;
            const redaxoPath = window.location.pathname.split('/redaxo/')[0];
            return `${baseUrl}${redaxoPath}/media/${filename}`;
        },
        
        createImagePreview(filename) {
            const mediaUrl = this.getMediaUrl(filename);
            return `
                <div class="filepond-preview-container">
                    <img src="${mediaUrl}" 
                         alt="${filename}" 
                         class="filepond-preview-image"
                         style="
                             max-width: 80px; 
                             max-height: 80px; 
                             border-radius: 4px; 
                             border: 1px solid #ddd;
                             object-fit: cover;
                             box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                         "
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                    />
                    <div style="display: none; width: 80px; height: 80px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; align-items: center; justify-content: center;">
                        <i class="fa fa-image text-muted" style="font-size: 24px;"></i>
                    </div>
                </div>
            `;
        },
        
        createFileIcon(filename) {
            const extension = filename.split('.').pop().toLowerCase();
            let iconClass = 'fa-file';
            let iconColor = '#6c757d';
            
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
                <div class="filepond-file-icon" style="
                    width: 80px; 
                    height: 80px; 
                    background: #f8f9fa; 
                    border: 1px solid #ddd; 
                    border-radius: 4px; 
                    display: flex; 
                    flex-direction: column;
                    align-items: center; 
                    justify-content: center;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                ">
                    <i class="fa ${iconClass}" style="font-size: 32px; color: ${iconColor}; margin-bottom: 4px;"></i>
                    <small style="font-size: 10px; color: #6c757d; text-transform: uppercase; font-weight: bold;">${extension}</small>
                </div>
            `;
        },
        
        handleUploadSuccess(filename) {
            console.log('=== Upload Success ===');
            console.log('Filename:', filename);
            
            const container = this.createResultsContainer();
            const filesList = container.querySelector('#filepond-uploaded-files');
            
            if (filesList) {
                const listItem = document.createElement('li');
                listItem.style.cssText = 'padding: 15px; border: 1px solid #d4edda; background-color: #f8fff9; margin-bottom: 10px; border-radius: 4px;';
                listItem.dataset.filename = filename; // Für "Alle übernehmen" Funktion
                
                const buttonText = this.isMediaList ? 'In Medienliste übernehmen' : 'Übernehmen';
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
                
                // "Alle übernehmen" Button anzeigen wenn mehr als eine Datei und Medialist
                if (this.isMediaList) {
                    const bulkActions = container.querySelector('#filepond-bulk-actions');
                    const allFiles = filesList.querySelectorAll('li[data-filename]');
                    if (bulkActions && allFiles.length > 1) {
                        bulkActions.style.display = 'block';
                    }
                }
            }
        },
        
        selectMedia(filename, alt = '') {
            console.log('=== selectMedia ===');
            console.log('Filename:', filename);
            
            if (!window.opener) {
                console.error('Opener-Fenster nicht gefunden!');
                return;
            }
            
            try {
                const input = window.opener.document.getElementById(this.openerInputField);
                if (input) {
                    input.value = filename;
                    
                    if (window.opener.jQuery) {
                        window.opener.jQuery(input).trigger('change');
                    } else {
                        const event = new Event('change', { bubbles: true });
                        input.dispatchEvent(event);
                    }
                    
                    console.log('Datei erfolgreich übernommen:', filename);
                    window.close(); // Nur bei Einzelmedien schließen
                } else {
                    console.error('Input-Feld nicht gefunden:', this.openerInputField);
                }
            } catch (error) {
                console.error('Error in selectMedia:', error);
            }
        },
        
        selectMedialist(filename) {
            console.log('=== selectMedialist ===');
            
            if (!window.opener) {
                console.error('Opener-Fenster nicht gefunden!');
                return;
            }
            
            try {
                const openerId = this.openerInputField.slice('REX_MEDIALIST_'.length);
                const medialist = 'REX_MEDIALIST_SELECT_' + openerId;
                
                const source = window.opener.document.getElementById(medialist);
                if (source) {
                    // Prüfen ob Datei bereits in der Liste ist
                    const existingOption = Array.from(source.options).find(option => option.value === filename);
                    if (!existingOption) {
                        const option = window.opener.document.createElement('OPTION');
                        option.text = filename;
                        option.value = filename;
                        
                        source.options.add(option, source.options.length);
                        
                        if (window.opener.writeREXMedialist) {
                            window.opener.writeREXMedialist(openerId);
                        }
                        
                        console.log('Datei erfolgreich zur Medienliste hinzugefügt:', filename);
                        
                        // Button als "hinzugefügt" markieren
                        const button = document.querySelector(`button[data-filename="${filename}"]`);
                        if (button) {
                            button.innerHTML = '<i class="fa fa-check"></i> Hinzugefügt';
                            button.className = 'btn btn-default btn-sm';
                            button.disabled = true;
                        }
                    } else {
                        console.log('Datei bereits in Medienliste vorhanden:', filename);
                    }
                    
                    // Fenster NICHT schließen bei Medialists
                } else {
                    console.error('Medienliste nicht gefunden:', medialist);
                }
            } catch (error) {
                console.error('Error in selectMedialist:', error);
            }
        },
        
        selectAllMedia() {
            console.log('=== selectAllMedia ===');
            
            if (!window.opener) {
                console.error('Opener-Fenster nicht gefunden!');
                return;
            }
            
            const filesList = document.querySelector('#filepond-uploaded-files');
            if (!filesList) {
                console.error('Keine Dateien zum Übernehmen gefunden!');
                return;
            }
            
            const allFiles = filesList.querySelectorAll('li[data-filename]');
            if (allFiles.length === 0) {
                console.error('Keine Dateien zum Übernehmen gefunden!');
                return;
            }
            
            try {
                const openerId = this.openerInputField.slice('REX_MEDIALIST_'.length);
                const medialist = 'REX_MEDIALIST_SELECT_' + openerId;
                
                const source = window.opener.document.getElementById(medialist);
                if (!source) {
                    console.error('Medienliste nicht gefunden:', medialist);
                    return;
                }
                
                let addedCount = 0;
                const addedFiles = [];
                
                allFiles.forEach(fileItem => {
                    const filename = fileItem.dataset.filename;
                    if (filename) {
                        // Prüfen ob Datei bereits in der Liste ist
                        const existingOption = Array.from(source.options).find(option => option.value === filename);
                        if (!existingOption) {
                            const option = window.opener.document.createElement('OPTION');
                            option.text = filename;
                            option.value = filename;
                            source.options.add(option, source.options.length);
                            addedCount++;
                            addedFiles.push(filename);
                            
                            // Button als "hinzugefügt" markieren
                            const button = fileItem.querySelector(`button[data-filename="${filename}"]`);
                            if (button) {
                                button.innerHTML = '<i class="fa fa-check"></i> Hinzugefügt';
                                button.className = 'btn btn-default btn-sm';
                                button.disabled = true;
                            }
                        }
                    }
                });
                
                if (addedCount > 0) {
                    if (window.opener.writeREXMedialist) {
                        window.opener.writeREXMedialist(openerId);
                    }
                    
                    console.log(`${addedCount} Datei(en) erfolgreich zur Medienliste hinzugefügt:`, addedFiles);
                    
                    // "Alle übernehmen" Button deaktivieren
                    const selectAllButton = document.querySelector('#filepond-select-all');
                    if (selectAllButton) {
                        selectAllButton.innerHTML = '<i class="fa fa-check"></i> Alle hinzugefügt';
                        selectAllButton.className = 'btn btn-default btn-sm';
                        selectAllButton.disabled = true;
                    }
                    
                    // Fenster NICHT schließen
                } else {
                    console.log('Alle Dateien sind bereits in der Medienliste vorhanden.');
                }
                
            } catch (error) {
                console.error('Error in selectAllMedia:', error);
            }
        },
        
        startUploadMonitoring() {
            console.log('=== Starting Upload Monitoring ===');
            
            const fileInputs = document.querySelectorAll('input[data-widget="filepond"]');
            console.log('Found FilePond inputs:', fileInputs.length);
            
            fileInputs.forEach((input, index) => {
                let lastValue = input.value;
                
                const checkValue = () => {
                    if (input.value !== lastValue) {
                        console.log(`Input ${index} value changed:`, lastValue, '->', input.value);
                        
                        if (input.value) {
                            const newFiles = input.value.split(',').filter(Boolean);
                            const oldFiles = lastValue ? lastValue.split(',').filter(Boolean) : [];
                            
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
                
                setInterval(checkValue, 1000);
                input.addEventListener('change', checkValue);
                input.addEventListener('input', checkValue);
            });
            
            console.log('=== Media Widget Integration Ready ===');
        }
    };
    
    // Initialisierung
    MediaWidget.init();
})();
</script>
<?php endif; ?>

<?php 
// Fragment ausgeben
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', rex_i18n::msg('filepond_upload_title'));
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
