<?php
/**
 * CsrfProtection - نظام حماية CSRF محسّن
 * 
 * يوفر:
 * - توليد tokens آمنة
 * - التحقق من tokens
 * - دوال مساعدة لإضافة tokens في النماذج
 * - دعم SessionManager إذا كان متاحاً
 */

require_once __DIR__ . '/../config/config.php';

class CsrfProtection {
    private static $tokenName = 'csrf_token';
    private static $tokenLifetime = 3600; // ساعة واحدة
    
    /**
     * توليد token جديد
     */
    public static function generateToken($forceNew = false) {
        // استخدام SessionManager إذا كان متاحاً
        if (class_exists('SessionManager')) {
            if ($forceNew || !SessionManager::has(self::$tokenName)) {
                $token = bin2hex(random_bytes(32));
                SessionManager::set(self::$tokenName, $token);
                SessionManager::set(self::$tokenName . '_time', time());
                return $token;
            }
            return SessionManager::get(self::$tokenName);
        }
        
        // Fallback للكود القديم
        if ($forceNew || !isset($_SESSION[self::$tokenName])) {
            $_SESSION[self::$tokenName] = bin2hex(random_bytes(32));
            $_SESSION[self::$tokenName . '_time'] = time();
        }
        return $_SESSION[self::$tokenName];
    }
    
    /**
     * الحصول على token الحالي (بدون إنشاء جديد)
     */
    public static function getToken() {
        if (class_exists('SessionManager')) {
            return SessionManager::get(self::$tokenName, '');
        }
        return $_SESSION[self::$tokenName] ?? '';
    }
    
    /**
     * التحقق من token
     */
    public static function validateToken($token, $strict = true) {
        if (empty($token)) {
            return false;
        }
        
        $storedToken = '';
        $tokenTime = 0;
        
        // استخدام SessionManager إذا كان متاحاً
        if (class_exists('SessionManager')) {
            $storedToken = SessionManager::get(self::$tokenName, '');
            $tokenTime = SessionManager::get(self::$tokenName . '_time', 0);
        } else {
            $storedToken = $_SESSION[self::$tokenName] ?? '';
            $tokenTime = $_SESSION[self::$tokenName . '_time'] ?? 0;
        }
        
        if (empty($storedToken)) {
            return false;
        }
        
        // التحقق من انتهاء الصلاحية
        if ($strict && (time() - $tokenTime) > self::$tokenLifetime) {
            return false;
        }
        
        // التحقق من تطابق token
        return hash_equals($storedToken, $token);
    }
    
    /**
     * التحقق من token من POST أو GET
     */
    public static function validateRequest($fieldName = 'csrf_token') {
        $token = $_POST[$fieldName] ?? $_GET[$fieldName] ?? '';
        return self::validateToken($token);
    }
    
    /**
     * حذف token
     */
    public static function clearToken() {
        if (class_exists('SessionManager')) {
            SessionManager::delete(self::$tokenName);
            SessionManager::delete(self::$tokenName . '_time');
        } else {
            unset($_SESSION[self::$tokenName]);
            unset($_SESSION[self::$tokenName . '_time']);
        }
    }
    
    /**
     * إرجاع HTML input للـ token
     */
    public static function getTokenField($fieldName = 'csrf_token') {
        $token = self::generateToken();
        return '<input type="hidden" name="' . htmlspecialchars($fieldName) . '" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * إرجاع token كـ JSON (للاستخدام في AJAX)
     */
    public static function getTokenJson() {
        $token = self::generateToken();
        return json_encode(['csrf_token' => $token], JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * التحقق من token في AJAX request
     */
    public static function validateAjaxRequest() {
        $token = null;
        
        // من Header
        $headers = getallheaders();
        if (isset($headers['X-CSRF-Token'])) {
            $token = $headers['X-CSRF-Token'];
        }
        
        // من POST
        if (!$token && isset($_POST['csrf_token'])) {
            $token = $_POST['csrf_token'];
        }
        
        // من JSON body
        if (!$token) {
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['csrf_token'])) {
                $token = $input['csrf_token'];
            }
        }
        
        return self::validateToken($token ?? '');
    }
    
    /**
     * تعيين اسم token
     */
    public static function setTokenName($name) {
        self::$tokenName = $name;
    }
    
    /**
     * تعيين مدة صلاحية token
     */
    public static function setTokenLifetime($seconds) {
        self::$tokenLifetime = $seconds;
    }
    
    /**
     * الحصول على معلومات token
     */
    public static function getTokenInfo() {
        $token = self::getToken();
        $tokenTime = 0;
        
        if (class_exists('SessionManager')) {
            $tokenTime = SessionManager::get(self::$tokenName . '_time', 0);
        } else {
            $tokenTime = $_SESSION[self::$tokenName . '_time'] ?? 0;
        }
        
        return [
            'token' => $token ? substr($token, 0, 8) . '...' : 'none',
            'exists' => !empty($token),
            'age' => $tokenTime ? (time() - $tokenTime) : 0,
            'expires_in' => $tokenTime ? (self::$tokenLifetime - (time() - $tokenTime)) : 0,
            'is_valid' => !empty($token) && (time() - $tokenTime) < self::$tokenLifetime
        ];
    }
}

