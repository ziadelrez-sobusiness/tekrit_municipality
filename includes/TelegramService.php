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
        if (!$this->enabled || empty($this->botToken)) {
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
            
            // إضافة أزرار تفاعلية (فقط إذا لم يكن localhost)
            $keyboard = null;
            $baseUrl = $this->getBaseUrl();
            
            // Telegram لا يقبل روابط localhost في الأزرار
            if (strpos($baseUrl, 'localhost') === false && strpos($baseUrl, '127.0.0.1') === false) {
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '🔍 تتبع الطلب', 'url' => $this->getTrackingUrl($requestData['tracking_number'])],
                            ['text' => '👤 حسابي', 'url' => $this->getDashboardUrl($accessCode)]
                        ]
                    ]
                ];
            } else {
                // إضافة الروابط في نص الرسالة بدلاً من الأزرار
                // على localhost، نضع الروابط كنص عادي
                $trackingUrl = $this->getTrackingUrl($requestData['tracking_number']);
                $dashboardUrl = $this->getDashboardUrl($accessCode);
                
                $message .= "\n\n🔗 الروابط:\n\n";
                $message .= "تتبع الطلب:\n" . $trackingUrl . "\n\n";
                $message .= "حسابك الشخصي:\n" . $dashboardUrl;
                
                $keyboard = null;
            }
            
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
            
            // إرسال الرسالة إذا كان Chat ID موجود
            if (!empty($citizenData['telegram_chat_id'])) {
                error_log("TelegramService::sendWelcomeMessage - Sending message to Chat ID: " . $citizenData['telegram_chat_id']);
                
                $sent = $this->sendMessage(
                    $citizenData['telegram_chat_id'],
                    $message,
                    $keyboard
                );
                
                if ($sent['success']) {
                    $this->updateMessageStatus($logId, 'sent');
                    return ['success' => true, 'message' => 'تم إرسال الرسالة'];
                } else {
                    $errorMsg = $sent['error'] ?? 'فشل الإرسال';
                    $this->updateMessageStatus($logId, 'failed', $errorMsg);
                    return ['success' => false, 'message' => $errorMsg];
                }
            } else {
                // المواطن لم يشترك في البوت بعد
                error_log("TelegramService::sendWelcomeMessage - Chat ID is empty, message logged as pending");
                return ['success' => true, 'message' => 'تم تسجيل الرسالة (في انتظار اشتراك المواطن)'];
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
     * إرسال رسالة Telegram عبر API
     */
    private function sendMessage($chatId, $message, $keyboard = null) {
        if (empty($this->botToken) || empty($chatId)) {
            return false;
        }
        
        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        
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
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            error_log("Telegram cURL Error: $curlError");
            return ['success' => false, 'error' => "خطأ في الاتصال: $curlError"];
        }
        
        if ($httpCode == 200) {
            $result = json_decode($response, true);
            if (isset($result['ok']) && $result['ok'] === true) {
                return ['success' => true];
            } else {
                $errorDesc = $result['description'] ?? 'خطأ غير معروف';
                error_log("Telegram API Error: $errorDesc");
                return ['success' => false, 'error' => $errorDesc];
            }
        }
        
        error_log("Telegram API Error: HTTP $httpCode - $response");
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
            return null;
        }
        
        $url = "https://api.telegram.org/bot{$this->botToken}/getMe";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if (isset($result['ok']) && $result['ok'] === true) {
            return $result['result'];
        }
        
        return null;
    }
}

