<?php
/**
 * CSRF Middleware - حماية تلقائية من CSRF
 * 
 * هذا الملف يوفر حماية تلقائية من CSRF لجميع POST requests
 * 
 * الاستخدام:
 * require_once __DIR__ . '/../includes/csrf_middleware.php';
 * csrf_protect(); // في بداية الملف
 */

// تحميل form_helper
if (file_exists(__DIR__ . '/form_helper.php')) {
    require_once __DIR__ . '/form_helper.php';
}

/**
 * حماية تلقائية من CSRF لجميع POST requests
 * 
 * @param bool $strict إذا كان true، يرفض الطلب إذا كان CSRF غير صالح
 * @param string $errorMessage رسالة الخطأ
 * @return bool True إذا كان CSRF صالحاً أو لم يكن POST request
 */
if (!function_exists('csrf_protect')) {
    function csrf_protect($strict = false, $errorMessage = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.') {
        // فقط للـ POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return true;
        }
        
        // التحقق من CSRF (مبسط لتسريع العملية)
        $token = $_POST['csrf_token'] ?? '';
        $storedToken = $_SESSION['csrf_token'] ?? '';
        $csrfValid = !empty($storedToken) && !empty($token) && hash_equals($storedToken, $token);
        
        if (!$csrfValid) {
            if ($strict) {
                if (!headers_sent()) {
                    http_response_code(403);
                }
                die($errorMessage);
            }
            return false;
        }
        
        return true;
    }
}

/**
 * إضافة CSRF field تلقائياً في النماذج
 * 
 * @param string $fieldName اسم الحقل
 * @return string HTML input field
 */
if (!function_exists('csrf_input')) {
    function csrf_input($fieldName = 'csrf_token') {
        if (function_exists('form_csrf_field')) {
            return form_csrf_field($fieldName);
        } elseif (function_exists('csrf_field')) {
            return csrf_field($fieldName);
        } elseif (class_exists('CsrfProtection')) {
            return CsrfProtection::getTokenField($fieldName);
        } elseif (class_exists('Utils')) {
            $token = Utils::generateCSRFToken();
            return '<input type="hidden" name="' . htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
        }
        
        // Fallback
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return '<input type="hidden" name="' . htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';
    }
}


