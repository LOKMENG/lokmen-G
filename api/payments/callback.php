<?php
/**
 * نقطة استقبال ردود بوابات الدفع (Moyasar / MyFatoorah).
 * تُستخدم عند الرجوع من البوابة أو عند استلام Webhook.
 * ملاحظة: مُستثناة من حماية CSRF لأنها تصل من خوادم البوابة.
 */
declare(strict_types=1);

define('APP_CSRF_EXEMPT', true);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

use App\Logger;
use App\Payments\MoyasarGateway;
use App\Payments\MyFatoorahGateway;
use App\Services\SubscriptionService;

$raw = file_get_contents('php://input') ?: '';
$payload = array_merge($_GET, $_POST, json_input());

$gatewayCode = strtolower((string) ($payload['gateway'] ?? 'moyasar'));
$paymentId = (int) ($payload['payment'] ?? 0);

/** @var array{ok:bool,status:string,transaction_id:string,amount:float,message:string} $verification */
$verification = ['ok' => false, 'status' => 'unknown', 'transaction_id' => '', 'amount' => 0.0, 'message' => 'بوابة غير معروفة.'];

switch ($gatewayCode) {
    case 'moyasar':
        $gateway = new MoyasarGateway();
        if (!$gateway->isEnabled()) {
            json_response(['ok' => false, 'message' => 'بوابة Moyasar غير مفعّلة.'], 503);
        }
        // Webhook: التحقق من التوقيع إن وُجد
        $signature = (string) ($_SERVER['HTTP_X_MOYASAR_SIGNATURE'] ?? $payload['secret_token'] ?? '');
        if ($signature !== '' && !$gateway->verifyWebhookSignature($raw !== '' ? $raw : json_encode($payload), $signature)) {
            Logger::security('توقيع Webhook غير صالح (Moyasar)', ['ip' => \App\Security::ip()]);
            json_response(['ok' => false, 'message' => 'توقيع غير صالح.'], 403);
        }
        $verification = $gateway->verifyPayment($payload);
        break;

    case 'myfatoorah':
        $gateway = new MyFatoorahGateway();
        if (!$gateway->isEnabled()) {
            json_response(['ok' => false, 'message' => 'بوابة MyFatoorah غير مفعّلة.'], 503);
        }
        $verification = $gateway->verifyPayment($payload);
        break;

    default:
        json_response(['ok' => false, 'message' => 'بوابة غير مدعومة.'], 422);
}

if ($paymentId <= 0) {
    json_response(['ok' => false, 'message' => 'معرّف الدفعة مفقود.', 'verification' => $verification], 422);
}

$payment = db()->one('SELECT * FROM `payments` WHERE id = :id', ['id' => $paymentId]);
if ($payment === null) {
    json_response(['ok' => false, 'message' => 'الدفعة غير موجودة.'], 404);
}

db()->update('payments', [
    'gateway_txn_id'  => $verification['transaction_id'] !== '' ? $verification['transaction_id'] : $payment['gateway_txn_id'],
    'gateway_status'  => $verification['status'],
    'gateway_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
], 'id = :id', ['id' => $paymentId]);

if (!$verification['ok']) {
    Logger::warning('لم يتم تأكيد الدفع إلكترونياً', ['payment' => $paymentId, 'status' => $verification['status']]);
    if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/html') && !json_input()) {
        flash('warning', 'لم يتم تأكيد الدفع: ' . $verification['message']);
        redirect('subscriptions/status');
    }
    json_response(['ok' => false, 'message' => $verification['message'], 'status' => $verification['status']], 422);
}

// التحقق من تطابق المبلغ
if ($verification['amount'] > 0 && abs($verification['amount'] - (float) $payment['amount']) > 1) {
    Logger::security('مبلغ الدفع لا يطابق قيمة الاشتراك', [
        'payment' => $paymentId,
        'expected' => (float) $payment['amount'],
        'received' => $verification['amount'],
    ]);
    json_response(['ok' => false, 'message' => 'المبلغ المدفوع لا يطابق قيمة الاشتراك.'], 422);
}

// تفعيل تلقائي عند نجاح التحقق (تحويل: pending → approved → active)
$result = SubscriptionService::approvePayment($paymentId, 0, 'تفعيل تلقائي عبر بوابة ' . $gatewayCode, true);

Logger::info('تفعيل اشتراك عبر بوابة دفع', ['payment' => $paymentId, 'ok' => $result['ok']]);

if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/html')) {
    flash('success', 'تم تأكيد الدفع وتفعيل اشتراكك بنجاح.');
    redirect('student/dashboard');
}
json_response(['ok' => true, 'message' => 'تم تفعيل الاشتراك.', 'payment_id' => $paymentId]);
