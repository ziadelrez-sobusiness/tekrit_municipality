# Official Municipal Budget Model Implementation Summary

## 1. Safety and Backup
The following files have been backed up securely to `_archive/before_official_municipal_budget_model/`:
- `modules/budgets.php`
- `modules/budgets_report.php`
- `modules/accounting_committee_budgets.php`
- `modules/accounting_treasury.php`
- `modules/accounting_reports.php`
- `modules/accounting_cashbox_statement.php`
- `includes/menu_config.php`

**Confirmations:**
- **NO old budgets were deleted.**
- **NO committee budget tables were deleted.**
- **NO test transactions were created.**
- Old pages (`budgets.php` and `budgets_report.php`) remain available and functioning for legacy reference.

## 2. Source Data Extraction
- **Source File:** `docs/budget_templates/نموذج_ميزانية_بلدية_تكريت.xlsx`
- **Sheets Parsed:** `قسم النفقات` (Expense Section) & `قسم الواردات` (Income Section)
- **Extraction Logic:** 
  - Iterated over the Excel file preserving Arabic names.
  - Automatically skipped headers (`قسم النفقات - تقديرات...`, `الباب / الفصل`) and summary `مجموع` rows.
  - Extracted 25 unique expense lines and 27 unique income lines.

| Metric | Expense Sheet | Income Sheet |
|---|---|---|
| Lines Found in Excel | 25 | 27 |
| Lines Imported to Template | 25 | 27 |

*Matches successfully. No lines were lost.*

### Skipped Rows:
- Header Rows: `قسم النفقات - تقديرات لموازنة لعام ......`, `الباب | الفصل | نوع النفقات...`
- Total Rows: `مجموع الباب الأول`, `مجموع الباب الثاني`
- Reason: These are dynamically calculated by the application UI and should not exist as individual database budget item rows.

## 3. Database Modifications
Created new relational tables securely inside `database/official_municipal_budget_model.sql` (and executed them):
1. `municipal_budget_templates`: Stores the Excel default template metadata.
2. `municipal_budget_template_lines`: Stores the 52 imported baseline items.
3. `municipal_budgets`: Annual budgets instantiated for specific fiscal years.
4. `municipal_budget_lines`: Actual cloned copies of the template for the annual budget with `current_estimate` and `actual_amount`.

**Table Alteration:**
- `ALTER TABLE financial_transactions ADD COLUMN municipal_budget_line_id INT NULL`
- `ALTER TABLE financial_transactions ADD COLUMN municipal_budget_id INT NULL`
- *No records were truncated, modified, or dropped.*

## 4. UI Modules Created
**File:** `modules/municipal_budget.php`
- **New Budget Creation:** Prompts for the fiscal year, copies the structure exactly from the Excel template `municipal_budget_template_lines` directly to `municipal_budget_lines` for that year.
- **Budget VS Actual Engine:** Evaluates exactly how much is spent vs estimated by querying `financial_transactions` grouped by `municipal_budget_line_id` dynamically.
- **Editing Estimates:** Allows the municipality to enter `current_estimate` and `explanation` row by row directly matching the Excel layout. Calculates all Chapter and Section totals live via PHP.

**File:** `modules/municipal_budget_print.php`
- A clean, print-friendly A4 format mirroring the exact grouping seen in Excel (`قسم الواردات`, `قسم النفقات`, grouped by `الباب`).

## 5. Treasury Integration (الحركة المالية)
- **File Modified:** `modules/accounting_treasury.php`
- **What Changed:** Added a distinct drop-down explicitly named **بند الموازنة البلدية الرسمية - إيراد/مصروف** mapped to active `municipal_budget_lines`. It automatically filters based on whether the user is adding Income or Expense.
- The selection safely saves to the newly created `municipal_budget_line_id` column.

## 6. Menu Integration
- **File Modified:** `includes/menu_config.php`
- Added **الموازنة البلدية 🏛️** under the Finance section.
- Renamed the legacy pages to **الميزانيات القديمة (للمراجعة فقط)** and **تقارير الميزانية القديمة (للمراجعة فقط)** to prevent user confusion while maintaining data safety.

---

## 7. Manual Testing Checklist
*Please perform these steps to ensure the logic perfectly matches the business requirements:*

1. [ ] Open **الموازنة البلدية** from the side menu.
2. [ ] Confirm the baseline template appears in the dropdown.
3. [ ] Create a budget for year `2026` or `2027`.
4. [ ] In the detail page, verify the imported expense lines exactly match the 25 items from the Excel file (spanning 2 Chapters).
5. [ ] Verify the imported income lines exactly match the 27 items from the Excel file (1 Chapter).
6. [ ] Enter some test `التقدير الحالي` values and verify the Chapter and Total summaries compute correctly.
7. [ ] Go to **الحركة المالية** and open "إضافة مدخول".
8. [ ] Choose a budget line from the new **بند الموازنة البلدية الرسمية - إيراد** dropdown and save.
9. [ ] Return to **الموازنة البلدية**, and observe that the `الفعلي (التحصيل/الصرف)` column live updates, calculating the exact `الفارق`.
10. [ ] Click `🖨️ طباعة الموازنة` and confirm the printed document reflects the Excel structure.
11. [ ] Check the menu and verify that committee budgets remain fully independent and operational.
