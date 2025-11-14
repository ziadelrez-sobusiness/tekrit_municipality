<?php
echo "تحقق من نظام الصور الجديد...\n";

require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // فحص الجداول
    echo "✅ قاعدة البيانات متصلة\n";
    
    $result = $db->query("SHOW TABLES LIKE 'news_images'");
    if ($result->rowCount() > 0) {
        echo "✅ جدول news_images موجود\n";
    } else {
        echo "❌ جدول news_images غير موجود\n";
    }
    
    $result = $db->query("SHOW TABLES LIKE 'news_image_settings'");
    if ($result->rowCount() > 0) {
        echo "✅ جدول news_image_settings موجود\n";
    } else {
        echo "❌ جدول news_image_settings غير موجود\n";
    }
    
    // فحص المجلد
    if (is_dir('uploads/news')) {
        echo "✅ مجلد uploads/news موجود\n";
        if (is_writable('uploads/news')) {
            echo "✅ المجلد قابل للكتابة\n";
        } else {
            echo "⚠️ المجلد غير قابل للكتابة\n";
        }
    } else {
        echo "❌ مجلد uploads/news غير موجود\n";
    }
    
    echo "\n🎉 النظام الجديد للصور جاهز!\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
?> 