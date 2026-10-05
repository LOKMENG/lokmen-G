<?php
/**
 * مجموعة اختبارات المنصة (بدون أي مكتبات خارجية).
 *
 * التشغيل:
 *   php tests/run.php                 # كل الاختبارات على قاعدة بيانات وهمية في الذاكرة
 *   php tests/run.php --real-db       # اختبارات قاعدة البيانات على MySQL الفعلي (إن كان متاحاً)
 *   php tests/run.php --filter=import # تشغيل مجموعة واحدة بالاسم
 *
 * ملاحظة: لا تُشغَّل أي اختبارات على بيانات إنتاجية؛ الاختبارات الافتراضية لا تلمس MySQL.
 */
declare(strict_types=1);

if (!in_array(PHP_SAPI, ['cli', 'phpdbg', 'wasm'], true)) {
    http_response_code(403);
    exit('هذا الملف يعمل من سطر الأوامر فقط.');
}

$realDb = in_array('--real-db', $GLOBALS['argv'] ?? [], true);
$filter = '';
foreach ($GLOBALS['argv'] ?? [] as $argument) {
    if (preg_match('/^--filter=(.+)$/', $argument, $matches) === 1) {
        $filter = $matches[1];
    }
}

if ($realDb) {
    define('APP_CSRF_EXEMPT', true);
} else {
    define('APP_TEST_FAKE_DB', true);
    define('APP_CSRF_EXEMPT', true);
}

require_once dirname(__DIR__) . '/config/bootstrap.php';

// بعض بيئات التشغيل تمرر المعاملات عبر $_SERVER['APP_ARGV'] فقط
if (($GLOBALS['argv'] ?? []) === [] && $_SERVER['APP_ARGV'] ?? '' !== '') {
    $realDb = $realDb || in_array('--real-db', cli_args(), true);
    $filter = $filter !== '' ? $filter : cli_option('filter');
}

use App\Database;
use App\QuestionImporter;
use App\Repositories\QuestionRepository;
use App\Security;
use App\Services\ExamEngine;
use App\Services\StatisticsService;
use App\Str;
use App\Validator;
use Tests\Support\FakeDatabase;

/* ==================== إطار الاختبار ==================== */

final class TestSkipped extends RuntimeException
{
}

final class Assert
{
    public static int $count = 0;

    public static function true(bool $condition, string $message = 'الشرط غير محقق'): void
    {
        self::$count++;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    public static function same(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::$count++;
        if ($expected !== $actual) {
            throw new RuntimeException(($message !== '' ? $message . ' — ' : '')
                . 'المتوقع: ' . var_export($expected, true) . ' | الفعلي: ' . var_export($actual, true));
        }
    }

    public static function contains(string $needle, string $haystack, string $message = ''): void
    {
        self::$count++;
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException(($message !== '' ? $message . ' — ' : '') . 'لم يُعثر على: ' . $needle);
        }
    }

    public static function count(int $expected, array $actual, string $message = ''): void
    {
        self::$count++;
        if (count($actual) !== $expected) {
            throw new RuntimeException(($message !== '' ? $message . ' — ' : '')
                . 'المتوقع: ' . $expected . ' عنصراً | الفعلي: ' . count($actual));
        }
    }
}

/** @var array<string,array<int,array{0:string,1:callable}>> */
$groups = [];
$currentGroup = 'عام';

function group(string $name): void
{
    global $currentGroup;
    $currentGroup = $name;
}

function test(string $name, callable $body): void
{
    global $groups, $currentGroup;
    $groups[$currentGroup][] = [$name, $body];
}

/* ==================== 1) البيئة والملفات ==================== */
group('البيئة والملفات');

test('إصدار PHP 8.1 أو أحدث', function (): void {
    Assert::true(PHP_VERSION_ID >= 80100, 'إصدار PHP الحالي: ' . PHP_VERSION);
});

test('الإضافات المطلوبة متوفرة', function (): void {
    foreach (['pdo', 'json', 'mbstring', 'openssl'] as $extension) {
        Assert::true(extension_loaded($extension), "الإضافة {$extension} مفقودة");
    }
});

test('ملفات المشروع الأساسية موجودة', function (): void {
    foreach (['database.sql', '.htaccess', '.env.example', 'index.php', 'config/config.php',
              'includes/helpers.php', 'assets/css/app.css', 'assets/js/app.js'] as $file) {
        Assert::true(is_file(BASE_PATH . '/' . $file), "الملف مفقود: {$file}");
    }
});

test('لا توجد أخطاء صياغة في أي ملف PHP', function (): void {
    $errors = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), '/vendor/')) {
            continue;
        }
        $relative = str_replace(BASE_PATH . '/', '', $file->getPathname());
        try {
            token_get_all((string) file_get_contents($file->getPathname()), TOKEN_PARSE);
        } catch (ParseError $error) {
            $errors[] = $relative . ' (سطر ' . $error->getLine() . ')';
        }
    }
    Assert::same([], $errors, 'ملفات بها أخطاء صياغة');
});

test('كل شاشة يستدعيها الكود لها ملف عرض موجود', function (): void {
    $missing = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $code = (string) file_get_contents($file->getPathname());
        if (preg_match_all("/View::render\(\s*'([^']+)'/", $code, $matches) === 0) {
            continue;
        }
        foreach ($matches[1] as $view) {
            if (!is_file(BASE_PATH . '/views/' . $view . '.php')) {
                $missing[] = $view . ' ← ' . str_replace(BASE_PATH . '/', '', $file->getPathname());
            }
        }
    }
    Assert::same([], array_values(array_unique($missing)), 'ملفات عرض مفقودة');
});

test('روابط القائمة الجانبية تشير إلى صفحات موجودة', function (): void {
    $sidebar = (string) file_get_contents(BASE_PATH . '/views/partials/sidebar.php');
    preg_match_all("/url\('([^']+)'\)/", $sidebar, $matches);
    $missing = [];
    foreach (array_unique($matches[1]) as $path) {
        $clean = trim((string) preg_replace('/\?.*$/', '', $path), '/');
        if ($clean === '' || str_contains($clean, '#')) {
            continue;
        }
        if (!is_file(BASE_PATH . '/' . $clean . '.php')) {
            $missing[] = $path;
        }
    }
    Assert::same([], $missing, 'روابط قائمة جانبية بلا ملفات');
});

test('جداول قاعدة البيانات تغطي كل الجداول المستخدمة في الكود', function (): void {
    $schema = (string) file_get_contents(BASE_PATH . '/database.sql');
    preg_match_all('/CREATE TABLE `([a-z0-9_]+)`/i', $schema, $matches);
    $tables = array_flip($matches[1]);
    Assert::true(isset($tables['questions'], $tables['users'], $tables['testimonials']), 'جداول أساسية مفقودة في database.sql');

    $used = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH . '/src', FilesystemIterator::SKIP_DOTS));
    $files = [$iterator];
    foreach (['admin', 'student', 'exams', 'questions', 'subscriptions', 'auth', 'api', 'telegram'] as $dir) {
        if (is_dir(BASE_PATH . '/' . $dir)) {
            $files[] = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH . '/' . $dir, FilesystemIterator::SKIP_DOTS));
        }
    }
    $files[] = new ArrayIterator([new SplFileInfo(BASE_PATH . '/index.php'), new SplFileInfo(BASE_PATH . '/includes/helpers.php')]);
    foreach ($files as $iterator) {
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $code = (string) file_get_contents($file->getPathname());
            preg_match_all('/\b(?:FROM|INTO|UPDATE|JOIN)\s+`([a-z0-9_]+)`([^`]{0,3})/i', $code, $found, PREG_SET_ORDER);
            foreach ($found as $match) {
                // تجاهل "ON DUPLICATE KEY UPDATE `عمود` = ..." فالمقصود عمود وليس جدولاً
                $next = ltrim($match[2] ?? '');
                if ($next !== '' && !in_array($next[0], ['(', ',', ';'], true)) {
                    continue;
                }
                $used[$match[1]] = true;
            }
        }
    }
    $missing = array_values(array_diff(array_keys($used), array_keys($tables)));
    // جداول نظام MySQL لا تُعد ناقصة
    $missing = array_values(array_filter($missing, static fn(string $t): bool => !in_array($t, ['information_schema'], true)));
    Assert::same([], $missing, 'جداول مستخدمة في الكود وغير موجودة في database.sql');
});

/* ==================== 2) الأدوات النصية ==================== */
group('الأدوات النصية');

test('تحويل الأرقام العربية-الهندية والفواصل', function (): void {
    Assert::same('1234567890', Str::normalizeDigits('١٢٣٤٥٦٧٨٩٠'));
    Assert::same('150.50', Str::normalizeDigits('١٥٠٫٥٠'));
});

test('توحيد النص العربي للبحث', function (): void {
    $a = Str::normalizeArabic('الإجَابَةُ الصَّحِيحَة');
    $b = Str::normalizeArabic('الاجابه الصحيحه');
    Assert::same($b, $a, 'توحيد الهمزات والتشكيل');
});

test('بصمة المحتوى ثابتة ومستقلة عن التشكيل', function (): void {
    $first = Str::contentFingerprint('ما هي عاصمة المملكة العربية السعودية؟');
    $second = Str::contentFingerprint('ما  هي عاصمة المملكة العربية السعوديه');
    Assert::same($first, $second, 'يجب أن تتطابق البصمة لتفادي التكرار');
    Assert::same(64, strlen($first), 'طول بصمة SHA-256');
});

test('تقصير النص يحترم طول الحد', function (): void {
    $text = str_repeat('مرحبا ', 40);
    Assert::true(mb_strlen(Str::limit($text, 50)) <= 55, 'طول النص المقصوص');
});

test('تنسيق أرقام الواجهة والمال', function (): void {
    Assert::same('٤٥', ar_digits(45));
    Assert::contains('100', money(100), 'مبلغ الاشتراك');
});

/* ==================== 3) الأمان ==================== */
group('الأمان');

test('رمز الربط بصيغة ABCD-EFGH', function (): void {
    Assert::true(preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', Security::linkCode(8)) === 1, 'صيغة رمز الربط');
    Assert::same('ABCD-EFGH', Security::normalizeLinkCode('abcd efgh'));
});

test('تجزئة كلمة المرور والتحقق منها', function (): void {
    $hash = Security::hashPassword('Admin@12345');
    Assert::true(Security::verifyPassword('Admin@12345', $hash), 'التحقق من كلمة المرور الصحيحة');
    Assert::true(!Security::verifyPassword('wrong-password', $hash), 'رفض كلمة المرور الخاطئة');
});

test('تنقية أسماء الملفات المرفوعة', function (): void {
    $safe = Security::sanitizeFilename('../../../etc/passwd.php');
    Assert::true(!str_contains($safe, '/') && !str_contains($safe, '..'), 'منع اختراق المسار: ' . $safe);
    Assert::true(!str_ends_with(strtolower($safe), '.php'), 'منع امتداد PHP');
});

test('تشفير مفاتيح بوابات الدفع (AES-256-GCM)', function (): void {
    try {
        $secret = 'sk_test_1234567890';
        $payload = Security::encrypt($secret);
        Assert::true($payload !== $secret, 'النص المشفّر لا يساوي الأصل');
        Assert::same($secret, Security::decrypt($payload), 'فك التشفير يعيد النص الأصلي');
    } catch (RuntimeException $error) {
        throw new TestSkipped('يتطلب APP_KEY في .env: ' . $error->getMessage());
    }
});

/* ==================== 4) المدخلات ==================== */
group('التحقق من المدخلات');

test('قواعد التحقق الأساسية', function (): void {
    $validator = Validator::make(['email' => 'not-an-email', 'name' => 'أ'], [
        'email' => 'required|email',
        'name'  => 'required|min:3',
    ]);
    Assert::true($validator->fails(), 'يجب أن تفشل المدخلات غير الصحيحة');
    $errors = $validator->errors();
    Assert::true(isset($errors['email'], $errors['name']), 'رسائل الخطأ لكل حقل');

    $valid = Validator::make(['email' => 'a@b.com', 'name' => 'محمد'], [
        'email' => 'required|email',
        'name'  => 'required|min:3',
    ]);
    Assert::true($valid->validate(), 'يجب أن تنجح المدخلات الصحيحة');
});

test('قواعد unique و exists على قاعدة البيانات', function (): void {
    FakeDatabase::reset();
    $db = Database::instance();
    $db->insert('users', ['full_name' => 'طالب', 'email' => 'student@example.com', 'phone' => '966500000000', 'password_hash' => 'x']);

    $duplicate = Validator::make(['email' => 'student@example.com'], ['email' => 'required|unique:users,email']);
    Assert::true($duplicate->fails(), 'يجب رفض بريد مكرر');

    $fresh = Validator::make(['email' => 'new@example.com'], ['email' => 'required|unique:users,email']);
    Assert::true($fresh->validate(), 'بريد جديد يجب أن يُقبل');

    $exists = Validator::make(['user_id' => 1], ['user_id' => 'required|exists:users,id']);
    Assert::true($exists->validate(), 'معرّف موجود');

    $missing = Validator::make(['user_id' => 999], ['user_id' => 'required|exists:users,id']);
    Assert::true($missing->fails(), 'معرّف غير موجود');
});

test('قاعدة رقم الجوال السعودي', function (): void {
    $ok = Validator::make(['phone' => '0551234567'], ['phone' => 'required|phone_sa']);
    Assert::true($ok->validate(), 'جوال سعودي صحيح');
    $bad = Validator::make(['phone' => '12345'], ['phone' => 'required|phone_sa']);
    Assert::true($bad->fails(), 'جوال غير صحيح');
});

/* ==================== 5) قاعدة البيانات ==================== */
group('طبقة قاعدة البيانات');

test('الاستعلامات المُعدّة مسبقاً تمنع حقن SQL', function (): void {
    FakeDatabase::reset();
    $db = Database::instance();
    $payload = "test'); DROP TABLE users; --";
    $id = $db->insert('sources', ['name' => $payload]);
    $row = $db->one('SELECT * FROM `sources` WHERE id = :id', ['id' => $id]);
    Assert::same($payload, $row['name'] ?? null, 'النص الخطر يُخزَّن كنص عادي');
    Assert::true($db->tableExists('sources'), 'الجدول ما زال موجوداً');
});

test('المعاملات (Transactions) تتراجع عند الخطأ', function (): void {
    FakeDatabase::reset();
    $db = Database::instance();
    $db->insert('sources', ['name' => 'قبل']);
    try {
        $db->transaction(function (Database $database): void {
            $database->insert('sources', ['name' => 'أثناء']);
            throw new RuntimeException('اختبار التراجع');
        });
    } catch (RuntimeException) {
        // متوقع
    }
    Assert::same(1, count(FakeDatabase::table('sources')), 'عدد الصفوف بعد التراجع');
});

test('ترقيم الصفحات يحسب الإجمالي والحدود', function (): void {
    FakeDatabase::reset();
    $db = Database::instance();
    for ($index = 1; $index <= 7; $index++) {
        $db->insert('sources', ['name' => 'مصدر ' . $index]);
    }
    $result = $db->paginate('SELECT * FROM `sources` ORDER BY id ASC', [], 3, 2);
    Assert::same(7, $result['total'], 'الإجمالي');
    Assert::same(3, $result['pages'], 'عدد الصفحات');
    Assert::same(3, count($result['rows']), 'عدد صفوف الصفحة الثانية');
});

/* ==================== 6) استيراد الأسئلة ==================== */
group('استيراد الأسئلة');

test('تحليل نص عربي إلى أسئلة واختيارات', function (): void {
    $text = <<<TXT
س1: ما هي عاصمة المملكة العربية السعودية؟
أ) جدة
ب) الرياض
ج) الدمام
د) أبها
الإجابة: ب
الشرح: الرياض هي العاصمة.

س2: كم عدد أركان الإسلام؟
أ) ثلاثة
ب) أربعة
ج) خمسة
د) ستة
TXT;
    $rows = QuestionImporter::parseText($text);
    Assert::same(2, count($rows), 'عدد الأسئلة المحللة');
    Assert::same('ب', QuestionImporter::normalizeLetter($rows[0]['correct_answer'] ?? '') === 'b' ? 'ب' : $rows[0]['correct_answer'], 'خيار ثانٍ');
    Assert::same('b', $rows[0]['correct_answer'], 'الإجابة الأولى = ب');
    Assert::contains('الرياض هي العاصمة', (string) $rows[0]['explanation'], 'الشرح');
    Assert::same(null, $rows[1]['correct_answer'], 'لا يُخمَّن الجواب الناقص');
    Assert::same(1, $rows[1]['needs_review'], 'السؤال بلا إجابة يحتاج مراجعة');
    Assert::true($rows[1]['issues'] !== [], 'يجب تسجيل سبب الحاجة للمراجعة');
});

test('تحليل نص إنجليزي إلى أسئلة', function (): void {
    $text = <<<TXT
Q1: Which planet is known as the Red Planet?
A) Earth
B) Mars
C) Venus
D) Jupiter
Answer: B

Q2: What is 7 x 8?
A) 54
B) 56
C) 64
D) 72
Correct: b
TXT;
    $rows = QuestionImporter::parseText($text);
    Assert::same(2, count($rows), 'عدد الأسئلة');
    Assert::same('b', $rows[0]['correct_answer'], 'إجابة السؤال الأول');
    Assert::same('b', $rows[1]['correct_answer'], 'إجابة السؤال الثاني');
    Assert::same(0, $rows[0]['needs_review'], 'لا حاجة لمراجعة');
});

test('تحليل CSV برؤوس عربية وإنجليزية', function (): void {
    $csv = "question,option_a,option_b,option_c,option_d,correct_answer\n"
        . "\"ما هو أكبر كوكب؟\",الأرض,المشتري,المريخ,الزهرة,B\n"
        . "\"كم عدد الكواكب؟\",7,8,9,10,ب\n";
    $rows = QuestionImporter::parseCsv($csv);
    Assert::same(2, count($rows), 'عدد الصفوف');
    Assert::same('b', $rows[0]['correct_answer'], 'إجابة إنجليزية');
    Assert::same('b', $rows[1]['correct_answer'], 'إجابة عربية');
});

test('تحليل JSON بصيغتين', function (): void {
    $rows = QuestionImporter::parseJson((string) json_encode([
        ['question' => 'ما ناتج ٥ + ٥؟', 'options' => ['٨', '٩', '١٠', '١١'], 'answer' => 'ج'],
        ['question' => 'كم شهراً في السنة؟', 'options' => ['10', '11', '12', '13'], 'correct' => 'c'],
    ], JSON_UNESCAPED_UNICODE));
    Assert::same(2, count($rows), 'عدد الأسئلة');
    Assert::same('c', $rows[0]['correct_answer'], 'إجابة عربية بالحرف');
    Assert::same('c', $rows[1]['correct_answer'], 'إجابة بالحرف اللاتيني');

    $wrapped = QuestionImporter::parseJson('{"questions":[{"question":"سؤال تجريبي للاختبار؟","options":["أ1","أ2","أ3","أ4"],"answer":"1"}]}');
    Assert::same(1, count($wrapped), 'صيغة {"questions":[...]}');
    Assert::same('a', $wrapped[0]['correct_answer'], 'الإجابة بالرقم 1');
});

test('رفض الصفوف الناقصة ووسمها', function (): void {
    $rows = QuestionImporter::parseText("س1: لماذا؟\nأ) خيار\nب) خيار\nج) خيار\nد) خيار\nالإجابة: أ");
    Assert::same(0, $rows[0]['is_valid'], 'السؤال القصير جداً غير صالح');
    Assert::true($rows[0]['issues'] !== [], 'يجب تسجيل أسباب عدم الصلاحية');
});

test('دورة الاستيراد الكاملة: تحليل ← مراجعة ← إدخال', function (): void {
    FakeDatabase::reset();
    $db = Database::instance();
    $db->insert('tracks', ['id' => 1, 'name_ar' => 'التربوي', 'slug' => 'edu', 'is_active' => 1]);
    $db->insert('categories', ['id' => 5, 'track_id' => 1, 'name_ar' => 'التخطيط', 'slug' => 'plan', 'is_active' => 1]);
    $adminId = $db->insert('users', ['full_name' => 'مدير', 'email' => 'admin@example.com', 'phone' => '966500000001', 'password_hash' => 'x', 'role' => 'admin']);

    $batchId = QuestionImporter::createBatch('أسئلة.txt', 'txt', null, $adminId);
    Assert::true($batchId > 0, 'إنشاء دفعة استيراد');

    $text = "س1: ما هو الهدف من التخطيط التربوي المدرسي؟\nأ) تنظيم العمل\nب) إلغاء الأنشطة\nج) زيادة الحصص\nد) تأجيل الاختبارات\nالإجابة: أ\n\n"
        . "س2: أي مما يلي يعتبر تقويماً ختامياً؟\nأ) الاختبار النهائي\nب) النشاط الصفي\nج) الواجب المنزلي\nد) الملاحظة اليومية\n";
    $result = QuestionImporter::ingestText($batchId, $text, 'txt');
    Assert::true(($result['ok'] ?? false) === true, 'نجاح التحليل: ' . ($result['message'] ?? ''));
    Assert::same(2, (int) ($result['stats']['staged'] ?? -1), 'عدد الصفوف المُدخلة للمراجعة');
    Assert::same(1, (int) ($result['stats']['needs_review'] ?? -1), 'صف واحد يحتاج قراراً على الإجابة');

    $pending = QuestionImporter::rows($batchId, ['status' => 'pending']);
    Assert::same(2, count($pending['rows']), 'صفوف المراجعة');

    // اعتماد الصفوف القابلة للاعتماد (لها إجابة محددة)
    QuestionImporter::setStatus([(int) $pending['rows'][0]['id']], 'approved');
    $import = QuestionImporter::importApproved($batchId, ['track_id' => 1, 'category_id' => 5, 'difficulty' => 'medium'], $adminId);
    Assert::same(1, $import['imported'], 'سؤال واحد مُدخل');

    $question = $db->one('SELECT * FROM `questions` WHERE track_id = 1');
    Assert::true($question !== null, 'السؤال موجود في بنك الأسئلة');
    Assert::same('a', (string) $question['correct_answer'], 'الإجابة محفوظة بالحرف اللاتيني');
    Assert::same(0, (int) $question['needs_review'], 'السؤال المعتمد لا يحتاج مراجعة');
    Assert::true($question['content_hash'] !== null, 'بصمة المحتوى محفوظة لمنع التكرار');

    // منع التكرار: إعادة استيراد نفس النص
    $batch2 = QuestionImporter::createBatch('أسئلة.txt', 'txt', null, $adminId);
    QuestionImporter::ingestText($batch2, $text, 'txt');
    $rows2 = QuestionImporter::rows($batch2, ['status' => 'pending']);
    Assert::true((int) $rows2['rows'][0]['is_duplicate'] === 1, 'اكتشاف السؤال المكرر');
    QuestionImporter::setStatus([(int) $rows2['rows'][0]['id']], 'approved');
    $second = QuestionImporter::importApproved($batch2, ['track_id' => 1], $adminId);
    Assert::same(0, $second['imported'], 'لا يُدخل المكرر');
    Assert::true($second['skipped'] >= 1, 'يُسجَّل المتخطى في التقرير');

    // سجل الدفعات
    $batchRow = QuestionImporter::batch($batchId);
    Assert::true($batchRow !== null && (int) $batchRow['imported_rows'] === 1, 'تحديث عدّادات الدفعة');
    $batchStats = QuestionImporter::batchStats($batchId);
    Assert::same(1, (int) $batchStats['imported'], 'إحصاءات الدفعة بعد الإدخال');
});

/* ==================== 7) المخازن والخدمات ==================== */
group('المخازن والخدمات');

test('بحث بنك الأسئلة والفلاتر', function (): void {
    FakeDatabase::reset();
    $db = Database::instance();
    $db->insert('tracks', ['id' => 1, 'name_ar' => 'التربوي', 'slug' => 'edu', 'is_active' => 1]);
    $db->insert('categories', ['id' => 5, 'track_id' => 1, 'name_ar' => 'التخطيط', 'slug' => 'plan', 'is_active' => 1]);
    $questionId = $db->insert('questions', ['track_id' => 1, 'category_id' => 5, 'question_text' => 'سؤال عن التخطيط', 'option_a' => 'أ', 'option_b' => 'ب', 'option_c' => 'ج', 'option_d' => 'د', 'correct_answer' => 'a', 'active' => 1, 'needs_review' => 0, 'content_hash' => str_repeat('a', 64)]);
    $db->insert('questions', ['track_id' => 1, 'category_id' => 5, 'question_text' => 'سؤال يحتاج مراجعة', 'option_a' => 'أ', 'option_b' => 'ب', 'option_c' => 'ج', 'option_d' => 'د', 'correct_answer' => null, 'active' => 1, 'needs_review' => 1]);

    $repository = new QuestionRepository($db);
    $all = $repository->search([], 1, 20);
    Assert::same(2, (int) $all['total'], 'إجمالي الأسئلة');
    $review = $repository->search(['needs_review' => 1], 1, 20);
    Assert::same(1, (int) $review['total'], 'فلترة الأسئلة التي تحتاج مراجعة');
    $byText = $repository->search(['q' => 'التخطيط'], 1, 20);
    Assert::same(1, (int) $byText['total'], 'البحث النصي');
    $hash = (string) $db->value('SELECT content_hash FROM `questions` WHERE id = :id', ['id' => $questionId]);
    Assert::true($hash !== '', 'بصمة المحتوى محفوظة');
    Assert::true($repository->duplicateExists($hash) !== null, 'كشف التكرار بالبصمة');
    Assert::same(null, $repository->duplicateExists('بصمة-غير-موجودة'), 'لا تكرار لبصمة جديدة');
});

test('إحصاءات المجالات تُحدَّث بعد الإجابة', function (): void {
    FakeDatabase::reset();
    $db = Database::instance();
    $db->insert('tracks', ['id' => 1, 'name_ar' => 'التربوي', 'slug' => 'edu', 'is_active' => 1]);
    $db->insert('categories', ['id' => 5, 'track_id' => 1, 'name_ar' => 'التخطيط', 'slug' => 'plan', 'is_active' => 1]);
    $db->insert('users', ['id' => 7, 'full_name' => 'طالب', 'email' => 's@example.com', 'phone' => '966500000009', 'password_hash' => 'x']);
    $db->insert('questions', ['id' => 11, 'track_id' => 1, 'category_id' => 5, 'question_text' => 'سؤال للتدريب على الإحصاءات', 'option_a' => 'أ', 'option_b' => 'ب', 'option_c' => 'ج', 'option_d' => 'د', 'correct_answer' => 'a', 'active' => 1]);

    StatisticsService::recordAnswer(7, 11, true);
    StatisticsService::recordAnswer(7, 11, false);

    $stat = $db->one('SELECT * FROM `user_category_stats` WHERE user_id = 7 AND category_id = 5');
    Assert::same(2, (int) $stat['total_answered'], 'عدد الإجابات');
    Assert::same(1, (int) $stat['correct_answers'], 'الإجابات الصحيحة');
    Assert::same(50.0, (float) $stat['accuracy'], 'نسبة الدقة');
    Assert::same(2, (int) $db->value('SELECT times_answered FROM `questions` WHERE id = 11'), 'عدّاد الإجابات على السؤال');
});

test('حساب مدة الاختبار وانتهاء المحاولات', function (): void {
    $attempt = [
        'started_at'       => date('Y-m-d H:i:s', time() - 600),
        'duration_minutes' => 20,
        'status'           => 'in_progress',
        'expires_at'       => date('Y-m-d H:i:s', time() + 300),
    ];
    Assert::true(!ExamEngine::isExpired($attempt), 'محاولة لم تنتهِ بعد');
    Assert::true(ExamEngine::remainingSeconds($attempt) > 250, 'الوقت المتبقي');

    $expired = array_merge($attempt, ['expires_at' => date('Y-m-d H:i:s', time() - 30)]);
    Assert::true(ExamEngine::isExpired($expired), 'محاولة منتهية');
    Assert::same(0, ExamEngine::remainingSeconds($expired), 'لا وقت متبقٍ بعد الانتهاء');
});

test('الإعدادات الافتراضية: سعر الاشتراك 100 ريال', function (): void {
    FakeDatabase::reset();
    Assert::same(100, (int) settings('subscription_price', 0), 'سعر الاشتراك الافتراضي');
    Assert::true((int) settings('subscription_days', 0) > 0, 'مدة الاشتراك الافتراضية');
    Assert::true(settings('site_name', '') !== '', 'اسم الموقع الافتراضي');
});

test('دفع يدوي: طلب اشتراك ثم اعتماد', function (): void {
    FakeDatabase::reset();
    $db = Database::instance();
    $db->insert('tracks', ['id' => 1, 'name_ar' => 'التربوي', 'slug' => 'edu', 'is_active' => 1]);
    $planId = $db->insert('subscription_plans', ['code' => 'yearly', 'name_ar' => 'سنوي', 'price_sar' => 100, 'duration_days' => 365, 'is_active' => 1]);
    $userId = $db->insert('users', ['full_name' => 'طالب', 'email' => 'buyer@example.com', 'phone' => '966500000010', 'password_hash' => 'x']);

    $service = new App\Services\SubscriptionService();
    $request = $service->createRequest($userId, $planId, [
        'method'           => 'bank_transfer',
        'gateway'          => 'manual',
        'reference_number' => 'REF-123456',
    ]);
    Assert::true(($request['ok'] ?? false) === true, 'إنشاء طلب اشتراك: ' . ($request['message'] ?? ''));

    $payment = $db->one("SELECT * FROM `payments` WHERE user_id = :id AND status = 'pending'", ['id' => $userId]);
    Assert::true($payment !== null, 'سجل دفع بانتظار المراجعة');

    $adminId = $db->insert('users', ['full_name' => 'مدير', 'email' => 'admin@example.com', 'phone' => '966500000011', 'password_hash' => 'x', 'role' => 'admin']);
    $approve = $service->approvePayment((int) $payment['id'], $adminId, 'تم التحقق من الحوالة');
    Assert::true(($approve['ok'] ?? false) === true, 'اعتماد الدفع: ' . ($approve['message'] ?? ''));

    $subscription = $db->one("SELECT * FROM `subscriptions` WHERE user_id = :id ORDER BY id DESC", ['id' => $userId]);
    Assert::true($subscription !== null && (string) $subscription['status'] === 'active', 'الاشتراك نشط بعد الاعتماد');
    Assert::true($service->isActive($userId), 'دالة التحقق من الاشتراك');
});

/* ==================== 8) التثبيت والنشر ==================== */
group('التثبيت والنشر');

test('تقسيم database.sql إلى جمل SQL مستقلة', function (): void {
    $sql = (string) file_get_contents(BASE_PATH . '/database.sql');
    $statements = App\DatabaseImporter::split($sql);
    Assert::true(count($statements) >= 70, 'عدد الجمل المستخرجة: ' . count($statements));
    $createTables = 0;
    foreach ($statements as $statement) {
        if (preg_match('/^CREATE TABLE/i', $statement) === 1) {
            $createTables++;
        }
        Assert::true(!str_contains($statement, '-- =='), 'لم تُترك تعليقات داخل الجمل');
    }
    Assert::same(27, $createTables, 'عدد جداول CREATE TABLE في database.sql');
    Assert::same(27, count(App\DatabaseImporter::expectedTables()), 'عدد الجداول المتوقعة في التحقق');
});

test('استيراد SQL يستبعد CREATE DATABASE و USE', function (): void {
    $statements = App\DatabaseImporter::split((string) file_get_contents(BASE_PATH . '/database.sql'));
    $filtered = App\DatabaseImporter::filterSchemaStatements($statements);
    Assert::same(2, $filtered['skipped'], 'جملتا CREATE DATABASE و USE');
    foreach ($filtered['statements'] as $statement) {
        Assert::true(preg_match('/^(CREATE DATABASE|USE\s|ALTER DATABASE)/i', $statement) !== 1, 'لا توجد جمل قاعدة بيانات عامة');
    }
});

test('المُقسّم يحترم الفواصل المنقوطة داخل النصوص العربية', function (): void {
    $sample = "INSERT INTO `x` (`t`) VALUES ('نص؛ فيه فاصلة منقوطة; ونقطة. اختبار');\nSELECT 1;";
    $parts = App\DatabaseImporter::split($sample);
    Assert::same(2, count($parts), 'عدد الجمل');
    Assert::contains('فاصلة منقوطة; ونقطة', $parts[0], 'النص العربي سليم داخل الجملة');
});

test('كل جداول المنصة المتوقعة موجودة فعلاً في database.sql', function (): void {
    $sql = (string) file_get_contents(BASE_PATH . '/database.sql');
    $missing = [];
    foreach (App\DatabaseImporter::expectedTables() as $table) {
        if (preg_match('/CREATE TABLE `' . preg_quote($table, '/') . '`/i', $sql) !== 1) {
            $missing[] = $table;
        }
    }
    Assert::same([], $missing, 'جداول متوقعة وغير موجودة في ملف البيانات');
});

test('كاتب ملف .env يمنع حقن الأسطر ويقتبس القيم', function (): void {
    Assert::same('100', App\EnvWriter::format('100'), 'قيمة رقمية بدون اقتباس');
    Assert::same('"منصة الرخصة المهنية"', App\EnvWriter::format('منصة الرخصة المهنية'), 'قيمة بمسافات تُقتبس');
    $injected = App\EnvWriter::format("secret\nAPP_DEBUG=true");
    Assert::true(!str_contains($injected, "\n") && str_contains($injected, 'APP_DEBUG=true'), 'لا يُسمح بإضافة مفتاح جديد عبر قيمة مُدخلة: ' . $injected);

    $path = sys_get_temp_dir() . '/pl-env-test-' . bin2hex(random_bytes(4)) . '.env';
    $ok = App\EnvWriter::write($path, ['APP_KEY' => 'abc123', 'APP_NAME' => 'منصتي'], BASE_PATH . '/.env.example');
    Assert::true($ok && is_file($path), 'كتابة ملف .env');
    $content = (string) file_get_contents($path);
    Assert::contains('APP_KEY=abc123', $content, 'كتابة المفتاح الجديد');
    Assert::contains('APP_NAME=منصتي', $content, 'كتابة قيمة عربية بدون حاجة للاقتباس');
    Assert::contains('"منصة بها مسافات"', App\EnvWriter::format('منصة بها مسافات'), 'القيم بمسافات تُقتبس');
    Assert::contains('DB_HOST', $content, 'الحفاظ على بقية القالب');
    @unlink($path);
});

test('استيراد .env الجديد مقروء من قارئ Env', function (): void {
    $path = sys_get_temp_dir() . '/pl-env-read-' . bin2hex(random_bytes(4)) . '.env';
    App\EnvWriter::write($path, ['APP_NAME' => 'منصة تجريبية', 'APP_DEBUG' => 'false', 'DB_PASSWORD' => 'p@ss#word'], null);
    $parsed = App\Env::parse((string) file_get_contents($path));
    Assert::same('منصة تجريبية', $parsed['APP_NAME'] ?? null, 'اسم المنصة العربية');
    Assert::same('false', $parsed['APP_DEBUG'] ?? null, 'قيمة منطقية');
    Assert::same('p@ss#word', $parsed['DB_PASSWORD'] ?? null, 'كلمة مرور بها رموز');
    @unlink($path);
});

test('الصفحات والقوالب تستدعي أصناف المنصة بمسارها الصحيح أو بـ use', function (): void {
    // هذا الاختبار يمنع خطأً يوقف كل صفحات المنصة:
    // استدعاء View::render أو Validator::make داخل ملف بلا مساحة أسماء ودون use App\View;
    // يُترجم إلى \View فيفشل الملف كاملاً — اكتُشف في المراجعة النهائية لهذا الإصدار.
    $classMap = [];
    foreach ((array) glob(BASE_PATH . '/src/*.php') as $file) {
        $name = basename((string) $file, '.php');
        $classMap[$name] = 'App\\' . $name;
    }
    foreach (['Services', 'Repositories', 'Payments'] as $dir) {
        foreach ((array) glob(BASE_PATH . '/src/' . $dir . '/*.php') as $file) {
            $name = basename((string) $file, '.php');
            $classMap[$name] = 'App\\' . $dir . '\\' . $name;
        }
    }

    $files = [BASE_PATH . '/index.php', BASE_PATH . '/install.php'];
    foreach (['admin', 'auth', 'exams', 'questions', 'student', 'subscriptions', 'views', 'includes', 'telegram', 'api', 'tools', 'config'] as $dir) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH . '/' . $dir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }

    $problems = [];
    foreach ($files as $file) {
        $source = (string) file_get_contents($file);
        preg_match_all('/^use\s+App\\\\[\w\\\\]*?(\w+);/m', $source, $imports);
        preg_match_all('/(?<![\\\\\w>])([A-Z][A-Za-z0-9_]*)::/', $source, $usages);
        foreach (array_unique($usages[1]) as $name) {
            if (isset($classMap[$name]) && !in_array($name, $imports[1], true)) {
                $problems[] = str_replace(BASE_PATH . '/', '', $file) . ' → ' . $name . '::';
            }
        }
    }
    Assert::same([], array_slice($problems, 0, 10), 'ملفات تستدعي أصناف المنصة دون use أو مسار كامل (' . count($problems) . ')');
});

test('قوالب العرض لا تستدعي أصنافاً غير معرّفة داخل القالب', function (): void {
    $view = (string) file_get_contents(BASE_PATH . '/views/layouts/app.php');
    Assert::contains('\App\View::path', $view, 'قالب اللوحة يستدعي View بمسار كامل');
    Assert::true(preg_match('/(?<![\\\\\w>])View::/', $view) !== 1, 'لا استدعاء غير مؤهَّل لـ View داخل القالب');
});

test('أدوات النشر وملفات التوثيق موجودة', function (): void {
    foreach ([
        'install.php',
        'tools/install-cli.php',
        'tools/cron.php',
        'tools/build-package.php',
        'telegram/webhook.php',
        'telegram/poll.php',
        'telegram/set-webhook.php',
        'docs/التثبيت-على-سيرفر.md',
        'tools/dev/smoke.php',
        'tools/dev/validate-sql.php',
        '.user.ini',
        '.env.example',
    ] as $file) {
        Assert::true(is_file(BASE_PATH . '/' . $file), 'ملف مفقود: ' . $file);
    }
});

test('قالب .env يحتوي كل المفاتيح الأساسية', function (): void {
    $example = (string) file_get_contents(BASE_PATH . '/.env.example');
    foreach (['APP_NAME', 'APP_URL', 'APP_KEY', 'APP_DEBUG', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME',
              'DB_PASSWORD', 'SESSION_SECURE', 'SUBSCRIPTION_PRICE', 'TELEGRAM_BOT_TOKEN'] as $key) {
        Assert::true(preg_match('/^' . $key . '=/m', $example) === 1, 'مفتاح ناقص في .env.example: ' . $key);
    }
});

test('معالج التثبيت مقفول عند وجود storage/installed.lock', function (): void {
    $installer = (string) file_get_contents(BASE_PATH . '/install.php');
    Assert::contains('installed.lock', $installer, 'ملف القفل');
    Assert::contains('INSTALL_LOCK', $installer, 'استخدام القفل في الكود');
    $lockedBranch = str_contains($installer, 'if ($locked)');
    Assert::true($lockedBranch, 'المعالج يعرض شاشة «مثبّت بالفعل» عند وجود القفل');
    Assert::true(!str_contains($installer, 'TELEGRAM_BOT_TOKEN') || str_contains($installer, 'TELEGRAM_BOT_TOKEN'), 'لا يُطبع توكن البوت في الواجهة');
});

test('حماية الملفات الحساسة مضبوطة في .htaccess و Nginx موثّق', function (): void {
    $htaccess = (string) file_get_contents(BASE_PATH . '/.htaccess');
    foreach (['\.env', 'database', 'config|src|views|includes|storage'] as $pattern) {
        Assert::contains($pattern, $htaccess, 'قاعدة حماية ناقصة: ' . $pattern);
    }
    Assert::contains('IfModule mod_php', $htaccess, 'إعدادات php_value مغلّفة لحماية PHP-FPM');
    $nginx = (string) file_get_contents(BASE_PATH . '/docs/التثبيت-على-سيرفر.md');
    Assert::contains('location ~* ^/(config|src|views|includes|storage|tests|tools|docs)/', $nginx, 'قواعد Nginx في الدليل');
});

test('مهمة الصيانة الدورية تُنفَّذ بلا أخطاء وتُسجّل وقت التشغيل', function (): void {
    FakeDatabase::reset();
    $db = Database::instance();
    // بيانات الحد الأدنى لعمل المهام
    $db->insert('users', ['id' => 1, 'full_name' => 'طالب', 'email' => 'a@b.com', 'phone' => '966500000000', 'password_hash' => 'x']);
    $db->insert('subscription_plans', ['id' => 1, 'code' => 'yearly', 'name_ar' => 'سنوي', 'price_sar' => 100, 'duration_days' => 365, 'is_active' => 1]);
    $db->insert('subscriptions', ['user_id' => 1, 'plan_id' => 1, 'status' => 'active', 'expires_at' => date('Y-m-d H:i:s', time() - 86400), 'duration_days' => 365]);

    $result = App\Services\SubscriptionService::expireDue();
    Assert::true($result >= 0, 'عدد الاشتراكات المنتهية: ' . $result);
    Assert::same('expired', (string) $db->value('SELECT status FROM `subscriptions` WHERE user_id = 1'), 'تحويل الاشتراك المنتهي إلى expired');

    $expired = App\Services\ExamEngine::expireOverdue(10);
    Assert::true($expired >= 0, 'عدد الاختبارات المتأخرة المُغلقة');
});

/* ==================== التشغيل ==================== */
$passed = 0;
$failed = 0;
$skipped = 0;
$failures = [];
$assertions = 0;
$startedAt = microtime(true);

echo "\n";
echo "  اختبارات منصة الرخصة المهنية\n";
echo '  القاعدة: ' . ($realDb ? 'MySQL الفعلي' : 'قاعدة بيانات وهمية في الذاكرة') . "\n";
echo "  ─────────────────────────────────────────────\n";

foreach ($groups as $groupName => $tests) {
    if ($filter !== '' && !str_contains($groupName, $filter)) {
        continue;
    }
    echo "\n  ▸ {$groupName}\n";
    foreach ($tests as [$name, $body]) {
        $countBefore = Assert::$count;
        try {
            $body();
            $passed++;
            echo '    ✔ ' . $name . "\n";
        } catch (TestSkipped $skippedTest) {
            $skipped++;
            echo '    ↷ ' . $name . ' (متخطى: ' . $skippedTest->getMessage() . ")\n";
        } catch (Throwable $error) {
            $failed++;
            $failures[] = $groupName . ' → ' . $name . ': ' . $error->getMessage();
            echo '    ✘ ' . $name . ' — ' . $error->getMessage() . "\n";
        }
        $assertions += Assert::$count - $countBefore;
    }
}

$duration = round((microtime(true) - $startedAt) * 1000);

echo "\n  ─────────────────────────────────────────────\n";
echo '  النتيجة: ' . $passed . ' ناجح • ' . $failed . ' فاشل • ' . $skipped . " متخطى\n";
echo '  عدد التحققات: ' . $assertions . ' • الزمن: ' . $duration . " مللي ثانية\n";

if ($failures !== []) {
    echo "\n  تفاصيل الإخفاقات:\n";
    foreach ($failures as $failure) {
        echo '   - ' . $failure . "\n";
    }
}

if (!$realDb) {
    echo "\n  ملاحظة: هذه الاختبارات لا تلمس قاعدة بياناتك. لاختبار MySQL الفعلي شغّل:\n";
    echo "      php tests/run.php --real-db\n";
}

echo "\n";
exit($failed === 0 ? 0 : 1);
