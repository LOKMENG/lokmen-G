<?php
/** @var array $paginated @var array $filters @var array $actions */
$actionLabels = [
    'login' => 'تسجيل دخول', 'logout' => 'تسجيل خروج', 'register' => 'تسجيل حساب',
    'password.reset' => 'استعادة كلمة المرور', 'password.changed' => 'تغيير كلمة المرور',
    'profile.updated' => 'تحديث الملف الشخصي', 'question.reported' => 'بلاغ عن سؤال',
    'subscription.cancelled_by_user' => 'إلغاء طلب اشتراك',
    'admin.settings_updated' => 'تحديث الإعدادات', 'admin.user_created' => 'إنشاء مستخدم',
    'admin.user_updated' => 'تحديث مستخدم', 'admin.user_deleted' => 'حذف مستخدم',
    'admin.user_status_changed' => 'تغيير حالة مستخدم',
    'admin.question_created' => 'إضافة سؤال', 'admin.question_updated' => 'تحديث سؤال',
    'admin.questions_deleted' => 'حذف أسئلة', 'admin.report_resolved' => 'معالجة بلاغ',
    'admin.report_rejected' => 'رفض بلاغ', 'admin.ticket_replied' => 'الرد على تذكرة',
    'admin.template_created' => 'إنشاء قالب اختبار', 'admin.template_updated' => 'تحديث قالب اختبار',
    'admin.template_deleted' => 'حذف قالب اختبار', 'admin.import_created' => 'بدء استيراد',
    'admin.import_approved' => 'اعتماد استيراد', 'admin.import_rejected' => 'رفض استيراد',
];
?>
<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-body">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label small" for="q">بحث في نوع العملية</label>
                        <input type="text" class="form-control form-control-sm" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="مثال: admin.question">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small" for="user">معرّف المستخدم المنفّذ</label>
                        <input type="number" class="form-control form-control-sm" id="user" name="user" min="1" value="<?= (int) $filters['user'] ?: '' ?>">
                    </div>
                    <div class="col-md-3"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-search me-1"></i> بحث</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-bar-chart text-primary me-2"></i> أكثر العمليات</div>
            <div class="card-body d-flex flex-wrap gap-1 align-content-start">
                <?php foreach ($actions as $action): ?>
                    <a href="<?= e(url('admin/audit', ['q' => $action['action']])) ?>" class="badge bg-light text-dark border text-decoration-none">
                        <?= e($actionLabels[$action['action']] ?? $action['action']) ?>
                        <span class="text-muted">(<?= e(ar_digits((int) $action['c'])) ?>)</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-shield-check text-primary me-2"></i> السجل (<?= e(ar_digits($paginated['total'])) ?>)</div>
    <div class="card-body p-0">
        <?php if ($paginated['rows'] === []): ?>
            <div class="pl-empty"><i class="bi bi-clock-history"></i> لا توجد عمليات مسجّلة مطابقة.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead><tr><th>#</th><th>العملية</th><th>المنفّذ</th><th>الكيان</th><th class="d-none d-lg-table-cell">التفاصيل</th><th class="d-none d-md-table-cell">IP</th><th>الوقت</th></tr></thead>
                    <tbody>
                        <?php foreach ($paginated['rows'] as $log): ?>
                            <?php
                            $meta = json_decode((string) ($log['meta'] ?? ''), true);
                            $metaText = is_array($meta) && $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : '—';
                            ?>
                            <tr>
                                <td class="small text-muted"><?= e(ar_digits((int) $log['id'])) ?></td>
                                <td class="small">
                                    <div class="fw-semibold"><?= e($actionLabels[$log['action']] ?? $log['action']) ?></div>
                                    <div class="text-muted" dir="ltr" style="font-size:.7rem;"><?= e((string) $log['action']) ?></div>
                                </td>
                                <td class="small">
                                    <?php if ($log['user_id'] !== null): ?>
                                        <a href="<?= e(url('admin/user-form', ['id' => (int) $log['user_id']])) ?>" class="text-decoration-none">
                                            <?= e((string) ($log['full_name'] ?? 'مستخدم #' . $log['user_id'])) ?>
                                        </a>
                                        <div class="text-muted"><?= e((string) ($log['email'] ?? '')) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">النظام</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <?= e((string) ($log['entity_type'] ?? '—')) ?>
                                    <?= $log['entity_id'] !== null ? '#' . e(ar_digits((int) $log['entity_id'])) : '' ?>
                                </td>
                                <td class="small d-none d-lg-table-cell pl-copy" style="max-width:280px; word-break:break-all; font-size:.72rem;"><?= e($metaText) ?></td>
                                <td class="small text-muted d-none d-md-table-cell pl-copy"><?= e((string) ($log['ip'] ?? '—')) ?></td>
                                <td class="small text-muted text-nowrap"><?= e(format_date((string) $log['created_at'], true)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php if ((int) $paginated['pages'] > 1): ?>
        <div class="card-footer"><?= pagination((int) $paginated['page'], (int) $paginated['pages'], $filters) ?></div>
    <?php endif; ?>
</div>
