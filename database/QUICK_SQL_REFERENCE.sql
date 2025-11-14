-- ═══════════════════════════════════════════════════════════════
-- مرجع سريع - أوامر SQL المفيدة
-- نظام الحساب الشخصي للمواطن
-- بلدية تكريت - عكار، شمال لبنان
-- ═══════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ═══════════════════════════════════════════════════════════════
-- 1️⃣ عرض جميع المواطنين
-- ═══════════════════════════════════════════════════════════════

SELECT * FROM v_citizens_summary ORDER BY created_at DESC;

-- ═══════════════════════════════════════════════════════════════
-- 2️⃣ البحث عن مواطن بالهاتف
-- ═══════════════════════════════════════════════════════════════

SELECT * FROM citizens_accounts WHERE phone = '96103123456';

-- ═══════════════════════════════════════════════════════════════
-- 3️⃣ إنشاء أو جلب حساب مواطن
-- ═══════════════════════════════════════════════════════════════

CALL sp_get_or_create_citizen_account(
    '96103123456',           -- رقم الهاتف
    'أحمد محمد',             -- الاسم
    'ahmad@example.com',     -- البريد (اختياري)
    'تكريت - عكار',         -- العنوان (اختياري)
    '123456789'              -- الرقم الوطني (اختياري)
);

-- ═══════════════════════════════════════════════════════════════
-- 4️⃣ إحصائيات مواطن معين
-- ═══════════════════════════════════════════════════════════════

-- جلب معرف المواطن
SET @citizen_id = (SELECT id FROM citizens_accounts WHERE phone = '96103123456');

-- عرض الإحصائيات
CALL sp_get_citizen_stats(@citizen_id);

-- ═══════════════════════════════════════════════════════════════
-- 5️⃣ إنشاء Magic Link
-- ═══════════════════════════════════════════════════════════════

INSERT INTO magic_links (citizen_id, token, phone, expires_at)
SELECT 
    id,
    SHA2(CONCAT(phone, NOW(), RAND()), 256),
    phone,
    DATE_ADD(NOW(), INTERVAL 7 DAY)
FROM citizens_accounts 
WHERE phone = '96103123456';

-- عرض الرابط
SELECT 
    CONCAT('https://yourdomain.com/login?token=', token) as magic_link,
    expires_at,
    CASE 
        WHEN expires_at > NOW() AND used = 0 THEN 'صالح ✅'
        ELSE 'منتهي ❌'
    END as status
FROM magic_links 
WHERE phone = '96103123456'
ORDER BY created_at DESC 
LIMIT 1;

-- ═══════════════════════════════════════════════════════════════
-- 6️⃣ إرسال رسالة لمواطن
-- ═══════════════════════════════════════════════════════════════

INSERT INTO citizen_messages (
    citizen_id,
    message_type,
    title,
    message,
    priority,
    sent_via_whatsapp,
    created_by
)
SELECT 
    id,
    'إشعار',
    'تحديث على طلبك',
    'تم مراجعة طلبك وهو قيد التنفيذ',
    'مهم',
    1,
    1  -- معرف الموظف
FROM citizens_accounts 
WHERE phone = '96103123456';

-- ═══════════════════════════════════════════════════════════════
-- 7️⃣ عرض رسائل مواطن
-- ═══════════════════════════════════════════════════════════════

SELECT * FROM v_citizen_messages_detailed
WHERE citizen_phone = '96103123456'
ORDER BY created_at DESC;

-- ═══════════════════════════════════════════════════════════════
-- 8️⃣ تحديث حالة رسالة (قراءة)
-- ═══════════════════════════════════════════════════════════════

UPDATE citizen_messages 
SET is_read = 1, read_at = NOW()
WHERE id = 1;

-- ═══════════════════════════════════════════════════════════════
-- 9️⃣ عرض سجل WhatsApp لمواطن
-- ═══════════════════════════════════════════════════════════════

SELECT * FROM v_whatsapp_log_detailed
WHERE phone = '96103123456'
ORDER BY created_at DESC;

-- ═══════════════════════════════════════════════════════════════
-- 🔟 تحديث حالة رسالة WhatsApp
-- ═══════════════════════════════════════════════════════════════

-- تحديث إلى "تم الإرسال"
UPDATE whatsapp_log 
SET status = 'sent', sent_at = NOW()
WHERE id = 1;

-- تحديث إلى "تم التسليم"
UPDATE whatsapp_log 
SET status = 'delivered', delivered_at = NOW()
WHERE id = 1;

-- تحديث إلى "تم القراءة"
UPDATE whatsapp_log 
SET status = 'read', read_at = NOW()
WHERE id = 1;

-- ═══════════════════════════════════════════════════════════════
-- 1️⃣1️⃣ عرض رسائل WhatsApp المعلقة
-- ═══════════════════════════════════════════════════════════════

SELECT * FROM whatsapp_log 
WHERE status = 'pending'
ORDER BY created_at ASC;

-- ═══════════════════════════════════════════════════════════════
-- 1️⃣2️⃣ عرض Magic Links الصالحة
-- ═══════════════════════════════════════════════════════════════

SELECT 
    ml.*,
    ca.name,
    ca.phone,
    TIMESTAMPDIFF(HOUR, NOW(), ml.expires_at) as hours_remaining
FROM magic_links ml
JOIN citizens_accounts ca ON ml.citizen_id = ca.id
WHERE ml.used = 0 
AND ml.expires_at > NOW()
ORDER BY ml.expires_at ASC;

-- ═══════════════════════════════════════════════════════════════
-- 1️⃣3️⃣ عرض الجلسات النشطة
-- ═══════════════════════════════════════════════════════════════

SELECT 
    cs.*,
    ca.name,
    ca.phone,
    TIMESTAMPDIFF(MINUTE, cs.last_activity, NOW()) as minutes_inactive
FROM citizen_sessions cs
JOIN citizens_accounts ca ON cs.citizen_id = ca.id
WHERE cs.expires_at > NOW()
ORDER BY cs.last_activity DESC;

-- ═══════════════════════════════════════════════════════════════
-- 1️⃣4️⃣ تنظيف الروابط والجلسات المنتهية
-- ═══════════════════════════════════════════════════════════════

CALL sp_cleanup_expired_links();

-- ═══════════════════════════════════════════════════════════════
-- 1️⃣5️⃣ إحصائيات عامة
-- ═══════════════════════════════════════════════════════════════

SELECT 
    'المواطنين' as category,
    COUNT(*) as total,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN whatsapp_notifications = 1 THEN 1 ELSE 0 END) as whatsapp_enabled
FROM citizens_accounts

UNION ALL

SELECT 
    'Magic Links',
    COUNT(*),
    SUM(CASE WHEN used = 0 AND expires_at > NOW() THEN 1 ELSE 0 END),
    SUM(CASE WHEN used = 1 THEN 1 ELSE 0 END)
FROM magic_links

UNION ALL

SELECT 
    'الرسائل',
    COUNT(*),
    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END),
    SUM(CASE WHEN sent_via_whatsapp = 1 THEN 1 ELSE 0 END)
FROM citizen_messages

UNION ALL

SELECT 
    'WhatsApp',
    COUNT(*),
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END),
    SUM(CASE WHEN status IN ('sent', 'delivered', 'read') THEN 1 ELSE 0 END)
FROM whatsapp_log

UNION ALL

SELECT 
    'الجلسات',
    COUNT(*),
    SUM(CASE WHEN expires_at > NOW() THEN 1 ELSE 0 END),
    0
FROM citizen_sessions;

-- ═══════════════════════════════════════════════════════════════
-- 1️⃣6️⃣ أكثر المواطنين نشاطاً
-- ═══════════════════════════════════════════════════════════════

SELECT 
    name,
    phone,
    total_requests,
    total_messages,
    login_count,
    last_login
FROM v_citizens_summary
ORDER BY login_count DESC, total_requests DESC
LIMIT 10;

-- ═══════════════════════════════════════════════════════════════
-- 1️⃣7️⃣ الرسائل غير المقروءة
-- ═══════════════════════════════════════════════════════════════

SELECT 
    citizen_name,
    citizen_phone,
    title,
    message_type,
    priority,
    created_at
FROM v_citizen_messages_detailed
WHERE is_read = 0
ORDER BY 
    CASE priority
        WHEN 'عاجل' THEN 1
        WHEN 'مهم' THEN 2
        ELSE 3
    END,
    created_at DESC;

-- ═══════════════════════════════════════════════════════════════
-- 1️⃣8️⃣ إعدادات إشعارات مواطن
-- ═══════════════════════════════════════════════════════════════

SELECT 
    ca.name,
    ca.phone,
    np.*
FROM notification_preferences np
JOIN citizens_accounts ca ON np.citizen_id = ca.id
WHERE ca.phone = '96103123456';

-- ═══════════════════════════════════════════════════════════════
-- 1️⃣9️⃣ تحديث إعدادات الإشعارات
-- ═══════════════════════════════════════════════════════════════

UPDATE notification_preferences np
JOIN citizens_accounts ca ON np.citizen_id = ca.id
SET 
    np.whatsapp_enabled = 1,
    np.notify_on_status_change = 1,
    np.notify_on_new_message = 1,
    np.notify_on_completion = 1
WHERE ca.phone = '96103123456';

-- ═══════════════════════════════════════════════════════════════
-- 2️⃣0️⃣ تعطيل حساب مواطن
-- ═══════════════════════════════════════════════════════════════

UPDATE citizens_accounts 
SET is_active = 0
WHERE phone = '96103123456';

-- ═══════════════════════════════════════════════════════════════
-- 2️⃣1️⃣ تفعيل حساب مواطن
-- ═══════════════════════════════════════════════════════════════

UPDATE citizens_accounts 
SET is_active = 1
WHERE phone = '96103123456';

-- ═══════════════════════════════════════════════════════════════
-- 2️⃣2️⃣ حذف جميع جلسات مواطن
-- ═══════════════════════════════════════════════════════════════

DELETE cs FROM citizen_sessions cs
JOIN citizens_accounts ca ON cs.citizen_id = ca.id
WHERE ca.phone = '96103123456';

-- ═══════════════════════════════════════════════════════════════
-- 2️⃣3️⃣ إرسال رسالة عامة لجميع المواطنين
-- ═══════════════════════════════════════════════════════════════

INSERT INTO citizen_messages (
    citizen_id,
    message_type,
    title,
    message,
    priority,
    sent_via_whatsapp,
    created_by
)
SELECT 
    id,
    'عام',
    'إعلان مهم',
    'نود إعلامكم بأن مكاتب البلدية ستكون مغلقة يوم الجمعة',
    'مهم',
    1,
    1
FROM citizens_accounts 
WHERE is_active = 1 
AND whatsapp_notifications = 1;

-- ═══════════════════════════════════════════════════════════════
-- 2️⃣4️⃣ عرض طلبات مواطن
-- ═══════════════════════════════════════════════════════════════

SELECT 
    cr.id,
    cr.tracking_number,
    cr.request_title,
    cr.request_type,
    cr.status,
    cr.created_at,
    cr.completion_date
FROM citizen_requests cr
JOIN citizens_accounts ca ON cr.citizen_phone = ca.phone
WHERE ca.phone = '96103123456'
ORDER BY cr.created_at DESC;

-- ═══════════════════════════════════════════════════════════════
-- 2️⃣5️⃣ متوسط وقت إنجاز الطلبات
-- ═══════════════════════════════════════════════════════════════

SELECT 
    AVG(DATEDIFF(completion_date, created_at)) as avg_days,
    MIN(DATEDIFF(completion_date, created_at)) as min_days,
    MAX(DATEDIFF(completion_date, created_at)) as max_days,
    COUNT(*) as total_completed
FROM citizen_requests
WHERE completion_date IS NOT NULL;

-- ═══════════════════════════════════════════════════════════════
-- 2️⃣6️⃣ تقرير نشاط WhatsApp اليومي
-- ═══════════════════════════════════════════════════════════════

SELECT 
    DATE(created_at) as date,
    COUNT(*) as total_messages,
    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
FROM whatsapp_log
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY date DESC;

-- ═══════════════════════════════════════════════════════════════
-- 2️⃣7️⃣ المواطنين الذين لم يسجلوا دخول أبداً
-- ═══════════════════════════════════════════════════════════════

SELECT 
    name,
    phone,
    created_at,
    DATEDIFF(NOW(), created_at) as days_since_registration
FROM citizens_accounts
WHERE login_count = 0
ORDER BY created_at DESC;

-- ═══════════════════════════════════════════════════════════════
-- 2️⃣8️⃣ تقرير الرسائل حسب النوع
-- ═══════════════════════════════════════════════════════════════

SELECT 
    message_type,
    COUNT(*) as total,
    SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read,
    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
    ROUND(SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as read_percentage
FROM citizen_messages
GROUP BY message_type
ORDER BY total DESC;

-- ═══════════════════════════════════════════════════════════════
-- 2️⃣9️⃣ أرشفة الرسائل القديمة (أكثر من 6 أشهر)
-- ═══════════════════════════════════════════════════════════════

-- عرض عدد الرسائل التي سيتم أرشفتها
SELECT COUNT(*) as messages_to_archive
FROM citizen_messages 
WHERE is_read = 1 
AND created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);

-- حذف (أو يمكن نقلها لجدول أرشيف)
-- DELETE FROM citizen_messages 
-- WHERE is_read = 1 
-- AND created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);

-- ═══════════════════════════════════════════════════════════════
-- 3️⃣0️⃣ تحسين الأداء (تشغيل دورياً)
-- ═══════════════════════════════════════════════════════════════

-- تحليل الجداول
ANALYZE TABLE 
    citizens_accounts,
    magic_links,
    citizen_messages,
    whatsapp_log,
    notification_preferences,
    citizen_sessions;

-- تحسين الجداول (اختياري)
-- OPTIMIZE TABLE 
--     citizens_accounts,
--     magic_links,
--     citizen_messages,
--     whatsapp_log;

-- ═══════════════════════════════════════════════════════════════
-- 📊 نهاية المرجع السريع
-- ═══════════════════════════════════════════════════════════════

SELECT '✅ مرجع SQL السريع - 30 استعلام مفيد' as status;

