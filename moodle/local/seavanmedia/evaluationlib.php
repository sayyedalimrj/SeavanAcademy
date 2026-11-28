<?php
defined('MOODLE_INTERNAL') || die();

/** The printed Seavan instructor evaluation, in its original order. */
function local_seavanmedia_evaluation_items(): array {
    return [
        'تسلط و احاطه بر موضوع درس',
        'توانایی پاسخگویی به سؤالات فراگیران',
        'توانایی ارائه مثال و تمرین‌های مربوط و مناسب',
        'توانایی تفهیم مطالب درسی به فراگیران',
        'میزان ارائه مطالب و اطلاعات به‌روز علمی',
        'مشارکت دادن فراگیران در فعالیت‌های علمی و مطالب درسی به تناسب موضوع',
        'نحوه ارائه مطالب به گونه‌ای که فراگیران مشتاق شنیدن و کسب اطلاع بیشتر باشند',
        'مشخص نمودن عناوین و اهداف درس از همان جلسه اول',
        'توانایی و رغبت در رفع مشکلات علمی فراگیران در کلاس درس',
        'زمان‌بندی مناسب و سعی در حداکثر بهره‌برداری از وقت کلاس',
        'انجام پرسش و پاسخ در کلاس',
        'استفاده صحیح و به‌جا از وسایل کمک‌آموزشی',
        'مهارت در معرفی موضوع و جلب علاقه فراگیران به تناسب موضوع',
        'میزان راهنمایی و معرفی منابع فرعی برای یادگیری درس',
        'دلسوزی و تلاش در جهت بالا بردن میزان یادگیری فراگیران',
        'میزان تلاش در رفع نقایص مطرح‌شده از جانب فراگیران',
        'صبر و شکیبایی در پاسخگویی به سؤالات و انتقادات فراگیران',
        'وقت‌شناسی و حضور به‌موقع در کلاس',
        'نحوه برخورد استاد با فراگیران و ایجاد احترام، تفاهم و اعتماد متقابل به هنگام تدریس و خارج از کلاس',
        'امکان دسترسی به استاد برای اخذ راهنمایی در خارج از کلاس',
    ];
}

/** Return or create the native Moodle Feedback activity for a course, instructor and subject. */
function local_seavanmedia_create_evaluation(stdClass $course, string $instructor, string $subject): int {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/course/lib.php');
    require_once($CFG->dirroot . '/mod/feedback/lib.php');
    $context = context_course::instance($course->id);
    require_capability('moodle/course:manageactivities', $context);
    require_capability('mod/feedback:addinstance', $context);
    $idnumber = 'seavan-evaluation-' . hash('sha256', $instructor . chr(0) . $subject);
    $lock = \core\lock\lock_config::get_lock_factory('local_seavanmedia')->get_lock($course->id . ':' . $idnumber, 10);
    if (!$lock) {
        throw new moodle_exception('invalidrequest');
    }
    try {
        $moduleid = $DB->get_field('modules', 'id', ['name' => 'feedback'], MUST_EXIST);
        $existing = $DB->get_record('course_modules', [
            'course' => $course->id, 'module' => $moduleid, 'idnumber' => $idnumber, 'deletioninprogress' => 0,
        ], 'id', IGNORE_MULTIPLE);
        if ($existing) {
            return (int)$existing->id;
        }
        $description = html_writer::tag('p', 'استاد: ' . s($instructor)) .
            html_writer::tag('p', 'درس: ' . s($subject)) .
            html_writer::tag('p', 'دوره: ' . s(format_string($course->fullname, true, ['context' => $context]))) .
            html_writer::tag('p', 'برای هر شاخص، یک گزینه انتخاب کنید.');
        $transaction = $DB->start_delegated_transaction();
        try {
            $module = create_module((object)[
                'modulename' => 'feedback',
                'course' => $course->id,
                'section' => 0,
                'visible' => 1,
                'name' => 'ارزیابی استاد · ' . $instructor . ' · ' . $subject,
                'cmidnumber' => $idnumber,
                'introeditor' => ['text' => $description, 'format' => FORMAT_HTML, 'itemid' => 0],
                'page_after_submit_editor' => ['text' => 'ارزیابی شما ثبت شد.', 'format' => FORMAT_PLAIN, 'itemid' => 0],
                'anonymous' => FEEDBACK_ANONYMOUS_YES,
                'multiple_submit' => 0,
                'publish_stats' => 0,
                'autonumbering' => 1,
                'email_notification' => 0,
                'timeopen' => 0,
                'timeclose' => 0,
            ]);
            $item = feedback_get_item_class('multichoicerated');
            $values = $item->prepare_presentation_values_save("5/عالی\n4/خیلی خوب\n3/خوب\n2/متوسط\n1/ضعیف",
                FEEDBACK_MULTICHOICERATED_VALUE_SEP2, FEEDBACK_MULTICHOICERATED_VALUE_SEP);
            foreach (local_seavanmedia_evaluation_items() as $index => $question) {
                $item->set_data((object)[
                    'id' => 0,
                    'feedback' => $module->instance,
                    'template' => 0,
                    'typ' => 'multichoicerated',
                    'name' => $question,
                    'label' => (string)($index + 1),
                    'position' => $index + 1,
                    'required' => 1,
                    'dependitem' => 0,
                    'dependvalue' => '',
                    'presentation' => 'r' . FEEDBACK_MULTICHOICERATED_TYPE_SEP . $values,
                    'options' => '',
                    'hidenoselect' => 1,
                    'ignoreempty' => 0,
                ]);
                if (!$item->save_item()) {
                    throw new moodle_exception('invalidrequest');
                }
            }
            $transaction->allow_commit();
        } catch (Throwable $error) {
            $transaction->rollback($error);
        }
        return (int)$module->coursemodule;
    } finally {
        $lock->release();
    }
}
