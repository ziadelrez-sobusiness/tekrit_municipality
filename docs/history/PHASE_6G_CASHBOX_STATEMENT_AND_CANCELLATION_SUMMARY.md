# Phase 6G: Cashbox Statement + Transaction Cancellation Summary

## 1. Files Created
| File | Purpose |
|------|---------|
| `modules/accounting_cashbox_statement.php` | Main cashbox statement page (كشف حركة الصندوق) |
| `modules/accounting_transaction_view.php` | Transaction detail view + safe edit |
| `modules/accounting_transaction_cancel.php` | Transaction cancellation with balance reversal |
| `database/phase6g_transaction_cancellation_columns.sql` | DB migration SQL |

## 2. Files Modified
| File | Change |
|------|--------|
| `includes/menu_config.php` | Added "كشف حركة الصندوق" to Finance menu |

## 3. Files Backed Up
All backed up to `_archive/before_phase6g/`:
- `includes/menu_config.php`
- `modules/accounting_treasury.php`
- `modules/accounting_reports.php`

## 4. Database Columns Added
All added safely to `financial_transactions` using `ADD COLUMN IF NOT EXISTS`:

| Column | Type | Purpose |
|--------|------|---------|
| `cancelled_at` | DATETIME NULL | Timestamp of cancellation |
| `cancelled_by_user_id` | INT NULL | Which user cancelled |
| `cancellation_reason` | TEXT NULL | Required reason text |
| `reversal_transaction_id` | INT NULL | For future reversal linking |

The `status` ENUM was extended to include `'ملغى'` (cancelled), alongside existing values.

## 5. Cashbox Statement Filters
- Cashbox (required, auto-selects first active cashbox)
- Date range (from / to) — defaults to current month
- Transaction type: All / Income / Expense
- Committee (optional)
- Project (optional)
- Category (optional)

## 6. Cancellation Logic
When a transaction is cancelled:
1. `financial_transactions.status` is set to `'ملغى'`
2. `cancelled_at`, `cancelled_by_user_id`, `cancellation_reason` are written
3. Cashbox balance is reversed atomically in a DB transaction:
   - **Income was cancelled** → `current_balance -= amount`
   - **Expense was cancelled** → `current_balance += amount`
4. Linked receipt: `accounting_receipts.status = 'cancelled'`
5. Linked voucher: `accounting_payment_vouchers.status = 'cancelled'`
6. Audit log entry written to `accounting_audit_log`

All wrapped in `$db->beginTransaction()` / `$db->commit()` / `$db->rollBack()`.

## 7. Safety Guarantees
- **No permanent deletion** — `financial_transactions` rows are never deleted
- **No double cancellation** — page checks `status = 'ملغى'` before showing the form
- **Negative balance warning** — projected post-cancel balance is shown. If negative, a red warning is displayed but the user may still proceed (accounting reality)
- **CSRF protected** — all POST actions use `csrf_protect(false)` + `csrf_input()`

## 8. Edit Constraints
The view/edit page allows editing ONLY:
- `description` (notes)
- `payment_method`
- `committee_id`
- `project_id`

Blocked from editing: `amount`, `currency_id`, `cashbox_id`, `type`. If these are wrong, the user must cancel the transaction and create a new correct one.

## 9. How Cancelled Transactions Are Treated
| Context | Behaviour |
|---------|-----------|
| `accounting_cashbox_statement.php` totals | **Excluded** (WHERE status != 'ملغى') |
| `accounting_cashbox_statement.php` table | **Shown** with strikethrough red style |
| `accounting_reports.php` totals | **Excluded** (base WHERE already had status != 'ملغى') |
| `accounting_treasury.php` | Not affected (only adds new records) |

## 10. Payment Method Display
- `payment_method` column already exists on `financial_transactions`
- Statement page uses `pmLabel()` helper to map raw values to Arabic labels
- Fallback: displays "غير محدد" if empty

## 11. Known Limitations
- **Opening balance calculation** uses a heuristic (current balance minus after-period net) rather than a true historical snapshot ledger. This is accurate for simple cases but may drift if data was imported historically.
- **Reversal transaction** linking (`reversal_transaction_id`) is reserved for future use — not yet auto-created on cancellation.

## 12. Testing Results
| Test | Result |
|------|--------|
| PHP syntax — all 6 files | ✅ No errors |
| DB migration applied | ✅ Columns added |
| `accounting_cashbox_statement.php` structure | ✅ Login-protected, filters, summary cards, table |
| `accounting_transaction_cancel.php` balance reversal logic | ✅ Atomic transaction |
| Double-cancel prevention | ✅ Status check before form display |
| `accounting_reports.php` excludes cancelled | ✅ Already had WHERE clause, confirmed |
| Menu integration | ✅ Added to Finance section |
