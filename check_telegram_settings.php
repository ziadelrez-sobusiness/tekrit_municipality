<?php
/**
 * فحص إعدادات Telegram Bot
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "=== إعدادات Telegram Bot ===\n\n";
    
    $stmt = $db->query("SELECT setting_key, setting_value FROM website_settings WHERE setting_key LIKE 'telegram%'");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($settings)) {
        echo "❌ لا توجد إعدادات Telegram في قاعدة البيانات!\n\n";
        echo "يجب إضافة الإعدادات من:\n";
        echo "http://localhost:8080/tekrit_municipality/modules/telegram_settings.php\n";
    } else {
        foreach ($settings as $setting) {
            $key = $setting['setting_key'];
            $value = $setting['setting_value'];
            
            if ($key == 'telegram_bot_token') {
                // إخفاء جزء من التوكن
                $value = substr($value, 0, 10) . '...' . substr($value, -10);
            }
            
            echo "$key: $value\n";
        }
    }
    
    echo "\n=== فحص Webhook ===\n\n";
    
    $stmt = $db->query("SELECT setting_value FROM website_settings WHERE setting_key = 'telegram_webhook_url'");
    $webhookUrl = $stmt->fetchColumn();
    
    if ($webhookUrl) {
        echo "Webhook URL: $webhookUrl\n";
        
        // التحقق من أن الملف موجود
        $webhookFile = __DIR__ . '/public/telegram_webhook.php';
        if (file_exists($webhookFile)) {
            echo "✅ ملف Webhook موجود: $webhookFile\n";
        } else {
            echo "❌ ملف Webhook غير موجود: $webhookFile\n";
        }
    } else {
        echo "❌ Webhook URL غير محدد!\n";
    }
    
    echo "\n=== فحص حساب المواطن ===\n\n";
    
    $accessCode = 'TKT-121683E2';
    $stmt = $db->prepare("SELECT * FROM citizens_accounts WHERE permanent_access_code = ?");
    $stmt->execute([$accessCode]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($account) {
        echo "✅ الحساب موجود:\n";
        echo "  ID: " . $account['id'] . "\n";
        echo "  Phone: " . $account['phone'] . "\n";
        echo "  Access Code: " . $account['permanent_access_code'] . "\n";
        echo "  Telegram Chat ID: " . ($account['telegram_chat_id'] ?? '❌ غير مربوط') . "\n";
        echo "  Telegram Username: " . ($account['telegram_username'] ?? 'غير محدد') . "\n";
    } else {
        echo "❌ الحساب غير موجود!\n";
    }
    
    echo "\n=== الرسائل المعلقة ===\n\n";
    
    if ($account) {
        $stmt = $db->prepare("SELECT * FROM telegram_log WHERE citizen_id = ? AND status = 'pending' ORDER BY created_at DESC");
        $stmt->execute([$account['id']]);
        $pendingMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($pendingMessages)) {
            echo "✅ لا توجد رسائل معلقة\n";
        } else {
            echo "📬 عدد الرسائل المعلقة: " . count($pendingMessages) . "\n\n";
            foreach ($pendingMessages as $msg) {
                echo "  - ID: " . $msg['id'] . "\n";
                echo "    Type: " . $msg['message_type'] . "\n";
                echo "    Date: " . $msg['created_at'] . "\n";
                echo "    Message: " . substr($msg['message_text'], 0, 50) . "...\n\n";
            }
        }
    }
    
    echo "\n=== اختبار Webhook يدوياً ===\n\n";
    echo "لاختبار الـ Webhook يدوياً، قم بتشغيل:\n";
    echo "http://localhost:8080/tekrit_municipality/test_telegram_webhook.php?code=$accessCode\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}

