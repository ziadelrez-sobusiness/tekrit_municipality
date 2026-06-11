# Phase 6 - Accounting, Committees & Budget Audit and Implementation Plan

## 1. Objective
The goal of this accounting and budget module is to provide Tekrit Municipality with a clear, practical, and highly organized financial system. It extends standard accounting by deeply integrating municipal committees and official budget structures. 

The primary objectives are:
- Simple, transparent municipal accounting (Income, Expense, Invoices, Receipts, Payment Vouchers).
- Clear tracking of Debts (Receivables) and Obligations (Payables).
- Creation and management of a **General Annual Municipal Budget**.
- Creation and management of **Committee-Specific Budgets** and budget items.
- Real-time **Budget vs Actual** tracking at the general level, committee level, and budget item level.
- Linkage of transactions (income/expense) to specific committees and specific projects.
- Strict and uncompromised separation of **USD and LBP** currencies.
- Delivery of comprehensive monthly, yearly, and committee-specific financial reporting.

## 2. Current Finance and Committee Finance Overview
The existing project contains several fragmented files for finance, budgets, and committees.

| File | Current Purpose | Related Tables | Has Committee Link? | Has Budget Link? | Has Currency Link? | Notes |
|------|-----------------|----------------|---------------------|------------------|--------------------|-------|
| `finance.php` | General ledger | `financial_transactions` | No | No | Yes | Needs overhaul to support budgets/committees. |
| `financial_dashboard.php`| General KPIs | Multiple | No | No | Yes | Prone to mixing currencies. |
| `budgets.php` | General budget | `budgets`, `items` | No | Yes | Yes | Too basic, lacks Lebanese classification. |
| `budgets_report.php` | Budget reports | `budgets`, `items` | No | Yes | Yes | - |
| `invoices.php` | Supplier bills | `supplier_invoices` | Yes | Yes | Yes | Has basic committee and budget link. |
| `print_invoice.php` | PDF Invoice | `supplier_invoices` | N/A | N/A | Yes | - |
| `suppliers.php` | Vendor records | `suppliers` | No | No | No | - |
| `tax_collection.php` | Fee collection | `tax_collections` | No | No | Yes | Not linked to general ledger. |
| `donations.php` | Charity income | `donations` | No | No | Yes | Not linked to general ledger. |
| `contributions.php` | Project income | `project_contributions` | No | No | Yes | - |
| `currencies.php` | FX rates | `currencies` | N/A | N/A | Yes | Foundation is solid. |
| `projects_finance.php` | Project costs | `projects` | No | No | Yes | - |
| `committee_dashboard.php`| Committee hub | `municipal_committees`| Yes | No | Yes | Has dedicated finance tab but basic. |
| `api/financial_transactions.php` | API endpoints | `financial_transactions`| No | No | Yes | - |

## 3. Current Database Finance and Committee Structure
A robust set of tables currently exists, but they lack the connective relationships needed for a unified committee-budget system.

| Table | Purpose | Records Count | Main Columns | Current Usage | Committee Link Exists? | Budget Link Exists? | Problems |
|-------|---------|---------------|--------------|---------------|------------------------|---------------------|----------|
| `financial_transactions` | Main ledger | 10 | type, amount, currency_id | High | No | No | Disconnected from budgets & committees. |
| `budgets` | Annual budget | 1 | year, total_amount | Medium | No | N/A | Too simplistic. |
| `budget_items` | Budget lines | 14 | budget_id, allocated | Medium | No | Yes | Lacks official hierarchy (باب/مادة). |
| `committee_finance_summary`| Committee balances | 5 | committee_id, total_income | Medium | Yes | No | Hardcoded balances instead of calculated. |
| `committee_finance_transactions`| Committee ledger | 0 | committee_id, amount, currency | Low | Yes | No | Duplicates `financial_transactions`. |
| `municipal_committees` | Committees list | 5+ | name, type, chairman_id | High | N/A | No | - |
| `supplier_invoices` | Vendor bills | 3 | supplier, amount, status | High | Yes | Yes | Disconnected from actual payments ledger. |
| `tax_collections` | Municipal fees | 2 | tax_id, amount | High | No | No | - |
| `projects` | Development | 6 | budget, actual_cost | High | No | Yes | - |
| `donations` | Received funds | 0 | donor, amount, currency | Medium | No | Yes | - |

## 4. What Is Missing
The system lacks the necessary architectural links to unify accounting, budgets, and committees. 

**Missing Elements:**
- **Flexible Budget Classification:** No hierarchical structure for Lebanese municipal budgets (الباب، المادة، البند).
- **Committee Budgets:** No way to assign a specific budget or specific budget items to a committee.
- **Relational Ledger:** `financial_transactions` cannot link an expense directly to a `committee_id`, `budget_item_id`, or `project_id`.
- **Receipts & Vouchers:** No sequential, official Receipt (وصل استلام) or Payment Voucher (سند صرف) generation.
- **Accounts Receivable/Payable:** No unified view of citizen debts or supplier obligations.
- **Chart of Accounts:** Missing standardized income/expense categories.
- **Cashbox/Bank Accounts:** Money exists in a vacuum; there is no representation of physical cashboxes or bank accounts.
- **Audit & Closings:** No monthly freeze/closing mechanism to lock past transactions.

## 5. Recommended Accounting Structure

### 5.1 Accounting Dashboard
- Total income/expenses this month (by currency).
- Current cashbox/bank balances (by currency).
- Unpaid invoices (Payables) and uncollected fees (Receivables).
- Budget used percentage (General).
- Top spending committees.
- Pending payment approvals.

### 5.2 Income Management (مداخيل)
- **Types:** Citizen payments, fees, taxes, government transfers, donations, project contributions.
- **Fields:** Date, Payer, Amount, Currency, Income Category, **Related Committee**, **Related Project**, **Related Budget Item**, Receipt #, Payment Method, Notes, Attachment.

### 5.3 Expense Management (مصاريف)
- **Types:** Supplier payments, maintenance, salaries, office expenses, project/committee expenses.
- **Fields:** Date, Payee, Amount, Currency, Expense Category, **Related Committee**, **Related Project**, **Related Budget Item**, Related Invoice, Voucher #, Payment Method, Approved By, Notes, Attachment.

### 5.4 Committee Budget Management (ميزانية اللجان)
- Support for creating dedicated budgets for each committee.
- Ability to assign specific budget items to a committee.
- Real-time tracking of actual spending vs allocated committee budget.
- Over-budget warnings.
- **Views:** Committee Budget Details, Committee Income/Expense Statement, Committee Budget vs Actual, Committee Remaining Balance (Separated by Currency).

### 5.5 General Municipal Budget (الموازنة العامة)
- Annual master budget encompassing all income and expense chapters.
- Tracks total actuals against total estimates.
- Generates the yearly closing report.

### 5.6 Budget Classification (تصنيف الموازنة)
Flexible, multi-level hierarchy to match Lebanese municipal laws without hardcoding them:
- **Chapter (الباب):** e.g., إيرادات عادية / نفقات إدارية
- **Article (المادة):** e.g., رسوم مباشرة / رواتب وأجور
- **Item (البند):** e.g., رسوم القيمة التأجيرية / مخصصات رئيس البلدية
- **Properties:** Type (Income/Expense), Fiscal Year, Optional Committee Link, Optional Project Link.

### 5.7 Invoices (فواتير)
- **Fields:** Supplier, Invoice #, Date, Due Date, Amount, Currency, Status, **Related Committee**, **Related Project**, **Related Budget Item**, Payments History, Attachments.

### 5.8 Receipts (إيصالات استلام)
- **Fields:** Receipt #, Payer, Amount, Currency, Date, Reason, Linked Transaction, **Related Committee**, **Related Budget Item**.

### 5.9 Payment Vouchers (سندات صرف)
- **Fields:** Voucher #, Payee, Amount, Currency, Date, Reason, Linked Transaction, **Related Committee**, **Related Budget Item**, Approver.

### 5.10 Receivables and Payables (مستحقات)
- Aging reports (0-30, 31-60, 61-90, 90+ days) for who owes the municipality and who the municipality owes.
- Grouped optionally by Committee or Project.

### 5.11 Currency Management (إدارة العملات)
- **Strict Separation:** USD and LBP are absolutely distinct. No mixed totals unless a deliberate exchange rate conversion view is requested.
- Every transaction, budget item, and report must have a `currency_id`.
- Committee spending reports must show separate columns/rows for USD and LBP.

### 5.12 Reports (التقارير)
- Monthly Income/Expenses.
- Budget vs Actual (General).
- Budget vs Actual (By Committee).
- Budget vs Actual (By Budget Item).
- Committee Financial Statement.
- Unpaid Invoices & Receivables.
- Cashbox/Bank Movement.

## 6. Proposed Database Additions
Keep additions minimal but structurally sound. Do not duplicate transactions.

| Proposed Table | Purpose | Key Columns | Relationship With Existing Tables | Supports Committee Budget? | Priority |
|----------------|---------|-------------|-----------------------------------|----------------------------|----------|
| `accounting_budget_classifications` | Flexible hierarchy (باب/مادة/بند) | `id`, `parent_id`, `code`, `name`, `type`, `year` | Replaces/Upgrades `budget_item_templates` | Yes | High |
| `accounting_categories` | Income/Expense categories | `id`, `name`, `type` | Links to `financial_transactions` | No | High |
| `accounting_committee_budgets` | Links committees to specific budget items | `id`, `committee_id`, `budget_item_id`, `allocated_amount`, `currency_id` | Links `municipal_committees` & `budget_items` | Yes | High |
| `accounting_receipts` | Sequential official receipts | `id`, `receipt_number`, `transaction_id`, `payer` | Links to `financial_transactions` | No | High |
| `accounting_payment_vouchers` | Sequential official vouchers | `id`, `voucher_number`, `transaction_id`, `payee` | Links to `financial_transactions` | No | High |
| `accounting_cashboxes` | Bank/Cash physical accounts | `id`, `name`, `currency_id`, `balance` | Links to `financial_transactions` | No | Medium |

## 7. Required Changes to Existing Tables
Existing tables need foreign keys to tie the ecosystem together. *(Note: Do NOT apply yet).*

| Existing Table | Missing Link Columns | Why Needed | Risk | Priority |
|----------------|----------------------|------------|------|----------|
| `financial_transactions` | `committee_id`, `budget_item_id`, `project_id`, `invoice_id`, `receipt_id`, `voucher_id`, `cashbox_id` | To trace every penny to its committee, budget, and project. | Modifying a core table | High |
| `tax_collections` | `transaction_id` | To link fee collection to the main ledger. | Low | Medium |
| `donations` | `transaction_id` | To link received donations to the main ledger. | Low | Medium |

## 8. Relationship With Existing Tables
- **`financial_transactions`**: This becomes the undisputed Single Source of Truth. The table `committee_finance_transactions` should be deprecated in favor of storing committee expenses directly in `financial_transactions` with `committee_id` filled.
- **`budget_items`**: Will reference the new `accounting_budget_classifications` to maintain the (باب/مادة/بند) hierarchy.
- **`projects` & `municipal_committees`**: Both will act as optional analytical dimensions on every financial transaction.
- **`supplier_invoices`**: An invoice is a Payable. When paid, it creates a `financial_transaction` and an `accounting_payment_voucher`.

## 9. Simplification Plan
To ensure staff adoption, the UI must avoid complex double-entry accounting jargon.
- **Labels:** Use familiar terms: مداخيل (Income), مصاريف (Expenses), إيصالات (Receipts), فواتير (Invoices), سندات صرف (Vouchers), موازنة عامة (General Budget), موازنة اللجان (Committee Budgets).
- **One-Screen Entry:** A single "Record Expense" screen that auto-generates the voucher and updates the committee budget in the background.
- **Unified Views:** Instead of checking separate pages for committee finance and general finance, use a unified Treasury page with filters for Committees/Projects.

## 10. User Roles and Permissions
- `view_finance`: Read-only dashboards.
- `add_income` / `add_expense`: Can record transactions.
- `issue_receipts` / `issue_vouchers`: Can generate official PDFs.
- `manage_general_budget`: Can alter the annual municipal budget.
- `manage_committee_budget`: Can allocate budget lines to specific committees.
- `view_committee_finance`: Committee heads can view only their own budget vs actuals.
- `approve_expense`: Mayor / Finance Head approval workflow.

## 11. Suggested UI Flow
1. **Setup Year:** Create Annual General Budget using flexible classifications (Chapters/Articles).
2. **Setup Committees:** Allocate specific Budget Items to Committees.
3. **Daily Income:** Clerk adds income -> Selects optional Committee/Project -> System generates Receipt.
4. **Daily Expense:** Clerk registers Supplier Invoice -> Links to Budget Item & Committee.
5. **Payment:** Mayor approves -> Clerk issues Payment Voucher -> Deducts from Cashbox & Committee Budget.
6. **Reporting:** Head of Municipality views Committee Spending Report to see which committee is nearing its budget limit.

## 12. Implementation Roadmap
- **Phase A:** Audit and Plan (Completed via this document).
- **Phase B:** Create core accounting, budget classification, and committee budget tables.
- **Phase C:** `ALTER` existing `financial_transactions` table to add nullable relational columns (`committee_id`, `budget_item_id`, etc.).
- **Phase D:** Build the hierarchical Budget Setup screens (General & Committee).
- **Phase E:** Build unified Income/Expense entry screens with Receipt/Voucher generation.
- **Phase F:** Refactor Invoices and Tax Collections to sync with the main ledger.
- **Phase G:** Build Committee Financial Reports and Budget vs Actual dashboards.
- **Phase H:** Deprecate `committee_finance_transactions` and migrate legacy data safely.

## 13. Risks
- **Mixing Currencies:** The system must strictly enforce USD/LBP isolation in all DB columns and UI views.
- **Hardcoded Headings:** Using verified Lebanese municipal headings is good, but hardcoding them is a risk. The `accounting_budget_classifications` table mitigates this.
- **Data Duplication:** If `committee_finance_transactions` is kept alongside `financial_transactions`, balances will desync. `financial_transactions` must be the only ledger.
- **Orphaned Expenses:** If staff forget to select a `committee_id` or `budget_item_id`, the expense will hit the general budget but miss the committee reports. UI validation is critical.

## 14. Final Recommendation
**What to build first:** Do not touch the UI yet. Start by establishing the Database architecture: Build the flexible `accounting_budget_classifications` table, the `accounting_committee_budgets` table, and update `financial_transactions` with the necessary foreign keys.
**What to avoid:** Avoid hardcoding Lebanese budget laws. Avoid maintaining separate transaction tables for committees (`committee_finance_transactions`); unify everything into `financial_transactions`. 
**Strategy:** Build the new "Clean Accounting Module" alongside the existing pages. Once the new unified Treasury, Receipt, Voucher, and Committee Budget screens are verified, safely redirect old pages to the new module.
