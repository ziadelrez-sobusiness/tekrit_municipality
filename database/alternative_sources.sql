-- مصادر بيانات بديلة ومقترحة
-- بلدية تكريت-عكار

-- ============================================
-- مصادر بديلة للبيانات اللبنانية
-- ============================================

-- ملاحظة: هذه مصادر مقترحة - يجب اختبارها قبل التفعيل

-- 1. OpenStreetMap Overpass API - للمرافق العامة في لبنان
INSERT INTO `important_link_sources` 
(`name_ar`, `name_en`, `source_type`, `fetch_method`, `file_format`, `api_url`, `source_category_id`, `category_id`, `update_frequency`, `is_active`, `auto_import`, `mapping_config`) 
VALUES 
('OpenStreetMap - مستشفيات لبنان', 'OpenStreetMap - Lebanon Hospitals', 'api', 'api', 'json', 
'https://overpass-api.de/api/interpreter?data=[out:json];area["ISO3166-1"="LB"];(node["amenity"="hospital"](area););out;',
NULL, 2, 'monthly', 0, 0,
'{"data_path": "elements", "name_ar": "tags.name", "name_en": "tags.name:en", "location_lat": "lat", "location_lng": "lon", "address_ar": "tags.addr:street"}');

-- 2. Humanitarian Data Exchange (HDX) - بيانات لبنان
INSERT INTO `important_link_sources` 
(`name_ar`, `name_en`, `source_type`, `fetch_method`, `file_format`, `api_url`, `source_category_id`, `category_id`, `update_frequency`, `is_active`, `auto_import`) 
VALUES 
('HDX - مرافق صحية لبنان', 'HDX - Lebanon Health Facilities', 'api', 'api', 'json', 
'https://data.humdata.org/api/3/action/package_show?id=lebanon-healthsites',
NULL, 2, 'monthly', 0, 0);

-- 3. WHO - منظمة الصحة العالمية - لبنان
INSERT INTO `important_link_sources` 
(`name_ar`, `name_en`, `source_type`, `fetch_method`, `file_format`, `api_url`, `source_category_id`, `category_id`, `update_frequency`, `is_active`, `auto_import`) 
VALUES 
('WHO - مرافق صحية', 'WHO Health Facilities', 'api', 'api', 'json', 
'https://www.who.int/data/gho/data/indicators/indicator-details/GHO/health-facilities',
NULL, 2, 'monthly', 0, 0);

-- 4. وزارة الصحة - الصفحة الرسمية (scraping)
-- هذا المصدر موجود بالفعل (ID: 7) وتم إصلاحه

-- ============================================
-- مصادر حكومية مباشرة (للاستخدام اليدوي)
-- ============================================

-- مواقع يمكن scraping منها
INSERT INTO `important_link_sources` 
(`name_ar`, `name_en`, `source_type`, `fetch_method`, `file_format`, `scraping_url`, `category_id`, `update_frequency`, `is_active`, `auto_import`) 
VALUES 
('موقع رئاسة الحكومة - دليل الوزارات', 'PCM - Ministries Directory', 'scraping', 'html_scraper', 'html', 
'https://www.pcm.gov.lb/arabic/subpg.aspx?pageid=166', 1, 'monthly', 0, 0);

-- ============================================
-- ملاحظات للمطور
-- ============================================

SELECT '
📌 ملاحظات مهمة:
-----------------
1. جميع هذه المصادر معطلة (is_active = 0) - يجب اختبارها أولاً
2. OpenStreetMap: مصدر مجاني وموثوق لكن قد يحتاج معالجة خاصة للبيانات
3. HDX: مصدر إنساني جيد لكن يحتاج API key في بعض الأحيان
4. WHO: بيانات عالمية قد لا تكون محدثة للبنان
5. الحل الأفضل: استخدام البيانات الأولية اليدوية + scraping من وزارة الصحة

✅ التوصية: استخدم initial_data_lebanon.sql للحصول على بيانات فورية
' as notes;
