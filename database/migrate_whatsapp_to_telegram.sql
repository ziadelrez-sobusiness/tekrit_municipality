-- ========================================
-- استبدال WhatsApp بـ Telegram
-- بلدية تكريت - عكار، شمال لبنان
-- ========================================

-- 1. إعادة تسمية الجداول
-- ========================================

-- إعادة تسمية whatsapp_log إلى telegram_log
DROP TABLE IF EXISTS telegram_log;
RENAME TABLE whatsapp_log TO telegram_log;

-- 2. تحديث بنية الجداول
-- ========================================

-- تحديث جدول telegram_log
ALTER TABLE telegram_log 
CHANGE COLUMN phone telegram_chat_id VARCHAR(100) NOT NULL COMMENT 'Telegram Chat ID';

ALTER TABLE telegram_log
ADD COLUMN telegram_username VARCHAR(100) NULL COMMENT 'Telegram Username' AFTER telegram_chat_id;

-- 3. تحديث جدول citizens_accounts
-- ========================================

ALTER TABLE citizens_accounts
ADD COLUMN telegram_chat_id VARCHAR(100) NULL COMMENT 'Telegram Chat ID' AFTER phone;

ALTER TABLE citizens_accounts
ADD COLUMN telegram_username VARCHAR(100) NULL COMMENT 'Telegram Username' AFTER telegram_chat_id;

ALTER TABLE citizens_accounts
ADD COLUMN permanent_access_code VARCHAR(20) NULL UNIQUE COMMENT 'رمز دخول ثابت للمواطن' AFTER telegram_username;

-- 4. تحديث إعدادات النظام
-- ========================================

-- حذف إعدادات WhatsApp القديمة
DELETE FROM website_settings WHERE setting_key LIKE 'whatsapp%';

-- إضافة إعدادات Telegram
INSERT INTO website_settings (setting_key, setting_value, setting_description) VALUES
('telegram_bot_enabled', '1', 'تفعيل Telegram Bot'),
('telegram_bot_token', '', 'Telegram Bot Token من BotFather'),
('telegram_bot_username', 'TekritAkkarBot', 'اسم المستخدم للبوت'),
('telegram_welcome_template', '✅ مرحباً بك في بلدية تكريت - عكار!\n\n📋 تم تقديم طلبكم بنجاح:\n\n🔢 رقم التتبع: {tracking_number}\n📝 نوع الطلب: {request_type}\n📅 التاريخ: {date}\n\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\n🔐 {access_code}\n\nسيتم إبلاغكم بأي تحديثات على طلبكم.', 'قالب رسالة الترحيب'),
('telegram_status_update_template', '📢 تحديث حالة الطلب\n\n🔢 رقم التتبع: {tracking_number}\n📝 نوع الطلب: {request_type}\n\n🔄 الحالة الجديدة: {new_status}\n\n💬 ملاحظات: {notes}', 'قالب رسالة تحديث الحالة'),
('telegram_completion_template', '✅ تم إنجاز طلبكم!\n\n🔢 رقم التتبع: {tracking_number}\n📝 نوع الطلب: {request_type}\n📅 تاريخ الإنجاز: {completion_date}\n\n💬 {notes}\n\nشكراً لتعاملكم مع بلدية تكريت - عكار 🏛️', 'قالب رسالة إنجاز الطلب'),
('telegram_webhook_url', '', 'رابط Webhook لاستقبال رسائل Telegram')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- 5. إعادة إنشاء Views
-- ========================================

-- حذف Views القديمة
DROP VIEW IF EXISTS v_whatsapp_log_detailed;
DROP VIEW IF EXISTS v_telegram_log_detailed;

-- إنشاء View جديد لـ Telegram
CREATE VIEW v_telegram_log_detailed AS
SELECT 
    tl.id,
    tl.citizen_id,
    ca.name as citizen_name,
    ca.phone as citizen_phone,
    tl.telegram_chat_id,
    tl.telegram_username,
    tl.request_id,
    cr.tracking_number,
    rt.type_name as request_type,
    tl.message_type,
    tl.message,
    tl.status,
    tl.sent_at,
    tl.error_message,
    tl.created_at
FROM telegram_log tl
LEFT JOIN citizens_accounts ca ON tl.citizen_id = ca.id
LEFT JOIN citizen_requests cr ON tl.request_id = cr.id
LEFT JOIN request_types rt ON cr.request_type_id = rt.id
ORDER BY tl.created_at DESC;

-- تحديث View للمواطنين
DROP VIEW IF EXISTS v_citizens_summary;

CREATE VIEW v_citizens_summary AS
SELECT 
    ca.id,
    ca.name,
    ca.phone,
    ca.telegram_chat_id,
    ca.telegram_username,
    ca.permanent_access_code,
    ca.email,
    ca.national_id,
    ca.created_at,
    ca.last_login,
    COUNT(DISTINCT cr.id) as total_requests,
    COUNT(DISTINCT CASE WHEN cr.status IN ('جديد', 'قيد المراجعة', 'قيد التنفيذ') THEN cr.id END) as active_requests,
    COUNT(DISTINCT cm.id) as total_messages,
    COUNT(DISTINCT tl.id) as total_telegram_messages
FROM citizens_accounts ca
LEFT JOIN citizen_requests cr ON ca.phone = cr.citizen_phone
LEFT JOIN citizen_messages cm ON ca.id = cm.citizen_id
LEFT JOIN telegram_log tl ON ca.id = tl.citizen_id
GROUP BY ca.id;

-- 6. إعادة إنشاء Stored Procedures
-- ========================================

-- حذف Procedures القديمة
DROP PROCEDURE IF EXISTS sp_get_or_create_citizen_account;

-- إنشاء Procedure جديد
DELIMITER //

CREATE PROCEDURE sp_get_or_create_citizen_account(
    IN p_phone VARCHAR(20),
    IN p_name VARCHAR(255),
    IN p_email VARCHAR(255),
    IN p_national_id VARCHAR(50),
    IN p_telegram_chat_id VARCHAR(100),
    IN p_telegram_username VARCHAR(100)
)
BEGIN
    DECLARE v_citizen_id INT;
    DECLARE v_access_code VARCHAR(20);
    
    SELECT id INTO v_citizen_id 
    FROM citizens_accounts 
    WHERE phone = p_phone 
    LIMIT 1;
    
    IF v_citizen_id IS NULL THEN
        SET v_access_code = CONCAT('TKT-', LPAD(FLOOR(RAND() * 999999), 6, '0'));
        
        INSERT INTO citizens_accounts (
            phone, name, email, national_id, 
            telegram_chat_id, telegram_username, 
            permanent_access_code, created_at
        ) VALUES (
            p_phone, p_name, p_email, p_national_id,
            p_telegram_chat_id, p_telegram_username,
            v_access_code, NOW()
        );
        
        SET v_citizen_id = LAST_INSERT_ID();
    ELSE
        UPDATE citizens_accounts 
        SET 
            name = COALESCE(p_name, name),
            email = COALESCE(p_email, email),
            national_id = COALESCE(p_national_id, national_id),
            telegram_chat_id = COALESCE(p_telegram_chat_id, telegram_chat_id),
            telegram_username = COALESCE(p_telegram_username, telegram_username),
            updated_at = NOW()
        WHERE id = v_citizen_id;
        
        SELECT permanent_access_code INTO v_access_code
        FROM citizens_accounts
        WHERE id = v_citizen_id;
        
        IF v_access_code IS NULL THEN
            SET v_access_code = CONCAT('TKT-', LPAD(FLOOR(RAND() * 999999), 6, '0'));
            UPDATE citizens_accounts 
            SET permanent_access_code = v_access_code
            WHERE id = v_citizen_id;
        END IF;
    END IF;
    
    SELECT v_citizen_id as citizen_id, v_access_code as access_code;
END //

DELIMITER ;

-- 7. تحديث Triggers
-- ========================================

DROP TRIGGER IF EXISTS tr_log_citizen_message;

DELIMITER //

CREATE TRIGGER tr_log_citizen_message
AFTER INSERT ON citizen_messages
FOR EACH ROW
BEGIN
    DECLARE v_telegram_chat_id VARCHAR(100);
    
    SELECT telegram_chat_id INTO v_telegram_chat_id
    FROM citizens_accounts
    WHERE id = NEW.citizen_id;
    
    IF v_telegram_chat_id IS NOT NULL THEN
        INSERT INTO telegram_log (
            citizen_id, telegram_chat_id, message_type, 
            message, status, created_at
        ) VALUES (
            NEW.citizen_id, v_telegram_chat_id, 'message',
            CONCAT('💬 رسالة من البلدية\n\n', NEW.title, '\n\n', NEW.message),
            'pending', NOW()
        );
    END IF;
END //

DELIMITER ;

-- 8. إنشاء فهارس للأداء
-- ========================================

CREATE INDEX idx_telegram_chat_id ON citizens_accounts(telegram_chat_id);
CREATE INDEX idx_permanent_access_code ON citizens_accounts(permanent_access_code);
CREATE INDEX idx_telegram_status ON telegram_log(status, created_at);
CREATE INDEX idx_telegram_citizen ON telegram_log(citizen_id, created_at);

-- 9. تنظيف البيانات القديمة (اختياري)
-- ========================================

-- يمكنك حذف هذا القسم إذا كنت تريد الاحتفاظ بالبيانات القديمة
-- DELETE FROM telegram_log WHERE status = 'sent' AND created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);

-- ========================================
-- انتهى السكريبت
-- ========================================

SELECT '✅ تم الترحيل من WhatsApp إلى Telegram بنجاح!' as status;

