<?php
/**
 * إنشاء حزمة جاهزة للنشر على السيرفر (ZIP).
 *
 *      php tools/build-package.php                          # يبني storage/build/lokmen-license-<تاريخ>.zip
 *      php tools/build-package.php --name=my.zip            # اسم مخصص
 *      php tools/build-package.php --out=/path/lokmen.zip   # مسار مخصص (يُنشأ المجلد إن لزم)
 *      php tools/build-package.php --with-tests             # تضمين الاختبارات وأدوات الفحص
 *
 * الحزمة تستثني: .git و .env و node_modules و storage/build وملفات السجلات المؤقتة،
 * وتضم: كل ملفات التشغيل + database.sql + install.php + docs + tools (عدا أدوات التطوير).
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

if (!is_cli()) {
    http_response_code(403);
    exit('هذا الملف يعمل من سطر الأوامر فقط.');
}

$withTests = cli_has_flag('with-tests');
$name = (string) cli_option('name', 'lokmen-license-' . date('Ymd-His') . '.zip');
$out = cli_option('out');
$buildDir = BASE_PATH . '/storage/build';
if ($out !== null && $out !== '') {
    // مسار كامل مطلوب صراحةً (مثال: dist/lokmen-license-v1.0.0.zip)
    $out = (string) $out;
    if (!str_starts_with($out, '/')) {
        $out = BASE_PATH . '/' . ltrim($out, '/');
    }
    $targetDir = dirname($out);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
        fwrite(STDERR, "تعذّر إنشاء المجلد: {$targetDir}\n");
        exit(1);
    }
    $zipPath = $out;
} else {
    if (!is_dir($buildDir) && !mkdir($buildDir, 0755, true)) {
        fwrite(STDERR, "تعذّر إنشاء مجلد storage/build\n");
        exit(1);
    }
    $zipPath = str_starts_with($name, '/') ? $name : $buildDir . '/' . basename($name);
}

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "إضافة zip غير مفعّلة في PHP.\n");
    fwrite(STDERR, "الحل: فعّل extension=zip من php.ini، أو اضغط المجلد يدوياً مع استثناء:\n");
    fwrite(STDERR, "  .git، .env، node_modules، storage/build، storage/logs/*، uploads/*\n");
    exit(1);
}

/** مجلدات/ملفات مستثناة من الحزمة */
$excluded = ['.git', '.github', 'node_modules', 'vendor', '.venv', '__pycache__',
             'storage/build', 'storage/logs', 'storage/cache', 'storage/backups', 'storage/tmp_pdf_test',
             'dist', '.env', '.DS_Store', 'Thumbs.db'];
// لا تُضمَّن حزمة نشر قديمة داخل الحزمة الجديدة أبداً
$excludedFiles = ['/^\.env\.(?!example$)/', '/\.log$/', '/\.tmp$/', '/\.zip$/', '/installed\.lock$/'];
if (!$withTests) {
    $excluded[] = 'tests';
    $excluded[] = 'tools/dev';
    $excluded[] = 'tools/preview';
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "تعذّر إنشاء ملف ZIP: {$zipPath}\n");
    exit(1);
}

$rootName = 'lokmen-license-platform';
$addedFiles = 0;
$skipped = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(BASE_PATH, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($iterator as $item) {
    /** @var SplFileInfo $item */
    $relative = ltrim(str_replace(BASE_PATH, '', $item->getPathname()), '/');
    if ($relative === '') {
        continue;
    }
    $skip = false;
    foreach ($excluded as $pattern) {
        if ($relative === $pattern || str_starts_with($relative, $pattern . '/')) {
            $skip = true;
            break;
        }
    }
    foreach ($excludedFiles as $pattern) {
        if (preg_match($pattern, '/' . basename($relative)) === 1) {
            $skip = true;
            break;
        }
    }
    // لا تُضمَّن محتويات مجلد الرفع (تبقى مجلداته فقط)
    if (str_starts_with($relative, 'uploads/') && !$item->isDir() && basename($relative) !== '.gitkeep' && basename($relative) !== '.htaccess') {
        $skip = true;
    }
    if ($skip) {
        $skipped++;
        continue;
    }
    $local = $rootName . '/' . $relative;
    if ($item->isDir()) {
        $zip->addEmptyDir($local);
        continue;
    }
    $zip->addFile($item->getPathname(), $local);
    $addedFiles++;
}

// احفظ مجلدات لا بد منها للعمل
foreach (['storage/logs', 'storage/cache', 'storage/build', 'uploads/payments', 'uploads/questions', 'uploads/receipts'] as $dir) {
    $zip->addEmptyDir($rootName . '/' . $dir);
}

$zip->close();
$size = filesize($zipPath);

echo "\n";
echo "  ✔ تم إنشاء حزمة النشر\n";
echo "  ─────────────────────────────────────────────\n";
echo '  الملف : ' . $zipPath . "\n";
echo '  الحجم : ' . number_format($size / 1048576, 2) . " ميجابايت\n";
echo '  الملفات: ' . $addedFiles . ' (تم استثناء ' . $skipped . " عنصراً)\n";
echo "\n  طريقة النشر على السيرفر:\n";
echo "  1) ارفع الحزمة عبر FTP/cPanel ثم فك الضغط في مجلد الموقع (public_html أو مجلد فرعي).\n";
echo "  2) أنشئ قاعدة بيانات MySQL من لوحة الاستضافة، وانسخ بياناتها.\n";
echo "  3) افتح https://your-domain.com/install.php وأكمل الخطوات.\n";
echo "  4) احذف install.php بعد نجاح التثبيت.\n";
if ($withTests) {
    echo "  5) (اختياري) على السيرفر: php tests/lint.php && php tests/run.php\n";
    echo "     وفحص الصفحات: php tools/dev/smoke.php --route=index.php\n";
}
echo "  الدليل الكامل: docs/التثبيت-على-سيرفر.md\n\n";
exit(0);
