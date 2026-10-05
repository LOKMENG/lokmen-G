<?php
/** تسجيل الدخول */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
use App\Validator;
use App\View;

auth()->requireGuest();

$errors = errors();
$post = fn(string $key, string $default = '') => e((string) old($key, $default));

if (is_post()) {
    $identifier = (string) post('identifier', '');
    $password = (string) post('password', '');
    $remember = (bool) post('remember', false);

    $validator = Validator::make($_POST, [
        'identifier' => 'required',
        'password'   => 'required',
    ]);
    if (!$validator->validate()) {
        set_errors($validator->errors());
        keep_old_input();
        redirect('auth/login');
    }

    $result = auth()->attempt($identifier, $password, $remember);
    if (!$result['ok']) {
        set_errors(['identifier' => $result['message']]);
        keep_old_input();
        redirect('auth/login');
    }
    clear_old_input();
    flash('success', 'مرحباً بك ' . (string) ($result['user']['full_name'] ?? '') . ' 👋');
    $intended = auth()->intendedUrl((($result['user']['role'] ?? 'student') === 'student') ? 'student/dashboard' : 'admin/index');
    redirect($intended);
}

View::render('auth/login', [
    'title'  => 'تسجيل الدخول',
    'errors' => $errors,
    'old'    => $post,
], 'layouts/auth');
