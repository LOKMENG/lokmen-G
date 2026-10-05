<?php
declare(strict_types=1);

namespace App\Payments;

/**
 * واجهة بوابات الدفع السعودية.
 * أي بوابة جديدة (مدى/Apple Pay/STC Pay عبر Moyasar أو MyFatoorah أو Tap) تُنفّذ هذه الواجهة
 * وتُسجَّل في PaymentManager دون تغيير باقي النظام.
 */
interface PaymentGateway
{
    /** رمز البوابة كما يُخزَّن في جدول payments.gateway */
    public function code(): string;

    /** هل البوابة مفعّلة وبياناتها مكتملة؟ */
    public function isEnabled(): bool;

    /** الاسم الظاهر للعميل */
    public function label(): string;

    /**
     * إنشاء عملية دفع.
     * @param array{amount:float,currency:string,description:string,reference:string,callback_url:string,user:array<string,mixed>} $payload
     * @return array{ok:bool,redirect_url?:string,transaction_id?:string,message:string,raw?:mixed}
     */
    public function createPayment(array $payload): array;

    /**
     * التحقق من عملية دفع بعد رجوع العميل أو من Webhook.
     * @param array<string,mixed> $request
     * @return array{ok:bool,status:string,transaction_id:string,amount:float,message:string,raw?:mixed}
     */
    public function verifyPayment(array $request): array;
}
