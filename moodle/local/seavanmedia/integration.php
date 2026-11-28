<?php
defined('MOODLE_INTERNAL') || die();

/** Stable identity for a relative media path within its course. */
function sam_session_id(array $file): string {
    return hash('sha256', $file['rel']);
}

/** Apply the same Moodle visibility rules to the protected player and media endpoints. */
function sam_session_visibility(stdClass $course): array {
    $prefix = 'seavan-session-';
    $visibility = [];
    foreach (get_fast_modinfo($course)->get_instances_of('url') as $cm) {
        if (str_starts_with((string)$cm->idnumber, $prefix)) {
            $visibility[substr($cm->idnumber, strlen($prefix))] = $cm->uservisible && !$cm->deletioninprogress;
        }
    }
    return $visibility;
}

/** Import the media as Moodle sections and URL activities pointing to protected playback. */
function sam_sync_sections(stdClass $course, array $files, int $urlmodule): void {
    global $DB;
    $sections = ['.' => 0];
    $existing = $DB->get_records_menu('course_modules', ['course' => $course->id, 'module' => $urlmodule,
        'deletioninprogress' => 0], '', 'idnumber,id');
    foreach ($files as $file) {
        $idnumber = 'seavan-session-' . sam_session_id($file);
        if (isset($existing[$idnumber])) {
            continue;
        }
        $group = dirname($file['rel']);
        if (!isset($sections[$group])) {
            $setting = 'sasection_' . $course->id . '_' . substr(hash('sha256', $group), 0, 40);
            $id = get_config('local_seavanmedia', $setting);
            $section = $id ? $DB->get_record('course_sections', ['id' => $id, 'course' => $course->id]) : false;
            if (!$section) {
                $section = course_create_section($course);
                course_update_section($course, $section, ['name' => core_text::substr(str_replace('/', ' / ', $group), 0, 255)]);
                set_config($setting, $section->id, 'local_seavanmedia');
            }
            $sections[$group] = $section->section;
        }
        $url = new moodle_url('/local/seavanmedia/view.php', ['id' => $course->id, 'pathid' => sam_session_id($file)]);
        add_moduleinfo((object)[
            'modulename' => 'url', 'module' => $urlmodule, 'section' => $sections[$group], 'visible' => 1,
            'name' => core_text::substr($file['title'], 0, 255), 'cmidnumber' => $idnumber, 'description' => '',
            'intro' => '', 'introformat' => FORMAT_HTML, 'externalurl' => $url->out(false),
            'display' => RESOURCELIB_DISPLAY_OPEN, 'groupmode' => 0, 'completion' => 0,
        ], $course);
    }
}

/** Find an enabled native manual enrolment instance, adding one only if none was configured. */
function sam_manual_enrolment(stdClass $course, bool $create = false): ?stdClass {
    global $DB;
    $manual = enrol_get_plugin('manual');
    if (!$manual || !enrol_is_enabled('manual')) {
        return null;
    }
    $instances = $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], 'sortorder, id');
    foreach ($instances as $instance) {
        if ($manual->allow_enrol($instance) && (int)$instance->status === ENROL_INSTANCE_ENABLED) {
            return $instance;
        }
    }
    if ($create && !$instances) {
        $id = $manual->add_instance($course, $manual->get_instance_defaults());
        return $id ? $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST) : null;
    }
    return null;
}
