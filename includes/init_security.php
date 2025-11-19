<?php
/**
 * تهيئة أنظمة الأمان
 * 
 * هذا الملف يجب تضمينه في بداية كل صفحة لتفعيل:
 * - Security Headers (CSP, X-Frame-Options, etc.)
 * - دوال مساعدة (e(), csrf_field(), etc.)
 * 
 * الاستخدام:
 * require_once __DIR__ . '/includes/init_security.php';
 */

// تحميل دوال المساعدة
if (file_exists(__DIR__ . '/helpers.php')) {
    require_once __DIR__ . '/helpers.php';
}

// تحميل Security Headers (فقط إذا لم يتم إرسال headers بعد)
if (!headers_sent() && file_exists(__DIR__ . '/SecurityHeaders.php')) {
    require_once __DIR__ . '/SecurityHeaders.php';
    
    // تهيئة Security Headers
    // يمكن تخصيص CSP من خلال ملف config إذا لزم الأمر
    $cspConfig = null;
    if (file_exists(__DIR__ . '/../config/csp_config.php')) {
        $cspConfig = require __DIR__ . '/../config/csp_config.php';
    }
    
    SecurityHeaders::init($cspConfig);
}



