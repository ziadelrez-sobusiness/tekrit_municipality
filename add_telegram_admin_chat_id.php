<?php
/**
 * سكريبت لإضافة telegram_admin_chat_id إلى قاعدة البيانات
 * 
 * استخدم هذا السكريبت لإضافة Chat ID الإداري
 * 
 * كيفية الحصول على Chat ID:
 * 1. افتح Telegram
 * 2. ابحث عن @userinfobot
 * 3. ابدأ محادثة معه
 * 4. سيرسل لك Chat ID الخاص بك
 * 5. أو أضف البوت إلى قناة/مجموعة واحصل على Chat ID الخاص بها
 */

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "=== إضافة telegram_admin_chat_id ===\n\n";

// إذا تم تمرير Chat ID كمعامل سطر الأوامر
$chatId = $argv[1] ?? null;

if (!$chatId) {
    echo "الرجاء إدخال Chat ID الإداري:\n";
    echo "مثال: php add_telegram_admin_chat_id.php YOUR_CHAT_ID\n\n";
    echo "أو أدخل Chat ID الآن:\n";
    $chatId = trim(fgets(STDIN));
}

if (empty($chatId)) {
    echo "❌ Chat ID فارغ!\n";
    exit(1);
}

try {
    // إضافة أو تحديث الإعداد
    $stmt = $db->prepare("
        INSERT INTO website_settings (setting_key, setting_value, setting_description) 
        VALUES ('telegram_admin_chat_id', ?, 'Chat ID الإداري لإرسال إشعارات الطلبات الجديدة')
        ON DUPLICATE KEY UPDATE 
            setting_value = ?,
            setting_description = 'Chat ID الإداري لإرسال إشعارات الطلبات الجديدة'
    ");
    
    $stmt->execute([$chatId, $chatId]);
    
    echo "✅ تم إضافة/تحديث telegram_admin_chat_id بنجاح!\n";
    echo "Chat ID: $chatId\n\n";
    
    // التحقق من الإعدادات الأخرى
    echo "=== فحص إعدادات Telegram ===\n\n";
    
    $stmt = $db->query("
        SELECT setting_key, setting_value 
        FROM website_settings 
        WHERE setting_key LIKE 'telegram%'
        ORDER BY setting_key
    ");
    
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($settings as $setting) {
        $key = $setting['setting_key'];
        $value = $setting['setting_value'];
        
        if ($key == 'telegram_bot_token') {
            $value = substr($value, 0, 10) . '...' . substr($value, -10);
        }
        
        echo "$key: $value\n";
    }
    
    echo "\n✅ جاهز! الآن سيتم إرسال الإشعارات الإدارية إلى Chat ID: $chatId\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}



