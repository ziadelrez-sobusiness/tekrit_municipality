<?php
header('Content-Type: text/html; charset=utf-8');
echo "<h1>🚀 اختبار سريع لـ API المحدث</h1>";

// اختبار API الجديد
$api_url = 'http://localhost:8080/tekrit_municipality/modules/facilities_api.php?action=get_facilities';

echo "<p><strong>اختبار API:</strong> <a href='$api_url' target='_blank'>$api_url</a></p>";

$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'ignore_errors' => true
    ]
]);

$response = @file_get_contents($api_url, false, $context);

if ($response !== false) {
    $json = json_decode($response, true);
    
    if ($json) {
        echo "<h3>📊 نتيجة API:</h3>";
        echo "<div style='background: #f5f5f5; padding: 10px; border-radius: 5px; font-family: monospace; white-space: pre-wrap; max-height: 300px; overflow-y: auto;'>$response</div>";
        
        if ($json['success']) {
            echo "<p style='color: green; font-size: 18px; font-weight: bold;'>✅ API يعمل بنجاح!</p>";
            
            if (isset($json['facilities'])) {
                $count = count($json['facilities']);
                echo "<p><strong>عدد المرافق المسترجعة:</strong> $count</p>";
                
                if ($count > 0) {
                    echo "<h4>🏢 المرافق الموجودة:</h4>";
                    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                    echo "<tr style='background: #e3f2fd;'><th>الاسم</th><th>الفئة</th><th>خط الطول</th><th>خط العرض</th></tr>";
                    
                    foreach ($json['facilities'] as $facility) {
                        echo "<tr>";
                        echo "<td>{$facility['name_ar']}</td>";
                        echo "<td>{$facility['category_name_ar']}</td>";
                        echo "<td>{$facility['longitude']}</td>";
                        echo "<td>{$facility['latitude']}</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }
            }
        } else {
            echo "<p style='color: red; font-size: 18px; font-weight: bold;'>❌ خطأ في API!</p>";
            if (isset($json['error'])) {
                echo "<p><strong>رسالة الخطأ:</strong> {$json['error']}</p>";
            }
        }
    } else {
        echo "<p style='color: red;'>❌ خطأ في تحليل JSON:</p>";
        echo "<pre>$response</pre>";
    }
} else {
    echo "<p style='color: red;'>❌ فشل في الوصول لـ API</p>";
}

echo "<h3>🔗 اختبارات إضافية:</h3>";
echo "<div style='margin: 15px 0;'>";
echo "<a href='http://localhost:8080/tekrit_municipality/modules/facilities_api.php?action=get_categories' target='_blank' style='background: #4caf50; color: white; padding: 10px 15px; margin: 5px; text-decoration: none; border-radius: 5px; display: inline-block;'>📂 اختبار الفئات</a>";
echo "<a href='http://localhost:8080/tekrit_municipality/modules/facilities_api.php?action=get_statistics' target='_blank' style='background: #ff9800; color: white; padding: 10px 15px; margin: 5px; text-decoration: none; border-radius: 5px; display: inline-block;'>📊 اختبار الإحصائيات</a>";
echo "<a href='http://localhost:8080/tekrit_municipality/public/facilities-map.php' target='_blank' style='background: #2196f3; color: white; padding: 10px 15px; margin: 5px; text-decoration: none; border-radius: 5px; display: inline-block;'>🗺️ الخريطة الأصلية</a>";
echo "</div>";

echo "<h3>💡 الخطوات التالية:</h3>";
echo "<ol>";
echo "<li>إذا كان API يعمل لكن الخريطة لا تظهر البيانات، امسح cache المتصفح</li>";
echo "<li>أضف مرافق جديدة من <a href='modules/facilities_management.php'>واجهة الإدارة</a></li>";
echo "<li>تحقق من وجود أخطاء JavaScript في console المتصفح</li>";
echo "</ol>";
?> 