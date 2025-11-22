-- ============================================
-- تحديث نظام الشكاوى لربطه بحساب المواطن
-- ============================================

-- 1. إضافة عمود citizen_id إلى جدول complaints
-- التحقق من وجود الأعمدة أولاً
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'complaints' 
                   AND COLUMN_NAME = 'citizen_id');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `complaints` ADD COLUMN `citizen_id` INT(11) NULL COMMENT ''معرف حساب المواطن'' AFTER `id`',
    'SELECT ''Column citizen_id already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'complaints' 
                   AND COLUMN_NAME = 'complaint_number');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `complaints` ADD COLUMN `complaint_number` VARCHAR(50) NULL COMMENT ''رقم الشكوى للتتبع'' AFTER `citizen_id`',
    'SELECT ''Column complaint_number already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إضافة الفهارس
CREATE INDEX IF NOT EXISTS `idx_citizen_id` ON `complaints` (`citizen_id`);
CREATE INDEX IF NOT EXISTS `idx_complaint_number` ON `complaints` (`complaint_number`);

-- إضافة Foreign Key (بعد التحقق من عدم وجوده)
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                  WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'complaints' 
                  AND CONSTRAINT_NAME = 'fk_complaints_citizen');

SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE `complaints` ADD CONSTRAINT `fk_complaints_citizen` FOREIGN KEY (`citizen_id`) REFERENCES `citizens_accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT ''Foreign key fk_complaints_citizen already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. إنشاء جدول تحديثات الشكاوى (مثل request_updates)
CREATE TABLE IF NOT EXISTS `complaint_updates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `complaint_id` int(11) NOT NULL,
  `updated_by` int(11) NULL COMMENT 'معرف الموظف المسؤول',
  `update_type` enum('status_change','comment','municipality_response','admin_note','data_update') NOT NULL DEFAULT 'comment',
  `update_text` text NOT NULL COMMENT 'نص التحديث',
  `is_visible_to_citizen` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'مرئي للمواطن',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_complaint_id` (`complaint_id`),
  INDEX `idx_updated_by` (`updated_by`),
  INDEX `idx_update_type` (`update_type`),
  INDEX `idx_visible_to_citizen` (`is_visible_to_citizen`),
  INDEX `idx_created_at` (`created_at`),
  CONSTRAINT `fk_complaint_updates_complaint` 
    FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_complaint_updates_user` 
    FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) 
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. إنشاء view للشكاوى مع المعلومات المرتبطة
CREATE OR REPLACE VIEW `complaints_detailed` AS
SELECT 
    c.*,
    ca.phone as citizen_phone_from_account,
    ca.name as citizen_name_from_account,
    ca.telegram_chat_id,
    ca.telegram_username,
    u.full_name as assigned_user_name,
    COUNT(cu.id) as updates_count
FROM `complaints` c
LEFT JOIN `citizens_accounts` ca ON c.citizen_id = ca.id
LEFT JOIN `users` u ON c.assigned_to = u.id
LEFT JOIN `complaint_updates` cu ON c.id = cu.complaint_id AND cu.is_visible_to_citizen = 1
GROUP BY c.id;

-- 4. إنشاء stored procedure لتحديث حالة الشكوى مع إضافة تحديث
DROP PROCEDURE IF EXISTS UpdateComplaintStatus;

DELIMITER //

CREATE PROCEDURE UpdateComplaintStatus(
    IN p_complaint_id INT,
    IN p_new_status VARCHAR(50),
    IN p_user_id INT,
    IN p_comment TEXT,
    IN p_visible_to_citizen BOOLEAN
)
BEGIN
    DECLARE v_old_status VARCHAR(50);
    DECLARE v_complaint_exists INT DEFAULT 0;
    DECLARE v_update_type VARCHAR(50);
    DECLARE v_update_text TEXT;
    
    -- التحقق من وجود الشكوى
    SELECT COUNT(*) INTO v_complaint_exists 
    FROM complaints 
    WHERE id = p_complaint_id;
    
    IF v_complaint_exists = 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'الشكوى غير موجودة';
    END IF;
    
    -- الحصول على الحالة القديمة
    SELECT status INTO v_old_status 
    FROM complaints 
    WHERE id = p_complaint_id;
    
    -- تحديد نوع التحديث
    IF v_old_status != p_new_status THEN
        SET v_update_type = 'status_change';
    ELSE
        SET v_update_type = 'comment';
    END IF;
    
    -- بناء نص التحديث
    IF v_old_status != p_new_status THEN
        IF p_comment IS NOT NULL AND p_comment != '' THEN
            SET v_update_text = CONCAT('تم تغيير الحالة من ''', v_old_status, ''' إلى ''', p_new_status, '''\n\n', p_comment);
        ELSE
            SET v_update_text = CONCAT('تم تغيير الحالة من ''', v_old_status, ''' إلى ''', p_new_status, '''');
        END IF;
    ELSE
        SET v_update_text = p_comment;
    END IF;
    
    -- تحديث حالة الشكوى
    UPDATE complaints 
    SET status = p_new_status, 
        updated_at = CURRENT_TIMESTAMP,
        assigned_to = IFNULL(p_user_id, assigned_to)
    WHERE id = p_complaint_id;
    
    -- إضافة تحديث إذا كان هناك تعليق أو تغيير في الحالة
    IF (p_comment IS NOT NULL AND p_comment != '') OR v_old_status != p_new_status THEN
        INSERT INTO complaint_updates 
        (complaint_id, updated_by, update_type, update_text, is_visible_to_citizen, created_at)
        VALUES 
        (p_complaint_id, 
         p_user_id, 
         v_update_type,
         v_update_text,
         IFNULL(p_visible_to_citizen, 1),
         NOW());
    END IF;
END //

DELIMITER ;

-- 5. إنشاء stored procedure لإضافة رد من البلدية
DROP PROCEDURE IF EXISTS AddComplaintResponse;

DELIMITER //

CREATE PROCEDURE AddComplaintResponse(
    IN p_complaint_id INT,
    IN p_user_id INT,
    IN p_response_text TEXT,
    IN p_visible_to_citizen BOOLEAN
)
BEGIN
    DECLARE v_complaint_exists INT DEFAULT 0;
    
    -- التحقق من وجود الشكوى
    SELECT COUNT(*) INTO v_complaint_exists 
    FROM complaints 
    WHERE id = p_complaint_id;
    
    IF v_complaint_exists = 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'الشكوى غير موجودة';
    END IF;
    
    IF p_response_text IS NULL OR p_response_text = '' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'نص الرد مطلوب';
    END IF;
    
    -- تحديث حقل response في جدول complaints
    UPDATE complaints 
    SET response = p_response_text,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = p_complaint_id;
    
    -- إضافة تحديث
    INSERT INTO complaint_updates 
    (complaint_id, updated_by, update_type, update_text, is_visible_to_citizen, created_at)
    VALUES 
    (p_complaint_id, p_user_id, 'municipality_response', p_response_text, IFNULL(p_visible_to_citizen, 1), NOW());
END //

DELIMITER ;

-- 6. إنشاء trigger لتوليد رقم الشكوى تلقائياً
DROP TRIGGER IF EXISTS tr_generate_complaint_number;

DELIMITER //

CREATE TRIGGER tr_generate_complaint_number
BEFORE INSERT ON complaints
FOR EACH ROW
BEGIN
    IF NEW.complaint_number IS NULL OR NEW.complaint_number = '' THEN
        SET NEW.complaint_number = CONCAT('SHK-', YEAR(NOW()), '-', LPAD((SELECT COALESCE(MAX(CAST(SUBSTRING(complaint_number, 9) AS UNSIGNED)), 0) + 1 FROM complaints WHERE complaint_number LIKE CONCAT('SHK-', YEAR(NOW()), '-%')), 5, '0'));
    END IF;
END //

DELIMITER ;

-- 7. إضافة إعدادات النظام للشكاوى
INSERT IGNORE INTO `website_settings` (`setting_key`, `setting_value`, `setting_description`) VALUES
('complaints_enabled', '1', 'تفعيل نظام الشكاوى للمواطنين'),
('complaint_notification_telegram', '1', 'تفعيل إشعارات Telegram للشكاوى'),
('complaint_auto_assign', '0', 'التوزيع التلقائي للشكاوى');

-- 8. تحديث الشكاوى الموجودة لربطها بحسابات المواطنين (إذا كان رقم الهاتف متطابق)
-- ملاحظة: قد يكون العمود citizen_phone أو complainant_phone حسب البنية
UPDATE complaints c
INNER JOIN citizens_accounts ca ON (c.citizen_phone = ca.phone OR c.complainant_phone = ca.phone)
SET c.citizen_id = ca.id
WHERE c.citizen_id IS NULL AND (c.citizen_phone IS NOT NULL AND c.citizen_phone != '' OR c.complainant_phone IS NOT NULL AND c.complainant_phone != '');

