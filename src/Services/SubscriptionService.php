<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Logger;
use App\Security;

/**
 * نظام الاشتراك المدفوع: pending → approved → active → expired
 * القيمة الأساسية 100 ريال سعودي (قابلة للتعديل من الإعدادات أو من الباقة).
 */
final class SubscriptionService
{
    private static ?array $currentCache = [];

    // ------------------------------------------------------------------
    //  الباقات والاشتراك الحالي
    // ------------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public static function plans(bool $onlyActive = true): array
    {
        $sql = 'SELECT * FROM `subscription_plans`' . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order, id';
        return Database::instance()->all($sql);
    }

    /** @return array<string,mixed>|null */
    public static function plan(int $planId): ?array
    {
        return Database::instance()->one('SELECT * FROM `subscription_plans` WHERE id = :id', ['id' => $planId]);
    }

    /**
     * الاشتراك الأكثر ملاءمة للعرض: النشط غير المنتهي، وإلا آخر اشتراك.
     * @return array<string,mixed>|null
     */
    public static function current(?int $userId = null): ?array
    {
        $userId ??= \user_id();
        if ($userId === null) {
            return null;
        }
        if (array_key_exists($userId, self::$currentCache) && !\is_post()) {
            return self::$currentCache[$userId];
        }
        $row = Database::instance()->one(
            'SELECT s.*, p.name_ar AS plan_name, p.code AS plan_code, p.duration_days AS plan_days
               FROM `subscriptions` s
               JOIN `subscription_plans` p ON p.id = s.plan_id
              WHERE s.user_id = :id
              ORDER BY (s.status = "active" AND (s.expires_at IS NULL OR s.expires_at > NOW())) DESC,
                       s.expires_at DESC, s.id DESC
              LIMIT 1',
            ['id' => $userId]
        );
        self::$currentCache[$userId] = $row;
        return $row;
    }

    public static function isActive(?int $userId = null): bool
    {
        $current = self::current($userId);
        if ($current === null) {
            return false;
        }
        return self::rowIsActive($current);
    }

    /** @param array<string,mixed> $row */
    public static function rowIsActive(array $row): bool
    {
        if (($row['status'] ?? '') !== 'active') {
            return false;
        }
        if (empty($row['expires_at'])) {
            return true;
        }
        return strtotime((string) $row['expires_at']) > time();
    }

    public static function status(?int $userId = null): string
    {
        $current = self::current($userId);
        if ($current === null) {
            return 'none';
        }
        if (self::rowIsActive($current)) {
            return 'active';
        }
        if ($current['status'] === 'active' && !empty($current['expires_at']) && strtotime((string) $current['expires_at']) <= time()) {
            return 'expired';
        }
        return (string) $current['status'];
    }

    public static function daysRemaining(?int $userId = null): int
    {
        $current = self::current($userId);
        if ($current === null || empty($current['expires_at'])) {
            return 0;
        }
        return max(0, \days_between(date('Y-m-d H:i:s'), (string) $current['expires_at']));
    }

    /** هل يملك الطالب اشتراكاً فعّالاً؟ إن لا، يُحوَّل لصفحة الباقات برسالة واضحة */
    public static function requireActive(string $redirectBack = ''): void
    {
        if (\is_admin()) {
            return; // الإدارة تستعرض المحتوى دائماً
        }
        if (self::isActive()) {
            return;
        }
        \flash('warning', 'هذه الميزة تتطلب اشتراكاً فعّالاً. يمكنك الاشتراك الآن بمبلغ ' . \money((float) \settings('subscription_price', 100)) . ' فقط.');
        \redirect($redirectBack !== '' ? $redirectBack : 'subscriptions/plans');
    }

    /** @return array<int,int>|null المسارات المسموح بها (null = كل المسارات) */
    public static function allowedTrackIds(?int $userId = null): ?array
    {
        $current = self::current($userId);
        if ($current === null) {
            return null;
        }
        $tracks = json_decode((string) ($current['track_ids'] ?? '[]'), true);
        if (!is_array($tracks) || $tracks === []) {
            return null;
        }
        return array_map('intval', $tracks);
    }

    // ------------------------------------------------------------------
    //  إنشاء طلب اشتراك + دفعة
    // ------------------------------------------------------------------

    /**
     * @param array<string,mixed> $payload {method, reference_number, payer_note, receipt_path, gateway, gateway_txn_id, gateway_payload}
     * @return array{ok:bool,message:string,subscription_id?:int,payment_id?:int}
     */
    public static function createRequest(int $userId, int $planId, array $payload): array
    {
        $db = Database::instance();
        $plan = self::plan($planId);
        if ($plan === null || (int) $plan['is_active'] !== 1) {
            return ['ok' => false, 'message' => 'الباقة المختارة غير متاحة.'];
        }
        if (!self::methodEnabled((string) ($payload['method'] ?? 'bank_transfer'))) {
            return ['ok' => false, 'message' => 'طريقة الدفع المختارة غير مفعّلة حالياً.'];
        }

        try {
            return $db->transaction(function (Database $db) use ($userId, $plan, $payload): array {
                $subscriptionId = $db->insert('subscriptions', [
                    'user_id'       => $userId,
                    'plan_id'       => (int) $plan['id'],
                    'status'        => 'pending',
                    'amount'        => (float) $plan['price_sar'],
                    'currency'      => 'SAR',
                    'duration_days' => (int) $plan['duration_days'],
                ]);

                $paymentId = $db->insert('payments', [
                    'subscription_id'  => $subscriptionId,
                    'user_id'          => $userId,
                    'method'           => (string) ($payload['method'] ?? 'bank_transfer'),
                    'gateway'          => (string) ($payload['gateway'] ?? 'manual'),
                    'amount'           => (float) $plan['price_sar'],
                    'currency'         => 'SAR',
                    'reference_number' => $payload['reference_number'] ?? null,
                    'payer_note'       => $payload['payer_note'] ?? null,
                    'receipt_path'     => $payload['receipt_path'] ?? null,
                    'gateway_txn_id'   => $payload['gateway_txn_id'] ?? null,
                    'gateway_payload'  => isset($payload['gateway_payload']) ? json_encode($payload['gateway_payload'], JSON_UNESCAPED_UNICODE) : null,
                    'status'           => 'pending',
                ]);

                \audit('subscription.requested', 'subscription', $subscriptionId, [
                    'plan'    => $plan['code'],
                    'method'  => $payload['method'] ?? 'bank_transfer',
                    'payment' => $paymentId,
                ]);

                return ['ok' => true, 'message' => 'تم تسجيل طلب الاشتراك.', 'subscription_id' => $subscriptionId, 'payment_id' => $paymentId];
            });
        } catch (\Throwable $e) {
            Logger::error('تعذّر إنشاء طلب اشتراك: ' . $e->getMessage(), ['user_id' => $userId, 'plan' => $planId]);
            return ['ok' => false, 'message' => 'تعذّر تسجيل الطلب، حاول مرة أخرى.'];
        }
    }

    public static function methodEnabled(string $method): bool
    {
        $payments = (array) config('payments', []);
        return match ($method) {
            'bank_transfer', 'cash', 'stc_pay', 'admin_manual' => (bool) ($payments['manual']['enabled'] ?? true),
            'moyasar', 'mada', 'apple_pay'                     => (bool) ($payments['moyasar']['enabled'] ?? false),
            'myfatoorah'                                       => (bool) ($payments['myfatoorah']['enabled'] ?? false),
            'free'                                             => \setting_bool('free_trial_enabled'),
            default                                            => false,
        };
    }

    // ------------------------------------------------------------------
    //  موافقة / رفض الدفع
    // ------------------------------------------------------------------

    /** موافقة الإدارة على الدفع وتفعيل الاشتراك */
    public static function approvePayment(int $paymentId, int $adminId, string $note = '', bool $activateNow = true): array
    {
        $db = Database::instance();
        $payment = $db->one('SELECT * FROM `payments` WHERE id = :id', ['id' => $paymentId]);
        if ($payment === null) {
            return ['ok' => false, 'message' => 'الدفعة غير موجودة.'];
        }
        if ($payment['status'] === 'approved') {
            return ['ok' => false, 'message' => 'تمت الموافقة على هذه الدفعة مسبقاً.'];
        }

        $db->transaction(function (Database $db) use ($payment, $adminId, $note, $activateNow): void {
            $db->update('payments', [
                'status'      => 'approved',
                'reviewed_by' => $adminId,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'admin_note'  => $note !== '' ? $note : null,
            ], 'id = :id', ['id' => (int) $payment['id']]);

            if ($activateNow) {
                self::activate((int) $payment['subscription_id'], $adminId);
            } else {
                $db->update('subscriptions', ['status' => 'approved', 'approved_by' => $adminId, 'approved_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $payment['subscription_id']]);
            }
        });

        \audit('payment.approved', 'payment', $paymentId, ['user_id' => (int) $payment['user_id']]);
        Notifier::paymentReviewed((int) $payment['user_id'], true, $note);
        if ($activateNow) {
            $subscription = $db->one('SELECT expires_at FROM `subscriptions` WHERE id = :id', ['id' => (int) $payment['subscription_id']]);
            Notifier::subscriptionActivated((int) $payment['user_id'], $subscription['expires_at'] ?? null);
        }
        return ['ok' => true, 'message' => 'تم قبول الدفع وتفعيل الاشتراك.'];
    }

    public static function rejectPayment(int $paymentId, int $adminId, string $reason = ''): array
    {
        $db = Database::instance();
        $payment = $db->one('SELECT * FROM `payments` WHERE id = :id', ['id' => $paymentId]);
        if ($payment === null) {
            return ['ok' => false, 'message' => 'الدفعة غير موجودة.'];
        }
        $db->transaction(function (Database $db) use ($payment, $adminId, $reason): void {
            $db->update('payments', [
                'status'      => 'rejected',
                'reviewed_by' => $adminId,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'admin_note'  => $reason !== '' ? $reason : 'لم يتم التحقق من الدفع',
            ], 'id = :id', ['id' => (int) $payment['id']]);

            $db->update('subscriptions', [
                'status'           => 'rejected',
                'rejection_reason' => $reason !== '' ? $reason : 'لم يتم التحقق من الدفع',
            ], 'id = :id', ['id' => (int) $payment['subscription_id']]);
        });

        \audit('payment.rejected', 'payment', $paymentId, ['reason' => $reason]);
        Notifier::paymentReviewed((int) $payment['user_id'], false, $reason);
        return ['ok' => true, 'message' => 'تم رفض الدفعة.'];
    }

    /** تفعيل الاشتراك: يبدأ من تاريخ اليوم أو من نهاية الاشتراك الحالي (تراكمي) */
    public static function activate(int $subscriptionId, int $adminId): array
    {
        $db = Database::instance();
        $subscription = $db->one('SELECT * FROM `subscriptions` WHERE id = :id', ['id' => $subscriptionId]);
        if ($subscription === null) {
            return ['ok' => false, 'message' => 'الاشتراك غير موجود.'];
        }
        $userId = (int) $subscription['user_id'];
        $days = (int) $subscription['duration_days'] + (int) $subscription['extended_days'];

        $active = $db->one(
            "SELECT MAX(expires_at) AS max_expiry FROM `subscriptions`
              WHERE user_id = :id AND status = 'active' AND expires_at IS NOT NULL AND expires_at > NOW() AND id <> :current",
            ['id' => $userId, 'current' => $subscriptionId]
        );
        $base = (!empty($active['max_expiry']) && strtotime((string) $active['max_expiry']) > time())
            ? (string) $active['max_expiry']
            : date('Y-m-d H:i:s');

        $db->update('subscriptions', [
            'status'      => 'active',
            'started_at'  => date('Y-m-d H:i:s'),
            'expires_at'  => date('Y-m-d H:i:s', strtotime($base . ' +' . $days . ' days')),
            'approved_at' => $subscription['approved_at'] ?? date('Y-m-d H:i:s'),
            'approved_by' => $adminId,
        ], 'id = :id', ['id' => $subscriptionId]);

        self::$currentCache = [];
        \audit('subscription.activated', 'subscription', $subscriptionId, ['user_id' => $userId, 'days' => $days]);
        return ['ok' => true, 'message' => 'تم تفعيل الاشتراك.'];
    }

    public static function extend(int $subscriptionId, int $days, int $adminId): array
    {
        if ($days === 0) {
            return ['ok' => false, 'message' => 'عدد الأيام غير صحيح.'];
        }
        $db = Database::instance();
        $subscription = $db->one('SELECT * FROM `subscriptions` WHERE id = :id', ['id' => $subscriptionId]);
        if ($subscription === null) {
            return ['ok' => false, 'message' => 'الاشتراك غير موجود.'];
        }
        $base = (!empty($subscription['expires_at']) && strtotime((string) $subscription['expires_at']) > time())
            ? (string) $subscription['expires_at']
            : date('Y-m-d H:i:s');
        $newExpiry = date('Y-m-d H:i:s', strtotime($base . ' ' . ($days >= 0 ? '+' : '-') . abs($days) . ' days'));

        $db->update('subscriptions', [
            'expires_at'    => $newExpiry,
            'extended_days' => (int) $subscription['extended_days'] + $days,
            'status'        => $newExpiry > date('Y-m-d H:i:s') ? 'active' : $subscription['status'],
        ], 'id = :id', ['id' => $subscriptionId]);

        self::$currentCache = [];
        \audit('subscription.extended', 'subscription', $subscriptionId, ['days' => $days, 'admin' => $adminId]);
        return ['ok' => true, 'message' => 'تم تمديد الاشتراك حتى ' . \format_date($newExpiry)];
    }

    public static function cancel(int $subscriptionId, int $adminId, string $reason = ''): array
    {
        Database::instance()->update('subscriptions', [
            'status'           => 'cancelled',
            'cancelled_at'     => date('Y-m-d H:i:s'),
            'rejection_reason' => $reason !== '' ? $reason : null,
        ], 'id = :id', ['id' => $subscriptionId]);
        self::$currentCache = [];
        \audit('subscription.cancelled', 'subscription', $subscriptionId, ['reason' => $reason, 'admin' => $adminId]);
        return ['ok' => true, 'message' => 'تم إلغاء الاشتراك.'];
    }

    // ------------------------------------------------------------------
    //  مهام دورية (Cron)
    // ------------------------------------------------------------------

    /** تحويل الاشتراكات المنتهية إلى expired + إشعار الطلاب */
    public static function expireDue(): int
    {
        $db = Database::instance();
        $rows = $db->all(
            "SELECT id, user_id FROM `subscriptions`
              WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at < NOW()"
        );
        foreach ($rows as $row) {
            $db->update('subscriptions', ['status' => 'expired'], 'id = :id', ['id' => (int) $row['id']]);
            Notifier::subscriptionExpired((int) $row['user_id']);
        }
        if ($rows !== []) {
            Logger::info('تم إنهاء ' . count($rows) . ' اشتراك منتهي');
        }
        return count($rows);
    }

    /** إشعار الطلاب الذين سينتهي اشتراكهم خلال عدد أيام محدد */
    public static function notifyExpiring(?int $days = null): int
    {
        $days ??= (int) \settings('telegram_notify_expiry_days', 7);
        $db = Database::instance();
        $rows = $db->all(
            "SELECT id, user_id, expires_at FROM `subscriptions`
              WHERE status = 'active'
                AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL :days DAY)
                AND (reminder_sent_at IS NULL OR reminder_sent_at < DATE_SUB(NOW(), INTERVAL 3 DAY))",
            ['days' => $days]
        );
        foreach ($rows as $row) {
            Notifier::subscriptionExpiring((int) $row['user_id'], (int) \days_between(date('Y-m-d H:i:s'), (string) $row['expires_at']));
            $db->update('subscriptions', ['reminder_sent_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $row['id']]);
        }
        return count($rows);
    }

    // ------------------------------------------------------------------
    //  إحصائيات
    // ------------------------------------------------------------------

    /** @return array<string,int|float> */
    public static function statistics(): array
    {
        $db = Database::instance();
        return [
            'active'      => (int) $db->value("SELECT COUNT(DISTINCT user_id) FROM `subscriptions` WHERE status = 'active' AND (expires_at IS NULL OR expires_at > NOW())", [], 0),
            'pending'     => (int) $db->value("SELECT COUNT(*) FROM `subscriptions` WHERE status = 'pending'", [], 0),
            'expired'     => (int) $db->value("SELECT COUNT(*) FROM `subscriptions` WHERE status = 'expired'", [], 0),
            'rejected'    => (int) $db->value("SELECT COUNT(*) FROM `subscriptions` WHERE status = 'rejected'", [], 0),
            'revenue'     => (float) $db->value("SELECT COALESCE(SUM(amount), 0) FROM `payments` WHERE status = 'approved'", [], 0),
            'revenue_month' => (float) $db->value("SELECT COALESCE(SUM(amount), 0) FROM `payments` WHERE status = 'approved' AND reviewed_at >= DATE_FORMAT(NOW(), '%Y-%m-01')", [], 0),
            'pending_payments' => (int) $db->value("SELECT COUNT(*) FROM `payments` WHERE status = 'pending'", [], 0),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function expiringSoon(int $days = 14): array
    {
        return Database::instance()->all(
            "SELECT s.*, u.full_name, u.phone, p.name_ar AS plan_name
               FROM `subscriptions` s
               JOIN `users` u ON u.id = s.user_id
               JOIN `subscription_plans` p ON p.id = s.plan_id
              WHERE s.status = 'active' AND s.expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL :days DAY)
              ORDER BY s.expires_at ASC",
            ['days' => $days]
        );
    }
}
