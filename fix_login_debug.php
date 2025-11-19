<?php
/**
 * سكريبت لفحص مشكلة تسجيل الدخول
 */

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// جلب جميع المستخدمين
$users = $db->query("SELECT id, username, password, is_active FROM users LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>فحص كلمات المرور في قاعدة البيانات</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Username</th><th>Password Hash</th><th>Is Active</th><th>Hash Type</th></tr>";

foreach ($users as $user) {
    $hash = $user['password'];
    $hashType = 'Unknown';
    
    // التحقق من نوع الـ hash
    if (strpos($hash, '$2y$') === 0 || strpos($hash, '$2a$') === 0 || strpos($hash, '$2b$') === 0) {
        $hashType = 'bcrypt (password_hash)';
    } elseif (strpos($hash, '$argon2i$') === 0 || strpos($hash, '$argon2id$') === 0) {
        $hashType = 'Argon2';
    } elseif (strlen($hash) < 60) {
        $hashType = 'Plain text (غير مشفر!)';
    } else {
        $hashType = 'Unknown hash format';
    }
    
    echo "<tr>";
    echo "<td>{$user['id']}</td>";
    echo "<td>{$user['username']}</td>";
    echo "<td style='font-size:10px;'>{$hash}</td>";
    echo "<td>" . ($user['is_active'] ? 'نعم' : 'لا') . "</td>";
    echo "<td>{$hashType}</td>";
    echo "</tr>";
}

echo "</table>";

// اختبار password_verify
echo "<h2>اختبار password_verify</h2>";
if (isset($_GET['test_username']) && isset($_GET['test_password'])) {
    $test_username = $_GET['test_username'];
    $test_password = $_GET['test_password'];
    
    $stmt = $db->prepare("SELECT password FROM users WHERE username = ?");
    $stmt->execute([$test_username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $result = password_verify($test_password, $user['password']);
        echo "<p>Username: {$test_username}</p>";
        echo "<p>Password: {$test_password}</p>";
        echo "<p>Hash: {$user['password']}</p>";
        echo "<p>password_verify result: " . ($result ? '✅ صحيح' : '❌ خطأ') . "</p>";
    } else {
        echo "<p>❌ المستخدم غير موجود</p>";
    }
} else {
    echo "<p>استخدم: ?test_username=admin&test_password=password</p>";
}



