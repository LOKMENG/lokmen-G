<?php
/**
 * @var array $batch @var array $stats @var array $rows @var string $status @var int $onlyReview @var int $onlyDuplicates
 * @var int $onlyInvalid @var array $tracks @var array $categories @var array $sources
 */
$parents = array_values(array_filter($categories, static fn(array $c) => $c['parent_id'] === null));
$query = static fn(array $extra = []): array => array_filter(array_merge(['batch' => (int) $batch['id'], 'status' => $status], $extra), static fn($v) => $v !== '' && $v !== null && $v !== 0);
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div>
        <a href="<?= e(url('admin/import')) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right me-1"></i> كل الدفعات</a>
        <span class="ms-2 small text-muted">
            الملف: <strong><?= e((string) $batch['file_name']) ?></strong>
            (<?= e(strtoupper((string) $batch['file_type'])) ?>) —
            <?= e(format_date((string) $batch['created_at'], true)) ?>
        </span>
    </div>
    <form method="post" class="d-flex gap-2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_batch">
        <input type="hidden" name="batch_id" value="<?= (int) $batch['id'] ?>">
        <button class="btn btn-sm btn-outline-danger" data-confirm="حذف الدفعة وكل صفوفها؟"><i class="bi bi-trash me-1"></i> حذف الدفعة</button>
    </form>
</div>

<div class="row g-3 mb-3">
    <?php
    $cards = [
        ['إجمالي الصفوف', $stats['total'], 'is-blue', 'bi-list-ol'],
        ['بانتظار المراجعة', $stats['pending'], 'is-gold', 'bi-hourglass'],
        ['معتمدة', $stats['approved'], 'is-teal', 'bi-check2-circle'],
        ['مُدخلة فعلاً', $stats['imported'], 'is-teal', 'bi-database-check'],
        ['ناقصة/غير صالحة', $stats['invalid'], 'is-red', 'bi-exclamation-triangle'],
        ['مكررة', $stats['duplicates'], 'is-red', 'bi-files'],
        ['تحتاج قراراً على الإجابة', $stats['needs_review'], 'is-gold', 'bi-question-circle'],
        ['مرفوضة', $stats['rejected'], 'is-gold', 'bi-x-circle'],
    ];
    foreach ($cards as [$label, $value, $class, $icon]):
    ?>
        <div class="col-6 col-md-3">
            <div class="pl-stat">
                <div class="pl-stat-icon <?= e($class) ?>"><i class="bi <?= e($icon) ?>"></i></div>
                <div><div class="value"><?= e(ar_digits($value)) ?></div><div class="label"><?= e($label) ?></div></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-database-add text-primary me-2"></i> الخطوة 2 — الإدخال النهائي (للصفوف المعتمدة فقط)</div>
    <div class="card-body">
        <?php if ($stats['approved'] === 0): ?>
            <div class="alert alert-warning small mb-0">
                <i class="bi bi-exclamation-triangle me-1"></i>
                لا توجد صفوف معتمدة بعد. راجع الصفوف أدناه، صحّح ما يلزم، ثم اعتمدها (زر «اعتماد المحدد» أو «اعتماد كل الصالح»).
            </div>
        <?php else: ?>
            <form method="post" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="import">
                <input type="hidden" name="batch_id" value="<?= (int) $batch['id'] ?>">
                <div class="col-md-3">
                    <label class="form-label small" for="imp_track">المسار</label>
                    <select class="form-select form-select-sm" id="imp_track" name="track_id" required>
                        <option value="">— اختر —</option>
                        <?php foreach ($tracks as $track): ?>
                            <option value="<?= (int) $track['id'] ?>"><?= e($track['name_ar']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small" for="imp_category">المجال (اختياري)</label>
                    <select class="form-select form-select-sm" id="imp_category" name="category_id">
                        <option value="">— بدون مجال —</option>
                        <?php foreach ($parents as $category): ?>
                            <option value="<?= (int) $category['id'] ?>"><?= e($category['name_ar']) ?> (<?= e($category['track_name']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small" for="imp_source">المصدر</label>
                    <select class="form-select form-select-sm" id="imp_source" name="source_id">
                        <option value="">— بدون —</option>
                        <?php foreach ($sources as $source): ?>
                            <option value="<?= (int) $source['id'] ?>"><?= e(str_limit((string) $source['name'], 30)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small" for="imp_difficulty">الصعوبة الافتراضية</label>
                    <select class="form-select form-select-sm" id="imp_difficulty" name="difficulty">
                        <option value="easy">سهل</option>
                        <option value="medium" selected>متوسط</option>
                        <option value="hard">صعب</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-success w-100" data-confirm="إدخال <?= (int) $stats['approved'] ?> صفاً معتمداً إلى بنك الأسئلة؟">
                        <i class="bi bi-database-add me-1"></i> إدخال <?= e(ar_digits($stats['approved'])) ?> سؤالاً
                    </button>
                </div>
                <div class="col-12">
                    <div class="form-check mt-1">
                        <input class="form-check-input" type="checkbox" value="1" id="use_row_category" name="use_row_category" checked>
                        <label class="form-check-label small" for="use_row_category">
                            استخدام التصنيف المقترح لكل صف (إن وُجد) بدل مجال واحد للدُفعة كلها
                        </label>
                    </div>
                </div>
            </form>
            <div class="form-text mt-2">
                الصفوف التي لا تحتوي إجابة صحيحة ستُدخل بحالة «يحتاج مراجعة» و<strong>لن تُستخدم</strong> في الاختبارات حتى تصحّحها.
            </div>
        <?php endif; ?>
    </div>
</div>

<ul class="nav nav-pills mb-3 gap-1 flex-wrap">
    <?php
    $tabs = [
        ['pending', 'بانتظار المراجعة', $stats['pending']],
        ['approved', 'معتمدة', $stats['approved']],
        ['rejected', 'مرفوضة', $stats['rejected']],
        ['imported', 'مُدخلة', $stats['imported']],
        ['', 'الكل', $stats['total']],
    ];
    foreach ($tabs as [$key, $label, $count]):
    ?>
        <li class="nav-item">
            <a class="nav-link <?= $status === $key ? 'active' : '' ?>" href="<?= e(url('admin/import', ['batch' => (int) $batch['id'], 'status' => $key])) ?>">
                <?= e($label) ?> <span class="badge bg-light text-dark border ms-1"><?= e(ar_digits($count)) ?></span>
            </a>
        </li>
    <?php endforeach; ?>
    <li class="nav-item ms-auto d-flex gap-1">
        <a href="<?= e(url('admin/import', $query(['only_invalid' => 1]))) ?>" class="nav-link <?= $onlyInvalid ? 'active' : '' ?>"><i class="bi bi-exclamation-triangle me-1"></i> الناقصة</a>
        <a href="<?= e(url('admin/import', $query(['only_duplicates' => 1]))) ?>" class="nav-link <?= $onlyDuplicates ? 'active' : '' ?>"><i class="bi bi-files me-1"></i> المكررة</a>
        <a href="<?= e(url('admin/import', $query(['only_review' => 1]))) ?>" class="nav-link <?= $onlyReview ? 'active' : '' ?>"><i class="bi bi-question-circle me-1"></i> بلا إجابة</a>
    </li>
</ul>

<div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <span><i class="bi bi-list-check text-primary me-2"></i> الصفوف (<?= e(ar_digits((int) $rows['total'])) ?>)</span>
            <!-- نموذج الإجراءات الجماعية: مربعات الاختيار داخل الجدول مرتبطة به عبر سمة form -->
            <form method="post" id="stagingForm" class="d-flex flex-wrap gap-1 mb-0">
                <?= csrf_field() ?>
                <input type="hidden" name="batch_id" value="<?= (int) $batch['id'] ?>">
                <button type="submit" name="action" value="approve_valid" class="btn btn-sm btn-outline-success" data-confirm="اعتماد كل الصفوف الصالحة غير المكررة (التي لها إجابة محددة)؟">
                    <i class="bi bi-check2-all me-1"></i> اعتماد كل الصالح
                </button>
                <button type="submit" name="action" value="approve" class="btn btn-sm btn-success" data-confirm="اعتماد الصفوف المحددة؟"><i class="bi bi-check2 me-1"></i> اعتماد المحدد</button>
                <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline-danger" data-confirm="رفض الصفوف المحددة؟"><i class="bi bi-x me-1"></i> رفض المحدد</button>
                <button type="submit" name="action" value="reset" class="btn btn-sm btn-outline-secondary" data-confirm="إعادة الصفوف المحددة إلى «بانتظار المراجعة»؟"><i class="bi bi-arrow-counterclockwise me-1"></i> إرجاع للمراجعة</button>
            </form>
        </div>
        <div class="card-body p-0">
            <?php if ($rows['rows'] === []): ?>
                <div class="pl-empty"><i class="bi bi-inbox"></i> لا توجد صفوف مطابقة لهذه التصفية.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:34px;"><input type="checkbox" class="form-check-input" data-check-all="#stagingForm" data-check-form="stagingForm"></th>
                                <th>#</th><th>السؤال والاختيارات</th><th>الإجابة</th><th>الحالة</th><th>ملاحظات</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows['rows'] as $row): ?>
                                <?php
                                $issues = json_decode((string) ($row['issues'] ?? ''), true) ?: [];
                                $rowStatus = ['pending' => ['warning', 'بانتظار المراجعة'], 'approved' => ['success', 'معتمد'], 'rejected' => ['secondary', 'مرفوض'], 'imported' => ['primary', 'مُدخل']][$row['status']] ?? ['secondary', $row['status']];
                                ?>
                                <tr class="<?= (int) $row['is_duplicate'] === 1 ? 'table-danger' : ((int) $row['is_valid'] === 0 ? 'table-warning' : '') ?>">
                                    <td>
                                        <input type="checkbox" class="form-check-input" name="ids[]" form="stagingForm" value="<?= (int) $row['id'] ?>"
                                            <?= $row['status'] === 'pending' ? '' : 'disabled' ?>>
                                    </td>
                                    <td class="small text-muted">
                                        <?= e(ar_digits((int) $row['row_number'])) ?>
                                        <?php if ($row['source_page'] !== null): ?>
                                            <div class="text-muted" style="font-size:.7rem;">ص <?= e(ar_digits((int) $row['source_page'])) ?></div>
                                        <?php endif; ?>
                                        <?php if ($row['question_id'] !== null): ?>
                                            <a href="<?= e(url('admin/question-form', ['id' => (int) $row['question_id']])) ?>" class="small d-block" title="السؤال المُدخل">
                                                <i class="bi bi-box-arrow-up-right"></i> <?= e(ar_digits((int) $row['question_id'])) ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td style="max-width:520px;">
                                        <form method="post" id="rowForm<?= (int) $row['id'] ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="save_row">
                                            <input type="hidden" name="row_id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="batch_id" value="<?= (int) $batch['id'] ?>">
                                            <div class="mb-1">
                                                <textarea class="form-control form-control-sm" name="question_text" rows="2" required><?= e((string) $row['question_text']) ?></textarea>
                                            </div>
                                            <div class="row g-1">
                                                <?php foreach (['a' => 'أ', 'b' => 'ب', 'c' => 'ج', 'd' => 'د'] as $letter => $letterAr): ?>
                                                    <div class="col-md-6">
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text" style="min-width:38px;"><?= e($letterAr) ?></span>
                                                            <input type="text" class="form-control" name="option_<?= e($letter) ?>" value="<?= e((string) ($row['option_' . $letter] ?? '')) ?>">
                                                            <span class="input-group-text">
                                                                <input class="form-check-input mt-0" type="radio" name="correct_answer" value="<?= e($letter) ?>"
                                                                    <?= ($row['correct_answer'] ?? null) === $letter ? 'checked' : '' ?>>
                                                            </span>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="row g-1 mt-1">
                                                <div class="col-md-7">
                                                    <input type="text" class="form-control form-control-sm" name="explanation" placeholder="الشرح (اختياري)"
                                                           value="<?= e((string) ($row['explanation'] ?? '')) ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <select class="form-select form-select-sm" name="difficulty">
                                                        <option value="">الصعوبة</option>
                                                        <?php foreach (['easy' => 'سهل', 'medium' => 'متوسط', 'hard' => 'صعب'] as $key => $label): ?>
                                                            <option value="<?= e($key) ?>" <?= (string) $row['difficulty'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <input type="number" class="form-control form-control-sm" name="source_page" placeholder="صفحة" value="<?= (int) ($row['source_page'] ?? 0) ?: '' ?>">
                                                </div>
                                            </div>
                                        </form>
                                    </td>
                                    <td class="small">
                                        <?php if ($row['correct_answer'] === null): ?>
                                            <span class="badge bg-danger-subtle text-danger-emphasis">غير محددة</span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success-emphasis"><?= e(answer_letter_ar((string) $row['correct_answer'])) ?></span>
                                        <?php endif; ?>
                                        <?php if ((int) $row['needs_review'] === 1): ?>
                                            <div class="mt-1"><span class="badge bg-warning-subtle text-warning-emphasis">يحتاج مراجعة</span></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <span class="badge bg-<?= e($rowStatus[0]) ?>-subtle text-<?= e($rowStatus[0]) ?>-emphasis"><?= e($rowStatus[1]) ?></span>
                                        <?php if ((int) $row['is_duplicate'] === 1): ?>
                                            <div class="mt-1"><span class="badge bg-danger-subtle text-danger-emphasis">مكرر</span></div>
                                        <?php endif; ?>
                                        <?php if ((int) $row['is_valid'] === 0): ?>
                                            <div class="mt-1"><span class="badge bg-warning-subtle text-warning-emphasis">ناقص</span></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small" style="max-width:220px;">
                                        <?php if ($issues === []): ?>
                                            <span class="text-success"><i class="bi bi-check2"></i> لا ملاحظات</span>
                                        <?php else: ?>
                                            <ul class="mb-0 ps-3 text-danger-emphasis">
                                                <?php foreach (array_slice($issues, 0, 4) as $issue): ?>
                                                    <li><?= e((string) $issue) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                        <?php if (!empty($row['category_guess'])): ?>
                                            <div class="text-muted mt-1">المجال في الملف: <?= e((string) $row['category_guess']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['raw_text'])): ?>
                                            <details class="mt-1">
                                                <summary class="text-muted" style="font-size:.72rem;">النص الأصلي</summary>
                                                <div class="text-muted" style="font-size:.7rem; word-break:break-word;"><?= e(str_limit((string) $row['raw_text'], 300)) ?></div>
                                            </details>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <button type="submit" form="rowForm<?= (int) $row['id'] ?>" class="btn btn-sm btn-primary" title="حفظ التعديلات">
                                            <i class="bi bi-save"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php if ((int) $rows['pages'] > 1): ?>
            <div class="card-footer">
                <?= pagination((int) $rows['page'], (int) $rows['pages'], $query(['page' => $rows['page'], 'only_invalid' => $onlyInvalid, 'only_duplicates' => $onlyDuplicates, 'only_review' => $onlyReview])) ?>
            </div>
        <?php endif; ?>
    </div>
