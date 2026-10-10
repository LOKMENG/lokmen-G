<?php
/** @var array|null $current @var bool $isActive @var int $daysLeft @var array $subscriptions @var array $payments */
?>
<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-card-checklist text-primary me-2"></i> الاشتراك الحالي</span>
                <?= subscription_badge($current['status'] ?? null) ?>
            </div>
            <div class="card-body">
                <?php if ($current === null): ?>
                    <div class="pl-empty py-4">
                        <i class="bi bi-credit-card-2-front"></i>
                        لا يوجد اشتراك مرتبط بحسابك.
                        <div class="mt-3"><a href="<?= e(url('subscriptions/plans')) ?>" class="btn btn-primary btn-sm">عرض الباقات</a></div>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="small text-muted">الباقة</div>
                            <div class="fw-bold"><?= e($current['plan_name']) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="small text-muted">المبلغ</div>
                            <div class="fw-bold"><?= e(money((float) $current['amount'])) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="small text-muted">بداية الاشتراك</div>
                            <div class="fw-bold"><?= e(format_date($current['started_at'])) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="small text-muted">تاريخ الانتهاء</div>
                            <div class="fw-bold"><?= e(format_date($current['expires_at'])) ?></div>
                        </div>
                    </div>
                    <?php if ($isActive): ?>
                        <div class="progress pl-progress-thin mt-3">
                            <?php
                            $totalDays = max(1, (int) $current['duration_days'] + (int) $current['extended_days']);
                            $elapsed = max(0, $totalDays - $daysLeft);
                            ?>
                            <div class="progress-bar bg-primary" style="width: <?= (float) min(100, ($elapsed / $totalDays) * 100) ?>%"></div>
                        </div>
                        <div class="small text-muted mt-1">متبقٍ <?= e(ar_digits($daysLeft)) ?> يوماً من أصل <?= e(ar_digits($totalDays)) ?>.</div>
                    <?php elseif ($current['status'] === 'pending'): ?>
                        <div class="alert alert-warning small mt-3 mb-0">
                            <i class="bi bi-hourglass-split me-1"></i>
                            طلبك بانتظار التحقق من الدفع. سيتم التفعيل قريباً بإذن الله.
                            <form method="post" class="mt-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="cancel_pending">
                                <input type="hidden" name="subscription_id" value="<?= (int) $current['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" data-confirm="هل تريد إلغاء طلب الاشتراك؟">
                                    <i class="bi bi-x-circle me-1"></i> إلغاء الطلب
                                </button>
                            </form>
                        </div>
                    <?php elseif ($current['status'] === 'rejected'): ?>
                        <div class="alert alert-danger small mt-3 mb-0">
                            <i class="bi bi-x-octagon me-1"></i>
                            تم رفض إثبات الدفع<?= !empty($current['rejection_reason']) ? ': ' . e($current['rejection_reason']) : '' ?>.
                            يمكنك إرسال طلب جديد مع بيانات صحيحة.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-lightning text-primary me-2"></i> إجراءات سريعة</div>
            <div class="card-body d-grid gap-2">
                <a href="<?= e(url('subscriptions/plans')) ?>" class="btn btn-primary">
                    <i class="bi bi-credit-card me-1"></i> <?= $isActive ? 'تجديد الاشتراك' : 'اشترك الآن' ?>
                </a>
                <?php if ($isActive): ?>
                    <a href="<?= e(url('exams/index')) ?>" class="btn btn-outline-primary"><i class="bi bi-journal-check me-1"></i> بدء اختبار</a>
                <?php endif; ?>
                <a href="<?= e(url('student/telegram')) ?>" class="btn btn-outline-secondary"><i class="bi bi-telegram me-1"></i> إشعارات تلجرام</a>
                <a href="<?= e(url('student/support')) ?>" class="btn btn-outline-secondary"><i class="bi bi-headset me-1"></i> الدعم الفني</a>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-receipt text-primary me-2"></i> سجل المدفوعات</div>
    <div class="card-body p-0">
        <?php if ($payments === []): ?>
            <div class="pl-empty py-4"><i class="bi bi-wallet2"></i> لا توجد مدفوعات مسجّلة.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr><th>#</th><th>المبلغ</th><th>الطريقة</th><th>رقم العملية</th><th>الإثبات</th><th>الحالة</th><th>التاريخ</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td class="small text-muted"><?= e(ar_digits((int) $payment['id'])) ?></td>
                                <td class="fw-semibold"><?= e(money((float) $payment['amount'])) ?></td>
                                <td class="small"><?= e(\App\Payments\PaymentManager::methodLabel((string) $payment['method'])) ?></td>
                                <td class="small pl-copy"><?= e($payment['reference_number'] ?? '—') ?></td>
                                <td class="small">
                                    <?php if (!empty($payment['receipt_path'])): ?>
                                        <a href="<?= e(upload_url($payment['receipt_path'])) ?>" target="_blank" rel="noopener">
                                            <i class="bi bi-paperclip"></i> عرض
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    [$class, $label] = match ($payment['status']) {
                                        'approved' => ['success', 'مقبول'],
                                        'rejected' => ['danger', 'مرفوض'],
                                        'refunded' => ['secondary', 'مُسترجع'],
                                        default     => ['warning', 'قيد المراجعة'],
                                    };
                                    ?>
                                    <span class="badge bg-<?= e($class) ?>-subtle text-<?= e($class) ?>-emphasis"><?= e($label) ?></span>
                                    <?php if (!empty($payment['admin_note'])): ?>
                                        <div class="small text-muted"><?= e($payment['admin_note']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= e(format_date((string) $payment['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($subscriptions !== []): ?>
    <div class="card">
        <div class="card-header"><i class="bi bi-clock-history text-primary me-2"></i> كل الاشتراكات</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>#</th><th>الباقة</th><th>المدة</th><th>من</th><th>إلى</th><th>الحالة</th></tr></thead>
                    <tbody>
                        <?php foreach ($subscriptions as $subscription): ?>
                            <tr>
                                <td class="small text-muted"><?= e(ar_digits((int) $subscription['id'])) ?></td>
                                <td class="small fw-semibold"><?= e($subscription['plan_name']) ?></td>
                                <td class="small"><?= e(ar_digits((int) $subscription['duration_days'] + (int) $subscription['extended_days'])) ?> يوماً</td>
                                <td class="small"><?= e(format_date($subscription['started_at'])) ?></td>
                                <td class="small"><?= e(format_date($subscription['expires_at'])) ?></td>
                                <td><?= subscription_badge((string) $subscription['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>
