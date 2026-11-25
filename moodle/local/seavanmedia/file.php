<?php
require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
require_once($CFG->libdir.'/filelib.php');
$id=required_param('id',PARAM_INT);
$course=local_seavanmedia_require_course($id);
$file=local_seavanmedia_get_file($course,required_param('file',PARAM_ALPHANUM));
$download=optional_param('download',0,PARAM_BOOL) || $file['kind']==='download';
if($download && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && empty($_SERVER['HTTP_RANGE'])){
    \local_seavanmedia\event\media_accessed::create(['context'=>context_course::instance($id),'other'=>['filekey'=>$file['key'],'action'=>'download']])->trigger();
}
header('X-Content-Type-Options: nosniff');
send_file($file['path'],str_replace(['"',"\r","\n"],'_',$file['name']),60,0,false,$download,$file['mime'],false,['cacheability'=>'private']);
