<?php
/**
 * معالج التثبيت — يجهّز المنصة على أي سيرفر (استضافة مشتركة أو VPS) بدون سطر أوامر.
 *
 * الخطوات:
 *   1) فحص المتطلبات
 *   2) إعدادات قاعدة البيانات (إنشاء القاعدة إن لم تكن موجودة)
 *   3) بيانات الموقع وحساب المدير
 *   4) كتابة .env + استيراد database.sql + إنشاء المدير + قفل المعالج
 *
 * بعد نجاح التثبيت: احذف هذا الملف من السيرفر (أو أبقِ القفل storage/installed.lock).
 */
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

use App\Database;
use App\DatabaseImporter;
use App\Env;
use App\EnvWriter;
use App\Logger;
use App\Security;
use App\Validator;

const INSTALL_LOCK = BASE_PATH . '/storage/installed.lock';
const LOCAL_ENV    = BASE_PATH . '/.env';

/* ====================================================================
 *  أدوات العرض (HTML عربي مستقل عن قوالب المنصة حتى يعمل قبل التثبيت)
 * ==================================================================== */

function inst_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function inst_flash(string $type, string $message): void
{
    $_SESSION['install_messages'][] = ['type' => $type, 'text' => $message];
}

/** @return array<int,array{type:string,text:string}> */
function inst_messages(): array
{
    $messages = $_SESSION['install_messages'] ?? [];
    unset($_SESSION['install_messages']);
    return is_array($messages) ? $messages : [];
}

function inst_state(): array
{
    $state = $_SESSION['install_state'] ?? [];
    return is_array($state) ? $state : [];
}

/** @param array<string,mixed> $values */
function inst_set_state(array $values): void
{
    $_SESSION['install_state'] = array_merge(inst_state(), $values);
}

function inst_redirect(int $step): never
{
    header('Location: install.php?step=' . $step);
    exit;
}

/** الرابط الأساسي المكتشف تلقائياً (يعمل في مجلد فرعي أيضاً) */
function inst_detected_url(): string
{
    $https = Security::isHttps();
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/install.php');
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return ($https ? 'https://' : 'http://') . $host . ($base === '/' ? '' : $base);
}

/** فحص المتطلبات @return array<int,array{label:string,ok:bool,note:string,critical:bool}> */
function inst_requirements(): array
{
    $uploadMax = (int) (ini_get('upload_max_filesize') ?: 0);
    $postMax = (int) (ini_get('post_max_size') ?: 0);
    $memory = (int) (ini_get('memory_limit') ?: 0);

    return [
        [
            'label'    => 'إصدار PHP 8.1 أو أحدث',
            'ok'       => PHP_VERSION_ID >= 80100,
            'note'     => 'الإصدار الحالي: ' . PHP_VERSION,
            'critical' => true,
        ],
        [
            'label'    => 'إضافة MySQL (pdo_mysql)',
            'ok'       => extension_loaded('pdo_mysql'),
            'note'     => 'مطلوبة للاتصال بقاعدة البيانات',
            'critical' => true,
        ],
        [
            'label'    => 'إضافة النصوص المتعددة (mbstring)',
            'ok'       => extension_loaded('mbstring'),
            'note'     => 'مطلوبة لدعم اللغة العربية',
            'critical' => true,
        ],
        [
            'label'    => 'إضافة JSON',
            'ok'       => extension_loaded('json'),
            'note'     => 'مطلوبة لتحليل الملفات وواجهات API',
            'critical' => true,
        ],
        [
            'label'    => 'إضافة OpenSSL',
            'ok'       => extension_loaded('openssl'),
            'note'     => 'مطلوبة لتشفير مفاتيح الدفع وإنشاء APP_KEY',
            'critical' => true,
        ],
        [
            'label'    => 'إضافة zlib (لاستخراج نصوص PDF)',
            'ok'       => extension_loaded('zlib'),
            'note'     => 'بدونها يمكن الاستيراد من TXT/CSV/JSON أو اللصق اليدوي فقط',
            'critical' => false,
        ],
        [
            'label'    => 'إضافة cURL (لتلجرام وبوابات الدفع)',
            'ok'       => extension_loaded('curl'),
            'note'     => 'بديلها file_get_contents لكن الأداء أقل',
            'critical' => false,
        ],
        [
            'label'    => 'إضافة fileinfo (للتحقق من الملفات المرفوعة)',
            'ok'       => extension_loaded('fileinfo'),
            'note'     => 'تُستخدم للتحقق من نوع الملفات المرفوعة',
            'critical' => false,
        ],
        [
            'label'    => 'إمكانية كتابة ملف .env',
            'ok'       => is_writable(BASE_PATH) || is_file(LOCAL_ENV),
            'note'     => BASE_PATH . ' — امنح المجلد صلاحية الكتابة مؤقتاً (755/775)',
            'critical' => true,
        ],
        [
            'label'    => 'إمكانية الكتابة في storage/ و uploads/',
            'ok'       => is_writable(BASE_PATH . '/storage') && is_writable(BASE_PATH . '/uploads'),
            'note'     => 'تُحفظ فيها السجلات والملفات المرفوعة',
            'critical' => true,
        ],
        [
            'label'    => 'رفع الملفات مفعّل',
            'ok'       => (bool) ini_get('file_uploads'),
            'note'     => 'مطلوب لرفع ملفات أسئلة PDF وإثباتات الدفع',
            'critical' => false,
        ],
        [
            'label'    => 'حد الرفع كافٍ (10MB أو أكثر)',
            'ok'       => $uploadMax >= 10 && $postMax >= 10,
            'note'     => 'upload_max_filesize=' . ini_get('upload_max_filesize') . ' • post_max_size=' . ini_get('post_max_size'),
            'critical' => false,
        ],
        [
            'label'    => 'حد الذاكرة كافٍ (128MB أو أكثر)',
            'ok'       => $memory === -1 || $memory >= 128,
            'note'     => 'memory_limit=' . ini_get('memory_limit'),
            'critical' => false,
        ],
    ];
}

/** فحص الاتصال بقاعدة البيانات مع خيار إنشاء القاعدة @return array{ok:bool,message:string,pdo:?PDO} */
function inst_connect(array $config, bool $createIfMissing): array
{
    $host = (string) $config['host'];
    $port = (int) $config['port'];
    $name = (string) $config['name'];
    $user = (string) $config['user'];
    $pass = (string) $config['pass'];

    if (!preg_match('/^[A-Za-z0-9_\-\.]+$/', $host)) {
        return ['ok' => false, 'message' => 'اسم الخادم غير صالح.', 'pdo' => null];
    }
    if (!preg_match('/^[A-Za-z0-9_\-]+$/', $name)) {
        return ['ok' => false, 'message' => 'اسم قاعدة البيانات يجب أن يحتوي حروفاً إنجليزية وأرقاماً و _ فقط.', 'pdo' => null];
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, $options);
    } catch (PDOException $error) {
        return ['ok' => false, 'message' => 'تعذّر الاتصال بخادم قاعدة البيانات: ' . $error->getMessage(), 'pdo' => null];
    }

    try {
        $pdo->exec("USE `{$name}`");
        return ['ok' => true, 'message' => 'تم الاتصال بقاعدة البيانات الموجودة.', 'pdo' => $pdo];
    } catch (PDOException) {
        if (!$createIfMissing) {
            return ['ok' => false, 'message' => "قاعدة البيانات «{$name}» غير موجودة، أو أن المستخدم لا يملك صلاحية الوصول إليها.", 'pdo' => null];
        }
    }

    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$name}`");
        return ['ok' => true, 'message' => 'تم إنشاء قاعدة البيانات والاتصال بها.', 'pdo' => $pdo];
    } catch (PDOException $error) {
        return [
            'ok'      => false,
            'message' => 'تعذّر إنشاء قاعدة البيانات: ' . $error->getMessage()
                . ' — أنشئ القاعدة يدوياً من لوحة الاستضافة (phpMyAdmin) ثم أدخل اسمها هنا.',
            'pdo'     => null,
        ];
    }
}

/* ====================================================================
 *  بدء التشغيل
 * ==================================================================== */

// إن كان الملف غير موجود أصلاً فهذا يعني أن المجلد غير مكتمل
if (!is_file(BASE_PATH . '/database.sql')) {
    http_response_code(500);
    exit('ملف database.sql غير موجود في مجلد المشروع. ارفع كل ملفات المشروع أولاً.');
}

$locked = is_file(INSTALL_LOCK);
$installData = $locked ? json_decode((string) file_get_contents(INSTALL_LOCK), true) : null;
$step = max(1, min(4, (int) ($_GET['step'] ?? 1)));
$state = inst_state();
$messages = inst_messages();

/* ====================================================================
 *  تنفيذ الخطوات (POST)
 * ==================================================================== */

if (!$locked && is_post()) {
    $action = (string) post('step', '');

    /* ---------- الخطوة 2: قاعدة البيانات ---------- */
    if ($action === '2') {
        $config = [
            'host' => trim((string) post('db_host', 'localhost')),
            'port' => (int) post('db_port', 3306),
            'name' => trim((string) post('db_name', '')),
            'user' => trim((string) post('db_user', '')),
            'pass' => (string) post('db_pass', ''),
        ];
        $create = (bool) post('create_database');

        if ($config['name'] === '' || $config['user'] === '') {
            inst_flash('danger', 'اسم قاعدة البيانات واسم المستخدم مطلوبان.');
            inst_redirect(2);
        }
        $result = inst_connect($config, $create);
        if (!$result['ok']) {
            inst_flash('danger', $result['message']);
            inst_set_state(['db' => $config]);
            inst_redirect(2);
        }
        inst_set_state(['db' => $config, 'db_ok' => true]);
        inst_flash('success', $result['message']);
        inst_redirect(3);
    }

    /* ---------- الخطوة 3: الموقع وحساب المدير ---------- */
    if ($action === '3') {
        $site = [
            'app_name'  => trim((string) post('app_name', 'منصة الرخصة المهنية')),
            'app_url'   => rtrim(trim((string) post('app_url', '')), '/'),
            'timezone'  => trim((string) post('timezone', 'Asia/Riyadh')),
            'price'     => (int) post('subscription_price', 100),
            'days'      => (int) post('subscription_days', 365),
        ];
        $admin = [
            'full_name' => trim((string) post('admin_name', '')),
            'email'     => strtolower(trim((string) post('admin_email', ''))),
            'phone'     => trim((string) post('admin_phone', '')),
            'password'  => (string) post('admin_password', ''),
            'password_confirmation' => (string) post('admin_password_confirmation', ''),
        ];
        $options = [
            'remove_demo_users'    => (bool) post('remove_demo_users'),
            'remove_demo_questions'=> (bool) post('remove_demo_questions'),
            'telegram_token'       => trim((string) post('telegram_token', '')),
            'telegram_username'    => ltrim(trim((string) post('telegram_username', '')), '@'),
        ];

        $validator = Validator::make($admin, [
            'full_name' => 'required|min:3|max:120',
            'email'     => 'required|email|max:190',
            'phone'     => 'required|phone_sa',
            'password'  => 'required|password|confirmed',
        ], [
            'full_name' => 'اسم المدير',
            'email'     => 'البريد الإلكتروني',
            'phone'     => 'رقم الجوال',
            'password'  => 'كلمة المرور',
            'password_confirmation' => 'تأكيد كلمة المرور',
        ]);

        if ($validator->fails()) {
            inst_flash('danger', implode(' ', $validator->errors()));
            inst_set_state(['site' => $site, 'admin' => ['full_name' => $admin['full_name'], 'email' => $admin['email'], 'phone' => $admin['phone']], 'options' => $options]);
            inst_redirect(3);
        }

        if (!filter_var($site['app_url'], FILTER_VALIDATE_URL)) {
            inst_flash('danger', 'رابط الموقع غير صالح.');
            inst_redirect(3);
        }

        inst_set_state([
            'site'    => $site,
            'admin'   => $admin,
            'options' => $options,
        ]);
        inst_redirect(4);
    }
}

/* ====================================================================
 *  الخطوة 4: التنفيذ الفعلي
 * ==================================================================== */

$results = [];
$fatal = '';

if (!$locked && $step === 4 && $state !== [] && isset($state['db'], $state['site'], $state['admin'])) {
    $db = $state['db'];
    $site = $state['site'];
    $admin = $state['admin'];
    $options = $state['options'] ?? [];
    $baseUrl = $site['app_url'] !== '' ? $site['app_url'] : inst_detected_url();

    try {
        // 1) كتابة ملف .env
        $envValues = [
            'APP_NAME'         => $site['app_name'],
            'APP_ENV'          => 'production',
            'APP_DEBUG'        => 'false',
            'APP_URL'          => $baseUrl,
            'APP_TIMEZONE'     => $site['timezone'],
            'APP_KEY'          => bin2hex(random_bytes(32)),
            'APP_PRETTY_URLS'  => 'true',
            'APP_MAINTENANCE'  => 'false',
            'DB_HOST'          => $db['host'],
            'DB_PORT'          => (string) $db['port'],
            'DB_DATABASE'      => $db['name'],
            'DB_USERNAME'      => $db['user'],
            'DB_PASSWORD'      => (string) $db['pass'],
            'DB_CHARSET'       => 'utf8mb4',
            'SESSION_SECURE'   => Security::isHttps() ? 'true' : 'false',
            'SUBSCRIPTION_PRICE' => (string) $site['price'],
            'SUBSCRIPTION_DAYS'  => (string) $site['days'],
            'TELEGRAM_BOT_TOKEN'    => (string) $options['telegram_token'],
            'TELEGRAM_BOT_USERNAME' => (string) $options['telegram_username'],
        ];
        $template = is_file(BASE_PATH . '/.env.example') ? BASE_PATH . '/.env.example' : null;
        if (!EnvWriter::write(LOCAL_ENV, $envValues, $template)) {
            throw new RuntimeException('تعذّرت كتابة ملف .env — تأكد من صلاحيات الكتابة على مجلد المشروع.');
        }
        foreach ($envValues as $key => $value) {
            Env::set((string) $key, (string) $value);
        }
        @chmod(LOCAL_ENV, 0640);
        $results[] = ['ok' => true, 'text' => 'تم إنشاء ملف الإعدادات .env وتوليد مفتاح التشفير APP_KEY.'];

        // 2) الاتصال بقاعدة البيانات عبر طبقة المنصة نفسها
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], (int) $db['port'], $db['name']),
            (string) $db['user'],
            (string) $db['pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        Database::reset();
        Database::setConnectionFactory(static fn(): PDO => $pdo);
        $results[] = ['ok' => true, 'text' => 'تم الاتصال بقاعدة البيانات: ' . $db['name'] . '.'];

        // 3) استيراد المخطط والبيانات الأولية
        $sql = (string) file_get_contents(BASE_PATH . '/database.sql');
        $import = DatabaseImporter::run($pdo, $sql);
        if (!$import['ok']) {
            throw new RuntimeException(
                'فشل استيراد database.sql بعد ' . $import['executed'] . ' جملة. الخطأ: ' . $import['error']
                . ' — الجملة: ' . $import['failed_statement']
            );
        }
        $results[] = ['ok' => true, 'text' => 'تم استيراد قاعدة البيانات (' . $import['executed'] . ' جملة).'];

        // 4) التحقق من الجداول
        $verify = DatabaseImporter::verifySchema($pdo, (string) $db['name'], DatabaseImporter::expectedTables());
        if (!$verify['ok']) {
            throw new RuntimeException('جداول مفقودة بعد الاستيراد: ' . implode('، ', $verify['missing']));
        }
        $results[] = ['ok' => true, 'text' => 'تم التحقق من ' . count($verify['present']) . ' جدولاً في قاعدة البيانات.'];

        // 5) حساب المدير
        $existingAdmin = db()->one('SELECT id FROM `users` WHERE email = :email', ['email' => $admin['email']]);
        $adminData = [
            'full_name'     => $admin['full_name'],
            'phone'         => $admin['phone'],
            'email'         => $admin['email'],
            'password_hash' => Security::hashPassword((string) $admin['password']),
            'role'          => 'admin',
            'status'        => 'active',
        ];
        if ($existingAdmin !== null) {
            db()->update('users', $adminData, 'id = :id', ['id' => (int) $existingAdmin['id']]);
            $adminId = (int) $existingAdmin['id'];
            $results[] = ['ok' => true, 'text' => 'تم تحديث حساب المدير الموجود بنفس البريد.'];
        } else {
            $adminId = db()->insert('users', $adminData);
            $results[] = ['ok' => true, 'text' => 'تم إنشاء حساب المدير.'];

            // حذف حساب المدير الافتراضي القادم من ملف البيانات (نفس البريد admin@example.com)
            if ($admin['email'] !== 'admin@example.com') {
                db()->delete('users', "email = 'admin@example.com'");
            }
        }

        // 6) خيارات ما بعد التثبيت
        if (!empty($options['remove_demo_users'])) {
            db()->delete('users', "email IN ('student@example.com', 'admin@example.com') AND id <> :id", ['id' => $adminId]);
            $results[] = ['ok' => true, 'text' => 'تم حذف حسابات العرض التجريبية.'];
        }
        if (!empty($options['remove_demo_questions'])) {
            // الأسئلة القادمة مع ملف البيانات موسومة بـ source_note = 'بيانات تجريبية'
            $deleted = db()->query("DELETE FROM `questions` WHERE `source_note` = :note", ['note' => 'بيانات تجريبية'])->rowCount();
            $results[] = ['ok' => true, 'text' => 'تم حذف الأسئلة التجريبية (' . (int) $deleted . ' سؤالاً).'];
        }

        // 7) إعدادات الموقع والاشتراك
        $settingsMap = [
            'site_name'          => $site['app_name'],
            'subscription_price' => (string) $site['price'],
            'subscription_days'  => (string) $site['days'],
        ];
        foreach ($settingsMap as $key => $value) {
            $exists = db()->value('SELECT COUNT(*) FROM `settings` WHERE setting_key = :key', ['key' => $key]);
            if ((int) $exists > 0) {
                db()->update('settings', ['setting_value' => $value], 'setting_key = :key', ['key' => $key]);
            } else {
                db()->insert('settings', ['setting_key' => $key, 'setting_value' => $value, 'setting_group' => 'subscription']);
            }
        }
        $results[] = ['ok' => true, 'text' => 'تم ضبط اسم الموقع وسعر الاشتراك (' . $site['price'] . ' ريال) ومدة الاشتراك (' . $site['days'] . ' يوماً).'];

        // 8) سجل التدقيق + قفل المعالج
        audit('install.completed', 'settings', null, ['admin' => $admin['email'], 'url' => $baseUrl]);

        $lockData = [
            'installed_at' => date('c'),
            'version'      => '1.0.0',
            'app_url'      => $baseUrl,
            'admin_email'  => $admin['email'],
            'database'     => (string) $db['name'],
            'php'          => PHP_VERSION,
        ];
        if (file_put_contents(INSTALL_LOCK, json_encode($lockData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) {
            throw new RuntimeException('تعذّر إنشاء ملف القفل storage/installed.lock — أنشئه يدوياً لمنع إعادة تشغيل المعالج.');
        }
        $results[] = ['ok' => true, 'text' => 'تم قفل المعالج (storage/installed.lock).'];

        unset($_SESSION['install_state']);
        $_SESSION['install_done'] = true;
        Logger::info('تم تثبيت المنصة بنجاح', ['admin' => $admin['email'], 'db' => $db['name']]);
    } catch (Throwable $error) {
        $fatal = $error->getMessage();
        Logger::error('فشل التثبيت: ' . $error->getMessage());
    }
}

$requirements = inst_requirements();
$criticalOk = !in_array(false, array_map(static fn(array $row): bool => $row['critical'] ? $row['ok'] : true, $requirements), true);
$dbState = $state['db'] ?? ['host' => 'localhost', 'port' => 3306, 'name' => 'professional_license', 'user' => '', 'pass' => ''];
$siteState = $state['site'] ?? ['app_name' => 'منصة الرخصة المهنية', 'app_url' => inst_detected_url(), 'timezone' => 'Asia/Riyadh', 'price' => 100, 'days' => 365];
$adminState = $state['admin'] ?? ['full_name' => '', 'email' => '', 'phone' => ''];
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تثبيت منصة الرخصة المهنية</title>
    <style>
        :root { --green:#0b6b3a; --green-soft:#e7f4ec; --gold:#c8a24a; --ink:#16221b; --muted:#6b7a6f; --line:#dde5df; }
        * { box-sizing: border-box; }
        body { margin:0; font-family:"Segoe UI",Tahoma,"Noto Naskh Arabic",sans-serif; background:#f4f7f5; color:var(--ink); line-height:1.7; }
        .wrap { max-width:860px; margin:0 auto; padding:32px 16px 64px; }
        header { text-align:center; margin-bottom:24px; }
        header .logo { width:64px; height:64px; border-radius:18px; background:var(--green); color:#fff; display:flex; align-items:center; justify-content:center; font-size:30px; margin:0 auto 12px; }
        h1 { font-size:1.5rem; margin:0 0 6px; }
        header p { color:var(--muted); margin:0; font-size:.95rem; }
        .steps { display:flex; gap:8px; margin:24px 0; flex-wrap:wrap; }
        .steps div { flex:1 1 140px; background:#fff; border:1px solid var(--line); border-radius:12px; padding:10px 12px; font-size:.85rem; color:var(--muted); }
        .steps div.active { border-color:var(--green); background:var(--green-soft); color:var(--green); font-weight:700; }
        .steps div.done { border-color:var(--green); color:var(--green); }
        .card { background:#fff; border:1px solid var(--line); border-radius:16px; padding:22px; margin-bottom:18px; box-shadow:0 1px 2px rgba(0,0,0,.03); }
        .card h2 { font-size:1.1rem; margin:0 0 14px; }
        label { display:block; font-weight:600; font-size:.9rem; margin-bottom:6px; }
        input[type=text], input[type=password], input[type=number], input[type=email], select {
            width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px; font-size:.95rem; font-family:inherit; background:#fff;
        }
        input:focus, select:focus { outline:2px solid rgba(11,107,58,.25); border-color:var(--green); }
        .grid { display:grid; gap:14px; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); }
        .hint { color:var(--muted); font-size:.82rem; margin-top:5px; }
        .btn { display:inline-block; background:var(--green); color:#fff; border:0; border-radius:10px; padding:12px 22px; font-size:1rem; font-family:inherit; font-weight:700; cursor:pointer; text-decoration:none; }
        .btn.secondary { background:#fff; color:var(--green); border:1px solid var(--green); }
        .btn:disabled { opacity:.5; cursor:not-allowed; }
        .list { list-style:none; margin:0; padding:0; }
        .list li { display:flex; justify-content:space-between; gap:12px; padding:9px 0; border-bottom:1px dashed var(--line); font-size:.92rem; }
        .list li:last-child { border-bottom:0; }
        .ok { color:#12873f; font-weight:700; }
        .bad { color:#c0392b; font-weight:700; }
        .warn { color:#a5801c; font-weight:700; }
        .alert { border-radius:12px; padding:12px 14px; margin-bottom:14px; font-size:.92rem; border:1px solid; }
        .alert.success { background:#e9f8ee; border-color:#b9e3c8; color:#12592f; }
        .alert.danger { background:#fdeceb; border-color:#f3c4c0; color:#8f2a20; }
        .alert.info { background:#eef4fb; border-color:#c9dcf2; color:#1d4e82; }
        .checks { display:flex; gap:12px; align-items:flex-start; font-weight:500; }
        .checks input { margin-top:6px; }
        code, .mono { background:#f1f5f2; border-radius:6px; padding:2px 6px; font-size:.85rem; direction:ltr; display:inline-block; }
        .footer { text-align:center; color:var(--muted); font-size:.82rem; margin-top:22px; }
        .kv { display:grid; grid-template-columns:auto 1fr; gap:6px 14px; font-size:.9rem; }
        .kv b { color:var(--muted); font-weight:600; }
        .next ol { padding-inline-start:20px; }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <div class="logo">🎓</div>
        <h1>منصة الرخصة المهنية للمعلمين</h1>
        <p>معالج التثبيت — يعمل على XAMPP والاستضافة المشتركة والسيرفرات الخاصة</p>
    </header>

    <?php if ($locked): ?>
        <div class="card">
            <h2 class="ok">✔ المنصة مثبّتة بالفعل</h2>
            <?php if (is_array($installData)): ?>
                <div class="kv">
                    <b>تاريخ التثبيت:</b><span><?= inst_e((string) ($installData['installed_at'] ?? '—')) ?></span>
                    <b>الرابط:</b><span class="mono"><?= inst_e((string) ($installData['app_url'] ?? '—')) ?></span>
                    <b>قاعدة البيانات:</b><span class="mono"><?= inst_e((string) ($installData['database'] ?? '—')) ?></span>
                    <b>بريد المدير:</b><span class="mono"><?= inst_e((string) ($installData['admin_email'] ?? '—')) ?></span>
                </div>
            <?php endif; ?>
            <div class="alert info" style="margin-top:16px;">
                لأسباب أمنية لا يمكن إعادة تشغيل المعالج وهو مقفول. إن أردت إعادة التثبيت من الصفر:
                احذف الملف <code>storage/installed.lock</code> ثم أعد فتح هذه الصفحة.
            </div>
            <a class="btn" href="<?= inst_e(rtrim((string) ($installData['app_url'] ?? ''), '/') . '/admin/index.php') ?>">الذهاب إلى لوحة الإدارة</a>
            <a class="btn secondary" href="<?= inst_e(rtrim((string) ($installData['app_url'] ?? ''), '/') . '/auth/login.php') ?>">صفحة تسجيل الدخول</a>
            <div class="alert danger" style="margin-top:16px;">
                بعد التأكد من عمل المنصة احذف ملف <code>install.php</code> من السيرفر نهائياً.
            </div>
        </div>
    <?php else: ?>

    <div class="steps">
        <?php foreach ([1 => 'فحص المتطلبات', 2 => 'قاعدة البيانات', 3 => 'الموقع والمدير', 4 => 'التنفيذ'] as $number => $label): ?>
            <div class="<?= $step === $number ? 'active' : ($step > $number ? 'done' : '') ?>">
                <?= $step > $number ? '✔ ' : $number . '. ' ?><?= inst_e($label) ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php foreach ($messages as $message): ?>
        <div class="alert <?= inst_e($message['type']) ?>"><?= inst_e($message['text']) ?></div>
    <?php endforeach; ?>

    <?php if ($fatal !== ''): ?>
        <div class="alert danger">
            <strong>تعذّر إكمال التثبيت:</strong><br><?= inst_e($fatal) ?>
            <div class="hint">
                يمكنك تصحيح المشكلة ثم إعادة المحاولة من نفس الصفحة (لم يُستورد أي جزء من البيانات بشكل نهائي إن كان الخطأ قبل الاستيراد).
            </div>
        </div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
        <div class="card">
            <h2>1. فحص متطلبات السيرفر</h2>
            <ul class="list">
                <?php foreach ($requirements as $requirement): ?>
                    <li>
                        <span>
                            <?= inst_e($requirement['label']) ?>
                            <div class="hint"><?= inst_e($requirement['note']) ?></div>
                        </span>
                        <span class="<?= $requirement['ok'] ? 'ok' : ($requirement['critical'] ? 'bad' : 'warn') ?>">
                            <?= $requirement['ok'] ? '✔ متوفر' : ($requirement['critical'] ? '✘ مطلوب' : '⚠ مستحسن') ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (!$criticalOk): ?>
                <div class="alert danger" style="margin-top:14px;">
                    لا يمكن المتابعة: عالج العناصر المعلّمة بـ «مطلوب» أولاً، وغالباً يتم ذلك من لوحة الاستضافة
                    (اختيار إصدار PHP وتفعيل الإضافات) أو بتعديل صلاحيات المجلدات إلى 755.
                </div>
            <?php endif; ?>
            <a class="btn <?= $criticalOk ? '' : 'disabled' ?>" style="margin-top:16px;" href="<?= $criticalOk ? 'install.php?step=2' : '#' ?>">التالي: إعدادات قاعدة البيانات</a>
        </div>

    <?php elseif ($step === 2): ?>
        <div class="card">
            <h2>2. إعدادات قاعدة البيانات (MySQL / MariaDB)</h2>
            <div class="alert info">
                أنشئ قاعدة بيانات فارغة من لوحة الاستضافة إن أمكن (مثلاً <span class="mono">yourname_license</span>)،
                ثم أدخل بياناتها هنا. وإن مُنح المستخدم صلاحية الإنشاء، سينشئها المعالج تلقائياً بترميز utf8mb4.
            </div>
            <form method="post" action="install.php">
                <?= csrf_field() ?>
                <input type="hidden" name="step" value="2">
                <div class="grid">
                    <div>
                        <label for="db_host">خادم قاعدة البيانات</label>
                        <input type="text" id="db_host" name="db_host" value="<?= inst_e((string) $dbState['host']) ?>" required dir="ltr">
                        <div class="hint">عادةً <span class="mono">localhost</span> على XAMPP والاستضافة المشتركة</div>
                    </div>
                    <div>
                        <label for="db_port">المنفذ</label>
                        <input type="number" id="db_port" name="db_port" value="<?= (int) $dbState['port'] ?>" required dir="ltr">
                    </div>
                    <div>
                        <label for="db_name">اسم قاعدة البيانات</label>
                        <input type="text" id="db_name" name="db_name" value="<?= inst_e((string) $dbState['name']) ?>" required dir="ltr">
                    </div>
                    <div>
                        <label for="db_user">مستخدم قاعدة البيانات</label>
                        <input type="text" id="db_user" name="db_user" value="<?= inst_e((string) $dbState['user']) ?>" required dir="ltr" autocomplete="off">
                    </div>
                    <div>
                        <label for="db_pass">كلمة مرور المستخدم</label>
                        <input type="password" id="db_pass" name="db_pass" value="<?= inst_e((string) $dbState['pass']) ?>" dir="ltr" autocomplete="new-password">
                        <div class="hint">تُحفظ في ملف .env فقط ولا تُخزَّن في قاعدة البيانات</div>
                    </div>
                </div>
                <div class="checks" style="margin-top:14px;">
                    <input type="checkbox" id="create_database" name="create_database" value="1" checked>
                    <label for="create_database" style="font-weight:500; margin:0;">
                        أنشئ قاعدة البيانات تلقائياً إن لم تكن موجودة
                    </label>
                </div>
                <button class="btn" style="margin-top:16px;">فحص الاتصال والمتابعة</button>
            </form>
        </div>

    <?php elseif ($step === 3): ?>
        <div class="card">
            <h2>3. بيانات الموقع وحساب المدير</h2>
            <form method="post" action="install.php">
                <?= csrf_field() ?>
                <input type="hidden" name="step" value="3">
                <div class="grid">
                    <div>
                        <label for="app_name">اسم المنصة (يظهر في الواجهة)</label>
                        <input type="text" id="app_name" name="app_name" value="<?= inst_e((string) $siteState['app_name']) ?>" required>
                    </div>
                    <div>
                        <label for="app_url">رابط الموقع النهائي</label>
                        <input type="text" id="app_url" name="app_url" value="<?= inst_e((string) $siteState['app_url']) ?>" required dir="ltr">
                        <div class="hint">بدون شرطة في النهاية — مثال: <span class="mono">https://example.com</span></div>
                    </div>
                    <div>
                        <label for="timezone">المنطقة الزمنية</label>
                        <select id="timezone" name="timezone">
                            <?php foreach (['Asia/Riyadh' => 'الرياض (السعودية)', 'Asia/Dubai' => 'دبي', 'Asia/Kuwait' => 'الكويت', 'Africa/Cairo' => 'القاهرة', 'UTC' => 'UTC'] as $zone => $label): ?>
                                <option value="<?= inst_e($zone) ?>" <?= $siteState['timezone'] === $zone ? 'selected' : '' ?>><?= inst_e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="subscription_price">سعر الاشتراك (ريال سعودي)</label>
                        <input type="number" id="subscription_price" name="subscription_price" value="<?= (int) $siteState['price'] ?>" min="0" step="1">
                    </div>
                    <div>
                        <label for="subscription_days">مدة الاشتراك (أيام)</label>
                        <input type="number" id="subscription_days" name="subscription_days" value="<?= (int) $siteState['days'] ?>" min="1" step="1">
                    </div>
                </div>

                <hr style="border:0; border-top:1px dashed var(--line); margin:22px 0;">
                <h2>حساب المدير (التحكم الكامل)</h2>
                <div class="grid">
                    <div>
                        <label for="admin_name">الاسم الكامل</label>
                        <input type="text" id="admin_name" name="admin_name" value="<?= inst_e((string) $adminState['full_name']) ?>" required>
                    </div>
                    <div>
                        <label for="admin_email">البريد الإلكتروني (لتسجيل الدخول)</label>
                        <input type="text" id="admin_email" name="admin_email" value="<?= inst_e((string) $adminState['email']) ?>" required dir="ltr">
                    </div>
                    <div>
                        <label for="admin_phone">رقم الجوال</label>
                        <input type="text" id="admin_phone" name="admin_phone" value="<?= inst_e((string) $adminState['phone']) ?>" required dir="ltr" placeholder="05XXXXXXXX">
                    </div>
                    <div>
                        <label for="admin_password">كلمة المرور</label>
                        <input type="password" id="admin_password" name="admin_password" required dir="ltr" autocomplete="new-password">
                        <div class="hint">8 أحرف على الأقل وتحتوي حرفاً ورقماً</div>
                    </div>
                    <div>
                        <label for="admin_password_confirmation">تأكيد كلمة المرور</label>
                        <input type="password" id="admin_password_confirmation" name="admin_password_confirmation" required dir="ltr" autocomplete="new-password">
                    </div>
                </div>

                <hr style="border:0; border-top:1px dashed var(--line); margin:22px 0;">
                <h2>خيارات إضافية</h2>
                <div class="checks">
                    <input type="checkbox" id="remove_demo_users" name="remove_demo_users" value="1" checked>
                    <label for="remove_demo_users" style="font-weight:500; margin:0;">
                        حذف حسابات العرض التجريبية (admin@example.com و student@example.com)
                        <div class="hint">يُنصح بها بشدة على السيرفر الحقيقي لتجنّب كلمات مرور معروفة.</div>
                    </label>
                </div>
                <div class="checks" style="margin-top:12px;">
                    <input type="checkbox" id="remove_demo_questions" name="remove_demo_questions" value="1">
                    <label for="remove_demo_questions" style="font-weight:500; margin:0;">
                        حذف الأسئلة التجريبية الخمسين القادمة مع ملف البيانات
                        <div class="hint">اتركها إن أردت تجربة المنصة أولاً، ويمكن حذفها لاحقاً من «بنك الأسئلة».</div>
                    </label>
                </div>
                <div class="grid" style="margin-top:16px;">
                    <div>
                        <label for="telegram_token">توكن بوت تلجرام (اختياري)</label>
                        <input type="text" id="telegram_token" name="telegram_token" dir="ltr" placeholder="123456789:AA...">
                        <div class="hint">من @BotFather — يُحفظ في .env فقط ولا يُعرض في أي شاشة.</div>
                    </div>
                    <div>
                        <label for="telegram_username">اسم مستخدم البوت (اختياري)</label>
                        <input type="text" id="telegram_username" name="telegram_username" dir="ltr" placeholder="MyLicenseBot">
                    </div>
                </div>

                <div class="alert info" style="margin-top:18px;">
                    سيتم الآن: كتابة <span class="mono">.env</span> ← استيراد <span class="mono">database.sql</span>
                    (سيُعاد إنشاء الجداول التي تحمل نفس الأسماء) ← إنشاء حساب المدير ← قفل المعالج.
                </div>
                <button class="btn">بدء التثبيت الآن</button>
            </form>
        </div>

    <?php elseif ($step === 4): ?>
        <div class="card">
            <h2>4. نتيجة التثبيت</h2>
            <?php if ($fatal === '' && $results !== []): ?>
                <div class="alert success">تم تثبيت المنصة بنجاح ✔</div>
                <ul class="list">
                    <?php foreach ($results as $row): ?>
                        <li><span><?= inst_e($row['text']) ?></span><span class="ok">✔</span></li>
                    <?php endforeach; ?>
                </ul>
                <div class="next" style="margin-top:18px;">
                    <h2>الخطوات التالية</h2>
                    <ol>
                        <li><strong>احذف ملف <code>install.php</code></strong> من السيرفر الآن (أهم خطوة أمنية).</li>
                        <li>تأكد من أن صلاحيات <code>storage/</code> و <code>uploads/</code> = 755 أو 775، وأن <code>.env</code> = 600/640.</li>
                        <li>سجّل الدخول بحساب المدير ثم غيّر كلمة المرور من «ملفي الشخصي» إن رغبت.</li>
                        <li>لتفعيل بوت تلجرام: لوحة الإدارة ← تلجرام ← «تسجيل الويبهوك»، أو من SSH: <code>php telegram/set-webhook.php</code>.</li>
                        <li>أضف مهمة مجدولة (Cron) كل ساعة: <code>php <?= inst_e(BASE_PATH) ?>/tools/cron.php</code>
                            لإنهاء الاشتراكات المنتهية وإنهاء المحاولات المتأخرة وإرسال تنبيهات القرب من الانتهاء.</li>
                        <li>لمراجعة دليل النشر الكامل: <code>docs/التثبيت-على-سيرفر.md</code></li>
                    </ol>
                </div>
                <a class="btn" href="<?= inst_e((string) ($siteState['app_url'] !== '' ? $siteState['app_url'] : inst_detected_url())) ?>/auth/login.php">تسجيل الدخول إلى المنصة</a>
            <?php else: ?>
                <div class="alert danger">
                    توقف التثبيت قبل الاكتمال. راجع الرسالة أعلاه، صحّح السبب، ثم أعد المحاولة من الخطوة 2.
                </div>
                <?php if ($results !== []): ?>
                    <ul class="list">
                        <?php foreach ($results as $row): ?>
                            <li><span><?= inst_e($row['text']) ?></span><span class="ok">✔</span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <a class="btn secondary" href="install.php?step=2">إعادة المحاولة</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php endif; ?>

    <div class="footer">
        منصة الرخصة المهنية — معالج التثبيت • PHP <?= inst_e(PHP_VERSION) ?> •
        <a href="docs/التثبيت-على-سيرفر.md" style="color:inherit;">دليل النشر</a>
    </div>
</div>
</body>
</html>
