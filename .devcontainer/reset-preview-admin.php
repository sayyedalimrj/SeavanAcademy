<?php
// Runs through PHP CLI inside the existing preview container.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('CLI_SCRIPT', true);
require '/var/www/html/config.php';
require_once($CFG->libdir . '/authlib.php');

$administrator = get_complete_user_data('username', 'admin', $CFG->mnet_localhost_id);
if (!$administrator || !is_siteadmin($administrator->id) || $administrator->auth !== 'manual' ||
        $administrator->deleted || $administrator->suspended || !$administrator->confirmed) {
    error_log("The preview needs an active local admin account. No account was changed.");
    exit(1);
}

$password = getenv('SEAVAN_TEST_PASSWORD');
$error = '';
if ($password === false || !check_password_policy($password, $error, $administrator)) {
    error_log("Generated password rejected by Moodle policy.");
    exit(1);
}

$transaction = $DB->start_delegated_transaction();
if (!get_auth_plugin('manual')->user_update_password($administrator, $password)) {
    throw new RuntimeException('Moodle could not update the administrator password.');
}
$updated = $DB->get_record('user', ['id' => $administrator->id], '*', MUST_EXIST);
if (!validate_internal_user_password($updated, $password)) {
    throw new RuntimeException('Moodle could not verify the updated administrator password.');
}
login_unlock_account($updated);
unset_user_preference('auth_forcepasswordchange', $updated->id);
$transaction->allow_commit();
\core\session\manager::kill_user_sessions($updated->id);
echo "Admin password updated and verified by Moodle.\n";
