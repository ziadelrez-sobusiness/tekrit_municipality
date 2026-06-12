-- ======================================================================
-- Triggers للربط التلقائي - بلدية تكريت
-- ======================================================================
-- يجب تنفيذ هذا الملف بعد unify_projects_system.sql
-- يُنفذ مرة واحدة فقط عبر phpMyAdmin أو MySQL CLI
-- ======================================================================

USE `tekrit_municipality`;

-- ======================================================================
-- Trigger 1: تحديث المساهمات عند الإضافة
-- ======================================================================

DROP TRIGGER IF EXISTS `after_contribution_insert`;

DELIMITER $$

CREATE TRIGGER `after_contribution_insert`
AFTER INSERT ON `project_contributions`
FOR EACH ROW
BEGIN
    -- تحديث المبلغ المُجمّع في المشروع
    UPDATE `projects` 
    SET `contributions_collected` = `contributions_collected` + NEW.`contribution_amount`
    WHERE `id` = NEW.`project_id`;
    
    -- إنشاء معاملة مالية تلقائياً (إذا تم التحقق)
    IF NEW.`is_verified` = 1 THEN
        INSERT INTO `financial_transactions` 
        (
            `transaction_date`,
            `type`,
            `category`,
            `description`,
            `amount`,
            `currency_id`,
            `payment_method`,
            `reference_number`,
            `related_project_id`,
            `created_by`,
            `status`
        )
        VALUES 
        (
            NEW.`contribution_date`,
            'إيراد',
            'مساهمات شعبية',
            CONCAT('مساهمة من: ', NEW.`contributor_name`, ' في مشروع رقم ', NEW.`project_id`),
            NEW.`contribution_amount`,
            NEW.`currency_id`,
            NEW.`payment_method`,
            NEW.`reference_number`,
            NEW.`project_id`,
            NEW.`created_by`,
            'معتمد'
        );
        
        -- تحديث رقم المعاملة المالية في المساهمة
        UPDATE `project_contributions`
        SET `financial_transaction_id` = LAST_INSERT_ID()
        WHERE `id` = NEW.`id`;
    END IF;
END$$

DELIMITER ;

-- ======================================================================
-- Trigger 2: تحديث المساهمات عند الحذف
-- ======================================================================

DROP TRIGGER IF EXISTS `after_contribution_delete`;

DELIMITER $$

CREATE TRIGGER `after_contribution_delete`
AFTER DELETE ON `project_contributions`
FOR EACH ROW
BEGIN
    -- تحديث المبلغ المُجمّع في المشروع (طرح)
    UPDATE `projects` 
    SET `contributions_collected` = `contributions_collected` - OLD.`contribution_amount`
    WHERE `id` = OLD.`project_id`;
END$$

DELIMITER ;

-- ======================================================================
-- نهاية Triggers
-- ======================================================================

SELECT 'تم إنشاء Triggers بنجاح!' as status;


