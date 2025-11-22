<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<html dir='rtl'><head><meta charset='UTF-8'><title>فحص المرافق</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5}";
echo ".box{background:white;padding:15px;margin:10px 0;border-radius:5px;box-shadow:0 2px 5px rgba(0,0,0,0.1)}";
echo ".success{color:green;font-weight:bold}.error{color:red;font-weight:bold}</style></head><body>";

echo "<h1>🗺️ فحص نظام المرافق</h1>";

// 1. فحص جدول facilities
echo "<div class='box'>";
echo "<h2>1️⃣ فحص جدول المرافق (facilities)</h2>";
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM facilities");
    $total = $stmt->fetch()['total'];

    echo "<p><strong>عدد المرافق:</strong> ";
    if ($total > 0) {
        echo "<span class='success'>{$total} مرفق ✅</span>";
    } else {
        echo "<span class='error'>0 - الجدول فارغ! ❌</span>";
    }
    echo "</p>";

    if ($total > 0) {
        echo "<h3>أول 5 مرافق:</h3>";
        $stmt = $db->query("SELECT id, name_ar, latitude, longitude, category_id FROM facilities LIMIT 5");
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
        echo "<tr><th>ID</th><th>الاسم</th><th>خط العرض</th><th>خط الطول</th><th>الفئة</th></tr>";
        while ($row = $stmt->fetch()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['name_ar']}</td>";
            echo "<td>{$row['latitude']}</td>";
            echo "<td>{$row['longitude']}</td>";
            echo "<td>{$row['category_id']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p class='error'>خطأ: " . $e->getMessage() . "</p>";
}
echo "</div>";

// 2. فحص جدول facility_categories
echo "<div class='box'>";
echo "<h2>2️⃣ فحص فئات المرافق (facility_categories)</h2>";
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM facility_categories");
    $total = $stmt->fetch()['total'];

    echo "<p><strong>عدد الفئات:</strong> ";
    if ($total > 0) {
        echo "<span class='success'>{$total} فئة ✅</span>";
    } else {
        echo "<span class='error'>0 - الجدول فارغ! ❌</span>";
    }
    echo "</p>";

    if ($total > 0) {
        $stmt = $db->query("SELECT id, name_ar, color, icon FROM facility_categories WHERE is_active = 1");
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
        echo "<tr><th>ID</th><th>الاسم</th><th>اللون</th><th>الأيقونة</th></tr>";
        while ($row = $stmt->fetch()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['name_ar']}</td>";
            echo "<td><span style='background:{$row['color']};color:white;padding:2px 8px;border-radius:3px'>{$row['color']}</span></td>";
            echo "<td>{$row['icon']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p class='error'>خطأ: " . $e->getMessage() . "</p>";
}
echo "</div>";

// 3. فحص جدول map_settings
echo "<div class='box'>";
echo "<h2>3️⃣ فحص إعدادات الخريطة (map_settings)</h2>";
try {
    $stmt = $db->query("SELECT * FROM map_settings WHERE is_public = 1");
    $settings = $stmt->fetchAll();

    if (count($settings) > 0) {
        echo "<p class='success'>✅ تم العثور على " . count($settings) . " إعداد</p>";
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
        echo "<tr><th>الاسم</th><th>القيمة</th></tr>";
        foreach ($settings as $row) {
            echo "<tr>";
            echo "<td>{$row['setting_name']}</td>";
            echo "<td>{$row['setting_value']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>❌ لا توجد إعدادات للخريطة</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>خطأ: " . $e->getMessage() . "</p>";
}
echo "</div>";

// 4. اختبار facilities_api.php
echo "<div class='box'>";
echo "<h2>4️⃣ اختبار API المرافق</h2>";
try {
    $url = "http://localhost:8080/tekrit_municipality/modules/facilities_api.php?action=get_facilities";
    echo "<p>محاولة الاتصال بـ: <code>{$url}</code></p>";

    $response = @file_get_contents($url);

    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['success'])) {
            if ($data['success']) {
                echo "<p class='success'>✅ API يعمل! عدد المرافق المعادة: " . count($data['facilities']) . "</p>";
            } else {
                echo "<p class='error'>❌ API يعمل لكن مع خطأ: " . ($data['error'] ?? 'غير معروف') . "</p>";
            }
        } else {
            echo "<p class='error'>❌ الاستجابة ليست JSON صحيح</p>";
            echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
        }
    } else {
        echo "<p class='error'>❌ فشل الاتصال بـ API</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>خطأ: " . $e->getMessage() . "</p>";
}
echo "</div>";

// التوصيات
echo "<div class='box' style='background:#fffbeb;border-right:4px solid #f59e0b'>";
echo "<h2>📋 التوصيات</h2>";

$stmt = $db->query("SELECT COUNT(*) as total FROM facilities");
$facilities_count = $stmt->fetch()['total'];

if ($facilities_count == 0) {
    echo "<p class='error'><strong>⚠️ لا توجد مرافق في قاعدة البيانات!</strong></p>";
    echo "<p>لإضافة مرافق:</p>";
    echo "<ol>";
    echo "<li>افتح لوحة التحكم: <a href='http://localhost:8080/tekrit_municipality/comprehensive_dashboard.php'>Dashboard</a></li>";
    echo "<li>اذهب إلى: 🗺️ الخرائط والمرافق → إدارة المرافق</li>";
    echo "<li>أضف مرافق تكريت (مدارس، مساجد، مستشفيات، إلخ)</li>";
    echo "</ol>";
} else {
    echo "<p class='success'><strong>✅ كل شيء يبدو جيداً!</strong></p>";
    echo "<p>افتح الخريطة: <a href='http://localhost:8080/tekrit_municipality/public/facilities-map.php'>خريطة المرافق</a></p>";
}

echo "</div>";

echo "</body></html>";
?>
