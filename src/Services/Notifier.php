<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Logger;

/**
 * الإشعارات: داخل المنصة + تلجرام (إن كان الحساب مربوطاً).
 * لا يُفشل أي إشعار العملية الأساسية إذا تعذّر إرساله.
 */
final class Notifier
{
    /** إشعار مستخدم: يُخزَّن في المنصة، ويُرسل على تلجرام إن كان مربوطاً */
    public static function toUser(int $userId, string $title, string $body = '', ?string $link = null, string $type = 'system'): void
    {
        try {
            Database::instance()->insert('notifications', [
                'user_id' => $userId,
                'type'    => $type,
                'title'   => $title,
                'body'    => $body,
                'link'    => $link,
            ]);
        } catch (\Throwable $e) {
            Logger::warning('تعذّر تخزين إشعار: ' . $e->getMessage());
        }

        if (!TelegramService::enabled()) {
            return;
        }
        $chatId = self::telegramChatId($userId);
        if ($chatId === null) {
            return;
        }
        $text = '<b>' . TelegramService::escape($title) . '</b>';
        if ($body !== '') {
            $text .= "\n" . TelegramService::escape($body);
        }
        if ($link !== null && $link !== '') {
            $text .= "\n\n🔗 " . TelegramService::escape($link);
        }
        TelegramService::instance()->sendMessage($chatId, $text, [
            'reply_markup' => json_encode(['inline_keyboard' => [[
                ['text' => 'فتح المنصة', 'url' => $link !== null && $link !== '' ? $link : (string) config('app.url')],
            ]]], JSON_UNESCAPED_UNICODE),
        ]);
    }

    /** إشعار كل المديرين (على تلجرام + داخل المنصة) */
    public static function toAdmins(string $title, string $body = ''): void
    {
        $adminChat = trim((string) config('telegram.admin_chat_id', ''));
        $chatIds = $adminChat !== '' ? [$adminChat] : [];

        if (TelegramService::enabled()) {
            foreach ($chatIds as $chatId) {
                TelegramService::instance()->sendMessage($chatId, '<b>' . TelegramService::escape($title) . "</b>\n" . TelegramService::escape($body));
            }
        }
        try {
            $admins = Database::instance()->column("SELECT id FROM `users` WHERE role = 'admin' AND status = 'active'");
            foreach ($admins as $adminId) {
                Database::instance()->insert('notifications', [
                    'user_id' => (int) $adminId,
                    'type'    => 'admin',
                    'title'   => $title,
                    'body'    => $body,
                    'link'    => \url('admin/index'),
                ]);
            }
        } catch (\Throwable $e) {
            Logger::warning('تعذّر إشعار الإدارة: ' . $e->getMessage());
        }
    }

    public static function telegramChatId(int $userId): ?int
    {
        try {
            $chatId = Database::instance()->value(
                'SELECT COALESCE(tl.telegram_chat_id, tl.telegram_user_id)
                   FROM `telegram_links` tl
                  WHERE tl.user_id = :id AND tl.is_blocked = 0 AND tl.telegram_user_id > 0
                  ORDER BY tl.id DESC LIMIT 1',
                ['id' => $userId]
            );
            return $chatId === null ? null : (int) $chatId;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function welcome(int $userId): void
    {
        $user = Database::instance()->one('SELECT full_name FROM `users` WHERE id = :id', ['id' => $userId]);
        $name = (string) ($user['full_name'] ?? '');
        self::toUser(
            $userId,
            'أهلاً بك في ' . (string) config('app.name'),
            'مرحباً ' . $name . " 👋\nتم إنشاء حسابك بنجاح. لتفعيل كامل المحتوى يلزم اشتراك بقيمة " . (int) settings('subscription_price', 100) . ' ريال.',
            \url('subscriptions/plans'),
            'subscription'
        );
    }

    public static function subscriptionSubmitted(int $userId, string $reference): void
    {
        self::toUser(
            $userId,
            'تم استلام طلب الاشتراك',
            'طلبك بانتظار التحقق من الدفع. رقم العملية: ' . $reference . "\nسيتم إشعارك فور التفعيل.",
            \url('subscriptions/status'),
            'subscription'
        );
        $user = Database::instance()->one('SELECT full_name, phone, email FROM `users` WHERE id = :id', ['id' => $userId]);
        self::toAdmins(
            'طلب اشتراك جديد بانتظار المراجعة',
            sprintf(
                "الطالب: %s\nالجوال: %s\nرقم العملية: %s\n\nراجع الطلبات: %s",
                (string) ($user['full_name'] ?? ''),
                (string) ($user['phone'] ?? ''),
                $reference,
                \url('admin/payments')
            )
        );
    }

    public static function subscriptionActivated(int $userId, ?string $expiresAt): void
    {
        self::toUser(
            $userId,
            'تم تفعيل اشتراكك ✅',
            'يمكنك الآن الوصول إلى بنك الأسئلة والاختبارات التجريبية.' . ($expiresAt !== null ? "\nينتهي الاشتراك في: " . \format_date($expiresAt) : ''),
            \url('student/dashboard'),
            'subscription'
        );
    }

    public static function subscriptionExpiring(int $userId, int $days): void
    {
        self::toUser(
            $userId,
            'اشتراكك على وشك الانتهاء',
            'يتبقى ' . $days . ' يوم على انتهاء اشتراكك. جدّد الآن لتستمر في التدريب.',
            \url('subscriptions/plans'),
            'subscription'
        );
    }

    public static function subscriptionExpired(int $userId): void
    {
        self::toUser(
            $userId,
            'انتهى اشتراكك',
            'انتهى اشتراكك الحالي. يمكنك التجديد من صفحة الباقات للاستمرار في التدريب.',
            \url('subscriptions/plans'),
            'subscription'
        );
    }

    public static function paymentReviewed(int $userId, bool $approved, string $note = ''): void
    {
        self::toUser(
            $userId,
            $approved ? 'تم قبول الدفع' : 'تم رفض إثبات الدفع',
            $note !== '' ? $note : ($approved ? 'شكراً لك، تم تفعيل اشتراكك.' : 'يرجى التأكد من بيانات التحويل والمحاولة مرة أخرى.'),
            \url('subscriptions/status'),
            'subscription'
        );
    }

    public static function supportReply(int $userId, string $reply): void
    {
        self::toUser($userId, 'ردّ الدعم الفني', $reply, \url('student/support'), 'support');
    }

    /** @return array<int,array<string,mixed>> */
    public static function forUser(int $userId, int $limit = 10): array
    {
        try {
            return Database::instance()->all(
                'SELECT * FROM `notifications` WHERE user_id = :id ORDER BY id DESC LIMIT ' . max(1, $limit),
                ['id' => $userId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public static function unreadCount(int $userId): int
    {
        try {
            return (int) Database::instance()->value(
                'SELECT COUNT(*) FROM `notifications` WHERE user_id = :id AND is_read = 0',
                ['id' => $userId],
                0
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function markAllRead(int $userId): void
    {
        Database::instance()->update('notifications', ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')], 'user_id = :id AND is_read = 0', ['id' => $userId]);
    }
}
