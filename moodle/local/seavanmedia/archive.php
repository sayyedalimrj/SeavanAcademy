<?php
require(__DIR__.'/../../config.php');require_once(__DIR__.'/lib.php');require_once(__DIR__.'/assets.php');
require_login();require_capability('moodle/site:config',context_system::instance());
$url=new moodle_url('/local/seavanmedia/archive.php');
$folders=local_seavanmedia_folders();
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    require_sesskey();$action=required_param('action',PARAM_ALPHA);
    if($action==='policy'){
        $c=get_course(required_param('id',PARAM_INT));
        if(!local_seavanmedia_is_course($c)){throw new moodle_exception('invalidrequest');}
        set_config('nodownload_'.$c->id,optional_param('blocked',0,PARAM_BOOL)?'1':'0','local_seavanmedia');
    }elseif($action==='upload'){
        $id=optional_param('id',0,PARAM_INT);
        $root=$CFG->dataroot.'/seavan-uploads';
        if(!is_dir($root)){mkdir($root,0770,true);}
        if($id){
            $c=get_course($id);$folder=$folders[$c->idnumber]??'';
            if(!$folder||!str_starts_with($folder,$root.'/')){throw new moodle_exception('invalidrequest');}
        }else{
            $name=clean_filename(required_param('name',PARAM_TEXT));
            if($name===''||str_starts_with($name,'.')){throw new moodle_exception('invalidrequest');}
            $folder=$root.'/'.$name;
            if(file_exists($folder)||!mkdir($folder,0770)){throw new moodle_exception('invalidrequest');}
        }
        $f=$_FILES['media']??[];
        $name=clean_filename($f['name']??'');
        $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        if(($f['error']??1)!==UPLOAD_ERR_OK||!is_uploaded_file($f['tmp_name'])||$f['size']>2*1024*1024*1024||
            !in_array($ext,['mp4','m4v','webm','mkv','mov','avi','wmv','mp3','wav','m4a','pdf','xlsx','docx','pptx','zip','vtt'],true)||
            str_starts_with($name,'.')||file_exists($folder.'/'.$name)||disk_free_space($root)<$f['size']+1073741824){throw new moodle_exception('invalidrequest');}
        if(!move_uploaded_file($f['tmp_name'],$folder.'/'.$name)){throw new moodle_exception('invalidrequest');}
        touch($folder.'/'.$name,time()-180);
    }elseif($action==='hashall'){
        $c=get_course(required_param('id',PARAM_INT));
        foreach(local_seavanmedia_course_files($c) as $f){$p=sam_asset_dir($c->id,$f['key']).'/sha256.json';if(!is_file($p)){sam_queue($c->id,$f,'hash');}}
    }elseif($action==='hash'){
        $c=get_course(required_param('id',PARAM_INT));$f=local_seavanmedia_get_file($c,required_param('file',PARAM_ALPHANUM));
        sam_queue($c->id,$f,'hash');
    }else{throw new moodle_exception('invalidrequest');}
    $target=$action==='upload'?'upload':(in_array($action,['hash','hashall'],true)?'versions':'courses');
    redirect(new moodle_url('/local/seavanmedia/archive.php',['section'=>$target]+($target==='versions'?['course'=>$c->id]:[])),'تنظیم یا درخواست ثبت شد.');
}
$PAGE->set_context(context_system::instance());$PAGE->set_url($url);$PAGE->set_pagelayout(($CFG->theme??'')==='seavan'?'seavanworkspace':'standard');$PAGE->add_body_class('seavan-workspace');$PAGE->set_title('مدیریت آرشیو');$PAGE->set_heading('مدیریت آرشیو');
echo $OUTPUT->header();
echo html_writer::div(html_writer::link(new moodle_url('/local/seavan/index.php',['view'=>'manage']),'مدیریت ← بازگشت'),'sa-admin-back');
echo html_writer::tag('h1','مدیریت آرشیو',['class'=>'sa-page-title']);
echo html_writer::tag('p','مدیریت دوره‌های متصل، مجوز دانلود و فایل‌های آموزشی.',['class'=>'seavan-note']);
$section=optional_param('section','courses',PARAM_ALPHA);
if(!in_array($section,['courses','upload','versions','duplicates'],true)){$section='courses';}
$sectionlinks=['courses'=>'دوره‌ها و دسترسی','upload'=>'بارگذاری فایل','versions'=>'شناسه و نسخه‌ها','duplicates'=>'فایل‌های تکراری'];
echo html_writer::start_tag('nav',['class'=>'sa-subnav','aria-label'=>'بخش‌های آرشیو']);
foreach($sectionlinks as $v=>$title){echo html_writer::link(new moodle_url('/local/seavanmedia/archive.php',['section'=>$v]),$title,$v===$section?['aria-current'=>'page']:[]);}
echo html_writer::end_tag('nav');
$choices=[0=>'دوره جدید'];$courses=$DB->get_records_select('course',$DB->sql_like('idnumber',':prefix'),['prefix'=>'seavanmedia:%'],'fullname','id,fullname,idnumber');
$table=new html_table();$table->head=['دوره','آرشیو','دانلود کارکنان','مدیریت دوره'];
foreach($courses as $c){
    $folder=$folders[$c->idnumber]??null;
    if($folder&&str_starts_with($folder,$CFG->dataroot.'/seavan-uploads/')){$choices[$c->id]=$c->fullname;}
    $blocked=get_config('local_seavanmedia','nodownload_'.$c->id)==='1';
    $button=$OUTPUT->single_button(new moodle_url('/local/seavanmedia/archive.php',['id'=>$c->id,'action'=>'policy','blocked'=>$blocked?0:1,'sesskey'=>sesskey()]),$blocked?'فعال کردن دانلود':'غیرفعال کردن دانلود','post');
    if($section==='courses'){$table->data[]=[s($c->fullname),html_writer::tag('span',$folder?'متصل':'قطع یا نامشخص',['class'=>'sa-status '.($folder?'sa-ok':'sa-warn')]),html_writer::tag('span',$blocked?'ممنوع':'مجاز',['class'=>'sa-status '.($blocked?'sa-warn':'sa-ok')]).$button,html_writer::div(
        html_writer::link(new moodle_url('/course/edit.php',['id'=>$c->id]),'ویرایش و کاور') .
        html_writer::link(new moodle_url('/enrol/users.php',['id'=>$c->id]),'ثبت‌نام و دسترسی کاربران') .
        ($folder ? html_writer::link(new moodle_url('/local/seavanmedia/downloads.php',['id'=>$c->id]),'فایل‌های قابل دانلود') : ''),
        'sa-archive-actions')];}
}
if($section==='courses'){
if($table->data){echo html_writer::div(html_writer::table($table),'table-responsive');}else{echo html_writer::div('هنوز دوره‌ای در آرشیو ثبت نشده است.','seavan-empty');}
echo html_writer::tag('p','محدودیت دانلود، دکمه‌ها و دانلود ZIP را غیرفعال می‌کند؛ جلوگیری قطعی از ذخیره جریان ویدیو توسط مرورگر نیست.');
}
if($section==='upload'){
echo html_writer::tag('h3','بارگذاری مستقیم');
echo html_writer::tag('p','فقط برای مدیر؛ فایل‌های بارگذاری‌شده در فضای Moodledata ذخیره می‌شوند. برای آرشیوهای بزرگ کپی پوشه بهتر است. فایل موجود بازنویسی نمی‌شود.');
echo html_writer::start_tag('form',['method'=>'post','action'=>$url->out(false),'enctype'=>'multipart/form-data','class'=>'card p-3']);
foreach(['action'=>'upload','sesskey'=>sesskey()] as $n=>$v){echo html_writer::empty_tag('input',['type'=>'hidden','name'=>$n,'value'=>$v]);}
echo html_writer::select($choices,'id',0,false,['class'=>'custom-select']);
echo html_writer::empty_tag('input',['name'=>'name','placeholder'=>'نام دوره جدید (اگر دوره جدید انتخاب شده)','class'=>'form-control my-2']);
echo html_writer::empty_tag('input',['type'=>'file','name'=>'media','required'=>'required','class'=>'form-control']);
echo html_writer::tag('button','بارگذاری فایل',['class'=>'btn btn-primary mt-2','type'=>'submit']);echo html_writer::end_tag('form');
}
if($section==='versions'){
echo html_writer::tag('h3','شناسه و نسخه فایل‌ها',['class'=>'mt-4']);
echo html_writer::tag('p','قبل از تغییر نام پوشه، شناسه ثابت آن را در فایل .seavan-id قرار دهید. برای تغییر نام یک فایل بدون تغییر محتوا، شناسه قبلی را در .seavan-aliases.json ثبت کنید. نسخه جدید محتوای متفاوت باید شناسه جدا داشته باشد؛ فایل قبلی را نگه دارید.');
$id=optional_param('course',0,PARAM_INT);
echo html_writer::start_tag('form',['method'=>'get','action'=>$url->out(false)]);
echo html_writer::empty_tag('input',['type'=>'hidden','name'=>'section','value'=>'versions']);
echo html_writer::select([0=>'انتخاب دوره']+array_map(fn($c)=>s($c->fullname),$courses),'course',$id,false,['class'=>'custom-select']);
echo html_writer::tag('button','نمایش شناسه‌ها',['class'=>'btn btn-outline-primary']);echo html_writer::end_tag('form');
if($id&&isset($courses[$id])){
    echo html_writer::div('شناسه ثابت دوره: '.html_writer::tag('code',s($courses[$id]->idnumber)),'sa-info');
    echo $OUTPUT->single_button(new moodle_url('/local/seavanmedia/archive.php',['action'=>'hashall','id'=>$id,'sesskey'=>sesskey()]),'بررسی هش همه فایل‌های این دوره','post');
    $t=new html_table();$t->head=['نام نسبی','شناسه','SHA-256','عملیات'];
    foreach(local_seavanmedia_course_files($courses[$id]) as $key=>$f){
        $p=sam_asset_dir($id,$key).'/sha256.json';$h=is_file($p)?json_decode(file_get_contents($p),true):[];
        $t->data[]=[s($f['rel']),html_writer::tag('code',s($key)),s($h['hash']??'بررسی نشده'),$OUTPUT->single_button(new moodle_url('/local/seavanmedia/archive.php',['action'=>'hash','id'=>$id,'file'=>$key,'sesskey'=>sesskey()]),'محاسبه هش','post')];
    }echo html_writer::div(html_writer::table($t),'table-responsive');
    echo html_writer::tag('h3','تاریخچه نسخه‌های فهرست دوره');
    $revisions=glob($CFG->dataroot.'/seavan-revisions/'.$id.'/*-*.json')?:[];rsort($revisions);
    foreach(array_slice($revisions,0,20) as $path){$v=json_decode(file_get_contents($path),true);if(!$v){continue;}echo html_writer::tag('p',userdate($v['time']).' — '.count($v['files']).' فایل — '.s(substr($v['hash'],0,12)));}
    echo html_writer::tag('p','این تاریخچه فهرست است؛ نسخه‌های باینری را در هارد یا بکاپ جداگانه نگه دارید.');
}
}
if($section==='duplicates'){
echo html_writer::tag('h3','فایل‌های کاملاً تکراری (هش یکسان)');
$hashes=[];$unhashed=0;
foreach($courses as $c){
    foreach(local_seavanmedia_course_files($c) as $f){
        $p=sam_asset_dir($c->id,$f['key']).'/sha256.json';$h=is_file($p)?json_decode(file_get_contents($p),true):[];
        if(empty($h['hash'])||($h['fingerprint']??'')!==($f['fingerprint']??$f['key'])){$unhashed++;continue;}
        $hashes[$h['hash']][]=$c->fullname.' / '.$f['rel'];
    }
}
echo html_writer::tag('p','فایل‌های هنوز بررسی‌نشده: '.$unhashed.'؛ هیچ فایل تکراری خودکار حذف نمی‌شود.');
foreach($hashes as $hash=>$names){if(count($names)>1){echo html_writer::div(html_writer::tag('strong',count($names).' فایل یکسان').html_writer::alist(array_map('s',$names)),'card p-3 mb-2');}}
}
echo $OUTPUT->footer();
