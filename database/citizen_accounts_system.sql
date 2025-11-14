-- ========================================
-- نظام الحساب الشخصي للمواطن
-- بلدية تكريت - عكار، شمال لبنان
-- ========================================
-- التاريخ: 2025-11-10
-- الوصف: إنشاء جداول نظام الحساب الشخصي مع إشعارات WhatsApp
-- ========================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ========================================
-- 1. جدول حسابات المواطنين
-- ========================================
CREATE TABLE IF NOT EXISTS `citizens_accounts` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `phone` VARCHAR(20) UNIQUE NOT NULL COMMENT 'رقم الهاتف (فريد)',
    `name` VARCHAR(100) NOT NULL COMMENT 'اسم المواطن',
    `email` VARCHAR(100) NULL COMMENT 'البريد الإلكتروني (اختياري)',
    `address` VARCHAR(255) NULL COMMENT 'العنوان',
    `national_id` VARCHAR(50) NULL COMMENT 'الرقم الوطني',
    `whatsapp_notifications` BOOLEAN DEFAULT 1 COMMENT 'تفعيل إشعارات WhatsApp',
    `website_notifications` BOOLEAN DEFAULT 1 COMMENT 'تفعيل إشعارات الموقع',
    `is_active` BOOLEAN DEFAULT 1 COMMENT 'الحساب نشط',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإنشاء',
    `last_login` TIMESTAMP NULL COMMENT 'آخر تسجيل دخول',
    `login_count` INT DEFAULT 0 COMMENT 'عدد مرات تسجيل الدخول',
    INDEX `idx_phone` (`phone`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='حسابات المواطنين';

-- ========================================
-- 2. جدول الروابط السحرية (Magic Links)
-- ========================================
CREATE TABLE IF NOT EXISTS `magic_links` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `citizen_id` INT NOT NULL COMMENT 'معرف المواطن',
    `token` VARCHAR(64) UNIQUE NOT NULL COMMENT 'الرمز الفريد للرابط',
    `phone` VARCHAR(20) NOT NULL COMMENT 'رقم الهاتف',
    `used` BOOLEAN DEFAULT 0 COMMENT 'تم استخدام الرابط',
    `used_at` TIMESTAMP NULL COMMENT 'تاريخ الاستخدام',
    `ip_address` VARCHAR(45) NULL COMMENT 'عنوان IP',
    `user_agent` TEXT NULL COMMENT 'معلومات المتصفح',
    `expires_at` TIMESTAMP NOT NULL COMMENT 'تاريخ انتهاء الصلاحية',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإنشاء',
    INDEX `idx_token` (`token`),
    INDEX `idx_citizen` (`citizen_id`),
    INDEX `idx_expires` (`expires_at`),
    INDEX `idx_used` (`used`),
    FOREIGN KEY (`citizen_id`) REFERENCES `citizens_accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='روابط الدخول السحرية';

-- ========================================
-- 3. جدول رسائل البلدية للمواطنين
-- ========================================
CREATE TABLE IF NOT EXISTS `citizen_messages` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `citizen_id` INT NULL COMMENT 'معرف المواطن (NULL = رسالة عامة)',
    `message_type` ENUM('عام', 'خاص', 'تحديث طلب', 'إشعار', 'تذكير') DEFAULT 'عام' COMMENT 'نوع الرسالة',
    `title` VARCHAR(200) NOT NULL COMMENT 'عنوان الرسالة',
    `message` TEXT NOT NULL COMMENT 'نص الرسالة',
    `request_id` INT NULL COMMENT 'معرف الطلب (إذا كانت مرتبطة بطلب)',
    `priority` ENUM('عادي', 'مهم', 'عاجل') DEFAULT 'عادي' COMMENT 'الأولوية',
    `is_read` BOOLEAN DEFAULT 0 COMMENT 'تم القراءة',
    `read_at` TIMESTAMP NULL COMMENT 'تاريخ القراءة',
    `sent_via_whatsapp` BOOLEAN DEFAULT 0 COMMENT 'تم الإرسال عبر WhatsApp',
    `whatsapp_sent_at` TIMESTAMP NULL COMMENT 'تاريخ الإرسال عبر WhatsApp',
    `created_by` INT NULL COMMENT 'معرف الموظف المرسل',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإنشاء',
    INDEX `idx_citizen` (`citizen_id`),
    INDEX `idx_read` (`is_read`),
    INDEX `idx_type` (`message_type`),
    INDEX `idx_request` (`request_id`),
    INDEX `idx_priority` (`priority`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`citizen_id`) REFERENCES `citizens_accounts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`request_id`) REFERENCES `citizen_requests`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='رسائل البلدية للمواطنين';

-- ========================================
-- 4. جدول سجل رسائل WhatsApp
-- ========================================
CREATE TABLE IF NOT EXISTS `whatsapp_log` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `phone` VARCHAR(20) NOT NULL COMMENT 'رقم الهاتف المستلم',
    `message` TEXT NOT NULL COMMENT 'نص الرسالة',
    `message_type` VARCHAR(50) NULL COMMENT 'نوع الرسالة',
    `request_id` INT NULL COMMENT 'معرف الطلب',
    `citizen_id` INT NULL COMMENT 'معرف المواطن',
    `status` ENUM('pending', 'sent', 'failed', 'delivered', 'read') DEFAULT 'pending' COMMENT 'حالة الإرسال',
    `error_message` TEXT NULL COMMENT 'رسالة الخطأ (إن وجدت)',
    `sent_at` TIMESTAMP NULL COMMENT 'تاريخ الإرسال',
    `delivered_at` TIMESTAMP NULL COMMENT 'تاريخ التسليم',
    `read_at` TIMESTAMP NULL COMMENT 'تاريخ القراءة',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإنشاء',
    INDEX `idx_phone` (`phone`),
    INDEX `idx_status` (`status`),
    INDEX `idx_request` (`request_id`),
    INDEX `idx_citizen` (`citizen_id`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`request_id`) REFERENCES `citizen_requests`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`citizen_id`) REFERENCES `citizens_accounts`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='سجل رسائل WhatsApp';

-- ========================================
-- 5. جدول إعدادات الإشعارات للمواطنين
-- ========================================
CREATE TABLE IF NOT EXISTS `notification_preferences` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `citizen_id` INT NOT NULL COMMENT 'معرف المواطن',
    `whatsapp_enabled` BOOLEAN DEFAULT 1 COMMENT 'تفعيل WhatsApp',
    `website_enabled` BOOLEAN DEFAULT 1 COMMENT 'تفعيل إشعارات الموقع',
    `notify_on_status_change` BOOLEAN DEFAULT 1 COMMENT 'إشعار عند تغيير حالة الطلب',
    `notify_on_new_message` BOOLEAN DEFAULT 1 COMMENT 'إشعار عند رسالة جديدة',
    `notify_on_general_news` BOOLEAN DEFAULT 1 COMMENT 'إشعار بالأخبار العامة',
    `notify_on_completion` BOOLEAN DEFAULT 1 COMMENT 'إشعار عند إنجاز الطلب',
    `notify_on_reminder` BOOLEAN DEFAULT 1 COMMENT 'إشعار بالتذكيرات',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ التحديث',
    UNIQUE KEY `unique_citizen` (`citizen_id`),
    FOREIGN KEY (`citizen_id`) REFERENCES `citizens_accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='إعدادات الإشعارات';

-- ========================================
-- 6. جدول جلسات المواطنين (Sessions)
-- ========================================
CREATE TABLE IF NOT EXISTS `citizen_sessions` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `citizen_id` INT NOT NULL COMMENT 'معرف المواطن',
    `session_token` VARCHAR(64) UNIQUE NOT NULL COMMENT 'رمز الجلسة',
    `ip_address` VARCHAR(45) NULL COMMENT 'عنوان IP',
    `user_agent` TEXT NULL COMMENT 'معلومات المتصفح',
    `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'آخر نشاط',
    `expires_at` TIMESTAMP NOT NULL COMMENT 'تاريخ انتهاء الجلسة',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإنشاء',
    INDEX `idx_citizen` (`citizen_id`),
    INDEX `idx_token` (`session_token`),
    INDEX `idx_expires` (`expires_at`),
    FOREIGN KEY (`citizen_id`) REFERENCES `citizens_accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جلسات تسجيل دخول المواطنين';

-- ========================================
-- 7. إضافة إعدادات WhatsApp لجدول إعدادات الموقع
-- ========================================
INSERT INTO `website_settings` (`setting_key`, `setting_value`, `setting_description`, `setting_type`) VALUES
('whatsapp_enabled', '1', 'تفعيل إشعارات WhatsApp', 'boolean'),
('whatsapp_business_number', '', 'رقم WhatsApp للبلدية (مثال: 96176123456)', 'text'),
('whatsapp_api_method', 'manual', 'طريقة الإرسال: manual, api, webhook', 'select'),
('whatsapp_welcome_template', 'مرحباً {name}!\n\n✅ تم استلام طلبك بنجاح\n📋 نوع الطلب: {request_type}\n🔢 رقم التتبع: {tracking_number}\n📅 التاريخ: {date}\n\n🔐 للدخول لحسابك الشخصي:\n👉 {magic_link}\n\nأو استخدم:\n📱 الهاتف: {phone}\n🔑 الرمز: {code}\n\n━━━━━━━━━━━━━━━━━━━\n💚 شكراً لثقتكم\n🏛️ بلدية تكريت - في خدمتكم', 'textarea'),
('whatsapp_status_update_template', '🏛️ بلدية تكريت\n\n📢 تحديث على طلبك\n\n🔢 {tracking_number}\n📋 {request_type}\n\n✅ الحالة الجديدة:\n{status}\n\n📝 التحديث:\n{update_text}\n\n👉 للتفاصيل:\n{magic_link}\n\n━━━━━━━━━━━━━━━━━━━', 'textarea'),
('whatsapp_completion_template', '🏛️ بلدية تكريت\n\n✅ طلبك جاهز!\n\n🔢 {tracking_number}\n📋 {request_type}\n\n📍 يرجى المرور على مكتب البلدية لاستلام:\n{request_title}\n\n🕐 أوقات الدوام:\nالإثنين - الجمعة\n8:00 ص - 2:00 م\n\n📞 للاستفسار: {municipality_phone}\n\n━━━━━━━━━━━━━━━━━━━\n💚 شكراً لثقتكم', 'textarea'),
('whatsapp_reminder_template', '🏛️ بلدية تكريت\n\n⏰ تذكير\n\n{reminder_text}\n\n🔢 رقم الطلب: {tracking_number}\n\n👉 للتفاصيل:\n{magic_link}\n\n━━━━━━━━━━━━━━━━━━━', 'textarea'),
('whatsapp_general_message_template', '🏛️ بلدية تكريت\n\n📢 {title}\n\n{message}\n\n━━━━━━━━━━━━━━━━━━━\n💚 بلدية تكريت - في خدمتكم', 'textarea'),
('municipality_phone', '06-123-456', 'رقم هاتف البلدية', 'text'),
('municipality_whatsapp_name', 'بلدية تكريت', 'اسم حساب WhatsApp Business', 'text')
ON DUPLICATE KEY UPDATE 
    `setting_value` = VALUES(`setting_value`),
    `setting_description` = VALUES(`setting_description`);

-- ========================================
-- 8. إنشاء فهارس إضافية لتحسين الأداء
-- ========================================

-- فهرس مركب على citizen_requests للبحث السريع
ALTER TABLE `citizen_requests` 
ADD INDEX IF NOT EXISTS `idx_phone_tracking` (`citizen_phone`, `tracking_number`);

-- فهرس على حالة الطلب وتاريخ الإنشاء
ALTER TABLE `citizen_requests` 
ADD INDEX IF NOT EXISTS `idx_status_created` (`status`, `created_at`);

-- ========================================
-- 9. إنشاء Views مفيدة
-- ========================================

-- View: ملخص حسابات المواطنين مع عدد الطلبات
CREATE OR REPLACE VIEW `v_citizens_summary` AS
SELECT 
    ca.id,
    ca.phone,
    ca.name,
    ca.email,
    ca.whatsapp_notifications,
    ca.is_active,
    ca.created_at,
    ca.last_login,
    ca.login_count,
    COUNT(DISTINCT cr.id) as total_requests,
    SUM(CASE WHEN cr.status = 'جديد' THEN 1 ELSE 0 END) as new_requests,
    SUM(CASE WHEN cr.status IN ('قيد المراجعة', 'قيد التنفيذ') THEN 1 ELSE 0 END) as active_requests,
    SUM(CASE WHEN cr.status = 'مكتمل' THEN 1 ELSE 0 END) as completed_requests,
    COUNT(DISTINCT cm.id) as total_messages,
    SUM(CASE WHEN cm.is_read = 0 THEN 1 ELSE 0 END) as unread_messages
FROM citizens_accounts ca
LEFT JOIN citizen_requests cr ON ca.phone = cr.citizen_phone
LEFT JOIN citizen_messages cm ON ca.id = cm.citizen_id
GROUP BY ca.id;

-- View: رسائل المواطنين مع تفاصيل المرسل
CREATE OR REPLACE VIEW `v_citizen_messages_detailed` AS
SELECT 
    cm.*,
    ca.name as citizen_name,
    ca.phone as citizen_phone,
    u.full_name as sender_name,
    cr.tracking_number,
    cr.request_title
FROM citizen_messages cm
LEFT JOIN citizens_accounts ca ON cm.citizen_id = ca.id
LEFT JOIN users u ON cm.created_by = u.id
LEFT JOIN citizen_requests cr ON cm.request_id = cr.id;

-- View: سجل WhatsApp مع تفاصيل المواطن والطلب
CREATE OR REPLACE VIEW `v_whatsapp_log_detailed` AS
SELECT 
    wl.*,
    ca.name as citizen_name,
    cr.tracking_number,
    cr.request_title,
    cr.status as request_status
FROM whatsapp_log wl
LEFT JOIN citizens_accounts ca ON wl.citizen_id = ca.id
LEFT JOIN citizen_requests cr ON wl.request_id = cr.id;

-- ========================================
-- 10. إنشاء Stored Procedures مفيدة
-- ========================================

DELIMITER //

-- Procedure: إنشاء أو جلب حساب مواطن
CREATE PROCEDURE IF NOT EXISTS `sp_get_or_create_citizen_account`(
    IN p_phone VARCHAR(20),
    IN p_name VARCHAR(100),
    IN p_email VARCHAR(100),
    IN p_address VARCHAR(255),
    IN p_national_id VARCHAR(50)
)
BEGIN
    DECLARE v_citizen_id INT;
    
    -- البحث عن الحساب
    SELECT id INTO v_citizen_id 
    FROM citizens_accounts 
    WHERE phone = p_phone 
    LIMIT 1;
    
    -- إذا لم يوجد، إنشاء حساب جديد
    IF v_citizen_id IS NULL THEN
        INSERT INTO citizens_accounts (phone, name, email, address, national_id)
        VALUES (p_phone, p_name, p_email, p_address, p_national_id);
        
        SET v_citizen_id = LAST_INSERT_ID();
        
        -- إنشاء إعدادات الإشعارات الافتراضية
        INSERT INTO notification_preferences (citizen_id)
        VALUES (v_citizen_id);
    ELSE
        -- تحديث البيانات إذا كانت أحدث
        UPDATE citizens_accounts 
        SET 
            name = COALESCE(NULLIF(p_name, ''), name),
            email = COALESCE(NULLIF(p_email, ''), email),
            address = COALESCE(NULLIF(p_address, ''), address),
            national_id = COALESCE(NULLIF(p_national_id, ''), national_id)
        WHERE id = v_citizen_id;
    END IF;
    
    SELECT v_citizen_id as citizen_id;
END //

-- Procedure: تنظيف الروابط المنتهية
CREATE PROCEDURE IF NOT EXISTS `sp_cleanup_expired_links`()
BEGIN
    -- حذف الروابط المنتهية والمستخدمة
    DELETE FROM magic_links 
    WHERE expires_at < NOW() 
    OR (used = 1 AND used_at < DATE_SUB(NOW(), INTERVAL 30 DAY));
    
    -- حذف الجلسات المنتهية
    DELETE FROM citizen_sessions 
    WHERE expires_at < NOW();
    
    SELECT ROW_COUNT() as deleted_records;
END //

-- Procedure: إحصائيات المواطن
CREATE PROCEDURE IF NOT EXISTS `sp_get_citizen_stats`(
    IN p_citizen_id INT
)
BEGIN
    SELECT 
        (SELECT COUNT(*) FROM citizen_requests WHERE citizen_phone = (SELECT phone FROM citizens_accounts WHERE id = p_citizen_id)) as total_requests,
        (SELECT COUNT(*) FROM citizen_requests WHERE citizen_phone = (SELECT phone FROM citizens_accounts WHERE id = p_citizen_id) AND status = 'جديد') as new_requests,
        (SELECT COUNT(*) FROM citizen_requests WHERE citizen_phone = (SELECT phone FROM citizens_accounts WHERE id = p_citizen_id) AND status IN ('قيد المراجعة', 'قيد التنفيذ')) as active_requests,
        (SELECT COUNT(*) FROM citizen_requests WHERE citizen_phone = (SELECT phone FROM citizens_accounts WHERE id = p_citizen_id) AND status = 'مكتمل') as completed_requests,
        (SELECT COUNT(*) FROM citizen_messages WHERE citizen_id = p_citizen_id) as total_messages,
        (SELECT COUNT(*) FROM citizen_messages WHERE citizen_id = p_citizen_id AND is_read = 0) as unread_messages,
        (SELECT AVG(DATEDIFF(completion_date, created_at)) FROM citizen_requests WHERE citizen_phone = (SELECT phone FROM citizens_accounts WHERE id = p_citizen_id) AND completion_date IS NOT NULL) as avg_completion_days;
END //

DELIMITER ;

-- ========================================
-- 11. إنشاء Triggers
-- ========================================

DELIMITER //

-- Trigger: تحديث عداد تسجيل الدخول
CREATE TRIGGER IF NOT EXISTS `tr_update_login_count`
AFTER INSERT ON `citizen_sessions`
FOR EACH ROW
BEGIN
    UPDATE citizens_accounts 
    SET 
        last_login = NEW.created_at,
        login_count = login_count + 1
    WHERE id = NEW.citizen_id;
END //

-- Trigger: تسجيل رسالة WhatsApp عند إنشاء رسالة للمواطن
CREATE TRIGGER IF NOT EXISTS `tr_log_citizen_message`
AFTER INSERT ON `citizen_messages`
FOR EACH ROW
BEGIN
    DECLARE v_phone VARCHAR(20);
    DECLARE v_whatsapp_enabled BOOLEAN;
    
    IF NEW.citizen_id IS NOT NULL THEN
        -- جلب رقم الهاتف وإعدادات WhatsApp
        SELECT ca.phone, COALESCE(np.whatsapp_enabled, 1)
        INTO v_phone, v_whatsapp_enabled
        FROM citizens_accounts ca
        LEFT JOIN notification_preferences np ON ca.id = np.citizen_id
        WHERE ca.id = NEW.citizen_id;
        
        -- إذا كان WhatsApp مفعل، تسجيل في سجل الإرسال
        IF v_whatsapp_enabled = 1 AND NEW.sent_via_whatsapp = 1 THEN
            INSERT INTO whatsapp_log (
                phone, 
                message, 
                message_type, 
                request_id, 
                citizen_id, 
                status
            ) VALUES (
                v_phone,
                CONCAT(NEW.title, '\n\n', NEW.message),
                NEW.message_type,
                NEW.request_id,
                NEW.citizen_id,
                'pending'
            );
        END IF;
    END IF;
END //

DELIMITER ;

-- ========================================
-- 12. منح الصلاحيات (اختياري)
-- ========================================
-- GRANT SELECT, INSERT, UPDATE, DELETE ON tekrit_municipality.citizens_accounts TO 'your_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON tekrit_municipality.magic_links TO 'your_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON tekrit_municipality.citizen_messages TO 'your_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON tekrit_municipality.whatsapp_log TO 'your_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON tekrit_municipality.notification_preferences TO 'your_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON tekrit_municipality.citizen_sessions TO 'your_user'@'localhost';

-- ========================================
-- 13. بيانات تجريبية (للاختبار فقط)
-- ========================================
-- يمكن حذف هذا القسم في بيئة الإنتاج

-- INSERT INTO citizens_accounts (phone, name, email) VALUES
-- ('96103123456', 'أحمد محمد', 'ahmad@example.com'),
-- ('96170123456', 'فاطمة علي', 'fatima@example.com'),
-- ('96176123456', 'محمد حسن', 'mohammad@example.com');

-- ========================================
-- انتهى السكريبت
-- ========================================

SELECT '✅ تم إنشاء جميع الجداول والإعدادات بنجاح!' as status;

