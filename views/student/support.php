<?php /** @var array $tickets @var array $errors @var string $botUsername @var bool $telegramOn */ ?>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="bi bi-headset text-primary me-2"></i> تذكرة جديدة</div>
            <div class="card-body">
                <?php if ($errors !== []): ?>
                    <div class="alert alert-danger py-2 small"><?= e(reset($errors)) ?></div>
                <?php endif; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="subject">الموضوع</label>
                        <input type="text" class="form-control" id="subject" name="subject" required value="<?= e((string) old('subject')) ?>"
                               placeholder="مثال: مشكلة في تفعيل الاشتراك">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="message">تفاصيل الرسالة</label>
                        <textarea class="form-control" id="message" name="message" rows="5" required
                                  placeholder="اشرح المشكلة أو الاستفسار بالتفصيل..."><?= e((string) old('message')) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="priority">الأهمية</label>
                        <select class="form-select" id="priority" name="priority">
                            <option value="normal">عادي</option>
                            <option value="high">مهم</option>
                            <option value="low">غير عاجل</option>
                        </select>
                    </div>
                    <button class="btn btn-primary w-100"><i class="bi bi-send me-1"></i> إرسال التذكرة</button>
                </form>

                <?php if ($telegramOn && $botUsername !== ''): ?>
                    <hr class="pl-hr">
                    <p class="small text-muted mb-0">
                        يمكنك أيضاً التواصل مباشرة عبر بوت تلجرام:
                        <a href="https://t.me/<?= e(ltrim($botUsername, '@')) ?>" target="_blank" rel="noopener">
                            @<?= e(ltrim($botUsername, '@')) ?>
                        </a>
                        — أرسل <span class="pl-copy">/support</span> ثم رسالتك.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history text-primary me-2"></i> تذاكري السابقة</div>
            <div class="card-body p-0">
                <?php if ($tickets === []): ?>
                    <div class="pl-empty"><i class="bi bi-inbox"></i> لم ترسل أي تذكرة بعد.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($tickets as $ticket): ?>
                            <div class="list-group-item py-3">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <div class="fw-semibold"><?= e($ticket['subject']) ?></div>
                                        <div class="small text-muted"><?= e(format_date((string) $ticket['created_at'], true)) ?></div>
                                    </div>
                                    <?php
                                    [$statusClass, $statusLabel] = match ($ticket['status']) {
                                        'answered' => ['success', 'تم الرد'],
                                        'closed'   => ['secondary', 'مغلقة'],
                                        default     => ['warning', 'قيد المعالجة'],
                                    };
                                    ?>
                                    <span class="badge bg-<?= e($statusClass) ?>-subtle text-<?= e($statusClass) ?>-emphasis"><?= e($statusLabel) ?></span>
                                </div>
                                <p class="small mt-2 mb-0" style="white-space: pre-line;"><?= e(str_limit((string) $ticket['message'], 220)) ?></p>
                                <?php if (!empty($ticket['admin_reply'])): ?>
                                    <div class="alert alert-success small mt-2 mb-0">
                                        <strong>رد الدعم:</strong>
                                        <div style="white-space: pre-line;"><?= e($ticket['admin_reply']) ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
