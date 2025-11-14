-- نظام خريطة المرافق والخدمات
-- =====================================

-- جدول أنواع المرافق
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول المرافق والخدمات
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
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_facility_active (is_active),
    INDEX idx_facility_category (category_id),
    INDEX idx_facility_featured (is_featured),
    INDEX idx_facility_location (latitude, longitude),
    FULLTEXT KEY idx_facility_search_ar (name_ar, description_ar),
    FULLTEXT KEY idx_facility_search_en (name_en, description_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول إعدادات الخريطة
CREATE TABLE IF NOT EXISTS map_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_description TEXT NULL,
    data_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    is_public TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول تقييمات المرافق (اختياري)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إدراج الفئات الافتراضية
INSERT IGNORE INTO facility_categories (name_ar, name_en, icon, color, display_order) VALUES
('مدارس', 'Schools', 'school', '#e74c3c', 1),
('مساجد', 'Mosques', 'mosque', '#2ecc71', 2),
('مراكز صحية', 'Health Centers', 'hospital', '#3498db', 3),
('محلات تجارية', 'Commercial Shops', 'store', '#f39c12', 4),
('مطاعم ومقاهي', 'Restaurants & Cafes', 'restaurant', '#e67e22', 5),
('مؤسسات حكومية', 'Government Institutions', 'government', '#9b59b6', 6),
('بنوك وصرافات', 'Banks & ATMs', 'bank', '#1abc9c', 7),
('محطات وقود', 'Gas Stations', 'gas-station', '#34495e', 8),
('حدائق ومتنزهات', 'Parks & Gardens', 'park', '#27ae60', 9),
('مراكز رياضية', 'Sports Centers', 'sports', '#f1c40f', 10),
('صيدليات', 'Pharmacies', 'pharmacy', '#e74c3c', 11),
('فنادق ونزل', 'Hotels & Lodges', 'hotel', '#8e44ad', 12),
('خدمات عامة', 'Public Services', 'service', '#95a5a6', 13),
('مواقف سيارات', 'Parking Areas', 'parking', '#7f8c8d', 14),
('أسواق', 'Markets', 'market', '#d35400', 15);

-- إدراج إعدادات الخريطة الافتراضية
INSERT IGNORE INTO map_settings (setting_name, setting_value, setting_description, data_type, is_public) VALUES
('map_center_lat', '34.6137', 'خط العرض لمركز الخريطة (تكريت)', 'number', 1),
('map_center_lng', '43.6793', 'خط الطول لمركز الخريطة (تكريت)', 'number', 1),
('map_zoom_level', '13', 'مستوى التكبير الافتراضي للخريطة', 'number', 1),
('google_maps_api_key', '', 'مفتاح Google Maps API', 'string', 0),
('enable_user_location', '1', 'تفعيل تحديد موقع المستخدم', 'boolean', 1),
('show_directions', '1', 'عرض خاصية الاتجاهات', 'boolean', 1),
('enable_clustering', '1', 'تفعيل تجميع النقاط المتقاربة', 'boolean', 1),
('max_facilities_per_page', '50', 'الحد الأقصى للمرافق في الصفحة الواحدة', 'number', 0),
('enable_ratings', '1', 'تفعيل نظام التقييمات', 'boolean', 1),
('auto_approve_ratings', '0', 'الموافقة التلقائية على التقييمات', 'boolean', 0),
('map_style', 'default', 'نمط الخريطة (default, satellite, terrain)', 'string', 1),
('enable_search', '1', 'تفعيل البحث في الخريطة', 'boolean', 1),
('enable_filters', '1', 'تفعيل فلاتر الفئات', 'boolean', 1),
('contact_info_required', '0', 'إجبارية معلومات الاتصال عند إضافة مرفق', 'boolean', 0),
('default_language', 'ar', 'اللغة الافتراضية (ar/en)', 'string', 1);

-- إنشاء view للمرافق مع معلومات الفئة
CREATE OR REPLACE VIEW facilities_with_category AS
SELECT 
    f.*,
    fc.name_ar as category_name_ar,
    fc.name_en as category_name_en,
    fc.icon as category_icon,
    fc.color as category_color,
    (SELECT COUNT(*) FROM facility_ratings fr WHERE fr.facility_id = f.id AND fr.is_approved = 1) as ratings_count,
    (SELECT AVG(fr.rating) FROM facility_ratings fr WHERE fr.facility_id = f.id AND fr.is_approved = 1) as average_rating
FROM facilities f
LEFT JOIN facility_categories fc ON f.category_id = fc.id
WHERE f.is_active = 1 AND fc.is_active = 1;

-- إجراء مخزون للبحث في المرافق
DELIMITER //
CREATE OR REPLACE PROCEDURE SearchFacilities(
    IN search_term VARCHAR(255),
    IN category_filter INT,
    IN language_code VARCHAR(2),
    IN limit_count INT
)
BEGIN
    DECLARE search_query TEXT;
    
    SET search_query = CONCAT('%', search_term, '%');
    
    IF category_filter > 0 THEN
        IF language_code = 'en' THEN
            SELECT * FROM facilities_with_category 
            WHERE category_id = category_filter 
            AND (name_en LIKE search_query OR description_en LIKE search_query)
            ORDER BY is_featured DESC, name_en ASC
            LIMIT limit_count;
        ELSE
            SELECT * FROM facilities_with_category 
            WHERE category_id = category_filter 
            AND (name_ar LIKE search_query OR description_ar LIKE search_query)
            ORDER BY is_featured DESC, name_ar ASC
            LIMIT limit_count;
        END IF;
    ELSE
        IF language_code = 'en' THEN
            SELECT * FROM facilities_with_category 
            WHERE (name_en LIKE search_query OR description_en LIKE search_query)
            ORDER BY is_featured DESC, name_en ASC
            LIMIT limit_count;
        ELSE
            SELECT * FROM facilities_with_category 
            WHERE (name_ar LIKE search_query OR description_ar LIKE search_query)
            ORDER BY is_featured DESC, name_ar ASC
            LIMIT limit_count;
        END IF;
    END IF;
END //
DELIMITER ;

-- إجراء مخزون لجلب المرافق حسب المنطقة الجغرافية
DELIMITER //
CREATE OR REPLACE PROCEDURE GetFacilitiesInBounds(
    IN north_lat DECIMAL(10,8),
    IN south_lat DECIMAL(10,8),
    IN east_lng DECIMAL(11,8),
    IN west_lng DECIMAL(11,8),
    IN category_filter INT
)
BEGIN
    IF category_filter > 0 THEN
        SELECT * FROM facilities_with_category 
        WHERE latitude BETWEEN south_lat AND north_lat
        AND longitude BETWEEN west_lng AND east_lng
        AND category_id = category_filter
        ORDER BY is_featured DESC;
    ELSE
        SELECT * FROM facilities_with_category 
        WHERE latitude BETWEEN south_lat AND north_lat
        AND longitude BETWEEN west_lng AND east_lng
        ORDER BY is_featured DESC;
    END IF;
END //
DELIMITER ;

-- trigger لتحديث عداد المشاهدات
DELIMITER //
CREATE OR REPLACE TRIGGER update_facility_views
AFTER INSERT ON facility_ratings
FOR EACH ROW
BEGIN
    UPDATE facilities 
    SET views_count = views_count + 1 
    WHERE id = NEW.facility_id;
END //
DELIMITER ;

-- فهارس إضافية للأداء
CREATE INDEX IF NOT EXISTS idx_facilities_created_at ON facilities(created_at);
CREATE INDEX IF NOT EXISTS idx_facility_ratings_created_at ON facility_ratings(created_at);

-- تعليقات للجداول
ALTER TABLE facility_categories COMMENT = 'فئات المرافق والخدمات';
ALTER TABLE facilities COMMENT = 'المرافق والخدمات الموجودة في البلدة';
ALTER TABLE map_settings COMMENT = 'إعدادات نظام الخريطة';
ALTER TABLE facility_ratings COMMENT = 'تقييمات المرافق من المستخدمين';

COMMIT; 