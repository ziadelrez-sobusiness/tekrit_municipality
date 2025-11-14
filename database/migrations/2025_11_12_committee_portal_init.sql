-- ============================================
-- ترقية: نظام بوابة اللجان (12-11-2025)
-- ============================================

-- إنشاء جدول محاضر اجتماعات اللجان
CREATE TABLE IF NOT EXISTS `committee_sessions` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `committee_id` INT NOT NULL,
    `session_number` VARCHAR(100) DEFAULT NULL,
    `session_title` VARCHAR(255) NOT NULL,
    `session_date` DATE NOT NULL,
    `session_time` TIME DEFAULT NULL,
    `location` VARCHAR(255) DEFAULT NULL,
    `agenda` TEXT,
    `minutes` LONGTEXT,
    `attachments` TEXT,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_committee_sessions_committee` (`committee_id`),
    CONSTRAINT `fk_committee_sessions_committee`
        FOREIGN KEY (`committee_id`) REFERENCES `municipal_committees` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إنشاء جدول قرارات اللجان
CREATE TABLE IF NOT EXISTS `committee_decisions` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `committee_id` INT NOT NULL,
    `session_id` INT DEFAULT NULL,
    `decision_number` VARCHAR(100) DEFAULT NULL,
    `decision_title` VARCHAR(255) NOT NULL,
    `decision_text` LONGTEXT NOT NULL,
    `status` ENUM('قيد المتابعة','منفذ','مرفوض','معلق') DEFAULT 'قيد المتابعة',
    `due_date` DATE DEFAULT NULL,
    `implemented_at` DATE DEFAULT NULL,
    `notes` TEXT,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_committee_decisions_committee` (`committee_id`),
    KEY `idx_committee_decisions_session` (`session_id`),
    CONSTRAINT `fk_committee_decisions_committee`
        FOREIGN KEY (`committee_id`) REFERENCES `municipal_committees` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_committee_decisions_session`
        FOREIGN KEY (`session_id`) REFERENCES `committee_sessions` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إنشاء جدول الحركات المالية للجان
CREATE TABLE IF NOT EXISTS `committee_finance_transactions` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `committee_id` INT NOT NULL,
    `transaction_date` DATE NOT NULL,
    `transaction_type` ENUM('إيراد','مصروف') NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `currency_id` INT UNSIGNED DEFAULT NULL,
    `exchange_rate` DECIMAL(15,6) DEFAULT 1.0,
    `description` VARCHAR(255) DEFAULT NULL,
    `reference_type` VARCHAR(100) DEFAULT NULL,
    `reference_id` INT UNSIGNED DEFAULT NULL,
    `source` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_finance_transactions_committee` (`committee_id`),
    KEY `idx_finance_transactions_date` (`transaction_date`),
    CONSTRAINT `fk_finance_transactions_committee`
        FOREIGN KEY (`committee_id`) REFERENCES `municipal_committees` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إنشاء جدول ملخص الرصيد المالي للجان
CREATE TABLE IF NOT EXISTS `committee_finance_summary` (
    `committee_id` INT NOT NULL,
    `opening_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_income` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_expense` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `current_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`committee_id`),
    CONSTRAINT `fk_finance_summary_committee`
        FOREIGN KEY (`committee_id`) REFERENCES `municipal_committees` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- تعبئة بيانات الملخص للجان الحالية (قيمة ابتدائية صفرية)
INSERT INTO `committee_finance_summary` (`committee_id`)
SELECT mc.id
FROM `municipal_committees` mc
LEFT JOIN `committee_finance_summary` cfs ON mc.id = cfs.committee_id
WHERE cfs.committee_id IS NULL;

-- ربط الفواتير باللجان
ALTER TABLE `supplier_invoices`
    ADD COLUMN IF NOT EXISTS `committee_id` INT DEFAULT NULL AFTER `budget_item_id`,
    ADD CONSTRAINT `fk_supplier_invoices_committee`
        FOREIGN KEY (`committee_id`) REFERENCES `municipal_committees` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- ربط الدفعات باللجان (للإحصاءات)
ALTER TABLE `invoice_payments`
    ADD COLUMN IF NOT EXISTS `committee_id` INT DEFAULT NULL AFTER `invoice_id`,
    ADD CONSTRAINT `fk_invoice_payments_committee`
        FOREIGN KEY (`committee_id`) REFERENCES `municipal_committees` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- ربط المعاملات المالية باللجان
ALTER TABLE `financial_transactions`
    ADD COLUMN IF NOT EXISTS `committee_id` INT DEFAULT NULL AFTER `supplier_invoice_id`,
    ADD KEY IF NOT EXISTS `idx_financial_transactions_committee` (`committee_id`),
    ADD CONSTRAINT `fk_financial_transactions_committee`
        FOREIGN KEY (`committee_id`) REFERENCES `municipal_committees` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- تحديث اللجنة المرتبطة بالفواتير إلى الدفعات والمعاملات
UPDATE `invoice_payments` ip
JOIN `supplier_invoices` si ON ip.invoice_id = si.id
SET ip.committee_id = si.committee_id
WHERE si.committee_id IS NOT NULL;

UPDATE `financial_transactions` ft
JOIN `supplier_invoices` si ON ft.supplier_invoice_id = si.id
SET ft.committee_id = si.committee_id
WHERE ft.supplier_invoice_id IS NOT NULL
  AND si.committee_id IS NOT NULL;

-- ============================================
-- نهاية ملف الترقية
-- ============================================

