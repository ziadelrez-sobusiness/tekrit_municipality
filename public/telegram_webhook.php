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

if (!$update) {
    http_response_code(200);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // استخراج المعلومات
    $message = $update['message'] ?? null;
    
    if (!$message) {
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
    
    // الحصول على Bot Token
    $stmt = $db->query("SELECT setting_value FROM website_settings WHERE setting_key = 'telegram_bot_token'");
    $botToken = $stmt->fetchColumn();
    
    if (!$botToken || !$chatId) {
        http_response_code(200);
        exit;
    }
    
    // معالجة الأوامر
    if ($text == '/start') {
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
        
    } elseif (preg_match('/^TKT\-[0-9]{5}$/', $text)) {
        // المستخدم أرسل رمز دخول
        $accessCode = strtoupper($text);
        
        $accountHelper = new CitizenAccountHelper($db);
        $accountResult = $accountHelper->getAccountByAccessCode($accessCode);
        
        if ($accountResult['success']) {
            $account = $accountResult['account'];
            
            // ربط Telegram Chat ID بالحساب
            $stmt = $db->prepare("
                UPDATE citizens_accounts 
                SET telegram_chat_id = ?, 
                    telegram_username = ?
                WHERE id = ?
            ");
            $stmt->execute([$chatId, $username, $account['id']]);
            
            // إرسال رسالة التأكيد
            $responseMessage = "✅ تم ربط حسابك بنجاح!\n\n";
            $responseMessage .= "👤 الاسم: " . $account['name'] . "\n";
            $responseMessage .= "📱 الهاتف: " . $account['phone'] . "\n\n";
            $responseMessage .= "🔔 ستستلم الآن جميع الإشعارات المتعلقة بطلباتك.";
            
            // إضافة أزرار تفاعلية
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '👤 حسابي الشخصي', 'url' => getBaseUrl() . '/public/citizen-dashboard.php?code=' . $accessCode],
                        ['text' => '📝 تقديم طلب', 'url' => getBaseUrl() . '/public/citizen-requests.php']
                    ]
                ]
            ];
            
            sendTelegramMessage($botToken, $chatId, $responseMessage, $keyboard);
            
            // إرسال جميع الرسائل المعلقة (pending)
            sendPendingMessages($db, $botToken, $account['id'], $chatId);
            
        } else {
            sendTelegramMessage($botToken, $chatId, "❌ رمز الدخول غير صحيح. يرجى التحقق والمحاولة مرة أخرى.");
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
    error_log("Telegram Webhook Error: " . $e->getMessage());
}

http_response_code(200);
exit;

/**
 * إرسال رسالة Telegram
 */
function sendTelegramMessage($botToken, $chatId, $message, $keyboard = null) {
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    
    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response;
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
            $sent = sendTelegramMessage($botToken, $chatId, $msg['message_text']);
            
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

