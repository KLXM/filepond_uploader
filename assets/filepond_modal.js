class SimpleModal {
    constructor() {
        this.modal = document.createElement('div');
        this.modal.className = 'simple-modal';
        this.modal.style.display = 'none';

        // Prüfen ob Style bereits existiert
        if (!document.getElementById('simple-modal-style')) {
            const style = document.createElement('style');
            style.id = 'simple-modal-style';
            style.textContent = `
                :root {
                    --modal-color-bg: #fff;
                    --modal-color-text: #283542;
                    --modal-color-border: #ced4da;
                    --modal-color-header: #283542;
                    --modal-color-header-text: #fff;
                    --modal-color-footer: #f5f5f5;
                    --modal-color-input: #fff;
                    --modal-color-input-border: #ced4da;
                    --modal-color-primary: #3bb594;
                    --modal-color-primary-hover: #318c73;
                    --modal-color-danger: #c33;
                    --modal-color-danger-hover: #a52b2b;
                    --modal-backdrop: rgba(40, 53, 66, .95);
                    --modal-progress-bg: #e9ecef;
                    --modal-progress-fill: #3bb594;
                }
                
                @media (prefers-color-scheme: dark) {
                    :root {
                        --modal-color-bg: #32373c;
                        --modal-color-text: #f5f5f5;
                        --modal-color-border: #404448;
                        --modal-color-header: #202528;
                        --modal-color-footer: #282c30;
                        --modal-color-input: #32373c;
                        --modal-color-input-border: #404448;
                        --modal-backdrop: rgba(0, 0, 0, 0.9);
                        --modal-progress-bg: #404448;
                        --modal-progress-fill: #3bb594;
                    }
                }
                
                // Dark Mode via REDAXO Theme Class
                .rex-theme-dark .simple-modal {
                    --modal-color-bg: #32373c;
                    --modal-color-text: #f5f5f5;
                    --modal-color-border: #404448;
                    --modal-color-header: #202528;
                    --modal-color-footer: #282c30;
                    --modal-color-input: #32373c;
                    --modal-color-input-border: #404448;
                    --modal-backdrop: rgba(0, 0, 0, 0.9);
                    --modal-progress-bg: #404448;
                    --modal-progress-fill: #3bb594;
                }

                .simple-modal {
                    position: fixed;
                    inset: 0;
                    background: var(--modal-backdrop);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 10000;
                    opacity: 0;
                    transition: opacity .3s ease;
                    padding: 20px;
                }

                .simple-modal.show {
                    opacity: 1;
                }

                .simple-modal.show .simple-modal-content {
                    transform: translate(0) scale(1);
                    opacity: 1;
                }

                .simple-modal-content {
                    background: var(--modal-color-bg);
                    color: var(--modal-color-text);
                    border-radius: 4px;
                    max-width: 800px;
                    width: 100%;
                    max-height: 90vh;
                    transform: translateY(-20px) scale(.95);
                    opacity: 0;
                    transition: all .3s ease;
                    margin: auto;
                    display: flex;
                    flex-direction: column;
                }

                .simple-modal-header {
                    background: var(--modal-color-header);
                    color: var(--modal-color-header-text);
                    padding: 15px 20px;
                    border-radius: 4px 4px 0 0;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                }

                .simple-modal-header h2 {
                    margin: 0;
                    font-size: 1.2rem;
                    font-weight: normal;
                    color: var(--modal-color-header-text);
                }

                .simple-modal-close {
                    color: var(--modal-color-header-text);
                    border: 0;
                    background: none;
                    font-size: 24px;
                    cursor: pointer;
                    padding: 0;
                    opacity: .75;
                    transition: opacity .2s;
                }

                .simple-modal-close:hover {
                    opacity: 1;
                }

                .simple-modal-body {
                    padding: 20px;
                    overflow: auto;
                }

                .simple-modal-footer {
                    background: var(--modal-color-footer);
                    padding: 15px 20px;
                    border-top: 1px solid var(--modal-color-border);
                    border-radius: 0 0 4px 4px;
                    display: flex;
                    justify-content: flex-end;
                    gap: 10px;
                }

                .simple-modal-grid {
                    display: grid;
                    grid-template-columns: minmax(200px, 1fr) 2fr;
                    gap: 20px;
                }

                .simple-modal-preview {
                    background: var(--modal-color-footer);
                    border: 1px solid var(--modal-color-border);
                    border-radius: 4px;
                    padding: 15px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 200px;
                }

                .simple-modal-preview img,
                .simple-modal-preview video {
                    max-width: 100%;
                    max-height: 300px;
                    object-fit: contain;
                }

                .simple-modal input,
                .simple-modal textarea {
                    width: 100%;
                    padding: 8px;
                    background: var(--modal-color-input);
                    border: 1px solid var(--modal-color-input-border);
                    border-radius: 4px;
                    color: var(--modal-color-text);
                }

                .simple-modal textarea {
                    resize: vertical;
                    min-height: 80px;
                }

                .simple-modal input:focus,
                .simple-modal textarea:focus {
                    border-color: var(--modal-color-primary);
                    outline: none;
                }

                .simple-modal label {
                    display: block;
                    margin-bottom: 5px;
                }

                .simple-modal .help-text {
                    font-size: 0.85em;
                    opacity: 0.7;
                    margin-top: 4px;
                }

                .simple-modal .modal-btn {
                    padding: 8px 16px;
                    border-radius: 4px;
                    border: 0;
                    cursor: pointer;
                    min-width: 80px;
                    font-size: 14px;
                    transition: .2s ease;
                    background: var(--modal-color-primary);
                    color: #fff;
                }

                .simple-modal .modal-btn:hover {
                    background: var(--modal-color-primary-hover);
                }

                .simple-modal .modal-btn.danger {
                    background: var(--modal-color-danger);
                }

                .simple-modal .modal-btn.danger:hover {
                    background: var(--modal-color-danger-hover);
                }

                .simple-modal-progress {
                    width: 100%;
                    height: 8px;
                    background: var(--modal-progress-bg);
                    border-radius: 4px;
                    margin-top: 15px;
                    overflow: hidden;
                }

                .simple-modal-progress-bar {
                    height: 100%;
                    background: var(--modal-progress-fill);
                    width: 0;
                    transition: width 0.2s ease;
                }

                .simple-modal-file-icon {
                    font-size: 3rem;
                    opacity: 0.7;
                }

                .simple-modal-form-group {
                    margin-bottom: 15px;
                }

                @media (max-width: 768px) {
                    .simple-modal-grid {
                        grid-template-columns: 1fr;
                    }
                    
                    .simple-modal-preview {
                        max-height: 200px;
                    }
                }
            `;
            document.head.appendChild(style);
        }
    }

    show(options) {
        const content = document.createElement('div');
        content.className = 'simple-modal-content';

        if (options.title) {
            const header = document.createElement('div');
            header.className = 'simple-modal-header';
            
            const title = document.createElement('h2');
            title.textContent = options.title;
            header.appendChild(title);

            // Close button hinzufügen, wenn nicht explizit deaktiviert
            if (options.showCloseButton !== false) {
                const closeBtn = document.createElement('button');
                closeBtn.className = 'simple-modal-close';
                closeBtn.innerHTML = '×';
                closeBtn.onclick = () => {
                    if (options.buttons) {
                        const cancelButton = options.buttons.find(btn => !btn.primary);
                        if (cancelButton && cancelButton.handler) {
                            cancelButton.handler();
                        }
                    }
                    this.close();
                };
                header.appendChild(closeBtn);
            }
            
            content.appendChild(header);
        }

        if (options.content) {
            const body = document.createElement('div');
            body.className = 'simple-modal-body';
            if (typeof options.content === 'string') {
                body.innerHTML = options.content;
            } else {
                body.appendChild(options.content);
            }
            content.appendChild(body);
            
            // Fortschrittsbalken hinzufügen, wenn gewünscht
            if (options.showProgress) {
                const progress = document.createElement('div');
                progress.className = 'simple-modal-progress';
                
                const progressBar = document.createElement('div');
                progressBar.className = 'simple-modal-progress-bar';
                progress.appendChild(progressBar);
                
                body.appendChild(progress);
                
                // Methode zum Aktualisieren des Fortschritts
                this.updateProgress = (percent) => {
                    progressBar.style.width = `${percent}%`;
                };
            }
        }

        if (options.buttons) {
            const footer = document.createElement('div');
            footer.className = 'simple-modal-footer';
            
            const sortedButtons = [...options.buttons].sort((a, b) => {
                return a.primary && !b.primary ? 1 : !a.primary && b.primary ? -1 : 0;
            });

            sortedButtons.forEach(btn => {
                const button = document.createElement('button');
                button.className = `modal-btn ${btn.primary ? '' : 'danger'}`;
                button.textContent = btn.text;
                
                // Button deaktivieren, wenn Option gesetzt
                if (btn.disabled) {
                    button.disabled = true;
                }
                
                button.onclick = () => {
                    if (btn.handler) {
                        btn.handler();
                    }
                    if (btn.closeModal) {
                        this.close();
                    }
                };
                footer.appendChild(button);
            });
            
            content.appendChild(footer);
        }

        this.modal.innerHTML = '';
        this.modal.appendChild(content);
        document.body.appendChild(this.modal);

        const handleClose = () => {
            if (options.buttons) {
                const cancelButton = options.buttons.find(btn => !btn.primary);
                if (cancelButton && cancelButton.handler) {
                    cancelButton.handler();
                }
            }
            this.close();
        };

        // Nur Background-Klick zulassen, wenn nicht explizit deaktiviert
        if (options.closeOnBackdrop !== false) {
            this.modal.addEventListener('click', (e) => {
                if (e.target === this.modal) handleClose();
            });
        }

        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                handleClose();
                document.removeEventListener('keydown', handleEscape);
            }
        };
        document.addEventListener('keydown', handleEscape);
        
        requestAnimationFrame(() => {
            this.modal.style.display = 'flex';
            requestAnimationFrame(() => {
                this.modal.classList.add('show');
            });
        });
        
        // Zugriff auf Footer-Buttons ermöglichen
        if (options.buttons) {
            this.buttons = {};
            options.buttons.forEach((btn, index) => {
                if (btn.id) {
                    this.buttons[btn.id] = this.modal.querySelectorAll('.modal-btn')[index];
                }
            });
        }
        
        return this;
    }

    close() {
        this.modal.classList.remove('show');
        setTimeout(() => {
            this.modal.style.display = 'none';
            if (this.modal.parentNode) {
                this.modal.parentNode.removeChild(this.modal);
            }
        }, 300);
    }
    
    // Button per ID aktivieren/deaktivieren
    setButtonState(id, enabled) {
        if (this.buttons && this.buttons[id]) {
            this.buttons[id].disabled = !enabled;
        }
    }
    
    // Titel aktualisieren
    updateTitle(title) {
        const titleElement = this.modal.querySelector('.simple-modal-header h2');
        if (titleElement) {
            titleElement.textContent = title;
        }
    }
    
    // Content aktualisieren
    updateContent(content) {
        const bodyElement = this.modal.querySelector('.simple-modal-body');
        if (bodyElement) {
            bodyElement.innerHTML = '';
            if (typeof content === 'string') {
                bodyElement.innerHTML = content;
            } else {
                bodyElement.appendChild(content);
            }
        }
    }

    static setTheme(theme = {}) {
        for (const [key, value] of Object.entries(theme)) {
            document.documentElement.style.setProperty(`--modal-${key}`, value);
        }
    }
    
    // Fortschritts-Modal anzeigen
    static showProgress(title, message) {
        const modal = new SimpleModal();
        const content = document.createElement('div');
        content.innerHTML = `<p>${message}</p>`;
        
        modal.show({
            title: title,
            content: content,
            showProgress: true,
            showCloseButton: false,
            closeOnBackdrop: false
        });
        
        return modal;
    }
    
    // Benachrichtigungs-Modal anzeigen
    static notify(title, message, type = 'info', autoClose = 3000) {
        const modal = new SimpleModal();
        const content = document.createElement('div');
        content.innerHTML = `<p>${message}</p>`;
        
        let buttonClass = '';
        if (type === 'error') buttonClass = 'danger';
        
        modal.show({
            title: title,
            content: content,
            buttons: [{
                text: 'OK',
                primary: type !== 'error',
                closeModal: true,
                class: buttonClass
            }]
        });
        
        if (autoClose) {
            setTimeout(() => {
                modal.close();
            }, autoClose);
        }
        
        return modal;
    }
}

window.SimpleModal = SimpleModal;
