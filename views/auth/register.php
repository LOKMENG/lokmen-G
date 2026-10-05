<?php
/** @var array $errors @var callable $old @var array $tracks */
?>
<h3 class="fw-bold mb-1">إنشاء حساب جديد</h3>
<p class="text-muted small mb-4">دقيقة واحدة تفصلك عن بدء التدريب على اختبار الرخصة المهنية.</p>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger py-2 small">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <?= e(reset($errors)) ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('auth/register')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label" for="full_name">الاسم الكامل <span class="text-danger">*</span></label>
        <input type="text" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
               id="full_name" name="full_name" required value="<?= $old('full_name') ?>" placeholder="مثال: عبدالله محمد العتيبي">
        <?php if (isset($errors['full_name'])): ?><div class="invalid-feedback"><?= e($errors['full_name']) ?></div><?php endif; ?>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="phone">رقم الجوال <span class="text-danger">*</span></label>
            <input type="tel" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                   id="phone" name="phone" required value="<?= $old('phone') ?>" placeholder="05XXXXXXXX">
            <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?= e($errors['phone']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="city">المدينة</label>
            <input type="text" class="form-control" id="city" name="city" value="<?= $old('city') ?>" placeholder="مثال: الرياض">
        </div>
    </div>

    <div class="mb-3 mt-3">
        <label class="form-label" for="email">البريد الإلكتروني <span class="text-danger">*</span></label>
        <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
               id="email" name="email" required value="<?= $old('email') ?>" placeholder="example@email.com">
        <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="specialty">التخصص</label>
            <input type="text" class="form-control" id="specialty" name="specialty" value="<?= $old('specialty') ?>" placeholder="مثال: معلم حاسب آلي">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="target_track_id">المسار المستهدف</label>
            <select class="form-select" id="target_track_id" name="target_track_id">
                <option value="">— اختر —</option>
                <?php foreach ($tracks as $track): ?>
                    <option value="<?= (int) $track['id'] ?>" <?= (string) old('target_track_id') === (string) $track['id'] ? 'selected' : '' ?>>
                        <?= e($track['name_ar']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row g-3 mt-3">
        <div class="col-md-6">
            <label class="form-label" for="password">كلمة المرور <span class="text-danger">*</span></label>
            <div class="input-group">
                <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                       id="password" name="password" required placeholder="8 أحرف على الأقل">
                <span class="input-group-text cursor-pointer" data-toggle-password="#password"><i class="bi bi-eye"></i></span>
                <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= e($errors['password']) ?></div><?php endif; ?>
            </div>
            <div class="form-text">يجب أن تحتوي على حروف وأرقام (8 أحرف على الأقل).</div>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="password_confirmation">تأكيد كلمة المرور <span class="text-danger">*</span></label>
            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required placeholder="أعد كتابة كلمة المرور">
        </div>
    </div>

    <div class="form-check mt-3">
        <input class="form-check-input <?= isset($errors['terms']) ? 'is-invalid' : '' ?>" type="checkbox" name="terms" id="terms" value="1" required>
        <label class="form-check-label small" for="terms">
            أوافق على <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">شروط الاستخدام</a> وسياسة الخصوصية،
            وأقر بأن المحتوى لأغراض التدريب الشخصي فقط.
        </label>
        <?php if (isset($errors['terms'])): ?><div class="invalid-feedback"><?= e($errors['terms']) ?></div><?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary w-100 btn-lg mt-3">
        <i class="bi bi-person-plus me-1"></i> إنشاء الحساب
    </button>
</form>

<hr class="pl-hr">
<p class="text-center small mb-0">
    لديك حساب بالفعل؟ <a href="<?= e(url('auth/login')) ?>" class="fw-bold">تسجيل الدخول</a>
</p>

<!-- شروط الاستخدام -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">شروط الاستخدام وسياسة الخصوصية</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body small">
                <ol class="ps-3">
                    <li class="mb-2">الحساب شخصي ولا يجوز مشاركته أو إعادة بيع المحتوى.</li>
                    <li class="mb-2">الأسئلة والتصنيفات داخل المنصة تدريبية تنظيمية، وليست بديلاً عن المصادر الرسمية.</li>
                    <li class="mb-2">نحفظ بياناتك (الاسم، الجوال، البريد) لأغراض تشغيل الحساب والدعم فقط، ولا نشاركها مع أي طرف ثالث.</li>
                    <li class="mb-2">كلمة المرور تُخزَّن مشفّرة (Hashed) ولا يمكن لأي أحد قراءتها.</li>
                    <li class="mb-2">الاشتراك غير قابل للاسترداد بعد التفعيل إلا وفق ما تحدده الإدارة.</li>
                    <li class="mb-0">بالاشتراك أنت تقر بأن أي ملفات ترفعها للمنصة تملك حق استخدامها.</li>
                </ol>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">موافق</button>
            </div>
        </div>
    </div>
</div>
