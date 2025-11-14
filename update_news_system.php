<?php
require_once 'config/database.php';

echo "<h1>🔄 تحديث نظام إدارة صور الأخبار</h1>";

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

try {
    // إنشاء مجلد الصور إذا لم يكن موجوداً
    $upload_dir = 'uploads/news/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
        echo "<p>✅ تم إنشاء مجلد الصور: $upload_dir</p>";
    } else {
        echo "<p>✅ مجلد الصور موجود: $upload_dir</p>";
    }

    // 1. تحديث جدول الأخبار (إزالة gallery_images إن وجد)
    echo "<h2>🔧 تحديث جدول الأخبار...</h2>";
    
    try {
        $db->exec("ALTER TABLE news_activities DROP COLUMN gallery_images");
        echo "<p>✅ تم حذف العمود gallery_images من جدول الأخبار</p>";
    } catch (Exception $e) {
        echo "<p>ℹ️ العمود gallery_images غير موجود (هذا طبيعي)</p>";
    }

    // 2. إنشاء جدول صور الأخبار
    echo "<h2>📷 إنشاء جدول صور الأخبار...</h2>";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS news_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            news_id INT NOT NULL,
            image_filename VARCHAR(255) NOT NULL,
            image_title VARCHAR(255) NULL,
            image_description TEXT NULL,
            image_type ENUM('gallery', 'content', 'attachment') DEFAULT 'gallery' COMMENT 'نوع الصورة',
            display_order INT DEFAULT 0,
            image_size INT NULL COMMENT 'حجم الصورة بالبايت',
            upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            uploaded_by INT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (news_id) REFERENCES news_activities(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_news_images_news_id (news_id),
            INDEX idx_news_images_active (is_active),
            INDEX idx_news_images_order (display_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✅ تم إنشاء جدول news_images</p>";

    // 3. إنشاء جدول إعدادات الصور
    echo "<h2>⚙️ إنشاء جدول إعدادات الصور...</h2>";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS news_image_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_name VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NOT NULL,
            setting_description TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✅ تم إنشاء جدول news_image_settings</p>";

    // 4. إدراج الإعدادات الافتراضية
    echo "<h2>📋 إدراج الإعدادات الافتراضية...</h2>";
    
    $settings = [
        ['max_file_size', '5242880', 'الحد الأقصى لحجم الصورة بالبايت (5MB)'],
        ['allowed_extensions', 'jpg,jpeg,png,gif,webp', 'امتدادات الصور المسموحة'],
        ['featured_image_width', '800', 'عرض الصورة الرئيسية بالبكسل'],
        ['featured_image_height', '600', 'ارتفاع الصورة الرئيسية بالبكسل'],
        ['gallery_image_width', '600', 'عرض صور المعرض بالبكسل'],
        ['gallery_image_height', '400', 'ارتفاع صور المعرض بالبكسل'],
        ['thumbnail_width', '150', 'عرض الصور المصغرة بالبكسل'],
        ['thumbnail_height', '100', 'ارتفاع الصور المصغرة بالبكسل'],
        ['max_images_per_news', '10', 'الحد الأقصى لعدد الصور لكل خبر'],
        ['auto_generate_thumbnails', '1', 'إنشاء صور مصغرة تلقائياً'],
        ['watermark_enabled', '0', 'تفعيل العلامة المائية'],
        ['compress_images', '1', 'ضغط الصور تلقائياً']
    ];

    $stmt = $db->prepare("INSERT IGNORE INTO news_image_settings (setting_name, setting_value, setting_description) VALUES (?, ?, ?)");
    
    foreach ($settings as $setting) {
        $stmt->execute($setting);
    }
    echo "<p>✅ تم إدراج " . count($settings) . " إعدادات افتراضية</p>";

    // 5. إنشاء فهارس إضافية
    echo "<h2>📊 إنشاء فهارس الأداء...</h2>";
    
    try {
        $db->exec("CREATE INDEX IF NOT EXISTS idx_news_activities_featured ON news_activities(featured_image)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_news_activities_publish_date ON news_activities(publish_date, is_published)");
        echo "<p>✅ تم إنشاء فهارس الأداء</p>";
    } catch (Exception $e) {
        echo "<p>⚠️ تحذير في إنشاء الفهارس: " . $e->getMessage() . "</p>";
    }

    // 6. إنشاء .htaccess لحماية الصور
    echo "<h2>🔒 إنشاء ملف الحماية...</h2>";
    
    $htaccess_content = "# حماية مجلد صور الأخبار
# السماح فقط بملفات الصور
<FilesMatch \"\\.(jpg|jpeg|png|gif|webp)$\">
    Require all granted
</FilesMatch>

# منع تنفيذ PHP
<Files *.php>
    Require all denied
</Files>

# منع عرض قائمة الملفات
Options -Indexes

# إعدادات الأمان
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
</IfModule>

# ضغط الصور
<IfModule mod_deflate.c>
    <FilesMatch \"\\.(jpg|jpeg|png|gif)$\">
        SetEnv no-gzip 1
    </FilesMatch>
</IfModule>

# تحديد أنواع MIME
<IfModule mod_mime.c>
    AddType image/jpeg .jpg .jpeg
    AddType image/png .png
    AddType image/gif .gif
    AddType image/webp .webp
</IfModule>";

    if (file_put_contents($upload_dir . '.htaccess', $htaccess_content)) {
        echo "<p>✅ تم إنشاء ملف الحماية .htaccess</p>";
    } else {
        echo "<p>⚠️ فشل في إنشاء ملف الحماية</p>";
    }

    // 7. فحص الصلاحيات
    echo "<h2>🔍 فحص صلاحيات المجلدات...</h2>";
    
    if (is_writable($upload_dir)) {
        echo "<p>✅ مجلد الصور قابل للكتابة</p>";
    } else {
        echo "<p>❌ مجلد الصور غير قابل للكتابة - يرجى تعديل الصلاحيات</p>";
    }

    // 8. عرض إحصائيات
    echo "<h2>📈 إحصائيات النظام...</h2>";
    
    $total_news = $db->query("SELECT COUNT(*) as count FROM news_activities")->fetch()['count'];
    $news_with_images = $db->query("SELECT COUNT(DISTINCT featured_image) as count FROM news_activities WHERE featured_image IS NOT NULL AND featured_image != ''")->fetch()['count'];
    $total_images = $db->query("SELECT COUNT(*) as count FROM news_images")->fetch()['count'];
    
    echo "<div style='background: #f0f9ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<p><strong>📰 إجمالي الأخبار:</strong> $total_news</p>";
    echo "<p><strong>🖼️ أخبار لها صور رئيسية:</strong> $news_with_images</p>";
    echo "<p><strong>📷 إجمالي صور المعرض:</strong> $total_images</p>";
    echo "</div>";

    echo "<h2>✅ تم تحديث نظام إدارة صور الأخبار بنجاح!</h2>";
    echo "<p><strong>الخطوات التالية:</strong></p>";
    echo "<ul>";
    echo "<li>✅ نظام الجداول محدث ومجهز</li>";
    echo "<li>✅ مجلد الصور محمي بـ .htaccess</li>";
    echo "<li>🔄 يمكنك الآن استخدام النظام الجديد لرفع وإدارة الصور</li>";
    echo "<li>📱 تحديث صفحات العرض العامة لدعم النظام الجديد</li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<h2>❌ حدث خطأ أثناء التحديث</h2>";
    echo "<p style='color: red;'>الخطأ: " . $e->getMessage() . "</p>";
    echo "<p>الملف: " . $e->getFile() . "</p>";
    echo "<p>السطر: " . $e->getLine() . "</p>";
}
?> 