<?php
echo "<h1>🔍 تشخيص مشكلة الخريطة العامة</h1>";

require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->exec("SET NAMES utf8mb4");
    
    echo "<h2>📊 فحص قاعدة البيانات:</h2>";
    
    // إحصائيات المرافق
    $stmt = $db->query("SELECT COUNT(*) as total FROM facilities");
    $total = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as active FROM facilities WHERE is_active = 1");
    $active = $stmt->fetch()['active'];
    
    $stmt = $db->query("SELECT COUNT(*) as with_coords FROM facilities WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND latitude != 0 AND longitude != 0 AND is_active = 1");
    $with_coords = $stmt->fetch()['with_coords'];
    
    echo "<div style='background: #f0f9ff; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<p><strong>إجمالي المرافق:</strong> $total</p>";
    echo "<p><strong>المرافق النشطة:</strong> $active</p>";
    echo "<p><strong>المرافق مع إحداثيات صحيحة ونشطة:</strong> $with_coords</p>";
    echo "</div>";
    
    if ($with_coords == 0) {
        echo "<div style='background: #fef2f2; border: 1px solid #fca5a5; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<h3 style='color: #dc2626; margin: 0 0 10px 0;'>⚠️ مشكلة: لا توجد مرافق نشطة مع إحداثيات صحيحة!</h3>";
        echo "<p>هذا هو السبب في عدم ظهور المرافق على الخريطة.</p>";
        echo "</div>";
        
        // فحص المرافق الموجودة
        echo "<h3>🔍 فحص المرافق الموجودة:</h3>";
        $stmt = $db->query("SELECT id, name_ar, latitude, longitude, is_active FROM facilities ORDER BY id ASC LIMIT 10");
        $facilities = $stmt->fetchAll();
        
        if ($facilities) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
            echo "<tr style='background: #f3f4f6;'><th style='padding: 8px;'>ID</th><th style='padding: 8px;'>الاسم</th><th style='padding: 8px;'>خط العرض</th><th style='padding: 8px;'>خط الطول</th><th style='padding: 8px;'>الحالة</th><th style='padding: 8px;'>المشكلة</th></tr>";
            
            foreach ($facilities as $facility) {
                $issues = [];
                if (!$facility['is_active']) $issues[] = "غير نشط";
                if (!$facility['latitude'] || $facility['latitude'] == 0) $issues[] = "خط العرض مفقود";
                if (!$facility['longitude'] || $facility['longitude'] == 0) $issues[] = "خط الطول مفقود";
                
                $status_color = $facility['is_active'] ? '#16a34a' : '#dc2626';
                $issues_text = empty($issues) ? '✅ جيد' : '❌ ' . implode(', ', $issues);
                
                echo "<tr>";
                echo "<td style='padding: 8px;'>{$facility['id']}</td>";
                echo "<td style='padding: 8px;'>" . htmlspecialchars($facility['name_ar']) . "</td>";
                echo "<td style='padding: 8px;'>{$facility['latitude']}</td>";
                echo "<td style='padding: 8px;'>{$facility['longitude']}</td>";
                echo "<td style='padding: 8px; color: $status_color;'>" . ($facility['is_active'] ? 'نشط' : 'معطل') . "</td>";
                echo "<td style='padding: 8px;'>$issues_text</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    
    echo "<h2>🔌 اختبار API:</h2>";
    
    // اختبار استدعاء API محلياً
    $api_url = 'http://localhost:8080/tekrit_municipality/modules/facilities_api.php?action=get_facilities';
    
    echo "<p>اختبار API على: <a href='$api_url' target='_blank'>$api_url</a></p>";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'ignore_errors' => true
        ]
    ]);
    
    $api_response = @file_get_contents($api_url, false, $context);
    
    if ($api_response !== false) {
        $api_data = json_decode($api_response, true);
        
        if ($api_data && isset($api_data['success'])) {
            if ($api_data['success']) {
                $facilities_count = isset($api_data['facilities']) ? count($api_data['facilities']) : 0;
                echo "<div style='background: #f0fdf4; border: 1px solid #16a34a; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
                echo "<h3 style='color: #16a34a; margin: 0 0 10px 0;'>✅ API يعمل بنجاح!</h3>";
                echo "<p><strong>عدد المرافق المسترجعة:</strong> $facilities_count</p>";
                echo "</div>";
                
                if ($facilities_count > 0) {
                    echo "<h3>📋 أول مرفق من API:</h3>";
                    $first_facility = $api_data['facilities'][0];
                    echo "<pre style='background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;'>";
                    echo "الاسم: " . htmlspecialchars($first_facility['name_ar']) . "\n";
                    echo "خط العرض: " . $first_facility['latitude'] . "\n";
                    echo "خط الطول: " . $first_facility['longitude'] . "\n";
                    echo "الفئة: " . htmlspecialchars($first_facility['category_name_ar'] ?? 'غير محدد') . "\n";
                    echo "نشط: " . ($first_facility['id'] ? 'نعم' : 'لا') . "\n";
                    echo "</pre>";
                } else {
                    echo "<div style='background: #fef3c7; border: 1px solid #f59e0b; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
                    echo "<h3 style='color: #92400e; margin: 0 0 10px 0;'>⚠️ API يعمل لكن لا يرجع مرافق!</h3>";
                    echo "<p>هذا يعني أن المشكلة في البيانات وليس في API نفسه.</p>";
                    echo "</div>";
                }
            } else {
                echo "<div style='background: #fef2f2; border: 1px solid #f87171; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
                echo "<h3 style='color: #dc2626; margin: 0 0 10px 0;'>❌ API يرجع خطأ!</h3>";
                echo "<p><strong>رسالة الخطأ:</strong> " . htmlspecialchars($api_data['error'] ?? 'غير محدد') . "</p>";
                echo "</div>";
            }
        } else {
            echo "<div style='background: #fef2f2; border: 1px solid #f87171; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
            echo "<h3 style='color: #dc2626; margin: 0 0 10px 0;'>❌ API يرجع بيانات غير صحيحة!</h3>";
            echo "<pre style='background: #f8fafc; padding: 10px; border-radius: 5px; overflow: auto; max-height: 200px;'>" . htmlspecialchars($api_response) . "</pre>";
            echo "</div>";
        }
    } else {
        echo "<div style='background: #fef2f2; border: 1px solid #f87171; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<h3 style='color: #dc2626; margin: 0 0 10px 0;'>❌ فشل في الوصول إلى API!</h3>";
        echo "<p>تحقق من أن الخادم يعمل وأن مسار API صحيح.</p>";
        echo "</div>";
    }
    
    echo "<h2>🌐 اختبار الخريطة العامة:</h2>";
    
    // فحص ملف الخريطة
    $map_file = 'public/facilities-map.php';
    if (file_exists($map_file)) {
        echo "<div style='background: #f0fdf4; border: 1px solid #16a34a; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<h3 style='color: #16a34a; margin: 0 0 10px 0;'>✅ ملف الخريطة موجود</h3>";
        echo "<p>المسار: $map_file</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #fef2f2; border: 1px solid #f87171; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<h3 style='color: #dc2626; margin: 0 0 10px 0;'>❌ ملف الخريطة مفقود!</h3>";
        echo "<p>المسار: $map_file</p>";
        echo "</div>";
    }
    
    echo "<h2>🛠️ الحلول المقترحة:</h2>";
    
    if ($with_coords == 0) {
        echo "<div style='background: #fffbeb; border: 1px solid #f59e0b; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<h3 style='color: #92400e; margin: 0 0 10px 0;'>💡 إضافة مرافق تجريبية:</h3>";
        echo "<p>سأضيف بعض المرافق التجريبية لتكريت بإحداثيات صحيحة.</p>";
        
        // إضافة مرافق تجريبية
        $sample_facilities = [
            ['سوق تكريت المركزي', 'Tikrit Central Market', 34.6137, 43.6793, 1],
            ['مستشفى تكريت العام', 'Tikrit General Hospital', 34.6089, 43.6751, 3],
            ['جامعة تكريت', 'University of Tikrit', 34.6247, 43.6832, 2],
            ['محطة وقود الوحدة', 'Al-Wahda Gas Station', 34.6156, 43.6804, 8],
            ['مسجد الحكيم', 'Al-Hakeem Mosque', 34.6123, 43.6778, 7]
        ];
        
        try {
            foreach ($sample_facilities as $facility) {
                $stmt = $db->prepare("INSERT IGNORE INTO facilities (name_ar, name_en, latitude, longitude, category_id, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
                $stmt->execute($facility);
            }
            echo "<p style='color: #16a34a; font-weight: bold;'>✅ تم إضافة المرافق التجريبية!</p>";
        } catch (Exception $e) {
            echo "<p style='color: #dc2626;'>❌ خطأ في إضافة المرافق: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        echo "</div>";
    }
    
    echo "<h2>🔗 روابط الاختبار:</h2>";
    echo "<div style='margin: 20px 0;'>";
    echo "<a href='$api_url' target='_blank' style='background: #16a34a; color: white; padding: 12px 20px; text-decoration: none; border-radius: 8px; margin: 5px; display: inline-block;'>🔌 اختبار API</a>";
    echo "<a href='public/facilities-map.php' target='_blank' style='background: #2563eb; color: white; padding: 12px 20px; text-decoration: none; border-radius: 8px; margin: 5px; display: inline-block;'>🗺️ الخريطة العامة</a>";
    echo "<a href='modules/facilities_management.php' target='_blank' style='background: #f59e0b; color: white; padding: 12px 20px; text-decoration: none; border-radius: 8px; margin: 5px; display: inline-block;'>⚙️ إدارة المرافق</a>";
    echo "<a href='check_facilities_data.php' target='_blank' style='background: #7c2d12; color: white; padding: 12px 20px; text-decoration: none; border-radius: 8px; margin: 5px; display: inline-block;'>🔍 فحص البيانات</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #fef2f2; border: 1px solid #f87171; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<h3 style='color: #dc2626; margin: 0 0 10px 0;'>❌ خطأ في الاتصال بقاعدة البيانات:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?> 