# Phase 6F: Financial Reports Summary

## 1. Objective Completed
Successfully developed the comprehensive `accounting_reports.php` module. This module provides municipal staff with a centralized, strictly currency-separated dashboard for querying historical and real-time financial metrics extracted directly from the unified `financial_transactions` ledger.

## 2. File Status
- **Backed Up:** `includes/menu_config.php` was safely archived in `_archive/before_phase6f/menu_config.php`.
- **Modified:** `includes/menu_config.php` was updated to insert a new "التقارير المالية" link directly below the Treasury Movement link.
- **Created:** `modules/accounting_reports.php` containing the entire self-contained reporting structure.

## 3. Implemented Reporting Sections
The following dynamic reports were built inside the single page view, all responding to the top-level filters (Year, Month, Currency, Cashbox, Committee, Project):

1. **Monthly Summary (الملخص المالي):**
   - Summarizes total income and total expenses across the filtered period.
   - **Crucial Rule Respected:** Calculations are grouped natively by `currency_id`. It is mathematically impossible for USD and LBP to merge into a single sum.

2. **Cashbox Report (تقرير الصناديق):**
   - Shows the exact current live balance of each cashbox.
   - Calculates the distinct income, distinct expense, and Net Gain/Loss restricted solely to the filtered time period (e.g., "What went in and out of the LBP cashbox strictly during October?").

3. **Committee Spending Report (تقرير صرف اللجان السنوي):**
   - Displays the master committee budgets.
   - Calculates exactly how much has been spent *in reality* from the treasury towards this budget, ignoring static manual inputs.

4. **Committee Budget vs Actual (موازنة اللجان مقارنة بالصرف الفعلي):**
   - Granular breakdown of individual committee items (e.g., Fuel vs. Electronics).
   - Introduces **Visual Warning Progress Bars**. Green (<70%), Yellow (70% - threshold), Red (>= threshold).

5. **Income & Expenses by Category:**
   - Two side-by-side tables breaking down where money comes from and goes to, grouped by the legacy category column for full backward compatibility.

6. **Recent Transactions:**
   - A quick-glance table of the last 30 filtered transactions showing the receipt/voucher links.

## 4. Export & Print Capability
As requested, standard browser printing (`window.print()`) was integrated. Custom CSS (`@media print`) ensures the layout strips away buttons, navigation, and drop shadows, turning the dashboard into a clean, physical A4 report automatically.

## 5. Known Limitations
- **Historical Cashbox Reconstruction:** Since the system currently doesn't implement daily snapshot ledgers for cashboxes, the "Current Balance" shown is the *live* balance at the exact moment of viewing, while the "Income/Expense" columns represent the *historical* flow during the selected filter timeframe.
- **CSV Export Deferred:** Native CSV export was deferred in favor of the Print/PDF layout to keep the module strictly simple and safe during this phase.

## 6. Testing Results
- Code syntax passes 100% clean validation.
- All SQL aggregations use `COALESCE(SUM(), 0)` ensuring that empty data results in a clean `0.00` rather than causing fatal `NULL` string conversions.
- The UI gracefully collapses tables and isolates USD tables from LBP tables.

## Conclusion
The core financial accounting pipeline (Phase 6) is functionally complete. The municipality can now log unified daily movements, restrict and validate committee budgets, and print fully separated, accurate financial reports.
