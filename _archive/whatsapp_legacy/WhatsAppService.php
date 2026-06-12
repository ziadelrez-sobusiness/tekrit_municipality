<?php
/**
 * خدمة إرسال رسائل WhatsApp
 * بلدية تكريت - عكار، شمال لبنان
 */

class WhatsAppService {
    private $db;
    private $settings;
    
    public function __construct($database) {
        $this->db = $database;
        $this->loadSettings();
    }
    
    /**
     * تحميل إعدادات WhatsApp من قاعدة البيانات
     */
    private function loadSettings() {
        $this->settings = [];
        
        $stmt = $this->db->prepare("SELECT setting_key, setting_value FROM website_settings WHERE setting_key LIKE 'whatsapp_%' OR setting_key IN ('municipality_phone', 'municipality_whatsapp_name')");
        $stmt->execute();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    /**
     * التحقق من تفعيل WhatsApp
     */
    public function isEnabled() {
        return isset($this->settings['whatsapp_enabled']) && $this->settings['whatsapp_enabled'] == '1';
    }
    
    /**
     * إرسال رسالة ترحيب عند إنشاء طلب جديد
     */
    public function sendWelcomeMessage($citizenData, $requestData, $magicLink = null) {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'WhatsApp غير مفعل'];
        }
        
        // الحصول على القالب
        $template = $this->settings['whatsapp_welcome_template'] ?? $this->getDefaultWelcomeTemplate();
        
        // استبدال المتغيرات
        $message = $this->replaceVariables($template, [
            '{name}' => $citizenData['name'],
            '{request_type}' => $requestData['type_name'],
            '{tracking_number}' => $requestData['tracking_number'],
            '{date}' => date('Y-m-d H:i'),
            '{magic_link}' => $magicLink ?? 'http://localhost:8080/tekrit_municipality/public/track-request.php',
            '{phone}' => $citizenData['phone'],
            '{code}' => substr($requestData['tracking_number'], -6)
        ]);
        
        // تسجيل في whatsapp_log
        return $this->logMessage($citizenData['phone'], $message, 'welcome', $requestData['request_id'], $citizenData['citizen_id'] ?? null);
    }
    
    /**
     * إرسال رسالة تحديث حالة الطلب
     */
    public function sendStatusUpdate($citizenData, $requestData, $updateText, $magicLink = null) {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'WhatsApp غير مفعل'];
        }
        
        $template = $this->settings['whatsapp_status_update_template'] ?? $this->getDefaultStatusTemplate();
        
        $message = $this->replaceVariables($template, [
            '{tracking_number}' => $requestData['tracking_number'],
            '{request_type}' => $requestData['type_name'],
            '{status}' => $requestData['status'],
            '{update_text}' => $updateText,
            '{magic_link}' => $magicLink ?? 'http://localhost:8080/tekrit_municipality/public/track-request.php'
        ]);
        
        return $this->logMessage($citizenData['phone'], $message, 'status_update', $requestData['request_id'], $citizenData['citizen_id'] ?? null);
    }
    
    /**
     * إرسال رسالة إنجاز الطلب
     */
    public function sendCompletionMessage($citizenData, $requestData) {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'WhatsApp غير مفعل'];
        }
        
        $template = $this->settings['whatsapp_completion_template'] ?? $this->getDefaultCompletionTemplate();
        
        $message = $this->replaceVariables($template, [
            '{tracking_number}' => $requestData['tracking_number'],
            '{request_type}' => $requestData['type_name'],
            '{request_title}' => $requestData['request_title'],
            '{municipality_phone}' => $this->settings['municipality_phone'] ?? '06-123-456'
        ]);
        
        return $this->logMessage($citizenData['phone'], $message, 'completion', $requestData['request_id'], $citizenData['citizen_id'] ?? null);
    }
    
    /**
     * تسجيل الرسالة في قاعدة البيانات
     */
    private function logMessage($phone, $message, $messageType, $requestId = null, $citizenId = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO whatsapp_log 
                (phone, message, message_type, request_id, citizen_id, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            
            $stmt->execute([$phone, $message, $messageType, $requestId, $citizenId]);
            
            $logId = $this->db->lastInsertId();
            
            // محاولة الإرسال الفعلي
            $sendResult = $this->sendActualMessage($phone, $message, $logId);
            
            return [
                'success' => true,
                'log_id' => $logId,
                'message' => 'تم تسجيل الرسالة',
                'send_result' => $sendResult
            ];
            
        } catch (Exception $e) {
            error_log("WhatsApp Log Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'فشل في تسجيل الرسالة: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * الإرسال الفعلي للرسالة (يعتمد على الطريقة المختارة)
     */
    private function sendActualMessage($phone, $message, $logId) {
        $method = $this->settings['whatsapp_api_method'] ?? 'manual';
        
        switch ($method) {
            case 'manual':
                // الطريقة اليدوية - يتم عرض الرسائل في لوحة التحكم
                return [
                    'method' => 'manual',
                    'status' => 'pending',
                    'message' => 'يرجى إرسال الرسالة يدوياً من لوحة التحكم'
                ];
                
            case 'api':
                // استخدام WhatsApp Business API (يتطلب إعداد)
                return $this->sendViaAPI($phone, $message, $logId);
                
            case 'webhook':
                // استخدام Webhook خارجي
                return $this->sendViaWebhook($phone, $message, $logId);
                
            default:
                return [
                    'method' => 'unknown',
                    'status' => 'failed',
                    'message' => 'طريقة إرسال غير معروفة'
                ];
        }
    }
    
    /**
     * الإرسال عبر WhatsApp Business API
     */
    private function sendViaAPI($phone, $message, $logId) {
        // هنا يمكن إضافة كود الاتصال بـ WhatsApp Business API
        // مثال: استخدام مكتبة Twilio أو WhatsApp Cloud API
        
        // للتطوير المستقبلي
        return [
            'method' => 'api',
            'status' => 'pending',
            'message' => 'API غير مُعد بعد'
        ];
    }
    
    /**
     * الإرسال عبر Webhook
     */
    private function sendViaWebhook($phone, $message, $logId) {
        // هنا يمكن إضافة كود الاتصال بـ Webhook خارجي
        
        // للتطوير المستقبلي
        return [
            'method' => 'webhook',
            'status' => 'pending',
            'message' => 'Webhook غير مُعد بعد'
        ];
    }
    
    /**
     * استبدال المتغيرات في القالب
     */
    private function replaceVariables($template, $variables) {
        foreach ($variables as $key => $value) {
            $template = str_replace($key, $value, $template);
        }
        return $template;
    }
    
    /**
     * قالب الترحيب الافتراضي
     */
    private function getDefaultWelcomeTemplate() {
        return "مرحباً {name}!\n\n✅ تم استلام طلبك بنجاح\n📋 نوع الطلب: {request_type}\n🔢 رقم التتبع: {tracking_number}\n📅 التاريخ: {date}\n\n🔐 للدخول لحسابك الشخصي:\n👉 {magic_link}\n\n━━━━━━━━━━━━━━━━━━━\n💚 شكراً لثقتكم\n🏛️ بلدية تكريت - في خدمتكم";
    }
    
    /**
     * قالب تحديث الحالة الافتراضي
     */
    private function getDefaultStatusTemplate() {
        return "🏛️ بلدية تكريت\n\n📢 تحديث على طلبك\n\n🔢 {tracking_number}\n📋 {request_type}\n\n✅ الحالة الجديدة:\n{status}\n\n📝 التحديث:\n{update_text}\n\n👉 للتفاصيل:\n{magic_link}\n\n━━━━━━━━━━━━━━━━━━━";
    }
    
    /**
     * قالب الإنجاز الافتراضي
     */
    private function getDefaultCompletionTemplate() {
        return "🏛️ بلدية تكريت\n\n✅ طلبك جاهز!\n\n🔢 {tracking_number}\n📋 {request_type}\n\n📍 يرجى المرور على مكتب البلدية لاستلام:\n{request_title}\n\n🕐 أوقات الدوام:\nالإثنين - الجمعة\n8:00 ص - 2:00 م\n\n📞 للاستفسار: {municipality_phone}\n\n━━━━━━━━━━━━━━━━━━━\n💚 شكراً لثقتكم";
    }
    
    /**
     * الحصول على رسائل WhatsApp المعلقة (للإرسال اليدوي)
     */
    public function getPendingMessages($limit = 50) {
        $stmt = $this->db->prepare("
            SELECT wl.*, cr.tracking_number, cr.request_title
            FROM whatsapp_log wl
            LEFT JOIN citizen_requests cr ON wl.request_id = cr.id
            WHERE wl.status = 'pending'
            ORDER BY wl.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * تحديث حالة الرسالة
     */
    public function updateMessageStatus($logId, $status, $errorMessage = null) {
        $stmt = $this->db->prepare("
            UPDATE whatsapp_log 
            SET status = ?, 
                error_message = ?,
                sent_at = CASE WHEN ? = 'sent' THEN NOW() ELSE sent_at END,
                delivered_at = CASE WHEN ? = 'delivered' THEN NOW() ELSE delivered_at END,
                read_at = CASE WHEN ? = 'read' THEN NOW() ELSE read_at END
            WHERE id = ?
        ");
        
        return $stmt->execute([$status, $errorMessage, $status, $status, $status, $logId]);
    }
}

