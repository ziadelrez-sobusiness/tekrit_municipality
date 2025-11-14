<?php
require_once __DIR__ . '/../config/config.php';

class Utils {
    
    /**
     * إنشاء رمز CSRF
     * 
     * @deprecated استخدم CsrfProtection::generateToken() بدلاً منه
     */
    public static function generateCSRFToken() {
        // استخدام CsrfProtection إذا كان متاحاً
        if (class_exists('CsrfProtection')) {
            return CsrfProtection::generateToken();
        }
        
        // Fallback للكود القديم
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    /**
     * التحقق من رمز CSRF
     * 
     * @deprecated استخدم CsrfProtection::validateToken() بدلاً منه
     */
    public static function validateCSRFToken($token) {
        // استخدام CsrfProtection إذا كان متاحاً
        if (class_exists('CsrfProtection')) {
            return CsrfProtection::validateToken($token);
        }
        
        // Fallback للكود القديم
        return isset($_SESSION[CSRF_TOKEN_NAME]) && 
               hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
    
    /**
     * تنظيف البيانات المدخلة
     */
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * التحقق من صحة البريد الإلكتروني
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * التحقق من صحة رقم الهاتف العراقي
     */
    public static function validateIraqiPhone($phone) {
        // إزالة المسافات والرموز
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // التحقق من الأنماط المختلفة للأرقام العراقية
        $patterns = [
            '/^07[3-9][0-9]{8}$/',  // أرقام الموبايل
            '/^01[0-9]{8}$/',       // أرقام بغداد
            '/^0[2-6][0-9]{7,8}$/'  // أرقام المحافظات
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $phone)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * التحقق من صحة الرقم الوطني العراقي
     */
    public static function validateIraqiNationalId($nationalId) {
        // إزالة المسافات والرموز
        $nationalId = preg_replace('/[^0-9]/', '', $nationalId);
        
        // التحقق من الطول (11 رقم)
        if (strlen($nationalId) !== 11) {
            return false;
        }
        
        // التحقق من أن الرقم لا يبدأ بصفر
        if ($nationalId[0] === '0') {
            return false;
        }
        
        return true;
    }
    
    /**
     * تنسيق التاريخ للعرض
     */
    public static function formatDate($date, $format = 'Y-m-d H:i') {
        if (empty($date)) {
            return '';
        }
        
        $dateTime = new DateTime($date);
        return $dateTime->format($format);
    }
    
    /**
     * إنشاء كلمة مرور عشوائية
     */
    public static function generateRandomPassword($length = 8) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $password;
    }
    
    /**
     * تشفير كلمة المرور
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * التحقق من كلمة المرور
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * إرسال استجابة JSON
     */
    public static function sendJsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * إعادة توجيه
     */
    public static function redirect($url) {
        header("Location: $url");
        exit;
    }
    
    /**
     * التحقق من تسجيل الدخول
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * التحقق من صلاحية المستخدم
     */
    public static function hasRole($role) {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
    }
    
    /**
     * تسجيل الخروج
     */
    public static function logout() {
        session_destroy();
        self::redirect('login.php');
    }
    
    /**
     * التحقق من انتهاء صلاحية الجلسة
     */
    public static function checkSessionTimeout() {
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
                self::logout();
            }
        }
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * تحويل الحجم بالبايت إلى وحدة قابلة للقراءة
     */
    public static function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * إنشاء رمز تحقق عشوائي
     */
    public static function generateVerificationCode($length = 6) {
        return str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }
    
    /**
     * تسجيل الأخطاء
     */
    public static function logError($message, $file = 'error.log') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        file_put_contents($file, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * إنشاء معرف فريد
     */
    public static function generateUniqueId($prefix = '') {
        return $prefix . uniqid() . rand(1000, 9999);
    }
    
    /**
     * التحقق من صحة التاريخ
     */
    public static function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    /**
     * تحويل النص إلى رابط آمن (slug)
     */
    public static function createSlug($text) {
        // تحويل النص العربي إلى transliteration
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\-]/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        return trim($text, '-');
    }
}
?> 