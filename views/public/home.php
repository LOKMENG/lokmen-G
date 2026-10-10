<?php
/**
 * الصفحة الرئيسية العامة — تصميم «سيلادون» الكلايمورفي.
 *
 * كل الأرقام والشهادات تُجلب من قاعدة البيانات، وإن كانت قاعدة البيانات فارغة
 * تظهر الأقسام بحالة فارغة أنيقة بدل أن تنكسر الصفحة.
 *
 * @var array $stats @var array $tracks @var array $categories @var array $plans @var array $templates
 */
$settingPlans = $plans ?: [[
    'id' => 0, 'name_ar' => 'باقة الرخصة المهنية – كامل المنصة', 'price_sar' => settings('subscription_price', 100),
    'duration_days' => settings('subscription_days', 365), 'features' => json_encode([]), 'is_featured' => 1,
]];
$freeQuestions = (int) db()->value('SELECT COUNT(*) FROM `questions` WHERE active = 1 AND is_free = 1', [], 0);
$examTemplatesCount = count($templates);
$price = (float) settings('subscription_price', 100);
$days = (int) settings('subscription_days', 365);
?>

<!-- ============ الغلاف الترويجي ============ -->
<section class="pl-hero" id="top">
    <div class="cl-shell">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="cl-pill rv mb-3">
                    <b>جديد</b> أسئلة مجانية للتجربة قبل الاشتراك
                </span>
                <h1 class="rv d1">
                    استعد لاختبار <span class="text-gold">الرخصة المهنية</span><br>
                    بتدريب مركّز وواضح
                </h1>
                <p class="lead rv d2 mt-3">
                    بنك أسئلة للاختبار التخصصي (حاسب آلي) والاختبار التربوي العام، مع اختبارات تجريبية
                    بنفس نمط الاختبار وتحليل مستوى يوضح نقاط قوتك وضعفك قبل الاختبار الرسمي.
                </p>
                <div class="d-flex flex-wrap gap-2 mt-4 rv d3">
                    <a href="<?= e(url('auth/register')) ?>" class="cl-btn solid lg">
                        <i class="bi bi-rocket-takeoff"></i> ابدأ الآن
                    </a>
                    <a href="<?= e(url('questions/index')) ?>" class="cl-btn lg">
                        <i class="bi bi-journal-text"></i> جرّب الأسئلة المجانية
                    </a>
                </div>

                <div class="row g-3 mt-4 rv d4">
                    <div class="col-6 col-md-3">
                        <div class="cl-plate cl-plate-pad text-center h-100 cl-glossy">
                            <div class="cl-num tabular">
                                <span data-count-to="<?= (int) $stats['questions'] ?>" data-count-prefix="+" data-count-suffix=""
                                ><?= e(ar_digits(number_format((int) $stats['questions']))) ?></span>
                            </div>
                            <div class="cl-num-label">سؤال متاح</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="cl-plate cl-plate-pad text-center h-100 cl-glossy">
                            <div class="cl-num tabular">
                                <span data-count-to="<?= (int) $stats['students'] ?>"
                                ><?= e(ar_digits((int) $stats['students'])) ?></span>
                            </div>
                            <div class="cl-num-label">معلم مسجّل</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="cl-plate cl-plate-pad text-center h-100 cl-glossy">
                            <div class="cl-num tabular">
                                <span data-count-to="<?= (int) $stats['attempts'] ?>"
                                ><?= e(ar_digits((int) $stats['attempts'])) ?></span>
                            </div>
                            <div class="cl-num-label">اختبار مُنجز</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="cl-plate cl-plate-pad text-center h-100 cl-glossy">
                            <div class="cl-num tabular">
                                <span data-count-to="<?= $examTemplatesCount ?>"
                                ><?= e(ar_digits($examTemplatesCount)) ?></span>
                            </div>
                            <div class="cl-num-label">نموذج اختبار</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- تكوين طيني توضيحي: لوحة تحليل المستوى -->
            <div class="col-lg-6">
                <div class="pl-hero-card rv d2">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="mb-0 d-flex align-items-center gap-2">
                            <span class="cl-disc sm"><i class="bi bi-speedometer2"></i></span>
                            لوحة تحليل مستواك
                        </h5>
                        <span class="badge bg-light">نموذج توضيحي</span>
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
                            <div class="d-flex justify-content-between small fw-bold">
                                <span><?= e($name) ?></span>
                                <span class="text-<?= e($color) ?> tabular"><?= e(ar_digits($value)) ?>%</span>
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

<!-- ============ شريط ثقة ============ -->
<section class="cl-pad-s">
    <div class="cl-shell">
        <div class="d-flex flex-wrap justify-content-center gap-2 rv">
            <span class="cl-chip"><i class="bi bi-shield-check"></i> دفع آمن بمراجعة يدوية</span>
            <span class="cl-chip sky"><i class="bi bi-phone"></i> متوافقة مع الجوال</span>
            <span class="cl-chip coral"><i class="bi bi-telegram"></i> متابعة عبر تلجرام (اختيارية)</span>
            <span class="cl-chip"><i class="bi bi-graph-up-arrow"></i> تحليل مستوى تفصيلي</span>
            <span class="cl-chip sky"><i class="bi bi-journal-text"></i> <?= e(ar_digits($freeQuestions)) ?> سؤال مجاني</span>
        </div>
    </div>
</section>

<!-- ============ المسارات ============ -->
<section class="cl-pad" id="tracks">
    <div class="cl-shell">
        <div class="cl-center mb-5 rv">
            <span class="cl-eyebrow">مسارات التدريب</span>
            <h2 class="cl-h2 mt-3">ابنِ تدريبك على المسار الذي تحتاجه</h2>
            <p class="cl-lede">بنية مرنة تدعم أكثر من مسار وتُحدَّث باستمرار، وتصنيفاتها قابلة للتعديل من لوحة التحكم.</p>
        </div>
        <div class="row g-4">
            <?php if ($tracks === []): ?>
                <div class="col-12">
                    <div class="cl-plate cl-plate-pad text-center">
                        <span class="cl-disc lg mx-auto mb-3"><i class="bi bi-diagram-3"></i></span>
                        <h5>لم تُضف مسارات بعد</h5>
                        <p class="text-muted mb-0">تُدار المسارات والتصنيفات من لوحة الإدارة، وتظهر هنا فور إضافتها.</p>
                    </div>
                </div>
            <?php endif; ?>
            <?php foreach ($tracks as $track): ?>
                <div class="col-lg-6">
                    <div class="cl-plate cl-plate-pad h-100 cl-glossy rv">
                        <div class="d-flex align-items-start gap-3">
                            <span class="cl-disc jade"
                                  style="<?= e($track['color'] ? 'background: linear-gradient(180deg, ' . $track['color'] . 'bb 0%, ' . $track['color'] . ' 60%);' : '') ?>">
                                <i class="bi <?= e($track['icon'] ?: 'bi-book') ?>"></i>
                            </span>
                            <div class="flex-grow-1 min-w-0">
                                <h5 class="mb-1"><?= e($track['name_ar']) ?></h5>
                                <p class="text-muted small mb-3"><?= e(str_limit((string) $track['description'], 150)) ?></p>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach (array_slice($categories[(int) $track['id']] ?? [], 0, 10) as $category): ?>
                                        <span class="cl-chip small">
                                            <?= e($category['name_ar']) ?>
                                            <?php if ((int) $category['questions_count'] > 0): ?>
                                                <span class="text-muted"><?= e(ar_digits((int) $category['questions_count'])) ?></span>
                                            <?php endif; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ المزايا (شرائح تتوسّع) ============ -->
<section class="cl-pad cl-band-plate" id="features">
    <div class="cl-shell">
        <div class="cl-center mb-5 rv">
            <span class="cl-eyebrow">المزايا</span>
            <h2 class="cl-h2 mt-3">كل ما تحتاجه للاستعداد</h2>
            <p class="cl-lede">اضغط أي بطاقة لعرض تفاصيلها.</p>
        </div>
        <div class="cl-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));" data-slab-group>
            <?php
            $features = [
                ['bi-journal-check', 'اختبارات تجريبية كاملة', 'محاكاة للاختبار بنفس عدد الأسئلة ومؤقت تنازلي وترتيب عشوائي.', 'تُنشَأ النماذج من لوحة الإدارة بمعايير محددة: المجال، عدد الأسئلة، مدة الاختبار، ودرجة النجاح، ويمكن للطالب إعادة الاختبار أكثر من مرة لقياس التحسن.'],
                ['bi-collection', 'تدريب حسب المجال والمحور', 'اختر المجال الذي تريد تقويته ودرّب نفسك على أسئلته فقط.', 'بنك الأسئلة مُنظَّم في مسارات ثم مجالات ومحاور فرعية، ويمكنك البدء بتدريب حر أو تدريب مقيّد بمجال واحد أو بالسؤال الخطأ فقط.'],
                ['bi-lightning-charge', 'تدريب سريع بالإجابة الفورية', 'اعرض الإجابة الصحيحة والشرح مباشرة بعد كل سؤال.', 'نمط التدريب الحر مثالي للمراجعة السريعة: سؤال بعد سؤال مع شرح فوري، وتُحدَّث إحصاءاتك تلقائياً بعد كل إجابة.'],
                ['bi-bar-chart-line', 'تحليل مستوى تفصيلي', 'نسب دقة لكل مجال مع تحديد نقاط القوة والضعف والتوصيات.', 'بعد كل اختبار تحصل على نسبة دقة عامة، وتفصيل لكل مجال، وقائمة بأصعب الأسئلة التي أخفقت فيها، مع توصية بالمذاكرة.'],
                ['bi-arrow-repeat', 'مراجعة الأخطاء', 'راجع أسئلتك الخاطئة مع شرح الإجابة وأعد الاختبار.', 'كل محاولة تُحفظ مع إجاباتها، ويمكنك فتح أي محاولة سابقة ومراجعة الأسئلة الخطأ فقط مع الإجابة الصحيحة والشرح.'],
                ['bi-telegram', 'متابعة عبر تلجرام', 'حالة الاشتراك، إثبات الدفع، الإشعارات، والدعم الفني من تلجرام.', 'الربط اختياري تماماً — يمكنك استخدام المنصة كاملة دون تلجرام، وإن ربطت حسابك تصلك إشعارات التفعيل وقرب انتهاء الاشتراك وتذكيرات المذاكرة.'],
            ];
            foreach ($features as $index => [$icon, $title, $text, $details]):
            ?>
                <article class="cl-stave rv <?= $index === 0 ? 'open' : '' ?>" data-slab-item tabindex="0" role="button"
                         aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>">
                    <div class="cl-stave-head" data-slab-head>
                        <span class="cl-disc <?= ['jade', '', 'sky'][(int) $index % 3] ?>"><i class="bi <?= e($icon) ?>"></i></span>
                        <h5><?= e($title) ?></h5>
                        <span class="hint">التفاصيل <i class="bi bi-chevron-down"></i></span>
                    </div>
                    <div class="cl-stave-body">
                        <div class="cl-stave-body-inner">
                            <p class="mb-0"><?= e($text) ?> <?= e($details) ?></p>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ كيف تبدأ ============ -->
<section class="cl-pad" id="how">
    <div class="cl-shell">
        <div class="cl-center mb-5 rv">
            <span class="cl-eyebrow">ثلاث خطوات</span>
            <h2 class="cl-h2 mt-3">كيف تبدأ؟</h2>
            <p class="cl-lede">من التسجيل إلى أول اختبار تجريبي في أقل من خمس دقائق.</p>
        </div>
        <div class="row g-4">
            <?php
            $steps = [
                ['١', 'أنشئ حسابك', 'سجّل بالاسم ورقم الجوال والبريد الإلكتروني في أقل من دقيقة.', 'auth/register', 'bi-person-plus'],
                ['٢', 'فعّل اشتراكك', 'اختر الباقة وادفع ' . money($price) . ' ثم ارفع إثبات الدفع.', 'subscriptions/plans', 'bi-credit-card'],
                ['٣', 'ابدأ التدريب', 'ادخل لبنك الأسئلة، اختبر نفسك، وتابع تحليل مستواك.', 'exams/index', 'bi-play-circle'],
            ];
            foreach ($steps as [$number, $title, $text, $link, $icon]):
            ?>
                <div class="col-md-4">
                    <div class="pl-step rv">
                        <div class="num"><?= e($number) ?></div>
                        <span class="cl-disc sm mx-auto mb-3"><i class="bi <?= e($icon) ?>"></i></span>
                        <h5><?= e($title) ?></h5>
                        <p class="text-muted small mb-3"><?= e($text) ?></p>
                        <a href="<?= e(url($link)) ?>" class="cl-btn sm">المتابعة <i class="bi bi-arrow-left"></i></a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ الأسعار ============ -->
<section class="cl-pad cl-band-plate" id="pricing">
    <div class="cl-shell">
        <div class="cl-center mb-5 rv">
            <span class="cl-eyebrow">الأسعار</span>
            <h2 class="cl-h2 mt-3">باقات الاشتراك</h2>
            <p class="cl-lede">اشتراك لمرة واحدة — بدون تجديد تلقائي وبدون رسوم خفية.</p>
        </div>
        <div class="row g-4 justify-content-center">
            <?php foreach ($settingPlans as $plan): ?>
                <?php $planFeatures = json_decode((string) ($plan['features'] ?? '[]'), true) ?: []; ?>
                <div class="col-lg-4">
                    <div class="pl-price-card p-4 <?= (int) ($plan['is_featured'] ?? 0) === 1 ? 'featured' : '' ?> rv">
                        <?php if ((int) ($plan['is_featured'] ?? 0) === 1): ?>
                            <span class="cl-pill mb-3"><b>الأكثر اختياراً</b></span>
                        <?php endif; ?>
                        <h5 class="fw-bold mb-1"><?= e($plan['name_ar']) ?></h5>
                        <p class="text-muted small mb-3"><?= e(str_limit((string) ($plan['description'] ?? ''), 110)) ?></p>
                        <div class="pl-price tabular"><?= e(number_format((float) $plan['price_sar'], 0)) ?> <small>ر.س</small></div>
                        <div class="text-muted small mb-3">
                            لمدة <?= e(ar_digits((int) $plan['duration_days'])) ?> يوماً
                            <?php if ((int) $plan['duration_days'] >= 365): ?>(سنة كاملة)<?php endif; ?>
                        </div>
                        <ul class="pl-check-list">
                            <?php if ($planFeatures === []): ?>
                                <li>وصول كامل لبنك الأسئلة والاختبارات التجريبية</li>
                                <li>تحليل مستوى تفصيلي بعد كل اختبار</li>
                                <li>تحديثات مستمرة للأسئلة والمحتوى</li>
                            <?php endif; ?>
                            <?php foreach ($planFeatures as $feature): ?>
                                <li><?= e($feature) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="<?= e(url('subscriptions/checkout', ['plan' => (int) $plan['id']])) ?>"
                           class="cl-btn <?= (int) ($plan['is_featured'] ?? 0) === 1 ? 'solid' : '' ?> wide mt-3">
                            <i class="bi bi-credit-card"></i> اشترك الآن
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="text-center text-muted small mt-4 mb-0 rv">
            <i class="bi bi-shield-check me-1"></i>
            طرق الدفع: تحويل بنكي / STC Pay (تفعيل يدوي خلال ساعات) — ويدعم النظام بوابات الدفع السعودية عند تفعيلها.
        </p>
    </div>
</section>

<!-- ============ آراء المتدربين ============ -->
<?php if (($testimonials ?? []) !== []): ?>
<section class="cl-pad" id="testimonials">
    <div class="cl-shell">
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4 rv">
            <div>
                <span class="cl-eyebrow">آراء المتدربين</span>
                <h2 class="cl-h2 mt-3 mb-1">ماذا يقول من جرّب المنصة؟</h2>
                <p class="cl-lede mb-0">جميع الشهادات منشورة بموافقة أصحابها الصريحة.</p>
            </div>
            <div class="cl-rail-nav">
                <span class="cl-rail-count" id="railCount"><?= e(ar_digits(count($testimonials))) ?> شهادة</span>
                <button class="cl-arrow" type="button" data-rail-prev aria-label="السابق"><i class="bi bi-arrow-right"></i></button>
                <button class="cl-arrow" type="button" data-rail-next aria-label="التالي"><i class="bi bi-arrow-left"></i></button>
            </div>
        </div>
        <div class="cl-rail rv" data-rail>
            <div class="cl-rail-track" data-rail-track tabindex="0" aria-label="شهادات المتدربين — اسحب أفقياً">
                <?php foreach ($testimonials as $testimonial): ?>
                    <article class="pl-quote">
                        <div class="stars">
                            <?php for ($star = 1; $star <= 5; $star++): ?>
                                <i class="bi <?= $star <= (int) $testimonial['rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="mb-3" style="white-space: pre-line;"><?= e(str_limit((string) $testimonial['body'], 400)) ?></p>
                        <div class="d-flex align-items-center gap-2">
                            <span class="pl-avatar-sm"><?= e(mb_substr((string) $testimonial['name'], 0, 1)) ?></span>
                            <div class="small">
                                <div class="fw-bold"><?= e((string) $testimonial['name']) ?></div>
                                <div class="text-muted">
                                    <?= e(trim((string) $testimonial['role'] . (($testimonial['city'] ?? '') !== '' ? ' — ' . $testimonial['city'] : ''), ' —')) ?>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ الأسئلة الشائعة ============ -->
<section class="cl-pad cl-band-plate" id="faq">
    <div class="cl-shell">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="cl-center mb-5 rv">
                    <span class="cl-eyebrow">الأسئلة الشائعة</span>
                    <h2 class="cl-h2 mt-3">ما يسأل عنه المعلمون عادة</h2>
                </div>
                <div class="accordion rv" id="faqAccordion">
                    <?php
                    $faqs = [
                        ['هل الأسئلة مطابقة تماماً لأسئلة الاختبار الرسمي؟', 'لا. الأسئلة تدريبية تهدف إلى تقوية المهارات والمفاهيم في نفس مجالات الاختبار. الاختبار الرسمي يُعدّ من الجهة المختصة، والتصنيفات هنا تنظيمية للتدريب فقط.'],
                        ['ما مدة الاشتراك؟', 'مدة الاشتراك ' . ar_digits($days) . ' يوماً من تاريخ التفعيل، ويمكن للإدارة تمديده عند الحاجة.'],
                        ['كيف يتم تفعيل الاشتراك بعد الدفع؟', 'بعد التحويل ترفع رقم العملية وصورة الإثبات من صفحة الاشتراك أو من بوت تلجرام، وتُراجع الإدارة الطلب ويُفعّل الحساب عادة خلال ساعات.'],
                        ['هل يمكنني استخدام المنصة من الجوال؟', 'نعم، المنصة متوافقة تماماً مع الجوال والتابلت، ومتوفرة بالوضعين الليلي والنهاري.'],
                        ['هل أستطيع الربط مع تلجرام؟', 'نعم، من صفحة «ربط تلجرام» داخل حسابك، وستصلك إشعارات التفعيل وقرب انتهاء الاشتراك. والربط اختياري تماماً.'],
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
                                <div class="accordion-body"><?= e($answer) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ دعوة للبدء ============ -->
<section class="cl-pad" id="start">
    <div class="cl-shell">
        <div class="cl-plate cl-plate-pad text-center rv" style="padding: clamp(28px, 4vw, 52px);">
            <span class="cl-disc lg mx-auto mb-3"><i class="bi bi-mortarboard-fill"></i></span>
            <h2 class="cl-h2 mb-2">جاهز للبدء؟</h2>
            <p class="cl-lede mx-auto mb-4" style="max-width: 560px;">
                أنشئ حسابك الآن وتصفّح الأسئلة المجانية فوراً، ثم فعّل اشتراكك
                (<?= e(money($price)) ?>) لفتح المنصة كاملة لمدة <?= e(ar_digits($days)) ?> يوماً.
            </p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <a href="<?= e(url('auth/register')) ?>" class="cl-btn solid lg">
                    <i class="bi bi-person-plus"></i> إنشاء حساب جديد
                </a>
                <a href="<?= e(url('auth/login')) ?>" class="cl-btn lg">
                    <i class="bi bi-box-arrow-in-left"></i> لدي حساب
                </a>
            </div>
        </div>
    </div>
</section>
