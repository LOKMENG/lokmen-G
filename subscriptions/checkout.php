<?php
/** إتمام الاشتراك: اختيار الباقة وطريقة الدفع ثم رفع إثبات الدفع */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Payments\PaymentManager;
use App\Security;
use App\Services\Notifier;
use App\Services\SubscriptionService;
use App\Validator;
use App\View;

$user = auth()->requireLogin();
$userId = (int) $user['id'];
$planId = (int) (query('plan', 0) ?: post('plan_id', 0));
$plan = $planId > 0 ? SubscriptionService::plan($planId) : null;

if ($plan === null || (int) $plan['is_active'] !== 1) {
    flash('warning', 'يرجى اختيار باقة صحيحة.');
    redirect('subscriptions/plans');
}

$errors = errors();

if (is_post()) {
    $validator = Validator::make($_POST, [
        'plan_id'          => 'required|integer|exists:subscription_plans,id',
        'method'           => 'required|in:bank_transfer,stc_pay,cash,moyasar,myfatoorah,mada,apple_pay',
        'reference_number' => 'required|min:4|max:120',
        'payer_note'       => 'max:255',
    ]);
    if (!$validator->validate()) {
        set_errors($validator->errors());
        keep_old_input();
        redirect('subscriptions/checkout', 302, ['plan' => $planId]);
    }

    $receiptPath = null;
    $hasReceipt = isset($_FILES['receipt']) && (int) ($_FILES['receipt']['error'] ?? 4) !== UPLOAD_ERR_NO_FILE;
    if ($hasReceipt) {
        $upload = Security::upload($_FILES['receipt'], array_merge(config('uploads.allowed_image'), ['pdf']), 'receipts', (int) config('uploads.max_mb', 8));
        if (!$upload['ok']) {
            set_errors(['receipt' => $upload['error']]);
            keep_old_input();
            redirect('subscriptions/checkout', 302, ['plan' => $planId]);
        }
        $receiptPath = $upload['path'];
    }

    $result = SubscriptionService::createRequest($userId, $planId, [
        'method'           => (string) post('method'),
        'gateway'          => in_array((string) post('method'), ['moyasar', 'mada', 'apple_pay'], true) ? 'moyasar' : (string) (post('method') === 'myfatoorah' ? 'myfatoorah' : 'manual'),
        'reference_number' => (string) post('reference_number'),
        'payer_note'       => (string) post('payer_note', '') ?: null,
        'receipt_path'     => $receiptPath,
    ]);

    if (!$result['ok']) {
        flash('danger', $result['message']);
        redirect('subscriptions/checkout', 302, ['plan' => $planId]);
    }

    clear_old_input();
    Notifier::subscriptionSubmitted($userId, (string) post('reference_number'));
    flash('success', 'تم استلام طلب الاشتراك. سيتم التحقق من الدفع وتفعيل حسابك، وستصلك رسالة عند التفعيل.');
    redirect('subscriptions/status');
}

View::render('subscriptions/checkout', [
    'title'        => 'إتمام الاشتراك',
    'pageSub'      => 'الخطوة الأخيرة — حوّل المبلغ ثم أرسل بيانات العملية',
    'activeMenu'   => 'subscriptions/plans',
    'plan'         => $plan,
    'methods'      => PaymentManager::options(),
    'errors'       => $errors,
    'instructions' => (string) settings('payment_instructions', ''),
], 'layouts/app');
