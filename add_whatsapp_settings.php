<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة إعدادات WhatsApp</title>
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
                ⚙️ إضافة إعدادات WhatsApp
            </h1>
            <p class="text-center text-gray-600 mb-6">بلدية تكريت - عكار، شمال لبنان</p>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">📋 سجل الإضافة</h2>

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
    
    // إعدادات WhatsApp
    $settings = [
        [
            'key' => 'whatsapp_enabled',
            'value' => '1',
            'description' => 'تفعيل إشعارات WhatsApp'
        ],
        [
            'key' => 'whatsapp_business_number',
            'value' => '',
            'description' => 'رقم WhatsApp للبلدية (مثال: 96176123456)'
        ],
        [
            'key' => 'whatsapp_api_method',
            'value' => 'manual',
            'description' => 'طريقة الإرسال: manual, api, webhook'
        ],
        [
            'key' => 'whatsapp_welcome_template',
            'value' => "مرحباً {name}!\n\n✅ تم استلام طلبك بنجاح\n📋 نوع الطلب: {request_type}\n🔢 رقم التتبع: {tracking_number}\n📅 التاريخ: {date}\n\n🔐 للدخول لحسابك الشخصي:\n👉 {magic_link}\n\nأو استخدم:\n📱 الهاتف: {phone}\n🔑 الرمز: {code}\n\n━━━━━━━━━━━━━━━━━━━\n💚 شكراً لثقتكم\n🏛️ بلدية تكريت - في خدمتكم",
            'description' => 'قالب رسالة الترحيب'
        ],
        [
            'key' => 'whatsapp_status_update_template',
            'value' => "🏛️ بلدية تكريت\n\n📢 تحديث على طلبك\n\n🔢 {tracking_number}\n📋 {request_type}\n\n✅ الحالة الجديدة:\n{status}\n\n📝 التحديث:\n{update_text}\n\n👉 للتفاصيل:\n{magic_link}\n\n━━━━━━━━━━━━━━━━━━━",
            'description' => 'قالب تحديث الحالة'
        ],
        [
            'key' => 'whatsapp_completion_template',
            'value' => "🏛️ بلدية تكريت\n\n✅ طلبك جاهز!\n\n🔢 {tracking_number}\n📋 {request_type}\n\n📍 يرجى المرور على مكتب البلدية لاستلام:\n{request_title}\n\n🕐 أوقات الدوام:\nالإثنين - الجمعة\n8:00 ص - 2:00 م\n\n📞 للاستفسار: {municipality_phone}\n\n━━━━━━━━━━━━━━━━━━━\n💚 شكراً لثقتكم",
            'description' => 'قالب إنجاز الطلب'
        ],
        [
            'key' => 'whatsapp_reminder_template',
            'value' => "🏛️ بلدية تكريت\n\n⏰ تذكير\n\n{reminder_text}\n\n🔢 رقم الطلب: {tracking_number}\n\n👉 للتفاصيل:\n{magic_link}\n\n━━━━━━━━━━━━━━━━━━━",
            'description' => 'قالب التذكير'
        ],
        [
            'key' => 'whatsapp_general_message_template',
            'value' => "🏛️ بلدية تكريت\n\n📢 {title}\n\n{message}\n\n━━━━━━━━━━━━━━━━━━━\n💚 بلدية تكريت - في خدمتكم",
            'description' => 'قالب الرسائل العامة'
        ],
        [
            'key' => 'municipality_phone',
            'value' => '06-123-456',
            'description' => 'رقم هاتف البلدية'
        ],
        [
            'key' => 'municipality_whatsapp_name',
            'value' => 'بلدية تكريت',
            'description' => 'اسم حساب WhatsApp Business'
        ]
    ];
    
    echo '<div class="space-y-2">';
    
    $added = 0;
    $updated = 0;
    $errors = 0;
    
    foreach ($settings as $setting) {
        try {
            // التحقق من وجود الإعداد
            $check = $db->prepare("SELECT setting_key FROM website_settings WHERE setting_key = ?");
            $check->execute([$setting['key']]);
            $exists = $check->fetch();
            $check->closeCursor();
            
            if ($exists) {
                // تحديث
                $stmt = $db->prepare("UPDATE website_settings SET setting_value = ?, setting_description = ? WHERE setting_key = ?");
                $stmt->execute([$setting['value'], $setting['description'], $setting['key']]);
                echo '<p class="text-blue-600">🔄 تم تحديث: ' . htmlspecialchars($setting['key']) . '</p>';
                $updated++;
            } else {
                // إضافة
                $stmt = $db->prepare("INSERT INTO website_settings (setting_key, setting_value, setting_description) VALUES (?, ?, ?)");
                $stmt->execute([$setting['key'], $setting['value'], $setting['description']]);
                echo '<p class="text-green-600">✅ تم إضافة: ' . htmlspecialchars($setting['key']) . '</p>';
                $added++;
            }
            
        } catch (PDOException $e) {
            echo '<p class="text-red-600">❌ خطأ في ' . htmlspecialchars($setting['key']) . ': ' . $e->getMessage() . '</p>';
            $errors++;
        }
    }
    
    echo '</div>';
    
    echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 mt-6">';
    echo '<h3 class="font-bold text-lg mb-2">🎉 اكتمل!</h3>';
    echo '<p class="text-green-700">تم إضافة: ' . $added . ' إعداد</p>';
    echo '<p class="text-blue-700">تم تحديث: ' . $updated . ' إعداد</p>';
    if ($errors > 0) {
        echo '<p class="text-red-700">أخطاء: ' . $errors . '</p>';
    }
    echo '</div>';
    
} catch (PDOException $e) {
    echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4">';
    echo '<p class="text-red-600">❌ خطأ في الاتصال: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div>';
}
?>

            <div class="mt-6 flex gap-4">
                <a href="setup_citizen_accounts_system.php" class="flex-1 bg-blue-600 text-white text-center py-3 rounded-lg hover:bg-blue-700 transition">
                    🔄 إعادة التحقق
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

