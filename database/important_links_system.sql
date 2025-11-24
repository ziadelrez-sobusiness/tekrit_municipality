-- نظام روابط مهمة - المرافق العامة
-- بلدية تكريت

-- جدول فئات المرافق
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

-- جدول المرافق العامة
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

-- إدراج بعض المرافق الأساسية كمثال
INSERT INTO `important_links` (`category_id`, `name_ar`, `name_en`, `phone`, `email`, `website`, `address_ar`, `is_government`, `is_emergency`, `display_order`) VALUES
(7, 'الصليب الأحمر اللبناني', 'Lebanese Red Cross', '140', 'info@redcross.org.lb', 'https://www.redcross.org.lb', 'بيروت، لبنان', 1, 1, 1),
(8, 'الدفاع المدني', 'Civil Defense', '125', NULL, NULL, 'لبنان', 1, 1, 1),
(9, 'الأمن العام', 'General Security', '1717', NULL, NULL, 'لبنان', 1, 0, 1),
(10, 'الطوارئ الطبية', 'Medical Emergency', '140', NULL, NULL, 'لبنان', 1, 1, 1)
ON DUPLICATE KEY UPDATE `name_ar` = VALUES(`name_ar`);

