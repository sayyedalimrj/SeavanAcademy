<?php
require(__DIR__.'/../../config.php');require_once(__DIR__.'/lib.php');
require_login();require_capability('moodle/site:config',context_system::instance());
$PAGE->set_context(context_system::instance());$PAGE->set_url(new moodle_url('/local/seavanmedia/server.php'));$PAGE->set_title('سلامت سرور');$PAGE->set_heading('سلامت سرور');
echo $OUTPUT->header();echo html_writer::tag('h2','سلامت سرور');
$t=new html_table();$t->head=['منبع','وضعیت','فضای خالی گیگابایت'];
foreach(sam_roots()+['data'=>$CFG->dataroot] as $label=>$p){
    $ok=is_dir($p)&&is_readable($p);$free=$ok?disk_free_space($p):false;
    $t->data[]=[s($label),$ok?'قابل خواندن':'قطع / پیکربندی نشده',$free===false?'—':round($free/1073741824,1)];
}
echo html_writer::table($t);
$cpu=is_readable('/proc/loadavg')?trim(file_get_contents('/proc/loadavg')):'';
$mem=is_readable('/sys/fs/cgroup/memory.current')?trim(file_get_contents('/sys/fs/cgroup/memory.current')):'';
if($cpu){echo html_writer::tag('p','Load average (Linux): '.s(explode(' ',$cpu)[0]));}
if(is_numeric($mem)){echo html_writer::tag('p','حافظه مصرفی کانتینر: '.round((float)$mem/1048576).' MB');}
echo html_writer::tag('p','دیتابیس: فعال · نسخه PHP: '.PHP_VERSION.' · نسخه Moodle: '.s($CFG->release));
$last=$DB->get_field_sql('SELECT MAX(lastruntime) FROM {task_scheduled}');
echo html_writer::tag('p','آخرین اجرای ثبت‌شده وظایف: '.($last?userdate($last):'ثبت نشده'));
echo html_writer::tag('p','در صورت قطع هارد دوره و سابقه پاک نمی‌شوند. پوشه خالی ممکن است ناشی از اتصال نادرست باشد.');
echo $OUTPUT->footer();
