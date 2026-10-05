<?php
/**
 * واجهة حفظ الإجابة أثناء الاختبار (AJAX - JSON).
 * POST: attempt_id, question_id, answer (a|b|c|d|null), flagged (0|1)
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

use App\Services\ExamEngine;

$user = auth()->requireLogin();
$payload = array_merge($_POST, json_input());

$attemptId = (int) ($payload['attempt_id'] ?? 0);
$questionId = (int) ($payload['question_id'] ?? 0);
$answer = $payload['answer'] ?? null;
$flagged = array_key_exists('flagged', $payload) ? (bool) ((int) $payload['flagged']) : null;

if ($attemptId <= 0 || $questionId <= 0) {
    json_response(['ok' => false, 'message' => 'بيانات ناقصة.'], 422);
}

if ($answer !== null) {
    $answer = (string) $answer;
    if ($answer === '') {
        $answer = null; // إلغاء الإجابة
    }
}

$result = ExamEngine::saveAnswer($attemptId, (int) $user['id'], $questionId, $answer, $flagged);

// في نمط التدريب: نُرجع الإجابة الصحيحة والشرح فوراً
if (($result['ok'] ?? false) && $answer !== null) {
    $attempt = ExamEngine::attempt($attemptId, (int) $user['id']);
    if ($attempt !== null && $attempt['mode'] === 'practice' && (int) ($attempt['show_explanation'] ?? 1) === 1) {
        $question = db()->one('SELECT correct_answer, explanation FROM `questions` WHERE id = :id', ['id' => $questionId]);
        $result['feedback'] = [
            'correct'        => $question !== null && (string) $question['correct_answer'] === $answer,
            'correct_answer' => $question['correct_answer'] ?? null,
            'explanation'    => (string) ($question['explanation'] ?? ''),
        ];
    }
    $result['progress'] = ExamEngine::progress($attemptId);
}

json_response($result, ($result['ok'] ?? false) ? 200 : 422);
