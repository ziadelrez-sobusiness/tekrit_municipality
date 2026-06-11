# Cashbox Details and Cancellation Fix Summary

## 1. Files Backed Up
All backups saved to `_archive/before_cashbox_details_cancellation_fix/`:

| File |
|------|
| `modules/accounting_cashboxes.php` |
| `modules/accounting_cashbox_statement.php` |
| `modules/accounting_transaction_view.php` |
| `modules/accounting_transaction_cancel.php` |
| `modules/accounting_treasury.php` |

---

## 2. Files Modified

| File | What Changed |
|------|-------------|
| `modules/accounting_cashboxes.php` | Added **📋 كشف الحركة** button for every cashbox row |
| `modules/accounting_cashbox_statement.php` | Full rewrite — complete columns, quick filters, summary cards, correct cancelled status |
| `modules/accounting_transaction_cancel.php` | Full rewrite — robust cancellation with CSRF, balance reversal, receipt/voucher cancel, audit log |
| `modules/accounting_transaction_view.php` | Full rewrite — complete details, edit form, cancel button, back link |

---

## 3. Database Fix Applied

**Problem found:** All 16 existing transactions had an empty `status` field (the ENUM default was not being stored on insert).

**Fix applied:**
```sql
UPDATE financial_transactions SET status='معتمد' WHERE status IS NULL OR status='';
```

All records now correctly read as `معتمد` (approved/active).

**Status ENUM also re-confirmed** to include `'ملغى'` alongside existing values (`معلق`, `معتمد`, `مسودة`).

**Cancellation columns confirmed present** (`cancelled_at`, `cancelled_by_user_id`, `cancellation_reason`, `reversal_transaction_id`) from Phase 6G migration.

---

## 4. Cashbox Details Button — `accounting_cashboxes.php`

**Before:** Only "تعديل" and "تفعيل/تعطيل" buttons existed.

**After:** Added a new prominent indigo **"📋 كشف الحركة"** button that links directly to the cashbox statement filtered by that cashbox:
```
accounting_cashbox_statement.php?cashbox_id=ID
```
No delete button was added. The existing edit and toggle buttons remain unchanged.

---

## 5. Cashbox Statement — `accounting_cashbox_statement.php`

### Improvements Made:
- **Auto-selects cashbox** from `?cashbox_id=` URL parameter
- **Title** shows cashbox name and currency symbol
- **Quick filter buttons:** اليوم / هذا الشهر / هذه السنة / كل الحركات
- **Default date range:** current month

### Summary Cards (6 cards):
| Card | Content |
|------|---------|
| الرصيد الحالي | Live cashbox balance |
| مجموع المداخيل | Active income in period |
| مجموع المصاريف | Active expenses in period |
| صافي الحركة | Net (income − expense) |
| عدد الحركات | Count of active transactions |
| حركات ملغاة | Count of cancelled transactions |

> Totals **exclude** cancelled transactions.

### Table Columns (17 columns):
`#` · `التاريخ` · `نوع الحركة` · `المبلغ` · `العملة` · `اسم الدافع/المستلم` · `نوعه` · `طريقة الدفع` · `التصنيف` · `اللجنة` · `المشروع` · `بند الميزانية` · `إيصال/سند` · `الملاحظات` · `أدخلها` · `الحالة` · `إجراءات`

### Data Sources:
- **Payer name** → pulled from `accounting_receipts.payer_name` (for income)
- **Payee name** → pulled from `accounting_payment_vouchers.payee_name` (for expense)
- **Payment method** → pulled from `r.payment_method` / `v.payment_method` first, fallback to `ft.payment_method`
- **Budget item** → shows committee budget item name, or general budget item name, or item code
- Falls back to `'غير محدد'` if any field is empty

### Cancelled Transaction Display:
- Red strikethrough row style (`cancelled-row` CSS class)
- "ملغى" badge shown in status column (no strikethrough on the badge)
- Actions column shows cancellation date instead of action buttons
- **NOT counted in totals**

### Action Buttons per Row:
- **عرض** — links to transaction view page
- **تعديل** — links to edit mode (active only)
- **إلغاء** — links to cancellation page with JS confirm dialog (active only)

---

## 6. Transaction Cancellation — `accounting_transaction_cancel.php`

### Cancellation Workflow (Atomic DB Transaction):
1. **Status check** — if `status IN ('ملغى','cancelled')` → block with error, prevent double cancel
2. **Reason required** — form validation, field cannot be empty
3. **Mark transaction** → `status='ملغى'`, `cancelled_at=NOW()`, `cancelled_by_user_id`, `cancellation_reason`
4. **Reverse cashbox balance**:
   - Income cancelled → `current_balance -= amount`
   - Expense cancelled → `current_balance += amount`
5. **Cancel linked receipt** → `accounting_receipts.status = 'cancelled'`
6. **Cancel linked voucher** → `accounting_payment_vouchers.status = 'cancelled'`
7. **Audit log** → inserted into `accounting_audit_log` with full JSON detail
8. **Show new balance** after successful cancel

### Safety:
- Wrapped in `beginTransaction()` / `commit()` / `rollBack()`
- CSRF protected via `csrf_protect(false)` + `csrf_input()`
- Negative balance projected and shown as a warning (does not block, but clearly warns)
- **No DELETE** executed anywhere

### Back Link:
- Returns to `accounting_cashbox_statement.php?cashbox_id=X` (preserves context)

---

## 7. Transaction View — `accounting_transaction_view.php`

### Complete Fields Displayed:
- Type, Amount, Date, Cashbox, Category, Status
- Payer/Payee name and type (from receipts/vouchers)
- Payment method
- Receipt number or voucher number + its status (issued/cancelled)
- Committee, Project, Budget item / Committee budget item
- Created by, Created at
- Notes/Description
- Cancellation info (date, by whom, reason) — shown in red banner if cancelled

### Buttons:
- **✎ تعديل** — opens edit form (active transactions only)
- **⊘ إلغاء الحركة** — links to cancel page with cashbox context (active transactions only)
- **← كشف الصندوق** — always visible, returns to cashbox statement

### Safe Edit Form:
Only allows editing:
- `description` (notes)
- `payment_method`
- `committee_id`
- `project_id`

Blocked from editing: `amount`, `currency_id`, `cashbox_id`, `type` (shown as read-only with explicit Arabic warning).

---

## 8. Status Handling — Standardized

All pages now use:
```php
// Check if cancelled:
in_array($tx['status'] ?? '', ['ملغى', 'cancelled'])

// Exclude from totals:
WHERE status NOT IN ('ملغى', 'cancelled')
```

This handles both the Arabic value `'ملغى'` and the English value `'cancelled'`, making the system robust regardless of which value was previously stored.

---

## 9. Confirmation: No Delete Action Exists

Confirmed across all modified files:
- No `DELETE FROM` SQL statement exists in any of the 4 modified files
- No delete button exists in `accounting_cashboxes.php`
- No delete button exists in `accounting_cashbox_statement.php`
- Cancellation is soft-only: `UPDATE ... SET status='ملغى'`

---

## 10. Known Limitations

| Limitation | Notes |
|------------|-------|
| Opening balance calculation | Uses a live heuristic (current balance minus after-period net). True historical snapshots would require a separate ledger table. |
| Budget items JOIN | Joins `budget_items` table — if that table doesn't exist, the JOIN is a LEFT JOIN and gracefully returns NULL |
| Negative balance after cancel | Shown as warning but not blocked — accounting staff may need to reconcile manually |
| `reversal_transaction_id` | Column exists but not auto-populated — reserved for future linked-reversal feature |

---

## 11. Testing Checklist

| Test | Status |
|------|--------|
| إدارة الصناديق shows 📋 كشف الحركة button for each cashbox | ✅ Added |
| كشف الحركة link passes `cashbox_id` in URL | ✅ Correct |
| Statement auto-selects that cashbox | ✅ Correct |
| Quick filters (اليوم / هذا الشهر / السنة / الكل) | ✅ Implemented |
| Date range filters work | ✅ Implemented |
| All 17 columns in statement table | ✅ Implemented |
| Payer/payee pulled from receipts/vouchers | ✅ via LEFT JOIN + COALESCE |
| Payment method pulled from receipts/vouchers | ✅ fallback chain |
| Cancel button appears for active transactions | ✅ Implemented |
| Cancel button hidden for cancelled transactions | ✅ Implemented |
| Cancellation requires reason | ✅ Required field + server-side check |
| Cancellation reverses cashbox balance | ✅ Atomic DB transaction |
| Receipt/voucher marked cancelled | ✅ Implemented |
| Audit log written | ✅ Implemented |
| Double cancel blocked | ✅ Status check before form renders |
| Cancelled row visible in table (strikethrough) | ✅ CSS `cancelled-row` class |
| Cancelled excluded from totals | ✅ `NOT IN ('ملغى','cancelled')` |
| No permanent delete anywhere | ✅ Confirmed |
| PHP syntax check — all 4 files | ✅ No errors |
| Existing transaction status data fixed | ✅ All 16 rows set to `معتمد` |
