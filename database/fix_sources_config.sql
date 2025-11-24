-- إصلاح إعدادات المصادر
-- بلدية تكريت-عكار

-- ============================================
-- 1. تحديث دليل الحكومة اللبنانية - TRA
-- ============================================
UPDATE `important_link_sources`
SET 
    `scraping_selector` = '{"item_selector": "//table//tr[position()>1]", "fields": {"name_ar": ".//td[1]", "website": ".//td[2]//a/@href", "description_ar": ".//td[3]"}}',
    `mapping_config` = NULL
WHERE `name_ar` = 'دليل الحكومة اللبنانية - TRA';

-- ============================================
-- 2. تفعيل مستشفيات - وزارة الصحة
-- ============================================
UPDATE `important_link_sources`
SET 
    `auto_import` = 1,
    `scraping_selector` = '{"item_selector": "//div[@class=\"facility-item\"] | //table//tr[position()>1]", "fields": {"name_ar": ".//h3 | .//td[1]", "phone": ".//span[@class=\"phone\"] | .//td[2]", "address_ar": ".//span[@class=\"address\"] | .//td[3]"}}'
WHERE `name_ar` = 'مستشفيات - وزارة الصحة';

-- ============================================
-- 3. تعطيل المصادر المعطلة مؤقتاً
-- ============================================

-- تعطيل مصدر السفارات حتى إيجاد رابط صحيح
UPDATE `important_link_sources`
SET 
    `is_active` = 0,
    `error_log` = 'معطل: لا يوجد رابط للبيانات. يحتاج إلى رابط صحيح للملف أو API.'
WHERE `name_ar` = 'السفارات في لبنان - AUB';

-- تعطيل مصدر Open Data مؤقتاً حتى إيجاد رابط مباشر للـ Excel
UPDATE `important_link_sources`
SET 
    `is_active` = 0,
    `error_log` = 'معطل مؤقتاً: الرابط يشير لصفحة ويب وليس ملف Excel مباشر. يحتاج تحديث.'
WHERE `name_ar` = 'مستشفيات حكومية - Open Data Lebanon';

-- ============================================
-- عرض النتيجة
-- ============================================
SELECT 
    id,
    name_ar,
    source_type,
    is_active,
    auto_import,
    CASE 
        WHEN scraping_selector IS NOT NULL THEN 'لديه selectors ✅'
        ELSE 'لا يوجد selectors ❌'
    END as scraping_status,
    CASE 
        WHEN error_log IS NOT NULL THEN CONCAT('خطأ: ', LEFT(error_log, 50))
        ELSE 'لا يوجد أخطاء ✅'
    END as error_status
FROM important_link_sources
ORDER BY id;
