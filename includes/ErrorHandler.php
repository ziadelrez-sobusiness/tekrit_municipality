<?php
/**
 * ErrorHandler - نظام معالجة الأخطاء المركزي
 * 
 * يوفر معالجة موحدة للأخطاء في جميع أنحاء النظام
 * مع إخفاء التفاصيل الحساسة في بيئة الإنتاج
 */

require_once __DIR__ . '/Logger.php';

class ErrorHandler {
    private static $isProduction = false;
    private static $logger = null;
    
    /**
     * تهيئة ErrorHandler
     */
    public static function init($isProduction = false) {
        self::$isProduction = $isProduction;
        self::$logger = new Logger();
        
        // تعيين معالج الأخطاء المخصص
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }
    
    /**
     * معالجة الأخطاء العادية (E_ERROR, E_WARNING, etc.)
     */
    public static function handleError($errno, $errstr, $errfile, $errline) {
        // تجاهل الأخطاء التي تم قمعها بـ @
        if (error_reporting() === 0) {
            return false;
        }
        
        $errorType = self::getErrorType($errno);
        $errorMessage = "[$errorType] $errstr in $errfile on line $errline";
        
        // تسجيل الخطأ
        self::$logger->error($errorMessage, [
            'type' => $errorType,
            'file' => $errfile,
            'line' => $errline,
            'error_code' => $errno
        ]);
        
        // في بيئة الإنتاج، لا نعرض تفاصيل الخطأ
        if (self::$isProduction) {
            // إرجاع رسالة عامة فقط
            return true;
        }
        
        // في بيئة التطوير، عرض التفاصيل
        echo "<div style='background: #fee; border: 1px solid #fcc; padding: 10px; margin: 10px; border-radius: 5px;'>";
        echo "<strong>خطأ:</strong> $errstr<br>";
        echo "<small>في الملف: $errfile (السطر: $errline)</small>";
        echo "</div>";
        
        return true;
    }
    
    /**
     * معالجة الاستثناءات (Exceptions)
     */
    public static function handleException($exception) {
        $errorMessage = "Uncaught Exception: " . $exception->getMessage();
        $trace = $exception->getTraceAsString();
        
        // تسجيل الخطأ
        self::$logger->error($errorMessage, [
            'type' => 'Exception',
            'class' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $trace
        ]);
        
        // في بيئة الإنتاج
        if (self::$isProduction) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'حدث خطأ في النظام. يرجى المحاولة لاحقاً.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // في بيئة التطوير
        http_response_code(500);
        echo "<div style='background: #fee; border: 2px solid #f00; padding: 15px; margin: 10px; border-radius: 5px;'>";
        echo "<h3 style='color: #c00; margin-top: 0;'>خطأ غير معالج:</h3>";
        echo "<p><strong>الرسالة:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
        echo "<p><strong>الملف:</strong> " . htmlspecialchars($exception->getFile()) . "</p>";
        echo "<p><strong>السطر:</strong> " . $exception->getLine() . "</p>";
        echo "<details><summary>تفاصيل الخطأ</summary><pre>" . htmlspecialchars($trace) . "</pre></details>";
        echo "</div>";
        exit;
    }
    
    /**
     * معالجة الأخطاء القاتلة (Fatal Errors)
     */
    public static function handleShutdown() {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $errorMessage = "Fatal Error: {$error['message']} in {$error['file']} on line {$error['line']}";
            
            // تسجيل الخطأ
            self::$logger->error($errorMessage, [
                'type' => 'Fatal Error',
                'file' => $error['file'],
                'line' => $error['line'],
                'error_code' => $error['type']
            ]);
            
            // في بيئة الإنتاج
            if (self::$isProduction) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'حدث خطأ في النظام. يرجى المحاولة لاحقاً.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // في بيئة التطوير
            echo "<div style='background: #fee; border: 2px solid #f00; padding: 15px; margin: 10px; border-radius: 5px;'>";
            echo "<h3 style='color: #c00; margin-top: 0;'>خطأ قاتل:</h3>";
            echo "<p><strong>الرسالة:</strong> " . htmlspecialchars($error['message']) . "</p>";
            echo "<p><strong>الملف:</strong> " . htmlspecialchars($error['file']) . "</p>";
            echo "<p><strong>السطر:</strong> " . $error['line'] . "</p>";
            echo "</div>";
        }
    }
    
    /**
     * معالجة خطأ مخصص (للاستخدام في try-catch)
     */
    public static function handle($exception, $context = []) {
        $errorMessage = $exception->getMessage();
        
        // تسجيل الخطأ
        self::$logger->error($errorMessage, array_merge([
            'type' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine()
        ], $context));
        
        // إرجاع استجابة JSON للـ API
        if (self::isApiRequest()) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => self::$isProduction 
                    ? 'حدث خطأ في النظام. يرجى المحاولة لاحقاً.'
                    : $errorMessage
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // إرجاع رسالة خطأ للصفحات العادية
        if (self::$isProduction) {
            return 'حدث خطأ في النظام. يرجى المحاولة لاحقاً.';
        }
        
        return $errorMessage;
    }
    
    /**
     * التحقق من نوع الخطأ
     */
    private static function getErrorType($errno) {
        $types = [
            E_ERROR => 'Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated'
        ];
        
        return $types[$errno] ?? 'Unknown Error';
    }
    
    /**
     * التحقق من أن الطلب هو API request
     */
    private static function isApiRequest() {
        $path = $_SERVER['REQUEST_URI'] ?? '';
        return strpos($path, '/api/') !== false || 
               strpos($path, '_api') !== false ||
               isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    }
    
    /**
     * إرجاع استجابة JSON آمنة للأخطاء
     */
    public static function jsonError($message, $code = 500, $hideDetails = null) {
        if ($hideDetails === null) {
            $hideDetails = self::$isProduction;
        }
        
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = [
            'success' => false,
            'error' => $hideDetails 
                ? 'حدث خطأ في النظام. يرجى المحاولة لاحقاً.'
                : $message
        ];
        
        // في بيئة التطوير، إضافة معلومات إضافية
        if (!$hideDetails && isset($_GET['debug'])) {
            $response['debug'] = [
                'file' => debug_backtrace()[0]['file'] ?? 'unknown',
                'line' => debug_backtrace()[0]['line'] ?? 'unknown'
            ];
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

