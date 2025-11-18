<?php
/**
 * صفحة إدارة إعدادات Telegram Bot
 * بلدية تكريت - عكار، شمال لبنان
 */

session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// معالجة حفظ الإعدادات
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
    try {
        $settings = [
            'telegram_bot_enabled' => $_POST['telegram_bot_enabled'] ?? '0',
            'telegram_bot_token' => trim($_POST['telegram_bot_token'] ?? ''),
            'telegram_bot_username' => trim($_POST['telegram_bot_username'] ?? 'TekritAkkarBot'),
            'telegram_welcome_template' => trim($_POST['telegram_welcome_template'] ?? ''),
            'telegram_status_update_template' => trim($_POST['telegram_status_update_template'] ?? ''),
            'telegram_completion_template' => trim($_POST['telegram_completion_template'] ?? ''),
            'telegram_webhook_url' => trim($_POST['telegram_webhook_url'] ?? '')
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("
                INSERT INTO website_settings (setting_key, setting_value) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute([$key, $value, $value]);
        }
        
        $success_message = "تم حفظ الإعدادات بنجاح!";
        
    } catch (Exception $e) {
        $error_message = "خطأ في حفظ الإعدادات: " . $e->getMessage();
    }
}

// جلب الإعدادات الحالية
$settings = [];
try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM website_settings WHERE setting_key LIKE 'telegram%'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $error_message = "خطأ في جلب الإعدادات: " . $e->getMessage();
}

// اختبار الاتصال بالبوت (فقط عند الطلب)
$botInfo = null;
if (isset($_POST['test_connection']) && !empty($settings['telegram_bot_token'])) {
    require_once '../includes/TelegramService.php';
    $telegramService = new TelegramService($db);
    $botInfo = $telegramService->getBotInfo();

    if ($botInfo) {
        $success_message = "✅ تم الاتصال بالبوت بنجاح!";
    } else {
        $error_message = "❌ فشل الاتصال بالبوت! تحقق من Token";
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعدادات Telegram Bot - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8 max-w-5xl">
        
        <!-- رأس الصفحة -->
        <div class="bg-white rounded-2xl shadow-xl p-8 mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-4xl font-bold text-gray-800 mb-2">⚙️ إعدادات Telegram Bot</h1>
                    <p class="text-gray-600">إدارة البوت الخاص ببلدية تكريت - عكار</p>
                </div>
                <div class="text-6xl">✈️</div>
            </div>
        </div>

        <!-- الرسائل -->
        <?php if ($success_message): ?>
            <div class="bg-green-50 border-2 border-green-400 rounded-xl p-6 mb-8">
                <p class="text-green-800 font-bold text-center">✅ <?= htmlspecialchars($success_message) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="bg-red-50 border-2 border-red-400 rounded-xl p-6 mb-8">
                <p class="text-red-800 font-bold text-center">❌ <?= htmlspecialchars($error_message) ?></p>
            </div>
        <?php endif; ?>

        <!-- حالة البوت -->
        <?php if ($botInfo): ?>
            <div class="bg-green-50 border-2 border-green-400 rounded-xl p-6 mb-8">
                <h2 class="text-2xl font-bold text-green-800 mb-4">✅ البوت متصل ويعمل!</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-white rounded-lg p-4">
                        <p class="text-sm text-gray-600 mb-1">اسم البوت:</p>
                        <p class="text-lg font-bold text-gray-800"><?= htmlspecialchars($botInfo['first_name'] ?? 'N/A') ?></p>
                    </div>
                    <div class="bg-white rounded-lg p-4">
                        <p class="text-sm text-gray-600 mb-1">Username:</p>
                        <p class="text-lg font-bold text-gray-800">@<?= htmlspecialchars($botInfo['username'] ?? 'N/A') ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- زر اختبار الاتصال -->
        <?php if (!empty($settings['telegram_bot_token']) && !$botInfo): ?>
            <div class="bg-blue-50 border-2 border-blue-300 rounded-xl p-6 mb-8">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-blue-900 mb-2">🔍 اختبار الاتصال بالبوت</h3>
                        <p class="text-sm text-blue-700">تحقق من صحة Token والاتصال بـ Telegram</p>
                    </div>
                    <form method="POST" class="inline-block">
                        <button type="submit"
                                name="test_connection"
                                class="bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition">
                            🧪 اختبار الاتصال
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-8">
            
            <!-- الإعدادات الأساسية -->
            <div class="bg-white rounded-2xl shadow-xl p-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">🔧 الإعدادات الأساسية</h2>
                
                <!-- تفعيل البوت -->
                <div class="mb-6">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" 
                               name="telegram_bot_enabled" 
                               value="1"
                               <?= ($settings['telegram_bot_enabled'] ?? '0') == '1' ? 'checked' : '' ?>
                               class="w-6 h-6 text-blue-600 rounded">
                        <span class="text-lg font-bold text-gray-800">تفعيل Telegram Bot</span>
                    </label>
                    <p class="text-sm text-gray-600 mr-9">عند التفعيل، سيتم إرسال الرسائل تلقائياً للمواطنين</p>
                </div>

                <!-- Bot Token -->
                <div class="mb-6">
                    <label class="block text-gray-700 font-bold mb-2">
                        🔑 Bot Token <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           name="telegram_bot_token" 
                           value="<?= htmlspecialchars($settings['telegram_bot_token'] ?? '') ?>"
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none"
                           placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz"
                           required>
                    <p class="text-sm text-gray-600 mt-2">
                        💡 احصل على Token من <a href="https://t.me/BotFather" target="_blank" class="text-blue-600 hover:underline">@BotFather</a>
                    </p>
                </div>

                <!-- Bot Username -->
                <div class="mb-6">
                    <label class="block text-gray-700 font-bold mb-2">
                        👤 Bot Username
                    </label>
                    <div class="flex items-center gap-2">
                        <span class="text-gray-600 font-bold">@</span>
                        <input type="text" 
                               name="telegram_bot_username" 
                               value="<?= htmlspecialchars($settings['telegram_bot_username'] ?? 'TekritAkkarBot') ?>"
                               class="flex-1 px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none"
                               placeholder="TekritAkkarBot">
                    </div>
                </div>

                <!-- Webhook URL -->
                <div class="mb-6">
                    <label class="block text-gray-700 font-bold mb-2">
                        🔗 Webhook URL (اختياري)
                    </label>
                    <input type="url" 
                           name="telegram_webhook_url" 
                           value="<?= htmlspecialchars($settings['telegram_webhook_url'] ?? '') ?>"
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none"
                           placeholder="https://yourdomain.com/telegram_webhook.php">
                    <p class="text-sm text-gray-600 mt-2">
                        💡 لاستقبال رسائل المواطنين والرد عليهم تلقائياً
                    </p>
                </div>
            </div>

            <!-- قوالب الرسائل -->
            <div class="bg-white rounded-2xl shadow-xl p-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">📝 قوالب الرسائل</h2>
                
                <div class="bg-blue-50 border-2 border-blue-300 rounded-lg p-4 mb-6">
                    <p class="text-blue-800 font-bold mb-2">💡 المتغيرات المتاحة:</p>
                    <div class="text-sm text-blue-700 space-y-1">
                        <p><code class="bg-blue-200 px-2 py-1 rounded">{tracking_number}</code> - رقم التتبع</p>
                        <p><code class="bg-blue-200 px-2 py-1 rounded">{request_type}</code> - نوع الطلب</p>
                        <p><code class="bg-blue-200 px-2 py-1 rounded">{citizen_name}</code> - اسم المواطن</p>
                        <p><code class="bg-blue-200 px-2 py-1 rounded">{date}</code> - التاريخ</p>
                        <p><code class="bg-blue-200 px-2 py-1 rounded">{access_code}</code> - رمز الدخول الثابت</p>
                        <p><code class="bg-blue-200 px-2 py-1 rounded">{new_status}</code> - الحالة الجديدة</p>
                        <p><code class="bg-blue-200 px-2 py-1 rounded">{notes}</code> - ملاحظات</p>
                    </div>
                </div>

                <!-- قالب رسالة الترحيب -->
                <div class="mb-6">
                    <label class="block text-gray-700 font-bold mb-2">
                        ✅ قالب رسالة الترحيب (عند تقديم طلب جديد)
                    </label>
                    <textarea name="telegram_welcome_template" 
                              rows="8"
                              class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none font-mono text-sm"
                              placeholder="مرحباً {citizen_name}..."><?= htmlspecialchars($settings['telegram_welcome_template'] ?? '✅ مرحباً بك في بلدية تكريت - عكار!

📋 تم تقديم طلبكم بنجاح:

🔢 رقم التتبع: {tracking_number}
📝 نوع الطلب: {request_type}
📅 التاريخ: {date}

💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:
🔐 {access_code}

سيتم إبلاغكم بأي تحديثات على طلبكم.') ?></textarea>
                </div>

                <!-- قالب تحديث الحالة -->
                <div class="mb-6">
                    <label class="block text-gray-700 font-bold mb-2">
                        📢 قالب رسالة تحديث الحالة
                    </label>
                    <textarea name="telegram_status_update_template" 
                              rows="6"
                              class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none font-mono text-sm"><?= htmlspecialchars($settings['telegram_status_update_template'] ?? '📢 تحديث حالة الطلب

🔢 رقم التتبع: {tracking_number}
📝 نوع الطلب: {request_type}

🔄 الحالة الجديدة: {new_status}

💬 ملاحظات: {notes}') ?></textarea>
                </div>

                <!-- قالب إنجاز الطلب -->
                <div class="mb-6">
                    <label class="block text-gray-700 font-bold mb-2">
                        ✅ قالب رسالة إنجاز الطلب
                    </label>
                    <textarea name="telegram_completion_template" 
                              rows="6"
                              class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none font-mono text-sm"><?= htmlspecialchars($settings['telegram_completion_template'] ?? '✅ تم إنجاز طلبكم!

🔢 رقم التتبع: {tracking_number}
📝 نوع الطلب: {request_type}
📅 تاريخ الإنجاز: {completion_date}

💬 {notes}

شكراً لتعاملكم مع بلدية تكريت - عكار 🏛️') ?></textarea>
                </div>
            </div>

            <!-- أزرار الحفظ -->
            <div class="bg-white rounded-2xl shadow-xl p-8">
                <div class="flex gap-4 justify-center">
                    <button type="submit" 
                            name="save_settings"
                            class="bg-blue-600 text-white px-12 py-4 rounded-lg font-bold hover:bg-blue-700 transition text-lg">
                        💾 حفظ الإعدادات
                    </button>
                    <a href="../comprehensive_dashboard.php" 
                       class="bg-gray-600 text-white px-12 py-4 rounded-lg font-bold hover:bg-gray-700 transition text-lg">
                        ↩️ رجوع
                    </a>
                </div>
            </div>

        </form>

        <!-- دليل الاستخدام -->
        <div class="bg-gradient-to-r from-blue-50 to-purple-50 rounded-2xl border-2 border-blue-300 p-8 mt-8">
            <h2 class="text-2xl font-bold text-blue-900 mb-6">📖 دليل الاستخدام السريع</h2>
            
            <div class="space-y-4">
                <div class="bg-white rounded-lg p-4">
                    <h3 class="font-bold text-blue-800 mb-2">1️⃣ إنشاء البوت</h3>
                    <p class="text-gray-700 text-sm">افتح Telegram وابحث عن <strong>@BotFather</strong> وأرسل <code class="bg-gray-200 px-2 py-1 rounded">/newbot</code></p>
                </div>
                
                <div class="bg-white rounded-lg p-4">
                    <h3 class="font-bold text-blue-800 mb-2">2️⃣ الحصول على Token</h3>
                    <p class="text-gray-700 text-sm">BotFather سيعطيك Token، انسخه والصقه في الحقل أعلاه</p>
                </div>
                
                <div class="bg-white rounded-lg p-4">
                    <h3 class="font-bold text-blue-800 mb-2">3️⃣ تفعيل البوت</h3>
                    <p class="text-gray-700 text-sm">فعّل الخيار "تفعيل Telegram Bot" واحفظ الإعدادات</p>
                </div>
                
                <div class="bg-white rounded-lg p-4">
                    <h3 class="font-bold text-blue-800 mb-2">4️⃣ اختبار البوت</h3>
                    <p class="text-gray-700 text-sm">ابحث عن <strong>@TekritAkkarBot</strong> في Telegram واضغط Start</p>
                </div>
            </div>
        </div>

    </div>
</body>
</html>

