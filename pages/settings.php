<?php
$addon = rex_addon::get('filepond_uploader');

$form = new rex_config_form($addon->getName());

$field = $form->addInputField('number', 'max_files', null, ["class" => "form-control"]);
$field->setLabel(rex_i18n::msg('filepond_settings_max_files'));
$field->setAttribute('min', '1');

$field = $form->addInputField('number', 'max_filesize', null, ["class" => "form-control"]);
$field->setLabel(rex_i18n::msg('filepond_settings_maxsize'));
$field->setNotice(rex_i18n::msg('filepond_settings_maxsize_notice'));
$field->setAttribute('min', '1');

$field = $form->addInputField('text', 'allowed_types', null, ["class" => "form-control"]);
$field->setLabel(rex_i18n::msg('filepond_settings_allowed_types'));

$field = $form->addInputField('number', 'category_id', null, ["class" => "form-control"]);
$field->setLabel(rex_i18n::msg('filepond_settings_category_id'));
$field->setAttribute('min', '0');

$field = $form->addInputField('text', 'lang', null, ["class" => "form-control"]);
$field->setLabel(rex_i18n::msg('filepond_settings_lang'));

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', $addon->i18n('filepond_settings_title'));
$fragment->setVar('body', $form->get(), false);
echo $fragment->parse('core/page/section.php');