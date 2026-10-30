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

    /**
     * سياسة كلمة المرور القوية.
     * @param array<string,string> $context بيانات المستخدم (email, full_name, phone) لمنع اشتقاق كلمة المرور منها
     * @return array{ok:bool,message:string,score:int}
     */
    public static function passwordPolicy(string $password, array $context = []): array
    {
        $min = max(8, (int) config('security.password_min_length', 10));
        $password = trim($password);

        if ($password === '') {
            return ['ok' => false, 'message' => 'كلمة المرور مطلوبة.', 'score' => 0];
        }
        if (mb_strlen($password, 'UTF-8') < $min) {
            return ['ok' => false, 'message' => sprintf('كلمة المرور يجب ألا تقل عن %d أحرف.', $min), 'score' => 0];
        }
        if (mb_strlen($password, 'UTF-8') > 200) {
            return ['ok' => false, 'message' => 'كلمة المرور طويلة بشكل غير معتاد.', 'score' => 0];
        }
        if (preg_match('/[A-Za-z]/', $password) !== 1 || preg_match('/\d/', $password) !== 1) {
            return ['ok' => false, 'message' => 'كلمة المرور يجب أن تحتوي على حرف لاتيني ورقم على الأقل.', 'score' => 0];
        }
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $password) === 1) {
            return ['ok' => false, 'message' => 'يُفضَّل عدم استخدام الحروف العربية في كلمة المرور لتجنب مشكلات لوحات المفاتيح.', 'score' => 1];
        }
        if (self::isCommonPassword($password)) {
            return ['ok' => false, 'message' => 'كلمة المرور شائعة وسهلة التخمين؛ اختر كلمة مرور أخرى.', 'score' => 0];
        }
        if (self::passwordLooksLikeUserData($password, $context)) {
            return ['ok' => false, 'message' => 'لا يجوز اشتقاق كلمة المرور من بريدك أو اسمك أو رقم جوالك.', 'score' => 0];
        }
        if (self::isRepetitivePassword($password)) {
            return ['ok' => false, 'message' => 'كلمة المرور نمطية متكررة؛ اختر تركيبة غير متوقعة.', 'score' => 1];
        }
        return ['ok' => true, 'message' => 'كلمة المرور مطابقة للسياسة.', 'score' => self::passwordScore($password)];
    }

    /** @return list<string> */
    private static function commonPasswordList(): array
    {
        static $list = null;
        if ($list !== null) {
            return $list;
        }
        $list = [
            '123456', '1234567', '12345678', '123456789', '1234567890', '12345', '1234', '123123',
            '000000', '111111', '112233', '654321', '987654321',
            'password', 'passw0rd', 'password1', 'password123', 'p@ssword', 'qwerty', 'qwerty123',
            'qwertyuiop', 'azerty', 'abc123', 'admin', 'admin123', 'administrator', 'root', 'toor',
            'letmein', 'welcome', 'welcome1', 'monkey', 'dragon', 'sunshine', 'princess', 'football',
            'iloveyou', 'trustno1', 'baseball', 'master', 'login', 'starwars', 'whatever', 'michael',
            'saudi', 'saudi123', 'ksa', 'ksa123', 'teacher', 'teacher123', 'student', 'student123',
            'mohammed', 'mohammad123', 'ahmed123', '123456789a', 'zaq12wsx', '1q2w3e4r', '1q2w3e4r5t',
            'lokmen', 'lokmeng', 'test1234', 'changeme', 'secret', 'secret123',
        ];
        return $list;
    }

    private static function isCommonPassword(string $password): bool
    {
        $lower = mb_strtolower($password, 'UTF-8');
        if (in_array($lower, self::commonPasswordList(), true)) {
            return true;
        }
        $stripped = (string) preg_replace('/[\d!@#$%^&*._-]+$/u', '', $lower);
        if ($stripped !== '' && mb_strlen($stripped, 'UTF-8') >= 4) {
            return in_array($stripped, self::commonPasswordList(), true);
        }
        return false;
    }

    /** @param array<string,string> $context */
    private static function passwordLooksLikeUserData(string $password, array $context): bool
    {
        $lower = mb_strtolower($password, 'UTF-8');
        foreach ($context as $value) {
            $value = mb_strtolower(trim((string) $value), 'UTF-8');
            if ($value === '') {
                continue;
            }
            if (mb_strlen($value, 'UTF-8') >= 4 && str_contains($lower, $value)) {
                return true;
            }
            if (str_contains($value, '@')) {
                $local = explode('@', $value)[0];
                if (mb_strlen($local, 'UTF-8') >= 4 && str_contains($lower, $local)) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function isRepetitivePassword(string $password): bool
    {
        if (preg_match('/^(.)\1{3,}$/u', $password) === 1 || preg_match('/^(..)\1{2,}$/u', $password) === 1) {
            return true;
        }
        if (preg_match('/^(?:0123|1234|2345|3456|4567|5678|6789|abcd|bcde|cdef|qwer|asdf)/u', mb_strtolower($password, 'UTF-8')) === 1) {
            return preg_match('/^(?:\d+|[A-Za-z]+)$/u', $password) === 1;
        }
        return false;
    }

    /** تقدير قوة كلمة المرور من ٠ إلى ٥. */
    public static function passwordScore(string $password): int
    {
        $score = 0;
        $len = mb_strlen($password, 'UTF-8');
        if ($len >= 8) { $score++; }
        if ($len >= 12) { $score++; }
        if (preg_match('/[a-z]/', $password) === 1 && preg_match('/[A-Z]/', $password) === 1) { $score++; }
        if (preg_match('/\d/', $password) === 1) { $score++; }
        if (preg_match('/[^A-Za-z0-9]/', $password) === 1) { $score++; }
        return min(5, $score);
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
    /**
     * هل تجاوز هذا المفتاح الحد المسموح؟
     * يُسجَّل العدّ في قاعدة البيانات، وإن تعذّر الاتصال تُستخدم ذاكرة على الملفات (إغلاق آمن).
     */
    public static function exceededLimit(string $key, int $max, int $minutes): bool
    {
        $storedKey = self::limitKey($key);
        $dbCount = 0;
        try {
            $dbCount = (int) Database::instance()->value(
                'SELECT COUNT(*) FROM login_attempts WHERE identifier = :k AND created_at >= :s',
                ['k' => 'limit:' . $storedKey, 's' => date('Y-m-d H:i:s', time() - $minutes * 60)],
                0
            );
        } catch (\Throwable) {
            $dbCount = 0;
        }
        return max($dbCount, self::fileLimitCount($storedKey, $minutes)) >= $max;
    }

    public static function hitLimit(string $key): void
    {
        $storedKey = self::limitKey($key);
        self::fileLimitHit($storedKey);
        try {
            Database::instance()->insert('login_attempts', [
                'identifier' => 'limit:' . $storedKey,
                'ip'         => self::ip(),
                'user_agent' => self::userAgent(),
                'successful' => 1,
            ]);
        } catch (\Throwable) {
            // لا نُفشل العملية الأساسية عند تعذّر تسجيل الحد
        }
    }

    private static function limitKey(string $key): string
    {
        return mb_substr($key, 0, 120);
    }

    /** @return list<int> */
    private static function fileLimitRead(string $key, int $minutes): array
    {
        $file = self::fileLimitPath($key);
        if (!is_file($file)) {
            return [];
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return [];
        }
        $data  = json_decode($raw, true);
        $times = is_array($data) ? array_values(array_filter(array_map('intval', $data))) : [];
        $cut   = time() - ($minutes * 60);
        return array_values(array_filter($times, static fn(int $t): bool => $t >= $cut));
    }

    private static function fileLimitCount(string $key, int $minutes): int
    {
        return count(self::fileLimitRead($key, $minutes));
    }

    private static function fileLimitHit(string $key): void
    {
        $file = self::fileLimitPath($key);
        $dir  = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }
        $handle = @fopen($file, 'c+');
        if ($handle === false) {
            return;
        }
        try {
            @flock($handle, LOCK_EX);
            rewind($handle);
            $raw   = (string) stream_get_contents($handle);
            $data  = $raw !== '' ? json_decode($raw, true) : [];
            $times = is_array($data) ? array_values(array_filter(array_map('intval', $data))) : [];
            $cut   = time() - 86400;
            $times = array_values(array_filter($times, static fn(int $t): bool => $t >= $cut));
            $times[] = time();
            if (count($times) > 200) {
                $times = array_slice($times, -200);
            }
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($times));
            fflush($handle);
        } finally {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private static function fileLimitPath(string $key): string
    {
        $hash = hash('sha256', $key);
        return rtrim((string) config('paths.storage', BASE_PATH . '/storage'), '/')
            . '/cache/ratelimit/' . mb_substr($hash, 0, 2) . '/' . mb_substr($hash, 2, 30) . '.json';
    }

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
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=(), fullscreen=(self)');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        header('X-Permitted-Cross-Domain-Policies: none');
        header('X-DNS-Prefetch-Control: off');
        header_remove('X-Powered-By');
        header_remove('Server');
        // CSP: الأصول محلية بالكامل (Bootstrap و Chart.js داخل assets) لذا 'self' كافٍ،
        // مع السماح بالأنماط المضمنة المستخدمة في الرسوم البيانية.
        $csp = "default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; "
            . "script-src 'self' 'unsafe-inline'; font-src 'self' data:; connect-src 'self'; "
            . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'";
        if (self::isHttps()) {
            $csp .= '; upgrade-insecure-requests';
            $maxAge = max(300, (int) config('security.hsts_max_age', 31536000));
            header('Strict-Transport-Security: max-age=' . $maxAge . '; includeSubDomains');
        }
        header('Content-Security-Policy: ' . $csp);
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
    }
}
