<?php
require(__DIR__.'/../../config.php');require_login();
require_capability('report/log:view',context_system::instance());
$PAGE->set_context(context_system::instance());$PAGE->set_url(new moodle_url('/local/seavanmedia/report.php'));
$PAGE->set_pagelayout(($CFG->theme??'')==='seavan'?'seavanworkspace':'standard');$PAGE->add_body_class('seavan-workspace');$PAGE->set_title('گزارش آموزش');$PAGE->set_heading('گزارش آموزش');
echo $OUTPUT->header();
echo html_writer::div(html_writer::link(new moodle_url('/local/seavan/index.php',['view'=>'manage']),'مدیریت ← بازگشت'),'sa-admin-back');echo html_writer::start_div('seavan-shell');echo html_writer::tag('h1','گزارش آموزش',['class'=>'seavan-section']);
echo html_writer::tag('p','خلاصه استفاده ثبت‌شده؛ این اعداد اثبات حضور یا توجه کاربر نیستند.',['class'=>'seavan-note']);
$sql='SELECT c.id,c.fullname,COUNT(DISTINCT p.userid) AS learners,COUNT(p.id) AS sessions,SUM(CASE WHEN p.percent >= 95 THEN 1 ELSE 0 END) AS completed,MAX(p.timemodified) AS latest FROM {course} c JOIN {local_seavanmedia_progress} p ON p.courseid=c.id GROUP BY c.id,c.fullname ORDER BY learners DESC,c.id';
$table=new html_table();$table->head=['دوره','کاربران فعال','جلسه-کاربر ثبت‌شده','دیده‌شده','آخرین فعالیت'];
foreach($DB->get_records_sql($sql) as $row){$table->data[]=[s($row->fullname),$row->learners,$row->sessions,$row->completed,userdate($row->latest)];}
if($table->data){echo html_writer::div(html_writer::table($table),'table-responsive');}else{echo html_writer::div('هنوز سابقه تماشای آموزش ثبت نشده است.','seavan-empty');}echo html_writer::link(new moodle_url('/report/log/index.php',['id'=>SITEID]),'گزارش کامل Moodle',['class'=>'btn btn-outline-primary mt-3']);echo html_writer::end_div();echo $OUTPUT->footer();
