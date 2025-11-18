<?php
/**
 * دوال مساعدة عامة للنظام
 * 
 * هذا الملف يحتوي على دوال مساعدة لتحسين الأمان والكود
 */

// تحميل الملفات المطلوبة
if (file_exists(__DIR__ . '/CsrfProtection.php')) {
    require_once __DIR__ . '/CsrfProtection.php';
}

/**
 * دالة مساعدة: Escape للوقاية من XSS
 * 
 * استخدام محسّن لـ htmlspecialchars مع ENT_QUOTES
 * 
 * @param string $string النص المراد تنظيفه
 * @param int $flags Flags إضافية (افتراضي: ENT_QUOTES | ENT_HTML5)
 * @return string النص المنظف
 */
if (!function_exists('e')) {
    function e($string, $flags = ENT_QUOTES | ENT_HTML5) {
        if ($string === null) {
            return '';
        }
        if (is_array($string)) {
            return array_map('e', $string);
        }
        return htmlspecialchars((string)$string, $flags, 'UTF-8');
    }
}

/**
 * دالة مساعدة: Escape مع السماح ببعض HTML
 * 
 * @param string $string النص المراد تنظيفه
 * @param array $allowedTags قائمة بالـ tags المسموحة
 * @return string النص المنظف
 */
if (!function_exists('e_allow_html')) {
    function e_allow_html($string, $allowedTags = ['<p>', '<br>', '<strong>', '<em>']) {
        if ($string === null) {
            return '';
        }
        // تنظيف أولي
        $string = htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // إعادة السماح ببعض الـ tags
        foreach ($allowedTags as $tag) {
            $cleanTag = strip_tags($tag);
            $string = str_replace('&lt;' . $cleanTag . '&gt;', '<' . $cleanTag . '>', $string);
            $string = str_replace('&lt;/' . $cleanTag . '&gt;', '</' . $cleanTag . '>', $string);
        }
        return $string;
    }
}

/**
 * دالة مساعدة: CSRF Field للاستخدام في النماذج
 * 
 * @param string $fieldName اسم الحقل (افتراضي: 'csrf_token')
 * @return string HTML input field
 */
if (!function_exists('csrf_field')) {
    function csrf_field($fieldName = 'csrf_token') {
        if (class_exists('CsrfProtection')) {
            return CsrfProtection::getTokenField($fieldName);
        }
        
        // Fallback للكود القديم
        if (class_exists('Utils')) {
            $token = Utils::generateCSRFToken();
            return '<input type="hidden" name="' . e($fieldName) . '" value="' . e($token) . '">';
        }
        
        // Fallback أخير - توليد token بسيط
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return '<input type="hidden" name="' . e($fieldName) . '" value="' . e($_SESSION['csrf_token']) . '">';
    }
}

/**
 * دالة مساعدة: التحقق من CSRF token
 * 
 * @param string $fieldName اسم الحقل (افتراضي: 'csrf_token')
 * @param bool $throwException إذا كان true، يرمي exception بدلاً من إرجاع false
 * @return bool True إذا كان الرمز صالحاً
 * @throws Exception إذا كان $throwException = true والرمز غير صالح
 */
if (!function_exists('csrf_validate')) {
    function csrf_validate($fieldName = 'csrf_token', $throwException = false) {
        if (class_exists('CsrfProtection')) {
            $valid = CsrfProtection::validateRequest($fieldName);
            if (!$valid && $throwException) {
                throw new Exception('CSRF token غير صالح');
            }
            return $valid;
        }
        
        // Fallback للكود القديم
        if (class_exists('Utils')) {
            $token = $_POST[$fieldName] ?? $_GET[$fieldName] ?? '';
            $valid = Utils::validateCSRFToken($token);
            if (!$valid && $throwException) {
                throw new Exception('CSRF token غير صالح');
            }
            return $valid;
        }
        
        if ($throwException) {
            throw new Exception('نظام CSRF غير متاح');
        }
        return false;
    }
}

/**
 * دالة مساعدة: الحصول على CSRF token للاستخدام في AJAX
 * 
 * @return string CSRF token
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
 * 
 * @return bool True إذا كان الرمز صالحاً
 */
if (!function_exists('csrf_validate_ajax')) {
    function csrf_validate_ajax() {
        if (class_exists('CsrfProtection')) {
            return CsrfProtection::validateAjaxRequest();
        }
        
        return false;
    }
}

/**
 * دالة مساعدة: تنظيف البيانات المدخلة
 * 
 * @param mixed $input البيانات المراد تنظيفها
 * @return mixed البيانات المنظفة
 */
if (!function_exists('sanitize')) {
    function sanitize($input) {
        if (is_array($input)) {
            return array_map('sanitize', $input);
        }
        return e(trim((string)$input));
    }
}

/**
 * دالة مساعدة: تنظيف البيانات مع السماح ببعض HTML
 * 
 * @param mixed $input البيانات المراد تنظيفها
 * @param array $allowedTags قائمة بالـ tags المسموحة
 * @return mixed البيانات المنظفة
 */
if (!function_exists('sanitize_allow_html')) {
    function sanitize_allow_html($input, $allowedTags = ['<p>', '<br>', '<strong>', '<em>']) {
        if (is_array($input)) {
            return array_map(function($item) use ($allowedTags) {
                return sanitize_allow_html($item, $allowedTags);
            }, $input);
        }
        return e_allow_html(trim((string)$input), $allowedTags);
    }
}

