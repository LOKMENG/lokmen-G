<?php
declare(strict_types=1);

namespace App\Payments;

/**
 * مدير بوابات الدفع: يسجّل البوابات المتاحة ويوجّه العمليات.
 */
final class PaymentManager
{
    /** @return array<string,PaymentGateway> */
    public static function gateways(): array
    {
        $gateways = [
            'manual'     => new ManualGateway(),
            'moyasar'    => new MoyasarGateway(),
            'myfatoorah' => new MyFatoorahGateway(),
        ];
        $enabled = [];
        foreach ($gateways as $code => $gateway) {
            if ($gateway->isEnabled()) {
                $enabled[$code] = $gateway;
            }
        }
        return $enabled;
    }

    /** @return array<string,string> code => label */
    public static function options(): array
    {
        $options = [];
        foreach (self::gateways() as $code => $gateway) {
            $options[$code] = $gateway->label();
        }
        return $options;
    }

    public static function get(string $code): ?PaymentGateway
    {
        return self::gateways()[$code] ?? null;
    }

    /** اسم طريقة الدفع الظاهر في الفواتير */
    public static function methodLabel(string $method): string
    {
        return match ($method) {
            'bank_transfer' => 'تحويل بنكي',
            'stc_pay'       => 'STC Pay',
            'mada'          => 'مدى',
            'apple_pay'     => 'Apple Pay',
            'moyasar'       => 'Moyasar',
            'myfatoorah'    => 'MyFatoorah',
            'cash'          => 'نقداً',
            'admin_manual'  => 'تفعيل إداري',
            'free'          => 'مجاني',
            default          => $method,
        };
    }
}
