<?php
/** @var array $errors @var callable $old */
?>
<h3 class="fw-bold mb-1">تسجيل الدخول</h3>
<p class="text-muted small mb-4">أدخل بياناتك للوصول إلى بنك الأسئلة والاختبارات.</p>

<?php if (!empty($errors['identifier'])): ?>
    <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle me-1"></i><?= e($errors['identifier']) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(url('auth/login')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label" for="identifier">البريد الإلكتروني أو رقم الجوال</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" class="form-control" id="identifier" name="identifier" required autofocus
                   value="<?= $old('identifier') ?>" placeholder="example@email.com أو 05XXXXXXXX">
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label" for="password">كلمة المرور</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
            <span class="input-group-text cursor-pointer" data-toggle-password="#password"><i class="bi bi-eye"></i></span>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" <?= old('remember') ? 'checked' : '' ?>>
            <label class="form-check-label small" for="remember">تذكر تسجيل الدخول</label>
        </div>
        <a class="small" href="<?= e(url('auth/forgot-password')) ?>">نسيت كلمة المرور؟</a>
    </div>
    <button type="submit" class="btn btn-primary w-100 btn-lg">
        <i class="bi bi-box-arrow-in-left me-1"></i> تسجيل الدخول
    </button>
</form>

<hr class="pl-hr">
<p class="text-center small text-muted mb-2">
    ليس لديك حساب؟
    <a href="<?= e(url('auth/register')) ?>" class="fw-bold">أنشئ حساباً جديداً</a>
</p>
<?php if (trim((string) settings('telegram_bot_username', '')) !== ''): ?>
    <p class="text-center small mb-0">
        <i class="bi bi-telegram text-primary"></i>
        <a href="https://t.me/<?= e(ltrim((string) settings('telegram_bot_username'), '@')) ?>" target="_blank" rel="noopener">
            تابعنا على تلجرام
        </a>
    </p>
<?php endif; ?>
