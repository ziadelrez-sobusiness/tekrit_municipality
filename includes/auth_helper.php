<?php
/**
 * مساعد التحقق من الصلاحيات والمصادقة
 * يحتوي على دوال مساعدة للتحقق من صلاحيات المستخدمين
 */

require_once __DIR__ . '/../config/database.php';

/**
 * التحقق من صلاحية معينة للمستخدم الحالي
 */
function hasPermission($permission_name, $user_id = null) {
    global $db;
    
    if ($user_id === null) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        $user_id = $_SESSION['user_id'];
    }
    
    // المدير الرئيسي له صلاحيات كاملة
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin') {
        return true;
    }
    
    if (!isset($db)) {
        $database = new Database();
        $db = $database->getConnection();
    }
    
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM user_permissions up
            JOIN permissions p ON up.permission_id = p.id
            WHERE up.user_id = ? AND p.permission_name = ? AND up.is_active = 1 AND p.is_active = 1
        ");
        $stmt->execute([$user_id, $permission_name]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * التحقق من تسجيل الدخول وتوجيه المستخدم إذا لم يكن مسجلاً
 */
function requireLogin($redirect_url = 'login.php') {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . $redirect_url);
        exit();
    }
}

/**
 * التحقق من صلاحية مطلوبة وتوجيه المستخدم إذا لم تكن متوفرة
 */
function requirePermission($permission_name, $redirect_url = null) {
    requireLogin();
    
    if (!hasPermission($permission_name)) {
        if ($redirect_url === null) {
            $redirect_url = 'dashboard.php?error=no_permission';
        }
        header('Location: ' . $redirect_url);
        exit();
    }
}

/**
 * الحصول على جميع صلاحيات المستخدم
 */
function getUserPermissions($user_id = null) {
    global $db;
    
    if ($user_id === null) {
        if (!isset($_SESSION['user_id'])) {
            return [];
        }
        $user_id = $_SESSION['user_id'];
    }
    
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin') {
        if (!isset($db)) {
            $database = new Database();
            $db = $database->getConnection();
        }
        
        $stmt = $db->query("SELECT permission_name FROM permissions WHERE is_active = 1");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    if (!isset($db)) {
        $database = new Database();
        $db = $database->getConnection();
    }
    
    try {
        $stmt = $db->prepare("
            SELECT p.permission_name
            FROM user_permissions up
            JOIN permissions p ON up.permission_id = p.id
            WHERE up.user_id = ? AND up.is_active = 1 AND p.is_active = 1
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("خطأ في جلب صلاحيات المستخدم: " . $e->getMessage());
        return [];
    }
}

/**
 * تسجيل أنشطة المستخدم
 */
function logUserActivity($action, $details = null) {
    global $db;
    
    if (!isset($_SESSION['user_id'])) {
        return;
    }
    
    if (!isset($db)) {
        $database = new Database();
        $db = $database->getConnection();
    }
    
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS user_activity_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                action VARCHAR(255) NOT NULL,
                details TEXT,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        $stmt = $db->prepare("
            INSERT INTO user_activity_log (user_id, action, details, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("خطأ في تسجيل نشاط المستخدم: " . $e->getMessage());
    }
}
?>
