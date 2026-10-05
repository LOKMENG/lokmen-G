<?php
/** @var array $attempt @var array $questions @var bool $onlyWrong */
$total = (int) $attempt['total_questions'];
$correct = (int) $attempt['correct_count'];
?>
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-3 justify-content-between align-items-center">
        <div>
            <div class="fw-bold"><?= e($attempt['title'] ?? '') ?></div>
            <div class="small text-muted">
                <?= e(format_date($attempt['finished_at'] ?? $attempt['started_at'], true)) ?> —
                النتيجة <?= e(ar_digits((float) $attempt['score'])) ?>%
                (<?= e(ar_digits($correct)) ?> من <?= e(ar_digits($total)) ?>)
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= e(url('exams/review', ['id' => (int) $attempt['id']])) ?>" class="btn btn-sm <?= $onlyWrong ? 'btn-outline-secondary' : 'btn-primary' ?>">كل الأسئلة</a>
            <a href="<?= e(url('exams/review', ['id' => (int) $attempt['id'], 'wrong' => 1])) ?>" class="btn btn-sm <?= $onlyWrong ? 'btn-primary' : 'btn-outline-secondary' ?>">
                الأخطاء فقط (<?= e(ar_digits((int) $attempt['wrong_count'] + (int) $attempt['unanswered_count'])) ?>)
            </a>
            <a href="<?= e(url('exams/result', ['id' => (int) $attempt['id']])) ?>" class="btn btn-sm btn-outline-primary">صفحة النتيجة</a>
        </div>
    </div>
</div>

<?php if ($questions === []): ?>
    <div class="card"><div class="card-body pl-empty"><i class="bi bi-emoji-smile"></i> لا توجد أسئلة لعرضها بهذا التصفية — أحسنت!</div></div>
<?php endif; ?>

<?php foreach ($questions as $question): ?>
    <?php
    $userAnswer = $question['selected'];
    $correctAnswer = $question['correct_answer'];
    $isCorrect = $question['is_correct'] === true;
    ?>
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex gap-2 align-items-center">
                <span class="badge bg-primary">السؤال <?= e(ar_digits($question['number'])) ?></span>
                <span class="badge bg-light text-dark border"><?= e($question['category']) ?></span>
                <span class="badge bg-<?= e(difficulty_class($question['difficulty'])) ?>-subtle text-<?= e(difficulty_class($question['difficulty'])) ?>-emphasis">
                    <?= e(difficulty_ar($question['difficulty'])) ?>
                </span>
            </div>
            <div>
                <?php if ($userAnswer === null): ?>
                    <span class="badge bg-secondary-subtle text-secondary-emphasis"><i class="bi bi-dash-circle me-1"></i> لم تُجب</span>
                <?php elseif ($isCorrect): ?>
                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> إجابة صحيحة</span>
                <?php else: ?>
                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i> إجابة خاطئة</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="pl-question-text"><?= nl2br(e($question['text'])) ?></div>

            <div class="pl-options mb-3">
                <?php $letterIndex = 0; ?>
                <?php foreach ($question['options'] as $letter => $text): ?>
                    <?php
                    $letterIndex++;
                    $class = '';
                    if ($letter === $correctAnswer) {
                        $class = 'correct';
                    } elseif ($letter === $userAnswer) {
                        $class = 'wrong';
                    }
                    ?>
                    <div class="pl-option <?= e($class) ?>">
                        <span class="pl-option-key"><?= e(ar_digits($letterIndex)) ?></span>
                        <span class="flex-grow-1"><?= nl2br(e($text)) ?></span>
                        <?php if ($letter === $correctAnswer): ?>
                            <span class="badge bg-success align-self-center">الإجابة الصحيحة</span>
                        <?php elseif ($letter === $userAnswer): ?>
                            <span class="badge bg-danger align-self-center">اختيارك</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($question['explanation'] !== ''): ?>
                <div class="alert alert-info mb-2">
                    <strong><i class="bi bi-info-circle me-1"></i> شرح الإجابة:</strong>
                    <div style="white-space: pre-line;"><?= e($question['explanation']) ?></div>
                </div>
            <?php else: ?>
                <div class="alert alert-secondary small mb-2">لا يوجد شرح مضاف لهذا السؤال.</div>
            <?php endif; ?>

            <div class="small">
                <a href="<?= e(url('questions/report', ['question' => (int) $question['id']])) ?>" class="text-muted">
                    <i class="bi bi-flag me-1"></i> الإبلاغ عن خطأ في السؤال أو الإجابة
                </a>
            </div>
        </div>
    </div>
<?php endforeach; ?>
