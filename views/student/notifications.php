<?php /** @var array $notifications @var int $unread */ ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-bell text-primary me-2"></i> الإشعارات
            <?php if ($unread > 0): ?>
                <span class="badge bg-danger"><?= e(ar_digits($unread)) ?> جديد</span>
            <?php endif; ?>
        </span>
        <?php if ($unread > 0): ?>
            <form method="post" class="mb-0">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_all_read">
                <button class="btn btn-sm btn-outline-primary"><i class="bi bi-check2-all me-1"></i> تعليم الكل كمقروء</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if ($notifications === []): ?>
            <div class="pl-empty">
                <i class="bi bi-inbox"></i>
                لا توجد إشعارات حالياً.
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($notifications as $notification): ?>
                    <div class="list-group-item py-3 <?= (int) $notification['is_read'] === 0 ? 'bg-primary-soft' : '' ?>">
                        <div class="d-flex gap-3">
                            <div class="pl-stat-icon <?= $notification['type'] === 'subscription' ? 'is-gold' : '' ?>" style="width:42px;height:42px;flex:0 0 42px;font-size:1.1rem;">
                                <i class="bi <?= $notification['type'] === 'subscription' ? 'bi-credit-card' : ($notification['type'] === 'exam' ? 'bi-journal-check' : 'bi-info-circle') ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold"><?= e($notification['title']) ?></div>
                                <div class="small text-muted" style="white-space: pre-line;"><?= e($notification['body'] ?? '') ?></div>
                                <div class="small text-muted mt-1">
                                    <i class="bi bi-clock me-1"></i><?= e(format_date((string) $notification['created_at'], true)) ?>
                                </div>
                            </div>
                            <?php if (!empty($notification['link'])): ?>
                                <a href="<?= e($notification['link']) ?>" class="btn btn-sm btn-outline-primary align-self-center">فتح</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
