<?php /** @var array $plan @var array $methods @var array $errors @var string $instructions */ ?>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-credit-card text-primary me-2"></i> بيانات الدفع</div>
            <div class="card-body">
                <?php if ($errors !== []): ?>
                    <div class="alert alert-danger py-2 small">
                        <?php foreach ($errors as $error): ?><div><i class="bi bi-exclamation-triangle me-1"></i><?= e($error) ?></div><?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label" for="method">طريقة الدفع</label>
                        <select class="form-select" id="method" name="method" required>
                            <?php foreach ($methods as $code => $label): ?>
                                <option value="<?= e($code === 'manual' ? 'bank_transfer' : $code) ?>">
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="reference_number">رقم العملية / الإيداع <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="reference_number" name="reference_number" required
                               value="<?= e((string) old('reference_number')) ?>" placeholder="مثال: 4455667788">
                        <div class="form-text">اكتب رقم الحوالة أو رقم عملية STC Pay أو الرقم المرجعي للإيصال.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="receipt">صورة إثبات الدفع</label>
                        <input type="file" class="form-control" id="receipt" name="receipt" accept="image/*,application/pdf">
                        <div class="form-text">صورة الإيصال (JPG/PNG/WEBP) أو ملف PDF — بحد أقصى <?= e(ar_digits((int) config('uploads.max_mb', 8))) ?> ميجابايت.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="payer_note">ملاحظات إضافية (اختياري)</label>
                        <textarea class="form-control" id="payer_note" name="payer_note" rows="2" maxlength="255"><?= e((string) old('payer_note')) ?></textarea>
                    </div>

                    <button class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-send-check me-1"></i> إرسال طلب التفعيل
                    </button>
                    <p class="text-muted small mt-2 mb-0 text-center">
                        يتم التفعيل بعد مراجعة الإدارة — وستصلك رسالة داخل المنصة وعلى تلجرام (إن كان مربوطاً).
                    </p>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-receipt text-primary me-2"></i> ملخص الباقة</div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">الباقة</span><strong><?= e($plan['name_ar']) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">المدة</span><span><?= e(ar_digits((int) $plan['duration_days'])) ?> يوماً</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-bold">الإجمالي</span>
                    <span class="pl-price" style="font-size:1.8rem;"><?= e(money((float) $plan['price_sar'])) ?></span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-bank text-primary me-2"></i> تعليمات التحويل</div>
            <div class="card-body small">
                <?php if ($instructions !== ''): ?>
                    <div style="white-space: pre-line;"><?= e($instructions) ?></div>
                <?php endif; ?>
                <ul class="list-unstyled mt-3 mb-0">
                    <?php if ((string) settings('bank_name', '') !== ''): ?>
                        <li class="d-flex justify-content-between border-bottom py-1">
                            <span class="text-muted">البنك</span><strong><?= e(settings('bank_name')) ?></strong></li>
                    <?php endif; ?>
                    <?php if ((string) settings('bank_account_name', '') !== ''): ?>
                        <li class="d-flex justify-content-between border-bottom py-1">
                            <span class="text-muted">اسم المستفيد</span><strong><?= e(settings('bank_account_name')) ?></strong></li>
                    <?php endif; ?>
                    <?php if ((string) settings('bank_iban', '') !== ''): ?>
                        <li class="d-flex justify-content-between border-bottom py-1 align-items-center">
                            <span class="text-muted">الآيبان</span>
                            <span class="d-flex align-items-center gap-1">
                                <strong class="pl-copy"><?= e(settings('bank_iban')) ?></strong>
                                <button class="btn btn-sm btn-outline-secondary py-0" type="button" data-copy="<?= e(settings('bank_iban')) ?>">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </span>
                        </li>
                    <?php endif; ?>
                    <?php if ((string) settings('stc_pay_number', '') !== ''): ?>
                        <li class="d-flex justify-content-between py-1">
                            <span class="text-muted">STC Pay</span><strong class="pl-copy"><?= e(settings('stc_pay_number')) ?></strong></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
