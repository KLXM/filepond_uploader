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
                <label class="control-label">' . $addon->i18n('filepond_current_token') . '</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="current-token" value="' . 
                    rex_escape(rex_config::get('filepond_uploader', 'api_token')) . 
                    '" readonly>
                </div>
                <p class="help-block">' . $addon->i18n('filepond_token_help') . '</p>
            </div>
            
            <div class="form-group">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="regenerate_token" value="1">
                        ' . $addon->i18n('filepond_regenerate_token') . '
                    </label>
                    <p class="help-block rex-warning">' . $addon->i18n('filepond_regenerate_token_warning') . '</p>
                </div>
            </div>
        </div>
    </div>
');

// Einstellungen für Uploads
$form->addFieldset($addon->i18n('filepond_upload_settings'));

$form->addRawField('<div class="row">');

// Linke Spalte
$form->addRawField('<div class="col-sm-6">');

// Maximale Anzahl Dateien
$field = $form->addInputField('number', 'max_files', null, [
    'class' => 'form-control',
    'min' => '1',
    'required' => 'required'
]);
$field->setLabel($addon->i18n('filepond_settings_max_files'));

// Maximale Dateigröße
$field = $form->addInputField('number', 'max_filesize', null, [
    'class' => 'form-control',
    'min' => '1',
    'required' => 'required'
]);
$field->setLabel($addon->i18n('filepond_settings_maxsize'));
$field->setNotice($addon->i18n('filepond_settings_maxsize_notice'));

// Chunk-Größe
$field = $form->addInputField('number', 'chunk_size', null, [
    'class' => 'form-control',
    'min' => '1',
    'required' => 'required'
]);
$field->setLabel($addon->i18n('filepond_settings_chunk_size'));
$field->setNotice($addon->i18n('filepond_settings_chunk_size_notice'));

// Chunk-Upload aktivieren/deaktivieren
$field = $form->addCheckboxField('enable_chunks');
$field->setLabel($addon->i18n('filepond_settings_enable_chunks'));
$field->addOption($addon->i18n('filepond_settings_enable_chunks_label'), 1);
$field->setNotice($addon->i18n('filepond_settings_enable_chunks_notice'));

$form->addRawField('</div>');

// Rechte Spalte
$form->addRawField('<div class="col-sm-6">');

// Maximale Pixelgröße
$field = $form->addInputField('number', 'max_pixel', null, [
    'class' => 'form-control',
    'min' => '50',
    'required' => 'required'
]);
$field->setLabel($addon->i18n('filepond_settings_max_pixel'));
$field->setNotice($addon->i18n('filepond_settings_max_pixel_notice'));

// Bildqualität
$field = $form->addInputField('number', 'image_quality', null, [
    'class' => 'form-control',
    'min' => '10',
    'max' => '100',
    'required' => 'required'
]);
$field->setLabel($addon->i18n('filepond_settings_image_quality'));
$field->setNotice($addon->i18n('filepond_settings_image_quality_notice'));

// Thumbnail erstellen
$field = $form->addCheckboxField('create_thumbnails');
$field->setLabel($addon->i18n('filepond_settings_create_thumbnails'));
$field->addOption($addon->i18n('filepond_settings_create_thumbnails_label'), 1);
$field->setNotice($addon->i18n('filepond_settings_create_thumbnails_notice'));

$form->addRawField('</div>');
$form->addRawField('</div>'); // Ende row

// Allgemeine Einstellungen
$form->addFieldset($addon->i18n('filepond_general_settings'));
$form->addRawField('<div class="row">');

// Linke Spalte
$form->addRawField('<div class="col-sm-6">');

// Sprache
$field = $form->addSelectField('lang', null, [
    'class' => 'form-control selectpicker'
]);
$field->setLabel($addon->i18n('filepond_settings_lang'));
$select = $field->getSelect();
$select->addOption('Deutsch', 'de_de');
$select->addOption('English', 'en_gb');
$field->setNotice($addon->i18n('filepond_settings_lang_notice'));

// Erlaubte Dateitypen
$field = $form->addTextAreaField('allowed_types', null, [
    'class' => 'form-control',
    'rows' => '5',
    'style' => 'font-family: monospace;'
]);
$field->setLabel($addon->i18n('filepond_settings_allowed_types'));
$field->setNotice($addon->i18n('filepond_settings_allowed_types_notice'));

$form->addRawField('</div>');

// Rechte Spalte
$form->addRawField('<div class="col-sm-6">');

// Lösung für das Medienkategorie-Problem:
// 1. Ein normales Select-Feld erstellen, das in der Konfiguration gespeichert wird
$field = $form->addSelectField('category_id', null, [
    'class' => 'form-control selectpicker'
]);
$field->setLabel($addon->i18n('filepond_settings_fallback_category'));
$field->setNotice($addon->i18n('filepond_settings_fallback_category_notice'));

// 2. Select-Optionen manuell hinzufügen
$select = $field->getSelect();
$select->addOption($addon->i18n('filepond_upload_no_category'), 0); // Option "Keine Kategorie"

// 3. Alle Medienkategorien laden und zum Select hinzufügen
$mediaCategories = rex_media_category::getRootCategories();
if (!empty($mediaCategories)) {
    // Rekursive Funktion zum Hinzufügen von Kategorien mit Einrückung
    $addCategories = function($categories, $level = 0) use (&$addCategories, $select) {
        foreach ($categories as $category) {
            // Verbesserte Einrückung für die Hierarchieebene mit klarer Darstellung
            if ($level > 0) {
                $prefix = str_repeat('· ', $level - 1) . '└─ ';
            } else {
                $prefix = '';
            }
            
            // Option zum Select hinzufügen
            $select->addOption($prefix . $category->getName(), $category->getId());
            
            // Rekursiv für Unterkategorien
            if ($children = $category->getChildren()) {
                $addCategories($children, $level + 1);
            }
        }
    };
    
    // Alle Kategorien hinzufügen, beginnend mit den Root-Kategorien
    $addCategories($mediaCategories);
}

// Meta-Dialog immer anzeigen
$field = $form->addCheckboxField('always_show_meta');
$field->setLabel($addon->i18n('filepond_settings_always_show_meta'));
$field->addOption($addon->i18n('filepond_settings_always_show_meta_label'), 1);
$field->setNotice($addon->i18n('filepond_settings_always_show_meta_notice'));

// Medienpool ersetzen
$field = $form->addCheckboxField('replace_mediapool');
$field->setLabel($addon->i18n('filepond_settings_replace_mediapool'));
$field->addOption($addon->i18n('filepond_settings_replace_mediapool'), 1);
$field->setNotice($addon->i18n('filepond_settings_replace_mediapool_notice'));

// Neue Einstellung: Meta-Dialoge bei Upload deaktivieren
$field = $form->addCheckboxField('upload_skip_meta');
$field->setLabel($addon->i18n('filepond_settings_upload_skip_meta'));
$field->addOption($addon->i18n('filepond_settings_upload_skip_meta_label'), 1);
$field->setNotice($addon->i18n('filepond_settings_upload_skip_meta_notice'));

// Neue Einstellung: Verzögerter Upload-Modus
$field = $form->addCheckboxField('delayed_upload_mode');
$field->setLabel($addon->i18n('filepond_settings_delayed_upload'));
$field->addOption($addon->i18n('filepond_settings_delayed_upload_label'), 1);
$field->setNotice($addon->i18n('filepond_settings_delayed_upload_notice'));

$form->addRawField('</div>');
$form->addRawField('</div>'); // Ende row

// Wartungsbereich
$form->addFieldset($addon->i18n('filepond_maintenance_section'));

// Button zum Aufräumen temporärer Dateien
$form->addRawField('
    <div class="form-group">
        <label class="control-label">' . $addon->i18n('filepond_maintenance_cleanup') . '</label>
        <div>
            <button type="button" class="btn btn-default" id="cleanup-temp-files">
                <i class="fa fa-trash"></i> ' . $addon->i18n('filepond_maintenance_cleanup_button') . '
            </button>
            <span id="cleanup-status" class="help-block"></span>
        </div>
        <p class="help-block">' . $addon->i18n('filepond_maintenance_cleanup_notice') . '</p>
    </div>
    
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        document.getElementById("cleanup-temp-files").addEventListener("click", function() {
            const statusEl = document.getElementById("cleanup-status");
            statusEl.textContent = "' . $addon->i18n('filepond_maintenance_cleanup_running') . '";
            
            fetch("' . rex_url::currentBackendPage() . '", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: "cleanup_temp=1"
            })
            .then(response => response.json())
            .then(data => {
                statusEl.textContent = data.message;
                setTimeout(() => {
                    statusEl.textContent = "";
                }, 5000);
            })
            .catch(error => {
                statusEl.textContent = "' . $addon->i18n('filepond_maintenance_cleanup_error') . '";
                console.error("Error:", error);
            });
        });
    });
    </script>
');

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

// AJAX-Aktion für Aufräumen temporärer Dateien
if (rex_request('cleanup_temp', 'boolean') && rex::isBackend() && rex::getUser()->isAdmin()) {
    $api = new rex_api_filepond_uploader();
    try {
        $result = $api->handleCleanup();
        rex_response::cleanOutputBuffers();
        rex_response::sendJson($result);
        exit;
    } catch (Exception $e) {
        rex_response::cleanOutputBuffers();
        rex_response::setStatus(rex_response::HTTP_INTERNAL_ERROR);
        rex_response::sendJson(['error' => $e->getMessage()]);
        exit;
    }
}

// Formular ausgeben
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', $addon->i18n('filepond_settings_title'));
$fragment->setVar('body', $form->get(), false);
echo $fragment->parse('core/page/section.php');
