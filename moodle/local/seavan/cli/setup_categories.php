<?php
// Optional starter categories. No sample courses, users, or content are created.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
\core\session\manager::set_user(get_admin());
$categories = [
    'seavan-technical'=>'دفتر فنی و مهندسی',
    'seavan-project'=>'مدیریت پروژه و اجرا',
    'seavan-software'=>'نرم‌افزار و فناوری',
    'seavan-finance'=>'مالی و اداری',
    'seavan-safety'=>'ایمنی و کارگاه',
    'seavan-skills'=>'مهارت‌های فردی و سازمانی',
    'seavan-onboarding'=>'آشنایی با شرکت',
];
foreach ($categories as $code=>$name) {
    if (!$DB->record_exists('course_categories',['idnumber'=>$code])) {
        core_course_category::create((object)['name'=>$name,'idnumber'=>$code,'parent'=>0]);
        cli_writeln('Created: ' . $name);
    } else {
        cli_writeln('Already exists: ' . $code);
    }
}
