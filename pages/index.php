<?php

namespace FriendsOfRedaxo\QuickNavigation;

use rex_addon;
use rex_be_controller;
use rex_view;

$package = rex_addon::get('filepond_uploader');
echo rex_view::title($package->i18n('filepond_uploader_title'));
rex_be_controller::includeCurrentPageSubPath();
