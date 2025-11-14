<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 تصحيح إرسال Telegram</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-5xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">🔍 تصحيح إرسال Telegram</h1>
        
        <?php
        require_once 'config/database.php';
        require_once 'includes/TelegramService.php';
        
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // 1. فحص آخر طلب
            echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
            echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">📋 آخر طلب مقدم</h2>';
            
            $stmt = $db->query("
                SELECT 
                    cr.*,
                    ca.telegram_chat_id,
                    ca.telegram_username,
                    ca.permanent_access_code
                FROM citizen_requests cr
                LEFT JOIN citizens_accounts ca ON cr.citizen_phone = ca.phone
                ORDER BY cr.created_at DESC 
                LIMIT 1
            ");
            $lastRequest = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lastRequest) {
                echo '<div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4">';
                echo '<table class="w-full text-sm">';
                echo '<tr><td class="py-1 font-bold">رقم التتبع:</td><td>' . htmlspecialchars($lastRequest['tracking_number']) . '</td></tr>';
                echo '<tr><td class="py-1 font-bold">الاسم:</td><td>' . htmlspecialchars($lastRequest['citizen_name']) . '</td></tr>';
                echo '<tr><td class="py-1 font-bold">الهاتف:</td><td>' . htmlspecialchars($lastRequest['citizen_phone']) . '</td></tr>';
                echo '<tr><td class="py-1 font-bold">التاريخ:</td><td>' . $lastRequest['created_at'] . '</td></tr>';
                echo '<tr><td class="py-1 font-bold">Telegram Chat ID:</td><td>' . ($lastRequest['telegram_chat_id'] ?: '<span class="text-red-600">❌ غير موجود</span>') . '</td></tr>';
                echo '<tr><td class="py-1 font-bold">رمز الدخول:</td><td><code class="bg-gray-100 px-2 py-1 rounded">' . htmlspecialchars($lastRequest['permanent_access_code']) . '</code></td></tr>';
                echo '</table>';
                echo '</div>';
                
                $requestId = $lastRequest['id'];
                $citizenPhone = $lastRequest['citizen_phone'];
            } else {
                echo '<p class="text-gray-600">لا توجد طلبات</p>';
                $requestId = null;
                $citizenPhone = null;
            }
            
            echo '</div>';
            
            // 2. فحص رسائل Telegram لهذا الطلب
            if ($requestId) {
                echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
                echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">📬 رسائل Telegram لهذا الطلب</h2>';
                
                $stmt = $db->prepare("
                    SELECT * FROM telegram_log 
                    WHERE request_id = ? 
                    ORDER BY created_at DESC
                ");
                $stmt->execute([$requestId]);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($messages)) {
                    foreach ($messages as $msg) {
                        $statusColor = [
                            'pending' => 'bg-yellow-50 border-yellow-500',
                            'sent' => 'bg-green-50 border-green-500',
                            'failed' => 'bg-red-50 border-red-500'
                        ][$msg['status']] ?? 'bg-gray-50 border-gray-500';
                        
                        echo '<div class="' . $statusColor . ' border-l-4 rounded p-4 mb-3">';
                        echo '<div class="flex justify-between mb-2">';
                        echo '<p class="font-bold">' . ucfirst($msg['status']) . '</p>';
                        echo '<p class="text-xs text-gray-600">' . $msg['created_at'] . '</p>';
                        echo '</div>';
                        echo '<p class="text-sm mb-1"><strong>نوع:</strong> ' . htmlspecialchars($msg['message_type']) . '</p>';
                        if (!empty($msg['message_text']) || !empty($msg['message'])) {
                            echo '<p class="text-sm mb-2"><strong>الرسالة:</strong></p>';
                            $messageText = $msg['message_text'] ?? $msg['message'] ?? '';
                            echo '<pre class="text-xs bg-white p-3 rounded overflow-x-auto">' . htmlspecialchars($messageText) . '</pre>';
                        }
                        if ($msg['error_message']) {
                            echo '<p class="text-xs text-red-600 mt-2"><strong>خطأ:</strong> ' . htmlspecialchars($msg['error_message']) . '</p>';
                        }
                        echo '</div>';
                    }
                } else {
                    echo '<div class="bg-red-50 border-l-4 border-red-500 p-4">';
                    echo '<p class="font-bold text-red-900">❌ لا توجد رسائل مسجلة لهذا الطلب!</p>';
                    echo '<p class="text-red-800 text-sm mt-2">هذا يعني أن الكود لم يصل لجزء إرسال Telegram أو حدث خطأ</p>';
                    echo '</div>';
                }
                
                echo '</div>';
            }
            
            // 3. اختبار إرسال يدوي
            if (isset($_POST['test_send']) && $lastRequest && $lastRequest['telegram_chat_id']) {
                echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
                echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">🧪 نتيجة الاختبار</h2>';
                
                $telegramService = new TelegramService($db);
                
                $testResult = $telegramService->sendWelcomeMessage(
                    [
                        'name' => $lastRequest['citizen_name'],
                        'phone' => $lastRequest['citizen_phone'],
                        'citizen_id' => $lastRequest['id'],
                        'telegram_chat_id' => $lastRequest['telegram_chat_id'],
                        'telegram_username' => $lastRequest['telegram_username']
                    ],
                    [
                        'request_id' => $lastRequest['id'],
                        'type_name' => 'طلب اختبار',
                        'tracking_number' => $lastRequest['tracking_number'],
                        'request_title' => $lastRequest['request_title']
                    ],
                    $lastRequest['permanent_access_code']
                );
                
                if ($testResult['success']) {
                    echo '<div class="bg-green-50 border-l-4 border-green-500 p-4">';
                    echo '<p class="font-bold text-green-900 text-xl">✅ تم إرسال الرسالة بنجاح!</p>';
                    echo '<p class="text-green-800 mt-2">تحقق من Telegram - يجب أن تكون وصلتك الرسالة</p>';
                    echo '</div>';
                } else {
                    echo '<div class="bg-red-50 border-l-4 border-red-500 p-4">';
                    echo '<p class="font-bold text-red-900 text-xl">❌ فشل إرسال الرسالة</p>';
                    echo '<p class="text-red-800 mt-2">' . htmlspecialchars($testResult['message'] ?? 'خطأ غير معروف') . '</p>';
                    echo '</div>';
                }
                
                echo '</div>';
            }
            
            // 4. نموذج الاختبار
            if ($lastRequest && $lastRequest['telegram_chat_id']) {
                echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
                echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">🧪 اختبار إرسال يدوي</h2>';
                
                echo '<form method="POST">';
                echo '<p class="text-gray-700 mb-4">اضغط الزر لإرسال رسالة اختبار إلى آخر مواطن قدم طلب:</p>';
                echo '<button type="submit" name="test_send" class="w-full bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition">';
                echo '📤 إرسال رسالة اختبار الآن';
                echo '</button>';
                echo '</form>';
                
                echo '</div>';
            }
            
            // 5. فحص إعدادات Telegram
            echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
            echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">⚙️ إعدادات Telegram</h2>';
            
            $stmt = $db->query("SELECT setting_key, setting_value FROM website_settings WHERE setting_key LIKE 'telegram%'");
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            $botEnabled = ($settings['telegram_bot_enabled'] ?? '0') == '1';
            $botToken = $settings['telegram_bot_token'] ?? '';
            
            echo '<div class="space-y-2">';
            echo '<p class="text-sm"><strong>Bot Enabled:</strong> ' . ($botEnabled ? '<span class="text-green-600">✅ نعم</span>' : '<span class="text-red-600">❌ لا</span>') . '</p>';
            echo '<p class="text-sm"><strong>Bot Token:</strong> ' . (!empty($botToken) ? '<span class="text-green-600">✅ محدد</span>' : '<span class="text-red-600">❌ غير محدد</span>') . '</p>';
            echo '</div>';
            
            if (!$botEnabled) {
                echo '<div class="bg-red-50 border-l-4 border-red-500 p-4 mt-4">';
                echo '<p class="font-bold text-red-900">❌ البوت غير مفعّل!</p>';
                echo '<p class="text-red-800 text-sm mt-2">يجب تفعيل البوت من صفحة الإعدادات</p>';
                echo '</div>';
            }
            
            echo '</div>';
            
            // 6. التشخيص
            echo '<div class="bg-yellow-50 border-2 border-yellow-400 rounded-lg p-6">';
            echo '<h2 class="text-2xl font-bold text-yellow-900 mb-4">🔍 التشخيص</h2>';
            
            echo '<div class="space-y-3">';
            
            if (!$botEnabled) {
                echo '<div class="bg-red-100 rounded p-3">';
                echo '<p class="font-bold text-red-900">❌ المشكلة: البوت غير مفعّل</p>';
                echo '<p class="text-sm text-red-800">الحل: فعّل البوت من صفحة إعدادات Telegram</p>';
                echo '</div>';
            } elseif (empty($botToken)) {
                echo '<div class="bg-red-100 rounded p-3">';
                echo '<p class="font-bold text-red-900">❌ المشكلة: Bot Token غير محدد</p>';
                echo '<p class="text-sm text-red-800">الحل: أدخل Bot Token من صفحة إعدادات Telegram</p>';
                echo '</div>';
            } elseif ($lastRequest && empty($lastRequest['telegram_chat_id'])) {
                echo '<div class="bg-orange-100 rounded p-3">';
                echo '<p class="font-bold text-orange-900">⚠️ المشكلة: الحساب غير مربوط</p>';
                echo '<p class="text-sm text-orange-800">الحل: المواطن يجب أن يرسل رمز الدخول للبوت</p>';
                echo '</div>';
            } elseif ($requestId && empty($messages)) {
                echo '<div class="bg-red-100 rounded p-3">';
                echo '<p class="font-bold text-red-900">❌ المشكلة: لم يتم تسجيل أي رسالة</p>';
                echo '<p class="text-sm text-red-800">الحل: هناك خطأ في كود citizen-requests.php - الكود لا يصل لجزء Telegram</p>';
                echo '</div>';
            } elseif (!empty($messages) && $messages[0]['status'] == 'pending') {
                echo '<div class="bg-yellow-100 rounded p-3">';
                echo '<p class="font-bold text-yellow-900">⏳ المشكلة: الرسالة معلقة</p>';
                echo '<p class="text-sm text-yellow-800">الحل: تحقق من أن telegram_chat_id صحيح</p>';
                echo '</div>';
            } elseif (!empty($messages) && $messages[0]['status'] == 'failed') {
                echo '<div class="bg-red-100 rounded p-3">';
                echo '<p class="font-bold text-red-900">❌ المشكلة: فشل الإرسال</p>';
                echo '<p class="text-sm text-red-800">الخطأ: ' . htmlspecialchars($messages[0]['error_message'] ?? 'غير محدد') . '</p>';
                echo '</div>';
            } else {
                echo '<div class="bg-green-100 rounded p-3">';
                echo '<p class="font-bold text-green-900">✅ كل شيء يبدو صحيحاً</p>';
                echo '<p class="text-sm text-green-800">جرّب الاختبار اليدوي أعلاه</p>';
                echo '</div>';
            }
            
            echo '</div>';
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="bg-red-50 border-l-4 border-red-500 p-4">';
            echo '<p class="font-bold text-red-900">❌ خطأ:</p>';
            echo '<p class="text-red-700">' . $e->getMessage() . '</p>';
            echo '<pre class="text-xs mt-2">' . $e->getTraceAsString() . '</pre>';
            echo '</div>';
        }
        ?>
        
        <div class="mt-6 text-center space-x-3 space-x-reverse">
            <a href="public/citizen-requests.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition">
                📝 تقديم طلب جديد
            </a>
            <a href="modules/telegram_settings.php" class="inline-block bg-purple-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-purple-700 transition">
                ⚙️ إعدادات Telegram
            </a>
        </div>
    </div>
</body>
</html>

