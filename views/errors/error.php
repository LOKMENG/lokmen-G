<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card text-center">
                <div class="card-body p-5">
                    <div class="display-1 fw-bold text-primary"><?= e(ar_digits($code)) ?></div>
                    <h2 class="h4 mt-2 mb-3"><?= e($title) ?></h2>
                    <p class="text-muted" style="white-space: pre-line;"><?= e($message) ?></p>
                    <div class="d-flex justify-content-center gap-2 mt-4 flex-wrap">
                        <a href="<?= e(url('')) ?>" class="btn btn-primary"><i class="bi bi-house me-1"></i> الصفحة الرئيسية</a>
                        <?php if (auth()->check()): ?>
                            <a href="<?= e(url('student/dashboard')) ?>" class="btn btn-outline-primary"><i class="bi bi-speedometer2 me-1"></i> لوحتي</a>
                        <?php else: ?>
                            <a href="<?= e(url('auth/login')) ?>" class="btn btn-outline-primary"><i class="bi bi-box-arrow-in-left me-1"></i> تسجيل الدخول</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
