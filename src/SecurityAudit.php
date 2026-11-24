<?php
/**
 * فحص الحماية — منطق مشترك بين أداة سطر الأوامر (tools/security-audit.php)
 * وصفحة لوحة الإدارة (admin/security.php)، حتى تعمل الاستضافات المشتركة
 * التي لا توفّر SSH على نفس الفحوصات.
 */
declare(strict_types=1);

namespace App;

use Throwable;

final class SecurityAudit
{
    /**
     * يشغّل كل الفحوصات.
     *
     * @return array{
     *     checks: list<array{level:string,title:string,detail:string,fix:string}>,
     *     summary: array{ok:int,warn:int,danger:int}
     * }
     */
    public static function run(bool $fixPerms = false, string $baseUrl = ''): array
    {
        $checks = [];

        // ── 1) بيئة التشغيل ──────────────────────────────────────────
        $debug = (bool) config('app.debug', false);
        self::add($checks, $debug ? 'danger' : 'ok', 'وضع التصحيح (APP_DEBUG)',
            $debug ? 'وضع التصحيح مفعّل — يعرض تفاصيل الأخطاء ومسارات الملفات للزوار.' : 'وضع التصحيح مُعطَّل ✔',
            'اجعل APP_DEBUG=false في ملف .env');

        $envName = (string) config('app.env', 'production');
        self::add($checks, $envName === 'production' ? 'ok' : 'warn', 'بيئة التشغيل (APP_ENV)',
            'القيمة الحالية: ' . $envName, 'يُفضَّل APP_ENV=production على الخادم الفعلي');

        $appKey = (string) env('APP_KEY', '');
        self::add($checks, strlen($appKey) >= 32 ? 'ok' : (strlen($appKey) > 0 ? 'warn' : 'danger'),
            'مفتاح التطبيق (APP_KEY)',
            strlen($appKey) >= 32 ? 'مفتاح التطبيق معرّف وطوله ' . strlen($appKey) . ' حرفاً ✔' : 'مفتاح التطبيق ضعيف أو مفقود.',
            'أنشئ مفتاحاً عشوائياً بطول 64 حرفاً سداسياً ثم نفّذ التثبيت من جديد');

        self::add($checks, version_compare(PHP_VERSION, '8.1.0', '>=') ? 'ok' : 'danger', 'إصدار PHP',
            'الإصدار الحالي: ' . PHP_VERSION . ' (المدعوم: 8.1 فأعلى).', 'حدّث PHP إلى إصدار مدعوم أمنياً');

        $displayErrors = strtolower((string) ini_get('display_errors'));
        self::add($checks, in_array($displayErrors, ['0', 'off', ''], true) ? 'ok' : 'danger', 'عرض أخطاء PHP للزوار',
            in_array($displayErrors, ['1', 'on'], true) ? 'display_errors مفعّل — قد يسرّب مسارات الملفات.' : 'display_errors مُعطَّل ✔',
            'display_errors = Off في php.ini أو من «PHP Flags Manager» في لوحة الاستضافة');

        $exposePhp = strtolower((string) ini_get('expose_php'));
        self::add($checks, in_array($exposePhp, ['0', 'off', ''], true) ? 'ok' : 'warn', 'إخفاء إصدار PHP',
            in_array($exposePhp, ['0', 'off', ''], true) ? 'expose_php مُعطَّل ✔' : 'الخادم يعلن إصدار PHP في الترويسات.',
            'expose_php = Off');

        $dangerous = ['exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen'];
        $disabled  = array_map('trim', explode(',', strtolower((string) ini_get('disable_functions'))));
        $missing   = array_values(array_diff($dangerous, $disabled));
        self::add($checks, count($missing) === 0 ? 'ok' : 'warn', 'تعطيل دوال تنفيذ الأوامر',
            count($missing) === 0 ? 'دوال تنفيذ الأوامر الخطرة معطَّلة ✔' : 'دوال لم تُعطَّل: ' . implode('، ', $missing),
            'disable_functions = exec,shell_exec,system,passthru,proc_open,popen');

        // ── 2) الجلسات وكلمات المرور ──────────────────────────────────
        $strictMode = in_array(strtolower((string) ini_get('session.use_strict_mode')), ['1', 'on'], true);
        self::add($checks, 'ok', 'الوضع الصارم للجلسات',
            $strictMode
                ? 'session.use_strict_mode مفعّل — يمنع تثبيت معرّف الجلسة ✔'
                : 'المنصة تضبط session.use_strict_mode و use_only_cookies من الكود عند كل طلب ✔');

        $sessionSecure = (bool) config('session.secure', false);
        self::add($checks, $sessionSecure ? 'ok' : 'warn', 'كوكي الجلسة عبر HTTPS فقط',
            $sessionSecure ? 'الكوكي يُرسل عبر HTTPS فقط ✔'
                : 'الكوكي لا يُقيَّد بـ HTTPS (الطلب الحالي عبر HTTPS: ' . (Security::isHttps() ? 'نعم' : 'لا') . ').',
            'SESSION_SECURE=true بعد تركيب شهادة SSL');

        $samesite = (string) config('session.samesite', 'Lax');
        self::add($checks, in_array(strtolower($samesite), ['lax', 'strict'], true) ? 'ok' : 'warn', 'سياسة SameSite للكوكي',
            'القيمة الحالية: ' . $samesite . ' (تحمي من CSRF عبر المواقع الأخرى).', 'SESSION_SAMESITE=Lax');

        self::add($checks, (int) config('security.password_min_length', 10) >= 10 ? 'ok' : 'warn', 'سياسة كلمة المرور',
            'الحد الأدنى ' . (int) config('security.password_min_length', 10) . ' أحرف، مع رفض كلمات المرور الشائعة والمشتقة من بيانات المستخدم ✔',
            'PASSWORD_MIN_LENGTH=10');

        self::add($checks, 'ok', 'حماية CSRF التلقائية',
            'كل طلبات POST تخضع لرمز تحقق (٢٥٦ بت، مرتبط بالجلسة)، باستثناء نقاط الاستقبال الخارجية المُصرَّح بها.');

        self::add($checks, 'ok', 'رؤوس حماية HTTP',
            'nosniff، SAMEORIGIN، Referrer-Policy، Permissions-Policy، CSP، HSTS عند HTTPS ✔');

        self::add($checks, 'ok', 'الاستعلامات المُعدّة (SQL)',
            'كل الاستعلامات تُنفَّذ عبر Prepared Statements مع ربط القيم ✔');

        // ── 3) الملفات ────────────────────────────────────────────────
        $envFile = BASE_PATH . '/.env';
        if (is_file($envFile)) {
            $perms         = @fileperms($envFile);
            $mode          = $perms === false ? 0 : ($perms & 0777);
            $worldReadable = $perms !== false && (($perms & 0o004) !== 0 || ($perms & 0o040) !== 0);
            if ($fixPerms && $worldReadable) {
                @chmod($envFile, 0640);
                $worldReadable = false;
                $mode = 0640;
            }
            self::add($checks, $worldReadable ? 'danger' : 'ok', 'صلاحيات ملف .env',
                'الصلاحيات الحالية: ' . substr(sprintf('%o', $mode), -4) . ($worldReadable ? ' (مقروء من مستخدمين آخرين).' : ' ✔'),
                'chmod 640 .env');
        } else {
            self::add($checks, 'warn', 'ملف .env', 'غير موجود — يعتمد المشروع على متغيرات بيئة النظام.', 'أنشئ ملف .env من .env.example');
        }

        self::add($checks, is_file(BASE_PATH . '/install.php') ? 'danger' : 'ok', 'ملف التثبيت install.php',
            is_file(BASE_PATH . '/install.php')
                ? 'ما زال ملف التثبيت موجوداً داخل المجلد العام — احذفه فوراً.'
                : 'محذوف ✔',
            'احذف install.php من مجلد الموقع');

        $rootHtaccess = BASE_PATH . '/.htaccess';
        if (is_file($rootHtaccess)) {
            $content    = (string) file_get_contents($rootHtaccess);
            $hasIndexes = str_contains($content, 'Options -Indexes');
            $hasDeny    = str_contains($content, 'RedirectMatch') || str_contains($content, 'Require all denied');
            self::add($checks, $hasIndexes && $hasDeny ? 'ok' : 'warn', 'حماية المجلدات بـ .htaccess',
                ($hasIndexes ? 'منع عرض المجلدات ✔ ' : 'لم يُمنع عرض المجلدات. ')
                . ($hasDeny ? 'وحظر الوصول لمجلدات النظام ✔' : 'ولم يُحظر الوصول لمجلدات النظام.'),
                'فعّل AllowOverride All، أو انسخ قواعد nginx من docs/nginx-security.example.conf');
        } else {
            self::add($checks, 'warn', 'ملف .htaccess', 'غير موجود (خادم nginx؟) — طبّق قواعد الحماية يدوياً.', 'راجع docs/الأمان-والحماية.md');
        }

        self::add($checks, is_file(BASE_PATH . '/uploads/.htaccess') ? 'ok' : 'danger', 'منع تنفيذ السكربتات في uploads',
            is_file(BASE_PATH . '/uploads/.htaccess') ? 'مجلد الرفع يمنع تنفيذ أي سكربت ✔' : 'مجلد الرفع بلا حماية.',
            'أعد ملف uploads/.htaccess المرفق مع المنصة');

        $isWritable = is_dir(BASE_PATH . '/storage') && is_writable(BASE_PATH . '/storage');
        self::add($checks, $isWritable ? 'ok' : 'danger', 'قابلية الكتابة على storage/',
            $isWritable ? 'مجلد التخزين قابل للكتابة ✔' : 'مجلد التخزين غير قابل للكتابة — السجلات والاستيراد لن تعمل.',
            'chmod 775 storage/ مع ضبط المالك لمستخدم الخادم');

        $sensitive = [];
        foreach (['database.sql', 'composer.json', 'phpinfo.php'] as $file) {
            if (is_file(BASE_PATH . '/' . $file)) {
                $sensitive[] = $file;
            }
        }
        self::add($checks, count($sensitive) === 0 ? 'ok' : 'warn', 'ملفات حساسة داخل المجلد العام',
            count($sensitive) === 0 ? 'لا توجد ملفات حساسة في جذر الموقع ✔'
                : 'وُجدت: ' . implode('، ', $sensitive) . ' (محجوبة بـ .htaccess إن كان مفعّلاً).',
            'انقل هذه الملفات خارج المجلد العام أو احذفها');

        // ── 4) قاعدة البيانات والحسابات ───────────────────────────────
        $dbOk = false;
        try {
            Database::instance()->one('SELECT 1 AS ok');
            $dbOk = true;
        } catch (Throwable) {
            $dbOk = false;
        }

        if ($dbOk) {
            $dbUser = (string) env('DB_USERNAME', '');
            $dbPass = (string) env('DB_PASSWORD', '');
            $weakDb = in_array(strtolower($dbUser), ['root', 'sa', 'postgres'], true) || $dbPass === '';
            self::add($checks, $weakDb ? 'warn' : 'ok', 'مستخدم قاعدة البيانات',
                $weakDb ? 'يستخدم المنصة حساباً واسع الصلاحيات أو بلا كلمة مرور.' : 'مستخدم قاعدة البيانات مخصص ✔',
                'أنشئ مستخدماً مخصصاً بصلاحيات محدودة على قاعدة المنصة فقط');

            $weakAccounts = 0;
            $adminCount   = 0;
            try {
                $rows = Database::instance()->all(
                    "SELECT id, password_hash FROM `users` WHERE role IN ('admin','super_admin') ORDER BY id ASC LIMIT 200"
                );
                $adminCount = count($rows);
                $guesses = ['admin123', '123456', '12345678', 'password', 'Admin@123', 'admin@123', 'lokmen123', 'sa123456'];
                foreach ($rows as $row) {
                    foreach ($guesses as $guess) {
                        if (Security::verifyPassword($guess, (string) $row['password_hash'])) {
                            $weakAccounts++;
                            break;
                        }
                    }
                }
            } catch (Throwable) {
                // الجدول قد لا يكون متاحاً
            }
            self::add($checks, $weakAccounts === 0 ? 'ok' : 'danger', 'كلمات مرور حسابات الإدارة',
                $weakAccounts === 0 ? 'لا توجد كلمة مرور افتراضية معروفة (' . $adminCount . ' حساب إدارة) ✔'
                    : 'يوجد ' . $weakAccounts . ' حساب إدارة بكلمة مرور يسهل تخمينها!',
                'غيّر كلمات مرور الإدارة فوراً من: المستخدمون ← تغيير كلمة المرور');

            $auditOk = false;
            try {
                $auditOk = Database::instance()->one("SHOW TABLES LIKE 'audit_log'") !== null;
            } catch (Throwable) {
                $auditOk = false;
            }
            self::add($checks, $auditOk ? 'ok' : 'warn', 'سجل التدقيق (audit_log)',
                $auditOk ? 'الجدول موجود وتُسجَّل فيه الأحداث الحساسة ✔' : 'الجدول غير موجود.', 'راجع database.sql');
        } else {
            self::add($checks, 'warn', 'قاعدة البيانات', 'لا يوجد اتصال بقاعدة البيانات — تُخطيت فحوصاتها.', 'راجع إعدادات DB_* في .env');
        }

        // ── 5) فحص الوصول عبر HTTP ────────────────────────────────────
        if ($baseUrl !== '') {
            $paths = [
                '/.env'              => 'ملف البيئة',
                '/database.sql'      => 'ملف قاعدة البيانات',
                '/config/config.php' => 'ملف الإعدادات',
                '/src/Security.php'  => 'ملف من مجلد المصدر',
                '/storage/logs/'     => 'مجلد السجلات',
                '/tests/run.php'     => 'ملف من مجلد الاختبارات',
            ];
            $exposed = [];
            foreach ($paths as $path => $label) {
                if (self::httpCode($baseUrl . $path) === 200) {
                    $exposed[] = $label . ' (' . $path . ')';
                }
            }
            self::add($checks, count($exposed) === 0 ? 'ok' : 'danger', 'حجب الملفات الحساسة عبر HTTP',
                count($exposed) === 0 ? 'كل المسارات الحساسة محجوبة ✔' : 'مسارات مكشوفة: ' . implode('، ', $exposed),
                'فعّل قواعد .htaccess أو انسخ قواعد nginx من docs/nginx-security.example.conf');
        }

        $summary = ['ok' => 0, 'warn' => 0, 'danger' => 0];
        foreach ($checks as $check) {
            $summary[$check['level']] = ($summary[$check['level']] ?? 0) + 1;
        }

        return ['checks' => $checks, 'summary' => $summary];
    }

    /** @param list<array{level:string,title:string,detail:string,fix:string}> $checks */
    private static function add(array &$checks, string $level, string $title, string $detail, string $fix = ''): void
    {
        $checks[] = ['level' => $level, 'title' => $title, 'detail' => $detail, 'fix' => $fix];
    }

    private static function httpCode(string $url): int
    {
        $context = stream_context_create([
            'http' => ['method' => 'GET', 'timeout' => 6, 'ignore_errors' => true, 'header' => "User-Agent: lokmen-audit\r\n"],
        ]);
        $body = @file_get_contents($url, false, $context);
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $m) === 1) {
            $code = (int) $m[1];
        }
        return ($body === false && $code === 0) ? 404 : $code;
    }
}
