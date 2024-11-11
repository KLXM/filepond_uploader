class SimpleModal {
    constructor() {
        // Modal Container erstellen
        this.modal = document.createElement('div');
        this.modal.className = 'simple-modal';
        this.modal.style.display = 'none';

        // Modal Styles
        const style = document.createElement('style');
        style.textContent = `
            .simple-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
            }

            .simple-modal-content {
                background: #fff;
                padding: 20px;
                border-radius: 8px;
                max-width: 90%;
                max-height: 90vh;
                overflow: auto;
                position: relative;
                width: 800px;
            }

            .simple-modal-header {
                margin: -20px -20px 20px -20px;
                padding: 15px 20px;
                background: #f7f7f7;
                border-top-left-radius: 8px;
                border-top-right-radius: 8px;
                border-bottom: 1px solid #eee;
            }

            .simple-modal-footer {
                margin: 20px -20px -20px -20px;
                padding: 15px 20px;
                background: #f7f7f7;
                border-bottom-left-radius: 8px;
                border-bottom-right-radius: 8px;
                border-top: 1px solid #eee;
                text-align: right;
            }

            .simple-modal-close {
                position: absolute;
                right: 10px;
                top: 10px;
                border: none;
                background: none;
                font-size: 24px;
                cursor: pointer;
                padding: 5px;
                line-height: 1;
            }

            .simple-modal button {
                padding: 8px 16px;
                border-radius: 4px;
                border: 1px solid #ddd;
                background: #fff;
                cursor: pointer;
                margin-left: 10px;
            }

            .simple-modal button.primary {
                background: #4CAF50;
                color: white;
                border-color: #4CAF50;
            }

            .simple-modal button:hover {
                opacity: 0.8;
            }

            @media (prefers-color-scheme: dark) {
                .simple-modal-content {
                    background: #333;
                    color: #fff;
                }
                
                .simple-modal-header,
                .simple-modal-footer {
                    background: #2a2a2a;
                    border-color: #444;
                }
                
                .simple-modal button {
                    background: #444;
                    border-color: #555;
                    color: #fff;
                }
            }
        `;
        document.head.appendChild(style);

        // Close Handler
        this.handleClose = this.close.bind(this);
    }

    show(options) {
        const content = document.createElement('div');
        content.className = 'simple-modal-content';

        // Header
        if (options.title) {
            const header = document.createElement('div');
            header.className = 'simple-modal-header';
            header.innerHTML = `<h2>${options.title}</h2>`;
            content.appendChild(header);
        }

        // Close Button
        const closeBtn = document.createElement('button');
        closeBtn.className = 'simple-modal-close';
        closeBtn.innerHTML = '×';
        closeBtn.onclick = this.handleClose;
        content.appendChild(closeBtn);

        // Body
        if (options.content) {
            const body = document.createElement('div');
            body.className = 'simple-modal-body';
            if (typeof options.content === 'string') {
                body.innerHTML = options.content;
            } else {
                body.appendChild(options.content);
            }
            content.appendChild(body);
        }

        // Footer with buttons
        if (options.buttons) {
            const footer = document.createElement('div');
            footer.className = 'simple-modal-footer';
            
            options.buttons.forEach(btn => {
                const button = document.createElement('button');
                button.className = btn.primary ? 'primary' : '';
                button.textContent = btn.text;
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
        this.modal.style.display = 'flex';
        document.body.appendChild(this.modal);

        // Event Listener für ESC-Taste
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.close();
        });
    }

    close() {
        this.modal.style.display = 'none';
        if (this.modal.parentNode) {
            this.modal.parentNode.removeChild(this.modal);
        }
    }
}

// Globale Verfügbarkeit sicherstellen
window.SimpleModal = SimpleModal;
