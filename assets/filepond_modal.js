class SimpleModal {
    constructor() {
        this.modal=document.createElement('div');
        this.modal.className='simple-modal';
        this.modal.style.display='none';

        const style=document.createElement('style');

        style.textContent=` :root {
            /* Colors */
            --modal-primary: #3bb594;
            --modal-primary-hover: #318c73;
            --modal-background: #fff;
            --modal-backdrop: rgba(40, 53, 66, .95);
            --modal-header-bg: #283542;
            --modal-header-color: #fff;
            --modal-footer-bg: #f3f6fb;
            --modal-border-color: #e9ecef;
            --modal-shadow: rgba(40, 53, 66, .2);
            --modal-text: #495057;
            --modal-text-light: #6c757d;
            --modal-close-opacity: 0.75;

            /* Sizes */
            --modal-width: 800px;
            --modal-max-width: 90%;
            --modal-max-height: 90vh;
            --modal-border-radius: 0px;
            --modal-padding: 20px;
            --modal-header-padding: 15px 20px;
            --modal-footer-padding: 15px 20px;

            /* Fonts */
            --modal-font-size: 14px;
            --modal-title-size: 1.2rem;
            --modal-line-height: 1.5;

            /* Animations */
            --modal-animation-duration: 0.3s;
            --modal-animation-timing: ease-in-out;
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
            transition: opacity var(--modal-animation-duration) var(--modal-animation-timing);
            padding: calc(var(--modal-padding) * 2);
        }

        .simple-modal img,
        .simple-modal video {
            max-width: 100%;
        }

        .simple-modal-content {
            background: var(--modal-background);
            border-radius: var(--modal-border-radius);
            width: var(--modal-width);
            max-width: var(--modal-max-width);
            max-height: var(--modal-max-height);
            overflow: auto;
            position: relative;
            transform: translateY(-20px) scale(0.95);
            opacity: 0;
            transition: all var(--modal-animation-duration) var(--modal-animation-timing);
            box-shadow: 0 10px 25px var(--modal-shadow);
            margin: auto;
        }

        .simple-modal.show {
            opacity: 1;
        }

        .simple-modal.show .simple-modal-content {
            transform: translateY(0) scale(1);
            opacity: 1;
        }

        .simple-modal-header {
            padding: var(--modal-header-padding);
            background: var(--modal-header-bg);
            color: var(--modal-header-color);
            border-top-left-radius: var(--modal-border-radius);
            border-top-right-radius: var(--modal-border-radius);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .simple-modal-header h2 {
            margin: 0;
            font-size: var(--modal-title-size);
            font-weight: normal;
            color: var(--modal-header-color);
        }

        .simple-modal-body {
            padding: var(--modal-padding);
        }

        .simple-modal-footer {
            padding: var(--modal-footer-padding);
            background: var(--modal-footer-bg);
            border-bottom-left-radius: var(--modal-border-radius);
            border-bottom-right-radius: var(--modal-border-radius);
            text-align: right;
            border-top: 1px solid var(--modal-border-color);
        }

        .simple-modal-close {
            color: var(--modal-header-color);
            border: none;
            background: none;
            font-size: calc(var(--modal-title-size) * 1.5);
            cursor: pointer;
            padding: 0;
            line-height: 1;
            opacity: var(--modal-close-opacity);
            transition: opacity var(--modal-animation-duration);
        }

        .simple-modal-close:hover {
            opacity: 1;
        }

        .modal-btn {
            padding: 8px 16px;
            border-radius: calc(var(--modal-border-radius) - 1px);
            border: 1px solid var(--modal-border-color);
            background: var(--modal-background);
            cursor: pointer;
            margin-left: 10px;
            font-size: var(--modal-font-size);
            line-height: var(--modal-line-height);
            transition: all var(--modal-animation-duration);
        }

        .modal-btn:hover {
            background: var(--modal-footer-bg);
            border-color: var(--modal-text-light);
        }

        .modal-btn.primary {
            background: var(--modal-primary);
            border-color: var(--modal-primary);
            color: white;
        }

        .modal-btn.primary:hover {
            background: var(--modal-primary-hover);
            border-color: var(--modal-primary-hover);
        }

        /* Form elements */
        .simple-modal input[type="text"],
        .simple-modal textarea {
            display: block;
            width: 100%;
            padding: 8px 12px;
            font-size: var(--modal-font-size);
            line-height: var(--modal-line-height);
            color: var(--modal-text);
            background-color: var(--modal-background);
            border: 1px solid var(--modal-border-color);
            border-radius: calc(var(--modal-border-radius) - 1px);
            transition: border-color var(--modal-animation-duration);
        }

        .simple-modal input[type="text"]:focus,
        .simple-modal textarea:focus {
            border-color: var(--modal-primary);
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(59, 181, 148, 0.25);
        }

        .simple-modal label {
            display: block;
            margin-bottom: 5px;
            color: var(--modal-text);
            font-weight: 500;
        }

        .simple-modal .help-text {
            color: var(--modal-text-light);
            font-size: calc(var(--modal-font-size) * 0.85);
            margin-top: 4px;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --modal-background: #32373c;
                --modal-text: #f1f3f4;
                --modal-text-light: #9aa0a6;
                --modal-header-bg: #212529;
                --modal-footer-bg: #283542;
                --modal-border-color: #4a545c;
            }
        }

        /* Dark Theme für Redaxo spezifisch */
        .rexk-theme-dark :root {
            --modal-background: #32373c;
            --modal-text: #f1f3f4;
            --modal-text-light: #9aa0a6;
            --modal-header-bg: #212529;
            --modal-footer-bg: #283542;
            --modal-border-color: #4a545c;
            --modal-shadow: rgba(15, 20, 25, 0.2);
            --modal-primary: #3ba1e3;
            --modal-primary-hover: #318ac5;
        }



        .simple-modal-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: var(--modal-padding);
        }


        .simple-modal-col-4 {
            grid-column: span 4;
        }

        .simple-modal-col-8 {
            grid-column: span 8;
        }

        @media (max-width: 768px) {
            :root {
                --modal-padding: 15px;
            }

            .simple-modal-col-4,
            .simple-modal-col-8 {
                grid-column: span 12;
            }
        }

        /* Preview Container */
        .simple-modal-preview {
            background: var(--modal-footer-bg);
            border: 1px solid var(--modal-border-color);
            border-radius: var(--modal-border-radius);
            padding: var(--modal-padding);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 200px;
        }

        `;
        document.head.appendChild(style);
        this.handleClose=this.close.bind(this);
    }

    // Statische Methode zum Überschreiben der CSS-Variablen
    static setTheme(variables) {
        const root=document.documentElement;

        for (const [key, value] of Object.entries(variables)) {
            root.style.setProperty(`--modal-$ {
                    key
                }

                `, value);
        }
    }

    show(options) {
        const content=document.createElement('div');
        content.className='simple-modal-content';

        // Header
        if (options.title) {
            const header=document.createElement('div');
            header.className='simple-modal-header';

            const title=document.createElement('h2');
            title.textContent=options.title;
            header.appendChild(title);

            const closeBtn=document.createElement('button');
            closeBtn.className='simple-modal-close';
            closeBtn.innerHTML='×';
            closeBtn.onclick=this.handleClose;
            header.appendChild(closeBtn);

            content.appendChild(header);
        }

        // Body
        if (options.content) {
            const body=document.createElement('div');
            body.className='simple-modal-body';

            if (typeof options.content==='string') {
                body.innerHTML=options.content;
            }

            else {
                body.appendChild(options.content);
            }

            content.appendChild(body);
        }

        // Footer with buttons
        if (options.buttons) {
            const footer=document.createElement('div');
            footer.className='simple-modal-footer';

            options.buttons.forEach(btn=> {
                    const button=document.createElement('button');

                    button.className=`modal-btn$ {
                        btn.primary ? ' primary' : ''
                    }

                    `;
                    button.textContent=btn.text;

                    button.onclick=()=> {
                        if (btn.handler) {
                            btn.handler();
                        }

                        if (btn.closeModal) {
                            this.close();
                        }
                    }

                    ;
                    footer.appendChild(button);
                });

            content.appendChild(footer);
        }

        this.modal.innerHTML='';
        this.modal.appendChild(content);
        document.body.appendChild(this.modal);

        // Trigger animation
        requestAnimationFrame(()=> {
                this.modal.style.display='flex';

                requestAnimationFrame(()=> {
                        this.modal.classList.add('show');
                    });
            });

        // Event Listener für ESC-Taste
        document.addEventListener('keydown', (e)=> {
                if (e.key==='Escape') this.close();
            });

        // Click outside to close
        this.modal.addEventListener('click', (e)=> {
                if (e.target===this.modal) this.close();
            });
    }

    close() {
        this.modal.classList.remove('show');

        setTimeout(()=> {
                this.modal.style.display='none';

                if (this.modal.parentNode) {
                    this.modal.parentNode.removeChild(this.modal);
                }
            }

            , 300);
    }
}

// Global verfügbar machen
window.SimpleModal=SimpleModal;
