# Phase 6D: Committee Budget UI Summary

## 1. Objective Completed
Successfully built the `accounting_committee_budgets.php` module. This module enables the municipality to allocate annual budgets for specific committees, assign detailed budget items, and automatically track expenditure progress based directly on real-time data from `financial_transactions`.

## 2. File Status
- **Created Backup:** The `includes/menu_config.php` file was safely backed up to `_archive/before_phase6d/menu_config.php`.
- **Created Module:** `modules/accounting_committee_budgets.php` was created from scratch using safe, isolated PHP/PDO structure.
- **Modified Menu:** `includes/menu_config.php` was updated to insert a new "ميزانية اللجان" shortcut right below the legacy budgets menu item.

## 3. Database Tables Utilized
- `accounting_committee_budgets`: Stores the overall annual budget per committee, its currency, and total assigned allocation.
- `accounting_committee_budget_items`: Stores the specific items (e.g., fuel, equipment) inside the committee budget, along with their individual allocations and warning thresholds.
- `financial_transactions`: Used in real-time as the **Single Source of Truth** to calculate the `spent_amount`.
- `municipal_committees` & `currencies`: Used as lookup tables for dropdowns and metadata.

## 4. Workflows Implemented
### A. Creating a Committee Budget
- The admin selects a fiscal year, a committee, a strict currency (USD or LBP), and defines an overall allocated amount.
- The system checks for duplicates (preventing the creation of multiple budgets for the exact same committee, year, and currency) and then saves the record as a draft.

### B. Adding Budget Items
- Once a budget is created, the admin clicks "إدارة البنود والتفاصيل" to enter the detail view.
- From there, individual budget items are added.
- The currency for the item is **automatically inherited** from the parent budget, enforcing strict USD/LBP separation without risking user input errors.
- A "warning threshold" (default 90%) is set to trigger visual warnings if spending nears the limit.

### C. Automatic Spend Tracking
- The system no longer trusts a static `spent_amount` integer that can drift out of sync.
- Instead, it calculates the real-time spent amount directly from `financial_transactions` using the `committee_id`, `budget_item_id`, and `currency_id` fields where `type = 'مصروف'`.
- The UI dynamically computes the remaining amount and percentage used for both the parent budget and its individual child items.

## 5. Currency Separation Logic
- A single committee budget is strictly tied to **one currency**.
- The spent calculations mandate that `financial_transactions.currency_id` matches the budget's currency. It is mathematically impossible for LBP expenditures to drain a USD committee budget.

## 6. Known Limitations
- **Legacy Budget Mapping:** Currently, legacy expenses from the old system that don't have a `budget_item_id` mapped strictly to the new schema won't show up in the itemized totals. However, they are protected from corruption.
- **Classification Sync:** A friendly warning is displayed indicating that exact classification mapping (Chapter/Article) is skipped for now, but manual items can still be added and tracked. This fulfills the user request to keep Phase 6D simple and safe.

## 7. Testing Results
- Code syntax linting (`php -l`) confirms 100% clean PHP code without errors.
- The UI properly loads the committees and currencies tables.
- The SQL mathematical aggregations (`COALESCE(SUM(amount), 0)`) ensure that blank budgets gracefully show `0.00` rather than throwing `NULL` errors.
- CSRF validation is successfully integrated into both the budget creation and item creation forms.

## Conclusion
Phase 6D is functionally complete and ready for use. The foundation is now set to easily link new daily expenses (from the Phase 6C Treasury UI) directly into these new committee budget items.
