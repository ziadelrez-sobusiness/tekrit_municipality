<?php
/**
 * فحص جدول request_updates والتحديثات للطلب
 */

$tracking_number = 'REQ-2025-35455';

try {
    $db = new PDO('mysql:host=localhost;dbname=tekrit_municipality;charset=utf8mb4', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1 style='font-family: Arial; color: #333;'>🔍 فحص التحديثات للطلب: $tracking_number</h1>";
    
    // 1. جلب معلومات الطلب
    echo "<h2>1️⃣ معلومات الطلب:</h2>";
    $stmt = $db->prepare("SELECT * FROM citizen_requests WHERE tracking_number = ?");
    $stmt->execute([$tracking_number]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($request) {
        echo "<div style='background: #e8f5e9; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>";
        echo "✅ الطلب موجود<br>";
        echo "📌 ID: " . $request['id'] . "<br>";
        echo "📌 رقم التتبع: " . $request['tracking_number'] . "<br>";
        echo "📌 الحالة: " . $request['status'] . "<br>";
        echo "📌 تاريخ الإنشاء: " . $request['created_at'] . "<br>";
        echo "</div>";
        
        $request_id = $request['id'];
        
        // 2. فحص وجود جدول request_updates
        echo "<h2>2️⃣ فحص جدول request_updates:</h2>";
        $tables = $db->query("SHOW TABLES LIKE 'request_updates'")->fetchAll();
        
        if (count($tables) > 0) {
            echo "<div style='background: #e8f5e9; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>";
            echo "✅ جدول request_updates موجود<br>";
            echo "</div>";
            
            // 3. عرض أعمدة الجدول
            echo "<h2>3️⃣ أعمدة جدول request_updates:</h2>";
            $columns = $db->query("SHOW COLUMNS FROM request_updates")->fetchAll(PDO::FETCH_ASSOC);
            echo "<div style='background: #fff3e0; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>";
            foreach ($columns as $col) {
                echo "📋 " . $col['Field'] . " (" . $col['Type'] . ")<br>";
            }
            echo "</div>";
            
            // 4. جلب التحديثات للطلب
            echo "<h2>4️⃣ التحديثات للطلب (request_id = $request_id):</h2>";
            $stmt = $db->prepare("SELECT * FROM request_updates WHERE request_id = ? ORDER BY created_at DESC");
            $stmt->execute([$request_id]);
            $updates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($updates) > 0) {
                echo "<div style='background: #e8f5e9; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>";
                echo "✅ عدد التحديثات: " . count($updates) . "<br><br>";
                
                foreach ($updates as $i => $update) {
                    echo "<div style='background: white; padding: 10px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 5px;'>";
                    echo "<strong>تحديث #" . ($i + 1) . ":</strong><br>";
                    echo "ID: " . $update['id'] . "<br>";
                    echo "العنوان: " . ($update['update_title'] ?? 'غير محدد') . "<br>";
                    echo "الوصف: " . ($update['update_description'] ?? 'غير محدد') . "<br>";
                    echo "الحالة القديمة: " . ($update['old_status'] ?? 'غير محدد') . "<br>";
                    echo "الحالة الجديدة: " . ($update['new_status'] ?? 'غير محدد') . "<br>";
                    echo "التاريخ: " . $update['created_at'] . "<br>";
                    echo "</div>";
                }
                echo "</div>";
            } else {
                echo "<div style='background: #ffebee; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>";
                echo "❌ لا توجد تحديثات لهذا الطلب<br>";
                echo "💡 السبب المحتمل: لم يتم إضافة أي تحديثات من قبل الموظفين<br>";
                echo "</div>";
            }
            
            // 5. عرض جميع التحديثات في الجدول (للمقارنة)
            echo "<h2>5️⃣ جميع التحديثات في الجدول (أول 10):</h2>";
            $all_updates = $db->query("SELECT * FROM request_updates ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($all_updates) > 0) {
                echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>";
                echo "📊 إجمالي التحديثات في الجدول: " . count($all_updates) . "<br><br>";
                
                echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
                echo "<tr style='background: #2196f3; color: white;'>";
                echo "<th>ID</th><th>Request ID</th><th>العنوان</th><th>التاريخ</th>";
                echo "</tr>";
                
                foreach ($all_updates as $upd) {
                    $highlight = ($upd['request_id'] == $request_id) ? "background: #ffeb3b;" : "";
                    echo "<tr style='$highlight'>";
                    echo "<td>" . $upd['id'] . "</td>";
                    echo "<td>" . $upd['request_id'] . "</td>";
                    echo "<td>" . ($upd['update_title'] ?? 'غير محدد') . "</td>";
                    echo "<td>" . $upd['created_at'] . "</td>";
                    echo "</tr>";
                }
                
                echo "</table>";
                echo "</div>";
            } else {
                echo "<div style='background: #ffebee; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>";
                echo "❌ الجدول فارغ تماماً - لا توجد أي تحديثات<br>";
                echo "</div>";
            }
            
        } else {
            echo "<div style='background: #ffebee; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>";
            echo "❌ جدول request_updates غير موجود!<br>";
            echo "💡 يجب إنشاء الجدول أولاً<br>";
            echo "</div>";
        }
        
    } else {
        echo "<div style='background: #ffebee; padding: 15px; border-radius: 8px;'>";
        echo "❌ الطلب غير موجود<br>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #ffebee; padding: 15px; border-radius: 8px;'>";
    echo "❌ خطأ: " . $e->getMessage();
    echo "</div>";
}
?>

