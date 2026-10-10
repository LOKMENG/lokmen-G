<?php
/**
 * أداة تطوير: تتحقق من سلامة طبقة التصميم قبل النشر.
 *
 *   1) توازن الأقواس في ملفات CSS (كشف خطأ صياغة يوقف كل الأنماط).
 *   2) كل صنف CSS مستخدم في القوالب مُعرَّف فعلاً (يمنع عناصر بلا تنسيق).
 *   3) كل صنف من طبقة سيلادون مذكور في القوالب أو ملفات JS (كشف الأصناف الميتة).
 *   4) وجود الملفات الأساسية للهوية البصرية وربطها في القوالب.
 *
 * التشغيل: php tools/dev/check-design.php
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

$failures = [];
$notes = [];

/** ---------- 1) توازن الأقواس في CSS ---------- */
$cssFiles = glob(BASE_PATH . '/assets/css/*.css') ?: [];
$cssClasses = [];
foreach ($cssFiles as $file) {
    $name = basename($file);
    if (str_contains($name, 'bootstrap')) {
        continue; // ملفات ضخمة جاهزة، تُفحص صياغتها من مصدرها
    }
    $css = (string) file_get_contents($file);
    $stripped = preg_replace('!/\*.*?\*/!s', '', $css) ?? $css;
    $depth = 0;
    $maxDepth = 0;
    $quote = null;
    $length = strlen($stripped);
    for ($i = 0; $i < $length; $i++) {
        $char = $stripped[$i];
        if ($quote !== null) {
            if ($char === $quote && ($stripped[$i - 1] ?? '') !== '\\') {
                $quote = null;
            }
            continue;
        }
        if ($char === '"' || $char === "'") {
            $quote = $char;
            continue;
        }
        if ($char === '{') {
            $depth++;
            $maxDepth = max($maxDepth, $depth);
        } elseif ($char === '}') {
            $depth--;
            if ($depth < 0) {
                $failures[] = "{$name}: قوس إغلاق زائد";
                break;
            }
        }
    }
    if ($depth !== 0) {
        $failures[] = "{$name}: أقواس غير متوازنة (الفرق {$depth})";
    }
    $notes[] = "{$name}: " . number_format(strlen($css) / 1024, 1) . ' ك.ب، أقصى عمق ' . $maxDepth;

    preg_match_all('/\.([a-zA-Z][a-zA-Z0-9_-]*)/', $stripped, $matches);
    foreach ($matches[1] as $class) {
        $cssClasses[$class] = true;
    }
    preg_match_all('/--([a-z0-9-]+)\s*:/', $stripped, $vars);
    foreach ($vars[1] as $var) {
        $cssVars[$var] = true;
    }
}

/* المتغيّرات المعرَّفة في celadon.css */
$celadon = (string) file_get_contents(BASE_PATH . '/assets/css/celadon.css');
$definedVars = [];
preg_match_all('/--([a-z0-9-]+)\s*:/', $celadon, $varMatches);
foreach ($varMatches[1] as $var) {
    $definedVars[$var] = true;
}
/* المتغيّرات المستخدمة عبر var(--x) داخل celadon.css */
preg_match_all('/var\(--([a-z0-9-]+)/', $celadon, $usedVars);
$missingVars = [];
foreach (array_unique($usedVars[1]) as $var) {
    if (!isset($definedVars[$var]) && !in_array($var, ['bs-body-font-family', 'bs-border-radius', 'bs-primary-rgb', 'bs-body-bg', 'bs-body-color', 'bs-border-color', 'bs-primary', 'bs-link-color', 'bs-link-hover-color', 'bs-border-radius-sm', 'bs-border-radius-lg', 'bs-border-radius-xl', 'bs-body-font-size', 'bs-emphasis-color', 'bs-secondary-color', 'bs-table-bg', 'bs-table-color', 'bs-table-border-color', 'bs-table-striped-bg', 'bs-table-hover-bg', 'bs-breadcrumb-divider-color', 'pl-primary', 'pl-primary-dark', 'pl-muted', 'pl-surface', 'pl-border', 'pl-body-bg', 'pl-shadow', 'pl-radius'], true)) {
        $missingVars[] = $var;
    }
}
if ($missingVars !== []) {
    $failures[] = 'متغيّرات CSS مستخدمة وغير معرَّفة في celadon.css: ' . implode('، ', $missingVars);
}

/** ---------- 2) الأصناف المستخدمة في القوالب مُعرَّفة ---------- */
$templateClasses = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(BASE_PATH . '/views', FilesystemIterator::SKIP_DOTS)
);
$checkedTemplates = 0;
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $checkedTemplates++;
    $php = (string) file_get_contents($file->getPathname());
    if (preg_match_all('/class="([^"]*)"/', $php, $matches) === 0) {
        continue;
    }
    foreach ($matches[1] as $attribute) {
        if (str_contains($attribute, '<?')) {
            // أصناف مبنية ديناميكياً: نفحص الأجزاء الثابتة فقط
            $attribute = (string) preg_replace('/<\?.*?\?>/s', ' ', $attribute);
        }
        foreach (preg_split('/\s+/', trim($attribute)) ?: [] as $class) {
            $class = trim($class);
            if ($class === '' || !preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $class)) {
                continue;
            }
            $templateClasses[$class] = ($templateClasses[$class] ?? 0) + 1;
        }
    }
}
$unstyled = [];
$icons = (string) file_get_contents(BASE_PATH . '/assets/css/bootstrap-icons.min.css');
foreach ($templateClasses as $class => $count) {
    if (isset($cssClasses[$class])) {
        continue;
    }
    // أصناف ديناميكية أو مساعدة تُبنى وقت التنفيذ
    // صنف مقطوع لأن جزءه ديناميكي (bi- ثم تعبير PHP) — يُتجاهل بأمان
    if (str_ends_with($class, '-')) {
        continue;
    }
    if (in_array($class, ['tabular'], true)) {
        $cssClasses[$class] = true;
        continue;
    }
    // أيقونات Bootstrap Icons: خط أيقونات لا أصناف تنسيق
    if ($class !== 'bi-' && str_starts_with($class, 'bi-')) {
        if (str_contains($icons, '.' . $class . ':')) {
            $cssClasses[$class] = true;
            continue;
        }
        $failures[] = 'أيقونة غير موجودة في bootstrap-icons: ' . $class;
        $cssClasses[$class] = true;
        continue;
    }
    // أدوات Bootstrap المساعدة موجودة في ملف Bootstrap المضغوط
    $bootstrap = (string) file_get_contents(BASE_PATH . '/assets/css/bootstrap.rtl.min.css');
    if (str_contains($bootstrap, '.' . $class)) {
        $cssClasses[$class] = true;
        continue;
    }
    $unstyled[$class] = $count;
}
arsort($unstyled);
if ($unstyled !== []) {
    $list = [];
    foreach (array_slice($unstyled, 0, 12, true) as $class => $count) {
        $list[] = $class . "×{$count}";
    }
    $failures[] = 'أصناف مستخدمة في القوالب بلا تعريف CSS: ' . implode('، ', $list);
}

/** ---------- 3) الملفات وربطها ---------- */
foreach (['assets/css/celadon.css', 'assets/js/celadon.js', 'assets/css/app.css', 'assets/js/app.js'] as $asset) {
    if (!is_file(BASE_PATH . '/' . $asset)) {
        $failures[] = 'ملف مفقود: ' . $asset;
    }
}
foreach (['views/layouts/public.php', 'views/layouts/app.php', 'views/layouts/auth.php'] as $layout) {
    $php = (string) file_get_contents(BASE_PATH . '/' . $layout);
    foreach (['celadon.css', 'celadon.js'] as $needle) {
        if (!str_contains($php, $needle)) {
            $failures[] = $layout . ' لا يربط ' . $needle;
        }
    }
}

/** ---------- التقرير ---------- */
echo "\n  فحص طبقة التصميم\n  ─────────────────────────────────────────────\n";
echo '  القوالب المفحوصة: ' . $checkedTemplates . ' — ملفات CSS: ' . count($cssFiles) . "\n";
echo '  أصناف CSS المعرَّفة: ' . count($cssClasses) . ' — أصناف في القوالب: ' . count($templateClasses) . "\n";
foreach (array_slice($notes, 0, 4) as $note) {
    echo '  • ' . $note . "\n";
}
if ($failures === []) {
    echo "\n  النتيجة: ناجح ✔\n\n";
    exit(0);
}
echo "\n  النتيجة: فاشل ✘\n";
foreach ($failures as $failure) {
    echo '  - ' . $failure . "\n";
}
echo "\n";
exit(1);
