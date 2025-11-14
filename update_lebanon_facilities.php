<?php
echo "<h1>🇱🇧 تحديث إحداثيات المرافق للبنان</h1>";

require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->exec("SET NAMES utf8mb4");
    
    // إحداثيات مناطق مختلفة في لبنان
    $lebanon_locations = [
        // بيروت
        ['name' => 'مركز بيروت التجاري', 'lat' => 33.8938, 'lng' => 35.5018, 'area' => 'بيروت'],
        ['name' => 'مستشفى الجامعة الأمريكية', 'lat' => 33.8958, 'lng' => 35.4762, 'area' => 'بيروت'],
        ['name' => 'جامعة بيروت العربية', 'lat' => 33.8755, 'lng' => 35.5093, 'area' => 'بيروت'],
        
        // طرابلس
        ['name' => 'سوق طرابلس', 'lat' => 34.4333, 'lng' => 35.8333, 'area' => 'طرابلس'],
        ['name' => 'مستشفى طرابلس الحكومي', 'lat' => 34.4267, 'lng' => 35.8378, 'area' => 'طرابلس'],
        
        // صيدا  
        ['name' => 'القلعة البحرية - صيدا', 'lat' => 33.5563, 'lng' => 35.3731, 'area' => 'صيدا'],
        
        // جونيه
        ['name' => 'خليج جونيه', 'lat' => 33.9808, 'lng' => 35.6178, 'area' => 'جونيه'],
        
        // زحلة
        ['name' => 'مركز زحلة', 'lat' => 33.8467, 'lng' => 35.9019, 'area' => 'زحلة']
    ];
    
    echo "<h2>📊 الوضع الحالي:</h2>";
    
    // فحص المرافق الموجودة
    $stmt = $db->query("SELECT id, name_ar, latitude, longitude FROM facilities ORDER BY id");
    $existing_facilities = $stmt->fetchAll();
    
    if (empty($existing_facilities)) {
        echo "<div style='background: #fef3c7; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<h3 style='color: #92400e;'>⚠️ لا توجد مرافق - سأقوم بإنشاء مرافق تجريبية للبنان</h3>";
        echo "</div>";
        
        // إنشاء مرافق تجريبية للبنان
        $lebanon_facilities = [
            [
                'name_ar' => 'مول سيتي سنتر بيروت',
                'name_en' => 'City Centre Beirut Mall',
                'latitude' => 33.8938,
                'longitude' => 35.5018,
                'category_id' => 1,
                'description_ar' => 'مجمع تجاري كبير في قلب بيروت',
                'address_ar' => 'شارع الحمرا، بيروت'
            ],
            [
                'name_ar' => 'مستشفى الجامعة الأمريكية',
                'name_en' => 'American University Hospital',
                'latitude' => 33.8958,
                'longitude' => 35.4762,
                'category_id' => 3,
                'description_ar' => 'أحد أهم المستشفيات في لبنان',
                'address_ar' => 'رأس بيروت، بيروت'
            ],
            [
                'name_ar' => 'جامعة بيروت العربية',
                'name_en' => 'Beirut Arab University',
                'latitude' => 33.8755,
                'longitude' => 35.5093,
                'category_id' => 2,
                'description_ar' => 'جامعة رائدة في الشرق الأوسط',
                'address_ar' => 'الطريق الجديدة، بيروت'
            ],
            [
                'name_ar' => 'مطعم الفيروز',
                'name_en' => 'Al Fayrouz Restaurant',
                'latitude' => 33.8918,
                'longitude' => 35.5045,
                'category_id' => 4,
                'description_ar' => 'مطعم لبناني أصيل',
                'address_ar' => 'الأشرفية، بيروت'
            ],
            [
                'name_ar' => 'بنك لبنان والمهجر',
                'name_en' => 'Bank of Lebanon and the Arab World',
                'latitude' => 33.8889,
                'longitude' => 35.4974,
                'category_id' => 5,
                'description_ar' => 'أحد البنوك الرئيسية في لبنان',
                'address_ar' => 'شارع الحمرا، بيروت'
            ]
        ];
        
        foreach ($lebanon_facilities as $facility) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO facilities 
                    (name_ar, name_en, latitude, longitude, category_id, description_ar, address_ar, is_active, created_at, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), 1)
                ");
                $stmt->execute([
                    $facility['name_ar'],
                    $facility['name_en'],
                    $facility['latitude'],
                    $facility['longitude'],
                    $facility['category_id'],
                    $facility['description_ar'],
                    $facility['address_ar']
                ]);
            } catch (Exception $e) {
                // تجاهل الأخطاء
            }
        }
        
        echo "<p style='color: #16a34a; font-weight: bold;'>✅ تم إنشاء " . count($lebanon_facilities) . " مرفق في لبنان</p>";
        
    } else {
        echo "<div style='background: #f0f9ff; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<h3 style='color: #1e40af;'>📋 المرافق الموجودة:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f3f4f6;'><th style='padding: 8px;'>ID</th><th style='padding: 8px;'>الاسم</th><th style='padding: 8px;'>خط العرض</th><th style='padding: 8px;'>خط الطول</th><th style='padding: 8px;'>المنطقة المقدرة</th></tr>";
        
        foreach ($existing_facilities as $facility) {
            $lat = $facility['latitude'];
            $lng = $facility['longitude'];
            
            // تقدير المنطقة حسب الإحداثيات
            $estimated_area = 'غير محدد';
            if ($lat >= 33.8 && $lat <= 34.0 && $lng >= 35.4 && $lng <= 35.6) {
                $estimated_area = '🇱🇧 بيروت، لبنان';
            } elseif ($lat >= 34.4 && $lat <= 34.5 && $lng >= 43.6 && $lng <= 43.7) {
                $estimated_area = '🇮🇶 تكريت، العراق';
            } elseif ($lat >= 34.4 && $lat <= 34.5 && $lng >= 35.8 && $lng <= 35.9) {
                $estimated_area = '🇱🇧 طرابلس، لبنان';
            }
            
            echo "<tr>";
            echo "<td style='padding: 8px;'>{$facility['id']}</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($facility['name_ar']) . "</td>";
            echo "<td style='padding: 8px;'>$lat</td>";
            echo "<td style='padding: 8px;'>$lng</td>";
            echo "<td style='padding: 8px;'>$estimated_area</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
        
        // خيارات التحديث
        echo "<h3>🔄 خيارات التحديث:</h3>";
        echo "<div style='margin: 10px 0;'>";
        
        echo "<form method='POST' style='margin: 10px 0;'>";
        echo "<input type='hidden' name='action' value='update_to_lebanon'>";
        echo "<button type='submit' style='background: #2563eb; color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; margin: 5px;'>🇱🇧 تحديث جميع المرافق لإحداثيات لبنان</button>";
        echo "</form>";
        
        echo "<form method='POST' style='margin: 10px 0;'>";
        echo "<input type='hidden' name='action' value='add_lebanon_facilities'>";
        echo "<button type='submit' style='background: #16a34a; color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; margin: 5px;'>➕ إضافة مرافق جديدة في لبنان</button>";
        echo "</form>";
        
        echo "</div>";
    }
    
    // معالجة الطلبات
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] == 'update_to_lebanon') {
            echo "<h3>🔄 تحديث المرافق الموجودة:</h3>";
            
            $updated_count = 0;
            foreach ($existing_facilities as $index => $facility) {
                if ($index < count($lebanon_locations)) {
                    $location = $lebanon_locations[$index];
                    try {
                        $stmt = $db->prepare("UPDATE facilities SET latitude = ?, longitude = ?, address_ar = ? WHERE id = ?");
                        $stmt->execute([
                            $location['lat'],
                            $location['lng'],
                            $location['area'] . ', لبنان',
                            $facility['id']
                        ]);
                        $updated_count++;
                        echo "<p style='color: #16a34a;'>✅ تم تحديث: {$facility['name_ar']} → {$location['area']}</p>";
                    } catch (Exception $e) {
                        echo "<p style='color: #dc2626;'>❌ خطأ في تحديث: {$facility['name_ar']}</p>";
                    }
                }
            }
            
            echo "<div style='background: #f0fdf4; border: 1px solid #16a34a; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
            echo "<h3 style='color: #16a34a; margin: 0 0 10px 0;'>✅ تم التحديث بنجاح!</h3>";
            echo "<p>تم تحديث $updated_count مرفق بإحداثيات لبنان.</p>";
            echo "</div>";
        }
        
        if ($_POST['action'] == 'add_lebanon_facilities') {
            echo "<h3>➕ إضافة مرافق جديدة:</h3>";
            // يمكن إضافة المزيد من المرافق هنا
        }
    }
    
    echo "<h2>🔗 اختبر الخريطة الآن:</h2>";
    echo "<div style='margin: 20px 0;'>";
    echo "<a href='public/facilities-map.php' target='_blank' style='background: #2563eb; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 10px; display: inline-block; font-size: 18px; font-weight: bold;'>🗺️ افتح الخريطة</a>";
    echo "</div>";
    
    echo "<h3>📍 إحداثيات مناطق لبنان الرئيسية:</h3>";
    echo "<div style='background: #f8fafc; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<ul style='margin: 0; padding-right: 20px; line-height: 1.8;'>";
    echo "<li><strong>بيروت:</strong> 33.8938, 35.5018</li>";
    echo "<li><strong>طرابلس:</strong> 34.4333, 35.8333</li>";
    echo "<li><strong>صيدا:</strong> 33.5563, 35.3731</li>";
    echo "<li><strong>جونيه:</strong> 33.9808, 35.6178</li>";
    echo "<li><strong>زحلة:</strong> 33.8467, 35.9019</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #fef2f2; border: 1px solid #f87171; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<h3 style='color: #dc2626; margin: 0 0 10px 0;'>❌ خطأ في الاتصال بقاعدة البيانات:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?> 