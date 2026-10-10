<?php /** @var array $errors @var string $devLink */ ?>
<h3 class="fw-bold mb-1">نسيت كلمة المرور؟</h3>
<p class="text-muted small mb-4">أدخل بريدك الإلكتروني وسنرسل لك رابطاً لإعادة التعيين صالحاً لمدة ساعة.</p>

<?php if (!empty($errors['email'])): ?>
    <div class="alert alert-danger py-2 small"><?= e($errors['email']) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(url('auth/forgot-password')) ?>">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label" for="email">البريد الإلكتروني</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control" id="email" name="email" required value="<?= e((string) old('email')) ?>">
        </div>
    </div>
    <button type="submit" class="btn btn-primary w-100 btn-lg">
        <i class="bi bi-send me-1"></i> إرسال رابط الاستعادة
    </button>
</form>

<?php if ($devLink !== ''): ?>
    <div class="alert alert-info mt-3 small">
        <strong>وضع التطوير:</strong> لم يتم إعداد بريد حقيقي (MAIL_DRIVER=log)، لذلك هذا هو رابط الاستعادة مباشرة:
        <div class="mt-2 d-flex gap-2">
            <a href="<?= e($devLink) ?>" class="btn btn-sm btn-outline-primary flex-grow-1 text-truncate">فتح رابط الاستعادة</a>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-copy="<?= e($devLink) ?>"><i class="bi bi-clipboard"></i></button>
        </div>
        <div class="mt-2 text-muted">اضبط MAIL_DRIVER=smtp مع بيانات بريدك لإرسال الروابط تلقائياً.</div>
    </div>
<?php endif; ?>

<hr class="pl-hr">
<p class="text-center small mb-0"><a href="<?= e(url('auth/login')) ?>">الرجوع لتسجيل الدخول</a></p>
