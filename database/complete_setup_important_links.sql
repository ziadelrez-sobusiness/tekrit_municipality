-- إعداد كامل لنظام روابط مهمة مع المصادر اللبنانية
-- بلدية تكريت
-- تنفيذ هذا الملف أولاً قبل insert_lebanon_sources.sql

-- ============================================
-- الجزء 1: إنشاء الجداول الأساسية
-- ============================================

-- جدول فئات المرافق (إذا لم يكن موجوداً)
CREATE TABLE IF NOT EXISTS `important_link_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name_ar` varchar(255) NOT NULL,
  `name_en` varchar(255) DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `color` varchar(7) DEFAULT '#3b82f6',
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_display_order` (`display_order`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول المرافق العامة (إذا لم يكن موجوداً)
CREATE TABLE IF NOT EXISTS `important_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `name_ar` varchar(255) NOT NULL,
  `name_en` varchar(255) DEFAULT NULL,
  `description_ar` text DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `phone_2` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `address_ar` text DEFAULT NULL,
  `address_en` text DEFAULT NULL,
  `location_lat` decimal(10,8) DEFAULT NULL,
  `location_lng` decimal(11,8) DEFAULT NULL,
  `working_hours_ar` varchar(255) DEFAULT NULL,
  `working_hours_en` varchar(255) DEFAULT NULL,
  `is_government` tinyint(1) DEFAULT 0,
  `is_emergency` tinyint(1) DEFAULT 0,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_is_emergency` (`is_emergency`),
  KEY `idx_display_order` (`display_order`),
  CONSTRAINT `fk_important_links_category` FOREIGN KEY (`category_id`) REFERENCES `important_link_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- الجزء 2: جداول المصادر
-- ============================================

-- جدول مصادر البيانات
CREATE TABLE IF NOT EXISTS `important_link_sources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name_ar` varchar(255) NOT NULL COMMENT 'اسم المصدر بالعربي',
  `name_en` varchar(255) DEFAULT NULL COMMENT 'اسم المصدر بالإنجليزي',
  `source_type` enum('api','scraping','manual','csv_import') NOT NULL DEFAULT 'api' COMMENT 'نوع المصدر',
  `fetch_method` ENUM('api', 'html_scraper', 'file_import', 'manual') DEFAULT 'api' COMMENT 'طريقة الجلب',
  `file_format` ENUM('json', 'csv', 'xlsx', 'xls', 'pdf', 'html') DEFAULT 'json' COMMENT 'صيغة الملف',
  `api_url` text DEFAULT NULL COMMENT 'رابط API',
  `api_key` varchar(255) DEFAULT NULL COMMENT 'مفتاح API (مشفّر)',
  `scraping_url` text DEFAULT NULL COMMENT 'رابط للـ scraping',
  `file_url` TEXT DEFAULT NULL COMMENT 'رابط ملف للتحميل',
  `scraping_selector` text DEFAULT NULL COMMENT 'CSS selector للـ scraping',
  `category_id` int(11) DEFAULT NULL COMMENT 'الفئة الافتراضية',
  `source_category_id` int(11) DEFAULT NULL COMMENT 'فئة المصدر',
  `update_frequency` enum('hourly','daily','weekly','monthly','manual') DEFAULT 'daily' COMMENT 'تكرار التحديث',
  `last_update` timestamp NULL DEFAULT NULL COMMENT 'آخر تحديث',
  `next_update` timestamp NULL DEFAULT NULL COMMENT 'التحديث القادم',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'نشط/غير نشط',
  `auto_import` tinyint(1) DEFAULT 1 COMMENT 'استيراد تلقائي',
  `mapping_config` text DEFAULT NULL COMMENT 'إعدادات ربط الحقول (JSON)',
  `parser_config` TEXT DEFAULT NULL COMMENT 'إعدادات parser (JSON)',
  `headers_config` TEXT DEFAULT NULL COMMENT 'HTTP Headers (JSON)',
  `error_log` text DEFAULT NULL COMMENT 'سجل الأخطاء',
  `success_count` int(11) DEFAULT 0 COMMENT 'عدد النجاحات',
  `error_count` int(11) DEFAULT 0 COMMENT 'عدد الأخطاء',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_source_category_id` (`source_category_id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_next_update` (`next_update`),
  CONSTRAINT `fk_sources_category` FOREIGN KEY (`category_id`) REFERENCES `important_link_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول سجل عمليات الجلب
CREATE TABLE IF NOT EXISTS `important_link_fetch_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_id` int(11) NOT NULL,
  `fetch_type` enum('auto','manual') DEFAULT 'auto',
  `status` enum('success','failed','partial') DEFAULT 'success',
  `items_fetched` int(11) DEFAULT 0 COMMENT 'عدد العناصر المستخرجة',
  `items_imported` int(11) DEFAULT 0 COMMENT 'عدد العناصر المستوردة',
  `items_updated` int(11) DEFAULT 0 COMMENT 'عدد العناصر المحدثة',
  `error_message` text DEFAULT NULL,
  `execution_time` decimal(10,2) DEFAULT NULL COMMENT 'وقت التنفيذ بالثواني',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_source_id` (`source_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_logs_source` FOREIGN KEY (`source_id`) REFERENCES `important_link_sources` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول فئات المصادر
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
-- الجزء 3: إدراج البيانات الأساسية
-- ============================================

-- إدراج الفئات الأساسية
INSERT INTO `important_link_categories` (`name_ar`, `name_en`, `icon`, `color`, `display_order`) VALUES
('وزارات', 'Ministries', '🏛️', '#1e40af', 1),
('مستشفيات حكومية', 'Government Hospitals', '🏥', '#dc2626', 2),
('مستشفيات خاصة', 'Private Hospitals', '🏨', '#ea580c', 3),
('جامعات حكومية', 'Public Universities', '🎓', '#2563eb', 4),
('جامعات خاصة', 'Private Universities', '📚', '#7c3aed', 5),
('سفارات', 'Embassies', '🌍', '#059669', 6),
('صليب أحمر', 'Red Cross', '➕', '#dc2626', 7),
('دفاع مدني', 'Civil Defense', '🚒', '#ea580c', 8),
('أمن عام', 'General Security', '👮', '#1e40af', 9),
('خدمات طوارئ', 'Emergency Services', '🚨', '#dc2626', 10),
('مؤسسات عامة', 'Public Institutions', '🏢', '#475569', 11),
('أخرى', 'Others', '📋', '#64748b', 12)
ON DUPLICATE KEY UPDATE `name_ar` = VALUES(`name_ar`);

-- إدراج فئات المصادر
INSERT INTO `source_categories` (`id`, `code`, `name_ar`, `name_en`, `description_ar`, `target_table`, `mapper_class`) VALUES
(1, 'GOV_DIRECTORY', 'دليل الحكومة اللبنانية', 'Lebanese Government Directory', 'وزارات ومؤسسات رسمية', 'important_links', 'GovernmentDirectoryMapper'),
(2, 'PUBLIC_HOSPITALS', 'مستشفيات حكومية', 'Public Hospitals', 'مستشفيات حكومية من وزارة الصحة', 'important_links', 'HospitalsMapper'),
(3, 'EDUCATION_SCHOOLS', 'مدارس رسمية / جامعات', 'Public Schools / Universities', 'مدارس وجامعات رسمية', 'important_links', 'EducationMapper'),
(4, 'EMBASSIES', 'سفارات', 'Embassies', 'سفارات وقنصليات في لبنان', 'important_links', 'EmbassiesMapper'),
(5, 'PRIVATE_HOSPITALS', 'مستشفيات خاصة', 'Private Hospitals', 'مستشفيات خاصة', 'important_links', 'HospitalsMapper')
ON DUPLICATE KEY UPDATE `name_ar` = VALUES(`name_ar`);

-- إضافة Foreign Key للمصادر (مع التحقق من وجوده أولاً)
SET @dbname = DATABASE();
SET @tablename = 'important_link_sources';
SET @constraintname = 'fk_sources_source_category';
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
     WHERE CONSTRAINT_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND CONSTRAINT_NAME = @constraintname) > 0,
    'SELECT "Foreign key already exists" AS result;',
    CONCAT('ALTER TABLE ', @tablename, ' ADD CONSTRAINT ', @constraintname, 
           ' FOREIGN KEY (source_category_id) REFERENCES source_categories(id) ON DELETE SET NULL ON UPDATE CASCADE;')
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- الجزء 4: إدراج المصادر الحقيقية من لبنان
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
SELECT '✅ تم إعداد نظام روابط مهمة مع المصادر اللبنانية بنجاح!' AS result;

