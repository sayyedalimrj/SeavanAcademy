<?php
defined('MOODLE_INTERNAL') || die();
$toggle = get_config('core', 'loginpasswordtoggle');
echo $OUTPUT->render_from_template('theme_seavan/login', [
    'sitename'=>format_string($SITE->shortname),
    'output'=>$OUTPUT,
    'bodyattributes'=>$OUTPUT->body_attributes(),
    'passwordtoggle' => $toggle == TOGGLE_SENSITIVE_ENABLED || $toggle == TOGGLE_SENSITIVE_SMALL_SCREENS_ONLY,
    'passwordtogglesmallscreensonly' => $toggle == TOGGLE_SENSITIVE_SMALL_SCREENS_ONLY,
]);
