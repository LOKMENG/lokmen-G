<?php
declare(strict_types=1);

namespace App\Payments;

use App\Logger;

/**
 * بوابة Moyasar — تدعم مدى و Apple Pay و STC Pay وبطاقات الائتمان.
 * تحتاج مفاتيح من .env:
 *   MOYASAR_ENABLED=true, MOYASAR_PUBLIC_KEY, MOYASAR_SECRET_KEY, MOYASAR_WEBHOOK_SECRET
 * الوثائق: https://docs.moyasar.com
 */
final class MoyasarGateway implements PaymentGateway
{
    public function code(): string
    {
        return 'moyasar';
    }

    public function isEnabled(): bool
    {
        return (bool) config('payments.moyasar.enabled', false)
            && trim((string) config('payments.moyasar.secret_key', '')) !== '';
    }

    public function label(): string
    {
        return 'مدى / Apple Pay / STC Pay / بطاقات (Moyasar)';
    }

    public function createPayment(array $payload): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'message' => 'بوابة Moyasar غير مفعّلة. فعّل المفاتيح في ملف .env أولاً.'];
        }
        $body = [
            'amount'      => (int) round($payload['amount'] * 100), // هللات
            'currency'    => $payload['currency'] ?? 'SAR',
            'description' => $payload['description'] ?? 'اشتراك المنصة',
            'callback_url' => $payload['callback_url'] ?? '',
            'publishable_api_key' => (string) config('payments.moyasar.public_key', ''),
            'metadata'    => ['reference' => $payload['reference'] ?? ''],
        ];
        $response = $this->request('POST', '/invoices', $body);
        if (!$response['ok']) {
            return ['ok' => false, 'message' => $response['message']];
        }
        $data = $response['data'] ?? [];
        return [
            'ok'             => true,
            'message'        => 'تم إنشاء فاتورة الدفع.',
            'redirect_url'   => (string) ($data['url'] ?? ''),
            'transaction_id' => (string) ($data['id'] ?? ''),
            'raw'            => $data,
        ];
    }

    public function verifyPayment(array $request): array
    {
        $invoiceId = (string) ($request['id'] ?? $request['invoice_id'] ?? '');
        if ($invoiceId === '') {
            return ['ok' => false, 'status' => 'pending', 'transaction_id' => '', 'amount' => 0, 'message' => 'معرّف الفاتورة مفقود.'];
        }
        $response = $this->request('GET', '/invoices/' . urlencode($invoiceId));
        if (!$response['ok']) {
            return ['ok' => false, 'status' => 'pending', 'transaction_id' => $invoiceId, 'amount' => 0, 'message' => $response['message']];
        }
        $data = $response['data'] ?? [];
        $status = (string) ($data['status'] ?? '');
        $amount = ((int) ($data['amount'] ?? 0)) / 100;
        return [
            'ok'             => $status === 'paid',
            'status'         => $status,
            'transaction_id' => $invoiceId,
            'amount'         => $amount,
            'message'        => $status === 'paid' ? 'تم الدفع بنجاح.' : 'الدفع لم يكتمل (الحالة: ' . $status . ').',
            'raw'            => $data,
        ];
    }

    /** التحقق من توقيع الـ Webhook (Moyasar يرسل secret_token) */
    public function verifyWebhookSignature(string $rawBody, string $signatureHeader): bool
    {
        $secret = trim((string) config('payments.moyasar.webhook_secret', ''));
        if ($secret === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $signatureHeader) || hash_equals($secret, $signatureHeader);
    }

    /** @return array{ok:bool,message:string,data?:array<string,mixed>} */
    private function request(string $method, string $path, array $body = []): array
    {
        $url = rtrim((string) config('payments.moyasar.base_url', 'https://api.moyasar.com/v1'), '/') . $path;
        $secret = (string) config('payments.moyasar.secret_key', '');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $secret . ':',
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            Logger::error('Moyasar: فشل الاتصال', ['error' => $error]);
            return ['ok' => false, 'message' => 'تعذّر الاتصال ببوابة الدفع.'];
        }
        $decoded = json_decode((string) $response, true);
        if ($status >= 400) {
            Logger::error('Moyasar: رد خطأ', ['status' => $status, 'body' => $decoded]);
            return ['ok' => false, 'message' => (string) ($decoded['message'] ?? 'خطأ من بوابة الدفع.')];
        }
        return ['ok' => true, 'message' => 'ok', 'data' => is_array($decoded) ? $decoded : []];
    }
}
