<?php
// تحميل SessionManager إذا كان موجوداً
$useSessionManager = file_exists(__DIR__ . '/SessionManager.php');
if ($useSessionManager) {
    require_once __DIR__ . '/SessionManager.php';
    SessionManager::init();
} else {
    // Fallback للكود القديم
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

require_once __DIR__ . '/../config/database.php';

// تحميل LoginAttemptsTracker إذا كان موجوداً
$useLoginTracker = file_exists(__DIR__ . '/LoginAttemptsTracker.php');
if ($useLoginTracker) {
    require_once __DIR__ . '/LoginAttemptsTracker.php';
}

class Auth {
    private $db;
    private $loginTracker;
    private $lastError = ''; // لتخزين آخر رسالة خطأ
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        
        // تهيئة LoginAttemptsTracker إذا كان متاحاً
        if (class_exists('LoginAttemptsTracker')) {
            $this->loginTracker = new LoginAttemptsTracker();
        }
    }
    
    /**
     * الحصول على آخر رسالة خطأ
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    public function login($username, $password) {
        $this->lastError = ''; // إعادة تعيين رسالة الخطأ
        
        try {
            // التحقق من محاولات تسجيل الدخول الفاشلة
            if ($this->loginTracker) {
                $checkResult = $this->loginTracker->checkAttempts($username);
                if ($checkResult['blocked']) {
                    $this->lastError = 'تم حظر تسجيل الدخول مؤقتاً بسبب عدد كبير من المحاولات الفاشلة. يرجى المحاولة بعد ' . 
                                      ceil($checkResult['remaining_seconds'] / 60) . ' دقيقة.';
                    return false;
                }
            }
            
            // استعلام مبسط يعمل حتى لو لم توجد جداول roles أو departments
            $query = "
                SELECT u.id, u.username, u.password, u.full_name, u.email, u.is_active,
                       'admin' as role_name, 'الإدارة العامة' as department_name
                FROM users u
                WHERE u.username = ? AND u.is_active = 1
            ";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$username]);
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // تسجيل محاولة ناجحة
                if ($this->loginTracker) {
                    $this->loginTracker->recordAttempt($username, true, $user['id']);
                }
                
                // استخدام SessionManager إذا كان متاحاً
                if (class_exists('SessionManager')) {
                    SessionManager::set('user_id', $user['id']);
                    SessionManager::set('username', $user['username']);
                    SessionManager::set('full_name', $user['full_name']);
                    SessionManager::set('role_name', $user['role_name']);
                    SessionManager::set('department_name', $user['department_name']);
                    SessionManager::set('logged_in', true);
                    SessionManager::set('login_time', time());
                    SessionManager::regenerate(); // تجديد معرف الجلسة لأمان إضافي
                } else {
                    // Fallback للكود القديم
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role_name'] = $user['role_name'];
                    $_SESSION['department_name'] = $user['department_name'];
                    $_SESSION['logged_in'] = true;
                }
                
                return true;
            }
            
            // تسجيل محاولة فاشلة
            if ($this->loginTracker) {
                $this->loginTracker->recordAttempt($username, false);
            }
            
            return false;
            
        } catch (PDOException $e) {
            error_log("خطأ في تسجيل الدخول: " . $e->getMessage());
            
            // تسجيل محاولة فاشلة
            if ($this->loginTracker) {
                $this->loginTracker->recordAttempt($username, false);
            }
            
            return false;
        }
    }
    
    public function logout() {
        if (class_exists('SessionManager')) {
            SessionManager::destroy();
        } else {
            session_unset();
            session_destroy();
            
            // حذف ملفات تعريف الارتباط
            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time()-3600, '/');
            }
        }
    }
    
    public function isLoggedIn() {
        if (class_exists('SessionManager')) {
            return SessionManager::get('logged_in') === true && 
                   SessionManager::get('user_id') !== null;
        }
        
        // Fallback للكود القديم
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && 
               isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: ' . $this->getBaseUrl() . '/login.php');
            exit();
        }
    }
    
    public function getUserInfo() {
        if ($this->isLoggedIn()) {
            if (class_exists('SessionManager')) {
                return [
                    'id' => SessionManager::get('user_id'),
                    'username' => SessionManager::get('username', 'غير محدد'),
                    'full_name' => SessionManager::get('full_name', 'مستخدم'),
                    'role_name' => SessionManager::get('role_name', 'غير محدد'),
                    'department_name' => SessionManager::get('department_name', 'غير محدد')
                ];
            }
            
            // Fallback للكود القديم
            return [
                'id' => $_SESSION['user_id'] ?? null,
                'username' => $_SESSION['username'] ?? 'غير محدد',
                'full_name' => $_SESSION['full_name'] ?? 'مستخدم',
                'role_name' => $_SESSION['role_name'] ?? 'غير محدد',
                'department_name' => $_SESSION['department_name'] ?? 'غير محدد'
            ];
        }
        return [
            'id' => null,
            'username' => 'زائر',
            'full_name' => 'زائر',
            'role_name' => 'زائر',
            'department_name' => 'غير محدد'
        ];
    }
    
    public function getCurrentUser() {
        return $this->getUserInfo();
    }
    
    public function checkPermission($required_role = 'employee') {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $user_role = '';
        if (class_exists('SessionManager')) {
            $user_role = SessionManager::get('role_name', '');
        } else {
            $user_role = $_SESSION['role_name'] ?? '';
        }
        
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
        $host = $_SERVER['HTTP_HOST'];
        $path = dirname($_SERVER['SCRIPT_NAME']);
        return $protocol . '://' . $host . $path;
    }
}

// إنشاء كائن المصادقة العام
$auth = new Auth();
?> 
