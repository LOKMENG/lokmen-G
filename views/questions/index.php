<?php
/** @var array $tracks @var int $trackId @var array $tree @var int $highlight @var bool $isActive */
?>
<div class="d-flex flex-wrap gap-2 mb-3">
    <?php foreach ($tracks as $track): ?>
        <a href="<?= e(url('questions/index', ['track' => (int) $track['id']])) ?>"
           class="btn btn-sm <?= $trackId === (int) $track['id'] ? 'btn-primary' : 'btn-outline-primary' ?>">
            <i class="bi <?= e($track['icon'] ?: 'bi-book') ?> me-1"></i> <?= e($track['name_ar']) ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if (!$isActive): ?>
    <div class="alert alert-warning d-flex flex-wrap gap-2 align-items-center">
        <i class="bi bi-lock-fill"></i>
        <div class="flex-grow-1">التدريب الكامل يتطلب اشتراكاً فعّالاً.</div>
        <a href="<?= e(url('subscriptions/plans')) ?>" class="btn btn-sm btn-primary">تفعيل الاشتراك</a>
    </div>
<?php endif; ?>

<div class="row g-3 rv">
    <?php foreach ($tree as $category): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100 <?= $highlight === (int) $category['id'] ? 'border-primary' : '' ?>">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="pl-stat-icon" style="background: <?= e($category['color'] ?: '#0b6b3a') ?>1a; color: <?= e($category['color'] ?: '#0b6b3a') ?>">
                            <i class="bi <?= e($category['icon'] ?: 'bi-folder') ?>"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold"><?= e($category['name_ar']) ?></h6>
                            <div class="small text-muted">
                                <?= e(ar_digits((int) $category['total_questions'])) ?> سؤالاً متاحاً
                            </div>
                        </div>
                    </div>

                    <?php if ($category['children'] !== []): ?>
                        <div class="d-flex flex-wrap gap-1 mb-3">
                            <?php foreach ($category['children'] as $child): ?>
                                <span class="badge bg-light text-dark border">
                                    <?= e($child['name_ar']) ?>
                                    <span class="text-muted">(<?= e(ar_digits((int) $child['questions_count'])) ?>)</span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mt-auto d-flex gap-2">
                        <form method="post" action="<?= e(url('exams/start')) ?>" class="flex-grow-1">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="custom">
                            <input type="hidden" name="track_id" value="<?= (int) $trackId ?>">
                            <input type="hidden" name="category_ids[]" value="<?= (int) $category['id'] ?>">
                            <input type="hidden" name="count" value="20">
                            <input type="hidden" name="duration_minutes" value="0">
                            <input type="hidden" name="mode" value="practice">
                            <button class="btn btn-sm btn-primary w-100" <?= (int) $category['total_questions'] === 0 ? 'disabled' : '' ?>>
                                <i class="bi bi-play-fill me-1"></i> تدريب (20 سؤالاً)
                            </button>
                        </form>
                        <form method="post" action="<?= e(url('exams/start')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="custom">
                            <input type="hidden" name="track_id" value="<?= (int) $trackId ?>">
                            <input type="hidden" name="category_ids[]" value="<?= (int) $category['id'] ?>">
                            <input type="hidden" name="count" value="20">
                            <input type="hidden" name="duration_minutes" value="20">
                            <input type="hidden" name="mode" value="category">
                            <button class="btn btn-sm btn-outline-primary" title="اختبار بمؤقت" <?= (int) $category['total_questions'] === 0 ? 'disabled' : '' ?>>
                                <i class="bi bi-stopwatch"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
