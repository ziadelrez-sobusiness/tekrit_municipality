-- نظام إدارة صور الأخبار المحسن
-- ==========================================

-- تحديث جدول الأخبار ليحتوي على الصورة الرئيسية فقط
ALTER TABLE news_activities 
MODIFY COLUMN featured_image VARCHAR(255) NULL COMMENT 'الصورة الرئيسية للخبر',
DROP COLUMN IF EXISTS gallery_images;

-- إنشاء جدول منفصل لصور الأخبار
CREATE TABLE IF NOT EXISTS news_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    news_id INT NOT NULL,
    image_filename VARCHAR(255) NOT NULL,
    image_title VARCHAR(255) NULL,
    image_description TEXT NULL,
    image_type ENUM('gallery', 'content', 'attachment') DEFAULT 'gallery' COMMENT 'نوع الصورة: معرض، محتوى، مرفق',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إنشاء جدول لإعدادات الصور
CREATE TABLE IF NOT EXISTS news_image_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إدراج إعدادات افتراضية للصور
INSERT IGNORE INTO news_image_settings (setting_name, setting_value, setting_description) VALUES
('max_file_size', '5242880', 'الحد الأقصى لحجم الصورة بالبايت (5MB)'),
('allowed_extensions', 'jpg,jpeg,png,gif,webp', 'امتدادات الصور المسموحة'),
('featured_image_width', '800', 'عرض الصورة الرئيسية بالبكسل'),
('featured_image_height', '600', 'ارتفاع الصورة الرئيسية بالبكسل'),
('gallery_image_width', '600', 'عرض صور المعرض بالبكسل'),
('gallery_image_height', '400', 'ارتفاع صور المعرض بالبكسل'),
('thumbnail_width', '150', 'عرض الصور المصغرة بالبكسل'),
('thumbnail_height', '100', 'ارتفاع الصور المصغرة بالبكسل'),
('max_images_per_news', '10', 'الحد الأقصى لعدد الصور لكل خبر'),
('auto_generate_thumbnails', '1', 'إنشاء صور مصغرة تلقائياً'),
('watermark_enabled', '0', 'تفعيل العلامة المائية'),
('compress_images', '1', 'ضغط الصور تلقائياً');

-- إنشاء فهارس إضافية للأداء
CREATE INDEX IF NOT EXISTS idx_news_activities_featured ON news_activities(featured_image);
CREATE INDEX IF NOT EXISTS idx_news_activities_publish_date ON news_activities(publish_date, is_published);

-- إنشاء view لعرض الأخبار مع صورها
CREATE OR REPLACE VIEW news_with_images AS
SELECT 
    n.*,
    (SELECT COUNT(*) FROM news_images ni WHERE ni.news_id = n.id AND ni.is_active = 1) as total_images,
    (SELECT GROUP_CONCAT(
        JSON_OBJECT(
            'id', ni.id,
            'filename', ni.image_filename,
            'title', ni.image_title,
            'description', ni.image_description,
            'type', ni.image_type,
            'order', ni.display_order
        ) ORDER BY ni.display_order, ni.id
    ) FROM news_images ni WHERE ni.news_id = n.id AND ni.is_active = 1) as images_json
FROM news_activities n
WHERE n.is_published = 1
ORDER BY n.publish_date DESC, n.created_at DESC;

-- إجراء مخزون لجلب خبر مع صوره
DELIMITER //
CREATE OR REPLACE PROCEDURE GetNewsWithImages(IN news_id INT)
BEGIN
    SELECT 
        n.*,
        u.full_name as creator_name
    FROM news_activities n 
    LEFT JOIN users u ON n.created_by = u.id 
    WHERE n.id = news_id AND n.is_published = 1;
    
    SELECT 
        ni.*
    FROM news_images ni 
    WHERE ni.news_id = news_id AND ni.is_active = 1 
    ORDER BY ni.display_order, ni.id;
END //
DELIMITER ;

-- إجراء مخزون لحذف صورة
DELIMITER //
CREATE OR REPLACE PROCEDURE DeleteNewsImage(IN image_id INT, IN user_id INT)
BEGIN
    DECLARE image_filename VARCHAR(255);
    DECLARE news_id INT;
    
    -- جلب معلومات الصورة
    SELECT ni.image_filename, ni.news_id 
    INTO image_filename, news_id
    FROM news_images ni 
    WHERE ni.id = image_id;
    
    -- حذف الصورة من قاعدة البيانات
    DELETE FROM news_images WHERE id = image_id;
    
    -- تسجيل العملية في سجل النشاطات
    INSERT INTO activity_log (user_id, action, table_name, record_id, old_values) 
    VALUES (user_id, 'DELETE_NEWS_IMAGE', 'news_images', image_id, 
            JSON_OBJECT('filename', image_filename, 'news_id', news_id));
END //
DELIMITER ;

-- إجراء مخزون لإعادة ترتيب الصور
DELIMITER //
CREATE OR REPLACE PROCEDURE ReorderNewsImages(IN news_id INT, IN image_orders JSON)
BEGIN
    DECLARE i INT DEFAULT 0;
    DECLARE image_id INT;
    DECLARE new_order INT;
    
    WHILE i < JSON_LENGTH(image_orders) DO
        SET image_id = JSON_UNQUOTE(JSON_EXTRACT(image_orders, CONCAT('$[', i, '].id')));
        SET new_order = JSON_UNQUOTE(JSON_EXTRACT(image_orders, CONCAT('$[', i, '].order')));
        
        UPDATE news_images 
        SET display_order = new_order 
        WHERE id = image_id AND news_id = news_id;
        
        SET i = i + 1;
    END WHILE;
END //
DELIMITER ;

-- دالة للحصول على إعدادات الصور
DELIMITER //
CREATE OR REPLACE FUNCTION GetImageSetting(setting_name VARCHAR(100)) 
RETURNS TEXT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE setting_value TEXT DEFAULT '';
    
    SELECT nis.setting_value INTO setting_value
    FROM news_image_settings nis
    WHERE nis.setting_name = setting_name
    LIMIT 1;
    
    RETURN setting_value;
END //
DELIMITER ;

-- trigger لتنظيف الصور عند حذف خبر
DELIMITER //
CREATE OR REPLACE TRIGGER news_images_cleanup
AFTER DELETE ON news_activities
FOR EACH ROW
BEGIN
    -- حذف جميع صور الخبر المحذوف
    DELETE FROM news_images WHERE news_id = OLD.id;
END //
DELIMITER ;

-- إضافة تعليقات للجداول
ALTER TABLE news_activities COMMENT = 'جدول الأخبار والأنشطة الرئيسي';
ALTER TABLE news_images COMMENT = 'جدول صور الأخبار المنفصل';
ALTER TABLE news_image_settings COMMENT = 'إعدادات نظام صور الأخبار';

-- إنشاء مستخدم خاص لإدارة الصور (اختياري)
-- CREATE USER IF NOT EXISTS 'news_images_user'@'localhost' IDENTIFIED BY 'secure_password';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON tekrit_municipality.news_images TO 'news_images_user'@'localhost';
-- GRANT SELECT ON tekrit_municipality.news_image_settings TO 'news_images_user'@'localhost';

COMMIT; 