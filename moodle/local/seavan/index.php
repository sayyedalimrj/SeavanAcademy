<?php
// Seavan Academy: native Moodle data, permissions and navigation.
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/completionlib.php');
$medialib = $CFG->dirroot . '/local/seavanmedia/lib.php';
if (is_readable($medialib)) { require_once($medialib); require_once($CFG->dirroot.'/local/seavanmedia/assets.php'); }
require_once($CFG->dirroot . '/course/lib.php');
require_login(null, false);
if (isguestuser()) {
    redirect(new moodle_url('/login/index.php'));
}
$context = context_system::instance();
$view = $seavanroute ?? optional_param('view', 'home', PARAM_ALPHA);
if (!in_array($view, ['home','mine','catalog','manage'], true)) { $view = 'home'; }
// Home is an alias of the main library; there is no duplicate destination in the menu.
if ($view === 'home') { $view = 'catalog'; }
$query = trim(optional_param('q', '', PARAM_TEXT));
$favonly=optional_param('favorites',0,PARAM_BOOL);
$categoryid = optional_param('category', $seavancategory ?? 0, PARAM_INT);
$url = new moodle_url(match($view) {'home'=>'/my/index.php','mine'=>'/my/courses.php','catalog'=>'/course/index.php',default=>'/local/seavan/index.php'});
$actions = [];
$add = function($capability, $label, $detail, $path, $params = []) use (&$actions, $context) {
    if (has_capability($capability, $context)) {
        $actions[] = [$label, $detail, new moodle_url($path, $params)];
    }
};
$add('moodle/user:create', 'افزودن همکار', 'ساخت حساب کاربری جدید', '/user/editadvanced.php', ['id'=>-1]);
$add('moodle/user:update', 'کاربران', 'جست‌وجو و ویرایش حساب‌ها', '/admin/user.php');
$add('moodle/site:uploadusers', 'افزودن گروهی کاربران', 'ورود فهرست Excel یا CSV کارکنان', '/local/seavanmedia/employees.php');
$add('moodle/category:manage', 'دوره‌ها و دسته‌بندی', 'ساخت دوره، ویرایش و مرتب‌سازی', '/course/management.php');
$defaultcategory = core_course_category::get_default();
if ($defaultcategory && has_capability('moodle/course:create', context_coursecat::instance($defaultcategory->id))) {
    $actions[] = ['افزودن دوره', 'ایجاد یک آموزش جدید', new moodle_url('/course/edit.php',['category'=>$defaultcategory->id])];
}
$add('moodle/cohort:manage', 'گروه‌های آموزشی', 'مدیریت گروه‌های همکاران', '/cohort/index.php');
$add('report/log:view', 'گزارش فعالیت', 'گزارش‌های استاندارد مودل', '/report/log/index.php', ['id'=>SITEID]);
if (function_exists('local_seavanmedia_sync')) { $add('moodle/site:config','ورود خودکار دوره‌ها','شناسایی پوشه‌ها و ساخت دوره','/local/seavanmedia/sync.php'); }
$add('moodle/site:config','مدیریت آرشیو','هاردها، بارگذاری و مجوز دانلود','/local/seavanmedia/archive.php');
$add('moodle/site:config','صف پردازش','کارهای رسانه‌ای در انتظار و انجام‌شده','/local/seavanmedia/queue.php');
$add('moodle/site:config','سلامت سرور','دیسک‌ها، دیتابیس و اجرای وظایف','/local/seavanmedia/server.php');
$add('moodle/site:config','سلامت کتابخانه','کدک، مدت، کاور و پردازش فایل‌ها','/local/seavanmedia/health.php');
$add('report/log:view','خلاصه استفاده','گزارش ساده دوره‌ها','/local/seavanmedia/report.php');
$add('moodle/site:config', 'ظاهر و فونت', 'بارگذاری ایران‌سنس دارای مجوز', '/admin/settings.php', ['section'=>'themesettingseavan']);
$add('moodle/site:config', 'تنظیمات سامانه', 'پشتیبان‌گیری، افزونه‌ها و سایر تنظیمات', '/admin/search.php');
if (!$actions && $view === 'manage') {
    require_capability('moodle/site:config', $context);
}
if (!in_array($view, ['home','mine','catalog','manage'], true)) { $view = 'home'; }
$PAGE->set_context($context);
$PAGE->set_url($url, ['view'=>$view,'q'=>$query,'category'=>$categoryid]);
$PAGE->set_pagelayout(($CFG->theme ?? '') === 'seavan' ? 'seavanworkspace' : 'mydashboard');
$PAGE->add_body_class('seavan-workspace');
$PAGE->set_title('آکادمی سیوان');
$PAGE->set_heading('آکادمی سیوان');

$categories = core_course_category::get_all();
$mine = enrol_get_my_courses();
$lastaccess = $DB->get_records('user_lastaccess', ['userid'=>$USER->id], 'timeaccess DESC', 'courseid,timeaccess');
$enrolled = [];
foreach ($mine as $item) {
    $course = get_course($item->id);
    $ctx = context_course::instance($course->id);
    if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $ctx)) {
        continue;
    }
    $enrolled[$course->id] = [
        'course'=>$course,
        'progress'=>\core_completion\progress::get_course_progress_percentage($course),
        'last'=>$lastaccess[$course->id]->timeaccess ?? 0,
    ];
}
uasort($enrolled, fn($a,$b)=>$b['last'] <=> $a['last']);
$digits = fn($n)=>strtr((string)$n, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
$tabs = ['catalog'=>'آموزش‌ها','mine'=>'یادگیری من'];
if ($actions) { $tabs['manage'] = 'مدیریت'; }
$go = fn($v)=>$v==='manage' ? new moodle_url('/local/seavan/index.php',['view'=>'manage']) : new moodle_url(match($v){'home'=>'/my/index.php','mine'=>'/my/courses.php','catalog'=>'/course/index.php'});
echo $OUTPUT->header();
echo html_writer::start_div('seavan-shell');
if (($CFG->theme ?? '') !== 'seavan') {
echo html_writer::start_tag('nav', ['class'=>'seavan-nav','aria-label'=>'منوی آکادمی']);
foreach ($tabs as $key=>$label) {
    echo html_writer::link($go($key), $label, $key === $view ? ['aria-current'=>'page'] : []);
}
echo html_writer::end_tag('nav');
}
if ($view === 'catalog' && $query === '' && !$categoryid && !$favonly) {
    echo html_writer::start_div('sa-welcome');
    echo html_writer::tag('span','SEAVAN ACADEMY',['class'=>'sa-kicker','lang'=>'en','dir'=>'ltr']);
    echo html_writer::tag('h1','سلام، '.s($USER->firstname).'!');
    echo html_writer::tag('p','آموزش موردنیازت رو پیدا کن و با سرعت خودت یاد بگیر.');
    echo html_writer::div('', 'sa-hero-art',['aria-hidden'=>'true']);
    echo html_writer::end_div();
    if (function_exists('local_seavanmedia_is_course')) {
        $recent = $DB->get_records('local_seavanmedia_progress',['userid'=>$USER->id],'timemodified DESC','*',0,40);
        foreach ($recent as $row) {
            if (!isset($enrolled[$row->courseid])) { continue; }
            $course = $enrolled[$row->courseid]['course'];
            $files = local_seavanmedia_course_files($course);
            $file = $files[$row->filekey] ?? null;
            if (!$file || $row->percent >= 95) { continue; }
            $resume = new moodle_url('/local/seavanmedia/view.php',['id'=>$course->id,'file'=>$row->filekey]);
            echo html_writer::start_div('seavan-resume');
            $thumb=is_file(sam_asset_dir($course->id,$row->filekey).'/cover.jpg')?html_writer::empty_tag('img',['src'=>sam_asset_url($course->id,$row->filekey,'cover')->out(false),'alt'=>'']):html_writer::tag('span','▶',['aria-hidden'=>'true']);
            echo html_writer::div($thumb,'sa-resume-thumb');
            echo html_writer::start_div('seavan-resume-copy');
            echo html_writer::tag('span','آخرین آموزش نیمه‌تمام',['class'=>'seavan-eyebrow']);
            echo html_writer::tag('h3',s($file['title']));
            echo html_writer::tag('p',format_string($course->fullname).' · ادامه از '.$digits(gmdate('H:i:s',(int)$row->position)),['class'=>'seavan-note']);
            echo html_writer::tag('progress','',['value'=>$row->percent,'max'=>100,'class'=>'seavan-progress','aria-label'=>'بخش‌های مشاهده‌شده']);
            echo html_writer::end_div();
            echo html_writer::link($resume,'ادامه تماشا',['class'=>'btn btn-primary']);
            echo html_writer::end_div();
            break;
        }
    }
}
if ($view === 'manage') {
    echo html_writer::tag('h1','مرکز مدیریت',['class'=>'sa-page-title']);
    echo html_writer::tag('p','برای انجام هر کار، بخش مربوط را انتخاب کنید.',['class'=>'seavan-note']);

    $groups = [
        'دوره‌ها و فایل‌ها'=>['ورود خودکار دوره‌ها','مدیریت آرشیو','دوره‌ها و دسته‌بندی','افزودن دوره'],
        'کارکنان و دسترسی'=>['کاربران','افزودن همکار','افزودن گروهی کاربران','گروه‌های آموزشی'],
        'آماده‌سازی رسانه'=>['سلامت کتابخانه','صف پردازش'],
        'گزارش و نگهداری'=>['خلاصه استفاده','گزارش فعالیت','سلامت سرور','ظاهر و فونت','تنظیمات سامانه'],
    ];
    echo html_writer::start_div('seavan-actions');
    $groupicons=['01','02','03','04'];
    $groupindex=0;
    foreach ($groups as $heading=>$labels) {
        $icon=$groupicons[$groupindex++];
        $rows = '';
        foreach ($labels as $wanted) {
            foreach ($actions as [$label,$detail,$link]) {
                if ($label !== $wanted) { continue; }
                $rows .= html_writer::link($link,html_writer::tag('span',s($label)).html_writer::tag('small',s($detail)),['class'=>'sa-admin-link']);
            }
        }
        if ($rows) {
            $headinghtml=html_writer::div(html_writer::tag('span',$icon,['class'=>'sa-group-icon','aria-hidden'=>'true']).html_writer::tag('h3',s($heading)),'sa-admin-heading');
            echo html_writer::div($headinghtml.$rows,'sa-admin-panel');
        }
    }
    echo html_writer::end_div();
    echo html_writer::start_tag('details',['class'=>'sa-advanced']);
    echo html_writer::tag('summary','امکانات پیشرفته Moodle');
    echo html_writer::div(html_writer::link(new moodle_url('/my/index.php',['classic'=>1]),'داشبورد کامل').' · '.html_writer::link(new moodle_url('/course/index.php',['classic'=>1]),'فهرست کامل دوره‌ها'));
    echo html_writer::end_tag('details');
} else {
    $showcatalog = $view === 'catalog' || ($view === 'home' && !$enrolled);
    $title = $showcatalog ? 'کتابخانه' : 'یادگیری من';
    echo html_writer::start_div('sa-title-row');
    echo html_writer::tag('h2',$title,['class'=>'seavan-section']);
    if($view==='catalog'){echo html_writer::link(new moodle_url('/local/seavanmedia/request.php'),'درخواست آموزش',['class'=>'sa-subtle-link']);}
    echo html_writer::end_div();
    echo html_writer::start_tag('form',['method'=>'get','action'=>$url->out(false),'class'=>'seavan-search']);
    echo html_writer::empty_tag('input',['type'=>'hidden','name'=>'view','value'=>$view]);
    echo html_writer::empty_tag('input',['type'=>'search','name'=>'q','value'=>$query,'placeholder'=>$view==='catalog'?'جست‌وجوی دوره، جلسه یا فایل':'جست‌وجوی دوره','aria-label'=>'جست‌وجوی آموزش','class'=>'form-control']);
    $options = [0=>'همه دسته‌بندی‌ها'];
    foreach ($categories as $cat) { $options[$cat->id] = format_string($cat->name,true,['context'=>context_coursecat::instance($cat->id)]); }
    echo html_writer::select($options,'category',$categoryid,false,['class'=>'custom-select','aria-label'=>'دسته‌بندی']);
    echo html_writer::tag('label',html_writer::empty_tag('input',['type'=>'checkbox','name'=>'favorites','value'=>1]+($favonly?['checked'=>'checked']:[])).' علاقه‌مندی‌ها',['class'=>'seavan-note']);
    echo html_writer::tag('button','جست‌وجو',['type'=>'submit','class'=>'btn btn-primary']);
    if($query!==''||$categoryid||$favonly){echo html_writer::link($url,'پاک کردن',['class'=>'sa-subtle-link']);}
    echo html_writer::end_tag('form');
    $items = $showcatalog
        ? core_course_category::top()->get_courses(['recursive'=>true])
        : array_map(fn($r)=>$r['course'],$enrolled);
    $count = 0;
    $offset=max(0,optional_param('page',0,PARAM_INT))*24;
    $matched=0;
    $sessionmatches = [];
    $normal = fn($s)=>function_exists('local_seavanmedia_normalize')?local_seavanmedia_normalize(core_text::strtolower($s)):core_text::strtolower($s);
    $needle = $normal($query);
    echo html_writer::start_div('seavan-grid');
    foreach ($items as $item) {
        $ctx = context_course::instance($item->id);
        if (!$item->visible && !has_capability('moodle/course:viewhiddencourses',$ctx)) { continue; }
        if ($categoryid && (int)$item->category !== $categoryid) { continue; }
        if ($favonly && !get_user_preferences('seavanmedia_fav_'.$item->id,0)) { continue; }
        $name = format_string($item->fullname,true,['context'=>$ctx]);
        // Search session names only in courses the current user may actually access.
        if ($view === 'catalog' && $query !== '' && count($sessionmatches)<60 &&
                function_exists('local_seavanmedia_is_course') && local_seavanmedia_is_course($item) &&
                (is_enrolled($ctx,null,'',true) || has_capability('moodle/course:update',$ctx))) {
            foreach (local_seavanmedia_course_files($item) as $file) {
                $match = core_text::strpos($normal($file['title'].' '.$file['group']),$needle) !== false;
                $segmentmatch=null;
                if(!$match && core_text::strlen($query)>=3){
                    foreach(sam_transcript($item->id,$file['key']) as $seg){
                        if(core_text::strpos($normal($seg['text']),$needle)!==false){$segmentmatch=$seg;break;}
                    }
                }
                if(!$match&&!$segmentmatch){continue;}
                $sessionmatches[] = ['title'=>$file['title'],'course'=>$name,'id'=>$item->id,'key'=>$file['key'],'group'=>$file['group'],'segment'=>$segmentmatch];
                if (count($sessionmatches)>=60) { break; }
            }
        }
        if ($query !== '' && core_text::strpos($normal(strip_tags($name)),$needle) === false) { continue; }
        $matched++;
        if ($matched <= $offset || $count>=24) { continue; }
        $count++;
        $cat = $categories[$item->category] ?? null;
        $catname = $cat ? format_string($cat->name,true,['context'=>context_coursecat::instance($cat->id)]) : 'آموزش سازمانی';
        $cover = '';
        $courseobj = $item instanceof core_course_list_element ? $item : new core_course_list_element($item);
        foreach ($courseobj->get_course_overviewfiles() as $file) {
            if ($file->is_valid_image()) {
                $imageurl = moodle_url::make_file_url($CFG->wwwroot . '/pluginfile.php', '/' . $file->get_contextid() . '/' . $file->get_component() . '/' . $file->get_filearea() . $file->get_filepath() . $file->get_filename());
                $cover = html_writer::empty_tag('img',['src'=>$imageurl->out(false),'alt'=>'','loading'=>'lazy']);
                break;
            }
        }
        if (!$cover && function_exists('sam_asset_dir') && local_seavanmedia_is_course($item) &&
                (is_enrolled($ctx,null,'',true) || has_capability('moodle/course:update',$ctx))) {
            foreach(local_seavanmedia_course_files($item) as $file){
                if(is_file(sam_asset_dir($item->id,$file['key']).'/cover.jpg')){
                    $cover=html_writer::empty_tag('img',['src'=>sam_asset_url($item->id,$file['key'],'cover')->out(false),'alt'=>'','loading'=>'lazy']);break;
                }
            }
        }
        if (!$cover) { $cover = html_writer::div(html_writer::tag('span','SEAVAN ACADEMY',['class'=>'sa-cover-brand','lang'=>'en','dir'=>'ltr']).html_writer::tag('span',$catname,['class'=>'sa-cover-category']).html_writer::tag('span','▶',['class'=>'seavan-course-symbol','aria-hidden'=>'true']),'sa-cover-fallback'); }
        $courseurl=new moodle_url('/course/view.php',['id'=>$item->id]);
        echo html_writer::start_div('seavan-course');
        echo html_writer::link($courseurl,$cover,['class'=>'seavan-course-cover','aria-label'=>'مشاهده '.strip_tags($name),'tabindex'=>'-1']);
        echo html_writer::start_div('sa-course-body');
        echo html_writer::tag('span',$catname,['class'=>'sa-category-label']);
        echo html_writer::tag('h3',html_writer::link($courseurl,$name));

        $mediahistory = [];
        $media = function_exists('local_seavanmedia_is_course') && local_seavanmedia_is_course($item);
        $mediaaccess = $media && (is_enrolled($ctx,null,'',true) || has_capability('moodle/course:update',$ctx));
        $progress = $enrolled[$item->id]['progress'] ?? null;
        if ($mediaaccess) {
            $mediafiles = array_filter(local_seavanmedia_course_files($item),fn($f)=>in_array($f['kind'],['video','audio'],true));
            $mediahistory = local_seavanmedia_progress($item->id);
            $seconds=0;foreach($mediafiles as $mf){$seconds+=(float)(sam_metadata($item->id,$mf['key'])['duration']??0);}

            $completed = count(array_filter($mediafiles,fn($f)=>($mediahistory[$f['key']]->percent ?? 0)>=95));
            $progress = count($mediafiles) ? 100*$completed/count($mediafiles) : null;
            echo html_writer::tag('span',$digits(count($mediafiles)).' جلسه'.($seconds>0?' · '.$digits(round($seconds/3600,1)).' ساعت':''),['class'=>'seavan-course-label']);
        }
        if ($progress !== null) {
            $percent = max(0,min(100,(int)round($progress)));
            echo html_writer::tag('span',$digits($percent).'٪ تکمیل شده',['class'=>'seavan-course-label']);
            echo html_writer::tag('progress',$percent.'%',['value'=>$percent,'max'=>100,'class'=>'seavan-progress','aria-label'=>'تکمیل فعالیت‌ها']);
        } else {
            if (!$mediaaccess) { echo html_writer::tag('span',isset($enrolled[$item->id])?'در دسترس':'اطلاعات و عضویت',['class'=>'seavan-course-label']); }
        }
        $label = $progress !== null && $progress >= 100 ? 'مرور آموزش' : ($mediaaccess && !empty($mediahistory) ? 'ادامه یادگیری' : (isset($enrolled[$item->id])?'ورود به آموزش':'مشاهده دوره'));
        echo html_writer::link($courseurl,$label,['class'=>'btn btn-primary sa-course-open']);
        echo html_writer::end_div();
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
    if (!$count && !$sessionmatches) {
        $empty=$query!==''?'نتیجه‌ای پیدا نشد.':($view==='mine'?'هنوز دوره‌ای در یادگیری شما نیست.':'هنوز دوره‌ای به کتابخانه اضافه نشده است.');
        $extra=$view==='mine'?html_writer::link($go('catalog'),'مشاهده آموزش‌ها'):($actions?html_writer::link(new moodle_url('/local/seavanmedia/sync.php'),'ورود دوره‌ها'):'');
        echo html_writer::div(html_writer::tag('strong',$empty).html_writer::div($extra,'mt-2'), 'seavan-empty');
    } elseif ($view === 'home') {
        echo html_writer::div(html_writer::link($go($showcatalog?'catalog':'mine'),'مشاهده همه آموزش‌ها',['class'=>'btn btn-outline-primary']),'mt-3');
    }
    if($matched>24){echo $OUTPUT->paging_bar($matched,(int)($offset/24),24,new moodle_url($url,['q'=>$query,'category'=>$categoryid,'favorites'=>$favonly]));}
    if ($sessionmatches) {
        echo html_writer::tag('h2','جلسات و فایل‌های پیدا شده',['class'=>'seavan-section']);
        echo html_writer::start_div('sa-session-results');
        foreach ($sessionmatches as $match) {
            echo html_writer::link(new moodle_url('/local/seavanmedia/view.php',['id'=>$match['id'],'file'=>$match['key']]+($match['segment']?['at'=>$match['segment']['start']]:[])),
                html_writer::tag('strong',s($match['title'])).html_writer::tag('small',$match['course'].' · '.s($match['group'])).($match['segment']?html_writer::tag('small','متن فیلم: '.s(core_text::substr($match['segment']['text'],0,180))):''),
                ['class'=>'sa-session-result']);
        }
        echo html_writer::end_div();
        if (count($sessionmatches)>=60) { echo html_writer::tag('p','برای نتایج دقیق‌تر، عبارت جست‌وجو را کامل‌تر کنید.',['class'=>'seavan-note']); }
    }
}

echo html_writer::end_div();
echo $OUTPUT->footer();
