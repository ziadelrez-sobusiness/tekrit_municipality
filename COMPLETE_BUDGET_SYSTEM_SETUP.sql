-- ======================================================================
-- إعداد نظام الميزانيات الكامل لبلدية تكريت
-- ======================================================================
-- انسخ هذا الكود بالكامل والصقه في phpMyAdmin → SQL
-- ======================================================================

USE `tekrit_municipality`;

-- ═════════════════════════════════════════════════════════════════════
-- 1. إضافة عمود committee_id لجدول budgets
-- ═════════════════════════════════════════════════════════════════════

ALTER TABLE `budgets` 
ADD COLUMN IF NOT EXISTS `committee_id` INT NULL COMMENT 'اللجنة المرتبطة بالميزانية' 
AFTER `currency_id`;

-- إضافة المفتاح الأجنبي
SET @constraint_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = 'tekrit_municipality' 
    AND TABLE_NAME = 'budgets' 
    AND CONSTRAINT_NAME = 'fk_budget_committee'
);

SET @sql = IF(@constraint_exists = 0,
    'ALTER TABLE `budgets` ADD CONSTRAINT `fk_budget_committee` FOREIGN KEY (`committee_id`) REFERENCES `municipal_committees`(`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT "Foreign key already exists" as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ═════════════════════════════════════════════════════════════════════
-- 2. إضافة عمود currency_id لجدول budget_items
-- ═════════════════════════════════════════════════════════════════════

ALTER TABLE `budget_items` 
ADD COLUMN IF NOT EXISTS `currency_id` INT NULL DEFAULT 1 COMMENT 'عملة البند' 
AFTER `allocated_amount`;

-- إضافة المفتاح الأجنبي
SET @constraint_exists2 = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = 'tekrit_municipality' 
    AND TABLE_NAME = 'budget_items' 
    AND CONSTRAINT_NAME = 'fk_budget_item_currency'
);

SET @sql2 = IF(@constraint_exists2 = 0,
    'ALTER TABLE `budget_items` ADD CONSTRAINT `fk_budget_item_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE',
    'SELECT "Foreign key already exists" as status'
);

PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- تحديث البنود الموجودة لتأخذ العملة الافتراضية
UPDATE `budget_items` 
SET `currency_id` = 1 
WHERE `currency_id` IS NULL;

-- ═════════════════════════════════════════════════════════════════════
-- 3. إنشاء جدول قوالب بنود الميزانية (budget_item_templates)
-- ═════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `budget_item_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `committee_id` INT NOT NULL,
    `item_code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `item_type` ENUM('إيراد', 'مصروف') DEFAULT 'مصروف',
    `category` VARCHAR(100),
    `default_amount` DECIMAL(15,2) DEFAULT 0,
    `currency_id` INT DEFAULT 1,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`committee_id`) REFERENCES `municipal_committees`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`currency_id`) REFERENCES `currencies`(`id`) ON DELETE RESTRICT,
    
    INDEX `idx_committee` (`committee_id`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='قوالب بنود الميزانية للجان';

-- ═════════════════════════════════════════════════════════════════════
-- 4. إضافة قوالب بنود افتراضية لكل لجنة
-- ═════════════════════════════════════════════════════════════════════

-- إضافة قوالب بنود عامة لجميع اللجان
INSERT INTO `budget_item_templates` 
(`committee_id`, `item_code`, `name`, `description`, `item_type`, `category`, `default_amount`, `currency_id`) 
SELECT 
    mc.id,
    CONCAT('ITEM-', mc.id, '-001'),
    CONCAT('رواتب وأجور - ', mc.committee_name),
    'رواتب الموظفين والعمال التابعين للجنة',
    'مصروف',
    'رواتب',
    50000,
    1
FROM `municipal_committees` mc
WHERE mc.is_active = 1
AND NOT EXISTS (
    SELECT 1 FROM `budget_item_templates` 
    WHERE committee_id = mc.id AND item_code = CONCAT('ITEM-', mc.id, '-001')
);

INSERT INTO `budget_item_templates` 
(`committee_id`, `item_code`, `name`, `description`, `item_type`, `category`, `default_amount`, `currency_id`) 
SELECT 
    mc.id,
    CONCAT('ITEM-', mc.id, '-002'),
    CONCAT('مستلزمات وقرطاسية - ', mc.committee_name),
    'مواد مكتبية ومستلزمات عمل اللجنة',
    'مصروف',
    'تشغيلية',
    5000,
    1
FROM `municipal_committees` mc
WHERE mc.is_active = 1
AND NOT EXISTS (
    SELECT 1 FROM `budget_item_templates` 
    WHERE committee_id = mc.id AND item_code = CONCAT('ITEM-', mc.id, '-002')
);

INSERT INTO `budget_item_templates` 
(`committee_id`, `item_code`, `name`, `description`, `item_type`, `category`, `default_amount`, `currency_id`) 
SELECT 
    mc.id,
    CONCAT('ITEM-', mc.id, '-003'),
    CONCAT('صيانة وخدمات - ', mc.committee_name),
    'صيانة المعدات والأصول التابعة للجنة',
    'مصروف',
    'صيانة',
    10000,
    1
FROM `municipal_committees` mc
WHERE mc.is_active = 1
AND NOT EXISTS (
    SELECT 1 FROM `budget_item_templates` 
    WHERE committee_id = mc.id AND item_code = CONCAT('ITEM-', mc.id, '-003')
);

INSERT INTO `budget_item_templates` 
(`committee_id`, `item_code`, `name`, `description`, `item_type`, `category`, `default_amount`, `currency_id`) 
SELECT 
    mc.id,
    CONCAT('ITEM-', mc.id, '-004'),
    CONCAT('مشتريات ومعدات - ', mc.committee_name),
    'شراء معدات وأدوات عمل جديدة',
    'مصروف',
    'مشتريات',
    15000,
    1
FROM `municipal_committees` mc
WHERE mc.is_active = 1
AND NOT EXISTS (
    SELECT 1 FROM `budget_item_templates` 
    WHERE committee_id = mc.id AND item_code = CONCAT('ITEM-', mc.id, '-004')
);

INSERT INTO `budget_item_templates` 
(`committee_id`, `item_code`, `name`, `description`, `item_type`, `category`, `default_amount`, `currency_id`) 
SELECT 
    mc.id,
    CONCAT('ITEM-', mc.id, '-005'),
    CONCAT('خدمات استشارية - ', mc.committee_name),
    'خدمات استشارية وتدريب',
    'مصروف',
    'خدمات',
    8000,
    1
FROM `municipal_committees` mc
WHERE mc.is_active = 1
AND NOT EXISTS (
    SELECT 1 FROM `budget_item_templates` 
    WHERE committee_id = mc.id AND item_code = CONCAT('ITEM-', mc.id, '-005')
);

-- ═════════════════════════════════════════════════════════════════════
-- 5. التحقق من النجاح
-- ═════════════════════════════════════════════════════════════════════

SELECT '✅ تم إعداد نظام الميزانيات الكامل بنجاح!' as 'النتيجة';

-- عرض الإحصائيات
SELECT 
    'budgets' as 'الجدول',
    CASE WHEN COUNT(*) > 0 THEN '✅' ELSE '❌' END as 'committee_id موجود'
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'tekrit_municipality' 
AND TABLE_NAME = 'budgets' 
AND COLUMN_NAME = 'committee_id'

UNION ALL

SELECT 
    'budget_items' as 'الجدول',
    CASE WHEN COUNT(*) > 0 THEN '✅' ELSE '❌' END as 'currency_id موجود'
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'tekrit_municipality' 
AND TABLE_NAME = 'budget_items' 
AND COLUMN_NAME = 'currency_id'

UNION ALL

SELECT 
    'budget_item_templates' as 'الجدول',
    CASE WHEN COUNT(*) > 0 THEN '✅' ELSE '❌' END as 'الجدول موجود'
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'tekrit_municipality' 
AND TABLE_NAME = 'budget_item_templates';

-- عرض عدد القوالب المضافة لكل لجنة
SELECT 
    mc.committee_name as 'اللجنة',
    COUNT(btemp.id) as 'عدد قوالب البنود'
FROM tekrit_municipality.municipal_committees mc
LEFT JOIN tekrit_municipality.budget_item_templates btemp ON mc.id = btemp.committee_id
WHERE mc.is_active = 1
GROUP BY mc.id, mc.committee_name
ORDER BY mc.committee_name;

-- ======================================================================
-- ✅ تم! الآن يمكنك استخدام نظام الميزانيات الكامل
-- ======================================================================

-- للتجربة:
-- 1. افتح: municipality_management.php?tab=committees
-- 2. اضغط زر "💰 الميزانية" بجانب أي لجنة
-- 3. اضغط "⚡ إنشاء ميزانية تلقائية"
-- 4. ستُنشأ الميزانية مع جميع البنود تلقائياً!

