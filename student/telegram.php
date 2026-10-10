<?php
/** ربط حساب المنصة بحساب تلجرام عبر رمز مؤقت */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Security;
use App\Services\TelegramService;
use App\View;

$user = auth()->requireLogin();
$userId = (int) $user['id'];

if (is_post()) {
    $action = (string) post('action', '');

    if ($action === 'generate') {
        $code = Security::linkCode(8);
        // يُخزَّن الرمز مشفّراً (SHA-256) ولا يُحفظ نصاً صريحاً، ويُستهلك مرة واحدة فقط
        db()->query('DELETE FROM `telegram_link_codes` WHERE user_id = :u', ['u' => $userId]);
        db()->insert('telegram_link_codes', [
            'user_id'    => $userId,
            'code_hash'  => hash('sha256', $code),
            'code_hint'  => substr($code, -4),
            'expires_at' => date('Y-m-d H:i:s', time() + 1800),
        ]);
        $_SESSION['telegram_link_code'] = $code; // يُعرض مرة واحدة في الصفحة التالية
        audit('telegram.link_code_created', 'user', $userId);
        flash('success', 'تم إنشاء رمز الربط. أرسله للبوت خلال 30 دقيقة ولا تشاركه مع أحد.');
        redirect('student/telegram');
    }

    if ($action === 'unlink') {
        db()->delete('telegram_links', 'user_id = :u', ['u' => $userId]);
        db()->update('users', ['telegram_chat_id' => null], 'id = :id', ['id' => $userId]);
        audit('telegram.unlinked', 'user', $userId);
        flash('success', 'تم فصل حساب تلجرام.');
        redirect('student/telegram');
    }
}

$link = db()->one(
    'SELECT * FROM `telegram_links` WHERE user_id = :id AND telegram_user_id > 0 ORDER BY id DESC LIMIT 1',
    ['id' => $userId]
);
$pendingCode = (string) ($_SESSION['telegram_link_code'] ?? '');
unset($_SESSION['telegram_link_code']);
$codeRow = db()->one(
    'SELECT * FROM `telegram_link_codes` WHERE user_id = :id AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1',
    ['id' => $userId]
);

View::render('student/telegram', [
    'title'     => 'ربط تلجرام',
    'pageSub'   => 'استقبل إشعارات الاشتراك وتواصل مع الدعم عبر تلجرام',
    'activeMenu'=> 'student/telegram',
    'link'      => $link,
    'pending'   => $codeRow,
    'linkCode'  => $pendingCode,
    'botUsername' => trim((string) settings('telegram_bot_username', '')),
    'enabled'   => TelegramService::enabled(),
], 'layouts/app');
