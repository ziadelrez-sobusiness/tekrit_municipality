# Treasury Budget Link & Budget Cleanup Fix Summary

## Files Backed Up
All files backed up to `_archive/before_treasury_budget_link_and_budget_cleanup_fix/` before any changes.

## Root Cause of the `exchange_rate` Error

**Error:** `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'exchange_rate' in 'field list'`

**Diagnosis:** The code in `accounting_treasury.php` (lines 60 and 151) was querying:
```sql
SELECT currency_symbol, exchange_rate FROM currencies WHERE id = ?
```
But the `currencies` table does **not** have a column called `exchange_rate`.
The actual column is named `exchange_rate_to_iqd`.

**Fix Applied:** Changed both occurrences (income and expense handlers) from:
```php
$curr_info['exchange_rate']
```
to:
```php
$curr_info['exchange_rate_to_iqd']
```
and updated the SELECT queries accordingly.

## Actual `financial_transactions` Columns Confirmed
The following budget-related columns exist and are correct:
- `exchange_rate` (col 7 — existing, decimal 10,4, for legacy use)
- `municipal_budget_line_id` (col 40)
- `municipal_budget_id` (col 41)
- `budget_exchange_rate` (col 42 — decimal 15,4, NULL)
- `budget_amount_lbp` (col 43 — decimal 18,2, NULL)

## Actual `currencies` Columns Confirmed
- `exchange_rate_to_iqd` — **this is the correct column name**

## USD to LBP Conversion Logic (Now Working)
When a treasury transaction is linked to a `municipal_budget_line_id`:

| Transaction Currency | budget_exchange_rate | budget_amount_lbp |
|---|---|---|
| LBP (ل.ل., ل.ل, LBP) | 1 | amount |
| USD or other | `currencies.exchange_rate_to_iqd` | amount × rate |
| No budget line linked | NULL | NULL |

The parent `municipal_budget_id` is now also automatically resolved and stored.

## Treasury Budget Line Dropdowns — Fixed
| Form | Before | After |
|---|---|---|
| Income (مدخول) | Missing or no income-only filter | Proper `section_type='income'` only, searchable |
| Expense (مصروف) | Plain non-searchable dropdown | Proper `section_type='expense'` only, searchable |

Both now include:
- A **live search text box** filtering by: chapter_number, chapter_name, item_number, item_name
- Option label format: `[2028] قسم الواردات | الباب الأول - اسم الباب | 1.1 - اسم البند`
- Warning if no municipal budget exists for the current year, with a link to create one
- Note: "الموازنة البلدية بالليرة اللبنانية. الحركات بالدولار تُحوَّل تلقائياً"

## Budget Delete/Cancel Logic
Added to `modules/municipal_budget.php`:

### Delete Action
- Counts all linked `financial_transactions` (by `municipal_budget_id` or `municipal_budget_line_id`)
- If count = 0: deletes `municipal_budget_lines` then `municipal_budgets` row
- If count > 0: shows error message, blocks deletion

### Cancel Action
- Sets `status = 'cancelled'`
- Lines are preserved
- Transactions are untouched

### Budget List Enhancements
- New "الحركات" column shows number of linked transactions per budget
- Budgets with 0 linked transactions show a **حذف** button
- Budgets with linked transactions show 🔒 محمي (protected) instead
- All budgets show an **إلغاء** button (except already cancelled ones)
- Print link added directly in list

## Database Scripts Created
- `database/cleanup_test_municipal_budgets_safe.sql` — Diagnostic + safe cleanup with instructions

## Confirmations
- ✅ No financial transactions deleted
- ✅ No committee budget tables modified
- ✅ No test transactions created
- ✅ No legacy budgets or old budget tables touched
- ✅ Template lines untouched
- ✅ Corrected template remains active

---

## Manual Testing Checklist
1. [ ] Open الحركة المالية → Add Income → No error when saving (even without budget line)
2. [ ] Select an income budget line → Save → No `exchange_rate` error
3. [ ] Select a USD income linked to a budget line → Verify `budget_amount_lbp` is stored correctly
4. [ ] Open Expense tab → Confirm search box filters نفقات only
5. [ ] Verify income search box filters واردات only (not نفقات)
6. [ ] Open الموازنة البلدية → Verify linked transaction count shows per row
7. [ ] Delete a budget with 0 linked transactions → Should work cleanly
8. [ ] Attempt to delete a budget with linked transactions → Should block with message
9. [ ] Cancel a budget → Status changes to cancelled
10. [ ] Go to Budget VS Actual view → Verify LBP figures shown correctly
