# Budget Line Visibility, Edit & Search Fix Summary

## Files Backed Up
`_archive/before_budget_line_visibility_and_search_fix/`
- `accounting_treasury.php`
- `accounting_cashbox_statement.php`
- `accounting_transaction_view.php`

## Files Modified
| File | Tasks |
|---|---|
| `modules/accounting_cashbox_statement.php` | Task 1 |
| `modules/accounting_transaction_view.php` | Tasks 2, 3 |
| `modules/accounting_treasury.php` | Tasks 4, 5 |

---

## Task 1 — Official Budget Line in Cashbox Statement

**What was added:**
- Added `LEFT JOIN municipal_budget_lines mbl ON ft.municipal_budget_line_id = mbl.id` to the main SQL query
- Added `mbl_section_type`, `mbl_chapter_number`, `mbl_chapter_name`, `mbl_item_number`, `mbl_item_name` to SELECT
- Added new column header: **"🏛️ بند الموازنة الرسمية"** (highlighted indigo in header)
- Each row now shows formatted label: `قسم النفقات | الباب X - chapter_name | الفصل X.X - item_name`
- Truncated to 55 chars with tooltip showing full text
- If NULL: shows `غير مرتبط بموازنة` in light gray
- NULL-safe — does not break if `municipal_budget_line_id` is NULL

---

## Task 2 — Official Budget Line in Transaction Details

**What was added to `fetchTx()`:**
- Added `LEFT JOIN municipal_budget_lines mbl` and `LEFT JOIN municipal_budgets mb` 
- Now fetches: `mbl_section_type`, `mbl_section_name`, `mbl_chapter_number`, `mbl_chapter_name`, `mbl_item_number`, `mbl_item_name`, `mbl_fiscal_year`
- Also fetches `c.exchange_rate_to_iqd` from currencies

**New display card:** "🏛️ بند الموازنة البلدية الرسمية"
- Shows: القسم (green=واردات / red=نفقات), الباب, الفصل, السنة المالية
- If USD: shows سعر الصرف المستخدم
- If budget_amount_lbp present: shows المبلغ بالليرة in indigo
- If no line linked: shows "هذه الحركة غير مرتبطة ببند من الموازنة الرسمية."
- Card border is indigo if linked, gray if not

---

## Task 3 — Safe Edit of Budget Line in Transaction Details

**New POST handler logic:**
- Reads `$_POST['municipal_budget_line_id']` from edit form
- Validates with same nonce/CSRF check as existing edit
- Does NOT allow: amount, currency, cashbox, type changes
- Recalculates `budget_exchange_rate` and `budget_amount_lbp`:
  - LBP currency → rate=1, lbp=amount
  - USD or other → rate=`exchange_rate_to_iqd`, lbp=amount*rate
  - No line selected → all NULL
- Looks up `municipal_budget_id` from the selected line
- Updates: `municipal_budget_line_id`, `municipal_budget_id`, `budget_exchange_rate`, `budget_amount_lbp`
- Audit log entry: `action='update_transaction_budget_line'`

**Edit form selector:**
- Filtered by transaction type: income tx → only واردات lines, expense tx → only نفقات lines
- Search input: `id="edit-mbl-search"` filters options in real-time via `filterEditMbl()` JS
- Option label format: `[2026] قسم النفقات | الباب 2 - chapter_name | الفصل 2.3 - item_name`
- Pre-selected to current value if already linked
- Helper text: "اختر بند نفقات فقط. سيتم إعادة تحسيب المبلغ بالليرة تلقائياً عند الحفظ."

---

## Task 4 — Search Fix in Treasury Movement

**Root Cause Diagnosed:**
- `array_filter()` on a 0-indexed PHP array preserves numeric keys → `json_encode` produces a JSON object with numeric string keys like `{"0":{...},"1":{...}}`
- The old JS used `Object.values(data)` which was already correct, BUT the real user report of search "not working" was due to:
  1. The income dropdown was **empty** because the budget was in `draft` status when initially loaded (now approved)
  2. The placeholder text was not helpful
  3. No "no results found" feedback

**Fix Applied:**
- Changed to `array_values(array_filter(...))` → guaranteed clean JSON arrays
- Changed `Object.values(data)` → `data.forEach(...)` (works cleanly with arrays)
- Added `id="income-budget-noresult"` and `id="expense-budget-noresult"` divs that show when search returns 0 results
- Updated placeholder text:
  - Income: "ابحث في بنود الواردات حسب الباب أو الفصل أو اسم البند..."
  - Expense: "ابحث في بنود النفقات حسب الباب أو الفصل أو اسم البند..."
- Added `autocomplete="off"` to prevent browser autocomplete interfering

---

## Task 5 — Improved Selector Option Labels

**Format (treasury + edit form):**
```
[2026] قسم النفقات | الباب 2 - نفقات التجهيزات والصيانة | الفصل 2.4 - المحروقات
```

**Implementation:**
- `buildBudgetOption()` function builds full structured label
- Search string includes: chapter_number, chapter_name, item_number, item_name, fiscal_year, section label
- Works in both income and expense selectors in treasury, and in transaction edit form

---

## Task 6 — Budget vs Actual Updates Automatically

`municipal_budget.php` calculates actuals **live from `financial_transactions.municipal_budget_line_id`** — no cache.

When a budget line is changed on a transaction:
- Old line: next budget view load excludes that transaction → actual decreases
- New line: next budget view load includes that transaction → actual increases
- No additional code needed — the existing live query handles this

---

## Recalculation Logic Summary

```
if selected line IS NULL:
    municipal_budget_line_id = NULL
    municipal_budget_id = NULL
    budget_exchange_rate = NULL
    budget_amount_lbp = NULL

if currency IS LBP:
    budget_exchange_rate = 1
    budget_amount_lbp = amount

if currency IS USD (or other):
    budget_exchange_rate = currencies.exchange_rate_to_iqd
    budget_amount_lbp = amount * exchange_rate_to_iqd
```

---

## Confirmations
- ✅ No transactions deleted
- ✅ No test transactions created
- ✅ No budgets deleted
- ✅ Amount / currency / cashbox / type are NOT editable via the safe edit form
- ✅ NULL-safe throughout (LEFT JOINs, `?? ''` fallbacks)
- ✅ Audit log written for budget_line change

---

## Manual Testing Checklist

1. [ ] Open **الحركة المالية / Treasury Movement** → Income tab
2. [ ] Click on income budget line search → type "رسم" or "ترخيص"
3. [ ] Confirm only واردات lines appear with correct format
4. [ ] Try expense tab → search "صيانة" or "محروقات"
5. [ ] Confirm only نفقات lines appear
6. [ ] Search something with no result → confirm "لا توجد نتائج مطابقة" appears
7. [ ] Open **كشف حركة الصندوق**
8. [ ] Confirm column "🏛️ بند الموازنة الرسمية" appears in table header
9. [ ] For TX 23 (USD expense with صيانة المباني) → confirm it shows the budget line
10. [ ] For transactions with no linked line → confirm "غير مرتبط بموازنة"
11. [ ] Open **تفاصيل الحركة** for TX 23
12. [ ] Confirm "🏛️ بند الموازنة البلدية الرسمية" card appears with all details
13. [ ] Confirm سعر الصرف and المبلغ بالليرة appear (89,500 and 17,900,000)
14. [ ] Click ✎ تعديل → confirm budget line selector appears (نفقات only)
15. [ ] Search in edit form → confirm real-time filtering works
16. [ ] Change budget line to a different نفقات line → Save
17. [ ] Return to كشف الصندوق → confirm change is reflected
18. [ ] Open **الموازنة البلدية** → old line shows 0 actual, new line shows the amount
19. [ ] Open TX detail again → confirm new line is shown and LBP amount recalculated
