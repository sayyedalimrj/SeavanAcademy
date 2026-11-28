<?php
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/evaluationlib.php');

$id = optional_param('id', 0, PARAM_INT);
$evaluation = optional_param('evaluation', false, PARAM_BOOL);
$manage = optional_param('manage', false, PARAM_BOOL);
$type = $evaluation ? 'feedback' : 'quiz';
$system = context_system::instance();
$course = $id ? get_course($id) : null;
$context = $course ? context_course::instance($id) : $system;
require_login($course, false);
if (isguestuser() || ($course && !is_enrolled($context, null, '', true) &&
        !has_capability('moodle/course:manageactivities', $context))) {
    throw new moodle_exception('invalidrequest');
}
if ($manage) {
    require_capability('moodle/course:manageactivities', $context);
}
$name = $evaluation ? 'ارزیابی استاد' : 'آزمون‌های دوره';
$url = new moodle_url('/local/seavanmedia/quizzes.php', ['id' => $id, 'evaluation' => (int)$evaluation, 'manage' => (int)$manage]);
$enabled = array_key_exists($type, \core\plugininfo\mod::get_enabled_plugins());
$canadd = $course && has_capability('moodle/course:manageactivities', $context) &&
    has_capability('mod/' . $type . ':addinstance', $context);
$canenable = has_capability('moodle/site:config', $system);

if (data_submitted()) {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHA);
    if ($action === 'enable' && $canenable && !$enabled) {
        \core\plugininfo\mod::enable_plugin($type, 1);
        redirect($url);
    }
    if ($action === 'create' && $evaluation && $canadd && $enabled) {
        $instructor = trim(required_param('instructor', PARAM_TEXT));
        $subject = trim(required_param('subject', PARAM_TEXT));
        if (!$instructor || !$subject || core_text::strlen($instructor) > 100 || core_text::strlen($subject) > 100) {
            throw new moodle_exception('invalidrequest');
        }
        $cmid = local_seavanmedia_create_evaluation($course, $instructor, $subject);
        redirect(new moodle_url('/mod/feedback/view.php', ['id' => $cmid]));
    }
    throw new moodle_exception('invalidrequest');
}

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout(($CFG->theme ?? '') === 'seavan' ? 'seavanworkspace' : 'standard');
$PAGE->add_body_class('seavan-workspace');
$PAGE->set_title($name);
$PAGE->set_heading($name);
echo $OUTPUT->header();
echo html_writer::start_div('seavan-shell');
echo html_writer::tag('h1', $name, ['class' => 'sa-page-title']);
if (!$enabled) {
    echo html_writer::div('این فعالیت در تنظیمات مودل غیرفعال است.', 'seavan-empty');
    if ($canenable) {
        echo $OUTPUT->single_button(new moodle_url($url, ['action' => 'enable']), 'فعال‌کردن ' . $name, 'post');
    }
} else if (!$course) {
    $courses = $manage ? get_courses('all', 'c.fullname ASC', 'c.id,c.fullname,c.visible') : enrol_get_my_courses();
    $count = 0;
    foreach ($courses as $item) {
        if ($item->id == SITEID) {
            continue;
        }
        $coursecontext = context_course::instance($item->id);
        if (!$item->visible && !has_capability('moodle/course:viewhiddencourses', $coursecontext)) {
            continue;
        }
        if ($manage && !has_capability('moodle/course:manageactivities', $coursecontext)) {
            continue;
        }
        echo html_writer::div(html_writer::link(new moodle_url($url, ['id' => $item->id]),
            format_string($item->fullname), ['class' => 'sa-session-result']), 'mb-2');
        $count++;
    }
    if (!$count) {
        echo html_writer::div('هنوز دوره‌ای برای نمایش وجود ندارد.', 'seavan-empty');
    }
} else {
    echo html_writer::div(html_writer::link(new moodle_url($url, ['id' => 0]), 'همهٔ دوره‌ها'), 'sa-admin-back');
    echo html_writer::tag('h2', format_string($course->fullname), ['class' => 'seavan-section']);
    if ($canadd && !$evaluation) {
        echo html_writer::link(new moodle_url('/course/modedit.php', ['add' => 'quiz', 'course' => $id, 'section' => 0]),
            'افزودن آزمون', ['class' => 'btn btn-primary mb-3']);
    } else if ($canadd) {
        echo html_writer::start_tag('form', ['action' => $url->out(false), 'method' => 'post', 'class' => 'card p-3 mb-3']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'create']);
        foreach (['instructor' => 'نام استاد', 'subject' => 'نام درس'] as $field => $label) {
            echo html_writer::tag('label', $label, ['for' => $field]);
            echo html_writer::empty_tag('input', [
                'id' => $field, 'name' => $field, 'class' => 'form-control mb-2', 'required' => true, 'maxlength' => 100,
                'value' => $field === 'subject' ? $course->fullname : '',
            ]);
        }
        echo html_writer::tag('button', 'ساخت فرم ۲۰ سؤالی ارزیابی', ['type' => 'submit', 'class' => 'btn btn-primary']);
        echo html_writer::end_tag('form');
    }
    $modules = array_filter(get_fast_modinfo($course)->get_instances_of($type),
        static fn($cm) => $cm->uservisible && $cm->url && (!$evaluation || str_starts_with((string)$cm->idnumber, 'seavan-evaluation-')));
    if (!$modules) {
        echo html_writer::div($evaluation ? 'فرم ارزیابی در دسترس نیست.' : 'آزمونی در دسترس نیست.', 'seavan-empty');
    }
    foreach ($modules as $cm) {
        $links = html_writer::link($cm->url, format_string($cm->name), ['class' => 'sa-session-result']);
        if (has_capability('mod/' . $type . ':viewreports', $cm->context)) {
            $reportpath = $evaluation ? '/mod/feedback/analysis.php' : '/mod/quiz/report.php';
            $links .= html_writer::link(new moodle_url($reportpath, ['id' => $cm->id]), 'نتایج', ['class' => 'btn btn-link']);
        }
        echo html_writer::div($links, 'card p-2 mb-2');
    }
}
echo html_writer::end_div();
echo $OUTPUT->footer();
