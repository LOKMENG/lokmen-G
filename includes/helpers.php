<?php
/**
 * دوال مساعدة عامة تستخدمها كل صفحات المنصة.
 */
declare(strict_types=1);

use App\Auth;
use App\Database;
use App\Env;
use App\Logger;
use App\Security;
use App\Settings;
use App\Str;
use App\View;

if (!function_exists('e')) {
    /** تهريب المخرجات لمنع XSS (يُستخدم مع كل نص يخرج من المستخدم أو قاعدة البيانات) */
    function e(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        static $config = null;
        if ($config === null) {
            $config = require (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)) . '/config/config.php';
        }
        if ($key === null) {
            return $config;
        }
        $segments = explode('.', $key);
        $value = $config;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('db')) {
    function db(): Database
    {
        return Database::instance();
    }
}

if (!function_exists('settings')) {
    function settings(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? Settings::all() : Settings::get($key, $default);
    }
}

if (!function_exists('setting_bool')) {
    function setting_bool(string $key, bool $default = false): bool
    {
        return Settings::bool($key, $default);
    }
}

if (!function_exists('auth')) {
    function auth(): Auth
    {
        return Auth::instance();
    }
}

if (!function_exists('user')) {
    function user(): ?array
    {
        return Auth::instance()->user();
    }
}

if (!function_exists('user_id')) {
    function user_id(): ?int
    {
        $user = Auth::instance()->user();
        return $user === null ? null : (int) $user['id'];
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return Auth::instance()->isAdmin();
    }
}

if (!function_exists('url')) {
    /** بناء رابط مطلق داخل المنصة: url('student/dashboard') */
    function url(string $path = '', array $query = []): string
    {
        $base = (string) config('app.url', '');
        $path = ltrim($path, '/');
        if ($path !== '' && !str_contains($path, '.') && !str_ends_with($path, '/')) {
            // الروابط الجميلة اختيارية - تعمل مع .htaccess أو بدونه
            if (!config('app.pretty_urls', true)) {
                $path .= '.php';
            }
        }
        $url = $base . ($path === '' ? '' : '/' . $path);
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }
        return $url;
    }
}

if (!function_exists('asset')) {
    /** رابط ملف ثابت مع نسخة للتحديث التلقائي في المتصفح */
    function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)) . '/' . $path;
        $version = is_file($file) ? (string) filemtime($file) : (string) config('app.version');
        return rtrim((string) config('app.url'), '/') . '/' . $path . '?v=' . $version;
    }
}

if (!function_exists('upload_url')) {
    function upload_url(?string $path): string
    {
        if ($path === null || $path === '') {
            return '';
        }
        return rtrim((string) config('app.url'), '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path, int $status = 302, array $query = []): never
    {
        $target = preg_match('#^https?://#', $path) === 1 ? $path : url($path, $query);
        if (!headers_sent()) {
            header('Location: ' . $target, true, $status);
        }
        echo '<script>location.replace(' . json_encode($target) . ');</script>';
        exit;
    }
}

if (!function_exists('back')) {
    function back(string $fallback = ''): never
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if ($referer !== '' && str_contains($referer, (string) parse_url((string) config('app.url'), PHP_URL_HOST))) {
            if (!headers_sent()) {
                header('Location: ' . $referer);
            }
            exit;
        }
        redirect($fallback === '' ? '' : $fallback);
    }
}

if (!function_exists('current_path')) {
    function current_path(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $uri = (string) (parse_url($uri, PHP_URL_PATH) ?? '');
        $base = (string) (parse_url((string) config('app.url'), PHP_URL_PATH) ?? '');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        return trim($uri, '/');
    }
}

if (!function_exists('is_active_menu')) {
    function is_active_menu(string $prefix): bool
    {
        return str_starts_with(current_path(), trim($prefix, '/'));
    }
}

if (!function_exists('is_post')) {
    function is_post(): bool
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    }
}

if (!function_exists('input')) {
    /** قراءة مدخل من POST/GET مع تنظيف المسافات الزائدة */
    function input(?string $key = null, mixed $default = null): mixed
    {
        $all = array_merge($_GET, $_POST);
        if ($key === null) {
            return $all;
        }
        $value = $all[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }
}

if (!function_exists('post')) {
    function post(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }
}

if (!function_exists('query')) {
    function query(string $key, mixed $default = null): mixed
    {
        $value = $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }
}

if (!function_exists('json_input')) {
    /** قراءة جسم الطلب JSON (لواجهات API) */
    function json_input(): array
    {
        static $data = null;
        if ($data === null) {
            $raw = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);
            $data = is_array($decoded) ? $decoded : [];
        }
        return $data;
    }
}

if (!function_exists('json_response')) {
    function json_response(mixed $data, int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('old')) {
    /** إعادة عرض القيم المدخلة بعد فشل التحقق */
    function old(string $key, mixed $default = ''): mixed
    {
        $values = $_SESSION['_old_input'] ?? [];
        return $values[$key] ?? $default;
    }
}

if (!function_exists('keep_old_input')) {
    function keep_old_input(): void
    {
        $_SESSION['_old_input'] = array_filter(
            $_POST,
            static fn($value) => !is_array($value) || $value === []
        );
    }
}

if (!function_exists('clear_old_input')) {
    function clear_old_input(): void
    {
        unset($_SESSION['_old_input']);
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('flashes')) {
    /** @return array<int,array{type:string,message:string}> */
    function flashes(): array
    {
        $items = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return is_array($items) ? $items : [];
    }
}

if (!function_exists('errors')) {
    /** @return array<string,string> */
    function errors(): array
    {
        $items = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);
        return is_array($items) ? $items : [];
    }
}

if (!function_exists('set_errors')) {
    /** @param array<string,string> $items */
    function set_errors(array $items): void
    {
        $_SESSION['_errors'] = $items;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Security::csrfToken();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(Security::csrfToken()) . '">';
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify(bool $abortOnFailure = true): bool
    {
        $valid = Security::verifyCsrf(
            (string) ($_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? json_input()['_token'] ?? '')
        );
        if (!$valid && $abortOnFailure) {
            Logger::security('فشل التحقق من رمز CSRF', [
                'uri'    => $_SERVER['REQUEST_URI'] ?? '',
                'ip'     => Security::ip(),
                'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            ]);
            abort(419, 'انتهت صلاحية الجلسة أو أن الطلب غير موثوق. أعد المحاولة من فضلك.');
        }
        return $valid;
    }
}

if (!function_exists('abort')) {
    function abort(int $code, string $message = '', string $title = ''): never
    {
        View::error($code, $message, $title);
        exit;
    }
}

if (!function_exists('format_date')) {
    /** تاريخ بصيغة عربية مقروءة: 5 أكتوبر 2026 */
    function format_date(?string $datetime, bool $withTime = false): string
    {
        if ($datetime === null || $datetime === '' || str_starts_with($datetime, '0000')) {
            return '—';
        }
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return '—';
        }
        $months = [1 => 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
        $date = (int) date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
        if ($withTime) {
            $date .= ' - ' . date('h:i', $timestamp) . ' ' . ((int) date('G', $timestamp) < 12 ? 'صباحاً' : 'مساءً');
        }
        return $date;
    }
}

if (!function_exists('format_date_short')) {
    function format_date_short(?string $datetime): string
    {
        if ($datetime === null || $datetime === '' || str_starts_with($datetime, '0000')) {
            return '—';
        }
        $ts = strtotime($datetime);
        return $ts === false ? '—' : date('Y/m/d', $ts);
    }
}

if (!function_exists('days_between')) {
    function days_between(?string $from, ?string $to = null): int
    {
        if ($from === null || $from === '') {
            return 0;
        }
        $start = strtotime($from);
        $end = $to === null ? time() : strtotime($to);
        if ($start === false || $end === false) {
            return 0;
        }
        return (int) floor(($end - $start) / 86400);
    }
}

if (!function_exists('ar_digits')) {
    function ar_digits(string|int|float|null $value): string
    {
        $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        return str_replace($western, $arabic, (string) $value);
    }
}

if (!function_exists('money')) {
    function money(float|int|string|null $amount, bool $withCurrency = true): string
    {
        $formatted = number_format((float) $amount, 2, '.', ',');
        return $withCurrency ? $formatted . ' ر.س' : $formatted;
    }
}

if (!function_exists('duration_ar')) {
    function duration_ar(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;
        if ($hours > 0) {
            return sprintf('%d ساعة و %d دقيقة', $hours, $minutes);
        }
        if ($minutes > 0) {
            return sprintf('%d دقيقة و %d ثانية', $minutes, $secs);
        }
        return sprintf('%d ثانية', $secs);
    }
}

if (!function_exists('difficulty_ar')) {
    function difficulty_ar(?string $difficulty): string
    {
        return match ($difficulty) {
            'easy'  => 'سهل',
            'medium'=> 'متوسط',
            'hard'  => 'صعب',
            default => 'غير محدد',
        };
    }
}

if (!function_exists('difficulty_class')) {
    function difficulty_class(?string $difficulty): string
    {
        return match ($difficulty) {
            'easy'  => 'success',
            'medium'=> 'warning',
            'hard'  => 'danger',
            default => 'secondary',
        };
    }
}

if (!function_exists('answer_letter_ar')) {
    function answer_letter_ar(?string $letter): string
    {
        return match (strtolower((string) $letter)) {
            'a' => 'أ',
            'b' => 'ب',
            'c' => 'ج',
            'd' => 'د',
            default => '—',
        };
    }
}

if (!function_exists('track_name')) {
    function track_name(?string $code): string
    {
        return match ($code) {
            'specialist'  => 'التخصصي',
            'educational' => 'التربوي',
            default        => (string) $code,
        };
    }
}

if (!function_exists('str_limit')) {
    function str_limit(?string $text, int $limit = 90, string $end = '…'): string
    {
        return Str::limit((string) $text, $limit, $end);
    }
}

if (!function_exists('score_class')) {
    function score_class(float|int $percentage): string
    {
        return match (true) {
            $percentage >= 80 => 'success',
            $percentage >= 60 => 'warning',
            default            => 'danger',
        };
    }
}

if (!function_exists('initials')) {
    function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_substr($part, 0, 1, 'UTF-8');
        }
        return $letters === '' ? '؟' : $letters;
    }
}

if (!function_exists('avatar_url')) {
    function avatar_url(?string $path, string $name = ''): string
    {
        if ($path !== null && $path !== '' && is_file((defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)) . '/' . ltrim($path, '/'))) {
            return upload_url($path);
        }
        // صورة رمزية مولَّدة (SVG Data URI) بأول حرفين من الاسم
        $text = initials($name);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80"><rect width="80" height="80" rx="40" fill="#0f766e"/>'
             . '<text x="50%" y="54%" font-size="30" fill="#ffffff" text-anchor="middle" dominant-baseline="middle" font-family="sans-serif">'
             . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</text></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}

if (!function_exists('percent')) {
    function percent(float|int $value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals) . '%';
    }
}

if (!function_exists('paginate_links')) {
    /**
     * بناء روابط الترقيم مع الحفاظ على باقي معاملات الرابط.
     * @return string
     */
    function pagination(int $page, int $pages, array $query = []): string
    {
        if ($pages <= 1) {
            return '';
        }
        $html = '<nav aria-label="ترقيم الصفحات"><ul class="pagination pagination-sm justify-content-center mb-0">';
        $makeUrl = static function (int $target) use ($query): string {
            $query['page'] = $target;
            return '?' . http_build_query($query);
        };
        $html .= '<li class="page-item ' . ($page <= 1 ? 'disabled' : '') . '"><a class="page-link" href="'
            . e($makeUrl(max(1, $page - 1))) . '">السابق</a></li>';
        $start = max(1, $page - 2);
        $end = min($pages, $start + 4);
        $start = max(1, $end - 4);
        for ($i = $start; $i <= $end; $i++) {
            $html .= '<li class="page-item ' . ($i === $page ? 'active' : '') . '"><a class="page-link" href="'
                . e($makeUrl($i)) . '">' . ar_digits($i) . '</a></li>';
        }
        $html .= '<li class="page-item ' . ($page >= $pages ? 'disabled' : '') . '"><a class="page-link" href="'
            . e($makeUrl(min($pages, $page + 1))) . '">التالي</a></li>';
        return $html . '</ul></nav>';
    }
}

if (!function_exists('active_badge')) {
    function active_badge(bool|int $active, string $trueText = 'مفعّل', string $falseText = 'معطّل'): string
    {
        return $active
            ? '<span class="badge bg-success-subtle text-success-emphasis">' . e($trueText) . '</span>'
            : '<span class="badge bg-secondary-subtle text-secondary-emphasis">' . e($falseText) . '</span>';
    }
}

if (!function_exists('subscription_badge')) {
    function subscription_badge(?string $status): string
    {
        [$class, $label] = match ($status) {
            'active'   => ['success', 'نشط'],
            'approved' => ['info', 'تم التحقق من الدفع'],
            'pending'  => ['warning', 'بانتظار الدفع'],
            'expired'  => ['secondary', 'منتهي'],
            'rejected' => ['danger', 'مرفوض'],
            'cancelled'=> ['dark', 'ملغي'],
            default     => ['secondary', 'لا يوجد اشتراك'],
        };
        return '<span class="badge bg-' . $class . '-subtle text-' . $class . '-emphasis">' . e($label) . '</span>';
    }
}

if (!function_exists('audit')) {
    /** تسجيل عملية حساسة في سجل التدقيق */
    function audit(string $action, ?string $entityType = null, ?int $entityId = null, array $meta = []): void
    {
        try {
            App\Audit::record($action, $entityType, $entityId, $meta);
        } catch (\Throwable $e) {
            Logger::warning('تعذّر تسجيل عملية التدقيق: ' . $e->getMessage(), ['action' => $action]);
        }
    }
}

if (!function_exists('is_cli')) {
    /**
     * هل التنفيذ من سطر الأوامر؟
     * يقبل cli و phpdbg و wasm (بيئة التشغيل في المعاينة/الاختبارات).
     */
    function is_cli(): bool
    {
        return in_array(PHP_SAPI, ['cli', 'phpdbg', 'wasm'], true);
    }
}

if (!function_exists('cli_args')) {
    /**
     * معاملات سطر الأوامر بشكل موحّد.
     * بعض بيئات التشغيل لا تمرر $argv وتضعها في $_SERVER['APP_ARGV'] بدلاً منها.
     * @return array<int,string>
     */
    function cli_args(): array
    {
        if (isset($GLOBALS['argv']) && is_array($GLOBALS['argv']) && $GLOBALS['argv'] !== []) {
            return array_values(array_map('strval', array_slice($GLOBALS['argv'], 1)));
        }
        $raw = (string) ($_SERVER['APP_ARGV'] ?? getenv('APP_ARGV') ?: '');
        $parts = preg_split('/\s+/', trim($raw)) ?: [];
        return array_values(array_filter($parts, static fn(string $part): bool => $part !== ''));
    }
}

if (!function_exists('cli_option')) {
    /** قراءة قيمة معامل مثل --filter=abc أو --minutes=10 */
    function cli_option(string $name, string $default = ''): string
    {
        foreach (cli_args() as $argument) {
            if (str_starts_with($argument, '--' . $name . '=')) {
                return substr($argument, strlen($name) + 3);
            }
        }
        return $default;
    }
}

if (!function_exists('cli_has_flag')) {
    /** هل وُجد علم مثل --once */
    function cli_has_flag(string $name): bool
    {
        return in_array('--' . $name, cli_args(), true);
    }
}
