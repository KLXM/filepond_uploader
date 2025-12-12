<?php
/**
 * Alt-Text-Checker - Bilder ohne Alt-Text finden und bearbeiten
 * 
 * @package filepond_uploader
 */

// Berechtigung prüfen
if (!rex::getUser() || (!rex::getUser()->isAdmin() && !rex::getUser()->hasPerm('filepond_uploader[alt_checker]'))) {
    echo rex_view::error(rex_i18n::msg('no_perm'));
    return;
}

$addon = rex_addon::get('filepond_uploader');

// Kategorien laden
$categories = [];
$sqlCats = rex_sql::factory();
$sqlCats->setQuery('SELECT id, name FROM ' . rex::getTable('media_category') . ' ORDER BY name');
foreach ($sqlCats as $cat) {
    $categories[$cat->getValue('id')] = $cat->getValue('name');
}

// API Endpoint
$apiEndpoint = rex_url::backendController([
    'rex-api-call' => 'filepond_alt_checker',
    '_csrf_token' => rex_csrf_token::factory('filepond_alt_checker')->getValue()
]);

// Prüfen ob med_alt Feld existiert
$altFieldExists = filepond_alt_text_checker::checkAltFieldExists();

// Mehrsprachigkeit prüfen
$isMultiLang = filepond_alt_text_checker::isMultiLangField();
$languages = [];
if ($isMultiLang) {
    foreach (rex_clang::getAll() as $clang) {
        $languages[$clang->getId()] = [
            'id' => $clang->getId(),
            'code' => $clang->getCode(),
            'name' => $clang->getName()
        ];
    }
}
$currentLangId = rex_clang::getCurrentId();

?>

<div id="alt-checker-app">
    <?php if (!$altFieldExists): ?>
    <div class="alert alert-warning">
        <h4><i class="fa fa-exclamation-triangle"></i> <?= $addon->i18n('alt_checker_field_missing_title') ?></h4>
        <p><?= $addon->i18n('alt_checker_field_missing_text') ?></p>
        <p>
            <a href="<?= rex_url::backendPage('metainfo/media') ?>" class="btn btn-warning">
                <i class="fa fa-cog"></i> <?= $addon->i18n('alt_checker_create_field') ?>
            </a>
        </p>
    </div>
    <?php else: ?>
    
    <!-- Statistik-Header -->
    <div class="panel panel-default" id="stats-panel">
        <div class="panel-heading">
            <div class="panel-title">
                <i class="fa fa-pie-chart fa-fw"></i> 
                <?= $addon->i18n('alt_checker_statistics') ?>
            </div>
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-3 text-center">
                    <div class="stat-box">
                        <div class="stat-value" id="stat-total">-</div>
                        <div class="stat-label"><?= $addon->i18n('alt_checker_stat_total') ?></div>
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="stat-box">
                        <div class="stat-value text-success" id="stat-with-alt">-</div>
                        <div class="stat-label"><?= $addon->i18n('alt_checker_stat_with_alt') ?></div>
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="stat-box">
                        <div class="stat-value text-danger" id="stat-without-alt">-</div>
                        <div class="stat-label"><?= $addon->i18n('alt_checker_stat_without_alt') ?></div>
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="stat-box">
                        <div class="progress" style="height: 30px; margin: 5px 0;">
                            <div id="progress-bar" class="progress-bar progress-bar-success" role="progressbar" style="width: 0%; line-height: 30px;">
                                0%
                            </div>
                        </div>
                        <div class="stat-label"><?= $addon->i18n('alt_checker_stat_complete') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <form id="alt-checker-filter-form" class="form-inline" style="margin-bottom: 15px;">
        <div class="form-group">
            <label for="filter_filename" class="sr-only"><?= $addon->i18n('alt_checker_filename') ?></label>
            <input type="text" class="form-control" id="filter_filename" name="filter_filename" 
                   placeholder="<?= $addon->i18n('alt_checker_filter_filename') ?>" style="width: 200px;">
        </div>
        <div class="form-group" style="margin-left: 10px;">
            <label for="filter_category" class="sr-only"><?= $addon->i18n('alt_checker_category') ?></label>
            <select class="form-control" id="filter_category" name="filter_category">
                <option value="-1"><?= $addon->i18n('alt_checker_all_categories') ?></option>
                <option value="0"><?= rex_i18n::msg('pool_kats_no') ?></option>
                <?php foreach ($categories as $catId => $catName): ?>
                    <option value="<?= $catId ?>"><?= rex_escape($catName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="margin-left: 10px;">
            <i class="fa fa-search"></i> <?= $addon->i18n('alt_checker_search') ?>
        </button>
        <button type="button" class="btn btn-default" id="btn-refresh" style="margin-left: 5px;">
            <i class="fa fa-refresh"></i>
        </button>
    </form>

    <!-- Bilder-Tabelle -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <div class="panel-title">
                <i class="fa fa-image fa-fw"></i> 
                <?= $addon->i18n('alt_checker_images_without_alt') ?>
                <span id="image-count" class="badge">0</span>
                
                <div class="pull-right">
                    <button type="button" class="btn btn-success btn-xs" id="btn-save-all" disabled>
                        <i class="fa fa-save"></i> <?= $addon->i18n('alt_checker_save_all') ?>
                    </button>
                </div>
            </div>
        </div>
        <div class="panel-body" id="images-container">
            <div class="text-center text-muted" id="loading-indicator" style="padding: 50px;">
                <i class="fa fa-spinner fa-spin fa-3x"></i>
                <p style="margin-top: 15px;"><?= $addon->i18n('alt_checker_loading') ?></p>
            </div>
            
            <table class="table table-hover" id="images-table" style="display:none;">
                <thead>
                    <tr>
                        <th width="50"></th>
                        <th width="200"><?= $addon->i18n('alt_checker_filename') ?></th>
                        <th><?= $addon->i18n('alt_checker_alt_text') ?></th>
                        <th width="100"><?= $addon->i18n('alt_checker_category') ?></th>
                        <th width="120"><?= $addon->i18n('alt_checker_actions') ?></th>
                    </tr>
                </thead>
                <tbody id="images-tbody">
                </tbody>
            </table>
            
            <div id="no-images-message" class="alert alert-success" style="display:none;">
                <i class="fa fa-check-circle"></i> <?= $addon->i18n('alt_checker_all_complete') ?>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<style>
.stat-box {
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 10px;
}
.stat-value {
    font-size: 24px;
    font-weight: bold;
    line-height: 1.2;
}
.stat-label {
    font-size: 11px;
    text-transform: uppercase;
    opacity: 0.7;
    margin-top: 5px;
}
#images-table .thumb-mini {
    max-width: 40px;
    max-height: 30px;
    border-radius: 3px;
}
.preview-toggle {
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}
.preview-toggle i {
    transition: transform 0.2s;
}
.preview-toggle.open i {
    transform: rotate(90deg);
}
.preview-row {
    display: none;
}
.preview-row.open {
    display: table-row;
}
.preview-row td {
    padding: 15px 20px !important;
    background: rgba(0,0,0,0.02);
}
.preview-container {
    text-align: center;
}
.preview-container img {
    max-width: 100%;
    max-height: 400px;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
#images-table .alt-input {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    transition: border-color 0.2s;
}
#images-table .alt-input:focus {
    border-color: #3bb594;
    outline: none;
    box-shadow: none;
}
#images-table .alt-input.modified {
    border-color: #f0ad4e;
    
}
#images-table .alt-input.saved {
    border-color: #3bb594;
   
}
#images-table tr.saving {
    opacity: 0.6;
}
.btn-save-row {
    opacity: 0;
    transition: opacity 0.2s;
}
tr:hover .btn-save-row,
.btn-save-row.visible {
    opacity: 1;
}
.btn-ignore {
    margin-left: 3px;
}
#images-table tr.ignored {
    opacity: 0.5;
}
#images-table .alt-input.decorative {
    background: repeating-linear-gradient(
        45deg,
        transparent,
        transparent 5px,
        rgba(0,0,0,0.03) 5px,
        rgba(0,0,0,0.03) 10px
    );
    color: #999;
    font-style: italic;
}
/* Mehrsprachige Alt-Text Felder */
.alt-input-group {
    display: flex;
    align-items: center;
    gap: 6px;
}
.alt-input-group .alt-input {
    flex: 1;
}
.alt-input-group .lang-badge {
    font-size: 10px;
    font-weight: 600;
    padding: 4px 6px;
    border-radius: 3px;
    background: rgba(128,128,128,0.15);
    color: inherit;
    min-width: 28px;
    text-align: center;
}
.alt-input-group .lang-toggle {
    padding: 6px 8px;
    border-radius: 4px;
    opacity: 0.6;
    transition: opacity 0.2s, background 0.2s;
}
.alt-input-group .lang-toggle:hover {
    opacity: 1;
}
.alt-input-group .lang-toggle.active {
    opacity: 1;
    background-color: #337ab7;
    color: #fff;
}
.lang-row {
    display: none;
}
.lang-row.open {
    display: table-row;
}
.lang-row td {
    padding-top: 0 !important;
    border-top: none !important;
    background: rgba(0,0,0,0.02);
}
.other-langs-container {
    padding: 8px 0 5px 0;
}
.other-langs-container .input-group {
    margin-bottom: 6px;
}
.other-langs-container .input-group:last-child {
    margin-bottom: 0;
}
.other-langs-container .input-group-addon {
    min-width: 32px;
    font-size: 10px;
    font-weight: 600;
}
</style>

<script>
$(document).on('rex:ready', function() {
    const AltChecker = {
        apiEndpoint: <?= json_encode($apiEndpoint) ?>,
        categories: <?= json_encode($categories) ?>,
        isMultiLang: <?= json_encode($isMultiLang) ?>,
        languages: <?= json_encode($languages) ?>,
        currentLangId: <?= json_encode($currentLangId) ?>,
        images: [],
        modifiedImages: new Set(),
        
        init() {
            this.bindEvents();
            this.loadImages();
        },
        
        bindEvents() {
            $('#alt-checker-filter-form').on('submit', (e) => {
                e.preventDefault();
                this.loadImages();
            });
            
            $('#btn-refresh').on('click', () => this.loadImages());
            
            $('#btn-save-all').on('click', () => this.saveAll());
            
            // Inline-Edit Events
            $(document).on('input', '.alt-input', (e) => {
                const $input = $(e.target);
                const filename = $input.data('filename');
                $input.addClass('modified').removeClass('saved');
                $input.closest('tr.image-row').find('.btn-save-row').addClass('visible');
                this.modifiedImages.add(filename);
                this.updateSaveAllButton();
            });
            
            $(document).on('keydown', '.alt-input', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const filename = $(e.target).data('filename');
                    this.saveOne(filename);
                }
                // Tab to next input
                if (e.key === 'Tab' && !e.shiftKey) {
                    const $inputs = $('.alt-input');
                    const currentIndex = $inputs.index(e.target);
                    if (currentIndex < $inputs.length - 1) {
                        e.preventDefault();
                        $inputs.eq(currentIndex + 1).focus();
                    }
                }
            });
            
            $(document).on('click', '.btn-save-row', (e) => {
                const filename = $(e.currentTarget).data('filename');
                this.saveOne(filename);
            });
            
            // Akkordeon-Vorschau togglen
            $(document).on('click', '.preview-toggle', (e) => {
                const $toggle = $(e.currentTarget);
                const filename = $toggle.data('filename');
                const $previewRow = $(`.preview-row[data-filename="${this.escapeHtml(filename)}"]`);
                
                $toggle.toggleClass('open');
                $previewRow.toggleClass('open');
            });
            
            // Sprachen-Toggle (Globus-Button)
            $(document).on('click', '.lang-toggle', (e) => {
                e.stopPropagation();
                const $toggle = $(e.currentTarget);
                const filename = $toggle.data('filename');
                const $langRow = $(`.lang-row[data-filename="${this.escapeHtml(filename)}"]`);
                
                $toggle.toggleClass('active');
                $langRow.toggleClass('open');
            });
            
            // Ignorieren (dekoratives Bild)
            $(document).on('click', '.btn-ignore', (e) => {
                const filename = $(e.currentTarget).data('filename');
                this.ignoreImage(filename);
            });
        },
        
        loadImages() {
            const $loading = $('#loading-indicator');
            const $table = $('#images-table');
            const $noImages = $('#no-images-message');
            
            $loading.show();
            $table.hide();
            $noImages.hide();
            
            const params = {
                action: 'list',
                filter_filename: $('#filter_filename').val(),
                filter_category: $('#filter_category').val()
            };
            
            $.getJSON(this.apiEndpoint + '&' + $.param(params))
                .done((response) => {
                    $loading.hide();
                    
                    if (response.error) {
                        if (response.field_missing) {
                            // Wird durch PHP-Template gehandelt
                        } else {
                            alert(response.error);
                        }
                        return;
                    }
                    
                    // Statistiken aktualisieren
                    if (response.stats) {
                        this.updateStats(response.stats);
                    }
                    
                    this.images = response.images || [];
                    this.modifiedImages.clear();
                    $('#image-count').text(this.images.length);
                    this.updateSaveAllButton();
                    
                    if (this.images.length === 0) {
                        $noImages.show();
                        return;
                    }
                    
                    this.renderTable();
                    $table.show();
                })
                .fail((xhr, status, error) => {
                    $loading.hide();
                    alert('Fehler: ' + error);
                });
        },
        
        updateStats(stats) {
            $('#stat-total').text(stats.total);
            $('#stat-with-alt').text(stats.with_alt);
            $('#stat-without-alt').text(stats.without_alt);
            $('#progress-bar')
                .css('width', stats.percent_complete + '%')
                .text(stats.percent_complete + '%');
            
            // Farbe des Progress-Bars
            const $bar = $('#progress-bar');
            $bar.removeClass('progress-bar-success progress-bar-warning progress-bar-danger');
            if (stats.percent_complete >= 90) {
                $bar.addClass('progress-bar-success');
            } else if (stats.percent_complete >= 50) {
                $bar.addClass('progress-bar-warning');
            } else {
                $bar.addClass('progress-bar-danger');
            }
        },
        
        renderTable() {
            const $tbody = $('#images-tbody');
            $tbody.empty();
            
            const langArray = Object.values(this.languages);
            const firstLang = langArray[0] || { id: this.currentLangId, code: 'DE', name: 'Deutsch' };
            const otherLangs = langArray.slice(1);
            const hasMoreLangs = this.isMultiLang && otherLangs.length > 0;
            
            this.images.forEach((img, index) => {
                const categoryName = img.category_id == 0 
                    ? '<?= rex_i18n::msg('pool_kats_no') ?>' 
                    : (this.categories[img.category_id] || '-');
                
                // Alt-Text Eingabefeld generieren
                let altInputHtml = '';
                
                if (this.isMultiLang && hasMoreLangs) {
                    // Mehrsprachig: Kompaktes Layout mit Badge und Globus
                    altInputHtml = `
                        <div class="alt-input-group">
                            <span class="lang-badge">${this.escapeHtml(firstLang.code.toUpperCase())}</span>
                            <input type="text" class="form-control input-sm alt-input" 
                                   data-filename="${this.escapeHtml(img.filename)}"
                                   data-clang-id="${firstLang.id}"
                                   value=""
                                   placeholder="<?= $addon->i18n('alt_checker_enter_alt') ?>"
                                   tabindex="${index + 1}">
                            <button type="button" class="btn btn-default btn-sm lang-toggle" 
                                    data-filename="${this.escapeHtml(img.filename)}" 
                                    title="<?= $addon->i18n('alt_checker_more_languages') ?> (${otherLangs.length})">
                                <i class="fa fa-globe"></i>
                            </button>
                        </div>
                    `;
                } else {
                    // Einsprachig: Nur Input ohne Badge
                    altInputHtml = `
                        <input type="text" class="form-control input-sm alt-input" 
                               data-filename="${this.escapeHtml(img.filename)}"
                               data-clang-id="${this.currentLangId}"
                               value=""
                               placeholder="<?= $addon->i18n('alt_checker_enter_alt') ?>"
                               tabindex="${index + 1}">
                    `;
                }
                
                // Hauptzeile
                $tbody.append(`
                    <tr data-filename="${this.escapeHtml(img.filename)}" class="image-row">
                        <td>
                            <span class="preview-toggle" data-filename="${this.escapeHtml(img.filename)}" title="<?= $addon->i18n('alt_checker_show_preview') ?>">
                                <i class="fa fa-chevron-right"></i>
                                <img src="index.php?rex_media_type=rex_media_small&rex_media_file=${encodeURIComponent(img.filename)}" 
                                     alt="" class="thumb-mini" loading="lazy">
                            </span>
                        </td>
                        <td>
                            <strong>${this.escapeHtml(img.filename)}</strong>
                            ${img.title ? '<br><small class="text-muted">' + this.escapeHtml(img.title) + '</small>' : ''}
                            <br><small class="text-muted">${img.width || '?'} × ${img.height || '?'} px</small>
                        </td>
                        <td>
                            ${altInputHtml}
                        </td>
                        <td><small>${this.escapeHtml(categoryName)}</small></td>
                        <td class="text-nowrap">
                            <button type="button" class="btn btn-success btn-xs btn-save-row" 
                                    data-filename="${this.escapeHtml(img.filename)}" title="<?= $addon->i18n('alt_checker_save') ?>">
                                <i class="fa fa-check"></i>
                            </button>
                            <button type="button" class="btn btn-default btn-xs btn-ignore" 
                                    data-filename="${this.escapeHtml(img.filename)}" title="<?= $addon->i18n('alt_checker_ignore_decorative') ?>">
                                <i class="fa fa-eye-slash"></i>
                            </button>
                        </td>
                    </tr>
                `);
                
                // Sprach-Zeile (nur bei mehrsprachig mit weiteren Sprachen)
                if (hasMoreLangs) {
                    let otherLangsHtml = '';
                    otherLangs.forEach(lang => {
                        otherLangsHtml += `
                            <div class="input-group input-group-sm" style="margin-bottom: 5px;">
                                <span class="input-group-addon" style="min-width: 35px;">${this.escapeHtml(lang.code.toUpperCase())}</span>
                                <input type="text" class="form-control alt-input" 
                                       data-filename="${this.escapeHtml(img.filename)}"
                                       data-clang-id="${lang.id}"
                                       value=""
                                       placeholder="<?= $addon->i18n('alt_checker_enter_alt') ?>">
                            </div>
                        `;
                    });
                    
                    $tbody.append(`
                        <tr class="lang-row" data-filename="${this.escapeHtml(img.filename)}">
                            <td></td>
                            <td colspan="2">
                                <div class="other-langs-container">
                                    ${otherLangsHtml}
                                </div>
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    `);
                }
                
                // Vorschau-Zeile (Akkordeon)
                $tbody.append(`
                    <tr class="preview-row" data-filename="${this.escapeHtml(img.filename)}">
                        <td colspan="5">
                            <div class="preview-container">
                                <img src="index.php?rex_media_type=rex_media_medium&rex_media_file=${encodeURIComponent(img.filename)}" 
                                     alt="" loading="lazy">
                                <div style="margin-top: 10px;">
                                    <a href="index.php?page=mediapool/media&file_name=${encodeURIComponent(img.filename)}" 
                                       target="_blank" class="btn btn-default btn-xs">
                                        <i class="fa fa-external-link"></i> <?= $addon->i18n('alt_checker_open_mediapool') ?>
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                `);
            });
        },
        
        updateSaveAllButton() {
            $('#btn-save-all').prop('disabled', this.modifiedImages.size === 0);
        },
        
        saveOne(filename) {
            const $row = $(`tr.image-row[data-filename="${this.escapeHtml(filename)}"]`);
            const $langRow = $(`.lang-row[data-filename="${this.escapeHtml(filename)}"]`);
            const $previewRow = $(`.preview-row[data-filename="${this.escapeHtml(filename)}"]`);
            
            // Alle Inputs sammeln (aus Hauptzeile und Sprach-Zeile)
            const $allInputs = $row.find('.alt-input').add($langRow.find('.alt-input'));
            
            // Alt-Text sammeln (einsprachig oder mehrsprachig)
            let altData = {};
            let hasValue = false;
            
            if (this.isMultiLang) {
                // Mehrsprachig: alle Sprachen sammeln
                $allInputs.each((i, input) => {
                    const $input = $(input);
                    const clangId = $input.data('clang-id');
                    const value = $input.val().trim();
                    altData[clangId] = value;
                    if (value) hasValue = true;
                });
            } else {
                // Einsprachig
                const altText = $allInputs.first().val().trim();
                if (altText) {
                    altData = altText;
                    hasValue = true;
                }
            }
            
            if (!hasValue) {
                $allInputs.first().focus();
                return;
            }
            
            $row.addClass('saving');
            
            $.post(this.apiEndpoint, {
                action: 'update',
                filename: filename,
                alt_text: typeof altData === 'object' ? JSON.stringify(altData) : altData,
                is_multilang: this.isMultiLang && typeof altData === 'object'
            })
            .done((response) => {
                if (response.success) {
                    $allInputs.removeClass('modified').addClass('saved');
                    $row.find('.btn-save-row').removeClass('visible');
                    this.modifiedImages.delete(filename);
                    this.updateSaveAllButton();
                    
                    // Nach kurzer Zeit aus Tabelle entfernen (inkl. Sprach-Zeile)
                    setTimeout(() => {
                        $row.fadeOut(300);
                        $langRow.fadeOut(300);
                        $previewRow.fadeOut(300, () => {
                            $row.remove();
                            $langRow.remove();
                            $previewRow.remove();
                            this.images = this.images.filter(i => i.filename !== filename);
                            $('#image-count').text(this.images.length);
                            
                            if (this.images.length === 0) {
                                $('#images-table').hide();
                                $('#no-images-message').show();
                            }
                            
                            // Stats neu laden
                            this.loadStats();
                        });
                    }, 500);
                } else {
                    alert('Fehler: ' + (response.error || 'Unbekannt'));
                }
            })
            .fail((xhr, status, error) => {
                alert('Fehler: ' + error);
            })
            .always(() => {
                $row.removeClass('saving');
            });
        },
        
        saveAll() {
            const updates = [];
            
            this.modifiedImages.forEach(filename => {
                const $input = $(`.alt-input[data-filename="${this.escapeHtml(filename)}"]`);
                const altText = $input.val().trim();
                if (altText) {
                    updates.push({ filename, alt_text: altText });
                }
            });
            
            if (updates.length === 0) return;
            
            $('#btn-save-all').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Speichern...');
            
            $.post(this.apiEndpoint, {
                action: 'bulk_update',
                updates: JSON.stringify(updates)
            })
            .done((response) => {
                if (response.success > 0) {
                    // Tabelle neu laden
                    this.loadImages();
                }
                if (response.failed > 0) {
                    alert(response.failed + ' Fehler beim Speichern');
                }
            })
            .fail((xhr, status, error) => {
                alert('Fehler: ' + error);
            })
            .always(() => {
                $('#btn-save-all').prop('disabled', false).html('<i class="fa fa-save"></i> <?= $addon->i18n('alt_checker_save_all') ?>');
            });
        },
        
        loadStats() {
            $.getJSON(this.apiEndpoint + '&action=stats')
                .done((response) => {
                    if (response.stats) {
                        this.updateStats(response.stats);
                    }
                });
        },
        
        ignoreImage(filename) {
            const $row = $(`tr.image-row[data-filename="${this.escapeHtml(filename)}"]`);
            const $previewRow = $(`.preview-row[data-filename="${this.escapeHtml(filename)}"]`);
            const $input = $row.find('.alt-input');
            
            $row.addClass('saving');
            
            // Dekoratives Bild = leerer alt-Text mit speziellem Marker
            $.post(this.apiEndpoint, {
                action: 'update',
                filename: filename,
                alt_text: '',
                decorative: true
            })
            .done((response) => {
                if (response.success) {
                    $input.val('<?= $addon->i18n('alt_checker_decorative') ?>').addClass('decorative').prop('disabled', true);
                    $row.addClass('ignored');
                    $row.find('.btn-save-row, .btn-ignore').hide();
                    
                    // Nach kurzer Zeit aus Tabelle entfernen
                    setTimeout(() => {
                        $row.fadeOut(300);
                        $previewRow.fadeOut(300, () => {
                            $row.remove();
                            $previewRow.remove();
                            this.images = this.images.filter(i => i.filename !== filename);
                            $('#image-count').text(this.images.length);
                            
                            if (this.images.length === 0) {
                                $('#images-table').hide();
                                $('#no-images-message').show();
                            }
                            
                            this.loadStats();
                        });
                    }, 800);
                } else {
                    alert('Fehler: ' + (response.error || 'Unbekannt'));
                }
            })
            .fail((xhr, status, error) => {
                alert('Fehler: ' + error);
            })
            .always(() => {
                $row.removeClass('saving');
            });
        },
        
        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };
    
    AltChecker.init();
});
</script>
