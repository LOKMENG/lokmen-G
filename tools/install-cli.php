<?php
/**
 * تثبيت المنصة من سطر الأوامر (للسيرفرات الخاصة / VPS بدون واجهة ويب).
 *
 * طريقة استخدام تفاعلية:
 *      php tools/install-cli.php
 *
 * بطريقة غير تفاعلية (مناسب للنشر التلقائي):
 *      php tools/install-cli.php --db-host=localhost --db-name=license --db-user=root --db-pass=secret \
 *           --url=https://example.com --admin-name="محمد" --admin-email=me@example.com \
 *           --admin-phone=0551234567 --admin-pass="StrongPass1" --yes
 *
 * خيارات إضافية: --price=100 --days=365 --create-db --reset (يعيد التثبيت بحذف القفل)
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

if (!is_cli()) {
    http_response_code(403);
    exit('هذا الملف يعمل من سطر الأوامر فقط.');
}

use App\Database;
use App\DatabaseImporter;
use App\EnvWriter;
use App\Logger;
use App\Security;
use App\Env;

const CLI_LOCK = BASE_PATH . '/storage/installed.lock';
const CLI_ENV  = BASE_PATH . '/.env';

function out(string $line = ''): void
{
    fwrite(STDOUT, $line . "\n");
}

function ask(string $label, string $default = ''): string
{
    $suffix = $default !== '' ? ' [' . $default . ']' : '';
    fwrite(STDOUT, $label . $suffix . ': ');
    $answer = fgets(STDIN);
    if ($answer === false) {
        return $default;
    }
    $answer = trim($answer);
    return $answer === '' ? $default : $answer;
}

function fail(string $message): never
{
    fwrite(STDERR, "\n✘ " . $message . "\n");
    exit(1);
}

/* ---------------- قراءة الخيارات ---------------- */

$options = [
    'db-host' => cli_option('db-host', 'localhost'),
    'db-port' => (int) cli_option('db-port', '3306'),
    'db-name' => cli_option('db-name', ''),
    'db-user' => cli_option('db-user', ''),
    'db-pass' => cli_option('db-pass', ''),
    'url'     => rtrim(cli_option('url', ''), '/'),
    'name'    => cli_option('name', 'منصة الرخصة المهنية'),
    'admin-name'  => cli_option('admin-name', ''),
    'admin-email' => strtolower(cli_option('admin-email', '')),
    'admin-phone' => cli_option('admin-phone', ''),
    'admin-pass'  => cli_option('admin-pass', ''),
    'price'   => (int) cli_option('price', '100'),
    'days'    => (int) cli_option('days', '365'),
];
$assumeYes = cli_has_flag('yes');
$createDb = cli_has_flag('create-db');
$reset = cli_has_flag('reset');

out('');
out('  ╔══════════════════════════════════════════════════════════╗');
out('  ║   تثبيت منصة الرخصة المهنية — نسخة سطر الأوامر (CLI)      ║');
out('  ╚══════════════════════════════════════════════════════════╝');

if (is_file(CLI_LOCK) && !$reset) {
    $lock = json_decode((string) file_get_contents(CLI_LOCK), true);
    out('');
    out('  المنصة مثبّتة بالفعل (storage/installed.lock موجود).');
    if (is_array($lock)) {
        out('  تاريخ التثبيت: ' . ($lock['installed_at'] ?? '—'));
        out('  الرابط: ' . ($lock['app_url'] ?? '—'));
    }
    out('  لإعادة التثبيت أضف الخيار --reset (سيُعاد إنشاء الجداول ببياناتها الأولية).');
    exit(0);
}

if (!extension_loaded('pdo_mysql')) {
    fail('إضافة pdo_mysql غير مفعّلة — فعّلها من php.ini (extension=pdo_mysql) ثم أعد المحاولة.');
}
if (!is_file(BASE_PATH . '/database.sql')) {
    fail('ملف database.sql غير موجود في جذر المشروع.');
}
foreach (['storage', 'storage/logs', 'storage/cache', 'uploads'] as $dir) {
    $path = BASE_PATH . '/' . $dir;
    if (!is_dir($path) && !mkdir($path, 0755, true)) {
        fail('تعذّر إنشاء المجلد: ' . $dir);
    }
    if (!is_writable($path)) {
        fail('المجلد غير قابل للكتابة: ' . $dir . ' — نفّذ: chmod -R 775 ' . $path);
    }
}

/* ---------------- جمع المدخلات ---------------- */

$interactive = !$assumeYes && ($options['db-name'] === '' || $options['admin-email'] === '');
if ($interactive) {
    out('');
    out('  اترك الحقل فارغاً لقبول القيمة الافتراضية بين المعقوفتين.');
    out('');
    out('  ── قاعدة البيانات ──');
    $options['db-host'] = ask('  خادم قاعدة البيانات', $options['db-host']);
    $options['db-port'] = (int) ask('  المنفذ', (string) $options['db-port']);
    $options['db-name'] = ask('  اسم قاعدة البيانات', $options['db-name'] !== '' ? $options['db-name'] : 'professional_license');
    $options['db-user'] = ask('  مستخدم قاعدة البيانات', $options['db-user'] !== '' ? $options['db-user'] : 'root');
    $options['db-pass'] = ask('  كلمة مرور قاعدة البيانات', $options['db-pass']);
    $createDb = $createDb || strtolower(ask('  إنشاء القاعدة تلقائياً إن لم توجد؟ (y/n)', 'y')) === 'y';

    out('');
    out('  ── الموقع ──');
    $options['name'] = ask('  اسم المنصة', $options['name']);
    $options['url'] = rtrim(ask('  رابط الموقع النهائي (مثال: https://example.com)', $options['url']), '/');
    $options['price'] = (int) ask('  سعر الاشتراك بالريال', (string) $options['price']);
    $options['days'] = (int) ask('  مدة الاشتراك بالأيام', (string) $options['days']);

    out('');
    out('  ── حساب المدير ──');
    $options['admin-name'] = ask('  الاسم الكامل', $options['admin-name']);
    $options['admin-email'] = strtolower(ask('  البريد الإلكتروني', $options['admin-email']));
    $options['admin-phone'] = ask('  رقم الجوال (05XXXXXXXX)', $options['admin-phone']);
    $options['admin-pass'] = ask('  كلمة المرور (8 أحرف على الأقل، بها حرف ورقم)', $options['admin-pass']);
}

foreach (['db-name' => 'اسم قاعدة البيانات', 'db-user' => 'مستخدم قاعدة البيانات', 'url' => 'رابط الموقع',
          'admin-name' => 'اسم المدير', 'admin-email' => 'بريد المدير', 'admin-phone' => 'جوال المدير',
          'admin-pass' => 'كلمة مرور المدير'] as $key => $label) {
    if (trim((string) $options[$key]) === '') {
        fail('الحقل مطلوب: ' . $label . ' (يمكن تمريره كخيار مثل --' . $key . '=القيمة)');
    }
}
if (!filter_var($options['url'], FILTER_VALIDATE_URL)) {
    fail('رابط الموقع غير صالح: ' . $options['url']);
}
if (!filter_var($options['admin-email'], FILTER_VALIDATE_EMAIL)) {
    fail('بريد المدير غير صالح: ' . $options['admin-email']);
}
if (mb_strlen($options['admin-pass']) < 8 || preg_match('/[A-Za-z]/', $options['admin-pass']) !== 1 || preg_match('/\d/', $options['admin-pass']) !== 1) {
    fail('كلمة مرور المدير يجب أن تكون 8 أحرف على الأقل وتحتوي حرفاً ورقماً.');
}
if (preg_match('/^[A-Za-z0-9_\-]+$/', $options['db-name']) !== 1) {
    fail('اسم قاعدة البيانات يجب أن يحتوي حروفاً إنجليزية وأرقاماً و _ فقط.');
}

if (!$assumeYes) {
    out('');
    out('  ── ملخص ──');
    out('  قاعدة البيانات: ' . $options['db-user'] . '@' . $options['db-host'] . ':' . $options['db-port'] . '/' . $options['db-name']);
    out('  الرابط: ' . $options['url']);
    out('  المدير: ' . $options['admin-name'] . ' <' . $options['admin-email'] . '>');
    out('  سيعاد إنشاء جداول database.sql (تُحذف الجداول التي تحمل نفس الأسماء).');
    if (strtolower(ask('  متابعة التثبيت؟ (y/n)', 'n')) !== 'y') {
        out('  تم الإلغاء.');
        exit(0);
    }
}

/* ---------------- الاتصال بقاعدة البيانات ---------------- */

out('');
$dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $options['db-host'], $options['db-port']);
try {
    $pdo = new PDO($dsn, (string) $options['db-user'], (string) $options['db-pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    out('  ✔ تم الاتصال بخادم قاعدة البيانات.');
} catch (PDOException $error) {
    fail('تعذّر الاتصال بقاعدة البيانات: ' . $error->getMessage());
}

try {
    $pdo->exec("USE `{$options['db-name']}`");
    out('  ✔ قاعدة البيانات موجودة.');
} catch (PDOException $error) {
    if (!$createDb) {
        fail('قاعدة البيانات غير موجودة. أنشئها يدوياً أو أضف --create-db. (' . $error->getMessage() . ')');
    }
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$options['db-name']}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$options['db-name']}`");
    out('  ✔ تم إنشاء قاعدة البيانات.');
}

/* ---------------- كتابة .env ---------------- */

$appKey = bin2hex(random_bytes(32));
$envValues = [
    'APP_NAME'        => $options['name'],
    'APP_ENV'         => 'production',
    'APP_DEBUG'       => 'false',
    'APP_URL'         => $options['url'],
    'APP_TIMEZONE'    => 'Asia/Riyadh',
    'APP_KEY'         => $appKey,
    'APP_PRETTY_URLS' => 'true',
    'APP_MAINTENANCE' => 'false',
    'DB_HOST'         => (string) $options['db-host'],
    'DB_PORT'         => (string) $options['db-port'],
    'DB_DATABASE'     => (string) $options['db-name'],
    'DB_USERNAME'     => (string) $options['db-user'],
    'DB_PASSWORD'     => (string) $options['db-pass'],
    'DB_CHARSET'      => 'utf8mb4',
    'SESSION_SECURE'  => str_starts_with((string) $options['url'], 'https://') ? 'true' : 'false',
    'SUBSCRIPTION_PRICE' => (string) $options['price'],
    'SUBSCRIPTION_DAYS'  => (string) $options['days'],
];
$template = is_file(BASE_PATH . '/.env.example') ? BASE_PATH . '/.env.example' : null;
if (!EnvWriter::write(CLI_ENV, $envValues, $template)) {
    fail('تعذّرت كتابة ملف .env — تحقق من صلاحيات مجلد المشروع.');
}
foreach ($envValues as $key => $value) {
    Env::set((string) $key, (string) $value);
}
out('  ✔ تم إنشاء ملف .env ومفتاح التشفير.');

Database::reset();
Database::setConnectionFactory(static fn(): PDO => $pdo);

/* ---------------- استيراد قاعدة البيانات ---------------- */

out('  … جارٍ استيراد database.sql (قد يستغرق بضع ثوانٍ)');
$sqlText = (string) file_get_contents(BASE_PATH . '/database.sql');
$lastReport = 0;
$import = DatabaseImporter::run($pdo, $sqlText, static function (int $number) use (&$lastReport): void {
    if ($number - $lastReport >= 25) {
        $lastReport = $number;
        fwrite(STDOUT, '    … ' . $number . " جملة\n");
    }
});
if (!$import['ok']) {
    fail('فشل الاستيراد بعد ' . $import['executed'] . " جملة:\n  " . $import['error'] . "\n  الجملة: " . $import['failed_statement']);
}
out('  ✔ تم استيراد ' . $import['executed'] . ' جملة SQL.');

$verify = DatabaseImporter::verifySchema($pdo, (string) $options['db-name'], DatabaseImporter::expectedTables());
if (!$verify['ok']) {
    fail('جداول مفقودة بعد الاستيراد: ' . implode(', ', $verify['missing']));
}
out('  ✔ تم التحقق من ' . count($verify['present']) . ' جدولاً.');

/* ---------------- حساب المدير والإعدادات ---------------- */

$existing = db()->one('SELECT id FROM `users` WHERE email = :email', ['email' => $options['admin-email']]);
$adminData = [
    'full_name'     => (string) $options['admin-name'],
    'phone'         => (string) $options['admin-phone'],
    'email'         => (string) $options['admin-email'],
    'password_hash' => Security::hashPassword((string) $options['admin-pass']),
    'role'          => 'admin',
    'status'        => 'active',
];
if ($existing !== null) {
    db()->update('users', $adminData, 'id = :id', ['id' => (int) $existing['id']]);
    $adminId = (int) $existing['id'];
    out('  ✔ تم تحديث حساب المدير الموجود.');
} else {
    $adminId = db()->insert('users', $adminData);
    out('  ✔ تم إنشاء حساب المدير.');
}

// حذف حسابات العرض التجريبية القادمة مع ملف البيانات
db()->delete('users', "email IN ('admin@example.com', 'student@example.com') AND id <> :id", ['id' => $adminId]);
out('  ✔ تم حذف حسابات العرض التجريبية.');

foreach (['site_name' => (string) $options['name'], 'subscription_price' => (string) $options['price'], 'subscription_days' => (string) $options['days']] as $key => $value) {
    $exists = (int) db()->value('SELECT COUNT(*) FROM `settings` WHERE setting_key = :key', ['key' => $key]);
    if ($exists > 0) {
        db()->update('settings', ['setting_value' => $value], 'setting_key = :key', ['key' => $key]);
    } else {
        db()->insert('settings', ['setting_key' => $key, 'setting_value' => $value, 'setting_group' => 'subscription']);
    }
}
out('  ✔ تم ضبط اسم الموقع وسعر الاشتراك (' . $options['price'] . ' ريال) والمدة (' . $options['days'] . ' يوماً).');

audit('install.completed', 'settings', null, ['admin' => $options['admin-email'], 'cli' => true]);

$lockData = [
    'installed_at' => date('c'),
    'version'      => '1.0.0',
    'app_url'      => (string) $options['url'],
    'admin_email'  => (string) $options['admin-email'],
    'database'     => (string) $options['db-name'],
    'php'          => PHP_VERSION,
    'method'       => 'cli',
];
if (file_put_contents(CLI_LOCK, json_encode($lockData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) {
    fail('تعذّر إنشاء storage/installed.lock — أنشئه يدوياً لمنع تكرار التثبيت.');
}

Logger::info('تم التثبيت من سطر الأوامر', ['admin' => $options['admin-email']]);

out('');
out('  ══════════════════════════════════════════════════════════');
out('   ✔ تم تثبيت المنصة بنجاح');
out('  ══════════════════════════════════════════════════════════');
out('   لوحة الإدارة : ' . $options['url'] . '/admin/index.php');
out('   تسجيل الدخول: ' . $options['url'] . '/auth/login.php');
out('   المدير       : ' . $options['admin-email']);
out('');
out('   خطوات ما بعد التثبيت:');
out('   1) احذف ملف install.php إن كان موجوداً على السيرفر.');
out('   2) اضبط صلاحيات: chmod -R 775 storage uploads && chmod 600 .env');
out('   3) أضف مهمة مجدولة: * * * * * php ' . BASE_PATH . '/tools/cron.php >/dev/null 2>&1');
out('   4) لتلجرام: php telegram/set-webhook.php');
out('');
exit(0);
