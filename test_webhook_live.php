<?php
/**
 * اختبار Webhook مباشر
 */
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار Webhook</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">🔍 اختبار Webhook مباشر</h1>
        
        <?php
        require_once 'config/database.php';
        
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // 1. فحص إعدادات Telegram
            echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
            echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">⚙️ إعدادات Telegram</h2>';
            
            $stmt = $db->query("SELECT setting_key, setting_value FROM website_settings WHERE setting_key LIKE 'telegram%'");
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            $botToken = $settings['telegram_bot_token'] ?? '';
            $webhookUrl = $settings['telegram_webhook_url'] ?? '';
            $botEnabled = $settings['telegram_bot_enabled'] ?? '0';
            
            echo '<table class="w-full text-sm">';
            echo '<tr class="border-b"><td class="py-2 font-bold">Bot Enabled:</td><td>' . ($botEnabled == '1' ? '✅ نعم' : '❌ لا') . '</td></tr>';
            echo '<tr class="border-b"><td class="py-2 font-bold">Bot Token:</td><td>' . (empty($botToken) ? '❌ غير محدد' : '✅ محدد') . '</td></tr>';
            echo '<tr class="border-b"><td class="py-2 font-bold">Webhook URL:</td><td class="break-all">' . ($webhookUrl ?: '❌ غير محدد') . '</td></tr>';
            echo '</table>';
            echo '</div>';
            
            // 2. فحص Webhook في Telegram
            if (!empty($botToken)) {
                echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
                echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">🔗 حالة Webhook في Telegram</h2>';
                
                $url = "https://api.telegram.org/bot{$botToken}/getWebhookInfo";
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $response = curl_exec($ch);
                curl_close($ch);
                
                $webhookInfo = json_decode($response, true);
                
                if ($webhookInfo && $webhookInfo['ok']) {
                    $info = $webhookInfo['result'];
                    
                    echo '<div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4">';
                    echo '<p class="font-bold text-blue-900 mb-2">معلومات Webhook:</p>';
                    echo '<table class="w-full text-sm">';
                    echo '<tr class="border-b"><td class="py-2 font-bold">URL:</td><td class="break-all">' . ($info['url'] ?: '❌ غير مسجل') . '</td></tr>';
                    echo '<tr class="border-b"><td class="py-2 font-bold">Has Custom Certificate:</td><td>' . ($info['has_custom_certificate'] ? 'نعم' : 'لا') . '</td></tr>';
                    echo '<tr class="border-b"><td class="py-2 font-bold">Pending Update Count:</td><td>' . ($info['pending_update_count'] ?? 0) . '</td></tr>';
                    
                    if (isset($info['last_error_date'])) {
                        echo '<tr class="border-b bg-red-50"><td class="py-2 font-bold">Last Error Date:</td><td>' . date('Y-m-d H:i:s', $info['last_error_date']) . '</td></tr>';
                        echo '<tr class="border-b bg-red-50"><td class="py-2 font-bold">Last Error Message:</td><td>' . ($info['last_error_message'] ?? '') . '</td></tr>';
                    }
                    
                    if (isset($info['last_synchronization_error_date'])) {
                        echo '<tr class="border-b bg-yellow-50"><td class="py-2 font-bold">Last Sync Error:</td><td>' . date('Y-m-d H:i:s', $info['last_synchronization_error_date']) . '</td></tr>';
                    }
                    
                    echo '</table>';
                    echo '</div>';
                    
                    // تحليل المشكلة
                    if (empty($info['url'])) {
                        echo '<div class="bg-red-50 border-l-4 border-red-500 p-4">';
                        echo '<p class="font-bold text-red-900">❌ المشكلة: Webhook غير مسجل في Telegram!</p>';
                        echo '<p class="text-red-800 text-sm mt-2">الحل: تأكد من حفظ الإعدادات في صفحة إعدادات Telegram</p>';
                        echo '</div>';
                    } elseif ($info['url'] !== $webhookUrl) {
                        echo '<div class="bg-yellow-50 border-l-4 border-yellow-500 p-4">';
                        echo '<p class="font-bold text-yellow-900">⚠️ تحذير: Webhook URL مختلف!</p>';
                        echo '<p class="text-yellow-800 text-sm mt-2">المسجل في Telegram: ' . $info['url'] . '</p>';
                        echo '<p class="text-yellow-800 text-sm">المحفوظ في قاعدة البيانات: ' . $webhookUrl . '</p>';
                        echo '</div>';
                    } else {
                        echo '<div class="bg-green-50 border-l-4 border-green-500 p-4">';
                        echo '<p class="font-bold text-green-900">✅ Webhook مسجل بشكل صحيح!</p>';
                        echo '</div>';
                    }
                    
                } else {
                    echo '<div class="bg-red-50 border-l-4 border-red-500 p-4">';
                    echo '<p class="font-bold text-red-900">❌ خطأ في الاتصال بـ Telegram API</p>';
                    echo '</div>';
                }
                
                echo '</div>';
            }
            
            // 3. فحص ملف السجل
            echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
            echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">📝 آخر سجلات Webhook</h2>';
            
            $logFile = __DIR__ . '/logs/telegram_webhook.log';
            if (file_exists($logFile)) {
                $logs = file($logFile);
                $lastLogs = array_slice($logs, -10); // آخر 10 سجلات
                
                if (!empty($lastLogs)) {
                    echo '<div class="bg-gray-50 p-4 rounded overflow-x-auto">';
                    echo '<pre class="text-xs">';
                    foreach (array_reverse($lastLogs) as $log) {
                        echo htmlspecialchars($log);
                    }
                    echo '</pre>';
                    echo '</div>';
                } else {
                    echo '<p class="text-gray-600">لا توجد سجلات</p>';
                }
            } else {
                echo '<p class="text-yellow-600">⚠️ ملف السجل غير موجود - لم يتم استقبال أي طلبات بعد</p>';
            }
            
            echo '</div>';
            
            // 4. اختبار إرسال رسالة
            echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
            echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">🧪 اختبار إرسال رسالة</h2>';
            
            if (isset($_POST['test_send'])) {
                $testChatId = $_POST['chat_id'] ?? '';
                $testMessage = $_POST['message'] ?? 'رسالة اختبار من بلدية تكريت';
                
                if (!empty($testChatId) && !empty($botToken)) {
                    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
                    $data = [
                        'chat_id' => $testChatId,
                        'text' => $testMessage
                    ];
                    
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $response = curl_exec($ch);
                    curl_close($ch);
                    
                    $result = json_decode($response, true);
                    
                    if ($result && $result['ok']) {
                        echo '<div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4">';
                        echo '<p class="font-bold text-green-900">✅ تم إرسال الرسالة بنجاح!</p>';
                        echo '</div>';
                    } else {
                        echo '<div class="bg-red-50 border-l-4 border-red-500 p-4 mb-4">';
                        echo '<p class="font-bold text-red-900">❌ فشل إرسال الرسالة</p>';
                        echo '<pre class="text-xs mt-2">' . htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                        echo '</div>';
                    }
                }
            }
            
            echo '<form method="POST" class="space-y-4">';
            echo '<div>';
            echo '<label class="block font-bold text-gray-700 mb-2">Chat ID (من حساب المواطن):</label>';
            echo '<input type="text" name="chat_id" class="w-full border border-gray-300 rounded px-4 py-2" placeholder="مثال: 123456789">';
            echo '<p class="text-xs text-gray-600 mt-1">يمكنك الحصول على Chat ID من حسابات المواطنين</p>';
            echo '</div>';
            echo '<div>';
            echo '<label class="block font-bold text-gray-700 mb-2">الرسالة:</label>';
            echo '<textarea name="message" class="w-full border border-gray-300 rounded px-4 py-2" rows="3">مرحباً! هذه رسالة اختبار من بلدية تكريت - عكار 🏛️</textarea>';
            echo '</div>';
            echo '<button type="submit" name="test_send" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition">';
            echo '📤 إرسال رسالة اختبار';
            echo '</button>';
            echo '</form>';
            
            echo '</div>';
            
            // 5. التعليمات
            echo '<div class="bg-yellow-50 border-2 border-yellow-400 rounded-lg p-6">';
            echo '<h2 class="text-2xl font-bold text-yellow-900 mb-4">💡 خطوات الاختبار</h2>';
            echo '<ol class="space-y-2 text-yellow-800 mr-4">';
            echo '<li><strong>1.</strong> تأكد من أن Webhook مسجل بشكل صحيح (أعلاه)</li>';
            echo '<li><strong>2.</strong> افتح Telegram وابحث عن @TekritAkkarBot</li>';
            echo '<li><strong>3.</strong> اضغط Start (إذا لم تكن قد فعلت)</li>';
            echo '<li><strong>4.</strong> أرسل أي رسالة (مثلاً: "مرحبا")</li>';
            echo '<li><strong>5.</strong> تحقق من ظهور الرسالة في السجلات أعلاه</li>';
            echo '<li><strong>6.</strong> إذا ظهرت، جرّب إرسال رمز الدخول: TKT-121683E2</li>';
            echo '</ol>';
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="bg-red-50 border-l-4 border-red-500 p-4">';
            echo '<p class="font-bold text-red-900">❌ خطأ:</p>';
            echo '<p class="text-red-700">' . $e->getMessage() . '</p>';
            echo '</div>';
        }
        ?>
        
        <div class="mt-6 text-center">
            <a href="modules/telegram_settings.php" class="inline-block bg-purple-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-purple-700 transition mr-2">
                ⚙️ إعدادات Telegram
            </a>
            <a href="modules/citizens_accounts.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition">
                👥 حسابات المواطنين
            </a>
        </div>
    </div>
</body>
</html>

