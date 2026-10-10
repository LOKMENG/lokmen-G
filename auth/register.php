<?php
/** إنشاء حساب جديد */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Repositories\QuestionRepository;
use App\Security;
use App\Str;
use App\Validator;
use App\View;

auth()->requireGuest();

$errors = errors();
$post = fn(string $key, string $default = '') => e((string) old($key, $default));
$tracks = [];
try {
    $tracks = (new QuestionRepository())->tracks(true);
} catch (Throwable) {
    // تجاهل - الصفحة تعمل حتى لو تعذّر تحميل المسارات
}

if (is_post()) {
    $validator = Validator::make($_POST, [
        'full_name' => 'required|min:3|max:120',
        'phone'     => 'required|phone_sa',
        'email'     => 'required|email|max:190|unique:users,email',
        'password'  => 'required|password|confirmed',
        'city'      => 'max:100',
        'specialty' => 'max:190',
    ], ['password_confirmation' => 'تأكيد كلمة المرور']);

    if (!post('terms')) {
        $validator->errors()['terms'] ??= 'يجب الموافقة على شروط الاستخدام وسياسة الخصوصية.';
    }

    if (!$validator->validate()) {
        set_errors($validator->errors());
        keep_old_input();
        redirect('auth/register');
    }

    // حد أقصى للتسجيلات من نفس الـ IP
    if (Security::exceededLimit('register:' . Security::ip(), (int) config('security.register_max_per_ip', 5), 60)) {
        flash('danger', 'تم إنشاء عدة حسابات من هذا الجهاز مؤخراً. حاول لاحقاً أو تواصل مع الدعم.');
        redirect('auth/register');
    }

    $result = auth()->register([
        'full_name'       => (string) post('full_name'),
        'phone'           => Str::normalizeSaudiPhone((string) post('phone')),
        'email'           => (string) post('email'),
        'password'        => (string) post('password'),
        'city'            => (string) post('city', '') ?: null,
        'specialty'       => (string) post('specialty', '') ?: null,
        'target_track_id' => (int) post('target_track_id', 0) ?: null,
    ]);

    if (!$result['ok']) {
        set_errors(['email' => $result['message']]);
        keep_old_input();
        redirect('auth/register');
    }

    Security::hitLimit('register:' . Security::ip());
    clear_old_input();

    // تسجيل الدخول مباشرة بعد التسجيل
    $user = auth()->findById((int) $result['user_id']);
    if ($user !== null) {
        auth()->login($user, false);
    }
    flash('success', 'تم إنشاء حسابك بنجاح 🎉 فعّل اشتراكك لمتابعة التدريب.');
    redirect('subscriptions/plans');
}

View::render('auth/register', [
    'title'  => 'إنشاء حساب جديد',
    'errors' => $errors,
    'old'    => $post,
    'tracks' => $tracks,
], 'layouts/auth');
