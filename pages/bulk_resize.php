<?php

/** @var rex_addon $this */

use FriendsOfRedaxo\FilePondUploader\BulkResize;
use FriendsOfRedaxo\FilePondUploader\BulkReworkList;

echo rex_view::title($this->i18n('filepond_uploader_bulk_resize'));

$addon = rex_addon::get('filepond_uploader');
$maxWidth = (int) $addon->getConfig('max_width', 2000);
$maxHeight = (int) $addon->getConfig('max_height', 2000);

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

if (!empty($imageLibInfo)) {
    echo rex_view::info(implode(' | ', $imageLibInfo));
}

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

// Submit Button
$formElements = $n = [];
$n['field'] = $submitButton = '<button class="pull-right btn btn-save" type="button" id="bulk-resize-submit" data-max-width="' .
    $maxWidth . '" data-max-height="' . $maxHeight . '">' .
    sprintf($addon->i18n('bulk_resize_start'), '<span class="number">0</span>') .
    '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('flush', true);
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

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

// Tabelle
$fragment = new rex_fragment();
$fragment->setVar('title',
    $addon->i18n('bulk_resize_images') .
    '<div class="small text-muted">' . sprintf($addon->i18n('bulk_resize_current_settings'), $maxWidth, $maxHeight) . '</div>' .
    '<div class="small text-muted">' . $list->getRows() . ' ' . $addon->i18n('bulk_resize_hits') . '</div>',
    false
);
$fragment->setVar('options', $hitsPerPageForm . preg_replace('@(btn btn-save)@', '$1 btn-xs', $submitButton), false);
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

// Zusammenbauen
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit filepond-bulk-resize-wrapper', false);
$fragment->setVar('title', $addon->i18n('bulk_resize_title'));
$fragment->setVar('body', $searchFields . '<hr />' . $table, false);
$fragment->setVar('buttons', $buttons, false);
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
