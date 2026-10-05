<?php
/** تحليل مستوى الطالب: نسب الدقة لكل مجال + نقاط القوة والضعف */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Repositories\QuestionRepository;
use App\Services\ExamEngine;
use App\Services\StatisticsService;
use App\View;

$user = auth()->requireLogin();
$userId = (int) $user['id'];
$trackId = (int) query('track', 0);

$breakdown = StatisticsService::categoryBreakdown($userId, $trackId > 0 ? $trackId : null);
[$strengths, $weaknesses] = StatisticsService::splitByLevel($breakdown);

View::render('student/statistics', [
    'title'       => 'تحليل مستواي',
    'pageSub'     => 'نسب الإجابات الصحيحة لكل مجال ونقاط القوة والضعف',
    'activeMenu'  => 'student/statistics',
    'breakdown'   => $breakdown,
    'strengths'   => $strengths,
    'weaknesses'  => $weaknesses,
    'summary'     => ExamEngine::userSummary($userId),
    'readiness'   => StatisticsService::readinessScore($userId),
    'recent'      => ExamEngine::recentAttempts($userId, 10),
    'tracks'      => (new QuestionRepository())->tracks(true),
    'trackFilter' => $trackId,
], 'layouts/app');
