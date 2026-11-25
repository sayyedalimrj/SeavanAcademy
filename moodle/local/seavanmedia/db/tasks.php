<?php
defined('MOODLE_INTERNAL') || die();
$tasks = [[
    'classname'=>'\\local_seavanmedia\\task\\discover',
    'blocking'=>0,'minute'=>'*/5','hour'=>'*','day'=>'*','month'=>'*','dayofweek'=>'*','disabled'=>0,
],[
    'classname'=>'\\local_seavanmedia\\task\\prepare',
    'blocking'=>0,'minute'=>'*/10','hour'=>'*','day'=>'*','month'=>'*','dayofweek'=>'*','disabled'=>0,
]];
