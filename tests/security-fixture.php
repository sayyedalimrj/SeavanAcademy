<?php
define('CLI_SCRIPT',true);
require('/var/www/html/config.php');
require_once($CFG->dirroot.'/user/lib.php');
require_once($CFG->dirroot.'/local/seavanmedia/lib.php');
require_once($CFG->dirroot.'/local/seavanmedia/bundlelib.php');
require_once($CFG->libdir.'/enrollib.php');
if($CFG->wwwroot!=='http://127.0.0.1:8080'){throw new RuntimeException('CI only');}
\core\session\manager::set_user(get_admin());
$c=$DB->get_record('course',['fullname'=>'CI Media Course'],'*',MUST_EXIST);
$other=$DB->get_record('course',['fullname'=>'Other Course'],'*',MUST_EXIST);
$user=$DB->get_record('user',['username'=>'ci-learner']);
if(!$user){
    $id=user_create_user((object)['username'=>'ci-learner','firstname'=>'CI','lastname'=>'Learner','email'=>'ci-learner@example.invalid','auth'=>'manual','confirmed'=>1,'mnethostid'=>$CFG->mnet_localhost_id,'password'=>'Ci-Test-Only!2026'],true,false);
}else{$id=$user->id;}
$plugin=enrol_get_plugin('manual');
$instance=$DB->get_record('enrol',['courseid'=>$c->id,'enrol'=>'manual']);
if(!$instance){$instance=$DB->get_record('enrol',['id'=>$plugin->add_instance($c)],'*',MUST_EXIST);}
$role=$DB->get_record('role',['shortname'=>'student'],'*',MUST_EXIST);
$plugin->enrol_user($instance,$id,$role->id);
set_config('nodownload_'.$c->id,'1','local_seavanmedia');
require_once($CFG->libdir.'/testing/generator/lib.php');
$generator = new testing_data_generator();
if (!$DB->record_exists('quiz', ['course' => $c->id, 'name' => 'CI Moodle Quiz'])) {
    require_once($CFG->dirroot.'/mod/quiz/locallib.php');
    $quiz = $generator->create_module('quiz', ['course' => $c->id, 'name' => 'CI Moodle Quiz']);
    $questions = $generator->get_plugin_generator('core_question');
    $category = $questions->create_question_category(['contextid' => context_course::instance($c->id)->id]);
    $emptyfeedback = ['text' => '', 'format' => FORMAT_HTML];
    $question = question_bank::get_qtype('truefalse')->save_question(
        (object)['qtype' => 'truefalse'],
        (object)[
            'category' => (string)$category->id,
            'name' => 'CI protected playback quiz question',
            'questiontext' => ['text' => 'This is a real Moodle quiz.', 'format' => FORMAT_HTML],
            'defaultmark' => 1,
            'penalty' => 1,
            'correctanswer' => 1,
            'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
            'generalfeedback' => $emptyfeedback,
            'feedbacktrue' => $emptyfeedback,
            'feedbackfalse' => $emptyfeedback,
        ]
    );
    quiz_add_quiz_question($question->id, $quiz);
    \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
}

$files=local_seavanmedia_course_files($c);$key=array_key_first($files);
$progress = ['userid' => $id, 'courseid' => $c->id, 'filekey' => $key];
if (!$DB->record_exists('local_seavanmedia_progress', $progress)) {
    $DB->insert_record('local_seavanmedia_progress', (object)($progress + [
        'position' => 10, 'duration' => 20, 'rangesjson' => '[[0,9]]', 'percent' => 45, 'timemodified' => time(),
    ]));
}

$otherfiles = local_seavanmedia_course_files($other);
$otherkey = array_key_first($otherfiles);
$small = ['a' => ['size' => 4], 'b' => ['size' => 5], 'c' => ['size' => 8], 'd' => ['size' => 25]];
$parts = sam_bundle_parts($small, 10, 20);
if (count($parts) !== 3 || array_keys($parts[0]['files']) !== ['a', 'b'] ||
        array_keys($parts[1]['files']) !== ['c'] || $parts[1]['direct'] || !$parts[2]['direct']) {
    throw new RuntimeException('Incorrect multipart plan');
}
echo json_encode([
    'course' => $c->id, 'key' => $key, 'other' => $other->id, 'otherkey' => $otherkey,
    'otherrevision' => sam_bundle_revision($otherfiles),
    'otherfiles' => array_map(fn($file) => ['rel' => $file['rel'], 'group' => $file['group']], $otherfiles),
]);
