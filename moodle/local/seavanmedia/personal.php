<?php
require(__DIR__.'/../../config.php');require_once(__DIR__.'/lib.php');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){throw new moodle_exception('invalidrequest');}
$id=required_param('id',PARAM_INT);$course=local_seavanmedia_require_course($id);require_sesskey();
$action=required_param('action',PARAM_ALPHA);
$key=optional_param('file','',PARAM_ALPHANUM);
if($action==='favorite'){
    $pref='seavanmedia_fav_'.$id;
    if(get_user_preferences($pref,0)){unset_user_preference($pref);}else{set_user_preference($pref,1);}
}else{
    $file=local_seavanmedia_get_file($course,$key);
    $pref='seavanmedia_note_'.$id.'_'.$key;
    if($action==='note'){
        $text=core_text::substr(trim(required_param('note',PARAM_TEXT)),0,400);
        $time=max(0,min(864000,optional_param('position',0,PARAM_FLOAT)));
        if($text===''){unset_user_preference($pref);}else{set_user_preference($pref,json_encode(['text'=>$text,'time'=>$time],JSON_UNESCAPED_UNICODE));}
    }elseif($action==='delete'){unset_user_preference($pref);}
}
redirect(new moodle_url('/local/seavanmedia/view.php',['id'=>$id]+($key?['file'=>$key]:[])));
