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
$isCommandLine = in_array(PHP_SAPI, ['cli', 'phpdbg', 'wasm'], true);

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
//  5) الجلسة الآمنة
// ---------------------------------------------------------------------
if (session_status() !== PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli'
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
}

// ---------------------------------------------------------------------
//  6) رؤوس الحماية
// ---------------------------------------------------------------------
Security::sendSecurityHeaders();

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
