<?php
// Seavan Academy: native Moodle data, permissions and navigation.
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/completionlib.php');
$medialib = $CFG->dirroot . '/local/seavanmedia/lib.php';
if (is_readable($medialib)) { require_once($medialib); }
require_once($CFG->dirroot . '/course/lib.php');
require_login(null, false);
if (isguestuser()) {
    redirect(new moodle_url('/login/index.php'));
}
$context = context_system::instance();
$view = $seavanroute ?? optional_param('view', 'home', PARAM_ALPHA);
$query = trim(optional_param('q', '', PARAM_TEXT));
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
$add('moodle/site:uploadusers', 'افزودن گروهی کاربران', 'ورود فهرست CSV کارکنان', '/admin/tool/uploaduser/index.php');
$add('moodle/category:manage', 'دوره‌ها و دسته‌بندی', 'ساخت دوره، ویرایش و مرتب‌سازی', '/course/management.php');
$defaultcategory = core_course_category::get_default();
if ($defaultcategory && has_capability('moodle/course:create', context_coursecat::instance($defaultcategory->id))) {
    $actions[] = ['افزودن دوره', 'ایجاد یک آموزش جدید', new moodle_url('/course/edit.php',['category'=>$defaultcategory->id])];
}
$add('moodle/cohort:manage', 'گروه‌های آموزشی', 'مدیریت گروه‌های همکاران', '/cohort/index.php');
$add('report/log:view', 'گزارش فعالیت', 'گزارش‌های استاندارد مودل', '/report/log/index.php', ['id'=>SITEID]);
if (function_exists('local_seavanmedia_sync')) { $add('moodle/site:config','ورود خودکار دوره‌ها','شناسایی پوشه‌ها و ساخت دوره','/local/seavanmedia/sync.php'); }
$add('moodle/site:config', 'ظاهر و فونت', 'بارگذاری ایران‌سنس دارای مجوز', '/admin/settings.php', ['section'=>'themesettingseavan']);
$add('moodle/site:config', 'تنظیمات سامانه', 'پشتیبان‌گیری، افزونه‌ها و سایر تنظیمات', '/admin/search.php');
if (!$actions && $view === 'manage') {
    require_capability('moodle/site:config', $context);
}
if (!in_array($view, ['home','mine','catalog','manage'], true)) { $view = 'home'; }
$PAGE->set_context($context);
$PAGE->set_url($url, ['view'=>$view,'q'=>$query,'category'=>$categoryid]);
$PAGE->set_pagelayout('seavanworkspace');
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
$tabs = ['home'=>'خانه','catalog'=>'کتابخانه آموزش‌ها','mine'=>'دوره‌های من'];
if ($actions) { $tabs['manage'] = 'مدیریت'; }
$go = fn($v)=>$v==='manage' ? new moodle_url('/local/seavan/index.php',['view'=>'manage']) : new moodle_url(match($v){'home'=>'/my/index.php','mine'=>'/my/courses.php','catalog'=>'/course/index.php'});
echo $OUTPUT->header();
echo html_writer::start_div('seavan-shell');
echo html_writer::start_tag('nav', ['class'=>'seavan-nav','aria-label'=>'منوی آکادمی']);
foreach ($tabs as $key=>$label) {
    echo html_writer::link($go($key), $label, $key === $view ? ['aria-current'=>'page'] : []);
}
echo html_writer::end_tag('nav');
if ($view === 'home') {
    echo html_writer::start_div('seavan-hero');
    echo html_writer::tag('span','SEAVAN ACADEMY',['class'=>'seavan-eyebrow','lang'=>'en','dir'=>'ltr']);
    echo html_writer::tag('h2','سلام، ' . s($USER->firstname) . '!');
    echo html_writer::tag('p','آموزش مورد نیازت را پیدا کن؛ از همان جایی که مانده‌ای ادامه بده.');
    echo html_writer::link($go('catalog'),'مشاهده کتابخانه آموزش‌ها',['class'=>'btn btn-light']);
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
            echo html_writer::start_div('seavan-resume-copy');
            echo html_writer::tag('span','آخرین آموزش نیمه‌تمام',['class'=>'seavan-eyebrow']);
            echo html_writer::tag('h3',s($file['title']));
            echo html_writer::tag('p',format_string($course->fullname).' · '.$digits(gmdate('H:i:s',(int)$row->position)),['class'=>'seavan-note']);
            echo html_writer::tag('progress','',['value'=>$row->percent,'max'=>100,'class'=>'seavan-progress','aria-label'=>'بخش‌های مشاهده‌شده']);
            echo html_writer::end_div();
            echo html_writer::link($resume,'ادامه تماشا',['class'=>'btn btn-primary']);
            echo html_writer::end_div();
            break;
        }
    }
}
if ($view === 'manage') {
    echo html_writer::tag('h2','مدیریت آموزش',['class'=>'seavan-section']);
    echo html_writer::tag('p','کاربران، دوره‌ها و فایل‌های آموزشی در یک جا.',['class'=>'seavan-note']);
    echo html_writer::div(html_writer::link(new moodle_url('/my/index.php',['classic'=>1]),'داشبورد کامل Moodle').' · '.html_writer::link(new moodle_url('/course/index.php',['classic'=>1]),'فهرست پیشرفته دوره‌ها'),'seavan-note');
    echo html_writer::start_div('seavan-actions');
    foreach ($actions as [$label,$detail,$link]) {
        echo html_writer::link($link, s($label) . html_writer::tag('small',s($detail)), ['class'=>'seavan-action']);
    }
    echo html_writer::end_div();
    // Course-scoped actions are guarded individually, including category-scoped managers.
    $all = core_course_category::top()->get_courses(['recursive'=>true]);
    $rows = '';
    foreach ($all as $item) {
        $ctx = context_course::instance($item->id);
        $links = [];
        if (has_capability('moodle/course:update',$ctx)) {
            $links[] = html_writer::link(new moodle_url('/course/edit.php',['id'=>$item->id]),'تنظیمات');
        }
        if (has_capability('moodle/course:enrolreview',$ctx)) {
            $links[] = html_writer::link(new moodle_url('/user/index.php',['id'=>$item->id]),'شرکت‌کنندگان');
        }
        if (has_capability('report/log:view',$ctx)) {
            $links[] = html_writer::link(new moodle_url('/report/log/index.php',['id'=>$item->id]),'گزارش');
        }
        if (!$links) { continue; }
        $name = format_string($item->fullname,true,['context'=>$ctx]);
        $rows .= html_writer::tag('tr',html_writer::tag('td',html_writer::link(new moodle_url('/course/view.php',['id'=>$item->id,'classic'=>1]),$name)) .
            html_writer::tag('td',implode(' · ',$links)));
    }
    if ($rows) {
        echo html_writer::tag('h2','مدیریت سریع دوره‌ها',['class'=>'seavan-section']);
        echo html_writer::div(html_writer::tag('table',html_writer::tag('thead',html_writer::tag('tr',html_writer::tag('th','دوره',['scope'=>'col']) . html_writer::tag('th','عملیات',['scope'=>'col']))) . html_writer::tag('tbody',$rows),['class'=>'table table-hover']), 'table-responsive');
    }
} else {
    $title = $view === 'catalog' ? 'کتابخانه آموزش‌ها' : ($view === 'home' ? 'ادامه یادگیری' : 'دوره‌های من');
    echo html_writer::tag('h2',$title,['class'=>'seavan-section']);
    echo html_writer::start_tag('form',['method'=>'get','action'=>$url->out(false),'class'=>'seavan-search']);
    echo html_writer::empty_tag('input',['type'=>'hidden','name'=>'view','value'=>$view]);
    echo html_writer::empty_tag('input',['type'=>'search','name'=>'q','value'=>$query,'placeholder'=>'جست‌وجوی عنوان دوره','aria-label'=>'جست‌وجوی دوره','class'=>'form-control']);
    $options = [0=>'همه دسته‌بندی‌ها'];
    foreach ($categories as $cat) { $options[$cat->id] = format_string($cat->name,true,['context'=>context_coursecat::instance($cat->id)]); }
    echo html_writer::select($options,'category',$categoryid,false,['class'=>'custom-select','aria-label'=>'دسته‌بندی']);
    echo html_writer::tag('button','جست‌وجو',['type'=>'submit','class'=>'btn btn-outline-primary']);
    echo html_writer::end_tag('form');
    $items = $view === 'catalog'
        ? core_course_category::top()->get_courses(['recursive'=>true])
        : array_map(fn($r)=>$r['course'],$enrolled);
    $count = 0;
    echo html_writer::start_div('seavan-grid');
    foreach ($items as $item) {
        $ctx = context_course::instance($item->id);
        if (!$item->visible && !has_capability('moodle/course:viewhiddencourses',$ctx)) { continue; }
        if ($categoryid && (int)$item->category !== $categoryid) { continue; }
        $name = format_string($item->fullname,true,['context'=>$ctx]);
        $normal = fn($s)=>function_exists('local_seavanmedia_normalize')?local_seavanmedia_normalize(core_text::strtolower($s)):core_text::strtolower($s);
        if ($query !== '' && core_text::strpos($normal(strip_tags($name)),$normal($query)) === false) { continue; }
        if ($view === 'home' && $count >= 6) { break; }
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
        if (!$cover) { $cover = html_writer::tag('span',$catname) . html_writer::tag('span','▤',['class'=>'seavan-course-symbol','aria-hidden'=>'true']); }
        echo html_writer::start_div('seavan-course');
        echo html_writer::div($cover,'seavan-course-cover');
        echo html_writer::tag('span',$catname,['class'=>'seavan-course-label']);
        echo html_writer::tag('h3',$name);
        $mediahistory = [];
        $media = function_exists('local_seavanmedia_is_course') && local_seavanmedia_is_course($item);
        $mediaaccess = $media && (is_enrolled($ctx,null,'',true) || has_capability('moodle/course:update',$ctx));
        $progress = $enrolled[$item->id]['progress'] ?? null;
        if ($mediaaccess) {
            $mediafiles = array_filter(local_seavanmedia_course_files($item),fn($f)=>in_array($f['kind'],['video','audio'],true));
            $mediahistory = local_seavanmedia_progress($item->id);
            $completed = count(array_filter($mediafiles,fn($f)=>($mediahistory[$f['key']]->percent ?? 0)>=95));
            $progress = count($mediafiles) ? 100*$completed/count($mediafiles) : null;
            echo html_writer::tag('span',$digits($completed).' از '.$digits(count($mediafiles)).' جلسه مشاهده‌شده',['class'=>'seavan-course-label']);
        }
        if ($progress !== null) {
            $percent = max(0,min(100,(int)round($progress)));
            echo html_writer::tag('span',$digits($percent).($mediaaccess?'٪ جلسات مشاهده‌شده':'٪ تکمیل فعالیت‌ها'),['class'=>'seavan-course-label']);
            echo html_writer::tag('progress',$percent.'%',['value'=>$percent,'max'=>100,'class'=>'seavan-progress','aria-label'=>'تکمیل فعالیت‌ها']);
        } else {
            echo html_writer::tag('span',isset($enrolled[$item->id])?'آموزش آزاد، بدون درصد اجباری':'مشاهده اطلاعات و روش عضویت',['class'=>'seavan-course-label']);
        }
        $label = $progress !== null && $progress >= 100 ? 'مرور آموزش' : ($mediaaccess && !empty($mediahistory) ? 'ادامه یادگیری' : (isset($enrolled[$item->id])?'ورود به آموزش':'مشاهده دوره'));
        echo html_writer::link(new moodle_url('/course/view.php',['id'=>$item->id]),$label,['class'=>'btn btn-primary']);
        if (has_capability('moodle/course:enrolreview',$ctx)) {
            echo html_writer::link(new moodle_url('/user/index.php',['id'=>$item->id]),'مدیریت شرکت‌کنندگان',['class'=>'seavan-secondary']);
        }
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
    if (!$count) {
        echo html_writer::div('دوره‌ای برای نمایش وجود ندارد. ' . html_writer::link($go('catalog'),'مشاهده کتابخانه'), 'seavan-empty');
    } elseif ($view === 'home') {
        echo html_writer::div(html_writer::link($go('mine'),'مشاهده همه دوره‌های من',['class'=>'btn btn-outline-primary']),'mt-3');
    }
}
echo html_writer::tag('p','آکادمی سیوان — یادگیری ساده، هر روز بهتر.',['class'=>'seavan-note mt-4 text-center']);
echo html_writer::end_div();
echo $OUTPUT->footer();
