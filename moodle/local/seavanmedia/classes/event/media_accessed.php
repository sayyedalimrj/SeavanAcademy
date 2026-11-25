<?php
namespace local_seavanmedia\event;
defined('MOODLE_INTERNAL') || die();
class media_accessed extends \core\event\base {
    protected function init(){ $this->data['crud']='r'; $this->data['edulevel']=self::LEVEL_PARTICIPATING; }
    public static function get_name(){return get_string('eventmediaaccessed','local_seavanmedia');}
    public function get_description(){return "User {$this->userid} accessed media in course {$this->courseid} ({$this->other['action']}).";}
    public function get_url(){return new \moodle_url('/local/seavanmedia/view.php',['id'=>$this->courseid,'file'=>$this->other['filekey']]);}
}
