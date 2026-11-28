<?php
require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');require_once(__DIR__.'/assets.php');
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
$PAGE->set_pagelayout(($CFG->theme ?? '') === 'seavan' ? 'seavanworkspace' : 'standard');$PAGE->add_body_class('seavan-workspace');$PAGE->set_title(format_string($course->fullname));$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css('/local/seavanmedia/style.css');
$PAGE->requires->js_call_amd('local_seavanmedia/player','init');
$protected = !empty($CFG->seavanprotectedmedia);
if ($protected) {
    $PAGE->requires->js_init_code("document.querySelector('.sam-player-wrap')?.addEventListener('contextmenu', event => event.preventDefault());");
}
$goto=fn($k)=>new moodle_url('/local/seavanmedia/view.php',['id'=>$id,'file'=>$k]);
$digits=fn($n)=>strtr((string)$n,['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
$fmt=fn($s)=>gmdate($s>=3600?'H:i:s':'i:s',(int)$s);
$videos=array_filter($files,fn($f)=>in_array($f['kind'],['video','audio'],true));
$done=count(array_filter($videos,fn($f)=>($history[$f['key']]->percent??0)>=95));
echo $OUTPUT->header();
echo html_writer::start_div('sam');
echo html_writer::start_div('sam-coursehead');
echo html_writer::div(html_writer::link(new moodle_url('/course/index.php'),'بازگشت به کتابخانه'),'sam-crumb');
echo html_writer::tag('h1',format_string($course->fullname),['class'=>'sam-course-title']);
echo html_writer::link(new moodle_url('/local/seavanmedia/quizzes.php', ['id' => $id]), 'آزمون‌های دوره', ['class' => 'btn btn-outline-primary mr-2']);
echo html_writer::link(new moodle_url('/local/seavanmedia/quizzes.php', ['id' => $id, 'evaluation' => 1]),
    'ارزیابی استاد', ['class' => 'btn btn-outline-primary']);

echo html_writer::end_div();
if(!$files){echo html_writer::div('فایل‌های این دوره هنوز در مسیر سرور در دسترس نیستند.','alert alert-info');echo html_writer::end_div();echo $OUTPUT->footer();exit;}
$f=$files[$key];$p=$history[$key]??null;$meta=sam_metadata($id,$key);
$note=json_decode(get_user_preferences('seavanmedia_note_'.$id.'_'.$key,'{}'),true)?:[];
$converted=is_file(sam_asset_dir($id,$key).'/converted.mp4');
$low=is_file(sam_asset_dir($id,$key).'/480.mp4');
$quality=optional_param('quality','default',PARAM_ALPHA);
$useLow=$low&&$quality==='low';
\local_seavanmedia\event\media_accessed::create(['context'=>$ctx,'other'=>['filekey'=>$key,'action'=>'open']])->trigger();
echo html_writer::start_div('sam-layout');
echo html_writer::start_div('sam-main');

if(in_array($f['kind'],['video','audio'],true)){
    $attrs=['id'=>'sam-player','src'=>local_seavanmedia_url($id,$key)->out(false),'controls'=>true,'preload'=>'metadata',
        'data-position'=>(string)max(0,optional_param('at',($p && $p->percent<95)?$p->position:0,PARAM_FLOAT)),'data-ranges'=>$p->rangesjson??'[]','data-endpoint'=>(new moodle_url('/local/seavanmedia/progress.php'))->out(false),
        'data-course'=>$id,'data-file'=>$key,'data-sesskey'=>sesskey(),'playsinline'=>true];
    $attrs['class']='sam-native-media';
    $attrs['data-kind']=$f['kind'];
    $native=optional_param('native',0,PARAM_BOOL);
    $attrs['data-native']=$native?'1':'0';
    $attrs['data-mime']=$f['mime'];
    $attrs['data-language-url']=(new moodle_url('/media/player/videojs/videojs/lang/fa.json'))->out(false);
    unset($attrs['src']);
    if($f['kind']==='video'&&is_file(sam_asset_dir($id,$key).'/cover.jpg')){$attrs['poster']=sam_asset_url($id,$key,'cover')->out(false);}
    $previewfile=sam_asset_dir($id,$key).'/previews.json';
    if(is_readable($previewfile)){
        $preview=json_decode(file_get_contents($previewfile),true);
        $attrs['data-preview-url']=sam_asset_url($id,$key,'preview')->out(false);
        $attrs['data-preview-step']=$preview['step']??0;$attrs['data-preview-count']=$preview['count']??0;
    }
    $variant = $useLow ? 'low' : ($converted ? 'converted' : 'original');
    $sourceurl = $variant === 'original' ? local_seavanmedia_url($id, $key) : sam_asset_url($id, $key, $variant);
    if ($protected) {
        $attrs['controlslist'] = 'nodownload noremoteplayback';
        $attrs['disablepictureinpicture'] = 'disablepictureinpicture';
        $sourceurl = sam_playback_url($sourceurl, $id, $key, $variant);
    }
    $sources = html_writer::empty_tag('source', ['src' => $sourceurl->out(false), 'type' => $variant === 'original' ? $f['mime'] : 'video/mp4']);
    // Match WebVTT sidecars by exact stem and optional language suffix.
    $stem=pathinfo($f['name'],PATHINFO_FILENAME);
    foreach($files as $subtitle){
        if($subtitle['group']!==$f['group']||strtolower(pathinfo($subtitle['name'],PATHINFO_EXTENSION))!=='vtt'){continue;}
        $name=pathinfo($subtitle['name'],PATHINFO_FILENAME);
        $lang='fa';
        if($name!==$stem){
            if(!str_starts_with($name,$stem.'.')){continue;}
            $lang=substr($name,strlen($stem)+1);
            if(!preg_match('/^[a-z]{2,3}(?:-[A-Za-z]{2,8})?$/D',$lang)){continue;}
        }
        $label=['fa'=>'فارسی','en'=>'English','ar'=>'العربية'][$lang]??$lang;
        $trackurl=local_seavanmedia_url($id,$subtitle['key']);
        $trackurl->param('track',1);
        $sources.=html_writer::empty_tag('track',['src'=>$trackurl->out(false),'kind'=>'subtitles','srclang'=>$lang,'label'=>$label]);
    }
    $watermark = $protected ? html_writer::div('پخش آموزشی · ' . s(fullname($USER)), 'sam-watermark', ['aria-hidden' => 'true']) : '';
    echo html_writer::div(html_writer::tag($f['kind'], $sources . 'مرورگر شما از این نوع رسانه پشتیبانی نمی‌کند.', $attrs) . $watermark, 'sam-player-wrap');
    if($low){echo html_writer::div(html_writer::tag('label','کیفیت '.html_writer::select(['default'=>'کیفیت اصلی / سازگار','low'=>'کم‌حجم (تا ۴۸۰p)'],'quality',$useLow?'low':'default',false,['id'=>'sam-quality','class'=>'custom-select']),[]),'sam-quality');}
    echo html_writer::start_div('sam-tools sam-playtools',['id'=>'sam-native-controls']);
    echo html_writer::tag('label','سرعت '.html_writer::select(['0.75'=>'۰٫۷۵','1'=>'۱×','1.25'=>'۱٫۲۵','1.5'=>'۱٫۵','1.75'=>'۱٫۷۵','2'=>'۲×'],'speed','1',false,['id'=>'sam-speed','class'=>'custom-select']),[]);
    echo html_writer::tag('button','۱۰ ثانیه عقب',['id'=>'sam-back','type'=>'button','class'=>'btn btn-outline-primary']);
    echo html_writer::tag('button','۱۰ ثانیه جلو',['id'=>'sam-forward','type'=>'button','class'=>'btn btn-outline-primary']);
    if($p&&$p->position>3){echo html_writer::tag('button','از ابتدا',['id'=>'sam-restart','type'=>'button','class'=>'btn btn-outline-secondary']);}
    echo html_writer::end_div();
    echo html_writer::div('بخش‌های مشاهده‌شده: '.html_writer::tag('strong',$digits($p->percent??0).'٪',['id'=>'sam-percent']).' — '.html_writer::tag('span','ذخیره خودکار',['id'=>'sam-state','role'=>'status']),'sam-muted');
    echo html_writer::div('', 'sam-watched-track',['id'=>'sam-watched-track','role'=>'img','aria-label'=>'بازه‌های مشاهده‌شده فیلم']);


}elseif($f['kind']==='pdf'){
    echo html_writer::tag('iframe','',['src'=>local_seavanmedia_url($id,$key)->out(false),'class'=>'sam-pdf','title'=>s($f['title']),'loading'=>'lazy']);
}else{echo html_writer::div(sam_download_allowed($id) ? 'این فایل برای دانلود آماده است.' : 'دریافت این پیوست در این دوره غیرفعال است.', 'sam-muted');}
echo html_writer::tag('h2',s($f['title']),['class'=>'sam-title sam-after-player','dir'=>'auto']);
echo html_writer::tag('p',s($f['group']),['class'=>'sam-muted sam-group']);
echo html_writer::start_div('sam-tools sam-filetools');
if (sam_download_allowed($id)) {
    echo html_writer::link(new moodle_url('/local/seavanmedia/downloads.php', ['id' => $id]), 'دانلود دوره', ['class' => 'btn btn-primary']);
    echo html_writer::link(local_seavanmedia_url($id, $key, true), 'دانلود این فایل', ['class' => 'btn btn-outline-primary']);
}
$keys=in_array($f['kind'],['video','audio'],true)?array_keys($videos):array_keys($files);$idx=array_search($key,$keys,true);
if($idx>0){echo html_writer::link($goto($keys[$idx-1]),'جلسه قبلی',['class'=>'btn btn-outline-primary']);}
if($idx<count($keys)-1){echo html_writer::link($goto($keys[$idx+1]),'جلسه بعدی',['class'=>'btn btn-outline-primary','id'=>'sam-next']);}
echo html_writer::end_div();
echo html_writer::start_tag('details',['class'=>'sa-advanced sam-extras']);
echo html_writer::tag('summary','امکانات جلسه');
echo html_writer::link(new moodle_url('/course/view.php',['id'=>$id,'classic'=>1]),'صفحه کامل دوره',['class'=>'sam-extra']);
if(in_array($f['kind'],['video','audio'],true)){
    echo html_writer::tag('label',html_writer::empty_tag('input',['type'=>'checkbox','id'=>'sam-autonext']).' رفتن خودکار به جلسه بعدی',['class'=>'sam-muted sam-autonext']);
    echo html_writer::link(new moodle_url('/local/seavanmedia/view.php',['id'=>$id,'file'=>$key,'native'=>$native?0:1]),$native?'پلیر حرفه‌ای':'در صورت مشکل: پلیر ساده',['class'=>'sam-muted']);
}
echo $OUTPUT->single_button(new moodle_url('/local/seavanmedia/personal.php',['id'=>$id,'file'=>$key,'action'=>'favorite','sesskey'=>sesskey()]),get_user_preferences('seavanmedia_fav_'.$id,0)?'حذف از علاقه‌مندی‌ها':'افزودن به علاقه‌مندی‌ها','post');
echo html_writer::start_tag('form',['method'=>'post','action'=>(new moodle_url('/local/seavanmedia/personal.php'))->out(false),'class'=>'sam-note-form']);
foreach(['id'=>$id,'file'=>$key,'action'=>'note','sesskey'=>sesskey()] as $n=>$value){echo html_writer::empty_tag('input',['type'=>'hidden','name'=>$n,'value'=>$value]);}
echo html_writer::empty_tag('input',['type'=>'hidden','id'=>'sam-note-position','name'=>'position','value'=>$note['time']??0]);
echo html_writer::tag('label','یادداشت شخصی (حداکثر ۴۰۰ نویسه)',['for'=>'sam-note']);
echo html_writer::tag('textarea',s($note['text']??''),['id'=>'sam-note','name'=>'note','maxlength'=>400,'rows'=>2,'class'=>'form-control']);
echo html_writer::tag('button','ذخیره در زمان فعلی',['type'=>'submit','class'=>'btn btn-outline-primary mt-2']);
echo html_writer::end_tag('form');
if(isset($note['time'])){echo html_writer::link(new moodle_url('/local/seavanmedia/view.php',['id'=>$id,'file'=>$key,'at'=>$note['time']]),'رفتن به نشانک · '.$fmt($note['time']));}
echo html_writer::end_tag('details');
$transcript=sam_transcript($id,$key);
if($transcript){
    echo html_writer::start_tag('details',['class'=>'sa-advanced sam-transcript']);
    echo html_writer::tag('summary','متن گفتار فیلم');
    echo html_writer::empty_tag('input',['type'=>'search','id'=>'sam-transcript-filter','class'=>'form-control my-2','placeholder'=>'جست‌وجو در متن فیلم']);
    foreach($transcript as $segment){
        echo html_writer::link(new moodle_url('/local/seavanmedia/view.php',['id'=>$id,'file'=>$key,'at'=>$segment['start']]),html_writer::tag('small',$fmt($segment['start'])).' '.s($segment['text']),['class'=>'sam-transcript-row','data-text'=>$segment['text']]);
    }
    echo html_writer::end_tag('details');
}
echo html_writer::end_div();
echo html_writer::start_div('sam-side');
echo html_writer::start_div('sam-side-header');
echo html_writer::tag('h3','محتوای دوره');
echo html_writer::tag('span',$digits($done).' از '.$digits(count($videos)).' جلسه دیده‌شده',['class'=>'sam-muted']);
echo html_writer::tag('progress','',['value'=>$done,'max'=>max(1,count($videos)),'class'=>'sam-bar','aria-label'=>'پیشرفت دوره']);
echo html_writer::end_div();
echo html_writer::empty_tag('input',['id'=>'sam-filter','type'=>'search','class'=>'form-control','placeholder'=>'جست‌وجو در جلسات...','aria-label'=>'جست‌وجوی جلسات']);
echo html_writer::select(['all'=>'همه جلسات و فایل‌ها','unseen'=>'جلسات مشاهده‌نشده','started'=>'جلسات در حال مشاهده','done'=>'جلسات مشاهده‌شده','docs'=>'جزوه و پیوست'],'lessonstate','all',false,['id'=>'sam-lessonstate','class'=>'custom-select mt-2','aria-label'=>'فیلتر وضعیت جلسات']);
$groups=[];foreach($files as $item){if(in_array(strtolower(pathinfo($item['name'],PATHINFO_EXTENSION)),['vtt','srt'],true)){continue;}$groups[$item['group']][]=$item;}
foreach($groups as $name=>$items){
    $active=in_array($key,array_column($items,'key'),true);
    echo html_writer::start_tag('details',['class'=>'sam-chapter']+($active?['open'=>'open']:[]));
    echo html_writer::tag('summary',html_writer::tag('span',s($name)).html_writer::tag('small',$digits(count($items)).' فایل',['class'=>'sam-chapter-count']));
    foreach($items as $item){
        if(in_array(strtolower(pathinfo($item['name'],PATHINFO_EXTENSION)),['vtt','srt'],true)){continue;}
        $r=$history[$item['key']]??null;
        $state=$item['kind']==='pdf'?'PDF':(!in_array($item['kind'],['video','audio'],true)?strtoupper(pathinfo($item['name'],PATHINFO_EXTENSION)):($r?($r->percent>=95?'✓':$digits($r->percent).'٪'):'▷'));
        $m=sam_metadata($id,$item['key']);
        $label=html_writer::tag('span',s($item['title']).(!empty($m['duration'])?html_writer::tag('small',$fmt($m['duration']),['class'=>'sam-duration']):'' ),['class'=>'sam-lesson-copy']).html_writer::tag('small',$state,['class'=>'sam-lesson-status']);
        echo html_writer::link($goto($item['key']),$label,['class'=>'sam-lesson'.($key===$item['key']?' active':''),'data-title'=>$item['title'].' '.$item['group'],'data-state'=>!in_array($item['kind'],['video','audio'],true)?'docs':(($r->percent??0)>=95?'done':(($r->percent??0)>0?'started':'unseen')),'aria-current'=>$key===$item['key']?'page':'false']);
    }
    echo html_writer::end_tag('details');
}
echo html_writer::end_div();echo html_writer::end_div();echo html_writer::end_div();echo $OUTPUT->footer();
