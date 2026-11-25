<?php
require(__DIR__.'/../../config.php');require_once(__DIR__.'/lib.php');require_once(__DIR__.'/assets.php');
require_login();require_capability('moodle/site:config',context_system::instance());
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    require_sesskey();$id=required_param('id',PARAM_INT);$course=get_course($id);
    $f=local_seavanmedia_get_file($course,required_param('file',PARAM_ALPHANUM));
    $job=required_param('job',PARAM_ALPHA);
    if(!in_array($job,['convert','transcribe','preview','quality'],true)){throw new moodle_exception('invalidrequest');}
    sam_queue($id,$f,$job);
    redirect(new moodle_url('/local/seavanmedia/health.php'), 'در صف پردازش قرار گرفت.');
}
$PAGE->set_context(context_system::instance());$PAGE->set_url(new moodle_url('/local/seavanmedia/health.php'));
$PAGE->set_pagelayout(($CFG->theme??'')==='seavan'?'seavanworkspace':'standard');$PAGE->add_body_class('seavan-workspace');$PAGE->set_title('سلامت کتابخانه');$PAGE->set_heading('سلامت کتابخانه');
echo $OUTPUT->header();
echo html_writer::div(html_writer::link(new moodle_url('/local/seavan/index.php',['view'=>'manage']),'مدیریت ← بازگشت'),'sa-admin-back');echo html_writer::start_div('seavan-shell');
echo html_writer::tag('h1','سلامت کتابخانه',['class'=>'seavan-section']);

$rows=[];$issues=[];$total=0;$pending=0;$media=[];
foreach(local_seavanmedia_folders() as $number=>$folder){
    $c=$DB->get_record('course',['idnumber'=>$number],'id,fullname');if(!$c){continue;}
    $counts=['ok'=>0,'error'=>0,'pending'=>0,'convert'=>0];$seconds=0;
    foreach(local_seavanmedia_files($folder) as $f){
        if(!in_array(strtolower(pathinfo($f['name'],PATHINFO_EXTENSION)),['mp4','m4v','webm','mov','mkv','avi','wmv','ogv','mp3','m4a','wav','ogg','flac'],true)){continue;}
        $total++;$m=sam_metadata($c->id,$f['key']);$converted=is_file(sam_asset_dir($c->id,$f['key']).'/converted.mp4');
        $state=!$m?'pending':($m['status']!=='ok'?'error':(!empty($m['compatible'])||$converted?'ok':'convert'));$counts[$state]++;$seconds+=(float)($m['duration']??0);
        if(in_array($state,['error','convert'],true)&&count($issues)<100){$issues[]=[$c,$f,$state];}
        if($state!=='error'){$media[]=[$c,$f,$state];}
    }
    $pending+=$counts['pending'];
    $rows[]=[$c->fullname,$counts['ok'],$counts['pending'],$counts['convert'],$counts['error'],round($seconds/3600,1)];
}
echo html_writer::tag('p','فایل رسانه‌ای: '.$total.' · در انتظار بررسی: '.$pending.' — بررسی سریع کانتینر و کدک، نه آزمایش کامل تمام فریم‌ها.');
$table=new html_table();$table->head=['دوره','آماده','در انتظار','نیازمند بررسی کدک','خطای خواندن','ساعت شناسایی‌شده'];foreach($rows as $r){$r[0]=s($r[0]);$table->data[]=$r;}echo html_writer::table($table);
if($issues){echo html_writer::tag('h2','فایل‌های نیازمند بررسی (حداکثر ۱۰۰ مورد)');
foreach($issues as [$c,$f,$state]){
    $dir=sam_asset_dir($c->id,$f['key']);
    echo html_writer::div(html_writer::tag('strong',s($f['name'])).html_writer::tag('p',s($c->fullname).' — '.($state==='error'?'خطای خواندن':'کدک یا ظرف ناسازگار')).($state==='convert'?$OUTPUT->single_button(new moodle_url('/local/seavanmedia/health.php',['id'=>$c->id,'file'=>$f['key'],'job'=>'convert','sesskey'=>sesskey()]),is_file($dir.'/convert.job.json')?'در صف (ثبت مجدد)':'ساخت نسخه سازگار','post'):''),'card p-3 mb-2');
}}
echo html_writer::start_tag('details',['class'=>'sa-advanced']+(optional_param('q','',PARAM_TEXT)!==''?['open'=>'open']:[]));
echo html_writer::tag('summary','پردازش انتخابی فیلم (Whisper، کیفیت و پیش‌نمایش)');
echo html_writer::tag('p','برای پردازش، سرویس اختیاری Whisper را راه‌اندازی کنید. فقط فایل انتخابی پردازش می‌شود.');
$q=trim(optional_param('q','',PARAM_TEXT));
echo html_writer::start_tag('form',['method'=>'get','action'=>$PAGE->url->out(false)]);
echo html_writer::empty_tag('input',['name'=>'q','value'=>$q,'placeholder'=>'نام فیلم یا دوره','class'=>'form-control']);echo html_writer::tag('button','جست‌وجو',['class'=>'btn btn-outline-primary']);echo html_writer::end_tag('form');
$n=0;foreach($media as [$c,$f,$state]){
    if($q===''||core_text::strpos(local_seavanmedia_normalize(core_text::strtolower($c->fullname.' '.$f['name'])),local_seavanmedia_normalize(core_text::strtolower($q)))===false){continue;}
    $dir=sam_asset_dir($c->id,$f['key']);$last=is_readable($dir.'/transcribe.result.json')?json_decode(file_get_contents($dir.'/transcribe.result.json'),true):[];
    $result=is_file($dir.'/transcript.json')?'آماده':(is_file($dir.'/transcribe.job.json')?'در صف':(($last['status']??'')==='error'?'خطا؛ سرویس یا فایل را بررسی کنید':'آماده ثبت درخواست'));
    echo html_writer::div(html_writer::tag('span',s($f['name']).' — '.$result).$OUTPUT->single_button(new moodle_url('/local/seavanmedia/health.php',['id'=>$c->id,'file'=>$f['key'],'job'=>'transcribe','sesskey'=>sesskey()]),'تولید متن','post').($f['kind']==='video'?
    $OUTPUT->single_button(new moodle_url('/local/seavanmedia/health.php',['id'=>$c->id,'file'=>$f['key'],'job'=>'quality','sesskey'=>sesskey()]),'ساخت کیفیت کم‌حجم','post').
    $OUTPUT->single_button(new moodle_url('/local/seavanmedia/health.php',['id'=>$c->id,'file'=>$f['key'],'job'=>'preview','sesskey'=>sesskey()]),'ساخت پیش‌نمایش نوار زمان','post'):''),'card p-2 my-2');
    if(++$n>=40){break;}
}
echo html_writer::end_tag('details');echo html_writer::end_div();echo $OUTPUT->footer();
