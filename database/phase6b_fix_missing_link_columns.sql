-- Phase 6B: Fix Missing Link Columns
-- Safely add missing nullable link columns and indexes to existing tables

SET NAMES utf8mb4;

-- ==========================================
-- 1. financial_transactions
-- ==========================================
ALTER TABLE financial_transactions
    ADD COLUMN IF NOT EXISTS committee_id INT NULL,
    ADD COLUMN IF NOT EXISTS budget_item_id INT NULL,
    ADD COLUMN IF NOT EXISTS project_id INT NULL,
    ADD COLUMN IF NOT EXISTS invoice_id INT NULL,
    ADD COLUMN IF NOT EXISTS approved_by_user_id INT NULL;

ALTER TABLE financial_transactions
    ADD INDEX IF NOT EXISTS idx_ft_committee (committee_id),
    ADD INDEX IF NOT EXISTS idx_ft_project (project_id),
    ADD INDEX IF NOT EXISTS idx_ft_invoice (invoice_id),
    ADD INDEX IF NOT EXISTS idx_ft_approved_by (approved_by_user_id);

-- ==========================================
-- 2. supplier_invoices
-- ==========================================
ALTER TABLE supplier_invoices
    ADD COLUMN IF NOT EXISTS committee_id INT NULL,
    ADD COLUMN IF NOT EXISTS budget_item_id INT NULL,
    ADD COLUMN IF NOT EXISTS approved_by_user_id INT NULL;

ALTER TABLE supplier_invoices
    ADD INDEX IF NOT EXISTS idx_si_committee (committee_id),
    ADD INDEX IF NOT EXISTS idx_si_budget_item (budget_item_id),
    ADD INDEX IF NOT EXISTS idx_si_approved_by (approved_by_user_id);

-- ==========================================
-- 3. project_contributions
-- ==========================================
ALTER TABLE project_contributions
    ADD COLUMN IF NOT EXISTS transaction_id INT NULL;

ALTER TABLE project_contributions
    ADD INDEX IF NOT EXISTS idx_pc_transaction (transaction_id);

-- ==========================================
-- Safety note: No DROP, DELETE, TRUNCATE, or RENAME commands are included.
-- ==========================================
