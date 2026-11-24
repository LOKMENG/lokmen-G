<?php
/**
 * @var array $groups @var array $settingsByGroup @var float $price1
 */
$fieldTypes = [
    'site_name'                => ['text', 'اسم المنصة كما يظهر للطلاب والزوار'],
    'site_tagline'             => ['text', 'جملة قصيرة تظهر أسفل الاسم'],
    'site_description'         => ['textarea', 'وصف المنصة (يظهر في الصفحة الرئيسية وفي نتائج البحث)'],
    'contact_email'            => ['email', 'البريد الإلكتروني الرسمي'],
    'contact_phone'            => ['tel', 'رقم الجوال (يُستخدم في الواتساب والدعم)'],
    'default_currency'         => ['text', 'SAR'],
    'subscription_price'       => ['number', 'قيمة الاشتراك الأساسية بالريال'],
    'subscription_days'        => ['number', 'عدد أيام صلاحية الاشتراك'],
    'payment_instructions'     => ['textarea', 'تظهر للطالب في صفحة إتمام الاشتراك'],
    'bank_name'                => ['text', 'اسم البنك'],
    'bank_iban'                => ['text', 'الآيبان بصيغة SA...'],
    'bank_account_name'        => ['text', 'اسم المستفيد'],
    'stc_pay_number'           => ['text', 'رقم STC Pay أو اتركه فارغاً'],
    'telegram_bot_username'    => ['text', 'بدون علامة @'],
    'telegram_admin_chat_id'   => ['text', 'chat id لاستلام إشعارات الإدارة (اختياري)'],
    'telegram_notify_expiry_days' => ['number', 'التنبيه قبل انتهاء الاشتراك (أيام)'],
    'exam_default_pass'        => ['number', 'نسبة النجاح الافتراضية في القوالب الجديدة'],
    'strength_threshold'       => ['number', 'نسبة الدقة التي تُعد نقطة قوة'],
    'weakness_threshold'       => ['number', 'النسبة التي تُعد دونها نقطة ضعف'],
    'moyasar_enabled'          => ['bool', 'يتطلب تعبئة MOYASAR_PUBLIC_KEY و SECRET_KEY في .env'],
    'myfatoorah_enabled'       => ['bool', 'يتطلب تعبئة MYFATOORAH_API_KEY في .env'],
    'manual_payment_enabled'   => ['bool', 'الحوالة البنكية / STC Pay مع رفع الإيصال'],
    'free_trial_enabled'       => ['bool', 'تفعيل فترة تجريبية (اختياري)'],
    'allow_guest_exam'         => ['bool', 'السماح باختبار تجريبي مجاني للزوار'],
    'maintenance_mode'         => ['bool', 'إظهار صفحة الصيانة للزوار'],
];
?>
<form method="post">
    <?= csrf_field() ?>
    <ul class="nav nav-pills mb-3 gap-1" role="tablist">
        <?php $first = true; foreach ($groups as $key => [$icon, $label]): ?>
            <li class="nav-item">
                <button class="nav-link <?= $first ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#group-<?= e($key) ?>" type="button" role="tab">
                    <i class="bi <?= e($icon) ?> me-1"></i> <?= e($label) ?>
                    <span class="badge bg-light text-dark border ms-1"><?= e(ar_digits(count($settingsByGroup[$key] ?? []))) ?></span>
                </button>
            </li>
        <?php $first = false; endforeach; ?>
    </ul>

    <div class="tab-content">
        <?php $first = true; foreach ($groups as $groupKey => [$icon, $label]): ?>
            <div class="tab-pane fade <?= $first ? 'show active' : '' ?>" id="group-<?= e($groupKey) ?>" role="tabpanel">
                <div class="card">
                    <div class="card-header"><i class="bi <?= e($icon) ?> text-primary me-2"></i> <?= e($label) ?></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach (($settingsByGroup[$groupKey] ?? []) as $setting): ?>
                                <?php
                                $key = (string) $setting['key'];
                                $type = (string) $setting['type'];
                                [$inputType, $hint] = $fieldTypes[$key] ?? ['text', ''];
                                if ($key === 'demo_data_installed') {
                                    continue;
                                }
                                ?>
                                <div class="col-md-6">
                                    <?php if ($type === 'bool' || $inputType === 'bool'): ?>
                                        <div class="form-check form-switch mt-4">
                                            <input class="form-check-input" type="checkbox" role="switch" id="set_<?= e($key) ?>"
                                                   name="bools[]" value="<?= e($key) ?>" <?= (bool) $setting['value'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="set_<?= e($key) ?>">
                                                <?= e($setting['label']) ?>
                                            </label>
                                        </div>
                                    <?php else: ?>
                                        <label class="form-label" for="set_<?= e($key) ?>"><?= e($setting['label']) ?></label>
                                        <?php if ($inputType === 'textarea'): ?>
                                            <textarea class="form-control" id="set_<?= e($key) ?>" name="settings[<?= e($key) ?>]" rows="5"><?= e((string) $setting['value']) ?></textarea>
                                        <?php elseif ($inputType === 'number'): ?>
                                            <input type="number" class="form-control" id="set_<?= e($key) ?>" name="settings[<?= e($key) ?>]"
                                                   value="<?= e((string) $setting['value']) ?>" min="0" step="1">
                                        <?php else: ?>
                                            <input type="<?= e($inputType) ?>" class="form-control" id="set_<?= e($key) ?>" name="settings[<?= e($key) ?>]"
                                                   value="<?= e((string) $setting['value']) ?>">
                                        <?php endif; ?>
                                        <?php if ($hint !== ''): ?><div class="form-text"><?= e($hint) ?></div><?php endif; ?>
                                    <?php endif; ?>
                                    <div class="text-muted" style="font-size:.7rem;" dir="ltr"><?= e($key) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($groupKey === 'subscription'): ?>
                            <div class="alert alert-light border small mt-3 mb-0">
                                <i class="bi bi-info-circle me-1"></i>
                                قيمة الاشتراك الحالية: <strong><?= e(money($price1)) ?></strong>.
                                تغيير القيمة هنا يؤثر على الطلبات <strong>الجديدة</strong> فقط، ولا يغيّر اشتراكات الطلاب الحالية.
                            </div>
                        <?php endif; ?>
                        <?php if ($groupKey === 'payment'): ?>
                            <div class="alert alert-warning small mt-3 mb-0">
                                <i class="bi bi-shield-exclamation me-1"></i>
                                تشغيل بوابات الدفع يتطلب أيضاً تعبئة مفاتيحها في ملف <code>.env</code>
                                (MOYASAR_PUBLIC_KEY / MOYASAR_SECRET_KEY / MYFATOORAH_API_KEY) وضبط رابط الويبهوك على نطاقك العام.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php $first = false; endforeach; ?>
    </div>

    <div class="d-flex gap-2 mt-3 sticky-bottom bg-body py-3">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i> حفظ جميع الإعدادات</button>
        <span class="small text-muted align-self-center">تُسجَّل كل عملية حفظ في سجل التدقيق مع اسم المدير.</span>
    </div>
</form>
