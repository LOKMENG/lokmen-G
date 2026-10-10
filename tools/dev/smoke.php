<?php
/**
 * فحص دخاني لطبقة الويب: يُشغّل كل صفحة من صفحات المنصة بصلاحية مناسبة
 * على قاعدة بيانات وهمية في الذاكرة، ويتأكد من أنها تُنتج HTML بلا أخطاء PHP.
 *
 * لا يلمس قاعدة بياناتك ولا يحتاج Apache — يكفي PHP CLI:
 *
 *   php tools/dev/smoke.php --list                 # عرض الصفحات المكتشفة
 *   php tools/dev/smoke.php --all                  # تشغيل كل الصفحات (يفتح عملية لكل صفحة)
 *   php tools/dev/smoke.php --route=admin/index.php --persona=admin
 *
 * ملاحظة: في البيئات التي لا يوجد فيها ملف PHP تنفيذي (مثل php-wasm) يعمل
 * الخياران --list و --route= فقط، ويجب تشغيل الحلقة من الصدفة.
 */
declare(strict_types=1);

define('APP_TEST_FAKE_DB', true);
define('APP_CSRF_EXEMPT', true);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

use App\Audit;
use App\Env;
use App\Logger;
use Tests\Support\FakeDatabase;

/** الصفحات التي تُعرض عبر GET (تُستثنى نقاط POST والنماذج وواجهات API). */
function smoke_routes(): array
{
    $routes = [
        'index.php'                     => 'guest',
        'auth/login.php'                => 'guest',
        'auth/register.php'             => 'guest',
        'auth/forgot-password.php'      => 'guest',
        'auth/reset-password.php'       => 'guest',
        'questions/index.php'           => 'student',
        'questions/practice.php'        => 'student',
        'questions/report.php'          => 'student',
        'exams/index.php'               => 'student',
        'exams/take.php'                => 'student',
        'exams/history.php'             => 'student',
        'exams/result.php'              => 'student',
        'exams/review.php'              => 'student',
        'student/dashboard.php'         => 'student',
        'student/statistics.php'        => 'student',
        'student/notifications.php'     => 'student',
        'student/profile.php'           => 'student',
        'student/support.php'           => 'student',
        'student/telegram.php'          => 'student',
        'subscriptions/plans.php'       => 'student',
        'subscriptions/checkout.php'    => 'student',
        'subscriptions/status.php'      => 'student',
        'admin/index.php'               => 'admin',
        'admin/questions.php'           => 'admin',
        'admin/question-form.php'       => 'admin',
        'admin/import.php'              => 'admin',
        'admin/taxonomy.php'            => 'admin',
        'admin/exams.php'               => 'admin',
        'admin/subscriptions.php'       => 'admin',
        'admin/payments.php'            => 'admin',
        'admin/users.php'               => 'admin',
        'admin/user-form.php'           => 'admin',
        'admin/statistics.php'          => 'admin',
        'admin/testimonials.php'        => 'admin',
        'admin/support.php'             => 'admin',
        'admin/reports-queue.php'       => 'admin',
        'admin/telegram.php'            => 'admin',
        'admin/settings.php'            => 'admin',
        'admin/audit.php'               => 'admin',
    ];

    // أضف أي صفحة جديدة لم تُدرج يدوياً (تنبيه حتى تُضاف بقصد)
    foreach (['admin', 'student', 'exams', 'questions', 'subscriptions', 'auth'] as $dir) {
        foreach ((array) glob(BASE_PATH . '/' . $dir . '/*.php') as $file) {
            $rel = $dir . '/' . basename((string) $file);
            if (!isset($routes[$rel]) && !in_array(basename($rel), ['logout.php', 'start.php', 'submit.php'], true)) {
                $routes[$rel] = $dir === 'admin' ? 'admin' : 'student';
            }
        }
    }

    ksort($routes);
    return $routes;
}

/** بيانات تجريبية كافية لعرض الصفحات (مطابقة لأعمدة database.sql). */
function smoke_seed(): void
{
    FakeDatabase::reset();
    FakeDatabase::seed('settings', [
        ['key' => 'site_name', 'value' => 'منصة الرخصة المهنية للمعلمين'],
        ['key' => 'site_description', 'value' => 'منصة سعودية للتدريب على اختبار الرخصة المهنية'],
        ['key' => 'subscription_price', 'value' => '100'],
        ['key' => 'subscription_days', 'value' => '365'],
        ['key' => 'maintenance_mode', 'value' => '0'],
        ['key' => 'telegram_notify_expiry_days', 'value' => '7'],
        ['key' => 'exam_default_pass', 'value' => '60'],
        ['key' => 'strength_threshold', 'value' => '80'],
        ['key' => 'weakness_threshold', 'value' => '60'],
    ]);
    FakeDatabase::seed('users', [
        ['id' => 1, 'full_name' => 'مدير المنصة', 'email' => 'admin@example.com', 'phone' => '966500000001',
         'password_hash' => password_hash('Admin@12345', PASSWORD_BCRYPT), 'role' => 'admin', 'status' => 'active',
         'created_at' => date('Y-m-d H:i:s')],
        ['id' => 2, 'full_name' => 'طالب تجريبي', 'email' => 'student@example.com', 'phone' => '966500000002',
         'password_hash' => password_hash('Student@12345', PASSWORD_BCRYPT), 'role' => 'student', 'status' => 'active',
         'created_at' => date('Y-m-d H:i:s')],
    ]);
    FakeDatabase::seed('tracks', [['id' => 1, 'name_ar' => 'المسار العام', 'slug' => 'general', 'is_active' => 1, 'sort_order' => 1]]);
    FakeDatabase::seed('categories', [
        ['id' => 1, 'track_id' => 1, 'name_ar' => 'التربية الإسلامية', 'slug' => 'islamic', 'is_active' => 1, 'sort_order' => 1],
        ['id' => 2, 'track_id' => 1, 'name_ar' => 'اللغة العربية', 'slug' => 'arabic', 'is_active' => 1, 'sort_order' => 2],
    ]);
    FakeDatabase::seed('questions', [
        ['id' => 1, 'category_id' => 1, 'question_text' => 'ما هو أركان الإسلام الأول؟', 'option_a' => 'الشهادتان',
         'option_b' => 'الصلاة', 'option_c' => 'الزكاة', 'option_d' => 'الصوم', 'correct_answer' => 'a',
         'is_free' => 1, 'active' => 1, 'difficulty' => 'easy', 'question_type' => 'mcq', 'times_answered' => 0,
         'times_correct' => 0, 'created_at' => date('Y-m-d H:i:s')],
    ]);
    FakeDatabase::seed('subscription_plans', [
        ['id' => 1, 'code' => 'yearly', 'name_ar' => 'الاشتراك السنوي', 'price_sar' => 100, 'duration_days' => 365, 'is_active' => 1],
    ]);
    FakeDatabase::seed('testimonials', [
        ['id' => 1, 'user_id' => 2, 'name' => 'طالب تجريبي', 'role' => 'معلم', 'city' => 'الرياض',
         'body' => 'منصة رائعة ساعدتني على اجتياز الاختبار', 'content' => 'منصة رائعة', 'rating' => 5,
         'is_published' => 1, 'consent' => 1, 'sort_order' => 1],
    ]);
    FakeDatabase::seed('subscriptions', [
        ['id' => 1, 'user_id' => 2, 'plan_id' => 1, 'status' => 'active', 'duration_days' => 365,
         'starts_at' => date('Y-m-d H:i:s', time() - 86400 * 30),
         'expires_at' => date('Y-m-d H:i:s', time() + 86400 * 300)],
    ]);
    FakeDatabase::seed('exam_templates', [
        ['id' => 1, 'name_ar' => 'الاختبار الشامل', 'slug' => 'full', 'category_id' => null, 'questions_count' => 10,
         'duration_minutes' => 20, 'pass_score' => 60, 'is_active' => 1, 'is_free' => 1],
    ]);
}

$args = cli_args();
$personaOption = (string) (cli_option('persona') ?? '');
$routeOption = cli_option('route');

if (cli_has_flag('list')) {
    foreach (smoke_routes() as $route => $persona) {
        echo str_pad($route, 34) . $persona . "\n";
    }
    echo "\nالإجمالي: " . count(smoke_routes()) . " صفحة\n";
    exit(0);
}

smoke_seed();

/** تشغيل صفحة واحدة داخل نفس العملية (للاستعمال من الحلقة الخارجية). */
function smoke_run(string $route, string $persona): int
{
    $GLOBALS['smoke_reported'] = false;
    // بعض الصفحات توقف التنفيذ بعد تحويل (redirect) — نطبع سطراً بدلاً من الصمت
    register_shutdown_function(static function () use ($route, $persona): void {
        if (($GLOBALS['smoke_reported'] ?? true) === false) {
            echo sprintf("OK   %-34s %s (تحويل/إنهاء مبكر قبل الطباعة)\n", $route, str_pad($persona, 7));
        }
    });
    $file = BASE_PATH . '/' . $route;
    if (!is_file($file)) {
        $GLOBALS['smoke_reported'] = true;
        fwrite(STDERR, "غير موجود: {$route}\n");
        return 2;
    }

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
    $_SERVER['SCRIPT_FILENAME'] = $file;
    $_SERVER['HTTP_HOST'] = 'example.com';
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_GET = [];
    $_POST = [];

    ob_start();
    try {
        require $file;
    } catch (Throwable $e) {
        $GLOBALS['smoke_reported'] = true;
        $body = (string) ob_get_clean();
        echo sprintf("ERR  %-34s %s: %s (%s:%d)\n", $route, get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
        if ($body !== '') {
            echo '     آخر مخرجات: ' . \App\Str::limit(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '', 200) . "\n";
        }
        return 3;
    }
    $body = (string) ob_get_clean();

    $problems = [];
    foreach (['Fatal error', 'Parse error', 'Warning:', 'Deprecated:', 'Notice:', 'Uncaught', 'Undefined'] as $needle) {
        if (stripos($body, $needle) !== false) {
            $problems[] = $needle;
        }
    }
    // صفحة بلا مخرجات غالباً تعني تحويلاً (redirect) — مقبول لكن يُذكر
    $note = $body === '' ? ' (تحويل/فارغة)' : '';
    $GLOBALS['smoke_reported'] = true;
    if ($problems === []) {
        echo sprintf("OK   %-34s %s len=%d%s\n", $route, str_pad($persona, 7), strlen($body), $note);
        return 0;
    }
    echo sprintf("ERR  %-34s %s\n", $route, implode('، ', $problems));
    foreach (explode("\n", $body) as $line) {
        if (preg_match('/Fatal error|Warning:|Deprecated:|Notice:|Undefined/', $line) === 1) {
            echo '     > ' . trim(strip_tags($line)) . "\n";
        }
    }
    return 1;
}

if ($routeOption !== null) {
    $route = ltrim(str_replace('..', '', (string) $routeOption), '/');
    $persona = $personaOption !== '' ? $personaOption : (smoke_routes()[$route] ?? 'guest');
    exit(smoke_run($route, $persona));
}

if (cli_has_flag('all')) {
    $binary = PHP_BINARY;
    if ($binary === '' || str_contains($binary, 'wasm') || !is_file($binary)) {
        fwrite(STDERR, "لا يمكن تشغيل --all في هذه البيئة (PHP_BINARY غير تنفيذي).\n");
        fwrite(STDERR, "استخدم: for r in \$(php tools/dev/smoke.php --list | head -n -2 | awk '{print \$1}'); do php tools/dev/smoke.php --route=\$r; done\n");
        exit(2);
    }
    $failed = 0;
    $total = 0;
    foreach (smoke_routes() as $route => $persona) {
        $total++;
        $cmd = escapeshellarg($binary) . ' ' . escapeshellarg(__FILE__)
            . ' --route=' . escapeshellarg($route) . ' --persona=' . escapeshellarg($persona);
        $output = [];
        $code = 0;
        exec($cmd . ' 2>&1', $output, $code);
        echo implode("\n", $output) . "\n";
        if ($code !== 0) {
            $failed++;
        }
    }
    echo "\n  النتيجة: " . ($total - $failed) . ' صفحة سليمة من ' . $total
        . ($failed > 0 ? " — فاشلة: {$failed}" : '') . "\n";
    exit($failed === 0 ? 0 : 1);
}

echo "استخدم --list أو --route=... أو --all\n";
exit(64);
