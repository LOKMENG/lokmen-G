<?php
/** @var array $paginated @var array $summary */
$rows = $paginated['rows'];
?>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="pl-stat"><div class="pl-stat-icon"><i class="bi bi-journal-check"></i></div>
            <div><div class="value"><?= e(ar_digits($summary['attempts'])) ?></div><div class="label">إجمالي المحاولات</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pl-stat"><div class="pl-stat-icon is-gold"><i class="bi bi-graph-up"></i></div>
            <div><div class="value"><?= e(ar_digits($summary['avg_score'])) ?>%</div><div class="label">المتوسط</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pl-stat"><div class="pl-stat-icon is-teal"><i class="bi bi-check2-all"></i></div>
            <div><div class="value"><?= e(ar_digits($summary['correct'])) ?></div><div class="label">إجابات صحيحة</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pl-stat"><div class="pl-stat-icon is-red"><i class="bi bi-x-circle"></i></div>
            <div><div class="value"><?= e(ar_digits($summary['wrong'])) ?></div><div class="label">إجابات خاطئة</div></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history text-primary me-2"></i> المحاولات</span>
        <a href="<?= e(url('exams/index')) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i> اختبار جديد</a>
    </div>
    <div class="card-body p-0">
        <?php if ($rows === []): ?>
            <div class="pl-empty">
                <i class="bi bi-journal-x"></i> لم تخضع لأي اختبار بعد.
                <div class="mt-3"><a href="<?= e(url('exams/index')) ?>" class="btn btn-primary btn-sm">ابدأ أول اختبار</a></div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th><th>الاختبار</th><th class="d-none d-md-table-cell">المسار</th>
                            <th>النتيجة</th><th class="d-none d-sm-table-cell">المدة</th><th>التاريخ</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td class="small text-muted"><?= e(ar_digits((int) $row['id'])) ?></td>
                                <td>
                                    <div class="fw-semibold small"><?= e(str_limit((string) $row['title'], 45)) ?></div>
                                    <div class="small text-muted">
                                        <?= e(ar_digits((int) $row['correct_count'])) ?> صحيحة /
                                        <?= e(ar_digits((int) $row['wrong_count'])) ?> خاطئة /
                                        <?= e(ar_digits((int) $row['unanswered_count'])) ?> بدون إجابة
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell small"><?= e($row['track_name']) ?></td>
                                <td>
                                    <?php if ($row['status'] === 'in_progress'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis">قيد الأداء</span>
                                    <?php else: ?>
                                        <span class="badge bg-<?= e(score_class((float) $row['score'])) ?>-subtle text-<?= e(score_class((float) $row['score'])) ?>-emphasis">
                                            <?= e(ar_digits((float) $row['score'])) ?>%
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-sm-table-cell small text-muted"><?= e(duration_ar((int) $row['time_spent_seconds'])) ?></td>
                                <td class="small text-muted"><?= e(format_date($row['finished_at'] ?? $row['started_at'])) ?></td>
                                <td class="text-end">
                                    <?php if ($row['status'] === 'in_progress'): ?>
                                        <a href="<?= e(url('exams/take', ['id' => (int) $row['id']])) ?>" class="btn btn-sm btn-warning">متابعة</a>
                                    <?php else: ?>
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= e(url('exams/result', ['id' => (int) $row['id']])) ?>" class="btn btn-outline-primary">النتيجة</a>
                                            <a href="<?= e(url('exams/review', ['id' => (int) $row['id']])) ?>" class="btn btn-outline-secondary">مراجعة</a>
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
        <div class="card-footer"><?= pagination((int) $paginated['page'], (int) $paginated['pages'], ['page' => $paginated['page']]) ?></div>
    <?php endif; ?>
</div>
