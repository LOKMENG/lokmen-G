<?php
declare(strict_types=1);

namespace App;

/**
 * قارئ ملف .env خفيف بدون أي مكتبات خارجية.
 * لا يُخزَّن أي سر داخل الكود - كل المفاتيح الحساسة تُقرأ من .env.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $vars = [];
    private static bool $loaded = false;

    public static function load(string $path): bool
    {
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }
        self::$vars = self::parse((string) file_get_contents($path));
        self::$loaded = true;
        return true;
    }

    /** @return array<string,string> */
    public static function parse(string $content): array
    {
        $vars = [];
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            if ($key === '' || !preg_match('/^[A-Z0-9_.]+$/i', $key)) {
                continue;
            }
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $quote = $value[0];
                $value = preg_replace('/^' . preg_quote($quote, '/') . '|' . preg_quote($quote, '/') . '$/', '', $value) ?? $value;
                if ($quote === '"') {
                    $value = str_replace(['\\n', '\\r', '\\t', '\\"'], ["\n", "\r", "\t", '"'], $value);
                }
            } else {
                // إزالة التعليقات في نهاية السطر للقيم غير المُقتبسة
                $hash = strpos($value, ' #');
                if ($hash !== false) {
                    $value = trim(substr($value, 0, $hash));
                }
            }
            $vars[$key] = $value;
        }
        // دعم الإحالة ${VAR} على قيم أخرى
        foreach ($vars as $k => $v) {
            $vars[$k] = preg_replace_callback('/\$\{([A-Z0-9_]+)\}/i', static fn(array $m): string => $vars[$m[1]] ?? '', $v) ?? $v;
        }
        return $vars;
    }

    public static function isLoaded(): bool
    {
        return self::$loaded;
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        return self::$vars;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$vars)) {
            return self::$vars[$key];
        }
        $fromEnv = getenv($key);
        if ($fromEnv !== false && $fromEnv !== '') {
            return $fromEnv;
        }
        return $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, null);
        if ($value === null || $value === '') {
            return $default;
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key, null);
        return ($value === null || $value === '') ? $default : (int) $value;
    }

    public static function set(string $key, string|int|bool|null $value): void
    {
        self::$vars[$key] = match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value === null    => '',
            default            => (string) $value,
        };
    }

    /**
     * كتابة ملف .env (يُستخدم من معالج التثبيت فقط) مع الحفاظ على التعليقات.
     * @param array<string,string> $values
     */
    public static function write(string $path, array $values, ?string $template = null): bool
    {
        $content = $template !== null && is_file($template)
            ? (string) file_get_contents($template)
            : '';

        foreach ($values as $key => $value) {
            $needsQuotes = $value !== '' && (preg_match('/[\s#"\']/', $value) === 1);
            $formatted = $needsQuotes ? '"' . str_replace('"', '\"', $value) . '"' : $value;
            $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
            if (preg_match($pattern, $content) === 1) {
                $content = (string) preg_replace($pattern, $key . '=' . $formatted, $content);
            } else {
                $content = rtrim($content) . "\n" . $key . '=' . $formatted . "\n";
            }
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return file_put_contents($path, $content, LOCK_EX) !== false;
    }
}
