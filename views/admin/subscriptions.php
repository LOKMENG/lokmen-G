<?php
/** @var array $paginated @var array $stats @var array $filters @var array $plans @var array $expiring */
$statusLabels = [
    'pending'   => ['warning', 'بانتظار الدفع'],
    'approved'  => ['info', 'تم اعتماد الدفع'],
    'active'    => ['success', 'نشط'],
    'expired'   => ['secondary', 'منتهي'],
    'rejected'  => ['danger', 'مرفوض'],
    'cancelled' => ['secondary', 'ملغي'],
];
?>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="pl-stat"><div class="pl-stat-icon is-teal"><i class="bi bi-people"></i></div>
            <div><div class="value"><?= e(ar_digits($stats['active'] ?? 0)) ?></div><div class="label">اشتراكات نشطة</div></div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pl-stat"><div class="pl-stat-icon is-gold"><i class="bi bi-hourglass"></i></div>
            <div><div class="value"><?= e(ar_digits($stats['pending'] ?? 0)) ?></div><div class="label">بانتظار المراجعة</div></div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pl-stat"><div class="pl-stat-icon is-blue"><i class="bi bi-calendar-x"></i></div>
            <div><div class="value"><?= e(ar_digits($stats['expired'] ?? 0)) ?></div><div class="label">اشتراكات منتهية</div></div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pl-stat"><div class="pl-stat-icon is-red"><i class="bi bi-cash-coin"></i></div>
            <div><div class="value"><?= e(number_format((float) ($stats['revenue_month'] ?? 0), 0)) ?></div><div class="label">إيرادات الشهر (ر.س)</div></div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
        <form method="get" class="row g-2 align-items-end flex-grow-1">
            <div class="col-md-4">
                <label class="form-label small" for="q">بحث بالمستخدم</label>
                <input type="text" class="form-control form-control-sm" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="الاسم / البريد / الجوال">
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="status">الحالة</label>
                <select class="form-select form-select-sm" id="status" name="status" onchange="this.form.submit()">
                    <option value="">كل الحالات</option>
                    <?php foreach ($statusLabels as $key => [$class, $label]): ?>
                        <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> بحث</button></div>
        </form>
        <div class="d-flex gap-2">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="expire_now">
                <button class="btn btn-sm btn-outline-secondary" data-confirm="تحديث الاشتراكات المنتهية الآن؟"><i class="bi bi-arrow-repeat me-1"></i> تحديث المنتهية</button></form>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="notify_expiring"><input type="hidden" name="days" value="7">
                <button class="btn btn-sm btn-outline-primary" data-confirm="إرسال تنبيهات للاشتراكات التي تنتهي خلال 7 أيام؟"><i class="bi bi-bell me-1"></i> تنبيه المنتهية قريباً</button></form>
        </div>
    </div>
</div>

<?php if ($expiring !== []): ?>
    <div class="alert alert-warning small">
        <i class="bi bi-clock-history me-1"></i>
        <strong><?= e(ar_digits(count($expiring))) ?></strong> اشتراكاً ينتهي خلال 14 يوماً:
        <?= e(implode('، ', array_map(static fn($row) => (string) $row['full_name'], array_slice($expiring, 0, 5)))) ?>
        <?= count($expiring) > 5 ? ' وآخرون' : '' ?>.
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-card-checklist text-primary me-2"></i> الاشتراكات (<?= e(ar_digits($paginated['total'])) ?>)</span>
        <a href="#manual-activate" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-circle me-1"></i> تفعيل يدوي</a>
    </div>
    <div class="card-body p-0">
        <?php if ($paginated['rows'] === []): ?>
            <div class="pl-empty"><i class="bi bi-card-list"></i> لا توجد اشتراكات مطابقة.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th><th>المستخدم</th><th>الباقة</th><th>الفترة</th>
                            <th>آخر دفعة</th><th>الحالة</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginated['rows'] as $subscription): ?>
                            <?php [$badgeClass, $badgeLabel] = $statusLabels[$subscription['status']] ?? ['secondary', $subscription['status']]; ?>
                            <tr>
                                <td class="small text-muted"><?= e(ar_digits((int) $subscription['id'])) ?></td>
                                <td class="small">
                                    <a href="<?= e(url('admin/user-form', ['id' => (int) $subscription['user_id']])) ?>" class="fw-semibold text-decoration-none">
                                        <?= e($subscription['full_name']) ?>
                                    </a>
                                    <div class="text-muted"><?= e($subscription['phone']) ?></div>
                                </td>
                                <td class="small">
                                    <?= e($subscription['plan_name']) ?>
                                    <div class="text-muted"><?= e(money((float) $subscription['amount'])) ?></div>
                                </td>
                                <td class="small">
                                    <?= e(format_date($subscription['started_at'])) ?> →
                                    <?= e(format_date($subscription['expires_at'])) ?>
                                    <?php if (in_array($subscription['status'], ['active', 'approved'], true) && $subscription['expires_at'] !== null): ?>
                                        <div class="text-muted">متبقٍ <?= e(ar_digits(max(0, (int) $subscription['days_remaining']))) ?> يوم</div>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <?php if ($subscription['reference_number'] !== null): ?>
                                        <div class="pl-copy"><?= e($subscription['reference_number']) ?></div>
                                        <span class="badge bg-<?= e($subscription['payment_status'] === 'approved' ? 'success' : ($subscription['payment_status'] === 'rejected' ? 'danger' : 'warning')) ?>-subtle text-<?= e($subscription['payment_status'] === 'approved' ? 'success' : ($subscription['payment_status'] === 'rejected' ? 'danger' : 'warning')) ?>-emphasis">
                                            <?= e($subscription['payment_status'] === 'approved' ? 'مقبول' : ($subscription['payment_status'] === 'rejected' ? 'مرفوض' : 'معلّق')) ?>
                                        </span>
                                        <?php if (!empty($subscription['receipt_path'])): ?>
                                            <a href="<?= e(upload_url((string) $subscription['receipt_path'])) ?>" target="_blank" rel="noopener" class="small">
                                                <i class="bi bi-paperclip"></i> الإيصال
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-<?= e($badgeClass) ?>-subtle text-<?= e($badgeClass) ?>-emphasis"><?= e($badgeLabel) ?></span></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#subModal<?= (int) $subscription['id'] ?>">
                                        <i class="bi bi-gear"></i>
                                    </button>

                                    <div class="modal fade" id="subModal<?= (int) $subscription['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content text-end">
                                                <div class="modal-header">
                                                    <h6 class="modal-title">إدارة اشتراك <?= e($subscription['full_name']) ?></h6>
                                                    <button type="button" class="btn-close ms-0" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body small">
                                                    <ul class="list-unstyled">
                                                        <li class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">الحالة</span><span><?= e($badgeLabel) ?></span></li>
                                                        <li class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">رقم العملية</span><span class="pl-copy"><?= e($subscription['reference_number'] ?? '—') ?></span></li>
                                                        <li class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">المبلغ</span><span><?= e(money((float) $subscription['amount'])) ?></span></li>
                                                        <li class="d-flex justify-content-between py-1"><span class="text-muted">ينتهي</span><span><?= e(format_date($subscription['expires_at'])) ?></span></li>
                                                    </ul>

                                                    <?php if ($subscription['payment_status'] === 'pending' && (int) $subscription['last_payment_id'] > 0): ?>
                                                        <form method="post" class="mb-2">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="approve">
                                                            <input type="hidden" name="payment_id" value="<?= (int) $subscription['last_payment_id'] ?>">
                                                            <input type="text" name="note" class="form-control form-control-sm mb-2" placeholder="ملاحظة (اختياري)">
                                                            <button class="btn btn-sm btn-success w-100" data-confirm="اعتماد الدفع وتفعيل الاشتراك؟">
                                                                <i class="bi bi-check2-circle me-1"></i> اعتماد الدفع وتفعيل الاشتراك
                                                            </button>
                                                        </form>
                                                        <form method="post" class="mb-3">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="reject">
                                                            <input type="hidden" name="payment_id" value="<?= (int) $subscription['last_payment_id'] ?>">
                                                            <input type="text" name="reason" class="form-control form-control-sm mb-2" placeholder="سبب الرفض (يظهر للطالب)" required>
                                                            <button class="btn btn-sm btn-outline-danger w-100" data-confirm="رفض إثبات الدفع؟">
                                                                <i class="bi bi-x-circle me-1"></i> رفض الدفع
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <div class="dropdown-divider"></div>
                                                    <form method="post" class="d-flex gap-2 mb-2">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="extend">
                                                        <input type="hidden" name="subscription_id" value="<?= (int) $subscription['id'] ?>">
                                                        <input type="number" name="days" class="form-control form-control-sm" value="30" min="1" max="730">
                                                        <button class="btn btn-sm btn-outline-primary text-nowrap">تمديد (يوم)</button>
                                                    </form>
                                                    <form method="post" class="d-flex gap-2 mb-2">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="activate_existing">
                                                        <input type="hidden" name="subscription_id" value="<?= (int) $subscription['id'] ?>">
                                                        <button class="btn btn-sm btn-outline-success w-100" data-confirm="تفعيل هذا الاشتراك يدوياً؟">
                                                            <i class="bi bi-lightning me-1"></i> تفعيل الآن
                                                        </button>
                                                    </form>
                                                    <form method="post" class="d-flex gap-2">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="cancel">
                                                        <input type="hidden" name="subscription_id" value="<?= (int) $subscription['id'] ?>">
                                                        <input type="text" name="reason" class="form-control form-control-sm" placeholder="سبب الإلغاء">
                                                        <button class="btn btn-sm btn-outline-danger text-nowrap" data-confirm="إلغاء الاشتراك؟">إلغاء</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php if ((int) $paginated['pages'] > 1): ?>
        <div class="card-footer">
            <?= pagination((int) $paginated['page'], (int) $paginated['pages'], array_merge($filters, ['page' => $paginated['page']])) ?>
        </div>
    <?php endif; ?>
</div>

<div class="card mt-3" id="manual-activate">
    <div class="card-header"><i class="bi bi-plus-circle text-primary me-2"></i> تفعيل اشتراك يدوي لمستخدم</div>
    <div class="card-body">
        <form method="post" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="activate">
            <div class="col-md-5">
                <label class="form-label small" for="manual_user">معرّف المستخدم أو بريده</label>
                <input type="text" class="form-control form-control-sm" id="manual_user" name="user_lookup" required placeholder="ID أو البريد الإلكتروني">
                <div class="form-text">اكتب بريد المستخدم أو رقمه الظاهر في الجدول أعلاه.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="manual_plan">الباقة</label>
                <select class="form-select form-select-sm" id="manual_plan" name="plan_id">
                    <?php foreach ($plans as $plan): ?>
                        <option value="<?= (int) $plan['id'] ?>"><?= e($plan['name_ar']) ?> — <?= e(money((float) $plan['price_sar'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-sm btn-success w-100" data-confirm="تفعيل الاشتراك لهذا المستخدم؟"><i class="bi bi-patch-check me-1"></i> تفعيل</button>
            </div>
        </form>
        <div class="alert alert-light border small mt-3 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            هذا النموذج يبحث عن المستخدم بالبريد الإلكتروني أو بالمعرّف الرقمي ثم يفعّل الاشتراك فوراً (طريقة <code>admin_manual</code>) ويُسجّل العملية في سجل التدقيق.
        </div>
    </div>
</div>
