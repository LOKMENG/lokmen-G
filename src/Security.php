<?php
declare(strict_types=1);

namespace App;

use RuntimeException;

/** أدوات الأمان: CSRF، التشفير، الحماية من التخمين، رفع الملفات الآمن */
final class Security
{
    public static function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }

    public static function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 190);
    }

    /** @return array<string,mixed> */
    public static function requestContext(): array
    {
        return [
            'ip'         => self::ip(),
            'user_agent' => self::userAgent(),
            'uri'        => substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 255),
            'method'     => (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
        ];
    }

    // ------------------------------------------------------------------
    //  CSRF
    // ------------------------------------------------------------------

    public static function csrfToken(): string
    {
        $now = time();
        $ttl = (int) config('security.csrf_ttl', 7200);
        if (empty($_SESSION['_csrf_token']) || empty($_SESSION['_csrf_time']) || ($now - (int) $_SESSION['_csrf_time']) > $ttl) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['_csrf_time'] = $now;
        }
        return (string) $_SESSION['_csrf_token'];
    }

    public static function verifyCsrf(string $token): bool
    {
        return $token !== ''
            && !empty($_SESSION['_csrf_token'])
            && hash_equals((string) $_SESSION['_csrf_token'], $token);
    }

    // ------------------------------------------------------------------
    //  كلمات المرور
    // ------------------------------------------------------------------

    public static function hashPassword(string $password): string
    {
        // ملاحظة: لا يُشار إلى PASSWORD_ARGON2ID مباشرة إلا إذا كان معرّفاً،
        // وإلا حدث خطأ فادح على إصدارات PHP المبنية بدون دعم Argon2.
        $argonAvailable = \defined('PASSWORD_ARGON2ID');
        $algo = $argonAvailable ? \constant('PASSWORD_ARGON2ID') : \PASSWORD_BCRYPT;
        $options = ($argonAvailable && $algo === \constant('PASSWORD_ARGON2ID'))
            ? ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2]
            : ['cost' => 12];
        return password_hash($password, $algo, $options);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return $hash !== '' && password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        $algo = \defined('PASSWORD_ARGON2ID') ? \constant('PASSWORD_ARGON2ID') : \PASSWORD_BCRYPT;
        $options = $algo === \PASSWORD_BCRYPT ? ['cost' => 12] : ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2];
        try {
            return password_needs_rehash($hash, $algo, $options);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /** رمز قصير يسهل كتابته في تلجرام مثل: A7K2-9QX4 */
    public static function linkCode(int $length = 8): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        $max = strlen($alphabet) - 1;
        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }
        return $length > 4 ? substr($code, 0, 4) . '-' . substr($code, 4) : $code;
    }

    /**
     * توحيد رمز الربط إلى الصيغة القياسية ABCD-EFGH
     * (يقبل المدخل بأي شكل: مع شرطة أو بدونها أو بأحرف صغيرة).
     */
    public static function normalizeLinkCode(string $code): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        return strlen($clean) === 8 ? substr($clean, 0, 4) . '-' . substr($clean, 4) : $clean;
    }

    // ------------------------------------------------------------------
    //  تحديد المحاولات (Rate Limiting)
    // ------------------------------------------------------------------

    public static function isLoginRateLimited(string $identifier): bool
    {
        $max = (int) config('security.login_max_attempts', 5);
        $minutes = (int) config('security.login_lock_minutes', 15);
        $since = date('Y-m-d H:i:s', time() - ($minutes * 60));

        $byIdentifier = (int) Database::instance()->value(
            'SELECT COUNT(*) FROM login_attempts WHERE identifier = :i AND successful = 0 AND created_at >= :s',
            ['i' => mb_strtolower($identifier), 's' => $since],
            0
        );
        $byIp = (int) Database::instance()->value(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND successful = 0 AND created_at >= :s',
            ['ip' => self::ip(), 's' => $since],
            0
        );
        return $byIdentifier >= $max || $byIp >= ($max * 3);
    }

    public static function recordLoginAttempt(string $identifier, bool $successful): void
    {
        try {
            Database::instance()->insert('login_attempts', [
                'identifier' => mb_substr(mb_strtolower($identifier), 0, 190),
                'ip'         => self::ip(),
                'user_agent' => self::userAgent(),
                'successful' => $successful ? 1 : 0,
            ]);
        } catch (\Throwable $e) {
            Logger::warning('تعذّر تسجيل محاولة الدخول: ' . $e->getMessage());
        }
    }

    public static function clearLoginAttempts(string $identifier): void
    {
        Database::instance()->delete(
            'login_attempts',
            'identifier = :i AND ip = :ip',
            ['i' => mb_strtolower($identifier), 'ip' => self::ip()]
        );
    }

    /** حد عام لأي عملية (مثال: إنشاء حساب، إرسال رسائل) */
    public static function exceededLimit(string $key, int $max, int $minutes): bool
    {
        $count = (int) Database::instance()->value(
            'SELECT COUNT(*) FROM login_attempts WHERE identifier = :k AND created_at >= :s',
            ['k' => 'limit:' . $key, 's' => date('Y-m-d H:i:s', time() - $minutes * 60)],
            0
        );
        return $count >= $max;
    }

    public static function hitLimit(string $key): void
    {
        try {
            Database::instance()->insert('login_attempts', [
                'identifier' => 'limit:' . mb_substr($key, 0, 120),
                'ip'         => self::ip(),
                'user_agent' => self::userAgent(),
                'successful' => 1,
            ]);
        } catch (\Throwable) {
            // لا نُفشل العملية الأساسية عند تعذّر تسجيل الحد
        }
    }

    // ------------------------------------------------------------------
    //  رفع الملفات
    // ------------------------------------------------------------------

    /**
     * رفع ملف بشكل آمن: التحقق من الامتداد والمحتوى والحجم مع اسم عشوائي.
     * @param array<string,mixed> $file عنصر من $_FILES
     * @param array<int,string> $allowedExt
     * @return array{ok:bool,error:string,path:string,absolute:string,size:int,original:string}
     */
    public static function upload(array $file, array $allowedExt, string $relativeDir, int $maxMb = 8): array
    {
        $fail = static fn(string $message): array => ['ok' => false, 'error' => $message, 'path' => '', 'absolute' => '', 'size' => 0, 'original' => ''];

        if (!isset($file['tmp_name'], $file['error'])) {
            return $fail('لم يتم إرسال أي ملف.');
        }
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            return $fail('فشل رفع الملف (رمز الخطأ: ' . (int) $file['error'] . ').');
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxMb * 1024 * 1024) {
            return $fail('حجم الملف يجب أن يكون أقل من ' . $maxMb . ' ميجابايت.');
        }
        if (!is_uploaded_file((string) $file['tmp_name'])) {
            return $fail('الملف المرفوع غير صالح.');
        }

        $original = (string) ($file['name'] ?? 'file');
        $ext = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, array_map('strtolower', $allowedExt), true)) {
            return $fail('صيغة الملف غير مسموحة (' . e($ext) . '). الصيغ المسموحة: ' . implode(', ', $allowedExt));
        }
        if (in_array($ext, ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'pl', 'py', 'cgi', 'asp', 'aspx', 'jsp', 'sh', 'htaccess'], true)) {
            return $fail('صيغة الملف غير مسموحة.');
        }
        // فحص محتوى الصور فعلياً
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) && @getimagesize((string) $file['tmp_name']) === false) {
            return $fail('الملف ليس صورة صالحة.');
        }
        if ($ext === 'pdf') {
            $header = (string) @file_get_contents((string) $file['tmp_name'], false, null, 0, 5);
            if ($header !== '%PDF-') {
                return $fail('ملف PDF غير صالح.');
            }
        }

        $base = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)) . '/uploads/' . trim($relativeDir, '/');
        if (!is_dir($base) && !@mkdir($base, 0755, true) && !is_dir($base)) {
            return $fail('تعذّر إنشاء مجلد الرفع.');
        }
        $safeName = self::sanitizeFilename(pathinfo($original, PATHINFO_FILENAME));
        $fileName = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '_' . $safeName . '.' . $ext;
        $target = $base . '/' . $fileName;
        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            return $fail('تعذّر حفظ الملف على الخادم.');
        }
        @chmod($target, 0644);

        return [
            'ok'       => true,
            'error'    => '',
            'path'     => 'uploads/' . trim($relativeDir, '/') . '/' . $fileName,
            'absolute' => $target,
            'size'     => $size,
            'original' => $original,
        ];
    }

    public static function sanitizeFilename(string $name): string
    {
        // إبقاء الحروف العربية واللاتينية والأرقام والشرطة والنقطة فقط
        $name = (string) preg_replace('/[^\p{Arabic}\p{L}\p{N}_\-\. ]+/u', '', $name);
        // منع أي محاولة للتنقل في المسار أو إخفاء الامتداد بنقاط متتالية
        $name = str_replace(['..', '/', '\\'], '.', $name);
        $name = (string) preg_replace('/\.{2,}/', '.', $name);
        $name = ltrim($name, '.');
        $name = trim((string) preg_replace('/\s+/u', '_', $name));
        // إسقاط امتدادات التنفيذ نهائياً (التحقق النهائي يجري في upload())
        $dangerous = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pl', 'py', 'cgi', 'asp', 'aspx', 'jsp', 'sh', 'exe'];
        foreach ($dangerous as $extension) {
            if (preg_match('/\.' . $extension . '$/i', $name) === 1) {
                $name = (string) preg_replace('/\.' . $extension . '$/i', '', $name);
            }
        }
        $name = trim($name, '.');
        return $name === '' ? 'file' : mb_substr($name, 0, 60);
    }

    // ------------------------------------------------------------------
    //  تخزين القيم الحساسة (مفاتيح بوابات الدفع) مشفّرة بمفتاح التطبيق
    // ------------------------------------------------------------------

    private static function key(): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new RuntimeException('APP_KEY غير مضبوط في ملف .env');
        }
        return hash('sha256', $key, true);
    }

    public static function encrypt(string $plain): string
    {
        if (!function_exists('openssl_encrypt')) {
            return base64_encode($plain);
        }
        $iv = random_bytes(12);
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . ($tag ?? '') . (string) $cipher);
    }

    public static function decrypt(string $payload): string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 28 || !function_exists('openssl_decrypt')) {
            return '';
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        return (string) openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
    }

    // ------------------------------------------------------------------
    //  رؤوس الحماية
    // ------------------------------------------------------------------

    public static function sendSecurityHeaders(): void
    {
        if (headers_sent() || !config('security.headers', true)) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-XSS-Protection: 0');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header_remove('X-Powered-By');
        // CSP: الأصول محلية بالكامل (Bootstrap و Chart.js داخل assets) لذا 'self' كافٍ،
        // مع السماح بالأنماط المضمنة المستخدمة في الرسوم البيانية.
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; "
            . "script-src 'self' 'unsafe-inline'; font-src 'self' data:; connect-src 'self'; base-uri 'self'; form-action 'self'");
        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
    }
}
