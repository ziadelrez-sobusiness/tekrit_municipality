-- تحديث نظام طلبات المواطنين المحسن
-- بناءً على الكلاسات الجديدة والملفات المرفقة

-- إنشاء جدول أنواع الطلبات المحسن
CREATE TABLE IF NOT EXISTS `request_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_name` varchar(255) NOT NULL COMMENT 'اسم نوع الطلب',
  `name_ar` varchar(255) NOT NULL COMMENT 'الاسم بالعربية',
  `name_en` varchar(255) NULL COMMENT 'الاسم بالإنجليزية',
  `type_description` text NULL COMMENT 'وصف نوع الطلب',
  `form_fields` json NULL COMMENT 'الحقول الديناميكية للنموذج',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'حالة التفعيل',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type_name` (`type_name`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إدراج أنواع الطلبات الأساسية
INSERT INTO `request_types` (`type_name`, `name_ar`, `type_description`, `is_active`) VALUES
('إفادة سكن', 'إفادة سكن', 'طلب الحصول على إفادة سكن من البلدية', 1),
('شكوى', 'شكوى', 'تقديم شكوى أو اعتراض', 1),
('بلاغ أعطال', 'بلاغ أعطال', 'الإبلاغ عن أعطال في الخدمات العامة', 1),
('استشارة هندسية', 'استشارة هندسية', 'طلب استشارة هندسية', 1),
('طلب خدمة', 'طلب خدمة', 'طلب خدمة عامة من البلدية', 1),
('اقتراح', 'اقتراح', 'تقديم اقتراح لتحسين الخدمات', 1),
('المساهمة في المشروع', 'المساهمة في المشروع', 'طلب المساهمة في مشروع تنموي', 1),
('أخرى', 'أخرى', 'أنواع طلبات أخرى', 1);

-- تحديث جدول طلبات المواطنين
ALTER TABLE `citizen_requests` 
ADD COLUMN IF NOT EXISTS `request_type_id` int(11) NULL AFTER `request_type`,
ADD COLUMN IF NOT EXISTS `status` enum('جديد','قيد المراجعة','قيد التنفيذ','مكتمل','مرفوض','معلق') NOT NULL DEFAULT 'جديد' AFTER `request_status`,
ADD COLUMN IF NOT EXISTS `estimated_completion_date` date NULL COMMENT 'التاريخ المتوقع للإنجاز',
ADD COLUMN IF NOT EXISTS `actual_completion_date` datetime NULL COMMENT 'التاريخ الفعلي للإنجاز',
ADD INDEX `idx_request_type_id` (`request_type_id`),
ADD INDEX `idx_status` (`status`),
ADD INDEX `idx_priority` (`priority_level`),
ADD INDEX `idx_created_at` (`created_at`);

-- إضافة المفتاح الخارجي لأنواع الطلبات
ALTER TABLE `citizen_requests` 
ADD CONSTRAINT `fk_citizen_requests_request_type` 
FOREIGN KEY (`request_type_id`) REFERENCES `request_types` (`id`) 
ON DELETE SET NULL ON UPDATE CASCADE;

-- إنشاء جدول بيانات النماذج الديناميكية
CREATE TABLE IF NOT EXISTS `request_form_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `field_name` varchar(255) NOT NULL COMMENT 'اسم الحقل',
  `field_value` text NULL COMMENT 'قيمة الحقل',
  `field_type` varchar(50) NULL DEFAULT 'string' COMMENT 'نوع الحقل',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_request_id` (`request_id`),
  INDEX `idx_field_name` (`field_name`),
  CONSTRAINT `fk_request_form_data_request` 
    FOREIGN KEY (`request_id`) REFERENCES `citizen_requests` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إنشاء جدول المستندات المرفقة
CREATE TABLE IF NOT EXISTS `request_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `document_name` varchar(255) NOT NULL COMMENT 'اسم المستند',
  `original_filename` varchar(255) NOT NULL COMMENT 'اسم الملف الأصلي',
  `file_path` varchar(500) NOT NULL COMMENT 'مسار الملف',
  `file_size` int(11) NOT NULL DEFAULT 0 COMMENT 'حجم الملف بالبايت',
  `file_type` varchar(50) NULL COMMENT 'نوع الملف',
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_request_id` (`request_id`),
  INDEX `idx_file_type` (`file_type`),
  CONSTRAINT `fk_request_documents_request` 
    FOREIGN KEY (`request_id`) REFERENCES `citizen_requests` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- تحديث جدول تحديثات الطلبات (إذا لم يكن موجوداً)
CREATE TABLE IF NOT EXISTS `request_updates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `updated_by` int(11) NULL COMMENT 'معرف الموظف المسؤول',
  `update_type` enum('status_change','comment','municipality_response','admin_note','data_update') NOT NULL DEFAULT 'comment',
  `update_text` text NOT NULL COMMENT 'نص التحديث',
  `is_visible_to_citizen` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'مرئي للمواطن',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_request_id` (`request_id`),
  INDEX `idx_updated_by` (`updated_by`),
  INDEX `idx_update_type` (`update_type`),
  INDEX `idx_visible_to_citizen` (`is_visible_to_citizen`),
  CONSTRAINT `fk_request_updates_request` 
    FOREIGN KEY (`request_id`) REFERENCES `citizen_requests` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_request_updates_user` 
    FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) 
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- تحديث أسماء الأعمدة في الجدول الحالي (إذا كانت مختلفة)
UPDATE `citizen_requests` SET `status` = 'جديد' WHERE `status` IS NULL OR `status` = '';
UPDATE `citizen_requests` SET `status` = 'قيد المراجعة' WHERE `request_status` = 'قيد المراجعة';
UPDATE `citizen_requests` SET `status` = 'قيد التنفيذ' WHERE `request_status` = 'قيد التنفيذ';
UPDATE `citizen_requests` SET `status` = 'مكتمل' WHERE `request_status` = 'مكتمل';
UPDATE `citizen_requests` SET `status` = 'مرفوض' WHERE `request_status` = 'مرفوض';
UPDATE `citizen_requests` SET `status` = 'معلق' WHERE `request_status` = 'معلق';

-- ربط الطلبات الموجودة بأنواع الطلبات
UPDATE `citizen_requests` cr 
SET `request_type_id` = (
    SELECT rt.id 
    FROM `request_types` rt 
    WHERE rt.type_name = cr.request_type 
    LIMIT 1
) 
WHERE `request_type_id` IS NULL AND `request_type` IS NOT NULL;

-- إنشاء فهارس إضافية لتحسين الأداء
CREATE INDEX IF NOT EXISTS `idx_citizen_name` ON `citizen_requests` (`citizen_name`);
CREATE INDEX IF NOT EXISTS `idx_citizen_phone` ON `citizen_requests` (`citizen_phone`);
CREATE INDEX IF NOT EXISTS `idx_tracking_number` ON `citizen_requests` (`tracking_number`);
CREATE INDEX IF NOT EXISTS `idx_project_id` ON `citizen_requests` (`project_id`);

-- إنشاء view للطلبات مع المعلومات المرتبطة
CREATE OR REPLACE VIEW `citizen_requests_detailed` AS
SELECT 
    cr.*,
    rt.name_ar as request_type_name,
    rt.type_description,
    dp.project_name,
    d.department_name,
    u.full_name as assigned_user_name,
    COUNT(rd.id) as documents_count,
    COUNT(ru.id) as updates_count
FROM `citizen_requests` cr
LEFT JOIN `request_types` rt ON cr.request_type_id = rt.id
LEFT JOIN `development_projects` dp ON cr.project_id = dp.id
LEFT JOIN `departments` d ON cr.assigned_to_department_id = d.id
LEFT JOIN `users` u ON cr.assigned_to_user_id = u.id
LEFT JOIN `request_documents` rd ON cr.id = rd.request_id
LEFT JOIN `request_updates` ru ON cr.id = ru.request_id
GROUP BY cr.id;

-- إضافة إعدادات النظام للطلبات
INSERT IGNORE INTO `website_settings` (`setting_key`, `setting_value`, `setting_description`) VALUES
('citizen_requests_enabled', '1', 'تفعيل نظام طلبات المواطنين'),
('max_file_upload_size', '5242880', 'الحد الأقصى لحجم الملف المرفوع (بالبايت)'),
('allowed_file_types', 'pdf,jpg,jpeg,png,doc,docx', 'أنواع الملفات المسموحة'),
('auto_assign_requests', '0', 'التوزيع التلقائي للطلبات'),
('request_notification_email', '', 'البريد الإلكتروني لإشعارات الطلبات الجديدة');

-- إنشاء stored procedures مفيدة
DELIMITER //

-- procedure للحصول على إحصائيات الطلبات
CREATE PROCEDURE IF NOT EXISTS GetRequestStatistics()
BEGIN
    SELECT 
        COUNT(*) as total_requests,
        COUNT(CASE WHEN status = 'جديد' THEN 1 END) as new_requests,
        COUNT(CASE WHEN status = 'قيد المراجعة' THEN 1 END) as in_review_requests,
        COUNT(CASE WHEN status = 'قيد التنفيذ' THEN 1 END) as in_progress_requests,
        COUNT(CASE WHEN status = 'مكتمل' THEN 1 END) as completed_requests,
        COUNT(CASE WHEN status = 'مرفوض' THEN 1 END) as rejected_requests,
        COUNT(CASE WHEN status = 'معلق' THEN 1 END) as pending_requests,
        COUNT(CASE WHEN priority_level = 'عاجل' THEN 1 END) as urgent_requests,
        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_requests,
        COUNT(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 END) as this_month_requests
    FROM citizen_requests;
END //

-- procedure لتحديث حالة الطلب مع إضافة تحديث
CREATE PROCEDURE IF NOT EXISTS UpdateRequestStatus(
    IN p_request_id INT,
    IN p_new_status VARCHAR(50),
    IN p_user_id INT,
    IN p_comment TEXT
)
BEGIN
    DECLARE v_old_status VARCHAR(50);
    DECLARE v_update_text TEXT;
    
    -- الحصول على الحالة الحالية
    SELECT status INTO v_old_status FROM citizen_requests WHERE id = p_request_id;
    
    -- تحديث الحالة
    UPDATE citizen_requests 
    SET status = p_new_status,
        actual_completion_date = CASE WHEN p_new_status = 'مكتمل' THEN NOW() ELSE actual_completion_date END
    WHERE id = p_request_id;
    
    -- إنشاء نص التحديث
    SET v_update_text = CONCAT('تغيير الحالة من "', v_old_status, '" إلى "', p_new_status, '"');
    IF p_comment IS NOT NULL AND p_comment != '' THEN
        SET v_update_text = CONCAT(v_update_text, ': ', p_comment);
    END IF;
    
    -- إضافة سجل التحديث
    INSERT INTO request_updates (request_id, updated_by, update_type, update_text)
    VALUES (p_request_id, p_user_id, 'status_change', v_update_text);
END //

DELIMITER ;

-- إنشاء triggers مفيدة
DELIMITER //

-- trigger لتسجيل إنشاء طلب جديد
CREATE TRIGGER IF NOT EXISTS after_citizen_request_insert
AFTER INSERT ON citizen_requests
FOR EACH ROW
BEGIN
    INSERT INTO request_updates (request_id, update_type, update_text, is_visible_to_citizen)
    VALUES (NEW.id, 'status_change', 'تم إنشاء الطلب بنجاح', 1);
END //

DELIMITER ;

-- تحسين أداء الجداول
OPTIMIZE TABLE citizen_requests;
OPTIMIZE TABLE request_types;
OPTIMIZE TABLE request_form_data;
OPTIMIZE TABLE request_documents;
OPTIMIZE TABLE request_updates; 