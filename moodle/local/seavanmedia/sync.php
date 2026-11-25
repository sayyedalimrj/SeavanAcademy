<?php
require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
require_login(null,false);
$ctx=context_system::instance();
require_capability('moodle/site:config',$ctx);
$url=new moodle_url('/local/seavanmedia/sync.php');
$PAGE->set_context($ctx);$PAGE->set_url($url);
$PAGE->set_title('مدیریت کتابخانه پوشه‌ای');$PAGE->set_heading('مدیریت کتابخانه پوشه‌ای');
$result=[];
if (($_SERVER['REQUEST_METHOD']??'')==='POST') {
    require_sesskey();
    $action=required_param('action',PARAM_ALPHA);
    if($action==='settings'){
        $mode=required_param('access',PARAM_ALPHA);
        if(in_array($mode,['manual','self'],true)){
            set_config('newcourseaccess',$mode,'local_seavanmedia');
            $result[]='تنظیم دسترسی دوره‌های بعدی ذخیره شد؛ دوره‌های قبلی تغییری نکردند.';
        }
    }elseif($action==='sync'){
        $result=local_seavanmedia_sync();
    }
}
echo $OUTPUT->header();
echo html_writer::div('پوشه دوره‌ها را روی هارد سرور کپی کنید. نام پوشه، عنوان دوره و زیرپوشه‌ها، فصل‌های آن هستند. سامانه هر پنج دقیقه بررسی می‌کند.','alert alert-info');
foreach($result as $line){echo html_writer::tag('p',s($line));}
$mode=get_config('local_seavanmedia','newcourseaccess')==='self'?'self':'manual';
echo html_writer::start_tag('form',['method'=>'post','action'=>$url->out(false),'class'=>'card p-3 mb-3']);
echo html_writer::empty_tag('input',['type'=>'hidden','name'=>'sesskey','value'=>sesskey()]);
echo html_writer::empty_tag('input',['type'=>'hidden','name'=>'action','value'=>'settings']);
echo html_writer::tag('h3','دسترسی دوره‌های جدید');
echo html_writer::select(['manual'=>'فقط افراد اختصاص‌یافته توسط مدیر','self'=>'ثبت‌نام آزاد همکاران دارای حساب (بدون رمز دوره)'],'access',$mode,false,['class'=>'custom-select','aria-label'=>'نوع دسترسی']);
echo html_writer::tag('p','تنظیم فقط برای دوره‌هایی است که از این پس ساخته می‌شوند. ثبت‌نام آزاد نیازمند فعال بودن افزونه خودثبت‌نام Moodle است.',['class'=>'text-muted mt-2']);
echo html_writer::tag('button','ذخیره تنظیم',['class'=>'btn btn-primary','type'=>'submit']);
echo html_writer::end_tag('form');
echo $OUTPUT->single_button(new moodle_url('/local/seavanmedia/sync.php',['action'=>'sync','sesskey'=>sesskey()]),'بررسی و ثبت دوره‌ها','post');
$folders=local_seavanmedia_folders();
echo html_writer::tag('h3','وضعیت پوشه‌ها',['class'=>'mt-4']);
if(!$folders){echo html_writer::div('پوشه‌ای در مسیر متصل‌شده پیدا نشد. مسیر هارد و دسترسی Docker را بررسی کنید.','alert alert-warning');}
$rows='';
foreach($folders as $idnumber=>$folder){
    try{
        $files=local_seavanmedia_files($folder);
        $course=$DB->get_record('course',['idnumber'=>$idnumber],'id,fullname,visible');
        $name=s(basename($folder));
        $status=$course?($course->visible?'ثبت‌شده':'پنهان‌شده'):'در انتظار ثبت';
        $link=$course?html_writer::link(new moodle_url('/course/view.php',['id'=>$course->id]),'صفحه دوره'):'—';
        $rows.=html_writer::tag('tr',html_writer::tag('td',$name).html_writer::tag('td',count($files)).html_writer::tag('td',$status).html_writer::tag('td',$link));
    }catch(\Throwable $e){$rows.=html_writer::tag('tr',html_writer::tag('td',s(basename($folder))).html_writer::tag('td','—').html_writer::tag('td','خطا در خواندن').html_writer::tag('td','—'));}
}
if($rows){echo html_writer::div(html_writer::tag('table',html_writer::tag('thead',html_writer::tag('tr',html_writer::tag('th','پوشه').html_writer::tag('th','فایل قابل استفاده').html_writer::tag('th','وضعیت').html_writer::tag('th','دوره'))).html_writer::tag('tbody',$rows),['class'=>'table table-hover']),'table-responsive');}
echo $OUTPUT->footer();
