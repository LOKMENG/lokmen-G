<?php
/** مراجعة المدفوعات: اعتماد/رفض/تراجع + عرض الإيصال */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Payments\PaymentManager;
use App\Services\SubscriptionService;
use App\View;

$admin = auth()->requireAdmin();
$adminId = (int) $admin['id'];

if (is_post()) {
    $action = (string) post('action', '');
    $paymentId = (int) post('payment_id', 0);
    $ok = false;
    $message = '';

    switch ($action) {
        case 'approve':
            $result = SubscriptionService::approvePayment($paymentId, $adminId, (string) post('note', ''), true);
            $ok = $result['ok'];
            $message = $result['message'];
            break;
        case 'reject':
            $result = SubscriptionService::rejectPayment($paymentId, $adminId, (string) post('reason', ''));
            $ok = $result['ok'];
            $message = $result['message'];
            break;
        case 'delete':
            $payment = db()->one('SELECT * FROM `payments` WHERE id = :id', ['id' => $paymentId]);
            if ($payment === null) {
                $message = 'الدفعة غير موجودة.';
            } elseif ($payment['status'] === 'approved') {
                $message = 'لا يمكن حذف دفعة معتمدة.';
            } else {
                db()->delete('payments', 'id = :id', ['id' => $paymentId]);
                audit('admin.payment_deleted', 'payment', $paymentId);
                $ok = true;
                $message = 'تم حذف الدفعة.';
            }
            break;
    }

    flash($ok ? 'success' : 'danger', $message !== '' ? $message : 'لم يتم تنفيذ العملية.');
    redirect('admin/payments');
}

$status = (string) query('status', '');
$method = (string) query('method', '');
$q = (string) query('q', '');
$page = max(1, (int) query('page', 1));

$clauses = ['1'];
$params = [];
if (in_array($status, ['pending', 'approved', 'rejected', 'refunded'], true)) {
    $clauses[] = 'p.status = :status';
    $params['status'] = $status;
}
if ($method !== '') {
    $clauses[] = 'p.method = :method';
    $params['method'] = $method;
}
if ($q !== '') {
    $clauses[] = '(u.full_name LIKE :q OR u.email LIKE :q OR p.reference_number LIKE :q OR p.gateway_txn_id LIKE :q)';
    $params['q'] = '%' . $q . '%';
}

$paginated = db()->paginate(
    "SELECT p.*, u.full_name, u.email, u.phone, s.status AS subscription_status, pl.name_ar AS plan_name
       FROM `payments` p
       JOIN `users` u ON u.id = p.user_id
       LEFT JOIN `subscriptions` s ON s.id = p.subscription_id
       LEFT JOIN `subscription_plans` pl ON pl.id = s.plan_id
      WHERE " . implode(' AND ', $clauses) . "
      ORDER BY (p.status = 'pending') DESC, p.id DESC",
    $params,
    20,
    $page
);

View::render('admin/payments', [
    'title'      => 'المدفوعات',
    'pageSub'    => 'مراجعة إثباتات الدفع واعتمادها أو رفضها',
    'activeMenu' => 'admin/payments',
    'paginated'  => $paginated,
    'filters'    => ['status' => $status, 'method' => $method, 'q' => $q],
    'stats'      => SubscriptionService::statistics(),
    'methods'    => ['bank_transfer' => 'حوالة بنكية', 'stc_pay' => 'STC Pay', 'mada' => 'مدى', 'apple_pay' => 'Apple Pay', 'moyasar' => 'Moyasar', 'myfatoorah' => 'MyFatoorah', 'cash' => 'نقداً', 'admin_manual' => 'تفعيل إداري', 'free' => 'مجاني'],
], 'layouts/app');
