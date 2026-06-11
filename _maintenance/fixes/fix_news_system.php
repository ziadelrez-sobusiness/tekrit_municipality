<?php
require_once 'config/database.php';

echo "<h1>🔧 إصلاح مشكلة العمود gallery_images</h1>";

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

try {
    // 1. التحقق من وجود العمود gallery_images
    echo "<h2>📋 فحص هيكل الجدول...</h2>";
    
    $columns = $db->query("SHOW COLUMNS FROM news_activities LIKE 'gallery_images'")->fetchAll();
    
    if (!empty($columns)) {
        echo "<p>⚠️ العمود gallery_images موجود - سيتم حذفه</p>";
        
        // حذف العمود
        $db->exec("ALTER TABLE news_activities DROP COLUMN gallery_images");
        echo "<p>✅ تم حذف العمود gallery_images بنجاح</p>";
    } else {
        echo "<p>✅ العمود gallery_images غير موجود (هذا صحيح)</p>";
    }
    
    // 2. التحقق من وجود جدول news_images
    echo "<h2>🖼️ فحص جدول الصور...</h2>";
    
    $tables = $db->query("SHOW TABLES LIKE 'news_images'")->fetchAll();
    
    if (!empty($tables)) {
        echo "<p>✅ جدول news_images موجود</p>";
        
        // عد الصور
        $count = $db->query("SELECT COUNT(*) as total FROM news_images")->fetch()['total'];
        echo "<p>📊 يحتوي على $count صورة</p>";
    } else {
        echo "<p>❌ جدول news_images غير موجود - يجب إنشاؤه</p>";
        
        // إنشاء الجدول
        $db->exec("
            CREATE TABLE news_images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                news_id INT NOT NULL,
                image_filename VARCHAR(255) NOT NULL,
                image_title VARCHAR(255) NULL,
                image_description TEXT NULL,
                image_type ENUM('gallery', 'content', 'attachment') DEFAULT 'gallery',
                display_order INT DEFAULT 0,
                image_size INT NULL,
                upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                uploaded_by INT NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (news_id) REFERENCES news_activities(id) ON DELETE CASCADE,
                INDEX idx_news_images_news_id (news_id),
                INDEX idx_news_images_active (is_active),
                INDEX idx_news_images_order (display_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p>✅ تم إنشاء جدول news_images</p>";
    }
    
    // 3. التحقق من جدول الإعدادات
    echo "<h2>⚙️ فحص جدول الإعدادات...</h2>";
    
    $settings_tables = $db->query("SHOW TABLES LIKE 'news_image_settings'")->fetchAll();
    
    if (!empty($settings_tables)) {
        echo "<p>✅ جدول news_image_settings موجود</p>";
        
        $settings_count = $db->query("SELECT COUNT(*) as total FROM news_image_settings")->fetch()['total'];
        echo "<p>📊 يحتوي على $settings_count إعداد</p>";
    } else {
        echo "<p>❌ جدول news_image_settings غير موجود - سيتم إنشاؤه</p>";
        
        $db->exec("
            CREATE TABLE news_image_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_name VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT NOT NULL,
                setting_description TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // إدراج الإعدادات الافتراضية
        $settings = [
            ['max_file_size', '5242880', 'الحد الأقصى لحجم الصورة (5MB)'],
            ['allowed_extensions', 'jpg,jpeg,png,gif,webp', 'امتدادات الصور المسموحة'],
            ['max_images_per_news', '10', 'الحد الأقصى لعدد الصور لكل خبر']
        ];
        
        $stmt = $db->prepare("INSERT INTO news_image_settings (setting_name, setting_value, setting_description) VALUES (?, ?, ?)");
        foreach ($settings as $setting) {
            $stmt->execute($setting);
        }
        
        echo "<p>✅ تم إنشاء جدول news_image_settings مع الإعدادات الافتراضية</p>";
    }
    
    // 4. فحص مجلد الصور
    echo "<h2>📁 فحص مجلد الصور...</h2>";
    
    $upload_dir = 'uploads/news/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
        echo "<p>✅ تم إنشاء مجلد $upload_dir</p>";
    } else {
        echo "<p>✅ مجلد $upload_dir موجود</p>";
    }
    
    if (is_writable($upload_dir)) {
        echo "<p>✅ المجلد قابل للكتابة</p>";
    } else {
        echo "<p>⚠️ المجلد غير قابل للكتابة - تحقق من الصلاحيات</p>";
    }
    
    // 5. عرض هيكل الجدول النهائي
    echo "<h2>📋 هيكل جدول الأخبار النهائي:</h2>";
    
    $columns = $db->query("DESCRIBE news_activities")->fetchAll();
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>العمود</th><th>النوع</th><th>القيم الافتراضية</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<div style='background: #d1fae5; padding: 20px; margin: 20px 0; border-radius: 10px; border: 2px solid #10b981;'>";
    echo "<h2 style='color: #059669; margin-top: 0;'>✅ تم إصلاح المشكلة بنجاح!</h2>";
    echo "<p><strong>الآن يمكنك:</strong></p>";
    echo "<ul>";
    echo "<li>✅ استخدام نظام الأخبار بدون أخطاء</li>";
    echo "<li>✅ رفع وإدارة الصور بالنظام الجديد</li>";
    echo "<li>✅ الاستفادة من جميع الميزات المحدثة</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ خطأ في الإصلاح</h2>";
    echo "<p style='color: red;'>الخطأ: " . $e->getMessage() . "</p>";
    echo "<p>الملف: " . $e->getFile() . "</p>";
    echo "<p>السطر: " . $e->getLine() . "</p>";
}
?> 