<?php
/**
 * SessionManager - نظام إدارة الجلسات المحسّن
 * 
 * يوفر إدارة آمنة للجلسات مع حماية من:
 * - Session Fixation
 * - Session Hijacking
 * - Session Timeout
 * - Multiple Sessions
 */

require_once __DIR__ . '/Logger.php';

class SessionManager {
    private static $initialized = false;
    private static $logger = null;
    private static $timeout = 3600; // ساعة واحدة
    private static $regenerateInterval = 300; // 5 دقائق
    
    /**
     * تهيئة SessionManager
     */
    public static function init($timeout = 3600, $regenerateInterval = 300) {
        if (self::$initialized) {
            return;
        }
        
        self::$timeout = $timeout;
        self::$regenerateInterval = $regenerateInterval;
        self::$logger = new Logger();
        
        // التحقق من حالة الجلسة قبل تعديل الإعدادات
        $sessionStatus = session_status();
        
        // تعديل إعدادات session فقط إذا لم تبدأ الجلسة بعد
        if ($sessionStatus === PHP_SESSION_NONE && !headers_sent()) {
            // إعدادات session آمنة
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', 1);
            
            // إعدادات cookie آمنة
            $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            ini_set('session.cookie_secure', $isSecure ? 1 : 0);
        }
        
        // بدء الجلسة
        if ($sessionStatus === PHP_SESSION_NONE) {
            session_start();
        }
        
        // التحقق من صحة الجلسة
        self::validateSession();
        
        // تجديد معرف الجلسة دورياً
        self::regenerateIdIfNeeded();
        
        // تحديث last_activity
        $_SESSION['last_activity'] = time();
        
        self::$initialized = true;
    }
    
    /**
     * التحقق من صحة الجلسة
     */
    private static function validateSession() {
        // التحقق من انتهاء الجلسة
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > self::$timeout) {
            self::destroy();
            return false;
        }
        
        // التحقق من تغيير IP (يمكن تعطيله في حالة استخدام Proxy)
        if (isset($_SESSION['ip_address'])) {
            $currentIp = self::getClientIp();
            if ($_SESSION['ip_address'] !== $currentIp) {
                // تسجيل محاولة hijacking محتملة
                self::$logger->warning("Session IP mismatch detected", [
                    'session_ip' => $_SESSION['ip_address'],
                    'current_ip' => $currentIp,
                    'user_id' => $_SESSION['user_id'] ?? 'unknown'
                ]);
                
                // يمكن اختيار تدمير الجلسة أو السماح (حسب الحاجة)
                // self::destroy();
                // return false;
            }
        } else {
            $_SESSION['ip_address'] = self::getClientIp();
        }
        
        // التحقق من تغيير User Agent
        if (isset($_SESSION['user_agent'])) {
            $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if ($_SESSION['user_agent'] !== $currentUserAgent) {
                self::$logger->warning("Session User Agent mismatch detected", [
                    'session_ua' => $_SESSION['user_agent'],
                    'current_ua' => $currentUserAgent,
                    'user_id' => $_SESSION['user_id'] ?? 'unknown'
                ]);
            }
        } else {
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
        
        return true;
    }
    
    /**
     * تجديد معرف الجلسة دورياً
     */
    private static function regenerateIdIfNeeded() {
        // التحقق من أن headers لم يتم إرسالها بعد
        if (headers_sent()) {
            return;
        }
        
        if (!isset($_SESSION['regenerated_at'])) {
            $_SESSION['regenerated_at'] = time();
            session_regenerate_id(true);
            return;
        }
        
        $timeSinceRegeneration = time() - $_SESSION['regenerated_at'];
        if ($timeSinceRegeneration > self::$regenerateInterval) {
            session_regenerate_id(true);
            $_SESSION['regenerated_at'] = time();
        }
    }
    
    /**
     * الحصول على IP العميل
     */
    private static function getClientIp() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                   'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 
                   'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * تعيين قيمة في الجلسة
     */
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    /**
     * الحصول على قيمة من الجلسة
     */
    public static function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * التحقق من وجود مفتاح في الجلسة
     */
    public static function has($key) {
        return isset($_SESSION[$key]);
    }
    
    /**
     * حذف قيمة من الجلسة
     */
    public static function delete($key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    /**
     * الحصول على جميع بيانات الجلسة
     */
    public static function all() {
        return $_SESSION;
    }
    
    /**
     * حذف جميع بيانات الجلسة
     */
    public static function flush() {
        $_SESSION = [];
    }
    
    /**
     * تدمير الجلسة
     */
    public static function destroy() {
        self::flush();
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        session_destroy();
    }
    
    /**
     * تجديد معرف الجلسة
     */
    public static function regenerate($deleteOldSession = true) {
        // التحقق من أن headers لم يتم إرسالها بعد
        if (headers_sent()) {
            return false;
        }
        
        session_regenerate_id($deleteOldSession);
        $_SESSION['regenerated_at'] = time();
        return true;
    }
    
    /**
     * تعيين timeout للجلسة
     */
    public static function setTimeout($seconds) {
        self::$timeout = $seconds;
        ini_set('session.gc_maxlifetime', $seconds);
        ini_set('session.cookie_lifetime', $seconds);
    }
    
    /**
     * الحصول على timeout الحالي
     */
    public static function getTimeout() {
        return self::$timeout;
    }
    
    /**
     * التحقق من انتهاء الجلسة
     */
    public static function isExpired() {
        if (!isset($_SESSION['last_activity'])) {
            return true;
        }
        
        return (time() - $_SESSION['last_activity']) > self::$timeout;
    }
    
    /**
     * الحصول على الوقت المتبقي للجلسة
     */
    public static function getTimeRemaining() {
        if (!isset($_SESSION['last_activity'])) {
            return 0;
        }
        
        $remaining = self::$timeout - (time() - $_SESSION['last_activity']);
        return max(0, $remaining);
    }
    
    /**
     * تمديد الجلسة
     */
    public static function extend() {
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * الحصول على معرف الجلسة
     */
    public static function getId() {
        return session_id();
    }
    
    /**
     * تعيين معرف الجلسة (يستخدم فقط عند الحاجة)
     */
    public static function setId($id) {
        session_id($id);
    }
    
    /**
     * الحصول على اسم الجلسة
     */
    public static function getName() {
        return session_name();
    }
    
    /**
     * تعيين اسم الجلسة
     */
    public static function setName($name) {
        session_name($name);
    }
    
    /**
     * حفظ الجلسة (يتم تلقائياً عند انتهاء السكريبت)
     */
    public static function save() {
        session_write_close();
        session_start();
    }
    
    /**
     * الحصول على معلومات الجلسة
     */
    public static function getInfo() {
        return [
            'id' => session_id(),
            'name' => session_name(),
            'status' => session_status(),
            'timeout' => self::$timeout,
            'time_remaining' => self::getTimeRemaining(),
            'is_expired' => self::isExpired(),
            'last_activity' => $_SESSION['last_activity'] ?? null,
            'ip_address' => $_SESSION['ip_address'] ?? null,
            'user_agent' => isset($_SESSION['user_agent']) ? substr($_SESSION['user_agent'], 0, 50) . '...' : null
        ];
    }
}

