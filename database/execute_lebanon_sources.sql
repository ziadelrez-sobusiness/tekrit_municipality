-- تنفيذ إعداد المصادر اللبنانية
-- بلدية تكريت
-- شغّل هذا الملف في phpMyAdmin

-- ============================================
-- الخطوة 1: إضافة Foreign Key (آمن)
-- ============================================
SET @dbname = DATABASE();
SET @tablename = 'important_link_sources';
SET @constraintname = 'fk_sources_source_category';

-- التحقق من وجود العمود
SET @column_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname 
    AND TABLE_NAME = @tablename 
    AND COLUMN_NAME = 'source_category_id'
);

-- التحقق من وجود المفتاح
SET @constraint_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = @dbname 
    AND TABLE_NAME = @tablename 
    AND CONSTRAINT_NAME = @constraintname
);

-- بناء SQL
SET @sql = IF(
    @column_exists > 0 AND @constraint_exists = 0,
    CONCAT('ALTER TABLE ', @tablename, ' ADD CONSTRAINT ', @constraintname, 
           ' FOREIGN KEY (source_category_id) REFERENCES source_categories(id) ',
           'ON DELETE SET NULL ON UPDATE CASCADE;'),
    'SELECT "Foreign key already exists or column missing" AS result;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- الخطوة 2: إدراج المصادر الحقيقية من لبنان
-- ============================================
INSERT INTO `important_link_sources` 
(`name_ar`, `name_en`, `source_type`, `fetch_method`, `file_format`, `api_url`, `scraping_url`, `file_url`, `source_category_id`, `category_id`, `update_frequency`, `is_active`, `auto_import`) VALUES
('دليل الحكومة اللبنانية - TRA', 'Lebanese Government Directory - TRA', 'scraping', 'html_scraper', 'html', NULL, 'https://www.tra.gov.lb/useful-links-governmental-institutions', NULL, 1, 1, 'monthly', 1, 1),
('مستشفيات حكومية - Open Data Lebanon', 'Public Hospitals - Open Data', 'api', 'file_import', 'xlsx', 'https://www.opendatalebanon.org/job/list-of-public-hospitals-in-lebanon/', NULL, 'https://www.opendatalebanon.org/job/list-of-public-hospitals-in-lebanon/', 2, 2, 'monthly', 1, 1),
('مستشفيات - وزارة الصحة', 'Hospitals - MOPH', 'scraping', 'html_scraper', 'html', NULL, 'https://www.moph.gov.lb/en/HealthFacilities/index/3/188/8?facility_type=1', NULL, 2, 2, 'monthly', 1, 0),
('السفارات في لبنان - AUB', 'Embassies in Lebanon - AUB', 'api', 'file_import', 'pdf', NULL, NULL, NULL, 4, 6, 'monthly', 1, 1)
ON DUPLICATE KEY UPDATE 
    `name_ar` = VALUES(`name_ar`),
    `scraping_url` = VALUES(`scraping_url`),
    `file_url` = VALUES(`file_url`),
    `update_frequency` = VALUES(`update_frequency`),
    `source_category_id` = VALUES(`source_category_id`);

-- ============================================
-- تم الانتهاء!
-- ============================================
SELECT '✅ تم إضافة Foreign Key وإدراج المصادر بنجاح!' AS result;

