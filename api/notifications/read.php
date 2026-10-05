<?php
/** تعليم الإشعارات كمقروءة (AJAX) */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

use App\Services\Notifier;

$user = auth()->requireLogin();
Notifier::markAllRead((int) $user['id']);
json_response(['ok' => true, 'message' => 'تم تعليم الإشعارات كمقروءة.', 'unread' => 0]);
