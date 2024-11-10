<?php
$addon = rex_addon::get('filepond_uploader');

if (rex_post('btn_save', 'string') !== '') {
    // Token-Regenerierung wenn angefordert
    if (rex_post('regenerate_token', 'boolean')) {
        $token = bin2hex(random_bytes(32));
        rex_config::set('filepond_uploader', 'api_token', $token);
        
        // Erfolgsmeldung für Token
        echo rex_view::success($addon->i18n('filepond_token_regenerated'));
    }
    
    // Speichere die anderen Einstellungen
    $configs = [
        ['max_files', 'int'],
        ['max_filesize', 'int'],
        ['allowed_types', 'string'],
        ['category_id', 'int'],
        ['lang', 'string'],
    ];

    foreach ($configs as $conf) {
        list($key, $type) = $conf;
        $value = rex_post($key, $type, '');
        
        // Validierung der Werte
        switch ($key) {
            case 'max_files':
                $value = max(1, (int)$value);
                break;
            case 'max_filesize':
                $value = max(1, (int)$value);
                break;
            case 'allowed_types':
                $value = trim($value);
                if (empty($value)) {
                    $value = 'image/*,video/*,.pdf,.doc,.docx,.txt';
                }
                break;
        }
        
        rex_config::set('filepond_uploader', $key, $value);
    }
    
    echo rex_view::success($addon->i18n('filepond_settings_saved'));
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
                                    <input type="text" class="form-control" readonly value="' . rex_escape(rex_config::get('filepond_uploader', 'api_token')) . '">
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-default" data-clipboard-target="#api-token">
                                            <i class="fa fa-clipboard"></i> ' . $addon->i18n('filepond_copy_token') . '
                                        </button>
                                    </span>
                                </div>
                                <p class="help-block">' . $addon->i18n('filepond_token_help') . '</p>
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="regenerate_token" value="1">
                                    ' . $addon->i18n('filepond_regenerate_token') . '
                                </label>
                                <p class="help-block text-warning">' . $addon->i18n('filepond_regenerate_token_warning') . '</p>
                            </div>
                        </div>
                    </div>
                </fieldset>
                
                <!-- General Settings -->
                <fieldset>
                    <legend>' . $addon->i18n('filepond_general_settings') . '</legend>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <!-- Max Files -->
                            <div class="form-group">
                                <label for="max_files">' . $addon->i18n('filepond_settings_max_files') . '</label>
                                <input class="form-control" type="number" id="max_files" name="max_files" value="' . rex_escape(rex_config::get('filepond_uploader', 'max_files', 30)) . '" min="1">
                            </div>
                        </div>
                        
                        <div class="col-sm-6">
                            <!-- Max Filesize -->
                            <div class="form-group">
                                <label for="max_filesize">' . $addon->i18n('filepond_settings_maxsize') . '</label>
                                <input class="form-control" type="number" id="max_filesize" name="max_filesize" value="' . rex_escape(rex_config::get('filepond_uploader', 'max_filesize', 10)) . '" min="1">
                                <p class="help-block">' . $addon->i18n('filepond_settings_maxsize_notice') . '</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-sm-12">
                            <!-- Allowed Types -->
                            <div class="form-group">
                                <label for="allowed_types">' . $addon->i18n('filepond_settings_allowed_types') . '</label>
                                <input class="form-control" type="text" id="allowed_types" name="allowed_types" value="' . rex_escape(rex_config::get('filepond_uploader', 'allowed_types', 'image/*,video/*,.pdf,.doc,.docx,.txt')) . '">
                                <p class="help-block">' . $addon->i18n('filepond_settings_allowed_types_notice') . '</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <!-- Default Category -->
                            <div class="form-group">
                                <label for="category_id">' . $addon->i18n('filepond_settings_category_id') . '</label>
                                <select class="form-control" id="category_id" name="category_id">';

// Media Categories
$mediaCategories = rex_media_category::getRootCategories();
$content .= '<option value="0">' . $addon->i18n('filepond_upload_no_category') . '</option>';
foreach ($mediaCategories as $category) {
    $content .= '<option value="' . $category->getId() . '"' . 
        (rex_config::get('filepond_uploader', 'category_id') == $category->getId() ? ' selected' : '') . '>' . 
        rex_escape($category->getName()) . '</option>';
    
    // Add subcategories
    $subCategories = $category->getChildren();
    if(count($subCategories)) {
        foreach ($subCategories as $sub) {
            $content .= '<option value="' . $sub->getId() . '"' .
                (rex_config::get('filepond_uploader', 'category_id') == $sub->getId() ? ' selected' : '') . '>' .
                rex_escape($category->getName() . ' - ' . $sub->getName()) . '</option>';
        }
    }
}

$content .= '      </select>
                                <p class="help-block">' . $addon->i18n('filepond_settings_category_notice') . '</p>
                            </div>
                        </div>
                        
                        <div class="col-sm-6">
                            <!-- Language -->
                            <div class="form-group">
                                <label for="lang">' . $addon->i18n('filepond_settings_lang') . '</label>
                                <input class="form-control" type="text" id="lang" name="lang" value="' . rex_escape(rex_config::get('filepond_uploader', 'lang', 'de_de')) . '">
                                <p class="help-block">' . $addon->i18n('filepond_settings_lang_notice') . '</p>
                            </div>
                        </div>
                    </div>
                </fieldset>
            </div>
            
            <!-- Footer -->
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
</div>

<!-- Clipboard.js for token copying -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    new ClipboardJS(".btn");
    
    // Token regeneration warning
    document.querySelector("input[name=regenerate_token]").addEventListener("change", function(e) {
        if(this.checked) {
            if(!confirm("' . rex_escape($addon->i18n('filepond_regenerate_token_confirm')) . '")) {
                this.checked = false;
            }
        }
    });
});
</script>';

// Fragment ausgeben
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', $addon->i18n('filepond_settings_title'));
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
