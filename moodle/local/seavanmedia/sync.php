<?php
require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
require_login(null,false);
require_capability('moodle/site:config',context_system::instance());
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/seavanmedia/sync.php'));
$PAGE->set_title('ورود خودکار دوره‌ها');$PAGE->set_heading('ورود خودکار دوره‌ها');
echo $OUTPUT->header();
echo html_writer::tag('h2','کتابخانه پوشه‌ای');
echo html_writer::tag('p','هر پوشه سطح اول در مسیر تعیین‌شده روی سرور یک دوره است. فایل‌ها کپی نمی‌شوند. هر پنج دقیقه پوشه‌ها بررسی می‌شوند. دوره‌های جدید برای دسترسی از روش‌های عضویت Moodle استفاده می‌کنند.');
if(optional_param('run',0,PARAM_BOOL)){
    require_sesskey();
    foreach(local_seavanmedia_sync() as $line){echo html_writer::tag('p',s($line));}
}
echo $OUTPUT->single_button(new moodle_url('/local/seavanmedia/sync.php',['run'=>1,'sesskey'=>sesskey()]),'بررسی و ثبت دوره‌ها','post');
echo $OUTPUT->footer();
