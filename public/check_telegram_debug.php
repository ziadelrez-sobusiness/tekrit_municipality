<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فحص Telegram Bot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        pre { direction: ltr; text-align: left; }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">🔍 فحص نظام Telegram Bot</h1>
        
        <?php
        require_once '../config/database.php';
        
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // 1. فحص إعدادات Telegram
            echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
            echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">⚙️ إعدادات Telegram Bot</h2>';
            
            $stmt = $db->query("SELECT setting_key, setting_value FROM website_settings WHERE setting_key LIKE 'telegram%'");
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($settings)) {
                echo '<div class="bg-red-50 border-l-4 border-red-500 p-4">';
                echo '<p class="font-bold text-red-900">❌ لا توجد إعدادات Telegram في قاعدة البيانات!</p>';
                echo '<p class="text-red-700 mt-2">يجب إضافة الإعدادات من: <a href="../modules/telegram_settings.php" class="underline">صفحة إعدادات Telegram</a></p>';
                echo '</div>';
            } else {
                echo '<table class="w-full">';
                foreach ($settings as $setting) {
                    $key = $setting['setting_key'];
                    $value = $setting['setting_value'];
                    
                    if ($key == 'telegram_bot_token' && !empty($value)) {
                        $value = substr($value, 0, 10) . '...' . substr($value, -10);
                    }
                    
                    $statusIcon = empty($value) ? '❌' : '✅';
                    echo '<tr class="border-b">';
                    echo '<td class="py-2 font-bold">' . $statusIcon . ' ' . $key . '</td>';
                    echo '<td class="py-2 text-gray-600">' . ($value ?: 'غير محدد') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }
            echo '</div>';
            
            // 2. فحص Webhook
            echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
            echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">🔗 Webhook</h2>';
            
            $stmt = $db->query("SELECT setting_value FROM website_settings WHERE setting_key = 'telegram_webhook_url'");
            $webhookUrl = $stmt->fetchColumn();
            
            $stmt = $db->query("SELECT setting_value FROM website_settings WHERE setting_key = 'telegram_bot_token'");
            $botToken = $stmt->fetchColumn();
            
            if ($webhookUrl) {
                echo '<p class="mb-2"><strong>Webhook URL:</strong> <code class="bg-gray-100 px-2 py-1 rounded">' . htmlspecialchars($webhookUrl) . '</code></p>';
                
                $webhookFile = __DIR__ . '/telegram_webhook.php';
                if (file_exists($webhookFile)) {
                    echo '<p class="text-green-600">✅ ملف Webhook موجود</p>';
                } else {
                    echo '<p class="text-red-600">❌ ملف Webhook غير موجود!</p>';
                }
                
                // التحقق من Webhook في Telegram
                if ($botToken) {
                    $url = "https://api.telegram.org/bot{$botToken}/getWebhookInfo";
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $response = curl_exec($ch);
                    curl_close($ch);
                    
                    $webhookInfo = json_decode($response, true);
                    
                    if ($webhookInfo && $webhookInfo['ok']) {
                        $info = $webhookInfo['result'];
                        echo '<div class="mt-4 bg-blue-50 border-l-4 border-blue-500 p-4">';
                        echo '<p class="font-bold text-blue-900 mb-2">📊 معلومات Webhook من Telegram:</p>';
                        echo '<pre class="text-sm text-blue-800">' . json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
                        echo '</div>';
                        
                        if (empty($info['url'])) {
                            echo '<div class="mt-4 bg-yellow-50 border-l-4 border-yellow-500 p-4">';
                            echo '<p class="font-bold text-yellow-900">⚠️ Webhook غير مسجل في Telegram!</p>';
                            echo '<p class="text-yellow-800 mt-2">يجب تسجيل الـ Webhook من صفحة إعدادات Telegram</p>';
                            echo '</div>';
                        }
                    }
                }
            } else {
                echo '<p class="text-red-600">❌ Webhook URL غير محدد!</p>';
            }
            echo '</div>';
            
            // 3. فحص حساب المواطن
            echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
            echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">👤 حساب المواطن</h2>';
            
            $accessCode = 'TKT-12345';
            $stmt = $db->prepare("SELECT * FROM citizens_accounts WHERE permanent_access_code = ?");
            $stmt->execute([$accessCode]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($account) {
                echo '<div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4">';
                echo '<p class="font-bold text-green-900 mb-2">✅ الحساب موجود</p>';
                echo '<table class="w-full text-sm">';
                echo '<tr><td class="py-1 font-bold">ID:</td><td>' . $account['id'] . '</td></tr>';
                echo '<tr><td class="py-1 font-bold">Phone:</td><td>' . $account['phone'] . '</td></tr>';
                echo '<tr><td class="py-1 font-bold">Access Code:</td><td>' . $account['permanent_access_code'] . '</td></tr>';
                echo '<tr><td class="py-1 font-bold">Telegram Chat ID:</td><td>' . ($account['telegram_chat_id'] ?? '<span class="text-red-600">❌ غير مربوط</span>') . '</td></tr>';
                echo '<tr><td class="py-1 font-bold">Telegram Username:</td><td>' . ($account['telegram_username'] ?? 'غير محدد') . '</td></tr>';
                echo '</table>';
                echo '</div>';
                
                // 4. الرسائل المعلقة
                echo '<h3 class="text-xl font-bold text-gray-800 mb-3">📬 الرسائل المعلقة</h3>';
                
                $stmt = $db->prepare("SELECT * FROM telegram_log WHERE citizen_id = ? AND status = 'pending' ORDER BY created_at DESC");
                $stmt->execute([$account['id']]);
                $pendingMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($pendingMessages)) {
                    echo '<p class="text-gray-600">✅ لا توجد رسائل معلقة</p>';
                } else {
                    echo '<p class="font-bold text-orange-600 mb-3">⏳ عدد الرسائل المعلقة: ' . count($pendingMessages) . '</p>';
                    foreach ($pendingMessages as $msg) {
                        echo '<div class="bg-orange-50 border border-orange-300 rounded p-3 mb-2">';
                        echo '<p class="text-sm"><strong>ID:</strong> ' . $msg['id'] . '</p>';
                        echo '<p class="text-sm"><strong>Type:</strong> ' . $msg['message_type'] . '</p>';
                        echo '<p class="text-sm"><strong>Date:</strong> ' . $msg['created_at'] . '</p>';
                        echo '<p class="text-sm"><strong>Message:</strong></p>';
                        echo '<pre class="text-xs bg-white p-2 rounded mt-1">' . htmlspecialchars($msg['message_text']) . '</pre>';
                        echo '</div>';
                    }
                }
            } else {
                echo '<div class="bg-red-50 border-l-4 border-red-500 p-4">';
                echo '<p class="font-bold text-red-900">❌ الحساب غير موجود!</p>';
                echo '</div>';
            }
            echo '</div>';
            
            // 5. اختبار يدوي
            echo '<div class="bg-white rounded-lg shadow p-6">';
            echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">🧪 اختبار يدوي</h2>';
            echo '<p class="mb-4">لاختبار ربط الحساب يدوياً، استخدم هذا الرابط:</p>';
            echo '<a href="test_telegram_link.php?code=' . $accessCode . '" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition">';
            echo '🔗 اختبار ربط الحساب';
            echo '</a>';
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="bg-red-50 border-l-4 border-red-500 p-4">';
            echo '<p class="font-bold text-red-900">❌ خطأ:</p>';
            echo '<p class="text-red-700">' . $e->getMessage() . '</p>';
            echo '</div>';
        }
        ?>
    </div>
</body>
</html>

