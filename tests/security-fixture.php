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
$files=local_seavanmedia_course_files($c);$key=array_key_first($files);
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
