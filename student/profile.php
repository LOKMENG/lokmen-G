<?php
/** الملف الشخصي: تعديل البيانات + تغيير كلمة المرور + الصورة الرمزية + ربط تلجرام */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Repositories\QuestionRepository;
use App\Security;
use App\Str;
use App\Validator;
use App\View;

$user = auth()->requireLogin();
$userId = (int) $user['id'];
$errors = errors();

if (is_post()) {
    $action = (string) post('action', 'profile');

    if ($action === 'profile') {
        $validator = Validator::make($_POST, [
            'full_name' => 'required|min:3|max:120',
            'city'      => 'max:100',
            'specialty' => 'max:190',
        ]);
        if (!$validator->validate()) {
            set_errors($validator->errors());
            keep_old_input();
            redirect('student/profile');
        }
        $data = [
            'full_name' => (string) post('full_name'),
            'city'      => (string) post('city', '') ?: null,
            'specialty' => (string) post('specialty', '') ?: null,
            'target_track_id' => (int) post('target_track_id', 0) ?: null,
        ];

        if (isset($_FILES['avatar']) && (int) ($_FILES['avatar']['error'] ?? 4) !== UPLOAD_ERR_NO_FILE) {
            $upload = Security::upload($_FILES['avatar'], config('uploads.allowed_image'), 'avatars', 3);
            if ($upload['ok']) {
                $data['avatar'] = $upload['path'];
            } else {
                flash('warning', 'لم يتم تحديث الصورة: ' . $upload['error']);
            }
        }

        auth()->updateProfile($userId, $data);
        audit('profile.updated', 'user', $userId);
        clear_old_input();
        flash('success', 'تم تحديث بياناتك بنجاح.');
        redirect('student/profile');
    }

    if ($action === 'password') {
        $validator = Validator::make($_POST, [
            'current_password' => 'required',
            'password'         => 'required|password|confirmed',
        ], ['password_confirmation' => 'تأكيد كلمة المرور']);
        if (!$validator->validate()) {
            set_errors($validator->errors());
            redirect('student/profile');
        }
        $result = auth()->changePassword($userId, (string) post('current_password'), (string) post('password'));
        flash($result['ok'] ? 'success' : 'danger', $result['message']);
        redirect('student/profile');
    }
}

$telegram = db()->one('SELECT * FROM `telegram_links` WHERE user_id = :id ORDER BY id DESC LIMIT 1', ['id' => $userId]);

View::render('student/profile', [
    'title'      => 'الملف الشخصي',
    'pageSub'    => 'بياناتك الشخصية وأمان الحساب',
    'activeMenu' => 'student/profile',
    'user'       => auth()->user(),
    'errors'     => $errors,
    'tracks'     => (new QuestionRepository())->tracks(true),
    'telegram'   => $telegram,
], 'layouts/app');
