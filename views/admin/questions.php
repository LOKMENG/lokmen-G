<?php
/** @var array $paginated @var array $filters @var array $tracks @var array $tree @var array $stats @var array $sources */
$query = static function (array $extra = []) use ($filters): array {
    $base = array_filter([
        'q'            => $filters['q'],
        'track'        => $filters['track_id'] ?: null,
        'category'     => $filters['category_id'] ?: null,
        'difficulty'   => $filters['difficulty'],
        'active'       => $filters['active'],
        'needs_review' => $filters['needs_review'],
        'reported'     => $filters['reported'] ?: null,
    ], static fn($value) => $value !== null && $value !== '' && $value !== 0);
    return array_merge($base, $extra);
};
?>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-2">
        <div class="pl-stat"><div class="pl-stat-icon"><i class="bi bi-collection"></i></div>
            <div><div class="value"><?= e(ar_digits($stats['total'])) ?></div><div class="label">إجمالي الأسئلة</div></div></div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="pl-stat"><div class="pl-stat-icon is-teal"><i class="bi bi-check2-circle"></i></div>
            <div><div class="value"><?= e(ar_digits($stats['active'])) ?></div><div class="label">نشطة</div></div></div>
    </div>
    <div class="col-6 col-lg-2">
        <a href="<?= e(url('admin/questions', ['needs_review' => 1])) ?>" class="pl-stat text-decoration-none">
            <div class="pl-stat-icon is-gold"><i class="bi bi-clipboard-check"></i></div>
            <div><div class="value"><?= e(ar_digits($stats['needs_review'])) ?></div><div class="label">تحتاج مراجعة</div></div>
        </a>
    </div>
    <div class="col-6 col-lg-2">
        <a href="<?= e(url('admin/questions', ['reported' => 1])) ?>" class="pl-stat text-decoration-none">
            <div class="pl-stat-icon is-red"><i class="bi bi-flag"></i></div>
            <div><div class="value"><?= e(ar_digits($stats['open_reports'])) ?></div><div class="label">بلاغات مفتوحة</div></div>
        </a>
    </div>
    <div class="col-6 col-lg-2">
        <div class="pl-stat"><div class="pl-stat-icon is-blue"><i class="bi bi-slash-circle"></i></div>
            <div><div class="value"><?= e(ar_digits($stats['without_answer'])) ?></div><div class="label">بدون إجابة صحيحة</div></div></div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="pl-stat"><div class="pl-stat-icon is-gold"><i class="bi bi-shuffle"></i></div>
            <div><div class="value"><?= e(ar_digits($stats['easy'] + $stats['medium'] + $stats['hard'])) ?></div><div class="label">موزّعة على الصعوبة</div></div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small" for="q">بحث في النص</label>
                <input type="text" class="form-control form-control-sm" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="نص السؤال أو الاختيارات أو الشرح">
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="track">المسار</label>
                <select class="form-select form-select-sm" id="track" name="track">
                    <option value="">كل المسارات</option>
                    <?php foreach ($tracks as $track): ?>
                        <option value="<?= (int) $track['id'] ?>" <?= (int) $filters['track_id'] === (int) $track['id'] ? 'selected' : '' ?>><?= e($track['name_ar']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="category">المجال</label>
                <select class="form-select form-select-sm" id="category" name="category">
                    <option value="">كل المجالات</option>
                    <?php foreach ($tree as $category): ?>
                        <option value="<?= (int) $category['id'] ?>" <?= (int) $filters['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name_ar']) ?>
                        </option>
                        <?php foreach (($category['children'] ?? []) as $child): ?>
                            <option value="<?= (int) $child['id'] ?>" <?= (int) $filters['category_id'] === (int) $child['id'] ? 'selected' : '' ?>>
                                &nbsp;&nbsp;— <?= e($child['name_ar']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="difficulty">الصعوبة</label>
                <select class="form-select form-select-sm" id="difficulty" name="difficulty">
                    <option value="">الكل</option>
                    <?php foreach (['easy' => 'سهل', 'medium' => 'متوسط', 'hard' => 'صعب'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $filters['difficulty'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label small" for="active">الحالة</label>
                <select class="form-select form-select-sm" id="active" name="active">
                    <option value="">الكل</option>
                    <option value="1" <?= $filters['active'] === '1' ? 'selected' : '' ?>>نشط</option>
                    <option value="0" <?= $filters['active'] === '0' ? 'selected' : '' ?>>معطّل</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label small" for="needs_review">المراجعة</label>
                <select class="form-select form-select-sm" id="needs_review" name="needs_review">
                    <option value="">الكل</option>
                    <option value="1" <?= $filters['needs_review'] === '1' ? 'selected' : '' ?>>تحتاج</option>
                    <option value="0" <?= $filters['needs_review'] === '0' ? 'selected' : '' ?>>تمّت</option>
                </select>
            </div>
            <div class="col-md-1"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-search"></i></button></div>
        </form>
        <div class="d-flex flex-wrap gap-2 mt-2 align-items-center">
            <span class="small text-muted">اختصارات:</span>
            <a href="<?= e(url('admin/questions', ['needs_review' => 1])) ?>" class="badge bg-warning-subtle text-warning-emphasis text-decoration-none">تحتاج مراجعة</a>
            <a href="<?= e(url('admin/questions', ['reported' => 1])) ?>" class="badge bg-danger-subtle text-danger-emphasis text-decoration-none">مُبلَّغ عنها</a>
            <a href="<?= e(url('admin/questions', ['active' => 0])) ?>" class="badge bg-secondary-subtle text-secondary-emphasis text-decoration-none">معطّلة</a>
            <a href="<?= e(url('admin/questions')) ?>" class="badge bg-light text-dark border text-decoration-none">الكل</a>
            <div class="ms-auto d-flex gap-2">
                <a href="<?= e(url('admin/question-form')) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i> سؤال جديد</a>
                <a href="<?= e(url('admin/import')) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-pdf me-1"></i> استيراد PDF</a>
            </div>
        </div>
    </div>
</div>

<form method="post" id="bulkForm">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <span><i class="bi bi-list-ul text-primary me-2"></i> النتائج (<?= e(ar_digits($paginated['total'])) ?>)</span>
            <div class="d-flex gap-1 flex-wrap" id="bulkActions">
                <button type="submit" name="action" value="activate" class="btn btn-sm btn-outline-success" data-confirm="تفعيل الأسئلة المحددة؟"><i class="bi bi-check2-circle me-1"></i> تفعيل</button>
                <button type="submit" name="action" value="deactivate" class="btn btn-sm btn-outline-secondary" data-confirm="تعطيل الأسئلة المحددة؟"><i class="bi bi-slash-circle me-1"></i> تعطيل</button>
                <button type="submit" name="action" value="reviewed" class="btn btn-sm btn-outline-primary" data-confirm="تعليم المحدد كمراجَع؟"><i class="bi bi-clipboard-check me-1"></i> تمّت المراجعة</button>
                <button type="submit" name="action" value="needs_review" class="btn btn-sm btn-outline-warning" data-confirm="تعليم المحدد كمحتاج للمراجعة؟"><i class="bi bi-exclamation-triangle me-1"></i> يحتاج مراجعة</button>
                <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-danger" data-confirm="حذف الأسئلة المحددة نهائياً؟ لا يمكن التراجع."><i class="bi bi-trash me-1"></i> حذف</button>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if ($paginated['rows'] === []): ?>
                <div class="pl-empty"><i class="bi bi-search"></i> لا توجد أسئلة مطابقة للتصفية.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:34px;"><input type="checkbox" class="form-check-input" data-check-all="#bulkForm"></th>
                                <th>#</th><th>السؤال</th><th class="d-none d-lg-table-cell">التصنيف</th>
                                <th>الإجابة</th><th>الصعوبة</th><th class="d-none d-md-table-cell">إحصاء</th><th>الحالة</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paginated['rows'] as $question): ?>
                                <tr class="<?= (int) $question['needs_review'] === 1 ? 'table-warning' : '' ?>">
                                    <td><input type="checkbox" class="form-check-input" name="ids[]" value="<?= (int) $question['id'] ?>"></td>
                                    <td class="small text-muted"><?= e(ar_digits((int) $question['id'])) ?></td>
                                    <td style="max-width: 380px;">
                                        <div class="small"><?= e(str_limit((string) $question['question_text'], 110)) ?></div>
                                        <?php if (!empty($question['review_note'])): ?>
                                            <div class="small text-warning-emphasis"><i class="bi bi-info-circle me-1"></i><?= e(str_limit((string) $question['review_note'], 70)) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($question['source_name'])): ?>
                                            <div class="text-muted" style="font-size:.72rem;">
                                                المصدر: <?= e($question['source_name']) ?><?= !empty($question['source_page']) ? ' — ص ' . e(ar_digits((int) $question['source_page'])) : '' ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small d-none d-lg-table-cell">
                                        <div><?= e($question['category_name'] ?? '—') ?></div>
                                        <div class="text-muted"><?= e($question['track_name']) ?></div>
                                    </td>
                                    <td>
                                        <?php if ($question['correct_answer'] === null): ?>
                                            <span class="badge bg-danger-subtle text-danger-emphasis">غير محددة</span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success-emphasis"><?= e(answer_letter_ar((string) $question['correct_answer'])) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-<?= e(difficulty_class((string) $question['difficulty'])) ?>-subtle text-<?= e(difficulty_class((string) $question['difficulty'])) ?>-emphasis"><?= e(difficulty_ar((string) $question['difficulty'])) ?></span></td>
                                    <td class="small d-none d-md-table-cell">
                                        <?php
                                        $answered = (int) $question['times_answered'];
                                        $accuracy = $answered > 0 ? round(((int) $question['times_correct'] / $answered) * 100) : null;
                                        ?>
                                        <?php if ($accuracy === null): ?>
                                            <span class="text-muted">—</span>
                                        <?php else: ?>
                                            <span class="text-<?= e(score_class((float) $accuracy)) ?>"><?= e(ar_digits($accuracy)) ?>%</span>
                                            <div class="text-muted" style="font-size:.72rem;"><?= e(ar_digits($answered)) ?> إجابة</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <?= active_badge((int) $question['active'] === 1) ?>
                                        <?php if ((int) $question['needs_review'] === 1): ?>
                                            <div><span class="badge bg-warning-subtle text-warning-emphasis">مراجعة</span></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="<?= e(url('admin/question-form', ['id' => (int) $question['id']])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php if ((int) $paginated['pages'] > 1): ?>
            <div class="card-footer"><?= pagination((int) $paginated['page'], (int) $paginated['pages'], $query(['page' => $paginated['page']])) ?></div>
        <?php endif; ?>
    </div>
</form>
