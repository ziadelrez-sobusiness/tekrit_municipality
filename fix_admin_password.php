<?php
/**
 * سكريبت لإصلاح كلمة مرور admin
 */

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// كلمة المرور الجديدة
$username = 'admin';
$password = 'Admin@123';

// تشفير كلمة المرور
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>إصلاح كلمة مرور admin</h2>";

try {
    // التحقق من وجود المستخدم
    $stmt = $db->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "<p>✅ المستخدم موجود: {$user['username']} (ID: {$user['id']})</p>";
        echo "<p>كلمة المرور الحالية (Hash): <code style='font-size:10px;'>{$user['password']}</code></p>";
        
        // تحديث كلمة المرور
        $update_stmt = $db->prepare("UPDATE users SET password = ? WHERE username = ?");
        $update_stmt->execute([$hashed_password, $username]);
        
        echo "<p>✅ تم تحديث كلمة المرور بنجاح!</p>";
        echo "<p>كلمة المرور الجديدة (Hash): <code style='font-size:10px;'>{$hashed_password}</code></p>";
        
        // اختبار password_verify
        $test_result = password_verify($password, $hashed_password);
        echo "<p>اختبار password_verify: " . ($test_result ? '✅ نجح' : '❌ فشل') . "</p>";
        
        echo "<hr>";
        echo "<h3>معلومات تسجيل الدخول:</h3>";
        echo "<p><strong>اسم المستخدم:</strong> {$username}</p>";
        echo "<p><strong>كلمة المرور:</strong> {$password}</p>";
        echo "<p>✅ يمكنك الآن تسجيل الدخول باستخدام هذه البيانات</p>";
        
    } else {
        echo "<p>❌ المستخدم '{$username}' غير موجود في قاعدة البيانات</p>";
        echo "<p>هل تريد إنشاء مستخدم جديد؟</p>";
        
        // إنشاء مستخدم جديد
        if (isset($_GET['create'])) {
            $stmt = $db->prepare("INSERT INTO users (username, password, full_name, is_active) VALUES (?, ?, ?, 1)");
            $stmt->execute([$username, $hashed_password, 'مدير النظام']);
            
            echo "<p>✅ تم إنشاء المستخدم بنجاح!</p>";
            echo "<p><strong>اسم المستخدم:</strong> {$username}</p>";
            echo "<p><strong>كلمة المرور:</strong> {$password}</p>";
        } else {
            echo "<p><a href='?create=1'>إنشاء مستخدم جديد</a></p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ خطأ: " . $e->getMessage() . "</p>";
}
