<?php
// إعدادات خاصة للـ hosting
error_reporting(E_ALL);
ini_set('display_errors', 0); // إخفاء الأخطاء في الإنتاج
ini_set('log_errors', 1);

// تحميل أنظمة الأمان (Security Headers و دوال مساعدة)
if (file_exists(__DIR__ . '/includes/init_security.php')) {
    require_once __DIR__ . '/includes/init_security.php';
}

// بدء الجلسة مع إعدادات محسنة
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 86400);
    ini_set('session.gc_maxlifetime', 86400);
    session_start();
}

$auth = null;
$message = $_GET['message'] ?? '';
$error = '';

// محاولة تحميل الملفات المطلوبة مع معالجة الأخطاء
try {
    // البحث عن ملف المصادقة في مسارات مختلفة
    $auth_paths = ['includes/auth.php', './includes/auth.php', __DIR__ . '/includes/auth.php'];
    $auth_found = false;
    
    foreach ($auth_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $auth_found = true;
            break;
        }
    }
    
    if (!$auth_found) {
        throw new Exception('ملف المصادقة غير موجود');
    }
    
    // البحث عن ملف قاعدة البيانات
    $db_paths = ['config/database.php', './config/database.php', __DIR__ . '/config/database.php'];
    $db_found = false;
    
    foreach ($db_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $db_found = true;
            break;
        }
    }
    
    if (!$db_found) {
        throw new Exception('ملف إعدادات قاعدة البيانات غير موجود');
    }
    
    // تحميل مساعد reCAPTCHA (اختياري)
    if (file_exists('includes/recaptcha_helper.php')) {
        require_once 'includes/recaptcha_helper.php';
    }
    
    $auth = new Auth();
    
} catch (Exception $e) {
    $error = 'خطأ في تحميل النظام: ' . $e->getMessage();
    error_log('Login system error: ' . $e->getMessage());
}

// إذا كان مسجل دخول بالفعل، اذهب للوحة التحكم
if ($auth && $auth->isLoggedIn()) {
    header('Location: comprehensive_dashboard.php');
    exit();
}

// معالجة تسجيل الدخول
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // التحقق من reCAPTCHA أولاً (إذا كان متاحاً)
    if (function_exists('verify_recaptcha')) {
        $recaptcha_result = verify_recaptcha($_POST, $_SERVER['REMOTE_ADDR'] ?? null);
        if (!$recaptcha_result['success']) {
            $error = $recaptcha_result['error'];
        }
    }
    
    if (!$error && !empty($username) && !empty($password)) {
        if ($auth->login($username, $password)) {
            header('Location: comprehensive_dashboard.php');
            exit();
        } else {
            // استخدام رسالة الخطأ من Auth إذا كانت متوفرة
            $error = $auth->getLastError() ?: 'اسم المستخدم أو كلمة المرور غير صحيحة';
        }
    } else {
        $error = 'يرجى إدخال اسم المستخدم وكلمة المرور';
    }
}

// جلب قائمة المستخدمين للمساعدة
$database = new Database();
$db = $database->getConnection();

try {
    $users_query = "SELECT id, username, full_name FROM users WHERE is_active = 1 ORDER BY id LIMIT 10";
    $users = $db->query($users_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = [];
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل دخول سريع - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <?php if (class_exists('RecaptchaHelper')): ?>
        <?= RecaptchaHelper::renderScript() ?>
        <?= RecaptchaHelper::renderCSS() ?>
    <?php endif; ?>
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-500 to-purple-600 min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-2xl p-8 w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">🏛️ بلدية تكريت</h1>
            <p class="text-gray-600">تسجيل دخول للنظام</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                ✅ <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-2">اسم المستخدم</label>
                <input type="text" id="username" name="username" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                       placeholder="أدخل اسم المستخدم" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">كلمة المرور</label>
                <input type="password" id="password" name="password" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                       placeholder="أدخل كلمة المرور">
            </div>

            <!-- reCAPTCHA v3 (اختياري) -->
            <?php if (class_exists('RecaptchaHelper')): ?>
            <div class="recaptcha-container">
                <?= RecaptchaHelper::renderWidget('login') ?>
            </div>
            <?php endif; ?>

            <button type="submit" 
                    class="w-full bg-indigo-600 text-white py-3 px-4 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition duration-200 font-medium">
                🚀 دخول إلى النظام
            </button>
        </form>

        

        <div class="mt-6 text-center">
            <a href="public/index.php" class="text-indigo-600 hover:text-indigo-800 text-sm">
                🏠 العودة للصفحة الرئيسية
            </a>
        </div>

        <!-- معلومات إضافية -->
       
    </div>

    <script>
        // تحسين تجربة المستخدم
        document.addEventListener('DOMContentLoaded', function() {
            const usernameField = document.getElementById('username');
            const passwordField = document.getElementById('password');
            
            // التركيز على حقل اسم المستخدم
            usernameField.focus();
            
            // عند كتابة اسم المستخدم، ضع نفس القيمة في كلمة المرور
            usernameField.addEventListener('input', function() {
                if (passwordField.value === '') {
                    passwordField.value = usernameField.value;
                }
            });
        });
    </script>
</body>
</html> 
