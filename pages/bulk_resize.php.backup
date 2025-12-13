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
// Wenn max_height nicht explizit gesetzt wurde, verwende max_width (gleiches Feld im UI)
$filterMaxHeight = rex_request('max_height', 'int', $filterMaxWidth);
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

// Pagination
$itemsPerPage = (int) $addon->getConfig('items_per_page', 30);
if ($itemsPerPage < 1) $itemsPerPage = 30;

$filters = [
    'filename' => $filterFilename,
    'category_id' => $filterCategoryId,
    'max_width' => $filterMaxWidth,
    'max_height' => $filterMaxHeight
];

$totalCount = filepond_bulk_resize::countOversizedImages($filters);

// Pager initialisieren
$pager = new rex_pager($itemsPerPage, 'start');
$pager->setRowCount($totalCount);
$offset = $pager->getCursor();

$images = filepond_bulk_resize::findOversizedImages($filters, $itemsPerPage, $offset);


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
    <form action="<?= rex_url::currentBackendPage() ?>" method="get" class="form-horizontal">
        <input type="hidden" name="page" value="filepond_uploader/bulk_resize">
        
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
                                    <option value="-1" <?= $filterCategoryId == -1 ? 'selected' : '' ?>><?= $addon->i18n('bulk_resize_all_categories') ?></option>
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
                        <a href="<?= rex_url::currentBackendPage() ?>" class="btn btn-default">
                            <i class="fa fa-times"></i> <?= $addon->i18n('bulk_resize_reset') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Ergebnistabelle -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <div class="panel-title d-flex justify-content-between align-items-center">
                <span>
                    <i class="fa fa-images fa-fw"></i> 
                    <?= $addon->i18n('bulk_resize_images') ?>
                    <span id="image-count" class="badge"><?= $totalCount ?></span>
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
        
        <?php if (count($images) > 0): ?>
        <div class="panel-body" id="images-container" style="padding: 0;">
            <table class="table table-striped table-hover" id="images-table" style="margin-bottom: 0;">
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
                    <?php foreach ($images as $img): 
                        $categoryName = $img['category_id'] == 0 
                            ? rex_i18n::msg('pool_kats_no') 
                            : ($categories[$img['category_id']] ?? '');
                        
                        $isOversized = ($filterMaxWidth > 0 && $img['width'] > $filterMaxWidth) || 
                                       ($filterMaxHeight > 0 && $img['height'] > $filterMaxHeight);
                    ?>
                    <tr data-filename="<?= rex_escape($img['filename']) ?>">
                        <td>
                            <input type="checkbox" class="image-checkbox" 
                                   data-filename="<?= rex_escape($img['filename']) ?>">
                        </td>
                        <td>
                            <img src="index.php?rex_media_type=rex_media_small&rex_media_file=<?= urlencode($img['filename']) ?>" 
                                 alt="" loading="lazy">
                        </td>
                        <td>
                            <strong><?= rex_escape($img['filename']) ?></strong>
                            <?php if (!empty($img['title'])): ?>
                                <br><small class="text-muted"><?= rex_escape($img['title']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $img['width'] ?> × <?= $img['height'] ?>
                            <?php if ($isOversized): ?>
                                <span class="badge badge-oversized" title="Größer als <?= $filterMaxWidth ?>px">!</span>
                            <?php endif; ?>
                        </td>
                        <td><?= rex_formatter::bytes($img['filesize'], [2]) ?></td>
                        <td><small><?= rex_escape($categoryName) ?></small></td>
                        <td class="status-cell">
                            <span class="label label-default"><?= $addon->i18n('bulk_resize_status_pending') ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="panel-footer">
            <?php
            $urlProvider = new rex_context([
                'page' => 'filepond_uploader/bulk_resize',
                'filter_filename' => $filterFilename,
                'filter_category' => $filterCategoryId,
                'max_width' => $filterMaxWidth,
                'max_height' => $filterMaxHeight,
                'quality' => $filterQuality,
                'items_per_page' => $itemsPerPage
            ]);
            $fragment = new rex_fragment();
            $fragment->setVar('urlprovider', $urlProvider);
            $fragment->setVar('pager', $pager);
            echo $fragment->parse('core/navigations/pagination.php');
            ?>
        </div>
        <?php else: ?>
        <div class="panel-body">
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> <?= $addon->i18n('bulk_resize_no_images') ?>
            </div>
        </div>
        <?php endif; ?>
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
        batchId: null,
        cancelled: false,
        selectedImages: new Set(),
        
        init() {
            this.bindEvents();
        },
        
        bindEvents() {
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
        
        selectAll() {
            $('.image-checkbox').each((i, el) => {
                this.selectedImages.add($(el).data('filename'));
            });
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
            
            // Parameter aus Formular lesen
            this.maxWidth = parseInt($('#max_width').val()) || 2100;
            this.maxHeight = this.maxWidth;
            this.quality = parseInt($('#quality').val()) || 85;
            
            $.post(this.apiEndpoint, {
                action: 'start',
                filenames: filenames,
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
            
            // Seite neu laden nach Abschluss, um aktualisierte Dateigrößen zu sehen
            if (!this.cancelled) {
                setTimeout(() => window.location.reload(), 2000);
            }
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
