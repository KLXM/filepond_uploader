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

// System-Info
$hasGD = filepond_bulk_resize::hasGD();
$hasImageMagick = filepond_bulk_resize::hasImageMagick();
$hasConvertCli = filepond_bulk_resize::hasConvertCli();

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

// Load JS and CSS
rex_view::addJsFile($addon->getAssetsUrl('filepond_bulk_resize.js'));

?>
<body id="rex-page-filepond-uploader-bulk-resize">

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
                <?php if ($hasConvertCli): ?>
                <span class="label label-info">
                    CLI: Verfügbar
                </span>
                <?php endif; ?>
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
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="filter_filename"><?= $addon->i18n('bulk_resize_filename') ?></label>
                        <input type="text" class="form-control" id="filter_filename" name="filter_filename" 
                               value="<?= rex_escape($filterFilename) ?>" placeholder="z.B. foto_">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="filter_category"><?= $addon->i18n('bulk_resize_category') ?></label>
                        <select class="form-control selectpicker" id="filter_category" name="filter_category" data-live-search="true">
                            <option value="-1" <?= $filterCategoryId == -1 ? 'selected' : '' ?>><?= $addon->i18n('bulk_resize_all_categories') ?></option>
                            <option value="0" <?= $filterCategoryId === 0 ? 'selected' : '' ?>><?= rex_i18n::msg('pool_kats_no') ?></option>
                            <?php foreach ($categories as $catId => $catName): ?>
                                <option value="<?= $catId ?>" <?= $filterCategoryId === $catId ? 'selected' : '' ?>><?= rex_escape($catName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="max_width"><?= $addon->i18n('bulk_resize_max_size') ?></label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="max_width" name="max_width" 
                                   value="<?= $filterMaxWidth ?>" min="100" max="10000" step="100">
                            <span class="input-group-addon">px</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="quality"><?= $addon->i18n('bulk_resize_quality') ?></label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="quality" name="quality" 
                                   value="<?= $filterQuality ?>" min="10" max="100" step="5">
                            <span class="input-group-addon">%</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 text-right" style="padding-top: 25px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-search"></i> <?= $addon->i18n('bulk_resize_search') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Ergebnistabelle -->
<div class="panel panel-default">
    <div class="panel-heading">
        <div class="panel-title">
            <span>
                <i class="fa fa-images fa-fw"></i> 
                <?= $addon->i18n('bulk_resize_images') ?>
                <span id="image-count" class="badge"><?= $totalCount ?></span>
            </span>
            <span class="pull-right">
                <button type="button" class="btn btn-success btn-sm" name="rework-files-submit">
                    <i class="fa fa-play"></i> <span class="number">0</span> <?= $addon->i18n('bulk_resize_start_short', 'verarbeiten') ?>
                </button>
            </span>
        </div>
    </div>
    
    <?php if (count($images) > 0): ?>
    <div class="panel-body" id="images-container" style="padding: 0;">
        <table class="table table-striped table-hover" id="filepond-bulk-resize-table" style="margin-bottom: 0;">
            <thead>
                <tr>
                    <th width="40">
                        <input type="checkbox" name="rework-files-toggle" data-toggle="tooltip" title="Alle auswählen">
                    </th>
                    <th width="80"><?= $addon->i18n('bulk_resize_preview') ?></th>
                    <th><?= $addon->i18n('bulk_resize_filename') ?></th>
                    <th width="120"><?= $addon->i18n('bulk_resize_dimensions') ?></th>
                    <th width="100"><?= $addon->i18n('bulk_resize_filesize') ?></th>
                    <th width="100"><?= $addon->i18n('bulk_resize_category') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($images as $img): ?>
                <tr data-filename="<?= rex_escape($img['filename']) ?>">
                    <td>
                        <input type="checkbox" class="rex-table-checkbox" name="rework-file[]" value="<?= rex_escape($img['filename']) ?>">
                    </td>
                    <td>
                        <?php
                        $mediaFile = rex_media::get($img['filename']);
                        if ($mediaFile):
                            $mediaUrl = rex_url::media($img['filename']);
                        ?>
                        <img src="<?= $mediaUrl ?>" alt="<?= rex_escape($img['filename']) ?>" 
                             style="max-width: 60px; max-height: 60px; object-fit: cover;">
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?= rex_escape($img['filename']) ?></strong>
                        <?php if (!empty($img['title'])): ?>
                        <br><small class="text-muted"><?= rex_escape($img['title']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="<?= ($img['width'] > $filterMaxWidth || $img['height'] > $filterMaxHeight) ? 'text-danger' : '' ?>">
                            <?= $img['width'] ?> × <?= $img['height'] ?> px
                        </span>
                    </td>
                    <td><?= rex_formatter::bytes($img['filesize']) ?></td>
                    <td>
                        <?php
                        if ($img['category_id'] > 0 && isset($categories[$img['category_id']])) {
                            echo rex_escape($categories[$img['category_id']]);
                        } else {
                            echo rex_i18n::msg('pool_kats_no');
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($pager->getRowCount() > $pager->getRowsPerPage()): ?>
    <div class="panel-footer">
        <?php
        $fragment = new rex_fragment();
        $fragment->setVar('pager', $pager);
        $fragment->setVar('urlprovider', rex_be_controller::getCurrentPageObject());
        echo $fragment->parse('core/navigations/pagination.php');
        ?>
    </div>
    <?php endif; ?>
    
    <?php else: ?>
    <div class="panel-body">
        <p class="text-muted text-center" style="padding: 40px 0;">
            <i class="fa fa-info-circle fa-3x" style="display: block; margin-bottom: 15px;"></i>
            <?= $addon->i18n('bulk_resize_no_images') ?>
        </p>
    </div>
    <?php endif; ?>
</div>

</body>
