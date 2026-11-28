<?php
unset($CFG);
global $CFG;
$CFG = new stdClass();
$CFG->dbtype = 'mariadb';
$CFG->dblibrary = 'native';
$CFG->dbhost = 'db';
$CFG->dbname = 'moodle';
$CFG->dbuser = 'moodle';
$CFG->dbpass = getenv('DB_PASSWORD');
$CFG->prefix = 'mdl_';
$CFG->dboptions = ['dbpersist' => 0, 'dbport' => 3306, 'dbsocket' => '', 'dbcollation' => 'utf8mb4_unicode_ci'];
$CFG->wwwroot = getenv('SITE_URL');
if (getenv('SSL_PROXY') === '1') {
    // Codespaces terminates HTTPS outside Apache and may rewrite the Host header.
    // Use the fixed configured origin, never a host supplied by the client.
    $origin = parse_url($CFG->wwwroot);
    if (($origin['scheme'] ?? '') !== 'https' || empty($origin['host'])) {
        throw new RuntimeException('SSL_PROXY requires a valid HTTPS SITE_URL');
    }
    $CFG->sslproxy = true;
    $CFG->cookiesecure = true;
    $CFG->cookiehttponly = true;
    // Use an isolated host-only session cookie for forwarded Codespaces previews.
    // Never disable login tokens or accept untrusted forwarded host headers.
    if (str_ends_with($origin['host'], '.app.github.dev') || str_ends_with($origin['host'], '.preview.test')) {
        $CFG->sessioncookie = 'SeavanPreviewV2';
        $CFG->sessioncookiedomain = '';
        $CFG->sessioncookiepath = '/';
    }
    if (PHP_SAPI !== 'cli') {
        $_SERVER['HTTP_HOST'] = $origin['host'] . (isset($origin['port']) ? ':' . $origin['port'] : '');
        $_SERVER['SERVER_NAME'] = $origin['host'];
        $_SERVER['SERVER_PORT'] = $origin['port'] ?? 443;
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['REQUEST_SCHEME'] = 'https';
    }
}
// Docker maps host port 8080 to Apache port 80. Preserve the explicit canonical port
// only when the request Host matches the configured host and port.
if (PHP_SAPI !== 'cli' && getenv('SSL_PROXY') !== '1') {
    $origin = parse_url($CFG->wwwroot);
    $expected = ($origin['host'] ?? '') . (isset($origin['port']) ? ':' . $origin['port'] : '');
    if (isset($origin['port']) && strcasecmp($_SERVER['HTTP_HOST'] ?? '', $expected) === 0) {
        $_SERVER['SERVER_PORT'] = $origin['port'];
    }
}
$CFG->dataroot = '/var/moodledata';
$CFG->admin = 'admin';
$CFG->directorypermissions = 02770;
$CFG->lang = 'fa';
$CFG->langmenu = 0;
$CFG->langlist = 'fa';
$CFG->autolang = 0;
$CFG->registerauth = '';
$CFG->cookiesecure = ($CFG->cookiesecure ?? false);
$CFG->cookiehttponly = true;
$CFG->passwordpolicy = 1;
$CFG->minpasswordlength = 12;
$CFG->lockoutthreshold = 10;
$CFG->passwordchangelogout = 1;
$CFG->seavanprotectedmedia = getenv('SEAVAN_PROTECTED_MEDIA') === '1';
$CFG->forcelogin = 1;
$CFG->timezone = 'Asia/Tehran';
require_once(__DIR__ . '/lib/setup.php');
