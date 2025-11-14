<?php
/**
 * اختبار نظام CSRF Protection (المرحلة 4)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/CsrfProtection.php';
require_once __DIR__ . '/includes/csrf_helper.php';

echo "<h1>اختبار نظام CSRF Protection (المرحلة 4)</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; direction: rtl; }
    .test-section { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    pre { background: #fff; padding: 10px; border-radius: 5px; overflow-x: auto; }
    .form-example { background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin: 10px 0; }
</style>";

// ========== اختبار CsrfProtection ==========
echo "<div class='test-section'>";
echo "<h2>1. اختبار CsrfProtection</h2>";

// اختبار generateToken
$token1 = CsrfProtection::generateToken();
if (!empty($token1)) {
    echo "<p class='success'>✓ تم توليد token بنجاح</p>";
    echo "<p class='info'>Token: " . substr($token1, 0, 20) . "...</p>";
} else {
    echo "<p class='error'>✗ فشل توليد token</p>";
}

// اختبار getToken
$token2 = CsrfProtection::getToken();
if ($token1 === $token2) {
    echo "<p class='success'>✓ getToken() يعيد نفس token</p>";
} else {
    echo "<p class='error'>✗ getToken() لا يعيد نفس token</p>";
}

// اختبار validateToken
if (CsrfProtection::validateToken($token1)) {
    echo "<p class='success'>✓ validateToken() يعمل بشكل صحيح</p>";
} else {
    echo "<p class='error'>✗ فشل validateToken()</p>";
}

// اختبار token خاطئ
if (!CsrfProtection::validateToken('wrong_token')) {
    echo "<p class='success'>✓ validateToken() يرفض tokens خاطئة</p>";
} else {
    echo "<p class='error'>✗ validateToken() يقبل tokens خاطئة</p>";
}

// اختبار getTokenInfo
$info = CsrfProtection::getTokenInfo();
echo "<p class='info'>معلومات Token:</p>";
echo "<pre>" . print_r($info, true) . "</pre>";

echo "</div>";

// ========== اختبار الدوال المساعدة ==========
echo "<div class='test-section'>";
echo "<h2>2. اختبار الدوال المساعدة</h2>";

// اختبار csrf_field()
$field = csrf_field();
if (!empty($field) && strpos($field, 'csrf_token') !== false) {
    echo "<p class='success'>✓ csrf_field() يعمل بشكل صحيح</p>";
    echo "<p class='info'>HTML Output:</p>";
    echo "<pre>" . htmlspecialchars($field) . "</pre>";
} else {
    echo "<p class='error'>✗ فشل csrf_field()</p>";
}

// اختبار csrf_token()
$token3 = csrf_token();
if (!empty($token3)) {
    echo "<p class='success'>✓ csrf_token() يعمل بشكل صحيح</p>";
} else {
    echo "<p class='error'>✗ فشل csrf_token()</p>";
}

echo "</div>";

// ========== مثال على استخدام في النماذج ==========
echo "<div class='test-section'>";
echo "<h2>3. مثال على استخدام في النماذج</h2>";

echo "<div class='form-example'>";
echo "<h3>مثال 1: نموذج HTML بسيط</h3>";
echo "<pre>";
echo htmlspecialchars('
<form method="POST">
    ' . csrf_field() . '
    <input type="text" name="name" required>
    <button type="submit">إرسال</button>
</form>
');
echo "</pre>";
echo "</div>";

echo "<div class='form-example'>";
echo "<h3>مثال 2: التحقق من CSRF في معالج النموذج</h3>";
echo "<pre>";
echo htmlspecialchars('
if ($_SERVER[\'REQUEST_METHOD\'] === \'POST\') {
    if (!csrf_validate()) {
        die(\'رمز الأمان غير صحيح\');
    }
    // معالجة النموذج...
}
');
echo "</pre>";
echo "</div>";

echo "<div class='form-example'>";
echo "<h3>مثال 3: استخدام في AJAX</h3>";
echo "<pre>";
echo htmlspecialchars('
// في JavaScript
fetch(\'/api/endpoint\', {
    method: \'POST\',
    headers: {
        \'Content-Type\': \'application/json\',
        \'X-CSRF-Token\': \'' . csrf_token() . '\'
    },
    body: JSON.stringify({data: \'value\'})
});
');
echo "</pre>";
echo "</div>";

echo "</div>";

// ========== اختبار التكامل مع Utils ==========
echo "<div class='test-section'>";
echo "<h2>4. اختبار التكامل مع Utils.php</h2>";

if (file_exists(__DIR__ . '/includes/Utils.php')) {
    require_once __DIR__ . '/includes/Utils.php';
    
    // Utils يجب أن يستخدم CsrfProtection تلقائياً
    $utilsToken = Utils::generateCSRFToken();
    if (!empty($utilsToken)) {
        echo "<p class='success'>✓ Utils::generateCSRFToken() يعمل مع CsrfProtection</p>";
    }
    
    $isValid = Utils::validateCSRFToken($utilsToken);
    if ($isValid) {
        echo "<p class='success'>✓ Utils::validateCSRFToken() يعمل مع CsrfProtection</p>";
    }
} else {
    echo "<p class='error'>✗ ملف Utils.php غير موجود</p>";
}

echo "</div>";

// ========== ملخص ==========
echo "<div class='test-section'>";
echo "<h2>ملخص الاختبار</h2>";
echo "<p class='success'><strong>✓ تم إنشاء جميع أنظمة المرحلة 4 بنجاح:</strong></p>";
echo "<ul>";
echo "<li>CsrfProtection.php - نظام CSRF محسّن</li>";
echo "<li>csrf_helper.php - دوال مساعدة</li>";
echo "<li>تحديث Utils.php - دعم CsrfProtection</li>";
echo "<li>دوال مساعدة: csrf_field(), csrf_token(), csrf_validate()</li>";
echo "</ul>";
echo "<p class='info'><strong>الاستخدام:</strong></p>";
echo "<ul>";
echo "<li>في النماذج: استخدم <?= csrf_field() ?></li>";
echo "<li>في المعالجات: استخدم csrf_validate()</li>";
echo "<li>في AJAX: أرسل token في Header X-CSRF-Token</li>";
echo "</ul>";
echo "</div>";

?>

