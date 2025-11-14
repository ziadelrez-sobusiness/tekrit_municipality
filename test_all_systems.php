<?php
/**
 * اختبار شامل لجميع الأنظمة
 * 
 * يختبر:
 * - المرحلة 1: ErrorHandler, Logger, Validator, Cache
 * - المرحلة 2: SessionManager, LoginAttemptsTracker
 * - المرحلة 3: ApiSecurity
 * - المرحلة 4: CsrfProtection
 */

// بدء output buffering قبل أي output
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تحميل جميع الأنظمة
$systems = [
    'ErrorHandler' => __DIR__ . '/includes/ErrorHandler.php',
    'Logger' => __DIR__ . '/includes/Logger.php',
    'Validator' => __DIR__ . '/includes/Validator.php',
    'Cache' => __DIR__ . '/includes/Cache.php',
    'SessionManager' => __DIR__ . '/includes/SessionManager.php',
    'LoginAttemptsTracker' => __DIR__ . '/includes/LoginAttemptsTracker.php',
    'ApiSecurity' => __DIR__ . '/includes/ApiSecurity.php',
    'CsrfProtection' => __DIR__ . '/includes/CsrfProtection.php',
    'csrf_helper' => __DIR__ . '/includes/csrf_helper.php'
];

$loaded = [];
$failed = [];

foreach ($systems as $name => $path) {
    if (file_exists($path)) {
        require_once $path;
        $loaded[] = $name;
    } else {
        $failed[] = $name;
    }
}

// تحميل ملفات الإعدادات
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار شامل - جميع الأنظمة</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            direction: rtl;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
        }
        .phase {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            border-right: 4px solid #667eea;
        }
        .phase h2 {
            color: #667eea;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .test-item {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            border: 1px solid #e0e0e0;
        }
        .test-item h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            font-weight: bold;
        }
        .warning {
            color: #ffc107;
            font-weight: bold;
        }
        .info {
            color: #17a2b8;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
            margin-right: 10px;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-error { background: #f8d7da; color: #721c24; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
            margin-top: 10px;
        }
        .summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }
        .summary h2 {
            margin-bottom: 15px;
        }
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .stat-box {
            background: rgba(255,255,255,0.2);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-box .number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-box .label {
            font-size: 14px;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 اختبار شامل لجميع الأنظمة</h1>
        <p class="subtitle">نظام بلدية تكريت - جميع المراحل</p>

        <?php
        $totalTests = 0;
        $passedTests = 0;
        $failedTests = 0;
        $warnings = 0;

        // ========== حالة التحميل ==========
        echo "<div class='phase'>";
        echo "<h2>📦 حالة تحميل الأنظمة</h2>";
        
        if (!empty($loaded)) {
            echo "<div class='test-item'>";
            echo "<span class='status-badge badge-success'>✓ محمّل</span>";
            echo "<strong>الأنظمة المحمّلة (" . count($loaded) . "):</strong><br>";
            echo implode(', ', $loaded);
            echo "</div>";
        }
        
        if (!empty($failed)) {
            echo "<div class='test-item'>";
            echo "<span class='status-badge badge-error'>✗ غير موجود</span>";
            echo "<strong>الأنظمة غير الموجودة (" . count($failed) . "):</strong><br>";
            echo implode(', ', $failed);
            echo "</div>";
            $warnings += count($failed);
        }
        echo "</div>";

        // ========== المرحلة 1: الأنظمة الأساسية ==========
        echo "<div class='phase'>";
        echo "<h2>🔧 المرحلة 1: الأنظمة الأساسية</h2>";
        
        // ErrorHandler
        if (class_exists('ErrorHandler')) {
            $totalTests++;
            echo "<div class='test-item'>";
            echo "<h3>ErrorHandler</h3>";
            try {
                ErrorHandler::init(false);
                echo "<span class='status-badge badge-success'>✓</span> تم تهيئة ErrorHandler بنجاح<br>";
                $passedTests++;
            } catch (Exception $e) {
                echo "<span class='status-badge badge-error'>✗</span> فشل تهيئة ErrorHandler: " . $e->getMessage() . "<br>";
                $failedTests++;
            }
            echo "</div>";
        }
        
        // Logger
        if (class_exists('Logger')) {
            $totalTests++;
            echo "<div class='test-item'>";
            echo "<h3>Logger</h3>";
            try {
                $logger = new Logger();
                $logger->info("اختبار Logger", ['test' => true]);
                $logger->error("اختبار خطأ", ['test' => true]);
                echo "<span class='status-badge badge-success'>✓</span> Logger يعمل بشكل صحيح<br>";
                echo "<span class='info'>تم تسجيل رسائل في logs/app_" . date('Y-m-d') . ".log</span>";
                $passedTests++;
            } catch (Exception $e) {
                echo "<span class='status-badge badge-error'>✗</span> فشل Logger: " . $e->getMessage() . "<br>";
                $failedTests++;
            }
            echo "</div>";
        }
        
        // Validator
        if (class_exists('Validator')) {
            $totalTests++;
            echo "<div class='test-item'>";
            echo "<h3>Validator</h3>";
            try {
                $data = ['name' => 'أحمد', 'email' => 'ahmed@example.com', 'age' => 25];
                $validator = new Validator($data);
                $validator->rule('name', 'required|min_length:3');
                $validator->rule('email', 'required|email');
                $validator->rule('age', 'required|integer|min:18');
                
                if ($validator->validate()) {
                    echo "<span class='status-badge badge-success'>✓</span> Validator يعمل بشكل صحيح<br>";
                    $passedTests++;
                } else {
                    echo "<span class='status-badge badge-error'>✗</span> Validator فشل في التحقق<br>";
                    $failedTests++;
                }
            } catch (Exception $e) {
                echo "<span class='status-badge badge-error'>✗</span> فشل Validator: " . $e->getMessage() . "<br>";
                $failedTests++;
            }
            echo "</div>";
        }
        
        // Cache
        if (class_exists('Cache')) {
            $totalTests++;
            echo "<div class='test-item'>";
            echo "<h3>Cache</h3>";
            try {
                Cache::init();
                Cache::set('test_key', 'test_value', 60);
                $value = Cache::get('test_key');
                
                if ($value === 'test_value') {
                    echo "<span class='status-badge badge-success'>✓</span> Cache يعمل بشكل صحيح<br>";
                    $stats = Cache::stats();
                    echo "<span class='info'>إحصائيات Cache: " . json_encode($stats, JSON_UNESCAPED_UNICODE) . "</span>";
                    $passedTests++;
                } else {
                    echo "<span class='status-badge badge-error'>✗</span> Cache فشل في الحفظ/الاسترجاع<br>";
                    $failedTests++;
                }
            } catch (Exception $e) {
                echo "<span class='status-badge badge-error'>✗</span> فشل Cache: " . $e->getMessage() . "<br>";
                $failedTests++;
            }
            echo "</div>";
        }
        
        echo "</div>";

        // ========== المرحلة 2: Authentication ==========
        echo "<div class='phase'>";
        echo "<h2>🔐 المرحلة 2: Authentication</h2>";
        
        // SessionManager
        if (class_exists('SessionManager')) {
            $totalTests++;
            echo "<div class='test-item'>";
            echo "<h3>SessionManager</h3>";
            try {
                SessionManager::init();
                SessionManager::set('test_key', 'test_value');
                $value = SessionManager::get('test_key');
                
                if ($value === 'test_value') {
                    echo "<span class='status-badge badge-success'>✓</span> SessionManager يعمل بشكل صحيح<br>";
                    $info = SessionManager::getInfo();
                    echo "<span class='info'>معلومات الجلسة: " . json_encode($info, JSON_UNESCAPED_UNICODE) . "</span>";
                    $passedTests++;
                } else {
                    echo "<span class='status-badge badge-error'>✗</span> SessionManager فشل<br>";
                    $failedTests++;
                }
            } catch (Exception $e) {
                echo "<span class='status-badge badge-error'>✗</span> فشل SessionManager: " . $e->getMessage() . "<br>";
                $failedTests++;
            }
            echo "</div>";
        }
        
        // LoginAttemptsTracker
        if (class_exists('LoginAttemptsTracker')) {
            $totalTests++;
            echo "<div class='test-item'>";
            echo "<h3>LoginAttemptsTracker</h3>";
            try {
                $tracker = new LoginAttemptsTracker();
                
                // التحقق من وجود الجدول
                $database = new Database();
                $db = $database->getConnection();
                $stmt = $db->query("SHOW TABLES LIKE 'login_attempts'");
                $tableExists = $stmt->rowCount() > 0;
                
                if ($tableExists) {
                    $tracker->recordAttempt('test_user', false);
                    $check = $tracker->checkAttempts('test_user');
                    echo "<span class='status-badge badge-success'>✓</span> LoginAttemptsTracker يعمل بشكل صحيح<br>";
                    echo "<span class='info'>جدول login_attempts موجود</span>";
                    $passedTests++;
                } else {
                    echo "<span class='status-badge badge-warning'>⚠</span> LoginAttemptsTracker جاهز لكن الجدول غير موجود<br>";
                    echo "<span class='info'>قم بتشغيل: php database/create_login_attempts_table.php</span>";
                    $warnings++;
                }
            } catch (Exception $e) {
                echo "<span class='status-badge badge-error'>✗</span> فشل LoginAttemptsTracker: " . $e->getMessage() . "<br>";
                $failedTests++;
            }
            echo "</div>";
        }
        
        echo "</div>";

        // ========== المرحلة 3: API Security ==========
        echo "<div class='phase'>";
        echo "<h2>🌐 المرحلة 3: API Security</h2>";
        
        // ApiSecurity
        if (class_exists('ApiSecurity')) {
            $totalTests++;
            echo "<div class='test-item'>";
            echo "<h3>ApiSecurity</h3>";
            try {
                $configFile = __DIR__ . '/config/api_config.php';
                ApiSecurity::init(file_exists($configFile) ? $configFile : null);
                
                $config = ApiSecurity::getConfig();
                $keyValid = ApiSecurity::validateApiKey(false);
                $rateLimitOk = ApiSecurity::checkRateLimit('test_ip');
                
                if ($keyValid && $rateLimitOk) {
                    echo "<span class='status-badge badge-success'>✓</span> ApiSecurity يعمل بشكل صحيح<br>";
                    echo "<span class='info'>CORS: " . ($config['cors']['enabled'] ? 'مفعّل' : 'معطّل') . "</span><br>";
                    echo "<span class='info'>Rate Limiting: " . ($config['rate_limiting']['enabled'] ? 'مفعّل' : 'معطّل') . "</span>";
                    $passedTests++;
                } else {
                    echo "<span class='status-badge badge-error'>✗</span> ApiSecurity فشل في التحقق<br>";
                    $failedTests++;
                }
            } catch (Exception $e) {
                echo "<span class='status-badge badge-error'>✗</span> فشل ApiSecurity: " . $e->getMessage() . "<br>";
                $failedTests++;
            }
            echo "</div>";
        }
        
        echo "</div>";

        // ========== المرحلة 4: CSRF Protection ==========
        echo "<div class='phase'>";
        echo "<h2>🛡️ المرحلة 4: CSRF Protection</h2>";
        
        // CsrfProtection
        if (class_exists('CsrfProtection')) {
            $totalTests++;
            echo "<div class='test-item'>";
            echo "<h3>CsrfProtection</h3>";
            try {
                $token = CsrfProtection::generateToken();
                $isValid = CsrfProtection::validateToken($token);
                
                if (!empty($token) && $isValid) {
                    echo "<span class='status-badge badge-success'>✓</span> CsrfProtection يعمل بشكل صحيح<br>";
                    $info = CsrfProtection::getTokenInfo();
                    echo "<span class='info'>Token: " . substr($token, 0, 20) . "...</span><br>";
                    $passedTests++;
                } else {
                    echo "<span class='status-badge badge-error'>✗</span> CsrfProtection فشل<br>";
                    $failedTests++;
                }
            } catch (Exception $e) {
                echo "<span class='status-badge badge-error'>✗</span> فشل CsrfProtection: " . $e->getMessage() . "<br>";
                $failedTests++;
            }
            echo "</div>";
        }
        
        // csrf_helper
        if (function_exists('csrf_field')) {
            $totalTests++;
            echo "<div class='test-item'>";
            echo "<h3>csrf_helper Functions</h3>";
            try {
                $field = csrf_field();
                $token = csrf_token();
                
                if (!empty($field) && !empty($token)) {
                    echo "<span class='status-badge badge-success'>✓</span> دوال csrf_helper تعمل بشكل صحيح<br>";
                    echo "<span class='info'>csrf_field(): " . htmlspecialchars(substr($field, 0, 50)) . "...</span>";
                    $passedTests++;
                } else {
                    echo "<span class='status-badge badge-error'>✗</span> دوال csrf_helper فشلت<br>";
                    $failedTests++;
                }
            } catch (Exception $e) {
                echo "<span class='status-badge badge-error'>✗</span> فشل csrf_helper: " . $e->getMessage() . "<br>";
                $failedTests++;
            }
            echo "</div>";
        }
        
        echo "</div>";

        // ========== التكامل ==========
        echo "<div class='phase'>";
        echo "<h2>🔗 اختبار التكامل</h2>";
        
        // ErrorHandler + Logger
        if (class_exists('ErrorHandler') && class_exists('Logger')) {
            $totalTests++;
            echo "<div class='test-item'>";
            echo "<h3>ErrorHandler + Logger</h3>";
            try {
                ErrorHandler::init(false);
                $logger = new Logger();
                $logger->info("اختبار التكامل");
                echo "<span class='status-badge badge-success'>✓</span> التكامل بين ErrorHandler و Logger يعمل<br>";
                $passedTests++;
            } catch (Exception $e) {
                echo "<span class='status-badge badge-error'>✗</span> فشل التكامل: " . $e->getMessage() . "<br>";
                $failedTests++;
            }
            echo "</div>";
        }
        
        // SessionManager + CsrfProtection
        if (class_exists('SessionManager') && class_exists('CsrfProtection')) {
            $totalTests++;
            echo "<div class='test-item'>";
            echo "<h3>SessionManager + CsrfProtection</h3>";
            try {
                SessionManager::init();
                $token = CsrfProtection::generateToken();
                $sessionToken = SessionManager::get('csrf_token');
                
                if ($token === $sessionToken) {
                    echo "<span class='status-badge badge-success'>✓</span> التكامل بين SessionManager و CsrfProtection يعمل<br>";
                    $passedTests++;
                } else {
                    echo "<span class='status-badge badge-warning'>⚠</span> SessionManager و CsrfProtection يعملان بشكل منفصل<br>";
                    $warnings++;
                }
            } catch (Exception $e) {
                echo "<span class='status-badge badge-error'>✗</span> فشل التكامل: " . $e->getMessage() . "<br>";
                $failedTests++;
            }
            echo "</div>";
        }
        
        echo "</div>";

        // ========== الملخص ==========
        $successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;
        
        echo "<div class='summary'>";
        echo "<h2>📊 الملخص النهائي</h2>";
        echo "<div class='summary-stats'>";
        echo "<div class='stat-box'>";
        echo "<div class='number'>$totalTests</div>";
        echo "<div class='label'>إجمالي الاختبارات</div>";
        echo "</div>";
        echo "<div class='stat-box'>";
        echo "<div class='number'>$passedTests</div>";
        echo "<div class='label'>نجحت</div>";
        echo "</div>";
        echo "<div class='stat-box'>";
        echo "<div class='number'>$failedTests</div>";
        echo "<div class='label'>فشلت</div>";
        echo "</div>";
        echo "<div class='stat-box'>";
        echo "<div class='number'>$warnings</div>";
        echo "<div class='label'>تحذيرات</div>";
        echo "</div>";
        echo "<div class='stat-box'>";
        echo "<div class='number'>$successRate%</div>";
        echo "<div class='label'>معدل النجاح</div>";
        echo "</div>";
        echo "</div>";
        
        if ($failedTests == 0 && $warnings == 0) {
            echo "<p style='margin-top: 20px; font-size: 18px; text-align: center;'>🎉 جميع الأنظمة تعمل بشكل مثالي!</p>";
        } elseif ($failedTests == 0) {
            echo "<p style='margin-top: 20px; font-size: 18px; text-align: center;'>✅ جميع الاختبارات نجحت، لكن هناك بعض التحذيرات</p>";
        } else {
            echo "<p style='margin-top: 20px; font-size: 18px; text-align: center;'>⚠️ بعض الاختبارات فشلت، يرجى مراجعة الأخطاء أعلاه</p>";
        }
        echo "</div>";
        ?>

    </div>
</body>
</html>
<?php
// إرسال المحتوى المخزن في buffer
ob_end_flush();
?>

