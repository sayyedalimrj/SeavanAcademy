<?php
defined('MOODLE_INTERNAL') || die();

function local_seavanmedia_root(): string {
    return '/srv/seavan-courses';
}
function local_seavanmedia_idnumber(string $name): string {
    return 'seavanmedia:' . hash('sha256',$name);
}
function local_seavanmedia_is_course($course): bool {
    return str_starts_with((string)($course->idnumber ?? ''),'seavanmedia:');
}
function local_seavanmedia_folders(): array {
    $root = local_seavanmedia_root();
    if (!is_dir($root) || !is_readable($root)) { return []; }
    $out = [];
    foreach (new DirectoryIterator($root) as $entry) {
        if ($entry->isDot() || $entry->isLink() || !$entry->isDir() || str_starts_with($entry->getFilename(),'.')) { continue; }
        $out[local_seavanmedia_idnumber($entry->getFilename())] = $entry->getPathname();
    }
    return $out;
}
function local_seavanmedia_files(string $folder): array {
    $out = [];
    $base = realpath($folder);
    if (!$base || !is_dir($base)) { return []; }
    $exts = ['mp4'=>'video/mp4','webm'=>'video/webm','m4v'=>'video/mp4','ogv'=>'video/ogg','mov'=>'video/quicktime',
        'mkv'=>'video/x-matroska','pdf'=>'application/pdf','mp3'=>'audio/mpeg','m4a'=>'audio/mp4',
        'zip'=>'application/zip','xlsx'=>'application/octet-stream','docx'=>'application/octet-stream','pptx'=>'application/octet-stream'];
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
        $key = hash('sha256',$rel."\0".$file->getSize()."\0".$file->getMTime());
        $group = dirname($rel) === '.' ? 'جلسات دوره' : dirname($rel);
        $title = pathinfo($file->getFilename(),PATHINFO_FILENAME);
        $kind = in_array($ext,['mp4','webm','m4v','ogv','mov'],true) ? 'video' : ($ext === 'pdf' ? 'pdf' : (in_array($ext,['mp3','m4a'],true) ? 'audio' : 'download'));
        $out[$key] = ['key'=>$key,'path'=>$path,'name'=>$file->getFilename(),'group'=>$group,'title'=>$title,
            'mime'=>$exts[$ext],'kind'=>$kind,'size'=>$file->getSize(),'rel'=>$rel];
    }
    // Numeric prefixes define the chapter order; PDF-only groups follow video groups.
    $rank = [];
    foreach ($out as $f) {
        $n = preg_match('/^(\d+)\s*[-–]/u',$f['title'],$m) && $f['kind']==='video' ? (int)$m[1] : PHP_INT_MAX;
        $rank[$f['group']] = min($rank[$f['group']] ?? PHP_INT_MAX,$n);
    }
    uasort($out,fn($a,$b)=>($rank[$a['group']] <=> $rank[$b['group']]) ?: strnatcasecmp($a['group'],$b['group']) ?: strnatcasecmp($a['title'],$b['title']));
    return $out;
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
    return $files[$key];
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
                $files = local_seavanmedia_files($folder);
                if (!$files) { continue; }
                $course = $DB->get_record('course',['idnumber'=>$idnumber]);
                $created = !$course;
                if (!$course) {
                    $course = create_course((object)['category'=>$cat->id,'fullname'=>core_text::substr(basename($folder),0,254),
                        'shortname'=>'SA-'.substr(hash('sha256',basename($folder)),0,16),'idnumber'=>$idnumber,
                        'format'=>'topics','summary'=>'','summaryformat'=>FORMAT_HTML,'visible'=>1,'startdate'=>time(),
                        'enddate'=>0,'numsections'=>0,'enablecompletion'=>0]);
                }
                $ctx = context_course::instance($course->id);
                $module = $DB->get_record('modules',['name'=>'url'],'*',MUST_EXIST);
                if (!$DB->record_exists('course_modules',['course'=>$course->id,'idnumber'=>'seavanmedia-player'])) {
                    add_moduleinfo((object)['modulename'=>'url','module'=>$module->id,'section'=>0,'visible'=>1,
                        'name'=>'مشاهده جلسات و دانلود فایل‌ها','cmidnumber'=>'seavanmedia-player','intro'=>'','introformat'=>FORMAT_HTML,
                        'externalurl'=>(new moodle_url('/local/seavanmedia/view.php',['id'=>$course->id]))->out(false),
                        'display'=>RESOURCELIB_DISPLAY_OPEN,'groupmode'=>0,'completion'=>0],$course);
                }
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
