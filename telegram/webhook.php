<?php
/**
 * نقطة استقبال تحديثات تلجرام (Webhook).
 *
 * الإعداد (من لوحة الإدارة → تلجرام، أو من CLI: php telegram/set-webhook.php):
 *   https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://مثال.com/telegram/webhook&secret_token=<SECRET>
 *
 * ملاحظات أمنية:
 *  - يتم التحقق من ترويسة X-Telegram-Bot-Api-Secret-Token إن كان السر مضبوطاً في .env.
 *  - الطلبات من نوع POST فقط، والحجم محدود، ولا يُعاد أي بيانات حساسة في الرد.
 *  - لا تُنشأ جلسة ولا يُتحقق CSRF لأن المصدر خادم تلجرام وليس متصفحاً.
 */
declare(strict_types=1);

define('APP_NO_SESSION', true);
define('APP_CSRF_EXEMPT', true);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Logger;
use App\Services\TelegramCommandHandler;
use App\Services\TelegramService;

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('طريقة غير مسموحة');
}

$secret = trim((string) config('telegram.webhook_secret', ''));
if ($secret !== '') {
    $provided = (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
    if ($provided === '' || !hash_equals($secret, $provided)) {
        Logger::security('ويبهوك تلجرام: ترويسة سر غير صحيحة', ['ip' => App\Security::ip()]);
        http_response_code(403);
        exit('ممنوع');
    }
}

$raw = (string) file_get_contents('php://input');
if ($raw === '' || strlen($raw) > 524288) { // 512KB حد أعلى واقٍ
    http_response_code(400);
    exit('طلب غير صالح');
}

$update = json_decode($raw, true);
if (!is_array($update)) {
    http_response_code(400);
    exit('JSON غير صالح');
}

// الرد فوراً على تلجرام (كي لا يعيد الإرسال) ثم المعالجة
http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
echo 'ok';
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ignore_user_abort(true);
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
}

if (!TelegramService::enabled()) {
    Logger::telegram('تم استلام تحديث وتجاهله: توكن البوت غير مضبوط في .env', ['update_id' => $update['update_id'] ?? null]);
    exit;
}

try {
    (new TelegramCommandHandler())->handle($update);
} catch (Throwable $error) {
    Logger::error('فشل معالجة تحديث تلجرام: ' . $error->getMessage(), ['update_id' => $update['update_id'] ?? null]);
}
