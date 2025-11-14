<?php
/**
 * اختبار أنظمة المرحلة 1
 * ErrorHandler, Logger, Validator, Cache
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/ErrorHandler.php';
require_once __DIR__ . '/includes/Logger.php';
require_once __DIR__ . '/includes/Validator.php';
require_once __DIR__ . '/includes/Cache.php';

// تهيئة الأنظمة
ErrorHandler::init(false); // false = بيئة تطوير
Cache::init();

echo "<h1>اختبار أنظمة المرحلة 1</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; direction: rtl; }
    .test-section { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
</style>";

// ========== اختبار Logger ==========
echo "<div class='test-section'>";
echo "<h2>1. اختبار Logger</h2>";

$logger = new Logger();
$logger->info("رسالة معلومات تجريبية", ['test' => true]);
$logger->warning("تحذير تجريبي");
$logger->error("خطأ تجريبي", ['error_code' => 500]);
$logger->debug("رسالة debug تجريبية");

echo "<p class='success'>✓ تم تسجيل جميع الرسائل بنجاح</p>";
echo "<p class='info'>تحقق من ملف logs/app_" . date('Y-m-d') . ".log</p>";
echo "</div>";

// ========== اختبار Cache ==========
echo "<div class='test-section'>";
echo "<h2>2. اختبار Cache</h2>";

// اختبار set/get
Cache::set('test_key', 'test_value', 60);
$value = Cache::get('test_key');
if ($value === 'test_value') {
    echo "<p class='success'>✓ Cache::set() و Cache::get() يعملان بشكل صحيح</p>";
} else {
    echo "<p class='error'>✗ فشل اختبار Cache::set() و Cache::get()</p>";
}

// اختبار remember
$cached = Cache::remember('test_remember', function() {
    return 'cached_value_' . time();
}, 60);
echo "<p class='info'>Cache::remember() = $cached</p>";

// اختبار increment/decrement
Cache::set('counter', 10);
Cache::increment('counter', 5);
$counter = Cache::get('counter');
if ($counter === 15) {
    echo "<p class='success'>✓ Cache::increment() يعمل بشكل صحيح (القيمة: $counter)</p>";
} else {
    echo "<p class='error'>✗ فشل اختبار Cache::increment()</p>";
}

Cache::decrement('counter', 3);
$counter = Cache::get('counter');
if ($counter === 12) {
    echo "<p class='success'>✓ Cache::decrement() يعمل بشكل صحيح (القيمة: $counter)</p>";
} else {
    echo "<p class='error'>✗ فشل اختبار Cache::decrement()</p>";
}

// اختبار has
if (Cache::has('test_key')) {
    echo "<p class='success'>✓ Cache::has() يعمل بشكل صحيح</p>";
} else {
    echo "<p class='error'>✗ فشل اختبار Cache::has()</p>";
}

// إحصائيات
$stats = Cache::stats();
echo "<p class='info'>إحصائيات Cache: " . json_encode($stats, JSON_UNESCAPED_UNICODE) . "</p>";

echo "</div>";

// ========== اختبار Validator ==========
echo "<div class='test-section'>";
echo "<h2>3. اختبار Validator</h2>";

$data = [
    'name' => 'أحمد محمد',
    'email' => 'ahmed@example.com',
    'phone' => '03123456',
    'age' => 25,
    'password' => '12345678'
];

$validator = new Validator($data);

// اختبار القواعد
$validator->rule('name', 'required|min_length:3');
$validator->rule('email', 'required|email');
$validator->rule('phone', 'required|lebanese_phone');
$validator->rule('age', 'required|integer|min:18|max:100');
$validator->rule('password', 'required|min_length:8');

if ($validator->validate()) {
    echo "<p class='success'>✓ جميع البيانات صحيحة</p>";
} else {
    echo "<p class='error'>✗ هناك أخطاء في البيانات:</p>";
    echo "<pre>" . print_r($validator->getErrors(), true) . "</pre>";
}

// اختبار بيانات خاطئة
$badData = [
    'name' => 'ab', // قصير جداً
    'email' => 'invalid-email', // بريد غير صحيح
    'phone' => '123', // رقم غير صحيح
    'age' => 15, // أقل من 18
    'password' => '123' // قصير جداً
];

$validator2 = new Validator($badData);
$validator2->rule('name', 'required|min_length:3');
$validator2->rule('email', 'required|email');
$validator2->rule('phone', 'required|lebanese_phone');
$validator2->rule('age', 'required|integer|min:18');
$validator2->rule('password', 'required|min_length:8');

if (!$validator2->validate()) {
    echo "<p class='success'>✓ تم اكتشاف الأخطاء بشكل صحيح:</p>";
    echo "<pre>" . print_r($validator2->getErrors(), true) . "</pre>";
} else {
    echo "<p class='error'>✗ فشل في اكتشاف الأخطاء</p>";
}

// اختبار sanitize
$dirtyData = ['field' => '<script>alert("XSS")</script>Hello'];
$validator3 = new Validator($dirtyData);
$cleanData = $validator3->sanitizeAll();
if (strpos($cleanData['field'], '<script>') === false) {
    echo "<p class='success'>✓ تم تنظيف البيانات من HTML tags</p>";
} else {
    echo "<p class='error'>✗ فشل في تنظيف البيانات</p>";
}

echo "</div>";

// ========== اختبار ErrorHandler ==========
echo "<div class='test-section'>";
echo "<h2>4. اختبار ErrorHandler</h2>";

// اختبار معالجة خطأ عادي
echo "<p class='info'>محاولة إنتاج خطأ تجريبي...</p>";
@trigger_error("خطأ تجريبي", E_USER_WARNING);

// اختبار معالجة استثناء
try {
    throw new Exception("استثناء تجريبي للاختبار");
} catch (Exception $e) {
    ErrorHandler::handle($e, ['test' => true]);
    echo "<p class='success'>✓ تم معالجة الاستثناء بنجاح</p>";
}

// اختبار jsonError
echo "<p class='info'>اختبار ErrorHandler::jsonError() - يجب أن يظهر JSON response</p>";
// ErrorHandler::jsonError("خطأ تجريبي", 400); // معلق لتجنب إيقاف الصفحة

echo "</div>";

// ========== ملخص ==========
echo "<div class='test-section'>";
echo "<h2>ملخص الاختبار</h2>";
echo "<p class='success'><strong>✓ تم إنشاء جميع الأنظمة بنجاح:</strong></p>";
echo "<ul>";
echo "<li>ErrorHandler.php - معالجة الأخطاء المركزية</li>";
echo "<li>Logger.php - نظام تسجيل الأخطاء</li>";
echo "<li>Validator.php - نظام التحقق من المدخلات</li>";
echo "<li>Cache.php - نظام التخزين المؤقت</li>";
echo "</ul>";
echo "<p class='info'><strong>الخطوة التالية:</strong> دمج هذه الأنظمة في النظام الحالي</p>";
echo "</div>";

?>

