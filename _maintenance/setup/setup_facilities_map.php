<?php
require_once 'config/database.php';

echo "<h1>🗺️ إعداد نظام خريطة المرافق والخدمات</h1>";

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

try {
    // إنشاء مجلد الصور للمرافق
    $facilities_dir = 'uploads/facilities/';
    if (!is_dir($facilities_dir)) {
        mkdir($facilities_dir, 0755, true);
        echo "<p>✅ تم إنشاء مجلد الصور: $facilities_dir</p>";
    }

    echo "<h2>📋 إنشاء الجداول...</h2>";
    
    // 1. جدول فئات المرافق
    $db->exec("
        CREATE TABLE IF NOT EXISTS facility_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name_ar VARCHAR(100) NOT NULL,
            name_en VARCHAR(100) NOT NULL,
            icon VARCHAR(50) DEFAULT 'default-marker',
            color VARCHAR(7) DEFAULT '#3498db',
            description_ar TEXT NULL,
            description_en TEXT NULL,
            is_active TINYINT(1) DEFAULT 1,
            display_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_category_active (is_active),
            INDEX idx_category_order (display_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✅ تم إنشاء جدول facility_categories</p>";

    // 2. جدول المرافق
    $db->exec("
        CREATE TABLE IF NOT EXISTS facilities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name_ar VARCHAR(255) NOT NULL,
            name_en VARCHAR(255) NOT NULL,
            category_id INT NOT NULL,
            description_ar TEXT NULL,
            description_en TEXT NULL,
            latitude DECIMAL(10, 8) NOT NULL,
            longitude DECIMAL(11, 8) NOT NULL,
            contact_person_ar VARCHAR(100) NULL,
            contact_person_en VARCHAR(100) NULL,
            phone VARCHAR(20) NULL,
            email VARCHAR(100) NULL,
            address_ar TEXT NULL,
            address_en TEXT NULL,
            working_hours_ar VARCHAR(200) NULL,
            working_hours_en VARCHAR(200) NULL,
            website VARCHAR(255) NULL,
            image_path VARCHAR(255) NULL,
            is_active TINYINT(1) DEFAULT 1,
            is_featured TINYINT(1) DEFAULT 0,
            views_count INT DEFAULT 0,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES facility_categories(id) ON DELETE RESTRICT,
            INDEX idx_facility_active (is_active),
            INDEX idx_facility_category (category_id),
            INDEX idx_facility_featured (is_featured),
            INDEX idx_facility_location (latitude, longitude)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✅ تم إنشاء جدول facilities</p>";

    // 3. جدول إعدادات الخريطة
    $db->exec("
        CREATE TABLE IF NOT EXISTS map_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_name VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NOT NULL,
            setting_description TEXT NULL,
            data_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
            is_public TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✅ تم إنشاء جدول map_settings</p>";

    // 4. جدول التقييمات
    $db->exec("
        CREATE TABLE IF NOT EXISTS facility_ratings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            facility_id INT NOT NULL,
            user_name VARCHAR(100) NULL,
            user_email VARCHAR(100) NULL,
            rating TINYINT CHECK (rating >= 1 AND rating <= 5),
            comment TEXT NULL,
            is_approved TINYINT(1) DEFAULT 0,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (facility_id) REFERENCES facilities(id) ON DELETE CASCADE,
            INDEX idx_rating_facility (facility_id),
            INDEX idx_rating_approved (is_approved)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✅ تم إنشاء جدول facility_ratings</p>";

    echo "<h2>📝 إدراج البيانات الافتراضية...</h2>";

    // إدراج الفئات الافتراضية
    $categories = [
        ['مدارس', 'Schools', 'school', '#e74c3c', 1],
        ['مساجد', 'Mosques', 'mosque', '#2ecc71', 2],
        ['مراكز صحية', 'Health Centers', 'hospital', '#3498db', 3],
        ['محلات تجارية', 'Commercial Shops', 'store', '#f39c12', 4],
        ['مطاعم ومقاهي', 'Restaurants & Cafes', 'restaurant', '#e67e22', 5],
        ['مؤسسات حكومية', 'Government Institutions', 'government', '#9b59b6', 6],
        ['بنوك وصرافات', 'Banks & ATMs', 'bank', '#1abc9c', 7],
        ['محطات وقود', 'Gas Stations', 'gas-station', '#34495e', 8],
        ['حدائق ومتنزهات', 'Parks & Gardens', 'park', '#27ae60', 9],
        ['مراكز رياضية', 'Sports Centers', 'sports', '#f1c40f', 10],
        ['صيدليات', 'Pharmacies', 'pharmacy', '#e74c3c', 11],
        ['فنادق ونزل', 'Hotels & Lodges', 'hotel', '#8e44ad', 12],
        ['خدمات عامة', 'Public Services', 'service', '#95a5a6', 13],
        ['مواقف سيارات', 'Parking Areas', 'parking', '#7f8c8d', 14],
        ['أسواق', 'Markets', 'market', '#d35400', 15]
    ];

    $category_stmt = $db->prepare("INSERT IGNORE INTO facility_categories (name_ar, name_en, icon, color, display_order) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($categories as $category) {
        $category_stmt->execute($category);
    }
    echo "<p>✅ تم إدراج " . count($categories) . " فئة افتراضية</p>";

    // إدراج إعدادات الخريطة
    $settings = [
        ['map_center_lat', '34.6137', 'خط العرض لمركز الخريطة (تكريت)', 'number', 1],
        ['map_center_lng', '43.6793', 'خط الطول لمركز الخريطة (تكريت)', 'number', 1],
        ['map_zoom_level', '13', 'مستوى التكبير الافتراضي للخريطة', 'number', 1],
        ['google_maps_api_key', '', 'مفتاح Google Maps API', 'string', 0],
        ['enable_user_location', '1', 'تفعيل تحديد موقع المستخدم', 'boolean', 1],
        ['show_directions', '1', 'عرض خاصية الاتجاهات', 'boolean', 1],
        ['enable_clustering', '1', 'تفعيل تجميع النقاط المتقاربة', 'boolean', 1],
        ['max_facilities_per_page', '50', 'الحد الأقصى للمرافق في الصفحة الواحدة', 'number', 0],
        ['enable_ratings', '1', 'تفعيل نظام التقييمات', 'boolean', 1],
        ['auto_approve_ratings', '0', 'الموافقة التلقائية على التقييمات', 'boolean', 0],
        ['map_style', 'default', 'نمط الخريطة', 'string', 1],
        ['enable_search', '1', 'تفعيل البحث في الخريطة', 'boolean', 1],
        ['enable_filters', '1', 'تفعيل فلاتر الفئات', 'boolean', 1],
        ['default_language', 'ar', 'اللغة الافتراضية (ar/en)', 'string', 1]
    ];

    $settings_stmt = $db->prepare("INSERT IGNORE INTO map_settings (setting_name, setting_value, setting_description, data_type, is_public) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($settings as $setting) {
        $settings_stmt->execute($setting);
    }
    echo "<p>✅ تم إدراج " . count($settings) . " إعداد افتراضي</p>";

    // إنشاء .htaccess لحماية الصور
    $htaccess_content = "# حماية مجلد صور المرافق
<FilesMatch \"\\.(jpg|jpeg|png|gif|webp)$\">
    Require all granted
</FilesMatch>

<Files *.php>
    Require all denied
</Files>

Options -Indexes

<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
</IfModule>";

    if (file_put_contents($facilities_dir . '.htaccess', $htaccess_content)) {
        echo "<p>✅ تم إنشاء ملف الحماية .htaccess</p>";
    }

    // عرض إحصائيات النظام
    echo "<h2>📊 إحصائيات النظام:</h2>";
    
    $categories_count = $db->query("SELECT COUNT(*) as count FROM facility_categories")->fetch()['count'];
    $facilities_count = $db->query("SELECT COUNT(*) as count FROM facilities")->fetch()['count'];
    $settings_count = $db->query("SELECT COUNT(*) as count FROM map_settings")->fetch()['count'];
    
    echo "<div style='background: #f0f9ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<p><strong>📂 فئات المرافق:</strong> $categories_count</p>";
    echo "<p><strong>🏢 المرافق المضافة:</strong> $facilities_count</p>";
    echo "<p><strong>⚙️ الإعدادات:</strong> $settings_count</p>";
    echo "<p><strong>📁 مجلد الصور:</strong> $facilities_dir</p>";
    echo "</div>";

    echo "<h2>✅ تم إعداد نظام خريطة المرافق والخدمات بنجاح!</h2>";
    echo "<div style='background: #d1fae5; border: 2px solid #10b981; padding: 20px; margin: 20px 0; border-radius: 10px;'>";
    echo "<h3 style='color: #059669; margin-top: 0;'>🎉 النظام جاهز للاستخدام!</h3>";
    echo "<p><strong>الخطوات التالية:</strong></p>";
    echo "<ul>";
    echo "<li>✅ إعداد قاعدة البيانات مكتمل</li>";
    echo "<li>🔧 يمكن الآن إنشاء واجهات الإدارة والعرض</li>";
    echo "<li>🗺️ إضافة مفتاح Google Maps API في الإعدادات</li>";
    echo "<li>📍 بدء إضافة المرافق والخدمات</li>";
    echo "</ul>";
    echo "</div>";

    // عرض بعض المرافق التجريبية لتكريت
    echo "<h2>💡 مرافق تجريبية مقترحة:</h2>";
    echo "<div style='background: #f9fafb; padding: 15px; border-radius: 5px;'>";
    echo "<p><strong>يمكن إضافة هذه المرافق كأمثلة:</strong></p>";
    echo "<ul>";
    echo "<li>🏛️ بلدية تكريت (34.6137, 43.6793)</li>";
    echo "<li>🕌 جامع تكريت الكبير (34.6145, 43.6801)</li>";
    echo "<li>🏥 مستشفى تكريت العام (34.6125, 43.6785)</li>";
    echo "<li>🏫 جامعة تكريت (34.6089, 43.6712)</li>";
    echo "<li>🏪 سوق تكريت المركزي (34.6141, 43.6799)</li>";
    echo "</ul>";
    echo "</div>";

} catch (Exception $e) {
    echo "<h2>❌ حدث خطأ أثناء الإعداد</h2>";
    echo "<p style='color: red;'>الخطأ: " . $e->getMessage() . "</p>";
    echo "<p>الملف: " . $e->getFile() . "</p>";
    echo "<p>السطر: " . $e->getLine() . "</p>";
}
?> 