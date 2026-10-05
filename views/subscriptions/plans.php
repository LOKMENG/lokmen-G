<?php
/** @var array $plans @var array|null $current @var bool $isActive @var int $daysLeft @var array $methods @var array|null $pendingPayment */
?>
<?php if ($isActive): ?>
    <div class="alert alert-success d-flex flex-wrap gap-2 align-items-center">
        <i class="bi bi-patch-check-fill fs-5"></i>
        <div class="flex-grow-1">
            <strong>اشتراكك نشط.</strong>
            الباقة: <?= e($current['plan_name'] ?? '') ?> — ينتهي في <?= e(format_date($current['expires_at'] ?? null)) ?>
            (متبقٍ <?= e(ar_digits($daysLeft)) ?> يوماً).
        </div>
        <a href="<?= e(url('subscriptions/status')) ?>" class="btn btn-sm btn-outline-success">تفاصيل الاشتراك</a>
    </div>
<?php elseif ($pendingPayment !== null): ?>
    <div class="alert alert-warning d-flex flex-wrap gap-2 align-items-center">
        <i class="bi bi-hourglass-split fs-5"></i>
        <div class="flex-grow-1">
            <strong>لديك طلب اشتراك بانتظار التحقق من الدفع.</strong>
            رقم العملية: <?= e($pendingPayment['reference_number'] ?? '—') ?> —
            تاريخ الطلب: <?= e(format_date($pendingPayment['created_at'], true)) ?>
        </div>
        <a href="<?= e(url('subscriptions/status')) ?>" class="btn btn-sm btn-outline-warning">متابعة الطلب</a>
    </div>
<?php endif; ?>

<div class="row g-4">
    <?php foreach ($plans as $plan): ?>
        <?php $features = json_decode((string) ($plan['features'] ?? '[]'), true) ?: []; ?>
        <div class="col-lg-4">
            <div class="pl-price-card p-4 <?= (int) $plan['is_featured'] === 1 ? 'featured' : '' ?>">
                <h5 class="fw-bold mb-1"><?= e($plan['name_ar']) ?></h5>
                <p class="text-muted small mb-3"><?= e(str_limit((string) $plan['description'], 120)) ?></p>
                <div class="pl-price"><?= e(number_format((float) $plan['price_sar'], 0)) ?> <small>ر.س</small></div>
                <div class="text-muted small mb-3">لمدة <?= e(ar_digits((int) $plan['duration_days'])) ?> يوماً</div>

                <ul class="pl-check-list">
                    <?php foreach ($features as $feature): ?>
                        <li><?= e($feature) ?></li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($isActive && (int) ($current['plan_id'] ?? 0) === (int) $plan['id']): ?>
                    <button class="btn btn-success w-100 mt-3" disabled><i class="bi bi-check2-circle me-1"></i> باقتك الحالية</button>
                <?php else: ?>
                    <a href="<?= e(url('subscriptions/checkout', ['plan' => (int) $plan['id']])) ?>"
                       class="btn <?= (int) $plan['is_featured'] === 1 ? 'btn-primary' : 'btn-outline-primary' ?> w-100 mt-3">
                        <i class="bi bi-credit-card me-1"></i> <?= $isActive ? 'تجديد / ترقية' : 'اشترك الآن' ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card mt-4">
    <div class="card-header"><i class="bi bi-info-circle text-primary me-2"></i> طرق الدفع المتاحة</div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($methods as $code => $label): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="border rounded p-3 h-100">
                        <div class="fw-semibold"><i class="bi bi-check2-circle text-success me-1"></i> <?= e($label) ?></div>
                        <?php if ($code === 'manual'): ?>
                            <div class="small text-muted mt-1">حوّل المبلغ ثم ارفع رقم العملية وإثبات التحويل، ويتم التفعيل بعد المراجعة.</div>
                        <?php else: ?>
                            <div class="small text-muted mt-1">دفع إلكتروني فوري عبر بوابة <?= e($code) ?>.</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($methods) <= 1): ?>
            <div class="alert alert-info small mt-3 mb-0">
                <i class="bi bi-lightbulb me-1"></i>
                المنصة تعمل حالياً بـ<strong>الدفع اليدوي</strong>. لتفعيل مدى و Apple Pay و STC Pay، أضف مفاتيح Moyasar أو MyFatoorah
                في ملف <code>.env</code> (MOYASAR_ENABLED / MYFATOORAH_ENABLED) — النظام مهيأ بالكامل للربط.
            </div>
        <?php endif; ?>
    </div>
</div>
