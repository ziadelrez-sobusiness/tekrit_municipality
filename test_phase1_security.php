<?php
/**
 * اختبار المرحلة الأولى: تحسينات الأمان
 * 
 * يختبر:
 * - دوال المساعدة (e(), csrf_field(), etc.)
 * - Security Headers
 * - CSRF Protection
 * - API Security
 */

// بدء output buffering
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تحميل الأنظمة
require_once __DIR__ . '/includes/init_security.php';
require_once __DIR__ . '/includes/CsrfProtection.php';
require_once __DIR__ . '/includes/ApiSecurity.php';

?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار المرحلة الأولى - تحسينات الأمان</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .test-section { margin: 20px 0; padding: 20px; border: 2px solid #e5e7eb; border-radius: 8px; }
        .success { background-color: #d1fae5; border-color: #10b981; }
        .error { background-color: #fee2e2; border-color: #ef4444; }
        .warning { background-color: #fef3c7; border-color: #f59e0b; }
        .info { background-color: #dbeafe; border-color: #3b82f6; }
    </style>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-8 text-center">🔒 اختبار المرحلة الأولى: تحسينات الأمان</h1>
        
        <?php
        $tests = [];
        $totalTests = 0;
        $passedTests = 0;
        
        // --- Test 1: دوال المساعدة ---
        echo "<div class='test-section info'>";
        echo "<h2 class='text-xl font-bold mb-4'>1. اختبار دوال المساعدة</h2>";
        
        // Test e() function
        $totalTests++;
        $testInput = '<script>alert("XSS")</script>';
        $output = e($testInput);
        if (strpos($output, '<script>') === false && strpos($output, '&lt;script&gt;') !== false) {
            echo "<p class='text-green-700'>✅ دالة e() تعمل بشكل صحيح - تم تنظيف XSS</p>";
            $passedTests++;
        } else {
            echo "<p class='text-red-700'>❌ دالة e() لا تعمل بشكل صحيح</p>";
        }
        
        // Test csrf_field()
        $totalTests++;
        if (function_exists('csrf_field')) {
            $csrfField = csrf_field();
            if (strpos($csrfField, 'csrf_token') !== false && strpos($csrfField, 'hidden') !== false) {
                echo "<p class='text-green-700'>✅ دالة csrf_field() تعمل بشكل صحيح</p>";
                $passedTests++;
            } else {
                echo "<p class='text-red-700'>❌ دالة csrf_field() لا تعمل بشكل صحيح</p>";
            }
        } else {
            echo "<p class='text-red-700'>❌ دالة csrf_field() غير موجودة</p>";
        }
        
        // Test csrf_token()
        $totalTests++;
        if (function_exists('csrf_token')) {
            $token = csrf_token();
            if (!empty($token) && strlen($token) >= 32) {
                echo "<p class='text-green-700'>✅ دالة csrf_token() تعمل بشكل صحيح - Token: " . substr($token, 0, 10) . "...</p>";
                $passedTests++;
            } else {
                echo "<p class='text-red-700'>❌ دالة csrf_token() لا تعمل بشكل صحيح</p>";
            }
        } else {
            echo "<p class='text-red-700'>❌ دالة csrf_token() غير موجودة</p>";
        }
        
        echo "</div>";
        
        // --- Test 2: Security Headers ---
        echo "<div class='test-section info'>";
        echo "<h2 class='text-xl font-bold mb-4'>2. اختبار Security Headers</h2>";
        
        $totalTests++;
        if (class_exists('SecurityHeaders')) {
            echo "<p class='text-green-700'>✅ SecurityHeaders class موجود</p>";
            $passedTests++;
        } else {
            echo "<p class='text-red-700'>❌ SecurityHeaders class غير موجود</p>";
        }
        
        // التحقق من Headers (يجب أن تكون موجودة في Response)
        $totalTests++;
        $headers = headers_list();
        $hasCSP = false;
        $hasXFrame = false;
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Security-Policy') !== false) {
                $hasCSP = true;
            }
            if (stripos($header, 'X-Frame-Options') !== false) {
                $hasXFrame = true;
            }
        }
        
        if ($hasCSP) {
            echo "<p class='text-green-700'>✅ Content-Security-Policy header موجود</p>";
            $passedTests++;
        } else {
            echo "<p class='text-yellow-700'>⚠️ Content-Security-Policy header غير موجود (قد يكون بسبب headers_sent())</p>";
        }
        
        if ($hasXFrame) {
            echo "<p class='text-green-700'>✅ X-Frame-Options header موجود</p>";
            $passedTests++;
        } else {
            echo "<p class='text-yellow-700'>⚠️ X-Frame-Options header غير موجود (قد يكون بسبب headers_sent())</p>";
        }
        
        echo "</div>";
        
        // --- Test 3: CSRF Protection ---
        echo "<div class='test-section info'>";
        echo "<h2 class='text-xl font-bold mb-4'>3. اختبار CSRF Protection</h2>";
        
        $totalTests++;
        if (class_exists('CsrfProtection')) {
            echo "<p class='text-green-700'>✅ CsrfProtection class موجود</p>";
            $passedTests++;
        } else {
            echo "<p class='text-red-700'>❌ CsrfProtection class غير موجود</p>";
        }
        
        // Test token generation and validation
        $totalTests++;
        if (class_exists('CsrfProtection')) {
            $token1 = CsrfProtection::generateToken();
            $token2 = CsrfProtection::generateToken();
            if ($token1 === $token2) {
                echo "<p class='text-green-700'>✅ CSRF token generation يعمل - نفس الـ token يتم إرجاعه</p>";
                $passedTests++;
            } else {
                echo "<p class='text-yellow-700'>⚠️ CSRF token generation يولد tokens مختلفة (قد يكون طبيعي)</p>";
            }
            
            // Test validation
            $totalTests++;
            if (CsrfProtection::validateToken($token1)) {
                echo "<p class='text-green-700'>✅ CSRF token validation يعمل</p>";
                $passedTests++;
            } else {
                echo "<p class='text-red-700'>❌ CSRF token validation لا يعمل</p>";
            }
        }
        
        echo "</div>";
        
        // --- Test 4: API Security ---
        echo "<div class='test-section info'>";
        echo "<h2 class='text-xl font-bold mb-4'>4. اختبار API Security</h2>";
        
        $totalTests++;
        if (class_exists('ApiSecurity')) {
            echo "<p class='text-green-700'>✅ ApiSecurity class موجود</p>";
            $passedTests++;
        } else {
            echo "<p class='text-red-700'>❌ ApiSecurity class غير موجود</p>";
        }
        
        // Test initialization
        $totalTests++;
        if (class_exists('ApiSecurity')) {
            $configFile = __DIR__ . '/config/api_config.php';
            if (file_exists($configFile)) {
                ApiSecurity::init($configFile);
                echo "<p class='text-green-700'>✅ ApiSecurity تم تهيئته بنجاح</p>";
                $passedTests++;
            } else {
                echo "<p class='text-yellow-700'>⚠️ ملف api_config.php غير موجود - سيستخدم الإعدادات الافتراضية</p>";
            }
        }
        
        echo "</div>";
        
        // --- Test 5: ملفات محدثة ---
        echo "<div class='test-section info'>";
        echo "<h2 class='text-xl font-bold mb-4'>5. التحقق من الملفات المحدثة</h2>";
        
        $filesToCheck = [
            'api/financial_transactions.php' => 'ApiSecurity',
            'public/citizen-requests.php' => 'CSRF Protection',
            'public/contact.php' => 'CSRF Protection',
            'public/index.php' => 'Security Headers',
            'comprehensive_dashboard.php' => 'Security Headers'
        ];
        
        foreach ($filesToCheck as $file => $feature) {
            $totalTests++;
            if (file_exists($file)) {
                $content = file_get_contents($file);
                $hasFeature = false;
                
                if ($feature === 'ApiSecurity') {
                    $hasFeature = strpos($content, 'ApiSecurity') !== false;
                } elseif ($feature === 'CSRF Protection') {
                    $hasFeature = (strpos($content, 'csrf_validate') !== false || 
                                  strpos($content, 'CsrfProtection') !== false) &&
                                 strpos($content, 'csrf_field') !== false;
                } elseif ($feature === 'Security Headers') {
                    $hasFeature = strpos($content, 'init_security') !== false;
                }
                
                if ($hasFeature) {
                    echo "<p class='text-green-700'>✅ $file - $feature موجود</p>";
                    $passedTests++;
                } else {
                    echo "<p class='text-yellow-700'>⚠️ $file - $feature غير موجود أو غير مفعّل</p>";
                }
            } else {
                echo "<p class='text-red-700'>❌ $file غير موجود</p>";
            }
        }
        
        echo "</div>";
        
        // --- النتيجة النهائية ---
        $percentage = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;
        $resultClass = $percentage >= 80 ? 'success' : ($percentage >= 50 ? 'warning' : 'error');
        
        echo "<div class='test-section $resultClass'>";
        echo "<h2 class='text-xl font-bold mb-4'>📊 النتيجة النهائية</h2>";
        echo "<p class='text-lg font-bold'>الاختبارات الناجحة: $passedTests / $totalTests</p>";
        echo "<p class='text-lg font-bold'>نسبة النجاح: $percentage%</p>";
        
        if ($percentage >= 80) {
            echo "<p class='text-green-700 font-bold mt-4'>✅ المرحلة الأولى تم تنفيذها بنجاح!</p>";
        } elseif ($percentage >= 50) {
            echo "<p class='text-yellow-700 font-bold mt-4'>⚠️ المرحلة الأولى جزئية - يحتاج مراجعة</p>";
        } else {
            echo "<p class='text-red-700 font-bold mt-4'>❌ المرحلة الأولى تحتاج إصلاح</p>";
        }
        
        echo "</div>";
        
        // --- تعليمات ---
        echo "<div class='test-section info'>";
        echo "<h2 class='text-xl font-bold mb-4'>📝 تعليمات الاختبار اليدوي</h2>";
        echo "<ol class='list-decimal list-inside space-y-2'>";
        echo "<li>افتح <code>public/citizen-requests.php</code> وحاول إرسال نموذج - يجب أن يعمل</li>";
        echo "<li>افتح <code>public/contact.php</code> وحاول إرسال رسالة - يجب أن يعمل</li>";
        echo "<li>افتح Developer Tools > Network وتحقق من Response Headers - يجب أن ترى CSP و X-Frame-Options</li>";
        echo "<li>افتح <code>api/financial_transactions.php</code> (مع authentication) - يجب أن يعمل مع Rate Limiting</li>";
        echo "</ol>";
        echo "</div>";
        ?>
    </div>
</body>
</html>
<?php
ob_end_flush();
?>



