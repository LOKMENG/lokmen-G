<?php
/**
 * أداة تطوير: توليد صفحات ثابتة من المنصة لعرض التصميم بلا سيرفر PHP/MySQL.
 *
 *   PHP_ROOT=/path php tools/dev/render-preview.php --out=/path/preview
 *
 * تُستخدم لمراجعة الشكل فقط (تُعرض الصفحات على قاعدة بيانات وهمية في الذاكرة)،
 * ولا تُستخدم في الإنتاج ولا تعدّل أي بيانات.
 */
declare(strict_types=1);

define('APP_TEST_FAKE_DB', true);
define('APP_CSRF_EXEMPT', true);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

use Tests\Support\FakeDatabase;

$out = (string) (cli_option('out') ?? '/tmp/preview');
if (!is_dir($out)) {
    mkdir($out, 0775, true);
}

/** بيانات تجريبية ثرية لعرض الواجهات */
function preview_seed(): void
{
    FakeDatabase::reset();
    FakeDatabase::seed('settings', [
        ['key' => 'site_name', 'value' => 'منصة الرخصة المهنية'],
        ['key' => 'site_description', 'value' => 'منصة سعودية للتدريب على اختبار الرخصة المهنية للمعلمين'],
        ['key' => 'subscription_price', 'value' => '100'],
        ['key' => 'subscription_days', 'value' => '365'],
        ['key' => 'maintenance_mode', 'value' => '0'],
        ['key' => 'contact_phone', 'value' => '+966 55 000 0000'],
        ['key' => 'contact_email', 'value' => 'info@example.com'],
        ['key' => 'telegram_bot_username', 'value' => 'lokmen_bot'],
        ['key' => 'exam_default_pass', 'value' => '60'],
    ]);
    FakeDatabase::seed('users', [
        ['id' => 1, 'full_name' => 'أ. عبدالله المطيري', 'email' => 'admin@example.com', 'phone' => '966500000001',
         'password_hash' => password_hash('Admin@12345', PASSWORD_BCRYPT), 'role' => 'admin', 'status' => 'active',
         'created_at' => date('Y-m-d H:i:s', time() - 86400 * 120)],
        ['id' => 2, 'full_name' => 'نورة العتيبي', 'email' => 'student@example.com', 'phone' => '966500000002',
         'password_hash' => password_hash('Student@12345', PASSWORD_BCRYPT), 'role' => 'student', 'status' => 'active',
         'created_at' => date('Y-m-d H:i:s', time() - 86400 * 40)],
    ]);
    FakeDatabase::seed('tracks', [
        ['id' => 1, 'name_ar' => 'المسار التربوي العام', 'code' => 'general', 'slug' => 'general',
         'description' => 'المهارات التربوية والمهنية المشتركة لكل المعلمين: التخطيط، التقويم، الإدارة الصفية.',
         'icon' => 'bi-people', 'color' => '#2A7A62', 'is_active' => 1, 'sort_order' => 1],
        ['id' => 2, 'name_ar' => 'التخصص: حاسب آلي', 'code' => 'cs', 'slug' => 'cs',
         'description' => 'أسئلة التخصص لمعلمي الحاسب: البرمجة، قواعد البيانات، الشبكات، الأمن السيبراني.',
         'icon' => 'bi-cpu', 'color' => '#4576B5', 'is_active' => 1, 'sort_order' => 2],
    ]);
    FakeDatabase::seed('categories', [
        ['id' => 1, 'track_id' => 1, 'name_ar' => 'التخطيط للتعلم', 'is_active' => 1, 'sort_order' => 1],
        ['id' => 2, 'track_id' => 1, 'name_ar' => 'التقويم وأدواته', 'is_active' => 1, 'sort_order' => 2],
        ['id' => 3, 'track_id' => 1, 'name_ar' => 'الإدارة الصفية', 'is_active' => 1, 'sort_order' => 3],
        ['id' => 4, 'track_id' => 2, 'name_ar' => 'البرمجة', 'is_active' => 1, 'sort_order' => 1],
        ['id' => 5, 'track_id' => 2, 'name_ar' => 'قواعد البيانات', 'is_active' => 1, 'sort_order' => 2],
        ['id' => 6, 'track_id' => 2, 'name_ar' => 'الشبكات', 'is_active' => 1, 'sort_order' => 3],
    ]);
    $questions = [];
    $texts = [
        [1, 'أي مما يلي يُعدّ من مكوّنات التخطيط للتعلم؟'],
        [2, 'ما الأنسب لقياس نواتج التعلم العليا (التحليل والتركيب)؟'],
        [3, 'أفضل إجراء للتعامل مع الطالب كثير الحركة والحديث أثناء الحصة:'],
        [4, 'أي بنية تخزين تُعدّ الأفضل لجميع القيم في نطاق مغلق؟'],
        [5, 'في قواعد البيانات، ما وظيفة الفهرسة (Index)؟'],
        [6, 'ما البروتوكول المسؤول عن ترجمة أسماء النطاقات إلى عناوين IP؟'],
    ];
    foreach ($texts as $index => [$categoryId, $text]) {
        $questions[] = [
            'id' => $index + 1, 'track_id' => $categoryId <= 3 ? 1 : 2, 'category_id' => $categoryId,
            'question_text' => $text,
            'option_a' => 'الخيار الأول الصحيح', 'option_b' => 'الخيار الثاني', 'option_c' => 'الخيار الثالث', 'option_d' => 'الخيار الرابع',
            'correct_answer' => 'a',
            'explanation' => 'شرح موجز للمفهوم ومصدره التدريبي لمساعدة المتدرب على الفهم.',
            'difficulty' => ['easy', 'medium', 'hard'][$index % 3], 'question_type' => 'mcq',
            'is_free' => $index < 3 ? 1 : 0, 'active' => 1, 'needs_review' => 0,
            'times_answered' => 40 + $index * 7, 'times_correct' => 26 + $index * 4, 'times_reported' => 0,
            'created_at' => date('Y-m-d H:i:s', time() - 86400 * 10),
        ];
    }
    FakeDatabase::seed('questions', $questions);
    FakeDatabase::seed('subscription_plans', [
        ['id' => 1, 'code' => 'yearly', 'name_ar' => 'الاشتراك السنوي', 'description' => 'وصول كامل للسنة الدراسية',
         'price_sar' => 100, 'duration_days' => 365, 'is_active' => 1, 'is_featured' => 1,
         'features' => json_encode(['بنك الأسئلة كامل', 'اختبارات تجريبية بلا حدود', 'تحليل مستوى تفصيلي', 'تحديثات مستمرة'], JSON_UNESCAPED_UNICODE)],
    ]);
    FakeDatabase::seed('subscriptions', [
        ['id' => 1, 'user_id' => 2, 'plan_id' => 1, 'status' => 'active', 'duration_days' => 365,
         'starts_at' => date('Y-m-d H:i:s', time() - 86400 * 60), 'expires_at' => date('Y-m-d H:i:s', time() + 86400 * 305)],
    ]);
    FakeDatabase::seed('sources', [
        ['id' => 1, 'name' => 'كتاب الرخصة المهنية — التربوي العام', 'type' => 'book', 'author' => 'مجموعة مؤلفين',
         'year' => 1445, 'license_note' => 'نسخة يملك المالك حق استخدامها داخل المنصة', 'is_active' => 1],
        ['id' => 2, 'name' => 'ملف PDF: تجميعات تخصص الحاسب', 'type' => 'pdf',
         'license_note' => 'ملف قدّمه المالك — مرخّص للاستخدام داخل المنصة', 'is_active' => 1],
        ['id' => 3, 'name' => 'أسئلة من إعداد المشرف التربوي', 'type' => 'teacher', 'author' => 'أ. عبدالله',
         'license_note' => NULL, 'is_active' => 1],
    ]);
    FakeDatabase::seed('exam_templates', [
        ['id' => 1, 'title' => 'الاختبار التربوي الشامل', 'slug' => 'general-full', 'track_id' => 1,
         'description' => 'خمسون سؤالاً تغطي محاور الاختبار التربوي العام بنسبة نجاح 60%.',
         'question_count' => 5, 'duration_minutes' => 20, 'pass_percentage' => 60, 'is_active' => 1,
         'mode' => 'mock', 'difficulty' => 'any', 'show_explanation' => 1, 'require_subscription' => 0, 'sort_order' => 1],
        ['id' => 2, 'title' => 'التخصص: حاسب آلي', 'slug' => 'cs-full', 'track_id' => 2,
         'description' => 'اختبار تخصصي لمعلمي الحاسب الآلي: البرمجة وقواعد البيانات والشبكات.',
         'question_count' => 10, 'duration_minutes' => 30, 'pass_percentage' => 60, 'is_active' => 1,
         'mode' => 'mock', 'difficulty' => 'any', 'show_explanation' => 1, 'require_subscription' => 1, 'sort_order' => 2],
    ]);
    FakeDatabase::seed('testimonials', [
        ['id' => 1, 'user_id' => 2, 'name' => 'نورة العتيبي', 'role' => 'معلمة حاسب', 'city' => 'الرياض',
         'body' => "وفرت علي وقتاً كبيراً، راجعت أسئلة التخصص كاملة قبل الاختبار ونجحت من أول محاولة.", 'rating' => 5,
         'is_published' => 1, 'consent' => 1, 'sort_order' => 1],
        ['id' => 2, 'user_id' => 3, 'name' => 'محمد الشهري', 'role' => 'معلم علوم', 'city' => 'الدمام',
         'body' => "تحليل المستوى كان السبب في تحسّني، عرفت نقاط ضعفي بالضبط وذاكرتها.", 'rating' => 5,
         'is_published' => 1, 'consent' => 1, 'sort_order' => 2],
        ['id' => 3, 'user_id' => 4, 'name' => 'سارة القحطاني', 'role' => 'معلمة لغة عربية', 'city' => 'أبها',
         'body' => "المحاكاة بنفس نمط الاختبار خففت توتر يوم الاختبار، والتجربة على الجوال ممتازة.", 'rating' => 4,
         'is_published' => 1, 'consent' => 1, 'sort_order' => 3],
    ]);
    FakeDatabase::seed('exam_attempts', [
        ['id' => 1, 'user_id' => 2, 'track_id' => 1, 'exam_template_id' => 1, 'status' => 'in_progress',
         'mode' => 'exam', 'questions_count' => 5, 'duration_minutes' => 20, 'score' => 0, 'passed' => 0,
         'started_at' => date('Y-m-d H:i:s', time() - 300), 'expires_at' => date('Y-m-d H:i:s', time() + 900),
         'finished_at' => null],
    ]);
    $attemptQuestions = [];
    foreach (range(1, 5) as $order) {
        $attemptQuestions[] = [
            'id' => $order, 'attempt_id' => 1, 'question_id' => $order, 'question_order' => $order,
            'option_order' => json_encode(['a', 'b', 'c', 'd']),
            'selected_answer' => $order <= 2 ? 'a' : null,
            'is_correct' => $order <= 2 ? 1 : null, 'is_flagged' => $order === 3 ? 1 : 0, 'time_spent_seconds' => 25,
        ];
    }
    FakeDatabase::seed('exam_attempt_questions', $attemptQuestions);
    FakeDatabase::seed('user_category_stats', [
        ['id' => 1, 'user_id' => 2, 'category_id' => 4, 'total_answered' => 40, 'correct_answers' => 34, 'wrong_answers' => 6, 'accuracy' => 85],
        ['id' => 2, 'user_id' => 2, 'category_id' => 5, 'total_answered' => 30, 'correct_answers' => 27, 'wrong_answers' => 3, 'accuracy' => 90],
        ['id' => 3, 'user_id' => 2, 'category_id' => 6, 'total_answered' => 28, 'correct_answers' => 17, 'wrong_answers' => 11, 'accuracy' => 61],
        ['id' => 4, 'user_id' => 2, 'category_id' => 1, 'total_answered' => 22, 'correct_answers' => 19, 'wrong_answers' => 3, 'accuracy' => 86],
        ['id' => 5, 'user_id' => 2, 'category_id' => 3, 'total_answered' => 18, 'correct_answers' => 11, 'wrong_answers' => 7, 'accuracy' => 61],
    ]);
    FakeDatabase::seed('payments', [
        ['id' => 1, 'user_id' => 2, 'amount' => 100, 'method' => 'bank_transfer', 'status' => 'approved',
         'reference_number' => 'TRX-884213', 'created_at' => date('Y-m-d H:i:s', time() - 86400 * 60),
         'reviewed_at' => date('Y-m-d H:i:s', time() - 86400 * 59)],
        ['id' => 2, 'user_id' => 3, 'amount' => 100, 'method' => 'stc_pay', 'status' => 'pending',
         'reference_number' => 'STC-119002', 'created_at' => date('Y-m-d H:i:s', time() - 3600)],
    ]);
    FakeDatabase::seed('notifications', [
        ['id' => 1, 'user_id' => 2, 'title' => 'تم تفعيل اشتراكك', 'body' => 'اشتراكك السنوي فعّال الآن. بالتوفيق!',
         'link' => '', 'is_read' => 1, 'created_at' => date('Y-m-d H:i:s', time() - 86400 * 59)],
        ['id' => 2, 'user_id' => 2, 'title' => 'تذكير: اختبار تجريبي جديد', 'body' => 'أُضيف نموذج اختبار جديد لمسار الحاسب.',
         'link' => '', 'is_read' => 0, 'created_at' => date('Y-m-d H:i:s', time() - 7200)],
    ]);
}

/** الصفحات المطلوب توليدها */
$routes = [
    'index.php'              => ['guest', 'index.html', 'الصفحة الرئيسية'],
    'auth/login.php'         => ['guest', 'login.html', 'تسجيل الدخول'],
    'auth/register.php'      => ['guest', 'register.html', 'إنشاء حساب'],
    'questions/index.php'    => ['student', 'questions.html', 'بنك الأسئلة'],
    'exams/index.php'        => ['student', 'exams.html', 'الاختبارات'],
    'exams/take.php'         => ['student', 'exam-take.html', 'أداء الاختبار'],
    'student/dashboard.php'  => ['student', 'dashboard.html', 'لوحة الطالب'],
    'student/statistics.php' => ['student', 'statistics.html', 'إحصاءات الطالب'],
    'subscriptions/plans.php' => ['student', 'plans.html', 'الباقات'],
    'admin/index.php'        => ['admin', 'admin.html', 'لوحة الإدارة'],
    'admin/questions.php'    => ['admin', 'admin-questions.html', 'إدارة الأسئلة'],
    'admin/import.php'       => ['admin', 'admin-import.html', 'استيراد الأسئلة'],
];

// صفحة واحدة لكل عملية: بعض الصفحات تُنهي التنفيذ بعد تحويل (redirect)
$only = (string) (cli_option('route') ?? '');
if ($only === '') {
    if (cli_has_flag('list')) {
        foreach ($routes as $route => [$persona, $file, $label]) {
            echo str_pad($route, 26) . str_pad($persona, 9) . $file . '   ' . $label . "\n";
        }
        exit(0);
    }
    fwrite(STDERR, "استخدم --route=<page> لصفحة واحدة، أو --list لعرض القائمة.\n");
    exit(64);
}
if (!isset($routes[$only])) {
    fwrite(STDERR, "صفحة غير معروفة: {$only}\n");
    exit(2);
}

$generated = [];

foreach ([$only => $routes[$only]] as $route => [$persona, $file, $label]) {
    preview_seed();

    $_SESSION = [];
    if ($persona === 'admin') {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';
    } elseif ($persona === 'student') {
        $_SESSION['user_id'] = 2;
        $_SESSION['role'] = 'student';
    }

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/' . $route;
    $_SERVER['SCRIPT_NAME'] = '/' . $route;
    $_SERVER['PHP_SELF'] = '/' . $route;
    $_SERVER['SCRIPT_FILENAME'] = BASE_PATH . '/' . $route;
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_GET = str_contains($route, 'exams/take.php') ? ['id' => 1] : [];
    $_POST = [];

    ob_start();
    try {
        require BASE_PATH . '/' . $route;
        $html = (string) ob_get_clean();
    } catch (Throwable $e) {
        ob_end_clean();
        $html = '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8">'
            . '<body style="font-family:sans-serif;padding:40px"><h1>تعذّر توليد الصفحة</h1><pre>'
            . htmlspecialchars($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine()) . '</pre></body></html>';
    }

    // روابط نسبية لتعمل المعاينة تحت أي نطاق (بدون http://localhost)
    $html = str_replace('http://localhost', '', $html);

    // لافتة صغيرة توضّح أن هذه معاينة تصميم
    $banner = '<div style="position:fixed;inset-block-start:0;inset-inline-start:0;z-index:9999;'
        . 'font:600 12px/1.6 system-ui;background:#232D3C;color:#fff;padding:6px 14px;'
        . 'border-end-end-radius:12px">معاينة تصميم — ' . htmlspecialchars($label) . '</div>';
    $html = str_replace('<body', '<body data-preview', $html);
    $html = preg_replace('/(<body[^>]*>)/', '$1' . $banner, $html, 1) ?? $html;

    if (cli_has_flag('print')) {
        // الطبع إلى المخرج القياسي: يُستخدم حين لا يمكن الكتابة إلى قرص المضيف
        // (مثل تشغيل الأداة داخل بيئة WASM) فتُوجَّه المخرجات إلى ملف.
        echo $html;
        exit(0);
    }
    file_put_contents($out . '/' . $file, $html);
    $generated[] = [$file, strlen($html)];
}

foreach ($generated as [$file, $size]) {
    echo '  ✔ ' . str_pad($file, 26) . number_format($size / 1024, 1) . " ك.ب\n";
}
echo '  المجلد: ' . $out . "\n";
