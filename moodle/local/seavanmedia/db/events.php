<?php
defined('MOODLE_INTERNAL') || die();
$observers=[['eventname'=>'\\core\\event\\user_enrolment_created','callback'=>'\\local_seavanmedia\\observer::assigned']];
