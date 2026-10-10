<?php
/** إنهاء الاختبار عبر AJAX (يُستخدم عند انتهاء الوقت أو الإنهاء السريع) */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

use App\Services\ExamEngine;

$user = auth()->requireLogin();
$payload = array_merge($_POST, json_input());
$attemptId = (int) ($payload['attempt_id'] ?? 0);

if ($attemptId <= 0) {
    json_response(['ok' => false, 'message' => 'معرّف الاختبار مطلوب.'], 422);
}

$attempt = ExamEngine::attempt($attemptId, (int) $user['id']);
if ($attempt === null) {
    json_response(['ok' => false, 'message' => 'الاختبار غير موجود.'], 404);
}

$result = ExamEngine::submit($attemptId, (int) $user['id'], ExamEngine::isExpired($attempt));
json_response([
    'ok'         => $result['ok'],
    'message'    => $result['message'],
    'score'      => $result['score'] ?? null,
    'redirect'   => url('exams/result', ['id' => $attemptId]),
], $result['ok'] ? 200 : 422);
