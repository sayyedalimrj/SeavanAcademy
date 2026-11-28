<?php
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');
require_once(__DIR__ . '/lib.php');

require_login();
$context = context_system::instance();
require_capability('report/log:view', $context);
$url = new moodle_url('/local/seavanmedia/report.php');
$courseid = optional_param('course', 0, PARAM_INT);
$userid = optional_param('user', 0, PARAM_INT);
$page = max(0, optional_param('page', 0, PARAM_INT));
$historypage = max(0, optional_param('historypage', 0, PARAM_INT));
$pagesize = 50;
$course = $courseid ? get_course($courseid) : null;
$courseurl = new moodle_url($url, ['course' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_url($url, ['course' => $courseid, 'user' => $userid]);
$PAGE->set_pagelayout(($CFG->theme ?? '') === 'seavan' ? 'seavanworkspace' : 'standard');
$PAGE->add_body_class('seavan-workspace');
$PAGE->set_title('گزارش تماشای آموزش');
$PAGE->set_heading('گزارش تماشای آموزش');

$durationlabel = static function(float $seconds): string {
    $seconds = (int)round(max(0, $seconds));
    return sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
};
$names = \core_user\fields::for_name()->get_required_fields();
$personlink = static function(int $id, string $label) use ($courseurl): string {
    return html_writer::link(new moodle_url($courseurl, ['user' => $id]), s($label));
};
echo $OUTPUT->header();
echo html_writer::start_div('seavan-shell');
echo html_writer::div(html_writer::link(new moodle_url('/local/seavan/index.php', ['view' => 'manage']), 'بازگشت به مدیریت'), 'sa-admin-back');
echo html_writer::tag('h1', 'گزارش تماشای آموزش', ['class' => 'sa-page-title']);

if (!$course) {
    $table = new html_table();
    $table->head = ['دوره', 'فراگیران ثبت‌نام‌شده', 'افراد دارای سابقهٔ تماشا'];
    $like = $DB->sql_like('c.idnumber', ':mediaid');
    $params = ['mediaid' => 'seavanmedia:%'];
    $total = $DB->count_records_sql("SELECT COUNT(*) FROM {course} c WHERE $like", $params);
    $courses = $DB->get_records_sql("SELECT c.id,c.fullname FROM {course} c WHERE $like ORDER BY c.fullname,c.id", $params,
        $page * $pagesize, $pagesize);
    foreach ($courses as $item) {
        $ctx = context_course::instance($item->id);
        $link = html_writer::link(new moodle_url($url, ['course' => $item->id]), format_string($item->fullname));
        $watched = $DB->count_records_sql(
            'SELECT COUNT(DISTINCT userid) FROM {local_seavanmedia_progress} WHERE courseid = ?', [$item->id]);
        $table->data[] = [$link, count_enrolled_users($ctx), $watched];
    }
    echo $table->data ? html_writer::div(html_writer::table($table), 'table-responsive') :
        html_writer::div('هنوز دوره‌ای در کتابخانه ثبت نشده است.', 'seavan-empty');
    echo $OUTPUT->paging_bar($total, $page, $pagesize, $url);
} else {
    $ctx = context_course::instance($courseid);
    $allfiles = local_seavanmedia_course_files($course);
    $sessions = array_filter($allfiles, static fn($file) => in_array($file['kind'], ['video', 'audio'], true));
    echo html_writer::link($url, 'همه دوره‌ها', ['class' => 'sa-subtle-link']);
    echo html_writer::tag('h2', format_string($course->fullname), ['class' => 'seavan-section']);
    if (!$userid) {
        echo html_writer::tag('p',
            'درصد هر فراگیر میانگین درصد تماشای تمام جلسه‌های فعلی دوره است. هر جلسه وزن برابر دارد و جلسهٔ شروع‌نشده صفر است.',
            ['class' => 'seavan-note']);
        $total = count_enrolled_users($ctx);
        $fields = 'u.id,' . implode(',', array_map(static fn($field) => 'u.' . $field, $names));
        $participants = get_enrolled_users($ctx, '', 0, $fields, null, $page * $pagesize, $pagesize);
        $stats = [];
        if ($participants && $sessions) {
            [$insql, $inparams] = $DB->get_in_or_equal(array_keys($participants));
            $records = $DB->get_recordset_select('local_seavanmedia_progress', "courseid = ? AND userid $insql",
                array_merge([$courseid], $inparams), '', 'userid,filekey,percent');
            foreach ($records as $record) {
                if (isset($sessions[$record->filekey])) {
                    $stats[$record->userid] = ($stats[$record->userid] ?? 0) + max(0, min(100, (int)$record->percent));
                }
            }
            $records->close();
        }
        $summary = new html_table();
        $summary->head = ['فراگیر', 'درصد تماشای دوره'];
        foreach ($participants as $person) {
            $percent = $sessions ? round(($stats[$person->id] ?? 0) / count($sessions), 1) . '٪' : '—';
            $summary->data[] = [$personlink($person->id, fullname($person)), $percent];
        }
        echo $summary->data ? html_writer::div(html_writer::table($summary), 'table-responsive sa-watching-summary') :
            html_writer::div('فراگیری در این دوره ثبت‌نام نشده است.', 'seavan-empty');
        echo $OUTPUT->paging_bar($total, $page, $pagesize, $courseurl);
    } else {
        echo html_writer::div(html_writer::link($courseurl, 'مشاهده همهٔ فراگیران'), 'mb-2');
    }
    echo html_writer::tag('h3', $userid ? 'درصد تماشا به تفکیک جلسه' : 'سابقهٔ تماشا به تفکیک جلسه', ['class' => 'seavan-section']);
    echo html_writer::tag('p', 'مدت تماشا از بازه‌های ثبت‌شدهٔ پخش محاسبه می‌شود. تکرار یک بازه به مدت تماشا اضافه نمی‌کند.',
        ['class' => 'seavan-note']);
    $table = new html_table();
    $table->head = ['کاربر', 'جلسه', 'مدت مشاهده', 'طول جلسه', 'درصد تماشا', 'آخرین فعالیت'];
    $rowvalues = static function(?stdClass $row) use ($durationlabel): array {
        if (!$row) {
            return ['0:00:00', '—', '0٪', 'شروع نشده'];
        }
        $ranges = json_decode($row->rangesjson, true);
        $merged = local_seavanmedia_merge([], is_array($ranges) ? $ranges : [], (float)$row->duration);
        $seconds = array_sum(array_map(static fn($range) => $range[1] - $range[0], $merged));
        return [$durationlabel($seconds), $durationlabel($row->duration), (int)$row->percent . '٪', userdate($row->timemodified)];
    };
    if ($userid) {
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], 'id,' . implode(',', $names));
        $records = $DB->get_records('local_seavanmedia_progress', ['courseid' => $courseid, 'userid' => $userid],
            'timemodified DESC', 'filekey,id,duration,rangesjson,percent,timemodified');
        if (!$records && (!$user || !is_enrolled($ctx, $userid))) {
            throw new moodle_exception('invalidrequest');
        }
        $titles = array_map(static fn($file) => $file['title'], $sessions);
        foreach ($records as $key => $row) {
            $titles[$key] ??= $allfiles[$key]['title'] ?? 'جلسه خارج از آرشیو فعلی';
        }
        $total = count($titles);
        $person = $user ? s(fullname($user)) : 'حساب حذف‌شده';
        foreach (array_slice($titles, $historypage * $pagesize, $pagesize, true) as $key => $title) {
            $table->data[] = [$person, s($title), ...$rowvalues($records[$key] ?? null)];
        }
    } else {
        $conditions = ['courseid' => $courseid];
        $total = $DB->count_records('local_seavanmedia_progress', $conditions);
        $rows = $DB->get_records('local_seavanmedia_progress', $conditions, 'timemodified DESC, id DESC', '*',
            $historypage * $pagesize, $pagesize);
        $ids = array_unique(array_column($rows, 'userid'));
        $users = $ids ? $DB->get_records_list('user', 'id', $ids, '', 'id,deleted,' . implode(',', $names)) : [];
        foreach ($rows as $row) {
            $user = $users[$row->userid] ?? null;
            $label = $user && !$user->deleted ? fullname($user) : 'حساب حذف‌شده';
            $table->data[] = [$personlink($row->userid, $label), s($allfiles[$row->filekey]['title'] ?? 'جلسه خارج از آرشیو فعلی'),
                ...$rowvalues($row)];
        }
    }
    echo $table->data ? html_writer::div(html_writer::table($table), 'table-responsive') :
        html_writer::div('سابقه‌ای برای این فهرست ثبت نشده است.', 'seavan-empty');
    echo $OUTPUT->paging_bar($total, $historypage, $pagesize, new moodle_url($courseurl, ['user' => $userid, 'page' => $page]), 'historypage');
}
echo html_writer::link(new moodle_url('/report/log/index.php', ['id' => $courseid ?: SITEID]), 'لاگ رویدادهای Moodle',
    ['class' => 'btn btn-outline-primary mt-3']);
echo html_writer::end_div();
echo $OUTPUT->footer();
