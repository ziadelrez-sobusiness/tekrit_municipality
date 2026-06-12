# Budget Activation & Actuals Fix Summary

## Files Backed Up
`_archive/before_budget_activation_and_actuals_fix/`
- `accounting_treasury.php`
- `municipal_budget.php`
- `municipal_budget_print.php`

## Files Modified
- `modules/municipal_budget.php`
- `modules/municipal_budget_print.php`

---

## Issue 1: Budget Status / Activation

### Root Cause
The budget detail page showed raw status values (e.g., `draft`) with no way to change them.

### Fix Applied
Added two new POST handlers in `municipal_budget.php`:

| Handler | What it does |
|---|---|
| `approve_budget` | Sets `status='approved'`, `approved_by_user_id=current_user`, `approved_at=NOW()` |
| `revert_to_draft` | Sets `status='draft'` — only allowed if 0 linked transactions (admin only) |

### Status Labels (Arabic)
| DB value | Arabic label displayed |
|---|---|
| `draft` | مسودة |
| `approved` | معتمدة / فعالة |
| `closed` | مقفلة |
| `cancelled` | ملغاة |

### UI Changes
- The budget view header now shows the Arabic status label in color
- `draft` → shows green **"اعتماد الموازنة"** button
- `approved` → shows green ✅ badge + admin-only "إرجاع إلى مسودة" button
- `cancelled` → shows ❌ badge, no edit allowed
- `closed` → shows 🔒 badge, no edit allowed

### Budget Year 2016
The 2016 budget (ID=4, fiscal_year=2026 in actual data) is in `draft` status. Opening it now shows the **"اعتماد الموازنة"** button. Clicking it approves the budget immediately.

---

## Issue 2: USD Expense Not Appearing in Budget Actuals

### Diagnosis Steps
1. Queried `financial_transactions` for transactions with `municipal_budget_line_id IS NOT NULL`
2. Found TX id=23: `amount=200.00 USD`, `budget_exchange_rate=0.0001`, `budget_amount_lbp=0.02`
3. Expected: `budget_exchange_rate=89500`, `budget_amount_lbp=17,900,000`
4. Confirmed `currencies.exchange_rate_to_iqd = 89500` is correct
5. Confirmed `budget_exchange_rate DECIMAL(15,4)` can hold 89500 without truncation

### Root Cause
TX 23 was created when the exchange rate code was still buggy (the previous session's version that used wrong column name `exchange_rate` which returned NULL, and the fallback `?? 89500` was somehow not triggering or the transaction predates our fix). The stored `budget_exchange_rate = 0.0001` is incorrect.

### Data Fix Applied
The bad stored values in TX 23 were corrected:
```sql
UPDATE financial_transactions 
SET budget_exchange_rate=89500, budget_amount_lbp=17900000 
WHERE id=23;
```
✅ Confirmed: After update, `budget_exchange_rate=89500.0000`, `budget_amount_lbp=17900000.00`

### Actuals Query Fix (municipal_budget.php)
The old actuals query had two problems:
1. **Did not filter by transaction type** — an expense transaction could be counted as income and vice versa
2. **Did not fall back properly** for USD without stored `budget_amount_lbp`

New actuals query filters:
```sql
WHERE (mbl.section_type = 'income' AND ft.type IN ('إيراد','مدخول','income'))
   OR (mbl.section_type = 'expense' AND ft.type IN ('مصروف','expense','نفقة'))
```

New LBP calculation (with fallback chain):
```sql
SUM(
    COALESCE(
        ft.budget_amount_lbp,                              -- 1st: use stored LBP amount
        CASE 
            WHEN c.currency_symbol IN ('LBP','ل.ل.','ل.ل') THEN ft.amount   -- 2nd: LBP direct
            WHEN c.exchange_rate_to_iqd > 0 THEN ft.amount * c.exchange_rate_to_iqd  -- 3rd: compute
            ELSE 0
        END
    )
)
```

### Draft Budgets Also Show Actuals
The actuals query does **not** filter by `budget.status`, so even draft budgets show actual amounts from linked transactions.

---

## Linked Transaction Count Display
Each budget line in the detail table now shows:
- A clickable badge: **"N حركة"** — count of linked active transactions
- The badge links to the cashbox statement page for inspection
- ⚠️ warning icon if USD transactions lack a stored exchange rate

---

## Print File Updates
`municipal_budget_print.php` now:
- Shows Arabic status label
- Adds "الفعلي (ل.ل.)" column alongside "تقديرات الموازنة"
- Summary box shows both estimated and actual totals
- Chapter footer rows show both estimate sum and actual sum
- Uses the same `$line['actual_amount']` and `$totals` arrays as the edit view

---

## Exchange Rate Source
- Column used: `currencies.exchange_rate_to_iqd`
- LBP detection: `currency_symbol IN ('LBP', 'ل.ل.', 'ل.ل')`
- Fallback order: stored `budget_amount_lbp` → LBP direct → computed from rate

---

## Confirmations
- ✅ No financial transactions deleted
- ✅ No test transactions created
- ✅ No budgets deleted
- ✅ TX 23 data corrected (bad exchange rate `0.0001` → correct `89500`)
- ✅ No committee budget tables touched
- ✅ No unrelated modules modified

---

## Manual Testing Checklist
1. [ ] Open الموازنة البلدية → find the 2026 budget
2. [ ] Open budget detail → see "مسودة" in yellow with green "اعتماد الموازنة" button
3. [ ] Click "اعتماد الموازنة" → confirm status changes to "معتمدة / فعالة" ✅
4. [ ] Verify approved_at timestamp appears under the status
5. [ ] Open صيانة المباني expense line → confirm actual shows 17,900,000 ل.ل. (from TX 23)
6. [ ] Confirm "1 حركة" badge appears on that line
7. [ ] Add new USD expense linked to an expense budget line → verify it appears in LBP
8. [ ] Add new LBP income linked to an income budget line → verify it appears directly
9. [ ] Print the budget → verify actual column appears alongside estimate
10. [ ] Test "إرجاع إلى مسودة" (admin only) on a budget with 0 transactions → should work
11. [ ] Test "إرجاع إلى مسودة" on budget with linked transactions → should be blocked
