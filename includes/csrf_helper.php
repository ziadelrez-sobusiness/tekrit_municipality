<?php
/**
 * دوال مساعدة لـ CSRF Protection
 * 
 * لتسهيل استخدام CSRF في النماذج
 */

// تحميل CsrfProtection إذا كان موجوداً
if (file_exists(__DIR__ . '/CsrfProtection.php')) {
    require_once __DIR__ . '/CsrfProtection.php';
}

/**
 * دالة مساعدة: إرجاع HTML input للـ CSRF token
 */
if (!function_exists('csrf_field')) {
    function csrf_field($fieldName = 'csrf_token') {
        if (class_exists('CsrfProtection')) {
            return CsrfProtection::getTokenField($fieldName);
        }
        
        // Fallback
        if (class_exists('Utils')) {
            $token = Utils::generateCSRFToken();
            return '<input type="hidden" name="' . htmlspecialchars($fieldName) . '" value="' . htmlspecialchars($token) . '">';
        }
        
        return '';
    }
}

/**
 * دالة مساعدة: التحقق من CSRF token في الطلب
 */
if (!function_exists('csrf_validate')) {
    function csrf_validate($fieldName = 'csrf_token') {
        if (class_exists('CsrfProtection')) {
            return CsrfProtection::validateRequest($fieldName);
        }
        
        // Fallback
        if (class_exists('Utils')) {
            $token = $_POST[$fieldName] ?? $_GET[$fieldName] ?? '';
            return Utils::validateCSRFToken($token);
        }
        
        return false;
    }
}

/**
 * دالة مساعدة: الحصول على CSRF token
 */
if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (class_exists('CsrfProtection')) {
            return CsrfProtection::generateToken();
        }
        
        // Fallback
        if (class_exists('Utils')) {
            return Utils::generateCSRFToken();
        }
        
        return '';
    }
}

/**
 * دالة مساعدة: التحقق من CSRF في AJAX request
 */
if (!function_exists('csrf_validate_ajax')) {
    function csrf_validate_ajax() {
        if (class_exists('CsrfProtection')) {
            return CsrfProtection::validateAjaxRequest();
        }
        
        return false;
    }
}

