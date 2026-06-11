-- Phase 6B: Accounting, Committees & Budget Core Structures
-- This script safely creates core tables and alters existing ones using IF NOT EXISTS logic where possible.

SET NAMES utf8mb4;

-- ==========================================================
-- 1. Create Core Tables
-- ==========================================================

-- 1. accounting_budget_classifications (Flexible Lebanese budget hierarchy)
CREATE TABLE IF NOT EXISTS accounting_budget_classifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT NULL,
    code VARCHAR(50) NULL,
    name_ar VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NULL,
    type ENUM('income','expense') NOT NULL,
    level ENUM('chapter','article','item') NOT NULL,
    fiscal_year INT NULL,
    committee_id INT NULL,
    project_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES accounting_budget_classifications(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. accounting_categories (Simple accounting categories)
CREATE TABLE IF NOT EXISTS accounting_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NULL,
    type ENUM('income','expense') NOT NULL,
    classification_id INT NULL,
    parent_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (classification_id) REFERENCES accounting_budget_classifications(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_id) REFERENCES accounting_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. accounting_committee_budgets (Budget allocations per committee)
CREATE TABLE IF NOT EXISTS accounting_committee_budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    budget_id INT NULL,
    committee_id INT NOT NULL,
    fiscal_year INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    total_allocated DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    currency_id INT NOT NULL,
    status ENUM('draft','approved','closed','cancelled') DEFAULT 'draft',
    notes TEXT NULL,
    created_by_user_id INT NULL,
    approved_by_user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. accounting_committee_budget_items (Budget items per committee)
CREATE TABLE IF NOT EXISTS accounting_committee_budget_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    committee_budget_id INT NOT NULL,
    classification_id INT NULL,
    budget_item_id INT NULL,
    item_name VARCHAR(255) NOT NULL,
    allocated_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    spent_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    remaining_amount DECIMAL(15,2) NULL,
    currency_id INT NOT NULL,
    warning_threshold_percent INT DEFAULT 90,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (committee_budget_id) REFERENCES accounting_committee_budgets(id) ON DELETE CASCADE,
    FOREIGN KEY (classification_id) REFERENCES accounting_budget_classifications(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. accounting_receipts (Official municipal receipts)
CREATE TABLE IF NOT EXISTS accounting_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(100) UNIQUE NOT NULL,
    transaction_id INT NULL,
    committee_id INT NULL,
    project_id INT NULL,
    budget_item_id INT NULL,
    payer_name VARCHAR(255) NOT NULL,
    payer_type ENUM('citizen','government','donor','organization','other') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    currency_id INT NOT NULL,
    receipt_date DATE NOT NULL,
    payment_method ENUM('cash','bank_transfer','check','other') DEFAULT 'cash',
    received_by_user_id INT NULL,
    notes TEXT NULL,
    status ENUM('issued','cancelled') DEFAULT 'issued',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. accounting_payment_vouchers (Official payment vouchers)
CREATE TABLE IF NOT EXISTS accounting_payment_vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voucher_number VARCHAR(100) UNIQUE NOT NULL,
    transaction_id INT NULL,
    committee_id INT NULL,
    project_id INT NULL,
    budget_item_id INT NULL,
    payee_name VARCHAR(255) NOT NULL,
    payee_type ENUM('supplier','employee','contractor','citizen','other') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    currency_id INT NOT NULL,
    voucher_date DATE NOT NULL,
    payment_method ENUM('cash','bank_transfer','check','other') DEFAULT 'cash',
    paid_by_user_id INT NULL,
    approved_by_user_id INT NULL,
    notes TEXT NULL,
    status ENUM('draft','approved','paid','cancelled') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. accounting_cashboxes (Cashboxes and bank accounts)
CREATE TABLE IF NOT EXISTS accounting_cashboxes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('cash','bank','other') DEFAULT 'cash',
    currency_id INT NOT NULL,
    opening_balance DECIMAL(15,2) DEFAULT 0.00,
    current_balance DECIMAL(15,2) DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. accounting_attachments (Attachments)
CREATE TABLE IF NOT EXISTS accounting_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    related_type ENUM('transaction','receipt','voucher','invoice','budget','committee_budget','other') NOT NULL,
    related_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) NULL,
    uploaded_by_user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. accounting_audit_log (Audit Log)
CREATE TABLE IF NOT EXISTS accounting_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id INT NULL,
    old_data JSON NULL,
    new_data JSON NULL,
    ip_address VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. accounting_closings (Monthly closings)
CREATE TABLE IF NOT EXISTS accounting_closings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    closing_year INT NOT NULL,
    closing_month INT NOT NULL,
    currency_id INT NOT NULL,
    opening_balance DECIMAL(15,2) DEFAULT 0.00,
    total_income DECIMAL(15,2) DEFAULT 0.00,
    total_expenses DECIMAL(15,2) DEFAULT 0.00,
    closing_balance DECIMAL(15,2) DEFAULT 0.00,
    closed_by_user_id INT NULL,
    closed_at TIMESTAMP NULL,
    notes TEXT NULL,
    UNIQUE KEY (closing_year, closing_month, currency_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- 2. Add Nullable Columns to Existing Tables (MariaDB >= 10.2.8)
-- ==========================================================

ALTER TABLE financial_transactions
    ADD COLUMN IF NOT EXISTS budget_id INT NULL,
    ADD COLUMN IF NOT EXISTS receipt_id INT NULL,
    ADD COLUMN IF NOT EXISTS voucher_id INT NULL,
    ADD COLUMN IF NOT EXISTS cashbox_id INT NULL;

ALTER TABLE supplier_invoices
    ADD COLUMN IF NOT EXISTS project_id INT NULL;

ALTER TABLE invoice_payments
    ADD COLUMN IF NOT EXISTS transaction_id INT NULL,
    ADD COLUMN IF NOT EXISTS voucher_id INT NULL,
    ADD COLUMN IF NOT EXISTS cashbox_id INT NULL;

ALTER TABLE tax_collections
    ADD COLUMN IF NOT EXISTS transaction_id INT NULL,
    ADD COLUMN IF NOT EXISTS receipt_id INT NULL,
    ADD COLUMN IF NOT EXISTS committee_id INT NULL;

ALTER TABLE donations
    ADD COLUMN IF NOT EXISTS transaction_id INT NULL,
    ADD COLUMN IF NOT EXISTS receipt_id INT NULL,
    ADD COLUMN IF NOT EXISTS committee_id INT NULL;

ALTER TABLE project_contributions
    ADD COLUMN IF NOT EXISTS receipt_id INT NULL,
    ADD COLUMN IF NOT EXISTS committee_id INT NULL;

-- ==========================================================
-- 3. Seed Basic Data
-- ==========================================================

-- Seed categories if empty
INSERT INTO accounting_categories (name_ar, type, is_active, sort_order)
SELECT 'رسوم بلدية', 'income', 1, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM accounting_categories WHERE type = 'income');

INSERT INTO accounting_categories (name_ar, type, is_active, sort_order)
SELECT 'ضرائب ورسوم', 'income', 1, 2 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM accounting_categories WHERE name_ar = 'ضرائب ورسوم');

INSERT INTO accounting_categories (name_ar, type, is_active, sort_order)
SELECT 'دفعات من الدولة', 'income', 1, 3 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM accounting_categories WHERE name_ar = 'دفعات من الدولة');

INSERT INTO accounting_categories (name_ar, type, is_active, sort_order)
SELECT 'تبرعات', 'income', 1, 4 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM accounting_categories WHERE name_ar = 'تبرعات');

INSERT INTO accounting_categories (name_ar, type, is_active, sort_order)
SELECT 'مساهمات مشاريع', 'income', 1, 5 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM accounting_categories WHERE name_ar = 'مساهمات مشاريع');

INSERT INTO accounting_categories (name_ar, type, is_active, sort_order)
SELECT 'رسوم خدمات', 'income', 1, 6 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM accounting_categories WHERE name_ar = 'رسوم خدمات');

INSERT INTO accounting_categories (name_ar, type, is_active, sort_order)
SELECT 'مداخيل أخرى', 'income', 1, 7 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM accounting_categories WHERE name_ar = 'مداخيل أخرى');


INSERT INTO accounting_categories (name_ar, type, is_active, sort_order)
SELECT 'صيانة', 'expense', 1, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM accounting_categories WHERE type = 'expense');

INSERT INTO accounting_categories (name_ar, type, is_active, sort_order)
SELECT 'محروقات', 'expense', 1, 2 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM accounting_categories WHERE name_ar = 'محروقات');

INSERT INTO accounting_categories (name_ar, type, is_active, sort_order)
SELECT 'نفايات ونظافة', 'expense', 1, 3 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM accounting_categories WHERE name_ar = 'نفايات ونظافة');

INSERT INTO accounting_categories (name_ar, type, is_active, sort_order)
SELECT 'رواتب وأجور', 'expense', 1, 4 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM accounting_categories WHERE name_ar = 'رواتب وأجور');

INSERT INTO accounting_categories (name_ar, type, is_active, sort_order)
SELECT 'مصاريف مكتبية', 'expense', 1, 5 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM accounting_categories WHERE name_ar = 'مصاريف مكتبية');

INSERT INTO accounting_categories (name_ar, type, is_active, sort_order)
SELECT 'مصاريف مشاريع', 'expense', 1, 6 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM accounting_categories WHERE name_ar = 'مصاريف مشاريع');

INSERT INTO accounting_categories (name_ar, type, is_active, sort_order)
SELECT 'مصاريف لجان', 'expense', 1, 7 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM accounting_categories WHERE name_ar = 'مصاريف لجان');

INSERT INTO accounting_categories (name_ar, type, is_active, sort_order)
SELECT 'طوارئ', 'expense', 1, 8 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM accounting_categories WHERE name_ar = 'طوارئ');

INSERT INTO accounting_categories (name_ar, type, is_active, sort_order)
SELECT 'مصاريف أخرى', 'expense', 1, 9 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM accounting_categories WHERE name_ar = 'مصاريف أخرى');

-- Seed Cashboxes if empty
INSERT INTO accounting_cashboxes (name, type, currency_id, is_active)
SELECT 'صندوق البلدية - ليرة لبنانية', 'cash', 2, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM accounting_cashboxes WHERE currency_id = 2);

INSERT INTO accounting_cashboxes (name, type, currency_id, is_active)
SELECT 'صندوق البلدية - دولار أمريكي', 'cash', 1, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM accounting_cashboxes WHERE currency_id = 1);
