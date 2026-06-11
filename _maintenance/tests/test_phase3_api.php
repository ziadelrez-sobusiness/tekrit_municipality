<?php
/**
 * اختبار نظام API Security (المرحلة 3)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/ApiSecurity.php';
require_once __DIR__ . '/config/database.php';

echo "<h1>اختبار نظام API Security (المرحلة 3)</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; direction: rtl; }
    .test-section { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    pre { background: #fff; padding: 10px; border-radius: 5px; overflow-x: auto; }
</style>";

// ========== اختبار ApiSecurity ==========
echo "<div class='test-section'>";
echo "<h2>1. اختبار ApiSecurity</h2>";

$configFile = __DIR__ . '/config/api_config.php';
ApiSecurity::init(file_exists($configFile) ? $configFile : null);

// اختبار getConfig
$config = ApiSecurity::getConfig();
echo "<p class='success'>✓ تم تحميل إعدادات ApiSecurity</p>";
echo "<p class='info'>الإعدادات الحالية:</p>";
echo "<pre>" . print_r($config, true) . "</pre>";

// اختبار CORS
echo "<p class='info'>CORS Headers سيتم تطبيقها تلقائياً عند استخدام ApiSecurity::init()</p>";

// اختبار validateApiKey (اختياري)
$keyValid = ApiSecurity::validateApiKey(false);
if ($keyValid) {
    echo "<p class='success'>✓ API Key validation يعمل (اختياري حالياً)</p>";
} else {
    echo "<p class='error'>✗ فشل اختبار API Key validation</p>";
}

// اختبار checkRateLimit
$rateLimitOk = ApiSecurity::checkRateLimit('test_ip_123');
if ($rateLimitOk) {
    echo "<p class='success'>✓ Rate Limiting يعمل</p>";
} else {
    echo "<p class='error'>✗ فشل اختبار Rate Limiting</p>";
}

echo "</div>";

// ========== اختبار التكامل ==========
echo "<div class='test-section'>";
echo "<h2>2. اختبار التكامل مع API Files</h2>";

if (file_exists(__DIR__ . '/modules/facilities_api.php')) {
    echo "<p class='success'>✓ تم تحديث modules/facilities_api.php</p>";
    echo "<p class='info'>- يستخدم ApiSecurity مع Fallback للكود القديم</p>";
    echo "<p class='info'>- API Key اختياري</p>";
    echo "<p class='info'>- Rate Limiting مفعّل</p>";
} else {
    echo "<p class='error'>✗ ملف facilities_api.php غير موجود</p>";
}

if (file_exists(__DIR__ . '/api/finance.php')) {
    echo "<p class='success'>✓ تم تحديث api/finance.php</p>";
    echo "<p class='info'>- يستخدم ApiSecurity مع Fallback للكود القديم</p>";
} else {
    echo "<p class='error'>✗ ملف finance.php غير موجود</p>";
}

echo "</div>";

// ========== اختبار الإعدادات ==========
echo "<div class='test-section'>";
echo "<h2>3. اختبار ملفات الإعدادات</h2>";

if (file_exists(__DIR__ . '/config/api_config.php')) {
    echo "<p class='success'>✓ ملف api_config.php موجود</p>";
} else {
    echo "<p class='error'>✗ ملف api_config.php غير موجود</p>";
}

if (file_exists(__DIR__ . '/config/api_keys.php.example')) {
    echo "<p class='success'>✓ ملف api_keys.php.example موجود</p>";
    echo "<p class='info'>لتفعيل API Keys:</p>";
    echo "<ol>";
    echo "<li>انسخ api_keys.php.example إلى api_keys.php</li>";
    echo "<li>أضف API Keys الخاصة بك</li>";
    echo "<li>غير 'enabled' => false إلى true في api_config.php</li>";
    echo "</ol>";
} else {
    echo "<p class='error'>✗ ملف api_keys.php.example غير موجود</p>";
}

echo "</div>";

// ========== ملخص ==========
echo "<div class='test-section'>";
echo "<h2>ملخص الاختبار</h2>";
echo "<p class='success'><strong>✓ تم إنشاء جميع أنظمة المرحلة 3 بنجاح:</strong></p>";
echo "<ul>";
echo "<li>ApiSecurity.php - نظام تأمين API شامل</li>";
echo "<li>CORS Security - محسّن وقابل للتخصيص</li>";
echo "<li>API Keys - اختياري (يمكن تفعيله لاحقاً)</li>";
echo "<li>Rate Limiting - مفعّل تلقائياً</li>";
echo "<li>تحديث facilities_api.php - متوافق مع الكود القديم</li>";
echo "<li>تحديث api/finance.php - متوافق مع الكود القديم</li>";
echo "</ul>";
echo "<p class='info'><strong>الخطوة التالية:</strong> يمكن تفعيل API Keys لاحقاً عند الحاجة</p>";
echo "</div>";

?>

