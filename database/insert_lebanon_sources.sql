-- إدراج مصادر حقيقية من لبنان لروابط مهمة
-- بلدية تكريت

-- تأكد من وجود الفئات أولاً
INSERT IGNORE INTO `source_categories` (`id`, `code`, `name_ar`, `name_en`, `description_ar`, `target_table`, `mapper_class`) VALUES
(1, 'GOV_DIRECTORY', 'دليل الحكومة اللبنانية', 'Lebanese Government Directory', 'وزارات ومؤسسات رسمية', 'important_links', 'GovernmentDirectoryMapper'),
(2, 'PUBLIC_HOSPITALS', 'مستشفيات حكومية', 'Public Hospitals', 'مستشفيات حكومية من وزارة الصحة', 'important_links', 'HospitalsMapper'),
(4, 'EMBASSIES', 'سفارات', 'Embassies', 'سفارات وقنصليات في لبنان', 'important_links', 'EmbassiesMapper');

-- إدراج المصادر الحقيقية
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

