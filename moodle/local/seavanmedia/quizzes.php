<?php
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
$course = local_seavanmedia_require_course($id);
$context = context_course::instance($id);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/seavanmedia/quizzes.php', ['id' => $id]));
$PAGE->set_pagelayout(($CFG->theme ?? '') === 'seavan' ? 'seavanworkspace' : 'incourse');
$PAGE->add_body_class('seavan-workspace');
$PAGE->set_title('آزمون‌های دوره');
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo html_writer::start_div('seavan-shell');
echo html_writer::div(html_writer::link(new moodle_url('/local/seavanmedia/view.php', ['id' => $id]), 'بازگشت به جلسات'), 'sa-admin-back');
echo html_writer::tag('h1', 'آزمون‌های دوره', ['class' => 'sa-page-title']);
echo html_writer::tag('p', format_string($course->fullname), ['class' => 'seavan-note']);
if (has_capability('moodle/course:manageactivities', $context) && has_capability('mod/quiz:addinstance', $context) && !empty(get_config('mod_quiz', 'version'))) {
    echo html_writer::link(new moodle_url('/course/modedit.php', ['add' => 'quiz', 'course' => $id, 'section' => 0]),
        'افزودن آزمون', ['class' => 'btn btn-primary mb-3']);
}
$quizzes = array_filter(get_fast_modinfo($course)->get_instances_of('quiz'), static fn($cm) => $cm->uservisible && $cm->url);
if (!$quizzes) {
    echo html_writer::div('آزمونی برای شما در این دوره در دسترس نیست.', 'seavan-empty');
}
foreach ($quizzes as $quiz) {
    echo html_writer::div(html_writer::link($quiz->url, format_string($quiz->name), ['class' => 'sa-session-result']), 'mb-2');
}
echo html_writer::end_div();
echo $OUTPUT->footer();
