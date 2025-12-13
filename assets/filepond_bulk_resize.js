/**
 * FilePond Uploader - Bulk Resize JavaScript
 * Asynchrone Bildverarbeitung mit Progress-Modal
 */

(function($) {
    'use strict';

    const BulkResize = {
        batchId: null,
        totalFiles: 0,
        processedFiles: 0,
        successfulFiles: 0,
        errorFiles: 0,
        skippedFiles: 0,
        savedBytes: 0,
        isProcessing: false,
        pollInterval: null,
        
        init: function() {
            this.bindEvents();
            this.updateCounter();
            this.initLazyLoading();
        },
        
        initLazyLoading: function() {
            // Lazy load thumbnails with IntersectionObserver
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver(function(entries, observer) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            const src = img.getAttribute('data-src');
                            if (src) {
                                img.src = src;
                                img.removeAttribute('data-src');
                            }
                            observer.unobserve(img);
                        }
                    });
                }, {
                    rootMargin: '50px 0px',
                    threshold: 0.01
                });

                document.querySelectorAll('.bulk-resize-thumb[data-src]').forEach(function(img) {
                    imageObserver.observe(img);
                });
            } else {
                // Fallback for older browsers
                document.querySelectorAll('.bulk-resize-thumb[data-src]').forEach(function(img) {
                    img.src = img.getAttribute('data-src');
                });
            }
        },
        
        bindEvents: function() {
            const self = this;
            
            // Toggle All Checkbox
            $('#bulk-toggle-all').on('change', function() {
                $('.filepond-bulk-resize-table input[type="checkbox"]').prop('checked', $(this).prop('checked'));
                self.updateCounter();
            });
            
            // Individual Checkboxes
            $(document).on('change', '.filepond-bulk-resize-table input[type="checkbox"]:not(#bulk-toggle-all)', function() {
                self.updateCounter();
                
                // Update toggle-all state
                const totalCheckboxes = $('.filepond-bulk-resize-table input[type="checkbox"]:not(#bulk-toggle-all)').length;
                const checkedCheckboxes = $('.filepond-bulk-resize-table input[type="checkbox"]:not(#bulk-toggle-all):checked').length;
                $('#bulk-toggle-all').prop('checked', totalCheckboxes === checkedCheckboxes);
            });
            
            // Submit Buttons (oben und unten)
            $('#bulk-resize-submit, #bulk-resize-submit-top').on('click', function(e) {
                e.preventDefault();
                self.startProcessing();
            });
        },
        
        updateCounter: function() {
            const count = $('.filepond-bulk-resize-table input[type="checkbox"]:checked:not(#bulk-toggle-all)').length;
            
            // Update beide Buttons (oben und unten)
            $('#bulk-resize-submit .number, #bulk-resize-submit-top .number').text(count);
            
            if (count > 0) {
                $('#bulk-resize-submit, #bulk-resize-submit-top').prop('disabled', false);
            } else {
                $('#bulk-resize-submit, #bulk-resize-submit-top').prop('disabled', true);
            }
        },
        
        startProcessing: function() {
            const self = this;
            
            if (this.isProcessing) {
                return;
            }
            
            // Sammle ausgewählte Dateien
            const selectedFiles = [];
            $('.filepond-bulk-resize-table input[type="checkbox"]:checked:not(#bulk-toggle-all)').each(function() {
                selectedFiles.push($(this).val());
            });
            
            if (selectedFiles.length === 0) {
                alert('Bitte wählen Sie mindestens eine Datei aus.');
                return;
            }
            
            this.totalFiles = selectedFiles.length;
            this.processedFiles = 0;
            this.successfulFiles = 0;
            this.errorFiles = 0;
            this.skippedFiles = 0;
            this.savedBytes = 0;
            this.isProcessing = true;
            
            // Zeige Modal
            this.showModal();
            
            // Starte Batch - hole Werte von einem der Buttons
            const maxWidth = parseInt($('#bulk-resize-submit').data('max-width') || $('#bulk-resize-submit-top').data('max-width')) || null;
            const maxHeight = parseInt($('#bulk-resize-submit').data('max-height') || $('#bulk-resize-submit-top').data('max-height')) || null;
            
            $.ajax({
                url: 'index.php',
                type: 'POST',
                dataType: 'json',
                traditional: true,
                data: {
                    'rex-api-call': 'filepond_bulk_process',
                    action: 'start',
                    filenames: selectedFiles,
                    maxWidth: maxWidth,
                    maxHeight: maxHeight
                },
                success: function(response) {
                    console.log('Start response:', response);
                    if (response.success && response.data && response.data.batchId) {
                        self.batchId = response.data.batchId;
                        self.updateModal();
                        self.startPolling();
                        self.processNextBatch();
                    } else {
                        const errorMsg = response.error || (response.data && response.data.error) || 'Unbekannter Fehler';
                        console.error('Start error:', errorMsg, response);
                        self.showError('Fehler beim Starten: ' + errorMsg + '<br><small>Prüfen Sie die Browser-Konsole für Details.</small>');
                        self.stopProcessing();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', xhr.status, xhr.responseText);
                    let errorMsg = 'AJAX Fehler: ' + error;
                    if (xhr.status === 500) {
                        errorMsg += '<br><strong>Server-Fehler (500)</strong><br>Prüfen Sie die PHP Error-Logs.';
                    } else if (xhr.status === 403) {
                        errorMsg += '<br><strong>Zugriff verweigert (403)</strong><br>Keine Berechtigung.';
                    } else if (xhr.status === 0) {
                        errorMsg += '<br><strong>Verbindung fehlgeschlagen</strong><br>Timeout oder Netzwerkproblem.';
                    }
                    if (xhr.responseText) {
                        errorMsg += '<br><small>Response: ' + xhr.responseText.substring(0, 200) + '</small>';
                    }
                    self.showError(errorMsg);
                    self.stopProcessing();
                }
            });
        },
        
        processNextBatch: function() {
            const self = this;
            
            if (!this.batchId) {
                return;
            }
            
            $.ajax({
                url: 'index.php',
                type: 'POST',
                dataType: 'json',
                traditional: true,
                timeout: 60000, // 60 Sekunden Timeout
                data: {
                    'rex-api-call': 'filepond_bulk_process',
                    action: 'process',
                    batchId: this.batchId
                },
                success: function(response) {
                    console.log('Process response:', response);
                    if (response.success && response.data) {
                        if (response.data.finished) {
                            self.finishProcessing(response.data.status);
                        } else {
                            // Weiter verarbeiten
                            setTimeout(function() {
                                self.processNextBatch();
                            }, 100);
                        }
                    } else {
                        const errorMsg = response.error || (response.data && response.data.error) || 'Unbekannter Fehler';
                        console.error('Process error:', errorMsg, response);
                        self.showError('Fehler bei Verarbeitung: ' + errorMsg);
                        self.stopProcessing();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Process AJAX error:', xhr.status, status, error);
                    let errorMsg = 'AJAX Fehler: ' + error;
                    if (status === 'timeout') {
                        errorMsg = 'Timeout nach 60 Sekunden.<br>Bildverarbeitung dauert zu lange.<br>Versuchen Sie kleinere Batch-Größen oder wenden Sie sich an Ihren Hoster.';
                    } else if (xhr.status === 500) {
                        errorMsg += '<br>Server-Fehler. Prüfen Sie die PHP Error-Logs.';
                    }
                    self.showError(errorMsg);
                    self.stopProcessing();
                }
            });
        },
        
        startPolling: function() {
            const self = this;
            
            this.pollInterval = setInterval(function() {
                self.pollStatus();
            }, 500);
        },
        
        pollStatus: function() {
            const self = this;
            
            if (!this.batchId) {
                return;
            }
            
            $.ajax({
                url: 'index.php',
                type: 'POST',
                dataType: 'json',
                traditional: true,
                data: {
                    'rex-api-call': 'filepond_bulk_process',
                    action: 'status',
                    batchId: this.batchId
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.updateFromStatus(response.data);
                        self.updateModal();
                    }
                }
            });
        },
        
        updateFromStatus: function(status) {
            this.processedFiles = status.processed || 0;
            this.successfulFiles = status.successful || 0;
            this.errorFiles = (status.errors || []).length;
            this.skippedFiles = (status.skipped || []).length;
            this.savedBytes = status.savedBytes || 0;
        },
        
        showModal: function() {
            const modalHtml = `
                <div class="modal fade" id="bulk-resize-modal" tabindex="-1" role="dialog" data-backdrop="static">
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="display:none;">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                                <h4 class="modal-title">
                                    <i class="rex-icon fa-spinner fa-spin"></i>
                                    Bulk Resize - Verarbeitung läuft
                                </h4>
                            </div>
                            <div class="modal-body">
                                <div class="progress" style="margin-bottom: 20px;">
                                    <div class="progress-bar progress-bar-striped active" role="progressbar" 
                                         style="width: 0%">
                                        <span class="progress-text">0%</span>
                                    </div>
                                </div>
                                
                                <div class="row bulk-resize-stats">
                                    <div class="col-sm-3">
                                        <div class="panel panel-default">
                                            <div class="panel-body text-center">
                                                <div class="stat-value" id="stat-processed">0</div>
                                                <div class="stat-label">Verarbeitet</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-3">
                                        <div class="panel panel-success">
                                            <div class="panel-body text-center">
                                                <div class="stat-value" id="stat-successful">0</div>
                                                <div class="stat-label">Erfolgreich</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-3">
                                        <div class="panel panel-warning">
                                            <div class="panel-body text-center">
                                                <div class="stat-value" id="stat-skipped">0</div>
                                                <div class="stat-label">Übersprungen</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-3">
                                        <div class="panel panel-danger">
                                            <div class="panel-body text-center">
                                                <div class="stat-value" id="stat-errors">0</div>
                                                <div class="stat-label">Fehler</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info" id="saved-space-info" style="display:none;">
                                    <strong>Eingesparter Speicherplatz:</strong> <span id="saved-bytes">0 KB</span>
                                </div>
                                
                                <div id="bulk-resize-log" style="max-height: 200px; overflow-y: auto; display: none;">
                                    <h5>Protokoll</h5>
                                    <ul class="list-unstyled" id="log-entries"></ul>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-dismiss="modal" disabled id="modal-close-btn">
                                    Schließen
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            if ($('#bulk-resize-modal').length) {
                $('#bulk-resize-modal').remove();
            }
            
            $('body').append(modalHtml);
            $('#bulk-resize-modal').modal('show');
        },
        
        updateModal: function() {
            const percent = this.totalFiles > 0 ? Math.round((this.processedFiles / this.totalFiles) * 100) : 0;
            
            $('.progress-bar').css('width', percent + '%');
            $('.progress-text').text(percent + '%');
            
            $('#stat-processed').text(this.processedFiles + ' / ' + this.totalFiles);
            $('#stat-successful').text(this.successfulFiles);
            $('#stat-skipped').text(this.skippedFiles);
            $('#stat-errors').text(this.errorFiles);
            
            if (this.savedBytes > 0) {
                const savedMB = (this.savedBytes / 1024 / 1024).toFixed(2);
                $('#saved-bytes').text(savedMB + ' MB');
                $('#saved-space-info').show();
            }
        },
        
        finishProcessing: function(finalStatus) {
            this.stopPolling();
            this.isProcessing = false;
            
            this.updateFromStatus(finalStatus);
            this.updateModal();
            
            // Update Modal Title
            $('.modal-title').html('<i class="rex-icon fa-check"></i> Bulk Resize - Abgeschlossen');
            
            // Enable Close Button
            $('#modal-close-btn').prop('disabled', false);
            
            // Stop Progress Bar Animation
            $('.progress-bar').removeClass('active');
            
            // Reload page after close
            $('#bulk-resize-modal').on('hidden.bs.modal', function() {
                location.reload();
            });
        },
        
        stopProcessing: function() {
            this.stopPolling();
            this.isProcessing = false;
            $('#modal-close-btn').prop('disabled', false);
        },
        
        stopPolling: function() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
        },
        
        showError: function(message) {
            const errorHtml = '<div class="alert alert-danger"><strong>Fehler:</strong> ' + message + '</div>';
            $('#bulk-resize-modal .modal-body').prepend(errorHtml);
            $('.modal-title').html('<i class="rex-icon fa-exclamation-triangle"></i> Bulk Resize - Fehler');
        }
    };
    
    // Init on DOM ready
    $(document).ready(function() {
        if ($('.filepond-bulk-resize-table').length) {
            BulkResize.init();
        }
        
        // Tooltip aktivieren
        $('[data-toggle="tooltip"]').tooltip({
            html: true,
            container: 'body'
        });
    });
    
})(jQuery);
