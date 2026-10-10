<?php /** @var array $user @var array $errors @var array $tracks @var array|null $telegram */ ?>
<?php if ($errors !== []): ?>
    <div class="alert alert-danger py-2 small">
        <?php foreach ($errors as $error): ?><div><i class="bi bi-exclamation-triangle me-1"></i><?= e($error) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center">
                <img src="<?= e(avatar_url($user['avatar'] ?? null, (string) $user['full_name'])) ?>" alt="الصورة الرمزية" class="pl-avatar-lg mb-3">
                <h5 class="mb-1"><?= e($user['full_name']) ?></h5>
                <p class="text-muted small mb-2"><?= e($user['email']) ?></p>
                <div class="d-flex justify-content-center gap-2 mb-3">
                    <span class="badge bg-primary-subtle text-primary-emphasis">
                        <?= $user['role'] === 'admin' ? 'مدير' : ($user['role'] === 'supervisor' ? 'مشرف' : 'طالب') ?>
                    </span>
                    <?= active_badge(($user['status'] ?? '') === 'active', 'حساب نشط', 'حساب معطّل') ?>
                </div>
                <ul class="list-unstyled small text-muted mb-0">
                    <li class="mb-1"><i class="bi bi-telephone me-1"></i> <?= e($user['phone']) ?></li>
                    <li class="mb-1"><i class="bi bi-geo-alt me-1"></i> <?= e($user['city'] ?: 'غير محدد') ?></li>
                    <li class="mb-1"><i class="bi bi-briefcase me-1"></i> <?= e($user['specialty'] ?: 'غير محدد') ?></li>
                    <li><i class="bi bi-calendar me-1"></i> عضو منذ <?= e(format_date($user['created_at'])) ?></li>
                </ul>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-telegram text-primary me-2"></i> تلجرام</div>
            <div class="card-body small">
                <?php if ($telegram !== null && $telegram['user_id'] !== null): ?>
                    <p class="mb-2 text-success"><i class="bi bi-check-circle me-1"></i> الحساب مربوط بتلجرام</p>
                    <?php if (!empty($telegram['telegram_username'])): ?>
                        <p class="mb-2">المعرّف: <span class="pl-copy">@<?= e($telegram['telegram_username']) ?></span></p>
                    <?php endif; ?>
                    <p class="text-muted mb-3">ستصلك إشعارات الاشتراك وتقارير الأداء على تلجرام.</p>
                <?php else: ?>
                    <p class="text-muted">لم يتم ربط حسابك بتلجرام بعد.</p>
                <?php endif; ?>
                <a href="<?= e(url('student/telegram')) ?>" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-link-45deg me-1"></i> إدارة الربط
                </a>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-person-gear text-primary me-2"></i> البيانات الشخصية</div>
            <div class="card-body">
                <form method="post" action="<?= e(url('student/profile')) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="profile">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="full_name">الاسم الكامل</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required value="<?= e($user['full_name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">البريد الإلكتروني</label>
                            <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
                            <div class="form-text">لتغيير البريد تواصل مع الدعم الفني.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">رقم الجوال</label>
                            <input type="tel" class="form-control" value="<?= e($user['phone']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="city">المدينة</label>
                            <input type="text" class="form-control" id="city" name="city" value="<?= e($user['city'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="specialty">التخصص</label>
                            <input type="text" class="form-control" id="specialty" name="specialty" value="<?= e($user['specialty'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="target_track_id">المسار المستهدف</label>
                            <select class="form-select" id="target_track_id" name="target_track_id">
                                <option value="">— اختر —</option>
                                <?php foreach ($tracks as $track): ?>
                                    <option value="<?= (int) $track['id'] ?>" <?= (int) ($user['target_track_id'] ?? 0) === (int) $track['id'] ? 'selected' : '' ?>>
                                        <?= e($track['name_ar']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="avatar">الصورة الرمزية</label>
                            <input type="file" class="form-control" id="avatar" name="avatar" accept="image/*">
                            <div class="form-text">JPG/PNG/WEBP بحجم أقل من 3 ميجابايت.</div>
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> حفظ التغييرات</button>
                        <a href="<?= e(url('student/dashboard')) ?>" class="btn btn-outline-secondary">إلغاء</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-shield-lock text-primary me-2"></i> تغيير كلمة المرور</div>
            <div class="card-body">
                <form method="post" action="<?= e(url('student/profile')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="password">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="current_password">كلمة المرور الحالية</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                <span class="input-group-text cursor-pointer" data-toggle-password="#current_password"><i class="bi bi-eye"></i></span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="new_password">كلمة المرور الجديدة</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="password" required>
                                <span class="input-group-text cursor-pointer" data-toggle-password="#new_password"><i class="bi bi-eye"></i></span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="password_confirmation">تأكيد كلمة المرور</label>
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline-primary mt-3">
                        <i class="bi bi-key me-1"></i> تحديث كلمة المرور
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
