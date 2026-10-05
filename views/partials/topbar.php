<?php
$user = user();
$notifications = $user ? \App\Services\Notifier::forUser((int) $user['id'], 6) : [];
$isAdminArea = str_starts_with(current_path(), 'admin');
?>
<header class="pl-topbar">
    <button class="btn btn-outline-secondary btn-sm d-lg-none" type="button" onclick="plToggleSidebar()" aria-label="القائمة">
        <i class="bi bi-list"></i>
    </button>
    <div class="flex-grow-1 min-w-0">
        <h1 class="pl-page-title text-truncate"><?= e($title ?? 'لوحة التحكم') ?></h1>
        <?php if (!empty($pageSub)): ?>
            <p class="pl-page-sub text-truncate"><?= e($pageSub) ?></p>
        <?php endif; ?>
    </div>

    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="plToggleTheme()" aria-label="تبديل الوضع">
            <i class="bi bi-moon-stars" data-theme-icon></i>
        </button>

        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="الإشعارات">
                <i class="bi bi-bell"></i>
                <?php $unread = \App\Services\Notifier::unreadCount((int) $user['id']); ?>
                <?php if ($unread > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= e(ar_digits($unread)) ?></span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow" style="min-width: 320px;">
                <h6 class="dropdown-header">الإشعارات</h6>
                <?php if ($notifications === []): ?>
                    <div class="px-3 py-2 small text-muted">لا توجد إشعارات حالياً.</div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <a class="dropdown-item py-2 <?= (int) $notification['is_read'] === 0 ? 'bg-primary-soft' : '' ?>"
                           href="<?= e($notification['link'] ?: url('student/notifications')) ?>">
                            <div class="fw-semibold small"><?= e($notification['title']) ?></div>
                            <div class="small text-muted text-truncate" style="max-width: 280px;"><?= e(str_limit((string) $notification['body'], 60)) ?></div>
                            <div class="small text-muted"><?= e(format_date((string) $notification['created_at'], true)) ?></div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item text-center small" href="<?= e(url('student/notifications')) ?>">عرض كل الإشعارات</a>
            </div>
        </div>

        <div class="dropdown">
            <button class="btn btn-light btn-sm d-flex align-items-center gap-2 border" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="<?= e(avatar_url($user['avatar'] ?? null, (string) $user['full_name'])) ?>" alt="" class="pl-avatar" style="width:28px;height:28px;">
                <span class="d-none d-md-inline small fw-semibold"><?= e(str_limit((string) $user['full_name'], 18)) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li><a class="dropdown-item" href="<?= e(url('student/profile')) ?>"><i class="bi bi-person me-2"></i> الملف الشخصي</a></li>
                <li><a class="dropdown-item" href="<?= e(url('subscriptions/status')) ?>"><i class="bi bi-receipt me-2"></i> اشتراكي</a></li>
                <?php if (is_admin()): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="<?= e($isAdminArea ? url('student/dashboard') : url('admin/index')) ?>">
                            <i class="bi bi-arrow-left-right me-2"></i> <?= $isAdminArea ? 'واجهة الطالب' : 'لوحة الإدارة' ?>
                        </a>
                    </li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= e(url('auth/logout')) ?>"><i class="bi bi-box-arrow-right me-2"></i> تسجيل الخروج</a></li>
            </ul>
        </div>
    </div>
</header>
