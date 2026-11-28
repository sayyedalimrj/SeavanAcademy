<?php
// This file lives outside the served Moodle directory.
$statepath = getenv('SEAVAN_NATIVE_STATE');
if (!$statepath || !is_readable($statepath . '/settings.json')) {
    http_response_code(503);
    exit('Start Seavan Academy from START.cmd.');
}
$settings = json_decode(file_get_contents($statepath . '/settings.json'), true, 16, JSON_THROW_ON_ERROR);
unset($CFG);
global $CFG;
$CFG = new stdClass();
$CFG->dbtype = 'mariadb';
$CFG->dblibrary = 'native';
$CFG->dbhost = '127.0.0.1';
$CFG->dbname = 'moodle';
$CFG->dbuser = 'moodle';
$CFG->dbpass = $settings['databasePassword'];
$CFG->prefix = 'mdl_';
$CFG->dboptions = ['dbpersist' => 0, 'dbport' => 9336, 'dbsocket' => '', 'dbcollation' => 'utf8mb4_unicode_ci'];
$CFG->wwwroot = 'http://127.0.0.1:8080';
$CFG->dataroot = str_replace(chr(92), '/', $statepath) . '/data';
$CFG->seavanmediaroots = ['main' => str_replace(chr(92), '/', $settings['courseFolder'])];
$CFG->admin = 'admin';
$CFG->directorypermissions = 02770;
$CFG->lang = 'fa';
$CFG->langmenu = 0;
$CFG->langlist = 'fa';
$CFG->autolang = 0;
$CFG->registerauth = '';
$CFG->cookiehttponly = true;
$CFG->passwordpolicy = 1;
$CFG->minpasswordlength = 12;
$CFG->lockoutthreshold = 10;
$CFG->passwordchangelogout = 1;
$CFG->seavanprotectedmedia = true;
$CFG->forcelogin = 1;
$CFG->timezone = 'Asia/Tehran';
$CFG->task_scheduled_concurrency_limit = 1;
$CFG->task_adhoc_concurrency_limit = 1;
$CFG->scheduled_tasks = [
    '\\local_seavanmedia\\task\\discover' => ['disabled' => 1],
    '\\local_seavanmedia\\task\\prepare' => ['disabled' => 1],
];
unset($settings, $statepath);
require_once(__DIR__ . '/../moodle/lib/setup.php');
