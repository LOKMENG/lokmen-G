<?php /** @var array $question @var array $errors */ ?>
<div class="row g-3 justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-flag text-danger me-2"></i> بلاغ عن سؤال</div>
            <div class="card-body">
                <div class="alert alert-light border">
                    <div class="small text-muted mb-1">نص السؤال رقم <?= e(ar_digits((int) $question['id'])) ?>:</div>
                    <div><?= nl2br(e(str_limit((string) $question['question_text'], 300))) ?></div>
                </div>

                <?php if ($errors !== []): ?>
                    <div class="alert alert-danger py-2 small"><?= e(reset($errors)) ?></div>
                <?php endif; ?>

                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="question_id" value="<?= (int) $question['id'] ?>">
                    <div class="mb-3">
                        <label class="form-label" for="reason">سبب البلاغ</label>
                        <select class="form-select" id="reason" name="reason" required>
                            <option value="wrong_answer">الإجابة الصحيحة غير صحيحة</option>
                            <option value="typo">خطأ إملائي أو لغوي</option>
                            <option value="unclear">صياغة السؤال غير واضحة</option>
                            <option value="duplicate">السؤال مكرر</option>
                            <option value="copyright">المحتوى محمي بحقوق غير مصرّح بها</option>
                            <option value="other">سبب آخر</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="note">تفاصيل إضافية (اختياري)</label>
                        <textarea class="form-control" id="note" name="note" rows="4" maxlength="1000"
                                  placeholder="اشرح المشكلة بدقة، واذكر الإجابة التي تعتقد أنها صحيحة إن أمكن..."></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-danger"><i class="bi bi-send me-1"></i> إرسال البلاغ</button>
                        <a href="<?= e(url('exams/history')) ?>" class="btn btn-outline-secondary">إلغاء</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
