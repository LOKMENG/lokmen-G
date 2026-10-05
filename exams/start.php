<?php
/**
 * بدء اختبار جديد (من نموذج جاهز أو منشئ مخصص).
 * يتحقق من الاشتراك، يبني المحاولة، ثم يحوّل إلى شاشة الاختبار.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Services\ExamEngine;
use App\Services\SubscriptionService;

$user = auth()->requireLogin();
$userId = (int) $user['id'];

if (!is_post()) {
    // دعم الدخول المباشر برابط GET: /exams/start?template=3
    $templateId = (int) query('template', 0);
    if ($templateId > 0) {
        $_POST = ['template_id' => $templateId];
    } else {
        redirect('exams/index');
    }
}

$action = (string) post('action', 'template');
$isTemplate = (int) post('template_id', 0) > 0;
$template = $isTemplate ? ExamEngine::template((int) post('template_id')) : null;

// التحقق من الصلاحية (الاشتراك) للنماذج التي تتطلبه
$requiresSubscription = $template === null || (int) $template['require_subscription'] === 1;
if ($requiresSubscription && !SubscriptionService::isActive($userId) && !is_admin()) {
    flash('warning', 'يجب تفعيل الاشتراك قبل بدء هذا الاختبار.');
    redirect('subscriptions/plans');
}

if ($isTemplate) {
    $options = [
        'template_id' => (int) post('template_id'),
        'title'       => (string) ($template['title'] ?? ''),
        'transition'  => 'none',
    ];
} else {
    $categoryIds = array_map('intval', (array) (($_POST['category_ids'] ?? []) ?: []));
    $options = [
        'mode'              => in_array((string) post('mode', 'mock'), ['mock', 'practice', 'random', 'category'], true) ? (string) post('mode') : 'mock',
        'track_id'          => (int) post('track_id', 0),
        'category_ids'      => array_values(array_filter($categoryIds)),
        'difficulty'        => in_array((string) post('difficulty', 'any'), ['any', 'easy', 'medium', 'hard'], true) ? (string) post('difficulty') : 'any',
        'count'             => (int) post('count', 20),
        'duration_minutes'  => (int) post('duration_minutes', 0),
        'randomize_options' => (int) post('randomize_options', 0),
        'show_explanation'  => 1,
    ];
}

$result = ExamEngine::build($options, $userId);
if (!$result['ok']) {
    flash('danger', $result['message']);
    redirect('exams/index');
}

if (($result['available'] ?? 0) < ($result['requested'] ?? 0)) {
    flash('info', 'تم تجهيز ' . ar_digits((int) $result['available']) . ' سؤالاً من أصل ' . ar_digits((int) $result['requested']) . ' (عدد الأسئلة المتاحة حالياً في هذا التصنيف).');
}

redirect('exams/take', 302, ['id' => (int) $result['attempt_id']]);
