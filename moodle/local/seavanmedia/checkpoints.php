<?php
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/integration.php');

$id = required_param('id', PARAM_INT);
$course = local_seavanmedia_require_course($id);
$context = context_course::instance($id);
require_capability('moodle/course:manageactivities', $context);
$url = new moodle_url('/local/seavanmedia/checkpoints.php', ['id' => $id]);
$setting = 'checkpoints_' . $id;
$files = [];
foreach (local_seavanmedia_course_files($course) as $file) {
    if (in_array($file['kind'], ['video', 'audio'], true)) {
        $files[sam_session_id($file)] = $file;
    }
}
$activities = array_filter(get_fast_modinfo($course)->get_cms(), static fn($cm) =>
    in_array($cm->modname, ['quiz', 'feedback', 'choice'], true) && !$cm->deletioninprogress && $cm->url);
$rows = json_decode(get_config('local_seavanmedia', $setting) ?: '{}', true) ?: [];
if (data_submitted()) {
    require_sesskey();
    $lock = \core\lock\lock_config::get_lock_factory('local_seavanmedia')->get_lock($setting, 5);
    if (!$lock) {
        throw new moodle_exception('invalidrequest');
    }
    try {
        $rows = json_decode(get_config('local_seavanmedia', $setting) ?: '{}', true) ?: [];
        $action = required_param('action', PARAM_ALPHA);
        if ($action === 'delete') {
            unset($rows[required_param('entry', PARAM_ALPHANUMEXT)]);
        } else if ($action === 'add') {
            $session = required_param('session', PARAM_ALPHANUM);
            $activity = required_param('activity', PARAM_INT);
            $timing = required_param('timing', PARAM_ALPHA);
            $seconds = $timing === 'during' ? required_param('seconds', PARAM_INT) : 0;
            if (!isset($files[$session], $activities[$activity]) || !in_array($timing, ['during', 'after'], true) ||
                    ($timing === 'during' && ($seconds < 1 || $seconds > 864000)) || count($rows) >= 1000) {
                throw new moodle_exception('invalidrequest');
            }
            $entry = hash('sha256', "$session:$activity:$timing:$seconds");
            $rows[$entry] = compact('session', 'activity', 'timing', 'seconds');
        } else {
            throw new moodle_exception('invalidrequest');
        }
        set_config($setting, json_encode($rows, JSON_THROW_ON_ERROR), 'local_seavanmedia');
    } finally {
        $lock->release();
    }
    redirect($url);
}

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('seavanworkspace');
$PAGE->add_body_class('seavan-workspace');
$PAGE->set_title('آزمون و نظرسنجی کنار ویدیو');
$PAGE->set_heading(format_string($course->fullname));
echo $OUTPUT->header();
echo html_writer::start_div('seavan-shell');
echo html_writer::tag('h1', 'آزمون و نظرسنجی کنار ویدیو', ['class' => 'sa-page-title']);
echo html_writer::link(new moodle_url('/course/view.php', ['id' => $id]), 'ویرایش فعالیت‌ها در مودل', ['class' => 'btn btn-outline-primary mb-3']);
echo html_writer::tag('p', 'آزمون، بازخورد یا نظرسنجی را در همین دوره بسازید و زمان نمایش پیوند آن را روی ویدیو انتخاب کنید.');
if ($files && $activities) {
    $fileoptions = array_map(static fn($file) => s($file['rel']), $files);
    $options = array_map(static fn($cm) => s($cm->name), $activities);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => 'card p-3 mb-3']);
    foreach (['sesskey' => sesskey(), 'action' => 'add'] as $key => $value) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $key, 'value' => $value]);
    }
    foreach (['session' => ['جلسه', $fileoptions], 'activity' => ['فعالیت', $options],
            'timing' => ['زمان نمایش', ['after' => 'بعد از ویدیو', 'during' => 'حین ویدیو']]] as $key => [$label, $values]) {
        echo html_writer::tag('label', $label, ['for' => 'sa-cp-' . $key]);
        echo html_writer::select($values, $key, array_key_first($values), false,
            ['id' => 'sa-cp-' . $key, 'class' => 'form-control mb-2']);
    }
    echo html_writer::tag('label', 'ثانیهٔ نمایش در حالت «حین ویدیو»', ['for' => 'sa-cp-seconds']);
    echo html_writer::empty_tag('input', ['type' => 'number', 'name' => 'seconds', 'id' => 'sa-cp-seconds',
        'min' => 0, 'value' => 0, 'class' => 'form-control mb-2']);
    echo html_writer::tag('button', 'ثبت زمان نمایش', ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');
}
$table = new html_table();
$table->head = ['جلسه', 'فعالیت', 'زمان نمایش', 'عملیات'];
foreach ($rows as $key => $row) {
    $file = $files[$row['session']] ?? null;
    $activity = $activities[$row['activity']] ?? null;
    $table->data[] = [
        s($file['rel'] ?? 'جلسه در دسترس نیست'), s($activity->name ?? 'فعالیت در دسترس نیست'),
        $row['timing'] === 'during' ? (int)$row['seconds'] . ' ثانیه' : 'پس از پایان',
        $OUTPUT->single_button(new moodle_url($url, ['action' => 'delete', 'entry' => $key]), 'برداشتن زمان نمایش', 'post'),
    ];
}
if ($table->data) {
    echo html_writer::div(html_writer::table($table), 'table-responsive');
}
echo html_writer::end_div();
echo $OUTPUT->footer();
