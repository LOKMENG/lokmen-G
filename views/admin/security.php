<?php
/** @var array $checks @var array $summary @var string $baseUrl */
$icons  = ['ok' => 'bi-check-circle-fill text-success', 'warn' => 'bi-exclamation-triangle-fill text-warning', 'danger' => 'bi-x-octagon-fill text-danger'];
$labels = ['ok' => 'مطبَّق', 'warn' => 'يحتاج انتباهاً', 'danger' => 'خطر'];
?>
<div class="row g-3 mb-3">
    <?php
    $cards = [
        ['bi-shield-check', 'بنود مطبَّقة', ar_digits($summary['ok'] ?? 0), 'is-teal'],
        ['bi-exclamation-triangle', 'تحتاج انتباهاً', ar_digits($summary['warn'] ?? 0), 'is-gold'],
        ['bi-x-octagon', 'بنود خطرة', ar_digits($summary['danger'] ?? 0), 'is-blue'],
    ];
    foreach ($cards as [$icon, $label, $value, $class]):
    ?>
        <div class="col-4">
            <div class="pl-stat">
                <div class="pl-stat-icon <?= e($class) ?>"><i class="bi <?= e($icon) ?>"></i></div>
                <div><div class="value"><?= e($value) ?></div><div class="label"><?= e($label) ?></div></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (($summary['danger'] ?? 0) > 0): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="bi bi-x-octagon-fill"></i>
        <div>يوجد <?= ar_digits($summary['danger']) ?> بند «خطر» — عالجها قبل التشغيل الفعلي للموقع.</div>
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-shield-lock text-primary me-2"></i> تقرير فحص الحماية</span>
        <div class="d-flex gap-2">
            <form method="post" action="<?= e(url('admin/security')) ?>" class="d-flex gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="fix-perms">
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-file-lock2 me-1"></i> إصلاح صلاحية .env
                </button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width:38px;"></th>
                        <th>البند</th>
                        <th>الحالة</th>
                        <th>التفصيل</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($checks as $check): ?>
                    <tr>
                        <td><i class="bi <?= e($icons[$check['level']] ?? '') ?>"></i></td>
                        <td class="fw-semibold"><?= e($check['title']) ?></td>
                        <td>
                            <span class="badge bg-<?= $check['level'] === 'ok' ? 'success' : ($check['level'] === 'warn' ? 'warning' : 'danger') ?>">
                                <?= e($labels[$check['level']] ?? $check['level']) ?>
                            </span>
                        </td>
                        <td>
                            <?= e($check['detail']) ?>
                            <?php if ($check['level'] !== 'ok' && $check['fix'] !== ''): ?>
                                <div class="text-muted small mt-1"><i class="bi bi-arrow-return-left me-1"></i><?= e($check['fix']) ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-globe2 text-primary me-2"></i> فحص الوصول عبر HTTP</div>
    <div class="card-body">
        <p class="text-muted small">
            يتحقق أن المسارات الحساسة (ملف البيئة، قاعدة البيانات، مجلد المصدر) غير متاحة للعامة.
            أدخل رابط موقعك ليجرى الفحص من الخادم نفسه.
        </p>
        <form method="post" action="<?= e(url('admin/security')) ?>" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="check-http">
            <div class="col-md-8">
                <label class="form-label" for="base_url">رابط الموقع</label>
                <input type="url" class="form-control" id="base_url" name="base_url" dir="ltr"
                       placeholder="https://example.com" value="<?= e($baseUrl) ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> تشغيل الفحص</button>
            </div>
        </form>
    </div>
</div>
