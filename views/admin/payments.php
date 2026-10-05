<?php
/** @var array $paginated @var array $filters @var array $stats @var array $methods */
$statusMap = [
    'pending'  => ['warning', 'قيد المراجعة'],
    'approved' => ['success', 'معتمدة'],
    'rejected' => ['danger', 'مرفوضة'],
    'refunded' => ['secondary', 'مُسترجعة'],
];
?>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="pl-stat"><div class="pl-stat-icon is-gold"><i class="bi bi-hourglass-split"></i></div>
            <div><div class="value"><?= e(ar_digits($stats['pending_payments'])) ?></div><div class="label">دفعات قيد المراجعة</div></div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pl-stat"><div class="pl-stat-icon is-teal"><i class="bi bi-cash-coin"></i></div>
            <div><div class="value"><?= e(number_format((float) $stats['revenue'], 0)) ?></div><div class="label">إجمالي الإيرادات (ر.س)</div></div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pl-stat"><div class="pl-stat-icon is-blue"><i class="bi bi-calendar-week"></i></div>
            <div><div class="value"><?= e(number_format((float) $stats['revenue_month'], 0)) ?></div><div class="label">إيرادات الشهر (ر.س)</div></div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pl-stat"><div class="pl-stat-icon"><i class="bi bi-patch-check"></i></div>
            <div><div class="value"><?= e(ar_digits($stats['active'])) ?></div><div class="label">مشتركون نشطون</div></div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small" for="q">بحث</label>
                <input type="text" class="form-control form-control-sm" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="اسم المستخدم / رقم العملية">
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="status">الحالة</label>
                <select class="form-select form-select-sm" id="status" name="status">
                    <option value="">كل الحالات</option>
                    <?php foreach ($statusMap as $key => [$class, $label]): ?>
                        <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="method">طريقة الدفع</label>
                <select class="form-select form-select-sm" id="method" name="method">
                    <option value="">كل الطرق</option>
                    <?php foreach ($methods as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $filters['method'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel me-1"></i> تصفية</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-receipt text-primary me-2"></i> سجل المدفوعات (<?= e(ar_digits($paginated['total'])) ?>)</div>
    <div class="card-body p-0">
        <?php if ($paginated['rows'] === []): ?>
            <div class="pl-empty"><i class="bi bi-wallet2"></i> لا توجد مدفوعات مطابقة.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr><th>#</th><th>المستخدم</th><th>المبلغ</th><th>الطريقة</th><th>رقم العملية</th><th>الإيصال</th><th>الحالة</th><th class="d-none d-lg-table-cell">التاريخ</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginated['rows'] as $payment): ?>
                            <?php [$badge, $statusLabel] = $statusMap[$payment['status']] ?? ['secondary', $payment['status']]; ?>
                            <tr>
                                <td class="small text-muted"><?= e(ar_digits((int) $payment['id'])) ?></td>
                                <td class="small">
                                    <a href="<?= e(url('admin/user-form', ['id' => (int) $payment['user_id']])) ?>" class="fw-semibold text-decoration-none"><?= e($payment['full_name']) ?></a>
                                    <div class="text-muted"><?= e($payment['phone']) ?></div>
                                </td>
                                <td class="fw-semibold small"><?= e(money((float) $payment['amount'])) ?></td>
                                <td class="small"><?= e($methods[$payment['method']] ?? $payment['method']) ?></td>
                                <td class="small">
                                    <div class="pl-copy"><?= e($payment['reference_number'] ?? '—') ?></div>
                                    <?php if (!empty($payment['gateway_txn_id'])): ?>
                                        <div class="text-muted" style="font-size:.72rem;"><?= e($payment['gateway'] ?? '') ?>: <?= e($payment['gateway_txn_id']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <?php if (!empty($payment['receipt_path'])): ?>
                                        <a href="<?= e(upload_url((string) $payment['receipt_path'])) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary py-0">
                                            <i class="bi bi-image"></i> عرض
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= e($badge) ?>-subtle text-<?= e($badge) ?>-emphasis"><?= e($statusLabel) ?></span>
                                    <?php if ($payment['subscription_status'] !== null): ?>
                                        <div class="small text-muted">اشتراك: <?= subscription_badge((string) $payment['subscription_status']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($payment['admin_note'])): ?>
                                        <div class="small text-muted"><?= e($payment['admin_note']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted d-none d-lg-table-cell">
                                    <?= e(format_date((string) $payment['created_at'])) ?>
                                    <?php if (!empty($payment['reviewed_at'])): ?>
                                        <div>مراجعة: <?= e(format_date((string) $payment['reviewed_at'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <?php if ($payment['status'] === 'pending'): ?>
                                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#payApprove<?= (int) $payment['id'] ?>">
                                                <i class="bi bi-check2"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#payReject<?= (int) $payment['id'] ?>">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        <?php else: ?>
                                            <form method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                                                <button class="btn btn-sm btn-outline-danger" data-confirm="حذف هذه الدفعة؟"><i class="bi bi-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($payment['status'] === 'pending'): ?>
                                        <div class="modal fade text-end" id="payApprove<?= (int) $payment['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <form method="post" class="modal-content">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                                                    <div class="modal-header">
                                                        <h6 class="modal-title">اعتماد الدفعة #<?= e(ar_digits((int) $payment['id'])) ?></h6>
                                                        <button type="button" class="btn-close ms-0" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p class="small mb-2">
                                                            المستخدم: <strong><?= e($payment['full_name']) ?></strong><br>
                                                            المبلغ: <strong><?= e(money((float) $payment['amount'])) ?></strong><br>
                                                            الخطة: <strong><?= e($payment['plan_name'] ?? '—') ?></strong>
                                                        </p>
                                                        <input type="text" name="note" class="form-control form-control-sm" placeholder="ملاحظة إدارية (اختياري)">
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-dismiss="modal">إلغاء</button>
                                                        <button class="btn btn-sm btn-success"><i class="bi bi-check2-circle me-1"></i> اعتماد وتفعيل الاشتراك</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>

                                        <div class="modal fade text-end" id="payReject<?= (int) $payment['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <form method="post" class="modal-content">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                                                    <div class="modal-header">
                                                        <h6 class="modal-title">رفض الدفعة #<?= e(ar_digits((int) $payment['id'])) ?></h6>
                                                        <button type="button" class="btn-close ms-0" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <label class="form-label small" for="reason<?= (int) $payment['id'] ?>">سبب الرفض (يظهر للطالب)</label>
                                                        <textarea class="form-control form-control-sm" id="reason<?= (int) $payment['id'] ?>" name="reason" rows="3" required
                                                                  placeholder="مثال: رقم العملية غير صحيح / لم يتم استلام المبلغ"></textarea>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-dismiss="modal">إلغاء</button>
                                                        <button class="btn btn-sm btn-danger"><i class="bi bi-x-circle me-1"></i> رفض الدفعة</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php if ((int) $paginated['pages'] > 1): ?>
        <div class="card-footer"><?= pagination((int) $paginated['page'], (int) $paginated['pages'], array_merge($filters, ['page' => $paginated['page']])) ?></div>
    <?php endif; ?>
</div>
