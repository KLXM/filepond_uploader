<?php
$addon = rex_addon::get('filepond_uploader');

// Formular erstellen
$form = rex_config_form::factory('filepond_uploader');

// ============================================================================
// 1. UPLOAD-EINSTELLUNGEN
// ============================================================================
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

// Erlaubte Dateitypen
$field = $form->addTextAreaField('allowed_types', null, [
    'class' => 'form-control',
    'rows' => '4',
    'style' => 'font-family: monospace;'
]);
$field->setLabel($addon->i18n('filepond_settings_allowed_types'));
$field->setNotice($addon->i18n('filepond_settings_allowed_types_notice'));

$form->addRawField('</div>');

// Rechte Spalte
$form->addRawField('<div class="col-sm-6">');

// Chunk-Upload aktivieren/deaktivieren
$field = $form->addCheckboxField('enable_chunks');
$field->setLabel($addon->i18n('filepond_settings_enable_chunks'));
$field->addOption($addon->i18n('filepond_settings_enable_chunks_label'), 1);
$field->setNotice($addon->i18n('filepond_settings_enable_chunks_notice'));

// Chunk-Größe
$field = $form->addInputField('number', 'chunk_size', null, [
    'class' => 'form-control',
    'min' => '1',
    'required' => 'required'
]);
$field->setLabel($addon->i18n('filepond_settings_chunk_size'));
$field->setNotice($addon->i18n('filepond_settings_chunk_size_notice'));

// Verzögerter Upload-Modus
$field = $form->addCheckboxField('delayed_upload_mode');
$field->setLabel($addon->i18n('filepond_settings_delayed_upload'));
$field->addOption($addon->i18n('filepond_settings_delayed_upload_label'), 1);
$field->setNotice($addon->i18n('filepond_settings_delayed_upload_notice'));

$form->addRawField('</div>');
$form->addRawField('</div>'); // Ende row

// ============================================================================
// 2. BILDVERARBEITUNG
// ============================================================================
$form->addFieldset($addon->i18n('filepond_image_processing'));

$form->addRawField('<div class="row">');

// Linke Spalte - Grundeinstellungen
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

// EXIF-Orientierung korrigieren
$field = $form->addCheckboxField('fix_exif_orientation');
$field->setLabel($addon->i18n('filepond_settings_fix_exif_orientation'));
$field->addOption($addon->i18n('filepond_settings_fix_exif_orientation_label'), 1);
$field->setNotice($addon->i18n('filepond_settings_fix_exif_orientation_notice'));

$form->addRawField('</div>');

// Rechte Spalte - Verarbeitungsmethoden
$form->addRawField('<div class="col-sm-6">');

// Clientseitige Bildverkleinerung
$field = $form->addCheckboxField('create_thumbnails');
$field->setLabel($addon->i18n('filepond_settings_create_thumbnails'));
$field->addOption($addon->i18n('filepond_settings_create_thumbnails_label'), 1);
$field->setNotice($addon->i18n('filepond_settings_create_thumbnails_notice'));

// Serverseitige Bildverarbeitung aktivieren
$field = $form->addCheckboxField('server_image_processing');
$field->setLabel($addon->i18n('filepond_settings_server_image_processing'));
$field->addOption($addon->i18n('filepond_settings_server_image_processing_label'), 1);
$field->setNotice($addon->i18n('filepond_settings_server_image_processing_notice'));

$form->addRawField('</div>');
$form->addRawField('</div>'); // Ende row

// Erweiterte Einstellungen für kombinierte Verarbeitung (nur sichtbar wenn beide aktiv)
$form->addRawField('
<div id="combined-processing-settings" class="panel panel-default" style="margin-top: 15px; display: none;">
    <div class="panel-heading"><strong>' . $addon->i18n('filepond_settings_combined_processing') . '</strong></div>
    <div class="panel-body">
        <p class="help-block">' . $addon->i18n('filepond_settings_combined_processing_notice') . '</p>
        <div class="row">
            <div class="col-sm-6">
                <div class="form-group">
                    <label class="control-label">' . $addon->i18n('filepond_settings_client_max_pixel') . '</label>
                    <input type="number" class="form-control" name="rex_config[filepond_uploader][client_max_pixel]" 
                           value="' . rex_config::get('filepond_uploader', 'client_max_pixel', '') . '" 
                           min="50" placeholder="' . $addon->i18n('filepond_settings_use_global') . '">
                    <p class="help-block small">' . $addon->i18n('filepond_settings_client_max_pixel_notice') . '</p>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="form-group">
                    <label class="control-label">' . $addon->i18n('filepond_settings_client_image_quality') . '</label>
                    <input type="number" class="form-control" name="rex_config[filepond_uploader][client_image_quality]" 
                           value="' . rex_config::get('filepond_uploader', 'client_image_quality', '') . '" 
                           min="10" max="100" placeholder="' . $addon->i18n('filepond_settings_use_global') . '">
                    <p class="help-block small">' . $addon->i18n('filepond_settings_client_image_quality_notice') . '</p>
                </div>
            </div>
        </div>
    </div>
</div>
');

// ============================================================================
// 3. METADATEN & DIALOG-EINSTELLUNGEN
// ============================================================================
$form->addFieldset($addon->i18n('filepond_metadata_settings'));

$form->addRawField('<div class="row">');

// Linke Spalte
$form->addRawField('<div class="col-sm-6">');

// Meta-Dialog immer anzeigen
$field = $form->addCheckboxField('always_show_meta');
$field->setLabel($addon->i18n('filepond_settings_always_show_meta'));
$field->addOption($addon->i18n('filepond_settings_always_show_meta_label'), 1);
$field->setNotice($addon->i18n('filepond_settings_always_show_meta_notice'));

// Meta-Dialoge bei Upload deaktivieren
$field = $form->addCheckboxField('upload_skip_meta');
$field->setLabel($addon->i18n('filepond_settings_upload_skip_meta'));
$field->addOption($addon->i18n('filepond_settings_upload_skip_meta_label'), 1);
$field->setNotice($addon->i18n('filepond_settings_upload_skip_meta_notice'));

$form->addRawField('</div>');

// Rechte Spalte
$form->addRawField('<div class="col-sm-6">');

// Titel-Feld als Pflichtfeld
$field = $form->addCheckboxField('title_required_default');
$field->setLabel($addon->i18n('filepond_settings_title_required'));
$field->addOption($addon->i18n('filepond_settings_title_required_label'), 1);
$field->setNotice($addon->i18n('filepond_settings_title_required_notice'));

$form->addRawField('</div>');
$form->addRawField('</div>'); // Ende row

// ============================================================================
// 4. MEDIENPOOL-INTEGRATION
// ============================================================================
$form->addFieldset($addon->i18n('filepond_mediapool_settings'));

$form->addRawField('<div class="row">');

// Linke Spalte
$form->addRawField('<div class="col-sm-6">');

// Fallback Medienkategorie
$field = $form->addSelectField('category_id', null, [
    'class' => 'form-control selectpicker'
]);
$field->setLabel($addon->i18n('filepond_settings_fallback_category'));
$field->setNotice($addon->i18n('filepond_settings_fallback_category_notice'));

$select = $field->getSelect();
$select->addOption($addon->i18n('filepond_upload_no_category'), 0);

// Alle Medienkategorien laden und zum Select hinzufügen
$mediaCategories = rex_media_category::getRootCategories();
if (!empty($mediaCategories)) {
    $addCategories = function($categories, $level = 0) use (&$addCategories, $select) {
        foreach ($categories as $category) {
            if ($level > 0) {
                $prefix = str_repeat('· ', $level - 1) . '└─ ';
            } else {
                $prefix = '';
            }
            $select->addOption($prefix . $category->getName(), $category->getId());
            if ($children = $category->getChildren()) {
                $addCategories($children, $level + 1);
            }
        }
    };
    $addCategories($mediaCategories);
}

// Sprache
$field = $form->addSelectField('lang', null, [
    'class' => 'form-control selectpicker'
]);
$field->setLabel($addon->i18n('filepond_settings_lang'));
$select = $field->getSelect();
$select->addOption('Deutsch', 'de_de');
$select->addOption('English', 'en_gb');
$field->setNotice($addon->i18n('filepond_settings_lang_notice'));

$form->addRawField('</div>');

// Rechte Spalte
$form->addRawField('<div class="col-sm-6">');

// Medienpool ersetzen
$field = $form->addCheckboxField('replace_mediapool');
$field->setLabel($addon->i18n('filepond_settings_replace_mediapool'));
$field->addOption($addon->i18n('filepond_settings_replace_mediapool'), 1);
$field->setNotice($addon->i18n('filepond_settings_replace_mediapool_notice'));

// Alt-Text-Checker aktivieren
$field = $form->addCheckboxField('enable_alt_checker');
$field->setLabel($addon->i18n('filepond_settings_alt_checker'));
$field->addOption($addon->i18n('filepond_settings_alt_checker_label'), 1);
$field->setNotice($addon->i18n('filepond_settings_alt_checker_notice'));

$form->addRawField('</div>');
$form->addRawField('</div>'); // Ende row

// ============================================================================
// 5. AI ALT-TEXT GENERIERUNG (GEMINI)
// ============================================================================
$form->addFieldset($addon->i18n('filepond_ai_settings'));

$form->addRawField('<div class="row">');

// Linke Spalte - Aktivierung und API-Key
$form->addRawField('<div class="col-sm-6">');

// AI Alt-Text aktivieren
$field = $form->addCheckboxField('enable_ai_alt');
$field->setLabel($addon->i18n('filepond_settings_enable_ai_alt'));
$field->addOption($addon->i18n('filepond_settings_enable_ai_alt_label'), 1);
$field->setNotice($addon->i18n('filepond_settings_enable_ai_alt_notice'));

// Gemini API Key
$field = $form->addInputField('text', 'gemini_api_key', null, [
    'class' => 'form-control',
    'autocomplete' => 'off'
]);
$field->setLabel($addon->i18n('filepond_settings_gemini_api_key'));
$field->setNotice($addon->i18n('filepond_settings_gemini_api_key_notice'));

$form->addRawField('</div>');

// Rechte Spalte - Custom Prompt
$form->addRawField('<div class="col-sm-6">');

// Custom AI Prompt
$field = $form->addTextAreaField('ai_alt_prompt', null, [
    'class' => 'form-control',
    'rows' => '4',
    'style' => 'font-family: monospace; font-size: 12px;'
]);
$field->setLabel($addon->i18n('filepond_settings_ai_prompt'));
$field->setNotice($addon->i18n('filepond_settings_ai_prompt_notice'));

$form->addRawField('</div>');
$form->addRawField('</div>'); // Ende row

// API-Verbindungstest Button
$form->addRawField('
    <div class="form-group">
        <button type="button" class="btn btn-default" id="btn-test-ai-connection">
            <i class="fa fa-flask"></i> ' . $addon->i18n('filepond_settings_test_ai_connection') . '
        </button>
        <span id="ai-connection-result" style="margin-left: 10px;"></span>
    </div>
');

// ============================================================================
// 6. API & SICHERHEIT
// ============================================================================
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

// ============================================================================
// 6. WARTUNG
// ============================================================================
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
    
    <script nonce="' . rex_response::getNonce() . '">
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

// JavaScript für kombinierte Verarbeitungseinstellungen (nach Formularausgabe)
?>
<script nonce="<?= rex_response::getNonce() ?>">
(function() {
    function initCombinedSettings() {
        const combinedSettings = document.getElementById("combined-processing-settings");
        if (!combinedSettings) {
            return;
        }
        
        // Finde Checkboxen über ID-Teilstring
        let clientCheckbox = null;
        let serverCheckbox = null;
        
        document.querySelectorAll("input[type=checkbox]").forEach(function(cb) {
            const cbId = cb.id || "";
            const cbName = cb.name || "";
            if (cbId.includes("create-thumbnails") || cbName.includes("create_thumbnails")) {
                clientCheckbox = cb;
            }
            if (cbId.includes("server-image-processing") || cbName.includes("server_image_processing")) {
                serverCheckbox = cb;
            }
        });
        
        function toggleCombinedSettings() {
            if (clientCheckbox && serverCheckbox && combinedSettings) {
                const bothActive = clientCheckbox.checked && serverCheckbox.checked;
                combinedSettings.style.display = bothActive ? "block" : "none";
            }
        }
        
        if (clientCheckbox) clientCheckbox.addEventListener("change", toggleCombinedSettings);
        if (serverCheckbox) serverCheckbox.addEventListener("change", toggleCombinedSettings);
        
        // Initial check
        toggleCombinedSettings();
    }
    
    // AI-Verbindungstest
    function initAiTest() {
        const testBtn = document.getElementById('btn-test-ai-connection');
        const resultSpan = document.getElementById('ai-connection-result');
        
        if (!testBtn || !resultSpan) return;
        
        testBtn.addEventListener('click', function() {
            testBtn.disabled = true;
            testBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Teste...';
            resultSpan.innerHTML = '';
            
            const apiUrl = '<?= rex_url::backendController([
                'rex-api-call' => 'filepond_alt_checker',
                'action' => 'ai_test',
                '_csrf_token' => rex_csrf_token::factory('filepond_alt_checker')->getValue()
            ]) ?>';
            
            fetch(apiUrl)
                .then(r => {
                    if (!r.ok) {
                        throw new Error('HTTP ' + r.status);
                    }
                    return r.json();
                })
                .then(data => {
                    if (data && data.success) {
                        resultSpan.innerHTML = '<span class="text-success"><i class="fa fa-check"></i> ' + (data.message || 'OK') + '</span>';
                    } else {
                        resultSpan.innerHTML = '<span class="text-danger"><i class="fa fa-times"></i> ' + (data?.message || data?.error || 'Unbekannter Fehler') + '</span>';
                    }
                })
                .catch(err => {
                    resultSpan.innerHTML = '<span class="text-danger"><i class="fa fa-times"></i> Fehler: ' + err.message + '</span>';
                })
                .finally(() => {
                    testBtn.disabled = false;
                    testBtn.innerHTML = '<i class="fa fa-flask"></i> <?= $addon->i18n('filepond_settings_test_ai_connection') ?>';
                });
        });
    }
    
    // Warte auf DOM ready
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function() {
            initCombinedSettings();
            initAiTest();
        });
    } else {
        initCombinedSettings();
        initAiTest();
    }
})();
</script>
<?php
