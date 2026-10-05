<?php
/** @var string $content */
/** @var string $title */
$user = user();
$pageStyles = $pageStyles ?? [];
$pageScripts = $pageScripts ?? [];
$breadcrumbs = $breadcrumbs ?? [];
?>
<!doctype html>
<html lang="ar" dir="rtl" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0b6b3a">
    <title><?= e($title ?? 'لوحة التحكم') ?> | <?= e(settings('site_name', config('app.name'))) ?></title>
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
<div class="pl-app">
    <?php require \App\View::path('partials/sidebar'); ?>
    <div class="pl-main">
        <?php require \App\View::path('partials/topbar'); ?>
        <main class="pl-content">
            <?php if (!empty($breadcrumbs)): ?>
                <nav aria-label="مسار التنقل" class="mb-2">
                    <ol class="breadcrumb mb-0 small">
                        <?php foreach ($breadcrumbs as $label => $href): ?>
                            <?php if ($href): ?>
                                <li class="breadcrumb-item"><a href="<?= e($href) ?>"><?= e($label) ?></a></li>
                            <?php else: ?>
                                <li class="breadcrumb-item active" aria-current="page"><?= e($label) ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </nav>
            <?php endif; ?>

            <?php if ($flashes = flashes()): ?>
                <?php foreach ($flashes as $flash): ?>
                    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert" data-auto-dismiss>
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?= $content ?>
        </main>
        <footer class="text-center py-3 small text-muted border-top">
            <?= e(settings('site_name', config('app.name'))) ?> © <?= e(date('Y')) ?> — الإصدار <?= e(config('app.version')) ?>
        </footer>
    </div>
</div>

<script src="<?= e(asset('assets/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('assets/js/chart.min.js')) ?>"></script>
<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
<?php foreach ($pageScripts as $script): ?>
    <script src="<?= e($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
