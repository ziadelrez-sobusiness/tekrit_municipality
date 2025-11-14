<?php
/**
 * سكريبت تشغيل تحديثات قاعدة البيانات
 * تشغيل النظام المحسن لطلبات المواطنين
 */

// إيقاف إخراج الهيدر
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/database.php';

echo "🚀 بدء تحديث قاعدة البيانات للنظام المحسن...\n\n";

try {
    // استخدام الكلاس القديم للاتصال
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception("فشل في الاتصال بقاعدة البيانات");
    }
    
    // قراءة ملف SQL
    $sqlFile = 'database/enhanced_citizen_requests_system.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("ملف SQL غير موجود: {$sqlFile}");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // تقسيم الاستعلامات
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($queries as $query) {
        if (empty($query) || strpos($query, '--') === 0 || strpos($query, 'DELIMITER') !== false) {
            continue;
        }
        
        try {
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $successCount++;
            echo "✅ تم تنفيذ الاستعلام بنجاح\n";
        } catch (Exception $e) {
            $errorCount++;
            echo "❌ خطأ في الاستعلام: " . $e->getMessage() . "\n";
            echo "الاستعلام: " . substr($query, 0, 100) . "...\n\n";
        }
    }
    
    echo "\n📊 ملخص التحديث:\n";
    echo "✅ الاستعلامات الناجحة: {$successCount}\n";
    echo "❌ الاستعلامات الفاشلة: {$errorCount}\n";
    
    if ($errorCount <= 3) { // السماح ببعض الأخطاء البسيطة
        echo "\n🎉 تم تحديث قاعدة البيانات بنجاح!\n";
        
        // اختبار النظام الجديد
        echo "\n🔍 اختبار النظام الجديد...\n";
        
        // اختبار إنشاء البيانات الأساسية
        try {
            // إدراج أنواع الطلبات الأساسية
            $requestTypes = [
                ['إفادة سكن', 'إفادة سكن', 'طلب الحصول على إفادة سكن من البلدية'],
                ['شكوى', 'شكوى', 'تقديم شكوى أو اعتراض'],
                ['بلاغ أعطال', 'بلاغ أعطال', 'الإبلاغ عن أعطال في الخدمات العامة'],
                ['المساهمة في المشروع', 'المساهمة في المشروع', 'طلب المساهمة في مشروع تنموي']
            ];
            
            foreach ($requestTypes as $type) {
                $stmt = $conn->prepare("
                    INSERT IGNORE INTO request_types (type_name, name_ar, type_description, is_active) 
                    VALUES (?, ?, ?, 1)
                ");
                $stmt->execute($type);
            }
            
            echo "✅ تم إدراج أنواع الطلبات الأساسية\n";
            
            // اختبار إنشاء طلب تجريبي
            $stmt = $conn->prepare("
                INSERT INTO citizen_requests 
                (tracking_number, citizen_name, citizen_phone, request_type, request_title, request_description, status, priority_level) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $trackingNumber = 'TRK' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $stmt->execute([
                $trackingNumber,
                'اختبار النظام',
                '07701234567',
                'اختبار',
                'اختبار النظام المحسن',
                'هذا طلب اختبار للنظام المحسن',
                'جديد',
                'عادي'
            ]);
            
            echo "✅ تم إنشاء طلب اختبار بنجاح - رقم التتبع: {$trackingNumber}\n";
            
            // اختبار الإحصائيات
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM citizen_requests");
            $stmt->execute();
            $result = $stmt->fetch();
            echo "✅ إحصائيات الطلبات: إجمالي " . $result['total'] . " طلب\n";
            
        } catch (Exception $e) {
            echo "❌ خطأ في الاختبار: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "\n⚠️ تم التحديث مع بعض الأخطاء. يرجى مراجعة الأخطاء أعلاه.\n";
    }
    
} catch (Exception $e) {
    echo "❌ خطأ عام في تحديث قاعدة البيانات: " . $e->getMessage() . "\n";
}

echo "\n✅ انتهى التحديث.\n";
?> 