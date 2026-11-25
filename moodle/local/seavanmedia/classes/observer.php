<?php
namespace local_seavanmedia;
defined('MOODLE_INTERNAL') || die();
class observer {
    public static function assigned(\core\event\user_enrolment_created $event): void {
        global $DB,$CFG;
        $course=$DB->get_record('course',['id'=>$event->courseid]);
        $user=$DB->get_record('user',['id'=>$event->relateduserid]);
        if(!$course||!$user||$user->deleted||$user->suspended||!$course->visible||!str_starts_with($course->idnumber,'seavanmedia:')){return;}
        try{
            $m=new \core\message\message();$m->component='local_seavanmedia';$m->name='courseassigned';
            $m->userfrom=\core_user::get_noreply_user();$m->userto=$user;
            $m->subject='آموزش جدید در آکادمی سیوان';$m->fullmessage=$course->fullname;$m->fullmessageformat=FORMAT_PLAIN;
            $m->fullmessagehtml='';$m->smallmessage=$course->fullname;$m->notification=1;
            $m->contexturl=(new \moodle_url('/course/view.php',['id'=>$course->id]))->out(false);
            $m->contexturlname='مشاهده آموزش';message_send($m);
        }catch(\Throwable $e){debugging('Seavan notification failed',DEBUG_DEVELOPER);}
    }
}
