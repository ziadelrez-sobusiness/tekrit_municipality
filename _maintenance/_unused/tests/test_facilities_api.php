<?php
header('Content-Type: text/html; charset=utf-8');
echo "<h1>🧪 اختبار API المرافق</h1>";

// اختبار الاتصال بقاعدة البيانات
try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    $db->exec("SET NAMES utf8mb4");
    
    echo "<h2>✅ تم الاتصال بقاعدة البيانات بنجاح</h2>";
    
    // عرض المرافق من قاعدة البيانات مباشرة
    echo "<h3>🗄️ المرافق الموجودة في قاعدة البيانات:</h3>";
    
    $stmt = $db->query("
        SELECT f.*, fc.name_ar as category_name_ar, fc.name_en as category_name_en, fc.icon, fc.color
        FROM facilities f 
        LEFT JOIN facility_categories fc ON f.category_id = fc.id 
        WHERE f.is_active = 1
        ORDER BY f.created_at DESC
    ");
    
    $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>عدد المرافق:</strong> " . count($facilities) . "</p>";
    
    if (count($facilities) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f5f5f5;'>";
        echo "<th>ID</th><th>الاسم</th><th>الفئة</th><th>خط الطول</th><th>خط العرض</th><th>النشاط</th><th>تاريخ الإضافة</th>";
        echo "</tr>";
        
        foreach ($facilities as $facility) {
            echo "<tr>";
            echo "<td>{$facility['id']}</td>";
            echo "<td>{$facility['name_ar']}</td>";
            echo "<td>{$facility['category_name_ar']}</td>";
            echo "<td>{$facility['longitude']}</td>";
            echo "<td>{$facility['latitude']}</td>";
            echo "<td>" . ($facility['is_active'] ? 'نشط' : 'غير نشط') . "</td>";
            echo "<td>{$facility['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>❌ لا توجد مرافق في قاعدة البيانات</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطأ في قاعدة البيانات: " . $e->getMessage() . "</p>";
}

echo "<h3>🔗 اختبار API:</h3>";

// اختبار API مباشرة
$api_url = 'http://localhost:8080/tekrit_municipality/modules/facilities_api.php?action=get_facilities';

echo "<p><strong>رابط API:</strong> <a href='$api_url' target='_blank'>$api_url</a></p>";

// محاولة استدعاء API
$context = stream_context_create([
    'http' => [
        'timeout' => 5
    ]
]);

$api_response = @file_get_contents($api_url, false, $context);

if ($api_response !== false) {
    echo "<h4>✅ استجابة API:</h4>";
    echo "<textarea style='width: 100%; height: 200px; font-family: monospace;'>$api_response</textarea>";
    
    $json_data = json_decode($api_response, true);
    if ($json_data) {
        echo "<h4>📊 تحليل البيانات:</h4>";
        echo "<p><strong>حالة الاستجابة:</strong> " . ($json_data['success'] ? 'نجح' : 'فشل') . "</p>";
        
        if (isset($json_data['data'])) {
            echo "<p><strong>عدد المرافق في API:</strong> " . count($json_data['data']) . "</p>";
        }
        
        if (isset($json_data['message'])) {
            echo "<p><strong>رسالة:</strong> {$json_data['message']}</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ خطأ في تحليل JSON</p>";
    }
} else {
    echo "<p style='color: red;'>❌ فشل في الوصول للـ API</p>";
    echo "<p>تحقق من أن الخادم يعمل على http://localhost:8080</p>";
}

echo "<h3>🗺️ اختبار صفحة الخريطة:</h3>";
$map_url = 'http://localhost:8080/tekrit_municipality/public/facilities-map.php';
echo "<p><strong>رابط الخريطة:</strong> <a href='$map_url' target='_blank'>$map_url</a></p>";

echo "<h3>🔧 الحلول المقترحة:</h3>";
echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>";
echo "<h4>إذا كانت المشكلة في عدم ظهور البيانات:</h4>";
echo "<ol>";
echo "<li>✅ تحقق من أن API يعمل ويجلب البيانات</li>";
echo "<li>🔄 امسح cache المتصفح</li>";
echo "<li>🌐 تحقق من أن JavaScript يعمل في المتصفح</li>";
echo "<li>📡 تحقق من console المتصفح للأخطاء</li>";
echo "<li>🗄️ تأكد من وجود بيانات في قاعدة البيانات</li>";
echo "</ol>";
echo "</div>";

echo "<div style='margin: 20px 0; text-align: center;'>";
echo "<a href='modules/facilities_management.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🏢 إدارة المرافق</a>";
echo "<a href='public/facilities-map.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🗺️ عرض الخريطة</a>";
echo "<a href='$api_url' target='_blank' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🔗 اختبار API</a>";
echo "</div>";
?> 