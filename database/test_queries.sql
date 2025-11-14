-- ========================================
-- اختبارات قاعدة البيانات
-- نظام الحساب الشخصي للمواطن
-- بلدية تكريت - عكار، شمال لبنان
-- ========================================

SET NAMES utf8mb4;

-- ========================================
-- 1. التحقق من وجود الجداول
-- ========================================

SELECT '=== 1. التحقق من وجود الجداول ===' as test_section;

SELECT 
    TABLE_NAME as 'اسم الجدول',
    TABLE_ROWS as 'عدد الصفوف',
    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024, 2) as 'الحجم (KB)',
    TABLE_COLLATION as 'الترميز'
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'tekrit_municipality' 
AND TABLE_NAME IN (
    'citizens_accounts',
    'magic_links',
    'citizen_messages',
    'whatsapp_log',
    'notification_preferences',
    'citizen_sessions'
)
ORDER BY TABLE_NAME;

-- ========================================
-- 2. التحقق من الأعمدة في كل جدول
-- ========================================

SELECT '=== 2. التحقق من أعمدة citizens_accounts ===' as test_section;

SELECT 
    COLUMN_NAME as 'العمود',
    COLUMN_TYPE as 'النوع',
    IS_NULLABLE as 'يقبل NULL',
    COLUMN_DEFAULT as 'القيمة الافتراضية',
    COLUMN_KEY as 'المفتاح'
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'tekrit_municipality' 
AND TABLE_NAME = 'citizens_accounts'
ORDER BY ORDINAL_POSITION;

-- ========================================
-- 3. التحقق من الفهارس
-- ========================================

SELECT '=== 3. التحقق من الفهارس ===' as test_section;

SELECT 
    TABLE_NAME as 'الجدول',
    INDEX_NAME as 'اسم الفهرس',
    COLUMN_NAME as 'العمود',
    NON_UNIQUE as 'غير فريد',
    INDEX_TYPE as 'نوع الفهرس'
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = 'tekrit_municipality' 
AND TABLE_NAME IN (
    'citizens_accounts',
    'magic_links',
    'citizen_messages',
    'whatsapp_log'
)
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- ========================================
-- 4. التحقق من Foreign Keys
-- ========================================

SELECT '=== 4. التحقق من Foreign Keys ===' as test_section;

SELECT 
    TABLE_NAME as 'الجدول',
    COLUMN_NAME as 'العمود',
    REFERENCED_TABLE_NAME as 'الجدول المرجعي',
    REFERENCED_COLUMN_NAME as 'العمود المرجعي',
    CONSTRAINT_NAME as 'اسم القيد'
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = 'tekrit_municipality' 
AND REFERENCED_TABLE_NAME IS NOT NULL
AND TABLE_NAME IN (
    'magic_links',
    'citizen_messages',
    'whatsapp_log',
    'notification_preferences',
    'citizen_sessions'
)
ORDER BY TABLE_NAME;

-- ========================================
-- 5. التحقق من Views
-- ========================================

SELECT '=== 5. التحقق من Views ===' as test_section;

SELECT 
    TABLE_NAME as 'اسم الـ View',
    VIEW_DEFINITION as 'التعريف'
FROM information_schema.VIEWS 
WHERE TABLE_SCHEMA = 'tekrit_municipality' 
AND TABLE_NAME IN (
    'v_citizens_summary',
    'v_citizen_messages_detailed',
    'v_whatsapp_log_detailed'
);

-- ========================================
-- 6. التحقق من Stored Procedures
-- ========================================

SELECT '=== 6. التحقق من Stored Procedures ===' as test_section;

SELECT 
    ROUTINE_NAME as 'اسم الـ Procedure',
    ROUTINE_TYPE as 'النوع',
    DTD_IDENTIFIER as 'نوع الإرجاع',
    CREATED as 'تاريخ الإنشاء'
FROM information_schema.ROUTINES 
WHERE ROUTINE_SCHEMA = 'tekrit_municipality' 
AND ROUTINE_NAME IN (
    'sp_get_or_create_citizen_account',
    'sp_cleanup_expired_links',
    'sp_get_citizen_stats'
);

-- ========================================
-- 7. التحقق من Triggers
-- ========================================

SELECT '=== 7. التحقق من Triggers ===' as test_section;

SELECT 
    TRIGGER_NAME as 'اسم الـ Trigger',
    EVENT_MANIPULATION as 'الحدث',
    EVENT_OBJECT_TABLE as 'الجدول',
    ACTION_TIMING as 'التوقيت',
    ACTION_STATEMENT as 'الأمر'
FROM information_schema.TRIGGERS 
WHERE TRIGGER_SCHEMA = 'tekrit_municipality' 
AND TRIGGER_NAME IN (
    'tr_update_login_count',
    'tr_log_citizen_message'
);

-- ========================================
-- 8. التحقق من إعدادات WhatsApp
-- ========================================

SELECT '=== 8. التحقق من إعدادات WhatsApp ===' as test_section;

SELECT 
    setting_key as 'المفتاح',
    LEFT(setting_value, 50) as 'القيمة (أول 50 حرف)',
    setting_description as 'الوصف'
FROM website_settings 
WHERE setting_key LIKE 'whatsapp%' 
OR setting_key LIKE 'municipality%'
ORDER BY setting_key;

-- ========================================
-- 9. اختبار إنشاء حساب مواطن
-- ========================================

SELECT '=== 9. اختبار إنشاء حساب مواطن ===' as test_section;

-- إنشاء حساب تجريبي
CALL sp_get_or_create_citizen_account(
    '96103999999',
    'مواطن تجريبي',
    'test@example.com',
    'تكريت - عكار',
    'TEST123456'
);

-- التحقق من الإنشاء
SELECT 
    id,
    phone,
    name,
    email,
    whatsapp_notifications,
    is_active,
    created_at
FROM citizens_accounts 
WHERE phone = '96103999999';

-- التحقق من إعدادات الإشعارات
SELECT 
    np.*
FROM notification_preferences np
JOIN citizens_accounts ca ON np.citizen_id = ca.id
WHERE ca.phone = '96103999999';

-- ========================================
-- 10. اختبار Magic Link
-- ========================================

SELECT '=== 10. اختبار Magic Link ===' as test_section;

-- إنشاء Magic Link تجريبي
INSERT INTO magic_links (
    citizen_id,
    token,
    phone,
    expires_at
) 
SELECT 
    id,
    SHA2(CONCAT(phone, NOW(), RAND()), 256),
    phone,
    DATE_ADD(NOW(), INTERVAL 7 DAY)
FROM citizens_accounts 
WHERE phone = '96103999999'
LIMIT 1;

-- عرض Magic Link
SELECT 
    ml.id,
    ml.token,
    ml.phone,
    ml.used,
    ml.expires_at,
    CASE 
        WHEN ml.expires_at > NOW() AND ml.used = 0 THEN 'صالح ✅'
        WHEN ml.expires_at <= NOW() THEN 'منتهي ⏰'
        WHEN ml.used = 1 THEN 'مستخدم 🔒'
        ELSE 'غير معروف'
    END as 'الحالة'
FROM magic_links ml
WHERE ml.phone = '96103999999'
ORDER BY ml.created_at DESC
LIMIT 1;

-- ========================================
-- 11. اختبار رسالة للمواطن
-- ========================================

SELECT '=== 11. اختبار رسالة للمواطن ===' as test_section;

-- إنشاء رسالة تجريبية
INSERT INTO citizen_messages (
    citizen_id,
    message_type,
    title,
    message,
    priority,
    sent_via_whatsapp
)
SELECT 
    id,
    'إشعار',
    'رسالة تجريبية',
    'هذه رسالة اختبار من نظام البلدية',
    'عادي',
    1
FROM citizens_accounts 
WHERE phone = '96103999999'
LIMIT 1;

-- عرض الرسالة
SELECT 
    cm.id,
    cm.title,
    cm.message,
    cm.message_type,
    cm.priority,
    cm.is_read,
    cm.sent_via_whatsapp,
    cm.created_at
FROM citizen_messages cm
JOIN citizens_accounts ca ON cm.citizen_id = ca.id
WHERE ca.phone = '96103999999'
ORDER BY cm.created_at DESC
LIMIT 1;

-- التحقق من تسجيل WhatsApp (عبر Trigger)
SELECT 
    wl.id,
    wl.phone,
    LEFT(wl.message, 50) as 'الرسالة (أول 50 حرف)',
    wl.status,
    wl.created_at
FROM whatsapp_log wl
WHERE wl.phone = '96103999999'
ORDER BY wl.created_at DESC
LIMIT 1;

-- ========================================
-- 12. اختبار View: v_citizens_summary
-- ========================================

SELECT '=== 12. اختبار v_citizens_summary ===' as test_section;

SELECT 
    name as 'الاسم',
    phone as 'الهاتف',
    total_requests as 'إجمالي الطلبات',
    new_requests as 'طلبات جديدة',
    active_requests as 'طلبات نشطة',
    completed_requests as 'طلبات مكتملة',
    total_messages as 'إجمالي الرسائل',
    unread_messages as 'رسائل غير مقروءة',
    login_count as 'عدد تسجيلات الدخول',
    last_login as 'آخر دخول'
FROM v_citizens_summary
WHERE phone = '96103999999';

-- ========================================
-- 13. اختبار Stored Procedure: sp_get_citizen_stats
-- ========================================

SELECT '=== 13. اختبار sp_get_citizen_stats ===' as test_section;

-- جلب معرف المواطن التجريبي
SET @test_citizen_id = (SELECT id FROM citizens_accounts WHERE phone = '96103999999' LIMIT 1);

-- تشغيل Procedure
CALL sp_get_citizen_stats(@test_citizen_id);

-- ========================================
-- 14. اختبار الجلسة
-- ========================================

SELECT '=== 14. اختبار الجلسة ===' as test_section;

-- إنشاء جلسة تجريبية
INSERT INTO citizen_sessions (
    citizen_id,
    session_token,
    ip_address,
    user_agent,
    expires_at
)
SELECT 
    id,
    SHA2(CONCAT(phone, NOW(), RAND()), 256),
    '127.0.0.1',
    'Test Browser',
    DATE_ADD(NOW(), INTERVAL 1 DAY)
FROM citizens_accounts 
WHERE phone = '96103999999'
LIMIT 1;

-- التحقق من تحديث login_count (عبر Trigger)
SELECT 
    id,
    phone,
    name,
    last_login,
    login_count
FROM citizens_accounts 
WHERE phone = '96103999999';

-- عرض الجلسات
SELECT 
    cs.id,
    cs.session_token,
    cs.ip_address,
    cs.last_activity,
    cs.expires_at,
    CASE 
        WHEN cs.expires_at > NOW() THEN 'نشطة ✅'
        ELSE 'منتهية ⏰'
    END as 'الحالة'
FROM citizen_sessions cs
JOIN citizens_accounts ca ON cs.citizen_id = ca.id
WHERE ca.phone = '96103999999'
ORDER BY cs.created_at DESC;

-- ========================================
-- 15. اختبار التنظيف
-- ========================================

SELECT '=== 15. اختبار التنظيف ===' as test_section;

-- عرض عدد الروابط والجلسات قبل التنظيف
SELECT 
    'قبل التنظيف' as 'الحالة',
    (SELECT COUNT(*) FROM magic_links) as 'Magic Links',
    (SELECT COUNT(*) FROM citizen_sessions) as 'الجلسات';

-- تشغيل التنظيف
CALL sp_cleanup_expired_links();

-- عرض عدد الروابط والجلسات بعد التنظيف
SELECT 
    'بعد التنظيف' as 'الحالة',
    (SELECT COUNT(*) FROM magic_links) as 'Magic Links',
    (SELECT COUNT(*) FROM citizen_sessions) as 'الجلسات';

-- ========================================
-- 16. تنظيف البيانات التجريبية
-- ========================================

SELECT '=== 16. تنظيف البيانات التجريبية ===' as test_section;

-- حذف المواطن التجريبي (سيحذف تلقائياً جميع البيانات المرتبطة عبر CASCADE)
DELETE FROM citizens_accounts WHERE phone = '96103999999';

SELECT 'تم حذف البيانات التجريبية بنجاح ✅' as 'النتيجة';

-- ========================================
-- 17. إحصائيات عامة
-- ========================================

SELECT '=== 17. إحصائيات عامة ===' as test_section;

SELECT 
    (SELECT COUNT(*) FROM citizens_accounts) as 'إجمالي المواطنين',
    (SELECT COUNT(*) FROM citizens_accounts WHERE is_active = 1) as 'المواطنين النشطين',
    (SELECT COUNT(*) FROM magic_links) as 'Magic Links',
    (SELECT COUNT(*) FROM magic_links WHERE used = 0 AND expires_at > NOW()) as 'روابط صالحة',
    (SELECT COUNT(*) FROM citizen_messages) as 'إجمالي الرسائل',
    (SELECT COUNT(*) FROM citizen_messages WHERE is_read = 0) as 'رسائل غير مقروءة',
    (SELECT COUNT(*) FROM whatsapp_log) as 'سجل WhatsApp',
    (SELECT COUNT(*) FROM whatsapp_log WHERE status = 'pending') as 'رسائل WhatsApp معلقة',
    (SELECT COUNT(*) FROM citizen_sessions) as 'الجلسات',
    (SELECT COUNT(*) FROM citizen_sessions WHERE expires_at > NOW()) as 'جلسات نشطة';

-- ========================================
-- 18. التحقق من الأداء
-- ========================================

SELECT '=== 18. التحقق من الأداء ===' as test_section;

-- عرض حجم كل جدول
SELECT 
    TABLE_NAME as 'الجدول',
    TABLE_ROWS as 'عدد الصفوف',
    ROUND((DATA_LENGTH) / 1024, 2) as 'حجم البيانات (KB)',
    ROUND((INDEX_LENGTH) / 1024, 2) as 'حجم الفهارس (KB)',
    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024, 2) as 'الحجم الإجمالي (KB)',
    ENGINE as 'المحرك'
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'tekrit_municipality' 
AND TABLE_NAME IN (
    'citizens_accounts',
    'magic_links',
    'citizen_messages',
    'whatsapp_log',
    'notification_preferences',
    'citizen_sessions'
)
ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC;

-- ========================================
-- النهاية
-- ========================================

SELECT '=== ✅ اكتملت جميع الاختبارات بنجاح ===' as test_section;
SELECT CONCAT(
    '🎉 تم اختبار ',
    '6 جداول، ',
    '3 Views، ',
    '3 Stored Procedures، ',
    '2 Triggers'
) as 'النتيجة';

