<?php
/**
 * @var string $token @var bool $envWritable @var string $envPath @var bool $enabled @var array $webhookInfo
 * @var array $botInfo @var string $apiError @var string $webhookUrl @var string $username @var array $links @var array $stats
 */
$masked = $token !== '' ? substr($token, 0, 6) . '••••••••' . substr($token, -4) : '';
?>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="pl-stat"><div class="pl-stat-icon <?= $enabled ? 'is-teal' : 'is-red' ?>"><i class="bi bi-robot"></i></div>
            <div><div class="value"><?= $enabled ? 'مفعّل' : 'غير مفعّل' ?></div><div class="label">حالة البوت</div></div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pl-stat"><div class="pl-stat-icon is-blue"><i class="bi bi-link-45deg"></i></div>
            <div><div class="value"><?= e(ar_digits($stats['linked'])) ?></div><div class="label">حسابات مربوطة</div></div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pl-stat"><div class="pl-stat-icon is-gold"><i class="bi bi-telegram"></i></div>
            <div><div class="value"><?= e(ar_digits($stats['total'])) ?></div><div class="label">محادثات مسجّلة</div></div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pl-stat"><div class="pl-stat-icon is-red"><i class="bi bi-slash-circle"></i></div>
            <div><div class="value"><?= e(ar_digits($stats['blocked'])) ?></div><div class="label">حسابات محظورة</div></div></div>
    </div>
</div>

<?php if (!$enabled): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        البوت غير مفعّل بعد. أنشئ بوتاً عبر <strong>@BotFather</strong> في تلجرام، ثم أضف التوكن أدناه.
        البوت <strong>اختياري للطالب</strong> — لن يُطلب من أحد ربط حسابه.
    </div>
<?php endif; ?>

<?php if ($apiError !== ''): ?>
    <div class="alert alert-danger small"><i class="bi bi-x-octagon me-1"></i> خطأ من Telegram API: <?= e($apiError) ?></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-key text-primary me-2"></i> توكن البوت (في ملف .env)</div>
            <div class="card-body">
                <div class="alert alert-light border small">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">التوكن الحالي</span>
                        <span class="pl-copy"><?= $masked !== '' ? e($masked) : 'غير مُعرَّف' ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">ملف الإعدادات</span>
                        <span dir="ltr" class="pl-copy" style="font-size:.75rem;"><?= e($envPath) ?></span>
                    </div>
                </div>
                <?php if (!$envWritable): ?>
                    <div class="alert alert-warning small">
                        <i class="bi bi-lock me-1"></i> الملف غير قابل للكتابة من الخادم — أضف السطرين التاليين يدوياً في ملف <code>.env</code>:
                        <pre class="mb-0 mt-2 small" dir="ltr">TELEGRAM_BOT_TOKEN=123456789:AA...
TELEGRAM_BOT_USERNAME=your_bot</pre>
                    </div>
                <?php endif; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_env">
                    <div class="mb-3">
                        <label class="form-label" for="bot_token">توكن البوت</label>
                        <input type="text" class="form-control" id="bot_token" name="TELEGRAM_BOT_TOKEN" dir="ltr"
                               placeholder="123456789:AA..." <?= $envWritable ? '' : 'disabled' ?>>
                        <div class="form-text">اتركه فارغاً إن لم ترغب بتغييره (سيتم حذف التوكن الحالي).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="bot_username">اسم البوت (@username)</label>
                        <input type="text" class="form-control" id="bot_username" name="TELEGRAM_BOT_USERNAME" dir="ltr"
                               value="<?= e($username) ?>" placeholder="my_exam_bot" <?= $envWritable ? '' : 'disabled' ?>>
                    </div>
                    <button class="btn btn-primary" <?= $envWritable ? '' : 'disabled' ?>><i class="bi bi-save me-1"></i> حفظ في .env</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-broadcast text-primary me-2"></i> الويبهوك والإشعارات</div>
            <div class="card-body">
                <ul class="list-unstyled small mb-3">
                    <li class="d-flex justify-content-between border-bottom py-1">
                        <span class="text-muted">رابط الويبهوك المقترح</span>
                        <span dir="ltr" class="pl-copy" style="font-size:.75rem;"><?= e($webhookUrl) ?></span>
                    </li>
                    <li class="d-flex justify-content-between border-bottom py-1">
                        <span class="text-muted">حالة الويبهوك</span>
                        <span>
                            <?php if ((bool) ($webhookInfo['ok'] ?? false) && !empty($webhookInfo['result']['url'])): ?>
                                <span class="badge bg-success-subtle text-success-emphasis">مُسجَّل</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary-emphasis">غير مُسجَّل</span>
                            <?php endif; ?>
                        </span>
                    </li>
                    <?php if (!empty($webhookInfo['result']['url'])): ?>
                        <li class="d-flex justify-content-between border-bottom py-1">
                            <span class="text-muted">الرابط الحالي</span>
                            <span dir="ltr" class="pl-copy" style="font-size:.72rem;"><?= e((string) $webhookInfo['result']['url']) ?></span>
                        </li>
                        <li class="d-flex justify-content-between border-bottom py-1">
                            <span class="text-muted">تحديثات معلّقة</span><span><?= e(ar_digits((int) ($webhookInfo['result']['pending_update_count'] ?? 0))) ?></span>
                        </li>
                        <?php if (!empty($webhookInfo['result']['last_error_message'])): ?>
                            <li class="d-flex justify-content-between py-1 text-danger">
                                <span>آخر خطأ</span><span style="font-size:.72rem;"><?= e((string) $webhookInfo['result']['last_error_message']) ?></span>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ((bool) ($botInfo['ok'] ?? false)): ?>
                        <li class="d-flex justify-content-between py-1">
                            <span class="text-muted">البوت</span>
                            <span><?= e((string) ($botInfo['result']['first_name'] ?? '')) ?> (@<?= e((string) ($botInfo['result']['username'] ?? '')) ?>)</span>
                        </li>
                    <?php endif; ?>
                </ul>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="set_webhook">
                        <button class="btn btn-sm btn-primary" data-confirm="تسجيل الويبهوك على رابط المنصة؟"><i class="bi bi-cloud-upload me-1"></i> تسجيل الويبهوك</button></form>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete_webhook">
                        <button class="btn btn-sm btn-outline-danger" data-confirm="حذف الويبهوك؟"><i class="bi bi-cloud-slash me-1"></i> حذف الويبهوك</button></form>
                </div>

                <form method="post" class="border-top pt-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_settings">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="tg_enabled" name="telegram_enabled" value="1"
                            <?= (string) settings('telegram_enabled', '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="tg_enabled">تفعيل إشعارات تلجرام (اختيارية للطالب)</label>
                    </div>
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-6">
                            <label class="form-label" for="tg_days">تنبيه قبل انتهاء الاشتراك بـ</label>
                            <input type="number" class="form-control" id="tg_days" name="telegram_notify_expiry_days" min="1" max="60"
                                   value="<?= (int) settings('telegram_notify_expiry_days', 7) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="tg_username">اسم البوت المعروض</label>
                            <input type="text" class="form-control" id="tg_username" name="telegram_bot_username" dir="ltr" value="<?= e($username) ?>">
                        </div>
                    </div>
                    <div class="d-flex gap-2 mb-3">
                        <button class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i> حفظ الإعدادات</button>
                    </div>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" name="test_chat_id" dir="ltr" placeholder="chat id للاختبار">
                        <button class="btn btn-outline-secondary" name="action" value="test"><i class="bi bi-send me-1"></i> إرسال رسالة اختبار</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-info-circle text-primary me-2"></i> أوامر البوت المتاحة للطالب</span>
                <span class="small text-muted"><?= e(ar_digits($stats['states'])) ?> محادثة في حالة إدخال حالياً</span>
            </div>
            <div class="card-body">
                <div class="row g-3 small">
                    <div class="col-md-6 col-lg-4">
                        <div class="border rounded p-2 h-100">
                            <div class="fw-bold" dir="ltr">/start</div>
                            <div class="text-muted">رسالة ترحيب وشرح البوت وإمكانية الربط.</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="border rounded p-2 h-100">
                            <div class="fw-bold" dir="ltr">/link ABCD-EFGH</div>
                            <div class="text-muted">ربط الحساب بكود من صفحة «تلجرام» في حساب الطالب.</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="border rounded p-2 h-100">
                            <div class="fw-bold" dir="ltr">/status</div>
                            <div class="text-muted">عرض حالة الاشتراك وعدد الأيام المتبقية والمعدل العام.</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="border rounded p-2 h-100">
                            <div class="fw-bold" dir="ltr">/support نص الرسالة</div>
                            <div class="text-muted">إنشاء تذكرة دعم تظهر فوراً في لوحة الإدارة.</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="border rounded p-2 h-100">
                            <div class="fw-bold">إرسال صورة إثبات دفع</div>
                            <div class="text-muted">أثناء انتظار التفعيل: أرسل صورة الحوالة ليتم ربطها بطلب الاشتراك.</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="border rounded p-2 h-100">
                            <div class="fw-bold" dir="ltr">/unlink</div>
                            <div class="text-muted">فك ربط الحساب في أي وقت (البوت اختياري تماماً).</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="bi bi-people text-primary me-2"></i> المحادثات المربوطة</div>
            <div class="card-body p-0">
                <?php if ($links['rows'] === []): ?>
                    <div class="pl-empty"><i class="bi bi-telegram"></i> لم يبدأ أحد محادثة مع البوت بعد.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>#</th><th>المستخدم</th><th>تلجرام</th><th>كود الربط</th><th>آخر تفاعل</th><th>الحالة</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($links['rows'] as $link): ?>
                                    <tr>
                                        <td class="small text-muted"><?= e(ar_digits((int) $link['id'])) ?></td>
                                        <td class="small">
                                            <?php if ($link['user_id'] !== null): ?>
                                                <a href="<?= e(url('admin/user-form', ['id' => (int) $link['user_id']])) ?>" class="fw-semibold text-decoration-none"><?= e((string) $link['full_name']) ?></a>
                                                <div class="text-muted"><?= e((string) $link['email']) ?></div>
                                            <?php else: ?>
                                                <span class="text-muted">غير مربوط بحساب</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small">
                                            <div class="pl-copy" dir="ltr"><?= e((string) ($link['telegram_username'] !== null ? '@' . $link['telegram_username'] : $link['telegram_user_id'])) ?></div>
                                            <div class="text-muted" style="font-size:.72rem;"><?= e((string) ($link['telegram_first_name'] ?? '')) ?></div>
                                        </td>
                                        <td class="small pl-copy"><?= e((string) ($link['link_code'] ?? '—')) ?></td>
                                        <td class="small text-muted"><?= e(format_date((string) ($link['last_interaction_at'] ?? $link['created_at']), true)) ?></td>
                                        <td class="small">
                                            <?php if ((int) $link['is_blocked'] === 1): ?>
                                                <span class="badge bg-danger-subtle text-danger-emphasis">محظور</span>
                                            <?php elseif ($link['linked_at'] !== null): ?>
                                                <span class="badge bg-success-subtle text-success-emphasis">مربوط</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary-emphasis">بانتظار الربط</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <form method="post" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="unlink">
                                                <input type="hidden" name="link_id" value="<?= (int) $link['id'] ?>">
                                                <button class="btn btn-sm btn-outline-secondary" data-confirm="فك ربط هذا الحساب؟" title="فك الربط"><i class="bi bi-link-45deg"></i></button>
                                            </form>
                                            <form method="post" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="block">
                                                <input type="hidden" name="link_id" value="<?= (int) $link['id'] ?>">
                                                <input type="hidden" name="blocked" value="<?= (int) $link['is_blocked'] === 1 ? '0' : '1' ?>">
                                                <button class="btn btn-sm btn-outline-danger" data-confirm="تغيير حالة الحظر؟" title="حظر/إلغاء حظر"><i class="bi bi-slash-circle"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ((int) $links['pages'] > 1): ?>
                <div class="card-footer"><?= pagination((int) $links['page'], (int) $links['pages'], []) ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>
