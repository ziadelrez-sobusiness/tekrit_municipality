-- ============================================
-- إضافة أعمدة المساهمات إلى جدول projects
-- ============================================

-- 1. التحقق من الأعمدة الموجودة
SHOW COLUMNS FROM projects;

-- 2. إضافة عمود target_amount (هدف المساهمات)
ALTER TABLE `projects` 
ADD COLUMN IF NOT EXISTS `target_amount` DECIMAL(15,2) DEFAULT 0.00 
COMMENT 'هدف المساهمات الشعبية للمشروع';

-- 3. إضافة عمود contributions_collected (المبلغ المجموع)
ALTER TABLE `projects` 
ADD COLUMN IF NOT EXISTS `contributions_collected` DECIMAL(15,2) DEFAULT 0.00 
COMMENT 'المبلغ المجموع من المساهمات الشعبية';

-- 4. إضافة عمود allow_public_contributions (السماح بالمساهمات)
ALTER TABLE `projects` 
ADD COLUMN IF NOT EXISTS `allow_public_contributions` TINYINT(1) DEFAULT 0 
COMMENT 'هل يسمح المشروع بالمساهمات الشعبية؟';

-- 5. التحقق من إضافة الأعمدة
SHOW COLUMNS FROM projects WHERE Field IN ('target_amount', 'contributions_collected', 'allow_public_contributions');

-- 6. الآن تحديث المشروع رقم 2
UPDATE projects 
SET 
    target_amount = 2300,
    contributions_collected = 0,
    currency_id = (SELECT id FROM currencies WHERE currency_code = 'USD' LIMIT 1),
    allow_public_contributions = 1
WHERE id = 2;

-- 7. التحقق من التحديث
SELECT 
    id,
    project_name,
    target_amount,
    contributions_collected,
    (target_amount - contributions_collected) as remaining,
    allow_public_contributions,
    currency_id
FROM projects 
WHERE id = 2;

-- 8. عرض مع تفاصيل العملة
SELECT 
    p.id,
    p.project_name,
    CONCAT(p.target_amount, ' ', c.currency_symbol) as target,
    CONCAT(IFNULL(p.contributions_collected, 0), ' ', c.currency_symbol) as collected,
    CONCAT((p.target_amount - IFNULL(p.contributions_collected, 0)), ' ', c.currency_symbol) as remaining,
    CASE 
        WHEN p.allow_public_contributions = 1 THEN '✅ مفعّل'
        ELSE '❌ معطّل'
    END as contributions_status
FROM projects p
LEFT JOIN currencies c ON p.currency_id = c.id
WHERE p.id = 2;

-- ============================================
-- ✅ النتائج المتوقعة:
-- ============================================
-- target_amount: 2300
-- contributions_collected: 0
-- remaining: 2300
-- allow_public_contributions: 1 (مفعّل)
-- currency: USD ($)
-- 
-- الآن صفحة المساهمات ستعرض:
-- الهدف: 2,300 $
-- المُجمّع: 0 $
-- المتبقي: 2,300 $
-- نسبة الإنجاز: 0%
-- الحالة: 📊 جاري جمع المساهمات
-- ============================================

-- ============================================
-- اختياري: تحديث مشاريع أخرى للسماح بالمساهمات
-- ============================================
-- قم بإلغاء التعليق على السطور التالية إذا أردت تحديث مشاريع أخرى:

-- UPDATE projects 
-- SET 
--     target_amount = 5000,
--     allow_public_contributions = 1
-- WHERE id = 1;

-- ============================================


