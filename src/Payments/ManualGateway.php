<?php
declare(strict_types=1);

namespace App\Payments;

/**
 * الدفع اليدوي: تحويل بنكي / STC Pay + رفع إثبات الدفع، ثم مراجعة إدارية.
 * هذا هو الوضع الافتراضي في النسخة الأولى (بدون مفاتيح بوابات).
 */
final class ManualGateway implements PaymentGateway
{
    public function code(): string
    {
        return 'manual';
    }

    public function isEnabled(): bool
    {
        return \setting_bool('manual_payment_enabled', (bool) (config('payments.manual.enabled') ?? true));
    }

    public function label(): string
    {
        return 'تحويل بنكي / STC Pay (تفعيل يدوي)';
    }

    public function createPayment(array $payload): array
    {
        return [
            'ok'           => true,
            'message'      => 'يرجى تحويل المبلغ ثم رفع رقم العملية وإثبات التحويل.',
            'redirect_url' => \url('subscriptions/checkout', ['gateway' => 'manual']),
        ];
    }

    public function verifyPayment(array $request): array
    {
        return [
            'ok'             => false,
            'status'         => 'pending',
            'transaction_id' => (string) ($request['reference_number'] ?? ''),
            'amount'         => (float) ($request['amount'] ?? 0),
            'message'        => 'الدفع اليدوي يتطلب موافقة الإدارة من لوحة التحكم.',
        ];
    }
}
