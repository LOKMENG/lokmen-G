<?php
/** شاشة أداء الاختبار: سؤال واحد في كل مرة + المؤقت + التنقل + الحفظ التلقائي */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Services\ExamEngine;
use App\View;

$user = auth()->requireLogin();
$userId = (int) $user['id'];
$attemptId = (int) query('id', 0);

$attempt = ExamEngine::attempt($attemptId, $userId);
if ($attempt === null) {
    flash('danger', 'لم يتم العثور على الاختبار المطلوب.');
    redirect('exams/index');
}

if ($attempt['status'] !== 'in_progress') {
    redirect('exams/result', 302, ['id' => $attemptId]);
}

// إن انتهى الوقت: تصحيح تلقائي فوري
if (ExamEngine::isExpired($attempt)) {
    ExamEngine::submit($attemptId, $userId, true);
    flash('warning', 'انتهى وقت الاختبار وتم إرساله تلقائياً.');
    redirect('exams/result', 302, ['id' => $attemptId]);
}

$questions = ExamEngine::questions($attemptId);
$progress = ExamEngine::progress($attemptId);
$remaining = ExamEngine::remainingSeconds($attempt);
$isPractice = $attempt['mode'] === 'practice';
$showExplanation = (int) ($attempt['show_explanation'] ?? 1) === 1;

// في نمط التدريب نحتاج عرض الإجابة والشرح بعد الحفظ (عبر AJAX)
$feedback = [];
if ($isPractice && $showExplanation) {
    foreach ($questions as $question) {
        if ($question['selected'] !== null) {
            $row = db()->one('SELECT correct_answer, explanation FROM `questions` WHERE id = :id', ['id' => $question['id']]);
            $feedback[$question['id']] = [
                'correct' => (bool) $question['is_correct'],
                'correct_answer' => $row['correct_answer'] ?? null,
                'explanation' => (string) ($row['explanation'] ?? ''),
            ];
        }
    }
}

$startIndex = max(0, (int) query('q', 0) - 1);
if ($startIndex === 0) {
    foreach ($questions as $index => $question) {
        if ($question['selected'] === null) {
            $startIndex = $index;
            break;
        }
    }
}

View::render('exams/take', [
    'title'      => (string) ($attempt['title'] ?? 'اختبار'),
    'pageSub'    => ($isPractice ? 'نمط التدريب: تظهر الإجابة والشرح بعد كل سؤال' : 'أجب عن الأسئلة ثم أرسل الاختبار لمعرفة نتيجتك'),
    'bodyClass'  => 'pl-exam-page',
    'attempt'    => $attempt,
    'questions'  => $questions,
    'progress'   => $progress,
    'remaining'  => $remaining,
    'feedback'   => $feedback,
    'isPractice' => $isPractice,
    'startIndex' => $startIndex,
    'csrfToken'  => csrf_token(),
], null);
