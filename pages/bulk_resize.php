<?php
/**
 * Bulk Resize - Bilder im Medienpool verkleinern
 * 
 * @package filepond_uploader
 */

// Nur für Admins oder Nutzer mit bulk_resize Berechtigung
if (!rex::getUser() || (!rex::getUser()->isAdmin() && !rex::getUser()->hasPerm('filepond_uploader[bulk_resize]'))) {
    echo rex_view::error(rex_i18n::msg('no_perm'));
    return;
}

$addon = rex_addon::get('filepond_uploader');

// Konfiguration
$defaultMaxWidth = rex_config::get('filepond_uploader', 'max_pixel', 2100);
$defaultMaxHeight = rex_config::get('filepond_uploader', 'max_pixel', 2100);
$defaultQuality = rex_config::get('filepond_uploader', 'image_quality', 85);

// Filter aus Request
$filterFilename = rex_request('filter_filename', 'string', '');
$filterCategoryId = rex_request('filter_category', 'int', -1);
$filterMaxWidth = rex_request('max_width', 'int', $defaultMaxWidth);
$filterMaxHeight = rex_request('max_height', 'int', $defaultMaxHeight);
$filterQuality = rex_request('quality', 'int', $defaultQuality);

// API Endpoint
$apiEndpoint = rex_url::backendController([
    'rex-api-call' => 'filepond_bulk_resize',
    '_csrf_token' => rex_csrf_token::factory('filepond_bulk_resize')->getValue()
]);

// System-Info
$hasGD = filepond_bulk_resize::hasGD();
$hasImageMagick = filepond_bulk_resize::hasImageMagick();

// Kategorien laden
$categories = [];
$sqlCats = rex_sql::factory();
$sqlCats->setQuery('SELECT id, name FROM ' . rex::getTable('media_category') . ' ORDER BY name');
foreach ($sqlCats as $cat) {
    $categories[$cat->getValue('id')] = $cat->getValue('name');
}

?>

<div id="bulk-resize-app">
    <!-- Header mit Info -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <div class="panel-title">
                <i class="fa fa-compress fa-fw"></i> 
                <?= $addon->i18n('bulk_resize_title') ?>
            </div>
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-8">
                    <p><?= $addon->i18n('bulk_resize_description') ?></p>
                </div>
                <div class="col-md-4 text-right">
                    <span class="label <?= $hasGD ? 'label-success' : 'label-danger' ?>">
                        GD: <?= $hasGD ? 'OK' : 'Fehlt' ?>
                    </span>
                    <span class="label <?= $hasImageMagick ? 'label-success' : 'label-warning' ?>">
                        ImageMagick: <?= $hasImageMagick ? 'OK' : 'Optional' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Einstellungen -->
    <form id="bulk-resize-filter-form" class="form-horizontal">
        <div class="panel panel-default">
            <div class="panel-heading">
                <div class="panel-title">
                    <i class="fa fa-filter fa-fw"></i> 
                    <?= $addon->i18n('bulk_resize_filter') ?>
                </div>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="filter_filename" class="col-sm-4 control-label"><?= $addon->i18n('bulk_resize_filename') ?></label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="filter_filename" name="filter_filename" 
                                       value="<?= rex_escape($filterFilename) ?>" placeholder="z.B. foto_">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="filter_category" class="col-sm-4 control-label"><?= $addon->i18n('bulk_resize_category') ?></label>
                            <div class="col-sm-8">
                                <select class="form-control selectpicker" id="filter_category" name="filter_category" data-live-search="true">
                                    <option value="-1"><?= $addon->i18n('bulk_resize_all_categories') ?></option>
                                    <option value="0" <?= $filterCategoryId === 0 ? 'selected' : '' ?>><?= rex_i18n::msg('pool_kats_no') ?></option>
                                    <?php foreach ($categories as $catId => $catName): ?>
                                        <option value="<?= $catId ?>" <?= $filterCategoryId === $catId ? 'selected' : '' ?>><?= rex_escape($catName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="max_width" class="col-sm-4 control-label"><?= $addon->i18n('bulk_resize_max_size') ?></label>
                            <div class="col-sm-8">
                                <div class="input-group">
                                    <input type="number" class="form-control" id="max_width" name="max_width" 
                                           value="<?= $filterMaxWidth ?>" min="100" max="10000" step="100">
                                    <span class="input-group-addon">px</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="quality" class="col-sm-4 control-label"><?= $addon->i18n('bulk_resize_quality') ?></label>
                            <div class="col-sm-8">
                                <div class="input-group">
                                    <input type="number" class="form-control" id="quality" name="quality" 
                                           value="<?= $filterQuality ?>" min="10" max="100" step="5">
                                    <span class="input-group-addon">%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8 text-right" style="padding-top: 5px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-search"></i> <?= $addon->i18n('bulk_resize_search') ?>
                        </button>
                        <button type="button" class="btn btn-default" id="btn-reset-filter">
                            <i class="fa fa-times"></i> <?= $addon->i18n('bulk_resize_reset') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <div style="margin-bottom: 10px;">
        <div class="pull-right">
            <label for="br_per_page" class="sr-only"><?= $addon->i18n('bulk_resize_per_page') ?></label>
            <select id="br_per_page" class="form-control input-sm" style="width: 90px; display: inline-block;">
                <option value="10">10 <?= $addon->i18n('alt_checker_per_page_suffix') ?></option>
                <option value="25">25 <?= $addon->i18n('alt_checker_per_page_suffix') ?></option>
                <option value="50" selected>50 <?= $addon->i18n('alt_checker_per_page_suffix') ?></option>
                <option value="100">100 <?= $addon->i18n('alt_checker_per_page_suffix') ?></option>
            </select>
            <div class="btn-group" style="margin-left: 10px;">
                <button type="button" class="btn btn-default btn-sm" id="br_page_prev"><i class="fa fa-chevron-left"></i></button>
                <span class="btn btn-default btn-sm" id="br_page_info">1 / 1</span>
                <button type="button" class="btn btn-default btn-sm" id="br_page_next"><i class="fa fa-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <!-- Ergebnistabelle -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <div class="panel-title d-flex justify-content-between align-items-center">
                <span>
                    <i class="fa fa-images fa-fw"></i> 
                    <?= $addon->i18n('bulk_resize_images') ?>
                    <span id="image-count" class="badge">0</span>
                </span>
                <div class="pull-right">
                    <button type="button" class="btn btn-success btn-sm" id="btn-start-resize" disabled>
                        <i class="fa fa-play"></i> <?= $addon->i18n('bulk_resize_start') ?>
                    </button>
                    <button type="button" class="btn btn-default btn-sm" id="btn-select-all">
                        <i class="fa fa-check-square-o"></i> <?= $addon->i18n('bulk_resize_select_all') ?>
                    </button>
                    <button type="button" class="btn btn-default btn-sm" id="btn-deselect-all">
                        <i class="fa fa-square-o"></i> <?= $addon->i18n('bulk_resize_deselect_all') ?>
                    </button>
                </div>
            </div>
        </div>
        <div class="panel-body" id="images-container">
            <div class="text-center text-muted" id="loading-indicator" style="padding: 50px;">
                <i class="fa fa-spinner fa-spin fa-3x"></i>
                <p style="margin-top: 15px;"><?= $addon->i18n('bulk_resize_loading') ?></p>
            </div>
            <table class="table table-striped table-hover" id="images-table" style="display:none;">
                <thead>
                    <tr>
                        <th width="40"><input type="checkbox" id="select-all-checkbox"></th>
                        <th width="80"><?= $addon->i18n('bulk_resize_preview') ?></th>
                        <th><?= $addon->i18n('bulk_resize_filename') ?></th>
                        <th width="120"><?= $addon->i18n('bulk_resize_dimensions') ?></th>
                        <th width="100"><?= $addon->i18n('bulk_resize_filesize') ?></th>
                        <th width="120"><?= $addon->i18n('bulk_resize_category') ?></th>
                        <th width="100"><?= $addon->i18n('bulk_resize_status') ?></th>
                    </tr>
                </thead>
                <tbody id="images-tbody">
                </tbody>
            </table>
            <div id="no-images-message" class="alert alert-info" style="display:none;">
                <i class="fa fa-info-circle"></i> <?= $addon->i18n('bulk_resize_no_images') ?>
            </div>
        </div>
    </div>
</div>

<!-- Progress Modal -->
<div class="modal fade" id="resize-progress-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">
                    <i class="fa fa-compress"></i> <?= $addon->i18n('bulk_resize_processing') ?>
                </h4>
            </div>
            <div class="modal-body">
                <!-- Progress -->
                <div class="progress" style="height: 30px; margin-bottom: 20px;">
                    <div id="progress-bar" class="progress-bar progress-bar-striped active" role="progressbar" 
                         style="width: 0%; line-height: 30px; font-size: 14px;">
                        0%
                    </div>
                </div>
                
                <!-- Stats -->
                <div class="row" id="progress-stats">
                    <div class="col-md-3 text-center">
                        <div class="stat-box">
                            <div class="stat-value" id="stat-processed">0</div>
                            <div class="stat-label"><?= $addon->i18n('bulk_resize_stat_processed') ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="stat-box">
                            <div class="stat-value text-success" id="stat-success">0</div>
                            <div class="stat-label"><?= $addon->i18n('bulk_resize_stat_success') ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="stat-box">
                            <div class="stat-value text-warning" id="stat-skipped">0</div>
                            <div class="stat-label"><?= $addon->i18n('bulk_resize_stat_skipped') ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="stat-box">
                            <div class="stat-value text-primary" id="stat-saved">0 KB</div>
                            <div class="stat-label"><?= $addon->i18n('bulk_resize_stat_saved') ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Currently processing -->
                <div id="current-processing" style="margin-top: 20px;">
                    <h5><i class="fa fa-cog fa-spin"></i> <?= $addon->i18n('bulk_resize_current') ?></h5>
                    <ul id="current-files-list" class="list-unstyled" style="margin-left: 20px;"></ul>
                </div>
                
                <!-- Log -->
                <div id="process-log" style="margin-top: 20px;">
                    <h5><i class="fa fa-list"></i> <?= $addon->i18n('bulk_resize_log') ?></h5>
                    <div id="log-container"></div>
                </div>
            </div>
            <div class="modal-footer" id="modal-footer-processing">
                <span id="time-remaining" class="pull-left text-muted"></span>
                <button type="button" class="btn btn-warning" id="btn-cancel-resize">
                    <i class="fa fa-stop"></i> <?= $addon->i18n('bulk_resize_cancel') ?>
                </button>
            </div>
            <div class="modal-footer" id="modal-footer-complete" style="display:none;">
                <button type="button" class="btn btn-success" data-dismiss="modal">
                    <i class="fa fa-check"></i> <?= $addon->i18n('bulk_resize_done') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.stat-box {
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 10px;
    border: 1px solid #ddd;
}
.stat-value {
    font-size: 28px;
    font-weight: bold;
    line-height: 1.2;
}
.stat-label {
    font-size: 12px;
    text-transform: uppercase;
    opacity: 0.7;
    margin-top: 5px;
}
#images-table img {
    max-width: 60px;
    max-height: 40px;
    border-radius: 4px;
}
#images-table tr.processing {
    opacity: 0.7;
}
.log-entry {
    padding: 2px 0;
    border-bottom: 1px solid rgba(0,0,0,0.1);
}
.log-entry:last-child {
    border-bottom: none;
}
.log-entry .timestamp {
    opacity: 0.6;
    margin-right: 8px;
}
#log-container {
    max-height: 200px;
    overflow-y: auto;
    padding: 10px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 12px;
    border: 1px solid #ddd;
}
</style>

<script>
$(document).on('rex:ready', function() {
    const BulkResizer = {
        apiEndpoint: <?= json_encode($apiEndpoint) ?>,
        maxWidth: <?= $filterMaxWidth ?>,
        maxHeight: <?= $filterMaxHeight ?>,
        quality: <?= $filterQuality ?>,
        categories: <?= json_encode($categories) ?>,
        batchId: null,
        cancelled: false,
        images: [],
        selectedImages: new Set(),
        page: 1,
        perPage: 50,
        totalItems: 0,
        totalPages: 1,
        
        init() {
            this.bindEvents();
            const initialPerPage = parseInt($('#br_per_page').val());
            this.perPage = isNaN(initialPerPage) ? this.perPage : initialPerPage;
            this.loadImages();
        },
        
        bindEvents() {
            $('#bulk-resize-filter-form').on('submit', (e) => {
                e.preventDefault();
                this.maxWidth = parseInt($('#max_width').val()) || 2100;
                this.maxHeight = this.maxWidth;
                this.quality = parseInt($('#quality').val()) || 85;
                this.loadImages();
            });
            
            $('#btn-reset-filter').on('click', () => {
                $('#filter_filename').val('');
                $('#filter_category').val(-1).selectpicker('refresh');
                $('#max_width').val(<?= $defaultMaxWidth ?>);
                $('#quality').val(<?= $defaultQuality ?>);
                this.maxWidth = <?= $defaultMaxWidth ?>;
                this.maxHeight = <?= $defaultMaxWidth ?>;
                this.quality = <?= $defaultQuality ?>;
                this.loadImages();
            });

            $('#br_per_page').on('change', (e) => {
                this.perPage = parseInt($(e.target).val()) || 50;
                this.page = 1;
                this.loadImages();
            });
            $('#br_page_prev').on('click', () => {
                if (this.page > 1) {
                    this.page--;
                    this.loadImages();
                }
            });
            $('#br_page_next').on('click', () => {
                if (this.page < this.totalPages) {
                    this.page++;
                    this.loadImages();
                }
            });
            
            $('#btn-select-all').on('click', () => this.selectAll());
            $('#btn-deselect-all').on('click', () => this.deselectAll());
            $('#select-all-checkbox').on('change', (e) => {
                if (e.target.checked) {
                    this.selectAll();
                } else {
                    this.deselectAll();
                }
            });
            
            $('#btn-start-resize').on('click', () => this.startResize());
            $('#btn-cancel-resize').on('click', () => this.cancelResize());
            
            $(document).on('change', '.image-checkbox', (e) => {
                const filename = $(e.target).data('filename');
                if (e.target.checked) {
                    this.selectedImages.add(filename);
                } else {
                    this.selectedImages.delete(filename);
                }
                this.updateStartButton();
            });
        },
        
        loadImages() {
            const $container = $('#images-container');
            const $table = $('#images-table');
            const $tbody = $('#images-tbody');
            const $loading = $('#loading-indicator');
            const $noImages = $('#no-images-message');
            
            $loading.show();
            $table.hide();
            $noImages.hide();
            
            const params = {
                action: 'list',
                max_width: this.maxWidth,
                max_height: this.maxHeight,
                filter_filename: $('#filter_filename').val(),
                filter_category: $('#filter_category').val()
                ,page: this.page,
                per_page: this.perPage
            };
            
            $.getJSON(this.apiEndpoint + '&' + $.param(params))
                .done((response) => {
                    $loading.hide();
                    
                    if (response.error) {
                        $noImages.text(response.error).show();
                        return;
                    }
                    
                    this.images = response.images || [];
                    this.totalItems = response.total || this.images.length;
                    this.totalPages = Math.max(1, Math.ceil(this.totalItems / this.perPage));
                    if (this.page > this.totalPages) {
                        this.page = this.totalPages;
                    }
                    $('#image-count').text(this.images.length);
                    
                    if (this.images.length === 0) {
                        $noImages.show();
                        return;
                    }
                    
                    $tbody.empty();
                    this.images.forEach(img => {
                        const categoryName = img.category_id == 0 
                            ? '<?= rex_i18n::msg('pool_kats_no') ?>' 
                            : (this.categories[img.category_id] || '');
                        
                        const isOversized = (this.maxWidth > 0 && img.width > this.maxWidth) || 
                                           (this.maxHeight > 0 && img.height > this.maxHeight);
                        
                        $tbody.append(`
                            <tr data-filename="${this.escapeHtml(img.filename)}">
                                <td>
                                    <input type="checkbox" class="image-checkbox" 
                                           data-filename="${this.escapeHtml(img.filename)}"
                                           ${this.selectedImages.has(img.filename) ? 'checked' : ''}>
                                </td>
                                <td>
                                    <img src="index.php?rex_media_type=rex_media_small&rex_media_file=${encodeURIComponent(img.filename)}" 
                                         alt="" loading="lazy">
                                </td>
                                <td>
                                    <strong>${this.escapeHtml(img.filename)}</strong>
                                    ${img.title ? '<br><small class="text-muted">' + this.escapeHtml(img.title) + '</small>' : ''}
                                </td>
                                <td>
                                    ${img.width} × ${img.height}
                                    ${isOversized ? '<span class="badge badge-oversized" title="Größer als ' + this.maxWidth + 'px">!</span>' : ''}
                                </td>
                                <td>${this.formatBytes(img.filesize)}</td>
                                <td><small>${this.escapeHtml(categoryName)}</small></td>
                                <td class="status-cell">
                                    <span class="label label-default"><?= $addon->i18n('bulk_resize_status_pending') ?></span>
                                </td>
                            </tr>
                        `);
                    });
                    
                    $table.show();
                    this.updateStartButton();
                    this.updatePaginationControls();
                })
                .fail((xhr, status, error) => {
                    $loading.hide();
                    $noImages.text('Fehler beim Laden: ' + error).removeClass('alert-info').addClass('alert-danger').show();
                });
        },
        
        selectAll() {
            this.images.forEach(img => this.selectedImages.add(img.filename));
            $('.image-checkbox').prop('checked', true);
            $('#select-all-checkbox').prop('checked', true);
            this.updateStartButton();
        },
        
        deselectAll() {
            this.selectedImages.clear();
            $('.image-checkbox').prop('checked', false);
            $('#select-all-checkbox').prop('checked', false);
            this.updateStartButton();
        },
        
        updateStartButton() {
            const count = this.selectedImages.size;
            const $btn = $('#btn-start-resize');
            $btn.prop('disabled', count === 0);
            $btn.find('i').attr('class', count > 0 ? 'fa fa-play' : 'fa fa-ban');
            if (count > 0) {
                $btn.text(' ' + count + ' <?= $addon->i18n('bulk_resize_process_count') ?>');
                $btn.prepend('<i class="fa fa-play"></i>');
            }
        },

        updatePaginationControls() {
            $('#br_page_info').text(this.page + ' / ' + this.totalPages);
            $('#br_page_prev').prop('disabled', this.page <= 1);
            $('#br_page_next').prop('disabled', this.page >= this.totalPages);
        },
        
        startResize() {
            if (this.selectedImages.size === 0) return;
            
            this.cancelled = false;
            this.batchId = null;
            
            // Modal öffnen
            $('#resize-progress-modal').modal('show');
            $('#modal-footer-processing').show();
            $('#modal-footer-complete').hide();
            $('#progress-bar').css('width', '0%').text('0%').addClass('active');
            $('#stat-processed, #stat-success, #stat-skipped').text('0');
            $('#stat-saved').text('0 KB');
            $('#current-files-list').empty();
            $('#log-container').empty();
            $('#time-remaining').text('');
            
            // Batch starten
            const filenames = Array.from(this.selectedImages);
            
            $.post(this.apiEndpoint, {
                action: 'start',
                filenames: JSON.stringify(filenames),
                max_width: this.maxWidth,
                max_height: this.maxHeight,
                quality: this.quality
            })
            .done((response) => {
                if (response.error) {
                    this.logMessage('Fehler: ' + response.error, 'error');
                    return;
                }
                
                this.batchId = response.batch_id;
                this.logMessage('Batch gestartet: ' + filenames.length + ' Dateien', 'info');
                this.processNext();
            })
            .fail((xhr, status, error) => {
                this.logMessage('Fehler beim Starten: ' + error, 'error');
            });
        },
        
        processNext() {
            if (this.cancelled || !this.batchId) {
                this.finishResize();
                return;
            }
            
            $.post(this.apiEndpoint, {
                action: 'process',
                batch_id: this.batchId
            })
            .done((response) => {
                if (response.error) {
                    this.logMessage('Fehler: ' + response.error, 'error');
                    this.finishResize();
                    return;
                }
                
                this.updateProgress(response.batch);
                
                // Neue Ergebnisse loggen
                if (response.batch && response.batch.results) {
                    response.batch.results.slice(-3).forEach(r => {
                        if (r.success) {
                            this.logMessage(`✓ ${r.filename}: ${this.formatBytes(r.savedBytes)} gespart`, 'success');
                            this.updateRowStatus(r.filename, 'completed');
                        }
                    });
                }
                
                if (response.batch && response.batch.errors) {
                    Object.entries(response.batch.errors).slice(-3).forEach(([file, err]) => {
                        this.logMessage(`✗ ${file}: ${err}`, 'error');
                        this.updateRowStatus(file, 'error');
                    });
                }
                
                if (response.batch && response.batch.skipped) {
                    Object.entries(response.batch.skipped).slice(-3).forEach(([file, reason]) => {
                        this.logMessage(`○ ${file}: ${reason}`, 'skipped');
                        this.updateRowStatus(file, 'skipped');
                    });
                }
                
                if (response.status === 'completed') {
                    this.finishResize();
                } else {
                    // Nächsten Batch verarbeiten
                    setTimeout(() => this.processNext(), 100);
                }
            })
            .fail((xhr, status, error) => {
                this.logMessage('Fehler beim Verarbeiten: ' + error, 'error');
                this.finishResize();
            });
        },
        
        updateProgress(batch) {
            if (!batch) return;
            
            const progress = batch.progress || 0;
            $('#progress-bar')
                .css('width', progress + '%')
                .text(Math.round(progress) + '%');
            
            $('#stat-processed').text(batch.processed || 0);
            $('#stat-success').text(batch.successful || 0);
            $('#stat-skipped').text(Object.keys(batch.skipped || {}).length);
            $('#stat-saved').text(batch.savedBytesFormatted || '0 KB');
            
            // Aktuelle Dateien
            const $currentList = $('#current-files-list');
            $currentList.empty();
            if (batch.currentlyProcessing) {
                batch.currentlyProcessing.forEach(file => {
                    $currentList.append(`<li><i class="fa fa-cog fa-spin"></i> ${this.escapeHtml(file.filename)} (${file.duration}s)</li>`);
                    this.updateRowStatus(file.filename, 'processing');
                });
            }
            
            // Verbleibende Zeit
            if (batch.remainingTime) {
                const mins = Math.floor(batch.remainingTime / 60);
                const secs = batch.remainingTime % 60;
                $('#time-remaining').text(`Ca. ${mins}:${secs.toString().padStart(2, '0')} verbleibend`);
            }
        },
        
        finishResize() {
            $('#progress-bar').removeClass('active');
            if (!this.cancelled) {
                $('#progress-bar').addClass('progress-bar-success').css('width', '100%').text('100%');
            } else {
                $('#progress-bar').addClass('progress-bar-warning');
            }
            
            $('#modal-footer-processing').hide();
            $('#modal-footer-complete').show();
            $('#current-files-list').html('<li class="text-success"><i class="fa fa-check"></i> <?= $addon->i18n('bulk_resize_finished') ?></li>');
            
            this.logMessage(this.cancelled ? 'Verarbeitung abgebrochen' : 'Verarbeitung abgeschlossen', 'info');
        },
        
        cancelResize() {
            this.cancelled = true;
            this.logMessage('Abbruch angefordert...', 'warning');
        },
        
        updateRowStatus(filename, status) {
            const $row = $(`tr[data-filename="${this.escapeHtml(filename)}"]`);
            $row.removeClass('processing completed error skipped').addClass(status);
            
            let label = '';
            switch(status) {
                case 'processing':
                    label = '<span class="label label-warning"><i class="fa fa-spinner fa-spin"></i></span>';
                    break;
                case 'completed':
                    label = '<span class="label label-success"><i class="fa fa-check"></i></span>';
                    break;
                case 'error':
                    label = '<span class="label label-danger"><i class="fa fa-times"></i></span>';
                    break;
                case 'skipped':
                    label = '<span class="label label-default"><i class="fa fa-minus"></i></span>';
                    break;
            }
            $row.find('.status-cell').html(label);
        },
        
        logMessage(message, type = 'info') {
            const time = new Date().toLocaleTimeString();
            const $log = $('#log-container');
            $log.append(`<div class="log-entry ${type}"><span class="timestamp">[${time}]</span> ${this.escapeHtml(message)}</div>`);
            $log.scrollTop($log[0].scrollHeight);
        },
        
        formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        },
        
        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };
    
    BulkResizer.init();
});
</script>
