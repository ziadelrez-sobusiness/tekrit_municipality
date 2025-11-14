<?php
/**
 * ApiSecurity - نظام تأمين API
 * 
 * يوفر:
 * - CORS Security
 * - API Keys Authentication (اختياري)
 * - Rate Limiting
 * - Input Validation
 * - Error Handling
 */

require_once __DIR__ . '/Cache.php';
require_once __DIR__ . '/Logger.php';

class ApiSecurity {
    private static $config = null;
    private static $logger = null;
    private static $cache = null;
    
    // الإعدادات الافتراضية
    private static $defaultConfig = [
        'cors' => [
            'enabled' => true,
            'allowed_origins' => ['*'], // يمكن تغييرها لـ ['https://example.com']
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization', 'X-API-Key'],
            'max_age' => 3600
        ],
        'api_keys' => [
            'enabled' => false, // false = اختياري، true = مطلوب
            'header_name' => 'X-API-Key',
            'param_name' => 'api_key',
            'keys' => [] // سيتم تحميلها من ملف منفصل
        ],
        'rate_limiting' => [
            'enabled' => true,
            'max_requests' => 100, // لكل IP
            'window' => 3600, // ساعة واحدة
            'by_api_key' => true // Rate limiting منفصل لكل API key
        ],
        'error_handling' => [
            'hide_details' => false, // true في الإنتاج
            'log_errors' => true
        ]
    ];
    
    /**
     * تهيئة ApiSecurity
     */
    public static function init($configFile = null) {
        self::$logger = new Logger();
        Cache::init();
        self::$cache = Cache::class;
        
        // تحميل الإعدادات
        if ($configFile && file_exists($configFile)) {
            self::$config = array_merge(self::$defaultConfig, require $configFile);
        } else {
            self::$config = self::$defaultConfig;
        }
        
        // تحميل API Keys من ملف منفصل
        $keysFile = __DIR__ . '/../config/api_keys.php';
        if (file_exists($keysFile)) {
            $keys = require $keysFile;
            if (isset($keys['api_keys']) && is_array($keys['api_keys'])) {
                self::$config['api_keys']['keys'] = $keys['api_keys'];
            }
        }
        
        // تطبيق CORS
        if (self::$config['cors']['enabled']) {
            self::applyCors();
        }
    }
    
    /**
     * تطبيق CORS Headers
     */
    private static function applyCors() {
        // التحقق من أن headers لم يتم إرسالها بعد
        if (headers_sent()) {
            return;
        }
        
        $cors = self::$config['cors'];
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // التحقق من Origin المسموح
        if (in_array('*', $cors['allowed_origins'])) {
            header('Access-Control-Allow-Origin: *');
        } elseif (in_array($origin, $cors['allowed_origins'])) {
            header('Access-Control-Allow-Origin: ' . $origin);
        } elseif (!empty($cors['allowed_origins'])) {
            header('Access-Control-Allow-Origin: ' . $cors['allowed_origins'][0]);
        }
        
        header('Access-Control-Allow-Methods: ' . implode(', ', $cors['allowed_methods']));
        header('Access-Control-Allow-Headers: ' . implode(', ', $cors['allowed_headers']));
        header('Access-Control-Max-Age: ' . $cors['max_age']);
        header('Access-Control-Allow-Credentials: true');
        
        // معالجة OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
    
    /**
     * التحقق من API Key
     */
    public static function validateApiKey($required = null) {
        $apiKeysConfig = self::$config['api_keys'];
        
        // إذا كان API Keys معطلاً، السماح للجميع
        if (!$apiKeysConfig['enabled']) {
            return true;
        }
        
        // الحصول على API Key من Header أو Parameter
        $apiKey = null;
        
        // من Header
        $headerName = 'HTTP_' . str_replace('-', '_', strtoupper($apiKeysConfig['header_name']));
        if (isset($_SERVER[$headerName])) {
            $apiKey = $_SERVER[$headerName];
        }
        
        // من Parameter
        if (!$apiKey && isset($_GET[$apiKeysConfig['param_name']])) {
            $apiKey = $_GET[$apiKeysConfig['param_name']];
        }
        
        if (!$apiKey && isset($_POST[$apiKeysConfig['param_name']])) {
            $apiKey = $_POST[$apiKeysConfig['param_name']];
        }
        
        // إذا كان required = true ولم يتم توفير key
        if (($required === true || $apiKeysConfig['enabled']) && !$apiKey) {
            self::sendError('API Key مطلوب', 401);
            return false;
        }
        
        // إذا تم توفير key، التحقق منه
        if ($apiKey) {
            if (!in_array($apiKey, $apiKeysConfig['keys'])) {
                self::sendError('API Key غير صحيح', 401);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * التحقق من Rate Limiting
     */
    public static function checkRateLimit($identifier = null) {
        $rateLimitConfig = self::$config['rate_limiting'];
        
        if (!$rateLimitConfig['enabled']) {
            return true;
        }
        
        // تحديد المعرف (IP أو API Key)
        if ($identifier === null) {
            $identifier = self::getClientIp();
            
            // إذا كان Rate Limiting حسب API Key، استخدام API Key كمعرف
            if ($rateLimitConfig['by_api_key']) {
                $apiKey = self::getApiKey();
                if ($apiKey) {
                    $identifier = 'api_key_' . $apiKey;
                }
            }
        }
        
        $cacheKey = 'rate_limit_' . md5($identifier);
        $requests = Cache::get($cacheKey, 0);
        
        if ($requests >= $rateLimitConfig['max_requests']) {
            self::sendError('تم تجاوز الحد المسموح من الطلبات. يرجى المحاولة لاحقاً.', 429, [
                'retry_after' => $rateLimitConfig['window']
            ]);
            return false;
        }
        
        // زيادة العداد
        Cache::increment($cacheKey, 1, $rateLimitConfig['window']);
        
        return true;
    }
    
    /**
     * التحقق من جميع متطلبات الأمان
     */
    public static function validate($options = []) {
        $options = array_merge([
            'require_api_key' => null, // null = حسب الإعدادات، true = مطلوب، false = اختياري
            'rate_limit' => true,
            'log_request' => true
        ], $options);
        
        // تسجيل الطلب
        if ($options['log_request'] && self::$logger) {
            self::$logger->info("API Request", [
                'method' => $_SERVER['REQUEST_METHOD'],
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'ip' => self::getClientIp(),
                'api_key' => self::getApiKey() ? 'provided' : 'none'
            ]);
        }
        
        // التحقق من Rate Limiting
        if ($options['rate_limit'] && !self::checkRateLimit()) {
            return false;
        }
        
        // التحقق من API Key
        if (!self::validateApiKey($options['require_api_key'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * إرسال استجابة خطأ
     */
    public static function sendError($message, $code = 400, $additional = []) {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
        }
        
        $errorConfig = self::$config['error_handling'];
        
        $response = [
            'success' => false,
            'error' => $errorConfig['hide_details'] 
                ? 'حدث خطأ في معالجة الطلب'
                : $message,
            'code' => $code
        ];
        
        if (!empty($additional)) {
            $response = array_merge($response, $additional);
        }
        
        // تسجيل الخطأ
        if ($errorConfig['log_errors'] && self::$logger) {
            self::$logger->error("API Error: $message", [
                'code' => $code,
                'ip' => self::getClientIp(),
                'uri' => $_SERVER['REQUEST_URI'] ?? ''
            ]);
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * إرسال استجابة نجاح
     */
    public static function sendSuccess($data, $code = 200, $additional = []) {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
        }
        
        $response = [
            'success' => true,
            'data' => $data
        ];
        
        if (!empty($additional)) {
            $response = array_merge($response, $additional);
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * الحصول على API Key من الطلب
     */
    private static function getApiKey() {
        $apiKeysConfig = self::$config['api_keys'];
        
        $headerName = 'HTTP_' . str_replace('-', '_', strtoupper($apiKeysConfig['header_name']));
        if (isset($_SERVER[$headerName])) {
            return $_SERVER[$headerName];
        }
        
        if (isset($_GET[$apiKeysConfig['param_name']])) {
            return $_GET[$apiKeysConfig['param_name']];
        }
        
        if (isset($_POST[$apiKeysConfig['param_name']])) {
            return $_POST[$apiKeysConfig['param_name']];
        }
        
        return null;
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
     * الحصول على الإعدادات الحالية
     */
    public static function getConfig() {
        return self::$config;
    }
    
    /**
     * تحديث الإعدادات
     */
    public static function setConfig($key, $value) {
        if (is_array($key)) {
            self::$config = array_merge(self::$config, $key);
        } else {
            self::$config[$key] = $value;
        }
    }
}

