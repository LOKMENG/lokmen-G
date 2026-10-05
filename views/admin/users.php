<?php /** @var array $paginated @var array $filters @var array $counters */ ?>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="pl-stat"><div class="pl-stat-icon"><i class="bi bi-people"></i></div>
            <div><div class="value"><?= e(ar_digits($counters['total'])) ?></div><div class="label">إجمالي المستخدمين</div></div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pl-stat"><div class="pl-stat-icon is-blue"><i class="bi bi-mortarboard"></i></div>
            <div><div class="value"><?= e(ar_digits($counters['students'])) ?></div><div class="label">الطلاب</div></div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pl-stat"><div class="pl-stat-icon is-teal"><i class="bi bi-check2-circle"></i></div>
            <div><div class="value"><?= e(ar_digits($counters['active'])) ?></div><div class="label">حسابات نشطة</div></div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pl-stat"><div class="pl-stat-icon is-red"><i class="bi bi-slash-circle"></i></div>
            <div><div class="value"><?= e(ar_digits($counters['disabled'])) ?></div><div class="label">حسابات معطّلة</div></div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small" for="q">بحث</label>
                <input type="text" class="form-control form-control-sm" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="الاسم / البريد / الجوال">
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="role">الدور</label>
                <select class="form-select form-select-sm" id="role" name="role">
                    <option value="">الكل</option>
                    <?php foreach (['student' => 'طالب', 'supervisor' => 'مشرف', 'admin' => 'مدير'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $filters['role'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="status">الحالة</label>
                <select class="form-select form-select-sm" id="status" name="status">
                    <option value="">الكل</option>
                    <?php foreach (['active' => 'نشط', 'disabled' => 'معطّل', 'pending' => 'بانتظار التفعيل'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="subscription">الاشتراك</label>
                <select class="form-select form-select-sm" id="subscription" name="subscription">
                    <option value="">الكل</option>
                    <?php foreach (['active' => 'نشط', 'pending' => 'بانتظار الدفع', 'expired' => 'منتهي', 'none' => 'بدون اشتراك'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $filters['subscription'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-search"></i> بحث</button>
                <a href="<?= e(url('admin/user-form')) ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-ul text-primary me-2"></i> النتائج (<?= e(ar_digits($paginated['total'])) ?>)</span>
        <a href="<?= e(url('admin/user-form')) ?>" class="btn btn-sm btn-primary"><i class="bi bi-person-plus me-1"></i> إضافة مستخدم</a>
    </div>
    <div class="card-body p-0">
        <?php if ($paginated['rows'] === []): ?>
            <div class="pl-empty"><i class="bi bi-search"></i> لا توجد نتائج مطابقة للبحث.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th><th>المستخدم</th><th class="d-none d-md-table-cell">الجوال</th><th>الدور</th>
                            <th>الاشتراك</th><th class="d-none d-lg-table-cell">اختبارات</th><th>الحالة</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginated['rows'] as $user): ?>
                            <tr>
                                <td class="small text-muted"><?= e(ar_digits((int) $user['id'])) ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?= e(avatar_url($user['avatar'] ?? null, (string) $user['full_name'])) ?>" alt="" class="pl-avatar" style="width:34px;height:34px;">
                                        <div>
                                            <div class="fw-semibold small"><?= e($user['full_name']) ?></div>
                                            <div class="text-muted small"><?= e($user['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="small d-none d-md-table-cell pl-copy"><?= e($user['phone']) ?></td>
                                <td class="small">
                                    <span class="badge bg-light text-dark border">
                                        <?= e($user['role'] === 'admin' ? 'مدير' : ($user['role'] === 'supervisor' ? 'مشرف' : 'طالب')) ?>
                                    </span>
                                </td>
                                <td class="small">
                                    <?= subscription_badge($user['sub_status']) ?>
                                    <?php if (!empty($user['sub_expires'])): ?>
                                        <div class="text-muted" style="font-size:.75rem;">حتى <?= e(format_date((string) $user['sub_expires'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="small d-none d-lg-table-cell"><?= e(ar_digits((int) $user['attempts_count'])) ?></td>
                                <td><?= active_badge(($user['status'] ?? '') === 'active') ?></td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="<?= e(url('admin/user-form', ['id' => (int) $user['id']])) ?>"><i class="bi bi-pencil me-2"></i> تعديل</a></li>
                                            <li>
                                                <form method="post" class="px-0">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                                    <input type="hidden" name="action" value="toggle">
                                                    <button class="dropdown-item" data-confirm="تغيير حالة الحساب؟">
                                                        <i class="bi bi-power me-2"></i> <?= $user['status'] === 'active' ? 'تعطيل الحساب' : 'تفعيل الحساب' ?>
                                                    </button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="post">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                                    <input type="hidden" name="action" value="extend">
                                                    <input type="hidden" name="days" value="30">
                                                    <button class="dropdown-item" data-confirm="تمديد الاشتراك 30 يوماً؟"><i class="bi bi-calendar-plus me-2"></i> تمديد 30 يوماً</button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="post">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                                    <input type="hidden" name="action" value="activate">
                                                    <button class="dropdown-item" data-confirm="تفعيل اشتراك يدوياً لهذا المستخدم؟"><i class="bi bi-patch-check me-2"></i> تفعيل اشتراك إداري</button>
                                                </form>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="post">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button class="dropdown-item text-danger" data-confirm="حذف المستخدم نهائياً مع كل بياناته؟ لا يمكن التراجع.">
                                                        <i class="bi bi-trash me-2"></i> حذف
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php if ((int) $paginated['pages'] > 1): ?>
        <div class="card-footer">
            <?= pagination((int) $paginated['page'], (int) $paginated['pages'], array_merge($filters, ['page' => $paginated['page']])) ?>
        </div>
    <?php endif; ?>
</div>
