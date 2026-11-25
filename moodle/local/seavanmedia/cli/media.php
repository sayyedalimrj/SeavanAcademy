<?php
define('CLI_SCRIPT',true);
require(__DIR__.'/../../../config.php');
require_once(__DIR__.'/../lib.php');require_once(__DIR__.'/../assets.php');
require_once($CFG->libdir.'/clilib.php');
[$opts,$unknown]=cli_get_params(['action'=>'probe','course'=>0,'file'=>'','limit'=>12],[]);
if($unknown){cli_error('Unknown options');}
if(in_array($opts['action'],['convertqueue','transcribequeue'],true)){sam_queue_run(str_replace('queue','',$opts['action']));exit;}
if($opts['action']==='probe'){cli_writeln('Prepared '.sam_prepare(max(1,min(100,(int)$opts['limit']))).' files');exit;}
$id=(int)$opts['course'];$course=get_course($id);
$f=local_seavanmedia_get_file($course,$opts['file']);
$lock=\core\lock\lock_config::get_lock_factory('local_seavanmedia')->get_lock('asset-'.$id.'-'.$f['key'],1);
if(!$lock){cli_error('File is already being processed');}
try{
    if($opts['action']==='convert'){sam_convert($id,$f);}
    elseif($opts['action']==='transcribe'){
        $dir=sam_asset_dir($id,$f['key']);if(!is_dir($dir)){mkdir($dir,0770,true);}
        [$rc]=sam_process(['/opt/seavan-whisper/bin/python','/opt/seavan-tools/transcribe.py',$f['path'],$dir.'/transcript.json'],14400);
        if($rc!==0){throw new RuntimeException('Transcription failed; use whisper service and check model storage');}
    }else{cli_error('Unknown action');}
    cli_writeln('Done: '.$f['name']);
}finally{$lock->release();}
