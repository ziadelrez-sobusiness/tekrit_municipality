-- ======================================================================
-- سكريبت توحيد نظام المشاريع والمساهمات - بلدية تكريت
-- ======================================================================
-- التاريخ: 3 نوفمبر 2025
-- الهدف: توحيد جداول المشاريع وإضافة دعم المساهمات مع الربط المالي الكامل
-- ======================================================================

-- ======================================================================
-- الخطوة 1: إضافة الحقول الجديدة لجدول projects
-- ======================================================================

-- حقول المساهمات الشعبية
ALTER TABLE `projects` 
ADD COLUMN IF NOT EXISTS `allow_public_contributions` TINYINT(1) DEFAULT 0 COMMENT 'هل يقبل مساهمات شعبية؟' AFTER `notes`,
ADD COLUMN IF NOT EXISTS `contributions_target` DECIMAL(15,2) DEFAULT 0 COMMENT 'هدف المساهمات' AFTER `allow_public_contributions`,
ADD COLUMN IF NOT EXISTS `contributions_collected` DECIMAL(15,2) DEFAULT 0 COMMENT 'المبلغ المُجمّع' AFTER `contributions_target`,
ADD COLUMN IF NOT EXISTS `contributions_currency_id` INT DEFAULT 1 COMMENT 'عملة المساهمات' AFTER `contributions_collected`;

-- حقول العرض العام
ALTER TABLE `projects` 
ADD COLUMN IF NOT EXISTS `is_public` TINYINT(1) DEFAULT 0 COMMENT 'هل يُعرض للعامة؟' AFTER `contributions_currency_id`,
ADD COLUMN IF NOT EXISTS `is_featured` TINYINT(1) DEFAULT 0 COMMENT 'هل مشروع مميز؟' AFTER `is_public`,
ADD COLUMN IF NOT EXISTS `project_goal` TEXT COMMENT 'هدف المشروع' AFTER `is_featured`;

-- حقول المستفيدين
ALTER TABLE `projects` 
ADD COLUMN IF NOT EXISTS `beneficiaries_count` INT DEFAULT 0 COMMENT 'عدد المستفيدين' AFTER `project_goal`,
ADD COLUMN IF NOT EXISTS `beneficiaries_description` TEXT COMMENT 'وصف المستفيدين' AFTER `beneficiaries_count`;

-- حقول الصور
ALTER TABLE `projects` 
ADD COLUMN IF NOT EXISTS `main_image` VARCHAR(500) COMMENT 'الصورة الرئيسية' AFTER `beneficiaries_description`,
ADD COLUMN IF NOT EXISTS `gallery_images` TEXT COMMENT 'معرض الصور (JSON)' AFTER `main_image`,
ADD COLUMN IF NOT EXISTS `before_images` TEXT COMMENT 'صور قبل (JSON)' AFTER `gallery_images`,
ADD COLUMN IF NOT EXISTS `after_images` TEXT COMMENT 'صور بعد (JSON)' AFTER `before_images`;

-- إضافة فهارس للأداء
ALTER TABLE `projects`
ADD INDEX IF NOT EXISTS `idx_is_public` (`is_public`),
ADD INDEX IF NOT EXISTS `idx_allow_contributions` (`allow_public_contributions`),
ADD INDEX IF NOT EXISTS `idx_status` (`status`),
ADD INDEX IF NOT EXISTS `idx_project_type` (`project_type`);

-- إضافة المفتاح الخارجي لعملة المساهمات
ALTER TABLE `projects`
ADD CONSTRAINT `fk_contributions_currency` 
FOREIGN KEY (`contributions_currency_id`) REFERENCES `currencies`(`id`) 
ON DELETE RESTRICT ON UPDATE CASCADE;

-- ======================================================================
-- الخطوة 2: إنشاء جدول المساهمات
-- ======================================================================

CREATE TABLE IF NOT EXISTS `project_contributions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT NOT NULL COMMENT 'رقم المشروع',
    `contributor_name` VARCHAR(255) NOT NULL COMMENT 'اسم المساهم',
    `contributor_phone` VARCHAR(50) COMMENT 'هاتف المساهم',
    `contributor_email` VARCHAR(100) COMMENT 'بريد المساهم',
    `contributor_address` TEXT COMMENT 'عنوان المساهم',
    `contribution_amount` DECIMAL(15,2) NOT NULL COMMENT 'مبلغ المساهمة',
    `currency_id` INT NOT NULL COMMENT 'العملة',
    `contribution_date` DATE NOT NULL COMMENT 'تاريخ المساهمة',
    `payment_method` ENUM('نقد', 'شيك', 'تحويل مصرفي', 'بطاقة ائتمان', 'أخرى') DEFAULT 'نقد' COMMENT 'طريقة الدفع',
    `bank_name` VARCHAR(200) COMMENT 'اسم البنك',
    `check_number` VARCHAR(100) COMMENT 'رقم الشيك',
    `reference_number` VARCHAR(100) COMMENT 'الرقم المرجعي',
    `receipt_number` VARCHAR(100) COMMENT 'رقم الإيصال',
    `notes` TEXT COMMENT 'ملاحظات',
    `is_anonymous` TINYINT(1) DEFAULT 0 COMMENT 'مساهمة مجهولة؟',
    `is_verified` TINYINT(1) DEFAULT 0 COMMENT 'تم التحقق؟',
    `verified_by` INT COMMENT 'تم التحقق بواسطة',
    `verified_date` DATETIME COMMENT 'تاريخ التحقق',
    `financial_transaction_id` INT COMMENT 'رقم المعاملة المالية المرتبطة',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإنشاء',
    `created_by` INT COMMENT 'تم الإنشاء بواسطة',
    
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`currency_id`) REFERENCES `currencies`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`financial_transaction_id`) REFERENCES `financial_transactions`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_contribution_date` (`contribution_date`),
    INDEX `idx_is_verified` (`is_verified`),
    INDEX `idx_currency_id` (`currency_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول مساهمات المشاريع';

-- ======================================================================
-- الخطوة 3: نقل البيانات من development_projects إلى projects
-- ======================================================================

-- التحقق من وجود جدول development_projects
SET @table_exists = (
    SELECT COUNT(*) 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() 
    AND table_name = 'development_projects'
);

-- نقل البيانات إذا كان الجدول موجوداً
INSERT INTO `projects` 
(
    `project_name`, 
    `description`, 
    `project_type`, 
    `location`, 
    `start_date`, 
    `end_date`, 
    `budget`, 
    `budget_currency_id`, 
    `actual_cost`,
    `actual_cost_currency_id`,
    `progress_percentage`,
    `status`, 
    `contractor`, 
    `notes`,
    `allow_public_contributions`, 
    `contributions_target`, 
    `contributions_collected`,
    `contributions_currency_id`,
    `is_public`, 
    `is_featured`, 
    `project_goal`, 
    `beneficiaries_count`, 
    `beneficiaries_description`,
    `main_image`,
    `gallery_images`, 
    `before_images`, 
    `after_images`,
    `created_at`
)
SELECT 
    dp.`project_name`,
    dp.`project_description`,
    'إنمائي' as project_type,
    dp.`project_location`,
    dp.`start_date`,
    dp.`end_date`,
    dp.`project_cost`,
    1, -- افتراضياً ليرة لبنانية
    0,
    1,
    dp.`completion_percentage`,
    CASE dp.`project_status`
        WHEN 'مطروح' THEN 'مخطط'
        WHEN 'قيد التنفيذ' THEN 'قيد التنفيذ'
        WHEN 'منفذ' THEN 'مكتمل'
        WHEN 'متوقف' THEN 'متوقف'
        WHEN 'ملغي' THEN 'ملغي'
        ELSE 'مخطط'
    END,
    dp.`contractor`,
    CONCAT('مصدر التمويل: ', IFNULL(dp.`funding_source`, 'غير محدد')),
    dp.`allow_contributions`,
    dp.`contributions_target`,
    dp.`contributions_collected`,
    1, -- افتراضياً ليرة لبنانية
    1, -- يُعرض للعامة
    dp.`is_featured`,
    dp.`project_goal`,
    dp.`beneficiaries_count`,
    dp.`beneficiaries_description`,
    NULL, -- يمكن معالجة الصور لاحقاً
    dp.`project_images`,
    dp.`before_images`,
    dp.`after_images`,
    dp.`created_at`
FROM `development_projects` dp
WHERE @table_exists > 0
AND NOT EXISTS (
    SELECT 1 FROM `projects` p 
    WHERE p.`project_name` = dp.`project_name` 
    AND p.`location` = dp.`project_location`
); -- تجنب التكرار

-- ======================================================================
-- الخطوة 4: إضافة Triggers للربط التلقائي
-- ======================================================================
-- ملاحظة: Triggers سيتم تطبيقها في ملف منفصل بعد التنفيذ الأساسي

-- ======================================================================
-- الخطوة 5: إنشاء Views للتقارير السريعة
-- ======================================================================

-- View: ملخص المشاريع مع الإحصائيات
CREATE OR REPLACE VIEW `v_projects_summary` AS
SELECT 
    p.`id`,
    p.`project_name`,
    p.`project_type`,
    p.`status`,
    p.`location`,
    p.`budget`,
    bc.`currency_symbol` as budget_currency,
    p.`spent_amount`,
    p.`total_budget`,
    p.`contributions_target`,
    p.`contributions_collected`,
    cc.`currency_symbol` as contributions_currency,
    p.`progress_percentage`,
    p.`is_public`,
    p.`allow_public_contributions`,
    (SELECT COUNT(*) FROM `project_contributions` WHERE `project_id` = p.`id`) as contributions_count,
    (SELECT COUNT(*) FROM `financial_transactions` WHERE `related_project_id` = p.`id`) as transactions_count,
    CASE 
        WHEN p.`contributions_target` > 0 THEN 
            ROUND((p.`contributions_collected` / p.`contributions_target`) * 100, 2)
        ELSE 0 
    END as contributions_progress_percentage
FROM `projects` p
LEFT JOIN `currencies` bc ON p.`budget_currency_id` = bc.`id`
LEFT JOIN `currencies` cc ON p.`contributions_currency_id` = cc.`id`;

-- View: ملخص المساهمات
CREATE OR REPLACE VIEW `v_contributions_summary` AS
SELECT 
    pc.`id`,
    pc.`project_id`,
    p.`project_name`,
    pc.`contributor_name`,
    pc.`contribution_amount`,
    c.`currency_symbol`,
    c.`currency_code`,
    pc.`contribution_date`,
    pc.`payment_method`,
    pc.`is_verified`,
    pc.`is_anonymous`,
    u.`full_name` as verified_by_name
FROM `project_contributions` pc
INNER JOIN `projects` p ON pc.`project_id` = p.`id`
INNER JOIN `currencies` c ON pc.`currency_id` = c.`id`
LEFT JOIN `users` u ON pc.`verified_by` = u.`id`;

-- ======================================================================
-- الخطوة 6: إنشاء Stored Procedures للعمليات الشائعة
-- ======================================================================
-- ملاحظة: Stored Procedures سيتم تطبيقها في ملف منفصل بعد التنفيذ الأساسي

-- ======================================================================
-- الخطوة 7: إضافة بيانات تجريبية (اختياري)
-- ======================================================================

-- يمكن إضافة بيانات تجريبية هنا إذا لزم الأمر

-- ======================================================================
-- الخطوة 8: التحقق النهائي
-- ======================================================================

-- عرض إحصائيات سريعة
SELECT 
    'إجمالي المشاريع' as metric,
    COUNT(*) as value
FROM `projects`
UNION ALL
SELECT 
    'مشاريع عامة',
    COUNT(*)
FROM `projects`
WHERE `is_public` = 1
UNION ALL
SELECT 
    'مشاريع تقبل مساهمات',
    COUNT(*)
FROM `projects`
WHERE `allow_public_contributions` = 1
UNION ALL
SELECT 
    'إجمالي المساهمات',
    COUNT(*)
FROM `project_contributions`;

-- ======================================================================
-- نهاية السكريبت
-- ======================================================================
-- ملاحظات:
-- 1. تم إضافة دعم كامل للعملات المتعددة في كل مكان
-- 2. الربط التلقائي مع financial_transactions عبر Triggers
-- 3. Views جاهزة للتقارير السريعة
-- 4. Stored Procedures للعمليات المعقدة
-- 5. يمكن حذف جدول development_projects بعد التأكد من نقل البيانات
-- ======================================================================

