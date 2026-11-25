<?php
require(__DIR__.'/../../config.php');require_once(__DIR__.'/lib.php');require_once(__DIR__.'/assets.php');
require_login();require_capability('moodle/site:config',context_system::instance());
$PAGE->set_context(context_system::instance());$PAGE->set_url(new moodle_url('/local/seavanmedia/health.php'));
$PAGE->set_pagelayout(($CFG->theme??'')==='seavan'?'seavanworkspace':'standard');$PAGE->set_title('سلامت کتابخانه');$PAGE->set_heading('سلامت کتابخانه');
echo $OUTPUT->header();echo html_writer::start_div('seavan-shell');
echo html_writer::tag('h1','سلامت کتابخانه',['class'=>'seavan-section']);
echo html_writer::link(new moodle_url('/local/seavan/index.php',['view'=>'manage']),'بازگشت به مدیریت');
$rows=[];$issues=[];$total=0;$pending=0;
foreach(local_seavanmedia_folders() as $number=>$folder){
    $c=$DB->get_record('course',['idnumber'=>$number],'id,fullname');if(!$c){continue;}
    $counts=['ok'=>0,'error'=>0,'pending'=>0,'convert'=>0];$seconds=0;
    foreach(local_seavanmedia_files($folder) as $f){
        if(!in_array(strtolower(pathinfo($f['name'],PATHINFO_EXTENSION)),['mp4','m4v','webm','mov','mkv','avi','wmv','ogv','mp3','m4a','wav','ogg','flac'],true)){continue;}
        $total++;$m=sam_metadata($c->id,$f['key']);$converted=is_file(sam_asset_dir($c->id,$f['key']).'/converted.mp4');
        $state=!$m?'pending':($m['status']!=='ok'?'error':(!empty($m['compatible'])||$converted?'ok':'convert'));$counts[$state]++;$seconds+=(float)($m['duration']??0);
        if(in_array($state,['error','convert'],true)&&count($issues)<100){$issues[]=[$c,$f,$state];}
    }
    $pending+=$counts['pending'];
    $rows[]=[$c->fullname,$counts['ok'],$counts['pending'],$counts['convert'],$counts['error'],round($seconds/3600,1)];
}
echo html_writer::tag('p','فایل رسانه‌ای: '.$total.' · در انتظار بررسی: '.$pending.' — بررسی سریع کانتینر و کدک، نه آزمایش کامل تمام فریم‌ها.');
$table=new html_table();$table->head=['دوره','آماده','در انتظار','نیازمند بررسی کدک','خطای خواندن','ساعت شناسایی‌شده'];foreach($rows as $r){$r[0]=s($r[0]);$table->data[]=$r;}echo html_writer::table($table);
if($issues){echo html_writer::tag('h2','فایل‌های نیازمند بررسی (حداکثر ۱۰۰ مورد)');
foreach($issues as [$c,$f,$state]){
    $cmd='docker compose exec -T -u www-data cron php /var/www/html/local/seavanmedia/cli/media.php --action=convert --course='.$c->id.' --file='.$f['key'];
    echo html_writer::div(html_writer::tag('strong',s($f['name'])).html_writer::tag('p',s($c->fullname).' — '.($state==='error'?'خطای خواندن':'کدک یا ظرف ناسازگار')).($state==='convert'?html_writer::tag('code',s($cmd)):''),'card p-3 mb-2');
}}
echo html_writer::end_div();echo $OUTPUT->footer();
