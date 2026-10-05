<?php
/** الدعم الفني: إرسال تذكرة ومتابعة الردود */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Services\Notifier;
use App\Services\TelegramService;
use App\Validator;
use App\View;

$user = auth()->requireLogin();
$userId = (int) $user['id'];

if (is_post()) {
    $validator = Validator::make($_POST, [
        'subject' => 'required|min:4|max:190',
        'message' => 'required|min:10|max:3000',
    ]);
    if (!$validator->validate()) {
        set_errors($validator->errors());
        keep_old_input();
        redirect('student/support');
    }
    $ticketId = db()->insert('support_tickets', [
        'user_id'  => $userId,
        'name'     => (string) $user['full_name'],
        'contact'  => (string) $user['phone'],
        'channel'  => 'site',
        'subject'  => (string) post('subject'),
        'message'  => (string) post('message'),
        'status'   => 'open',
        'priority' => in_array((string) post('priority', 'normal'), ['low', 'normal', 'high'], true) ? (string) post('priority') : 'normal',
    ]);
    audit('support.created', 'support_ticket', $ticketId);
    Notifier::toAdmins('تذكرة دعم جديدة', 'من: ' . (string) $user['full_name'] . "\nالموضوع: " . (string) post('subject'));
    clear_old_input();
    flash('success', 'تم إرسال تذكرتك. سيتم الرد عليك قريباً.');
    redirect('student/support');
}

$tickets = db()->all(
    'SELECT * FROM `support_tickets` WHERE user_id = :id ORDER BY id DESC LIMIT 20',
    ['id' => $userId]
);

View::render('student/support', [
    'title'       => 'الدعم الفني',
    'pageSub'     => 'أرسل استفسارك وتابع الردود',
    'activeMenu'  => 'student/support',
    'tickets'     => $tickets,
    'errors'      => errors(),
    'botUsername' => (string) settings('telegram_bot_username', ''),
    'telegramOn'  => TelegramService::enabled(),
], 'layouts/app');
