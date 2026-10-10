<?php /** @var array $rows @var array|null $editing @var array $stats */ ?>
<div class="row g-3 mb-3">
    <?php
    $cards = [
        ['إجمالي الشهادات', $stats['total'], 'is-blue', 'bi-chat-quote'],
        ['منشورة على الموقع', $stats['published'], 'is-teal', 'bi-broadcast'],
        ['موافقة معلنة وتنتظر النشر', $stats['waiting'], 'is-gold', 'bi-hourglass-split'],
    ];
    foreach ($cards as [$label, $value, $class, $icon]):
    ?>
        <div class="col-md-4">
            <div class="pl-stat">
                <div class="pl-stat-icon <?= e($class) ?>"><i class="bi <?= e($icon) ?>"></i></div>
                <div><div class="value"><?= e(ar_digits($value)) ?></div><div class="label"><?= e($label) ?></div></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <i class="bi <?= $editing ? 'bi-pencil-square' : 'bi-plus-circle' ?> text-primary me-2"></i>
                <?= $editing ? 'تعديل شهادة' : 'إضافة شهادة جديدة' ?>
            </div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="t_name">اسم صاحب الشهادة <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="t_name" name="name" required maxlength="190"
                               value="<?= e((string) old('name', (string) ($editing['name'] ?? ''))) ?>">
                    </div>
                    <div class="row g-2">
                        <div class="col-md-7">
                            <label class="form-label" for="t_role">الصفة/التخصص</label>
                            <input type="text" class="form-control" id="t_role" name="role" maxlength="190"
                                   placeholder="مثال: معلم علوم — مرشح للرخصة"
                                   value="<?= e((string) old('role', (string) ($editing['role'] ?? ''))) ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="t_city">المدينة</label>
                            <input type="text" class="form-control" id="t_city" name="city" maxlength="100"
                                   value="<?= e((string) old('city', (string) ($editing['city'] ?? ''))) ?>">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label" for="t_body">نص الشهادة <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="t_body" name="body" rows="5" required maxlength="1000"
                                  placeholder="اكتب الشهادة كما وردت من صاحبها دون مبالغات أو وعود."><?= e((string) old('body', (string) ($editing['body'] ?? ''))) ?></textarea>
                        <div class="form-text">لا تُنشر شهادات تُدّعي نتائج مضمونة، ولا تُضف شهادات غير حقيقية.</div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label" for="t_rating">التقييم</label>
                            <select class="form-select" id="t_rating" name="rating">
                                <?php for ($star = 5; $star >= 1; $star--): ?>
                                    <option value="<?= $star ?>" <?= (int) ($editing['rating'] ?? 5) === $star ? 'selected' : '' ?>>
                                        <?= e(ar_digits($star)) ?> <?= str_repeat('★', $star) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="t_sort">الترتيب</label>
                            <input type="number" class="form-control" id="t_sort" name="sort_order" value="<?= (int) ($editing['sort_order'] ?? 0) ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="t_published" name="is_published" value="1"
                                    <?= (int) ($editing['is_published'] ?? 0) === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="t_published">نشر على الموقع</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-check mt-3 p-3 rounded bg-warning-subtle">
                        <input class="form-check-input" type="checkbox" id="t_consent" name="consent" value="1"
                            <?= (int) ($editing['consent'] ?? 0) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="t_consent">
                            <strong>أُقرّ بموافقة صاحب الشهادة الصريحة على نشر اسمه ونص شهادته.</strong>
                            <span class="d-block small text-muted">لا يمكن النشر قبل تفعيل هذا الإقرار.</span>
                        </label>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-primary flex-grow-1"><i class="bi bi-save me-1"></i> حفظ</button>
                        <?php if ($editing): ?>
                            <a href="<?= e(url('admin/testimonials')) ?>" class="btn btn-outline-secondary">إلغاء</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-list-ul text-primary me-2"></i> الشهادات المسجّلة</div>
            <div class="card-body p-0">
                <?php if ($rows === []): ?>
                    <div class="pl-empty"><i class="bi bi-chat-quote"></i> لا توجد شهادات بعد.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>الاسم</th><th>الشهادة</th><th>الحالة</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td class="small" style="min-width:140px;">
                                            <div class="fw-semibold"><?= e((string) $row['name']) ?></div>
                                            <div class="text-muted"><?= e(trim((string) $row['role'] . ' — ' . (string) $row['city'], ' —')) ?></div>
                                            <div class="text-warning"><?= str_repeat('★', (int) $row['rating']) ?></div>
                                        </td>
                                        <td class="small text-muted" style="max-width:280px;"><?= e(str_limit((string) $row['body'], 140)) ?></td>
                                        <td class="small">
                                            <?php if ((int) $row['is_published'] === 1): ?>
                                                <span class="badge bg-success-subtle text-success-emphasis">منشورة</span>
                                            <?php elseif ((int) $row['consent'] === 1): ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis">بانتظار النشر</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary-emphasis">بدون موافقة</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <a href="<?= e(url('admin/testimonials', ['edit' => (int) $row['id']])) ?>" class="btn btn-sm btn-outline-primary" title="تعديل"><i class="bi bi-pencil"></i></a>
                                            <form method="post" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                                <input type="hidden" name="action" value="<?= (int) $row['is_published'] === 1 ? 'unpublish' : 'publish' ?>">
                                                <button class="btn btn-sm btn-outline-<?= (int) $row['is_published'] === 1 ? 'secondary' : 'success' ?>"
                                                        title="<?= (int) $row['is_published'] === 1 ? 'إخفاء' : 'نشر' ?>">
                                                    <i class="bi <?= (int) $row['is_published'] === 1 ? 'bi-eye-slash' : 'bi-broadcast' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="post" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button class="btn btn-sm btn-outline-danger" data-confirm="حذف الشهادة نهائياً؟"><i class="bi bi-trash"></i></button>
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
        <div class="alert alert-light border small mt-3 mb-0">
            <i class="bi bi-info-circle text-primary me-1"></i>
            الشهادات المعروضة على الصفحة الرئيسية تُقرأ من هذا الجدول مباشرة، ولا تظهر إلا شهادات مكتملة الموافقة والنشر.
        </div>
    </div>
</div>
