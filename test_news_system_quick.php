<?php
echo "🧪 اختبار سريع لنظام الأخبار\n\n";

require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->exec("SET NAMES utf8mb4");
    
    echo "✅ قاعدة البيانات متصلة\n";
    
    // فحص هيكل جدول الأخبار
    echo "📋 فحص جدول news_activities:\n";
    $columns = $db->query("DESCRIBE news_activities")->fetchAll();
    
    $has_gallery_images = false;
    foreach ($columns as $column) {
        if ($column['Field'] == 'gallery_images') {
            $has_gallery_images = true;
            break;
        }
    }
    
    if ($has_gallery_images) {
        echo "❌ العمود gallery_images ما زال موجود!\n";
        echo "⚠️ يجب حذفه يدوياً من قاعدة البيانات\n";
        
        // محاولة حذفه
        try {
            $db->exec("ALTER TABLE news_activities DROP COLUMN gallery_images");
            echo "✅ تم حذف العمود gallery_images\n";
        } catch (Exception $e) {
            echo "❌ فشل في حذف العمود: " . $e->getMessage() . "\n";
        }
    } else {
        echo "✅ العمود gallery_images غير موجود (صحيح)\n";
    }
    
    // فحص جدول الصور
    echo "\n🖼️ فحص جدول news_images:\n";
    try {
        $count = $db->query("SELECT COUNT(*) as total FROM news_images")->fetch()['total'];
        echo "✅ جدول news_images موجود ويحتوي على $count صورة\n";
    } catch (Exception $e) {
        echo "❌ مشكلة في جدول news_images: " . $e->getMessage() . "\n";
    }
    
    // اختبار إنشاء خبر تجريبي
    echo "\n📰 اختبار إنشاء خبر تجريبي:\n";
    try {
        $stmt = $db->prepare("INSERT INTO news_activities (title, content, news_type, publish_date, is_featured, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            "خبر تجريبي - اختبار النظام الجديد",
            "هذا خبر تجريبي لاختبار النظام الجديد للصور",
            "إعلام رسمي",
            date('Y-m-d'),
            0,
            1
        ]);
        
        $news_id = $db->lastInsertId();
        echo "✅ تم إنشاء خبر تجريبي برقم: $news_id\n";
        
        // حذف الخبر التجريبي
        $db->prepare("DELETE FROM news_activities WHERE id = ?")->execute([$news_id]);
        echo "✅ تم حذف الخبر التجريبي\n";
        
    } catch (Exception $e) {
        echo "❌ خطأ في إنشاء خبر تجريبي: " . $e->getMessage() . "\n";
    }
    
    echo "\n🎉 اختبار النظام مكتمل!\n";
    echo "💡 إذا كانت جميع الاختبارات ناجحة، فالنظام جاهز للاستخدام\n";
    
} catch (Exception $e) {
    echo "❌ خطأ عام: " . $e->getMessage() . "\n";
}
?> 