<?php
require(__DIR__.'/../../config.php');require_login(null,false);
if(isguestuser()){throw new moodle_exception('noguest');}
$url=new moodle_url('/local/seavanmedia/request.php');
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    require_sesskey();
    if(time()-(int)get_user_preferences('seavanmedia_lastrequest',0)<300){throw new moodle_exception('invalidrequest');}
    $title=core_text::substr(trim(required_param('title',PARAM_TEXT)),0,160);
    $body=core_text::substr(trim(required_param('body',PARAM_TEXT)),0,1000);
    if($title===''){throw new moodle_exception('invalidrequest');}
    $message=new \core\message\message();
    $message->component='local_seavanmedia';$message->name='courserequest';
    $message->userfrom=$USER;$message->userto=get_admin();$message->subject='درخواست آموزش: '.$title;
    $message->fullmessage=$title."\n".$body;$message->fullmessageformat=FORMAT_PLAIN;
    $message->fullmessagehtml='';$message->smallmessage=$title;$message->notification=1;
    if(!message_send($message)){throw new moodle_exception('invalidrequest');}
    set_user_preference('seavanmedia_lastrequest',time());
    redirect(new moodle_url('/course/index.php'),'درخواست به مدیر ارسال شد.');
}
$PAGE->set_context(context_system::instance());$PAGE->set_url($url);$PAGE->set_pagelayout(($CFG->theme??'')==='seavan'?'seavanworkspace':'standard');$PAGE->add_body_class('seavan-workspace');$PAGE->set_title('درخواست آموزش');$PAGE->set_heading('درخواست آموزش');
echo $OUTPUT->header();echo html_writer::tag('h2','چه آموزشی نیاز دارید؟');
echo html_writer::start_tag('form',['method'=>'post','action'=>$url->out(false)]);
echo html_writer::empty_tag('input',['type'=>'hidden','name'=>'sesskey','value'=>sesskey()]);
echo html_writer::tag('label','نام آموزش',['for'=>'sam-request-title']);
echo html_writer::empty_tag('input',['name'=>'title','id'=>'sam-request-title','maxlength'=>160,'required'=>'required','class'=>'form-control']);
echo html_writer::tag('label','توضیحات (اختیاری)',['for'=>'sam-request-body']);
echo html_writer::tag('textarea','',['name'=>'body','id'=>'sam-request-body','rows'=>4,'maxlength'=>1000,'class'=>'form-control']);
echo html_writer::tag('button','ارسال درخواست',['type'=>'submit','class'=>'btn btn-primary mt-3']);
echo html_writer::end_tag('form');echo $OUTPUT->footer();
