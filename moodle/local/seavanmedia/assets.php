<?php
defined('MOODLE_INTERNAL') || die();
function sam_asset_dir(int $id,string $key): string {
    global $CFG;
    if(!preg_match('/^[a-f0-9]{64}$/D',$key)){throw new coding_exception('Invalid media key');}
    return $CFG->dataroot.'/seavan-assets/'.$id.'/'.$key;
}
function sam_metadata(int $id,string $key): array {
    $p=sam_asset_dir($id,$key).'/meta.json';
    return is_readable($p)?(json_decode(file_get_contents($p),true)?:[]):[];
}
function sam_asset_url(int $id,string $key,string $type): moodle_url {
    return new moodle_url('/local/seavanmedia/asset.php',['id'=>$id,'file'=>$key,'type'=>$type]);
}
function sam_write_json(string $p,array $data): void {
    $tmp=$p.'.'.bin2hex(random_bytes(5)).'.tmp';
    if(file_put_contents($tmp,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),LOCK_EX)===false || !rename($tmp,$p)){
        @unlink($tmp);throw new RuntimeException('Cannot save media metadata');
    }
}
function sam_process(array $cmd,int $seconds): array {
    $spec=[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['file','/dev/null','a']];
    $proc=proc_open(array_merge(['/usr/bin/timeout','--kill-after=5s',(string)$seconds],$cmd),$spec,$pipes,null,null,['bypass_shell'=>true]);
    if(!is_resource($proc)){return [1,''];}
    $out=stream_get_contents($pipes[1],4*1024*1024);
    fclose($pipes[1]);
    return [proc_close($proc),$out];
}
function sam_probe(int $id,array $f): array {
    $dir=sam_asset_dir($id,$f['key']);
    if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir)){throw new RuntimeException('Cannot create asset directory');}
    [$code,$raw]=sam_process(['/usr/bin/ffprobe','-v','error','-show_entries','format=duration,format_name:stream=codec_type,codec_name,pix_fmt,width,height','-of','json',$f['path']],25);
    $data=json_decode($raw,true);
    $meta=['status'=>'error','duration'=>0,'compatible'=>false,'video'=>'','audio'=>'','checked'=>time()];
    if($code===0&&is_array($data)&&!empty($data['streams'])){
        $meta['status']='ok';$meta['duration']=max(0,(float)($data['format']['duration']??0));
        $v=null;$a=null;foreach($data['streams'] as $st){if($st['codec_type']==='video'&&!$v){$v=$st;}if($st['codec_type']==='audio'&&!$a){$a=$st;}}
        $meta['video']=$v['codec_name']??'';$meta['audio']=$a['codec_name']??'';
        $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
        $meta['compatible']=($v&&in_array($ext,['mp4','m4v'],true)&&$meta['video']==='h264'&&in_array($v['pix_fmt']??'',['yuv420p','yuvj420p'],true)&&in_array($meta['audio'],['','aac','mp3'],true))
            ||($v&&$ext==='webm'&&in_array($meta['video'],['vp8','vp9','av1'],true)&&in_array($meta['audio'],['','opus','vorbis'],true))
            ||(!$v&&in_array($ext,['mp3','m4a','wav','ogg','flac'],true));
        if($v&&!is_file($dir.'/cover.jpg')){
            $tmp=$dir.'/cover.tmp.jpg';
            [$rc]=sam_process(['/usr/bin/ffmpeg','-nostdin','-v','error','-ss',(string)min(15,$meta['duration']*0.1),'-i',$f['path'],'-frames:v','1','-vf','scale=480:-2','-y',$tmp],30);
            if($rc===0&&is_file($tmp)&&filesize($tmp)>0){rename($tmp,$dir.'/cover.jpg');}else{@unlink($tmp);}
        }
    }
    sam_write_json($dir.'/meta.json',$meta);
    return $meta;
}
function sam_prepare(int $limit=12): int {
    global $DB;
    $count=0;
    foreach(local_seavanmedia_folders() as $idnumber=>$folder){
        $course=$DB->get_record('course',['idnumber'=>$idnumber],'id');
        if(!$course){continue;}
        foreach(local_seavanmedia_files($folder) as $f){
            if(!in_array(strtolower(pathinfo($f['name'],PATHINFO_EXTENSION)),['mp4','m4v','webm','mov','mkv','avi','wmv','ogv','mp3','m4a','wav','ogg','flac'],true)){continue;}
            if(sam_metadata($course->id,$f['key'])){continue;}
            sam_probe($course->id,$f);
            if(++$count >= $limit){return $count;}
        }
    }
    return $count;
}
function sam_convert(int $id,array $f,int $height=0): void {
    $dir=sam_asset_dir($id,$f['key']);
    $meta=sam_metadata($id,$f['key'])?:sam_probe($id,$f);
    if(empty($meta['video'])){throw new RuntimeException('No video stream');}
    if(disk_free_space($dir)<max(1073741824,$f['size']*2)){throw new RuntimeException('Not enough free storage');}
    $dest=$height===480?'480.mp4':'converted.mp4';
    $tmp=$dir.'/'.$dest.'.tmp.mp4';
    $scale=$height===480?"scale=-2:trunc(min(480\\,ih)/2)*2":"scale=trunc(iw/2)*2:trunc(ih/2)*2";
    try {
        [$rc]=sam_process(['/usr/bin/ffmpeg','-nostdin','-v','error','-i',$f['path'],'-map','0:v:0','-map','0:a:0?','-vf',$scale,'-c:v','libx264','-threads','2','-preset','veryfast','-crf','23','-pix_fmt','yuv420p','-c:a','aac','-movflags','+faststart','-y',$tmp],7200);
        if($rc!==0||!is_file($tmp)||filesize($tmp)===0){throw new RuntimeException('FFmpeg conversion failed');}
        rename($tmp,$dir.'/'.$dest);
    }finally{@unlink($tmp);}
}

function sam_queue(int $id,array $f,string $action): void {
    if(!in_array($action,['convert','transcribe','hash','quality','preview'],true)){throw new coding_exception('Invalid job');}
    $dir=sam_asset_dir($id,$f['key']);
    if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir)){throw new RuntimeException('Cannot make job directory');}
    sam_write_json($dir.'/'.$action.'.job.json',['id'=>$id,'key'=>$f['key'],'queued'=>time()]);
}
function sam_queue_run(string $action): void {
    global $CFG;
    $lock=\core\lock\lock_config::get_lock_factory('local_seavanmedia')->get_lock('queue-'.$action,0);
    if(!$lock){return;}
    try{
        foreach(glob($CFG->dataroot.'/seavan-assets/*/*/'.$action.'.job.json')?:[] as $p){
            $j=json_decode(file_get_contents($p),true);
            if(!is_array($j)){continue;}
            try{
                $c=get_course((int)$j['id']);$f=local_seavanmedia_get_file($c,$j['key']);
                if(sam_asset_dir($c->id,$f['key']).'/'.$action.'.job.json'!==$p){throw new RuntimeException('Invalid job path');}
                if($action==='convert'){sam_convert($c->id,$f);}
                elseif($action==='quality'){sam_convert($c->id,$f,480);}
                elseif($action==='hash'){
                    $hash=hash_file('sha256',$f['path']);
                    if(!$hash){throw new RuntimeException('Hash failed');}
                    sam_write_json(dirname($p).'/sha256.json',['hash'=>$hash,'size'=>$f['size'],'fingerprint'=>$f['fingerprint']??$f['key']]);
                }
                elseif($action==='preview'){sam_previews($c->id,$f);}
                else {
                    [$rc]=sam_process(['/opt/seavan-whisper/bin/python','/opt/seavan-tools/transcribe.py',$f['path'],dirname($p).'/transcript.json'],14400);
                    if($rc!==0){throw new RuntimeException('Transcription failed');}
                }
                sam_write_json(dirname($p).'/'.$action.'.result.json',['status'=>'done','time'=>time()]);
            }catch(\Throwable $e){
                sam_write_json(dirname($p).'/'.$action.'.result.json',['status'=>'error','time'=>time()]);
                mtrace($e->getMessage());
            }
            @unlink($p);return;
        }
    }finally{$lock->release();}
}
function sam_transcript(int $id,string $key): array {
    $p=sam_asset_dir($id,$key).'/transcript.json';
    if(!is_readable($p)||filesize($p)>16*1024*1024){return [];}
    return json_decode(file_get_contents($p),true)?:[];
}

function sam_previews(int $id,array $f): void {
    $dir=sam_asset_dir($id,$f['key']);
    $meta=sam_metadata($id,$f['key'])?:sam_probe($id,$f);
    if(empty($meta['video'])||empty($meta['duration'])){throw new RuntimeException('No video duration');}
    $step=max(1,ceil($meta['duration']/24));
    $count=0;
    for($t=0;$t<$meta['duration']&&$count<24;$t+=$step){
        $tmp=$dir.'/preview.tmp.jpg';
        [$rc]=sam_process(['/usr/bin/ffmpeg','-nostdin','-v','error','-ss',(string)$t,'-i',$f['path'],'-frames:v','1','-vf','scale=200:-2','-y',$tmp],20);
        if($rc!==0||!is_file($tmp)){@unlink($tmp);break;}
        rename($tmp,$dir.'/preview-'.$count.'.jpg');$count++;
    }
    if(!$count){throw new RuntimeException('Preview generation failed');}
    sam_write_json($dir.'/previews.json',['step'=>$step,'count'=>$count]);
}
