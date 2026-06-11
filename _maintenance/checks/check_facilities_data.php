<?php
echo "<h1>🔍 فحص بيانات المرافق في قاعدة البيانات</h1>";

require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->exec("SET NAMES utf8mb4");
    
    echo "<h2>📊 إحصائيات المرافق:</h2>";
    
    // عدد المرافق الإجمالي
    $stmt = $db->query("SELECT COUNT(*) as total FROM facilities");
    $total = $stmt->fetch()['total'];
    echo "<p><strong>إجمالي المرافق:</strong> $total</p>";
    
    // المرافق النشطة
    $stmt = $db->query("SELECT COUNT(*) as active FROM facilities WHERE is_active = 1");
    $active = $stmt->fetch()['active'];
    echo "<p><strong>المرافق النشطة:</strong> $active</p>";
    
    // المرافق مع إحداثيات صحيحة
    $stmt = $db->query("SELECT COUNT(*) as valid_coords FROM facilities WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND latitude != 0 AND longitude != 0");
    $valid_coords = $stmt->fetch()['valid_coords'];
    echo "<p><strong>المرافق مع إحداثيات صحيحة:</strong> $valid_coords</p>";
    
    echo "<h2>🗃️ آخر 5 مرافق:</h2>";
    $stmt = $db->query("SELECT id, name_ar, latitude, longitude, is_active, created_at FROM facilities ORDER BY id DESC LIMIT 5");
    $recent = $stmt->fetchAll();
    
    if ($recent) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>الاسم</th><th>خط العرض</th><th>خط الطول</th><th>نشط</th><th>تاريخ الإنشاء</th></tr>";
        foreach ($recent as $facility) {
            $status = $facility['is_active'] ? '✅ نشط' : '❌ معطل';
            echo "<tr>";
            echo "<td>{$facility['id']}</td>";
            echo "<td>" . htmlspecialchars($facility['name_ar']) . "</td>";
            echo "<td>{$facility['latitude']}</td>";
            echo "<td>{$facility['longitude']}</td>";
            echo "<td>$status</td>";
            echo "<td>{$facility['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>❌ لا توجد مرافق في قاعدة البيانات!</p>";
    }
    
    echo "<h2>🧪 اختبار API:</h2>";
    
    // اختبار API محلياً
    $api_url = 'http://localhost:8080/tekrit_municipality/modules/facilities_api.php?action=get_facilities';
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);
    
    $response = @file_get_contents($api_url, false, $context);
    
    if ($response !== false) {
        $json = json_decode($response, true);
        
        if ($json && isset($json['success'])) {
            if ($json['success']) {
                $count = isset($json['facilities']) ? count($json['facilities']) : 0;
                echo "<p style='color: green;'>✅ API يعمل ويسترجع $count مرفق</p>";
                
                if ($count > 0) {
                    echo "<h3>📋 أول مرفق من API:</h3>";
                    $first = $json['facilities'][0];
                    echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>";
                    echo "الاسم: " . htmlspecialchars($first['name_ar']) . "\n";
                    echo "خط العرض: " . $first['latitude'] . "\n";
                    echo "خط الطول: " . $first['longitude'] . "\n";
                    echo "الفئة: " . htmlspecialchars($first['category_name_ar'] ?? 'غير محدد') . "\n";
                    echo "</pre>";
                }
            } else {
                echo "<p style='color: red;'>❌ API يرجع خطأ: " . htmlspecialchars($json['error'] ?? 'غير محدد') . "</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ API يرجع بيانات غير صحيحة</p>";
            echo "<pre>$response</pre>";
        }
    } else {
        echo "<p style='color: red;'>❌ فشل في الوصول إلى API</p>";
    }
    
    echo "<h2>🔗 روابط الاختبار:</h2>";
    echo "<a href='$api_url' target='_blank' style='background: #4caf50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>🔗 اختبار API</a>";
    echo "<a href='public/facilities-map.php' target='_blank' style='background: #2196f3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>🗺️ الخريطة العامة</a>";
    echo "<a href='modules/facilities_management.php' target='_blank' style='background: #ff9800; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>⚙️ إدارة المرافق</a>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-size: 18px;'>❌ خطأ في الاتصال بقاعدة البيانات:</p>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?> 