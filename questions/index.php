<?php
/** التدريب حسب المجال: عرض المجالات مع أعداد الأسئلة وبدء تدريب مركّز */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Repositories\QuestionRepository;
use App\Services\SubscriptionService;
use App\View;

$user = auth()->requireLogin();
$repo = new QuestionRepository();
$tracks = $repo->tracks(true);
$trackId = (int) query('track', 0) ?: (int) ($tracks[0]['id'] ?? 0);
$highlight = (int) query('category', 0);

View::render('questions/index', [
    'title'      => 'التدريب حسب المجال',
    'pageSub'    => 'اختر المجال الذي تريد تقويته وابدأ تدريباً مركّزاً',
    'activeMenu' => 'questions/index',
    'tracks'     => $tracks,
    'trackId'    => $trackId,
    'tree'       => $repo->categoryTree($trackId, true),
    'highlight'  => $highlight,
    'isActive'   => SubscriptionService::isActive(),
], 'layouts/app');
