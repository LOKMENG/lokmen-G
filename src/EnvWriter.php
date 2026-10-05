<?php
declare(strict_types=1);

namespace App;

/**
 * كتابة ملف .env بأمان (يُستخدم في معالج التثبيت ولوحة الإدارة).
 *
 * - يدمج القيم داخل قالب .env.example مع الحفاظ على التعليقات العربية.
 * - يرفض أي مفتاح غير مطابق لصيغة متغيرات البيئة.
 * - يمنع حقن أسطر جديدة داخل القيم (لا يمكن إضافة مفتاح مزيف عبر قيمة مُدخلة).
 * - يقتبس القيم التي تحتوي مسافات أو # أو علامات اقتباس.
 * - يكتب الملف كتابة ذرّية ثم يضبط صلاحياته على 0600.
 */
final class EnvWriter
{
    /** @param array<string,string> $values */
    public static function write(string $path, array $values, ?string $template = null): bool
    {
        $content = ($template !== null && is_file($template))
            ? (string) file_get_contents($template)
            : '';

        foreach ($values as $key => $value) {
            if (preg_match('/^[A-Z0-9_]+$/', (string) $key) !== 1) {
                throw new \InvalidArgumentException('مفتاح غير صالح في ملف .env: ' . $key);
            }
            $formatted = self::format((string) $value);
            $pattern = '/^' . preg_quote((string) $key, '/') . '=.*$/m';
            if (preg_match($pattern, $content) === 1) {
                $content = (string) preg_replace($pattern, $key . '=' . $formatted, $content);
            } else {
                $content = rtrim($content) . "\n" . $key . '=' . $formatted . "\n";
            }
        }

        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }

        $temp = $path . '.tmp' . bin2hex(random_bytes(4));
        if (file_put_contents($temp, $content, LOCK_EX) === false) {
            return false;
        }
        @chmod($temp, 0600);
        if (!@rename($temp, $path)) {
            @unlink($temp);
            return false;
        }
        @chmod($path, 0600);
        return true;
    }

    /** تنسيق قيمة .env مع منع الأسطر الجديدة */
    public static function format(string $value): string
    {
        // منع حقن أسطر أو تعليقات جديدة من داخل القيمة
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/[\s#"\']/', $value) === 1) {
            return '"' . str_replace('"', '\"', $value) . '"';
        }
        return $value;
    }
}
