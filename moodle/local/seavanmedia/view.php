<?php
require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
$id=required_param('id',PARAM_INT);
$course=local_seavanmedia_require_course($id);
$files=local_seavanmedia_course_files($course);
$history=local_seavanmedia_progress($id);
$key=optional_param('file','',PARAM_ALPHANUM);
if($key!==''&&!isset($files[$key])){throw new moodle_exception('mediaunavailable','local_seavanmedia');}
if(!$key){
    // Resume the latest unfinished item. If it was completed, choose the next unfinished item.
    foreach($history as $k=>$row){
        if(!isset($files[$k])){continue;}
        if($row->percent<95){$key=$k;break;}
        $after=false;
        foreach($files as $nextkey=>$next){
            if($nextkey===$k){$after=true;continue;}
            if($after&&in_array($next['kind'],['video','audio'],true)&&($history[$nextkey]->percent??0)<95){$key=$nextkey;break;}
        }
        break;
    }
    if(!$key){foreach($files as $k=>$f){if(in_array($f['kind'],['video','audio'],true)&&(!isset($history[$k])||$history[$k]->percent<95)){$key=$k;break;}}}
    if(!$key&&$files){$key=array_key_first($files);}
}
$ctx=context_course::instance($id);
$PAGE->set_context($ctx);$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/local/seavanmedia/view.php',['id'=>$id,'file'=>$key]));
$PAGE->set_pagelayout('seavanworkspace');$PAGE->add_body_class('seavan-workspace');$PAGE->set_title(format_string($course->fullname));$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css('/local/seavanmedia/style.css');
$PAGE->requires->js('/local/seavanmedia/player.js');
$goto=fn($k)=>new moodle_url('/local/seavanmedia/view.php',['id'=>$id,'file'=>$k]);
$digits=fn($n)=>strtr((string)$n,['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
$fmt=fn($s)=>gmdate($s>=3600?'H:i:s':'i:s',(int)$s);
$videos=array_filter($files,fn($f)=>in_array($f['kind'],['video','audio'],true));
$done=count(array_filter($videos,fn($f)=>($history[$f['key']]->percent??0)>=95));
echo $OUTPUT->header();
echo html_writer::start_div('sam');
echo html_writer::div(html_writer::link(new moodle_url('/course/index.php'),'بازگشت به کتابخانه',['class'=>'btn btn-outline-primary']).' '.html_writer::link(new moodle_url('/course/view.php',['id'=>$id,'classic'=>1]),'امکانات کامل دوره',['class'=>'btn btn-outline-secondary']),'sam-top');
if(!$files){echo html_writer::div('فایل‌های این دوره هنوز در مسیر سرور در دسترس نیستند.','alert alert-info');echo html_writer::end_div();echo $OUTPUT->footer();exit;}
$f=$files[$key];$p=$history[$key]??null;
\local_seavanmedia\event\media_accessed::create(['context'=>$ctx,'other'=>['filekey'=>$key,'action'=>'open']])->trigger();
echo html_writer::start_div('sam-layout');
echo html_writer::start_div('sam-main');
echo html_writer::tag('h2',s($f['title']),['class'=>'sam-title']);
echo html_writer::tag('p',s($f['group']),['class'=>'sam-muted']);
if(in_array($f['kind'],['video','audio'],true)){
    $attrs=['id'=>'sam-player','src'=>local_seavanmedia_url($id,$key)->out(false),'controls'=>true,'preload'=>'metadata',
        'data-position'=>(string)(($p && $p->percent<95)?$p->position:0),'data-ranges'=>$p->rangesjson??'[]','data-endpoint'=>(new moodle_url('/local/seavanmedia/progress.php'))->out(false),
        'data-course'=>$id,'data-file'=>$key,'data-sesskey'=>sesskey(),'playsinline'=>true];
    echo html_writer::tag($f['kind'],'مرورگر شما از این نوع رسانه پشتیبانی نمی‌کند.',$attrs);
    echo html_writer::start_div('sam-tools');
    echo html_writer::tag('label','سرعت پخش '.html_writer::select(['0.75'=>'۰٫۷۵','1'=>'۱×','1.25'=>'۱٫۲۵','1.5'=>'۱٫۵','1.75'=>'۱٫۷۵','2'=>'۲×'],'speed','1',false,['id'=>'sam-speed','class'=>'custom-select']),[]);
    echo html_writer::tag('button','۱۰ ثانیه عقب',['id'=>'sam-back','type'=>'button','class'=>'btn btn-outline-primary']);
    echo html_writer::tag('button','۱۰ ثانیه جلو',['id'=>'sam-forward','type'=>'button','class'=>'btn btn-outline-primary']);
    if($p&&$p->position>3){echo html_writer::tag('button','از ابتدا',['id'=>'sam-restart','type'=>'button','class'=>'btn btn-outline-secondary']);}
    echo html_writer::end_div();
    echo html_writer::div('بخش‌های مشاهده‌شده: '.html_writer::tag('strong',$digits($p->percent??0).'٪',['id'=>'sam-percent']).' — '.html_writer::tag('span','ذخیره خودکار',['id'=>'sam-state','role'=>'status']),'sam-muted');
    echo html_writer::div('', 'sam-watched-track',['id'=>'sam-watched-track','role'=>'img','aria-label'=>'بازه‌های مشاهده‌شده فیلم']);
    echo html_writer::div('نوار بالا بخش‌های پخش‌شده را نشان می‌دهد. جلو زدن آزاد است.','sam-muted');
    echo html_writer::tag('label',html_writer::empty_tag('input',['type'=>'checkbox','id'=>'sam-autonext']).' رفتن خودکار به جلسه بعدی',['class'=>'sam-muted']);
}elseif($f['kind']==='pdf'){
    echo html_writer::tag('iframe','',['src'=>local_seavanmedia_url($id,$key)->out(false),'class'=>'sam-pdf','title'=>s($f['title']),'loading'=>'lazy']);
}else{echo html_writer::div('این فایل برای دانلود آماده است.','sam-muted');}
echo html_writer::start_div('sam-tools');
echo html_writer::link(local_seavanmedia_url($id,$key,true),'دانلود فایل · '.round($f['size']/1048576,1).' MB',['class'=>'btn btn-primary']);
$keys=in_array($f['kind'],['video','audio'],true)?array_keys($videos):array_keys($files);$idx=array_search($key,$keys,true);
if($idx>0){echo html_writer::link($goto($keys[$idx-1]),'جلسه قبلی',['class'=>'btn btn-outline-primary']);}
if($idx<count($keys)-1){echo html_writer::link($goto($keys[$idx+1]),'جلسه بعدی',['class'=>'btn btn-outline-primary','id'=>'sam-next']);}
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::start_div('sam-side');
echo html_writer::tag('h3','جلسات و فایل‌ها');
echo html_writer::tag('p',$digits($done).' از '.$digits(count($videos)).' جلسه مشاهده‌شده (۹۵٪ یا بیشتر)',['class'=>'sam-muted']);
echo html_writer::tag('progress','',['value'=>$done,'max'=>max(1,count($videos)),'class'=>'sam-bar']);
echo html_writer::empty_tag('input',['id'=>'sam-filter','type'=>'search','class'=>'form-control','placeholder'=>'جست‌وجو در جلسات...','aria-label'=>'جست‌وجوی جلسات']);
echo html_writer::select(['all'=>'همه فایل‌ها','unseen'=>'جلسات مشاهده‌نشده','started'=>'جلسات در حال مشاهده','done'=>'جلسات مشاهده‌شده','docs'=>'جزوه و پیوست'],'lessonstate','all',false,['id'=>'sam-lessonstate','class'=>'custom-select mt-2','aria-label'=>'فیلتر وضعیت جلسات']);
$groups=[];foreach($files as $item){$groups[$item['group']][]=$item;}
foreach($groups as $name=>$items){
    $active=in_array($key,array_column($items,'key'),true);
    echo html_writer::start_tag('details',['class'=>'sam-chapter']+($active?['open'=>'open']:[]));
    echo html_writer::tag('summary',s($name).' · '.$digits(count($items)));
    foreach($items as $item){
        $r=$history[$item['key']]??null;
        $state=$item['kind']==='pdf'?'PDF':($r?($r->percent>=95?'✓':$digits($r->percent).'٪'):'▷');
        $label=html_writer::tag('span',s($item['title'])).html_writer::tag('small',$state);
        echo html_writer::link($goto($item['key']),$label,['class'=>'sam-lesson'.($key===$item['key']?' active':''),'data-title'=>$item['title'].' '.$item['group'],'data-state'=>!in_array($item['kind'],['video','audio'],true)?'docs':(($r->percent??0)>=95?'done':(($r->percent??0)>0?'started':'unseen')),'aria-current'=>$key===$item['key']?'page':'false']);
    }
    echo html_writer::end_tag('details');
}
echo html_writer::end_div();echo html_writer::end_div();echo html_writer::end_div();echo $OUTPUT->footer();
