<?php
/**
 * إصلاح جدول telegram_log للسماح بـ NULL في telegram_chat_id
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "🔧 إصلاح جدول telegram_log...\n\n";
    
    // تعديل العمود للسماح بـ NULL
    $sql = "ALTER TABLE telegram_log MODIFY COLUMN telegram_chat_id VARCHAR(255) NULL";
    
    $db->exec($sql);
    
    echo "✅ تم إصلاح جدول telegram_log بنجاح!\n";
    echo "✅ الآن يمكن تسجيل رسائل Telegram حتى لو كان Chat ID فارغ\n\n";
    
    echo "📋 اختبار الجدول:\n";
    
    // اختبار إدراج رسالة بدون chat_id
    $testStmt = $db->prepare("
        INSERT INTO telegram_log (
            citizen_id, telegram_chat_id, request_id, 
            message_type, message, status, created_at
        ) VALUES (NULL, NULL, NULL, 'test', 'رسالة اختبار', 'pending', NOW())
    ");
    
    $testStmt->execute();
    $testId = $db->lastInsertId();
    
    echo "✅ تم إدراج رسالة اختبار برقم: $testId\n";
    
    // حذف الرسالة الاختبارية
    $db->exec("DELETE FROM telegram_log WHERE id = $testId");
    echo "✅ تم حذف الرسالة الاختبارية\n\n";
    
    echo "🎉 النظام جاهز الآن!\n";
    echo "📝 يمكنك تقديم طلب جديد والإشعارات ستعمل!\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "\n";
    echo "📋 تفاصيل الخطأ:\n";
    echo $e->getTraceAsString();
}
?>

