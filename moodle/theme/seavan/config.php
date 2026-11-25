<?php
defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../boost/config.php');
require_once(__DIR__ . '/lib.php');
$THEME->name = 'seavan';
$THEME->parents = ['boost'];
$THEME->prescsscallback = 'theme_seavan_pre_scss';
$THEME->extrascsscallback = 'theme_seavan_extra_scss';
$THEME->precompiledcsscallback = null;
