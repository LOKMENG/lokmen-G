<?php
/** إدارة المستخدمين: بحث، تعديل، تعطيل/تفعيل، وعرض الاشتراك */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
use App\View;

$admin = auth()->requireAdmin();

if (is_post()) {
    $userId = (int) post('user_id', 0);
    $action = (string) post('action', '');
    $target = db()->one('SELECT * FROM `users` WHERE id = :id', ['id' => $userId]);

    if ($target === null) {
        flash('danger', 'المستخدم غير موجود.');
        redirect('admin/users');
    }
    if ((int) $target['id'] === (int) $admin['id'] && in_array($action, ['toggle', 'delete'], true)) {
        flash('danger', 'لا يمكنك تعطيل أو حذف حسابك الخاص.');
        redirect('admin/users');
    }

    switch ($action) {
        case 'toggle':
            $newStatus = $target['status'] === 'active' ? 'disabled' : 'active';
            db()->update('users', ['status' => $newStatus], 'id = :id', ['id' => $userId]);
            audit('user.status_changed', 'user', $userId, ['status' => $newStatus]);
            db()->insert('notifications', [
                'user_id' => $userId,
                'type'    => 'system',
                'title'   => $newStatus === 'active' ? 'تم تفعيل حسابك' : 'تم تعطيل حسابك',
                'body'    => $newStatus === 'active' ? 'يمكنك الآن الدخول إلى المنصة.' : 'تواصل مع الدعم الفني لمزيد من التفاصيل.',
            ]);
            flash('success', 'تم ' . ($newStatus === 'active' ? 'تفعيل' : 'تعطيل') . ' الحساب.');
            break;

        case 'delete':
            if ($target['role'] === 'admin') {
                flash('danger', 'لا يمكن حذف حساب مدير. عطّله بدلاً من ذلك.');
                break;
            }
            db()->delete('users', 'id = :id', ['id' => $userId]);
            audit('user.deleted', 'user', $userId, ['email' => $target['email']]);
            flash('success', 'تم حذف المستخدم وكل بياناته المرتبطة.');
            break;

        case 'extend':
            $days = (int) post('days', 30);
            $subscription = db()->one(
                "SELECT * FROM `subscriptions` WHERE user_id = :id ORDER BY (status = 'active') DESC, id DESC LIMIT 1",
                ['id' => $userId]
            );
            if ($subscription === null) {
                flash('warning', 'لا يوجد اشتراك لهذا المستخدم. أنشئ اشتراكاً أولاً.');
                break;
            }
            $result = \App\Services\SubscriptionService::extend((int) $subscription['id'], $days, (int) $admin['id']);
            flash($result['ok'] ? 'success' : 'danger', $result['message']);
            break;

        case 'activate':
            $plan = db()->one('SELECT * FROM `subscription_plans` ORDER BY sort_order LIMIT 1');
            if ($plan === null) {
                flash('danger', 'لا توجد باقات مُعرّفة.');
                break;
            }
            $subscriptionId = db()->insert('subscriptions', [
                'user_id'       => $userId,
                'plan_id'       => (int) $plan['id'],
                'status'        => 'pending',
                'amount'        => (float) $plan['price_sar'],
                'duration_days' => (int) $plan['duration_days'],
            ]);
            $paymentId = db()->insert('payments', [
                'subscription_id'  => $subscriptionId,
                'user_id'          => $userId,
                'method'           => 'admin_manual',
                'gateway'          => 'manual',
                'amount'           => (float) $plan['price_sar'],
                'currency'         => 'SAR',
                'reference_number' => 'تفعيل إداري',
                'status'           => 'pending',
            ]);
            \App\Services\SubscriptionService::approvePayment($paymentId, (int) $admin['id'], 'تفعيل إداري من لوحة التحكم');
            flash('success', 'تم تفعيل اشتراك للمستخدم.');
            break;
    }
    redirect('admin/users');
}

$q = (string) query('q', '');
$role = (string) query('role', '');
$status = (string) query('status', '');
$subStatus = (string) query('subscription', '');
$page = max(1, (int) query('page', 1));

$clauses = ['1'];
$params = [];
if ($q !== '') {
    $clauses[] = '(u.full_name LIKE :q OR u.email LIKE :q OR u.phone LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
if (in_array($role, ['student', 'admin', 'supervisor'], true)) {
    $clauses[] = 'u.role = :role';
    $params['role'] = $role;
}
if (in_array($status, ['active', 'disabled', 'pending'], true)) {
    $clauses[] = 'u.status = :status';
    $params['status'] = $status;
}
if (in_array($subStatus, ['active', 'pending', 'expired', 'none'], true)) {
    if ($subStatus === 'none') {
        $clauses[] = 'NOT EXISTS (SELECT 1 FROM `subscriptions` s WHERE s.user_id = u.id)';
    } elseif ($subStatus === 'active') {
        $clauses[] = "EXISTS (SELECT 1 FROM `subscriptions` s WHERE s.user_id = u.id AND s.status = 'active' AND (s.expires_at IS NULL OR s.expires_at > NOW()))";
    } else {
        $clauses[] = 'EXISTS (SELECT 1 FROM `subscriptions` s WHERE s.user_id = u.id AND s.status = :sub_status)';
        $params['sub_status'] = $subStatus;
    }
}

$paginated = db()->paginate(
    "SELECT u.*,
            (SELECT p.name_ar FROM `subscriptions` s JOIN `subscription_plans` p ON p.id = s.plan_id
              WHERE s.user_id = u.id ORDER BY (s.status = 'active') DESC, s.id DESC LIMIT 1) AS plan_name,
            (SELECT s2.status FROM `subscriptions` s2 WHERE s2.user_id = u.id ORDER BY (s2.status = 'active') DESC, s2.id DESC LIMIT 1) AS sub_status,
            (SELECT s3.expires_at FROM `subscriptions` s3 WHERE s3.user_id = u.id ORDER BY (s3.status = 'active') DESC, s3.id DESC LIMIT 1) AS sub_expires,
            (SELECT COUNT(*) FROM `exam_attempts` a WHERE a.user_id = u.id AND a.status IN ('completed','expired')) AS attempts_count
       FROM `users` u
      WHERE " . implode(' AND ', $clauses) . "
      ORDER BY u.id DESC",
    $params,
    20,
    $page
);

View::render('admin/users', [
    'title'     => 'المستخدمون',
    'pageSub'   => 'إدارة الحسابات والصلاحيات والاشتراكات',
    'activeMenu'=> 'admin/users',
    'paginated' => $paginated,
    'filters'   => ['q' => $q, 'role' => $role, 'status' => $status, 'subscription' => $subStatus],
    'counters'  => [
        'total'    => (int) db()->value('SELECT COUNT(*) FROM `users`', [], 0),
        'students' => (int) db()->value("SELECT COUNT(*) FROM `users` WHERE role = 'student'", [], 0),
        'active'   => (int) db()->value("SELECT COUNT(*) FROM `users` WHERE status = 'active'", [], 0),
        'disabled' => (int) db()->value("SELECT COUNT(*) FROM `users` WHERE status = 'disabled'", [], 0),
    ],
], 'layouts/app');
