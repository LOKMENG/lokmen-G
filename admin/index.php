<?php
/** لوحة الإدارة: نظرة عامة */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Repositories\QuestionRepository;
use App\Services\StatisticsService;
use App\Services\SubscriptionService;
use App\View;

$admin = auth()->requireAdmin();

View::render('admin/index', [
    'title'             => 'لوحة الإدارة',
    'pageSub'           => 'نظرة عامة على المنصة والمؤشرات الرئيسية',
    'activeMenu'        => 'admin/index',
    'overview'          => StatisticsService::overview(),
    'subStats'          => SubscriptionService::statistics(),
    'questionStats'     => (new QuestionRepository())->statistics(),
    'registrations'     => StatisticsService::registrationsByDay(14),
    'attemptsSeries'    => StatisticsService::attemptsByDay(14),
    'revenueSeries'     => StatisticsService::revenueByDay(14),
    'pendingPayments'   => db()->all(
        "SELECT p.*, u.full_name, u.phone FROM `payments` p JOIN `users` u ON u.id = p.user_id
          WHERE p.status = 'pending' ORDER BY p.id DESC LIMIT 5"
    ),
    'weakest'           => StatisticsService::weakestCategories(5),
    'hardest'           => StatisticsService::hardestQuestions(5),
    'expiringSoon'      => SubscriptionService::expiringSoon(14),
    'recentUsers'       => db()->all('SELECT id, full_name, email, phone, role, status, created_at FROM `users` ORDER BY id DESC LIMIT 5'),
], 'layouts/app');
