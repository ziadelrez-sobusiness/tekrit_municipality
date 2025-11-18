<?php
/**
 * Telegram Service
 * خدمة إرسال رسائل Telegram للمواطنين
 * بلدية تكريت - عكار، شمال لبنان
 */

class TelegramService {
    private $db;
    private $botToken;
    private $botUsername;
    private $enabled;
    
    public function __construct($db) {
        $this->db = $db;
        $this->loadSettings();
    }
    
    /**
     * تحميل إعدادات Telegram من قاعدة البيانات
     */
    private function loadSettings() {
        try {
            $stmt = $this->db->query("
                SELECT setting_key, setting_value 
                FROM website_settings 
                WHERE setting_key LIKE 'telegram%'
            ");
            
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            $this->enabled = isset($settings['telegram_bot_enabled']) && $settings['telegram_bot_enabled'] == '1';
            $this->botToken = $settings['telegram_bot_token'] ?? '';
            $this->botUsername = $settings['telegram_bot_username'] ?? 'TekritAkkarBot';
            
        } catch (Exception $e) {
            error_log("Telegram Settings Error: " . $e->getMessage());
            $this->enabled = false;
        }
    }
    
    /**
     * إرسال رسالة ترحيب عند تقديم طلب جديد
     */
    public function sendWelcomeMessage($citizenData, $requestData, $accessCode) {
        error_log("=== sendWelcomeMessage CALLED ===");
        error_log("Enabled: " . ($this->enabled ? 'YES' : 'NO'));
        error_log("Bot Token: " . (empty($this->botToken) ? 'EMPTY' : 'SET'));
        error_log("Citizen Data: " . json_encode($citizenData, JSON_UNESCAPED_UNICODE));
        error_log("Request Data: " . json_encode($requestData, JSON_UNESCAPED_UNICODE));
        error_log("Access Code: " . ($accessCode ?? 'NULL'));
        
        if (!$this->enabled || empty($this->botToken)) {
            error_log("❌ TelegramService::sendWelcomeMessage - Bot not enabled or token missing");
            return ['success' => false, 'message' => 'Telegram Bot غير مفعّل'];
        }
        
        try {
            // الحصول على قالب الرسالة
            $stmt = $this->db->prepare("
                SELECT setting_value 
                FROM website_settings 
                WHERE setting_key = 'telegram_welcome_template'
            ");
            $stmt->execute();
            $template = $stmt->fetchColumn();
            
            // بناء الروابط أولاً (قبل استبدال المتغيرات)
            $baseUrl = $this->getBaseUrl();
            $trackingUrl = $this->getTrackingUrl($requestData['tracking_number'] ?? '');
            $dashboardUrl = $baseUrl . '/public/citizen-dashboard.php?code=' . urlencode($accessCode ?? '');
            
            if (!$template) {
                // قالب افتراضي محسّن مع الروابط
                $template = "✅ مرحباً بك في بلدية تكريت - عكار!\n\n📋 تم تقديم طلبكم بنجاح:\n\n🔢 رقم التتبع: {tracking_number}\n📝 نوع الطلب: {request_type}\n📅 التاريخ: {date}\n\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\n🔐 {access_code}\n\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\n{tracking_url}\n\nحسابك الشخصي:\n{dashboard_url}";
            }
            
            // استبدال المتغيرات
            $message = str_replace(
                [
                    '{tracking_number}', 
                    '{request_type}', 
                    '{date}', 
                    '{access_code}', 
                    '{citizen_name}',
                    '{tracking_url}',
                    '{dashboard_url}',
                    '{request_title}'
                ],
                [
                    $requestData['tracking_number'] ?? '',
                    $requestData['type_name'] ?? '',
                    date('Y-m-d'),
                    $accessCode ?? '',
                    $citizenData['name'] ?? '',
                    $trackingUrl,
                    $dashboardUrl,
                    $requestData['request_title'] ?? ''
                ],
                $template
            );
            
            // التأكد من إضافة الروابط حتى لو لم تكن في القالب
            if (strpos($message, $trackingUrl) === false && !empty($trackingUrl)) {
                $message .= "\n\n🔗 الروابط:\n\nتتبع الطلب:\n" . $trackingUrl;
            }
            if (strpos($message, $dashboardUrl) === false && !empty($dashboardUrl)) {
                $message .= "\n\nحسابك الشخصي:\n" . $dashboardUrl;
            }
            
            // إضافة أزرار تفاعلية (فقط إذا لم يكن localhost)
            $keyboard = null;
            $baseUrl = $this->getBaseUrl();
            
            // Telegram لا يقبل روابط localhost في الأزرار
            if (strpos($baseUrl, 'localhost') === false && strpos($baseUrl, '127.0.0.1') === false) {
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '🔍 تتبع الطلب', 'url' => $trackingUrl],
                            ['text' => '👤 حسابي', 'url' => $dashboardUrl]
                        ]
                    ]
                ];
            }
            // على localhost، الروابط موجودة بالفعل في القالب
            
            // تسجيل للتصحيح
            error_log("TelegramService::sendWelcomeMessage - Chat ID: " . ($citizenData['telegram_chat_id'] ?? 'NULL'));
            error_log("TelegramService::sendWelcomeMessage - Citizen ID: " . ($citizenData['citizen_id'] ?? 'NULL'));
            error_log("TelegramService::sendWelcomeMessage - Message Length: " . strlen($message));
            
            // تسجيل الرسالة في قاعدة البيانات
            $logId = $this->logMessage(
                $citizenData['citizen_id'] ?? null,
                $citizenData['telegram_chat_id'] ?? null,
                $requestData['request_id'] ?? null,
                'welcome',
                $message
            );
            
            // التحقق من وجود Chat ID قبل الإرسال
            $chatId = $citizenData['telegram_chat_id'] ?? null;
            error_log("Checking telegram_chat_id: " . ($chatId ?? 'NULL'));
            error_log("Empty check: " . (empty($chatId) ? 'YES (empty)' : 'NO (has value)'));
            
            // إذا لم يكن Chat ID موجود، لا نرسل الرسالة
            if (empty($chatId)) {
                error_log("⚠️ TelegramService::sendWelcomeMessage - Chat ID is empty, skipping message send");
                error_log("This is normal for new citizens who haven't linked their Telegram account yet");
                $this->updateMessageStatus($logId, 'pending', 'Chat ID not available');
                return ['success' => true, 'message' => 'تم تسجيل الرسالة (في انتظار ربط حساب Telegram)'];
            }
            
            // التحقق من أن Chat ID صحيح (رقم وليس نص فارغ)
            if (!is_numeric($chatId) || $chatId <= 0) {
                error_log("⚠️ TelegramService::sendWelcomeMessage - Invalid Chat ID: " . $chatId);
                $this->updateMessageStatus($logId, 'failed', 'Invalid Chat ID');
                return ['success' => false, 'message' => 'Chat ID غير صحيح'];
            }
            
            error_log("✅ TelegramService::sendWelcomeMessage - Sending message to Chat ID: " . $chatId);
            
            $sent = $this->sendMessage(
                $chatId,
                $message,
                $keyboard
            );
            
            error_log("Send result: " . json_encode($sent));
            
            if ($sent['success']) {
                $this->updateMessageStatus($logId, 'sent');
                error_log("✅ Message sent successfully to citizen");
                return ['success' => true, 'message' => 'تم إرسال الرسالة'];
            } else {
                $errorMsg = $sent['error'] ?? 'فشل الإرسال';
                $this->updateMessageStatus($logId, 'failed', $errorMsg);
                error_log("❌ Failed to send message: " . $errorMsg);
                return ['success' => false, 'message' => $errorMsg];
            }
            
        } catch (Exception $e) {
            error_log("Telegram Send Error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * إرسال رسالة تحديث حالة الطلب
     */
    public function sendStatusUpdate($citizenData, $requestData, $newStatus, $notes = '') {
        if (!$this->enabled || empty($this->botToken) || empty($citizenData['telegram_chat_id'])) {
            return ['success' => false, 'message' => 'Telegram غير متاح'];
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT setting_value 
                FROM website_settings 
                WHERE setting_key = 'telegram_status_update_template'
            ");
            $stmt->execute();
            $template = $stmt->fetchColumn();
            
            if (!$template) {
                $template = "📢 تحديث حالة الطلب\n\n🔢 {tracking_number}\n📝 {request_type}\n\n🔄 الحالة: {new_status}\n\n💬 {notes}";
            }
            
            $message = str_replace(
                ['{tracking_number}', '{request_type}', '{new_status}', '{notes}'],
                [
                    $requestData['tracking_number'] ?? '',
                    $requestData['type_name'] ?? '',
                    $newStatus,
                    $notes
                ],
                $template
            );
            
            // إضافة أزرار تفاعلية (فقط إذا لم يكن localhost)
            $keyboard = null;
            $baseUrl = $this->getBaseUrl();
            
            if (strpos($baseUrl, 'localhost') === false && strpos($baseUrl, '127.0.0.1') === false) {
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '🔍 عرض التفاصيل', 'url' => $this->getTrackingUrl($requestData['tracking_number'])]
                        ]
                    ]
                ];
            } else {
                // إضافة الرابط في نص الرسالة
                // Telegram يعرض الروابط تلقائياً كـ clickable
                $trackingUrl = $this->getTrackingUrl($requestData['tracking_number']);
                $message .= "\n\n🔗 عرض التفاصيل:\n" . $trackingUrl;
            }
            
            $logId = $this->logMessage(
                $citizenData['citizen_id'],
                $citizenData['telegram_chat_id'],
                $requestData['request_id'] ?? null,
                'status_update',
                $message
            );
            
            $sent = $this->sendMessage($citizenData['telegram_chat_id'], $message, $keyboard);
            
            if ($sent['success']) {
                $this->updateMessageStatus($logId, 'sent');
                return ['success' => true];
            } else {
                $errorMsg = $sent['error'] ?? 'فشل الإرسال';
                $this->updateMessageStatus($logId, 'failed', $errorMsg);
                return ['success' => false, 'error' => $errorMsg];
            }
            
        } catch (Exception $e) {
            error_log("Telegram Status Update Error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * إرسال إشعار إداري عند تقديم طلب جديد
     */
    public function sendAdminNotification($requestData) {
        error_log("=== sendAdminNotification CALLED ===");
        error_log("Enabled: " . ($this->enabled ? 'YES' : 'NO'));
        error_log("Bot Token: " . (empty($this->botToken) ? 'EMPTY' : 'SET'));
        
        if (!$this->enabled || empty($this->botToken)) {
            error_log("TelegramService::sendAdminNotification - Bot not enabled or token missing");
            return ['success' => false, 'message' => 'Telegram Bot غير مفعّل'];
        }
        
        try {
            // جلب Chat ID الإداري من الإعدادات
            $stmt = $this->db->prepare("
                SELECT setting_value 
                FROM website_settings 
                WHERE setting_key = 'telegram_admin_chat_id'
            ");
            $stmt->execute();
            $adminChatId = $stmt->fetchColumn();
            
            error_log("Admin Chat ID from DB: " . ($adminChatId ? $adminChatId : 'NOT FOUND'));
            
            // إذا لم يكن موجوداً، محاولة الحصول من webhook أو من أول مستخدم في citizens_accounts
            if (empty($adminChatId)) {
                error_log("Admin Chat ID not found, trying alternative methods...");
                
                // محاولة 1: جلب من webhook logs (آخر chat_id استقبل رسالة)
                $stmt = $this->db->query("
                    SELECT telegram_chat_id 
                    FROM telegram_log 
                    WHERE telegram_chat_id IS NOT NULL 
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $adminChatId = $stmt->fetchColumn();
                
                if ($adminChatId) {
                    error_log("Found Chat ID from telegram_log: " . $adminChatId);
                } else {
                    // محاولة 2: جلب من citizens_accounts (أول حساب مرتبط)
                    $stmt = $this->db->query("
                        SELECT telegram_chat_id 
                        FROM citizens_accounts 
                        WHERE telegram_chat_id IS NOT NULL 
                        LIMIT 1
                    ");
                    $adminChatId = $stmt->fetchColumn();
                    
                    if ($adminChatId) {
                        error_log("Found Chat ID from citizens_accounts: " . $adminChatId);
                    }
                }
            }
            
            if (empty($adminChatId)) {
                error_log("TelegramService::sendAdminNotification - Admin Chat ID not configured and no alternative found");
                error_log("Please set 'telegram_admin_chat_id' in website_settings table");
                return ['success' => false, 'message' => 'لم يتم تكوين Chat ID الإداري'];
            }
            
            error_log("Using Admin Chat ID: " . $adminChatId);
            
            // الحصول على قالب الرسالة الإدارية
            $stmt = $this->db->prepare("
                SELECT setting_value 
                FROM website_settings 
                WHERE setting_key = 'telegram_admin_notification_template'
            ");
            $stmt->execute();
            $template = $stmt->fetchColumn();
            
            if (!$template) {
                $template = "🔔 طلب جديد من مواطن\n\n👤 المواطن: {citizen_name}\n📞 الهاتف: {citizen_phone}\n📧 البريد: {citizen_email}\n\n🔢 رقم التتبع: {tracking_number}\n📝 نوع الطلب: {request_type}\n📋 عنوان الطلب: {request_title}\n⚡ الأولوية: {priority}\n📅 التاريخ: {date}\n\n🔗 رابط الطلب: {request_url}";
            }
            
            // استبدال المتغيرات
            $baseUrl = $this->getBaseUrl();
            $requestUrl = $baseUrl . '/modules/update_citizen_request.php?id=' . ($requestData['request_id'] ?? '');
            
            $message = str_replace(
                [
                    '{citizen_name}', 
                    '{citizen_phone}', 
                    '{citizen_email}', 
                    '{tracking_number}', 
                    '{request_type}', 
                    '{request_title}', 
                    '{priority}', 
                    '{date}', 
                    '{request_url}'
                ],
                [
                    $requestData['citizen_name'] ?? 'غير محدد',
                    $requestData['citizen_phone'] ?? 'غير محدد',
                    $requestData['citizen_email'] ?? 'غير محدد',
                    $requestData['tracking_number'] ?? 'غير محدد',
                    $requestData['type_name'] ?? 'غير محدد',
                    $requestData['request_title'] ?? 'غير محدد',
                    $requestData['priority_level'] ?? 'عادي',
                    date('Y-m-d H:i'),
                    $requestUrl
                ],
                $template
            );
            
            // إضافة أزرار تفاعلية (فقط إذا لم يكن localhost)
            $keyboard = null;
            if (strpos($baseUrl, 'localhost') === false && strpos($baseUrl, '127.0.0.1') === false) {
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '📋 عرض الطلب', 'url' => $requestUrl]
                        ]
                    ]
                ];
            } else {
                $message .= "\n\n🔗 رابط الطلب:\n" . $requestUrl;
            }
            
            error_log("TelegramService::sendAdminNotification - Preparing to send message");
            error_log("Message length: " . strlen($message));
            error_log("Sending to Admin Chat ID: " . $adminChatId);
            error_log("Bot Token (first 10 chars): " . substr($this->botToken, 0, 10) . "...");
            
            // إرسال الرسالة
            $sent = $this->sendMessage($adminChatId, $message, $keyboard);
            
            error_log("Send result: " . json_encode($sent));
            
            if ($sent['success']) {
                error_log("TelegramService::sendAdminNotification - ✅ Message sent successfully");
                return ['success' => true, 'message' => 'تم إرسال الإشعار الإداري'];
            } else {
                $errorMsg = $sent['error'] ?? 'فشل الإرسال';
                error_log("TelegramService::sendAdminNotification - ❌ Failed: " . $errorMsg);
                return ['success' => false, 'message' => $errorMsg];
            }
            
        } catch (Exception $e) {
            error_log("Telegram Admin Notification Error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * إرسال رسالة Telegram عبر API
     */
    private function sendMessage($chatId, $message, $keyboard = null) {
        error_log("=== sendMessage CALLED ===");
        error_log("Chat ID: " . $chatId);
        error_log("Message length: " . strlen($message));
        error_log("Bot Token exists: " . (empty($this->botToken) ? 'NO' : 'YES'));
        
        if (empty($this->botToken) || empty($chatId)) {
            error_log("sendMessage: Missing botToken or chatId");
            return ['success' => false, 'error' => 'Bot Token أو Chat ID مفقود'];
        }
        
        // محاولة استخدام IP مباشر إذا فشل DNS
        $telegramApiHost = 'api.telegram.org';
        $telegramApiIp = '149.154.167.220'; // IP احتياطي لـ api.telegram.org
        
        // محاولة حل DNS أولاً
        $resolvedIp = @gethostbyname($telegramApiHost);
        
        if ($resolvedIp === $telegramApiHost || empty($resolvedIp)) {
            // DNS resolution failed، استخدام IP مباشر
            error_log("⚠️ DNS resolution failed for $telegramApiHost, using IP directly: $telegramApiIp");
            $url = "https://{$telegramApiIp}/bot{$this->botToken}/sendMessage";
        } else {
            error_log("✅ DNS resolved: $telegramApiHost -> $resolvedIp");
            $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        }
        
        error_log("API URL: " . str_replace($this->botToken, 'TOKEN_HIDDEN', $url));
        
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];
        
        if ($keyboard) {
            $data['reply_markup'] = json_encode($keyboard);
            error_log("Keyboard added: " . json_encode($keyboard));
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // تقليل timeout إلى 10 ثواني لتسريع العملية
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // تقليل timeout للاتصال إلى 5 ثواني
        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 300); // cache DNS لمدة 5 دقائق
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // استخدام IPv4 فقط
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        
        // إذا استخدمنا IP مباشر، نضيف Host header
        if (strpos($url, $telegramApiIp) !== false) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Host: $telegramApiHost"]);
        }
        
        // محاولة الإرسال مع retry (تقليل المحاولات لتسريع العملية)
        $maxRetries = 1; // محاولة واحدة فقط لتسريع العملية
        $retryDelay = 1; // ثانية واحدة
        $response = false;
        $httpCode = 0;
        $curlError = '';
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            error_log("Attempt $attempt of $maxRetries to send Telegram message");
            
            // إعادة تهيئة cURL في كل محاولة
            if ($attempt > 1) {
                curl_close($ch);
                
                // في المحاولة الثانية، استخدم IP مباشر
                if ($attempt == 2) {
                    $url = "https://{$telegramApiIp}/bot{$this->botToken}/sendMessage";
                    error_log("🔄 Retry attempt $attempt: Using IP directly: $telegramApiIp");
                }
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
                curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 300);
                curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
                
                // إذا استخدمنا IP مباشر، نضيف Host header
                if (strpos($url, $telegramApiIp) !== false) {
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Host: $telegramApiHost"]);
                }
            }
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            
            if (!$curlError && $httpCode == 200) {
                error_log("✅ Success on attempt $attempt");
                break;
            }
            
            if ($attempt < $maxRetries) {
                error_log("⚠️ Attempt $attempt failed, retrying in $retryDelay seconds...");
                error_log("Error: $curlError, HTTP Code: $httpCode");
                sleep($retryDelay);
            }
        }
        
        curl_close($ch);
        
        error_log("Final HTTP Code: " . $httpCode);
        error_log("Final Response: " . substr($response, 0, 500)); // أول 500 حرف فقط
        
        if ($curlError) {
            error_log("❌ Telegram cURL Error after $maxRetries attempts: $curlError");
            
            // إذا كان الخطأ DNS resolution، نعطي رسالة أوضح
            if (strpos($curlError, 'Resolving timed out') !== false || strpos($curlError, 'Could not resolve host') !== false) {
                return ['success' => false, 'error' => "خطأ في الاتصال بالإنترنت أو DNS. يرجى التحقق من الاتصال بالإنترنت"];
            }
            
            return ['success' => false, 'error' => "خطأ في الاتصال: $curlError"];
        }
        
        if ($httpCode == 200) {
            $result = json_decode($response, true);
            if (isset($result['ok']) && $result['ok'] === true) {
                error_log("✅ Message sent successfully via Telegram API");
                return ['success' => true];
            } else {
                $errorDesc = $result['description'] ?? 'خطأ غير معروف';
                error_log("❌ Telegram API Error: $errorDesc");
                error_log("Full response: " . json_encode($result));
                return ['success' => false, 'error' => $errorDesc];
            }
        }
        
        error_log("❌ Telegram API Error: HTTP $httpCode");
        error_log("Response: " . $response);
        return ['success' => false, 'error' => "HTTP Error $httpCode"];
    }
    
    /**
     * تسجيل رسالة في قاعدة البيانات
     */
    private function logMessage($citizenId, $chatId, $requestId, $messageType, $message) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO telegram_log (
                    citizen_id, telegram_chat_id, request_id, 
                    message_type, message, status, created_at
                ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            
            $stmt->execute([
                $citizenId,
                $chatId,
                $requestId,
                $messageType,
                $message
            ]);
            
            return $this->db->lastInsertId();
            
        } catch (Exception $e) {
            error_log("Telegram Log Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * تحديث حالة الرسالة
     */
    private function updateMessageStatus($logId, $status, $errorMessage = null) {
        if (!$logId) return;
        
        try {
            $stmt = $this->db->prepare("
                UPDATE telegram_log 
                SET status = ?, 
                    sent_at = IF(? = 'sent', NOW(), sent_at),
                    error_message = ?
                WHERE id = ?
            ");
            
            $stmt->execute([$status, $status, $errorMessage, $logId]);
            
        } catch (Exception $e) {
            error_log("Telegram Update Status Error: " . $e->getMessage());
        }
    }
    
    /**
     * الحصول على رابط تتبع الطلب
     */
    private function getTrackingUrl($trackingNumber) {
        $baseUrl = $this->getBaseUrl();
        return $baseUrl . '/public/track-request.php?tracking=' . urlencode($trackingNumber);
    }
    
    /**
     * الحصول على رابط لوحة التحكم
     */
    private function getDashboardUrl($accessCode) {
        $baseUrl = $this->getBaseUrl();
        return $baseUrl . '/public/citizen-dashboard.php?code=' . urlencode($accessCode);
    }
    
    /**
     * الحصول على رابط الموقع الأساسي
     */
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseDir = dirname(dirname($_SERVER['SCRIPT_NAME']));
        return $protocol . '://' . $host . $baseDir;
    }
    
    /**
     * الحصول على معلومات البوت
     */
    public function getBotInfo() {
        if (empty($this->botToken)) {
            error_log("getBotInfo: Bot Token is empty");
            return null;
        }
        
        // محاولة استخدام IP مباشر إذا فشل DNS
        $telegramApiHost = 'api.telegram.org';
        $telegramApiIp = '149.154.167.220'; // IP احتياطي لـ api.telegram.org
        
        // محاولة حل DNS أولاً
        $resolvedIp = @gethostbyname($telegramApiHost);
        
        if ($resolvedIp === $telegramApiHost || empty($resolvedIp)) {
            // DNS resolution failed، استخدام IP مباشر
            error_log("⚠️ getBotInfo: DNS resolution failed, using IP directly: $telegramApiIp");
            $url = "https://{$telegramApiIp}/bot{$this->botToken}/getMe";
        } else {
            error_log("✅ getBotInfo: DNS resolved: $telegramApiHost -> $resolvedIp");
            $url = "https://api.telegram.org/bot{$this->botToken}/getMe";
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        
        // إذا استخدمنا IP مباشر، نضيف Host header
        if (strpos($url, $telegramApiIp) !== false) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Host: $telegramApiHost"]);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            error_log("❌ getBotInfo cURL Error: $curlError");
            // محاولة مرة أخرى مع IP مباشر
            if (strpos($url, $telegramApiHost) !== false) {
                error_log("🔄 getBotInfo: Retrying with IP directly...");
                $url = "https://{$telegramApiIp}/bot{$this->botToken}/getMe";
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
                curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Host: $telegramApiHost"]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);
            }
        }
        
        if ($curlError) {
            error_log("❌ getBotInfo: Failed after retry: $curlError");
            return null;
        }
        
        if ($httpCode != 200) {
            error_log("❌ getBotInfo: HTTP Error $httpCode");
            return null;
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['ok']) && $result['ok'] === true) {
            error_log("✅ getBotInfo: Success - Bot name: " . ($result['result']['first_name'] ?? 'N/A'));
            return $result['result'];
        }
        
        error_log("❌ getBotInfo: API returned error: " . ($result['description'] ?? 'Unknown error'));
        return null;
    }
}

