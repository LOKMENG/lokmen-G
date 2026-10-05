<?php
/**
 * المهام المجدولة (Cron) — أمر واحد يغطي كل الصيانة الدورية.
 *
 * الإعداد على السيرفر (كل ساعة أو كل 5 دقائق، كلاهما كافٍ):
 *      كل 5 دقائق: cd /path/to/project && php tools/cron.php >/dev/null 2>&1
 *
 * المهام:
 *   subscriptions : إنهاء الاشتراكات المنتهية وتحويلها إلى expired
 *   notify        : إرسال تنبيهات اقتراب انتهاء الاشتراك (تلجرام + إشعار داخلي)
 *   exams         : إغلاق محاولات الاختبار المتأخرة عن وقتها
 *   cleanup       : حذف أكواد الربط المنتهية وملفات PDF المؤقتة القديمة
 *   report        : تسجيل ملخص التشغيل في storage/logs/cron.log
 *
 * خيارات: --task=all|subscriptions|notify|exams|cleanup   --quiet
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

if (!is_cli()) {
    http_response_code(403);
    exit('هذا الملف يعمل من سطر الأوامر فقط.');
}

use App\Database;
use App\Logger;
use App\Services\ExamEngine;
use App\Services\SubscriptionService;

$quiet = cli_has_flag('quiet');
$task = cli_option('task', 'all');

function cron_line(string $message, bool $quiet = false): void
{
    if (!$quiet) {
        fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n");
    }
}

if (!is_file(BASE_PATH . '/.env')) {
    fwrite(STDERR, "ملف .env غير موجود — نفّذ التثبيت أولاً.\n");
    exit(1);
}

$summary = ['subscriptions' => 0, 'notified' => 0, 'exams' => 0, 'codes' => 0, 'temp_files' => 0];
$started = microtime(true);

try {
    Database::instance()->pdo();
} catch (Throwable $error) {
    fwrite(STDERR, 'تعذّر الاتصال بقاعدة البيانات: ' . $error->getMessage() . "\n");
    exit(1);
}

if (in_array($task, ['all', 'subscriptions'], true)) {
    $summary['subscriptions'] = SubscriptionService::expireDue();
    cron_line('اشتراكات منتهية تم تحديثها: ' . $summary['subscriptions'], $quiet);
}

if (in_array($task, ['all', 'notify'], true)) {
    $summary['notified'] = SubscriptionService::notifyExpiring();
    cron_line('تنبيهات إشعار أُرسلت: ' . $summary['notified'], $quiet);
}

if (in_array($task, ['all', 'exams'], true)) {
    $summary['exams'] = ExamEngine::expireOverdue(200);
    cron_line('محاولات اختبار أُغلقت: ' . $summary['exams'], $quiet);
}

if (in_array($task, ['all', 'cleanup'], true)) {
    // أكواد ربط تلجرام منتهية
    $summary['codes'] = db()->delete('telegram_link_codes', 'expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    cron_line('أكواد ربط منتهية حُذفت: ' . $summary['codes'], $quiet);

    // ملفات PDF مؤقتة أقدم من 3 أيام (تبقى الملفات المرتبطة بدفعات الاستيراد)
    $tempDir = BASE_PATH . '/storage/tmp_pdf_test';
    if (is_dir($tempDir)) {
        foreach ((array) glob($tempDir . '/*') as $file) {
            if (is_file($file) && filemtime($file) < time() - 3 * 86400) {
                @unlink($file);
                $summary['temp_files']++;
            }
        }
    }
    cron_line('ملفات مؤقتة حُذفت: ' . $summary['temp_files'], $quiet);
}

// ختم آخر تشغيل ليظهر في لوحة الإدارة
try {
    db()->query(
        'INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_group`, `setting_type`)
         VALUES (:key, :value, :group, :type)
         ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)',
        ['key' => 'cron_last_run_at', 'value' => date('Y-m-d H:i:s'), 'group' => 'system', 'type' => 'string']
    );
} catch (Throwable) {
    // ليس حرجاً
}

$elapsed = round((microtime(true) - $started) * 1000);
$line = sprintf(
    'task=%s | subscriptions=%d | notified=%d | exams=%d | codes=%d | temp=%d | %dms',
    $task,
    $summary['subscriptions'],
    $summary['notified'],
    $summary['exams'],
    $summary['codes'],
    $summary['temp_files'],
    $elapsed
);
Logger::info('cron: ' . $line);
cron_line('انتهى — ' . $line, $quiet);

exit(0);
