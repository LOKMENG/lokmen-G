<?php
/** إعدادات بوت تلجرام: التوكن (.env)، الربط، الويبهوك، والاختبار */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Env;
use App\Services\TelegramService;
use App\Settings;
use App\View;

$admin = auth()->requireAdmin();
$envPath = BASE_PATH . '/.env';

if (is_post()) {
    $action = (string) post('action', '');
    $ok = true;
    $message = '';

    switch ($action) {
        case 'save_env':
            $token = trim((string) post('TELEGRAM_BOT_TOKEN'));
            $username = trim((string) post('TELEGRAM_BOT_USERNAME'));
            if ($token !== '' && preg_match('/^\d{6,}:[A-Za-z0-9_-]{20,}$/', $token) !== 1) {
                $ok = false;
                $message = 'صيغة التوكن غير صحيحة. التوكن يبدأ بأرقام ثم «:» ثم حروف/أرقام (مثال: 123456789:AA...).';
                break;
            }
            if (!is_writable($envPath)) {
                $ok = false;
                $message = 'ملف .env غير قابل للكتابة من الخادم. عدّل الملف يدوياً وأضف TELEGRAM_BOT_TOKEN.';
                break;
            }
            $written = Env::write($envPath, [
                'TELEGRAM_BOT_TOKEN'    => $token,
                'TELEGRAM_BOT_USERNAME' => ltrim($username, '@'),
            ], $envPath);
            if (!$written) {
                $ok = false;
                $message = 'تعذّرت الكتابة في ملف .env.';
                break;
            }
            Env::set('TELEGRAM_BOT_TOKEN', $token);
            Env::set('TELEGRAM_BOT_USERNAME', ltrim($username, '@'));
            if (ltrim($username, '@') !== '') {
                Settings::set('telegram_bot_username', ltrim($username, '@'));
            }
            audit('admin.telegram_env_updated', 'settings', null, ['token_set' => $token !== '']);
            $message = 'تم حفظ إعدادات البوت في ملف .env.';
            break;

        case 'save_settings':
            Settings::set('telegram_enabled', (int) post('telegram_enabled', 0) === 1 ? '1' : '0');
            Settings::set('telegram_notify_expiry_days', max(1, min(60, (int) post('telegram_notify_expiry_days', 7))));
            if (trim((string) post('telegram_bot_username', '')) !== '') {
                Settings::set('telegram_bot_username', ltrim(trim((string) post('telegram_bot_username')), '@'));
            }
            audit('admin.telegram_settings', 'settings', null);
            $message = 'تم حفظ إعدادات الإشعارات.';
            break;

        case 'set_webhook':
            if (!TelegramService::enabled()) {
                $ok = false;
                $message = 'أضف توكن البوت أولاً.';
                break;
            }
            $webhookUrl = url('telegram/webhook');
            if (!str_starts_with($webhookUrl, 'https://')) {
                $ok = false;
                $message = 'تلجرام يتطلب رابط ويبهوك HTTPS عام. للتجربة المحلية استخدم وضع getUpdates أو أنفق نطاقاً (ngrok مثلاً).';
                break;
            }
            $secret = (string) config('telegram.webhook_secret', '');
            if ($secret === '') {
                $secret = bin2hex(random_bytes(16));
                if (is_writable($envPath)) {
                    Env::write($envPath, ['TELEGRAM_WEBHOOK_SECRET' => $secret], $envPath);
                    Env::set('TELEGRAM_WEBHOOK_SECRET', $secret);
                }
            }
            $result = TelegramService::instance()->setWebhook($webhookUrl, $secret !== '' ? $secret : null);
            $ok = (bool) ($result['ok'] ?? false);
            $message = $ok
                ? 'تم تسجيل الويبهوك على: ' . $webhookUrl
                : 'تعذّر تسجيل الويبهوك: ' . (string) ($result['description'] ?? 'خطأ غير معروف');
            audit('admin.telegram_webhook', 'settings', null, ['ok' => $ok]);
            break;

        case 'delete_webhook':
            $result = TelegramService::instance()->deleteWebhook();
            $ok = (bool) ($result['ok'] ?? false);
            $message = $ok ? 'تم حذف الويبهوك.' : 'تعذّر حذف الويبهوك: ' . (string) ($result['description'] ?? '');
            break;

        case 'test':
            $chatId = trim((string) post('test_chat_id'));
            if ($chatId === '') {
                $ok = false;
                $message = 'أدخل معرّف المحادثة (chat id) للاختبار.';
                break;
            }
            $result = TelegramService::instance()->sendMessage($chatId, "✅ رسالة اختبار من " . (string) settings('site_name', 'لوحة المعلم') . "\nالبوت يعمل بشكل صحيح.");
            $ok = (bool) ($result['ok'] ?? false);
            $message = $ok ? 'تم إرسال رسالة الاختبار بنجاح.' : 'فشل الإرسال: ' . (string) ($result['description'] ?? '');
            break;

        case 'unlink':
            $linkId = (int) post('link_id', 0);
            db()->update('telegram_links', ['user_id' => null, 'linked_at' => null, 'link_code' => null, 'link_code_expires_at' => null], 'id = :id', ['id' => $linkId]);
            audit('admin.telegram_unlinked', 'telegram_link', $linkId);
            $message = 'تم فك ربط الحساب.';
            break;

        case 'block':
            $linkId = (int) post('link_id', 0);
            $blocked = (int) post('blocked', 0) === 1 ? 1 : 0;
            db()->update('telegram_links', ['is_blocked' => $blocked], 'id = :id', ['id' => $linkId]);
            $message = $blocked === 1 ? 'تم حظر البوت لهذا المستخدم.' : 'تم إلغاء الحظر.';
            break;
    }

    flash($ok ? 'success' : 'danger', $message);
    redirect('admin/telegram');
}

$webhookInfo = [];
$botInfo = [];
$apiError = '';
if (TelegramService::enabled()) {
    $webhookInfo = TelegramService::instance()->getWebhookInfo();
    $botInfo = TelegramService::instance()->getMe();
    if (!(bool) ($botInfo['ok'] ?? false)) {
        $apiError = (string) ($botInfo['description'] ?? $botInfo['error'] ?? '');
    }
}

View::render('admin/telegram', [
    'title'      => 'بوت تلجرام',
    'pageSub'    => 'الربط والإشعارات وأوامر البوت',
    'activeMenu' => 'admin/telegram',
    'token'      => (string) config('telegram.token', ''),
    'envWritable'=> is_writable($envPath),
    'envPath'    => $envPath,
    'enabled'    => TelegramService::enabled(),
    'webhookInfo'=> $webhookInfo,
    'botInfo'    => $botInfo,
    'apiError'   => $apiError,
    'webhookUrl' => url('telegram/webhook'),
    'username'   => (string) settings('telegram_bot_username', (string) config('telegram.username', '')),
    'links'      => db()->paginate(
        "SELECT tl.*, u.full_name, u.email
           FROM `telegram_links` tl
      LEFT JOIN `users` u ON u.id = tl.user_id
          ORDER BY (tl.user_id IS NOT NULL) DESC, tl.id DESC",
        [],
        20,
        max(1, (int) query('page', 1))
    ),
    'stats'      => [
        'total'   => (int) db()->value('SELECT COUNT(*) FROM `telegram_links`', [], 0),
        'linked'  => (int) db()->value('SELECT COUNT(*) FROM `telegram_links` WHERE user_id IS NOT NULL', [], 0),
        'blocked' => (int) db()->value('SELECT COUNT(*) FROM `telegram_links` WHERE is_blocked = 1', [], 0),
        'states'  => (int) db()->value("SELECT COUNT(*) FROM `telegram_states` WHERE state <> 'idle'", [], 0),
    ],
], 'layouts/app');
