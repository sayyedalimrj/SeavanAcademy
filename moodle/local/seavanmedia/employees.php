<?php
require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/csvlib.class.php');
require_login();require_capability('moodle/site:uploadusers',context_system::instance());
$url=new moodle_url('/local/seavanmedia/employees.php');
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    require_sesskey();
    $f=$_FILES['staff']??[];
    if(($f['error']??1)!==UPLOAD_ERR_OK||!is_uploaded_file($f['tmp_name'])||$f['size']>5*1024*1024){throw new moodle_exception('invalidrequest');}
    $zip=new ZipArchive();
    if($zip->open($f['tmp_name'])!==true){throw new moodle_exception('invalidrequest');}
    $total=0;
    for($i=0;$i<$zip->numFiles;$i++){$total+=$zip->statIndex($i)['size'];}
    $zip->close();
    if($total>40*1024*1024){throw new moodle_exception('invalidrequest');}
    $reader=new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
    $reader->setReadDataOnly(true);
    $sheets=$reader->listWorksheetInfo($f['tmp_name']);
    if(!$sheets||$sheets[0]['totalRows']>2001||$sheets[0]['totalColumns']>20){throw new moodle_exception('invalidrequest');}
    $reader->setLoadSheetsOnly($sheets[0]['worksheetName']);
    $book=$reader->load($f['tmp_name']);$sheet=$book->getActiveSheet();
    $headers=[];$allowed=['username','firstname','lastname','email','idnumber','department','institution','cohort1','password'];
    foreach($sheet->rangeToArray('A1:'.$sheet->getHighestDataColumn().'1',null,false,false)[0] as $h){$headers[]=strtolower(trim((string)$h));}
    foreach(['username','firstname','lastname','email'] as $required){if(!in_array($required,$headers,true)){throw new moodle_exception('invalidrequest');}}
    if(count(array_unique($headers))!==count($headers)||array_diff($headers,$allowed)){throw new moodle_exception('invalidrequest');}
    $csv=fopen('php://temp','w+');fputcsv($csv,$headers,',','"','');
    for($r=2;$r<=$sheet->getHighestDataRow();$r++){
        $row=$sheet->rangeToArray('A'.$r.':'.$sheet->getHighestDataColumn().$r,null,false,false)[0];
        $row=array_map(fn($x)=>trim((string)$x),$row);
        if(!array_filter($row,fn($x)=>$x!=='')){continue;}
        foreach($row as $x){if(str_starts_with($x,'=')){throw new moodle_exception('invalidrequest');}}
        fputcsv($csv,$row,',','"','');
    }
    rewind($csv);$content=stream_get_contents($csv);fclose($csv);$book->disconnectWorksheets();
    $iid=csv_import_reader::get_new_iid('uploaduser');
    $cir=new csv_import_reader($iid,'uploaduser');
    $cir->load_csv_content($content,'UTF-8','comma');
    if($cir->get_error()!==null){throw new moodle_exception('csvloaderror','',null,$cir->get_error());}
    redirect(new moodle_url('/admin/tool/uploaduser/index.php',['iid'=>$iid]));
}
$PAGE->set_context(context_system::instance());$PAGE->set_url($url);$PAGE->set_title('ورود کارکنان از اکسل');$PAGE->set_heading('ورود کارکنان از اکسل');
echo $OUTPUT->header();
echo html_writer::tag('h2','ورود کارکنان از Excel');
echo html_writer::tag('p','فایل XLSX با سرستون انگلیسی username, firstname, lastname, email و در صورت نیاز idnumber, department, institution, cohort1, password. تا ۲۰۰۰ نفر. مرحله بعد پیش‌نمایش و تأیید استاندارد Moodle است.');
echo html_writer::start_tag('form',['method'=>'post','enctype'=>'multipart/form-data','action'=>$url->out(false)]);
echo html_writer::empty_tag('input',['type'=>'hidden','name'=>'sesskey','value'=>sesskey()]);
echo html_writer::empty_tag('input',['type'=>'file','name'=>'staff','accept'=>'.xlsx','required'=>'required','class'=>'form-control']);
echo html_writer::tag('button','بررسی و ادامه',['type'=>'submit','class'=>'btn btn-primary mt-3']);
echo html_writer::end_tag('form');
echo html_writer::link(new moodle_url('/admin/tool/uploaduser/index.php'),'ورود CSV استاندارد Moodle');
echo $OUTPUT->footer();
