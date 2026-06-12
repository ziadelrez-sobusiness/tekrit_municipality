-- ============================================
-- تحديث هدف المساهمات للمشروع رقم 2
-- ============================================

-- ⚠️ ملاحظة: هذا الملف قديم، استخدم ADD_CONTRIBUTIONS_COLUMNS.sql بدلاً منه

-- 1. إضافة الأعمدة المطلوبة أولاً
ALTER TABLE `projects` 
ADD COLUMN IF NOT EXISTS `target_amount` DECIMAL(15,2) DEFAULT 0.00;

ALTER TABLE `projects` 
ADD COLUMN IF NOT EXISTS `contributions_collected` DECIMAL(15,2) DEFAULT 0.00;

ALTER TABLE `projects` 
ADD COLUMN IF NOT EXISTS `allow_public_contributions` TINYINT(1) DEFAULT 0;

-- 2. عرض بيانات المشروع الحالية
SELECT 
    id,
    project_name,
    IFNULL(target_amount, 0) as target_amount,
    IFNULL(contributions_collected, 0) as contributions_collected,
    IFNULL(currency_id, 'NULL') as currency_id,
    allow_public_contributions
FROM projects 
WHERE id = 2;

-- 3. تحديث هدف المساهمات (2,300 دولار)
UPDATE projects 
SET 
    target_amount = 2300,
    contributions_collected = 0,
    currency_id = (SELECT id FROM currencies WHERE currency_code = 'USD' LIMIT 1),
    allow_public_contributions = 1
WHERE id = 2;

-- 3. التحقق من التحديث
SELECT 
    id,
    project_name,
    target_amount,
    contributions_collected,
    currency_id,
    allow_public_contributions,
    (target_amount - IFNULL(contributions_collected, 0)) as remaining
FROM projects 
WHERE id = 2;

-- 4. عرض مع معلومات العملة
SELECT 
    p.id,
    p.project_name,
    p.target_amount,
    IFNULL(p.contributions_collected, 0) as contributions_collected,
    (p.target_amount - IFNULL(p.contributions_collected, 0)) as remaining,
    c.currency_symbol,
    c.currency_code,
    CONCAT(
        'الهدف: ', p.target_amount, ' ', c.currency_symbol, ' | ',
        'المجموع: ', IFNULL(p.contributions_collected, 0), ' ', c.currency_symbol, ' | ',
        'المتبقي: ', (p.target_amount - IFNULL(p.contributions_collected, 0)), ' ', c.currency_symbol
    ) as summary
FROM projects p
LEFT JOIN currencies c ON p.currency_id = c.id
WHERE p.id = 2;

-- ============================================
-- ملاحظات:
-- ============================================
-- ✅ تم تحديد هدف المساهمات: 2,300 $
-- ✅ تم تفعيل السماح بالمساهمات الشعبية
-- ✅ تم تحديد العملة: USD
-- 
-- الآن عند فتح صفحة المساهمات سيظهر:
-- 📊 الهدف: 2,300 $
-- 📊 المُجمّع: 0 $
-- 📊 المتبقي: 2,300 $
-- 📊 نسبة الإنجاز: 0%
-- ============================================

