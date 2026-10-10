<?php
/** إحصائيات وتقارير الإدارة */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Services\StatisticsService;
use App\View;

$admin = auth()->requireAdmin();
$trackId = (int) query('track', 0);

View::render('admin/statistics', [
    'title'          => 'الإحصائيات والتقارير',
    'pageSub'        => 'مؤشرات الأداء وتقارير المحتوى والطلاب',
    'activeMenu'     => 'admin/statistics',
    'overview'       => StatisticsService::overview(),
    'categories'     => StatisticsService::categoryPerformance($trackId > 0 ? $trackId : null),
    'weakest'        => StatisticsService::weakestCategories(10),
    'hardest'        => StatisticsService::hardestQuestions(10),
    'topStudents'    => StatisticsService::topStudents(10),
    'registrations'  => StatisticsService::registrationsByDay(30),
    'revenue'        => StatisticsService::revenueByDay(30),
    'tracks'         => db()->all('SELECT * FROM `tracks` WHERE is_active = 1 ORDER BY sort_order'),
    'trackFilter'    => $trackId,
], 'layouts/app');
