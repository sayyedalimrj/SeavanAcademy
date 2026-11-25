<?php
require(__DIR__.'/../../config.php');require_once(__DIR__.'/lib.php');require_once(__DIR__.'/assets.php');
require_login();require_capability('moodle/site:config',context_system::instance());
$url=new moodle_url('/local/seavanmedia/queue.php');
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    require_sesskey();$id=required_param('id',PARAM_INT);$key=required_param('file',PARAM_ALPHANUM);
    $job=required_param('job',PARAM_ALPHA);$action=required_param('action',PARAM_ALPHA);
    if(!in_array($job,['convert','transcribe','hash','quality','preview'],true)){throw new moodle_exception('invalidrequest');}
    $f=local_seavanmedia_get_file(get_course($id),$key);
    if($action==='retry'){sam_queue($id,$f,$job);}
    elseif($action==='cancel'){
        $lock=\core\lock\lock_config::get_lock_factory('local_seavanmedia')->get_lock('queue-'.$job,0);
        if(!$lock){throw new moodle_exception('invalidrequest');}
        try{@unlink(sam_asset_dir($id,$key).'/'.$job.'.job.json');}finally{$lock->release();}
    }
    redirect($url);
}
$PAGE->set_context(context_system::instance());$PAGE->set_url($url);$PAGE->set_pagelayout(($CFG->theme??'')==='seavan'?'seavanworkspace':'standard');$PAGE->add_body_class('seavan-workspace');$PAGE->set_title('صف پردازش');$PAGE->set_heading('صف پردازش');
echo $OUTPUT->header();
echo html_writer::div(html_writer::link(new moodle_url('/local/seavan/index.php',['view'=>'manage']),'مدیریت ← بازگشت'),'sa-admin-back');echo html_writer::tag('h1','صف پردازش',['class'=>'sa-page-title']);
echo html_writer::tag('p','لغو فقط برای کار در انتظار است؛ توقف پردازش جاری نیازمند توقف سرویس است.');
$t=new html_table();$t->head=['دوره / شناسه فایل','عملیات','وضعیت','کنترل'];
$jobs=glob($CFG->dataroot.'/seavan-assets/*/*/*.job.json')?:[];
$results=glob($CFG->dataroot.'/seavan-assets/*/*/*.result.json')?:[];
$results=array_values(array_filter($results,fn($p)=>!is_file(str_replace('.result.json','.job.json',$p))));
$failed=count(array_filter($results,fn($p)=>(json_decode(file_get_contents($p),true)['status']??'')==='error'));
echo html_writer::div(html_writer::div(html_writer::tag('strong',count($jobs)).html_writer::tag('span','در صف یا در حال اجرا'),'sa-metric').html_writer::div(html_writer::tag('strong',count($results)-$failed).html_writer::tag('span','نتیجه ثبت‌شده بدون خطا'),'sa-metric').html_writer::div(html_writer::tag('strong',$failed).html_writer::tag('span','خطا'),'sa-metric'),'sa-metrics');
$filter=optional_param('state','all',PARAM_ALPHA);
$labels=['convert'=>'تبدیل کدک','transcribe'=>'گفتار به متن','hash'=>'بررسی تکراری','quality'=>'کیفیت کم‌حجم','preview'=>'پیش‌نمایش'];
echo html_writer::start_tag('nav',['class'=>'sa-subnav','aria-label'=>'وضعیت صف']);
foreach(['all'=>'همه','pending'=>'در انتظار','failed'=>'خطا','done'=>'نتیجه ثبت‌شده'] as $v=>$label){echo html_writer::link(new moodle_url('/local/seavanmedia/queue.php',['state'=>$v]),$label,$filter===$v?['aria-current'=>'page']:[]);}
echo html_writer::end_tag('nav');
foreach(array_slice(array_merge($jobs,$results),0,500) as $path){
    $job=explode('.',basename($path))[0];$key=basename(dirname($path));$id=(int)basename(dirname(dirname($path)));
    if(!in_array($job,['convert','transcribe','hash','quality','preview'],true)){continue;}
    $data=json_decode(file_get_contents($path),true)?:[];$pending=str_ends_with($path,'.job.json');
    $error=($data['status']??'')==='error';
    if(($filter==='pending'&&!$pending)||($filter==='failed'&&($pending||!$error))||($filter==='done'&&($pending||$error))){continue;}
    $state=$pending?'در انتظار / اجرا':($error?'خطا':(($data['status']??'')==='done'?'انجام شد':'نامشخص'));
    $label=$key;$course=$DB->get_record('course',['id'=>$id]);
    if($course){$files=local_seavanmedia_course_files($course);$label=$course->fullname.' / '.($files[$key]['name']??$key);}
    $t->data[]=[s($label),s($labels[$job]),html_writer::tag('span',$state,['class'=>'sa-status '.($pending?'sa-warn':($error?'sa-error':'sa-ok'))]),$OUTPUT->single_button(new moodle_url('/local/seavanmedia/queue.php',['id'=>$id,'file'=>$key,'job'=>$job,'action'=>$pending?'cancel':'retry','sesskey'=>sesskey()]),$pending?'لغو صف':'تلاش مجدد','post')];
}
if($t->data){echo html_writer::div(html_writer::table($t),'table-responsive');}else{echo html_writer::div('کاری در این بخش وجود ندارد.','seavan-empty');}echo $OUTPUT->footer();
