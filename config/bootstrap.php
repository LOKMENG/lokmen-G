<?php
/**
 * نقطة الإقلاع: تُستدعى في أول كل صفحة.
 *   require_once __DIR__ . '/../config/bootstrap.php';
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_START', microtime(true));

// ---------------------------------------------------------------------
//  1) التحقق من المتطلبات
// ---------------------------------------------------------------------
if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('يتطلب هذا النظام PHP 8.1 أو أحدث. الإصدار الحالي: ' . PHP_VERSION);
}

require_once BASE_PATH . '/src/autoload.php';

use App\Database;
use App\Env;
use App\Logger;
use App\Security;
use App\Settings;
use App\View;

// ---------------------------------------------------------------------
//  2) ملف البيئة والإعدادات
// ---------------------------------------------------------------------
// يمكن للاختبارات تحديد ملف بيئة بديل عبر الثابت APP_ENV_FILE
$envFile = (defined('APP_ENV_FILE') && is_string(APP_ENV_FILE)) ? APP_ENV_FILE : BASE_PATH . '/.env';
$envLoaded = Env::load($envFile);
// يُكتشف الملف الحالي بعدة طرق لأن بعض بيئات التشغيل لا تضبط SCRIPT_NAME
$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? $_SERVER['REQUEST_URI'] ?? '');
$isInstaller = str_contains($scriptName, 'install.php')
    || basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'install.php';
// وضع الاختبار: قاعدة بيانات وهمية في الذاكرة، بلا حاجة إلى .env أو MySQL
$fakeDbRequested = (defined('APP_TEST_FAKE_DB') && APP_TEST_FAKE_DB === true) || Env::bool('APP_TEST_FAKE_DB', false);

// التحويل إلى المعالج يخصّ طلبات المتصفح فقط:
// أدوات سطر الأوامر (cron، بناء الحزمة، الفحوصات) تتعامل مع غياب .env بنفسها.
$isCommandLine = in_array(PHP_SAPI, ['cli', 'phpdbg', 'wasm'], true) || defined('LOKMEN_WASM_RUNNER');

if (!$envLoaded && !$isInstaller && !$fakeDbRequested && !$isCommandLine) {
    $base = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    $base = (string) preg_replace('#/(admin|auth|student|exams|questions|subscriptions|telegram|api|tools).*$#', '', $base);
    header('Location: ' . ($base === '' ? '' : $base) . '/install.php');
    exit('لم يتم العثور على ملف الإعدادات .env — جرى تحويلك إلى معالج التثبيت.');
}

if (!$envLoaded) {
    // أثناء التثبيت: قيم افتراضية حتى تُحفظ الإعدادات
    $_ENV['APP_DEBUG'] = 'true';
}

// ملاحظة مهمة: يجب تحميل الدوال المساعدة (بها config()) قبل أي استخدام لها.
require_once BASE_PATH . '/includes/helpers.php';

// ---------------------------------------------------------------------
//  3) البيئة العامة
// ---------------------------------------------------------------------
date_default_timezone_set((string) config('app.timezone', 'Asia/Riyadh'));
mb_internal_encoding('UTF-8');
setlocale(LC_ALL, 'ar_SA.UTF-8', 'ar_SA.utf8', 'ar');

$debug = (bool) config('app.debug', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', Logger::path());

// ---------------------------------------------------------------------
//  4) معالجات الأخطاء (تسجيل + صفحة خطأ عربية)
// ---------------------------------------------------------------------
set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }
    Logger::error($message, ['file' => $file, 'line' => $line]);
    if (config('app.debug')) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return true;
});

set_exception_handler(static function (Throwable $e): void {
    Logger::error('استثناء غير معالج: ' . $e->getMessage(), [
        'file'  => $e->getFile() . ':' . $e->getLine(),
        'trace' => array_slice(array_map(
            static fn(array $t): string => ($t['file'] ?? '') . ':' . ($t['line'] ?? '') . ' ' . ($t['function'] ?? ''),
            $e->getTrace()
        ), 0, 8),
    ]);

    $isApi = str_contains((string) ($_SERVER['REQUEST_URI'] ?? ''), '/api/');
    if ($isApi) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'ok'      => false,
            'error'   => 'server_error',
            'message' => config('app.debug') ? $e->getMessage() : 'حدث خطأ غير متوقع في الخادم.',
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $message = 'حدث خطأ غير متوقع. إذا تكرر الخطأ أبلغ الدعم الفني.';
    if (config('app.debug')) {
        $message = $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine();
    } elseif ($e instanceof RuntimeException && str_contains($e->getMessage(), 'قاعدة البيانات')) {
        $message = $e->getMessage();
    }
    View::error(500, $message, 'خطأ غير متوقع');
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        Logger::error('خطأ فادح: ' . $error['message'], ['file' => $error['file'], 'line' => $error['line']]);
    }
});

// ملاحظة: نقاط النهاية التي لا تحتاج جلسة (مثل ويبهوك تلجرام) تعرّف
// APP_NO_SESSION قبل تضمين هذا الملف لتوفير موارد الخادم وتجنب إنشاء كوكيز.
// ---------------------------------------------------------------------
//  4.5) فرض HTTPS (اختياري من الإعدادات) — قبل أي جلسة أو كوكي
// ---------------------------------------------------------------------
if (!is_cli() && (bool) config('security.force_https', false) && !Security::isHttps()) {
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $uri  = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    if ($host !== '' && !str_contains($host, '127.0.0.1') && !str_contains($host, 'localhost')) {
        header('Location: https://' . $host . $uri, true, 301);
        exit;
    }
}

// ---------------------------------------------------------------------
//  5) الجلسة الآمنة
// ---------------------------------------------------------------------
if (session_status() !== PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli' && !defined('LOKMEN_WASM_RUNNER')
    && !(defined('APP_NO_SESSION') && APP_NO_SESSION === true)) {
    $lifetime = (int) config('session.lifetime', 180) * 60;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', (string) $lifetime);
    ini_set('session.cookie_samesite', (string) config('session.samesite', 'Lax'));
    session_name((string) config('session.name', 'pl_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => (bool) config('session.secure', false) || Security::isHttps(),
        'httponly' => true,
        'samesite' => (string) config('session.samesite', 'Lax'),
    ]);
    session_start();

    // إنهاء الجلسة عند الخمول الطويل أو تجاوز العمر الأقصى (حماية من سرقة الجلسة)
    if (isset($_SESSION['user_id'])) {
        $now         = time();
        $idleMax     = max(5, (int) config('security.session_idle_minutes', 120)) * 60;
        $absoluteMax = max(15, (int) config('security.session_max_minutes', 720)) * 60;
        $startedAt   = (int) ($_SESSION['session_started_at'] ?? $now);
        $lastSeen    = (int) ($_SESSION['last_seen_at'] ?? $now);

        if (($now - $lastSeen) > $idleMax) {
            Logger::security('إنهاء الجلسة لتجاوز مدة الخمول', Security::requestContext() + ['user_id' => (int) $_SESSION['user_id']]);
            session_destroy();
            session_start();
        } elseif (($now - $startedAt) > $absoluteMax) {
            Logger::security('إنهاء الجلسة لتجاوز العمر الأقصى', Security::requestContext() + ['user_id' => (int) $_SESSION['user_id']]);
            session_destroy();
            session_start();
        } else {
            $_SESSION['last_seen_at'] = $now;
        }
    }
}

// ---------------------------------------------------------------------
//  6) رؤوس الحماية
// ---------------------------------------------------------------------
Security::sendSecurityHeaders();

// ---------------------------------------------------------------------
//  6.5) حماية إضافية: منع الوصول للوحة الإدارة إلا من عناوين IP مصرَّح بها
// ---------------------------------------------------------------------
$adminAllowlist = trim((string) (config('security.admin_ip_allowlist', '') ?: (string) env('ADMIN_IP_ALLOWLIST', '')));
$requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$isAdminArea = preg_match('#(^|/)admin(/|$)#i', $requestPath) === 1
    || str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/');
if ($adminAllowlist !== '' && !is_cli() && $isAdminArea) {
    $allowed = array_map('trim', explode(',', $adminAllowlist));
    if (!in_array(Security::ip(), $allowed, true)) {
        Logger::security('محاولة دخول للوحة الإدارة من IP غير مصرح', Security::requestContext());
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><title>403</title>'
            . '<body style="font-family:Tahoma,sans-serif;text-align:center;padding-top:15vh">'
            . '<h1>403</h1><p>غير مصرح بالوصول إلى لوحة الإدارة من هذا العنوان.</p></body></html>';
        exit;
    }
}

// ---------------------------------------------------------------------
//  7) منفذ حقن اتصال قاعدة البيانات (للاختبارات والمعاينة المحلية فقط)
//      لا يعمل إلا إذا كان الطلب من CLI أو عند تفعيل APP_TEST_FAKE_DB صراحة
// ---------------------------------------------------------------------
if ($fakeDbRequested && is_file(BASE_PATH . '/tests/support/FakeDatabase.php')) {
    require_once BASE_PATH . '/tests/support/FakeDatabase.php';
    Database::setConnectionFactory(static fn() => Tests\Support\FakeDatabase::instance());
}

// ---------------------------------------------------------------------
//  8) حماية تلقائية من CSRF لكل طلبات POST
//      يمكن تعطيلها في نقاط الاستقبال الخارجية (تلجرام، بوابات الدفع) عبر:
//      define('APP_CSRF_EXEMPT', true); قبل تضمين bootstrap
// ---------------------------------------------------------------------
if (!$isCommandLine
    && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && !(defined('APP_CSRF_EXEMPT') && APP_CSRF_EXEMPT === true)
    && !$isInstaller) {
    csrf_verify();
}

// ---------------------------------------------------------------------
//  9) وضع الصيانة (يتجاوزه المدير وواجهات API وتلجرام)
// ---------------------------------------------------------------------
if (!(defined('APP_CSRF_EXEMPT') && APP_CSRF_EXEMPT === true)
    && !$isCommandLine
    && !isset($_SESSION['user_id'])
    && !$isInstaller
    && !str_contains((string) ($_SERVER['REQUEST_URI'] ?? ''), '/api/')) {
    try {
        if (Settings::bool('maintenance_mode') || config('app.maintenance', false)) {
            http_response_code(503);
            header('Retry-After: 3600');
            View::render('errors/maintenance', ['title' => 'صيانة مؤقتة'], 'layouts/public');
            exit;
        }
    } catch (Throwable) {
        // تجاهل: قاعدة البيانات قد تكون غير مهيأة بعد
    }
}
