<?php
defined('MOODLE_INTERNAL') || die();
function theme_seavan_pre_scss($theme) {
    return '$primary: #176b60;' . "\n" . '$body-bg: #f4f7f6;' . "\n" .
        '$body-color: #203831;' . "\n" . '$border-radius: .75rem;' . "\n";
}
function theme_seavan_extra_scss($theme) {
    global $CFG;
    $css = '';
    foreach (['IRANSansWeb.woff2' => 400, 'IRANSansWeb_Bold.woff2' => 700] as $file => $weight) {
        if (is_readable(__DIR__ . '/fonts/' . $file)) {
            $url = $CFG->wwwroot . '/theme/seavan/fonts/' . $file;
            $css .= "@font-face {font-family: SeavanIRANSans; src: url('$url') format('woff2'); font-weight:$weight; font-display:swap;}\n";
        }
    }
    return $css . file_get_contents(__DIR__ . '/style/seavan.css');
}
