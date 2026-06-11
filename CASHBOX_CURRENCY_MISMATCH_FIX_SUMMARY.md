# Cashbox Currency Mismatch Fix Summary

## 1. Cause of the Issue
The default cashboxes were inserted into `accounting_cashboxes` with their `currency_id` values swapped:
- **Cashbox 1** ("صندوق البلدية - ليرة لبنانية") was incorrectly mapped to `currency_id = 2` (Dollar / USD).
- **Cashbox 2** ("صندوق البلدية - دولار أمريكي") was incorrectly mapped to `currency_id = 1` (Lebanese Pound / LBP).

This caused the JavaScript filter (`filterCashboxes`) to show the wrong cashbox when a currency was selected, since it compares `data-currency` attributes against the currency dropdown value — and those attribute values reflected the wrong database mapping.

## 2. Currency IDs Found
| ID | Currency Name    | Symbol |
|----|-----------------|--------|
| 1  | ليرة لبنانية (LBP) | ل.ل    |
| 2  | دولار أمريكي (USD) | $      |

## 3. Database Mappings Found (Before Fix)
| Cashbox ID | Name                            | currency_id (WRONG) |
|------------|---------------------------------|---------------------|
| 1          | صندوق البلدية - ليرة لبنانية   | 2 (USD) ❌          |
| 2          | صندوق البلدية - دولار أمريكي   | 1 (LBP) ❌          |

## 4. Database Fix Applied
Corrected via direct SQL:
```sql
UPDATE accounting_cashboxes SET currency_id = 1 WHERE id = 1;
UPDATE accounting_cashboxes SET currency_id = 2 WHERE id = 2;
```

SQL fix file saved at: `database/fix_cashbox_currency_mapping.sql`

## 5. Database Mappings (After Fix — Verified)
| Cashbox ID | Name                            | currency_id (CORRECT) |
|------------|---------------------------------|----------------------|
| 1          | صندوق البلدية - ليرة لبنانية   | 1 (LBP) ✅           |
| 2          | صندوق البلدية - دولار أمريكي   | 2 (USD) ✅           |

## 6. JavaScript Filtering
The `filterCashboxes()` function in `accounting_treasury.php` compares `option.getAttribute('data-currency')` against the selected `currencyId` string. The logic was **correct** — the root cause was purely the wrong database values. Once the DB was fixed, the JS naturally works correctly.

## 7. Server-Side Validation Added
Both the **income** and **expense** POST handlers now include a backend currency mismatch guard before committing any transaction:
```php
$check_cb = $db->prepare("SELECT currency_id FROM accounting_cashboxes WHERE id = ?");
$check_cb->execute([$cashbox_id]);
$actual_cb_currency = $check_cb->fetchColumn();

if ($actual_cb_currency != $currency_id) {
    $error = 'الصندوق المختار لا يتطابق مع العملة المختارة. يرجى اختيار صندوق بنفس عملة العملية.';
}
```
This makes the accounting system safe even if someone bypasses the frontend.

## 8. Visual Labels Added
Cashbox options in both income and expense dropdowns now display the currency symbol alongside the cashbox name:
- `صندوق البلدية - ليرة لبنانية (ل.ل)`
- `صندوق البلدية - دولار أمريكي ($)`

## 9. Files Modified
- `modules/accounting_treasury.php`

## 10. Files Backed Up
- `_archive/before_cashbox_currency_mismatch_fix/accounting_treasury.php`
- `_archive/before_cashbox_currency_mismatch_fix/accounting_cashboxes.php`

## 11. SQL File Created
- `database/fix_cashbox_currency_mapping.sql`

## 12. Testing Results
- PHP syntax passes 100% clean (`php -l`).
- DB verification confirms correct mapping after fix.
- `filterCashboxes()` JS logic verified correct — was not the cause.
- Backend validation blocks mismatched submissions at the server layer.
- No balances, transactions, or other data were modified.
