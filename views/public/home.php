<?php
/** @var array $stats @var array $tracks @var array $categories @var array $plans @var array $templates */
$settingPlans = $plans ?: [[
    'id' => 0, 'name_ar' => 'باقة الرخصة المهنية – كامل المنصة', 'price_sar' => settings('subscription_price', 100),
    'duration_days' => settings('subscription_days', 365), 'features' => json_encode([]), 'is_featured' => 1,
]];
?>
<section class="pl-hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="badge bg-white bg-opacity-10 text-white mb-3 px-3 py-2">
                    <i class="bi bi-patch-check-fill text-gold me-1"></i> منصة سعودية متخصصة للرخصة المهنية
                </span>
                <h1>استعد لاختبار <span class="text-gold">الرخصة المهنية</span> بتدريب ذكي ومركّز</h1>
                <p class="lead mt-3">
                    بنك أسئلة للاختبار التخصصي (حاسب آلي) والاختبار التربوي العام، مع اختبارات تجريبية بنفس نمط الاختبار
                    وتحليل مستوى يوضح لك نقاط قوتك وضعفك قبل الاختبار الرسمي.
                </p>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a href="<?= e(url('auth/register')) ?>" class="btn btn-gold btn-lg">
                        <i class="bi bi-rocket-takeoff me-2"></i> ابدأ الآن
                    </a>
                    <a href="#pricing" class="btn btn-outline-light btn-lg">
                        <i class="bi bi-tags me-2"></i> الباقات (<?= e(money((float) settings('subscription_price', 100))) ?>)
                    </a>
                </div>
                <div class="row g-3 mt-4">
                    <div class="col-6 col-md-3">
                        <div class="pl-stat-pill">
                            <div class="value"><?= e(ar_digits(number_format($stats['questions']))) ?></div>
                            <div class="label">سؤال متاح</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="pl-stat-pill">
                            <div class="value"><?= e(ar_digits($stats['students'])) ?></div>
                            <div class="label">معلم مسجّل</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="pl-stat-pill">
                            <div class="value"><?= e(ar_digits($stats['attempts'])) ?></div>
                            <div class="label">اختبار مُنجز</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="pl-stat-pill">
                            <div class="value"><?= e(ar_digits(count($templates))) ?></div>
                            <div class="label">نموذج اختبار</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="pl-hero-card">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="mb-0"><i class="bi bi-speedometer2 text-success me-2"></i> لوحة تحليل مستواك</h5>
                        <span class="badge bg-success-subtle text-success-emphasis">نموذج توضيحي</span>
                    </div>
                    <?php
                    $demo = [
                        ['البرمجة', 85, 'success'],
                        ['قواعد البيانات', 92, 'success'],
                        ['الشبكات', 61, 'warning'],
                        ['الأمن السيبراني', 74, 'warning'],
                        ['الذكاء الاصطناعي', 88, 'success'],
                        ['هياكل البيانات', 55, 'danger'],
                    ];
                    foreach ($demo as [$name, $value, $color]):
                    ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small fw-semibold">
                                <span><?= e($name) ?></span>
                                <span class="text-<?= e($color) ?>"><?= e(ar_digits($value)) ?>%</span>
                            </div>
                            <div class="progress pl-progress-thin mt-1">
                                <div class="progress-bar bg-<?= e($color) ?>" style="width: <?= (int) $value ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="alert alert-warning mb-0 small">
                        <i class="bi bi-lightbulb me-1"></i>
                        ننصحك بمراجعة قسم <strong>هياكل البيانات</strong> والشبكات قبل إعادة الاختبار.
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5" id="tracks">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="fw-bold">مسارات التدريب</h2>
            <p class="text-muted">بنية مرنة تدعم أكثر من مسار وتُحدَّث باستمرار، وتصنيفاتها قابلة للتعديل من لوحة التحكم.</p>
        </div>
        <div class="row g-4">
            <?php foreach ($tracks as $track): ?>
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-3">
                                <div class="pl-stat-icon" style="background: <?= e($track['color'] ?: '#0b6b3a') ?>1a; color: <?= e($track['color'] ?: '#0b6b3a') ?>">
                                    <i class="bi <?= e($track['icon'] ?: 'bi-book') ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1"><?= e($track['name_ar']) ?></h5>
                                    <p class="text-muted small mb-3"><?= e(str_limit((string) $track['description'], 150)) ?></p>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach (array_slice($categories[(int) $track['id']] ?? [], 0, 10) as $category): ?>
                                            <span class="badge bg-light text-dark border">
                                                <?= e($category['name_ar']) ?>
                                                <?php if ((int) $category['questions_count'] > 0): ?>
                                                    <span class="text-muted">(<?= e(ar_digits((int) $category['questions_count'])) ?>)</span>
                                                <?php endif; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="py-5 bg-white" id="features" style="background: var(--pl-surface) !important;">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="fw-bold">كل ما تحتاجه للاستعداد</h2>
            <p class="text-muted">أدوات تدريب عملية مبنية على نمط اختبار الرخصة المهنية.</p>
        </div>
        <div class="row g-4">
            <?php
            $features = [
                ['bi-journal-check', 'اختبارات تجريبية كاملة', 'محاكاة للاختبار بنفس عدد الأسئلة ومؤقت تنازلي وترتيب عشوائي.'],
                ['bi-collection', 'تدريب حسب المجال والمحور', 'اختر المجال الذي تريد تقويته ودرّب نفسك على أسئلته فقط.'],
                ['bi-lightning-charge', 'تدريب سريع بالإجابة الفورية', 'اعرض الإجابة الصحيحة والشرح مباشرة بعد كل سؤال.'],
                ['bi-bar-chart-line', 'تحليل مستوى تفصيلي', 'نسب دقة لكل مجال مع تحديد نقاط القوة والضعف والتوصيات.'],
                ['bi-arrow-repeat', 'مراجعة الأخطاء', 'راجع أسئلتك الخاطئة مع شرح الإجابة وأعد الاختبار.'],
                ['bi-telegram', 'متابعة عبر تلجرام', 'حالة الاشتراك، إثبات الدفع، الإشعارات، والدعم الفني من تلجرام.'],
            ];
            foreach ($features as [$icon, $title, $text]):
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="pl-feature">
                        <div class="pl-feature-icon"><i class="bi <?= e($icon) ?>"></i></div>
                        <h5 class="mb-2"><?= e($title) ?></h5>
                        <p class="text-muted small mb-0"><?= e($text) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="py-5" id="how">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="fw-bold">كيف تبدأ؟ ثلاث خطوات</h2>
        </div>
        <div class="row g-4 text-center">
            <?php
            $steps = [
                ['١', 'أنشئ حسابك', 'سجّل بالاسم ورقم الجوال والبريد الإلكتروني في أقل من دقيقة.', 'auth/register'],
                ['٢', 'فعّل اشتراكك', 'اختر الباقة وادفع ' . money((float) settings('subscription_price', 100)) . ' ثم ارفع إثبات الدفع.', 'subscriptions/plans'],
                ['٣', 'ابدأ التدريب', 'ادخل لبنك الأسئلة، اختبر نفسك، وتابع تحليل مستواك.', 'exams/index'],
            ];
            foreach ($steps as [$number, $title, $text, $link]):
            ?>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="pl-stat-icon is-gold mx-auto mb-3" style="font-weight: 800; font-size: 1.3rem;"><?= e($number) ?></div>
                            <h5><?= e($title) ?></h5>
                            <p class="text-muted small"><?= e($text) ?></p>
                            <a href="<?= e(url($link)) ?>" class="btn btn-sm btn-outline-primary">المتابعة <i class="bi bi-arrow-left ms-1"></i></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="py-5" id="pricing" style="background: var(--pl-primary-light);">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="fw-bold">باقات الاشتراك</h2>
            <p class="text-muted">اشتراك لمرة واحدة — بدون تجديد تلقائي.</p>
        </div>
        <div class="row g-4 justify-content-center">
            <?php foreach ($settingPlans as $plan): ?>
                <?php $features = json_decode((string) ($plan['features'] ?? '[]'), true) ?: []; ?>
                <div class="col-lg-4">
                    <div class="pl-price-card p-4 <?= (int) ($plan['is_featured'] ?? 0) === 1 ? 'featured' : '' ?>">
                        <h5 class="fw-bold mb-1"><?= e($plan['name_ar']) ?></h5>
                        <p class="text-muted small mb-3"><?= e(str_limit((string) ($plan['description'] ?? ''), 110)) ?></p>
                        <div class="pl-price"><?= e(number_format((float) $plan['price_sar'], 0)) ?> <small>ر.س</small></div>
                        <div class="text-muted small mb-3">
                            لمدة <?= e(ar_digits((int) $plan['duration_days'])) ?> يوماً
                            <?php if ((int) $plan['duration_days'] >= 365): ?>(سنة كاملة)<?php endif; ?>
                        </div>
                        <ul class="pl-check-list">
                            <?php foreach ($features as $feature): ?>
                                <li><?= e($feature) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="<?= e(url('subscriptions/checkout', ['plan' => (int) $plan['id']])) ?>" class="btn <?= (int) ($plan['is_featured'] ?? 0) === 1 ? 'btn-primary' : 'btn-outline-primary' ?> w-100 mt-3">
                            <i class="bi bi-credit-card me-1"></i> اشترك الآن
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="text-center text-muted small mt-4 mb-0">
            <i class="bi bi-shield-check me-1"></i>
            طرق الدفع: تحويل بنكي / STC Pay (تفعيل يدوي خلال ساعات) — وقريباً مدى وApple Pay عبر بوابات الدفع السعودية.
        </p>
    </div>
</section>

<?php if (($testimonials ?? []) !== []): ?>
<section class="py-5" id="testimonials" style="background: var(--pl-surface);">
    <div class="container">
        <div class="text-center mb-4">
            <span class="badge bg-primary-subtle text-primary-emphasis mb-2">آراء المتدربين</span>
            <h2 class="h3 fw-bold mb-1">ماذا يقول من جرّب المنصة؟</h2>
            <p class="text-muted mb-0">جميع الشهادات منشورة بموافقة أصحابها الصريحة.</p>
        </div>
        <div class="row g-3">
            <?php foreach ($testimonials as $testimonial): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-warning mb-2">
                                <?php for ($star = 1; $star <= 5; $star++): ?>
                                    <i class="bi <?= $star <= (int) $testimonial['rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <p class="mb-3" style="white-space: pre-line;"><?= e(str_limit((string) $testimonial['body'], 400)) ?></p>
                            <div class="d-flex align-items-center gap-2">
                                <span class="pl-avatar-sm"><?= e(mb_substr((string) $testimonial['name'], 0, 1)) ?></span>
                                <div class="small">
                                    <div class="fw-semibold"><?= e((string) $testimonial['name']) ?></div>
                                    <div class="text-muted">
                                        <?= e(trim((string) $testimonial['role'] . (($testimonial['city'] ?? '') !== '' ? ' — ' . $testimonial['city'] : ''), ' —')) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="py-5" id="faq">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <h2 class="fw-bold text-center mb-4">الأسئلة الشائعة</h2>
                <div class="accordion" id="faqAccordion">
                    <?php
                    $faqs = [
                        ['هل الأسئلة مطابقة تماماً لأسئلة الاختبار الرسمي؟', 'لا. الأسئلة تدريبية تهدف إلى تقوية المهارات والمفاهيم في نفس مجالات الاختبار. الاختبار الرسمي يُعدّ من الجهة المختصة، والتصنيفات هنا تنظيمية للتدريب فقط.'],
                        ['ما مدة الاشتراك؟', 'مدة الاشتراك ' . ar_digits((int) settings('subscription_days', 365)) . ' يوماً من تاريخ التفعيل، ويمكن للإدارة تمديده عند الحاجة.'],
                        ['كيف يتم تفعيل الاشتراك بعد الدفع؟', 'بعد التحويل ترفع رقم العملية وصورة الإثبات من صفحة الاشتراك أو من بوت تلجرام، وتُراجع الإدارة الطلب ويُفعّل الحساب عادة خلال ساعات.'],
                        ['هل يمكنني استخدام المنصة من الجوال؟', 'نعم، المنصة متوافقة تماماً مع الجوال والتابلت، ومتوفرة بالوضعين الليلي والنهاري.'],
                        ['هل أستطيع الربط مع تلجرام؟', 'نعم، من صفحة «ربط تلجرام» داخل حسابك، وستصلك إشعارات التفعيل وقرب انتهاء الاشتراك على تلجرام.'],
                    ];
                    foreach ($faqs as $index => [$question, $answer]):
                    ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button <?= $index === 0 ? '' : 'collapsed' ?>" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#faq<?= (int) $index ?>">
                                    <?= e($question) ?>
                                </button>
                            </h2>
                            <div id="faq<?= (int) $index ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted"><?= e($answer) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="card mt-4 border-0" style="background: var(--pl-primary);">
                    <div class="card-body text-center text-white p-4">
                        <h4 class="fw-bold mb-2">جاهز للبدء؟</h4>
                        <p class="mb-3 opacity-75">أنشئ حسابك الآن وابدأ التدريب فوراً بعد تفعيل الاشتراك.</p>
                        <a href="<?= e(url('auth/register')) ?>" class="btn btn-gold btn-lg">
                            <i class="bi bi-person-plus me-2"></i> إنشاء حساب جديد
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
