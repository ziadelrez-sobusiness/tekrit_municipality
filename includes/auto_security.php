<?php
/**
 * Auto Security - إضافة الأمان تلقائياً للصفحات
 * 
 * هذا الملف يضيف Security Headers تلقائياً لجميع الصفحات
 * يمكن تضمينه في بداية أي صفحة
 */

// منع التكرار
if (defined('AUTO_SECURITY_LOADED')) {
    return;
}
define('AUTO_SECURITY_LOADED', true);

// تحميل Security Headers (فقط إذا لم يتم إرسال headers بعد)
if (!headers_sent()) {
    if (file_exists(__DIR__ . '/SecurityHeaders.php')) {
        require_once __DIR__ . '/SecurityHeaders.php';
        
        // تهيئة Security Headers
        $cspConfig = null;
        if (file_exists(__DIR__ . '/../config/csp_config.php')) {
            $cspConfig = require __DIR__ . '/../config/csp_config.php';
        }
        
        SecurityHeaders::init($cspConfig);
    }
}

// تحميل دوال المساعدة
if (file_exists(__DIR__ . '/helpers.php')) {
    require_once __DIR__ . '/helpers.php';
}

// تحميل form helper
if (file_exists(__DIR__ . '/form_helper.php')) {
    require_once __DIR__ . '/form_helper.php';
}







