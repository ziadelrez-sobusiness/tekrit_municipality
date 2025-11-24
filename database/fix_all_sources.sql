-- إصلاح شامل لجميع المصادر
-- بلدية تكريت-عكار
-- تاريخ: 2025-11-24

-- ============================================
-- الجزء 1: إصلاح المصدر الناجح (وزارة الصحة)
-- ============================================

-- تحديث scraping selectors لوزارة الصحة بناءً على الفحص الفعلي
UPDATE `important_link_sources`
SET 
    `scraping_selector` = '{
        "item_selector": "//table//tr[position()>1]",
        "fields": {
            "name_en": ".//td[1]//a",
            "name_ar": ".//td[3]",
            "phone": ".//td[5]",
            "address_ar": ".//td[4]"
        }
    }',
    `mapping_config` = '{
        "name_ar": "name_ar",
        "name_en": "name_en",
        "phone": "phone",
        "address_ar": "address_ar"
    }'
WHERE `id` = 7;

-- ============================================
-- الجزء 2: تعطيل المصادر المعطلة نهائياً
-- ============================================

-- تعطيل المصادر التي لا تعمل (APIs غير موجودة)
UPDATE `important_link_sources`
SET 
    `is_active` = 0,
    `error_log` = 'معطل: API غير موجود أو الموقع لا يستجيب. يحتاج مصدر بيانات بديل.'
WHERE `id` IN (1, 2, 4, 5);

-- تعطيل مصدر التربية (404 + HTML)
UPDATE `important_link_sources`
SET 
    `is_active` = 0,
    `source_type` = 'scraping',
    `fetch_method` = 'html_scraper',
    `scraping_url` = `api_url`,
    `api_url` = NULL,
    `error_log` = 'معطل: الرابط يعيد HTML وليس JSON. يحتاج تحويل إلى scraping مع selectors صحيحة.'
WHERE `id` = 3;

-- المصادر 6 و 8 معطلة مسبقاً (لا حاجة لتعديل)

-- ============================================
-- الجزء 3: إضافة ملاحظات للسجلات
-- ============================================

UPDATE `important_link_sources`
SET `error_log` = 'المصدر الوحيد العامل - تم إصلاح selectors بناءً على فحص الصفحة'
WHERE `id` = 7;

-- ============================================
-- عرض ملخص الحالة
-- ============================================

SELECT 
    id,
    name_ar,
    source_type,
    fetch_method,
    is_active,
    CASE 
        WHEN is_active = 1 THEN '✅ نشط'
        ELSE '❌ معطل'
    END as status,
    CASE 
        WHEN scraping_selector IS NOT NULL THEN '✅ لديه selectors'
        ELSE '⚠️ بدون selectors'
    END as has_selectors
FROM important_link_sources
ORDER BY is_active DESC, id;
