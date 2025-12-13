<?php

/** @var rex_addon $this */

use FriendsOfRedaxo\FilePondUploader\BulkResize;
use FriendsOfRedaxo\FilePondUploader\BulkReworkList;

$addon = rex_addon::get('filepond_uploader');

// Hole die Zielgröße aus den Addon-Einstellungen (max_pixel)
$maxWidth = (int) $addon->getConfig('max_pixel', 2000);
$maxHeight = (int) $addon->getConfig('max_pixel', 2000);

// Einträge pro Seite konfigurieren
if (rex_request('formsubmit', 'string') == 'set-num-hits-per-page') {
    $setHitsPerPage = rex_request('bulk-files-num-hits-per-page', 'int', 0);
    $this->setConfig('bulk-resize-hits-per-page', $setHitsPerPage ?: 100);
}
$hitsPerPage = $this->getConfig('bulk-resize-hits-per-page', 100);

// Info über verfügbare Bildverarbeitungsbibliotheken
$imageLibInfo = [];
if (BulkResize::hasGD()) {
    $imageLibInfo[] = '<i class="rex-icon fa-check"></i> GD verfügbar';
}
if (BulkResize::hasImageMagick()) {
    if (class_exists('Imagick')) {
        $imageLibInfo[] = '<i class="rex-icon fa-check"></i> ImageMagick (PHP Extension) verfügbar';
    } else {
        $imageLibInfo[] = '<i class="rex-icon fa-check"></i> ImageMagick (CLI) verfügbar';
    }
}

// Info-Box
$infoContent = '<div class="alert alert-info">';
$infoContent .= '<p><strong>' . $addon->i18n('bulk_resize_info_title') . '</strong></p>';
$infoContent .= '<p>' . rex_i18n::rawMsg('filepond_uploader::bulk_resize_info_text', $maxWidth, $maxHeight) . '</p>';
$infoContent .= '<p class="small"><i class="rex-icon fa-info-circle"></i> ' . $addon->i18n('bulk_resize_info_settings') . '</p>';
if (!empty($imageLibInfo)) {
    $infoContent .= '<hr><p class="small">' . implode(' | ', $imageLibInfo) . '</p>';
}
$infoContent .= '</div>';

echo $infoContent;

// Bereinige alte Batch-Dateien
BulkResize::cleanupOldBatches();

// Suchfilter
$search = [];

$searchFilename = rex_request('bulk-files-search-filename', 'string');
$searchMediaCategories = rex_request('bulk-files-search-media-category', 'string');
$searchMinFilesize = rex_request('bulk-files-search-min-filesize', 'int');
$searchMinWidth = rex_request('bulk-files-search-min-width', 'int');
$searchMinHeight = rex_request('bulk-files-search-min-height', 'int');

if ($searchFilename) {
    $search[] = 'filename LIKE ' . rex_sql::factory()->escape('%' . $searchFilename . '%');
}

if ($searchMediaCategories) {
    $categories = array_map('intval', explode(',', $searchMediaCategories));
    $categories = array_filter($categories);

    if (!empty($categories)) {
        $search[] = 'category_id IN (' . implode(',', $categories) . ')';
    }
}

if ($searchMinFilesize > 0) {
    $search[] = 'CAST(`filesize` AS SIGNED INTEGER) >= ' . ($searchMinFilesize * 1024);
}

if ($searchMinWidth > 0) {
    $search[] = 'width >= ' . $searchMinWidth;
}

if ($searchMinHeight > 0) {
    $search[] = 'height >= ' . $searchMinHeight;
}

// SQL Query - Finde Bilder die größer als max_width ODER max_height sind
$conditions = ['filetype LIKE "image/%"'];

if ($maxWidth > 0 || $maxHeight > 0) {
    $sizeConditions = [];
    if ($maxWidth > 0) {
        $sizeConditions[] = 'width > ' . $maxWidth;
    }
    if ($maxHeight > 0) {
        $sizeConditions[] = 'height > ' . $maxHeight;
    }
    $conditions[] = '(' . implode(' OR ', $sizeConditions) . ')';
}

if (!empty($search)) {
    $conditions = array_merge($conditions, $search);
}

$sql = '
    SELECT
        *
    FROM
        ' . rex::getTable('media') . '
    WHERE
        ' . implode(' AND ', $conditions) . '
';

$list = BulkReworkList::factory($sql, $hitsPerPage, 'filepond-bulk-resize', false, 1, ['id' => 'desc']);
$list->addParam('page', rex_be_controller::getCurrentPage());
$list->addTableAttribute('class', 'table table-striped table-hover filepond-bulk-resize-table');
$list->addTableAttribute('id', 'filepond-bulk-resize-table');
$list->setNoRowsMessage($addon->i18n('bulk_resize_no_images'));

// Icon Spalte
$tdIcon = '<i class="fa fa-image"></i>';
$thIcon = '';
$list->addColumn($thIcon, $tdIcon, 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);

// Nur erlaubte Felder
$allowedFields = ['id', 'filename', 'category_id', 'filesize', 'width', 'height', 'createdate', 'createuser'];
$existingFields = $list->getColumnNames();

foreach ($existingFields as $field) {
    if (!in_array($field, $allowedFields)) {
        $list->removeColumn($field);
    }
}

// ID Spalte
$list->setColumnLabel('id', rex_i18n::msg('id'));
$list->setColumnSortable('id');
$list->setColumnFormat('id', 'custom', static function ($params) use ($list) {
    return '<label for="bulk-file-' . $list->getValue('id') . '">' . $params['subject'] . '</label>';
});

// Vorschau Spalte (erste Spalte)
$list->addColumn(
    'preview',
    '',
    0,
    ['<th class="rex-table-icon" style="width: 80px;">###VALUE###</th>', '<td class="rex-table-thumbnail">###VALUE###</td>']
);
$list->setColumnLabel('preview', '<i class="rex-icon fa-image"></i>');
$list->setColumnFormat('preview', 'custom', static function ($params) use ($list) {
    $filename = $list->getValue('filename');
    
    // Wie Alt-Checker: Direkte Media Manager URL (kein rex_media_manager::getUrl)
    $isSvg = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'svg';
    $thumbSrc = $isSvg 
        ? rex_url::media($filename)
        : 'index.php?rex_media_type=rex_media_small&rex_media_file=' . urlencode($filename);
    $previewSrc = $isSvg 
        ? rex_url::media($filename)
        : 'index.php?rex_media_type=rex_media_large&rex_media_file=' . urlencode($filename);
    
    return '<img src="' . rex_escape($thumbSrc) . '" 
        alt="' . rex_escape($filename) . '" 
        class="bulk-resize-thumb" 
        data-preview="' . rex_escape($previewSrc) . '"
        data-filename="' . rex_escape($filename) . '"
        loading="lazy"
        style="max-width: 60px; max-height: 60px; display: block; margin: 0 auto; cursor: pointer;">';
});

// Dateiname Spalte
$list->setColumnLabel('filename', rex_i18n::msg('pool_filename'));
$list->setColumnSortable('filename');
$list->setColumnFormat('filename', 'custom', static function ($params) use ($list) {
    $title = trim($list->getValue('title'));
    $tooltip = '';
    if ($title) {
        $tooltip = '<i class="rex-icon rex-icon-info" data-toggle="tooltip" data-placement="top" title="<b>' .
            rex_i18n::msg('pool_file_title') . '</b>: ' . rex_escape($title) . '"></i> ';
    }
    return $tooltip . rex_escape($params['subject']);
});

// Kategorie Spalte
$list->setColumnLabel('category_id', '<nobr>' . rex_i18n::msg('pool_file_category') . '</nobr>');
$list->setColumnSortable('category_id');

// Dateigröße Spalte
$list->setColumnLabel('filesize', '<nobr>' . $addon->i18n('bulk_resize_filesize') . '</nobr>');
$list->setColumnSortable('filesize');
$list->setColumnFormat('filesize', 'custom', static function ($params) {
    return preg_replace(
        '@(,\d+)$@',
        '<span class="text-muted">$1</span>',
        number_format($params['subject'] / 1024, 2, ',', '.')
    ) . ' KB';
});

// Breite Spalte
$list->setColumnLabel('width', '<nobr>' . $addon->i18n('bulk_resize_dimensions') . '</nobr>');
$list->setColumnSortable('width');
$list->setColumnFormat('width', 'custom', static function ($params) use ($list, $maxWidth) {
    $width = (int) $params['subject'];
    $warning = '';

    if ($maxWidth > 0 && $width > $maxWidth) {
        $factor = 2 - (($width - $maxWidth) / $maxWidth);
        $factor = max(0.3, min(1.7, $factor));
        $percentPlus = round(($width / $maxWidth - 1) * 100);

        $warning = ' <span class="label" style="background-color: ' .
            BulkResize::lightenColor('FF0000', $factor) .
            ';" data-toggle="tooltip" title="+' . $percentPlus . '%">!</span>';
    }

    return $width . $warning;
});

// Höhe Spalte
$list->setColumnLabel('height', '<nobr></nobr>');
$list->setColumnSortable('height');
$list->setColumnFormat('height', 'custom', static function ($params) use ($list, $maxHeight) {
    $height = (int) $params['subject'];
    $warning = '';

    if ($maxHeight > 0 && $height > $maxHeight) {
        $factor = 2 - (($height - $maxHeight) / $maxHeight);
        $factor = max(0.3, min(1.7, $factor));
        $percentPlus = round(($height / $maxHeight - 1) * 100);

        $warning = ' <span class="label" style="background-color: ' .
            BulkResize::lightenColor('FF0000', $factor) .
            ';" data-toggle="tooltip" title="+' . $percentPlus . '%">!</span>';
    }

    return $height . $warning;
});

// Erstelldatum Spalte
$list->setColumnLabel('createdate', '<nobr>' . rex_i18n::msg('created_on') . '</nobr>');
$list->setColumnSortable('createdate');
$list->setColumnFormat('createdate', 'custom', static function ($params) {
    $date = new DateTime($params['subject']);
    return '<nobr>' . $date->format('d.m.Y') . '</nobr>';
});

// Ersteller Spalte
$list->setColumnLabel('createuser', '<nobr>' . rex_i18n::msg('created_by') . '</nobr>');
$list->setColumnSortable('createuser');

// Checkbox Spalte
$list->addColumn(
    'toggle-select-all',
    '<input type="checkbox" class="rex-table-checkbox" id="bulk-file-###id###" name="bulk-file[]" value="###id###" />',
    0
);
$list->setColumnLayout(
    'toggle-select-all',
    [
        '<th><input type="checkbox" class="rex-table-checkbox" id="bulk-toggle-all" data-toggle="tooltip" title="' .
        $addon->i18n('bulk_resize_select_all') . '"/></th>',
        '<td class="column-select">###VALUE###</td>',
    ]
);
$list->setColumnFormat('toggle-select-all', 'custom', static function ($params) use ($list) {
    return str_replace('value="###id###"', 'value="' . rex_escape($list->getValue('filename')) . '"', $params['subject']);
});

$listContent = $list->get();

// Submit Button - wird später am Ende eingefügt
$submitButton = '<button class="btn btn-save btn-lg" type="button" id="bulk-resize-submit" 
    data-max-width="' . $maxWidth . '" 
    data-max-height="' . $maxHeight . '">
    <i class="rex-icon fa-compress"></i> ' .
    $addon->i18n('bulk_resize_submit') . ' (<span class="number">0</span> ' . $addon->i18n('bulk_resize_images') . ')
</button>';

// Treffer pro Seite Form
$hitsPerPageForm = '<form class="filepond-bulk-resize-num-hits-per-page" action="' . rex_url::currentBackendPage() . '" method="post" style="display: inline-block; margin-right: 20px;">
    <div class="form-group" style="display: inline-flex; align-items: center; margin: 0; gap: 10px;">
        <label for="num-hits-per-page" style="margin: 0; font-weight: normal;">' . $addon->i18n('bulk_resize_hits_per_page') . '</label>
        <input class="form-control" type="number" id="num-hits-per-page" name="bulk-files-num-hits-per-page" value="' .
    $hitsPerPage . '" placeholder="100" style="width: 80px;">
        <button class="btn btn-sm btn-primary" type="submit">OK</button>
    </div>
    <input type="hidden" name="formsubmit" value="set-num-hits-per-page" />
</form>';

// Submit Button auch oben (kleinere Version)
$submitButtonTop = '<button class="btn btn-save" type="button" id="bulk-resize-submit-top" 
    data-max-width="' . $maxWidth . '" 
    data-max-height="' . $maxHeight . '"
    style="margin-left: 10px;">
    <i class="rex-icon fa-compress"></i> ' .
    $addon->i18n('bulk_resize_submit') . ' (<span class="number">0</span>)
</button>';

// Tabelle
$fragment = new rex_fragment();
$fragment->setVar('title',
    $addon->i18n('bulk_resize_images') .
    '<div class="small text-muted">' . rex_i18n::rawMsg('filepond_uploader::bulk_resize_current_settings', $maxWidth, $maxHeight) . '</div>' .
    '<div class="small text-muted">' . $list->getRows() . ' ' . $addon->i18n('bulk_resize_hits') . '</div>',
    false
);
$fragment->setVar('options', $hitsPerPageForm . $submitButtonTop, false);
$fragment->setVar('content', $listContent, false);
$table = $fragment->parse('core/page/section.php');

// Suchformular
$searchFields = [];

$searchFields[] = '<div class="col-lg-2 col-sm-9"><div class="form-group">
    <label for="search-filename">' . rex_i18n::msg('pool_filename') . '</label>
    <input class="form-control" type="text" id="search-filename" name="bulk-files-search-filename" value="' .
    rex_escape($searchFilename) . '">
</div></div>';

$searchFields[] = '<div class="col-lg-2 col-sm-3"><div class="form-group">
    <label for="search-media-category">' . $addon->i18n('bulk_resize_category') . '</label>
    <input class="form-control" type="text" id="search-media-category" name="bulk-files-search-media-category" value="' .
    rex_escape($searchMediaCategories) . '" placeholder="1,2,3">
</div></div>';

$searchFields[] = '<div class="col-lg-2 col-sm-3"><div class="form-group">
    <label for="search-min-filesize">min. ' . $addon->i18n('bulk_resize_filesize') . '</label>
    <input class="form-control" type="number" id="search-min-filesize" name="bulk-files-search-min-filesize" value="' .
    $searchMinFilesize . '" min="0" placeholder="KB">
</div></div>';

$searchFields[] = '<div class="col-lg-2 col-sm-3"><div class="form-group">
    <label for="search-min-width">min. ' . $addon->i18n('bulk_resize_dimensions') . '</label>
    <input class="form-control" type="number" id="search-min-width" name="bulk-files-search-min-width" value="' .
    $searchMinWidth . '" min="0" placeholder="px">
</div></div>';

$searchFields[] = '<div class="col-lg-2 col-sm-3"><div class="form-group">
    <label for="search-min-height">min. Höhe</label>
    <input class="form-control" type="number" id="search-min-height" name="bulk-files-search-min-height" value="' .
    $searchMinHeight . '" min="0" placeholder="px">
</div></div>';

$searchFields[] = '<div class="col-lg-2 col-sm-3"><div class="form-group">
    <label style="display: block;">&nbsp;</label>
    <div class="btn-group">
        <button class="btn btn-primary" type="submit" name="bulk-files-search-submit" value="1" data-toggle="tooltip" title="' . $addon->i18n('bulk_resize_search') . '">
            <i class="rex-icon rex-icon-search"></i>
        </button>
        <button class="btn btn-default" type="submit" name="bulk-files-search-reset" value="1" data-toggle="tooltip" title="' . $addon->i18n('bulk_resize_reset') . '">
            <i class="rex-icon fa-arrow-rotate-left"></i>
        </button>
    </div>
</div></div>';

$searchFields = '<div class="row bulk-files-search">' . implode('', $searchFields) . '</div>';

// Zusammenbauen - Button in den Footer
$footerButtons = '
<div class="panel panel-default" style="margin-top: 20px;">
    <div class="panel-body text-center" style="padding: 20px;">
        ' . $submitButton . '
    </div>
</div>';

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit filepond-bulk-resize-wrapper', false);
$fragment->setVar('title', $addon->i18n('bulk_resize_title'));
$fragment->setVar('body', $searchFields . '<hr />' . $table . $footerButtons, false);
$content = $fragment->parse('core/page/section.php');

// Query Params für Sortierung
$queryParams = [];
$sort = rex_request('sort', 'string', null);
$sorttype = rex_request('sorttype', 'string', null);
$listname = rex_request('list', 'string', null);

if ($sort) {
    $queryParams['sort'] = $sort;
}
if ($sorttype) {
    $queryParams['sorttype'] = $sorttype;
}
if ($listname) {
    $queryParams['list'] = $listname;
}

$actionUrl = rex_url::currentBackendPage();
if (!empty($queryParams)) {
    $actionUrl .= '&' . http_build_query($queryParams);
}

echo '
<form action="' . $actionUrl . '" method="post" id="filepond-bulk-resize-form">
    ' . $content . '
</form>';

// Bildvorschau Modal
?>
<div class="modal fade" id="image-preview-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="preview-modal-title">Bildvorschau</h4>
            </div>
            <div class="modal-body text-center" id="preview-modal-body" style="padding: 20px;">
                <img src="" id="preview-modal-image" alt="" style="max-width: 100%; height: auto; max-height: 70vh;">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<script>
// Sofortiges Initialisieren des Modal-Handlers
jQuery(function($) {
    console.log('Modal handler wird initialisiert');
    
    // Klick auf Thumbnails
    $(document).on('click', '.bulk-resize-thumb', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var previewSrc = $(this).attr('data-preview');
        var filename = $(this).attr('data-filename');
        
        console.log('Thumbnail geklickt:', filename, previewSrc);
        
        if (previewSrc && filename) {
            $('#preview-modal-title').text(filename);
            $('#preview-modal-image').attr('src', previewSrc);
            $('#image-preview-modal').modal('show');
        } else {
            console.error('Fehlende Daten:', {previewSrc: previewSrc, filename: filename});
        }
    });
});
</script>
<?php
