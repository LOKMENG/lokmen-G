<?php
/** باقات الاشتراك وحالة الاشتراك الحالي */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Payments\PaymentManager;
use App\Services\SubscriptionService;
use App\View;

$user = auth()->requireLogin();
$userId = (int) $user['id'];

$current = SubscriptionService::current($userId);
$pendingPayment = db()->one(
    "SELECT p.*, s.status AS subscription_status FROM `payments` p
       JOIN `subscriptions` s ON s.id = p.subscription_id
      WHERE p.user_id = :id AND p.status = 'pending' AND s.status = 'pending'
      ORDER BY p.id DESC LIMIT 1",
    ['id' => $userId]
);

View::render('subscriptions/plans', [
    'title'          => 'باقات الاشتراك',
    'pageSub'        => 'اشتراك لمرة واحدة يمنحك وصولاً كاملاً لبنك الأسئلة والاختبارات',
    'activeMenu'     => 'subscriptions/plans',
    'plans'          => SubscriptionService::plans(),
    'current'        => $current,
    'isActive'       => SubscriptionService::isActive($userId),
    'daysLeft'       => SubscriptionService::daysRemaining($userId),
    'methods'        => PaymentManager::options(),
    'pendingPayment' => $pendingPayment,
], 'layouts/app');
