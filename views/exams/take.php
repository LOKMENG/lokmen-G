<?php
/**
 * شاشة الاختبار - صفحة مستقلة (بدون قائمة جانبية) لتركيز الطالب.
 * @var array $attempt @var array $questions @var array $progress @var int $remaining @var array $feedback
 * @var bool $isPractice @var int $startIndex @var string $csrfToken
 */
$total = count($questions);
?>
<!doctype html>
<html lang="ar" dir="rtl" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($attempt['title'] ?? 'اختبار') ?> | <?= e(settings('site_name', config('app.name'))) ?></title>
    <script>
        (function () {
            var theme = localStorage.getItem('pl-theme');
            if (theme) { document.documentElement.setAttribute('data-bs-theme', theme); }
            // علامة تفعيل الجافاسكربت: تُخفي عناصر الظهور التدريجي قبل أول رسم فقط عند توفّر السكربت،
            // حتى تبقى كل المحتوى ظاهراً إذا كان السكربت معطّلاً في المتصفح.
            document.documentElement.classList.add('cl-js');
        })();
    </script>
    <link rel="stylesheet" href="<?= e(asset('assets/css/bootstrap.rtl.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/celadon.css')) ?>">
    <link rel="icon" href="<?= e(asset('assets/img/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body>
<div class="container py-3 pl-exam-shell">

    <!-- شريط المعلومات -->
    <div class="pl-exam-bar mb-3">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary-subtle text-primary-emphasis">
                <?= e($isPractice ? 'نمط التدريب' : 'اختبار تجريبي') ?>
            </span>
            <span class="small text-muted d-none d-md-inline"><?= e(str_limit((string) $attempt['title'], 40)) ?></span>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="small text-muted">السؤال <strong data-question-counter><?= e(ar_digits($startIndex + 1)) ?> / <?= e(ar_digits($total)) ?></strong></span>
            <?php if ((int) $attempt['duration_minutes'] > 0): ?>
                <span class="pl-timer" data-timer>
                    <i class="bi bi-stopwatch"></i>
                    <span data-timer-value><?= e(sprintf('%02d:%02d', intdiv($remaining, 60), $remaining % 60)) ?></span>
                </span>
            <?php endif; ?>
            <span class="small" data-save-indicator></span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-outline-danger btn-sm" type="button" data-finish-exam>
                <i class="bi bi-flag-fill me-1"></i> إنهاء الاختبار
            </button>
        </div>
    </div>

    <!-- التقدم -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex justify-content-between small mb-1">
                <span><i class="bi bi-check2-circle text-success me-1"></i> مُجاب: <strong data-answered-count><?= e(ar_digits($progress['answered'])) ?></strong></span>
                <span><i class="bi bi-dash-circle text-muted me-1"></i> متبقٍ: <strong data-remaining-count><?= e(ar_digits($progress['unanswered'])) ?></strong></span>
                <span><i class="bi bi-flag text-warning me-1"></i> مُعلَّم للمراجعة: <strong data-flagged-count><?= e(ar_digits($progress['flagged'])) ?></strong></span>
            </div>
            <div class="progress pl-progress-thin">
                <div class="progress-bar bg-primary" data-progress-bar style="width: <?= (float) $progress['percentage'] ?>%"></div>
            </div>
        </div>
    </div>

    <form method="post" action="<?= e(url('exams/submit')) ?>" id="pl-exam-form">
        <?= csrf_field() ?>
        <input type="hidden" name="attempt_id" value="<?= (int) $attempt['id'] ?>">
    </form>

    <?php if ($total === 0): ?>
        <div class="card"><div class="card-body pl-empty">
            <i class="bi bi-journal-x"></i> لا توجد أسئلة في هذا الاختبار.
            <div class="mt-3"><a href="<?= e(url('exams/index')) ?>" class="btn btn-primary btn-sm">العودة للاختبارات</a></div>
        </div></div>
    <?php else: ?>
        <?php foreach ($questions as $index => $question): ?>
            <?php
            $feedbackRow = $feedback[$question['id']] ?? null;
            ?>
            <div class="pl-question-card mb-3 <?= $index === $startIndex ? '' : 'd-none' ?>" data-question-index="<?= (int) $index ?>" data-question-id="<?= (int) $question['id'] ?>">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                    <div class="d-flex flex-wrap gap-2 small">
                        <span class="badge bg-light text-dark border"><?= e($question['category']) ?></span>
                        <span class="badge bg-<?= e(difficulty_class($question['difficulty'])) ?>-subtle text-<?= e(difficulty_class($question['difficulty'])) ?>-emphasis">
                            <?= e(difficulty_ar($question['difficulty'])) ?>
                        </span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary <?= $question['is_flagged'] ? 'active' : '' ?>"
                            data-flag-question="<?= (int) $question['id'] ?>">
                        <?php if ($question['is_flagged']): ?>
                            <i class="bi bi-flag-fill text-warning"></i> مُعلَّم للمراجعة
                        <?php else: ?>
                            <i class="bi bi-flag"></i> علّم للمراجعة
                        <?php endif; ?>
                    </button>
                </div>

                <div class="d-flex gap-2 mb-2">
                    <span class="badge bg-primary">السؤال <?= e(ar_digits($index + 1)) ?> من <?= e(ar_digits($total)) ?></span>
                </div>

                <div class="pl-question-text"><?= nl2br(e($question['text'])) ?></div>

                <div class="pl-options">
                    <?php $letterIndex = 0; ?>
                    <?php foreach ($question['options'] as $letter => $text): ?>
                        <?php $letterIndex++; ?>
                        <?php
                        $optionClass = '';
                        if ($feedbackRow !== null) {
                            if ($letter === $feedbackRow['correct_answer']) {
                                $optionClass = 'correct';
                            } elseif ($question['selected'] === $letter) {
                                $optionClass = 'wrong';
                            }
                        } elseif ($question['selected'] === $letter) {
                            $optionClass = 'selected';
                        }
                        ?>
                        <label class="pl-option <?= e($optionClass) ?>" for="q<?= (int) $question['id'] ?>_<?= e($letter) ?>">
                            <input type="radio" id="q<?= (int) $question['id'] ?>_<?= e($letter) ?>" name="answer_<?= (int) $question['id'] ?>"
                                   value="<?= e($letter) ?>" data-answer data-question-id="<?= (int) $question['id'] ?>"
                                   <?= $question['selected'] === $letter ? 'checked' : '' ?>
                                   <?= $feedbackRow !== null ? 'disabled' : '' ?>>
                            <span class="pl-option-key"><?= e(ar_digits($letterIndex)) ?></span>
                            <span class="flex-grow-1"><?= nl2br(e($text)) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <?php if ($feedbackRow !== null): ?>
                    <div class="alert alert-<?= $feedbackRow['correct'] ? 'success' : 'danger' ?> mt-3 mb-0" data-feedback-for="<?= (int) $question['id'] ?>">
                        <div class="fw-bold mb-1">
                            <?php if ($feedbackRow['correct']): ?>
                                <i class="bi bi-check-circle-fill me-1"></i> إجابة صحيحة
                            <?php else: ?>
                                <i class="bi bi-x-circle-fill me-1"></i> إجابة خاطئة — الصحيحة: <?= e(answer_letter_ar($feedbackRow['correct_answer'])) ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($feedbackRow['explanation'] !== ''): ?>
                            <div class="small"><strong>الشرح:</strong> <?= nl2br(e($feedbackRow['explanation'])) ?></div>
                        <?php endif; ?>
                        <div class="small text-muted mt-2">
                            <a href="<?= e(url('questions/report', ['question' => (int) $question['id']])) ?>">الإبلاغ عن خطأ في السؤال</a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between gap-2 mt-3">
                    <button type="button" class="btn btn-outline-secondary" data-exam-prev <?= $index === 0 ? 'disabled' : '' ?>>
                        <i class="bi bi-arrow-right me-1"></i> السابق
                    </button>
                    <button type="button" class="btn btn-primary" data-exam-next <?= $index === $total - 1 ? 'disabled' : '' ?>>
                        التالي <i class="bi bi-arrow-left ms-1"></i>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- خريطة الأسئلة -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-grid-3x3-gap text-primary me-2"></i> خريطة الأسئلة</span>
                <div class="pl-legend">
                    <span class="l-answered">مُجاب</span>
                    <span class="l-current">الحالي</span>
                    <span class="l-empty">لم يُجب</span>
                </div>
            </div>
            <div class="card-body">
                <div class="pl-navigator">
                    <?php foreach ($questions as $index => $question): ?>
                        <button type="button" class="pl-nav-btn <?= $question['selected'] !== null ? 'answered' : '' ?> <?= $question['is_flagged'] ? 'flagged' : '' ?> <?= $index === $startIndex ? 'current' : '' ?>"
                                data-nav-index="<?= (int) $index ?>" data-question-id="<?= (int) $question['id'] ?>">
                            <?= e(ar_digits($index + 1)) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="d-grid mt-3">
                    <button type="button" class="btn btn-danger btn-lg" data-finish-exam>
                        <i class="bi bi-flag-fill me-1"></i> إنهاء الاختبار وعرض النتيجة
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="alert alert-warning d-none" data-time-notice>انتهى وقت الاختبار! يتم إرسال إجاباتك...</div>
</div>

<script>
    window.PL_EXAM = {
        attemptId: <?= (int) $attempt['id'] ?>,
        csrfToken: <?= json_encode($csrfToken) ?>,
        saveUrl: <?= json_encode(url('api/exam/save-answer')) ?>,
        resultUrl: <?= json_encode(url('exams/result', ['id' => (int) $attempt['id']])) ?>,
        remainingSeconds: <?= (int) $remaining ?>,
        hasTimer: <?= (int) $attempt['duration_minutes'] > 0 ? 'true' : 'false' ?>,
        startIndex: <?= (int) $startIndex ?>,
        practiceMode: <?= $isPractice ? 'true' : 'false' ?>
    };
</script>
<script src="<?= e(asset('assets/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('assets/js/exam.js')) ?>"></script>
<script src="<?= e(asset('assets/js/celadon.js')) ?>"></script>
<script>
    // في نمط التدريب: عرض نتيجة السؤال مباشرة بعد الإجابة
    if (window.PL_EXAM && window.PL_EXAM.practiceMode) {
        document.addEventListener('change', function (event) {
            var input = event.target.closest('input[data-answer]');
            if (!input) return;
            var questionId = input.getAttribute('data-question-id');
            setTimeout(function () {
                var card = document.querySelector('[data-question-id="' + questionId + '"].pl-question-card');
                if (!card) return;
                var btn = card.querySelector('[data-exam-next]');
                if (btn && !btn.disabled) { btn.click(); } else { location.reload(); }
            }, 700);
        });
    }
</script>
</body>
</html>
