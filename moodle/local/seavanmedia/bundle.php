<?php
require(__DIR__.'/../../config.php');require_once(__DIR__.'/lib.php');require_once($CFG->libdir.'/filelib.php');
$id=required_param('id',PARAM_INT);$course=local_seavanmedia_require_course($id);require_sesskey();
$group=optional_param('chapter','',PARAM_RAW);
$files=array_filter(local_seavanmedia_course_files($course),fn($f)=>$group===''||$f['group']===$group);
if(!$files){throw new moodle_exception('mediaunavailable','local_seavanmedia');}
$size=array_sum(array_column($files,'size'));
$dir=make_temp_directory('seavan-bundles');
// Temporary ZIP, no permanent duplication. Limit concurrent jobs and available disk.
if($size>20*1024*1024*1024 || disk_free_space($dir)<$size+1024*1024*1024){throw new moodle_exception('mediaunavailable','local_seavanmedia');}
$lock=\core\lock\lock_config::get_lock_factory('local_seavanmedia')->get_lock('bundle',0);
if(!$lock){throw new moodle_exception('mediaunavailable','local_seavanmedia');}
$tmp=tempnam($dir,'zip-');
if(!$tmp){$lock->release();throw new moodle_exception('mediaunavailable','local_seavanmedia');}
register_shutdown_function(function()use($tmp,$lock){@unlink($tmp);$lock->release();});
\core\session\manager::write_close();
\core_php_time_limit::raise(1800);
$zip=new ZipArchive();
if($zip->open($tmp,ZipArchive::OVERWRITE)!==true){throw new moodle_exception('mediaunavailable','local_seavanmedia');}
foreach($files as $key=>$f){
    $f=local_seavanmedia_get_file($course,$key);
    if(!$zip->addFile($f['path'],$f['rel'])||!$zip->setCompressionName($f['rel'],ZipArchive::CM_STORE)){throw new moodle_exception('mediaunavailable','local_seavanmedia');}
}
if(!$zip->close()){throw new moodle_exception('mediaunavailable','local_seavanmedia');}
send_file($tmp,'course-'.$id.($group===''?'':'-chapter').'.zip',0,0,false,true,'application/zip',false,['cacheability'=>'private']);
