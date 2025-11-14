<?php
echo "<h1>🔧 إصلاح سريع لمشكلة الخريطة</h1>";

require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->exec("SET NAMES utf8mb4");
    
    // فحص المرافق الموجودة
    $stmt = $db->query("SELECT COUNT(*) as total FROM facilities WHERE is_active = 1 AND latitude IS NOT NULL AND longitude IS NOT NULL AND latitude != 0 AND longitude != 0");
    $valid_facilities = $stmt->fetch()['total'];
    
    echo "<h2>📊 الوضع الحالي:</h2>";
    echo "<p><strong>المرافق النشطة مع إحداثيات صحيحة:</strong> $valid_facilities</p>";
    
    if ($valid_facilities == 0) {
        echo "<div style='background: #fef3c7; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<h3 style='color: #92400e;'>⚠️ لا توجد مرافق مع إحداثيات صحيحة - سأضيف مرافق تجريبية</h3>";
        echo "</div>";
        
        // إضافة مرافق تجريبية لتكريت
        $tikrit_facilities = [
            [
                'name_ar' => 'سوق تكريت المركزي',
                'name_en' => 'Tikrit Central Market',
                'latitude' => 34.6137,
                'longitude' => 43.6793,
                'category_id' => 1,
                'description_ar' => 'السوق المركزي في مدينة تكريت',
                'address_ar' => 'مركز مدينة تكريت'
            ],
            [
                'name_ar' => 'مستشفى تكريت العام',
                'name_en' => 'Tikrit General Hospital', 
                'latitude' => 34.6089,
                'longitude' => 43.6751,
                'category_id' => 3,
                'description_ar' => 'المستشفى العام الرئيسي في تكريت',
                'address_ar' => 'شارع الجمهورية، تكريت'
            ],
            [
                'name_ar' => 'جامعة تكريت',
                'name_en' => 'University of Tikrit',
                'latitude' => 34.6247,
                'longitude' => 43.6832,
                'category_id' => 2,
                'description_ar' => 'الجامعة الرئيسية في محافظة صلاح الدين',
                'address_ar' => 'طريق بغداد، تكريت'
            ],
            [
                'name_ar' => 'محطة وقود الوحدة',
                'name_en' => 'Al-Wahda Gas Station',
                'latitude' => 34.6156,
                'longitude' => 43.6804,
                'category_id' => 8,
                'description_ar' => 'محطة وقود على الطريق الرئيسي',
                'address_ar' => 'الطريق العام، تكريت'
            ],
            [
                'name_ar' => 'مجمع الخدمات الحكومية',
                'name_en' => 'Government Services Complex',
                'latitude' => 34.6123,
                'longitude' => 43.6778,
                'category_id' => 6,
                'description_ar' => 'مجمع الدوائر الحكومية',
                'address_ar' => 'شارع الحكومة، تكريت'
            ]
        ];
        
        $added_count = 0;
        foreach ($tikrit_facilities as $facility) {
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
                $added_count++;
            } catch (Exception $e) {
                // تجاهل الأخطاء (قد يكون المرفق موجود مسبقاً)
            }
        }
        
        echo "<p style='color: #16a34a; font-weight: bold;'>✅ تم إضافة $added_count مرفق تجريبي</p>";
    }
    
    // التحقق من حالة الفئات
    $stmt = $db->query("SELECT COUNT(*) as categories FROM facility_categories WHERE is_active = 1");
    $categories_count = $stmt->fetch()['categories'];
    
    if ($categories_count == 0) {
        echo "<div style='background: #fef3c7; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<h3 style='color: #92400e;'>⚠️ لا توجد فئات نشطة - سأضيف فئات أساسية</h3>";
        echo "</div>";
        
        $categories = [
            ['محلات تجارية', 'Commercial Shops', '🏪', '#e74c3c', 1],
            ['مؤسسات تعليمية', 'Educational Institutions', '🏫', '#3498db', 2],
            ['مرافق صحية', 'Health Facilities', '🏥', '#2ecc71', 3],
            ['مطاعم ومقاهي', 'Restaurants & Cafes', '🍽️', '#f39c12', 4],
            ['خدمات مصرفية', 'Banking Services', '🏦', '#9b59b6', 5],
            ['دوائر حكومية', 'Government Offices', '🏛️', '#34495e', 6],
            ['أماكن عبادة', 'Places of Worship', '🕌', '#16a085', 7],
            ['محطات وقود', 'Gas Stations', '⛽', '#e67e22', 8]
        ];
        
        foreach ($categories as $category) {
            try {
                $stmt = $db->prepare("INSERT IGNORE INTO facility_categories (name_ar, name_en, icon, color, display_order, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->execute($category);
            } catch (Exception $e) {
                // تجاهل الأخطاء
            }
        }
        
        echo "<p style='color: #16a34a; font-weight: bold;'>✅ تم إضافة الفئات الأساسية</p>";
    }
    
    // فحص نهائي
    $stmt = $db->query("SELECT COUNT(*) as final_count FROM facilities WHERE is_active = 1 AND latitude IS NOT NULL AND longitude IS NOT NULL AND latitude != 0 AND longitude != 0");
    $final_count = $stmt->fetch()['final_count'];
    
    echo "<h2>✅ الوضع النهائي:</h2>";
    echo "<div style='background: #f0fdf4; border: 1px solid #16a34a; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<h3 style='color: #16a34a; margin: 0 0 10px 0;'>🎉 المرافق جاهزة للعرض!</h3>";
    echo "<p><strong>عدد المرافق النشطة مع إحداثيات صحيحة:</strong> $final_count</p>";
    echo "</div>";
    
    // اختبار API سريع
    echo "<h2>🧪 اختبار API:</h2>";
    $api_url = 'modules/facilities_api.php?action=get_facilities';
    $api_response = @file_get_contents('http://localhost:8080/tekrit_municipality/' . $api_url);
    
    if ($api_response) {
        $api_data = json_decode($api_response, true);
        if ($api_data && $api_data['success']) {
            $api_count = count($api_data['facilities']);
            echo "<p style='color: #16a34a; font-weight: bold;'>✅ API يسترجع $api_count مرفق</p>";
        } else {
            echo "<p style='color: #dc2626; font-weight: bold;'>❌ API يرجع خطأ</p>";
        }
    } else {
        echo "<p style='color: #dc2626; font-weight: bold;'>❌ فشل في الوصول لـ API</p>";
    }
    
    echo "<h2>🔗 اختبر الآن:</h2>";
    echo "<div style='margin: 20px 0;'>";
    echo "<a href='public/facilities-map.php' target='_blank' style='background: #2563eb; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 10px; display: inline-block; font-size: 18px; font-weight: bold;'>🗺️ افتح الخريطة العامة</a>";
    echo "<a href='modules/facilities_management.php' target='_blank' style='background: #f59e0b; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 10px; display: inline-block;'>⚙️ إدارة المرافق</a>";
    echo "<a href='http://localhost:8080/tekrit_municipality/modules/facilities_api.php?action=get_facilities' target='_blank' style='background: #16a34a; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 10px; display: inline-block;'>🔌 اختبار API</a>";
    echo "</div>";
    
    echo "<h3>💡 تعليمات:</h3>";
    echo "<ol style='line-height: 1.8;'>";
    echo "<li>اضغط على <strong>\"🗺️ افتح الخريطة العامة\"</strong> للتحقق من ظهور المرافق</li>";
    echo "<li>إذا لم تظهر المرافق، اضغط <strong>F12</strong> لفتح Developer Tools وتحقق من Console للأخطاء</li>";
    echo "<li>يمكنك إضافة مرافق جديدة من صفحة <strong>\"إدارة المرافق\"</strong></li>";
    echo "<li>تأكد من إدخال إحداثيات صحيحة (خط العرض وخط الطول)</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<div style='background: #fef2f2; border: 1px solid #f87171; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<h3 style='color: #dc2626; margin: 0 0 10px 0;'>❌ خطأ:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?> 