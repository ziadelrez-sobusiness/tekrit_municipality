<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حذف جداول نظام الحساب الشخصي</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <h1 class="text-3xl font-bold text-center text-gray-800 mb-2">
                🗑️ حذف جداول نظام الحساب الشخصي
            </h1>
            <p class="text-center text-gray-600 mb-6">بلدية تكريت - عكار، شمال لبنان</p>
            
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <p class="text-sm text-red-800">
                    ⚠️ <strong>تحذير:</strong> هذا السكريبت سيحذف جميع الجداول المتعلقة بنظام الحساب الشخصي.
                </p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">📋 سجل الحذف</h2>

<?php
header('Content-Type: text/html; charset=utf-8');

// إعدادات قاعدة البيانات
$db_host = "localhost";
$db_name = "tekrit_municipality";
$db_user = "root";
$db_pass = "";

try {
    $db = new PDO(
        "mysql:host=" . $db_host . ";dbname=" . $db_name . ";charset=utf8mb4",
        $db_user,
        $db_pass,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        )
    );
    
    echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">';
    echo '<p class="text-green-600">✅ تم الاتصال بقاعدة البيانات بنجاح</p>';
    echo '</div>';
    
    // تعطيل فحص Foreign Keys مؤقتاً
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // قائمة الجداول للحذف (بالترتيب العكسي)
    $tables = [
        'citizen_sessions',
        'notification_preferences',
        'whatsapp_log',
        'citizen_messages',
        'magic_links',
        'citizens_accounts'
    ];
    
    $views = [
        'v_whatsapp_log_detailed',
        'v_citizen_messages_detailed',
        'v_citizens_summary'
    ];
    
    echo '<div class="space-y-2">';
    
    // حذف Views
    echo '<h3 class="font-bold text-lg mt-4 mb-2">حذف Views:</h3>';
    foreach ($views as $view) {
        try {
            $db->exec("DROP VIEW IF EXISTS `$view`");
            echo '<p class="text-green-600">✅ تم حذف View: ' . htmlspecialchars($view) . '</p>';
        } catch (PDOException $e) {
            echo '<p class="text-red-600">❌ خطأ في حذف ' . htmlspecialchars($view) . ': ' . $e->getMessage() . '</p>';
        }
    }
    
    // حذف الجداول
    echo '<h3 class="font-bold text-lg mt-4 mb-2">حذف الجداول:</h3>';
    foreach ($tables as $table) {
        try {
            $db->exec("DROP TABLE IF EXISTS `$table`");
            echo '<p class="text-green-600">✅ تم حذف الجدول: ' . htmlspecialchars($table) . '</p>';
        } catch (PDOException $e) {
            echo '<p class="text-red-600">❌ خطأ في حذف ' . htmlspecialchars($table) . ': ' . $e->getMessage() . '</p>';
        }
    }
    
    // حذف الإعدادات
    echo '<h3 class="font-bold text-lg mt-4 mb-2">حذف الإعدادات:</h3>';
    $settings = [
        'whatsapp_enabled',
        'whatsapp_business_number',
        'whatsapp_api_method',
        'whatsapp_welcome_template',
        'whatsapp_status_update_template',
        'whatsapp_completion_template',
        'whatsapp_reminder_template',
        'whatsapp_general_message_template',
        'municipality_phone',
        'municipality_whatsapp_name'
    ];
    
    foreach ($settings as $setting) {
        try {
            $stmt = $db->prepare("DELETE FROM website_settings WHERE setting_key = ?");
            $stmt->execute([$setting]);
            if ($stmt->rowCount() > 0) {
                echo '<p class="text-green-600">✅ تم حذف الإعداد: ' . htmlspecialchars($setting) . '</p>';
            } else {
                echo '<p class="text-gray-600">⚪ الإعداد غير موجود: ' . htmlspecialchars($setting) . '</p>';
            }
        } catch (PDOException $e) {
            echo '<p class="text-red-600">❌ خطأ: ' . $e->getMessage() . '</p>';
        }
    }
    
    // إعادة تفعيل فحص Foreign Keys
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo '</div>';
    
    echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 mt-6">';
    echo '<h3 class="font-bold text-lg mb-2">🎉 اكتمل الحذف بنجاح!</h3>';
    echo '<p class="text-green-700">يمكنك الآن إعادة تشغيل سكريبت التثبيت.</p>';
    echo '</div>';
    
} catch (PDOException $e) {
    echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4">';
    echo '<p class="text-red-600">❌ خطأ في الاتصال: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div>';
}
?>

            <div class="mt-6 flex gap-4">
                <a href="setup_citizen_accounts_system.php" class="flex-1 bg-blue-600 text-white text-center py-3 rounded-lg hover:bg-blue-700 transition">
                    🚀 ابدأ التثبيت من جديد
                </a>
                <a href="comprehensive_dashboard.php" class="flex-1 bg-green-600 text-white text-center py-3 rounded-lg hover:bg-green-700 transition">
                    🏠 لوحة التحكم
                </a>
            </div>
        </div>

        <div class="mt-6 text-center text-sm text-gray-600">
            <p>🏛️ بلدية تكريت - عكار، شمال لبنان 🇱🇧</p>
        </div>
    </div>
</body>
</html>

