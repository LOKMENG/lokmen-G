<?php
/** صفحة الاختبارات: القوالب الجاهزة + منشئ اختبار مخصص */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Repositories\QuestionRepository;
use App\Services\ExamEngine;
use App\Services\SubscriptionService;
use App\View;

$user = auth()->requireLogin();
$repo = new QuestionRepository();
$tracks = $repo->tracks(true);

$trackId = (int) query('track', 0) ?: (int) ($tracks[0]['id'] ?? 0);
$categories = $repo->categoryTree($trackId, true);

View::render('exams/index', [
    'title'        => 'الاختبارات التجريبية',
    'pageSub'      => 'اختر نموذجاً جاهزاً أو أنشئ اختباراً مخصصاً حسب المجال والصعوبة',
    'activeMenu'   => 'exams',
    'tracks'       => $tracks,
    'trackId'      => $trackId,
    'categories'   => $categories,
    'templates'    => ExamEngine::templates(),
    'isActive'     => SubscriptionService::isActive(),
    'modes'        => ExamEngine::MODES,
], 'layouts/app');
