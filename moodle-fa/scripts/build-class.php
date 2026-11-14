<?php
/**
 * ساخت کامل یک کلاس دورهٔ «ساخت SaaS آموزشی با AI».
 * استفاده داخل کانتینر moodle:
 *   php build-class.php TEH-2
 * اگر کلاس از قبل محتوا داشته باشد، از ساخت دوباره جلوگیری می‌کند.
 */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');
require_once($CFG->dirroot . '/mod/customcert/lib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->libdir . '/completionlib.php');

global $DB, $CFG, $USER;
$USER = $DB->get_record('user', ['id' => 2]);

$shortname = $argv[1] ?? '';
if (!$shortname) {
    fwrite(STDERR, "نام کوتاه کلاس را بدهید. مثال: php build-class.php TEH-2\n");
    exit(1);
}
$course = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);

if ($DB->count_records('course_modules', ['course' => $course->id]) > 1) {
    echo "کلاس $shortname از قبل محتوا دارد؛ رد شد.\n";
    exit(0);
}

$context = context_course::instance($course->id);

// ---------- بخش‌ها ----------
$sections = [
    1 => ['معرفی و نقشهٔ راه', 'در این بخش می‌فهمید در پایان دوره چه چیزی ساخته‌اید و مسیر کلی چیست.'],
    2 => ['مرحله ۱ — انتخاب مسئله', 'یک مسئلهٔ واقعی پیدا کنید که مردم برای حلش پول می‌دهند.'],
    3 => ['مرحله ۲ — ساخت MVP با AI', 'اولین نسخهٔ قابل استفادهٔ محصول را با کمک ابزارهای AI بسازید.'],
    4 => ['مرحله ۳ — اولین کاربر واقعی', 'محصول را به دست ۵ نفر واقعی برسانید و بازخورد بگیرید.'],
    5 => ['مرحله ۴ — قیمت‌گذاری و درآمد', 'مدل درآمدی انتخاب کنید و اولین پرداخت را دریافت کنید.'],
    6 => ['مرحله ۵ — رشد و بازاریابی', 'کانال رشد خودتان را پیدا کنید و آن را تکرارپذیر کنید.'],
    7 => ['پروژهٔ پایانی', 'محصول خودتان را ارائه دهید و بازخورد بگیرید.'],
    8 => ['جمع‌بندی و گام بعدی', 'مسیر ادامهٔ کار پس از دوره.'],
];
course_create_sections_if_missing($course, array_keys($sections));
foreach ($sections as $num => [$name, $summary]) {
    $sec = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $num]);
    $sec->name = $name;
    $sec->summary = '<p>' . $summary . '</p>';
    $sec->summaryformat = FORMAT_HTML;
    $DB->update_record('course_sections', $sec);
}

// ---------- کمکی ----------
function mod_base($course, $section, $type, $name, $intro) {
    global $DB;
    $m = new stdClass();
    $m->modulename = $type;
    $m->course = $course->id;
    $m->section = $section;
    $m->name = $name;
    $m->intro = $intro;
    $m->introformat = FORMAT_HTML;
    $m->visible = 1;
    $m->completion = COMPLETION_TRACKING_MANUAL;
    $m->completionview = 1;
    $m->completionexpected = 0;
    $m->showdescription = 0;
    $m->instance = 0;
    $m->coursemodule = 0;
    $m->module = $DB->get_field('modules', 'id', ['name' => $type]);
    $m->add = $type;
    $m->update = 0;
    $m->return = 0;
    $m->sr = 0;
    $m->visibleoncoursepage = 1;
    $m->groupmode = 0;
    $m->groupingid = 0;
    $m->availability = null;
    $m->completiongradeitemnumber = null;
    $m->completionpassgrade = 0;
    $m->downloadcontent = 1;
    $m->lang = 'fa';
    return $m;
}

// ---------- برچسب خوش‌آمد ----------
$m = mod_base($course, 1, 'label', 'به دوره خوش آمدید', '');
$m->content = '<h3>در پایان این دوره چه چیزی دارید؟</h3><ul><li>یک محصول SaaS قابل استفاده</li><li>اولین کاربران واقعی</li><li>یک مدل درآمدی روشن</li></ul><p><strong>قاعدهٔ طلایی:</strong> هر مرحله یک خروجی عملی دارد که باید آپلود کنید.</p>';
$m->contentformat = FORMAT_HTML;
add_moduleinfo($m, $course);

// ---------- انجمن‌ها ----------
foreach ([['اعلانات کلاس', '<p>اعلانات مهم کلاس اینجا منتشر می‌شود.</p>', 'news', 1],
          ['پرسش و پاسخ', '<p>هر سؤالی دربارهٔ دوره دارید اینجا بپرسید.</p>', 'general', 0]] as [$n, $i, $t, $fs]) {
    $m = mod_base($course, 1, 'forum', $n, $i);
    $m->type = $t; $m->forcesubscribe = $fs; $m->assessed = 0; $m->scale = 0;
    $m->maxbytes = 52428800; $m->maxattachments = 3; $m->displaywordcount = 0;
    $m->lockdiscussionafter = 0; $m->duedate = 0; $m->cutoffdate = 0; $m->timemodified = time();
    add_moduleinfo($m, $course);
}

// ---------- صفحه‌های محتوا ----------
$pages = [
  2 => ['انتخاب مسئله', '<h3>هدف این مرحله</h3><p>یک مسئلهٔ واقعی پیدا کنید که مردم برای حلش پول می‌دهند.</p>
<h3>گام‌ها</h3><ol><li>۱۰ مسئله‌ای را که خودتان یا اطرافیانتان هر روز با آن درگیرید بنویسید.</li>
<li>برای هر مسئله بپرسید: «آیا کسی برای حل این، الان پول می‌دهد؟»</li>
<li>سه مسئله را انتخاب کنید که مخاطب مشخص و دسترسی‌پذیر دارند.</li>
<li>با ۵ نفر از مخاطب هدف صحبت کنید و بپرسید چطور الان حلش می‌کنند.</li>
<li>یک مسئله را انتخاب کنید و در یک جمله بنویسید: «من به [چه کسی] کمک می‌کنم [چه مشکلی] را حل کند.»</li></ol>
<h3>خروجی این مرحله</h3><p>یک جملهٔ مسئله + خلاصهٔ ۵ مصاحبه. این را در تکلیف «مرحله ۱» آپلود کنید.</p>
<h3>اشتباه رایج</h3><p>ساختن چیزی که خودتان دوست دارید، نه چیزی که بازار می‌خواهد.</p>'],
  3 => ['ساخت MVP با AI', '<h3>هدف این مرحله</h3><p>اولین نسخهٔ قابل استفادهٔ محصول را با کمک ابزارهای AI بسازید — نه کامل، فقط قابل استفاده.</p>
<h3>گام‌ها</h3><ol><li>کوچک‌ترین نسخه‌ای را تعریف کنید که یک نفر بتواند از آن استفاده کند.</li>
<li>ابزار مناسب را انتخاب کنید (سایت‌ساز، پلتفرم no-code، یا کدنویسی با کمک AI).</li>
<li>با AI پیش‌نویس اول را بسازید؛ بعد خودتان اصلاح کنید.</li>
<li>فقط یک قابلیت اصلی را کامل کنید؛ بقیه را حذف کنید.</li>
<li>لینک محصول را برای ۳ نفر بفرستید و ببینید می‌توانند بدون توضیح شما استفاده کنند.</li></ol>
<h3>خروجی این مرحله</h3><p>لینک محصول قابل استفاده + فهرست چیزهایی که حذف کردید.</p>
<h3>اشتباه رایج</h3><p>صرف وقت برای طراحی و جزئیات قبل از اینکه کسی محصول را ببیند.</p>'],
  4 => ['اولین کاربر واقعی', '<h3>هدف این مرحله</h3><p>محصول را به دست ۵ نفر واقعی برسانید و بازخورد بگیرید.</p>
<h3>گام‌ها</h3><ol><li>۵ نفر از مخاطب هدف را پیدا کنید (نه دوستانتان).</li>
<li>محصول را نشان دهید و ساکت بمانید؛ ببینید کجا گیر می‌کنند.</li>
<li>سه سؤال بپرسید: چه چیزی گیج‌کننده بود؟ چه چیزی کم بود؟ آیا برایش پول می‌دهید؟</li>
<li>بازخوردها را دسته‌بندی کنید: باگ، کمبود، سوءتفاهم.</li>
<li>مهم‌ترین مشکل را در یک هفته رفع کنید.</li></ol>
<h3>خروجی این مرحله</h3><p>خلاصهٔ ۵ جلسهٔ تست + فهرست مشکلات به‌ترتیب اهمیت.</p>'],
  5 => ['قیمت‌گذاری و درآمد', '<h3>هدف این مرحله</h3><p>مدل درآمدی انتخاب کنید و اولین پرداخت را دریافت کنید.</p>
<h3>گام‌ها</h3><ol><li>سه مدل را بررسی کنید: اشتراک ماهانه، پرداخت یک‌باره، یا رایگان + نسخهٔ پولی.</li>
<li>قیمت را بر اساس ارزشی که می‌سازید تعیین کنید، نه بر اساس هزینهٔ ساخت.</li>
<li>یک صفحهٔ پرداخت ساده بسازید (درگاه ایرانی یا لینک پرداخت).</li>
<li>به ۱۰ نفر پیشنهاد بدهید؛ حتی یک پرداخت واقعی کافی است.</li>
<li>عدد را ثبت کنید: چند نفر دیدند، چند نفر پرداختند.</li></ol>
<h3>خروجی این مرحله</h3><p>مدل قیمت‌گذاری + نتیجهٔ اولین تلاش فروش.</p>
<h3>اشتباه رایج</h3><p>رایگان گذاشتن برای همیشه به امید «بعداً پول می‌دهند».</p>'],
  6 => ['رشد و بازاریابی', '<h3>هدف این مرحله</h3><p>کانال رشد خودتان را پیدا کنید و آن را تکرارپذیر کنید.</p>
<h3>گام‌ها</h3><ol><li>سه کانال را امتحان کنید: محتوا، جامعهٔ آنلاین، یا معرفی توسط کاربران.</li>
<li>برای هر کانال یک هفته وقت بگذارید و نتیجه را ثبت کنید.</li>
<li>کانالی را انتخاب کنید که بیشترین کاربر با کمترین هزینه را داد.</li>
<li>فرایند آن کانال را به یک چک‌لیست هفتگی تبدیل کنید.</li>
<li>عدد کلیدی را هر هفته پیگیری کنید (تعداد کاربر جدید).</li></ol>
<h3>خروجی این مرحله</h3><p>گزارش سه کانال + کانال انتخاب‌شده + چک‌لیست هفتگی.</p>'],
  7 => ['پروژهٔ پایانی', '<h3>هدف</h3><p>محصول خودتان را ارائه دهید و بازخورد بگیرید.</p>
<h3>چه چیزی تحویل دهید</h3><ul><li>لینک محصول</li><li>جملهٔ مسئله</li><li>تعداد کاربران فعلی</li><li>مدل درآمدی</li><li>سه درس مهمی که گرفتید</li></ul>
<p>ارائه را در تکلیف «پروژهٔ پایانی» آپلود کنید (فایل یا لینک ویدیو).</p>'],
  8 => ['جمع‌بندی و گام بعدی', '<h3>حالا چه چیزی دارید</h3><ul><li>یک محصول واقعی</li><li>کاربر واقعی</li><li>مدل درآمدی</li><li>یک فرایند رشد</li></ul>
<h3>گام بعدی</h3><ol><li>هر هفته یک عدد را پیگیری کنید.</li><li>هر ماه یک مشکل اصلی کاربران را حل کنید.</li><li>وقتی درآمد پایدار شد، تیم را گسترش دهید.</li></ol>
<p>برای دریافت گواهی، آزمون پایانی را با نمرهٔ حداقل ۵۰٪ بگذرانید.</p>'],
];
foreach ($pages as $section => [$name, $content]) {
    $m = mod_base($course, $section, 'page', $name, '<p>مطالعهٔ این صفحه پیش‌نیاز تکلیف این مرحله است.</p>');
    $m->content = $content;
    $m->contentformat = FORMAT_HTML;
    $m->display = 5; $m->printintro = 0; $m->printlastmodified = 0;
    add_moduleinfo($m, $course);
}

// ---------- تکالیف ----------
$assigns = [
  2 => ['تکلیف مرحله ۱ — جملهٔ مسئله', '<p>جملهٔ مسئله و خلاصهٔ ۵ مصاحبه را آپلود کنید.</p>'],
  3 => ['تکلیف مرحله ۲ — لینک MVP', '<p>لینک محصول قابل استفاده + فهرست چیزهایی که حذف کردید.</p>'],
  4 => ['تکلیف مرحله ۳ — گزارش اولین کاربران', '<p>خلاصهٔ ۵ جلسهٔ تست + فهرست مشکلات به‌ترتیب اهمیت.</p>'],
  5 => ['تکلیف مرحله ۴ — مدل قیمت‌گذاری', '<p>مدل قیمت‌گذاری + نتیجهٔ اولین تلاش فروش.</p>'],
  6 => ['تکلیف مرحله ۵ — گزارش رشد', '<p>گزارش سه کانال + کانال انتخاب‌شده + چک‌لیست هفتگی.</p>'],
  7 => ['پروژهٔ پایانی', '<p>لینک محصول، جملهٔ مسئله، تعداد کاربران، مدل درآمدی و سه درس مهم.</p>'],
];
foreach ($assigns as $section => [$name, $intro]) {
    $m = mod_base($course, $section, 'assign', $name, $intro);
    $m->alwaysshowdescription = 1; $m->submissiondrafts = 0; $m->sendnotifications = 0;
    $m->sendlatenotifications = 0; $m->sendstudentnotifications = 1;
    $m->duedate = 0; $m->allowsubmissionsfromdate = 0; $m->grade = 100;
    $m->timemodified = time(); $m->completionsubmit = 1; $m->cutoffdate = 0;
    $m->gradingduedate = 0; $m->teamsubmission = 0; $m->requireallteammemberssubmit = 0;
    $m->teamsubmissiongroupingid = 0; $m->blindmarking = 0; $m->hidegrader = 0;
    $m->revealidentities = 0; $m->attemptreopenmethod = 'manual'; $m->maxattempts = -1;
    $m->markingworkflow = 0; $m->markingallocation = 0; $m->requiresubmissionstatement = 0;
    $m->submissionattachments = 0;
    $m->assignsubmission_file_enabled = 1; $m->assignsubmission_file_maxfiles = 5;
    $m->assignsubmission_file_maxsizebytes = 52428800;
    $m->assignsubmission_onlinetext_enabled = 1; $m->assignsubmission_comments_enabled = 0;
    $m->assignfeedback_comments_enabled = 1; $m->assignfeedback_file_enabled = 1;
    $m->assignfeedback_editpdf_enabled = 0; $m->assignfeedback_offline_enabled = 0;
    add_moduleinfo($m, $course);
}

// ---------- بانک سؤال ----------
$cat = $DB->get_record('question_categories', ['contextid' => $context->id, 'name' => 'بانک سؤال دوره']);
if (!$cat) {
    $cat = new stdClass();
    $cat->name = 'بانک سؤال دوره';
    $cat->contextid = $context->id;
    $cat->info = 'سؤالات مسیر ساخت SaaS با AI';
    $cat->infoformat = FORMAT_HTML;
    $cat->stamp = make_unique_id_code();
    $cat->parent = 0;
    $cat->sortorder = 999;
    $cat->id = $DB->insert_record('question_categories', $cat);
}

function qbase($cat, $name, $text) {
    $f = new stdClass();
    $f->category = $cat->id; $f->name = $name;
    $f->questiontext = $text; $f->questiontextformat = FORMAT_HTML;
    $f->generalfeedback = ''; $f->generalfeedbackformat = FORMAT_HTML;
    $f->defaultmark = 1; $f->penalty = 0; $f->status = 'ready';
    return $f;
}
$qids = [];

$f = qbase($cat, 'مرحله ۱ — معیار انتخاب مسئله', '<p>بهترین معیار برای انتخاب مسئله در مرحلهٔ اول چیست؟</p>');
$f->single = 1; $f->shuffleanswers = 1; $f->answernumbering = 'abc';
$f->correctfeedback = ['text' => '', 'format' => FORMAT_HTML];
$f->partiallycorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
$f->incorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
$f->shownumcorrect = 0;
$f->answer = [
  ['text' => 'مردم الان برای حلش پول می‌دهند', 'format' => FORMAT_HTML],
  ['text' => 'خودم به آن علاقه دارم', 'format' => FORMAT_HTML],
  ['text' => 'ساختنش آسان است', 'format' => FORMAT_HTML],
  ['text' => 'رقبای زیادی دارد', 'format' => FORMAT_HTML],
];
$f->fraction = [1, 0, 0, 0];
$f->feedback = [
  ['text' => 'درست است: پول‌دادن نشانهٔ مسئلهٔ واقعی است.', 'format' => FORMAT_HTML],
  ['text' => 'علاقه کافی نیست؛ بازار هم باید بخواهد.', 'format' => FORMAT_HTML],
  ['text' => 'آسانی ساخت معیار انتخاب مسئله نیست.', 'format' => FORMAT_HTML],
  ['text' => 'رقابت زیاد به‌تنهایی بد نیست، ولی معیار اصلی نیست.', 'format' => FORMAT_HTML],
];
$qids[] = question_bank::get_qtype('multichoice')->save_question($f, $f)->id;

$f = qbase($cat, 'مرحله ۲ — معنی MVP', '<p>مخفف MVP چیست؟ (فقط حروف اختصاری را بنویسید)</p>');
$f->usecase = 0;
$f->answer = ['MVP', 'Minimum Viable Product', '*'];
$f->fraction = [1, 1, 0];
$f->feedback = [
  ['text' => 'درست است.', 'format' => FORMAT_HTML],
  ['text' => 'درست است.', 'format' => FORMAT_HTML],
  ['text' => 'پاسخ درست: MVP', 'format' => FORMAT_HTML],
];
$qids[] = question_bank::get_qtype('shortanswer')->save_question($f, $f)->id;

$f = qbase($cat, 'مرحله ۳ — تطبیق ابزار و کاربرد', '<p>هر ابزار را به کاربرد اصلی‌اش وصل کنید.</p>');
$f->shuffleanswers = 1;
$f->correctfeedback = ['text' => '', 'format' => FORMAT_HTML];
$f->partiallycorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
$f->incorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
$f->shownumcorrect = 0;
$f->subquestions = [
  ['text' => 'ابزار ساخت سایت بدون کد', 'format' => FORMAT_HTML],
  ['text' => 'ابزار تحلیل رفتار کاربر', 'format' => FORMAT_HTML],
  ['text' => 'درگاه پرداخت', 'format' => FORMAT_HTML],
];
$f->subanswers = ['ساخت سریع MVP', 'فهمیدن کجا کاربر گیر می‌کند', 'دریافت اولین درآمد'];
$qids[] = question_bank::get_qtype('match')->save_question($f, $f)->id;

$f = qbase($cat, 'مرحله ۴ — جملهٔ مسئله', '<p>جملهٔ مسئلهٔ محصول خودتان را بنویسید: «من به [چه کسی] کمک می‌کنم [چه مشکلی] را حل کند.» و در دو خط توضیح دهید چرا این مسئله واقعی است.</p>');
$f->responseformat = 'editor'; $f->responserequired = 1; $f->responsefieldlines = 10;
$f->attachments = 0; $f->attachmentsrequired = 0; $f->maxbytes = 0; $f->filetypeslist = '';
$f->minwordlimit = 0; $f->maxwordlimit = 0;
$f->graderinfo = ['text' => '<p>معیار: جملهٔ مشخص با مخاطب روشن (۵ نمره) + دلیل مبتنی بر شواهد (۵ نمره).</p>', 'format' => FORMAT_HTML];
$f->responsetemplate = ['text' => '', 'format' => FORMAT_HTML];
$qids[] = question_bank::get_qtype('essay')->save_question($f, $f)->id;

// ---------- کوییزها ----------
function quiz_base($course, $section, $name, $intro, $opts) {
    $m = mod_base($course, $section, 'quiz', $name, $intro);
    $defaults = [
      'timeopen' => 0, 'timeclose' => 0, 'timelimit' => 0,
      'overduehandling' => 'autoabandon', 'graceperiod' => 0,
      'preferredbehaviour' => 'deferredfeedback',
      'attempts' => 0, 'attemptonlast' => 0, 'grademethod' => 1,
      'decimalpoints' => 2, 'questiondecimalpoints' => -1,
      'questionsperpage' => 1, 'navmethod' => 'free',
      'shuffleanswers' => 1, 'sumgrades' => 0, 'grade' => 10,
      'quizpassword' => '', 'subnet' => '', 'browsersecurity' => '-',
      'delay1' => 0, 'delay2' => 0, 'showuserpicture' => 0,
      'showblocks' => 0, 'reviewattempt' => 0x11111, 'reviewcorrectness' => 0x11111,
      'reviewmarks' => 0x11111, 'reviewspecificfeedback' => 0x11111,
      'reviewgeneralfeedback' => 0x11111, 'reviewrightanswer' => 0x11111,
      'reviewoverallfeedback' => 0x11111, 'boundaryrepeats' => [], 'showmaxmark' => 0,
    ];
    foreach (array_merge($defaults, $opts) as $k => $v) $m->$k = $v;
    return add_moduleinfo($m, $course);
}

function add_questions($cmid, $qids, $marks) {
    global $DB;
    $quizid = $DB->get_field('course_modules', 'instance', ['id' => $cmid]);
    $ctxid = context_module::instance($cmid)->id;
    $i = 0;
    foreach ($qids as $qid) {
        $DB->insert_record('quiz_slots', (object)[
            'quizid' => $quizid, 'slot' => $i + 1, 'page' => $i + 1,
            'requireprevious' => 0, 'maxmark' => $marks[$i] ?? 1,
        ]);
        $DB->insert_record('question_references', (object)[
            'usingcontextid' => $ctxid, 'component' => 'mod_quiz', 'questionarea' => 'slot',
            'itemid' => $i + 1,
            'questionbankentryid' => $DB->get_field('question_versions', 'questionbankentryid', ['questionid' => $qid]),
            'version' => null,
        ]);
        $i++;
    }
    $DB->set_field('quiz', 'sumgrades', array_sum($marks), ['id' => $quizid]);
}

$practice = quiz_base($course, 2, 'آزمون تمرینی — مرحله ۱',
  '<p>این آزمون تمرینی است: تلاش نامحدود، بدون اثر در کارنامه، بازخورد فوری.</p>',
  ['attempts' => 0, 'grade' => 0, 'sumgrades' => 0]);
add_questions($practice->coursemodule, $qids, [1, 1, 1, 1]);

$graded = quiz_base($course, 7, 'آزمون پایانی',
  '<p>آزمون پایانی دوره. یک تلاش، زمان محدود. نمرهٔ قبولی ۵۰٪.</p>',
  ['attempts' => 1, 'timelimit' => 1800, 'grade' => 10, 'sumgrades' => 10, 'quizpassword' => 'sorkh1404']);
add_questions($graded->coursemodule, $qids, [2.5, 2.5, 2.5, 2.5]);

// ---------- گواهی ----------
$m = mod_base($course, 8, 'customcert', 'گواهی پایان دوره',
  '<p>پس از تکمیل همهٔ مراحل و قبولی در آزمون پایانی، گواهی شما صادر می‌شود.</p>');
$m->requiredtime = 0; $m->verifyany = 0; $m->emailstudents = 0; $m->emailteachers = 0;
$m->emailothers = ''; $m->deliveryoption = 0;
$m->protection_print = 0; $m->protection_modify = 0; $m->protection_copy = 0;
$certcm = add_moduleinfo($m, $course);

// قالب گواهی
$template = new stdClass();
$template->name = 'گواهی پایان دوره — ' . $course->shortname;
$template->contextid = $context->id;
$template->timecreated = time();
$template->timemodified = time();
$template->id = $DB->insert_record('customcert_templates', $template);

$page = new stdClass();
$page->templateid = $template->id;
$page->width = 210; $page->height = 297;
$page->leftmargin = 15; $page->rightmargin = 15;
$page->sequence = 1;
$page->timecreated = time(); $page->timemodified = time();
$page->id = $DB->insert_record('customcert_pages', $page);

function cert_el($pageid, $name, $element, $data, $posy, $seq) {
    global $DB;
    $e = new stdClass();
    $e->pageid = $pageid; $e->name = $name; $e->element = $element;
    $e->data = json_encode($data, JSON_UNESCAPED_UNICODE);
    $e->posx = 0; $e->posy = $posy; $e->refpoint = 0; $e->alignment = 'C';
    $e->sequence = $seq; $e->timecreated = time(); $e->timemodified = time();
    $DB->insert_record('customcert_elements', $e);
}
cert_el($page->id, 'عنوان', 'text', ['text' => '<div style="text-align:center;font-size:34pt;color:#F52F53;font-weight:bold;">گواهی پایان دوره</div>', 'font' => 'freeserif', 'fontsize' => 34, 'colour' => '#F52F53', 'width' => 0], 40, 1);
cert_el($page->id, 'نام آموزشگاه', 'text', ['text' => '<div style="text-align:center;font-size:18pt;color:#636363;">آموزشگاه SaaS سرخ</div>', 'font' => 'freeserif', 'fontsize' => 18, 'colour' => '#636363', 'width' => 0], 60, 2);
cert_el($page->id, 'متن', 'text', ['text' => '<div style="text-align:center;font-size:14pt;color:#636363;">این گواهی به</div>', 'font' => 'freeserif', 'fontsize' => 14, 'colour' => '#636363', 'width' => 0], 85, 3);
cert_el($page->id, 'نام دانش‌آموز', 'userfield', ['userfield' => 'fullname', 'font' => 'freeserif', 'fontsize' => 24, 'colour' => '#06B91F', 'width' => 0], 100, 4);
cert_el($page->id, 'متن ۲', 'text', ['text' => '<div style="text-align:center;font-size:14pt;color:#636363;">به دلیل تکمیل موفقیت‌آمیز دورهٔ</div>', 'font' => 'freeserif', 'fontsize' => 14, 'colour' => '#636363', 'width' => 0], 120, 5);
cert_el($page->id, 'نام دوره', 'coursefield', ['coursefield' => 'fullname', 'font' => 'freeserif', 'fontsize' => 20, 'colour' => '#F52F53', 'width' => 0], 135, 6);
cert_el($page->id, 'تاریخ', 'date', ['dateitem' => 'issuedate', 'dateformat' => 'j F Y', 'font' => 'freeserif', 'fontsize' => 12, 'colour' => '#636363', 'width' => 0], 165, 7);
cert_el($page->id, 'کد گواهی', 'code', ['font' => 'freeserif', 'fontsize' => 10, 'colour' => '#636363', 'width' => 0], 180, 8);

$DB->set_field('customcert', 'templateid', $template->id, ['id' => $certcm->instance]);

// شرط گواهی: قبولی در آزمون پایانی
$avail = json_encode(['op' => '&', 'c' => [['type' => 'grade', 'id' => $graded->coursemodule, 'min' => 50]], 'showc' => [true]]);
$DB->set_field('course_modules', 'availability', $avail, ['id' => $certcm->coursemodule]);
$DB->set_field('course_modules', 'completionpassgrade', 1, ['id' => $certcm->coursemodule]);
$DB->set_field('course_modules', 'completion', COMPLETION_TRACKING_AUTOMATIC, ['id' => $certcm->coursemodule]);

echo "کلاس $shortname کامل ساخته شد.\n";
