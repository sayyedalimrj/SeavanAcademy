<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * A drawer based layout for the boost theme.
 *
 * @package   theme_boost
 * @copyright 2021 Bas Brands
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


// Real Moodle rendering hooks and account menu, with one Academy navigation.
$primary = new \core\navigation\output\primary($PAGE);
$menu = $primary->export_for_template($OUTPUT);
$active = $PAGE->url->get_path();
$view = optional_param('view','home',PARAM_ALPHA);
$isplayer = str_ends_with($active,'/local/seavanmedia/view.php');
$manage = has_capability('moodle/site:config', context_system::instance()) ||
    has_capability('moodle/user:create', context_system::instance()) ||
    has_capability('moodle/user:update', context_system::instance()) ||
    has_capability('moodle/category:manage', context_system::instance()) ||
    has_capability('moodle/cohort:manage', context_system::instance()) ||
    has_capability('report/log:view', context_system::instance());
$links = [
    ['text'=>'آموزش‌ها','url'=>new moodle_url('/course/index.php'),'active'=>$isplayer || str_ends_with($active,'/course/index.php') || str_ends_with($active,'/my/index.php') || str_ends_with($active,'/local/seavanmedia/request.php')],
    ['text'=>'یادگیری من','url'=>new moodle_url('/my/courses.php'),'active'=>str_ends_with($active,'/my/courses.php')],
];
if ($manage || ($view === 'manage' && str_ends_with($active,'/local/seavan/index.php'))) {
    $links[] = ['text'=>'مدیریت','url'=>new moodle_url('/local/seavan/index.php',['view'=>'manage']),
        'active'=>(str_contains($active,'/local/seavanmedia/') && !$isplayer && !str_ends_with($active,'/local/seavanmedia/request.php')) || (str_ends_with($active,'/local/seavan/index.php') && $view==='manage')];
}
foreach ($links as &$link) { $link['url'] = $link['url']->out(false); }
unset($link);
echo $OUTPUT->render_from_template('theme_seavan/workspace', [
    'output'=>$OUTPUT,
    'bodyattributes'=>$OUTPUT->body_attributes(),
    'links'=>$links,
    'usermenu'=>$menu['user'],
    'home'=>(new moodle_url('/course/index.php'))->out(false),
    'logo'=>(new moodle_url('/theme/seavan/pix/company-logo.svg'))->out(false),
]);
