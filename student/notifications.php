<?php
/** إشعارات المستخدم */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Services\Notifier;
use App\View;

$user = auth()->requireLogin();
$userId = (int) $user['id'];

if (is_post() && post('action') === 'mark_all_read') {
    Notifier::markAllRead($userId);
    flash('success', 'تم تعليم كل الإشعارات كمقروءة.');
    redirect('student/notifications');
}

View::render('student/notifications', [
    'title'         => 'الإشعارات',
    'pageSub'       => 'تحديثات الاشتراك والاختبارات ورسائل الإدارة',
    'activeMenu'    => 'student/notifications',
    'notifications' => Notifier::forUser($userId, 50),
    'unread'        => Notifier::unreadCount($userId),
], 'layouts/app');
