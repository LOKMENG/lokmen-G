<?php
/** لوحة الطالب: حالة الاشتراك، الإحصائيات، نقاط القوة والضعف، آخر الاختبارات */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Services\StatisticsService;
use App\Services\SubscriptionService;
use App\View;

$user = auth()->requireLogin();
$userId = (int) $user['id'];

$dashboard = StatisticsService::userDashboard($userId);
$readiness = StatisticsService::readinessScore($userId);
$subscription = $dashboard['subscription'];
$summary = $dashboard['summary'];

View::render('student/dashboard', [
    'title'        => 'لوحتي',
    'pageSub'      => 'متابعة مستواك وتقدمك في الاستعداد للاختبار',
    'activeMenu'   => 'student/dashboard',
    'dashboard'    => $dashboard,
    'readiness'    => $readiness,
    'subscription' => $subscription,
    'summary'      => $summary,
    'isActive'     => SubscriptionService::isActive($userId),
    'daysLeft'     => SubscriptionService::daysRemaining($userId),
    'user'         => $user,
], 'layouts/app');
