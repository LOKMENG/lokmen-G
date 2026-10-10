<?php
/** المراجعة التفصيلية: السؤال، إجابة الطالب، الإجابة الصحيحة، الشرح */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Services\ExamEngine;
use App\View;

$user = auth()->requireLogin();
$userId = (int) $user['id'];
$attemptId = (int) query('id', 0);
$onlyWrong = (bool) query('wrong', 0);

$attempt = ExamEngine::attempt($attemptId, $userId);
if ($attempt === null) {
    flash('danger', 'المحاولة غير موجودة.');
    redirect('exams/history');
}

$questions = ExamEngine::questions($attemptId, true);
if ($onlyWrong) {
    $questions = array_values(array_filter($questions, static fn(array $q): bool => $q['is_correct'] !== true));
}

View::render('exams/review', [
    'title'      => 'مراجعة الاختبار',
    'pageSub'    => (string) ($attempt['title'] ?? ''),
    'activeMenu' => 'exams',
    'attempt'    => $attempt,
    'questions'  => $questions,
    'onlyWrong'  => $onlyWrong,
], 'layouts/app');
