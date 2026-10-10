<?php
/** القائمة الجانبية - تختلف حسب دور المستخدم */
$role = user()['role'] ?? 'student';
$isAdminArea = str_starts_with(current_path(), 'admin');
$unread = user() ? \App\Services\Notifier::unreadCount((int) user()['id']) : 0;
$subStatus = user() && !$isAdminArea ? \App\Services\SubscriptionService::status((int) user()['id']) : '';
?>
<aside class="pl-sidebar" id="plSidebar">
    <a class="pl-sidebar-brand" href="<?= e(url('')) ?>">
        <span class="pl-logo"><i class="bi bi-mortarboard-fill"></i></span>
        <span><?= e(settings('site_name', config('app.name'))) ?></span>
    </a>

    <div class="p-3 border-bottom border-light border-opacity-10">
        <div class="d-flex align-items-center gap-2">
            <img src="<?= e(avatar_url(user()['avatar'] ?? null, (string) (user()['full_name'] ?? ''))) ?>" alt="الصورة الرمزية" class="pl-avatar">
            <div class="flex-grow-1 min-w-0">
                <div class="text-white fw-bold text-truncate small"><?= e(user()['full_name'] ?? '') ?></div>
                <div class="small text-white-50 text-truncate">
                    <?= $role === 'admin' ? 'مدير المنصة' : ($role === 'supervisor' ? 'مشرف محتوى' : 'طالب') ?>
                </div>
            </div>
        </div>
        <?php if (!$isAdminArea && $subStatus !== ''): ?>
            <div class="mt-2 small">
                <?php if ($subStatus === 'active'): ?>
                    <span class="badge bg-success-subtle text-success-emphasis w-100">
                        <i class="bi bi-patch-check-fill me-1"></i> اشتراك نشط —
                        متبقٍ <?= e(ar_digits(\App\Services\SubscriptionService::daysRemaining((int) user()['id']))) ?> يوماً
                    </span>
                <?php else: ?>
                    <span class="badge bg-warning-subtle text-warning-emphasis w-100">
                        <i class="bi bi-exclamation-circle me-1"></i> الاشتراك غير فعّال
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <nav class="pl-sidebar-nav">
        <?php if ($isAdminArea): ?>
            <div class="nav-section">لوحة الإدارة</div>
            <a class="nav-link <?= current_path() === 'admin/index' ? 'active' : '' ?>" href="<?= e(url('admin/index')) ?>">
                <i class="bi bi-speedometer2"></i> النظرة العامة
            </a>
            <a class="nav-link <?= is_active_menu('admin/statistics') ? 'active' : '' ?>" href="<?= e(url('admin/statistics')) ?>">
                <i class="bi bi-graph-up"></i> الإحصائيات والتقارير
            </a>
            <div class="nav-section">المستخدمون والاشتراكات</div>
            <a class="nav-link <?= is_active_menu('admin/users') ? 'active' : '' ?>" href="<?= e(url('admin/users')) ?>">
                <i class="bi bi-people"></i> المستخدمون
            </a>
            <a class="nav-link <?= is_active_menu('admin/subscriptions') ? 'active' : '' ?>" href="<?= e(url('admin/subscriptions')) ?>">
                <i class="bi bi-card-checklist"></i> الاشتراكات
            </a>
            <a class="nav-link <?= is_active_menu('admin/payments') ? 'active' : '' ?>" href="<?= e(url('admin/payments')) ?>">
                <i class="bi bi-credit-card"></i> المدفوعات
                <?php
                $pendingPayments = (int) db()->value("SELECT COUNT(*) FROM `payments` WHERE status = 'pending'", [], 0);
                ?>
                <?php if ($pendingPayments > 0): ?><span class="badge bg-danger"><?= e(ar_digits($pendingPayments)) ?></span><?php endif; ?>
            </a>
            <div class="nav-section">بنك الأسئلة</div>
            <a class="nav-link <?= is_active_menu('admin/questions') ? 'active' : '' ?>" href="<?= e(url('admin/questions')) ?>">
                <i class="bi bi-question-square"></i> الأسئلة
            </a>
            <a class="nav-link <?= is_active_menu('admin/question-form') ? 'active' : '' ?>" href="<?= e(url('admin/question-form')) ?>">
                <i class="bi bi-plus-circle"></i> إضافة سؤال
            </a>
            <a class="nav-link <?= is_active_menu('admin/import') ? 'active' : '' ?>" href="<?= e(url('admin/import')) ?>">
                <i class="bi bi-file-earmark-arrow-up"></i> استيراد من PDF / CSV
            </a>
            <a class="nav-link <?= is_active_menu('admin/taxonomy') ? 'active' : '' ?>" href="<?= e(url('admin/taxonomy')) ?>">
                <i class="bi bi-diagram-3"></i> المسارات والتصنيفات
            </a>
            <a class="nav-link <?= is_active_menu('admin/reports-queue') ? 'active' : '' ?>" href="<?= e(url('admin/reports-queue')) ?>">
                <i class="bi bi-flag"></i> بلاغات الأسئلة
            </a>
            <div class="nav-section">الاختبارات والنظام</div>
            <a class="nav-link <?= is_active_menu('admin/exams') ? 'active' : '' ?>" href="<?= e(url('admin/exams')) ?>">
                <i class="bi bi-journal-check"></i> قوالب الاختبارات
            </a>
            <a class="nav-link <?= is_active_menu('admin/support') ? 'active' : '' ?>" href="<?= e(url('admin/support')) ?>">
                <i class="bi bi-headset"></i> الدعم الفني
            </a>
            <a class="nav-link <?= is_active_menu('admin/telegram') ? 'active' : '' ?>" href="<?= e(url('admin/telegram')) ?>">
                <i class="bi bi-telegram"></i> تلجرام
            </a>
            <a class="nav-link <?= is_active_menu('admin/testimonials') ? 'active' : '' ?>" href="<?= e(url('admin/testimonials')) ?>">
                <i class="bi bi-chat-quote"></i> <span>شهادات المتدربين</span>
            </a>
            <a class="nav-link <?= is_active_menu('admin/settings') ? 'active' : '' ?>" href="<?= e(url('admin/settings')) ?>">
                <i class="bi bi-gear"></i> الإعدادات
            </a>
            <a class="nav-link <?= is_active_menu('admin/audit') ? 'active' : '' ?>" href="<?= e(url('admin/audit')) ?>">
                <i class="bi bi-shield-lock"></i> سجل العمليات
            </a>
        <?php else: ?>
            <div class="nav-section">الرئيسية</div>
            <a class="nav-link <?= current_path() === 'student/dashboard' ? 'active' : '' ?>" href="<?= e(url('student/dashboard')) ?>">
                <i class="bi bi-speedometer2"></i> لوحتي
            </a>
            <a class="nav-link <?= is_active_menu('student/statistics') ? 'active' : '' ?>" href="<?= e(url('student/statistics')) ?>">
                <i class="bi bi-bar-chart-line"></i> تحليل مستواي
            </a>
            <div class="nav-section">التدريب والاختبارات</div>
            <a class="nav-link <?= is_active_menu('exams') && !is_active_menu('exams/history') ? 'active' : '' ?>" href="<?= e(url('exams/index')) ?>">
                <i class="bi bi-journal-text"></i> الاختبارات التجريبية
            </a>
            <a class="nav-link <?= is_active_menu('questions/practice') ? 'active' : '' ?>" href="<?= e(url('questions/practice')) ?>">
                <i class="bi bi-lightning-charge"></i> تدريب سريع
            </a>
            <a class="nav-link <?= is_active_menu('questions/index') ? 'active' : '' ?>" href="<?= e(url('questions/index')) ?>">
                <i class="bi bi-collection"></i> التدريب حسب المجال
            </a>
            <a class="nav-link <?= is_active_menu('exams/history') ? 'active' : '' ?>" href="<?= e(url('exams/history')) ?>">
                <i class="bi bi-clock-history"></i> سجل اختباراتي
            </a>
            <div class="nav-section">الحساب</div>
            <a class="nav-link <?= is_active_menu('subscriptions/status') ? 'active' : '' ?>" href="<?= e(url('subscriptions/status')) ?>">
                <i class="bi bi-receipt"></i> حالة الاشتراك
            </a>
            <a class="nav-link <?= is_active_menu('student/notifications') ? 'active' : '' ?>" href="<?= e(url('student/notifications')) ?>">
                <i class="bi bi-bell"></i> الإشعارات
                <?php if ($unread > 0): ?><span class="badge bg-danger"><?= e(ar_digits($unread)) ?></span><?php endif; ?>
            </a>
            <a class="nav-link <?= is_active_menu('student/telegram') ? 'active' : '' ?>" href="<?= e(url('student/telegram')) ?>">
                <i class="bi bi-telegram"></i> ربط تلجرام
            </a>
            <a class="nav-link <?= is_active_menu('student/profile') ? 'active' : '' ?>" href="<?= e(url('student/profile')) ?>">
                <i class="bi bi-person-gear"></i> الملف الشخصي
            </a>
            <a class="nav-link <?= is_active_menu('student/support') ? 'active' : '' ?>" href="<?= e(url('student/support')) ?>">
                <i class="bi bi-headset"></i> الدعم الفني
            </a>
        <?php endif; ?>
    </nav>

    <div class="pl-sidebar-footer">
        <?php if (is_admin()): ?>
            <?php if ($isAdminArea): ?>
                <a class="text-white-50 d-block mb-1" href="<?= e(url('student/dashboard')) ?>"><i class="bi bi-arrow-left-right me-1"></i> الذهاب إلى واجهة الطالب</a>
            <?php else: ?>
                <a class="text-white-50 d-block mb-1" href="<?= e(url('admin/index')) ?>"><i class="bi bi-shield-check me-1"></i> لوحة الإدارة</a>
            <?php endif; ?>
        <?php endif; ?>
        <a class="text-white-50 d-block" href="<?= e(url('auth/logout')) ?>"><i class="bi bi-box-arrow-right me-1"></i> تسجيل الخروج</a>
    </div>
</aside>
