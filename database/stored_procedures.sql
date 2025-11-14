-- ========================================
-- Stored Procedures لنظام الحساب الشخصي للمواطن
-- بلدية تكريت - عكار، شمال لبنان
-- ========================================

-- حذف الـ Procedures القديمة إن وجدت
DROP PROCEDURE IF EXISTS sp_get_or_create_citizen_account;
DROP PROCEDURE IF EXISTS sp_cleanup_expired_links;
DROP PROCEDURE IF EXISTS sp_get_citizen_stats;

-- ========================================
-- 1. Procedure: إنشاء أو جلب حساب مواطن
-- ========================================
CREATE PROCEDURE sp_get_or_create_citizen_account(
    IN p_phone VARCHAR(20),
    IN p_name VARCHAR(100),
    IN p_email VARCHAR(100),
    IN p_national_id VARCHAR(50)
)
BEGIN
    DECLARE v_citizen_id INT;
    DECLARE v_exists INT;
    
    -- التحقق من وجود الحساب
    SELECT COUNT(*) INTO v_exists 
    FROM citizens_accounts 
    WHERE phone = p_phone;
    
    IF v_exists > 0 THEN
        -- تحديث البيانات إذا كانت أحدث
        UPDATE citizens_accounts 
        SET 
            name = COALESCE(p_name, name),
            email = COALESCE(p_email, email),
            national_id = COALESCE(p_national_id, national_id),
            last_login = CURRENT_TIMESTAMP,
            login_count = login_count + 1
        WHERE phone = p_phone;
        
        -- جلب معرف المواطن
        SELECT id INTO v_citizen_id 
        FROM citizens_accounts 
        WHERE phone = p_phone;
    ELSE
        -- إنشاء حساب جديد
        INSERT INTO citizens_accounts (
            phone, 
            name, 
            email, 
            national_id,
            whatsapp_notifications,
            website_notifications,
            is_active,
            login_count
        ) VALUES (
            p_phone,
            p_name,
            p_email,
            p_national_id,
            1,
            1,
            1,
            1
        );
        
        SET v_citizen_id = LAST_INSERT_ID();
        
        -- إنشاء إعدادات الإشعارات الافتراضية
        INSERT INTO notification_preferences (
            citizen_id,
            whatsapp_enabled,
            website_enabled,
            notify_on_status_change,
            notify_on_new_message,
            notify_on_general_news,
            notify_on_completion,
            notify_on_reminder
        ) VALUES (
            v_citizen_id,
            1, 1, 1, 1, 1, 1, 1
        );
    END IF;
    
    -- إرجاع معرف المواطن
    SELECT v_citizen_id as citizen_id;
END;

-- ========================================
-- 2. Procedure: تنظيف الروابط والجلسات المنتهية
-- ========================================
CREATE PROCEDURE sp_cleanup_expired_links()
BEGIN
    DECLARE deleted_links INT;
    DECLARE deleted_sessions INT;
    
    -- حذف الروابط السحرية المنتهية
    DELETE FROM magic_links 
    WHERE expires_at < NOW() 
    AND used = 0;
    
    SET deleted_links = ROW_COUNT();
    
    -- حذف الجلسات المنتهية
    DELETE FROM citizen_sessions 
    WHERE expires_at < NOW();
    
    SET deleted_sessions = ROW_COUNT();
    
    -- إرجاع النتائج
    SELECT 
        deleted_links as deleted_magic_links,
        deleted_sessions as deleted_sessions,
        NOW() as cleanup_time;
END;

-- ========================================
-- 3. Procedure: جلب إحصائيات المواطن
-- ========================================
CREATE PROCEDURE sp_get_citizen_stats(
    IN p_citizen_id INT
)
BEGIN
    DECLARE v_phone VARCHAR(20);
    
    -- جلب رقم هاتف المواطن
    SELECT phone INTO v_phone 
    FROM citizens_accounts 
    WHERE id = p_citizen_id;
    
    -- إرجاع الإحصائيات الكاملة
    SELECT 
        ca.id,
        ca.phone,
        ca.name,
        ca.email,
        ca.created_at,
        ca.last_login,
        ca.login_count,
        
        -- إحصائيات الطلبات
        COUNT(DISTINCT cr.id) as total_requests,
        SUM(CASE WHEN cr.status = 'جديد' THEN 1 ELSE 0 END) as new_requests,
        SUM(CASE WHEN cr.status = 'قيد المراجعة' THEN 1 ELSE 0 END) as under_review,
        SUM(CASE WHEN cr.status = 'قيد التنفيذ' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN cr.status = 'مكتمل' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN cr.status = 'مرفوض' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN cr.status = 'ملغي' THEN 1 ELSE 0 END) as cancelled,
        
        -- إحصائيات الرسائل
        COUNT(DISTINCT cm.id) as total_messages,
        SUM(CASE WHEN cm.is_read = 0 THEN 1 ELSE 0 END) as unread_messages,
        SUM(CASE WHEN cm.priority = 'عاجل' THEN 1 ELSE 0 END) as urgent_messages,
        
        -- إحصائيات WhatsApp
        COUNT(DISTINCT wl.id) as total_whatsapp_messages,
        SUM(CASE WHEN wl.status = 'sent' THEN 1 ELSE 0 END) as sent_whatsapp,
        SUM(CASE WHEN wl.status = 'delivered' THEN 1 ELSE 0 END) as delivered_whatsapp,
        SUM(CASE WHEN wl.status = 'failed' THEN 1 ELSE 0 END) as failed_whatsapp,
        
        -- آخر نشاط
        MAX(cr.created_at) as last_request_date,
        MAX(cm.created_at) as last_message_date
        
    FROM citizens_accounts ca
    LEFT JOIN citizen_requests cr ON ca.phone = cr.citizen_phone
    LEFT JOIN citizen_messages cm ON ca.id = cm.citizen_id
    LEFT JOIN whatsapp_log wl ON ca.id = wl.citizen_id
    WHERE ca.id = p_citizen_id
    GROUP BY ca.id;
END;

DROP PROCEDURE IF EXISTS sp_create_magic_link;

CREATE PROCEDURE sp_create_magic_link(
    IN p_citizen_id INT,
    IN p_phone VARCHAR(20),
    IN p_validity_hours INT
)
BEGIN
    DECLARE v_token VARCHAR(64);
    DECLARE v_expires_at DATETIME;
    
    SET v_token = SHA2(CONCAT(p_phone, NOW(), RAND()), 256);
    SET v_expires_at = DATE_ADD(NOW(), INTERVAL p_validity_hours HOUR);
    
    INSERT INTO magic_links (citizen_id, token, phone, expires_at, used) 
    VALUES (p_citizen_id, v_token, p_phone, v_expires_at, 0);
    
    SELECT v_token as token, v_expires_at as expires_at;
END;

DROP PROCEDURE IF EXISTS sp_validate_magic_link;

CREATE PROCEDURE sp_validate_magic_link(
    IN p_token VARCHAR(64),
    IN p_ip_address VARCHAR(45),
    IN p_user_agent TEXT
)
BEGIN
    DECLARE v_citizen_id INT;
    DECLARE v_is_valid BOOLEAN DEFAULT FALSE;
    
    -- التحقق من صلاحية الرابط
    SELECT 
        citizen_id,
        (used = 0 AND expires_at > NOW()) as is_valid
    INTO v_citizen_id, v_is_valid
    FROM magic_links
    WHERE token = p_token
    LIMIT 1;
    
    IF v_is_valid THEN
        -- تحديث الرابط كمستخدم
        UPDATE magic_links
        SET 
            used = 1,
            used_at = NOW(),
            ip_address = p_ip_address,
            user_agent = p_user_agent
        WHERE token = p_token;
        
        -- إنشاء جلسة جديدة
        INSERT INTO citizen_sessions (
            citizen_id,
            session_token,
            ip_address,
            user_agent,
            expires_at
        ) VALUES (
            v_citizen_id,
            SHA2(CONCAT(v_citizen_id, NOW(), RAND()), 256),
            p_ip_address,
            p_user_agent,
            DATE_ADD(NOW(), INTERVAL 7 DAY)
        );
        
        -- تحديث آخر تسجيل دخول
        UPDATE citizens_accounts
        SET 
            last_login = NOW(),
            login_count = login_count + 1
        WHERE id = v_citizen_id;
        
        -- إرجاع معلومات المواطن
        SELECT 
            ca.*,
            cs.session_token,
            cs.expires_at as session_expires_at
        FROM citizens_accounts ca
        JOIN citizen_sessions cs ON ca.id = cs.citizen_id
        WHERE ca.id = v_citizen_id
        ORDER BY cs.created_at DESC
        LIMIT 1;
    ELSE
        -- الرابط غير صالح
        SELECT NULL as id, 'invalid_link' as error;
    END IF;
END;

-- ========================================
-- تم إنشاء جميع الـ Stored Procedures بنجاح!
-- ========================================

