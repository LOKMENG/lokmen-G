<?php
/** إضافة/تعديل مستخدم + إدارة اشتراكه */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Repositories\QuestionRepository;
use App\Security;
use App\Str;
use App\Services\SubscriptionService;
use App\Validator;
use App\View;

$admin = auth()->requireAdmin();
$userId = (int) query('id', 0);
$user = $userId > 0 ? db()->one('SELECT * FROM `users` WHERE id = :id', ['id' => $userId]) : null;

if ($userId > 0 && $user === null) {
    flash('danger', 'المستخدم غير موجود.');
    redirect('admin/users');
}

if (is_post()) {
    $id = (int) post('id', 0);
    $rules = [
        'full_name' => 'required|min:3|max:120',
        'phone'     => 'required|phone_sa',
        'email'     => 'required|email|max:190' . ($id > 0 ? '|unique:users,email,' . $id : '|unique:users,email'),
        'role'      => 'required|in:student,supervisor,admin',
        'status'    => 'required|in:active,disabled,pending',
        'city'      => 'max:100',
        'specialty' => 'max:190',
    ];
    if ($id === 0) {
        $rules['password'] = 'required|password|confirmed';
    } else {
        $rules['password'] = 'password|confirmed';
    }

    $validator = Validator::make($_POST, $rules, ['password_confirmation' => 'تأكيد كلمة المرور']);
    if (!$validator->validate()) {
        set_errors($validator->errors());
        keep_old_input();
        redirect('admin/user-form', 302, $id > 0 ? ['id' => $id] : []);
    }

    $data = [
        'full_name'       => (string) post('full_name'),
        'phone'           => Str::normalizeSaudiPhone((string) post('phone')),
        'email'           => mb_strtolower((string) post('email')),
        'role'            => (string) post('role'),
        'status'          => (string) post('status'),
        'city'            => (string) post('city', '') ?: null,
        'specialty'       => (string) post('specialty', '') ?: null,
        'target_track_id' => (int) post('target_track_id', 0) ?: null,
        'notes'           => (string) post('notes', '') ?: null,
    ];
    if ((string) post('password') !== '') {
        $data['password_hash'] = Security::hashPassword((string) post('password'));
    }

    if ($id > 0) {
        db()->update('users', $data, 'id = :id', ['id' => $id]);
        audit('admin.user_updated', 'user', $id, ['role' => $data['role'], 'status' => $data['status']]);
        flash('success', 'تم تحديث بيانات المستخدم.');
    } else {
        $data['password_hash'] ??= Security::hashPassword(bin2hex(random_bytes(8)));
        $id = db()->insert('users', $data);
        audit('admin.user_created', 'user', $id, ['email' => $data['email']]);
        flash('success', 'تم إنشاء المستخدم بنجاح.');
    }
    clear_old_input();
    redirect('admin/user-form', 302, ['id' => $id]);
}

$subscriptions = $userId > 0
    ? db()->all('SELECT s.*, p.name_ar AS plan_name FROM `subscriptions` s JOIN `subscription_plans` p ON p.id = s.plan_id WHERE s.user_id = :id ORDER BY s.id DESC', ['id' => $userId])
    : [];
$payments = $userId > 0
    ? db()->all('SELECT * FROM `payments` WHERE user_id = :id ORDER BY id DESC LIMIT 10', ['id' => $userId])
    : [];
$attempts = $userId > 0
    ? db()->all("SELECT a.*, t.name_ar AS track_name FROM `exam_attempts` a JOIN `tracks` t ON t.id = a.track_id WHERE a.user_id = :id ORDER BY a.id DESC LIMIT 10", ['id' => $userId])
    : [];

View::render('admin/user-form', [
    'title'       => $userId > 0 ? 'تعديل مستخدم' : 'إضافة مستخدم',
    'pageSub'     => $userId > 0 ? 'تعديل البيانات والصلاحيات وإدارة الاشتراك' : 'إنشاء حساب جديد يدوياً',
    'activeMenu'  => 'admin/users',
    'user'        => $user,
    'tracks'      => (new QuestionRepository())->tracks(true),
    'subscriptions' => $subscriptions,
    'payments'    => $payments,
    'attempts'    => $attempts,
    'errors'      => errors(),
    'currentSub'  => $userId > 0 ? SubscriptionService::current($userId) : null,
], 'layouts/app');
