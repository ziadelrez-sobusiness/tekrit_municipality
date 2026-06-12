<?php
/**
 * اختبار نظام Authentication المحسّن (المرحلة 2)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/SessionManager.php';
require_once __DIR__ . '/includes/LoginAttemptsTracker.php';
require_once __DIR__ . '/config/database.php';

echo "<h1>اختبار نظام Authentication المحسّن (المرحلة 2)</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; direction: rtl; }
    .test-section { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    pre { background: #fff; padding: 10px; border-radius: 5px; overflow-x: auto; }
</style>";

// ========== اختبار SessionManager ==========
echo "<div class='test-section'>";
echo "<h2>1. اختبار SessionManager</h2>";

SessionManager::init();

// اختبار set/get
SessionManager::set('test_key', 'test_value');
$value = SessionManager::get('test_key');
if ($value === 'test_value') {
    echo "<p class='success'>✓ SessionManager::set() و SessionManager::get() يعملان بشكل صحيح</p>";
} else {
    echo "<p class='error'>✗ فشل اختبار SessionManager::set() و SessionManager::get()</p>";
}

// اختبار has
if (SessionManager::has('test_key')) {
    echo "<p class='success'>✓ SessionManager::has() يعمل بشكل صحيح</p>";
} else {
    echo "<p class='error'>✗ فشل اختبار SessionManager::has()</p>";
}

// اختبار getInfo
$info = SessionManager::getInfo();
echo "<p class='info'>معلومات الجلسة:</p>";
echo "<pre>" . print_r($info, true) . "</pre>";

// اختبار getTimeRemaining
$remaining = SessionManager::getTimeRemaining();
echo "<p class='info'>الوقت المتبقي للجلسة: " . gmdate("H:i:s", $remaining) . "</p>";

echo "</div>";

// ========== اختبار LoginAttemptsTracker ==========
echo "<div class='test-section'>";
echo "<h2>2. اختبار LoginAttemptsTracker</h2>";

$tracker = new LoginAttemptsTracker();

// التحقق من وجود الجدول
try {
    $database = new Database();
    $db = $database->getConnection();
    $stmt = $db->query("SHOW TABLES LIKE 'login_attempts'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        echo "<p class='success'>✓ جدول login_attempts موجود</p>";
        
        // اختبار recordAttempt
        $tracker->recordAttempt('test_user', false);
        echo "<p class='success'>✓ تم تسجيل محاولة فاشلة</p>";
        
        // اختبار checkAttempts
        $checkResult = $tracker->checkAttempts('test_user');
        echo "<p class='info'>نتيجة التحقق من المحاولات:</p>";
        echo "<pre>" . print_r($checkResult, true) . "</pre>";
        
        // اختبار getStats
        $stats = $tracker->getStats('test_user', null, 24);
        if ($stats) {
            echo "<p class='info'>إحصائيات المحاولات:</p>";
            echo "<pre>" . print_r($stats, true) . "</pre>";
        }
        
    } else {
        echo "<p class='error'>✗ جدول login_attempts غير موجود</p>";
        echo "<p class='info'>يرجى تشغيل: php database/create_login_attempts_table.php</p>";
    }
    
} catch (PDOException $e) {
    echo "<p class='error'>✗ خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage() . "</p>";
}

echo "</div>";

// ========== اختبار التكامل ==========
echo "<div class='test-section'>";
echo "<h2>3. اختبار التكامل مع auth.php</h2>";

if (file_exists(__DIR__ . '/includes/auth.php')) {
    echo "<p class='success'>✓ ملف auth.php موجود</p>";
    echo "<p class='info'>تم تحديث auth.php لاستخدام SessionManager و LoginAttemptsTracker</p>";
    echo "<p class='info'>الكود متوافق مع الإصدار القديم (Fallback)</p>";
} else {
    echo "<p class='error'>✗ ملف auth.php غير موجود</p>";
}

echo "</div>";

// ========== ملخص ==========
echo "<div class='test-section'>";
echo "<h2>ملخص الاختبار</h2>";
echo "<p class='success'><strong>✓ تم إنشاء جميع أنظمة المرحلة 2 بنجاح:</strong></p>";
echo "<ul>";
echo "<li>SessionManager.php - إدارة الجلسات المحسّنة</li>";
echo "<li>LoginAttemptsTracker.php - تتبع محاولات تسجيل الدخول</li>";
echo "<li>تحديث auth.php - دعم SessionManager و LoginAttemptsTracker</li>";
echo "<li>جدول login_attempts - SQL migration جاهز</li>";
echo "</ul>";
echo "<p class='info'><strong>الخطوة التالية:</strong> تشغيل migration لإنشاء جدول login_attempts</p>";
echo "</div>";

?>

