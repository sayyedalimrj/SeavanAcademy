<?php
defined('MOODLE_INTERNAL') || die();
echo $OUTPUT->render_from_template('theme_seavan/login', [
    'sitename'=>format_string($SITE->shortname),
    'output'=>$OUTPUT,
    'bodyattributes'=>$OUTPUT->body_attributes(),
]);
