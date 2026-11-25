<?php
require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
require_once($CFG->libdir.'/filelib.php');
$id=required_param('id',PARAM_INT);
$course=local_seavanmedia_require_course($id);
$file=local_seavanmedia_get_file($course,required_param('file',PARAM_ALPHANUM));
$istrack=optional_param('track',0,PARAM_BOOL) && strtolower(pathinfo($file['name'],PATHINFO_EXTENSION))==='vtt';
$download=optional_param('download',0,PARAM_BOOL) || ($file['kind']==='download' && !$istrack);
if($download && !sam_download_allowed($id)){throw new moodle_exception('mediaunavailable','local_seavanmedia');}
if($download && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && empty($_SERVER['HTTP_RANGE'])){
    \local_seavanmedia\event\media_accessed::create(['context'=>context_course::instance($id),'other'=>['filekey'=>$file['key'],'action'=>'download']])->trigger();
}
// Release the Moodle session lock before streaming so progress requests can run in parallel.
\core\session\manager::write_close();
header('X-Content-Type-Options: nosniff');
send_file($file['path'],str_replace(['"',"\r","\n"],'_',$file['name']),60,0,false,$download,$file['mime'],false,['cacheability'=>'private']);
