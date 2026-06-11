-- Phase 6G: Add cancellation columns to financial_transactions
-- Safe: uses IF NOT EXISTS, no DROP, no DELETE, no TRUNCATE

ALTER TABLE financial_transactions
    ADD COLUMN IF NOT EXISTS cancelled_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS cancelled_by_user_id INT NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS cancellation_reason TEXT NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS reversal_transaction_id INT NULL DEFAULT NULL;

-- Update status ENUM to include 'cancelled' if not already there
-- The current status is ENUM('pending','approved','draft') style - we need to add 'ملغى'
-- Check first: the column already has status. We update the enum safely.
ALTER TABLE financial_transactions
    MODIFY COLUMN status ENUM('معلق','معتمد','مسودة','ملغى') DEFAULT 'معتمد';
