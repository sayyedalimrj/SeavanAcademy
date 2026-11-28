<?php
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');
require_once(__DIR__ . '/integration.php');

require_login(null, false);
$system = context_system::instance();
$manage = optional_param('manage', false, PARAM_BOOL);
$id = optional_param('id', 0, PARAM_INT);
if ($manage) {
    require_capability('moodle/site:config', $system);
} else if (isguestuser()) {
    throw new moodle_exception('noguest');
}
$course = $id ? get_course($id) : null;
$ctx = $course ? context_course::instance($course->id) : $system;
$base = new moodle_url('/local/seavanmedia/enrolrequests.php', ['manage' => (int)$manage]);
$url = new moodle_url($base, ['id' => $id]);
if (!$manage && $course && ($course->id == SITEID || !$course->visible ||
        !core_course_category::get($course->category, IGNORE_MISSING))) {
    throw new moodle_exception('invalidrequest');
}

if (data_submitted()) {
    require_sesskey();
    if (!$course || $course->id == SITEID) {
        throw new moodle_exception('invalidrequest');
    }
    $target = $manage ? required_param('user', PARAM_INT) : $USER->id;
    $person = $DB->get_record('user', ['id' => $target, 'deleted' => 0, 'suspended' => 0], '*', MUST_EXIST);
    if (isguestuser($person)) {
        throw new moodle_exception('invalidrequest');
    }
    $action = required_param('action', PARAM_ALPHA);
    $preference = 'seavan_enrol_' . $id;
    $lock = \core\lock\lock_config::get_lock_factory('local_seavanmedia')->get_lock($preference . ':' . $target, 5);
    if (!$lock) {
        throw new moodle_exception('invalidrequest');
    }
    try {
        $previous = json_decode(get_user_preferences($preference, '{}', $target), true) ?: [];
        if (!$manage && $action === 'request' && !is_enrolled($ctx, $target, '', true) &&
                ($previous['status'] ?? '') !== 'pending') {
            $note = trim(optional_param('note', '', PARAM_TEXT));
            set_user_preference($preference, json_encode([
                'status' => 'pending', 'note' => core_text::substr($note, 0, 500), 'time' => time(),
            ], JSON_UNESCAPED_UNICODE), $target);
            $message = new \core\message\message();
            $message->component = 'local_seavanmedia';
            $message->name = 'courserequest';
            $message->userfrom = $person;
            $message->userto = get_admin();
            $message->subject = 'درخواست عضویت: ' . $course->fullname;
            $message->fullmessage = $message->subject . "\n" . $note;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = '';
            $message->smallmessage = $message->subject;
            $message->notification = 1;
            $message->contexturl = (new moodle_url($base, ['manage' => 1]))->out(false);
            $message->contexturlname = 'درخواست‌های عضویت';
            message_send($message);
        } else if ($manage && in_array($action, ['approve', 'decline'], true) && ($previous['status'] ?? '') === 'pending') {
            require_capability('enrol/manual:enrol', $ctx);
            if ($action === 'approve' && !is_enrolled($ctx, $target, '', true)) {
                $instance = sam_manual_enrolment($course, true);
                $role = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
                if (!$instance || !array_key_exists($role, get_assignable_roles($ctx))) {
                    throw new moodle_exception('invalidrequest');
                }
                enrol_get_plugin('manual')->enrol_user($instance, $target, $role, time(), 0, ENROL_USER_ACTIVE);
            }
            $previous['status'] = $action === 'approve' ? 'approved' : 'declined';
            set_user_preference($preference, json_encode($previous, JSON_UNESCAPED_UNICODE), $target);
        } else {
            throw new moodle_exception('invalidrequest');
        }
    } finally {
        $lock->release();
    }
    redirect($manage ? $base : $url, 'درخواست ثبت شد.');
}

$PAGE->set_context($ctx);
$PAGE->set_url($url);
$PAGE->set_pagelayout('seavanworkspace');
$PAGE->add_body_class('seavan-workspace');
$PAGE->set_title('درخواست عضویت در دوره');
$PAGE->set_heading('درخواست عضویت در دوره');
echo $OUTPUT->header();
echo html_writer::start_div('seavan-shell');
echo html_writer::tag('h1', $manage ? 'درخواست‌های عضویت' : 'درخواست عضویت در دوره', ['class' => 'sa-page-title']);
$labels = ['pending' => 'در انتظار بررسی', 'approved' => 'تأیید شد', 'declined' => 'رد شد'];
if (!$manage && $course) {
    echo html_writer::tag('h2', format_string($course->fullname), ['class' => 'seavan-section']);
    $request = json_decode(get_user_preferences('seavan_enrol_' . $id, '{}'), true) ?: [];
    if (is_enrolled($ctx, null, '', true)) {
        echo html_writer::link(new moodle_url('/course/view.php', ['id' => $id]), 'ورود به دوره', ['class' => 'btn btn-primary']);
    } else {
        if (isset($labels[$request['status'] ?? ''])) {
            echo html_writer::div($labels[$request['status']], 'sa-info');
        }
        if (($request['status'] ?? '') !== 'pending') {
            echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => 'card p-3']);
            foreach (['sesskey' => sesskey(), 'action' => 'request'] as $key => $value) {
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $key, 'value' => $value]);
            }
            echo html_writer::tag('label', 'توضیح برای مدیر (اختیاری)', ['for' => 'sam-enrol-note']);
            echo html_writer::tag('textarea', '', ['id' => 'sam-enrol-note', 'name' => 'note', 'maxlength' => 500,
                'class' => 'form-control mb-2']);
            echo html_writer::tag('button', 'ارسال درخواست عضویت', ['type' => 'submit', 'class' => 'btn btn-primary']);
            echo html_writer::end_tag('form');
        }
    }
    echo html_writer::link($base, 'درخواست‌های من', ['class' => 'btn btn-link']);
} else {
    $page = max(0, optional_param('page', 0, PARAM_INT));
    $like = $DB->sql_like('name', ':pattern');
    $params = ['pattern' => $DB->sql_like_escape('seavan_enrol_') . '%'];
    if (!$manage) {
        $like .= ' AND userid = :uid';
        $params['uid'] = $USER->id;
    }
    $table = new html_table();
    $table->head = $manage ? ['دوره', 'فراگیر', 'وضعیت', 'توضیح', 'عملیات'] : ['دوره', 'وضعیت'];
    $requests = $DB->get_records_select('user_preferences', $like, $params, 'id DESC', '*', $page * 30, 30);
    foreach ($requests as $row) {
        $request = json_decode($row->value, true);
        $course = $DB->get_record('course', ['id' => (int)substr($row->name, strlen('seavan_enrol_'))], 'id,fullname');
        if (!$course || !isset($labels[$request['status'] ?? ''])) {
            continue;
        }
        $status = $labels[$request['status']];
        if (!$manage) {
            $table->data[] = [html_writer::link(new moodle_url($base, ['id' => $course->id]), format_string($course->fullname)), $status];
            continue;
        }
        $namefields = \core_user\fields::for_name()->get_required_fields();
        $person = $DB->get_record('user', ['id' => $row->userid, 'deleted' => 0], 'id,suspended,' . implode(',', $namefields));
        if (!$person) {
            continue;
        }
        $buttons = '';
        if (!$person->suspended && $request['status'] === 'pending' &&
                has_capability('enrol/manual:enrol', context_course::instance($course->id))) {
            foreach (['approve' => 'پذیرش و ثبت‌نام', 'decline' => 'رد درخواست'] as $action => $label) {
                $buttons .= $OUTPUT->single_button(new moodle_url($base, [
                    'id' => $course->id, 'user' => $person->id, 'action' => $action,
                ]), $label, 'post');
            }
        }
        $table->data[] = [format_string($course->fullname), s(fullname($person)), $status, s($request['note'] ?? ''), $buttons];
    }
    echo $table->data ? html_writer::div(html_writer::table($table), 'table-responsive') :
        html_writer::div('درخواستی ثبت نشده است.', 'seavan-empty');
    echo $OUTPUT->paging_bar($DB->count_records_select('user_preferences', $like, $params), $page, 30, $base);
}
echo html_writer::end_div();
echo $OUTPUT->footer();
