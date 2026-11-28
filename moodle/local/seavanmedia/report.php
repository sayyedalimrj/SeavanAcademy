<?php
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
$context = context_system::instance();
require_capability('report/log:view', $context);
$url = new moodle_url('/local/seavanmedia/report.php');
$courseid = optional_param('course', 0, PARAM_INT);
$userid = optional_param('user', 0, PARAM_INT);
$page = max(0, optional_param('page', 0, PARAM_INT));
$pagesize = 50;
$PAGE->set_context($context);
$PAGE->set_url($url, ['course' => $courseid, 'user' => $userid]);
$PAGE->set_pagelayout(($CFG->theme ?? '') === 'seavan' ? 'seavanworkspace' : 'standard');
$PAGE->add_body_class('seavan-workspace');
$PAGE->set_title('گزارش تماشای آموزش');
$PAGE->set_heading('گزارش تماشای آموزش');

$course = $courseid ? get_course($courseid) : null;
$durationlabel = static function(float $seconds): string {
    $seconds = (int)round(max(0, $seconds));
    return sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
};
echo $OUTPUT->header();
echo html_writer::start_div('seavan-shell');
echo html_writer::div(html_writer::link(new moodle_url('/local/seavan/index.php', ['view' => 'manage']), 'بازگشت به مدیریت'), 'sa-admin-back');
echo html_writer::tag('h1', 'گزارش تماشای آموزش', ['class' => 'sa-page-title']);
echo html_writer::tag('p', 'مدت مشاهده از بازه‌های پخش ثبت‌شده در پلیر به‌دست می‌آید. تکرار یک بازه به این مدت اضافه نمی‌شود.',
    ['class' => 'seavan-note']);

$table = new html_table();
if (!$course) {
    $table->head = ['دوره', 'کاربران فعال', 'جلسه-کاربر ثبت‌شده', 'دیده‌شده', 'آخرین فعالیت'];
    $sql = 'SELECT c.id, c.fullname, COUNT(DISTINCT p.userid) AS learners, COUNT(p.id) AS sessions,
                SUM(CASE WHEN p.percent >= 95 THEN 1 ELSE 0 END) AS completed, MAX(p.timemodified) AS latest
              FROM {course} c JOIN {local_seavanmedia_progress} p ON p.courseid = c.id
              GROUP BY c.id, c.fullname ORDER BY learners DESC, c.id';
    $count = $DB->count_records_sql('SELECT COUNT(DISTINCT courseid) FROM {local_seavanmedia_progress}');
    foreach ($DB->get_records_sql($sql, [], $page * $pagesize, $pagesize) as $row) {
        $link = html_writer::link(new moodle_url($url, ['course' => $row->id]), s($row->fullname));
        $table->data[] = [$link, $row->learners, $row->sessions, $row->completed, userdate($row->latest)];
    }
} else {
    echo html_writer::link($url, 'همه دوره‌ها', ['class' => 'sa-subtle-link']);
    echo html_writer::tag('h2', format_string($course->fullname), ['class' => 'seavan-section']);
    if ($userid) {
        echo html_writer::link(new moodle_url($url, ['course' => $courseid]), 'مشاهده همه کارکنان', ['class' => 'sa-subtle-link']);
    }
    $table->head = ['کاربر', 'جلسه', 'مدت مشاهده', 'طول جلسه', 'پوشش', 'آخرین فعالیت'];
    $files = local_seavanmedia_course_files($course);
    $conditions = ['courseid' => $courseid] + ($userid ? ['userid' => $userid] : []);
    $count = $DB->count_records('local_seavanmedia_progress', $conditions);
    $rows = $DB->get_records('local_seavanmedia_progress', $conditions, 'timemodified DESC, id DESC', '*', $page * $pagesize, $pagesize);
    $userids = array_unique(array_column($rows, 'userid'));
    $users = $userids ? $DB->get_records_list('user', 'id', $userids, '', 'id,deleted,' .
        implode(',', \\core_user\\fields::for_name()->get_required_fields())) : [];
    foreach ($rows as $row) {
        $user = $users[$row->userid] ?? null;
        $label = ($user && !$user->deleted) ? s(fullname($user)) : 'حساب حذف‌شده';
        $person = html_writer::link(new moodle_url($url, ['course' => $courseid, 'user' => $row->userid]), $label);
        $ranges = json_decode($row->rangesjson, true);
        $merged = local_seavanmedia_merge([], is_array($ranges) ? $ranges : [], (float)$row->duration);
        $seconds = array_sum(array_map(static fn($range) => $range[1] - $range[0], $merged));
        $table->data[] = [$person, s($files[$row->filekey]['title'] ?? 'جلسه خارج از آرشیو فعلی'),
            $durationlabel($seconds), $durationlabel($row->duration), (int)$row->percent . '٪', userdate($row->timemodified)];
    }
}
if ($table->data) {
    echo html_writer::div(html_writer::table($table), 'table-responsive');
    echo $OUTPUT->paging_bar($count, $page, $pagesize, new moodle_url($PAGE->url));
} else {
    echo html_writer::div('سابقه‌ای برای این فهرست ثبت نشده است.', 'seavan-empty');
}
echo html_writer::link(new moodle_url('/report/log/index.php', ['id' => $courseid ?: SITEID]), 'لاگ رویدادهای Moodle',
    ['class' => 'btn btn-outline-primary mt-3']);
echo html_writer::end_div();
echo $OUTPUT->footer();
