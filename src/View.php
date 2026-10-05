<?php
declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * محرك قوالب بسيط: قوالب PHP + Layouts، بدون أي مكتبات خارجية.
 */
final class View
{
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function path(string $template): string
    {
        $root = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
        $safe = str_replace(['..', '\\'], '', $template);
        return $root . '/views/' . trim($safe, '/') . '.php';
    }

    public static function exists(string $template): bool
    {
        return is_file(self::path($template));
    }

    /** توليد محتوى قالب وإرجاعه كنص */
    public static function make(string $template, array $data = []): string
    {
        if (!self::exists($template)) {
            throw new RuntimeException('القالب غير موجود: ' . $template);
        }
        extract(array_merge(self::$shared, $data), EXTR_SKIP);
        ob_start();
        try {
            require self::path($template);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /** عرض قالب داخل Layout (أو بدون Layout إذا كان null) */
    public static function render(string $template, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $data = array_merge([
            'title'       => $data['title'] ?? config('app.name'),
            'pageStyles'  => $data['pageStyles'] ?? [],
            'pageScripts' => $data['pageScripts'] ?? [],
            'bodyClass'   => $data['bodyClass'] ?? '',
            'activeMenu'  => $data['activeMenu'] ?? '',
            'breadcrumbs' => $data['breadcrumbs'] ?? [],
        ], $data);

        $content = self::make($template, $data);
        if ($layout === null) {
            echo $content;
            return;
        }
        echo self::make($layout, array_merge($data, ['content' => $content]));
    }

    /** صفحة خطأ بالهوية البصرية للمنصة */
    public static function error(int $code, string $message = '', string $title = ''): void
    {
        http_response_code($code);
        $titles = [
            400 => 'طلب غير صالح',
            401 => 'يلزم تسجيل الدخول',
            403 => 'لا تملك صلاحية الوصول',
            404 => 'الصفحة غير موجودة',
            419 => 'انتهت صلاحية الجلسة',
            429 => 'محاولات كثيرة جداً',
            500 => 'خطأ في الخادم',
            503 => 'الخدمة غير متاحة مؤقتاً',
        ];
        try {
            self::render('errors/error', [
                'title'   => $title !== '' ? $title : ($titles[$code] ?? 'خطأ'),
                'code'    => $code,
                'message' => $message !== '' ? $message : ($titles[$code] ?? 'حدث خطأ غير متوقع'),
            ], 'layouts/public');
        } catch (\Throwable) {
            echo '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><title>خطأ ' . $code . '</title>'
                . '<body style="font-family:sans-serif;text-align:center;padding:3rem">'
                . '<h1>' . $code . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></body></html>';
        }
    }
}
