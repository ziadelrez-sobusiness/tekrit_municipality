-- تحسينات نظام المصادر لروابط مهمة
-- دعم مصادر متعددة: APIs, Scraping, Excel/CSV/PDF

-- تحديث جدول المصادر
ALTER TABLE `important_link_sources` 
ADD COLUMN IF NOT EXISTS `fetch_method` ENUM('api', 'html_scraper', 'file_import', 'manual') DEFAULT 'api' AFTER `source_type`,
ADD COLUMN IF NOT EXISTS `file_format` ENUM('json', 'csv', 'xlsx', 'xls', 'pdf', 'html') DEFAULT 'json' AFTER `fetch_method`,
ADD COLUMN IF NOT EXISTS `file_url` TEXT DEFAULT NULL COMMENT 'رابط ملف للتحميل' AFTER `file_format`,
ADD COLUMN IF NOT EXISTS `parser_config` TEXT DEFAULT NULL COMMENT 'إعدادات parser (JSON)' AFTER `mapping_config`,
ADD COLUMN IF NOT EXISTS `headers_config` TEXT DEFAULT NULL COMMENT 'HTTP Headers (JSON)' AFTER `parser_config`;

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

-- إدراج فئات المصادر
INSERT INTO `source_categories` (`code`, `name_ar`, `name_en`, `description_ar`, `target_table`, `mapper_class`) VALUES
('GOV_DIRECTORY', 'دليل الحكومة اللبنانية', 'Lebanese Government Directory', 'وزارات ومؤسسات رسمية', 'important_links', 'GovernmentDirectoryMapper'),
('PUBLIC_HOSPITALS', 'مستشفيات حكومية', 'Public Hospitals', 'مستشفيات حكومية من وزارة الصحة', 'important_links', 'HospitalsMapper'),
('EDUCATION_SCHOOLS', 'مدارس رسمية / جامعات', 'Public Schools / Universities', 'مدارس وجامعات رسمية', 'important_links', 'EducationMapper'),
('EMBASSIES', 'سفارات', 'Embassies', 'سفارات وقنصليات في لبنان', 'important_links', 'EmbassiesMapper'),
('PRIVATE_HOSPITALS', 'مستشفيات خاصة', 'Private Hospitals', 'مستشفيات خاصة', 'important_links', 'HospitalsMapper')
ON DUPLICATE KEY UPDATE `name_ar` = VALUES(`name_ar`);

-- إضافة عمود source_category_id للمصادر
ALTER TABLE `important_link_sources` 
ADD COLUMN IF NOT EXISTS `source_category_id` int(11) DEFAULT NULL AFTER `category_id`,
ADD KEY `idx_source_category_id` (`source_category_id`),
ADD CONSTRAINT `fk_sources_source_category` FOREIGN KEY (`source_category_id`) REFERENCES `source_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- إدراج مصادر حقيقية من لبنان
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

