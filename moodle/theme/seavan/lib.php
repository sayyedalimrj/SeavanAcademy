<?php
defined('MOODLE_INTERNAL') || die();
function theme_seavan_pre_scss($theme) {
    return '$primary: #176b60;' . "\n" . '$body-bg: #f4f7f6;' . "\n" .
        '$body-color: #203831;' . "\n" . '$border-radius: .75rem;' . "\n" .
        '$font-family-sans-serif: SeavanIRANSans, IRANSansWeb, IRANSans, Tahoma, Arial, sans-serif;' . "\n";
}
function theme_seavan_extra_scss($theme) {
    global $CFG;
    $css = '';
    foreach (['fontregular'=>[400,'IRANSansWeb'], 'fontbold'=>[700,'IRANSansWeb_Bold']] as $area=>[$weight,$filename]) {
        $url = $theme->setting_file_url($area, $area);
        if (!$url) {
            foreach (['woff2','woff'] as $ext) {
                if (is_readable(__DIR__ . '/fonts/' . $filename . '.' . $ext)) {
                    $url = $CFG->wwwroot . '/theme/seavan/fonts/' . $filename . '.' . $ext;
                    break;
                }
            }
        }
        if ($url) {
            $url = str_replace(["'", "\n", "\r", "\\"], ['%27','','','%5C'], $url);
            $css .= "@font-face {font-family: SeavanIRANSans; src: url('$url'); font-weight:$weight; font-display:swap;}\n";
        }
    }
    return $css . file_get_contents(__DIR__ . '/style/seavan.css');
}
function theme_seavan_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel !== CONTEXT_SYSTEM || !in_array($filearea, ['fontregular','fontbold'], true)) {
        send_file_not_found();
    }
    $theme = theme_config::load('seavan');
    return $theme->setting_file_serve($filearea, $args, false, $options);
}
