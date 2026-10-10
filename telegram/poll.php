<?php
/**
 * جلب تحديثات تلجرام بالاستقصاء (Polling) — بديل الويبهوك لعاملي XAMPP المحليين
 * الذين لا يملكون رابط HTTPS عام.
 *
 * التشغيل من سطر الأوامر:
 *      php telegram/poll.php                 # حلقة مستمرة (أوقفه بـ Ctrl+C)
 *      php telegram/poll.php --once          # جلب تحديثات واحدة ثم الخروج
 *      php telegram/poll.php --minutes=10    # التشغيل لمدة 10 دقائق
 *
 * ملاحظة: لا تُستخدم هذه الطريقة مع الويبهوك في نفس الوقت، وإلا فقد تُفقد التحديثات.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

if (!is_cli()) {
    http_response_code(403);
    echo 'هذا الملف يعمل من سطر الأوامر فقط.';
    exit(1);
}

use App\Logger;
use App\Services\TelegramCommandHandler;
use App\Services\TelegramService;

$once = cli_has_flag('once');
$minutes = (int) cli_option('minutes', '0');

if (!TelegramService::enabled()) {
    fwrite(STDERR, "توكن البوت غير مضبوط في .env (TELEGRAM_BOT_TOKEN) — لا يمكن التشغيل.\n");
    exit(1);
}

$service = TelegramService::instance();
$handler = new TelegramCommandHandler();
$offset = (int) settings('telegram_update_offset', 0);
$startedAt = time();
$deadline = $minutes > 0 ? $startedAt + ($minutes * 60) : 0;

echo "بدء استقبال تحديثات تلجرام (polling). للإيقاف: Ctrl+C\n";

while (true) {
    try {
        $updates = $service->getUpdates($offset, 20);
    } catch (Throwable $error) {
        Logger::error('telegram.poll: ' . $error->getMessage());
        sleep(5);
        continue;
    }

    foreach ($updates as $update) {
        $offset = max($offset, (int) ($update['update_id'] ?? 0) + 1);
        try {
            $handler->handle($update);
        } catch (Throwable $error) {
            Logger::error('telegram.poll.handle: ' . $error->getMessage(), ['update_id' => $update['update_id'] ?? null]);
        }
    }

    if ($updates !== []) {
        try {
            db()->query(
                'INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_group`, `setting_type`)
                 VALUES (:key, :value, :group, :type)
                 ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)',
                ['key' => 'telegram_update_offset', 'value' => (string) $offset, 'group' => 'telegram', 'type' => 'int']
            );
        } catch (Throwable $error) {
            Logger::warning('تعذّر حفظ موضع التحديثات: ' . $error->getMessage());
        }
        echo 'تمت معالجة ' . count($updates) . " تحديثاً\n";
    }

    if ($once) {
        break;
    }
    if ($deadline > 0 && time() >= $deadline) {
        break;
    }
    usleep(700000);
}

echo "توقف الاستقبال. آخر موضع: {$offset}\n";
