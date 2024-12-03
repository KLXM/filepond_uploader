<?php

$package = rex_addon::get('filepond_uploader');
echo rex_view::title($package->i18n('filepond_uploader_title'));
rex_be_controller::includeCurrentPageSubPath();
