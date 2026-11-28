<?php
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/bundlelib.php');

$id = required_param('id', PARAM_INT);
$course = local_seavanmedia_require_course($id);
if (!sam_download_allowed($id)) {
    throw new moodle_exception('mediaunavailable', 'local_seavanmedia');
}
$files = local_seavanmedia_course_files($course);
$groups = [];
foreach ($files as $file) {
    $groups[$file['group']][] = $file;
}
$total = array_sum(array_column($files, 'size'));
$title = format_string($course->fullname);
$downloadurl = new moodle_url('/local/seavanmedia/bundle.php', ['id' => $id, 'sesskey' => sesskey()]);
$bundle = static fn(array $parameters) => new moodle_url($downloadurl, $parameters);
$PAGE->set_url(new moodle_url('/local/seavanmedia/downloads.php', ['id' => $id]));
$PAGE->set_context(context_course::instance($id));
$PAGE->set_pagelayout(($CFG->theme ?? '') === 'seavan' ? 'seavanworkspace' : 'standard');
$PAGE->add_body_class('seavan-workspace');
$PAGE->set_title('دانلود دوره');
$PAGE->set_heading('دانلود دوره');

echo $OUTPUT->header();
echo html_writer::start_div('seavan-shell sa-downloads');
echo html_writer::div(html_writer::link(new moodle_url('/local/seavanmedia/view.php', ['id' => $id]), 'بازگشت به دوره'), 'sa-admin-back');
echo html_writer::tag('h1', 'دانلود دوره', ['class' => 'sa-page-title']);
echo html_writer::tag('p', $title, ['class' => 'seavan-note']);
if (!$files) {
    echo html_writer::div('فایل‌های دوره در آرشیو سرور در دسترس نیستند.', 'seavan-empty');
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_div('sa-download-hero');
echo html_writer::start_div();
echo html_writer::tag('h2', 'دوره کامل', ['class' => 'seavan-section']);
echo html_writer::tag('p', count($files) . ' فایل · ' . display_size($total), ['class' => 'seavan-note']);
echo html_writer::end_div();
if ($total <= SEAVAN_BUNDLE_MAX_BYTES) {
    echo html_writer::link($bundle(['mode' => 'course']), 'دانلود یکجای دوره', ['class' => 'btn btn-primary']);
} else {
    echo html_writer::tag('p', 'برای دوره‌های بزرگ‌تر از ۲۰ گیبی‌بایت، بخش‌ها را جداگانه دانلود کنید.', ['class' => 'sa-status sa-warn']);
}
echo html_writer::end_div();

$parts = sam_bundle_parts($files);
if (count($parts) > 1 || $total > SEAVAN_BUNDLE_MAX_BYTES) {
    echo html_writer::tag('h2', 'دانلود چندبخشی', ['class' => 'seavan-section']);
    echo html_writer::tag('p', 'هر بخش جداگانه باز می‌شود. اگر یک فایل بزرگ‌تر از ۲ گیبی‌بایت باشد، در بخش خودش قرار می‌گیرد.', ['class' => 'seavan-note']);
    echo html_writer::start_div('sa-download-parts');
    $revision = sam_bundle_revision($files);
    foreach ($parts as $index => $part) {
        $number = $index + 1;
        $label = 'دانلود بخش ' . $number;
        if ($part['direct']) {
            $link = local_seavanmedia_url($id, array_key_first($part['files']), true);
            $label .= ' · فایل اصلی';
        } else {
            $link = $bundle(['mode' => 'part', 'part' => $number, 'revision' => $revision]);
        }
        $link = html_writer::link($link, $label, ['class' => 'btn btn-outline-primary']);
        echo html_writer::div($link . html_writer::tag('small', count($part['files']) . ' فایل · ' . display_size($part['size'])), 'sa-download-part');
    }
    echo html_writer::end_div();
}

echo html_writer::tag('h2', 'دانلود فصل یا انتخاب فایل‌ها', ['class' => 'seavan-section']);
echo html_writer::tag('p', 'هر جلسه و پیوست جداگانه قابل دانلود است. برای گرفتن چند فایل در یک ZIP، آن‌ها را علامت بزنید.', ['class' => 'seavan-note']);
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $downloadurl->out_omit_querystring(), 'class' => 'sa-download-form']);
foreach (['id' => $id, 'sesskey' => sesskey(), 'mode' => 'selected'] as $name => $value) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
}
foreach ($groups as $group => $entries) {
    echo html_writer::start_tag('details', ['class' => 'sa-download-group'] + (count($groups) === 1 ? ['open' => 'open'] : []));
    echo html_writer::tag('summary', html_writer::tag('strong', s($group), ['dir' => 'auto']) .
        html_writer::tag('span', count($entries) . ' فایل', ['class' => 'seavan-note']));
    if (array_sum(array_column($entries, 'size')) <= SEAVAN_BUNDLE_MAX_BYTES) {
        echo html_writer::div(html_writer::link($bundle(['mode' => 'chapter', 'chapter' => $group]), 'دانلود این فصل', ['class' => 'btn btn-outline-primary']), 'sa-download-chapter');
    }
    foreach ($entries as $file) {
        $checkbox = html_writer::empty_tag('input', ['name' => 'files[]', 'type' => 'checkbox', 'value' => $file['key']]);
        $name = html_writer::tag('span', s($file['name']), ['dir' => 'auto']);
        $download = html_writer::link(local_seavanmedia_url($id, $file['key'], true), 'دانلود', [
            'class' => 'sa-subtle-link', 'aria-label' => 'دانلود ' . $file['name'],
        ]);
        echo html_writer::div(html_writer::tag('label', $checkbox . $name) .
            html_writer::tag('small', display_size($file['size'])) . $download, 'sa-download-row');
    }
    echo html_writer::end_tag('details');
}
echo html_writer::div(html_writer::tag('button', 'دانلود فایل‌های انتخاب‌شده', ['type' => 'submit', 'class' => 'btn btn-primary']), 'sa-download-submit');
echo html_writer::end_tag('form');
echo html_writer::end_div();
echo $OUTPUT->footer();
