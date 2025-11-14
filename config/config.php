<?php
// إعدادات النظام الأساسية
define('SITE_NAME', 'بلدية تكريت');
define('SITE_URL', 'http://localhost/tekrit_municipality');

// إعدادات قاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_NAME', 'tekrit_municipality');
define('DB_USER', 'root');
define('DB_PASS', '');

// إعدادات الأمان
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 3600); // ساعة واحدة

// إعدادات رفع الملفات
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 ميجابايت

// إعدادات رقم التتبع
define('TRACKING_PREFIX', 'TRK');
define('TRACKING_YEAR', date('Y'));

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?> 