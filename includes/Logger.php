<?php
/**
 * Logger - نظام تسجيل الأخطاء والأحداث
 * 
 * يوفر نظام تسجيل مركزي لجميع الأخطاء والأحداث المهمة في النظام
 */

class Logger {
    private $logDir;
    private $logFile;
    private $maxFileSize = 10485760; // 10MB
    private $maxFiles = 5;
    
    // مستويات السجل
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';
    
    public function __construct($logDir = null) {
        // تحديد مجلد السجلات
        if ($logDir === null) {
            $logDir = __DIR__ . '/../logs';
        }
        
        $this->logDir = $logDir;
        
        // إنشاء المجلد إذا لم يكن موجوداً
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        
        // تحديد ملف السجل اليومي
        $this->logFile = $this->logDir . '/app_' . date('Y-m-d') . '.log';
    }
    
    /**
     * تسجيل رسالة
     */
    public function log($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logMessage = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;
        
        // كتابة في الملف
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // تدوير الملفات إذا لزم الأمر
        $this->rotateLogs();
    }
    
    /**
     * تسجيل رسالة DEBUG
     */
    public function debug($message, $context = []) {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }
    
    /**
     * تسجيل رسالة INFO
     */
    public function info($message, $context = []) {
        $this->log(self::LEVEL_INFO, $message, $context);
    }
    
    /**
     * تسجيل رسالة WARNING
     */
    public function warning($message, $context = []) {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }
    
    /**
     * تسجيل رسالة ERROR
     */
    public function error($message, $context = []) {
        $this->log(self::LEVEL_ERROR, $message, $context);
        
        // للأخطاء الحرجة، نسجل أيضاً في ملف منفصل
        $errorFile = $this->logDir . '/errors_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logMessage = "[$timestamp] $message$contextStr" . PHP_EOL;
        file_put_contents($errorFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * تسجيل رسالة CRITICAL
     */
    public function critical($message, $context = []) {
        $this->log(self::LEVEL_CRITICAL, $message, $context);
        
        // للأخطاء الحرجة، نسجل في ملف منفصل
        $criticalFile = $this->logDir . '/critical_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logMessage = "[$timestamp] $message$contextStr" . PHP_EOL;
        file_put_contents($criticalFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // يمكن إضافة إشعارات إضافية هنا (مثل إرسال بريد إلكتروني)
    }
    
    /**
     * تسجيل طلب HTTP
     */
    public function logRequest($method, $uri, $params = [], $response = null) {
        $context = [
            'method' => $method,
            'uri' => $uri,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        if (!empty($params)) {
            // إخفاء البيانات الحساسة
            $safeParams = $this->sanitizeParams($params);
            $context['params'] = $safeParams;
        }
        
        if ($response !== null) {
            $context['response'] = $response;
        }
        
        $this->info("HTTP Request: $method $uri", $context);
    }
    
    /**
     * تسجيل عملية قاعدة البيانات
     */
    public function logDatabase($query, $params = [], $executionTime = null) {
        $context = [
            'query' => $query,
            'params' => $this->sanitizeParams($params),
            'execution_time' => $executionTime
        ];
        
        $this->debug("Database Query", $context);
    }
    
    /**
     * تنظيف المعاملات من البيانات الحساسة
     */
    private function sanitizeParams($params) {
        $sensitiveKeys = ['password', 'pass', 'pwd', 'token', 'secret', 'key', 'api_key'];
        $sanitized = [];
        
        foreach ($params as $key => $value) {
            $keyLower = strtolower($key);
            $isSensitive = false;
            
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (strpos($keyLower, $sensitiveKey) !== false) {
                    $isSensitive = true;
                    break;
                }
            }
            
            $sanitized[$key] = $isSensitive ? '***HIDDEN***' : $value;
        }
        
        return $sanitized;
    }
    
    /**
     * تدوير ملفات السجل (حذف القديمة)
     */
    private function rotateLogs() {
        $files = glob($this->logDir . '/app_*.log');
        
        if (count($files) > $this->maxFiles) {
            // ترتيب الملفات حسب تاريخ التعديل
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // حذف الملفات القديمة
            $filesToDelete = array_slice($files, 0, count($files) - $this->maxFiles);
            foreach ($filesToDelete as $file) {
                @unlink($file);
            }
        }
        
        // التحقق من حجم الملف الحالي
        if (file_exists($this->logFile) && filesize($this->logFile) > $this->maxFileSize) {
            $backupFile = $this->logFile . '.' . time() . '.bak';
            @rename($this->logFile, $backupFile);
        }
    }
    
    /**
     * قراءة السجلات
     */
    public function getLogs($level = null, $limit = 100, $date = null) {
        if ($date === null) {
            $date = date('Y-m-d');
        }
        
        $logFile = $this->logDir . '/app_' . $date . '.log';
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $logs = [];
        
        foreach (array_reverse($lines) as $line) {
            if ($limit > 0 && count($logs) >= $limit) {
                break;
            }
            
            // تحليل السطر
            if (preg_match('/^\[(.+?)\] \[(.+?)\] (.+)$/', $line, $matches)) {
                $logLevel = $matches[2];
                
                // فلترة حسب المستوى
                if ($level !== null && $logLevel !== $level) {
                    continue;
                }
                
                $logs[] = [
                    'timestamp' => $matches[1],
                    'level' => $logLevel,
                    'message' => $matches[3]
                ];
            }
        }
        
        return $logs;
    }
    
    /**
     * حذف السجلات القديمة
     */
    public function clearOldLogs($days = 30) {
        $files = glob($this->logDir . '/*.log');
        $cutoffTime = time() - ($days * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                @unlink($file);
            }
        }
    }
}

