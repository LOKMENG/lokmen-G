<?php
/** @var array $result @var array $analysis */
$score = (float) $result['score'];
$total = (int) $result['total_questions'];
$correct = (int) $result['correct_count'];
$wrong = (int) $result['wrong_count'];
$unanswered = (int) $result['unanswered_count'];
$passed = (bool) $result['passed'];
$circumference = 326.7;
$offset = $circumference * (1 - min(100, max(0, $score)) / 100);
?>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="pl-result-ring">
                    <svg width="170" height="170" viewBox="0 0 120 120">
                        <circle cx="60" cy="60" r="52" fill="none" stroke="var(--pl-border)" stroke-width="11"></circle>
                        <circle cx="60" cy="60" r="52" fill="none" stroke="var(--pl-<?= $passed ? 'primary' : 'accent' ?>)"
                                stroke-width="11" stroke-linecap="round" stroke-dasharray="<?= e((string) $circumference) ?>"
                                stroke-dashoffset="<?= e((string) round($offset, 1)) ?>"></circle>
                    </svg>
                    <div class="value">
                        <?= e(ar_digits($score)) ?>%
                        <small>النتيجة النهائية</small>
                    </div>
                </div>

                <h4 class="mt-3 mb-1">
                    <?= e(ar_digits($correct)) ?> / <?= e(ar_digits($total)) ?>
                </h4>
                <div class="mb-3">
                    <?php if ($passed): ?>
                        <span class="badge bg-success fs-6 px-3 py-2"><i class="bi bi-check-circle me-1"></i> ناجح</span>
                    <?php else: ?>
                        <span class="badge bg-danger fs-6 px-3 py-2"><i class="bi bi-x-circle me-1"></i> لم تجتز النسبة المطلوبة (<?= e(ar_digits((int) $result['pass_percentage'])) ?>%)</span>
                    <?php endif; ?>
                </div>

                <div class="row g-2 text-center">
                    <div class="col-4">
                        <div class="border rounded p-2">
                            <div class="fw-bold text-success fs-5"><?= e(ar_digits($correct)) ?></div>
                            <div class="small text-muted">صحيحة</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded p-2">
                            <div class="fw-bold text-danger fs-5"><?= e(ar_digits($wrong)) ?></div>
                            <div class="small text-muted">خاطئة</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded p-2">
                            <div class="fw-bold text-secondary fs-5"><?= e(ar_digits($unanswered)) ?></div>
                            <div class="small text-muted">بدون إجابة</div>
                        </div>
                    </div>
                </div>

                <ul class="list-unstyled small text-muted mt-3 mb-0">
                    <li><i class="bi bi-clock me-1"></i> الوقت المستغرق: <?= e($result['time_spent_human']) ?></li>
                    <li><i class="bi bi-calendar me-1"></i> <?= e(format_date($result['finished_at'], true)) ?></li>
                </ul>

                <div class="d-grid gap-2 mt-3">
                    <a href="<?= e(url('exams/review', ['id' => (int) $result['id']])) ?>" class="btn btn-primary">
                        <i class="bi bi-list-check me-1"></i> مراجعة الإجابات والشرح
                    </a>
                    <?php if (!empty($result['exam_template_id'])): ?>
                        <form method="post" action="<?= e(url('exams/start')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="template">
                            <input type="hidden" name="template_id" value="<?= (int) $result['exam_template_id'] ?>">
                            <button class="btn btn-outline-primary w-100"><i class="bi bi-arrow-repeat me-1"></i> إعادة الاختبار</button>
                        </form>
                    <?php else: ?>
                        <a href="<?= e(url('exams/index')) ?>" class="btn btn-outline-primary">
                            <i class="bi bi-plus-circle me-1"></i> اختبار جديد
                        </a>
                    <?php endif; ?>
                    <a href="<?= e(url('student/statistics')) ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-bar-chart-line me-1"></i> تحليل مستواي
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-pie-chart text-primary me-2"></i> التحليل حسب المجال</div>
            <div class="card-body p-0">
                <?php if ($analysis === []): ?>
                    <div class="pl-empty"><i class="bi bi-clipboard"></i> لا توجد بيانات تحليل.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr><th>المجال</th><th>الأسئلة</th><th>الصحيحة</th><th style="min-width:150px;">النسبة</th><th>التقييم</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($analysis as $row): ?>
                                    <tr>
                                        <td class="fw-semibold small"><?= e($row['name']) ?></td>
                                        <td class="small"><?= e(ar_digits($row['total'])) ?></td>
                                        <td class="small"><?= e(ar_digits($row['correct'])) ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress pl-progress-thin flex-grow-1">
                                                    <div class="progress-bar bg-<?= e(score_class($row['accuracy'])) ?>" style="width: <?= (float) $row['accuracy'] ?>%"></div>
                                                </div>
                                                <span class="small fw-bold"><?= e(ar_digits($row['accuracy'])) ?>%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($row['level'] === 'strength'): ?>
                                                <span class="badge bg-success-subtle text-success-emphasis">قوة</span>
                                            <?php elseif ($row['level'] === 'weakness'): ?>
                                                <span class="badge bg-danger-subtle text-danger-emphasis">مراجعة</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis">متوسط</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php
        $weaknesses = array_values(array_filter($analysis, static fn(array $row): bool => $row['level'] === 'weakness'));
        $strengths = array_values(array_filter($analysis, static fn(array $row): bool => $row['level'] === 'strength'));
        ?>
        <div class="card">
            <div class="card-header"><i class="bi bi-lightbulb text-primary me-2"></i> التوصيات</div>
            <div class="card-body">
                <ul class="list-unstyled mb-0 small">
                    <?php if ($weaknesses !== []): ?>
                        <li class="mb-2">
                            <i class="bi bi-exclamation-triangle text-danger me-1"></i>
                            ننصحك بمراجعة قسم <strong><?= e($weaknesses[0]['name']) ?></strong> قبل إعادة الاختبار
                            (نسبة دقتك فيه <?= e(ar_digits($weaknesses[0]['accuracy'])) ?>%).
                        </li>
                    <?php endif; ?>
                    <?php if ($strengths !== []): ?>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success me-1"></i>
                            أداء ممتاز في: <?= e(implode(' و ', array_slice(array_column($strengths, 'name'), 0, 3))) ?>.
                        </li>
                    <?php endif; ?>
                    <li class="mb-2"><i class="bi bi-arrow-repeat text-primary me-1"></i> استخدم «مراجعة الإجابات» لقراءة شرح كل سؤال أخطأت فيه.</li>
                    <li class="mb-0"><i class="bi bi-collection text-primary me-1"></i> تدرّب على المجالات الضعيفة من صفحة التدريب حسب المجال.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
