<?php
/** إعادة تعيين كلمة المرور باستخدام الرابط المرسل */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
use App\Validator;
use App\View;

auth()->requireGuest();

$token = (string) query('token', '');
if ($token === '') {
    flash('danger', 'رابط إعادة التعيين غير صالح.');
    redirect('auth/forgot-password');
}

$reset = auth()->validateResetToken($token);

if (is_post()) {
    $validator = Validator::make($_POST, [
        'password' => 'required|password|confirmed',
    ], ['password_confirmation' => 'تأكيد كلمة المرور']);

    if (!$validator->validate()) {
        set_errors($validator->errors());
        redirect('auth/reset-password', 302, ['token' => $token]);
    }

    $result = auth()->resetPassword((string) post('_token_reset', $token), (string) post('password'));
    if (!$result['ok']) {
        flash('danger', $result['message']);
        redirect('auth/forgot-password');
    }
    flash('success', $result['message']);
    redirect('auth/login');
}

View::render('auth/reset-password', [
    'title'  => 'تعيين كلمة مرور جديدة',
    'token'  => $token,
    'valid'  => $reset !== null,
    'errors' => errors(),
], 'layouts/auth');
