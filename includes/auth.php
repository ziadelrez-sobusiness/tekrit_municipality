<?php
// تجنب بدء جلسة مكررة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function login($username, $password) {
        try {
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
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role_name'] = $user['role_name'];
                $_SESSION['department_name'] = $user['department_name'];
                $_SESSION['logged_in'] = true;
                
                return true;
            }
            
            return false;
            
        } catch (PDOException $e) {
            error_log("خطأ في تسجيل الدخول: " . $e->getMessage());
            return false;
        }
    }
    
    public function logout() {
        session_unset();
        session_destroy();
        
        // حذف ملفات تعريف الارتباط
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time()-3600, '/');
        }
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: ' . $this->getBaseUrl() . '/login.php');
            exit();
        }
    }
    
    public function getUserInfo() {
        if ($this->isLoggedIn()) {
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
        $host = $_SERVER['HTTP_HOST'];
        $path = dirname($_SERVER['SCRIPT_NAME']);
        return $protocol . '://' . $host . $path;
    }
}

// إنشاء كائن المصادقة العام
$auth = new Auth();
?> 
