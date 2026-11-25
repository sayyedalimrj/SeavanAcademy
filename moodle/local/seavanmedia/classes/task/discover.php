<?php
namespace local_seavanmedia\task;
defined('MOODLE_INTERNAL') || die();
class discover extends \core\task\scheduled_task {
    public function get_name() { return get_string('syncfolders','local_seavanmedia'); }
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot.'/local/seavanmedia/lib.php');
        \core\session\manager::set_user(get_admin());
        foreach (\local_seavanmedia_sync() as $line) { mtrace($line); }
    }
}
