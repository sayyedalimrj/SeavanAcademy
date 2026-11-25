<?php
namespace local_seavanmedia\task;
defined('MOODLE_INTERNAL') || die();
class prepare extends \core\task\scheduled_task {
    public function get_name(){return 'Seavan: media metadata and thumbnails';}
    public function execute(){
        global $CFG;
        require_once($CFG->dirroot.'/local/seavanmedia/lib.php');
        require_once($CFG->dirroot.'/local/seavanmedia/assets.php');
        $lock=\core\lock\lock_config::get_lock_factory('local_seavanmedia')->get_lock('prepare',0);
        if(!$lock){return;}
        try{mtrace('Prepared '.\sam_prepare().' media files');}finally{$lock->release();}
    }
}
