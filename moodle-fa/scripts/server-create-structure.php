<?php
// ساخت دسته‌های شهر و کلاس‌های نمونه در Moodle — اجرا داخل کانتینر moodle.
define('CLI_SCRIPT', true);
require('/var/www/html/public/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/lib/completionlib.php');

$cities = [
    'tehran'     => 'تهران',
    'isfahan'    => 'اصفهان',
    'other'      => 'سایر شهرها',
];

$classes = [
    'tehran'  => [['تهران-۱', 'TEH-1'], ['تهران-۲', 'TEH-2'], ['تهران-۳', 'TEH-3']],
    'isfahan' => [['اصفهان-۱', 'ISF-1'], ['اصفهان-۲', 'ISF-2']],
    'other'   => [['سایر-۱', 'OTH-1']],
];

$summary = [];

foreach ($cities as $key => $name) {
    $existing = $DB->get_record('course_categories', ['name' => $name]);
    if ($existing) {
        $catid = $existing->id;
        $summary[] = "دستهٔ «$name» از قبل بود (id=$catid)";
    } else {
        $cat = new stdClass();
        $cat->name = $name;
        $cat->idnumber = 'CITY-' . strtoupper($key);
        $cat->description = "کلاس‌های شهر $name";
        $cat->descriptionformat = FORMAT_HTML;
        $cat->parent = 0;
        $cat->visible = 1;
        $cat = core_course_category::create($cat);
        $catid = $cat->id;
        $summary[] = "دستهٔ «$name» ساخته شد (id=$catid)";
    }

    foreach ($classes[$key] as [$fullname, $shortname]) {
        if ($DB->record_exists('course', ['shortname' => $shortname])) {
            $summary[] = "  کلاس $shortname از قبل بود";
            continue;
        }
        $course = new stdClass();
        $course->fullname = $fullname;
        $course->shortname = $shortname;
        $course->idnumber = $shortname;
        $course->category = $catid;
        $course->summary = "کلاس $fullname — مسیر ساخت SaaS آموزشی با AI";
        $course->summaryformat = FORMAT_HTML;
        $course->format = 'topics';
        $course->numsections = 8;
        $course->visible = 1;
        $course->startdate = time();
        $course->enablecompletion = 1;
        $course->showgrades = 1;
        $course->showreports = 1;
        $course->maxbytes = 52428800;
        $course->lang = 'fa';
        $course = create_course($course);

        // ثبت‌نام دستی فعال
        $instances = enrol_get_instances($course->id, false);
        foreach ($instances as $inst) {
            if ($inst->enrol === 'manual' && !$inst->status) {
                $summary[] = "  ثبت‌نام دستی $shortname فعال بود";
            }
        }
        $summary[] = "کلاس $shortname ساخته شد (id={$course->id})";
    }
}

// دستهٔ والد برای دسته‌بندی بهتر: «دوره‌های آموزشگاه»
foreach ($summary as $line) {
    echo $line, PHP_EOL;
}
