<?php
/**
 * فحص صحة الصياغة (Syntax) لكل ملفات PHP في المشروع.
 * التشغيل:  php tests/lint.php
 * ملاحظة: يستخدم token_get_all مع TOKEN_PARSE ليعمل بدون الحاجة إلى تشغيل ملفات `php -l` خارجية.
 */
declare(strict_types=1);

$root = getenv('APP_ROOT') ?: dirname(__DIR__);
$skipDirs = ['/node_modules/', '/vendor/', '/.git/', '/tools/preview/node_modules/', '/tests/tmp/'];
$errors = [];
$checked = 0;
$start = microtime(true);

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($iterator as $file) {
    /** @var SplFileInfo $file */
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    foreach ($skipDirs as $skip) {
        if (str_contains($path, $skip)) {
            continue 2;
        }
    }
    $code = (string) file_get_contents($path);
    $checked++;
    try {
        token_get_all($code, TOKEN_PARSE);
    } catch (ParseError $e) {
        $errors[] = [$path, $e->getMessage() . ' (سطر ' . $e->getLine() . ')'];
        continue;
    }
    if (str_starts_with($code, "\xEF\xBB\xBF")) {
        $errors[] = [$path, 'يحتوي على BOM في بداية الملف (قد يفسد ترويسات HTTP)'];
    }
    // وسم الإغلاق في نهاية الملف غير مستحسن في ملفات الكود (قد يسبب إرسال مخرجات بالخطأ)،
    // أما ملفات القوالب (views) فتنتهي به بشكل طبيعي.
    if (!str_contains($path, '/views/') && preg_match('/\?>\s*$/', $code) === 1) {
        $errors[] = [$path, 'يحتوي على وسم إغلاق ?> في نهاية الملف (غير مستحسن في ملفات الكود)'];
    }
}

$elapsed = round(microtime(true) - $start, 2);
echo "فحص الصياغة: {$checked} ملفاً في {$elapsed} ثانية\n";
if ($errors === []) {
    echo "النتيجة: لا توجد أخطاء صياغة ✔\n";
    exit(0);
}
echo "النتيجة: " . count($errors) . " مشكلة ✘\n";
foreach ($errors as [$path, $message]) {
    echo ' - ' . str_replace($root, '', $path) . ': ' . $message . "\n";
}
exit(1);
