<?php
// Ausgewählte Kategorie hat Vorrang vor der Einstellung aus der Config
$selectedCategory = rex_request('category_id', 'int', 0);

$selMedia = new rex_media_category_select($checkPerm = true);
$selMedia->setId('rex-mediapool-category');
$selMedia->setName('category_id');
$selMedia->setSize(1);
$selMedia->setSelected($selectedCategory);
$selMedia->setAttribute('class', 'selectpicker');
$selMedia->setAttribute('data-live-search', 'true');
if (rex::getUser()->getComplexPerm('media')->hasAll()) {
    $selMedia->addOption(rex_i18n::msg('filepond_upload_no_category'), '0');
}

$currentUser = rex::getUser();
$langCode = $currentUser ? $currentUser->getLanguage() : rex_config::get('filepond_uploader', 'lang', 'en_gb');

$content = '
<div class="rex-form">
    <form action="' . rex_url::currentBackendPage() . '" method="post" class="form-horizontal">
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
                            data-filepond-maxfiles="'.rex_config::get('filepond_uploader', 'max_files', 30).'"
                            data-filepond-types="'.rex_config::get('filepond_uploader', 'allowed_types', 'image/*,video/*,.pdf,.doc,.docx,.txt').'"
                            data-filepond-maxsize="'.rex_config::get('filepond_uploader', 'max_filesize', 10).'"
                            data-filepond-lang="'.$langCode.'"
                            value=""
                        >
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
';?>
<script>
$(document).on("rex:ready", function() {
    $("#rex-mediapool-category").on("change", function() {
        const newCategory = $(this).val();
        const $input = $("#filepond-upload");
        $input.attr("data-filepond-cat", newCategory);
        
        const pondElement = document.querySelector("#filepond-upload");
        if (pondElement && pondElement.FilePond) {
            pondElement.FilePond.removeFiles();
            // FilePond neu initialisieren
            document.dispatchEvent(new Event('filepond:init'));
        }
    });
});
</script>
<?php 
// Fragment ausgeben
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', rex_i18n::msg('filepond_upload_title'));
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
