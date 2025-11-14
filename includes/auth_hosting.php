<?php
// منع بدء جلسة مكررة مع إعدادات محسنة للـ hosting
if (session_status() === PHP_SESSION_NONE) {
    // إعدادات session محسنة للـ hosting
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    session_start();
}

// تحميل إعدادات قاعدة البيانات مع معالجة أخطاء أفضل
$database_paths = [
    __DIR__ . '/../config/database.php',
    'config/database.php',
    './config/database.php'
];

$database_loaded = false;
foreach ($database_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $database_loaded = true;
        break;
    }
}

if (!$database_loaded) {
    throw new Exception('لا يمكن العثور على ملف إعدادات قاعدة البيانات');
}

class Auth {
    private $db;
    private $debug_mode;
    
    public function __construct() {
        // تحديد وضع التشخيص بناءً على البيئة
        $this->debug_mode = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1']);
        
        try {
            $database = new Database();
            $this->db = $database->getConnection();
            
            if (!$this->db) {
                throw new Exception('فشل الاتصال بقاعدة البيانات');
            }
        } catch (Exception $e) {
            if ($this->debug_mode) {
                throw new Exception('خطأ في قاعدة البيانات: ' . $e->getMessage());
            } else {
                error_log('Database connection error: ' . $e->getMessage());
                throw new Exception('خطأ في الاتصال بقاعدة البيانات');
            }
        }
    }
    
    public function login($username, $password) {
        try {
            // التحقق من صحة المدخلات
            if (empty($username) || empty($password)) {
                return false;
            }
            
            // استعلام محسن مع معالجة أفضل للأخطاء
            $query = "
                SELECT u.id, u.username, u.password, u.full_name, u.email, u.is_active
                FROM users u
                WHERE u.username = ? AND u.is_active = 1
                LIMIT 1
            ";
            
            $stmt = $this->db->prepare($query);
            if (!$stmt) {
                throw new Exception('فشل في تحضير الاستعلام');
            }
            
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // تسجيل الدخول الناجح
                $this->setUserSession($user);
                
                // تسجيل محاولة الدخول الناجحة
                $this->logLoginAttempt($user['id'], $username, true);
                
                return true;
            } else {
                // تسجيل محاولة الدخول الفاشلة
                $this->logLoginAttempt(null, $username, false);
                return false;
            }
            
        } catch (PDOException $e) {
            if ($this->debug_mode) {
                error_log("خطأ في تسجيل الدخول: " . $e->getMessage());
            }
            return false;
        } catch (Exception $e) {
            if ($this->debug_mode) {
                error_log("خطأ عام في تسجيل الدخول: " . $e->getMessage());
            }
            return false;
        }
    }
    
    private function setUserSession($user) {
        // تجديد معرف الجلسة لأمان إضافي
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role_name'] = 'admin'; // افتراضي
        $_SESSION['department_name'] = 'الإدارة العامة'; // افتراضي
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
    }
    
    private function logLoginAttempt($user_id, $username, $success) {
        try {
            // محاولة تسجيل محاولات الدخول (اختياري)
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            // يمكن إضافة جدول لتسجيل محاولات الدخول لاحقاً
            error_log("Login attempt: User=$username, Success=" . ($success ? 'Yes' : 'No') . ", IP=$ip_address");
            
        } catch (Exception $e) {
            // تجاهل أخطاء التسجيل
        }
    }
    
    public function logout() {
        // حذف جميع متغيرات الجلسة
        $_SESSION = [];
        
        // حذف ملف تعريف ارتباط الجلسة
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time()-3600, '/');
        }
        
        // تدمير الجلسة
        session_destroy();
    }
    
    public function isLoggedIn() {
        // تحقق أساسي من الجلسة
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            return false;
        }
        
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            return false;
        }
        
        // تحقق من انتهاء صلاحية الجلسة (24 ساعة)
        if (isset($_SESSION['login_time'])) {
            $session_lifetime = 24 * 60 * 60; // 24 ساعة
            if (time() - $_SESSION['login_time'] > $session_lifetime) {
                $this->logout();
                return false;
            }
        }
        
        // تحديث وقت النشاط الأخير
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            $redirect_url = $this->getBaseUrl() . '/login.php';
            header('Location: ' . $redirect_url);
            exit();
        }
    }
    
    public function getUserInfo() {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'] ?? null,
                'username' => $_SESSION['username'] ?? 'غير محدد',
                'full_name' => $_SESSION['full_name'] ?? 'مستخدم',
                'email' => $_SESSION['email'] ?? '',
                'role_name' => $_SESSION['role_name'] ?? 'مستخدم',
                'department_name' => $_SESSION['department_name'] ?? 'غير محدد',
                'login_time' => $_SESSION['login_time'] ?? null,
                'last_activity' => $_SESSION['last_activity'] ?? null
            ];
        }
        return [
            'id' => null,
            'username' => 'زائر',
            'full_name' => 'زائر',
            'email' => '',
            'role_name' => 'زائر',
            'department_name' => 'غير محدد',
            'login_time' => null,
            'last_activity' => null
        ];
    }
    
    public function getCurrentUser() {
        return $this->getUserInfo();
    }
    
    public function checkPermission($required_role = 'employee') {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $user_role = $_SESSION['role_name'] ?? '';
        
        // تسلسل الصلاحيات: admin > mayor > employee
        switch ($required_role) {
            case 'admin':
                return $user_role === 'admin';
            case 'mayor':
                return in_array($user_role, ['admin', 'mayor']);
            case 'employee':
                return in_array($user_role, ['admin', 'mayor', 'employee']);
            default:
                return true; // السماح للجميع
        }
    }
    
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
        
        // تنظيف المسار
        $path = str_replace('\\', '/', $path);
        $path = rtrim($path, '/');
        
        return $protocol . '://' . $host . $path;
    }
    
    public function getDebugInfo() {
        if (!$this->debug_mode) {
            return [];
        }
        
        return [
            'session_status' => session_status(),
            'session_id' => session_id(),
            'logged_in' => $this->isLoggedIn(),
            'user_info' => $this->getUserInfo(),
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
            'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'unknown',
            'base_url' => $this->getBaseUrl()
        ];
    }
}

// إنشاء كائن المصادقة العام (مع معالجة الأخطاء)
try {
    $auth = new Auth();
} catch (Exception $e) {
    // في حالة فشل إنشاء كائن المصادقة
    if (in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1'])) {
        die('خطأ في نظام المصادقة: ' . $e->getMessage());
    } else {
        error_log('Auth system error: ' . $e->getMessage());
        die('خطأ في النظام. يرجى المحاولة لاحقاً.');
    }
}
?> 
