-- database/cleanup_test_municipal_budgets_safe.sql
-- SAFE cleanup script for test municipal budgets.
-- Run this ONLY after reviewing the results of the diagnostic query below.
-- This script will NEVER delete financial_transactions.

-- STEP 1: Diagnose — List all budgets and their linked transaction counts
SELECT 
    b.id,
    b.fiscal_year,
    b.title,
    b.status,
    (SELECT COUNT(*) FROM municipal_budget_lines WHERE municipal_budget_id = b.id) AS lines_count,
    (
        SELECT COUNT(*) FROM financial_transactions ft
        WHERE ft.municipal_budget_id = b.id
           OR ft.municipal_budget_line_id IN (
               SELECT id FROM municipal_budget_lines WHERE municipal_budget_id = b.id
           )
    ) AS linked_transactions
FROM municipal_budgets b
ORDER BY b.created_at DESC;

-- STEP 2: Safe Delete — Only budgets with 0 linked transactions
-- Uncomment the DELETE statements below ONLY after confirming the budget IDs are safe.

-- Example (replace ? with the budget ID you want to delete):
-- DELETE FROM municipal_budget_lines WHERE municipal_budget_id = ?;
-- DELETE FROM municipal_budgets WHERE id = ? AND (
--     SELECT COUNT(*) FROM financial_transactions ft
--     WHERE ft.municipal_budget_id = ?
--        OR ft.municipal_budget_line_id IN (SELECT id FROM municipal_budget_lines WHERE municipal_budget_id = ?)
-- ) = 0;

-- STEP 3: Cancel budgets with linked transactions (do NOT delete them)
-- UPDATE municipal_budgets SET status = 'cancelled' WHERE id = ? AND (linked_count > 0);

-- NOTE: financial_transactions are NEVER deleted by this script.
-- NOTE: municipal_budget_template_lines are NEVER touched by this script.
-- NOTE: committee budget tables are NEVER touched by this script.
