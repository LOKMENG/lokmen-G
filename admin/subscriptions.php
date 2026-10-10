<?php
/** إدارة الاشتراكات: نشطة/منتهية/معلقة، موافقة الدفع، تمديد، إلغاء، وتفعيل يدوي */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Services\Notifier;
use App\Services\SubscriptionService;
use App\View;

$admin = auth()->requireAdmin();
$adminId = (int) $admin['id'];

if (is_post()) {
    $action = (string) post('action', '');
    $subscriptionId = (int) post('subscription_id', 0);
    $message = '';
    $ok = false;

    switch ($action) {
        case 'activate':
            $planId = (int) post('plan_id', 0);
            $userId = (int) post('user_id', 0);
            $lookup = trim((string) post('user_lookup', ''));
            if ($userId <= 0 && $lookup !== '') {
                // يقبل المعرّف الرقمي أو البريد الإلكتروني أو رقم الجوال
                $target = ctype_digit($lookup)
                    ? db()->one('SELECT id FROM `users` WHERE id = :v', ['v' => (int) $lookup])
                    : db()->one('SELECT id FROM `users` WHERE email = :v OR phone = :v', ['v' => $lookup]);
                $userId = (int) ($target['id'] ?? 0);
            } else {
                $target = $userId > 0 ? db()->one('SELECT id FROM `users` WHERE id = :id', ['id' => $userId]) : null;
            }
            $plan = SubscriptionService::plan($planId);
            if ($plan === null || $target === null || $userId <= 0) {
                $message = 'لم يتم العثور على المستخدم. تأكد من البريد الإلكتروني أو المعرّف.';
                break;
            }
            $newSubscriptionId = db()->insert('subscriptions', [
                'user_id'       => $userId,
                'plan_id'       => $planId,
                'status'        => 'pending',
                'amount'        => (float) $plan['price_sar'],
                'duration_days' => (int) $plan['duration_days'],
            ]);
            $paymentId = db()->insert('payments', [
                'subscription_id'  => $newSubscriptionId,
                'user_id'          => $userId,
                'method'           => 'admin_manual',
                'gateway'          => 'manual',
                'amount'           => (float) $plan['price_sar'],
                'reference_number' => 'تفعيل إداري #' . $adminId,
                'status'           => 'pending',
            ]);
            $result = SubscriptionService::approvePayment($paymentId, $adminId, 'تفعيل إداري', true);
            $ok = $result['ok'];
            $message = $result['message'];
            break;

        case 'approve':
            $paymentId = (int) post('payment_id', 0);
            $result = SubscriptionService::approvePayment($paymentId, $adminId, (string) post('note', ''), true);
            $ok = $result['ok'];
            $message = $result['message'];
            break;

        case 'reject':
            $paymentId = (int) post('payment_id', 0);
            $result = SubscriptionService::rejectPayment($paymentId, $adminId, (string) post('reason', ''));
            $ok = $result['ok'];
            $message = $result['message'];
            break;

        case 'extend':
            $days = (int) post('days', 30);
            $result = SubscriptionService::extend($subscriptionId, $days, $adminId);
            $ok = $result['ok'];
            $message = $result['message'];
            break;

        case 'cancel':
            $result = SubscriptionService::cancel($subscriptionId, $adminId, (string) post('reason', ''));
            $ok = $result['ok'];
            $message = $result['message'];
            break;

        case 'activate_existing':
            $result = SubscriptionService::activate($subscriptionId, $adminId);
            $ok = $result['ok'];
            $message = $result['message'];
            if ($ok) {
                $subscription = db()->one('SELECT user_id, expires_at FROM `subscriptions` WHERE id = :id', ['id' => $subscriptionId]);
                if ($subscription !== null) {
                    Notifier::subscriptionActivated((int) $subscription['user_id'], $subscription['expires_at']);
                }
            }
            break;

        case 'notify_expiring':
            $count = SubscriptionService::notifyExpiring((int) post('days', 7));
            $ok = true;
            $message = 'تم إرسال ' . $count . ' تنبيه انتهاء اشتراك.';
            break;

        case 'expire_now':
            $count = SubscriptionService::expireDue();
            $ok = true;
            $message = 'تم إنهاء ' . $count . ' اشتراك منتهي.';
            break;
    }

    flash($ok ? 'success' : 'danger', $message !== '' ? $message : 'لم يتم تنفيذ العملية.');
    redirect('admin/subscriptions');
}

$status = (string) query('status', '');
$q = (string) query('q', '');
$page = max(1, (int) query('page', 1));

$clauses = ['1'];
$params = [];
if (in_array($status, ['pending', 'approved', 'active', 'expired', 'rejected', 'cancelled'], true)) {
    if ($status === 'active') {
        $clauses[] = "s.status = 'active' AND (s.expires_at IS NULL OR s.expires_at > NOW())";
    } elseif ($status === 'expired') {
        $clauses[] = "(s.status = 'expired' OR (s.status = 'active' AND s.expires_at <= NOW()))";
    } else {
        $clauses[] = 's.status = :status';
        $params['status'] = $status;
    }
}
if ($q !== '') {
    $clauses[] = '(u.full_name LIKE :q OR u.email LIKE :q OR u.phone LIKE :q)';
    $params['q'] = '%' . $q . '%';
}

$paginated = db()->paginate(
    "SELECT s.*, u.full_name, u.email, u.phone, p.name_ar AS plan_name,
            DATEDIFF(s.expires_at, NOW()) AS days_remaining,
            (SELECT pay.id FROM `payments` pay WHERE pay.subscription_id = s.id ORDER BY pay.id DESC LIMIT 1) AS last_payment_id,
            (SELECT pay.status FROM `payments` pay WHERE pay.subscription_id = s.id ORDER BY pay.id DESC LIMIT 1) AS payment_status,
            (SELECT pay.receipt_path FROM `payments` pay WHERE pay.subscription_id = s.id ORDER BY pay.id DESC LIMIT 1) AS receipt_path,
            (SELECT pay.reference_number FROM `payments` pay WHERE pay.subscription_id = s.id ORDER BY pay.id DESC LIMIT 1) AS reference_number
       FROM `subscriptions` s
       JOIN `users` u ON u.id = s.user_id
       JOIN `subscription_plans` p ON p.id = s.plan_id
      WHERE " . implode(' AND ', $clauses) . "
      ORDER BY (s.status = 'pending') DESC, s.id DESC",
    $params,
    20,
    $page
);

View::render('admin/subscriptions', [
    'title'      => 'الاشتراكات',
    'pageSub'    => 'متابعة الاشتراكات وتفعيلها وتمديدها',
    'activeMenu' => 'admin/subscriptions',
    'paginated'  => $paginated,
    'stats'      => SubscriptionService::statistics(),
    'filters'    => ['status' => $status, 'q' => $q],
    'plans'      => SubscriptionService::plans(),
    'expiring'   => SubscriptionService::expiringSoon(14),
], 'layouts/app');
