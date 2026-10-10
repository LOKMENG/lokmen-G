<?php
/** تدريب سريع: إعداد التدريب ثم البدء مباشرة */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Repositories\QuestionRepository;
use App\Services\ExamEngine;
use App\Services\SubscriptionService;
use App\View;

$user = auth()->requireLogin();
$userId = (int) $user['id'];

// زر "تدريب عشوائي سريع"
if (is_post() && post('action') === 'quick') {
    $trackId = (int) post('track_id', 0);
    $count = (int) post('count', 10);
    $result = ExamEngine::build([
        'mode'             => 'practice',
        'track_id'         => $trackId,
        'count'            => $count,
        'duration_minutes' => 0,
        'show_explanation' => 1,
        'randomize_options' => (int) post('randomize_options', 0),
    ], $userId);
    if (!$result['ok']) {
        flash('danger', $result['message']);
        redirect('questions/practice');
    }
    redirect('exams/take', 302, ['id' => (int) $result['attempt_id']]);
}

$repo = new QuestionRepository();
$tracks = $repo->tracks(true);
$trackId = (int) query('track', 0) ?: (int) ($tracks[0]['id'] ?? 0);

View::render('questions/practice', [
    'title'      => 'تدريب سريع',
    'pageSub'    => 'اختر إعداداتك وابدأ التدريب — يظهر الشرح بعد كل سؤال',
    'activeMenu' => 'questions/practice',
    'tracks'     => $tracks,
    'trackId'    => $trackId,
    'categories' => $repo->categoryTree($trackId, true),
    'isActive'   => SubscriptionService::isActive(),
], 'layouts/app');
