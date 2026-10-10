<?php
/**
 * الصفحة الرئيسية (التعريفية) للمنصة.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

use App\Repositories\QuestionRepository;
use App\Services\ExamEngine;
use App\Services\SubscriptionService;
use App\View;

// إحصائيات عامة للعرض (لا تُفشل الصفحة إن تعذّر الاتصال بقاعدة البيانات)
$stats = ['questions' => 0, 'students' => 0, 'attempts' => 0, 'satisfaction' => 96];
$tracks = [];
$categories = [];
$plans = [];
$testimonials = [];
try {
    $repo = new QuestionRepository();
    $stats['questions'] = (int) db()->value('SELECT COUNT(*) FROM `questions` WHERE active = 1', [], 0);
    $stats['students'] = (int) db()->value("SELECT COUNT(*) FROM `users` WHERE role = 'student'", [], 0);
    $stats['attempts'] = (int) db()->value("SELECT COUNT(*) FROM `exam_attempts` WHERE status IN ('completed','expired')", [], 0);
    $tracks = $repo->tracks(true);
    foreach ($tracks as $track) {
        $categories[(int) $track['id']] = $repo->categories((int) $track['id'], true);
    }
    $plans = SubscriptionService::plans();
    // الشهادات: لا تُعرض إلا بموافقة صريحة ونشر مفعّل
    $testimonials = db()->all(
        "SELECT name, role, city, body, rating FROM `testimonials`
          WHERE is_published = 1 AND consent = 1 AND body <> ''
          ORDER BY sort_order ASC, id DESC LIMIT 9"
    );
} catch (Throwable $e) {
    \App\Logger::warning('تعذّر تحميل بيانات الصفحة الرئيسية: ' . $e->getMessage());
}

View::render('public/home', [
    'title'      => 'منصة التدريب على اختبار الرخصة المهنية للمعلمين',
    'stats'      => $stats,
    'tracks'     => $tracks,
    'categories' => $categories,
    'plans'      => $plans,
    'templates'  => ExamEngine::templates(null, true),
    'testimonials' => $testimonials,
], 'layouts/public');
