<?php
declare(strict_types=1);

namespace App\Payments;

use App\Logger;

/**
 * بوابة MyFatoorah — تدعم مدى و KNET و Apple Pay و STC Pay.
 * تحتاج: MYFATOORAH_ENABLED=true, MYFATOORAH_API_KEY, MYFATOORAH_BASE_URL
 * الوثائق: https://docs.myfatoorah.com
 */
final class MyFatoorahGateway implements PaymentGateway
{
    public function code(): string
    {
        return 'myfatoorah';
    }

    public function isEnabled(): bool
    {
        return (bool) config('payments.myfatoorah.enabled', false)
            && trim((string) config('payments.myfatoorah.api_key', '')) !== '';
    }

    public function label(): string
    {
        return 'مدى / Apple Pay / STC Pay (MyFatoorah)';
    }

    public function createPayment(array $payload): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'message' => 'بوابة MyFatoorah غير مفعّلة. فعّل المفتاح في ملف .env أولاً.'];
        }
        $response = $this->request('/v2/SendPayment', [
            'CustomerName'       => $payload['user']['full_name'] ?? 'عميل',
            'NotificationOption' => 'LNK',
            'InvoiceValue'       => $payload['amount'],
            'DisplayCurrencyIso' => $payload['currency'] ?? 'SAR',
            'CallBackUrl'        => $payload['callback_url'] ?? '',
            'ErrorUrl'           => $payload['callback_url'] ?? '',
            'Language'           => 'ar',
            'CustomerEmail'      => $payload['user']['email'] ?? '',
            'CustomerMobile'     => $payload['user']['phone'] ?? '',
            'InvoiceItems'       => [[
                'ItemName'  => $payload['description'] ?? 'اشتراك المنصة',
                'Quantity'  => 1,
                'UnitPrice' => $payload['amount'],
            ]],
        ]);
        if (!$response['ok']) {
            return ['ok' => false, 'message' => $response['message']];
        }
        $data = $response['data']['Data'] ?? [];
        return [
            'ok'             => true,
            'message'        => 'تم إنشاء فاتورة الدفع.',
            'redirect_url'   => (string) ($data['InvoiceURL'] ?? ''),
            'transaction_id' => (string) ($data['InvoiceId'] ?? ''),
            'raw'            => $data,
        ];
    }

    public function verifyPayment(array $request): array
    {
        $paymentId = (string) ($request['paymentId'] ?? $request['Id'] ?? '');
        if ($paymentId === '') {
            return ['ok' => false, 'status' => 'pending', 'transaction_id' => '', 'amount' => 0, 'message' => 'معرّف العملية مفقود.'];
        }
        $response = $this->request('/v2/GetPaymentStatus', ['Key' => $paymentId, 'KeyType' => 'PaymentId']);
        if (!$response['ok']) {
            return ['ok' => false, 'status' => 'pending', 'transaction_id' => $paymentId, 'amount' => 0, 'message' => $response['message']];
        }
        $data = $response['data']['Data'] ?? [];
        $status = (string) ($data['InvoiceStatus'] ?? '');
        $paid = $status === 'Paid';
        return [
            'ok'             => $paid,
            'status'         => $status !== '' ? $status : 'unknown',
            'transaction_id' => $paymentId,
            'amount'         => (float) ($data['InvoiceValue'] ?? 0),
            'message'        => $paid ? 'تم الدفع بنجاح.' : 'لم يتم تأكيد الدفع.',
            'raw'            => $data,
        ];
    }

    /** @return array{ok:bool,message:string,data?:array<string,mixed>} */
    private function request(string $path, array $body): array
    {
        $url = rtrim((string) config('payments.myfatoorah.base_url', 'https://apitest.myfatoorah.com'), '/') . $path;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . (string) config('payments.myfatoorah.api_key', ''),
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            Logger::error('MyFatoorah: فشل الاتصال', ['error' => $error]);
            return ['ok' => false, 'message' => 'تعذّر الاتصال ببوابة الدفع.'];
        }
        $decoded = json_decode((string) $response, true);
        if ($status >= 400) {
            Logger::error('MyFatoorah: رد خطأ', ['status' => $status, 'body' => $decoded]);
            return ['ok' => false, 'message' => (string) ($decoded['Message'] ?? 'خطأ من بوابة الدفع.')];
        }
        return ['ok' => true, 'message' => 'ok', 'data' => is_array($decoded) ? $decoded : []];
    }
}
