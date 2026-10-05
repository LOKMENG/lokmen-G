<?php
/** @var string $content */
/** @var string $title */
$pageStyles = $pageStyles ?? [];
$pageScripts = $pageScripts ?? [];
?>
<!doctype html>
<html lang="ar" dir="rtl" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="<?= e(settings('site_description', 'منصة سعودية للتدريب على اختبار الرخصة المهنية للمعلمين')) ?>">
    <meta name="theme-color" content="#0b6b3a">
    <title><?= e($title ?? config('app.name')) ?> | <?= e(settings('site_name', config('app.name'))) ?></title>
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
<body class="<?= e($bodyClass ?? '') ?>">
<nav class="navbar navbar-expand-lg pl-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e(url('')) ?>">
            <span class="pl-logo"><i class="bi bi-mortarboard-fill"></i></span>
            <span><?= e(settings('site_name', config('app.name'))) ?></span>
        </a>
        <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#plPublicNav" aria-label="القائمة">
            <i class="bi bi-list fs-3"></i>
        </button>
        <div class="collapse navbar-collapse" id="plPublicNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <li class="nav-item"><a class="nav-link" href="<?= e(url('')) ?>#tracks">المسارات</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('')) ?>#features">المزايا</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('')) ?>#pricing">الأسعار</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('')) ?>#faq">الأسئلة الشائعة</a></li>
                <li class="nav-item">
                    <button class="btn btn-link nav-link" type="button" onclick="plToggleTheme()" aria-label="الوضع الليلي">
                        <i class="bi bi-moon-stars" data-theme-icon></i>
                    </button>
                </li>
                <?php if (auth()->check()): ?>
                    <li class="nav-item">
                        <a class="btn btn-light btn-sm px-3" href="<?= e(is_admin() ? url('admin/index') : url('student/dashboard')) ?>">
                            <i class="bi bi-speedometer2 me-1"></i> لوحتي
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('auth/login')) ?>">تسجيل الدخول</a></li>
                    <li class="nav-item">
                        <a class="btn btn-gold btn-sm px-3" href="<?= e(url('auth/register')) ?>">
                            <i class="bi bi-person-plus me-1"></i> إنشاء حساب
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php if ($flashes = flashes()): ?>
    <div class="container mt-3">
        <?php foreach ($flashes as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert" data-auto-dismiss>
                <?= e($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?= $content ?>

<footer class="pl-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-5">
                <h5 class="text-white mb-2"><i class="bi bi-mortarboard-fill me-2"></i><?= e(settings('site_name', config('app.name'))) ?></h5>
                <p class="small mb-2"><?= e(settings('site_description', '')) ?></p>
                <p class="small mb-0 text-white-50">
                    <i class="bi bi-info-circle me-1"></i>
                    التصنيفات داخل المنصة تنظيمية مرنة قابلة للتعديل، وليست تصنيفاً رسمياً معلناً من أي جهة.
                </p>
            </div>
            <div class="col-6 col-lg-3">
                <h6 class="text-white">روابط سريعة</h6>
                <ul class="list-unstyled small">
                    <li class="mb-1"><a href="<?= e(url('auth/login')) ?>">تسجيل الدخول</a></li>
                    <li class="mb-1"><a href="<?= e(url('auth/register')) ?>">إنشاء حساب جديد</a></li>
                    <li class="mb-1"><a href="<?= e(url('subscriptions/plans')) ?>">باقات الاشتراك</a></li>
                    <li class="mb-1"><a href="<?= e(url('')) ?>#faq">الأسئلة الشائعة</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-4">
                <h6 class="text-white">تواصل معنا</h6>
                <ul class="list-unstyled small">
                    <?php if (trim((string) settings('contact_phone', '')) !== ''): ?>
                        <li class="mb-1"><i class="bi bi-whatsapp me-1"></i> <?= e(settings('contact_phone')) ?></li>
                    <?php endif; ?>
                    <?php if (trim((string) settings('contact_email', '')) !== ''): ?>
                        <li class="mb-1"><i class="bi bi-envelope me-1"></i> <?= e(settings('contact_email')) ?></li>
                    <?php endif; ?>
                    <?php if (trim((string) settings('telegram_bot_username', '')) !== ''): ?>
                        <li class="mb-1">
                            <i class="bi bi-telegram me-1"></i>
                            <a href="https://t.me/<?= e(ltrim((string) settings('telegram_bot_username'), '@')) ?>" target="_blank" rel="noopener">بوت تلجرام</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <hr class="border-light opacity-25">
        <div class="d-flex flex-wrap justify-content-between align-items-center small">
            <span>© <?= e(date('Y')) ?> <?= e(settings('site_name', config('app.name'))) ?> — جميع الحقوق محفوظة.</span>
            <span class="text-white-50">الإصدار <?= e(config('app.version')) ?></span>
        </div>
    </div>
</footer>

<script src="<?= e(asset('assets/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('assets/js/chart.min.js')) ?>"></script>
<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
<?php foreach ($pageScripts as $script): ?>
    <script src="<?= e($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
