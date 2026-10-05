<?php
/** سجل الاختبارات والحالات */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Services\ExamEngine;
use App\View;

$user = auth()->requireLogin();
$userId = (int) $user['id'];
$page = max(1, (int) query('page', 1));

$paginated = db()->paginate(
    "SELECT a.*, t.name_ar AS track_name
       FROM `exam_attempts` a
       JOIN `tracks` t ON t.id = a.track_id
      WHERE a.user_id = :id
      ORDER BY a.id DESC",
    ['id' => $userId],
    15,
    $page
);

View::render('exams/history', [
    'title'      => 'سجل اختباراتي',
    'pageSub'    => 'كل المحاولات السابقة مع نتائجها',
    'activeMenu' => 'exams/history',
    'paginated'  => $paginated,
    'summary'    => ExamEngine::userSummary($userId),
], 'layouts/app');
