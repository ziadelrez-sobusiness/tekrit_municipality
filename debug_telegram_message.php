<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 فحص محتوى الرسالة</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">🔍 فحص محتوى الرسالة</h1>
        
        <?php
        require_once 'config/database.php';
        require_once 'includes/TelegramService.php';
        
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // جلب آخر مواطن مربوط
            $stmt = $db->query("
                SELECT * FROM citizens_accounts 
                WHERE telegram_chat_id IS NOT NULL 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $citizen = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$citizen) {
                echo '<div class="bg-yellow-50 border-l-4 border-yellow-500 p-4">';
                echo '<p class="font-bold text-yellow-900">⚠️ لا يوجد مواطنين مربوطين!</p>';
                echo '</div>';
                exit;
            }
            
            // إنشاء TelegramService
            $telegramService = new TelegramService($db);
            
            // بيانات الطلب الاختباري
            $citizenData = [
                'name' => $citizen['name'],
                'phone' => $citizen['phone'],
                'citizen_id' => $citizen['id'],
                'telegram_chat_id' => $citizen['telegram_chat_id'],
                'telegram_username' => $citizen['telegram_username']
            ];
            
            $requestData = [
                'request_id' => 999,
                'type_name' => 'طلب اختبار',
                'tracking_number' => 'REQ-2025-TEST',
                'request_title' => 'اختبار النظام'
            ];
            
            $accessCode = $citizen['permanent_access_code'];
            
            // جلب قالب الرسالة
            $stmt = $db->prepare("
                SELECT setting_value 
                FROM website_settings 
                WHERE setting_key = 'telegram_welcome_template'
            ");
            $stmt->execute();
            $template = $stmt->fetchColumn();
            
            if (!$template) {
                $template = "✅ تم تقديم طلبكم بنجاح!\n\n🔢 رقم التتبع: {tracking_number}\n📝 نوع الطلب: {request_type}\n📅 التاريخ: {date}\n\n🔐 رمز الدخول الثابت: {access_code}";
            }
            
            // استبدال المتغيرات
            $message = str_replace(
                ['{tracking_number}', '{request_type}', '{date}', '{access_code}', '{citizen_name}'],
                [
                    $requestData['tracking_number'] ?? '',
                    $requestData['type_name'] ?? '',
                    date('Y-m-d'),
                    $accessCode,
                    $citizenData['name'] ?? ''
                ],
                $template
            );
            
            // الروابط
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseDir = dirname(dirname($_SERVER['SCRIPT_NAME']));
            $baseUrl = $protocol . '://' . $host . $baseDir;
            
            $trackingUrl = $baseUrl . '/public/track-request.php?tracking=' . urlencode($requestData['tracking_number']);
            $dashboardUrl = $baseUrl . '/public/citizen-dashboard.php?code=' . urlencode($accessCode);
            
            // الأزرار
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🔍 تتبع الطلب', 'url' => $trackingUrl],
                        ['text' => '👤 حسابي', 'url' => $dashboardUrl]
                    ]
                ]
            ];
            
            // عرض المعلومات
            echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
            echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">📋 محتوى الرسالة</h2>';
            
            echo '<div class="bg-gray-50 border border-gray-300 rounded p-4 mb-4">';
            echo '<p class="font-bold text-gray-800 mb-2">النص:</p>';
            echo '<pre class="text-sm whitespace-pre-wrap">' . htmlspecialchars($message) . '</pre>';
            echo '</div>';
            
            echo '<div class="bg-gray-50 border border-gray-300 rounded p-4 mb-4">';
            echo '<p class="font-bold text-gray-800 mb-2">طول النص:</p>';
            echo '<p class="text-sm">' . strlen($message) . ' حرف</p>';
            echo '</div>';
            
            echo '</div>';
            
            echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
            echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">🔗 الروابط</h2>';
            
            echo '<div class="space-y-3">';
            
            echo '<div class="bg-blue-50 border border-blue-300 rounded p-3">';
            echo '<p class="font-bold text-blue-900 mb-1">رابط التتبع:</p>';
            echo '<p class="text-xs break-all">' . htmlspecialchars($trackingUrl) . '</p>';
            echo '<p class="text-xs text-blue-700 mt-1">الطول: ' . strlen($trackingUrl) . ' حرف</p>';
            echo '</div>';
            
            echo '<div class="bg-green-50 border border-green-300 rounded p-3">';
            echo '<p class="font-bold text-green-900 mb-1">رابط الحساب:</p>';
            echo '<p class="text-xs break-all">' . htmlspecialchars($dashboardUrl) . '</p>';
            echo '<p class="text-xs text-green-700 mt-1">الطول: ' . strlen($dashboardUrl) . ' حرف</p>';
            echo '</div>';
            
            echo '</div>';
            echo '</div>';
            
            echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
            echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">🎹 الأزرار (Keyboard)</h2>';
            
            echo '<div class="bg-gray-50 border border-gray-300 rounded p-4">';
            echo '<pre class="text-xs overflow-x-auto">' . htmlspecialchars(json_encode($keyboard, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
            echo '</div>';
            
            echo '</div>';
            
            echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
            echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">📦 البيانات المرسلة لـ Telegram</h2>';
            
            $data = [
                'chat_id' => $citizen['telegram_chat_id'],
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($keyboard)
            ];
            
            echo '<div class="bg-gray-50 border border-gray-300 rounded p-4">';
            echo '<pre class="text-xs overflow-x-auto">' . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
            echo '</div>';
            
            echo '</div>';
            
            // اختبار الإرسال
            if (isset($_POST['send_test'])) {
                echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
                echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">🧪 نتيجة الإرسال</h2>';
                
                // جلب Bot Token
                $stmt = $db->query("SELECT setting_value FROM website_settings WHERE setting_key = 'telegram_bot_token'");
                $botToken = $stmt->fetchColumn();
                
                $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
                
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
                        echo '<p class="font-bold text-red-900 text-xl">❌ فشل الإرسال</p>';
                        echo '<p class="text-sm text-red-800 mt-2"><strong>HTTP Code:</strong> ' . $httpCode . '</p>';
                        echo '<p class="text-sm text-red-800 mt-2"><strong>الرد من Telegram:</strong></p>';
                        echo '<pre class="text-xs bg-white p-3 rounded mt-2 overflow-x-auto">' . htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                        echo '</div>';
                    }
                }
                
                echo '</div>';
            }
            
            // زر الإرسال
            echo '<form method="POST" class="text-center">';
            echo '<button type="submit" name="send_test" class="bg-blue-600 text-white px-8 py-4 rounded-lg font-bold text-lg hover:bg-blue-700 transition">';
            echo '🚀 إرسال الرسالة الآن';
            echo '</button>';
            echo '</form>';
            
        } catch (Exception $e) {
            echo '<div class="bg-red-50 border-l-4 border-red-500 p-4">';
            echo '<p class="font-bold text-red-900">❌ خطأ:</p>';
            echo '<p class="text-red-700">' . $e->getMessage() . '</p>';
            echo '<pre class="text-xs mt-2">' . $e->getTraceAsString() . '</pre>';
            echo '</div>';
        }
        ?>
        
        <div class="mt-6 text-center">
            <a href="test_telegram_api.php" class="inline-block bg-gray-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-gray-700 transition">
                ← العودة
            </a>
        </div>
    </div>
</body>
</html>

