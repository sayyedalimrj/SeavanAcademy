<?php
if (PHP_SAPI !== 'cli') {
    exit(1);
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$state = getenv('SEAVAN_NATIVE_STATE');
$config = json_decode(file_get_contents($state . '/settings.json'), true, 16, JSON_THROW_ON_ERROR);
if (($argv[1] ?? '') === 'provision') {
    $db = new mysqli('127.0.0.1', 'root', $config['rootPassword'], null, 9336);
    $password = $db->real_escape_string($config['databasePassword']);
    $db->query('CREATE DATABASE IF NOT EXISTS moodle CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $db->query("CREATE USER IF NOT EXISTS 'moodle'@'127.0.0.1' IDENTIFIED BY '$password'");
    $db->query("GRANT ALL ON moodle.* TO 'moodle'@'127.0.0.1'");
} else {
    $db = new mysqli('127.0.0.1', 'moodle', $config['databasePassword'], 'moodle', 9336);
    $tables = $db->query('SHOW TABLES');
    echo $tables->num_rows ? 'existing' : 'empty';
}
