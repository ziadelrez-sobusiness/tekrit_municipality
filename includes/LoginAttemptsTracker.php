<?php
/**
 * LoginAttemptsTracker - تتبع محاولات تسجيل الدخول
 * 
 * يساعد في منع هجمات Brute Force من خلال:
 * - تسجيل جميع محاولات تسجيل الدخول
 * - حظر IP بعد عدد معين من المحاولات الفاشلة
 * - تتبع الأنماط المشبوهة
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Logger.php';

class LoginAttemptsTracker {
    private $db;
    private $logger;
    private $maxAttempts = 5; // عدد المحاولات المسموحة
    private $lockoutDuration = 900; // 15 دقيقة
    private $windowDuration = 3600; // ساعة واحدة
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->logger = new Logger();
    }
    
    /**
     * تسجيل محاولة تسجيل دخول
     */
    public function recordAttempt($username, $success, $userId = null) {
        try {
            $ipAddress = $this->getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            $stmt = $this->db->prepare("
                INSERT INTO login_attempts 
                (username, ip_address, user_agent, success, user_id, attempted_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $username,
                $ipAddress,
                $userAgent,
                $success ? 1 : 0,
                $userId
            ]);
            
            // تسجيل في Logger
            if ($success) {
                $this->logger->info("Login successful", [
                    'username' => $username,
                    'user_id' => $userId,
                    'ip' => $ipAddress
                ]);
            } else {
                $this->logger->warning("Login failed", [
                    'username' => $username,
                    'ip' => $ipAddress
                ]);
            }
            
            return true;
            
        } catch (PDOException $e) {
            // في حالة عدم وجود الجدول، نسجل فقط في Logger
            $this->logger->error("Failed to record login attempt", [
                'error' => $e->getMessage(),
                'username' => $username
            ]);
            return false;
        }
    }
    
    /**
     * التحقق من عدد المحاولات الفاشلة
     */
    public function checkAttempts($username, $ipAddress = null) {
        if ($ipAddress === null) {
            $ipAddress = $this->getClientIp();
        }
        
        try {
            // حساب المحاولات الفاشلة في آخر ساعة
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as failed_attempts
                FROM login_attempts
                WHERE username = ? 
                AND ip_address = ?
                AND success = 0
                AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            
            $stmt->execute([$username, $ipAddress, $this->windowDuration]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $failedAttempts = (int)($result['failed_attempts'] ?? 0);
            
            // التحقق من الحظر
            if ($failedAttempts >= $this->maxAttempts) {
                $lockoutEnd = $this->getLockoutEndTime($username, $ipAddress);
                if ($lockoutEnd && time() < strtotime($lockoutEnd)) {
                    return [
                        'blocked' => true,
                        'failed_attempts' => $failedAttempts,
                        'lockout_ends_at' => $lockoutEnd,
                        'remaining_seconds' => strtotime($lockoutEnd) - time()
                    ];
                }
            }
            
            return [
                'blocked' => false,
                'failed_attempts' => $failedAttempts,
                'remaining_attempts' => max(0, $this->maxAttempts - $failedAttempts)
            ];
            
        } catch (PDOException $e) {
            // في حالة عدم وجود الجدول، نسمح بالمحاولة
            $this->logger->error("Failed to check login attempts", [
                'error' => $e->getMessage()
            ]);
            return [
                'blocked' => false,
                'failed_attempts' => 0,
                'remaining_attempts' => $this->maxAttempts
            ];
        }
    }
    
    /**
     * الحصول على وقت انتهاء الحظر
     */
    private function getLockoutEndTime($username, $ipAddress) {
        try {
            $stmt = $this->db->prepare("
                SELECT attempted_at
                FROM login_attempts
                WHERE username = ? 
                AND ip_address = ?
                AND success = 0
                ORDER BY attempted_at DESC
                LIMIT 1
            ");
            
            $stmt->execute([$username, $ipAddress]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $lastAttempt = strtotime($result['attempted_at']);
                $lockoutEnd = $lastAttempt + $this->lockoutDuration;
                return date('Y-m-d H:i:s', $lockoutEnd);
            }
            
            return null;
            
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * الحصول على إحصائيات المحاولات
     */
    public function getStats($username = null, $ipAddress = null, $hours = 24) {
        try {
            $where = [];
            $params = [];
            
            if ($username) {
                $where[] = "username = ?";
                $params[] = $username;
            }
            
            if ($ipAddress) {
                $where[] = "ip_address = ?";
                $params[] = $ipAddress;
            }
            
            $where[] = "attempted_at > DATE_SUB(NOW(), INTERVAL ? HOUR)";
            $params[] = $hours;
            
            $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
            
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_attempts,
                    SUM(success = 1) as successful_attempts,
                    SUM(success = 0) as failed_attempts,
                    COUNT(DISTINCT username) as unique_usernames,
                    COUNT(DISTINCT ip_address) as unique_ips
                FROM login_attempts
                $whereClause
            ");
            
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $this->logger->error("Failed to get login stats", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * تنظيف المحاولات القديمة
     */
    public function cleanOldAttempts($days = 30) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM login_attempts
                WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            
            $stmt->execute([$days]);
            $deleted = $stmt->rowCount();
            
            $this->logger->info("Cleaned old login attempts", [
                'deleted' => $deleted,
                'days' => $days
            ]);
            
            return $deleted;
            
        } catch (PDOException $e) {
            $this->logger->error("Failed to clean old login attempts", [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
    
    /**
     * الحصول على IP العميل
     */
    private function getClientIp() {
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
     * تعيين عدد المحاولات المسموحة
     */
    public function setMaxAttempts($maxAttempts) {
        $this->maxAttempts = $maxAttempts;
    }
    
    /**
     * تعيين مدة الحظر
     */
    public function setLockoutDuration($seconds) {
        $this->lockoutDuration = $seconds;
    }
    
    /**
     * تعيين مدة النافذة الزمنية
     */
    public function setWindowDuration($seconds) {
        $this->windowDuration = $seconds;
    }
}

