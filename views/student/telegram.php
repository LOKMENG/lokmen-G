<?php /** @var array|null $link @var array|null $pending @var string $linkCode @var string $botUsername @var bool $enabled */ ?>
<div class="row g-3 justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-telegram text-primary me-2"></i> بوت تلجرام</div>
            <div class="card-body">
                <?php if (!$enabled): ?>
                    <div class="alert alert-warning small">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        لم يتم إعداد توكن البوت بعد. أضف <code>TELEGRAM_BOT_TOKEN</code> في ملف <code>.env</code> ثم فعّل الـ Webhook
                        من <a href="<?= e(url('admin/telegram')) ?>">لوحة الإدارة → تلجرام</a>.
                    </div>
                <?php endif; ?>

                <?php if ($link !== null && (int) $link['telegram_user_id'] > 0 && $link['user_id'] !== null): ?>
                    <div class="alert alert-success small">
                        <i class="bi bi-check-circle me-1"></i>
                        <strong>حسابك مربوط بتلجرام بنجاح.</strong>
                        <?php if (!empty($link['telegram_username'])): ?>
                            <span class="pl-copy">@<?= e($link['telegram_username']) ?></span>
                        <?php endif; ?>
                        — تاريخ الربط: <?= e(format_date($link['linked_at'])) ?>
                    </div>
                    <ul class="small text-muted">
                        <li>ستصلك إشعارات تفعيل الاشتراك وقرب انتهائه.</li>
                        <li>يمكنك الاستعلام عن حالة حسابك في أي وقت بإرسال <span class="pl-copy">/status</span>.</li>
                        <li>يمكنك إرسال إثبات الدفع مباشرة من تلجرام.</li>
                    </ul>
                    <form method="post" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="unlink">
                        <button class="btn btn-outline-danger btn-sm" data-confirm="هل تريد فصل حساب تلجرام؟">
                            <i class="bi bi-link-45deg me-1"></i> فصل الحساب
                        </button>
                    </form>
                <?php else: ?>
                    <ol class="mb-4">
                        <li class="mb-2">افتح البوت في تلجرام:
                            <?php if ($botUsername !== ''): ?>
                                <a href="https://t.me/<?= e(ltrim($botUsername, '@')) ?>" target="_blank" rel="noopener" class="fw-bold">
                                    @<?= e(ltrim($botUsername, '@')) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">(غير مضبوط في الإعدادات)</span>
                            <?php endif; ?>
                        </li>
                        <li class="mb-2">أرسل الأمر <span class="pl-copy">/link</span> ثم رمز الربط (أو اضغط زر الربط التلقائي بعد إنشاء الرمز).</li>
                        <li>سيتم ربط حسابك فوراً.</li>
                    </ol>

                    <?php if ($linkCode !== ''): ?>
                        <div class="alert alert-success">
                            <div class="small mb-2">
                                رمز الربط الخاص بك — يُعرض <strong>مرة واحدة فقط</strong>، صالح حتى
                                <?= e(format_date((string) ($pending['expires_at'] ?? date('Y-m-d H:i:s', time() + 1800)), true)) ?>
                                (تنتهي صلاحيته أيضاً بعد أول استخدام)
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="pl-copy fs-4"><?= e($linkCode) ?></span>
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-copy="<?= e($linkCode) ?>">
                                    <i class="bi bi-clipboard"></i> نسخ الرمز
                                </button>
                                <?php if ($botUsername !== ''): ?>
                                    <a class="btn btn-sm btn-primary" target="_blank" rel="noopener"
                                       href="https://t.me/<?= e(ltrim($botUsername, '@')) ?>?start=<?= e(urlencode($linkCode)) ?>">
                                        <i class="bi bi-telegram me-1"></i> فتح البوت والربط تلقائياً
                                    </a>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-outline-primary" type="button" data-copy="/link <?= e($linkCode) ?>">
                                    <i class="bi bi-clipboard-check"></i> نسخ أمر الربط كامل
                                </button>
                            </div>
                            <div class="small text-muted mt-2">
                                لا تشارك الرمز مع أي شخص — من يملكه يمكنه ربط حسابه بحسابك.
                            </div>
                        </div>
                    <?php elseif ($pending !== null): ?>
                        <div class="alert alert-info small">
                            <i class="bi bi-hourglass-split me-1"></i>
                            يوجد رمز ربط فعّال ينتهي في <?= e(format_date((string) $pending['expires_at'], true)) ?>
                            (آخره <span class="pl-copy"><?= e((string) $pending['code_hint']) ?></span>).
                            الرمز الكامل لا يمكن عرضه مرة أخرى؛ إن فقدته أنشئ رمزاً جديداً.
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="generate">
                        <button class="btn btn-primary">
                            <i class="bi bi-qr-code me-1"></i> <?= $linkCode !== '' ? 'إنشاء رمز جديد (يُلغي الحالي)' : 'إنشاء رمز الربط' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
