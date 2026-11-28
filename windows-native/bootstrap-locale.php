<?php
// The portable package already contains the translations. No network fetch is needed.
define('CLI_SCRIPT', true);
require __DIR__ . '/../moodle/config.php';
if (is_readable($CFG->dataroot . '/lang/fa/langconfig.php')) {
    $DB->set_field('user', 'lang', 'fa', ['id' => get_admin()->id, 'lang' => 'en']);
    set_config('lang', 'fa');
}
