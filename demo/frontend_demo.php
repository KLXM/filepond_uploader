<?php

declare(strict_types=1);

$addon = rex_addon::get('filepond_uploader');

if (!$addon->isAvailable()) {
    echo '<p>Das AddOn filepond_uploader ist nicht installiert oder nicht aktiviert.</p>';
    return;
}

$boolConfig = static function (string $key, bool $default): bool {
    $raw = rex_config::get('filepond_uploader', $key, $default ? '1' : '0');
    return in_array($raw, [1, '1', true, 'true', '|1|'], true);
};

$langCode = rex_clang::getCurrentId() === 1 ? 'de_de' : 'en_gb';

$allowedTypes = (string) rex_config::get('filepond_uploader', 'allowed_types', 'image/*');
$maxFiles = (string) (int) rex_config::get('filepond_uploader', 'max_files', 5);
$maxFilesize = (string) (int) rex_config::get('filepond_uploader', 'max_filesize', 10);
$categoryId = (string) (int) rex_config::get('filepond_uploader', 'category_id', 0);

$titleRequired = $boolConfig('title_required_default', false);
$altRequired = $boolConfig('alt_required_default', true);
$aiEnabled = $boolConfig('enable_ai_alt', false) && $boolConfig('enable_ai_upload_modal', true);

$aiTargetFieldRaw = (string) rex_config::get('filepond_uploader', 'ai_target_field', 'med_alt');
$aiTargetField = '' !== trim($aiTargetFieldRaw) ? trim($aiTargetFieldRaw) : 'med_alt';

$apiTokenConfig = rex_config::get('filepond_uploader', 'api_token');
$apiToken = is_string($apiTokenConfig) ? trim($apiTokenConfig) : '';

// Für Frontend-Demos ohne Login den konfigurierten Token in die Session spiegeln,
// damit api_filepond::isAuthorized() greift.
if ('' !== $apiToken) {
    rex_set_session('filepond_token', $apiToken);
}

$isBackendUser = null !== rex_backend_login::createUser();
$isYComUser = false;
if (rex_plugin::get('ycom', 'auth')->isAvailable()) {
    /** @phpstan-ignore class.notFound */
    $isYComUser = null !== rex_ycom_auth::getUser();
}

$demoHasUploadAuth = $isBackendUser || $isYComUser || '' !== $apiToken;

$submittedFiles = trim((string) rex_post('demo_filepond_files', 'string', ''));

echo filepond_helper::getStyles();


?>
<div class="container" style="max-width: 900px; margin: 40px auto; padding: 0 15px;">
    <h1>FilePond Frontend-Demo</h1>
    <p>
        Diese Seite ist eine echte Frontend-Referenz für das AddOn.
        Sie lädt alle benötigten Assets automatisch und nutzt dieselben data-Attribute wie das YForm-Template.
    </p>

    <?php if (!$demoHasUploadAuth): ?>
        <div class="alert alert-warning" style="margin-bottom: 15px;">
            Upload-Autorisierung fehlt: Bitte YCom-Login verwenden oder in den AddOn-Einstellungen einen API-Token setzen.
        </div>
    <?php endif; ?>

    <form method="post" action="" class="filepond-demo-form">
        <div class="form-group filepond-demo-uploader" id="demo-filepond-wrapper">
            <label class="control-label" for="demo-filepond-upload">Upload</label>
            <input
                id="demo-filepond-upload"
                type="hidden"
                name="demo_filepond_files"
                value="<?= rex_escape($submittedFiles) ?>"
                data-widget="filepond"
                data-filepond-cat="<?= rex_escape($categoryId) ?>"
                data-filepond-maxfiles="<?= rex_escape($maxFiles) ?>"
                data-filepond-types="<?= rex_escape($allowedTypes) ?>"
                data-filepond-maxsize="<?= rex_escape($maxFilesize) ?>"
                data-filepond-lang="<?= rex_escape($langCode) ?>"
                data-filepond-skip-meta="false"
                data-filepond-chunk-enabled="false"
                data-filepond-chunk-size="1048576"
                data-filepond-delayed-upload="false"
                data-filepond-delayed-type="0"
                data-filepond-title-required="<?= $titleRequired ? 'true' : 'false' ?>"
                data-filepond-alt-required="<?= $altRequired ? 'true' : 'false' ?>"
                data-filepond-max-pixel="2100"
                data-filepond-image-quality="90"
                data-filepond-client-resize="false"
                data-filepond-ai-enabled="<?= $aiEnabled ? 'true' : 'false' ?>"
                data-filepond-ai-target-field="<?= rex_escape($aiTargetField) ?>"
            >
            <p class="help-block">Der Uploader wird per JavaScript direkt in diesem Wrapper initialisiert.</p>
        </div>

        <div style="margin-top: 18px;">
            <button type="submit" class="btn btn-primary">Formular senden</button>
        </div>
    </form>

    <?php if ('' !== $submittedFiles): ?>
        <hr>
        <h3>Gesendeter Wert</h3>
        <p>So kommt der Wert im Formular an (kommagetrennte Dateinamen):</p>
        <pre><?= rex_escape($submittedFiles) ?></pre>
    <?php endif; ?>

    <hr>
    <h3>Einbau in dein Projekt</h3>
    <ol>
        <li>Datei in dein Projekt kopieren, z. B. als Template- oder Modul-Output.</li>
        <li>Wichtig: <code>echo filepond_helper::getStyles();</code> und <code>echo filepond_helper::getScripts();</code> müssen vorhanden sein.</li>
        <li>Hidden-Input mit <code>data-widget="filepond"</code> verwenden.</li>
        <li>API-Aufrufe laufen über <code>/redaxo/index.php?rex-api-call=...</code> und benötigen ein laufendes REDAXO.</li>
    </ol>
</div>

<?php echo filepond_helper::getScripts();