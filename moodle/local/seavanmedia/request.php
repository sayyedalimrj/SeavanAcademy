<?php
require(__DIR__.'/../../config.php');require_login(null,false);
if(isguestuser()){throw new moodle_exception('noguest');}
$contact = optional_param('contact', false, PARAM_BOOL);
$pageheading = $contact ? 'پیام به مدیر' : 'درخواست آموزش';
$url = new moodle_url('/local/seavanmedia/request.php', ['contact' => (int)$contact]);
$throttle = $contact ? 'seavanmedia_lastcontact' : 'seavanmedia_lastrequest';
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    require_sesskey();
    if(time()-(int)get_user_preferences($throttle, 0)<300){throw new moodle_exception('invalidrequest');}
    $title=core_text::substr(trim(required_param('title',PARAM_TEXT)),0,160);
    $body=core_text::substr(trim(required_param('body',PARAM_TEXT)),0,1000);
    if($title==='' || ($contact && $body==='')){throw new moodle_exception('invalidrequest');}
    $message=new \core\message\message();
    $message->component='local_seavanmedia';$message->name='courserequest';
    $message->userfrom=$USER;$message->userto=get_admin();$message->subject=$pageheading . ': ' . $title;
    $message->fullmessage=$title."\n".$body;$message->fullmessageformat=FORMAT_PLAIN;
    $message->fullmessagehtml='';$message->smallmessage=$contact ? $message->subject : $title;$message->notification=1;
    $message->contexturl = (new moodle_url('/local/seavanmedia/requests.php', ['filter' => 'all']))->out(false);
    $message->contexturlname = 'درخواست‌های آموزش';
    if(!message_send($message)){throw new moodle_exception('invalidrequest');}
    set_user_preference($throttle, time());
    redirect(new moodle_url($contact ? '/my/courses.php' : '/course/index.php'),
        $contact ? 'پیام به مدیر ارسال شد.' : 'درخواست به مدیر ارسال شد.');
}
$PAGE->set_context(context_system::instance());$PAGE->set_url($url);$PAGE->set_pagelayout(($CFG->theme??'')==='seavan'?'seavanworkspace':'standard');$PAGE->add_body_class('seavan-workspace');$PAGE->set_title($pageheading);$PAGE->set_heading($pageheading);
echo $OUTPUT->header();
echo html_writer::div(html_writer::link(new moodle_url('/course/index.php'),'بازگشت به آموزش‌ها'),'sa-admin-back');
echo html_writer::tag('h1',$pageheading,['class'=>'sa-page-title']);
echo html_writer::start_div('sa-form-panel');
echo html_writer::tag('p', $contact ? 'پیام در صندوق اعلان‌های مودل به مدیر می‌رسد.' :
    'موضوع آموزش موردنیاز را بنویسید تا برای بررسی به مدیر ارسال شود.');
echo html_writer::start_tag('form',['method'=>'post','action'=>$url->out(false)]);
echo html_writer::empty_tag('input',['type'=>'hidden','name'=>'sesskey','value'=>sesskey()]);
echo html_writer::tag('label',$contact ? 'موضوع پیام' : 'نام آموزش',['for'=>'sam-request-title']);
echo html_writer::empty_tag('input',['name'=>'title','id'=>'sam-request-title','maxlength'=>160,'required'=>'required','class'=>'form-control']);
echo html_writer::tag('label',$contact ? 'متن پیام' : 'توضیحات (اختیاری)',['for'=>'sam-request-body']);
echo html_writer::tag('textarea','',['name'=>'body','id'=>'sam-request-body','rows'=>4,'maxlength'=>1000,'class'=>'form-control']);
echo html_writer::tag('button',$contact ? 'ارسال پیام' : 'ارسال درخواست',['type'=>'submit','class'=>'btn btn-primary mt-3']);
echo html_writer::end_tag('form');echo html_writer::end_div();echo $OUTPUT->footer();
