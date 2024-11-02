<?php
$csrf = rex_csrf_token::factory('yform_filepond_settings');

$content = '';
$success = '';
$error = '';

// Einstellungen speichern
if (rex_post('submit', 'boolean') && $csrf->isValid()) {
    $settings = rex_post('settings', 'array', []);
    
    // Einstellungen speichern
    if (rex_config::set('yform_filepond', 'settings', $settings)) {
        $success = rex_i18n::msg('form_saved');
    } else {
        $error = rex_i18n::msg('form_save_error');
    }
}

// Aktuelle Einstellungen laden
$settings = rex_config::get('yform_filepond', 'settings', [
    'default_category' => 0,
    'allowed_types' => 'image/*,.pdf',
    'max_filesize' => 10
]);

// Medienpool-Kategorien holen
$categories = [];
$sql = rex_sql::factory();
$categories = $sql->getArray('SELECT id, name FROM ' . rex::getTable('media_category') . ' ORDER BY name');

if ($success != '') {
    $content .= rex_view::success($success);
}

if ($error != '') {
    $content .= rex_view::error($error);
}

$content .= '
<div class="rex-form">
    <form action="' . rex_url::currentBackendPage() . '" method="post" class="form-horizontal">
        ' . $csrf->getHiddenField() . '
        <div class="panel panel-default">
            <div class="panel-heading">
                <div class="panel-title">Einstellungen</div>
            </div>
            
            <div class="panel-body">
                <div class="form-group">
                    <label class="col-sm-2 control-label">Standard-Kategorie</label>
                    <div class="col-sm-10">
                        <select name="settings[default_category]" class="form-control">
                            <option value="0">Keine Kategorie</option>';

foreach ($categories as $category) {
    $content .= '<option value="' . $category['id'] . '"' . 
                ($settings['default_category'] == $category['id'] ? ' selected' : '') . '>' . 
                rex_escape($category['name']) . '</option>';
}

$content .= '
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="col-sm-2 control-label">Erlaubte Dateitypen</label>
                    <div class="col-sm-10">
                        <input type="text" name="settings[allowed_types]" value="' . $settings['allowed_types'] . '" class="form-control" />
                        <span class="help-block">z.B.: image/*,.pdf</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="col-sm-2 control-label">Max. Dateigröße (MB)</label>
                    <div class="col-sm-10">
                        <input type="number" name="settings[max_filesize]" value="' . $settings['max_filesize'] . '" class="form-control" />
                    </div>
                </div>
            </div>
            
            <div class="panel-footer">
                <div class="rex-form-panel-footer">
                    <div class="btn-toolbar">
                        <button type="submit" name="submit" value="1" class="btn btn-save rex-form-aligned">
                            Speichern
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>';

// Fragment ausgeben
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', 'Filepond Einstellungen');
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
