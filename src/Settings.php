<?php
declare(strict_types=1);

namespace App;

use Throwable;

/**
 * إعدادات المنصة القابلة للتعديل من لوحة التحكم (جدول settings).
 * القيم الحساسة (توكن تلجرام ومفاتيح الدفع) تبقى في .env ولا تُخزَّن هنا.
 */
final class Settings
{
    /** @var array<string,mixed>|null */
    private static ?array $cache = null;

    /** @return array<string,mixed> */
    public static function all(bool $fresh = false): array
    {
        if (self::$cache !== null && !$fresh) {
            return self::$cache;
        }
        try {
            $rows = Database::instance()->all('SELECT setting_key, setting_value, setting_type FROM `settings`');
        } catch (Throwable) {
            // قبل التثبيت أو عند تعذّر الاتصال: قيم افتراضية
            return self::defaults();
        }
        if ($rows === []) {
            return self::defaults();
        }
        $settings = [];
        foreach ($rows as $row) {
            $settings[(string) $row['setting_key']] = self::cast((string) $row['setting_value'], (string) $row['setting_type']);
        }
        self::$cache = array_merge(self::defaults(), $settings);
        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        return $all[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $db = Database::instance();
        $existing = $db->value('SELECT id FROM `settings` WHERE setting_key = :k', ['k' => $key]);
        $type = match (true) {
            is_bool($value) => 'bool',
            is_int($value)  => 'int',
            is_array($value) => 'json',
            default          => 'string',
        };
        $stored = match (true) {
            is_bool($value)  => $value ? '1' : '0',
            is_array($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
            default           => (string) $value,
        };
        if ($existing) {
            $db->update('settings', ['setting_value' => $stored, 'setting_type' => $type], 'setting_key = :k', ['k' => $key]);
        } else {
            $db->insert('settings', ['setting_key' => $key, 'setting_value' => $stored, 'setting_type' => $type]);
        }
        self::flush();
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    /** @return array<string,mixed> */
    public static function group(string $group): array
    {
        try {
            $rows = Database::instance()->all(
                'SELECT setting_key, setting_value, setting_type, label_ar FROM `settings` WHERE setting_group = :g ORDER BY id',
                ['g' => $group]
            );
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'key'   => (string) $row['setting_key'],
                'value' => self::cast((string) $row['setting_value'], (string) $row['setting_type']),
                'type'  => (string) $row['setting_type'],
                'label' => (string) ($row['label_ar'] ?? $row['setting_key']),
            ];
        }
        return $out;
    }

    private static function cast(string $value, string $type): mixed
    {
        return match ($type) {
            'int'  => (int) $value,
            'bool' => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true),
            'json' => json_decode($value, true) ?? [],
            default => $value,
        };
    }

    /** @return array<string,mixed> */
    private static function defaults(): array
    {
        return [
            'site_name'             => config('app.name'),
            'site_tagline'          => 'استعد لاختبار الرخصة المهنية للمعلمين بثقة',
            'site_description'      => 'منصة سعودية للتدريب على اختبار الرخصة المهنية للمعلمين.',
            'subscription_price'    => (int) Env::int('SUBSCRIPTION_PRICE', 100),
            'subscription_days'     => (int) Env::int('SUBSCRIPTION_DAYS', 365),
            'strength_threshold'    => 80,
            'weakness_threshold'    => 60,
            'exam_default_pass'     => 60,
            'telegram_bot_username' => (string) config('telegram.username'),
            'manual_payment_enabled'=> true,
            'contact_email'         => '',
            'contact_phone'         => '',
            'bank_name'             => '',
            'bank_iban'             => '',
            'bank_account_name'     => '',
            'stc_pay_number'        => '',
            'payment_instructions'  => '',
            'maintenance_mode'      => false,
            'allow_guest_exam'      => false,
            'telegram_notify_expiry_days' => 7,
        ];
    }
}
