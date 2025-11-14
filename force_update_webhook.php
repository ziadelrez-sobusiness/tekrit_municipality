<?php
/**
 * تحديث Webhook يدوياً
 */
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// الحصول على Bot Token
$stmt = $db->query("SELECT setting_value FROM website_settings WHERE setting_key = 'telegram_bot_token'");
$botToken = $stmt->fetchColumn();

// الرابط الجديد
$newWebhookUrl = 'https://squarishly-unforestalled-shawn.ngrok-free.dev/tekrit_municipality/public/telegram_webhook.php';

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحديث Webhook</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-3xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">🔧 تحديث Webhook يدوياً</h1>
        
        <?php
        if (isset($_POST['update_webhook'])) {
            if (!empty($botToken)) {
                // حذف Webhook القديم
                $deleteUrl = "https://api.telegram.org/bot{$botToken}/deleteWebhook";
                $ch = curl_init($deleteUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $deleteResponse = curl_exec($ch);
                curl_close($ch);
                
                echo '<div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4">';
                echo '<p class="font-bold text-blue-900">1️⃣ حذف Webhook القديم...</p>';
                echo '<pre class="text-xs mt-2">' . htmlspecialchars($deleteResponse) . '</pre>';
                echo '</div>';
                
                // تسجيل Webhook الجديد
                $setUrl = "https://api.telegram.org/bot{$botToken}/setWebhook";
                $data = [
                    'url' => $newWebhookUrl,
                    'drop_pending_updates' => true
                ];
                
                $ch = curl_init($setUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $setResponse = curl_exec($ch);
                curl_close($ch);
                
                $result = json_decode($setResponse, true);
                
                echo '<div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4">';
                echo '<p class="font-bold text-green-900">2️⃣ تسجيل Webhook الجديد...</p>';
                echo '<pre class="text-xs mt-2">' . htmlspecialchars($setResponse) . '</pre>';
                echo '</div>';
                
                if ($result && $result['ok']) {
                    // تحديث قاعدة البيانات
                    $updateStmt = $db->prepare("
                        UPDATE website_settings 
                        SET setting_value = ? 
                        WHERE setting_key = 'telegram_webhook_url'
                    ");
                    $updateStmt->execute([$newWebhookUrl]);
                    
                    echo '<div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4">';
                    echo '<p class="font-bold text-green-900 text-xl">✅ تم تحديث Webhook بنجاح!</p>';
                    echo '<p class="text-green-800 mt-2">الرابط الجديد: ' . htmlspecialchars($newWebhookUrl) . '</p>';
                    echo '</div>';
                    
                    echo '<div class="bg-blue-50 border-l-4 border-blue-500 p-4">';
                    echo '<p class="font-bold text-blue-900 mb-2">🧪 اختبر الآن:</p>';
                    echo '<ol class="text-sm text-blue-800 space-y-1 mr-4">';
                    echo '<li>1. افتح Telegram</li>';
                    echo '<li>2. ابحث عن @TekritAkkarBot</li>';
                    echo '<li>3. اضغط Start</li>';
                    echo '<li>4. أرسل: TKT-121683E2</li>';
                    echo '<li>5. يجب أن يرد البوت فوراً!</li>';
                    echo '</ol>';
                    echo '</div>';
                } else {
                    echo '<div class="bg-red-50 border-l-4 border-red-500 p-4">';
                    echo '<p class="font-bold text-red-900">❌ فشل تحديث Webhook</p>';
                    echo '<p class="text-red-800 text-sm mt-2">' . ($result['description'] ?? 'خطأ غير معروف') . '</p>';
                    echo '</div>';
                }
            } else {
                echo '<div class="bg-red-50 border-l-4 border-red-500 p-4">';
                echo '<p class="font-bold text-red-900">❌ Bot Token غير موجود</p>';
                echo '</div>';
            }
        } else {
            ?>
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">⚠️ المشكلة</h2>
                
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-4">
                    <p class="font-bold text-red-900 mb-2">Webhook القديم (خطأ):</p>
                    <p class="text-sm text-red-800 break-all">https://n8n.sobusiness.cfd/webhook/55acc711-c248-4ac9-b6cd-e295c2d33f4b/webhook</p>
                </div>
                
                <div class="bg-green-50 border-l-4 border-green-500 p-4">
                    <p class="font-bold text-green-900 mb-2">Webhook الجديد (صحيح):</p>
                    <p class="text-sm text-green-800 break-all"><?= htmlspecialchars($newWebhookUrl) ?></p>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">🔧 الحل</h2>
                
                <form method="POST">
                    <p class="text-gray-700 mb-4">
                        اضغط الزر أدناه لتحديث Webhook تلقائياً:
                    </p>
                    
                    <button type="submit" name="update_webhook" 
                            class="w-full bg-blue-600 text-white px-6 py-4 rounded-lg font-bold hover:bg-blue-700 transition text-xl">
                        🔄 تحديث Webhook الآن
                    </button>
                </form>
            </div>
            <?php
        }
        ?>
        
        <div class="mt-6 text-center">
            <a href="test_webhook_live.php" class="inline-block bg-purple-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-purple-700 transition">
                🔍 العودة لصفحة الفحص
            </a>
        </div>
    </div>
</body>
</html>

