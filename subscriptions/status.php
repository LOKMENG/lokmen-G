<?php
/** حالة الاشتراك وسجل المدفوعات + إمكانية إرسال إثبات دفع إضافي */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Payments\PaymentManager;
use App\Services\SubscriptionService;
use App\View;

$user = auth()->requireLogin();
$userId = (int) $user['id'];

if (is_post() && post('action') === 'cancel_pending') {
    $subscriptionId = (int) post('subscription_id', 0);
    $subscription = db()->one('SELECT * FROM `subscriptions` WHERE id = :id AND user_id = :u', ['id' => $subscriptionId, 'u' => $userId]);
    if ($subscription !== null && $subscription['status'] === 'pending') {
        db()->update('subscriptions', ['status' => 'cancelled', 'cancelled_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $subscriptionId]);
        db()->update('payments', ['status' => 'rejected', 'admin_note' => 'أُلغي بواسطة الطالب'], 'subscription_id = :id AND status = :s', ['id' => $subscriptionId, 's' => 'pending']);
        audit('subscription.cancelled_by_user', 'subscription', $subscriptionId);
        flash('success', 'تم إلغاء طلب الاشتراك المعلّق.');
    }
    redirect('subscriptions/status');
}

$subscriptions = db()->all(
    'SELECT s.*, p.name_ar AS plan_name FROM `subscriptions` s
       JOIN `subscription_plans` p ON p.id = s.plan_id
      WHERE s.user_id = :id ORDER BY s.id DESC LIMIT 20',
    ['id' => $userId]
);
$payments = db()->all(
    'SELECT * FROM `payments` WHERE user_id = :id ORDER BY id DESC LIMIT 20',
    ['id' => $userId]
);

View::render('subscriptions/status', [
    'title'         => 'حالة الاشتراك',
    'pageSub'       => 'تفاصيل اشتراكك وسجل المدفوعات',
    'activeMenu'    => 'subscriptions/status',
    'current'       => SubscriptionService::current($userId),
    'isActive'      => SubscriptionService::isActive($userId),
    'daysLeft'      => SubscriptionService::daysRemaining($userId),
    'subscriptions' => $subscriptions,
    'payments'      => $payments,
], 'layouts/app');
