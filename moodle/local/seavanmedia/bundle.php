<?php
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/bundlelib.php');
require_once($CFG->libdir . '/filelib.php');

$id = required_param('id', PARAM_INT);
$course = local_seavanmedia_require_course($id);
require_sesskey();
if (!sam_download_allowed($id)) {
    throw new moodle_exception('mediaunavailable', 'local_seavanmedia');
}

$back = new moodle_url('/local/seavanmedia/downloads.php', ['id' => $id]);
$folder = local_seavanmedia_folders()[$course->idnumber] ?? '';
$inventory = $folder === '' ? [] : local_seavanmedia_files($folder);
$chapter = optional_param('chapter', '', PARAM_RAW);
$mode = optional_param('mode', $chapter === '' ? 'course' : 'chapter', PARAM_ALPHA);
$filename = 'course-' . $id . '.zip';
switch ($mode) {
    case 'course':
        $files = $inventory;
        break;
    case 'chapter':
        if ($chapter === '') {
            throw new moodle_exception('invalidrequest');
        }
        $files = array_filter($inventory, static fn($file) => $file['group'] === $chapter);
        $filename = 'course-' . $id . '-chapter-' . substr(hash('sha256', $chapter), 0, 8) . '.zip';
        break;
    case 'selected':
        if (!data_submitted()) {
            throw new moodle_exception('invalidrequest');
        }
        $keys = optional_param_array('files', [], PARAM_ALPHANUM);
        if (!$keys || count($keys) > 1000) {
            throw new moodle_exception('bundlechoosefiles', 'local_seavanmedia', $back);
        }
        $selection = array_fill_keys($keys, true);
        $files = array_intersect_key($inventory, $selection);
        if (count($files) !== count($selection)) {
            throw new moodle_exception('bundlestale', 'local_seavanmedia', $back);
        }
        $filename = 'course-' . $id . '-selected.zip';
        break;
    case 'part':
        $number = required_param('part', PARAM_INT);
        $revision = required_param('revision', PARAM_ALPHANUM);
        if (!hash_equals(sam_bundle_revision($inventory), $revision)) {
            throw new moodle_exception('bundlestale', 'local_seavanmedia', $back);
        }
        $parts = sam_bundle_parts($inventory);
        $part = $parts[$number - 1] ?? null;
        if (!$part || $part['direct']) {
            throw new moodle_exception('invalidrequest');
        }
        $files = $part['files'];
        $filename = sprintf('course-%d-part-%02d-of-%02d.zip', $id, $number, count($parts));
        break;
    default:
        throw new moodle_exception('invalidrequest');
}
if (!$files) {
    throw new moodle_exception('mediaunavailable', 'local_seavanmedia', $back);
}
$size = array_sum(array_column($files, 'size'));
if ($size > SEAVAN_BUNDLE_MAX_BYTES) {
    throw new moodle_exception('bundletoolarge', 'local_seavanmedia', $back);
}

$dir = make_temp_directory('seavan-bundles');
$lock = \core\lock\lock_config::get_lock_factory('local_seavanmedia')->get_lock('bundle', 0);
if (!$lock) {
    throw new moodle_exception('bundlebusy', 'local_seavanmedia', $back);
}
$tmp = null;
try {
    $free = disk_free_space($dir);
    // Entries and the central directory add overhead even when the media is stored without recompression.
    if ($free === false || $free < $size + SEAVAN_BUNDLE_RESERVE_BYTES) {
        throw new moodle_exception('bundledisk', 'local_seavanmedia', $back);
    }
    $tmp = tempnam($dir, 'zip-');
    if ($tmp === false) {
        throw new moodle_exception('bundledisk', 'local_seavanmedia', $back);
    }
    // Also runs when PHP is interrupted while building the archive.
    register_shutdown_function(static function() use ($tmp) {
        if (is_file($tmp)) {
            @unlink($tmp);
        }
    });
    \core\session\manager::write_close();
    \core_php_time_limit::raise(1800);
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        throw new moodle_exception('bundlefailed', 'local_seavanmedia', $back);
    }
    foreach ($files as $key => $file) {
        $file = sam_validate_media_file($file, $folder, $key);
        if (str_contains($file['rel'], '\\') || preg_match('~(^|/)\.\.?(/|$)~', $file['rel'])
                || !$zip->addFile($file['path'], $file['rel'])
                || !$zip->setCompressionName($file['rel'], ZipArchive::CM_STORE)) {
            throw new moodle_exception('bundlefailed', 'local_seavanmedia', $back);
        }
    }
    if (!$zip->close()) {
        throw new moodle_exception('bundlefailed', 'local_seavanmedia', $back);
    }
} finally {
    $lock->release();
}

send_temp_file($tmp, $filename);
