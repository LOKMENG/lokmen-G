<?php
/**
 * @var array|null $question @var array $tracks @var array $categories @var array $sources @var array $errors @var int $duplicateId
 */
$isEdit = $question !== null;
$val = static function (string $key, string $default = '') use ($question): string {
    if ($question !== null && array_key_exists($key, $question) && $question[$key] !== null) {
        return (string) $question[$key];
    }
    $old = old($key);
    return $old !== null ? (string) $old : $default;
};
$parents = array_values(array_filter($categories, static fn(array $c) => $c['parent_id'] === null));
$currentCategory = (int) $val('category_id', '0');
$currentSub = (int) $val('subcategory_id', '0');
$currentTrack = (int) $val('track_id', '0');
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div class="d-flex gap-2">
        <a href="<?= e(url('admin/questions')) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right me-1"></i> بنك الأسئلة</a>
        <?php if ($isEdit): ?>
            <a href="<?= e(url('admin/question-form')) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-circle me-1"></i> سؤال جديد</a>
        <?php endif; ?>
    </div>
    <?php if ($isEdit): ?>
        <div class="small text-muted">
            أُنشئ في <?= e(format_date((string) $question['created_at'])) ?>
            آخر تحديث <?= e(format_date((string) $question['updated_at'])) ?>
            • أُجيب <?= e(ar_digits((int) $question['times_answered'])) ?> مرة
            (<?= e(ar_digits((int) $question['times_correct'])) ?> صحيحة)
        </div>
    <?php endif; ?>
</div>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger py-2 small">
        <?php foreach ($errors as $error): ?><div><i class="bi bi-exclamation-triangle me-1"></i><?= e($error) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($duplicateId > 0): ?>
    <div class="alert alert-warning d-flex flex-wrap gap-2 align-items-center">
        <i class="bi bi-files fs-5"></i>
        <div class="flex-grow-1">
            <strong>تنبيه تكرار:</strong> يوجد سؤال مشابه في البنك.
            <a href="<?= e(url('admin/question-form', ['id' => $duplicateId])) ?>" target="_blank" rel="noopener">عرض السؤال رقم <?= e(ar_digits($duplicateId)) ?></a>
        </div>
        <span class="small text-muted">لحفظ السؤال رغم ذلك، علّم «السماح بالتكرار» ثم أعد الحفظ.</span>
    </div>
<?php endif; ?>

<form method="post" id="questionForm">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) ($question['id'] ?? 0) ?>">

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-question-circle text-primary me-2"></i> نص السؤال</div>
                <div class="card-body">
                    <textarea class="form-control" name="question_text" rows="4" required
                              placeholder="اكتب نص السؤال كما تريد أن يظهر للطالب..."><?= e($val('question_text')) ?></textarea>
                    <div class="form-text">يُفضّل سؤال واحد لكل حقل، بصياغة واضحة لا تحتمل أكثر من معنى.</div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-list-check text-primary me-2"></i> الاختيارات</div>
                <div class="card-body">
                    <div class="row g-2">
                        <?php foreach (['a' => 'أ', 'b' => 'ب', 'c' => 'ج', 'd' => 'د'] as $letter => $letterAr): ?>
                            <div class="col-12">
                                <div class="input-group">
                                    <span class="input-group-text" style="min-width:46px;"><?= e($letterAr) ?></span>
                                    <input type="text" class="form-control" name="option_<?= e($letter) ?>"
                                           value="<?= e($val('option_' . $letter)) ?>"
                                           placeholder="<?= $letter === 'c' || $letter === 'd' ? 'اختياري للأسئلة الثنائية' : 'مطلوب' ?>"
                                           <?= $letter === 'a' || $letter === 'b' ? 'required' : '' ?>>
                                    <span class="input-group-text">
                                        <input class="form-check-input mt-0" type="radio" name="correct_answer" value="<?= e($letter) ?>"
                                               <?= $val('correct_answer') === $letter ? 'checked' : '' ?> title="الإجابة الصحيحة">
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text">اختر دائرة الإجابة الصحيحة بجانب الاختيار المناسب. اترك (ج) و(د) فارغين للسؤال الثنائي (صح/خطأ).</div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-lightbulb text-primary me-2"></i> الشرح</div>
                <div class="card-body">
                    <textarea class="form-control" name="explanation" rows="3" maxlength="2000"
                              placeholder="اشرح سبب صحة الإجابة — يظهر للطالب بعد الإجابة في نمط التدريب."><?= e($val('explanation')) ?></textarea>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-diagram-3 text-primary me-2"></i> التصنيف</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="track_id">المسار</label>
                        <select class="form-select" id="track_id" name="track_id" required>
                            <option value="">— اختر —</option>
                            <?php foreach ($tracks as $track): ?>
                                <option value="<?= (int) $track['id'] ?>" <?= $currentTrack === (int) $track['id'] ? 'selected' : '' ?>>
                                    <?= e($track['name_ar']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="category_id">المجال</label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">— اختر —</option>
                            <?php foreach ($parents as $category): ?>
                                <option value="<?= (int) $category['id'] ?>"
                                        data-track="<?= (int) $category['track_id'] ?>"
                                        <?= $currentCategory === (int) $category['id'] ? 'selected' : '' ?>>
                                    <?= e($category['name_ar']) ?> (<?= e($category['track_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="subcategory_id">المحور (تصنيف فرعي)</label>
                        <select class="form-select" id="subcategory_id" name="subcategory_id">
                            <option value="">— بدون محور —</option>
                            <?php foreach ($categories as $category): ?>
                                <?php if ($category['parent_id'] === null) { continue; } ?>
                                <option value="<?= (int) $category['id'] ?>" data-parent="<?= (int) $category['parent_id'] ?>"
                                        <?= $currentSub === (int) $category['id'] ? 'selected' : '' ?>>
                                    <?= e($category['name_ar']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">تظهر محاور المجال المختار فقط.</div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="difficulty">مستوى الصعوبة</label>
                        <select class="form-select" id="difficulty" name="difficulty">
                            <?php foreach (['easy' => 'سهل', 'medium' => 'متوسط', 'hard' => 'صعب'] as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $val('difficulty', 'medium') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-link-45deg text-primary me-2"></i> المصدر والحالة</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="source_id">المصدر</label>
                        <select class="form-select" id="source_id" name="source_id">
                            <option value="">— بدون مصدر —</option>
                            <?php foreach ($sources as $source): ?>
                                <option value="<?= (int) $source['id'] ?>" <?= (int) $val('source_id', '0') === (int) $source['id'] ? 'selected' : '' ?>>
                                    <?= e($source['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text"><a href="<?= e(url('admin/taxonomy', ['tab' => 'sources'])) ?>">إدارة المصادر</a></div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="source_page">رقم الصفحة</label>
                            <input type="number" class="form-control" id="source_page" name="source_page" min="1" value="<?= e($val('source_page')) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="year">السنة</label>
                            <input type="number" class="form-control" id="year" name="year" min="1400" max="1500" value="<?= e($val('year')) ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="source_note">ملاحظة المصدر</label>
                        <input type="text" class="form-control" id="source_note" name="source_note" maxlength="255" value="<?= e($val('source_note')) ?>">
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="active" name="active" value="1" <?= $isEdit ? ((int) $question['active'] === 1 ? 'checked' : '') : 'checked' ?>>
                        <label class="form-check-label" for="active">السؤال نشط (يظهر في الاختبارات)</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="needs_review" name="needs_review" value="1" <?= ($isEdit && (int) $question['needs_review'] === 1) || old('needs_review') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="needs_review">يحتاج مراجعة (لا يُستخدم في الاختبارات الرسمية)</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="review_note">سبب الحاجة للمراجعة</label>
                        <input type="text" class="form-control" id="review_note" name="review_note" maxlength="255" value="<?= e($val('review_note')) ?>"
                               placeholder="مثال: الإجابة غير واضحة في المصدر">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="allow_duplicate" name="allow_duplicate" value="1" <?= $duplicateId > 0 ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="allow_duplicate">السماح بحفظ سؤال مشابه (تجاوز تنبيه التكرار)</label>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button class="btn btn-primary btn-lg"><i class="bi bi-save me-1"></i> <?= $isEdit ? 'حفظ التعديلات' : 'إضافة السؤال' ?></button>
                <?php if ($isEdit): ?>
                    <a href="<?= e(url('admin/questions', ['q' => str_limit((string) $question['question_text'], 40)])) ?>" class="btn btn-outline-secondary">بحث عن مشابه</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
  var trackSelect = document.getElementById('track_id');
  var categorySelect = document.getElementById('category_id');
  var subSelect = document.getElementById('subcategory_id');
  if (!trackSelect || !categorySelect) return;

  function filterCategories() {
    var track = trackSelect.value;
    var keepSelection = false;
    Array.prototype.forEach.call(categorySelect.options, function (option) {
      if (!option.value) { option.hidden = false; return; }
      var match = !track || option.getAttribute('data-track') === track;
      option.hidden = !match;
      if (!match && option.selected) { option.selected = false; }
    });
    filterSubcategories();
  }

  function filterSubcategories() {
    if (!subSelect) return;
    var parent = categorySelect.value;
    Array.prototype.forEach.call(subSelect.options, function (option) {
      if (!option.value) { option.hidden = false; return; }
      var match = !!parent && option.getAttribute('data-parent') === parent;
      option.hidden = !match;
      if (!match && option.selected) { option.selected = false; }
    });
  }

  trackSelect.addEventListener('change', filterCategories);
  categorySelect.addEventListener('change', filterSubcategories);
  filterCategories();
  filterSubcategories();
})();
</script>
