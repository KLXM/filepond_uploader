<?php
$addon = rex_addon::get('filepond_uploader');

if (rex_post('btn_save', 'string') !== '') {
    // Token-Regenerierung wenn angefordert
    if (rex_post('regenerate_token', 'boolean')) {
        try {
            $token = bin2hex(random_bytes(32));
            rex_config::set('filepond_uploader', 'api_token', $token);
            echo rex_view::success($addon->i18n('filepond_token_regenerated') . '<br><br>' .
                '<div class="input-group">' .
                '<input type="text" class="form-control" id="new-token" value="' . rex_escape($token) . '" readonly>' .
                '<span class="input-group-btn">' .
                '<clipboard-copy for="new-token" class="btn btn-default"><i class="fa fa-clipboard"></i> ' . 
                $addon->i18n('filepond_copy_token') . '</clipboard-copy>' .
                '</span>' .
                '</div>');
        } catch (Exception $e) {
            echo rex_view::error($addon->i18n('filepond_token_regenerate_failed'));
        }
    }
    
    // Speichere die anderen Einstellungen
    $configs = [
        ['max_files', 'int', true],  // true = größer als 0 erforderlich
        ['max_filesize', 'int', true],
        ['allowed_types', 'string', false],
        ['category_id', 'int', false],
        ['lang', 'string', false],
    ];

    $errors = [];
    foreach ($configs as $conf) {
        list($key, $type, $required) = $conf;
        $value = rex_post($key, $type, '');
        
        // Validierung der Werte
        switch ($key) {
            case 'max_files':
            case 'max_filesize':
                if ($required && $value <= 0) {
                    $errors[] = $addon->i18n('filepond_error_' . $key . '_required');
                    continue 2;
                }
                break;
            case 'allowed_types':
                if (empty($value)) {
                    $value = 'image/*,video/*,.pdf,.doc,.docx,.txt';
                }
                break;
        }
        
        rex_config::set('filepond_uploader', $key, $value);
    }

    if (empty($errors)) {
        echo rex_view::success($addon->i18n('filepond_settings_saved'));
    } else {
        echo rex_view::error(implode('<br>', $errors));
    }
}

// Formular erstellen
$content = '<div class="rex-form">
    <form action="' . rex_url::currentBackendPage() . '" method="post">
        <div class="panel panel-edit">
            <header class="panel-heading">
                <div class="panel-title">' . $addon->i18n('filepond_settings_title') . '</div>
            </header>
            
            <div class="panel-body">
                <!-- API Token Section -->
                <fieldset>
                    <legend>' . $addon->i18n('filepond_token_section') . '</legend>
                    
                    <div class="row">
                        <div class="col-sm-8">
                            <div class="form-group">
                                <label>' . $addon->i18n('filepond_current_token') . '</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="current-token" value="' . 
                                    preg_replace('/[a-zA-Z0-9]/','*', rex_config::get('filepond_uploader', 'api_token')) . 
                                    '" readonly>
                                </div>
                                <p class="help-block">' . $addon->i18n('filepond_token_help') . '</p>
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="regenerate_token" value="1">
                                    ' . $addon->i18n('filepond_regenerate_token') . '
                                </label>
                                <p class="help-block rex-warning">' . $addon->i18n('filepond_regenerate_token_warning') . '</p>
                            </div>
                        </div>
                    </div>
                </fieldset>
                
                <!-- General Settings -->
                <fieldset>
                    <legend>' . $addon->i18n('filepond_general_settings') . '</legend>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="max_files">' . $addon->i18n('filepond_settings_max_files') . '</label>
                                <input class="form-control"
                                    type="number"
                                    id="max_files"
                                    name="max_files"
                                    value="' . rex_escape(rex_config::get('filepond_uploader', 'max_files', 30)) . '"
                                    min="1"
                                    required>
                            </div>
                        </div>
                        
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="max_filesize">' . $addon->i18n('filepond_settings_maxsize') . '</label>
                                <input class="form-control"
                                    type="number"
                                    id="max_filesize"
                                    name="max_filesize"
                                    value="' . rex_escape(rex_config::get('filepond_uploader', 'max_filesize', 10)) . '"
                                    min="1"
                                    required>
                                <p class="help-block">' . $addon->i18n('filepond_settings_maxsize_notice') . '</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="form-group">
                                <label for="allowed_types">' . $addon->i18n('filepond_settings_allowed_types') . '</label>
                                <input class="form-control"
                                    type="text"
                                    id="allowed_types"
                                    name="allowed_types"
                                    value="' . rex_escape(rex_config::get('filepond_uploader', 'allowed_types', 'image/*,video/*,.pdf,.doc,.docx,.txt')) . '">
                                <p class="help-block">' . $addon->i18n('filepond_settings_allowed_types_notice') . '</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="category_id">' . $addon->i18n('filepond_settings_category_id') . '</label>
                                ' . rex_media_category_select::factory()
                                    ->setName('category_id')
                                    ->setId('category_id')
                                    ->setSize(1)
                                    ->setAttribute('class', 'form-control selectpicker')
                                    ->setSelected(rex_config::get('filepond_uploader', 'category_id', 0))
                                    ->addOption($addon->i18n('filepond_upload_no_category'), 0)
                                    ->get() . '
                                <p class="help-block">' . $addon->i18n('filepond_settings_category_notice') . '</p>
                            </div>
                        </div>
                        
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="lang">' . $addon->i18n('filepond_settings_lang') . '</label>
                                <select class="form-control" id="lang" name="lang">
                                    <option value="de_de" ' . (rex_config::get('filepond_uploader', 'lang') == 'de_de' ? 'selected' : '') . '>Deutsch</option>
                                    <option value="en_gb" ' . (rex_config::get('filepond_uploader', 'lang') == 'en_gb' ? 'selected' : '') . '>English</option>
                                </select>
                                <p class="help-block">' . $addon->i18n('filepond_settings_lang_notice') . '</p>
                            </div>
                        </div>
                    </div>
                </fieldset>
            </div>
            
            <footer class="panel-footer">
                <div class="rex-form-panel-footer">
                    <div class="btn-toolbar">
                        <button class="btn btn-save rex-form-aligned" type="submit" name="btn_save" value="1">
                            ' . $addon->i18n('filepond_save') . '
                        </button>
                    </div>
                </div>
            </footer>
        </div>
    </form>
</div>';

// Fragment ausgeben
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', $addon->i18n('filepond_settings_title'));
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
