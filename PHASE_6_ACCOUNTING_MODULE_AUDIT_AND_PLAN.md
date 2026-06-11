# Phase 6 - Accounting Module Audit & Implementation Plan

## 1. Objective
The purpose of the accounting module for Tekrit Municipality is to provide a simple, practical, and transparent financial tracking system tailored to municipal work in Lebanon. The primary goals are:
- Implement simple municipal accounting.
- Track all income and expenses clearly.
- Manage invoices, receipts, and payment vouchers.
- Identify debts (receivables) and obligations (payables) reliably.
- Monitor budget usage in real-time.
- Ensure strict separation between USD and LBP currencies, avoiding mixed totals unless an exchange rate is explicitly applied.
- Generate accurate monthly and yearly financial reports.

## 2. Current Finance Module Overview
The project currently has a variety of standalone finance-related pages that handle fragmented pieces of the financial lifecycle. 

| File | Current Purpose | Related Tables | Works Well? | Notes |
|------|----------------|----------------|-------------|-------|
| `finance.php` | Main finance listing / transactions | `financial_transactions` | Yes | Needs separation of currencies. |
| `financial_dashboard.php` | Dashboard and KPIs | Multiple | Yes | Relies on potentially mixed currencies if not careful. |
| `budgets.php` | Managing municipality budgets | `budgets`, `budget_items` | Yes | Good foundation for budget limits. |
| `budgets_report.php` | Reports on budget usage | `budgets`, `budget_items` | Yes | Needs cross-checking with actual transactions. |
| `invoices.php` | Managing supplier invoices | `supplier_invoices` | Yes | Needs connection to payment vouchers. |
| `print_invoice.php` | Printing invoices | `supplier_invoices` | Yes | - |
| `suppliers.php` | Managing suppliers | `suppliers` | Yes | Needs supplier balance/statement view. |
| `tax_collection.php` | Collecting municipal taxes | `tax_collections` | Yes | Needs to tie into general income. |
| `tax_types.php` | Managing tax categories | `tax_types` | Yes | - |
| `donations.php` | Managing received donations | `donations` | Yes | Needs to tie into general income. |
| `contributions.php` | Managing project funding | `project_contributions` | Yes | Needs to tie into general income. |
| `currencies.php` | Managing exchange rates | `currencies` | Yes | USD/LBP base is well established. |
| `projects_finance.php` | Tracking project expenses | `projects`, transactions | Yes | Needs unified reporting. |
| `api/finance.php` | Financial API endpoints | `financial_transactions` | Yes | - |
| `api/financial_transactions.php` | Transaction endpoints | `financial_transactions` | Yes | - |
| `api/get_currencies.php` | Currency rate endpoints | `currencies` | Yes | - |
| `includes/currency_formatter.php` | Helper for UI formatting | N/A | Yes | Handles USD/LBP symbols well. |
| `includes/currency_helper.php` | Helper for conversion | `currencies` | Yes | - |

## 3. Current Database Finance Structure
The current system has a substantial foundation of finance tables. 

| Table | Purpose | Records Count | Main Columns | Current Usage | Problems |
|-------|---------|---------------|--------------|---------------|----------|
| `financial_transactions` | Central ledger for cash flow | 10 | id, type, amount, currency_id, category_id | High | Missing receipt/voucher linking. |
| `budgets` | High-level budgets | 1 | id, year, total_amount | Medium | - |
| `budget_items` | Specific budget lines | 14 | budget_id, name, allocated_amount | Medium | - |
| `budget_item_templates` | Predefined budget lines | 25 | name, category | Low | - |
| `fiscal_periods` | Financial years | 4 | year, status, start_date | Low | - |
| `currencies` | USD / LBP definitions | 2 | code, symbol, exchange_rate | High | - |
| `currency_conversion_log` | History of rates | 0 | from, to, rate, date | Low | - |
| `tax_types` | Fee definitions | 2 | name, default_amount | High | - |
| `tax_collections` | Paid municipal fees | 2 | tax_id, citizen_id, amount | High | Disconnected from main ledger. |
| `suppliers` | Vendor list | 1 | name, contact, balance | High | Lacks automated balance updates. |
| `supplier_invoices` | Bills from suppliers | 3 | supplier_id, total, status | High | - |
| `invoice_payments` | Payments on invoices | 3 | invoice_id, amount, date | High | Disconnected from main ledger. |
| `donations` | Received donations | 0 | donor, amount, currency | Medium | - |
| `donation_types` | Types of donations | 3 | name | Medium | - |
| `donation_statuses` | Status workflow | 6 | name | Medium | - |
| `donation_campaigns` | Crowdfunding | 0 | name, target | Low | - |
| `donors` | List of donors | 5 | name, contact | Medium | - |
| `project_contributions` | Money for projects | 0 | project_id, amount, date | Medium | - |
| `projects` | Project budgets/costs | 6 | budget, actual_cost | High | - |
| `committee_finance_summary`| Committee balances | 5 | committee_id, total_income | Medium | - |
| `committee_finance_transactions`| Committee ledger | 0 | committee_id, amount | Low | - |

## 4. What Is Missing
While the database covers many specific use cases, it lacks a unified accounting backbone. 

**Missing Elements:**
- **Unified Chart of Accounts:** No single list of standard income/expense categories (taxes, donations, and projects are tracked separately).
- **Cashbox / Treasury / Bank Accounts:** No way to know *where* the money is physically located (Cash vs Bank).
- **Official Receipts (وصل استلام):** Tax collections and donations don't generate unified, sequential municipal receipts.
- **Payment Vouchers (سند صرف):** Invoice payments don't generate unified payment vouchers.
- **Accounts Receivable / Payable:** Balances are scattered. We need a clear "who owes us" and "who we owe" view.
- **Monthly Closing:** No mechanism to freeze a month and calculate opening/closing balances.
- **Audit Trail:** Weak tracking of *who* deleted or modified a transaction.

## 5. Recommended Accounting Structure
The module should be organized into simple, municipal-friendly sections:

### 5.1 Accounting Dashboard
- Total income this month by currency.
- Total expenses this month by currency.
- Current cashbox/bank balances by currency.
- Unpaid invoices (Payables) by currency.
- Unpaid fees/taxes (Receivables) by currency.
- Budget usage gauge.

### 5.2 Income Management (مداخيل)
Consolidates all incoming money (Taxes, Fees, Donations, Transfers).
- **Fields:** Date, Payer Name, Payer Type, Amount, Currency, Income Category, Receipt Number, Payment Method, Received By, Notes, Attachment.

### 5.3 Expense Management (مصاريف)
Consolidates all outgoing money (Suppliers, Salaries, Maintenance).
- **Fields:** Date, Payee Name, Payee Type, Amount, Currency, Expense Category, Related Invoice, Payment Voucher Number, Payment Method, Approved By, Notes, Attachment.

### 5.4 Invoices (فواتير)
- Tracks obligations before they are paid.
- **Fields:** Supplier, Invoice #, Date, Due Date, Amount, Currency, Status (Draft/Pending/Partially Paid/Paid), Attachments.

### 5.5 Receipts (إيصالات استلام)
- Printable proof of income.
- **Fields:** Receipt #, Payer, Amount, Currency, Date, Reason, Linked Income ID.

### 5.6 Payment Vouchers (سندات صرف)
- Printable proof of expense.
- **Fields:** Voucher #, Payee, Amount, Currency, Date, Reason, Linked Expense ID.

### 5.7 Receivables and Payables (مستحقات)
- Clean tabular view showing names, phone numbers, and outstanding balances grouped by 0-30, 30-60, 60-90+ days.

### 5.8 Budget Management (الميزانية)
- Links every expense to a `budget_item_id` to enforce limits and show "Budget vs Actual".

### 5.9 Currency Management (إدارة العملات)
- **Strict Rule:** USD and LBP must NEVER be added together. All dashboards must have two distinct columns (Total USD, Total LBP) unless a specific "Convert to Base" toggle is activated.

### 5.10 Reports (التقارير)
- Monthly Income/Expense.
- Cashbox Movement.
- Statement of Account (for a specific supplier or citizen).

## 6. Proposed Database Additions
We should reuse existing tables where possible, but add the missing connective tissue.

| Proposed Table | Purpose | Key Columns | Relationship | Priority |
|----------------|---------|-------------|--------------|----------|
| `accounting_categories` | Unified Chart of Accounts | `id`, `name`, `type (income/expense)` | Links to `financial_transactions` | High |
| `accounting_receipts` | Sequential receipts | `id`, `receipt_number`, `transaction_id`, `payer` | Links to `financial_transactions` | High |
| `accounting_vouchers` | Sequential vouchers | `id`, `voucher_number`, `transaction_id`, `payee` | Links to `financial_transactions` | High |
| `accounting_cashboxes` | Bank/Cash accounts | `id`, `name`, `currency_id`, `balance` | Links to `financial_transactions` | Medium |
| `accounting_closings` | Monthly freezes | `id`, `month`, `year`, `usd_balance`, `lbp_balance` | - | Low |

*Note: `financial_transactions` will be heavily reused as the main ledger. `tax_collections` and `invoice_payments` should automatically insert a row into `financial_transactions`.*

## 7. Relationship With Existing Tables
- **`financial_transactions`**: Becomes the Single Source of Truth. If an `invoice_payment` happens, a trigger or PHP logic MUST write it to `financial_transactions`.
- **`budgets` & `budget_items`**: Every expense in `financial_transactions` should optionally link to a `budget_item_id`.
- **`tax_collections`**: Should generate an `accounting_receipts` record and a `financial_transactions` income row.
- **`supplier_invoices`**: Represents Payables. Payments against it go to `invoice_payments` -> `financial_transactions` -> `accounting_vouchers`.

## 8. Simplification Plan
To ensure municipal staff adopt the system:
- **Avoid Jargon:** Use Arabic terms like (مداخيل، مصاريف، إيصالات، فواتير، مستحقات).
- **One-Click Entry:** A single "Quick Add Expense" modal that automatically generates the voucher behind the scenes.
- **Unified Views:** Instead of checking 4 different pages for taxes, donations, and projects, staff should see a single "Treasury Movement" page.

## 9. User Roles and Permissions
- `view_finance`: Read-only access to dashboards.
- `add_income` / `add_expense`: Can create transactions but cannot delete them.
- `manage_invoices`: Can add/edit supplier invoices.
- `approve_expense`: Can finalize payment vouchers (Mayor/Head of Finance).
- `manage_budget`: Can alter yearly allocations.

## 10. Suggested UI Flow
1. **Clerk** receives cash from citizen for taxes -> Clicks "Add Income" -> System generates **Receipt** -> Prints Receipt.
2. **Supplier** delivers goods -> Clerk enters **Invoice**.
3. **Mayor** reviews pending Invoices -> Approves payment.
4. **Treasurer** gives cash to supplier -> Clicks "Pay Invoice" -> System generates **Payment Voucher** and deducts from Cashbox.
5. **End of Month**: Head of Municipality reviews the **Monthly Report** showing exact USD and LBP movements.

## 11. Implementation Roadmap
- **Phase A:** Audit and design (Completed via this document).
- **Phase B:** Create missing accounting tables (`categories`, `receipts`, `vouchers`, `cashboxes`).
- **Phase C:** Build the unified Income and Expense entry screens.
- **Phase D:** Implement printable Receipts and Payment Vouchers logic.
- **Phase E:** Refactor existing Invoices and Taxes to sync automatically with the unified ledger (`financial_transactions`).
- **Phase F:** Enforce budget tracking on expenses.
- **Phase G:** Build the clean, currency-separated Accounting Dashboard and Reports.

## 12. Risks
- **Mixing Currencies:** The biggest risk in Lebanon. The UI must strictly isolate USD and LBP.
- **Duplicate Data:** If `invoice_payments` and `financial_transactions` get out of sync. They must be tightly coupled.
- **Missing Approvals:** Expenses being paid without the Mayor's digital footprint.
- **Historical Data:** Legacy transactions might lack currency IDs or categories, requiring a safe migration script.

## 13. Final Recommendation
**Do not reinvent the wheel.** The database already has `financial_transactions`, `supplier_invoices`, and `budgets`. 
**What should be built first:** Build the unified `accounting_categories`, `accounting_receipts`, and `accounting_vouchers` tables. Then, build a centralized "Treasury / Cashbox" UI that writes to `financial_transactions` and generates sequential receipts/vouchers. 
**What should be avoided:** Avoid building a complex double-entry accounting system (debits/credits). Keep it to a simple "Cash In / Cash Out / Owed" municipal ledger.
