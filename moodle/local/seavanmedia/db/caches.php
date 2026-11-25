<?php
defined('MOODLE_INTERNAL') || die();
$definitions = [
    'inventory' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'ttl' => 60,
    ],
];
