<?php /** @var string $token @var bool $valid @var array $errors */ ?>
<h3 class="fw-bold mb-1">تعيين كلمة مرور جديدة</h3>

<?php if (!$valid): ?>
    <div class="alert alert-danger">
        <i class="bi bi-x-circle me-1"></i> رابط إعادة التعيين غير صالح أو انتهت صلاحيته.
    </div>
    <a href="<?= e(url('auth/forgot-password')) ?>" class="btn btn-primary w-100">طلب رابط جديد</a>
<?php else: ?>
    <p class="text-muted small mb-4">اختر كلمة مرور قوية (8 أحرف على الأقل، تحتوي حروفاً وأرقاماً).</p>
    <?php if ($errors !== []): ?>
        <div class="alert alert-danger py-2 small"><?= e(reset($errors)) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('auth/reset-password')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_token_reset" value="<?= e($token) ?>">
        <div class="mb-3">
            <label class="form-label" for="password">كلمة المرور الجديدة</label>
            <div class="input-group">
                <input type="password" class="form-control" id="password" name="password" required>
                <span class="input-group-text cursor-pointer" data-toggle-password="#password"><i class="bi bi-eye"></i></span>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password_confirmation">تأكيد كلمة المرور</label>
            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
        </div>
        <button type="submit" class="btn btn-primary w-100 btn-lg">
            <i class="bi bi-shield-check me-1"></i> حفظ كلمة المرور
        </button>
    </form>
<?php endif; ?>
