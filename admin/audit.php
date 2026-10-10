<?php
/** سجل التدقيق العمليات الإدارية */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
use App\View;

$admin = auth()->requireAdmin();

$q = (string) query('q', '');
$userId = (int) query('user', 0);
$page = max(1, (int) query('page', 1));
$clauses = [];
$params = [];
if ($q !== '') {
    $clauses[] = '(a.action LIKE :q OR a.entity_type LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
if ($userId > 0) {
    $clauses[] = 'a.user_id = :user';
    $params['user'] = $userId;
}

$paginated = db()->paginate(
    "SELECT a.*, u.full_name, u.email, u.role
       FROM `audit_logs` a
  LEFT JOIN `users` u ON u.id = a.user_id"
    . ($clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses))
    . ' ORDER BY a.id DESC',
    $params,
    30,
    $page
);

View::render('admin/audit', [
    'title'      => 'سجل التدقيق',
    'pageSub'    => 'كل عملية إدارية مسجّلة بالتفصيل',
    'activeMenu' => 'admin/audit',
    'paginated'  => $paginated,
    'filters'    => ['q' => $q, 'user' => $userId],
    'actions'    => db()->all('SELECT action, COUNT(*) AS c FROM `audit_logs` GROUP BY action ORDER BY c DESC LIMIT 15'),
], 'layouts/app');
