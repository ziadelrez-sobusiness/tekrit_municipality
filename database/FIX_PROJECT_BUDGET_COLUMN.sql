-- ============================================
-- إصلاح حقل الميزانية في جدول المشاريع
-- ============================================

-- التحقق من وجود الحقل وإضافته إذا لم يكن موجوداً

-- 1. إضافة حقل total_budget إذا لم يكن موجوداً
ALTER TABLE `projects` 
ADD COLUMN IF NOT EXISTS `total_budget` DECIMAL(15,2) DEFAULT 0.00 
COMMENT 'الميزانية الإجمالية للمشروع';

-- 2. إضافة حقل spent_amount إذا لم يكن موجوداً
ALTER TABLE `projects` 
ADD COLUMN IF NOT EXISTS `spent_amount` DECIMAL(15,2) DEFAULT 0.00 
COMMENT 'المبلغ المصروف من الميزانية';

-- 3. إضافة حقل currency_id إذا لم يكن موجوداً
ALTER TABLE `projects` 
ADD COLUMN IF NOT EXISTS `currency_id` INT(11) DEFAULT NULL 
COMMENT 'عملة المشروع';

-- 4. إضافة foreign key للعملة إذا لم يكن موجوداً
SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND CONSTRAINT_NAME = 'fk_projects_currency'
    AND TABLE_NAME = 'projects'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `projects` ADD CONSTRAINT `fk_projects_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies`(`id`) ON DELETE SET NULL',
    'SELECT ''Foreign key already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. تحديث المشاريع التي لها currency_id = NULL لتستخدم العملة الافتراضية
UPDATE `projects` 
SET `currency_id` = (SELECT id FROM currencies WHERE is_default = 1 LIMIT 1)
WHERE `currency_id` IS NULL;

-- عرض النتائج
SELECT 
    '✅ تم إضافة/تحديث حقول الميزانية في جدول المشاريع' AS message;

-- عرض المشاريع التي لا تحتوي على ميزانية
SELECT 
    id,
    COALESCE(name, project_name, title, CONCAT('مشروع #', id)) AS project_name,
    total_budget,
    spent_amount,
    CASE 
        WHEN total_budget = 0 OR total_budget IS NULL THEN '⚠️ لم يتم تحديد الميزانية'
        ELSE '✅ الميزانية محددة'
    END AS status
FROM projects
ORDER BY id DESC
LIMIT 20;


