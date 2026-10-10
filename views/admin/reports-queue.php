<?php
/** @var array $paginated @var array $filters @var array $counters */
$reasonLabels = [
    'wrong_answer' => 'إجابة خاطئة',
    'typo'         => 'خطأ إملائي',
    'unclear'      => 'صياغة غير واضحة',
    'duplicate'    => 'سؤال مكرر',
    'copyright'    => 'حقوق ملكية',
    'other'        => 'سبب آخر',
];
?>
<div class="row g-3 mb-3">
    <div class="col-4">
        <a href="<?= e(url('admin/reports-queue', ['status' => 'open'])) ?>" class="pl-stat text-decoration-none">
            <div class="pl-stat-icon is-red"><i class="bi bi-flag"></i></div>
            <div><div class="value"><?= e(ar_digits($counters['open'])) ?></div><div class="label">بلاغات مفتوحة</div></div>
        </a>
    </div>
    <div class="col-4">
        <a href="<?= e(url('admin/reports-queue', ['status' => 'resolved'])) ?>" class="pl-stat text-decoration-none">
            <div class="pl-stat-icon is-teal"><i class="bi bi-check2-circle"></i></div>
            <div><div class="value"><?= e(ar_digits($counters['resolved'])) ?></div><div class="label">تمت معالجتها</div></div>
        </a>
    </div>
    <div class="col-4">
        <a href="<?= e(url('admin/reports-queue', ['status' => 'rejected'])) ?>" class="pl-stat text-decoration-none">
            <div class="pl-stat-icon is-gold"><i class="bi bi-x-circle"></i></div>
            <div><div class="value"><?= e(ar_digits($counters['rejected'])) ?></div><div class="label">بلاغات مرفوضة</div></div>
        </a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small" for="status">الحالة</label>
                <select class="form-select form-select-sm" id="status" name="status">
                    <?php foreach (['open' => 'مفتوحة', 'resolved' => 'معالجة', 'rejected' => 'مرفوضة', '' => 'الكل'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="reason">السبب</label>
                <select class="form-select form-select-sm" id="reason" name="reason">
                    <option value="">كل الأسباب</option>
                    <?php foreach ($reasonLabels as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $filters['reason'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel me-1"></i> تصفية</button></div>
        </form>
    </div>
</div>

<?php if ($paginated['rows'] === []): ?>
    <div class="card"><div class="pl-empty"><i class="bi bi-flag"></i> لا توجد بلاغات مطابقة. ممتاز!</div></div>
<?php else: ?>
    <?php foreach ($paginated['rows'] as $report): ?>
        <div class="card mb-3 <?= $report['status'] === 'open' ? 'border-danger' : '' ?>">
            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-danger-subtle text-danger-emphasis"><?= e($reasonLabels[$report['reason']] ?? $report['reason']) ?></span>
                    <span class="small text-muted">
                        بلاغ #<?= e(ar_digits((int) $report['id'])) ?> —
                        <?= e($report['reporter_name'] !== null ? $report['reporter_name'] : 'مستخدم محذوف') ?> —
                        <?= e(format_date((string) $report['created_at'], true)) ?>
                    </span>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <?php if ($report['status'] === 'open'): ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis">مفتوح</span>
                    <?php elseif ($report['status'] === 'resolved'): ?>
                        <span class="badge bg-success-subtle text-success-emphasis">تمت المعالجة</span>
                    <?php else: ?>
                        <span class="badge bg-secondary-subtle text-secondary-emphasis">مرفوض</span>
                    <?php endif; ?>
                    <a href="<?= e(url('admin/question-form', ['id' => (int) $report['question_id']])) ?>" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">
                        <i class="bi bi-pencil me-1"></i> فتح السؤال
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-lg-7">
                        <div class="small text-muted mb-1">نص السؤال (<?= e($report['track_name']) ?> — <?= e($report['category_name'] ?? 'بدون مجال') ?>):</div>
                        <div class="border rounded p-2 mb-2" style="white-space: pre-line;"><?= e((string) $report['question_text']) ?></div>
                        <ul class="list-unstyled small mb-2">
                            <?php foreach (['a' => 'أ', 'b' => 'ب', 'c' => 'ج', 'd' => 'د'] as $letter => $letterAr): ?>
                                <?php if (($report['option_' . $letter] ?? null) === null) { continue; } ?>
                                <li class="<?= $report['correct_answer'] === $letter ? 'text-success fw-bold' : '' ?>">
                                    <?= e($letterAr) ?>) <?= e((string) $report['option_' . $letter]) ?>
                                    <?= $report['correct_answer'] === $letter ? ' ✔' : '' ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (!empty($report['explanation'])): ?>
                            <div class="alert alert-light border small mb-2"><strong>الشرح الحالي:</strong> <?= e((string) $report['explanation']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($report['note'])): ?>
                            <div class="alert alert-warning small mb-0"><i class="bi bi-chat-quote me-1"></i> ملاحظة الطالب: <?= e((string) $report['note']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-lg-5">
                        <div class="small text-muted mb-2">
                            إحصاء السؤال: أُجيب <?= e(ar_digits((int) $report['times_answered'])) ?> مرة،
                            <?= e(ar_digits((int) $report['times_correct'])) ?> صحيحة
                            <?php if ((int) $report['times_answered'] > 0): ?>
                                (دقة <?= e(ar_digits((int) round(((int) $report['times_correct'] / (int) $report['times_answered']) * 100))) ?>%)
                            <?php endif; ?>
                            • الحالة: <?= (int) $report['active'] === 1 ? 'نشط' : 'معطّل' ?>
                            <?= (int) $report['needs_review'] === 1 ? ' • يحتاج مراجعة' : '' ?>
                        </div>

                        <?php if ($report['status'] === 'open'): ?>
                            <form method="post" class="border rounded p-3">
                                <?= csrf_field() ?>
                                <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">
                                <div class="mb-2">
                                    <label class="form-label small" for="qa<?= (int) $report['id'] ?>">الإجراء على السؤال</label>
                                    <select class="form-select form-select-sm" id="qa<?= (int) $report['id'] ?>" name="question_action">
                                        <option value="none">إغلاق البلاغ دون تغيير السؤال</option>
                                        <option value="fix">تصحيح الإجابة/الشرح الآن</option>
                                        <option value="needs_review">تعليمه «يحتاج مراجعة»</option>
                                        <option value="deactivate">تعطيل السؤال</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small" for="ca<?= (int) $report['id'] ?>">الإجابة الصحيحة بعد التصحيح (إن وُجدت)</label>
                                    <select class="form-select form-select-sm" id="ca<?= (int) $report['id'] ?>" name="correct_answer">
                                        <option value="">— بدون تغيير —</option>
                                        <?php foreach (['a' => 'أ', 'b' => 'ب', 'c' => 'ج', 'd' => 'د'] as $letter => $letterAr): ?>
                                            <option value="<?= e($letter) ?>" <?= $report['correct_answer'] === $letter ? 'selected' : '' ?>><?= e($letterAr) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small" for="ex<?= (int) $report['id'] ?>">شرح محدّث (اختياري)</label>
                                    <textarea class="form-control form-control-sm" id="ex<?= (int) $report['id'] ?>" name="explanation" rows="2"></textarea>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-success flex-grow-1" name="action" value="resolve">
                                        <i class="bi bi-check2-circle me-1"></i> إغلاق البلاغ
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" name="action" value="reject" data-confirm="رفض البلاغ؟">
                                        <i class="bi bi-x-circle me-1"></i> رفض
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-light border small mb-0">
                                عُولج بواسطة <?= e((string) ($report['resolved_by_name'] ?? '—')) ?>
                                في <?= e(format_date((string) ($report['resolved_at'] ?? ''), true)) ?>.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ((int) $paginated['pages'] > 1): ?>
        <div class="card"><div class="card-footer"><?= pagination((int) $paginated['page'], (int) $paginated['pages'], $filters) ?></div></div>
    <?php endif; ?>
<?php endif; ?>
