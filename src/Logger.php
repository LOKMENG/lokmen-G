<?php
declare(strict_types=1);

namespace App;

/**
 * سجلات بسيطة إلى storage/logs مع دعم سياق JSON.
 * لا تُسجَّل كلمات المرور أو المفاتيح السرية في السجلات.
 */
final class Logger
{
    private const SENSITIVE = ['password', 'password_hash', 'password_confirmation', 'token', 'secret', 'api_key', 'current_password', 'bot_token'];

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        if (config('app.debug')) {
            self::write('debug', $message, $context);
        }
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    public static function security(string $message, array $context = []): void
    {
        self::write('security', $message, $context, 'security-');
    }

    public static function telegram(string $message, array $context = []): void
    {
        self::write('telegram', $message, $context, 'telegram-');
    }

    public static function path(string $prefix = 'app-'): string
    {
        $dir = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . '/' . $prefix . date('Y-m-d') . '.log';
    }

    private static function write(string $level, string $message, array $context = [], string $prefix = 'app-'): void
    {
        $context = self::redact($context);
        $line = sprintf(
            "[%s] %s: %s%s%s",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context === [] ? '' : ' | ',
            $context === [] ? '' : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        @file_put_contents(self::path($prefix), $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private static function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE, true)) {
                $context[$key] = '***';
            } elseif (is_array($value)) {
                $context[$key] = self::redact($value);
            }
        }
        return $context;
    }
}
