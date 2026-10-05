<?php
/** @var array $dashboard @var array $readiness @var array|null $subscription @var array $summary @var bool $isActive @var int $daysLeft @var array $user */
$strengths = $dashboard['strengths'];
$weaknesses = $dashboard['weaknesses'];
$breakdown = $dashboard['breakdown'];
?>
<?php if (!$isActive): ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 shadow-sm">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div class="flex-grow-1">
            <strong>اشتراكك غير مفعّل بعد.</strong>
            فعّل اشتراكك للوصول الكامل إلى بنك الأسئلة والاختبارات التجريبية
            (<?= e(money((float) settings('subscription_price', 100))) ?> لمدة <?= e(ar_digits((int) settings('subscription_days', 365))) ?> يوماً).
        </div>
        <a href="<?= e(url('subscriptions/plans')) ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-credit-card me-1"></i> تفعيل الاشتراك
        </a>
    </div>
<?php endif; ?>

<!-- بطاقات الإحصائيات -->
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="pl-stat">
            <div class="pl-stat-icon"><i class="bi bi-journal-check"></i></div>
            <div>
                <div class="value"><?= e(ar_digits($summary['attempts'])) ?></div>
                <div class="label">اختبار مُنجز</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pl-stat">
            <div class="pl-stat-icon is-gold"><i class="bi bi-graph-up-arrow"></i></div>
            <div>
                <div class="value"><?= e(ar_digits($summary['avg_score'])) ?>%</div>
                <div class="label">متوسط النتيجة</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pl-stat">
            <div class="pl-stat-icon is-teal"><i class="bi bi-patch-check"></i></div>
            <div>
                <div class="value"><?= e(ar_digits($summary['accuracy'])) ?>%</div>
                <div class="label">نسبة الإجابات الصحيحة</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pl-stat">
            <div class="pl-stat-icon is-blue"><i class="bi bi-trophy"></i></div>
            <div>
                <div class="value"><?= e(ar_digits($summary['best_score'])) ?>%</div>
                <div class="label">أفضل نتيجة</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- حالة الاشتراك + نسبة الاستعداد -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-card-checklist text-primary me-2"></i> حالة الاشتراك</span>
                <?= subscription_badge($subscription['status'] ?? null) ?>
            </div>
            <div class="card-body">
                <?php if ($subscription === null): ?>
                    <p class="text-muted small">لا يوجد اشتراك مرتبط بحسابك.</p>
                    <a href="<?= e(url('subscriptions/plans')) ?>" class="btn btn-primary w-100">
                        <i class="bi bi-credit-card me-1"></i> اشترك الآن
                    </a>
                <?php else: ?>
                    <ul class="list-unstyled small mb-3">
                        <li class="d-flex justify-content-between mb-1">
                            <span class="text-muted">الباقة:</span>
                            <strong><?= e($subscription['plan_name'] ?? '') ?></strong>
                        </li>
                        <li class="d-flex justify-content-between mb-1">
                            <span class="text-muted">بداية الاشتراك:</span>
                            <strong><?= e(format_date($subscription['started_at'] ?? null)) ?></strong>
                        </li>
                        <li class="d-flex justify-content-between mb-1">
                            <span class="text-muted">تاريخ الانتهاء:</span>
                            <strong><?= e(format_date($subscription['expires_at'] ?? null)) ?></strong>
                        </li>
                        <?php if ($isActive): ?>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">المتبقي:</span>
                                <strong class="text-success"><?= e(ar_digits($daysLeft)) ?> يوماً</strong>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <?php if ($isActive && $daysLeft <= 14): ?>
                        <div class="alert alert-warning py-2 small mb-3">
                            <i class="bi bi-clock-history me-1"></i> اشتراكك ينتهي قريباً، بادر بالتجديد.
                        </div>
                    <?php endif; ?>
                    <a href="<?= e(url('subscriptions/status')) ?>" class="btn btn-outline-primary w-100 btn-sm">
                        <i class="bi bi-receipt me-1"></i> تفاصيل الاشتراك والمدفوعات
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-speedometer text-primary me-2"></i> نسبة استعدادك</div>
            <div class="card-body text-center">
                <div class="pl-result-ring" style="width:150px;height:150px;">
                    <svg width="150" height="150" viewBox="0 0 120 120">
                        <circle cx="60" cy="60" r="52" fill="none" stroke="var(--pl-border)" stroke-width="12"></circle>
                        <circle cx="60" cy="60" r="52" fill="none" stroke="var(--pl-primary)" stroke-width="12"
                                stroke-linecap="round" stroke-dasharray="326.7"
                                stroke-dashoffset="<?= e((string) round(326.7 * (1 - $readiness['score'] / 100), 1)) ?>"></circle>
                    </svg>
                    <div class="value"><?= e(ar_digits((int) round($readiness['score']))) ?>%<small>استعداد تقديري</small></div>
                </div>
                <div class="row g-2 mt-3 text-center small">
                    <div class="col-4">
                        <div class="fw-bold"><?= e(ar_digits($readiness['accuracy'])) ?>%</div>
                        <div class="text-muted">الدقة</div>
                    </div>
                    <div class="col-4">
                        <div class="fw-bold"><?= e(ar_digits($readiness['average'])) ?>%</div>
                        <div class="text-muted">المتوسط</div>
                    </div>
                    <div class="col-4">
                        <div class="fw-bold"><?= e(ar_digits($readiness['coverage'])) ?>%</div>
                        <div class="text-muted">التغطية</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-lightbulb text-primary me-2"></i> توصيات لك</div>
            <div class="card-body">
                <ul class="list-unstyled small mb-0">
                    <?php foreach (array_slice($dashboard['recommendations'], 0, 3) as $recommendation): ?>
                        <li class="d-flex gap-2 mb-2">
                            <i class="bi bi-check2-circle text-success mt-1"></i>
                            <span><?= e($recommendation) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- تطور النتائج -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-graph-up text-primary me-2"></i> تطور نتائجك</span>
                <a href="<?= e(url('student/statistics')) ?>" class="small">تحليل مفصّل</a>
            </div>
            <div class="card-body" style="height: 280px;">
                <?php if ($dashboard['trend']['values'] === []): ?>
                    <div class="pl-empty">
                        <i class="bi bi-bar-chart"></i>
                        لا توجد نتائج بعد. ابدأ أول اختبار تجريبي ليظهر تطورك هنا.
                    </div>
                <?php else: ?>
                    <canvas data-chart='<?= e(json_encode([
                        'type' => 'line',
                        'data' => ['labels' => $dashboard['trend']['labels'], 'datasets' => [['label' => 'النتيجة %', 'data' => $dashboard['trend']['values']]]],
                    ], JSON_UNESCAPED_UNICODE)) ?>'></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- نقاط القوة والضعف -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-bullseye text-primary me-2"></i> نقاط القوة والضعف</div>
            <div class="card-body">
                <h6 class="small text-success mb-2"><i class="bi bi-arrow-up-circle me-1"></i> نقاط القوة (80% وأكثر)</h6>
                <?php if ($strengths === []): ?>
                    <p class="small text-muted">لا توجد بيانات كافية بعد.</p>
                <?php else: ?>
                    <?php foreach (array_slice($strengths, 0, 4) as $row): ?>
                        <div class="d-flex justify-content-between small mb-1">
                            <span><?= e($row['name']) ?></span>
                            <span class="badge bg-success-subtle text-success-emphasis"><?= e(ar_digits($row['accuracy'])) ?>%</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <hr class="pl-hr">

                <h6 class="small text-danger mb-2"><i class="bi bi-arrow-down-circle me-1"></i> نقاط الضعف (أقل من 60%)</h6>
                <?php if ($weaknesses === []): ?>
                    <p class="small text-muted mb-0">لا توجد نقاط ضعف واضحة — استمر!</p>
                <?php else: ?>
                    <?php foreach (array_slice($weaknesses, 0, 4) as $row): ?>
                        <div class="d-flex justify-content-between small mb-1">
                            <span><?= e($row['name']) ?></span>
                            <span class="badge bg-danger-subtle text-danger-emphasis"><?= e(ar_digits($row['accuracy'])) ?>%</span>
                        </div>
                    <?php endforeach; ?>
                    <div class="alert alert-warning small mt-2 mb-0">
                        ننصحك بمراجعة قسم <strong><?= e($weaknesses[0]['name']) ?></strong> قبل إعادة الاختبار.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- آخر الاختبارات -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history text-primary me-2"></i> آخر الاختبارات</span>
                <a href="<?= e(url('exams/history')) ?>" class="small">عرض السجل الكامل</a>
            </div>
            <div class="card-body p-0">
                <?php if ($dashboard['recent'] === []): ?>
                    <div class="pl-empty">
                        <i class="bi bi-journal-x"></i>
                        لم تخضع لأي اختبار بعد.
                        <div class="mt-3">
                            <a href="<?= e(url('exams/index')) ?>" class="btn btn-primary btn-sm">
                                <i class="bi bi-play-circle me-1"></i> ابدأ اختباراً تجريبياً
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>الاختبار</th>
                                    <th class="d-none d-md-table-cell">المسار</th>
                                    <th>النتيجة</th>
                                    <th class="d-none d-sm-table-cell">التاريخ</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dashboard['recent'] as $attempt): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold small"><?= e(str_limit((string) $attempt['title'], 45)) ?></div>
                                            <div class="text-muted small">
                                                <?= e(ar_digits((int) $attempt['correct_count'])) ?> صحيحة من
                                                <?= e(ar_digits((int) $attempt['total_questions'])) ?>
                                            </div>
                                        </td>
                                        <td class="d-none d-md-table-cell small"><?= e($attempt['track_name']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= e(score_class((float) $attempt['score'])) ?>-subtle text-<?= e(score_class((float) $attempt['score'])) ?>-emphasis">
                                                <?= e(ar_digits((float) $attempt['score'])) ?>%
                                            </span>
                                        </td>
                                        <td class="d-none d-sm-table-cell small text-muted">
                                            <?= e(format_date($attempt['finished_at'] ?? $attempt['started_at'])) ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($attempt['status'] === 'in_progress'): ?>
                                                <a href="<?= e(url('exams/take', ['id' => (int) $attempt['id']])) ?>" class="btn btn-sm btn-warning">متابعة</a>
                                            <?php else: ?>
                                                <a href="<?= e(url('exams/review', ['id' => (int) $attempt['id']])) ?>" class="btn btn-sm btn-outline-primary">مراجعة</a>
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
    </div>

    <!-- اختصارات سريعة -->
    <div class="col-12">
        <div class="row g-3">
            <div class="col-md-4">
                <a href="<?= e(url('exams/index')) ?>" class="card text-decoration-none h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="pl-stat-icon"><i class="bi bi-journal-check"></i></div>
                        <div>
                            <div class="fw-bold">اختبار تجريبي كامل</div>
                            <div class="small text-muted">محاكاة بنفس نمط الاختبار</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="<?= e(url('questions/practice')) ?>" class="card text-decoration-none h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="pl-stat-icon is-gold"><i class="bi bi-lightning-charge"></i></div>
                        <div>
                            <div class="fw-bold">تدريب سريع</div>
                            <div class="small text-muted">سؤال ثم إجابة وشرح مباشرة</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="<?= e(url('questions/index')) ?>" class="card text-decoration-none h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="pl-stat-icon is-teal"><i class="bi bi-collection"></i></div>
                        <div>
                            <div class="fw-bold">تدريب حسب المجال</div>
                            <div class="small text-muted">اختر المجال الذي تريد تقويته</div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>
