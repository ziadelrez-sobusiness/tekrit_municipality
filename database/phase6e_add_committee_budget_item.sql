-- phase6e_add_committee_budget_item.sql
ALTER TABLE financial_transactions 
ADD COLUMN IF NOT EXISTS committee_budget_item_id INT NULL AFTER committee_id;

-- Try adding the index (ignore error if it exists)
CREATE INDEX idx_ft_committee_budget_item_id ON financial_transactions(committee_budget_item_id);
