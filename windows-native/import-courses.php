<?php
define('CLI_SCRIPT', true);
require __DIR__ . '/../moodle/config.php';
require_once $CFG->dirroot . '/local/seavanmedia/lib.php';
\core\session\manager::set_user(get_admin());
foreach (local_seavanmedia_sync() as $line) {
    mtrace($line);
}
