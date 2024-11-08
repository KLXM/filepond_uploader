<?php
$csrf = rex_csrf_token::factory('filepond_uploader_settings');
$settings = [
    'max_files' => 10,
    'max_filesize' => 10
];
$content = '';
$success = '';
$error = '';

// Einstellungen speichern
if (rex_post('submit', 'boolean') && $csrf->isValid()) {
    $settings = rex_post('settings', 'array', []);
    
    if (rex_config::set('filepond_uploader', 'settings', $settings)) {
        $success = rex_i18n::msg('filepond_settings_saved');
    } else {
        $error = rex_i18n::msg('form_save_error');
    }
}

// Aktuelle Einstellungen laden
$settings = rex_config::get('filepond_uploader', 'settings', [
    'max_files' => 10,
    'max_filesize' => 10
]);

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
                <div class="panel-title">' . rex_i18n::msg('filepond_settings_title') . '</div>
            </div>
            
            <div class="panel-body">
                <div class="form-group">
                    <label class="col-sm-2 control-label">' . rex_i18n::msg('filepond_settings_max_files') . '</label>
                    <div class="col-sm-10">
                        <input type="number" name="settings[max_files]" value="' . $settings['max_files'] . '" class="form-control" min="1" />
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="col-sm-2 control-label">' . rex_i18n::msg('filepond_settings_maxsize') . '</label>
                    <div class="col-sm-10">
                        <input type="number" name="settings[max_filesize]" value="' . $settings['max_filesize'] . '" class="form-control" min="1" />
                        <p class="help-block">' . rex_i18n::msg('filepond_settings_maxsize_notice') . '</p>
                    </div>
                </div>
            </div>
            
            <div class="panel-footer">
                <div class="rex-form-panel-footer">
                    <div class="btn-toolbar">
                        <button type="submit" name="submit" value="1" class="btn btn-save rex-form-aligned">
                            ' . rex_i18n::msg('form_save') . '
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
$fragment->setVar('title', rex_i18n::msg('filepond_settings_title'));
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
