<?php
declare(strict_types=1);

namespace App;

use Throwable;

/**
 * نظام المستخدمين: تسجيل، دخول، خروج، استعادة كلمة المرور، تذكر الدخول، والصلاحيات.
 */
final class Auth
{
    private static ?Auth $instance = null;

    /** @var array<string,mixed>|null */
    private ?array $user = null;
    private bool $resolved = false;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    // ------------------------------------------------------------------
    //  المستخدم الحالي
    // ------------------------------------------------------------------

    /** @return array<string,mixed>|null */
    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->user;
        }
        $this->resolved = true;
        $this->user = null;

        $id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        if ($id > 0) {
            $this->user = $this->findById($id);
            if ($this->user === null || $this->user['status'] !== 'active') {
                $this->logout();
                return null;
            }
        } elseif (!empty($_COOKIE[$this->rememberCookieName()])) {
            $this->loginFromRememberCookie((string) $_COOKIE[$this->rememberCookieName()]);
        }

        if ($this->user !== null) {
            $this->validateFingerprint();
        }
        return $this->user;
    }

    public function id(): ?int
    {
        $user = $this->user();
        return $user === null ? null : (int) $user['id'];
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function isAdmin(): bool
    {
        $user = $this->user();
        return $user !== null && in_array($user['role'], ['admin', 'supervisor'], true);
    }

    public function isSuperAdmin(): bool
    {
        $user = $this->user();
        return $user !== null && $user['role'] === 'admin';
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        return Database::instance()->one('SELECT * FROM `users` WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    /** @return array<string,mixed>|null */
    public function findByIdentifier(string $identifier): ?array
    {
        $identifier = trim($identifier);
        $phone = Str::normalizeSaudiPhone($identifier);
        return Database::instance()->one(
            'SELECT * FROM `users` WHERE email = :email OR phone = :phone OR phone = :raw LIMIT 1',
            ['email' => mb_strtolower($identifier), 'phone' => $phone, 'raw' => $identifier]
        );
    }

    // ------------------------------------------------------------------
    //  الدخول والخروج
    // ------------------------------------------------------------------

    /** @return array{ok:bool,message:string,user?:array<string,mixed>} */
    public function attempt(string $identifier, string $password, bool $remember = false): array
    {
        $identifier = trim($identifier);
        if ($identifier === '' || $password === '') {
            return ['ok' => false, 'message' => 'يرجى إدخال بيانات الدخول كاملة.'];
        }
        if (Security::isLoginRateLimited($identifier)) {
            Logger::security('محاولات دخول كثيرة - تم الحظر المؤقت', ['identifier' => $identifier] + Security::requestContext());
            return ['ok' => false, 'message' => 'تم إيقاف تسجيل الدخول مؤقتاً بسبب كثرة المحاولات الخاطئة. حاول بعد ' . config('security.login_lock_minutes', 15) . ' دقيقة.'];
        }

        $user = $this->findByIdentifier($identifier);
        if ($user === null || !Security::verifyPassword($password, (string) $user['password_hash'])) {
            Security::recordLoginAttempt($identifier, false);
            return ['ok' => false, 'message' => 'بيانات الدخول غير صحيحة.'];
        }
        if (($user['status'] ?? '') !== 'active') {
            Security::recordLoginAttempt($identifier, false);
            $message = $user['status'] === 'disabled'
                ? 'حسابك معطّل حالياً. تواصل مع الإدارة.'
                : 'حسابك بانتظار التفعيل. تواصل مع الإدارة.';
            return ['ok' => false, 'message' => $message];
        }
        if ($user['locked_until'] !== null && strtotime((string) $user['locked_until']) > time()) {
            return ['ok' => false, 'message' => 'الحساب مقفل مؤقتاً. حاول لاحقاً أو تواصل مع الدعم.'];
        }

        // ترقية الهاش تلقائياً إذا تغيّرت الإعدادات
        if (Security::needsRehash((string) $user['password_hash'])) {
            Database::instance()->update('users', ['password_hash' => Security::hashPassword($password)], 'id = :id', ['id' => (int) $user['id']]);
        }

        $this->login($user, $remember);
        Security::recordLoginAttempt($identifier, true);
        Security::clearLoginAttempts($identifier);
        return ['ok' => true, 'message' => 'تم تسجيل الدخول بنجاح.', 'user' => $user];
    }

    /** @param array<string,mixed> $user */
    public function login(array $user, bool $remember = false): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['logged_in_at'] = time();
        $_SESSION['fingerprint'] = $this->fingerprint();
        unset($_SESSION['_csrf_token'], $_SESSION['_csrf_time']);

        $db = Database::instance();
        $db->update('users', [
            'last_login_at'      => date('Y-m-d H:i:s'),
            'last_login_ip'      => Security::ip(),
            'failed_login_count' => 0,
            'locked_until'       => null,
        ], 'id = :id', ['id' => (int) $user['id']]);

        if ($remember) {
            $this->remember($user);
        }
        $this->user = $this->findById((int) $user['id']);
        $this->resolved = true;

        audit('auth.login', 'user', (int) $user['id']);
    }

    public function logout(): void
    {
        $userId = $this->id();
        if ($userId !== null && isset($_COOKIE[$this->rememberCookieName()])) {
            $this->forgetRememberToken((string) $_COOKIE[$this->rememberCookieName()]);
        }
        $this->clearRememberCookie();

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
        $this->user = null;
        $this->resolved = true;
        if ($userId !== null) {
            audit('auth.logout', 'user', $userId);
        }
    }

    // ------------------------------------------------------------------
    //  "تذكر تسجيل الدخول" (Selector + Token مُجزّأ)
    // ------------------------------------------------------------------

    private function rememberCookieName(): string
    {
        return 'pl_remember';
    }

    /** @param array<string,mixed> $user */
    private function remember(array $user): void
    {
        $selector = bin2hex(random_bytes(16));
        $token = Security::randomToken(32);
        $days = 30;
        Database::instance()->insert('remember_tokens', [
            'user_id'    => (int) $user['id'],
            'selector'   => $selector,
            'token_hash' => hash('sha256', $token),
            'user_agent' => Security::userAgent(),
            'ip'         => Security::ip(),
            'expires_at' => date('Y-m-d H:i:s', time() + $days * 86400),
        ]);
        $this->setRememberCookie($selector . ':' . $token, time() + $days * 86400);
    }

    private function setRememberCookie(string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie($this->rememberCookieName(), $value, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => (bool) config('session.secure', false) || Security::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function clearRememberCookie(): void
    {
        if (!headers_sent()) {
            setcookie($this->rememberCookieName(), '', ['expires' => time() - 3600, 'path' => '/']);
        }
    }

    private function loginFromRememberCookie(string $cookie): void
    {
        $parts = explode(':', $cookie, 2);
        if (count($parts) !== 2) {
            $this->clearRememberCookie();
            return;
        }
        [$selector, $token] = $parts;
        $row = Database::instance()->one(
            'SELECT * FROM `remember_tokens` WHERE selector = :s AND expires_at >= NOW() LIMIT 1',
            ['s' => $selector]
        );
        if ($row === null || !hash_equals((string) $row['token_hash'], hash('sha256', $token))) {
            $this->clearRememberCookie();
            return;
        }
        $user = $this->findById((int) $row['user_id']);
        if ($user === null || $user['status'] !== 'active') {
            $this->clearRememberCookie();
            return;
        }
        // تدوير الرمز بعد كل استخدام (حماية من سرقة الملف)
        Database::instance()->delete('remember_tokens', 'id = :id', ['id' => (int) $row['id']]);
        $this->login($user, true);
    }

    private function forgetRememberToken(string $cookie): void
    {
        $selector = explode(':', $cookie, 2)[0] ?? '';
        if ($selector !== '') {
            Database::instance()->delete('remember_tokens', 'selector = :s', ['s' => $selector]);
        }
    }

    // ------------------------------------------------------------------
    //  بصمة الجلسة
    // ------------------------------------------------------------------

    private function fingerprint(): string
    {
        return hash('sha256', Security::userAgent() . '|' . mb_substr((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 0, 40));
    }

    private function validateFingerprint(): void
    {
        if (!config('security.session_fingerprint', true)) {
            return;
        }
        $stored = (string) ($_SESSION['fingerprint'] ?? '');
        if ($stored !== '' && !hash_equals($stored, $this->fingerprint())) {
            Logger::security('اختلاف بصمة الجلسة - تم إبطال الجلسة', Security::requestContext());
            $this->logout();
        }
        // تجديد معرّف الجلسة كل 30 دقيقة
        if (isset($_SESSION['logged_in_at']) && (time() - (int) $_SESSION['logged_in_at']) > 1800) {
            session_regenerate_id(true);
            $_SESSION['logged_in_at'] = time();
        }
    }

    // ------------------------------------------------------------------
    //  التسجيل
    // ------------------------------------------------------------------

    /** @param array<string,mixed> $data @return array{ok:bool,message:string,user_id?:int} */
    public function register(array $data): array
    {
        $db = Database::instance();
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $phone = Str::normalizeSaudiPhone((string) ($data['phone'] ?? ''));

        $exists = $db->one('SELECT id, email, phone FROM `users` WHERE email = :e OR phone = :p LIMIT 1', ['e' => $email, 'p' => $phone]);
        if ($exists !== null) {
            $message = mb_strtolower((string) $exists['email']) === $email
                ? 'البريد الإلكتروني مُسجَّل مسبقاً.'
                : 'رقم الجوال مُسجَّل مسبقاً.';
            return ['ok' => false, 'message' => $message];
        }

        $userId = $db->insert('users', [
            'full_name'        => trim((string) ($data['full_name'] ?? '')),
            'phone'            => $phone,
            'email'            => $email,
            'password_hash'    => Security::hashPassword((string) $data['password']),
            'role'             => 'student',
            'status'           => 'active',
            'city'             => $data['city'] ?? null,
            'specialty'        => $data['specialty'] ?? null,
            'target_track_id'  => isset($data['target_track_id']) ? (int) $data['target_track_id'] : null,
            'email_verified_at' => null,
        ]);

        Notifier::welcome($userId);
        audit('auth.register', 'user', $userId, ['email' => $email]);

        return ['ok' => true, 'message' => 'تم إنشاء حسابك بنجاح.', 'user_id' => $userId];
    }

    // ------------------------------------------------------------------
    //  استعادة كلمة المرور
    // ------------------------------------------------------------------

    /** @return array{ok:bool,message:string,token?:string} */
    public function requestPasswordReset(string $email): array
    {
        $email = mb_strtolower(trim($email));
        $user = Database::instance()->one('SELECT * FROM `users` WHERE email = :e LIMIT 1', ['e' => $email]);
        // لا نكشف إن كان البريد مسجلاً أم لا
        $generic = ['ok' => true, 'message' => 'إذا كان البريد مسجلاً لدينا فسيصلك رابط إعادة التعيين خلال دقائق.'];

        if ($user === null) {
            return $generic;
        }
        $token = Security::randomToken(32);
        Database::instance()->insert('password_resets', [
            'user_id'    => (int) $user['id'],
            'email'      => $email,
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'ip'         => Security::ip(),
        ]);
        audit('auth.password_reset_requested', 'user', (int) $user['id']);
        return $generic + ['token' => $token];
    }

    public function validateResetToken(string $token): ?array
    {
        $row = Database::instance()->one(
            'SELECT pr.*, u.status AS user_status FROM `password_resets` pr
              JOIN `users` u ON u.id = pr.user_id
             WHERE pr.token_hash = :h AND pr.used_at IS NULL AND pr.expires_at >= NOW() LIMIT 1',
            ['h' => hash('sha256', $token)]
        );
        return $row;
    }

    /** @return array{ok:bool,message:string,user_id?:int} */
    public function resetPassword(string $token, string $newPassword): array
    {
        $row = $this->validateResetToken($token);
        if ($row === null) {
            return ['ok' => false, 'message' => 'رابط إعادة التعيين غير صالح أو منتهي الصلاحية.'];
        }
        $db = Database::instance();
        $db->transaction(function (Database $db) use ($row, $newPassword): void {
            $db->update('users', ['password_hash' => Security::hashPassword($newPassword)], 'id = :id', ['id' => (int) $row['user_id']]);
            $db->update('password_resets', ['used_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $row['id']]);
            $db->delete('remember_tokens', 'user_id = :id', ['id' => (int) $row['user_id']]);
        });
        audit('auth.password_reset', 'user', (int) $row['user_id']);
        return ['ok' => true, 'message' => 'تم تحديث كلمة المرور بنجاح. يمكنك تسجيل الدخول الآن.', 'user_id' => (int) $row['user_id']];
    }

    /** @return array{ok:bool,message:string} */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        $user = $this->findById($userId);
        if ($user === null) {
            return ['ok' => false, 'message' => 'المستخدم غير موجود.'];
        }
        if (!Security::verifyPassword($currentPassword, (string) $user['password_hash'])) {
            return ['ok' => false, 'message' => 'كلمة المرور الحالية غير صحيحة.'];
        }
        Database::instance()->update('users', ['password_hash' => Security::hashPassword($newPassword)], 'id = :id', ['id' => $userId]);
        audit('auth.password_changed', 'user', $userId);
        return ['ok' => true, 'message' => 'تم تغيير كلمة المرور بنجاح.'];
    }

    // ------------------------------------------------------------------
    //  الصلاحيات
    // ------------------------------------------------------------------

    public function requireLogin(string $redirectTo = ''): array
    {
        $user = $this->user();
        if ($user === null) {
            $_SESSION['intended_url'] = $redirectTo !== '' ? $redirectTo : current_path();
            flash('warning', 'يجب تسجيل الدخول للوصول إلى هذه الصفحة.');
            redirect('auth/login');
        }
        return $user;
    }

    public function requireAdmin(): array
    {
        $user = $this->requireLogin();
        if (!in_array($user['role'], ['admin', 'supervisor'], true)) {
            Logger::security('محاولة وصول غير مصرح بها للوحة التحكم', ['user_id' => $user['id']] + Security::requestContext());
            abort(403, 'هذه الصفحة مخصصة لإدارة المنصة فقط.');
        }
        return $user;
    }

    public function requireSuperAdmin(): array
    {
        $user = $this->requireAdmin();
        if ($user['role'] !== 'admin') {
            abort(403, 'هذه العملية تتطلب صلاحية مدير عام.');
        }
        return $user;
    }

    public function requireGuest(): void
    {
        if ($this->check()) {
            redirect('student/dashboard');
        }
    }

    public function intendedUrl(string $default = 'student/dashboard'): string
    {
        $intended = $_SESSION['intended_url'] ?? '';
        unset($_SESSION['intended_url']);
        return is_string($intended) && $intended !== '' ? $intended : $default;
    }

    /** @param array<string,mixed> $data */
    public function updateProfile(int $userId, array $data): void
    {
        $allowed = ['full_name', 'city', 'specialty', 'target_track_id', 'avatar'];
        $update = array_intersect_key($data, array_flip($allowed));
        if (isset($update['target_track_id'])) {
            $update['target_track_id'] = (int) $update['target_track_id'] ?: null;
        }
        if ($update === []) {
            return;
        }
        Database::instance()->update('users', $update, 'id = :id', ['id' => $userId]);
        if ($this->user !== null && (int) $this->user['id'] === $userId) {
            $this->user = $this->findById($userId);
        }
    }

    public function refresh(): void
    {
        $this->resolved = false;
        $this->user = null;
    }
}
