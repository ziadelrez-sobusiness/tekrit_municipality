-- نظام المصادر التلقائية لروابط مهمة
-- بلدية تكريت

-- جدول مصادر البيانات
CREATE TABLE IF NOT EXISTS `important_link_sources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name_ar` varchar(255) NOT NULL COMMENT 'اسم المصدر بالعربي',
  `name_en` varchar(255) DEFAULT NULL COMMENT 'اسم المصدر بالإنجليزي',
  `source_type` enum('api','scraping','manual','csv_import') NOT NULL DEFAULT 'api' COMMENT 'نوع المصدر',
  `api_url` text DEFAULT NULL COMMENT 'رابط API',
  `api_key` varchar(255) DEFAULT NULL COMMENT 'مفتاح API (مشفّر)',
  `scraping_url` text DEFAULT NULL COMMENT 'رابط للـ scraping',
  `scraping_selector` text DEFAULT NULL COMMENT 'CSS selector للـ scraping',
  `category_id` int(11) DEFAULT NULL COMMENT 'الفئة الافتراضية',
  `update_frequency` enum('hourly','daily','weekly','monthly','manual') DEFAULT 'daily' COMMENT 'تكرار التحديث',
  `last_update` timestamp NULL DEFAULT NULL COMMENT 'آخر تحديث',
  `next_update` timestamp NULL DEFAULT NULL COMMENT 'التحديث القادم',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'نشط/غير نشط',
  `auto_import` tinyint(1) DEFAULT 1 COMMENT 'استيراد تلقائي',
  `mapping_config` text DEFAULT NULL COMMENT 'إعدادات ربط الحقول (JSON)',
  `error_log` text DEFAULT NULL COMMENT 'سجل الأخطاء',
  `success_count` int(11) DEFAULT 0 COMMENT 'عدد النجاحات',
  `error_count` int(11) DEFAULT 0 COMMENT 'عدد الأخطاء',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category_id` (`category_id`),
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

-- إدراج بعض المصادر الافتراضية
INSERT INTO `important_link_sources` (`name_ar`, `name_en`, `source_type`, `api_url`, `category_id`, `update_frequency`, `is_active`, `auto_import`) VALUES
('دليل الحكومة اللبنانية', 'Lebanese Government Directory', 'api', 'https://www.pcm.gov.lb/arabic/api/facilities', NULL, 'weekly', 1, 1),
('وزارة الصحة العامة', 'Ministry of Public Health', 'api', 'https://www.moph.gov.lb/api/hospitals', 2, 'monthly', 1, 1),
('وزارة التربية والتعليم', 'Ministry of Education', 'scraping', 'https://www.mehe.gov.lb/universities', NULL, 'monthly', 1, 0),
('السفارات في لبنان', 'Embassies in Lebanon', 'api', 'https://www.general-security.gov.lb/api/embassies', 6, 'monthly', 1, 1)
ON DUPLICATE KEY UPDATE `name_ar` = VALUES(`name_ar`);

-- جدول للمصادر الخارجية (APIs العامة)
CREATE TABLE IF NOT EXISTS `external_data_sources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `provider` varchar(100) DEFAULT NULL COMMENT 'مزود الخدمة',
  `api_endpoint` text NOT NULL,
  `api_key` varchar(255) DEFAULT NULL,
  `authentication_type` enum('none','api_key','oauth','basic') DEFAULT 'none',
  `request_method` enum('GET','POST') DEFAULT 'GET',
  `request_headers` text DEFAULT NULL COMMENT 'JSON',
  `request_body` text DEFAULT NULL COMMENT 'JSON',
  `response_format` enum('json','xml','csv','html') DEFAULT 'json',
  `data_mapping` text DEFAULT NULL COMMENT 'JSON mapping',
  `rate_limit` int(11) DEFAULT 100 COMMENT 'عدد الطلبات في الساعة',
  `is_active` tinyint(1) DEFAULT 1,
  `last_used` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

