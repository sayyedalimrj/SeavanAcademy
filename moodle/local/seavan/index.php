<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');
require_login();
$context = context_system::instance();
$query = optional_param('q', '', PARAM_TEXT);
$url = new moodle_url('/local/seavan/index.php');
$PAGE->set_context($context);
$PAGE->set_url($url, $query === '' ? [] : ['q' => $query]);
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_title('آکادمی سیوان');
$PAGE->set_heading('آکادمی سیوان');
$catalog = new moodle_url('/course/index.php');
$actions = [];
if (has_capability('moodle/user:create', $context)) {
    $actions[] = ['افزودن کاربر', 'ساخت حساب همکار جدید', new moodle_url('/user/editadvanced.php', ['id' => -1])];
}
if (has_capability('moodle/user:update', $context)) {
    $actions[] = ['مدیریت کاربران', 'ویرایش و جست‌وجوی حساب‌ها', new moodle_url('/admin/user.php')];
}
if (has_capability('moodle/course:create', $context) || has_capability('moodle/category:manage', $context)) {
    $actions[] = ['مدیریت دوره‌ها', 'ساخت دوره و دسته‌بندی', new moodle_url('/course/management.php')];
}
if (has_capability('moodle/cohort:manage', $context)) {
    $actions[] = ['گروه‌های آموزشی', 'اختصاص گروهی دوره‌ها از روش ثبت‌نام گروهی', new moodle_url('/cohort/index.php')];
}
if (has_capability('report/log:view', $context)) {
    $actions[] = ['گزارش فعالیت', 'مرور گزارش‌های استاندارد مودل', new moodle_url('/report/log/index.php', ['id' => SITEID])];
}
echo $OUTPUT->header();
echo html_writer::start_div('seavan-shell');
echo html_writer::start_div('seavan-hero');
echo html_writer::tag('h2', 'سلام، ' . s($USER->firstname) . '!');
echo html_writer::tag('p', 'اینجا برای یادگیری کنار هم هستیم. آموزش‌ها را با سرعت خودت دنبال کن.');
echo html_writer::link($catalog, 'مشاهده کتابخانه آموزش‌ها', ['class' => 'btn btn-light']);
echo html_writer::end_div();
echo html_writer::tag('h2', 'دوره‌های من', ['class' => 'seavan-section']);
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $url->out(false), 'class' => 'seavan-search']);
echo html_writer::empty_tag('input', ['type'=>'search','name'=>'q','value'=>$query,'placeholder'=>'جست‌وجوی دوره‌های من','aria-label'=>'جست‌وجوی دوره','class'=>'form-control']);
echo html_writer::tag('button', 'جست‌وجو', ['type'=>'submit','class'=>'btn btn-outline-primary']);
echo html_writer::end_tag('form');
$courses = enrol_get_my_courses();
$count = 0;
echo html_writer::start_div('seavan-grid');
foreach ($courses as $item) {
    $course = get_course($item->id);
    $coursecontext = context_course::instance($course->id);
    if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $coursecontext)) {
        continue;
    }
    $name = format_string($course->fullname, true, ['context'=>$coursecontext]);
    if ($query !== '' && core_text::strpos(core_text::strtolower(strip_tags($name)), core_text::strtolower($query)) === false) {
        continue;
    }
    $count++;
    echo html_writer::start_div('seavan-course');
    $category = core_course_category::get($course->category, IGNORE_MISSING);
    $categoryname = $category ? format_string($category->name) : 'آموزش سازمانی';
    echo html_writer::div(html_writer::tag('span', $categoryname) . html_writer::tag('span','▤',['aria-hidden'=>'true','class'=>'seavan-course-symbol']), 'seavan-course-cover');
    echo html_writer::tag('h3', $name);
    $progress = \core_completion\progress::get_course_progress_percentage($course);
    if ($progress !== null) {
        $percent = max(0, min(100, (int)round($progress)));
        echo html_writer::tag('span', $percent . '٪ پیشرفت', ['class'=>'seavan-course-label']);
        echo html_writer::tag('progress', $percent . '%', ['value'=>$percent,'max'=>100,'class'=>'seavan-progress','aria-label'=>'پیشرفت دوره']);
    }
    echo html_writer::link(new moodle_url('/course/view.php',['id'=>$course->id]),'ورود به دوره',['class'=>'btn btn-primary']);
    if (has_capability('moodle/course:enrolreview', $coursecontext)) {
        echo html_writer::link(new moodle_url('/user/index.php',['id'=>$course->id]),'مدیریت شرکت‌کنندگان');
    }
    echo html_writer::end_div();
}
echo html_writer::end_div();
if (!$count) {
    echo html_writer::div($query === '' ? 'هنوز دوره‌ای به شما اختصاص داده نشده. کتابخانه آموزش‌ها را ببینید.' : 'دوره‌ای با این عبارت پیدا نشد.', 'seavan-empty');
}
if ($actions) {
    echo html_writer::tag('h2','مدیریت آموزش',['class'=>'seavan-section']);
    echo html_writer::start_div('seavan-actions');
    foreach ($actions as [$label,$detail,$link]) {
        echo html_writer::link($link, s($label) . html_writer::tag('small',s($detail)), ['class'=>'seavan-action']);
    }
    echo html_writer::end_div();
}
echo html_writer::end_div();
echo $OUTPUT->footer();
