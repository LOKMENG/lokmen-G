<?php
/** @var string $tab @var array $templates @var array|null $edit @var array $tracks @var array $categories @var array $attempts @var array $filters @var array $summary */
$modeLabels = ['mock' => 'محاكي شامل', 'category' => 'مجال محدد', 'random' => 'عشوائي', 'practice' => 'تدريب', 'daily' => 'تحدي يومي'];
$statusLabels = ['in_progress' => ['info', 'جاري'], 'completed' => ['success', 'مكتمل'], 'expired' => ['warning', 'انتهى الوقت'], 'abandoned' => ['secondary', 'متروك']];
$editCategories = [];
if ($edit !== null && !empty($edit['category_ids'])) {
    $editCategories = json_decode((string) $edit['category_ids'], true) ?: [];
}
?>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="pl-stat"><div class="pl-stat-icon"><i class="bi bi-layout-text-window-reverse"></i></div>
            <div><div class="value"><?= e(ar_digits($summary['templates'])) ?></div><div class="label">قوالب الاختبارات</div></div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pl-stat"><div class="pl-stat-icon is-blue"><i class="bi bi-journal-check"></i></div>
            <div><div class="value"><?= e(ar_digits($summary['attempts'])) ?></div><div class="label">إجمالي المحاولات</div></div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pl-stat"><div class="pl-stat-icon is-teal"><i class="bi bi-check2-all"></i></div>
            <div><div class="value"><?= e(ar_digits($summary['completed'])) ?></div><div class="label">محاولات مُنجزة</div></div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pl-stat"><div class="pl-stat-icon is-gold"><i class="bi bi-percent"></i></div>
            <div><div class="value"><?= e(ar_digits($summary['avg_score'])) ?>%</div><div class="label">متوسط الدرجات</div></div></div>
    </div>
</div>

<ul class="nav nav-pills mb-3 gap-1">
    <li class="nav-item"><a class="nav-link <?= $tab === 'templates' ? 'active' : '' ?>" href="<?= e(url('admin/exams', ['tab' => 'templates'])) ?>">
        <i class="bi bi-layout-text-window-reverse me-1"></i> القوالب</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'attempts' ? 'active' : '' ?>" href="<?= e(url('admin/exams', ['tab' => 'attempts'])) ?>">
        <i class="bi bi-clock-history me-1"></i> المحاولات</a></li>
</ul>

<?php if ($tab === 'templates'): ?>
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><i class="bi bi-<?= $edit !== null ? 'pencil-square' : 'plus-circle' ?> text-primary me-2"></i>
                    <?= $edit !== null ? 'تعديل قالب' : 'قالب اختبار جديد' ?></div>
                <div class="card-body">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_template">
                        <input type="hidden" name="tab" value="templates">
                        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
                        <div class="mb-3">
                            <label class="form-label" for="tpl_title">عنوان القالب</label>
                            <input type="text" class="form-control" id="tpl_title" name="title" required value="<?= e((string) ($edit['title'] ?? '')) ?>"
                                   placeholder="مثال: الاختبار الشامل — تخصصي">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="tpl_desc">الوصف</label>
                            <textarea class="form-control" id="tpl_desc" name="description" rows="2"><?= e((string) ($edit['description'] ?? '')) ?></textarea>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label" for="tpl_track">المسار</label>
                                <select class="form-select" id="tpl_track" name="track_id" required>
                                    <option value="">— اختر —</option>
                                    <?php foreach ($tracks as $track): ?>
                                        <option value="<?= (int) $track['id'] ?>" <?= (int) ($edit['track_id'] ?? 0) === (int) $track['id'] ? 'selected' : '' ?>><?= e($track['name_ar']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="tpl_mode">النمط</label>
                                <select class="form-select" id="tpl_mode" name="mode">
                                    <?php foreach ($modeLabels as $key => $label): ?>
                                        <option value="<?= e($key) ?>" <?= (string) ($edit['mode'] ?? 'mock') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="tpl_categories">المجالات المشمولة (اتركها فارغة لجميع مجالات المسار)</label>
                            <select class="form-select" id="tpl_categories" name="category_ids[]" multiple size="5">
                                <?php foreach ($categories as $category): ?>
                                    <?php if ($category['parent_id'] !== null) { continue; } ?>
                                    <option value="<?= (int) $category['id'] ?>" <?= in_array((int) $category['id'], array_map('intval', $editCategories), true) ? 'selected' : '' ?>>
                                        <?= e($category['name_ar']) ?> (<?= e($category['track_name']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">اضغط Ctrl (أو Cmd) لتحديد أكثر من مجال.</div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label" for="tpl_difficulty">الصعوبة</label>
                                <select class="form-select" id="tpl_difficulty" name="difficulty">
                                    <?php foreach (['any' => 'مختلطة', 'easy' => 'سهلة', 'medium' => 'متوسطة', 'hard' => 'صعبة'] as $key => $label): ?>
                                        <option value="<?= e($key) ?>" <?= (string) ($edit['difficulty'] ?? 'any') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="tpl_count">عدد الأسئلة</label>
                                <input type="number" class="form-control" id="tpl_count" name="question_count" min="1" max="200" value="<?= (int) ($edit['question_count'] ?? 50) ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="tpl_duration">المدة (دقيقة، 0 = بدون مؤقت)</label>
                                <input type="number" class="form-control" id="tpl_duration" name="duration_minutes" min="0" max="600" value="<?= (int) ($edit['duration_minutes'] ?? 60) ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="tpl_pass">نسبة الاجتياز %</label>
                                <input type="number" class="form-control" id="tpl_pass" name="pass_percentage" min="1" max="100" value="<?= (int) ($edit['pass_percentage'] ?? 60) ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="tpl_max">أقصى عدد محاولات (0 = بلا حد)</label>
                                <input type="number" class="form-control" id="tpl_max" name="max_attempts" min="0" max="100" value="<?= (int) ($edit['max_attempts'] ?? 0) ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="tpl_sort">الترتيب</label>
                                <input type="number" class="form-control" id="tpl_sort" name="sort_order" value="<?= (int) ($edit['sort_order'] ?? 0) ?>">
                            </div>
                        </div>
                        <?php
                        $switches = [
                            'randomize_questions'  => ['ترتيب عشوائي للأسئلة', (int) ($edit['randomize_questions'] ?? 1) === 1],
                            'randomize_options'    => ['ترتيب عشوائي للاختيارات', (int) ($edit['randomize_options'] ?? 0) === 1],
                            'show_explanation'     => ['إظهار الشرح بعد التصحيح', (int) ($edit['show_explanation'] ?? 1) === 1],
                            'require_subscription' => ['يتطلب اشتراكاً فعّالاً', (int) ($edit['require_subscription'] ?? 1) === 1],
                            'is_active'            => ['القالب نشط', (int) ($edit['is_active'] ?? 1) === 1],
                        ];
                        foreach ($switches as $key => [$label, $checked]):
                        ?>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="sw_<?= e($key) ?>" name="<?= e($key) ?>" value="1" <?= $checked ? 'checked' : '' ?>>
                                <label class="form-check-label" for="sw_<?= e($key) ?>"><?= e($label) ?></label>
                            </div>
                        <?php endforeach; ?>
                        <div class="d-flex gap-2 mt-3">
                            <button class="btn btn-primary flex-grow-1"><i class="bi bi-save me-1"></i> حفظ القالب</button>
                            <?php if ($edit !== null): ?>
                                <a href="<?= e(url('admin/exams', ['tab' => 'templates'])) ?>" class="btn btn-outline-secondary">إلغاء</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><i class="bi bi-layout-text-window-reverse text-primary me-2"></i> القوالب الحالية</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>#</th><th>القالب</th><th>المسار</th><th>النمط</th><th>أسئلة</th><th>مدة</th><th>محاولات</th><th>الحالة</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($templates as $template): ?>
                                    <tr>
                                        <td class="small text-muted"><?= e(ar_digits((int) $template['id'])) ?></td>
                                        <td class="small">
                                            <div class="fw-semibold"><?= e($template['title']) ?></div>
                                            <?php if ((int) $template['require_subscription'] === 0): ?>
                                                <span class="badge bg-success-subtle text-success-emphasis">متاح للزوار</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted"><?= e($template['track_name']) ?></td>
                                        <td class="small"><?= e($modeLabels[$template['mode']] ?? $template['mode']) ?></td>
                                        <td class="small"><?= e(ar_digits((int) $template['question_count'])) ?></td>
                                        <td class="small"><?= e((int) $template['duration_minutes'] > 0 ? ar_digits((int) $template['duration_minutes']) . ' د' : 'بلا مؤقت') ?></td>
                                        <td class="small"><?= e(ar_digits((int) $template['attempts_count'])) ?></td>
                                        <td><?= active_badge((int) $template['is_active'] === 1) ?></td>
                                        <td class="text-end text-nowrap">
                                            <a href="<?= e(url('admin/exams', ['tab' => 'templates', 'edit' => (int) $template['id']])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                            <form method="post" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_template">
                                                <input type="hidden" name="tab" value="templates">
                                                <input type="hidden" name="id" value="<?= (int) $template['id'] ?>">
                                                <button class="btn btn-sm btn-outline-danger" data-confirm="حذف القالب (أو تعطيله إن كان مستخدماً)؟"><i class="bi bi-trash"></i></button>
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

<?php else: ?>
    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="attempts">
                <div class="col-md-4">
                    <label class="form-label small" for="q">بحث</label>
                    <input type="text" class="form-control form-control-sm" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="اسم الطالب / عنوان المحاولة">
                </div>
                <div class="col-md-3">
                    <label class="form-label small" for="status">الحالة</label>
                    <select class="form-select form-select-sm" id="status" name="status">
                        <option value="">الكل</option>
                        <?php foreach ($statusLabels as $key => [$class, $label]): ?>
                            <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small" for="mode">النمط</label>
                    <select class="form-select form-select-sm" id="mode" name="mode">
                        <option value="">الكل</option>
                        <?php foreach ($modeLabels as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $filters['mode'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-primary w-100"><i class="bi bi-search me-1"></i> بحث</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-clock-history text-primary me-2"></i> المحاولات (<?= e(ar_digits($attempts['total'])) ?>)</span>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="expire_stale">
                <input type="hidden" name="tab" value="attempts">
                <button class="btn btn-sm btn-outline-secondary" data-confirm="إنهاء المحاولات التي انتهى وقتها ولم تُسلَّم؟">
                    <i class="bi bi-stopwatch me-1"></i> إنهاء المنتهية
                </button>
            </form>
        </div>
        <div class="card-body p-0">
            <?php if ($attempts['rows'] === []): ?>
                <div class="pl-empty"><i class="bi bi-journal-x"></i> لا توجد محاولات مطابقة.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>#</th><th>الطالب</th><th>الاختبار</th><th>النتيجة</th><th>الحالة</th><th class="d-none d-lg-table-cell">التاريخ</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($attempts['rows'] as $attempt): ?>
                                <?php [$badge, $label] = $statusLabels[$attempt['status']] ?? ['secondary', $attempt['status']]; ?>
                                <tr>
                                    <td class="small text-muted"><?= e(ar_digits((int) $attempt['id'])) ?></td>
                                    <td class="small">
                                        <a href="<?= e(url('admin/user-form', ['id' => (int) $attempt['user_id']])) ?>" class="fw-semibold text-decoration-none"><?= e($attempt['full_name']) ?></a>
                                        <div class="text-muted"><?= e($attempt['email']) ?></div>
                                    </td>
                                    <td class="small">
                                        <div><?= e(str_limit((string) ($attempt['title'] ?? 'اختبار'), 50)) ?></div>
                                        <div class="text-muted"><?= e($modeLabels[$attempt['mode']] ?? $attempt['mode']) ?> — <?= e($attempt['track_name']) ?></div>
                                    </td>
                                    <td class="small">
                                        <?php if (in_array($attempt['status'], ['completed', 'expired'], true)): ?>
                                            <span class="badge bg-<?= e(score_class((float) $attempt['score'])) ?>-subtle text-<?= e(score_class((float) $attempt['score'])) ?>-emphasis">
                                                <?= e(ar_digits((float) $attempt['score'])) ?>%
                                            </span>
                                            <div class="text-muted" style="font-size:.72rem;">
                                                <?= e(ar_digits((int) $attempt['correct_count'])) ?>/<?= e(ar_digits((int) $attempt['total_questions'])) ?>
                                                <?= (int) $attempt['passed'] === 1 ? '• ناجح' : '• راسب' ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-<?= e($badge) ?>-subtle text-<?= e($badge) ?>-emphasis"><?= e($label) ?></span></td>
                                    <td class="small text-muted d-none d-lg-table-cell"><?= e(format_date($attempt['finished_at'] ?? $attempt['started_at'], true)) ?></td>
                                    <td class="text-end text-nowrap">
                                        <a href="<?= e(url('exams/review', ['id' => (int) $attempt['id']])) ?>" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener"><i class="bi bi-eye"></i></a>
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_attempt">
                                            <input type="hidden" name="tab" value="attempts">
                                            <input type="hidden" name="id" value="<?= (int) $attempt['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" data-confirm="حذف المحاولة؟"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php if ((int) $attempts['pages'] > 1): ?>
            <div class="card-footer"><?= pagination((int) $attempts['page'], (int) $attempts['pages'], array_merge($filters, ['tab' => 'attempts'])) ?></div>
        <?php endif; ?>
    </div>
<?php endif; ?>
