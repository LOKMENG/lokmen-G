<?php
/** @var array $tracks @var int $trackId @var array $categories @var array $templates @var bool $isActive @var array $modes */
?>
<?php if (!$isActive): ?>
    <div class="alert alert-warning d-flex flex-wrap gap-2 align-items-center">
        <i class="bi bi-lock-fill"></i>
        <div class="flex-grow-1">الاختبارات التجريبية تتطلب اشتراكاً فعّالاً (النماذج المجانية فقط هي المتاحة حالياً).</div>
        <a href="<?= e(url('subscriptions/plans')) ?>" class="btn btn-primary btn-sm">تفعيل الاشتراك</a>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <span><i class="bi bi-magic text-primary me-2"></i> إنشاء اختبار مخصص</span>
                <span class="badge bg-light text-dark border">طريقة مرنة للتدريب المركّز</span>
            </div>
            <div class="card-body">
                <form method="post" action="<?= e(url('exams/start')) ?>" class="row g-3 align-items-end">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="custom">
                    <div class="col-md-4">
                        <label class="form-label" for="track_id">المسار</label>
                        <select class="form-select" id="track_id" name="track_id" onchange="location.href='<?= e(url('exams/index')) ?>?track='+this.value">
                            <?php foreach ($tracks as $track): ?>
                                <option value="<?= (int) $track['id'] ?>" <?= $trackId === (int) $track['id'] ? 'selected' : '' ?>>
                                    <?= e($track['name_ar']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="category_ids">المجال (اختياري متعدد)</label>
                        <select class="form-select" id="category_ids" name="category_ids[]" multiple size="4">
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>">
                                    <?= e($category['name_ar']) ?>
                                    (<?= e(ar_digits((int) $category['total_questions'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">اتركه فارغاً ليشمل كل المجالات.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="difficulty">مستوى الصعوبة</label>
                        <select class="form-select" id="difficulty" name="difficulty">
                            <option value="any">متنوّع (الكل)</option>
                            <option value="easy">سهل</option>
                            <option value="medium">متوسط</option>
                            <option value="hard">صعب</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="count">عدد الأسئلة</label>
                        <select class="form-select" id="count" name="count">
                            <?php foreach ([5, 10, 15, 20, 25, 30, 40, 50] as $count): ?>
                                <option value="<?= (int) $count ?>" <?= $count === 20 ? 'selected' : '' ?>><?= e(ar_digits($count)) ?> سؤالاً</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="duration">مدة الاختبار</label>
                        <select class="form-select" id="duration" name="duration_minutes">
                            <option value="0">بدون مؤقت</option>
                            <?php foreach ([5, 10, 15, 20, 30, 45, 60, 90] as $minutes): ?>
                                <option value="<?= (int) $minutes ?>" <?= $minutes === 30 ? 'selected' : '' ?>><?= e(ar_digits($minutes)) ?> دقيقة</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="mode">نمط التدريب</label>
                        <select class="form-select" id="mode" name="mode">
                            <option value="mock">اختبار (بدون إظهار الإجابة)</option>
                            <option value="practice">تدريب (إظهار الإجابة والشرح فوراً)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="randomize_options" name="randomize_options" value="1">
                            <label class="form-check-label" for="randomize_options">ترتيب عشوائي للاختيارات</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mt-2">
                            <i class="bi bi-play-fill me-1"></i> ابدأ الاختبار
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<h5 class="mb-3"><i class="bi bi-journals text-primary me-2"></i> النماذج الجاهزة</h5>
<div class="row g-3">
    <?php if ($templates === []): ?>
        <div class="col-12">
            <div class="card"><div class="card-body pl-empty"><i class="bi bi-journal-x"></i> لا توجد نماذج اختبار متاحة حالياً.</div></div>
        </div>
    <?php endif; ?>
    <?php foreach ($templates as $template): ?>
        <?php $locked = (int) $template['require_subscription'] === 1 && !$isActive; ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge bg-primary-subtle text-primary-emphasis"><?= e($modes[$template['mode']] ?? $template['mode']) ?></span>
                        <span class="badge bg-light text-dark border"><?= e($template['track_name']) ?></span>
                    </div>
                    <h6 class="fw-bold mb-1"><?= e($template['title']) ?></h6>
                    <p class="text-muted small flex-grow-1"><?= e(str_limit((string) $template['description'], 110)) ?></p>
                    <div class="d-flex flex-wrap gap-2 small text-muted mb-3">
                        <span><i class="bi bi-list-ol me-1"></i><?= e(ar_digits((int) $template['question_count'])) ?> سؤالاً</span>
                        <span><i class="bi bi-clock me-1"></i>
                            <?= (int) $template['duration_minutes'] > 0 ? e(ar_digits((int) $template['duration_minutes'])) . ' دقيقة' : 'بدون مؤقت' ?>
                        </span>
                        <span><i class="bi bi-mortarboard me-1"></i>النجاح <?= e(ar_digits((int) $template['pass_percentage'])) ?>%</span>
                    </div>
                    <?php if ($locked): ?>
                        <a href="<?= e(url('subscriptions/plans')) ?>" class="btn btn-outline-warning">
                            <i class="bi bi-lock me-1"></i> يتطلب اشتراكاً
                        </a>
                    <?php else: ?>
                        <form method="post" action="<?= e(url('exams/start')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="template">
                            <input type="hidden" name="template_id" value="<?= (int) $template['id'] ?>">
                            <button class="btn btn-primary w-100"><i class="bi bi-play-fill me-1"></i> ابدأ الآن</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
