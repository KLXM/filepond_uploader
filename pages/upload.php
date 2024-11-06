<?php
$csrf = rex_csrf_token::factory('filepond_uploader');

// Ausgewählte Kategorie
$selectedCategory = rex_request('category_id', 'int', 0);

$selMedia = new rex_media_category_select($checkPerm = true);
$selMedia->setId('rex-mediapool-category');
$selMedia->setName('category_id');
$selMedia->setSize(1);
$selMedia->setSelected($selectedCategory);
$selMedia->setAttribute('class', 'selectpicker');
$selMedia->setAttribute('data-live-search', 'true');
if (rex::requireUser()->getComplexPerm('media')->hasAll()) {
    $selMedia->addOption(rex_i18n::msg('filepond_upload_no_category'), '0');
}

$content = '';
$success = '';
$error = '';

$currentUser = rex::getUser();

$lng = 'en';
// Prüfen, ob ein Backend-User eingeloggt ist
if ($currentUser) {
    $langCode = $currentUser->getLanguage();
} 
$content .= '
<div class="rex-form">
    <form action="' . rex_url::currentBackendPage() . '" method="post" class="form-horizontal">
        ' . $csrf->getHiddenField() . '
        <div class="panel panel-default">
            <div class="panel-heading">
                <div class="panel-title">' . rex_i18n::msg('filepond_upload_title') . '</div>
            </div>
            
            <div class="panel-body">
                <div class="form-group">
                    <label class="col-sm-2 control-label">' . rex_i18n::msg('filepond_upload_category') . '</label>
                    <div class="col-sm-10">
                        '.$selMedia->get().'
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="col-sm-2 control-label">' . rex_i18n::msg('filepond_upload_files') . '</label>
                    <div class="col-sm-10">
                        <input type="hidden" 
                            id="filepond-upload"
                            data-widget="filepond"
                            data-filepond-cat="'.$selectedCategory.'"
                            data-filepond-maxfiles="1000"
                            data-filepond-maxsize="'.$settings['max_filesize'].'"
                            data-filepond-lang="'. $langCode .'"
                            value=""
                        >
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
$(document).on("rex:ready", function() {
    $("#rex-mediapool-category").on("change", function() {
        const newCategory = $(this).val();
        $("#filepond-upload").attr("data-filepond-cat", newCategory);
        
        const pondElement = document.querySelector("#filepond-upload");
        if (pondElement && pondElement.FilePond) {
            pondElement.FilePond.removeFiles();
        }
    });
});
</script>';

// Fragment ausgeben
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', rex_i18n::msg('filepond_upload_title'));
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');