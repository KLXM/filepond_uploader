<?php

namespace FriendsOfRedaxo\QuickNavigation;

use rex_addon;
use rex_be_controller;
use rex_view;

$package = rex_addon::get('yform_filepond');
echo rex_view::title($package->i18n('yform_filepond_title'));
rex_be_controller::includeCurrentPageSubPath();
