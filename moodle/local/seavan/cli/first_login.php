<?php
// Local-only initial credential; never run on an existing installation to reset its administrator.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

$admin = get_admin();
if (!$admin) {
    throw new RuntimeException('No Moodle administrator found.');
}
$local = $CFG->wwwroot === 'http://127.0.0.1:8080' && getenv('SEAVAN_BIND_IP') === '127.0.0.1';
if (in_array('--initialize', $argv, true)) {
    if (!$local || getenv('SEAVAN_BOOTSTRAP_LOCAL') !== '1' || $admin->username !== 'admin' ||
            !validate_internal_user_password($admin, (string)getenv('ADMIN_PASSWORD')) ||
            get_config('local_seavan', 'localbootstrap')) {
        throw new RuntimeException('Unsafe initial administrator setup.');
    }
    $transaction = $DB->start_delegated_transaction();
    if (!get_auth_plugin('manual')->user_update_password($admin, 'admin')) {
        throw new RuntimeException('Could not initialize the administrator.');
    }
    set_user_preference('auth_forcepasswordchange', 1, $admin);
    set_config('localbootstrap', 1, 'local_seavan');
    $transaction->allow_commit();
} else if (!$local && validate_internal_user_password($admin, 'admin')) {
    throw new RuntimeException('Change the initial administrator password locally before enabling network access.');
}
