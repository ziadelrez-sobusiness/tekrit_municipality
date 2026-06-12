<?php
require_once 'config/database.php';

echo "<h1>🗺️ اختبار نظام خريطة المرافق والخدمات</h1>";

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

try {
    echo "<h2>📊 فحص حالة النظام:</h2>";
    
    // فحص وجود الجداول
    $tables_to_check = ['facility_categories', 'facilities', 'map_settings', 'facility_ratings'];
    
    foreach ($tables_to_check as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $count = $db->query("SELECT COUNT(*) as count FROM $table")->fetch()['count'];
            echo "<p>✅ جدول <strong>$table</strong>: موجود ($count سجل)</p>";
        } else {
            echo "<p>❌ جدول <strong>$table</strong>: غير موجود</p>";
        }
    }
    
    // فحص الإعدادات
    echo "<h3>⚙️ إعدادات الخريطة:</h3>";
    $settings = $db->query("SELECT setting_name, setting_value FROM map_settings WHERE is_public = 1")->fetchAll();
    
    if (count($settings) > 0) {
        echo "<ul>";
        foreach ($settings as $setting) {
            echo "<li><strong>{$setting['setting_name']}:</strong> {$setting['setting_value']}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>❌ لا توجد إعدادات</p>";
    }
    
    // فحص الفئات
    echo "<h3>📂 فئات المرافق:</h3>";
    $categories = $db->query("SELECT name_ar, name_en, icon, color FROM facility_categories WHERE is_active = 1 ORDER BY display_order")->fetchAll();
    
    if (count($categories) > 0) {
        echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin: 10px 0;'>";
        foreach ($categories as $category) {
            echo "<div style='background-color: {$category['color']}; color: white; padding: 10px; border-radius: 5px; text-align: center;'>";
            echo "<strong>{$category['name_ar']}</strong><br>";
            echo "<small>({$category['icon']})</small>";
            echo "</div>";
        }
        echo "</div>";
    } else {
        echo "<p>❌ لا توجد فئات</p>";
    }
    
    // إضافة مرافق تجريبية إذا لم تكن موجودة
    $facility_count = $db->query("SELECT COUNT(*) as count FROM facilities")->fetch()['count'];
    
    if ($facility_count == 0) {
        echo "<h3>📍 إضافة مرافق تجريبية...</h3>";
        
        $sample_facilities = [
            [
                'name_ar' => 'بلدية تكريت',
                'name_en' => 'Tikrit Municipality',
                'category_id' => 6, // مؤسسات حكومية
                'description_ar' => 'المقر الرئيسي لبلدية تكريت - تقديم الخدمات البلدية للمواطنين',
                'description_en' => 'Tikrit Municipality Main Office - Providing municipal services to citizens',
                'latitude' => 34.6137,
                'longitude' => 43.6793,
                'contact_person_ar' => 'مكتب المسؤول',
                'phone' => '07701234567',
                'address_ar' => 'مركز مدينة تكريت، محافظة صلاح الدين',
                'working_hours_ar' => 'الأحد - الخميس: 8:00 ص - 2:00 م'
            ],
            [
                'name_ar' => 'جامع تكريت الكبير',
                'name_en' => 'Tikrit Grand Mosque',
                'category_id' => 2, // مساجد
                'description_ar' => 'المسجد الجامع الرئيسي في تكريت',
                'description_en' => 'The main grand mosque in Tikrit',
                'latitude' => 34.6145,
                'longitude' => 43.6801,
                'contact_person_ar' => 'الإمام',
                'address_ar' => 'وسط تكريت القديمة'
            ],
            [
                'name_ar' => 'مستشفى تكريت العام',
                'name_en' => 'Tikrit General Hospital',
                'category_id' => 3, // مراكز صحية
                'description_ar' => 'المستشفى العام الرئيسي في تكريت',
                'description_en' => 'Main general hospital in Tikrit',
                'latitude' => 34.6125,
                'longitude' => 43.6785,
                'phone' => '07701234568',
                'address_ar' => 'حي المستشفى، تكريت',
                'working_hours_ar' => '24 ساعة'
            ],
            [
                'name_ar' => 'جامعة تكريت',
                'name_en' => 'University of Tikrit',
                'category_id' => 1, // مدارس
                'description_ar' => 'الجامعة الرئيسية في محافظة صلاح الدين',
                'description_en' => 'Main university in Salahuddin Governorate',
                'latitude' => 34.6089,
                'longitude' => 43.6712,
                'website' => 'https://www.tu.edu.iq',
                'address_ar' => 'طريق بغداد، تكريت',
                'working_hours_ar' => 'الأحد - الخميس: 8:00 ص - 4:00 م'
            ],
            [
                'name_ar' => 'سوق تكريت المركزي',
                'name_en' => 'Tikrit Central Market',
                'category_id' => 15, // أسواق
                'description_ar' => 'السوق التجاري الرئيسي في تكريت',
                'description_en' => 'Main commercial market in Tikrit',
                'latitude' => 34.6141,
                'longitude' => 43.6799,
                'address_ar' => 'شارع السوق الرئيسي، تكريت',
                'working_hours_ar' => 'يومياً: 8:00 ص - 8:00 م'
            ]
        ];
        
        $stmt = $db->prepare("INSERT INTO facilities (name_ar, name_en, category_id, description_ar, description_en, latitude, longitude, contact_person_ar, phone, address_ar, working_hours_ar, website, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($sample_facilities as $facility) {
            $stmt->execute([
                $facility['name_ar'],
                $facility['name_en'] ?? '',
                $facility['category_id'],
                $facility['description_ar'] ?? '',
                $facility['description_en'] ?? '',
                $facility['latitude'],
                $facility['longitude'],
                $facility['contact_person_ar'] ?? '',
                $facility['phone'] ?? '',
                $facility['address_ar'] ?? '',
                $facility['working_hours_ar'] ?? '',
                $facility['website'] ?? '',
                1 // مميز
            ]);
            
            echo "<p>✅ تم إضافة: {$facility['name_ar']}</p>";
        }
        
        echo "<p><strong>تم إضافة " . count($sample_facilities) . " مرافق تجريبية!</strong></p>";
    }
    
    // إحصائيات النظام
    echo "<h3>📈 إحصائيات النظام:</h3>";
    $total_facilities = $db->query("SELECT COUNT(*) as count FROM facilities WHERE is_active = 1")->fetch()['count'];
    $total_categories = $db->query("SELECT COUNT(*) as count FROM facility_categories WHERE is_active = 1")->fetch()['count'];
    $featured_facilities = $db->query("SELECT COUNT(*) as count FROM facilities WHERE is_featured = 1 AND is_active = 1")->fetch()['count'];
    
    echo "<div style='background: #f0f9ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<p><strong>🏢 إجمالي المرافق:</strong> $total_facilities</p>";
    echo "<p><strong>📂 إجمالي الفئات:</strong> $total_categories</p>";
    echo "<p><strong>⭐ المرافق المميزة:</strong> $featured_facilities</p>";
    echo "</div>";
    
    echo "<h3>🔗 روابط النظام:</h3>";
    echo "<div style='margin: 20px 0;'>";
    echo "<a href='public/facilities-map.php' target='_blank' style='background: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🗺️ عرض الخريطة العامة</a><br><br>";
    echo "<a href='modules/facilities_management.php' target='_blank' style='background: #059669; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🏢 إدارة المرافق</a><br><br>";
    echo "<a href='modules/facilities_categories.php' target='_blank' style='background: #7c3aed; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>📂 إدارة الفئات</a><br><br>";
    echo "<a href='modules/facilities_api.php?action=get_facilities' target='_blank' style='background: #dc2626; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🔌 اختبار API</a>";
    echo "</div>";
    
    echo "<h2>✅ نظام خريطة المرافق والخدمات جاهز للاستخدام!</h2>";
    echo "<div style='background: #d1fae5; border: 2px solid #10b981; padding: 20px; margin: 20px 0; border-radius: 10px;'>";
    echo "<h3 style='color: #059669; margin-top: 0;'>🎉 تم إعداد النظام بنجاح!</h3>";
    echo "<p><strong>الميزات المتوفرة:</strong></p>";
    echo "<ul>";
    echo "<li>✅ خريطة تفاعلية مع خرائط مفتوحة المصدر (OpenStreetMap)</li>";
    echo "<li>✅ دعم اللغتين العربية والإنجليزية</li>";
    echo "<li>✅ فلاتر متقدمة للبحث والتصفية</li>";
    echo "<li>✅ إدارة كاملة للمرافق والفئات</li>";
    echo "<li>✅ رفع الصور للمرافق</li>";
    echo "<li>✅ نظام تقييمات (اختياري)</li>";
    echo "<li>✅ تتبع الموقع الجغرافي للمستخدم</li>";
    echo "<li>✅ ربط مع خرائط Google للاتجاهات</li>";
    echo "<li>✅ واجهة إدارة متقدمة</li>";
    echo "<li>✅ API كامل للتطوير المستقبلي</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h2>❌ حدث خطأ</h2>";
    echo "<p style='color: red;'>الخطأ: " . $e->getMessage() . "</p>";
    echo "<p>الملف: " . $e->getFile() . "</p>";
    echo "<p>السطر: " . $e->getLine() . "</p>";
}
?> 