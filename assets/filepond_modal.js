class SimpleModal {
    constructor() {
        this.modal = document.createElement('div');
        this.modal.className = 'simple-modal';
        this.modal.style.display = 'none';

        const style = document.createElement('style');
        style.textContent = `
            .simple-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(40, 53, 66, .95);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
                opacity: 0;
                transition: opacity 0.3s ease-in-out;
            }

            .simple-modal-content {
                background: #fff;
                border-radius: 4px;
                max-width: 90%;
                max-height: 90vh;
                overflow: auto;
                position: relative;
                width: 800px;
                transform: translateY(-20px);
                opacity: 0;
                transition: all 0.3s ease-in-out;
                box-shadow: 0 10px 25px rgba(40, 53, 66, .2);
            }

            .simple-modal.show {
                opacity: 1;
            }

            .simple-modal.show .simple-modal-content {
                transform: translateY(0);
                opacity: 1;
            }

            .simple-modal-header {
                padding: 15px 20px;
                background: #283542;
                color: white;
                border-top-left-radius: 4px;
                border-top-right-radius: 4px;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .simple-modal-header h2 {
                margin: 0;
                font-size: 1.2rem;
                font-weight: normal;
                color: white;
            }

            .simple-modal-body {
                padding: 20px;
            }

            .simple-modal-footer {
                padding: 15px 20px;
                background: #f3f6fb;
                border-bottom-left-radius: 4px;
                border-bottom-right-radius: 4px;
                text-align: right;
                border-top: 1px solid #e9ecef;
            }

            .simple-modal-close {
                color: white;
                border: none;
                background: none;
                font-size: 24px;
                cursor: pointer;
                padding: 0;
                line-height: 1;
                opacity: .75;
                transition: opacity 0.2s;
            }

            .simple-modal-close:hover {
                opacity: 1;
            }

            .simple-modal button.modal-btn {
                padding: 8px 16px;
                border-radius: 3px;
                border: 1px solid #ddd;
                background: #fff;
                cursor: pointer;
                margin-left: 10px;
                font-size: 14px;
                line-height: 1.5;
                transition: all 0.2s;
            }

            .simple-modal button.modal-btn:hover {
                background: #f8f9fa;
                border-color: #cfcfcf;
            }

            .simple-modal button.modal-btn.primary {
                background: #3bb594;
                border-color: #3bb594;
                color: white;
            }

            .simple-modal button.modal-btn.primary:hover {
                background: #318c73;
                border-color: #318c73;
            }

            /* Form Styling */
            .simple-modal input[type="text"],
            .simple-modal textarea {
                display: block;
                width: 100%;
                padding: 8px 12px;
                font-size: 14px;
                line-height: 1.5;
                color: #495057;
                background-color: #fff;
                border: 1px solid #ced4da;
                border-radius: 3px;
                transition: border-color 0.2s;
            }

            .simple-modal input[type="text"]:focus,
            .simple-modal textarea:focus {
                border-color: #3bb594;
                outline: 0;
                box-shadow: 0 0 0 0.2rem rgba(59, 181, 148, 0.25);
            }

            .simple-modal label {
                display: block;
                margin-bottom: 5px;
                color: #495057;
                font-weight: 500;
            }

            .simple-modal .help-text {
                color: #6c757d;
                font-size: 12px;
                margin-top: 4px;
            }

            @media (prefers-color-scheme: dark) {
                .simple-modal-content {
                    background: #32373c;
                    color: #f1f3f4;
                }
                
                .simple-modal-header {
                    background: #212529;
                }
                
                .simple-modal-footer {
                    background: #283542;
                    border-top-color: #32373c;
                }
                
                .simple-modal button.modal-btn {
                    background: #3c434a;
                    border-color: #4a545c;
                    color: #f1f3f4;
                }

                .simple-modal button.modal-btn:hover {
                    background: #4a545c;
                }

                .simple-modal button.modal-btn.primary {
                    background: #3bb594;
                    border-color: #3bb594;
                }

                .simple-modal button.modal-btn.primary:hover {
                    background: #318c73;
                    border-color: #318c73;
                }

                .simple-modal input[type="text"],
                .simple-modal textarea {
                    background-color: #283542;
                    border-color: #4a545c;
                    color: #f1f3f4;
                }

                .simple-modal input[type="text"]:focus,
                .simple-modal textarea:focus {
                    border-color: #3bb594;
                    box-shadow: 0 0 0 0.2rem rgba(59, 181, 148, 0.25);
                }

                .simple-modal label {
                    color: #f1f3f4;
                }

                .simple-modal .help-text {
                    color: #9aa0a6;
                }

                .simple-modal-preview {
                    background: #283542 !important;
                    border-color: #4a545c !important;
                }
            }

            /* Animation keyframes */
            @keyframes modalIn {
                from {
                    opacity: 0;
                    transform: translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            @keyframes modalOut {
                from {
                    opacity: 1;
                    transform: translateY(0);
                }
                to {
                    opacity: 0;
                    transform: translateY(20px);
                }
            }
        `;
        document.head.appendChild(style);
        this.handleClose = this.close.bind(this);
    }

    show(options) {
        const content = document.createElement('div');
        content.className = 'simple-modal-content';

        // Header
        if (options.title) {
            const header = document.createElement('div');
            header.className = 'simple-modal-header';
            
            const title = document.createElement('h2');
            title.textContent = options.title;
            header.appendChild(title);

            const closeBtn = document.createElement('button');
            closeBtn.className = 'simple-modal-close';
            closeBtn.innerHTML = '×';
            closeBtn.onclick = this.handleClose;
            header.appendChild(closeBtn);
            
            content.appendChild(header);
        }

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
                button.className = 'modal-btn' + (btn.primary ? ' primary' : '');
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
        document.body.appendChild(this.modal);
        
        // Trigger animation
        requestAnimationFrame(() => {
            this.modal.style.display = 'flex';
            requestAnimationFrame(() => {
                this.modal.classList.add('show');
            });
        });

        // Event Listener für ESC-Taste
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.close();
        });

        // Click outside to close
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) this.close();
        });
    }

    close() {
        this.modal.classList.remove('show');
        setTimeout(() => {
            this.modal.style.display = 'none';
            if (this.modal.parentNode) {
                this.modal.parentNode.removeChild(this.modal);
            }
        }, 300); // Match transition duration
    }
}

// Global verfügbar machen
window.SimpleModal = SimpleModal;
