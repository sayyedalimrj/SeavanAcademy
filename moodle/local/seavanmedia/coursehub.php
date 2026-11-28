<?php
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');
require_once(__DIR__ . '/lib.php');

require_login();
require_capability('moodle/course:manageactivities', context_system::instance());
$url = new moodle_url('/local/seavanmedia/coursehub.php');
$query = trim(optional_param('q', '', PARAM_TEXT));
$page = max(0, optional_param('page', 0, PARAM_INT));
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url, ['q' => $query]);
$PAGE->set_pagelayout('seavanworkspace');
$PAGE->add_body_class('seavan-workspace');
$PAGE->set_title('دوره‌ها و ثبت‌نام مودل');
$PAGE->set_heading('دوره‌ها و ثبت‌نام مودل');

echo $OUTPUT->header();
echo html_writer::start_div('seavan-shell');
echo html_writer::tag('h1', 'دوره‌ها و ثبت‌نام مودل', ['class' => 'sa-page-title']);
echo html_writer::link(new moodle_url('/course/management.php'), 'مدیریت دسته‌بندی‌های مودل', ['class' => 'btn btn-outline-primary mb-3']);
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $url->out(false), 'class' => 'sa-form-panel mb-3']);
echo html_writer::tag('label', 'جست‌وجوی دوره', ['for' => 'sa-course-query']);
echo html_writer::empty_tag('input', ['id' => 'sa-course-query', 'name' => 'q', 'value' => $query, 'class' => 'form-control']);
echo html_writer::tag('button', 'جست‌وجو', ['type' => 'submit', 'class' => 'btn btn-primary mt-2']);
echo html_writer::end_tag('form');
$where = 'id <> :site';
$params = ['site' => SITEID];
if ($query !== '') {
    $where .= ' AND ' . $DB->sql_like('fullname', ':search', false);
    $params['search'] = '%' . $DB->sql_like_escape($query) . '%';
}
$table = new html_table();
$table->head = ['دوره', 'شرکت‌کنندگان', 'فعالیت‌های دوره', 'گزارش‌ها'];
foreach ($DB->get_records_select('course', $where, $params, 'fullname, id', 'id,fullname,idnumber,visible', $page * 30, 30) as $course) {
    $context = context_course::instance($course->id);
    $id = ['id' => $course->id];
    $link = static fn($path, $text, $extra = []) => html_writer::link(new moodle_url($path, $id + $extra), $text,
        ['class' => 'btn btn-outline-primary btn-sm m-1']);
    // The participants page opens Moodle's enrolment dialog.
    $people = $link('/user/index.php', 'شرکت‌کنندگان و ثبت‌نام');
    $activities = $link('/local/seavanmedia/quizzes.php', 'آزمون‌ها', ['manage' => 1]) .
        $link('/local/seavanmedia/quizzes.php', 'ارزیابی استاد', ['manage' => 1, 'evaluation' => 1]);
    if (local_seavanmedia_is_course($course)) {
        $activities .= $link('/local/seavanmedia/checkpoints.php', 'فعالیت‌های کنار ویدیو');
    }
    $reports = $link('/grade/report/grader/index.php', 'نمرات') . $link('/report/progress/index.php', 'تکمیل فعالیت‌ها');
    if (local_seavanmedia_is_course($course)) {
        $reports .= html_writer::link(new moodle_url('/local/seavanmedia/report.php', ['course' => $course->id]),
            'درصد تماشا', ['class' => 'btn btn-outline-primary btn-sm m-1']);
    }
    $table->data[] = [html_writer::link(new moodle_url('/course/view.php', $id), format_string($course->fullname)), $people, $activities, $reports];
}
echo $table->data ? html_writer::div(html_writer::table($table), 'table-responsive') : html_writer::div('دوره‌ای پیدا نشد.', 'seavan-empty');
echo $OUTPUT->paging_bar($DB->count_records_select('course', $where, $params), $page, 30, new moodle_url($url, ['q' => $query]));
echo html_writer::end_div();
echo $OUTPUT->footer();
