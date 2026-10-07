<?php
/**
 * إعدادات المنصة - تُقرأ من ملف .env مع قيم افتراضية آمنة.
 * لا تضع أي مفاتيح سرية داخل هذا الملف.
 */
declare(strict_types=1);

use App\Env;

$root = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);

return [
    'app' => [
        'name'        => Env::get('APP_NAME', 'منصة الرخصة المهنية'),
        'env'         => Env::get('APP_ENV', 'production'),
        'debug'       => Env::bool('APP_DEBUG', false),
        'url'         => rtrim(Env::get('APP_URL', 'http://localhost'), '/'),
        'timezone'    => Env::get('APP_TIMEZONE', 'Asia/Riyadh'),
        'key'         => Env::get('APP_KEY', ''),
        'pretty_urls' => Env::bool('APP_PRETTY_URLS', true),
        'maintenance' => Env::bool('APP_MAINTENANCE', false),
        'root'        => $root,
        'version'     => '1.1.0',
        'locale'      => 'ar',
    ],

    'database' => [
        'host'    => Env::get('DB_HOST', '127.0.0.1'),
        'port'    => Env::int('DB_PORT', 3306),
        'name'    => Env::get('DB_DATABASE', 'professional_license'),
        'user'    => Env::get('DB_USERNAME', 'root'),
        'pass'    => Env::get('DB_PASSWORD', ''),
        'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
    ],

    'session' => [
        'name'     => Env::get('SESSION_NAME', 'pl_session'),
        'lifetime' => Env::int('SESSION_LIFETIME', 180), // بالدقائق
        'secure'   => Env::bool('SESSION_SECURE', false),
        'samesite' => Env::get('SESSION_SAMESITE', 'Lax'),
    ],

    'security' => [
        'csrf_ttl'            => 7200,   // ثانية
        'login_max_attempts'  => 5,      // محاولات الدخول قبل الحظر المؤقت
        'login_lock_minutes'  => 15,
        'register_max_per_ip' => 5,      // تسجيلات من نفس الـ IP في الساعة
        'password_min_length' => 8,
        'session_fingerprint' => true,   // ربط الجلسة ببصمة المتصفح
        'headers'             => true,   // إرسال رؤوس حماية HTTP
    ],

    'uploads' => [
        'max_mb'        => Env::int('UPLOAD_MAX_MB', 8),
        'allowed_image' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        'allowed_docs'  => ['pdf', 'txt', 'csv', 'json', 'docx'],
        'dir'           => $root . '/uploads',
    ],

    'mail' => [
        'driver'   => Env::get('MAIL_DRIVER', 'log'),
        'host'     => Env::get('MAIL_HOST', ''),
        'port'     => Env::int('MAIL_PORT', 587),
        'username' => Env::get('MAIL_USERNAME', ''),
        'password' => Env::get('MAIL_PASSWORD', ''),
        'from'     => Env::get('MAIL_FROM', 'noreply@example.com'),
        'from_name'=> Env::get('MAIL_FROM_NAME', 'منصة الرخصة المهنية'),
    ],

    'telegram' => [
        'token'          => Env::get('TELEGRAM_BOT_TOKEN', ''),
        'username'       => Env::get('TELEGRAM_BOT_USERNAME', ''),
        'webhook_secret' => Env::get('TELEGRAM_WEBHOOK_SECRET', ''),
        'admin_chat_id'  => Env::get('TELEGRAM_ADMIN_CHAT_ID', ''),
        'api_base'       => 'https://api.telegram.org',
    ],

    'payments' => [
        'currency' => 'SAR',
        'manual'   => ['enabled' => Env::bool('PAYMENT_MANUAL_ENABLED', true)],
        'moyasar'  => [
            'enabled'        => Env::bool('MOYASAR_ENABLED', false),
            'public_key'     => Env::get('MOYASAR_PUBLIC_KEY', ''),
            'secret_key'     => Env::get('MOYASAR_SECRET_KEY', ''),
            'webhook_secret' => Env::get('MOYASAR_WEBHOOK_SECRET', ''),
            'base_url'       => 'https://api.moyasar.com/v1',
        ],
        'myfatoorah' => [
            'enabled'  => Env::bool('MYFATOORAH_ENABLED', false),
            'api_key'  => Env::get('MYFATOORAH_API_KEY', ''),
            'base_url' => Env::get('MYFATOORAH_BASE_URL', 'https://apitest.myfatoorah.com'),
        ],
        'tap' => [
            'enabled'    => Env::bool('TAP_ENABLED', false),
            'secret_key' => Env::get('TAP_SECRET_KEY', ''),
            'base_url'   => 'https://api.tap.company/v2',
        ],
    ],

    'sms' => [
        'provider' => Env::get('SMS_PROVIDER', 'log'),
        'api_key'  => Env::get('SMS_API_KEY', ''),
        'sender'   => Env::get('SMS_SENDER', ''),
    ],
];
