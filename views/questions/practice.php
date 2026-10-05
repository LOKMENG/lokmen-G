<?php /** @var array $tracks @var int $trackId @var array $categories @var bool $isActive */ ?>
<div class="row g-3 justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-lightning-charge text-primary me-2"></i> إعداد التدريب السريع</div>
            <div class="card-body">
                <?php if (!$isActive): ?>
                    <div class="alert alert-warning small">
                        <i class="bi bi-lock me-1"></i> التدريب الكامل يتطلب اشتراكاً فعّالاً.
                        <a href="<?= e(url('subscriptions/plans')) ?>">تفعيل الاشتراك</a>
                    </div>
                <?php endif; ?>
                <form method="post" class="row g-3 align-items-end">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="quick">
                    <div class="col-md-6">
                        <label class="form-label" for="track_id">المسار</label>
                        <select class="form-select" id="track_id" name="track_id" onchange="location.href='<?= e(url('questions/practice')) ?>?track='+this.value">
                            <?php foreach ($tracks as $track): ?>
                                <option value="<?= (int) $track['id'] ?>" <?= $trackId === (int) $track['id'] ? 'selected' : '' ?>>
                                    <?= e($track['name_ar']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="count">عدد الأسئلة</label>
                        <select class="form-select" id="count" name="count">
                            <?php foreach ([5, 10, 15, 20, 30] as $count): ?>
                                <option value="<?= (int) $count ?>" <?= $count === 10 ? 'selected' : '' ?>><?= e(ar_digits($count)) ?> أسئلة</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="randomize_options" name="randomize_options" value="1" checked>
                            <label class="form-check-label" for="randomize_options">ترتيب عشوائي للاختيارات (لتجنب حفظ موضع الإجابة)</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-play-fill me-1"></i> ابدأ التدريب الآن
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-collection text-primary me-2"></i> تدريب مركّز على مجال</div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($categories as $category): ?>
                        <form method="post" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="quick">
                            <input type="hidden" name="track_id" value="<?= (int) $trackId ?>">
                            <input type="hidden" name="count" value="15">
                            <input type="hidden" name="category_ids[]" value="<?= (int) $category['id'] ?>">
                            <button class="btn btn-sm btn-outline-primary" <?= (int) $category['total_questions'] === 0 ? 'disabled' : '' ?>>
                                <?= e($category['name_ar']) ?>
                                <span class="badge bg-light text-dark border ms-1"><?= e(ar_digits((int) $category['total_questions'])) ?></span>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
                <div class="form-text mt-2">ملاحظة: لتحديد مجال معيّن استخدم صفحة «التدريب حسب المجال».</div>
            </div>
        </div>
    </div>
</div>
