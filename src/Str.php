<?php
declare(strict_types=1);

namespace App;

/** أدوات نصوص عربية: تطبيع، تنظيف نتائج OCR، تقييد الطول */
final class Str
{
    /** تطبيع النص العربي: توحيد الألف والهمزات والتاء المربوطة وإزالة التشكيل والتطويل */
    public static function normalizeArabic(string $text): string
    {
        $text = self::stripDiacritics($text);
        $text = str_replace(['أ', 'إ', 'آ', 'ٱ', 'ٲ', 'ٳ'], 'ا', $text);
        $text = str_replace(['ى'], 'ي', $text);
        $text = str_replace(['ؤ'], 'و', $text);
        $text = str_replace(['ئ'], 'ي', $text);
        $text = str_replace(['ة'], 'ه', $text);
        $text = str_replace(["\u{0640}", "\u{200C}", "\u{200D}", "\u{200E}", "\u{200F}"], '', $text); // تطويل ومحارف تحكم
        $text = self::normalizeDigits($text);
        $text = str_replace(['«', '»', '“', '”', '‟', '„'], '"', $text);
        $text = str_replace(['–', '—', '―'], '-', $text);
        $text = preg_replace('/[ \t\x{00A0}\x{2000}-\x{200A}]+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    public static function stripDiacritics(string $text): string
    {
        return (string) preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $text);
    }

    /** توحيد الأرقام العربية/الفارسية إلى أرقام لاتينية */
    public static function normalizeDigits(string $text): string
    {
        $eastern = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $text = str_replace(array_merge($eastern, $persian), array_merge($western, $western), $text);
        // الفاصلة العشرية والفاصلة الألفية العربية
        return str_replace(["\u{066B}", "\u{066C}"], ['.', ','], $text);
    }

    /** بصمة موحّدة لمنع تكرار الأسئلة (يتجاهل الفروق الشكلية) */
    public static function contentFingerprint(string $text): string
    {
        $normalized = self::normalizeArabic($text);
        $normalized = mb_strtolower($normalized, 'UTF-8');
        $normalized = (string) preg_replace('/[^\p{L}\p{N} ]+/u', '', $normalized);
        $normalized = (string) preg_replace('/\s+/u', ' ', $normalized);
        return hash('sha256', trim($normalized));
    }

    public static function limit(?string $text, int $limit = 90, string $end = '…'): string
    {
        $text = trim((string) $text);
        if ($limit <= 0 || mb_strlen($text, 'UTF-8') <= $limit) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $limit, 'UTF-8')) . $end;
    }

    public static function arabicRatio(string $text): float
    {
        $letters = preg_replace('/[^\p{L}]/u', '', $text) ?? '';
        $total = mb_strlen($letters, 'UTF-8');
        if ($total === 0) {
            return 0.0;
        }
        $arabic = preg_match_all('/\p{Arabic}/u', $letters) ?: 0;
        return round(($arabic / $total) * 100, 2);
    }

    public static function hasArabic(string $text): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $text);
    }

    /** تحويل نص عربي إلى معرّف إنجليزي (slug) - يستخدم الكود اللاتيني عند توفره */
    public static function slug(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = (string) preg_replace('/[^a-z0-9\p{Arabic}]+/u', '-', $text);
        $text = trim($text, '-');
        return $text === '' ? 'item' : mb_substr($text, 0, 80);
    }

    /** تحويل رقم جوال سعودي إلى الصيغة الدولية 9665XXXXXXXX */
    public static function normalizeSaudiPhone(string $phone): string
    {
        $phone = self::normalizeDigits($phone);
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '00966')) {
            $digits = substr($digits, 5);
        } elseif (str_starts_with($digits, '966')) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        return '966' . $digits;
    }

    public static function maskPhone(string $phone): string
    {
        return strlen($phone) >= 6 ? substr($phone, 0, 5) . '****' . substr($phone, -2) : $phone;
    }

    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) < 2) {
            return $email;
        }
        $name = $parts[0];
        $visible = mb_substr($name, 0, 2);
        return $visible . str_repeat('*', max(2, mb_strlen($name) - 2)) . '@' . $parts[1];
    }

    /** إزالة الرموز التعبيرية والمحارف غير المرغوبة القادمة من PDF/OCR */
    public static function cleanOcrArtifacts(string $text): string
    {
        $text = str_replace(["\u{FFFD}", "\x{25A1}", "\x{25A0}"], ' ', $text); // محارف غير معروفة
        $text = (string) preg_replace('/[\p{So}\p{Sk}\x{1F000}-\x{1FAFF}]/u', '', $text); // رموز تعبيرية
        $text = (string) preg_replace('/(?<=\p{Arabic}) (?=\p{Arabic})/u', ' ', $text);
        $text = (string) preg_replace('/\s*\n\s*/u', "\n", $text);
        return trim($text);
    }
}
