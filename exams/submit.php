<?php
/** إنهاء الاختبار وتصحيحه على الخادم */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Services\ExamEngine;

$user = auth()->requireLogin();
$userId = (int) $user['id'];

$attemptId = (int) post('attempt_id', 0);
$attempt = ExamEngine::attempt($attemptId, $userId);
if ($attempt === null) {
    flash('danger', 'الاختبار غير موجود.');
    redirect('exams/index');
}

$result = ExamEngine::submit($attemptId, $userId, ExamEngine::isExpired($attempt));
flash($result['ok'] ? 'success' : 'danger', $result['message']);
redirect('exams/result', 302, ['id' => $attemptId]);
