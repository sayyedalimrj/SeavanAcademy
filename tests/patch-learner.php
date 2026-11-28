<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');

$course = $DB->get_record('course', ['fullname' => 'CI Media Course'], '*', MUST_EXIST);
\core\session\manager::set_user(get_admin());
$user = user_create_user((object)[
    'username' => 'ci-unstarted', 'firstname' => 'CI', 'lastname' => 'Unstarted',
    'email' => 'ci-unstarted@example.invalid', 'auth' => 'manual', 'confirmed' => 1,
    'password' => 'Ci-Test-Only!2026', 'mnethostid' => $CFG->mnet_localhost_id,
], true, false);
$instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
$role = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
enrol_get_plugin('manual')->enrol_user($instance, $user, $role->id);
