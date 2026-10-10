<?php
/** طلب استعادة كلمة المرور */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Security;
use App\Services\Mailer;
use App\Validator;
use App\View;

auth()->requireGuest();

$devLink = '';

if (is_post()) {
    if (Security::exceededLimit('forgot:' . Security::ip(), 5, 60)) {
        flash('danger', 'طلبات كثيرة من هذا الجهاز. حاول بعد ساعة أو تواصل مع الدعم.');
        redirect('auth/forgot-password');
    }
    $validator = Validator::make($_POST, ['email' => 'required|email']);
    if (!$validator->validate()) {
        set_errors($validator->errors());
        keep_old_input();
        redirect('auth/forgot-password');
    }

    $result = auth()->requestPasswordReset((string) post('email'));
    Security::hitLimit('forgot:' . Security::ip());

    if (isset($result['token'])) {
        $link = url('auth/reset-password', ['token' => $result['token']]);
        $user = db()->one('SELECT full_name, email FROM `users` WHERE email = :e', ['e' => mb_strtolower((string) post('email'))]);
        if ($user !== null) {
            Mailer::sendPasswordReset((string) $user['email'], (string) $user['full_name'], $link);
        }
        // في وضع التطوير (MAIL_DRIVER=log) نعرض الرابط لتسهيل التجربة محلياً
        if ((string) config('mail.driver', 'log') === 'log') {
            $devLink = $link;
        }
    }
    flash('success', $result['message']);
    if ($devLink === '') {
        redirect('auth/login');
    }
}

View::render('auth/forgot-password', [
    'title'   => 'استعادة كلمة المرور',
    'errors'  => errors(),
    'devLink' => $devLink,
], 'layouts/auth');
