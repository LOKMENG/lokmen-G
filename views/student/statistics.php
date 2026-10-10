<?php
/** @var array $breakdown @var array $strengths @var array $weaknesses @var array $summary @var array $readiness @var array $recent @var array $tracks @var int $trackFilter */
?>
<div class="row g-3 mb-3 rv">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <span><i class="bi bi-bar-chart-line text-primary me-2"></i> نسبة الإجابات الصحيحة حسب المجال</span>
                <form method="get" class="d-flex gap-2">
                    <select name="track" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">كل المسارات</option>
                        <?php foreach ($tracks as $track): ?>
                            <option value="<?= (int) $track['id'] ?>" <?= $trackFilter === (int) $track['id'] ? 'selected' : '' ?>>
                                <?= e($track['name_ar']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="card-body" style="height: 320px;">
                <?php if ($breakdown === []): ?>
                    <div class="pl-empty">
                        <i class="bi bi-clipboard-data"></i>
                        لا توجد بيانات كافية. أدِّ اختباراً أو تدريباً ليبدأ النظام بتحليل مستواك.
                        <div class="mt-3"><a href="<?= e(url('exams/index')) ?>" class="btn btn-primary btn-sm">ابدأ اختباراً</a></div>
                    </div>
                <?php else: ?>
                    <canvas data-chart='<?= e(json_encode([
                        'type' => 'bar',
                        'data' => [
                            'labels' => array_column($breakdown, 'name'),
                            'datasets' => [['label' => 'نسبة الدقة %', 'data' => array_column($breakdown, 'accuracy')]],
                        ],
                    ], JSON_UNESCAPED_UNICODE)) ?>'></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-clipboard-check text-primary me-2"></i> ملخص الأداء</div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="d-flex justify-content-between border-bottom py-2">
                        <span class="text-muted">عدد الاختبارات</span><strong><?= e(ar_digits($summary['attempts'])) ?></strong>
                    </li>
                    <li class="d-flex justify-content-between border-bottom py-2">
                        <span class="text-muted">إجابات صحيحة</span><strong class="text-success"><?= e(ar_digits($summary['correct'])) ?></strong>
                    </li>
                    <li class="d-flex justify-content-between border-bottom py-2">
                        <span class="text-muted">إجابات خاطئة</span><strong class="text-danger"><?= e(ar_digits($summary['wrong'])) ?></strong>
                    </li>
                    <li class="d-flex justify-content-between border-bottom py-2">
                        <span class="text-muted">بدون إجابة</span><strong><?= e(ar_digits($summary['unanswered'])) ?></strong>
                    </li>
                    <li class="d-flex justify-content-between border-bottom py-2">
                        <span class="text-muted">المتوسط العام</span><strong><?= e(ar_digits($summary['avg_score'])) ?>%</strong>
                    </li>
                    <li class="d-flex justify-content-between py-2">
                        <span class="text-muted">نسبة الاستعداد</span>
                        <strong class="text-primary"><?= e(ar_digits((int) round($readiness['score']))) ?>%</strong>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php if ($breakdown !== []): ?>
    <div class="card mb-3 rv d1">
        <div class="card-header"><i class="bi bi-list-check text-primary me-2"></i> تفصيل المجالات</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>المجال</th>
                            <th class="d-none d-md-table-cell">المسار</th>
                            <th>الأسئلة</th>
                            <th>الصحيحة</th>
                            <th style="min-width: 160px;">نسبة الدقة</th>
                            <th>التقييم</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($breakdown as $row): ?>
                            <tr>
                                <td class="fw-semibold"><?= e($row['name']) ?></td>
                                <td class="d-none d-md-table-cell small text-muted"><?= e($row['track_name']) ?></td>
                                <td><?= e(ar_digits($row['total'])) ?></td>
                                <td><?= e(ar_digits($row['correct'])) ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress pl-progress-thin flex-grow-1" style="min-width: 80px;">
                                            <div class="progress-bar bg-<?= e(score_class($row['accuracy'])) ?>"
                                                 style="width: <?= (float) $row['accuracy'] ?>%"></div>
                                        </div>
                                        <span class="small fw-bold"><?= e(ar_digits($row['accuracy'])) ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($row['level'] === 'strength'): ?>
                                        <span class="badge bg-success-subtle text-success-emphasis">نقطة قوة</span>
                                    <?php elseif ($row['level'] === 'weakness'): ?>
                                        <span class="badge bg-danger-subtle text-danger-emphasis">تحتاج مراجعة</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis">متوسط</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= e(url('questions/index', ['category' => (int) $row['category_id']])) ?>" class="btn btn-sm btn-outline-primary">
                                        تدريب
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3 rv d2">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header text-success"><i class="bi bi-arrow-up-circle me-2"></i> نقاط القوة</div>
            <div class="card-body">
                <?php if ($strengths === []): ?>
                    <p class="text-muted small mb-0">لم تصل بعد إلى 80% في أي مجال. ركّز على التدريب المستمر.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($strengths as $row): ?>
                            <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <span><?= e($row['name']) ?></span>
                                <span class="badge bg-success"><?= e(ar_digits($row['accuracy'])) ?>%</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header text-danger"><i class="bi bi-arrow-down-circle me-2"></i> نقاط الضعف والتوصيات</div>
            <div class="card-body">
                <?php if ($weaknesses === []): ?>
                    <p class="text-muted small mb-0">لا توجد مجالات تحت 60% حالياً.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-3">
                        <?php foreach ($weaknesses as $row): ?>
                            <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <span><?= e($row['name']) ?></span>
                                <span class="badge bg-danger"><?= e(ar_digits($row['accuracy'])) ?>%</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="alert alert-warning small mb-0">
                        <i class="bi bi-lightbulb me-1"></i>
                        ننصحك بمراجعة قسم <?= e($weaknesses[0]['name']) ?> قبل إعادة الاختبار، والتدريب على أسئلته مركّزاً على شرح الإجابات.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
