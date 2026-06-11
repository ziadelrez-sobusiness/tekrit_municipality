<?php
/**
 * سكريبت إصلاح مشكلة عمود request_status
 * تحويل جميع المراجع من request_status إلى status
 */

require_once 'config/database.php';

echo "🔧 بدء إصلاح مشكلة عمود request_status...\n\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception("فشل في الاتصال بقاعدة البيانات");
    }
    
    // التحقق من وجود العمود القديم
    $checkColumn = $conn->query("SHOW COLUMNS FROM citizen_requests LIKE 'request_status'");
    $hasRequestStatus = $checkColumn->rowCount() > 0;
    
    $checkStatusColumn = $conn->query("SHOW COLUMNS FROM citizen_requests LIKE 'status'");
    $hasStatus = $checkStatusColumn->rowCount() > 0;
    
    echo "📊 حالة الأعمدة:\n";
    echo "- request_status: " . ($hasRequestStatus ? "موجود" : "غير موجود") . "\n";
    echo "- status: " . ($hasStatus ? "موجود" : "غير موجود") . "\n\n";
    
    if ($hasRequestStatus && !$hasStatus) {
        // إعادة تسمية العمود القديم
        echo "🔄 إعادة تسمية العمود من request_status إلى status...\n";
        $conn->exec("ALTER TABLE citizen_requests CHANGE request_status status VARCHAR(50) NOT NULL DEFAULT 'جديد'");
        echo "✅ تم تغيير اسم العمود بنجاح\n\n";
        
    } elseif ($hasRequestStatus && $hasStatus) {
        // نسخ البيانات من العمود القديم إلى الجديد
        echo "📋 نسخ البيانات من request_status إلى status...\n";
        $conn->exec("UPDATE citizen_requests SET status = request_status WHERE status IS NULL OR status = ''");
        echo "✅ تم نسخ البيانات بنجاح\n";
        
        // حذف العمود القديم
        echo "🗑️ حذف العمود القديم request_status...\n";
        $conn->exec("ALTER TABLE citizen_requests DROP COLUMN request_status");
        echo "✅ تم حذف العمود القديم بنجاح\n\n";
        
    } elseif (!$hasRequestStatus && !$hasStatus) {
        // إضافة العمود الجديد
        echo "➕ إضافة عمود status جديد...\n";
        $conn->exec("ALTER TABLE citizen_requests ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'جديد' AFTER request_type");
        echo "✅ تم إضافة العمود الجديد بنجاح\n\n";
        
    } else {
        echo "✅ العمود status موجود بالفعل\n\n";
    }
    
    // التحقق من البيانات
    echo "🔍 التحقق من البيانات...\n";
    $statusCount = $conn->query("SELECT COUNT(*) as count FROM citizen_requests WHERE status IS NOT NULL AND status != ''")->fetch();
    echo "✅ عدد الطلبات التي لها حالة: " . $statusCount['count'] . "\n";
    
    // تحديث الحالات الفارغة
    $emptyStatus = $conn->query("SELECT COUNT(*) as count FROM citizen_requests WHERE status IS NULL OR status = ''")->fetch();
    if ($emptyStatus['count'] > 0) {
        echo "🔄 تحديث الحالات الفارغة...\n";
        $conn->exec("UPDATE citizen_requests SET status = 'جديد' WHERE status IS NULL OR status = ''");
        echo "✅ تم تحديث " . $emptyStatus['count'] . " طلب\n";
    }
    
    // إضافة فهرس للعمود الجديد
    echo "📊 إضافة فهرس للعمود...\n";
    try {
        $conn->exec("CREATE INDEX idx_status ON citizen_requests (status)");
        echo "✅ تم إضافة الفهرس بنجاح\n";
    } catch (Exception $e) {
        echo "ℹ️ الفهرس موجود بالفعل\n";
    }
    
    // اختبار النظام
    echo "\n🧪 اختبار النظام...\n";
    $testQuery = $conn->query("SELECT COUNT(*) as count FROM citizen_requests WHERE status = 'جديد'");
    $newCount = $testQuery->fetch();
    echo "✅ عدد الطلبات الجديدة: " . $newCount['count'] . "\n";
    
    $testQuery2 = $conn->query("SELECT COUNT(*) as count FROM citizen_requests WHERE status = 'مكتمل'");
    $completedCount = $testQuery2->fetch();
    echo "✅ عدد الطلبات المكتملة: " . $completedCount['count'] . "\n";
    
    echo "\n🎉 تم إصلاح مشكلة العمود بنجاح!\n";
    echo "✅ يمكنك الآن استخدام النظام بدون أخطاء\n";
    
} catch (Exception $e) {
    echo "❌ خطأ في الإصلاح: " . $e->getMessage() . "\n";
}

echo "\n✅ انتهى الإصلاح.\n";
?> 