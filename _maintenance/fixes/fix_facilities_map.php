<?php
header('Content-Type: text/html; charset=utf-8');
echo "<h1>🔧 إصلاح مشكلة خريطة المرافق</h1>";

echo "<h2>📊 التشخيص الأولي:</h2>";

// 1. فحص قاعدة البيانات
try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    $db->exec("SET NAMES utf8mb4");
    
    echo "<p style='color: green;'>✅ الاتصال بقاعدة البيانات: نجح</p>";
    
    // عد المرافق
    $stmt = $db->query("SELECT COUNT(*) as count FROM facilities WHERE is_active = 1");
    $count = $stmt->fetch()['count'];
    echo "<p>📊 عدد المرافق النشطة: <strong>$count</strong></p>";
    
    // عد الفئات
    $stmt = $db->query("SELECT COUNT(*) as count FROM facility_categories WHERE is_active = 1");
    $cat_count = $stmt->fetch()['count'];
    echo "<p>📂 عدد الفئات النشطة: <strong>$cat_count</strong></p>";
    
    if ($count == 0) {
        echo "<div style='background: #ffebee; padding: 15px; border: 1px solid #f44336; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3 style='color: #d32f2f;'>⚠️ المشكلة الرئيسية:</h3>";
        echo "<p>لا توجد مرافق في قاعدة البيانات أو جميع المرافق غير نشطة.</p>";
        echo "<p><strong>الحل:</strong> أضف مرافق جديدة من <a href='modules/facilities_management.php'>واجهة إدارة المرافق</a></p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطأ في قاعدة البيانات: " . $e->getMessage() . "</p>";
}

// 2. فحص API
echo "<h3>🔗 فحص API:</h3>";

$api_endpoints = [
    'get_facilities' => 'http://localhost:8080/tekrit_municipality/modules/facilities_api.php?action=get_facilities',
    'get_categories' => 'http://localhost:8080/tekrit_municipality/modules/facilities_api.php?action=get_categories'
];

foreach ($api_endpoints as $action => $url) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    
    if ($response !== false) {
        $json = json_decode($response, true);
        if ($json && isset($json['success']) && $json['success']) {
            if ($action == 'get_facilities') {
                $facility_count = isset($json['facilities']) ? count($json['facilities']) : 0;
                echo "<p style='color: green;'>✅ API $action: يعمل ($facility_count مرفق)</p>";
            } else {
                echo "<p style='color: green;'>✅ API $action: يعمل</p>";
            }
        } else {
            echo "<p style='color: orange;'>⚠️ API $action: يستجيب لكن بدون بيانات</p>";
            if (isset($json['error'])) {
                echo "<p style='color: red;'>خطأ: {$json['error']}</p>";
            }
        }
    } else {
        echo "<p style='color: red;'>❌ API $action: لا يستجيب</p>";
        echo "<p>URL: <a href='$url' target='_blank'>$url</a></p>";
    }
}

// 3. إنشاء بيانات تجريبية إذا لم تكن موجودة
if ($count == 0) {
    echo "<h3>➕ إضافة بيانات تجريبية:</h3>";
    
    try {
        // إضافة فئة تجريبية إذا لم تكن موجودة
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM facility_categories WHERE id = 1");
        $stmt->execute();
        $cat_exists = $stmt->fetch()['count'] > 0;
        
        if (!$cat_exists) {
            $db->exec("INSERT INTO facility_categories (id, name_ar, name_en, icon, color, is_active) VALUES (1, 'مؤسسات حكومية', 'Government Institutions', '🏛️', '#3498db', 1)");
            echo "<p style='color: green;'>✅ تم إضافة فئة تجريبية</p>";
        }
        
        // إضافة مرفق تجريبي
        $stmt = $db->prepare("
            INSERT INTO facilities 
            (name_ar, name_en, category_id, description_ar, description_en, latitude, longitude, address_ar, address_en, phone, is_active, is_featured)
            VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)
        ");
        
        $sample_facility = [
            'بلدية تكريت - مرفق تجريبي',
            'Tikrit Municipality - Test Facility',
            1,
            'هذا مرفق تجريبي لاختبار النظام',
            'This is a test facility for system testing',
            34.6137,
            43.6793,
            'مركز مدينة تكريت',
            'Tikrit City Center',
            '+964-123-456789'
        ];
        
        $stmt->execute($sample_facility);
        echo "<p style='color: green;'>✅ تم إضافة مرفق تجريبي</p>";
        
        // إعادة فحص العدد
        $stmt = $db->query("SELECT COUNT(*) as count FROM facilities WHERE is_active = 1");
        $new_count = $stmt->fetch()['count'];
        echo "<p>📊 العدد الجديد للمرافق: <strong>$new_count</strong></p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ خطأ في إضافة البيانات التجريبية: " . $e->getMessage() . "</p>";
    }
}

// 4. إنشاء ملف إعدادات محدث للخريطة
echo "<h3>🗺️ تحديث إعدادات الخريطة:</h3>";

try {
    // تحديث إعدادات الخريطة
    $map_settings = [
        ['setting_key' => 'map_center_lat', 'setting_value' => '34.6137'],
        ['setting_key' => 'map_center_lng', 'setting_value' => '43.6793'],
        ['setting_key' => 'map_zoom_level', 'setting_value' => '13'],
        ['setting_key' => 'enable_user_location', 'setting_value' => '1'],
        ['setting_key' => 'enable_clustering', 'setting_value' => '1'],
        ['setting_key' => 'cache_duration', 'setting_value' => '300'] // 5 دقائق
    ];
    
    foreach ($map_settings as $setting) {
        $stmt = $db->prepare("
            INSERT INTO map_settings (setting_key, setting_value) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->execute([$setting['setting_key'], $setting['setting_value']]);
    }
    
    echo "<p style='color: green;'>✅ تم تحديث إعدادات الخريطة</p>";
    
} catch (Exception $e) {
    echo "<p style='color: orange;'>⚠️ خطأ في تحديث الإعدادات: " . $e->getMessage() . "</p>";
}

// 5. تنظيف الـ cache إذا كان موجوداً
echo "<h3>🧹 تنظيف الـ Cache:</h3>";

// مسح ملفات cache JavaScript إذا كانت موجودة
$cache_files = ['public/assets/js/facilities-cache.js', 'cache/facilities.json'];
$cleaned = 0;

foreach ($cache_files as $file) {
    if (file_exists($file)) {
        unlink($file);
        $cleaned++;
        echo "<p style='color: green;'>✅ تم حذف: $file</p>";
    }
}

if ($cleaned == 0) {
    echo "<p style='color: blue;'>ℹ️ لا توجد ملفات cache للحذف</p>";
}

// 6. إنشاء ملف اختبار مبسط للخريطة
echo "<h3>🧪 إنشاء صفحة اختبار:</h3>";

$test_map_content = '
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار خريطة المرافق</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body>
    <h1>🧪 اختبار خريطة المرافق</h1>
    <div id="map" style="height: 400px; border: 2px solid #ddd; margin: 20px 0;"></div>
    <div id="status"></div>
    
    <script>
        // إنشاء الخريطة
        const map = L.map("map").setView([34.6137, 43.6793], 13);
        
        // إضافة طبقة الخريطة
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            attribution: "© OpenStreetMap contributors"
        }).addTo(map);
        
        // جلب البيانات من API
        fetch("modules/facilities_api.php?action=get_facilities")
            .then(response => response.json())
            .then(data => {
                document.getElementById("status").innerHTML = "<h3>📊 نتيجة API:</h3><pre>" + JSON.stringify(data, null, 2) + "</pre>";
                
                if (data.success && data.facilities) {
                    data.facilities.forEach(facility => {
                        const marker = L.marker([facility.latitude, facility.longitude]).addTo(map);
                        marker.bindPopup(`
                            <strong>${facility.name_ar}</strong><br>
                            ${facility.description_ar || "لا يوجد وصف"}<br>
                            الفئة: ${facility.category_name_ar}
                        `);
                    });
                    
                    if (data.facilities.length > 0) {
                        document.getElementById("status").innerHTML += `<p style="color: green;">✅ تم عرض ${data.facilities.length} مرفق على الخريطة</p>`;
                    }
                } else {
                    document.getElementById("status").innerHTML += `<p style="color: red;">❌ فشل في جلب البيانات: ${data.error || "خطأ غير معروف"}</p>`;
                }
            })
            .catch(error => {
                document.getElementById("status").innerHTML += `<p style="color: red;">❌ خطأ في الشبكة: ${error.message}</p>`;
            });
    </script>
</body>
</html>';

file_put_contents('test_map_simple.html', $test_map_content);
echo "<p style='color: green;'>✅ تم إنشاء صفحة اختبار: <a href='test_map_simple.html' target='_blank'>test_map_simple.html</a></p>";

// النتيجة النهائية
echo "<h2>🎯 الخلاصة والحلول:</h2>";
echo "<div style='background: #e8f5e8; padding: 20px; border: 2px solid #4caf50; border-radius: 10px;'>";

echo "<h3>✅ الخطوات المكتملة:</h3>";
echo "<ul>";
echo "<li>✅ فحص قاعدة البيانات والاتصال</li>";
echo "<li>✅ اختبار API endpoints</li>";
echo "<li>✅ إضافة بيانات تجريبية إذا لزم الأمر</li>";
echo "<li>✅ تحديث إعدادات الخريطة</li>";
echo "<li>✅ تنظيف الـ cache</li>";
echo "<li>✅ إنشاء صفحة اختبار مبسطة</li>";
echo "</ul>";

echo "<h3>🔗 روابط للاختبار:</h3>";
echo "<div style='margin: 15px 0;'>";
echo "<a href='test_map_simple.html' target='_blank' style='background: #2196f3; color: white; padding: 10px 20px; margin: 5px; text-decoration: none; border-radius: 5px; display: inline-block;'>🧪 خريطة الاختبار</a>";
echo "<a href='public/facilities-map.php' target='_blank' style='background: #4caf50; color: white; padding: 10px 20px; margin: 5px; text-decoration: none; border-radius: 5px; display: inline-block;'>🗺️ الخريطة الأصلية</a>";
echo "<a href='modules/facilities_management.php' target='_blank' style='background: #ff9800; color: white; padding: 10px 20px; margin: 5px; text-decoration: none; border-radius: 5px; display: inline-block;'>🏢 إدارة المرافق</a>";
echo "</div>";

echo "<h3>📋 خطوات إضافية إذا استمرت المشكلة:</h3>";
echo "<ol>";
echo "<li>🔄 امسح cache المتصفح (Ctrl+F5)</li>";
echo "<li>🌐 تحقق من وجود أخطاء JavaScript في Console المتصفح</li>";
echo "<li>📡 تأكد من أن الخادم يعمل على المنفذ 8080</li>";
echo "<li>🗄️ أضف مرافق جديدة من واجهة الإدارة</li>";
echo "<li>⚙️ تحقق من إعدادات الخريطة</li>";
echo "</ol>";

echo "</div>";

echo "<p style='text-align: center; margin: 20px 0; font-size: 18px; font-weight: bold; color: #2e7d32;'>🎉 تم الانتهاء من عملية الإصلاح!</p>";
?> 