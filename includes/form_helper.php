<?php
/**
 * Form Helper - دوال مساعدة للنماذج
 * 
 * يوفر دوال لإضافة CSRF Protection تلقائياً للنماذج
 */

// تحميل helpers.php إذا كان موجوداً
if (file_exists(__DIR__ . '/helpers.php')) {
    require_once __DIR__ . '/helpers.php';
}

/**
 * إضافة CSRF field للنموذج (للاستخدام في بداية <form>)
 * 
 * @param string $fieldName اسم الحقل (افتراضي: 'csrf_token')
 * @return string HTML input field
 */
if (!function_exists('form_csrf_field')) {
    function form_csrf_field($fieldName = 'csrf_token') {
        if (function_exists('csrf_field')) {
            return csrf_field($fieldName);
        } elseif (class_exists('CsrfProtection')) {
            return CsrfProtection::getTokenField($fieldName);
        } elseif (class_exists('Utils')) {
            $token = Utils::generateCSRFToken();
            return '<input type="hidden" name="' . htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
        }
        
        // Fallback أخير
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return '<input type="hidden" name="' . htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';
    }
}

/**
 * التحقق من CSRF في معالج النموذج (للاستخدام في بداية معالج POST)
 * 
 * @param string $fieldName اسم الحقل (افتراضي: 'csrf_token')
 * @param bool $throwException إذا كان true، يرمي exception
 * @return bool True إذا كان صالحاً
 * @throws Exception إذا كان $throwException = true والرمز غير صالح
 */
if (!function_exists('form_validate_csrf')) {
    function form_validate_csrf($fieldName = 'csrf_token', $throwException = false) {
        if (function_exists('csrf_validate')) {
            return csrf_validate($fieldName, $throwException);
        } elseif (class_exists('CsrfProtection')) {
            $valid = CsrfProtection::validateRequest($fieldName);
            if (!$valid && $throwException) {
                throw new Exception('CSRF token غير صالح');
            }
            return $valid;
        } elseif (class_exists('Utils')) {
            $token = $_POST[$fieldName] ?? $_GET[$fieldName] ?? '';
            $valid = Utils::validateCSRFToken($token);
            if (!$valid && $throwException) {
                throw new Exception('CSRF token غير صالح');
            }
            return $valid;
        }
        
        // Fallback أخير
        $token = $_POST[$fieldName] ?? $_GET[$fieldName] ?? '';
        $storedToken = $_SESSION['csrf_token'] ?? '';
        $valid = !empty($storedToken) && hash_equals($storedToken, $token);
        
        if (!$valid && $throwException) {
            throw new Exception('CSRF token غير صالح');
        }
        return $valid;
    }
}

/**
 * دالة مساعدة: إضافة CSRF validation في بداية معالج POST
 * 
 * الاستخدام:
 * if ($_SERVER['REQUEST_METHOD'] == 'POST') {
 *     form_require_csrf(); // هذا سيرفض الطلب تلقائياً إذا كان CSRF غير صالح
 *     // باقي الكود...
 * }
 * 
 * @param string $fieldName اسم الحقل
 * @param string $errorMessage رسالة الخطأ
 */
if (!function_exists('form_require_csrf')) {
    function form_require_csrf($fieldName = 'csrf_token', $errorMessage = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.') {
        if (!form_validate_csrf($fieldName, false)) {
            if (headers_sent()) {
                die($errorMessage);
            } else {
                http_response_code(403);
                die($errorMessage);
            }
        }
    }
}



