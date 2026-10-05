<?php
/** @var array|null $user @var array $tracks @var array $subscriptions @var array $payments @var array $attempts @var array $errors @var array|null $currentSub */
$isEdit = $user !== null;
$value = static fn(string $key, string $default = '') => e((string) ($user[$key] ?? old($key, $default)));
?>
<?php if ($errors !== []): ?>
    <div class="alert alert-danger py-2 small">
        <?php foreach ($errors as $error): ?><div><i class="bi bi-exclamation-triangle me-1"></i><?= e($error) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-<?= $isEdit ? '7' : '12' ?>">
        <div class="card">
            <div class="card-header"><i class="bi bi-person-gear text-primary me-2"></i> بيانات المستخدم</div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) ($user['id'] ?? 0) ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="full_name">الاسم الكامل</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required value="<?= $value('full_name') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">البريد الإلكتروني</label>
                            <input type="email" class="form-control" id="email" name="email" required value="<?= $value('email') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">رقم الجوال</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required value="<?= $value('phone') ?>" placeholder="05XXXXXXXX">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="city">المدينة</label>
                            <input type="text" class="form-control" id="city" name="city" value="<?= $value('city') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="specialty">التخصص</label>
                            <input type="text" class="form-control" id="specialty" name="specialty" value="<?= $value('specialty') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="target_track_id">المسار المستهدف</label>
                            <select class="form-select" id="target_track_id" name="target_track_id">
                                <option value="">— غير محدد —</option>
                                <?php foreach ($tracks as $track): ?>
                                    <option value="<?= (int) $track['id'] ?>" <?= (int) ($user['target_track_id'] ?? 0) === (int) $track['id'] ? 'selected' : '' ?>>
                                        <?= e($track['name_ar']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="role">الدور</label>
                            <select class="form-select" id="role" name="role" <?= ($isEdit && (int) $user['id'] === (int) user()['id']) ? 'disabled' : '' ?>>
                                <?php foreach (['student' => 'طالب', 'supervisor' => 'مشرف محتوى', 'admin' => 'مدير'] as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= ($user['role'] ?? 'student') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($isEdit && (int) $user['id'] === (int) user()['id']): ?>
                                <input type="hidden" name="role" value="<?= e($user['role']) ?>">
                                <div class="form-text">لا يمكن تغيير دورك الخاص.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="status">حالة الحساب</label>
                            <select class="form-select" id="status" name="status">
                                <?php foreach (['active' => 'نشط', 'disabled' => 'معطّل', 'pending' => 'بانتظار التفعيل'] as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= ($user['status'] ?? 'active') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="password">كلمة المرور <?= $isEdit ? '(اتركها فارغة لعدم التغيير)' : '' ?></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" <?= $isEdit ? '' : 'required' ?>>
                                <span class="input-group-text cursor-pointer" data-toggle-password="#password"><i class="bi bi-eye"></i></span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="password_confirmation">تأكيد كلمة المرور</label>
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="notes">ملاحظات إدارية</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"><?= $value('notes') ?></textarea>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-primary"><i class="bi bi-save me-1"></i> <?= $isEdit ? 'حفظ التعديلات' : 'إنشاء المستخدم' ?></button>
                        <a href="<?= e(url('admin/users')) ?>" class="btn btn-outline-secondary">رجوع</a>
                        <?php if ($isEdit): ?>
                            <a href="<?= e(url('admin/user-form')) ?>" class="btn btn-outline-primary ms-auto"><i class="bi bi-plus-circle me-1"></i> مستخدم جديد</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($isEdit): ?>
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-card-checklist text-primary me-2"></i> الاشتراك</span>
                    <?= subscription_badge($currentSub['status'] ?? null) ?>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled small mb-3">
                        <li class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-muted">الباقة</span><strong><?= e($currentSub['plan_name'] ?? 'لا يوجد') ?></strong></li>
                        <li class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-muted">من</span><span><?= e(format_date($currentSub['started_at'] ?? null)) ?></span></li>
                        <li class="d-flex justify-content-between py-1">
                            <span class="text-muted">إلى</span><span><?= e(format_date($currentSub['expires_at'] ?? null)) ?></span></li>
                    </ul>
                    <div class="d-flex gap-2 flex-wrap">
                        <form method="post" action="<?= e(url('admin/subscriptions')) ?>" class="d-flex gap-2 flex-grow-1">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="activate">
                            <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                            <select name="plan_id" class="form-select form-select-sm">
                                <?php foreach (\App\Services\SubscriptionService::plans() as $plan): ?>
                                    <option value="<?= (int) $plan['id'] ?>"><?= e($plan['name_ar']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm btn-success text-nowrap" data-confirm="تفعيل اشتراك جديد لهذا المستخدم؟">
                                <i class="bi bi-patch-check me-1"></i> تفعيل يدوي
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-list-check text-primary me-2"></i> سجل الاشتراكات</div>
                <div class="card-body p-0">
                    <?php if ($subscriptions === []): ?>
                        <div class="pl-empty py-3"><i class="bi bi-inbox"></i> لا يوجد سجل.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle">
                                <tbody>
                                    <?php foreach ($subscriptions as $subscription): ?>
                                        <tr>
                                            <td class="small">
                                                <div class="fw-semibold"><?= e($subscription['plan_name']) ?></div>
                                                <div class="text-muted"><?= e(format_date($subscription['started_at'])) ?> → <?= e(format_date($subscription['expires_at'])) ?></div>
                                            </td>
                                            <td class="text-end"><?= subscription_badge((string) $subscription['status']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-receipt text-primary me-2"></i> المدفوعات</div>
                <div class="card-body p-0">
                    <?php if ($payments === []): ?>
                        <div class="pl-empty py-3"><i class="bi bi-wallet2"></i> لا توجد مدفوعات.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle">
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td class="small">
                                                <div class="pl-copy"><?= e($payment['reference_number'] ?? '—') ?></div>
                                                <div class="text-muted"><?= e(format_date((string) $payment['created_at'])) ?></div>
                                            </td>
                                            <td class="small"><?= e(money((float) $payment['amount'])) ?></td>
                                            <td class="text-end">
                                                <span class="badge bg-<?= e($payment['status'] === 'approved' ? 'success' : ($payment['status'] === 'rejected' ? 'danger' : 'warning')) ?>-subtle text-<?= e($payment['status'] === 'approved' ? 'success' : ($payment['status'] === 'rejected' ? 'danger' : 'warning')) ?>-emphasis">
                                                    <?= e($payment['status'] === 'approved' ? 'مقبول' : ($payment['status'] === 'rejected' ? 'مرفوض' : 'معلّق')) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="bi bi-clock-history text-primary me-2"></i> آخر الاختبارات</div>
                <div class="card-body p-0">
                    <?php if ($attempts === []): ?>
                        <div class="pl-empty py-3"><i class="bi bi-journal-x"></i> لا توجد محاولات.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle">
                                <tbody>
                                    <?php foreach ($attempts as $attempt): ?>
                                        <tr>
                                            <td class="small">
                                                <div><?= e(str_limit((string) $attempt['title'], 40)) ?></div>
                                                <div class="text-muted"><?= e(format_date($attempt['finished_at'] ?? $attempt['started_at'])) ?></div>
                                            </td>
                                            <td class="small">
                                                <span class="badge bg-<?= e(score_class((float) $attempt['score'])) ?>-subtle text-<?= e(score_class((float) $attempt['score'])) ?>-emphasis">
                                                    <?= e(ar_digits((float) $attempt['score'])) ?>%
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <a href="<?= e(url('exams/review', ['id' => (int) $attempt['id']])) ?>" class="btn btn-sm btn-outline-secondary">مراجعة</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
