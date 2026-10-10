<?php
/** صفحة النتيجة: الدرجة، التوزيع، تحليل المجالات، وإعادة الاختبار */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Services\ExamEngine;
use App\View;

$user = auth()->requireLogin();
$userId = (int) $user['id'];
$attemptId = (int) query('id', 0);

$result = ExamEngine::result($attemptId, $userId);
if ($result === []) {
    flash('danger', 'النتيجة غير متوفرة.');
    redirect('exams/history');
}

View::render('exams/result', [
    'title'      => 'نتيجة الاختبار',
    'pageSub'    => (string) ($result['title'] ?? ''),
    'activeMenu' => 'exams',
    'result'     => $result,
    'analysis'   => ExamEngine::analysis($attemptId),
    'progress'   => ExamEngine::progress($attemptId),
    'categories' => [],
], 'layouts/app');
