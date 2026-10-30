<?php
/** فحص الحماية داخل لوحة الإدارة (يعمل على الاستضافات التي لا توفّر SSH) */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Logger;
use App\Security;
use App\SecurityAudit;
use App\View;

$admin = auth()->requireAdmin();

// إصلاح صلاحية .env عند الطلب (POST + رمز CSRF)
if (is_post() && (string) post('action') === 'fix-perms') {
    @chmod(BASE_PATH . '/.env', 0640);
    Logger::security('إصلاح صلاحيات .env من لوحة الإدارة', Security::requestContext());
    flash('success', 'تمت محاولة ضبط صلاحية ملف .env إلى 640.');
    redirect('admin/security');
}

$baseUrl = '';
if (is_post() && (string) post('action') === 'check-http') {
    $baseUrl = rtrim((string) post('base_url', ''), '/');
    if ($baseUrl !== '' && !preg_match('#^https?://#i', $baseUrl)) {
        $baseUrl = 'https://' . $baseUrl;
    }
}

$report = SecurityAudit::run(false, $baseUrl);

View::render('admin/security', [
    'title'      => 'فحص الحماية',
    'pageSub'    => 'تقرير فوري بوضع حماية المنصة على هذا الخادم',
    'activeMenu' => 'admin/security',
    'checks'     => $report['checks'],
    'summary'    => $report['summary'],
    'baseUrl'    => $baseUrl,
], 'layouts/app');
