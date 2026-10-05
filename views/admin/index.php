<?php
/**
 * @var array $overview @var array $subStats @var array $questionStats
 * @var array $registrations @var array $attemptsSeries @var array $revenueSeries
 * @var array $pendingPayments @var array $weakest @var array $hardest @var array $expiringSoon @var array $recentUsers
 */
?>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="pl-stat">
            <div class="pl-stat-icon"><i class="bi bi-people"></i></div>
            <div><div class="value"><?= e(ar_digits($overview['users_total'])) ?></div><div class="label">إجمالي المستخدمين</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pl-stat">
            <div class="pl-stat-icon is-gold"><i class="bi bi-patch-check"></i></div>
            <div><div class="value"><?= e(ar_digits($overview['active_subscribers'])) ?></div><div class="label">مشتركون نشطون</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pl-stat">
            <div class="pl-stat-icon is-teal"><i class="bi bi-cash-coin"></i></div>
            <div><div class="value"><?= e(number_format($overview['revenue_total'], 0)) ?></div><div class="label">الإيرادات (ر.س)</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pl-stat">
            <div class="pl-stat-icon is-blue"><i class="bi bi-question-square"></i></div>
            <div><div class="value"><?= e(ar_digits($overview['questions_total'])) ?></div><div class="label">الأسئلة في البنك</div></div>
        </div>
    </div>
</div>

<?php if ($subStats['pending_payments'] > 0 || $overview['questions_review'] > 0 || $questionStats['open_reports'] > 0): ?>
    <div class="row g-2 mb-3">
        <?php if ($subStats['pending_payments'] > 0): ?>
            <div class="col-md-4">
                <a href="<?= e(url('admin/payments')) ?>" class="alert alert-warning d-block mb-0 text-decoration-none">
                    <i class="bi bi-exclamation-circle me-1"></i>
                    <strong><?= e(ar_digits($subStats['pending_payments'])) ?></strong> دفعة بانتظار المراجعة
                </a>
            </div>
        <?php endif; ?>
        <?php if ($overview['questions_review'] > 0): ?>
            <div class="col-md-4">
                <a href="<?= e(url('admin/questions', ['needs_review' => 1])) ?>" class="alert alert-info d-block mb-0 text-decoration-none">
                    <i class="bi bi-clipboard-check me-1"></i>
                    <strong><?= e(ar_digits($overview['questions_review'])) ?></strong> سؤالاً يحتاج مراجعة
                </a>
            </div>
        <?php endif; ?>
        <?php if ($questionStats['open_reports'] > 0): ?>
            <div class="col-md-4">
                <a href="<?= e(url('admin/reports-queue')) ?>" class="alert alert-danger d-block mb-0 text-decoration-none">
                    <i class="bi bi-flag me-1"></i>
                    <strong><?= e(ar_digits($questionStats['open_reports'])) ?></strong> بلاغ عن أسئلة
                </a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-graph-up text-primary me-2"></i> التسجيلات والاختبارات (آخر 14 يوماً)</span>
            </div>
            <div class="card-body" style="height: 300px;">
                <canvas data-chart='<?= e(json_encode([
                    'type' => 'line',
                    'data' => [
                        'labels' => $registrations['labels'],
                        'datasets' => [
                            ['label' => 'مستخدمون جدد', 'data' => $registrations['values'], 'color' => '#0b6b3a'],
                            ['label' => 'اختبارات مُنجزة', 'data' => $attemptsSeries['values'], 'color' => '#c9a227'],
                        ],
                    ],
                ], JSON_UNESCAPED_UNICODE)) ?>'></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-cash-stack text-primary me-2"></i> الإيرادات (آخر 14 يوماً)</div>
            <div class="card-body" style="height: 300px;">
                <?php if (array_sum($revenueSeries['values']) <= 0): ?>
                    <div class="pl-empty"><i class="bi bi-cash"></i> لا توجد إيرادات مسجّلة في هذه الفترة.</div>
                <?php else: ?>
                    <canvas data-chart='<?= e(json_encode([
                        'type' => 'bar',
                        'data' => ['labels' => $revenueSeries['labels'], 'datasets' => [['label' => 'الإيرادات (ر.س)', 'data' => $revenueSeries['values']]]],
                    ], JSON_UNESCAPED_UNICODE)) ?>'></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-hourglass-split text-primary me-2"></i> مدفوعات بانتظار المراجعة</span>
                <a href="<?= e(url('admin/payments')) ?>" class="small">عرض الكل</a>
            </div>
            <div class="card-body p-0">
                <?php if ($pendingPayments === []): ?>
                    <div class="pl-empty py-4"><i class="bi bi-check2-all"></i> لا توجد مدفوعات معلّقة.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 small">
                            <thead><tr><th>المستخدم</th><th>المبلغ</th><th>رقم العملية</th><th>التاريخ</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($pendingPayments as $payment): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= e($payment['full_name']) ?></div>
                                            <div class="text-muted"><?= e($payment['phone']) ?></div>
                                        </td>
                                        <td><?= e(money((float) $payment['amount'])) ?></td>
                                        <td class="pl-copy"><?= e($payment['reference_number'] ?? '—') ?></td>
                                        <td class="text-muted"><?= e(format_date((string) $payment['created_at'])) ?></td>
                                        <td><a href="<?= e(url('admin/payments')) ?>" class="btn btn-sm btn-primary">مراجعة</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-arrow-down-circle text-danger me-2"></i> أكثر المجالات ضعفاً</span>
                <a href="<?= e(url('admin/statistics')) ?>" class="small">التقارير</a>
            </div>
            <div class="card-body">
                <?php if ($weakest === []): ?>
                    <p class="text-muted small mb-0">لا توجد بيانات كافية بعد.</p>
                <?php else: ?>
                    <?php foreach ($weakest as $row): ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small">
                                <span><?= e($row['name_ar']) ?> <span class="text-muted">(<?= e($row['track_name']) ?>)</span></span>
                                <span class="fw-bold text-danger"><?= e(ar_digits($row['accuracy'])) ?>%</span>
                            </div>
                            <div class="progress pl-progress-thin">
                                <div class="progress-bar bg-danger" style="width: <?= (float) $row['accuracy'] ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bug text-danger me-2"></i> أصعب الأسئلة (نسبة الخطأ)</span>
                <a href="<?= e(url('admin/statistics')) ?>" class="small">التقارير</a>
            </div>
            <div class="card-body p-0">
                <?php if ($hardest === []): ?>
                    <div class="pl-empty py-4"><i class="bi bi-clipboard-data"></i> لا توجد بيانات كافية.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <tbody>
                                <?php foreach ($hardest as $row): ?>
                                    <tr>
                                        <td class="small"><?= e(str_limit((string) $row['question_text'], 70)) ?></td>
                                        <td class="text-nowrap">
                                            <span class="badge bg-danger-subtle text-danger-emphasis"><?= e(ar_digits($row['wrong_rate'])) ?>% خطأ</span>
                                        </td>
                                        <td><a href="<?= e(url('admin/question-form', ['id' => (int) $row['id']])) ?>" class="btn btn-sm btn-outline-secondary">عرض</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history text-warning me-2"></i> اشتراكات تنتهي قريباً</span>
                <a href="<?= e(url('admin/subscriptions', ['status' => 'active'])) ?>" class="small">الاشتراكات</a>
            </div>
            <div class="card-body p-0">
                <?php if ($expiringSoon === []): ?>
                    <div class="pl-empty py-4"><i class="bi bi-calendar-check"></i> لا توجد اشتراكات على وشك الانتهاء.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <tbody>
                                <?php foreach (array_slice($expiringSoon, 0, 6) as $row): ?>
                                    <tr>
                                        <td class="small">
                                            <div class="fw-semibold"><?= e($row['full_name']) ?></div>
                                            <div class="text-muted"><?= e($row['phone']) ?></div>
                                        </td>
                                        <td class="small text-muted"><?= e($row['plan_name']) ?></td>
                                        <td class="small">
                                            <span class="badge bg-warning-subtle text-warning-emphasis">
                                                <?= e(ar_digits(max(0, (int) $row['days_remaining'] ?? days_between(date('Y-m-d H:i:s'), (string) $row['expires_at'])))) ?> يوم
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-plus text-primary me-2"></i> أحدث المستخدمين</span>
                <a href="<?= e(url('admin/users')) ?>" class="small">كل المستخدمين</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td class="small">
                                        <div class="fw-semibold"><?= e($user['full_name']) ?></div>
                                        <div class="text-muted"><?= e($user['email']) ?></div>
                                    </td>
                                    <td class="small">
                                        <span class="badge bg-light text-dark border">
                                            <?= e($user['role'] === 'admin' ? 'مدير' : ($user['role'] === 'supervisor' ? 'مشرف' : 'طالب')) ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted"><?= e(format_date((string) $user['created_at'])) ?></td>
                                    <td><a href="<?= e(url('admin/user-form', ['id' => (int) $user['id']])) ?>" class="btn btn-sm btn-outline-secondary">تعديل</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="bi bi-grid text-primary me-2"></i> مؤشرات سريعة</div>
            <div class="card-body">
                <div class="row g-3 text-center">
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-3">
                            <div class="fs-4 fw-bold"><?= e(ar_digits($overview['attempts_total'])) ?></div>
                            <div class="small text-muted">اختبار مُنجز</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-3">
                            <div class="fs-4 fw-bold"><?= e(ar_digits($overview['avg_score'])) ?>%</div>
                            <div class="small text-muted">متوسط الدرجات</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-3">
                            <div class="fs-4 fw-bold"><?= e(ar_digits($overview['pass_rate'])) ?>%</div>
                            <div class="small text-muted">نسبة الاجتياز</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-3">
                            <div class="fs-4 fw-bold"><?= e(ar_digits($overview['telegram_linked'])) ?></div>
                            <div class="small text-muted">حسابات مربوطة بتلجرام</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
