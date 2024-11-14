<?php
$addon = rex_addon::get('filepond_uploader');

// Formular erstellen
$form = rex_config_form::factory('filepond_uploader');

// API Token Bereich
$form->addFieldset($addon->i18n('filepond_token_section'));

$form->addRawField('
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
');

// Allgemeine Einstellungen
$form->addFieldset($addon->i18n('filepond_general_settings'));

// Maximale Anzahl Dateien
$field = $form->addInputField('number', 'max_files', null, ['class' => 'form-control', 'min' => '1', 'required' => 'required']);
$field->setLabel($addon->i18n('filepond_settings_max_files'));

// Maximale Dateigröße
$field = $form->addInputField('number', 'max_filesize', null, ['class' => 'form-control', 'min' => '1', 'required' => 'required']);
$field->setLabel($addon->i18n('filepond_settings_maxsize'));
$field->setNotice($addon->i18n('filepond_settings_maxsize_notice'));

// Erlaubte Dateitypen
$field = $form->addInputField('text', 'allowed_types', null, ['class' => 'form-control']);
$field->setLabel($addon->i18n('filepond_settings_allowed_types'));
$field->setNotice($addon->i18n('filepond_settings_allowed_types_notice'));

// Medien-Kategorie
$mediaSelect = new rex_media_category_select();
$mediaSelect->setName('category_id');
$mediaSelect->setId('category_id');
$mediaSelect->setSize(1);
$mediaSelect->setAttribute('class', 'form-control selectpicker');
$mediaSelect->setSelected(rex_config::get('filepond_uploader', 'category_id', 0));
$mediaSelect->addOption($addon->i18n('filepond_upload_no_category'), 0);

$form->addRawField('<div class="form-group">
    <label for="category_id">' . $addon->i18n('filepond_settings_category_id') . '</label>
    ' . $mediaSelect->get() . '
    <p class="help-block">' . $addon->i18n('filepond_settings_category_notice') . '</p>
</div>');

// Sprache
$field = $form->addSelectField('lang', null, ['class' => 'form-control selectpicker']);
$field->setLabel($addon->i18n('filepond_settings_lang'));
$select = $field->getSelect();
$select->addOption('Deutsch', 'de_de');
$select->addOption('English', 'en_gb');
$field->setNotice($addon->i18n('filepond_settings_lang_notice'));

// Medienpool ersetzen
$field = $form->addCheckboxField('replace_mediapool');
$field->setLabel($addon->i18n('filepond_settings_replace_mediapool'));
$field->addOption($addon->i18n('filepond_settings_replace_mediapool'), 1);
$field->setNotice($addon->i18n('filepond_settings_replace_mediapool_notice'));

// Token Regenerierung behandeln
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

// Formular ausgeben
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', $addon->i18n('filepond_settings_title'));
$fragment->setVar('body', $form->get(), false);
echo $fragment->parse('core/page/section.php');
