<?php
/**
 * Telegram Webhook
 * استقبال ومعالجة رسائل Telegram
 * بلدية تكريت - عكار، شمال لبنان
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/CitizenAccountHelper.php';

// قراءة البيانات الواردة من Telegram
$content = file_get_contents("php://input");
$update = json_decode($content, true);

// تسجيل الطلب للتصحيح (إنشاء المجلد إذا لم يكن موجوداً)
$logDir = __DIR__ . '/../logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/telegram_webhook.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $content . "\n", FILE_APPEND);

// دالة مساعدة لتسجيل في ملف log
function webhookLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    error_log($message);
}

// تسجيل في ملف log
webhookLog("=== TELEGRAM WEBHOOK CALLED ===");
webhookLog("Content length: " . strlen($content));

if (!$update) {
    webhookLog("❌ No update data, exiting");
    http_response_code(200);
    exit;
}

webhookLog("✅ Update received, processing...");

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // استخراج المعلومات
    $message = $update['message'] ?? null;
    
    if (!$message) {
        webhookLog("❌ No message in update, exiting");
        http_response_code(200);
        exit;
    }
    
    $chatId = $message['chat']['id'] ?? null;
    $text = $message['text'] ?? '';
    $from = $message['from'] ?? [];
    $username = $from['username'] ?? null;
    $firstName = $from['first_name'] ?? '';
    $lastName = $from['last_name'] ?? '';
    $fullName = trim($firstName . ' ' . $lastName);
    
    webhookLog("Message text: " . $text);
    webhookLog("Chat ID: " . $chatId);
    
    // الحصول على Bot Token
    $stmt = $db->query("SELECT setting_value FROM website_settings WHERE setting_key = 'telegram_bot_token'");
    $botToken = $stmt->fetchColumn();
    
    webhookLog("Bot Token exists: " . (!empty($botToken) ? 'YES' : 'NO'));
    
    if (!$botToken || !$chatId) {
        webhookLog("❌ Missing bot token or chat ID, exiting");
        http_response_code(200);
        exit;
    }
    
    // معالجة الأوامر
    webhookLog("Processing command/text: " . $text);
    
    if ($text == '/start') {
        webhookLog("Processing /start command");
        // رسالة الترحيب
        $welcomeMessage = "✅ مرحباً بك في بوت بلدية تكريت - عكار!\n\n";
        $welcomeMessage .= "🏛️ هذا البوت الرسمي لبلدية تكريت في عكار، شمال لبنان.\n\n";
        $welcomeMessage .= "📱 سيتم إرسال إشعارات فورية لك عند:\n";
        $welcomeMessage .= "• تقديم طلب جديد\n";
        $welcomeMessage .= "• تحديث حالة طلبك\n";
        $welcomeMessage .= "• إنجاز طلبك\n";
        $welcomeMessage .= "• رسائل من البلدية\n\n";
        $welcomeMessage .= "💡 لربط حسابك، أرسل رقم هاتفك (مثال: 03670065)\n";
        $welcomeMessage .= "أو أرسل رمز الدخول الخاص بك (مثال: TKT-12345)";
        
        sendTelegramMessage($botToken, $chatId, $welcomeMessage);
        
    } elseif (preg_match('/^TKT\-[0-9]+$/i', $text)) {
        webhookLog("✅ Access code pattern matched: " . $text);
        // المستخدم أرسل رمز دخول (يقبل أي عدد من الأرقام بعد TKT-)
        $accessCode = strtoupper(trim($text));
        
        webhookLog("=== TELEGRAM WEBHOOK: Access Code Received ===");
        webhookLog("Original Text: " . $text);
        webhookLog("Access Code (normalized): " . $accessCode);
        webhookLog("Chat ID: " . $chatId);
        webhookLog("Username: " . ($username ?? 'N/A'));
        
        $accountHelper = new CitizenAccountHelper($db);
        $accountResult = $accountHelper->getAccountByAccessCode($accessCode);
        
        webhookLog("Account Result: " . json_encode($accountResult, JSON_UNESCAPED_UNICODE));
        
        // إذا لم يجد الحساب، حاول البحث بدون TKT- prefix
        if (!$accountResult['success']) {
            webhookLog("⚠️ First search failed, trying without prefix...");
            $codeWithoutPrefix = str_replace('TKT-', '', $accessCode);
            if (!empty($codeWithoutPrefix)) {
                webhookLog("Trying code without prefix: " . $codeWithoutPrefix);
                $accountResult = $accountHelper->getAccountByAccessCode('TKT-' . $codeWithoutPrefix);
                if ($accountResult['success']) {
                    $accessCode = 'TKT-' . $codeWithoutPrefix;
                    webhookLog("✅ Found with prefix added: " . $accessCode);
                }
            }
        }
        
        if ($accountResult['success']) {
            webhookLog("✅ Account found, linking Telegram...");
            $account = $accountResult['account'];
            
            // ربط Telegram Chat ID بالحساب
            $stmt = $db->prepare("
                UPDATE citizens_accounts 
                SET telegram_chat_id = ?, 
                    telegram_username = ?
                WHERE id = ?
            ");
            $stmt->execute([$chatId, $username, $account['id']]);
            
            webhookLog("✅ Telegram account linked - Chat ID: " . $chatId . " to Citizen ID: " . $account['id']);
            
            // إرسال رسالة التأكيد مع معلومات الحساب
            $responseMessage = "✅ تم ربط حسابك بنجاح!\n\n";
            $responseMessage .= "👤 الاسم: " . ($account['name'] ?? 'غير محدد') . "\n";
            $responseMessage .= "📱 الهاتف: " . ($account['phone'] ?? 'غير محدد') . "\n";
            $responseMessage .= "🔐 رمز الدخول: " . $accessCode . "\n\n";
            
            // جلب طلبات المواطن
            $stmt = $db->prepare("
                SELECT COUNT(*) as total_requests,
                       SUM(CASE WHEN status IN ('جديد', 'قيد المراجعة', 'قيد التنفيذ') THEN 1 ELSE 0 END) as active_requests
                FROM citizen_requests 
                WHERE citizen_phone = ?
            ");
            $stmt->execute([$account['phone'] ?? '']);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($stats && $stats['total_requests'] > 0) {
                $responseMessage .= "📊 إحصائيات طلباتك:\n";
                $responseMessage .= "• إجمالي الطلبات: " . $stats['total_requests'] . "\n";
                $responseMessage .= "• الطلبات النشطة: " . ($stats['active_requests'] ?? 0) . "\n\n";
            }
            
            $responseMessage .= "🔔 ستستلم الآن جميع الإشعارات المتعلقة بطلباتك.";
            
            // إضافة أزرار تفاعلية
            $baseUrl = getBaseUrl();
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '👤 حسابي الشخصي', 'url' => $baseUrl . '/public/citizen-dashboard.php?code=' . urlencode($accessCode)]
                    ],
                    [
                        ['text' => '📝 تقديم طلب', 'url' => $baseUrl . '/public/citizen-requests.php'],
                        ['text' => '🔍 تتبع طلباتي', 'url' => $baseUrl . '/public/track-request.php']
                    ]
                ]
            ];
            
            webhookLog("📤 About to send response message to Chat ID: " . $chatId);
            webhookLog("Message preview: " . substr($responseMessage, 0, 100) . "...");
            webhookLog("Has keyboard: " . ($keyboard ? 'YES' : 'NO'));
            webhookLog("Bot Token exists: " . (!empty($botToken) ? 'YES' : 'NO'));
            
            $sendResult = sendTelegramMessage($botToken, $chatId, $responseMessage, $keyboard);
            
            if ($sendResult) {
                $resultData = json_decode($sendResult, true);
                if (isset($resultData['ok']) && $resultData['ok']) {
                    webhookLog("✅ Message sent successfully to Telegram");
                } else {
                    webhookLog("❌ Telegram API error: " . json_encode($resultData, JSON_UNESCAPED_UNICODE));
                }
            } else {
                webhookLog("❌ Failed to send message - sendTelegramMessage returned false/null");
            }
            
            // إرسال جميع الرسائل المعلقة (pending)
            sendPendingMessages($db, $botToken, $account['id'], $chatId);
            
        } else {
            $errorMsg = $accountResult['error'] ?? 'رمز الدخول غير صحيح';
            webhookLog("❌ Failed to find account: " . $errorMsg);
            sendTelegramMessage($botToken, $chatId, "❌ رمز الدخول غير صحيح.\n\n💡 يرجى التحقق من:\n• أن الرمز مكتوب بشكل صحيح (مثال: TKT-72089)\n• أنك استخدمت نفس الرمز الذي حصلت عليه عند تقديم الطلب\n\n📝 إذا نسيت الرمز، يمكنك تقديم طلب جديد للحصول على رمز جديد.");
        }
        
    } elseif (preg_match('/^0[0-9]{7,8}$/', $text)) {
        // المستخدم أرسل رقم هاتف
        $phone = $text;
        
        // البحث عن الحساب
        $stmt = $db->prepare("SELECT * FROM citizens_accounts WHERE phone = ?");
        $stmt->execute([$phone]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($account) {
            // ربط Telegram Chat ID
            $stmt = $db->prepare("
                UPDATE citizens_accounts 
                SET telegram_chat_id = ?, 
                    telegram_username = ?
                WHERE id = ?
            ");
            $stmt->execute([$chatId, $username, $account['id']]);
            
            $responseMessage = "✅ تم ربط حسابك بنجاح!\n\n";
            $responseMessage .= "👤 الاسم: " . $account['name'] . "\n";
            $responseMessage .= "🔐 رمز الدخول: " . $account['permanent_access_code'] . "\n\n";
            $responseMessage .= "💡 احتفظ برمز الدخول للدخول لحسابك في أي وقت.";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '👤 حسابي الشخصي', 'url' => getBaseUrl() . '/public/citizen-dashboard.php?code=' . $account['permanent_access_code']]
                    ]
                ]
            ];
            
            sendTelegramMessage($botToken, $chatId, $responseMessage, $keyboard);
            
            // إرسال جميع الرسائل المعلقة (pending)
            sendPendingMessages($db, $botToken, $account['id'], $chatId);
            
        } else {
            $responseMessage = "❌ لم يتم العثور على حساب بهذا الرقم.\n\n";
            $responseMessage .= "💡 يمكنك تقديم طلب جديد وسيتم إنشاء حساب لك تلقائياً.";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '📝 تقديم طلب جديد', 'url' => getBaseUrl() . '/public/citizen-requests.php']
                    ]
                ]
            ];
            
            sendTelegramMessage($botToken, $chatId, $responseMessage, $keyboard);
        }
        
    } elseif ($text == '/help' || $text == 'مساعدة') {
        $helpMessage = "📖 دليل استخدام البوت:\n\n";
        $helpMessage .= "🔹 /start - بدء المحادثة\n";
        $helpMessage .= "🔹 أرسل رمز الدخول (TKT-12345) لربط حسابك\n";
        $helpMessage .= "🔹 أرسل رقم هاتفك لربط حسابك\n";
        $helpMessage .= "🔹 /help - عرض هذه المساعدة\n\n";
        $helpMessage .= "📞 للاستفسارات: تواصل مع البلدية";
        
        sendTelegramMessage($botToken, $chatId, $helpMessage);
        
    } else {
        // رسالة افتراضية
        $defaultMessage = "مرحباً! 👋\n\n";
        $defaultMessage .= "لربط حسابك، يرجى إرسال:\n";
        $defaultMessage .= "• رمز الدخول (مثال: TKT-12345)\n";
        $defaultMessage .= "• أو رقم هاتفك (مثال: 03670065)\n\n";
        $defaultMessage .= "💡 اكتب /help للمساعدة";
        
        sendTelegramMessage($botToken, $chatId, $defaultMessage);
    }
    
} catch (Exception $e) {
    webhookLog("❌ Telegram Webhook Exception: " . $e->getMessage());
    webhookLog("Stack trace: " . $e->getTraceAsString());
}

http_response_code(200);
exit;

/**
 * إرسال رسالة Telegram
 */
function sendTelegramMessage($botToken, $chatId, $message, $keyboard = null) {
    try {
        $telegramApiHost = 'api.telegram.org';
        $telegramApiIp = '149.154.167.220'; // IP احتياطي لـ api.telegram.org
        
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];
        
        if ($keyboard) {
            $data['reply_markup'] = json_encode($keyboard);
        }
        
        webhookLog("sendTelegramMessage - Chat ID: " . $chatId);
        webhookLog("sendTelegramMessage - Message length: " . strlen($message));
        
        // محاولة الإرسال مع retry و DNS fallback
        $maxRetries = 2;
        $retryDelay = 1; // ثانية واحدة
        $response = false;
        $httpCode = 0;
        $curlError = '';
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            webhookLog("Attempt $attempt of $maxRetries to send Telegram message");
            
            $url = '';
            if ($attempt == 1) {
                // محاولة حل DNS أولاً في المحاولة الأولى
                $resolvedIp = @gethostbyname($telegramApiHost);
                if ($resolvedIp === $telegramApiHost || empty($resolvedIp)) {
                    webhookLog("⚠️ DNS resolution failed for $telegramApiHost, using IP directly: $telegramApiIp");
                    $url = "https://{$telegramApiIp}/bot{$botToken}/sendMessage";
                } else {
                    webhookLog("✅ DNS resolved: $telegramApiHost -> $resolvedIp");
                    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
                }
            } else {
                // في المحاولة الثانية، استخدم IP مباشر
                $url = "https://{$telegramApiIp}/bot{$botToken}/sendMessage";
                webhookLog("🔄 Retry attempt $attempt: Using IP directly: $telegramApiIp");
            }
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
            curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 300);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            
            // إذا استخدمنا IP مباشر، نضيف Host header
            if (strpos($url, $telegramApiIp) !== false) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Host: $telegramApiHost"]);
            }
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if (!$curlError && $httpCode == 200) {
                webhookLog("✅ Success on attempt $attempt");
                break;
            }
            
            if ($attempt < $maxRetries) {
                webhookLog("⚠️ Attempt $attempt failed, retrying in $retryDelay seconds...");
                webhookLog("Error: $curlError, HTTP Code: $httpCode");
                sleep($retryDelay);
            }
        }
        
        webhookLog("sendTelegramMessage - Final HTTP Code: " . $httpCode);
        if ($curlError) {
            webhookLog("sendTelegramMessage - Final cURL Error: " . $curlError);
        }
        
        if ($response) {
            $responseData = json_decode($response, true);
            if (isset($responseData['ok']) && $responseData['ok']) {
                webhookLog("✅ sendTelegramMessage - Success");
            } else {
                webhookLog("❌ sendTelegramMessage - API Error: " . ($responseData['description'] ?? 'Unknown'));
                webhookLog("Full response: " . substr($response, 0, 500));
            }
        } else {
            webhookLog("❌ sendTelegramMessage - No response from Telegram API");
        }
        
        return $response;
    } catch (Exception $e) {
        webhookLog("❌ sendTelegramMessage Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * إرسال جميع الرسائل المعلقة (pending) للمواطن
 */
function sendPendingMessages($db, $botToken, $citizenId, $chatId) {
    try {
        // جلب جميع الرسائل المعلقة
        $stmt = $db->prepare("
            SELECT * FROM telegram_log 
            WHERE citizen_id = ? 
            AND status = 'pending' 
            ORDER BY created_at ASC
        ");
        $stmt->execute([$citizenId]);
        $pendingMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($pendingMessages)) {
            return;
        }
        
        // إرسال كل رسالة معلقة
        foreach ($pendingMessages as $msg) {
            // استخدام message أو message_text حسب ما هو موجود في الجدول
            $messageText = $msg['message'] ?? $msg['message_text'] ?? '';
            
            if (!empty($messageText)) {
                $sent = sendTelegramMessage($botToken, $chatId, $messageText);
                
                if ($sent) {
                    // تحديث حالة الرسالة إلى "sent"
                    $updateStmt = $db->prepare("
                        UPDATE telegram_log 
                        SET status = 'sent', 
                            sent_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$msg['id']]);
                }
                
                // تأخير بسيط لتجنب الحظر من Telegram
                usleep(500000); // 0.5 ثانية
            }
        }
        
        // إرسال رسالة إضافية لإعلام المواطن
        if (count($pendingMessages) > 0) {
            $summaryMessage = "📬 تم إرسال " . count($pendingMessages) . " إشعار(ات) معلقة.\n\n";
            $summaryMessage .= "✅ أنت الآن مشترك في الإشعارات الفورية!";
            sendTelegramMessage($botToken, $chatId, $summaryMessage);
        }
        
    } catch (Exception $e) {
        error_log("Send Pending Messages Error: " . $e->getMessage());
    }
}

/**
 * الحصول على رابط الموقع الأساسي
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host . '/tekrit_municipality';
}

