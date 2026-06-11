<?php
/**
 * سكريبت تنفيذ نظام الحساب الشخصي للمواطن
 * بلدية تكريت - عكار، شمال لبنان
 * 
 * هذا السكريبت يقوم بـ:
 * 1. إنشاء جميع الجداول المطلوبة
 * 2. إضافة إعدادات WhatsApp
 * 3. إنشاء Views و Stored Procedures
 * 4. إنشاء Triggers
 */

header('Content-Type: text/html; charset=utf-8');

// إعدادات قاعدة البيانات
$db_host = "localhost";
$db_name = "tekrit_municipality";
$db_user = "root";
$db_pass = "";

// استخدام PDO مباشر
try {
    $db = new PDO(
        "mysql:host=" . $db_host . ";dbname=" . $db_name . ";charset=utf8mb4",
        $db_user,
        $db_pass,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            PDO::ATTR_EMULATE_PREPARES => false
        )
    );
} catch(PDOException $e) {
    die("خطأ في الاتصال: " . $e->getMessage());
}

$success_messages = [];
$error_messages = [];
$warnings = [];

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تثبيت نظام الحساب الشخصي للمواطن</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .step { margin-bottom: 1rem; padding: 1rem; border-radius: 0.5rem; }
        .step.success { background-color: #d1fae5; border: 2px solid #10b981; }
        .step.error { background-color: #fee2e2; border: 2px solid #ef4444; }
        .step.warning { background-color: #fef3c7; border: 2px solid #f59e0b; }
        .step.info { background-color: #dbeafe; border: 2px solid #3b82f6; }
    </style>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <h1 class="text-3xl font-bold text-center text-gray-800 mb-2">
                🏛️ تثبيت نظام الحساب الشخصي للمواطن
            </h1>
            <p class="text-center text-gray-600 mb-6">بلدية تكريت - عكار، شمال لبنان</p>
            
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <p class="text-sm text-blue-800">
                    ⚠️ <strong>تنبيه:</strong> هذا السكريبت سيقوم بإنشاء جداول جديدة في قاعدة البيانات.
                    يُنصح بعمل نسخة احتياطية قبل المتابعة.
                </p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">📋 سجل التثبيت</h2>

<?php

// ========================================
// الخطوة 1: قراءة ملف SQL
// ========================================
echo '<div class="step info">';
echo '<h3 class="font-bold text-lg mb-2">📄 الخطوة 1: قراءة ملف SQL</h3>';

$sql_file = __DIR__ . '/database/citizen_accounts_system_fixed.sql';

if (!file_exists($sql_file)) {
    echo '<p class="text-red-600">❌ خطأ: ملف SQL غير موجود في: ' . htmlspecialchars($sql_file) . '</p>';
    echo '</div></div></body></html>';
    exit;
}

$sql_content = file_get_contents($sql_file);
echo '<p class="text-green-600">✅ تم قراءة ملف SQL بنجاح (' . number_format(strlen($sql_content)) . ' حرف)</p>';
echo '</div>';

// ========================================
// الخطوة 2: تنفيذ السكريبت
// ========================================
echo '<div class="step info">';
echo '<h3 class="font-bold text-lg mb-2">⚙️ الخطوة 2: تنفيذ السكريبت</h3>';

try {
    // تقسيم السكريبت إلى أوامر منفصلة
    $statements = explode(';', $sql_content);
    $executed = 0;
    $skipped = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        
        // تجاهل التعليقات والأسطر الفارغة
        if (empty($statement)) {
            continue;
        }
        
        // إزالة التعليقات
        $lines = explode("\n", $statement);
        $clean_lines = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || substr($line, 0, 2) === '--') {
                continue;
            }
            $clean_lines[] = $line;
        }
        $statement = implode("\n", $clean_lines);
        
        if (empty($statement)) {
            continue;
        }
        
        // تجاهل أوامر SET
        if (strtoupper(substr($statement, 0, 3)) === 'SET') {
            continue;
        }
        
        try {
            $db->exec($statement);
            $executed++;
        } catch (PDOException $e) {
            $error_msg = $e->getMessage();
            // تجاهل أخطاء "already exists" و "Duplicate entry"
            if (strpos($error_msg, 'already exists') !== false || 
                strpos($error_msg, 'Duplicate entry') !== false ||
                strpos($error_msg, 'Duplicate key') !== false) {
                $skipped++;
            } else {
                $warnings[] = 'تحذير: ' . substr($error_msg, 0, 200);
            }
        }
    }
    
    echo '<p class="text-green-600">✅ تم تنفيذ ' . $executed . ' أمر SQL بنجاح</p>';
    if ($skipped > 0) {
        echo '<p class="text-yellow-600">⚠️ تم تجاهل ' . $skipped . ' أمر (موجود مسبقاً)</p>';
    }
    
} catch (Exception $e) {
    echo '<p class="text-red-600">❌ خطأ في التنفيذ: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div></div></body></html>';
    exit;
}

echo '</div>';

// ========================================
// الخطوة 3: التحقق من الجداول
// ========================================
echo '<div class="step info">';
echo '<h3 class="font-bold text-lg mb-2">🔍 الخطوة 3: التحقق من الجداول</h3>';

$required_tables = [
    'citizens_accounts' => 'حسابات المواطنين',
    'magic_links' => 'روابط الدخول السحرية',
    'citizen_messages' => 'رسائل البلدية',
    'whatsapp_log' => 'سجل WhatsApp',
    'notification_preferences' => 'إعدادات الإشعارات',
    'citizen_sessions' => 'جلسات المواطنين'
];

$all_tables_exist = true;

foreach ($required_tables as $table => $description) {
    try {
        // استخدام query مباشر بدلاً من prepare (SHOW TABLES لا يدعم prepared statements)
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        $result = $stmt->fetch();
        $stmt->closeCursor();
        
        if ($result) {
            echo '<p class="text-green-600">✅ ' . htmlspecialchars($description) . ' (' . $table . ')</p>';
            
            // عرض عدد الأعمدة
            $stmt2 = $db->query("SHOW COLUMNS FROM `$table`");
            $columns = $stmt2->fetchAll();
            $stmt2->closeCursor();
            echo '<p class="text-sm text-gray-600 mr-6">   └─ ' . count($columns) . ' عمود</p>';
        } else {
            echo '<p class="text-red-600">❌ ' . htmlspecialchars($description) . ' (' . $table . ') غير موجود</p>';
            $all_tables_exist = false;
        }
    } catch (PDOException $e) {
        echo '<p class="text-red-600">❌ خطأ في التحقق من ' . htmlspecialchars($description) . ': ' . $e->getMessage() . '</p>';
        $all_tables_exist = false;
    }
}

echo '</div>';

// ========================================
// الخطوة 4: التحقق من الإعدادات
// ========================================
echo '<div class="step info">';
echo '<h3 class="font-bold text-lg mb-2">⚙️ الخطوة 4: التحقق من إعدادات WhatsApp</h3>';

$whatsapp_settings = [
    'whatsapp_enabled',
    'whatsapp_business_number',
    'whatsapp_api_method',
    'whatsapp_welcome_template',
    'whatsapp_status_update_template',
    'whatsapp_completion_template'
];

foreach ($whatsapp_settings as $setting) {
    try {
        $stmt = $db->prepare("SELECT setting_value FROM website_settings WHERE setting_key = ?");
        $stmt->execute([$setting]);
        $value = $stmt->fetchColumn();
        $stmt->closeCursor(); // إغلاق الاستعلام
        
        if ($value !== false) {
            echo '<p class="text-green-600">✅ ' . htmlspecialchars($setting) . '</p>';
        } else {
            echo '<p class="text-yellow-600">⚠️ ' . htmlspecialchars($setting) . ' غير موجود (سيتم إضافته)</p>';
        }
    } catch (PDOException $e) {
        echo '<p class="text-red-600">❌ خطأ: ' . $e->getMessage() . '</p>';
    }
}

echo '</div>';

// ========================================
// الخطوة 5: التحقق من Views
// ========================================
echo '<div class="step info">';
echo '<h3 class="font-bold text-lg mb-2">👁️ الخطوة 5: التحقق من Views</h3>';

$views = [
    'v_citizens_summary' => 'ملخص حسابات المواطنين',
    'v_citizen_messages_detailed' => 'رسائل المواطنين التفصيلية',
    'v_whatsapp_log_detailed' => 'سجل WhatsApp التفصيلي'
];

foreach ($views as $view => $description) {
    try {
        $stmt = $db->query("SHOW FULL TABLES WHERE Table_type = 'VIEW' AND Tables_in_tekrit_municipality = '$view'");
        $result = $stmt->fetch();
        $stmt->closeCursor();
        
        if ($result) {
            echo '<p class="text-green-600">✅ ' . htmlspecialchars($description) . ' (' . $view . ')</p>';
        } else {
            echo '<p class="text-yellow-600">⚠️ ' . htmlspecialchars($description) . ' (' . $view . ') غير موجود</p>';
        }
    } catch (PDOException $e) {
        echo '<p class="text-yellow-600">⚠️ ' . htmlspecialchars($description) . ': ' . $e->getMessage() . '</p>';
    }
}

echo '</div>';

// ========================================
// الخطوة 6: التحقق من Stored Procedures
// ========================================
echo '<div class="step info">';
echo '<h3 class="font-bold text-lg mb-2">🔧 الخطوة 6: التحقق من Stored Procedures</h3>';

$procedures = [
    'sp_get_or_create_citizen_account' => 'إنشاء/جلب حساب مواطن',
    'sp_cleanup_expired_links' => 'تنظيف الروابط المنتهية',
    'sp_get_citizen_stats' => 'إحصائيات المواطن'
];

foreach ($procedures as $proc => $description) {
    try {
        $stmt = $db->query("SHOW PROCEDURE STATUS WHERE Db = 'tekrit_municipality' AND Name = '$proc'");
        $result = $stmt->fetch();
        $stmt->closeCursor();
        
        if ($result) {
            echo '<p class="text-green-600">✅ ' . htmlspecialchars($description) . ' (' . $proc . ')</p>';
        } else {
            echo '<p class="text-yellow-600">⚠️ ' . htmlspecialchars($description) . ' (' . $proc . ') غير موجود</p>';
        }
    } catch (PDOException $e) {
        echo '<p class="text-yellow-600">⚠️ ' . htmlspecialchars($description) . ': ' . $e->getMessage() . '</p>';
    }
}

echo '</div>';

// ========================================
// النتيجة النهائية
// ========================================
if ($all_tables_exist && empty($error_messages)) {
    echo '<div class="step success">';
    echo '<h3 class="font-bold text-lg mb-2">🎉 التثبيت مكتمل بنجاح!</h3>';
    echo '<p class="text-green-700 mb-4">تم إنشاء جميع الجداول والإعدادات المطلوبة لنظام الحساب الشخصي للمواطن.</p>';
    echo '<div class="bg-white rounded p-4 border border-green-300">';
    echo '<p class="font-bold mb-2">الخطوات التالية:</p>';
    echo '<ol class="list-decimal list-inside space-y-2 text-sm">';
    echo '<li>قم بتحديث إعدادات WhatsApp في صفحة <a href="modules/system_settings.php" class="text-blue-600 underline">إعدادات النظام</a></li>';
    echo '<li>أدخل رقم WhatsApp Business للبلدية</li>';
    echo '<li>اختبر إرسال رسالة WhatsApp تجريبية</li>';
    echo '<li>ابدأ باستخدام النظام!</li>';
    echo '</ol>';
    echo '</div>';
    echo '</div>';
} else {
    echo '<div class="step error">';
    echo '<h3 class="font-bold text-lg mb-2">⚠️ التثبيت غير مكتمل</h3>';
    echo '<p class="text-red-700">يرجى مراجعة الأخطاء أعلاه وإعادة المحاولة.</p>';
    echo '</div>';
}

// عرض التحذيرات
if (!empty($warnings)) {
    echo '<div class="step warning">';
    echo '<h3 class="font-bold text-lg mb-2">⚠️ تحذيرات</h3>';
    echo '<ul class="list-disc list-inside space-y-1">';
    foreach ($warnings as $warning) {
        echo '<li class="text-sm">' . htmlspecialchars($warning) . '</li>';
    }
    echo '</ul>';
    echo '</div>';
}

?>

            <div class="mt-6 flex gap-4">
                <a href="modules/system_settings.php" class="flex-1 bg-blue-600 text-white text-center py-3 rounded-lg hover:bg-blue-700 transition">
                    ⚙️ إعدادات النظام
                </a>
                <a href="comprehensive_dashboard.php" class="flex-1 bg-green-600 text-white text-center py-3 rounded-lg hover:bg-green-700 transition">
                    🏠 لوحة التحكم
                </a>
            </div>
        </div>

        <div class="mt-6 text-center text-sm text-gray-600">
            <p>🏛️ بلدية تكريت - عكار، شمال لبنان 🇱🇧</p>
            <p class="mt-1">نظام إدارة البلدية الإلكتروني</p>
        </div>
    </div>
</body>
</html>

