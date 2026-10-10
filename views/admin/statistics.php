<?php
/** @var array $overview @var array $categories @var array $weakest @var array $hardest @var array $topStudents @var array $registrations @var array $revenue @var array $tracks @var int $trackFilter */
?>
<div class="row g-3 mb-3">
    <?php
    $cards = [
        ['bi-people', 'إجمالي المستخدمين', ar_digits($overview['users_total']), ''],
        ['bi-person-plus', 'مستخدمون هذا الشهر', ar_digits($overview['users_new_month']), 'is-gold'],
        ['bi-journal-check', 'اختبارات هذا الشهر', ar_digits($overview['attempts_month']), 'is-teal'],
        ['bi-cash-coin', 'إيرادات هذا الشهر', number_format($overview['revenue_month'], 0) . ' ر.س', 'is-blue'],
    ];
    foreach ($cards as [$icon, $label, $value, $class]):
    ?>
        <div class="col-6 col-lg-3">
            <div class="pl-stat">
                <div class="pl-stat-icon <?= e($class) ?>"><i class="bi <?= e($icon) ?>"></i></div>
                <div><div class="value"><?= e($value) ?></div><div class="label"><?= e($label) ?></div></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-graph-up text-primary me-2"></i> نمو التسجيلات (30 يوماً)</div>
            <div class="card-body" style="height: 260px;">
                <canvas data-chart='<?= e(json_encode([
                    'type' => 'line',
                    'data' => ['labels' => $registrations['labels'], 'datasets' => [['label' => 'مستخدمون جدد', 'data' => $registrations['values']]]],
                ], JSON_UNESCAPED_UNICODE)) ?>'></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-cash-stack text-primary me-2"></i> الإيرادات (30 يوماً)</div>
            <div class="card-body" style="height: 260px;">
                <canvas data-chart='<?= e(json_encode([
                    'type' => 'bar',
                    'data' => ['labels' => $revenue['labels'], 'datasets' => [['label' => 'الإيرادات (ر.س)', 'data' => $revenue['values']]]],
                ], JSON_UNESCAPED_UNICODE)) ?>'></canvas>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span><i class="bi bi-bar-chart text-primary me-2"></i> أداء المجالات على مستوى المنصة</span>
        <form method="get" class="d-flex gap-2">
            <select name="track" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">كل المسارات</option>
                <?php foreach ($tracks as $track): ?>
                    <option value="<?= (int) $track['id'] ?>" <?= $trackFilter === (int) $track['id'] ? 'selected' : '' ?>><?= e($track['name_ar']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="card-body p-0">
        <?php if ($categories === []): ?>
            <div class="pl-empty py-4"><i class="bi bi-clipboard-data"></i> لا توجد بيانات كافية بعد.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>المجال</th><th>المسار</th><th>إجابات</th><th>صحيحة</th><th style="min-width:160px;">الدقة</th><th>التقييم</th></tr></thead>
                    <tbody>
                        <?php foreach ($categories as $row): ?>
                            <tr>
                                <td class="fw-semibold small"><?= e($row['name_ar']) ?></td>
                                <td class="small text-muted"><?= e($row['track_name']) ?></td>
                                <td class="small"><?= e(ar_digits((int) $row['total_answered'])) ?></td>
                                <td class="small"><?= e(ar_digits((int) $row['correct_answers'])) ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress pl-progress-thin flex-grow-1">
                                            <div class="progress-bar bg-<?= e(score_class((float) $row['accuracy'])) ?>" style="width: <?= (float) $row['accuracy'] ?>%"></div>
                                        </div>
                                        <span class="small fw-bold"><?= e(ar_digits($row['accuracy'])) ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ((float) $row['accuracy'] >= 80): ?>
                                        <span class="badge bg-success-subtle text-success-emphasis">قوي</span>
                                    <?php elseif ((float) $row['accuracy'] < 60): ?>
                                        <span class="badge bg-danger-subtle text-danger-emphasis">يحتاج محتوى إضافي</span>
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

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-bug text-danger me-2"></i> أكثر الأسئلة خطأً</div>
            <div class="card-body p-0">
                <?php if ($hardest === []): ?>
                    <div class="pl-empty py-4"><i class="bi bi-clipboard"></i> لا توجد بيانات كافية (يُحسب بعد 3 إجابات).</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>#</th><th>السؤال</th><th>إجابات</th><th>نسبة الخطأ</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($hardest as $row): ?>
                                    <tr>
                                        <td class="small text-muted"><?= e(ar_digits((int) $row['id'])) ?></td>
                                        <td class="small"><?= e(str_limit((string) $row['question_text'], 60)) ?></td>
                                        <td class="small"><?= e(ar_digits((int) $row['times_answered'])) ?></td>
                                        <td><span class="badge bg-danger-subtle text-danger-emphasis"><?= e(ar_digits($row['wrong_rate'])) ?>%</span></td>
                                        <td><a href="<?= e(url('admin/question-form', ['id' => (int) $row['id']])) ?>" class="btn btn-sm btn-outline-secondary">تعديل</a></td>
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
            <div class="card-header"><i class="bi bi-trophy text-primary me-2"></i> أفضل الطلاب</div>
            <div class="card-body p-0">
                <?php if ($topStudents === []): ?>
                    <div class="pl-empty py-4"><i class="bi bi-people"></i> لا توجد بيانات بعد.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>#</th><th>الطالب</th><th>اختبارات</th><th>المتوسط</th><th>اجتياز</th></tr></thead>
                            <tbody>
                                <?php foreach ($topStudents as $index => $student): ?>
                                    <tr>
                                        <td class="small text-muted"><?= e(ar_digits($index + 1)) ?></td>
                                        <td class="small">
                                            <div class="fw-semibold"><?= e($student['full_name']) ?></div>
                                            <div class="text-muted"><?= e($student['email']) ?></div>
                                        </td>
                                        <td class="small"><?= e(ar_digits((int) $student['attempts'])) ?></td>
                                        <td><span class="badge bg-<?= e(score_class((float) $student['avg_score'])) ?>-subtle text-<?= e(score_class((float) $student['avg_score'])) ?>-emphasis">
                                            <?= e(ar_digits($student['avg_score'])) ?>%</span></td>
                                        <td class="small"><?= e(ar_digits((int) $student['passed_count'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="bi bi-exclamation-triangle text-warning me-2"></i> توصيات تطوير المحتوى</div>
            <div class="card-body">
                <ul class="mb-0 small">
                    <?php if ($weakest !== []): ?>
                        <li class="mb-1">
                            المجالات الأضعف لدى الطلاب: <strong><?= e(implode('، ', array_column(array_slice($weakest, 0, 4), 'name_ar'))) ?></strong>
                            — ننصح بإضافة أسئلة وشرح تفصيلي لهذه المجالات.
                        </li>
                    <?php endif; ?>
                    <?php if ($hardest !== []): ?>
                        <li class="mb-1">
                            هناك <strong><?= e(ar_digits(count($hardest))) ?></strong> سؤالاً بنسب خطأ مرتفعة —
                            راجع صحة الإجابات والشرح فيها.
                        </li>
                    <?php endif; ?>
                    <li class="mb-1">
                        عدد الأسئلة التي تحتاج مراجعة: <strong><?= e(ar_digits($overview['questions_review'])) ?></strong>
                        — <a href="<?= e(url('admin/questions', ['needs_review' => 1])) ?>">مراجعتها الآن</a>.
                    </li>
                    <li>
                        نسبة الاجتياز العامة: <strong><?= e(ar_digits($overview['pass_rate'])) ?>%</strong>
                        (متوسط الدرجات <?= e(ar_digits($overview['avg_score'])) ?>%).
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
