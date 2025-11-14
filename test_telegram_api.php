<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🧪 اختبار Telegram API</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">🧪 اختبار Telegram API المباشر</h1>
        
        <?php
        require_once 'config/database.php';
        
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // جلب إعدادات Telegram
            $stmt = $db->query("SELECT setting_key, setting_value FROM website_settings WHERE setting_key LIKE 'telegram%'");
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            $botToken = $settings['telegram_bot_token'] ?? '';
            
            // جلب آخر مواطن مربوط
            $stmt = $db->query("
                SELECT * FROM citizens_accounts 
                WHERE telegram_chat_id IS NOT NULL 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $citizen = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (empty($botToken)) {
                echo '<div class="bg-red-50 border-l-4 border-red-500 p-4">';
                echo '<p class="font-bold text-red-900">❌ Bot Token غير محدد!</p>';
                echo '</div>';
                exit;
            }
            
            if (!$citizen) {
                echo '<div class="bg-yellow-50 border-l-4 border-yellow-500 p-4">';
                echo '<p class="font-bold text-yellow-900">⚠️ لا يوجد مواطنين مربوطين!</p>';
                echo '</div>';
                exit;
            }
            
            // عرض معلومات المواطن
            echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
            echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">👤 معلومات المواطن</h2>';
            echo '<table class="w-full text-sm">';
            echo '<tr><td class="py-1 font-bold">الاسم:</td><td>' . htmlspecialchars($citizen['name']) . '</td></tr>';
            echo '<tr><td class="py-1 font-bold">الهاتف:</td><td>' . htmlspecialchars($citizen['phone']) . '</td></tr>';
            echo '<tr><td class="py-1 font-bold">Chat ID:</td><td><code class="bg-gray-100 px-2 py-1 rounded">' . htmlspecialchars($citizen['telegram_chat_id']) . '</code></td></tr>';
            echo '<tr><td class="py-1 font-bold">Username:</td><td>@' . htmlspecialchars($citizen['telegram_username'] ?? 'غير محدد') . '</td></tr>';
            echo '</table>';
            echo '</div>';
            
            // اختبار 1: رسالة بسيطة
            if (isset($_POST['test_simple'])) {
                echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
                echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">🧪 اختبار 1: رسالة نصية بسيطة</h2>';
                
                $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
                $data = [
                    'chat_id' => $citizen['telegram_chat_id'],
                    'text' => 'مرحباً! هذه رسالة اختبار بسيطة من بلدية تكريت 👋'
                ];
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);
                
                if ($curlError) {
                    echo '<div class="bg-red-50 border-l-4 border-red-500 p-4">';
                    echo '<p class="font-bold text-red-900">❌ خطأ cURL:</p>';
                    echo '<p class="text-red-800 text-sm">' . htmlspecialchars($curlError) . '</p>';
                    echo '</div>';
                } else {
                    $result = json_decode($response, true);
                    
                    if ($httpCode == 200 && isset($result['ok']) && $result['ok']) {
                        echo '<div class="bg-green-50 border-l-4 border-green-500 p-4">';
                        echo '<p class="font-bold text-green-900 text-xl">✅ نجح الإرسال!</p>';
                        echo '<p class="text-green-800 mt-2">تحقق من Telegram - يجب أن تكون وصلتك الرسالة</p>';
                        echo '</div>';
                    } else {
                        echo '<div class="bg-red-50 border-l-4 border-red-500 p-4">';
                        echo '<p class="font-bold text-red-900">❌ فشل الإرسال</p>';
                        echo '<p class="text-sm text-red-800 mt-2"><strong>HTTP Code:</strong> ' . $httpCode . '</p>';
                        echo '<p class="text-sm text-red-800"><strong>Response:</strong></p>';
                        echo '<pre class="text-xs bg-white p-3 rounded mt-2 overflow-x-auto">' . htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                        echo '</div>';
                    }
                }
                
                echo '</div>';
            }
            
            // اختبار 2: رسالة مع HTML
            if (isset($_POST['test_html'])) {
                echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
                echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">🧪 اختبار 2: رسالة مع HTML</h2>';
                
                $message = "✅ <b>مرحباً بك في بلدية تكريت - عكار!</b>\n\n";
                $message .= "📋 تم تقديم طلبكم بنجاح:\n\n";
                $message .= "🔢 رقم التتبع: <code>REQ-2025-TEST</code>\n";
                $message .= "📝 نوع الطلب: طلب اختبار\n\n";
                $message .= "💡 رمز الدخول: <code>TKT-TEST123</code>";
                
                $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
                $data = [
                    'chat_id' => $citizen['telegram_chat_id'],
                    'text' => $message,
                    'parse_mode' => 'HTML'
                ];
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                $result = json_decode($response, true);
                
                if ($httpCode == 200 && isset($result['ok']) && $result['ok']) {
                    echo '<div class="bg-green-50 border-l-4 border-green-500 p-4">';
                    echo '<p class="font-bold text-green-900 text-xl">✅ نجح الإرسال!</p>';
                    echo '</div>';
                } else {
                    echo '<div class="bg-red-50 border-l-4 border-red-500 p-4">';
                    echo '<p class="font-bold text-red-900">❌ فشل الإرسال</p>';
                    echo '<p class="text-sm text-red-800 mt-2"><strong>HTTP Code:</strong> ' . $httpCode . '</p>';
                    echo '<pre class="text-xs bg-white p-3 rounded mt-2 overflow-x-auto">' . htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                    echo '</div>';
                }
                
                echo '<div class="bg-gray-50 border border-gray-300 rounded p-4 mt-4">';
                echo '<p class="font-bold text-gray-800 mb-2">الرسالة المرسلة:</p>';
                echo '<pre class="text-xs overflow-x-auto">' . htmlspecialchars($message) . '</pre>';
                echo '</div>';
                
                echo '</div>';
            }
            
            // اختبار 3: رسالة مع أزرار
            if (isset($_POST['test_buttons'])) {
                echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
                echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">🧪 اختبار 3: رسالة مع أزرار</h2>';
                
                $message = "✅ مرحباً! اختر أحد الخيارات:";
                
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '📋 طلباتي', 'callback_data' => 'my_requests'],
                            ['text' => '💬 رسائلي', 'callback_data' => 'my_messages']
                        ]
                    ]
                ];
                
                $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
                $data = [
                    'chat_id' => $citizen['telegram_chat_id'],
                    'text' => $message,
                    'reply_markup' => json_encode($keyboard)
                ];
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                $result = json_decode($response, true);
                
                if ($httpCode == 200 && isset($result['ok']) && $result['ok']) {
                    echo '<div class="bg-green-50 border-l-4 border-green-500 p-4">';
                    echo '<p class="font-bold text-green-900 text-xl">✅ نجح الإرسال!</p>';
                    echo '</div>';
                } else {
                    echo '<div class="bg-red-50 border-l-4 border-red-500 p-4">';
                    echo '<p class="font-bold text-red-900">❌ فشل الإرسال</p>';
                    echo '<p class="text-sm text-red-800 mt-2"><strong>HTTP Code:</strong> ' . $httpCode . '</p>';
                    echo '<pre class="text-xs bg-white p-3 rounded mt-2 overflow-x-auto">' . htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                    echo '</div>';
                }
                
                echo '</div>';
            }
            
            // اختبار 4: الرسالة الكاملة من TelegramService
            if (isset($_POST['test_full'])) {
                echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
                echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">🧪 اختبار 4: الرسالة الكاملة (من TelegramService)</h2>';
                
                require_once 'includes/TelegramService.php';
                
                $telegramService = new TelegramService($db);
                
                $result = $telegramService->sendWelcomeMessage(
                    [
                        'name' => $citizen['name'],
                        'phone' => $citizen['phone'],
                        'citizen_id' => $citizen['id'],
                        'telegram_chat_id' => $citizen['telegram_chat_id'],
                        'telegram_username' => $citizen['telegram_username']
                    ],
                    [
                        'request_id' => 999,
                        'type_name' => 'طلب اختبار',
                        'tracking_number' => 'REQ-2025-TEST',
                        'request_title' => 'اختبار النظام'
                    ],
                    $citizen['permanent_access_code']
                );
                
                if ($result['success']) {
                    echo '<div class="bg-green-50 border-l-4 border-green-500 p-4">';
                    echo '<p class="font-bold text-green-900 text-xl">✅ نجح الإرسال!</p>';
                    echo '<p class="text-green-800 mt-2">' . htmlspecialchars($result['message']) . '</p>';
                    echo '</div>';
                } else {
                    echo '<div class="bg-red-50 border-l-4 border-red-500 p-4">';
                    echo '<p class="font-bold text-red-900">❌ فشل الإرسال</p>';
                    echo '<p class="text-red-800 mt-2">' . htmlspecialchars($result['message']) . '</p>';
                    echo '</div>';
                }
                
                echo '</div>';
            }
            
            // نماذج الاختبار
            echo '<div class="bg-white rounded-lg shadow p-6">';
            echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">🎯 اختر نوع الاختبار</h2>';
            
            echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
            
            echo '<form method="POST">';
            echo '<button type="submit" name="test_simple" class="w-full bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition">';
            echo '1️⃣ رسالة بسيطة';
            echo '</button>';
            echo '</form>';
            
            echo '<form method="POST">';
            echo '<button type="submit" name="test_html" class="w-full bg-green-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-green-700 transition">';
            echo '2️⃣ رسالة مع HTML';
            echo '</button>';
            echo '</form>';
            
            echo '<form method="POST">';
            echo '<button type="submit" name="test_buttons" class="w-full bg-purple-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-purple-700 transition">';
            echo '3️⃣ رسالة مع أزرار';
            echo '</button>';
            echo '</form>';
            
            echo '<form method="POST">';
            echo '<button type="submit" name="test_full" class="w-full bg-orange-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-orange-700 transition">';
            echo '4️⃣ الرسالة الكاملة';
            echo '</button>';
            echo '</form>';
            
            echo '</div>';
            
            echo '<div class="mt-6 bg-yellow-50 border border-yellow-300 rounded p-4">';
            echo '<p class="text-sm text-yellow-900"><strong>💡 ملاحظة:</strong> ابدأ بالاختبار 1 (رسالة بسيطة) للتأكد من أن الاتصال يعمل، ثم انتقل للاختبارات الأخرى.</p>';
            echo '</div>';
            
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="bg-red-50 border-l-4 border-red-500 p-4">';
            echo '<p class="font-bold text-red-900">❌ خطأ:</p>';
            echo '<p class="text-red-700">' . $e->getMessage() . '</p>';
            echo '</div>';
        }
        ?>
        
        <div class="mt-6 text-center">
            <a href="debug_telegram_sending.php" class="inline-block bg-gray-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-gray-700 transition">
                ← العودة للتصحيح
            </a>
        </div>
    </div>
</body>
</html>

