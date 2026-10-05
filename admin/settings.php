<?php
/** إعدادات المنصة (عام / الاشتراك والدفع / الاختبارات / تلجرام) */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
use App\Settings;
use App\View;

$admin = auth()->requireAdmin();

if (is_post()) {
    $values = (array) post('settings', []);
    $bools = (array) post('bools', []);
    // مفاتيح منطقية: غير المُرسَل = 0
    $boolKeys = [];
    foreach ((array) db()->all("SELECT setting_key FROM `settings` WHERE setting_type = 'bool'") as $row) {
        $boolKeys[] = (string) $row['setting_key'];
    }
    foreach ($boolKeys as $key) {
        $values[$key] = in_array($key, $bools, true) ? '1' : '0';
    }

    $updated = 0;
    foreach ($values as $key => $value) {
        $key = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $key));
        if ($key === '' || $key === 'demo_data_installed') {
            continue; // لا يُعدَّل من الواجهة
        }
        Settings::set($key, is_string($value) ? trim($value) : (string) $value);
        $updated++;
    }

    audit('admin.settings_updated', 'settings', null, ['count' => $updated]);
    flash('success', 'تم حفظ ' . $updated . ' إعداداً.');
    redirect('admin/settings');
}

$groups = [
    'general'      => ['bi-sliders', 'إعدادات عامة'],
    'subscription' => ['bi-credit-card', 'الاشتراك والدفع'],
    'exam'         => ['bi-journal-check', 'الاختبارات والتحليل'],
    'telegram'     => ['bi-telegram', 'تلجرام'],
    'payment'      => ['bi-shield-lock', 'بوابات الدفع'],
];

View::render('admin/settings', [
    'title'      => 'إعدادات المنصة',
    'pageSub'    => 'تحكم كامل في اسم المنصة وقيم الاشتراك وبيانات الدفع وحدود التحليل',
    'activeMenu' => 'admin/settings',
    'groups'     => $groups,
    'settingsByGroup' => [
        'general'      => Settings::group('general'),
        'subscription' => Settings::group('subscription'),
        'exam'         => Settings::group('exam'),
        'telegram'     => Settings::group('telegram'),
        'payment'      => Settings::group('payment'),
    ],
    'price1'     => (float) settings('subscription_price', 100),
], 'layouts/app');
