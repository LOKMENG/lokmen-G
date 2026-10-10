<?php
/** الدعم الفني: تذاكر الطلاب والردود */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Services\Notifier;
use App\View;

$admin = auth()->requireAdmin();
$adminId = (int) $admin['id'];

if (is_post()) {
    $ticketId = (int) post('ticket_id', 0);
    $action = (string) post('action', '');
    $ticket = db()->one('SELECT * FROM `support_tickets` WHERE id = :id', ['id' => $ticketId]);

    if ($ticket === null) {
        flash('danger', 'التذكرة غير موجودة.');
        redirect('admin/support');
    }

    switch ($action) {
        case 'reply':
            $reply = trim((string) post('admin_reply'));
            if ($reply === '') {
                flash('danger', 'نص الرد مطلوب.');
                break;
            }
            db()->update('support_tickets', [
                'admin_reply' => $reply,
                'replied_by'  => $adminId,
                'replied_at'  => date('Y-m-d H:i:s'),
                'status'      => 'answered',
                'priority'    => (string) post('priority', $ticket['priority']),
            ], 'id = :id', ['id' => $ticketId]);
            if ($ticket['user_id'] !== null) {
                Notifier::supportReply((int) $ticket['user_id'], $reply);
            }
            audit('admin.ticket_replied', 'support_ticket', $ticketId);
            flash('success', 'تم إرسال الرد وإشعار الطالب.');
            break;

        case 'close':
            db()->update('support_tickets', ['status' => 'closed'], 'id = :id', ['id' => $ticketId]);
            audit('admin.ticket_closed', 'support_ticket', $ticketId);
            flash('success', 'تم إغلاق التذكرة.');
            break;

        case 'reopen':
            db()->update('support_tickets', ['status' => 'open'], 'id = :id', ['id' => $ticketId]);
            flash('success', 'تم إعادة فتح التذكرة.');
            break;

        case 'priority':
            db()->update('support_tickets', ['priority' => (string) post('priority', 'normal')], 'id = :id', ['id' => $ticketId]);
            flash('success', 'تم تحديث الأولوية.');
            break;

        case 'delete':
            db()->delete('support_tickets', 'id = :id', ['id' => $ticketId]);
            audit('admin.ticket_deleted', 'support_ticket', $ticketId);
            flash('success', 'تم حذف التذكرة.');
            break;
    }
    redirect('admin/support');
}

$status = (string) query('status', 'open');
$channel = (string) query('channel', '');
$page = max(1, (int) query('page', 1));
$clauses = [];
$params = [];
if (in_array($status, ['open', 'answered', 'closed'], true)) {
    $clauses[] = 'tk.status = :status';
    $params['status'] = $status;
}
if (in_array($channel, ['site', 'telegram', 'email', 'whatsapp'], true)) {
    $clauses[] = 'tk.channel = :channel';
    $params['channel'] = $channel;
}

$paginated = db()->paginate(
    "SELECT tk.*, u.full_name, u.email, u.phone, rb.full_name AS replied_by_name
       FROM `support_tickets` tk
  LEFT JOIN `users` u ON u.id = tk.user_id
  LEFT JOIN `users` rb ON rb.id = tk.replied_by"
    . ($clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses))
    . " ORDER BY (tk.status = 'open') DESC, FIELD(tk.priority, 'high', 'normal', 'low'), tk.id DESC",
    $params,
    15,
    $page
);

View::render('admin/support', [
    'title'      => 'الدعم الفني',
    'pageSub'    => 'تذاكر الطلاب والردود',
    'activeMenu' => 'admin/support',
    'paginated'  => $paginated,
    'filters'    => ['status' => $status, 'channel' => $channel],
    'counters'   => [
        'open'     => (int) db()->value("SELECT COUNT(*) FROM `support_tickets` WHERE status = 'open'", [], 0),
        'answered' => (int) db()->value("SELECT COUNT(*) FROM `support_tickets` WHERE status = 'answered'", [], 0),
        'closed'   => (int) db()->value("SELECT COUNT(*) FROM `support_tickets` WHERE status = 'closed'", [], 0),
        'high'     => (int) db()->value("SELECT COUNT(*) FROM `support_tickets` WHERE status <> 'closed' AND priority = 'high'", [], 0),
    ],
], 'layouts/app');
