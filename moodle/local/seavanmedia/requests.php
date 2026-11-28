<?php
require(__DIR__ . '/../../config.php');

require_login(null, false);
$context = context_system::instance();
require_capability('moodle/site:config', $context);
$recipient = get_admin();
if (!$recipient || (int)$recipient->id !== (int)$USER->id) {
    throw new moodle_exception('invalidrequest');
}

$filter = optional_param('filter', 'unread', PARAM_ALPHA);
$page = max(0, optional_param('page', 0, PARAM_INT));
$params = ['useridto' => $USER->id, 'component' => 'local_seavanmedia', 'eventtype' => 'courserequest'];
$url = new moodle_url('/local/seavanmedia/requests.php', ['filter' => $filter === 'all' ? 'all' : 'unread']);
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_sesskey();
    $record = $DB->get_record('notifications', $params + ['id' => required_param('id', PARAM_INT)], '*', MUST_EXIST);
    if (!$record->timeread) {
        \core_message\api::mark_notification_as_read($record);
    }
    redirect($url);
}
$where = 'useridto = :useridto AND component = :component AND eventtype = :eventtype';
if ($filter !== 'all') {
    $where .= ' AND timeread IS NULL';
}
$pagesize = 20;
$count = $DB->count_records_select('notifications', $where, $params);
$page = min($page, max(0, (int)ceil($count / $pagesize) - 1));
$records = $DB->get_records_select('notifications', $where, $params, 'timecreated DESC, id DESC', '*', $page * $pagesize, $pagesize);
$senderids = array_unique(array_column($records, 'useridfrom'));
$namefields = \core_user\fields::for_name()->get_required_fields();
$senders = $senderids ? $DB->get_records_list('user', 'id', $senderids, '', 'id,deleted,' . implode(',', $namefields)) : [];
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout(($CFG->theme ?? '') === 'seavan' ? 'seavanworkspace' : 'standard');
$PAGE->add_body_class('seavan-workspace');
$PAGE->set_title('پیام‌ها و درخواست‌های آموزش');
$PAGE->set_heading('پیام‌ها و درخواست‌های آموزش');

echo $OUTPUT->header();
echo html_writer::start_div('seavan-shell');
echo html_writer::div(html_writer::link(new moodle_url('/local/seavan/index.php', ['view' => 'manage']), 'بازگشت به مرکز مدیریت'), 'sa-admin-back');
echo html_writer::tag('h1', 'پیام‌ها و درخواست‌های آموزش', ['class' => 'sa-page-title']);
echo html_writer::start_tag('nav', ['class' => 'sa-subnav', 'aria-label' => 'فیلتر درخواست‌ها']);
foreach (['unread' => 'خوانده‌نشده', 'all' => 'همه درخواست‌ها'] as $value => $label) {
    echo html_writer::link(new moodle_url($url, ['filter' => $value]), $label, $url->get_param('filter') === $value ? ['aria-current' => 'page'] : []);
}
echo html_writer::end_tag('nav');
if (!$records) {
    echo html_writer::div('درخواستی در این فهرست وجود ندارد.', 'seavan-empty');
}
foreach ($records as $record) {
    $sender = $senders[$record->useridfrom] ?? null;
    $body = trim(explode("\n", $record->fullmessage ?? '', 2)[1] ?? '');
    echo html_writer::start_tag('article', ['class' => 'sa-admin-panel sa-request-card']);
    echo html_writer::tag('h2', s($record->smallmessage ?: $record->subject), ['class' => 'seavan-section']);
    echo html_writer::tag('p',
        ($sender && !$sender->deleted ? s(fullname($sender)) : 'کاربر حذف‌شده یا نامشخص') . ' · ' . userdate($record->timecreated),
        ['class' => 'seavan-note']);
    if ($body !== '') {
        echo html_writer::tag('p', nl2br(s($body)), ['class' => 'sa-request-body']);
    }
    echo html_writer::start_div('sa-request-actions');
    echo html_writer::tag('span', $record->timeread ? 'خوانده‌شده' : 'جدید', ['class' => 'sa-status ' . ($record->timeread ? 'sa-ok' : 'sa-warn')]);
    if (!$record->timeread) {
        echo $OUTPUT->single_button(new moodle_url($url, ['id' => $record->id]), 'علامت به‌عنوان خوانده‌شده', 'post');
    }
    if ($sender && !$sender->deleted && !empty($CFG->messaging) && \core_message\api::can_send_message($sender->id, $USER->id)) {
        echo html_writer::link(new moodle_url('/message/index.php', ['id' => $sender->id]), 'پاسخ در پیام‌های Moodle', ['class' => 'btn btn-outline-primary']);
    }
    echo html_writer::end_div();
    echo html_writer::end_tag('article');
}
echo $OUTPUT->paging_bar($count, $page, $pagesize, $url);
echo html_writer::end_div();
echo $OUTPUT->footer();
