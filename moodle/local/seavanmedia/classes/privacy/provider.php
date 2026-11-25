<?php
namespace local_seavanmedia\privacy;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
defined('MOODLE_INTERNAL') || die();
class provider implements \core_privacy\local\metadata\provider, \core_privacy\local\request\plugin\provider, \core_privacy\local\request\core_userlist_provider {
    public static function get_metadata(collection $items): collection {
        $fields=[];
        foreach(['userid','courseid','filekey','position','duration','rangesjson','percent','timemodified'] as $f){$fields[$f]='privacy:metadata:'.$f;}
        $items->add_database_table('local_seavanmedia_progress',$fields,'privacy:metadata:progress');
        return $items;
    }
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list=new contextlist();
        $list->add_from_sql('SELECT DISTINCT c.id FROM {context} c JOIN {local_seavanmedia_progress} p ON p.courseid=c.instanceid WHERE c.contextlevel=:level AND p.userid=:userid',['level'=>CONTEXT_COURSE,'userid'=>$userid]);
        return $list;
    }
    public static function get_users_in_context(userlist $userlist) {
        $ctx=$userlist->get_context();
        if($ctx instanceof \context_course){$userlist->add_from_sql('userid','SELECT DISTINCT userid FROM {local_seavanmedia_progress} WHERE courseid=:courseid',['courseid'=>$ctx->instanceid]);}
    }
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        foreach($contextlist->get_contexts() as $ctx){
            if(!$ctx instanceof \context_course){continue;}
            $rows=$DB->get_records('local_seavanmedia_progress',['courseid'=>$ctx->instanceid,'userid'=>$contextlist->get_user()->id]);
            if($rows){writer::with_context($ctx)->export_data(['Seavan media'],(object)['history'=>array_values($rows)]);}
        }
    }
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if($context instanceof \context_course){$DB->delete_records('local_seavanmedia_progress',['courseid'=>$context->instanceid]);}
    }
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        foreach($contextlist->get_contexts() as $ctx){
            if($ctx instanceof \context_course){$DB->delete_records('local_seavanmedia_progress',['courseid'=>$ctx->instanceid,'userid'=>$contextlist->get_user()->id]);}
        }
    }
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $ctx=$userlist->get_context();$ids=$userlist->get_userids();
        if($ctx instanceof \context_course&&$ids){
            [$sql,$params]=$DB->get_in_or_equal($ids,SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_seavanmedia_progress',"courseid=:courseid AND userid $sql",['courseid'=>$ctx->instanceid]+$params);
        }
    }
}
