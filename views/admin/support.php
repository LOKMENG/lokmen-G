<?php
/** @var array $paginated @var array $filters @var array $counters */
$channelLabels = ['site' => 'الموقع', 'telegram' => 'تلجرام', 'email' => 'البريد', 'whatsapp' => 'واتساب'];
$priorityLabels = ['high' => ['danger', 'عالية'], 'normal' => ['info', 'عادية'], 'low' => ['secondary', 'منخفضة']];
?>
<div class="row g-3 mb-3">
    <?php
    $cards = [
        ['open', 'تذاكر مفتوحة', $counters['open'], 'is-red', 'bi-envelope-open'],
        ['answered', 'تمت الإجابة', $counters['answered'], 'is-gold', 'bi-reply-all'],
        ['closed', 'مغلقة', $counters['closed'], 'is-teal', 'bi-check2-circle'],
        ['', 'أولوية عالية', $counters['high'], 'is-blue', 'bi-exclamation-triangle'],
    ];
    foreach ($cards as [$key, $label, $value, $class, $icon]):
    ?>
        <div class="col-6 col-lg-3">
            <a href="<?= e(url('admin/support', ['status' => $key])) ?>" class="pl-stat text-decoration-none">
                <div class="pl-stat-icon <?= e($class) ?>"><i class="bi <?= e($icon) ?>"></i></div>
                <div><div class="value"><?= e(ar_digits($value)) ?></div><div class="label"><?= e($label) ?></div></div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small" for="status">الحالة</label>
                <select class="form-select form-select-sm" id="status" name="status">
                    <option value="">الكل</option>
                    <?php foreach (['open' => 'مفتوحة', 'answered' => 'تمت الإجابة', 'closed' => 'مغلقة'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="channel">القناة</label>
                <select class="form-select form-select-sm" id="channel" name="channel">
                    <option value="">الكل</option>
                    <?php foreach ($channelLabels as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $filters['channel'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel me-1"></i> تصفية</button></div>
        </form>
    </div>
</div>

<?php if ($paginated['rows'] === []): ?>
    <div class="card"><div class="pl-empty"><i class="bi bi-inbox"></i> لا توجد تذاكر مطابقة.</div></div>
<?php else: ?>
    <?php foreach ($paginated['rows'] as $ticket): ?>
        <?php [$priorityClass, $priorityLabel] = $priorityLabels[$ticket['priority']] ?? ['secondary', $ticket['priority']]; ?>
        <div class="card mb-3">
            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="fw-bold small"><?= e($ticket['subject']) ?></span>
                    <span class="badge bg-<?= e($priorityClass) ?>-subtle text-<?= e($priorityClass) ?>-emphasis"><?= e($priorityLabel) ?></span>
                    <span class="badge bg-light text-dark border"><?= e($channelLabels[$ticket['channel']] ?? $ticket['channel']) ?></span>
                    <?php if ($ticket['status'] === 'open'): ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis">مفتوحة</span>
                    <?php elseif ($ticket['status'] === 'answered'): ?>
                        <span class="badge bg-success-subtle text-success-emphasis">تمت الإجابة</span>
                    <?php else: ?>
                        <span class="badge bg-secondary-subtle text-secondary-emphasis">مغلقة</span>
                    <?php endif; ?>
                </div>
                <div class="small text-muted">
                    #<?= e(ar_digits((int) $ticket['id'])) ?> — <?= e(format_date((string) $ticket['created_at'], true)) ?>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-lg-5">
                        <div class="small text-muted mb-1">مقدّم الطلب:</div>
                        <div class="small">
                            <?php if ($ticket['user_id'] !== null): ?>
                                <a href="<?= e(url('admin/user-form', ['id' => (int) $ticket['user_id']])) ?>" class="fw-semibold text-decoration-none"><?= e((string) $ticket['full_name']) ?></a>
                                <div class="text-muted"><?= e((string) $ticket['email']) ?> — <?= e((string) $ticket['phone']) ?></div>
                            <?php else: ?>
                                <span class="fw-semibold"><?= e((string) ($ticket['name'] ?? 'زائر')) ?></span>
                                <div class="text-muted"><?= e((string) ($ticket['contact'] ?? '—')) ?></div>
                                <?php if ($ticket['telegram_user_id'] !== null): ?>
                                    <div class="text-muted">تلجرام: <span class="pl-copy"><?= e((string) $ticket['telegram_user_id']) ?></span></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="border rounded p-2 mt-3 small" style="white-space: pre-line;"><?= e((string) $ticket['message']) ?></div>
                        <?php if (!empty($ticket['attachment_path'])): ?>
                            <a href="<?= e(upload_url((string) $ticket['attachment_path'])) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary mt-2">
                                <i class="bi bi-paperclip me-1"></i> المرفق
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="col-lg-7">
                        <?php if (!empty($ticket['admin_reply'])): ?>
                            <div class="alert alert-success small">
                                <div class="fw-bold mb-1"><i class="bi bi-reply me-1"></i> الرد السابق (<?= e((string) ($ticket['replied_by_name'] ?? '—')) ?> — <?= e(format_date((string) ($ticket['replied_at'] ?? ''), true)) ?>)</div>
                                <div style="white-space: pre-line;"><?= e((string) $ticket['admin_reply']) ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
                            <div class="mb-2">
                                <label class="form-label small" for="reply<?= (int) $ticket['id'] ?>">الرد على الطالب</label>
                                <textarea class="form-control" id="reply<?= (int) $ticket['id'] ?>" name="admin_reply" rows="3" required><?= e((string) ($ticket['admin_reply'] ?? '')) ?></textarea>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label small" for="priority<?= (int) $ticket['id'] ?>">الأولوية</label>
                                    <select class="form-select form-select-sm" id="priority<?= (int) $ticket['id'] ?>" name="priority">
                                        <?php foreach (['high' => 'عالية', 'normal' => 'عادية', 'low' => 'منخفضة'] as $key => $label): ?>
                                            <option value="<?= e($key) ?>" <?= $ticket['priority'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button class="btn btn-sm btn-primary" name="action" value="reply"><i class="bi bi-send me-1"></i> إرسال الرد</button>
                                <?php if ($ticket['status'] !== 'closed'): ?>
                                    <button class="btn btn-sm btn-outline-secondary" name="action" value="close" data-confirm="إغلاق التذكرة؟"><i class="bi bi-check2-circle me-1"></i> إغلاق</button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-primary" name="action" value="reopen"><i class="bi bi-arrow-counterclockwise me-1"></i> إعادة فتح</button>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-outline-danger ms-auto" name="action" value="delete" data-confirm="حذف التذكرة نهائياً؟"><i class="bi bi-trash"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if ((int) $paginated['pages'] > 1): ?>
        <div class="card"><div class="card-footer"><?= pagination((int) $paginated['page'], (int) $paginated['pages'], $filters) ?></div></div>
    <?php endif; ?>
<?php endif; ?>
