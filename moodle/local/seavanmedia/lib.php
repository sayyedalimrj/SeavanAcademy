<?php
defined('MOODLE_INTERNAL') || die();

function local_seavanmedia_root(): string {
    return '/srv/seavan-courses';
}
function sam_roots(): array {
    global $CFG;
    $roots=['main'=>local_seavanmedia_root(),'disk2'=>'/srv/seavan-courses2','disk3'=>'/srv/seavan-courses3','uploads'=>$CFG->dataroot.'/seavan-uploads'];
    return $roots;
}
function sam_root_for(string $folder): ?string {
    foreach(sam_roots() as $root){
        $real=realpath($root);
        if($real&&str_starts_with($folder,$real.'/')){return $real;}
    }
    return null;
}
function sam_download_allowed(int $id): bool {
    return get_config('local_seavanmedia','nodownload_'.$id)!=='1' ||
        has_capability('moodle/course:update',context_course::instance($id));
}
function local_seavanmedia_idnumber(string $name): string {
    return 'seavanmedia:' . hash('sha256',$name);
}
function local_seavanmedia_is_course($course): bool {
    return str_starts_with((string)$course->idnumber,'seavanmedia:');
}
// Only an explicit marker makes a folder a collection instead of a course.
function local_seavanmedia_folders(): array {
    $out=[];$conflicts=[];
    foreach(sam_roots() as $label=>$rootpath){
        $root=realpath($rootpath);
        if(!$root||!is_dir($root)||!is_readable($root)){continue;}
        $walk=function(string $dir,string $prefix='',int $depth=0)use(&$walk,&$out,&$conflicts,$root,$label){
            if($depth>12){return;}
            foreach(new DirectoryIterator($dir) as $entry){
                if($entry->isDot()||$entry->isLink()||!$entry->isDir()||str_starts_with($entry->getFilename(),'.')){continue;}
                $path=$entry->getRealPath();
                if(!$path||!str_starts_with($path,$root.'/')||!is_readable($path)||is_file($path.'/.seavan-ignore')){continue;}
                $rel=$prefix.$entry->getFilename();
                if(is_file($path.'/.seavan-category')&&!is_link($path.'/.seavan-category')){$walk($path,$rel.'/', $depth+1);continue;}
                $identity=local_seavanmedia_idnumber(($label==='main'?'':$label.'/').$rel);
                $marker=$path.'/.seavan-id';
                if(is_file($marker)&&!is_link($marker)&&filesize($marker)<200){
                    $value=trim(file_get_contents($marker));
                    if(preg_match('/^seavanmedia:[a-f0-9]{64}$/D',$value)){$identity=$value;}
                }
                if(isset($out[$identity])||isset($conflicts[$identity])){unset($out[$identity]);$conflicts[$identity]=true;continue;}
                $out[$identity]=$path;
            }
        };
        $walk($root);
    }
    return $out;
}
function local_seavanmedia_normalize(string $name): string {
    return strtr($name,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','ي'=>'ی','ك'=>'ک','‌'=>' ']);
}
function local_seavanmedia_scan(string $folder): array {
    $out = [];
    $base = realpath($folder);
    if (!$base || !is_dir($base)) { return []; }
    $aliases=[];$manifest=$base.'/.seavan-aliases.json';
    if(is_file($manifest)&&!is_link($manifest)&&filesize($manifest)<1048576){$aliases=json_decode(file_get_contents($manifest),true)?:[];}
    $conflicts=[];
    $exts = ['mp4'=>'video/mp4','webm'=>'video/webm','m4v'=>'video/mp4','ogv'=>'video/ogg','mov'=>'video/quicktime',
        'mkv'=>'video/x-matroska','pdf'=>'application/pdf','mp3'=>'audio/mpeg','m4a'=>'audio/mp4',
        'zip'=>'application/zip','7z'=>'application/octet-stream','rar'=>'application/octet-stream','xlsx'=>'application/octet-stream','xls'=>'application/octet-stream','docx'=>'application/octet-stream','doc'=>'application/octet-stream','pptx'=>'application/octet-stream','ppt'=>'application/octet-stream','txt'=>'text/plain','csv'=>'text/csv','dwg'=>'application/octet-stream','dxf'=>'application/octet-stream','rvt'=>'application/octet-stream','ifc'=>'application/octet-stream','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','wav'=>'audio/wav','ogg'=>'audio/ogg','flac'=>'audio/flac','avi'=>'application/octet-stream','wmv'=>'application/octet-stream','srt'=>'text/plain','vtt'=>'text/vtt'];
    $iter = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS),
        fn($f)=>!$f->isLink() && !str_starts_with($f->getFilename(),'.')
    ),RecursiveIteratorIterator::LEAVES_ONLY,RecursiveIteratorIterator::CATCH_GET_CHILD);
    foreach ($iter as $file) {
        if (!$file->isFile() || !$file->isReadable() || $file->isLink() || $file->getSize() <= 0 || $file->getMTime() > time()-120) { continue; }
        $ext = strtolower($file->getExtension());
        if (!isset($exts[$ext])) { continue; }
        $path = $file->getRealPath();
        if (!$path || !str_starts_with($path,$base.'/')) { continue; }
        $rel = substr($path,strlen($base)+1);
        $fingerprint = hash('sha256',$rel."\0".$file->getSize()."\0".$file->getMTime());
        $alias=$aliases[$rel]??'';
        $key=is_string($alias)&&preg_match('/^[a-f0-9]{64}$/D',$alias)?$alias:$fingerprint;
        if(isset($out[$key])||isset($conflicts[$key])){unset($out[$key]);$conflicts[$key]=true;continue;}
        $group = dirname($rel) === '.' ? 'جلسات دوره' : dirname($rel);
        $title = pathinfo($file->getFilename(),PATHINFO_FILENAME);
        $kind = in_array($ext,['mp4','webm','m4v','ogv','mov','mkv','avi','wmv'],true) ? 'video' : ($ext === 'pdf' ? 'pdf' : (in_array($ext,['mp3','m4a','wav','ogg','flac'],true) ? 'audio' : 'download'));
        $out[$key] = ['key'=>$key,'fingerprint'=>$fingerprint,'path'=>$path,'name'=>$file->getFilename(),'group'=>$group,'title'=>$title,
            'mime'=>$exts[$ext],'kind'=>$kind,'size'=>$file->getSize(),'rel'=>$rel];
    }
    // Numeric prefixes define the chapter order; PDF-only groups follow video groups.
    $rank = [];
    foreach ($out as $f) {
        $n = preg_match('/^\s*(\d+)(?:\D|$)/u',local_seavanmedia_normalize($f['title']),$m) && $f['kind']==='video' ? (int)$m[1] : PHP_INT_MAX;
        $rank[$f['group']] = min($rank[$f['group']] ?? PHP_INT_MAX,$n);
    }
    uasort($out,fn($a,$b)=>($rank[$a['group']] <=> $rank[$b['group']]) ?: strnatcasecmp(local_seavanmedia_normalize($a['group']),local_seavanmedia_normalize($b['group'])) ?: strnatcasecmp(local_seavanmedia_normalize($a['title']),local_seavanmedia_normalize($b['title'])) ?: strcmp($a['rel'],$b['rel']));
    return $out;
}
function local_seavanmedia_files(string $folder, bool $refresh=false): array {
    $cache = cache::make('local_seavanmedia','inventory');
    $key = hash('sha256',$folder);
    $data = $refresh ? false : $cache->get($key);
    if ($data !== false && is_array($data)) { return $data; }
    $data = local_seavanmedia_scan($folder);
    $cache->set($key,$data);
    return $data;
}
function local_seavanmedia_course_files($course): array {
    if (!local_seavanmedia_is_course($course)) { return []; }
    $folder = local_seavanmedia_folders()[$course->idnumber] ?? null;
    return $folder ? local_seavanmedia_files($folder) : [];
}
function local_seavanmedia_require_course(int $id) {
    $course = get_course($id);
    require_login($course,false);
    $ctx = context_course::instance($id);
    if (isguestuser() || !local_seavanmedia_is_course($course) ||
        (!is_enrolled($ctx,null,'',true) && !has_capability('moodle/course:update',$ctx))) {
        throw new moodle_exception('mediaunavailable','local_seavanmedia');
    }
    return $course;
}
function local_seavanmedia_get_file($course,string $key): array {
    $files = local_seavanmedia_course_files($course);
    if (!isset($files[$key])) { throw new moodle_exception('mediaunavailable','local_seavanmedia'); }
    $folder = local_seavanmedia_folders()[$course->idnumber] ?? '';
    return sam_validate_media_file($files[$key], $folder, $key);
}

/** Revalidate a known inventory entry immediately before serving its contents. */
function sam_validate_media_file(array $file, string $folder, string $key): array {
    // Cached inventory is not authorization. Recheck the actual path and fingerprint.
    clearstatcache(true, $file['path']);
    $root = sam_root_for($folder);
    $path = realpath($file['path']);
    if (!$root || !$folder || !$path || !str_starts_with($folder,$root.'/') ||
        !str_starts_with($path,$folder.'/') || $path !== $file['path'] ||
        !is_file($path) || !is_readable($path) || is_link($file['path']) ||
        hash('sha256',$file['rel']."\0".filesize($path)."\0".filemtime($path)) !== ($file['fingerprint']??$key)) {
        throw new moodle_exception('mediaunavailable','local_seavanmedia');
    }
    return $file;
}
function local_seavanmedia_category_for_folder($basecat, string $folder) {
    global $DB;
    $root = sam_root_for($folder);
    if(!$root){return $basecat;}
    $rel = substr($folder,strlen($root)+1);
    $parts = explode('/',$rel);
    array_pop($parts);
    $parent = $basecat;
    $prefix = '';
    foreach ($parts as $name) {
        $prefix .= '/'.$name;
        $idnumber = 'seavanmedia-cat:'.hash('sha256',$prefix);
        $cat = $DB->get_record('course_categories',['idnumber'=>$idnumber]);
        if (!$cat) {
            $cat = core_course_category::create((object)['name'=>core_text::substr($name,0,254),'idnumber'=>$idnumber,'parent'=>$parent->id]);
        }
        $parent = $cat;
    }
    return $parent;
}
function sam_record_revision(int $id,array $files): void {
    global $CFG;
    require_once(__DIR__.'/assets.php');
    $dir=$CFG->dataroot.'/seavan-revisions/'.$id;
    if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir)){return;}
    $manifest=[];
    foreach($files as $f){$manifest[$f['key']]=['path'=>$f['rel'],'size'=>$f['size'],'fingerprint'=>$f['fingerprint']??$f['key']];}
    ksort($manifest);
    $hash=hash('sha256',json_encode($manifest,JSON_INVALID_UTF8_SUBSTITUTE));
    $last=is_readable($dir.'/latest.json')?json_decode(file_get_contents($dir.'/latest.json'),true):[];
    if(($last['hash']??'')===$hash){return;}
    $record=['time'=>time(),'hash'=>$hash,'files'=>$manifest];
    sam_write_json($dir.'/'.time().'-'.substr($hash,0,12).'.json',$record);
    sam_write_json($dir.'/latest.json',$record);
}
function local_seavanmedia_sync(): array {
    global $CFG,$DB;
    require_once($CFG->dirroot.'/course/lib.php');
    require_once($CFG->dirroot.'/course/modlib.php');
    require_once($CFG->libdir.'/resourcelib.php');
    $lock = \core\lock\lock_config::get_lock_factory('local_seavanmedia')->get_lock('folder-sync',2);
    if (!$lock) { return ['همگام‌سازی دیگری در حال اجراست.']; }
    try {
        $folders = local_seavanmedia_folders();
        if (!$folders) { return ['پوشه‌ای در مسیر کتابخانه موجود نیست؛ هیچ دوره‌ای حذف نشد.']; }
        $cat = $DB->get_record('course_categories',['idnumber'=>'seavan-media-library']);
        if (!$cat) { $cat = core_course_category::create((object)['name'=>'کتابخانه ویدئویی شرکت','idnumber'=>'seavan-media-library']); }
        $result = [];
        foreach ($folders as $idnumber=>$folder) {
            try {
                $files = local_seavanmedia_files($folder,true);
                if (!$files) { continue; }
                $course = $DB->get_record('course',['idnumber'=>$idnumber]);
                $created = !$course;
                if (!$course) {
                    $targetcat = local_seavanmedia_category_for_folder($cat,$folder);
                    $course = create_course((object)['category'=>$targetcat->id,'fullname'=>core_text::substr(basename($folder),0,254),
                        'shortname'=>'SA-'.substr(hash('sha256',$idnumber),0,24),'idnumber'=>$idnumber,
                        'format'=>'topics','summary'=>'','summaryformat'=>FORMAT_HTML,'visible'=>1,'startdate'=>time(),
                        'enddate'=>0,'numsections'=>0,'enablecompletion'=>0]);
                }
                $ctx = context_course::instance($course->id);
                // The policy is applied only at creation. Existing enrolment settings are never overwritten.
                if ($created) {
                    require_once($CFG->libdir.'/enrollib.php');
                    $open = get_config('local_seavanmedia','newcourseaccess') === 'self';
                    $self = enrol_get_plugin('self');
                    if ($open && enrol_is_enabled('self') && $self) {
                        $role = $DB->get_record('role',['shortname'=>'student']);
                        if ($role) {
                            $fields = ['status'=>ENROL_INSTANCE_ENABLED,'roleid'=>$role->id,'password'=>'',
                                'customint1'=>0,'customint2'=>0,'customint3'=>0,'customint4'=>0,'customint5'=>0,'customint6'=>1,'enrolperiod'=>0];
                            $instances = $DB->get_records('enrol',['courseid'=>$course->id,'enrol'=>'self']);
                            if ($instances) { $self->update_instance(reset($instances),(object)($fields+['expirynotify'=>0])); }
                            else { $self->add_instance($course,$fields); }
                        }
                    } else if (!$open && $self) {
                        foreach ($DB->get_records('enrol',['courseid'=>$course->id,'enrol'=>'self']) as $instance) {
                            $self->update_status($instance,ENROL_INSTANCE_DISABLED);
                        }
                    }
                }
                $module = $DB->get_record('modules',['name'=>'url'],'*',MUST_EXIST);
                if (!$DB->record_exists('course_modules',['course'=>$course->id,'idnumber'=>'seavanmedia-player'])) {
                    add_moduleinfo((object)['modulename'=>'url','module'=>$module->id,'section'=>0,'visible'=>1,
                        'name'=>'مشاهده جلسات و دانلود فایل‌ها','cmidnumber'=>'seavanmedia-player','intro'=>'','introformat'=>FORMAT_HTML,
                        'externalurl'=>(new moodle_url('/local/seavanmedia/view.php',['id'=>$course->id]))->out(false),
                        'display'=>RESOURCELIB_DISPLAY_OPEN,'groupmode'=>0,'completion'=>0],$course);
                }
                sam_record_revision($course->id,$files);
                if($created){set_config('lastnewcourse',time(),'local_seavanmedia');}
                $result[] = ($created?'ایجاد: ':'به‌روز: ').$course->fullname.' — '.count($files).' فایل';
            } catch (\Throwable $e) {
                $result[] = 'خطا در '.basename($folder).': '.$e->getMessage();
            }
        }
        return $result;
    } finally { $lock->release(); }
}
function local_seavanmedia_url(int $courseid,string $file='',bool $download=false): moodle_url {
    $params=['id'=>$courseid,'file'=>$file];
    if ($download) { $params['download']=1; }
    return new moodle_url('/local/seavanmedia/file.php',$params);
}
function local_seavanmedia_progress(int $courseid): array {
    global $DB,$USER;
    $rows=$DB->get_records('local_seavanmedia_progress',['courseid'=>$courseid,'userid'=>$USER->id],'timemodified DESC');
    $out=[];
    foreach($rows as $r){$out[$r->filekey]=$r;}
    return $out;
}
function local_seavanmedia_merge(array $old,array $new,float $duration): array {
    $all=[];
    foreach(array_merge($old,$new) as $r){
        if(!is_array($r)||count($r)!==2||!is_numeric($r[0])||!is_numeric($r[1])){continue;}
        $a=max(0,min($duration,(float)$r[0]));$b=max(0,min($duration,(float)$r[1]));
        if(is_finite($a)&&is_finite($b)&&$b>$a){$all[]=[$a,$b];}
    }
    usort($all,fn($a,$b)=>$a[0]<=>$b[0]);$merged=[];
    foreach($all as $r){
        $i=count($merged)-1;
        if($i>=0&&$r[0]<=$merged[$i][1]+0.05){$merged[$i][1]=max($merged[$i][1],$r[1]);}
        else{$merged[]=$r;}
    }
    return array_slice($merged,0,2000);
}
