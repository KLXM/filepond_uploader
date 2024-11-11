<?php

rex_yform::addTemplatePath($this->getPath('ytemplates'));

if (rex::isBackend() && rex::getUser()) {
    filepond_helper::getStyles();
    filepond_helper::getScripts();
}
