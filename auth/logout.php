<?php
/** تسجيل الخروج */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

auth()->logout();
session_start();
flash('success', 'تم تسجيل خروجك بنجاح. نراك قريباً!');
redirect('auth/login');
