/**
 * FilePond MetaInfo Auto-Integration
 * Pragmatische Lösung: Vollautomatische Erkennung und Integration von MetaInfo-Feldern
 */
class FilePondAutoMetaInfo {
    constructor() {
        this.fieldsCache = null;
        this.currentModalId = null; // Eindeutige Modal-ID pro Instanz
        this.init();
    }

    /**
     * Generiert eine eindeutige Modal-ID
     */
    generateModalId() {
        return 'modal_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    init() {
        // Warte auf DOM-Ready und FilePond-Initialisierung
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupIntegration());
        } else {
            this.setupIntegration();
        }
    }
    
    setupIntegration() {
        // Hook in das bestehende FilePond Modal-System
        this.patchModalSystem();
    }
    
    /**
     * Erweitert das bestehende FilePond Modal-System
     */
    patchModalSystem() {
        // Warte bis FilePond initialisiert wird
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        // Prüfe auf FilePond-Elemente
                        const filepondElements = node.querySelectorAll ? 
                            node.querySelectorAll('.filepond--root') : [];
                        
                        if (filepondElements.length > 0 || node.classList?.contains('filepond--root')) {
                            // FilePond wurde initialisiert, patche das System
                            setTimeout(() => this.patchFilePondFunction(), 100);
                        }
                    }
                });
            });
        });
        
        observer.observe(document, { childList: true, subtree: true });
        
        // Falls FilePond bereits geladen ist
        if (document.querySelector('.filepond--root')) {
            setTimeout(() => this.patchFilePondFunction(), 100);
        }
    }
    
    /**
     * Erweitert die createMetadataDialog Funktion im FilePond-System
     */
    patchFilePondFunction() {
        // Alternativ: Monkey-patch über globale Funktion
        if (!window.originalCreateMetadataDialog) {
            // Speichere eine Referenz auf unsere erweiterte Funktion
            window.createEnhancedMetadataDialog = (file, existingMetadata = null) => {
                return this.showEnhancedModal(file, existingMetadata || {});
            };
        }
    }
    
    /**
     * Lädt alle verfügbaren MetaInfo-Felder
     */
    async loadMetaInfoFields() {
        if (this.fieldsCache) {
            return this.fieldsCache;
        }
        
        try {
            const response = await fetch('/redaxo/index.php?rex-api-call=filepond_auto_metainfo&action=get_fields', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            if (data.success) {
                this.fieldsCache = data.fields;
                return data.fields;
            } else {
                throw new Error(data.error || 'Fehler beim Laden der Felder');
            }
        } catch (error) {
            console.error('Fehler beim Laden der MetaInfo-Felder:', error);
            return [];
        }
    }
    
    /**
     * Zeigt das erweiterte Modal mit MetaInfo-Feldern
     */
    async showEnhancedModal(file, existingMetadata = {}) {
        try {
            // Generiere eindeutige Modal-ID für diese Instanz
            this.currentModalId = this.generateModalId();
            
            // Lade MetaInfo-Felder
            const fields = await this.loadMetaInfoFields();
            
            return new Promise((resolve, reject) => {
                const modal = new SimpleModal();
                
                // Modal-Content erstellen
                const content = this.createModalContent(file, existingMetadata, fields);
                
                modal.show({
                    title: `Metadaten für ${file.name || file.filename}`,
                    content: content,
                    buttons: [
                        {
                            text: 'Abbrechen',
                            closeModal: true,
                            callback: () => reject(new Error('Benutzer hat abgebrochen'))
                        },
                        {
                            text: 'Speichern',
                            primary: true,
                            callback: () => {
                                const metadata = this.collectFormData(modal);
                                if (this.validateMetadata(metadata, fields)) {
                                    modal.close();
                                    resolve(metadata);
                                }
                            }
                        }
                    ]
                });
            });
        } catch (error) {
            console.error('MetaInfo Integration Error:', error);
            // Fallback zum Standard-Modal
            return this.showStandardModal(file, existingMetadata);
        }
    }
    
    /**
     * Fallback zum Standard-Modal ohne MetaInfo-Integration
     */
    showStandardModal(file, existingMetadata = {}) {
        return new Promise((resolve, reject) => {
            const modal = new SimpleModal();
            
            // Einfaches Standard-Modal
            const content = document.createElement('div');
            content.innerHTML = `
                <div class="simple-modal-form-group">
                    <label for="title">Titel:</label>
                    <input type="text" id="title" name="title" class="simple-modal-input" required value="${existingMetadata.title || ''}">
                </div>
                <div class="simple-modal-form-group">
                    <label for="med_alt">Alt-Text:</label>
                    <input type="text" id="med_alt" name="med_alt" class="simple-modal-input" value="${existingMetadata.med_alt || ''}">
                </div>
                <div class="simple-modal-form-group">
                    <label for="med_copyright">Copyright:</label>
                    <input type="text" id="med_copyright" name="med_copyright" class="simple-modal-input" value="${existingMetadata.med_copyright || ''}">
                </div>
            `;
            
            modal.show({
                title: `Metadaten für ${file.name || file.filename}`,
                content: content,
                buttons: [
                    {
                        text: 'Abbrechen',
                        closeModal: true,
                        callback: () => reject(new Error('Benutzer hat abgebrochen'))
                    },
                    {
                        text: 'Speichern',
                        primary: true,
                        callback: () => {
                            const metadata = {
                                title: content.querySelector('#title').value,
                                med_alt: content.querySelector('#med_alt').value,
                                med_copyright: content.querySelector('#med_copyright').value
                            };
                            modal.close();
                            resolve(metadata);
                        }
                    }
                ]
            });
        });
    }
    
    /**
     * Erstellt den Modal-Content mit allen verfügbaren Feldern
     */
    createModalContent(file, existingMetadata, fields) {
        const container = document.createElement('div');
        container.className = 'simple-modal-grid';
        
        // Preview-Bereich
        const previewCol = document.createElement('div');
        previewCol.className = 'simple-modal-col-4';
        
        const isImage = file.type?.startsWith('image/') || (file instanceof File && file.type.startsWith('image/'));
        
        if (isImage) {
            const preview = document.createElement('div');
            preview.className = 'simple-modal-preview';
            const img = document.createElement('img');
            img.alt = '';
            
            if (file instanceof File) {
                img.src = URL.createObjectURL(file);
            } else if (file.serverId) {
                img.src = `/redaxo/index.php?rex-api-call=filepond_uploader&action=load&id=${file.serverId}`;
            }
            
            preview.appendChild(img);
            previewCol.appendChild(preview);
        }
        
        // Form-Bereich
        const formCol = document.createElement('div');
        formCol.className = 'simple-modal-col-8';
        
        let formHTML = '';
        
        // Felder in korrekter Reihenfolge erstellen
        const sortedFields = this.sortFields(fields);
        
        for (const field of sortedFields) {
            formHTML += this.createFieldHTML(field, existingMetadata);
        }
        
        formCol.innerHTML = formHTML;
        
        container.appendChild(previewCol);
        container.appendChild(formCol);
        
        // Event-Listener nach DOM-Einfügung
        setTimeout(() => this.setupFieldEvents(container, fields), 0);
        
        return container;
    }
    
    /**
     * Sortiert Felder in gewünschter Reihenfolge
     */
    sortFields(fields) {
        const order = ['title', 'med_title_lang', 'med_alt', 'med_copyright', 'med_description'];
        const sorted = [];
        
        // Erst die Felder in definierter Reihenfolge
        for (const fieldName of order) {
            const field = fields.find(f => f.name === fieldName);
            if (field) {
                sorted.push(field);
            }
        }
        
        // Dann alle anderen
        for (const field of fields) {
            if (!order.includes(field.name)) {
                sorted.push(field);
            }
        }
        
        return sorted;
    }
    
    /**
     * Erstellt HTML für ein Feld
     */
    createFieldHTML(field, existingMetadata) {
        const fieldId = `field_${field.name}_${this.currentModalId}`;
        const uniqueFieldId = `${field.name}_${this.currentModalId}`;
        let html = '';
        
        if (field.multilingual) {
            // Mehrsprachiges Feld mit Tabs (Framework-unabhängig)
            html += `<div class="simple-modal-form-group" data-field="${field.name}">`;
            html += `<label class="control-label">`;
            html += `<i class="fa fa-globe"></i> ${field.label}`;
            html += `</label>`;
            
            html += `<div class="fp-tabs-container">`;
            
            // Custom Tab Navigation
            html += `<div class="fp-tabs-nav">`;
            field.languages.forEach((lang, index) => {
                const isActive = index === 0 ? 'active' : '';
                html += `<button type="button" class="fp-tab-btn ${isActive}" data-group="${uniqueFieldId}" data-lang="${lang.code}">`;
                html += lang.code.toUpperCase();
                html += `</button>`;
            });
            html += `</div>`;

            // Tab Content
            html += `<div class="fp-tabs-content">`;
            field.languages.forEach((lang, index) => {
                const displayStyle = index === 0 ? 'block' : 'none';
                const langValue = existingMetadata[field.name] && existingMetadata[field.name][lang.code] 
                    ? existingMetadata[field.name][lang.code] : '';
                
                html += `<div class="fp-tab-pane" id="tab_${uniqueFieldId}_${lang.code}" style="display: ${displayStyle};">`;
                
                if (field.type === 'textarea') {
                    html += `<textarea class="simple-modal-input" name="${field.name}[${lang.code}]" `;
                    html += `data-field="${field.name}" data-lang="${lang.code}" rows="3">${langValue}</textarea>`;
                } else {
                    html += `<input type="text" class="simple-modal-input" name="${field.name}[${lang.code}]" `;
                    html += `data-field="${field.name}" data-lang="${lang.code}" value="${langValue}">`;
                }
                
                html += `</div>`;
            });
            html += `</div>`; // .fp-tabs-content
            
            html += `</div>`; // .fp-tabs-container
            html += `</div>`; // .simple-modal-form-group
            
        } else {
            // Standard-Feld
            html += `<div class="simple-modal-form-group" data-field="${field.name}">`;
            html += `<label for="${fieldId}">${field.label}`;
            
            // Hinweis für Title-Feld
            if (field.name === 'title') {
                html += ` <small class="text-muted">(nur für interne Verwaltung)</small>`;
            }
            
            html += `</label>`;
            
            const fieldValue = existingMetadata[field.name] || '';
            
            if (field.type === 'textarea') {
                html += `<textarea id="${fieldId}" name="${field.name}" class="simple-modal-input" `;
                html += `data-field="${field.name}" rows="3" ${field.required ? 'required' : ''}>${fieldValue}</textarea>`;
            } else {
                html += `<input type="text" id="${fieldId}" name="${field.name}" class="simple-modal-input" `;
                html += `data-field="${field.name}" value="${fieldValue}" ${field.required ? 'required' : ''}>`;
            }
            
            html += `</div>`;
        }
        
        return html;
    }
    
    /**
     * Setzt Event-Listener für Felder
     */
    setupFieldEvents(container, fields) {
        // Auto-Titel-Generierung für title-Feld
        const titleField = container.querySelector('[data-field="title"]');
        if (titleField && !titleField.value) {
            const filename = container.closest('.simple-modal').querySelector('.simple-modal-header h2')?.textContent || '';
            const match = filename.match(/Metadaten für (.+)/);
            if (match) {
                const name = match[1].replace(/\.[^/.]+$/, ''); // Dateiendung entfernen
                titleField.value = name;
            }
        }
        
        // Tab-Switching Logic (Framework-unabhängig)
        container.querySelectorAll('.fp-tab-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const group = button.getAttribute('data-group');
                const lang = button.getAttribute('data-lang');
                
                // Active State für Buttons aktualisieren (nur in diesem Modal)
                const tabsNav = button.closest('.fp-tabs-nav');
                tabsNav.querySelectorAll('.fp-tab-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                button.classList.add('active');
                
                // Panels umschalten
                const tabsContainer = button.closest('.fp-tabs-container');
                tabsContainer.querySelectorAll('.fp-tab-pane').forEach(pane => {
                    pane.style.display = 'none';
                });
                
                const activePane = tabsContainer.querySelector(`#tab_${group}_${lang}`);
                if (activePane) {
                    activePane.style.display = 'block';
                }
            });
        });
    }
    
    /**
     * Helper für Tabs
     */
    toggleLanguageFields(button) {
        // Deprecated - kept for compatibility
    }
    
    /**
     * Sammelt Formulardaten
     */
    collectFormData(modal) {
        const metadata = {};
        const inputs = modal.modal.querySelectorAll('input, textarea');
        
        inputs.forEach(input => {
            const fieldName = input.getAttribute('data-field');
            const langCode = input.getAttribute('data-lang');
            
            if (fieldName) {
                if (langCode) {
                    // Mehrsprachiges Feld
                    if (!metadata[fieldName]) {
                        metadata[fieldName] = {};
                    }
                    metadata[fieldName][langCode] = input.value;
                } else {
                    // Standard-Feld
                    metadata[fieldName] = input.value;
                }
            }
        });
        
        return metadata;
    }
    
    /**
     * Validiert Metadaten
     */
    validateMetadata(metadata, fields) {
        for (const field of fields) {
            if (field.required && (!metadata[field.name] || metadata[field.name].trim() === '')) {
                const input = document.querySelector(`[data-field="${field.name}"]`);
                if (input) {
                    input.focus();
                    input.reportValidity?.();
                }
                return false;
            }
        }
        return true;
    }
}

// CSS für mehrsprachige Felder und Tabs
const style = document.createElement('style');
style.textContent = `
.fp-tabs-container {
    margin-top: 5px;
    border: 1px solid var(--modal-color-border, #ddd);
    border-radius: 4px;
    background: var(--modal-color-bg, #fff);
}

.fp-tabs-nav {
    display: flex;
    background: rgba(0,0,0,0.03);
    border-bottom: 1px solid var(--modal-color-border, #ddd);
    padding: 0 5px;
}

.fp-tab-btn {
    border: none;
    background: transparent;
    padding: 8px 12px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
    color: inherit;
    opacity: 0.7;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
}

.fp-tab-btn:hover {
    opacity: 1;
    background: rgba(0,0,0,0.02);
}

.fp-tab-btn.active {
    opacity: 1;
    border-bottom: 2px solid #5b9bd5; /* Rex Blue fallback */
    background: var(--modal-color-bg, #fff);
    font-weight: bold;
}

.fp-tabs-content {
    padding: 10px;
}

/* Dark Mode Adjustments */
.rex-theme-dark .fp-tabs-container {
    background: rgba(255,255,255,0.02);
}

.rex-theme-dark .fp-tab-btn.active {
    background: transparent;
    border-bottom-color: #4b9ad9;
}
`;
document.head.appendChild(style);

// Initialisierung
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.filepond_auto_metainfo === 'undefined') {
        window.filepond_auto_metainfo = new FilePondAutoMetaInfo();
    }
});