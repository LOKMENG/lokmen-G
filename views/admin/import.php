<?php
/** @var array $batches @var array $sources @var array $tracks @var bool $pdfReady @var array $stats */
$statusMap = [
    'pending'   => ['secondary', 'بانتظار التحليل'],
    'previewed' => ['warning', 'بانتظار المراجعة'],
    'imported'  => ['success', 'تم الإدخال'],
    'failed'    => ['danger', 'فشل'],
    'cancelled' => ['secondary', 'ملغاة'],
];
?>
<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-cloud-upload text-primary me-2"></i> الخطوة 1 — ارفع ملفك أو الصق النص</div>
            <div class="card-body">
                <?php if (!$pdfReady): ?>
                    <div class="alert alert-danger small">
                        <i class="bi bi-exclamation-octagon me-1"></i>
                        إضافة zlib غير مفعّلة في PHP، لذلك لا يمكن استخراج نص PDF. فعّل <code>extension=zlib</code> من php.ini،
                        أو استخدم ملفات TXT/CSV/JSON أو الصق النص يدوياً.
                    </div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data" class="mb-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label" for="file">ملف الأسئلة</label>
                        <input type="file" class="form-control" id="file" name="file" accept=".pdf,.txt,.csv,.json">
                        <div class="form-text">
                            PDF (نصي - غير ممسوح ضوئياً) • TXT • CSV • JSON — بحد أقصى <?= e(ar_digits(25)) ?> ميجابايت.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="source_id">مصدر الأسئلة (لتوثيق حق الاستخدام)</label>
                        <select class="form-select" id="source_id" name="source_id">
                            <option value="">— بدون مصدر مسجّل —</option>
                            <?php foreach ($sources as $source): ?>
                                <option value="<?= (int) $source['id'] ?>" <?= empty($source['license_note']) ? 'class="text-warning"' : '' ?>>
                                    <?= e($source['name']) ?><?= empty($source['license_note']) ? ' (حق الاستخدام غير موثّق)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            أضف المصادر وأثبت حق الاستخدام من <a href="<?= e(url('admin/taxonomy', ['tab' => 'sources'])) ?>">صفحة المصادر</a>.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="pasted_text">أو الصق النص هنا (إن كان الملف ممسوحاً ضوئياً)</label>
                        <textarea class="form-control" id="pasted_text" name="pasted_text" rows="6" placeholder="س1: نص السؤال&#10;أ) ...&#10;ب) ...&#10;الإجابة: ب"></textarea>
                        <div class="form-text">إن مُلئ هذا الحقل وتم رفع ملف في نفس الوقت، سيُستخدم الملف.</div>
                    </div>
                    <button class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i> تحليل الملف وعرض شاشة المراجعة
                    </button>
                </form>

                <div class="alert alert-light border small mb-0">
                    <div class="fw-bold mb-1"><i class="bi bi-shield-check text-success me-1"></i> كيف يعمل الاستيراد؟</div>
                    <ol class="mb-0 ps-3">
                        <li>يُستخرج النص (استخراج PDF مدمج بالكامل داخل النظام).</li>
                        <li>يُحلَّل النص إلى أسئلة واختيارات في جدول مراجعة — <strong>بدون إدخال فعلي</strong>.</li>
                        <li>يُعلَّم أي صف ناقص أو مكرر أو إجابته غير واضحة — <strong>ولا يُخمَّن أي جواب أبداً</strong>.</li>
                        <li>تراجع أنت كل صف وتعدّله ثم تعتمد الصفوف، وبعدها فقط تُدخل إلى بنك الأسئلة.</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-info-circle text-primary me-2"></i> صيغ الملفات المدعومة</div>
            <div class="card-body small">
                <div class="fw-bold mb-1">TXT — الأسئلة بالشكل التالي:</div>
                <pre class="bg-body-secondary p-2 rounded mb-3" dir="rtl">س1: نص السؤال؟
أ) الاختيار الأول
ب) الاختيار الثاني
ج) الاختيار الثالث
د) الاختيار الرابع
الإجابة: ب
الشرح: سبب صحة الإجابة (اختياري)</pre>
                <div class="fw-bold mb-1">CSV — رؤوس أعمدة (بالإنجليزية أو العربية):</div>
                <pre class="bg-body-secondary p-2 rounded mb-3" dir="ltr">question,option_a,option_b,option_c,option_d,correct_answer,explanation</pre>
                <div class="fw-bold mb-1">JSON:</div>
                <pre class="bg-body-secondary p-2 rounded mb-0" dir="ltr">[{"question":"...","options":["أ1","أ2","أ3","أ4"],"correct":"أ","explanation":"..."}]</pre>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-exclamation-triangle text-warning me-2"></i> تنبيهات مهمة</div>
            <div class="card-body small">
                <ul class="mb-0 ps-3">
                    <li>لا ترفع محتوى محمياً بحقوق ملكية دون تصريح — سجّل «إقرار حق الاستخدام» في المصدر.</li>
                    <li>ملفات PDF الممسوحة ضوئياً (صور) لا تحتوي نصاً: استخرج النص ببرنامج OCR خارجي ثم ارفعه كـ TXT/CSV.</li>
                    <li>التصنيفات هنا تعريفية من إعداد المنصة، وليست التصنيف الرسمي لهيئة تقويم التعليم والتدريب.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history text-primary me-2"></i> دفعات الاستيراد</span>
        <span class="small text-muted">إجمالي الأسئلة في البنك: <?= e(ar_digits($stats['total'])) ?></span>
    </div>
    <div class="card-body p-0">
        <?php if ($batches === []): ?>
            <div class="pl-empty"><i class="bi bi-inbox"></i> لا توجد دفعات استيراد بعد.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>#</th><th>الملف</th><th>النوع</th><th>إجمالي</th><th>معلّق</th><th>معتمد</th><th>مُدخل</th><th>الحالة</th><th>التاريخ</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($batches as $batch): ?>
                            <?php [$badge, $label] = $statusMap[$batch['status']] ?? ['secondary', $batch['status']]; ?>
                            <tr>
                                <td class="small text-muted"><?= e(ar_digits((int) $batch['id'])) ?></td>
                                <td class="small">
                                    <div class="fw-semibold"><?= e(str_limit((string) $batch['file_name'], 45)) ?></div>
                                    <div class="text-muted"><?= e((string) ($batch['importer_name'] ?? '—')) ?></div>
                                </td>
                                <td class="small text-uppercase pl-copy"><?= e((string) $batch['file_type']) ?></td>
                                <td class="small"><?= e(ar_digits((int) $batch['total_rows'])) ?></td>
                                <td class="small"><?= e(ar_digits((int) $batch['pending_count'])) ?></td>
                                <td class="small text-success"><?= e(ar_digits((int) $batch['approved_count'])) ?></td>
                                <td class="small text-primary"><?= e(ar_digits((int) $batch['imported_count'])) ?></td>
                                <td><span class="badge bg-<?= e($badge) ?>-subtle text-<?= e($badge) ?>-emphasis"><?= e($label) ?></span></td>
                                <td class="small text-muted"><?= e(format_date((string) $batch['created_at'], true)) ?></td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= e(url('admin/import', ['batch' => (int) $batch['id']])) ?>" class="btn btn-sm btn-primary"><i class="bi bi-eye me-1"></i> مراجعة</a>
                                    <form method="post" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_batch">
                                        <input type="hidden" name="batch_id" value="<?= (int) $batch['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" data-confirm="حذف الدفعة وكل صفوفها؟ الأسئلة المُدخلة فعلاً لا تُحذف."><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
