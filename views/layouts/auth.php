<?php
/** @var string $content */
$pageStyles = $pageStyles ?? [];
?>
<!doctype html>
<html lang="ar" dir="rtl" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($title ?? 'تسجيل الدخول') ?> | <?= e(settings('site_name', config('app.name'))) ?></title>
    <script>
        (function () {
            var theme = localStorage.getItem('pl-theme');
            if (theme) { document.documentElement.setAttribute('data-bs-theme', theme); }
        })();
    </script>
    <link rel="stylesheet" href="<?= e(asset('assets/css/bootstrap.rtl.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <?php foreach ($pageStyles as $style): ?>
        <link rel="stylesheet" href="<?= e($style) ?>">
    <?php endforeach; ?>
    <link rel="icon" href="<?= e(asset('assets/img/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body>
<div class="pl-auth-wrap">
    <div class="col-lg-5 pl-auth-side">
        <a href="<?= e(url('')) ?>" class="pl-brand text-white d-flex align-items-center gap-2 mb-4">
            <span class="pl-logo"><i class="bi bi-mortarboard-fill"></i></span>
            <?= e(settings('site_name', config('app.name'))) ?>
        </a>
        <h2 class="fw-bold mb-3">استعد لاختبار الرخصة المهنية بثقة</h2>
        <p class="mb-4 opacity-75">بنك أسئلة للاختبار التخصصي (حاسب آلي) والاختبار التربوي العام، اختبارات تجريبية، وتحليل مستوى يساعدك على معرفة نقاط قوتك وضعفك.</p>
        <ul class="pl-check-list text-white">
            <li>أسئلة مصنّفة حسب المجال والمحور والصعوبة</li>
            <li>اختبارات تجريبية بنفس نمط الاختبار الرسمي</li>
            <li>مراجعة الإجابات مع الشرح التفصيلي</li>
            <li>تقارير أداء ونقاط قوة وضعف لكل مجال</li>
        </ul>
    </div>
    <div class="col-lg-7 d-flex align-items-center justify-content-center p-4">
        <div class="w-100" style="max-width: 520px;">
            <?php if ($flashes = flashes()): ?>
                <?php foreach ($flashes as $flash): ?>
                    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert" data-auto-dismiss>
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <div class="pl-auth-card">
                <?= $content ?>
            </div>
            <div class="text-center mt-3">
                <button class="btn btn-link btn-sm text-decoration-none" type="button" onclick="plToggleTheme()">
                    <i class="bi bi-moon-stars me-1" data-theme-icon></i> تبديل الوضع الليلي
                </button>
            </div>
        </div>
    </div>
</div>
<script src="<?= e(asset('assets/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
</body>
</html>
