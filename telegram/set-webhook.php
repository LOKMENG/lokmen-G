<?php
/**
 * تسجيل أو حذف أو فحص ويبهوك البوت من سطر الأوامر.
 *
 *      php telegram/set-webhook.php            # تسجيل الويبهوك
 *      php telegram/set-webhook.php --info     # عرض حالة الويبهوك الحالية
 *      php telegram/set-webhook.php --delete   # حذف الويبهوك (للانتقال إلى polling)
 *
 * يتطلب أن يكون APP_URL في .env رابطاً عاماً بـ https (شرط تلجرام).
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

if (!is_cli()) {
    http_response_code(403);
    echo 'هذا الملف يعمل من سطر الأوامر فقط.';
    exit(1);
}

use App\Env;
use App\Services\TelegramService;

if (!TelegramService::enabled()) {
    fwrite(STDERR, "توكن البوت غير مضبوط في .env (TELEGRAM_BOT_TOKEN).\n");
    exit(1);
}

$service = TelegramService::instance();
$info = $service->api('getMe');
if (($info['ok'] ?? false) !== true) {
    fwrite(STDERR, 'تعذّر الاتصال بتلجرام: ' . (string) ($info['error'] ?? $info['description'] ?? 'خطأ غير معروف') . "\n");
    exit(1);
}
echo 'البوت: @' . (string) ($info['result']['username'] ?? '') . "\n";

if (cli_has_flag('info')) {
    $status = $service->getWebhookInfo();
    echo "حالة الويبهوك:\n" . json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

if (cli_has_flag('delete')) {
    $result = $service->deleteWebhook();
    echo ($result['ok'] ?? false) ? "تم حذف الويبهوك (يمكنك استخدام polling الآن).\n" : "فشل الحذف.\n";
    exit(($result['ok'] ?? false) ? 0 : 1);
}

$webhookUrl = rtrim((string) config('app.url', ''), '/') . '/telegram/webhook';
if (!str_starts_with($webhookUrl, 'https://')) {
    fwrite(STDERR, "تلجرام يشترط رابطاً عاماً بـ https. الرابط الحالي: {$webhookUrl}\n");
    fwrite(STDERR, "استخدم مؤقتاً: php telegram/poll.php\n");
    exit(1);
}

$secret = (string) config('telegram.webhook_secret', '');
if ($secret === '') {
    $secret = bin2hex(random_bytes(16));
    $envPath = BASE_PATH . '/.env';
    if (is_file($envPath) && Env::write($envPath, ['TELEGRAM_WEBHOOK_SECRET' => $secret], $envPath)) {
        Env::set('TELEGRAM_WEBHOOK_SECRET', $secret);
        echo "تم توليد سر الويبهوك وحفظه في .env\n";
    }
}

$result = $service->setWebhook($webhookUrl, $secret !== '' ? $secret : null);
if (($result['ok'] ?? false) === true) {
    echo "تم تسجيل الويبهوك على: {$webhookUrl}\n";
    exit(0);
}
fwrite(STDERR, 'فشل التسجيل: ' . (string) ($result['error'] ?? $result['description'] ?? '') . "\n");
exit(1);
