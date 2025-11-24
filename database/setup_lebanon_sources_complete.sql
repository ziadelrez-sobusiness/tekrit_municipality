-- إعداد كامل لنظام المصادر اللبنانية
-- بلدية تكريت
-- تنفيذ هذا الملف بالترتيب

-- ============================================
-- الخطوة 1: تحديث جدول المصادر (إذا لم يكن محدثاً)
-- ============================================
ALTER TABLE `important_link_sources` 
ADD COLUMN IF NOT EXISTS `fetch_method` ENUM('api', 'html_scraper', 'file_import', 'manual') DEFAULT 'api' AFTER `source_type`,
ADD COLUMN IF NOT EXISTS `file_format` ENUM('json', 'csv', 'xlsx', 'xls', 'pdf', 'html') DEFAULT 'json' AFTER `fetch_method`,
ADD COLUMN IF NOT EXISTS `file_url` TEXT DEFAULT NULL COMMENT 'رابط ملف للتحميل' AFTER `file_format`,
ADD COLUMN IF NOT EXISTS `parser_config` TEXT DEFAULT NULL COMMENT 'إعدادات parser (JSON)' AFTER `mapping_config`,
ADD COLUMN IF NOT EXISTS `headers_config` TEXT DEFAULT NULL COMMENT 'HTTP Headers (JSON)' AFTER `parser_config`,
ADD COLUMN IF NOT EXISTS `source_category_id` int(11) DEFAULT NULL AFTER `category_id`;

-- ============================================
-- الخطوة 2: إنشاء جدول فئات المصادر
-- ============================================
CREATE TABLE IF NOT EXISTS `source_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL UNIQUE COMMENT 'كود الفئة',
  `name_ar` varchar(255) NOT NULL,
  `name_en` varchar(255) DEFAULT NULL,
  `description_ar` text DEFAULT NULL,
  `target_table` varchar(100) DEFAULT NULL COMMENT 'الجدول المستهدف',
  `mapper_class` varchar(100) DEFAULT NULL COMMENT 'اسم class المعالج',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- الخطوة 3: إدراج فئات المصادر
-- ============================================
INSERT INTO `source_categories` (`id`, `code`, `name_ar`, `name_en`, `description_ar`, `target_table`, `mapper_class`) VALUES
(1, 'GOV_DIRECTORY', 'دليل الحكومة اللبنانية', 'Lebanese Government Directory', 'وزارات ومؤسسات رسمية', 'important_links', 'GovernmentDirectoryMapper'),
(2, 'PUBLIC_HOSPITALS', 'مستشفيات حكومية', 'Public Hospitals', 'مستشفيات حكومية من وزارة الصحة', 'important_links', 'HospitalsMapper'),
(3, 'EDUCATION_SCHOOLS', 'مدارس رسمية / جامعات', 'Public Schools / Universities', 'مدارس وجامعات رسمية', 'important_links', 'EducationMapper'),
(4, 'EMBASSIES', 'سفارات', 'Embassies', 'سفارات وقنصليات في لبنان', 'important_links', 'EmbassiesMapper'),
(5, 'PRIVATE_HOSPITALS', 'مستشفيات خاصة', 'Private Hospitals', 'مستشفيات خاصة', 'important_links', 'HospitalsMapper')
ON DUPLICATE KEY UPDATE `name_ar` = VALUES(`name_ar`);

-- ============================================
-- الخطوة 4: إضافة Foreign Key (إذا لم يكن موجوداً)
-- ============================================
-- التحقق من وجود المفتاح أولاً
SET @dbname = DATABASE();
SET @tablename = 'important_link_sources';
SET @constraintname = 'fk_sources_source_category';

-- التحقق من وجود العمود أولاً
SET @column_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname 
    AND TABLE_NAME = @tablename 
    AND COLUMN_NAME = 'source_category_id');

-- إذا كان العمود موجوداً، أضف Foreign Key
SET @sql = (SELECT IF(
    @column_exists > 0 AND
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
     WHERE CONSTRAINT_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND CONSTRAINT_NAME = @constraintname) = 0,
    CONCAT('ALTER TABLE ', @tablename, ' ADD CONSTRAINT ', @constraintname, 
           ' FOREIGN KEY (source_category_id) REFERENCES source_categories(id) ON DELETE SET NULL ON UPDATE CASCADE;'),
    'SELECT "Foreign key already exists or column missing" AS result;'
));

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- الخطوة 5: إدراج المصادر الحقيقية من لبنان
-- ============================================
INSERT INTO `important_link_sources` 
(`name_ar`, `name_en`, `source_type`, `fetch_method`, `file_format`, `api_url`, `scraping_url`, `file_url`, `source_category_id`, `category_id`, `update_frequency`, `is_active`, `auto_import`, `description_ar`) VALUES
('دليل الحكومة اللبنانية - TRA', 'Lebanese Government Directory - TRA', 'scraping', 'html_scraper', 'html', NULL, 'https://www.tra.gov.lb/useful-links-governmental-institutions', NULL, 1, 1, 'monthly', 1, 1, 'دليل الوزارات والمؤسسات الرسمية من هيئة تنظيم الاتصالات'),
('مستشفيات حكومية - Open Data Lebanon', 'Public Hospitals - Open Data', 'api', 'file_import', 'xlsx', 'https://www.opendatalebanon.org/job/list-of-public-hospitals-in-lebanon/', NULL, 'https://www.opendatalebanon.org/job/list-of-public-hospitals-in-lebanon/', 2, 2, 'monthly', 1, 1, 'ملف Excel من Open Data Lebanon لمستشفيات حكومية'),
('مستشفيات - وزارة الصحة', 'Hospitals - MOPH', 'scraping', 'html_scraper', 'html', NULL, 'https://www.moph.gov.lb/en/HealthFacilities/index/3/188/8?facility_type=1', NULL, 2, 2, 'monthly', 1, 0, 'صفحة وزارة الصحة للمستشفيات الحكومية'),
('السفارات في لبنان - AUB', 'Embassies in Lebanon - AUB', 'api', 'file_import', 'pdf', NULL, NULL, NULL, 4, 6, 'monthly', 1, 1, 'قائمة السفارات من AUB Office of International Programs')
ON DUPLICATE KEY UPDATE 
    `name_ar` = VALUES(`name_ar`),
    `scraping_url` = VALUES(`scraping_url`),
    `file_url` = VALUES(`file_url`),
    `update_frequency` = VALUES(`update_frequency`),
    `source_category_id` = VALUES(`source_category_id`);

-- ============================================
-- تم الانتهاء!
-- ============================================
SELECT 'تم إعداد المصادر اللبنانية بنجاح!' AS result;

