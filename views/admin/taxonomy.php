<?php
/**
 * @var string $tab @var array $tracks @var array $categories @var array $sources
 * @var array|null $editTrack @var array|null $editCategory @var array|null $editSource
 */
$tabs = ['tracks' => ['bi-signpost-split', 'المسارات'], 'categories' => ['bi-diagram-3', 'المجالات والمحاور'], 'sources' => ['bi-journal-bookmark', 'المصادر']];
$trackNames = [];
foreach ($tracks as $track) {
    $trackNames[(int) $track['id']] = (string) $track['name_ar'];
}
$categoryNames = [];
foreach ($categories as $category) {
    if ($category['parent_id'] === null) {
        $categoryNames[(int) $category['id']] = (string) $category['name_ar'];
    }
}
?>
<ul class="nav nav-pills mb-3 flex-wrap gap-1">
    <?php foreach ($tabs as $key => [$icon, $label]): ?>
        <li class="nav-item">
            <a class="nav-link <?= $tab === $key ? 'active' : '' ?>" href="<?= e(url('admin/taxonomy', ['tab' => $key])) ?>">
                <i class="bi <?= e($icon) ?> me-1"></i> <?= e($label) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($tab === 'tracks'): ?>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-<?= $editTrack !== null ? 'pencil-square' : 'plus-circle' ?> text-primary me-2"></i>
                    <?= $editTrack !== null ? 'تعديل مسار' : 'إضافة مسار جديد' ?>
                </div>
                <div class="card-body">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_track">
                        <input type="hidden" name="tab" value="tracks">
                        <input type="hidden" name="id" value="<?= (int) ($editTrack['id'] ?? 0) ?>">
                        <div class="mb-3">
                            <label class="form-label" for="track_name">الاسم بالعربية</label>
                            <input type="text" class="form-control" id="track_name" name="name_ar" required value="<?= e((string) ($editTrack['name_ar'] ?? '')) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="track_name_en">الاسم بالإنجليزية (اختياري)</label>
                            <input type="text" class="form-control" id="track_name_en" name="name_en" value="<?= e((string) ($editTrack['name_en'] ?? '')) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="track_code">الرمز (يُولَّد تلقائياً إن تُرك فارغاً)</label>
                            <input type="text" class="form-control" id="track_code" name="code" value="<?= e((string) ($editTrack['code'] ?? '')) ?>">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label" for="track_icon">الأيقونة (Bootstrap Icons)</label>
                                <input type="text" class="form-control" id="track_icon" name="icon" dir="ltr" value="<?= e((string) ($editTrack['icon'] ?? 'bi-book')) ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="track_color">اللون</label>
                                <input type="color" class="form-control form-control-color w-100" id="track_color" name="color" value="<?= e((string) ($editTrack['color'] ?? '#0b6b3a')) ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="track_description">الوصف</label>
                            <textarea class="form-control" id="track_description" name="description" rows="2"><?= e((string) ($editTrack['description'] ?? '')) ?></textarea>
                        </div>
                        <div class="row g-2 mb-3 align-items-end">
                            <div class="col-6">
                                <label class="form-label" for="track_sort">الترتيب</label>
                                <input type="number" class="form-control" id="track_sort" name="sort_order" value="<?= (int) ($editTrack['sort_order'] ?? 0) ?>">
                            </div>
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="track_active" name="is_active" value="1"
                                        <?= ($editTrack === null || (int) $editTrack['is_active'] === 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="track_active">نشط</label>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary flex-grow-1"><i class="bi bi-save me-1"></i> حفظ</button>
                            <?php if ($editTrack !== null): ?>
                                <a href="<?= e(url('admin/taxonomy', ['tab' => 'tracks'])) ?>" class="btn btn-outline-secondary">إلغاء</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><i class="bi bi-signpost-split text-primary me-2"></i> المسارات (<?= e(ar_digits(count($tracks))) ?>)</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>#</th><th>المسار</th><th>الرمز</th><th>مجالات</th><th>أسئلة</th><th>الترتيب</th><th>الحالة</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($tracks as $track): ?>
                                    <?php
                                    $categoryCount = count(array_filter($categories, static fn(array $c) => (int) $c['track_id'] === (int) $track['id']));
                                    $questionCount = (int) db()->value('SELECT COUNT(*) FROM `questions` WHERE track_id = :id', ['id' => (int) $track['id']], 0);
                                    ?>
                                    <tr>
                                        <td class="small text-muted"><?= e(ar_digits((int) $track['id'])) ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi <?= e($track['icon'] ?: 'bi-book') ?>" style="color: <?= e($track['color'] ?: '#0b6b3a') ?>;"></i>
                                                <div>
                                                    <div class="fw-semibold small"><?= e($track['name_ar']) ?></div>
                                                    <?php if (!empty($track['description'])): ?>
                                                        <div class="text-muted" style="font-size:.75rem;"><?= e(str_limit((string) $track['description'], 60)) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="small pl-copy"><?= e($track['code']) ?></td>
                                        <td class="small"><?= e(ar_digits($categoryCount)) ?></td>
                                        <td class="small"><?= e(ar_digits($questionCount)) ?></td>
                                        <td class="small"><?= e(ar_digits((int) $track['sort_order'])) ?></td>
                                        <td><?= active_badge((int) $track['is_active'] === 1) ?></td>
                                        <td class="text-end text-nowrap">
                                            <a href="<?= e(url('admin/taxonomy', ['tab' => 'tracks', 'edit_track' => (int) $track['id']])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                            <form method="post" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_track">
                                                <input type="hidden" name="tab" value="tracks">
                                                <input type="hidden" name="id" value="<?= (int) $track['id'] ?>">
                                                <button class="btn btn-sm btn-outline-danger" data-confirm="حذف المسار وكل مجالاته؟"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'categories'): ?>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-<?= $editCategory !== null ? 'pencil-square' : 'plus-circle' ?> text-primary me-2"></i>
                    <?= $editCategory !== null ? 'تعديل مجال' : 'إضافة مجال / محور' ?>
                </div>
                <div class="card-body">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_category">
                        <input type="hidden" name="tab" value="categories">
                        <input type="hidden" name="id" value="<?= (int) ($editCategory['id'] ?? 0) ?>">
                        <div class="mb-3">
                            <label class="form-label" for="cat_track">المسار</label>
                            <select class="form-select" id="cat_track" name="track_id" required>
                                <option value="">— اختر —</option>
                                <?php foreach ($tracks as $track): ?>
                                    <option value="<?= (int) $track['id'] ?>" <?= (int) ($editCategory['track_id'] ?? 0) === (int) $track['id'] ? 'selected' : '' ?>>
                                        <?= e($track['name_ar']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="cat_parent">المجال الأب (اتركه فارغاً لمجال رئيسي)</label>
                            <select class="form-select" id="cat_parent" name="parent_id">
                                <option value="">— مجال رئيسي —</option>
                                <?php foreach ($categoryNames as $categoryId => $categoryName): ?>
                                    <?php if ((int) ($editCategory['id'] ?? 0) === $categoryId) { continue; } ?>
                                    <option value="<?= (int) $categoryId ?>" <?= (int) ($editCategory['parent_id'] ?? 0) === $categoryId ? 'selected' : '' ?>>
                                        <?= e($categoryName) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="cat_name">الاسم بالعربية</label>
                            <input type="text" class="form-control" id="cat_name" name="name_ar" required value="<?= e((string) ($editCategory['name_ar'] ?? '')) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="cat_code">الرمز</label>
                            <input type="text" class="form-control" id="cat_code" name="code" value="<?= e((string) ($editCategory['code'] ?? '')) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="cat_description">الوصف</label>
                            <textarea class="form-control" id="cat_description" name="description" rows="2"><?= e((string) ($editCategory['description'] ?? '')) ?></textarea>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-4">
                                <label class="form-label" for="cat_icon">الأيقونة</label>
                                <input type="text" class="form-control" id="cat_icon" name="icon" dir="ltr" value="<?= e((string) ($editCategory['icon'] ?? 'bi-folder')) ?>">
                            </div>
                            <div class="col-4">
                                <label class="form-label" for="cat_color">اللون</label>
                                <input type="color" class="form-control form-control-color w-100" id="cat_color" name="color" value="<?= e((string) ($editCategory['color'] ?? '#0b6b3a')) ?>">
                            </div>
                            <div class="col-4">
                                <label class="form-label" for="cat_sort">الترتيب</label>
                                <input type="number" class="form-control" id="cat_sort" name="sort_order" value="<?= (int) ($editCategory['sort_order'] ?? 0) ?>">
                            </div>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="cat_active" name="is_active" value="1"
                                <?= ($editCategory === null || (int) $editCategory['is_active'] === 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="cat_active">نشط</label>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary flex-grow-1"><i class="bi bi-save me-1"></i> حفظ</button>
                            <?php if ($editCategory !== null): ?>
                                <a href="<?= e(url('admin/taxonomy', ['tab' => 'categories'])) ?>" class="btn btn-outline-secondary">إلغاء</a>
                            <?php endif; ?>
                        </div>
                    </form>
                    <div class="alert alert-light border small mt-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        تصنيفات المنصة <strong>تعريفية</strong> وضعها مالك المنصة، وليست التصنيف الرسمي لهيئة تقويم التعليم والتدريب.
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><i class="bi bi-diagram-3 text-primary me-2"></i> المجالات والمحاور (<?= e(ar_digits(count($categories))) ?>)</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>#</th><th>المجال</th><th>المسار</th><th>النوع</th><th>أسئلة</th><th>الترتيب</th><th>الحالة</th><th></th></tr></thead>
                            <tbody>
                                <?php
                                // عرض المجالات الرئيسية ثم محاورها
                                $byParent = [];
                                foreach ($categories as $category) {
                                    $byParent[(int) ($category['parent_id'] ?? 0)][] = $category;
                                }
                                $renderRow = static function (array $category, bool $isChild) use ($trackNames): void { ?>
                                    <tr>
                                        <td class="small text-muted"><?= e(ar_digits((int) $category['id'])) ?></td>
                                        <td class="small">
                                            <?= $isChild ? '<span class="text-muted me-1">↳</span>' : '' ?>
                                            <span class="fw-semibold"><?= e($category['name_ar']) ?></span>
                                        </td>
                                        <td class="small text-muted"><?= e($trackNames[(int) $category['track_id']] ?? '') ?></td>
                                        <td class="small"><?= $isChild ? '<span class="badge bg-light text-dark border">محور</span>' : '<span class="badge bg-primary-subtle text-primary-emphasis">مجال رئيسي</span>' ?></td>
                                        <td class="small"><?= e(ar_digits((int) $category['questions_count'] + (int) $category['sub_questions_count'])) ?></td>
                                        <td class="small"><?= e(ar_digits((int) $category['sort_order'])) ?></td>
                                        <td><?= active_badge((int) $category['is_active'] === 1) ?></td>
                                        <td class="text-end text-nowrap">
                                            <a href="<?= e(url('admin/taxonomy', ['tab' => 'categories', 'edit_category' => (int) $category['id']])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                            <form method="post" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_category">
                                                <input type="hidden" name="tab" value="categories">
                                                <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                                                <button class="btn btn-sm btn-outline-danger" data-confirm="حذف المجال؟"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php };
                                foreach ($byParent[0] ?? [] as $parent) {
                                    $renderRow($parent, false);
                                    foreach ($byParent[(int) $parent['id']] ?? [] as $child) {
                                        $renderRow($child, true);
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-<?= $editSource !== null ? 'pencil-square' : 'plus-circle' ?> text-primary me-2"></i>
                    <?= $editSource !== null ? 'تعديل مصدر' : 'إضافة مصدر' ?>
                </div>
                <div class="card-body">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_source">
                        <input type="hidden" name="tab" value="sources">
                        <input type="hidden" name="id" value="<?= (int) ($editSource['id'] ?? 0) ?>">
                        <div class="mb-3">
                            <label class="form-label" for="src_name">اسم المصدر</label>
                            <input type="text" class="form-control" id="src_name" name="name" required value="<?= e((string) ($editSource['name'] ?? '')) ?>"
                                   placeholder="مثال: ملزمة الرياضيات — إعداد المعلم">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label" for="src_type">النوع</label>
                                <select class="form-select" id="src_type" name="type">
                                    <?php foreach (['pdf' => 'ملف PDF', 'book' => 'كتاب', 'website' => 'موقع', 'official' => 'جهة رسمية', 'teacher' => 'إعداد معلم', 'other' => 'أخرى'] as $key => $label): ?>
                                        <option value="<?= e($key) ?>" <?= (string) ($editSource['type'] ?? 'pdf') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="src_year">السنة</label>
                                <input type="number" class="form-control" id="src_year" name="year" min="1300" max="1500" value="<?= (int) ($editSource['year'] ?? 0) ?: '' ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="src_author">الجهة/المؤلف</label>
                            <input type="text" class="form-control" id="src_author" name="author" value="<?= e((string) ($editSource['author'] ?? '')) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="src_url">الرابط (اختياري)</label>
                            <input type="url" class="form-control" id="src_url" name="url" dir="ltr" value="<?= e((string) ($editSource['url'] ?? '')) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="src_license">إقرار حق الاستخدام</label>
                            <input type="text" class="form-control" id="src_license" name="license_note" maxlength="255"
                                   value="<?= e((string) ($editSource['license_note'] ?? '')) ?>"
                                   placeholder="مثال: بموافقة المالك / ملف مرخّص للاستخدام">
                            <div class="form-text text-warning-emphasis"><i class="bi bi-shield-exclamation me-1"></i> لا تُضف محتوى محمياً بحقوق ملكية دون تصريح.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="src_notes">ملاحظات</label>
                            <textarea class="form-control" id="src_notes" name="notes" rows="2"><?= e((string) ($editSource['notes'] ?? '')) ?></textarea>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="src_active" name="is_active" value="1"
                                <?= ($editSource === null || (int) $editSource['is_active'] === 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="src_active">نشط</label>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary flex-grow-1"><i class="bi bi-save me-1"></i> حفظ</button>
                            <?php if ($editSource !== null): ?>
                                <a href="<?= e(url('admin/taxonomy', ['tab' => 'sources'])) ?>" class="btn btn-outline-secondary">إلغاء</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><i class="bi bi-journal-bookmark text-primary me-2"></i> المصادر (<?= e(ar_digits(count($sources))) ?>)</div>
                <div class="card-body p-0">
                    <?php if ($sources === []): ?>
                        <div class="pl-empty"><i class="bi bi-journal-x"></i> لا توجد مصادر مسجّلة بعد.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead><tr><th>#</th><th>المصدر</th><th>النوع</th><th>السنة</th><th>أسئلة</th><th>إقرار الاستخدام</th><th>الحالة</th><th></th></tr></thead>
                                <tbody>
                                    <?php foreach ($sources as $source): ?>
                                        <tr>
                                            <td class="small text-muted"><?= e(ar_digits((int) $source['id'])) ?></td>
                                            <td class="small">
                                                <div class="fw-semibold"><?= e($source['name']) ?></div>
                                                <?php if (!empty($source['author'])): ?><div class="text-muted"><?= e($source['author']) ?></div><?php endif; ?>
                                            </td>
                                            <td class="small">
                                                <?= e(['pdf' => 'PDF', 'book' => 'كتاب', 'website' => 'موقع', 'official' => 'جهة رسمية', 'teacher' => 'إعداد معلم', 'other' => 'أخرى'][$source['type']] ?? $source['type']) ?>
                                            </td>
                                            <td class="small"><?= e($source['year'] !== null ? ar_digits((int) $source['year']) : '—') ?></td>
                                            <td class="small"><?= e(ar_digits((int) $source['questions_count'])) ?></td>
                                            <td class="small">
                                                <?php if (!empty($source['license_note'])): ?>
                                                    <span class="badge bg-success-subtle text-success-emphasis">موثّق</span>
                                                    <div class="text-muted" style="font-size:.72rem;"><?= e(str_limit((string) $source['license_note'], 50)) ?></div>
                                                <?php else: ?>
                                                    <span class="badge bg-warning-subtle text-warning-emphasis">غير موثّق</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= active_badge((int) $source['is_active'] === 1) ?></td>
                                            <td class="text-end text-nowrap">
                                                <a href="<?= e(url('admin/taxonomy', ['tab' => 'sources', 'edit_source' => (int) $source['id']])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                                <form method="post" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete_source">
                                                    <input type="hidden" name="tab" value="sources">
                                                    <input type="hidden" name="id" value="<?= (int) $source['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-danger" data-confirm="حذف المصدر وإلغاء ربطه بالأسئلة؟"><i class="bi bi-trash"></i></button>
                                                </form>
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
    </div>
<?php endif; ?>
