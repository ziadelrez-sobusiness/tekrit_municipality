<?php
/**
 * إعدادات WhatsApp
 * صفحة لإدارة جميع إعدادات WhatsApp للنظام
 */

session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

// التحقق من تسجيل الدخول
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

// الاتصال بقاعدة البيانات
try {
    $db = new PDO(
        "mysql:host=localhost;dbname=tekrit_municipality;charset=utf8mb4",
        "root",
        "",
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        )
    );
} catch(PDOException $e) {
    die("خطأ في الاتصال: " . $e->getMessage());
}

$success_message = '';
$error_message = '';

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        $settings = [
            'whatsapp_enabled' => isset($_POST['whatsapp_enabled']) ? '1' : '0',
            'whatsapp_business_number' => $_POST['whatsapp_business_number'] ?? '',
            'whatsapp_api_method' => $_POST['whatsapp_api_method'] ?? 'manual',
            'municipality_phone' => $_POST['municipality_phone'] ?? '',
            'municipality_whatsapp_name' => $_POST['municipality_whatsapp_name'] ?? '',
            'whatsapp_welcome_template' => $_POST['whatsapp_welcome_template'] ?? '',
            'whatsapp_status_update_template' => $_POST['whatsapp_status_update_template'] ?? '',
            'whatsapp_completion_template' => $_POST['whatsapp_completion_template'] ?? '',
            'whatsapp_reminder_template' => $_POST['whatsapp_reminder_template'] ?? '',
            'whatsapp_general_message_template' => $_POST['whatsapp_general_message_template'] ?? ''
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("
                INSERT INTO website_settings (setting_key, setting_value, setting_description) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            
            $description = '';
            switch ($key) {
                case 'whatsapp_enabled': $description = 'تفعيل إشعارات WhatsApp'; break;
                case 'whatsapp_business_number': $description = 'رقم WhatsApp للبلدية'; break;
                case 'whatsapp_api_method': $description = 'طريقة الإرسال'; break;
                case 'municipality_phone': $description = 'رقم هاتف البلدية'; break;
                case 'municipality_whatsapp_name': $description = 'اسم حساب WhatsApp Business'; break;
                case 'whatsapp_welcome_template': $description = 'قالب رسالة الترحيب'; break;
                case 'whatsapp_status_update_template': $description = 'قالب تحديث الحالة'; break;
                case 'whatsapp_completion_template': $description = 'قالب إنجاز الطلب'; break;
                case 'whatsapp_reminder_template': $description = 'قالب التذكير'; break;
                case 'whatsapp_general_message_template': $description = 'قالب الرسائل العامة'; break;
            }
            
            $stmt->execute([$key, $value, $description, $value]);
        }
        
        $success_message = 'تم حفظ الإعدادات بنجاح!';
    } catch (PDOException $e) {
        $error_message = 'خطأ في حفظ الإعدادات: ' . $e->getMessage();
    }
}

// جلب الإعدادات الحالية
$current_settings = [];
try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM website_settings WHERE setting_key LIKE 'whatsapp_%' OR setting_key LIKE 'municipality_%'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    $error_message = 'خطأ في جلب الإعدادات: ' . $e->getMessage();
}

// القيم الافتراضية
$defaults = [
    'whatsapp_enabled' => '1',
    'whatsapp_business_number' => '',
    'whatsapp_api_method' => 'manual',
    'municipality_phone' => '06-123-456',
    'municipality_whatsapp_name' => 'بلدية تكريت',
    'whatsapp_welcome_template' => "مرحباً {name}!\n\n✅ تم استلام طلبك بنجاح\n📋 نوع الطلب: {request_type}\n🔢 رقم التتبع: {tracking_number}\n📅 التاريخ: {date}\n\n🔐 للدخول لحسابك الشخصي:\n👉 {magic_link}\n\nأو استخدم:\n📱 الهاتف: {phone}\n🔑 الرمز: {code}\n\n━━━━━━━━━━━━━━━━━━━\n💚 شكراً لثقتكم\n🏛️ بلدية تكريت - في خدمتكم",
    'whatsapp_status_update_template' => "🏛️ بلدية تكريت\n\n📢 تحديث على طلبك\n\n🔢 {tracking_number}\n📋 {request_type}\n\n✅ الحالة الجديدة:\n{status}\n\n📝 التحديث:\n{update_text}\n\n👉 للتفاصيل:\n{magic_link}\n\n━━━━━━━━━━━━━━━━━━━",
    'whatsapp_completion_template' => "🏛️ بلدية تكريت\n\n✅ طلبك جاهز!\n\n🔢 {tracking_number}\n📋 {request_type}\n\n📍 يرجى المرور على مكتب البلدية لاستلام:\n{request_title}\n\n🕐 أوقات الدوام:\nالإثنين - الجمعة\n8:00 ص - 2:00 م\n\n📞 للاستفسار: {municipality_phone}\n\n━━━━━━━━━━━━━━━━━━━\n💚 شكراً لثقتكم",
    'whatsapp_reminder_template' => "🏛️ بلدية تكريت\n\n⏰ تذكير\n\n{reminder_text}\n\n🔢 رقم الطلب: {tracking_number}\n\n👉 للتفاصيل:\n{magic_link}\n\n━━━━━━━━━━━━━━━━━━━",
    'whatsapp_general_message_template' => "🏛️ بلدية تكريت\n\n📢 {title}\n\n{message}\n\n━━━━━━━━━━━━━━━━━━━\n💚 بلدية تكريت - في خدمتكم"
];

// دمج الإعدادات الحالية مع الافتراضية
$settings = array_merge($defaults, $current_settings);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعدادات WhatsApp - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-100">
    
    <!-- Header -->
    <div class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center">
                <a href="../comprehensive_dashboard.php" class="text-blue-600 hover:text-blue-800 ml-4">
                    ← العودة للوحة التحكم
                </a>
                <h1 class="text-2xl font-bold text-gray-800">إعدادات WhatsApp</h1>
            </div>
            <div class="text-sm text-gray-600">
                🏛️ بلدية تكريت - عكار
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-8">
        
        <!-- Messages -->
        <?php if ($success_message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            ✅ <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            ❌ <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            
            <!-- الإعدادات الأساسية -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <span class="text-2xl ml-2">⚙️</span>
                    الإعدادات الأساسية
                </h2>
                
                <div class="grid md:grid-cols-2 gap-6">
                    <!-- تفعيل WhatsApp -->
                    <div>
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="whatsapp_enabled" value="1" 
                                   <?php echo ($settings['whatsapp_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>
                                   class="w-5 h-5 text-blue-600 ml-2">
                            <span class="font-bold">تفعيل إشعارات WhatsApp</span>
                        </label>
                        <p class="text-sm text-gray-600 mt-1 mr-7">تفعيل/تعطيل جميع إشعارات WhatsApp</p>
                    </div>
                    
                    <!-- طريقة الإرسال -->
                    <div>
                        <label class="block font-bold mb-2">طريقة الإرسال</label>
                        <select name="whatsapp_api_method" class="w-full border border-gray-300 rounded-lg px-4 py-2">
                            <option value="manual" <?php echo ($settings['whatsapp_api_method'] ?? 'manual') == 'manual' ? 'selected' : ''; ?>>
                                يدوي (Manual)
                            </option>
                            <option value="api" <?php echo ($settings['whatsapp_api_method'] ?? '') == 'api' ? 'selected' : ''; ?>>
                                API
                            </option>
                            <option value="webhook" <?php echo ($settings['whatsapp_api_method'] ?? '') == 'webhook' ? 'selected' : ''; ?>>
                                Webhook
                            </option>
                        </select>
                    </div>
                </div>
                
                <div class="grid md:grid-cols-2 gap-6 mt-6">
                    <!-- رقم WhatsApp -->
                    <div>
                        <label class="block font-bold mb-2">
                            📱 رقم WhatsApp للبلدية
                            <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="whatsapp_business_number" 
                               value="<?php echo htmlspecialchars($settings['whatsapp_business_number'] ?? ''); ?>"
                               placeholder="مثال: 96176123456"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2"
                               required>
                        <p class="text-sm text-gray-600 mt-1">رقم WhatsApp Business مع رمز الدولة (بدون +)</p>
                    </div>
                    
                    <!-- اسم الحساب -->
                    <div>
                        <label class="block font-bold mb-2">اسم حساب WhatsApp Business</label>
                        <input type="text" name="municipality_whatsapp_name" 
                               value="<?php echo htmlspecialchars($settings['municipality_whatsapp_name'] ?? 'بلدية تكريت'); ?>"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2">
                    </div>
                    
                    <!-- رقم الهاتف العادي -->
                    <div>
                        <label class="block font-bold mb-2">📞 رقم هاتف البلدية</label>
                        <input type="text" name="municipality_phone" 
                               value="<?php echo htmlspecialchars($settings['municipality_phone'] ?? '06-123-456'); ?>"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2">
                        <p class="text-sm text-gray-600 mt-1">للظهور في الرسائل</p>
                    </div>
                </div>
            </div>

            <!-- قوالب الرسائل -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <span class="text-2xl ml-2">📝</span>
                    قوالب رسائل WhatsApp
                </h2>
                
                <div class="space-y-6">
                    <!-- قالب الترحيب -->
                    <div>
                        <label class="block font-bold mb-2">
                            💚 قالب رسالة الترحيب
                            <span class="text-sm font-normal text-gray-600">(عند تقديم طلب جديد)</span>
                        </label>
                        <textarea name="whatsapp_welcome_template" rows="8" 
                                  class="w-full border border-gray-300 rounded-lg px-4 py-2 font-mono text-sm"><?php echo htmlspecialchars($settings['whatsapp_welcome_template'] ?? ''); ?></textarea>
                        <p class="text-xs text-gray-600 mt-1">
                            المتغيرات المتاحة: {name}, {request_type}, {tracking_number}, {date}, {magic_link}, {phone}, {code}
                        </p>
                    </div>
                    
                    <!-- قالب التحديث -->
                    <div>
                        <label class="block font-bold mb-2">
                            📢 قالب تحديث الحالة
                            <span class="text-sm font-normal text-gray-600">(عند تغيير حالة الطلب)</span>
                        </label>
                        <textarea name="whatsapp_status_update_template" rows="6" 
                                  class="w-full border border-gray-300 rounded-lg px-4 py-2 font-mono text-sm"><?php echo htmlspecialchars($settings['whatsapp_status_update_template'] ?? ''); ?></textarea>
                        <p class="text-xs text-gray-600 mt-1">
                            المتغيرات: {tracking_number}, {request_type}, {status}, {update_text}, {magic_link}
                        </p>
                    </div>
                    
                    <!-- قالب الإنجاز -->
                    <div>
                        <label class="block font-bold mb-2">
                            ✅ قالب إنجاز الطلب
                            <span class="text-sm font-normal text-gray-600">(عند إنجاز الطلب)</span>
                        </label>
                        <textarea name="whatsapp_completion_template" rows="6" 
                                  class="w-full border border-gray-300 rounded-lg px-4 py-2 font-mono text-sm"><?php echo htmlspecialchars($settings['whatsapp_completion_template'] ?? ''); ?></textarea>
                        <p class="text-xs text-gray-600 mt-1">
                            المتغيرات: {tracking_number}, {request_type}, {request_title}, {municipality_phone}
                        </p>
                    </div>
                    
                    <!-- قالب التذكير -->
                    <div>
                        <label class="block font-bold mb-2">
                            ⏰ قالب التذكير
                            <span class="text-sm font-normal text-gray-600">(للتذكيرات)</span>
                        </label>
                        <textarea name="whatsapp_reminder_template" rows="4" 
                                  class="w-full border border-gray-300 rounded-lg px-4 py-2 font-mono text-sm"><?php echo htmlspecialchars($settings['whatsapp_reminder_template'] ?? ''); ?></textarea>
                        <p class="text-xs text-gray-600 mt-1">
                            المتغيرات: {reminder_text}, {tracking_number}, {magic_link}
                        </p>
                    </div>
                    
                    <!-- قالب الرسائل العامة -->
                    <div>
                        <label class="block font-bold mb-2">
                            📢 قالب الرسائل العامة
                            <span class="text-sm font-normal text-gray-600">(للإعلانات والأخبار)</span>
                        </label>
                        <textarea name="whatsapp_general_message_template" rows="4" 
                                  class="w-full border border-gray-300 rounded-lg px-4 py-2 font-mono text-sm"><?php echo htmlspecialchars($settings['whatsapp_general_message_template'] ?? ''); ?></textarea>
                        <p class="text-xs text-gray-600 mt-1">
                            المتغيرات: {title}, {message}
                        </p>
                    </div>
                </div>
            </div>

            <!-- أزرار الحفظ -->
            <div class="flex gap-4">
                <button type="submit" name="save_settings" 
                        class="flex-1 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition font-bold">
                    💾 حفظ الإعدادات
                </button>
                <a href="../comprehensive_dashboard.php" 
                   class="flex-1 bg-gray-300 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-400 transition text-center font-bold">
                    ❌ إلغاء
                </a>
            </div>
        </form>

        <!-- معلومات إضافية -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mt-6">
            <h3 class="font-bold text-lg mb-3">💡 معلومات مفيدة</h3>
            <ul class="space-y-2 text-sm text-gray-700">
                <li>• رقم WhatsApp يجب أن يكون رقم WhatsApp Business مفعّل</li>
                <li>• المتغيرات مثل {name} سيتم استبدالها تلقائياً بالبيانات الفعلية</li>
                <li>• يمكنك استخدام \n للانتقال لسطر جديد في الرسائل</li>
                <li>• الرموز التعبيرية (Emoji) مدعومة بالكامل</li>
            </ul>
        </div>

    </div>

</body>
</html>

