# Phase 5 - Database Audit Report

**Date:** 2026-04-29  
**Project:** Tekrit Municipality Website  
**Scope:** Database structural analysis and PHP script relationship mapping. No data or schemas were modified.

---

## 1. General Database Summary
- **Database Name:** `tekrit_municipality`
- **Total Tables:** 96
- **Total Views:** 6
- **Approximate Total Records:** ~570
- **Tables with the Highest Record Counts:**
  1. `user_permissions` (119 records)
  2. `permissions` (118 records)
  3. `request_updates` (75 records)
  4. `citizen_requests` (63 records)
  5. `telegram_log` (43 records)

---

## 2. Table Inventory (Core Tables)
| Table Name | Type | Records Count | Main Purpose | Related PHP Files Found |
|------------|------|---------------|--------------|-------------------------|
| `users` | BASE TABLE | 9 | Admin/Staff accounts | `auth.php`, `hr.php`, `login.php` |
| `citizens_accounts` | BASE TABLE | 2 | Online portal citizen accounts | `citizens_accounts.php`, `citizen-dashboard.php` |
| `citizen_requests` | BASE TABLE | 63 | Citizen online requests | `citizen-requests.php`, `update_citizen_request.php` |
| `departments` | BASE TABLE | 18 | Municipality organizational structure | `departments.php`, `hr.php` |
| `projects` | BASE TABLE | 1 | Unified projects management | `projects_unified.php` |
| `development_projects` | BASE TABLE | 5 | Legacy projects mapping | `public/projects.php`, `public/project-detail.php` |
| `financial_transactions` | BASE TABLE | 10 | Core financial movements | `finance.php`, `financial_dashboard.php` |
| `system_settings` | BASE TABLE | 14 | Backend system config | `system_settings.php` |
| `website_settings` | BASE TABLE | 24 | Frontend public settings | `system_settings.php`, `public/index.php` |

---

## 3. Empty Tables
The database contains 35 empty tables (0 records). They are classified as follows:

- **Likely Future-Use Tables:**
  `donation_campaigns`, `initiative_activities`, `citizen_messages`, `project_phases`, `campaign_donations`, `donor_organizations`, `initiative_evaluations`, `committee_finance_transactions`, `vehicles`, `cleaning_reports`, `municipal_decisions`, `citizen_opinions`, `facility_ratings`, `polls`, `volunteer_attendance`, `committee_members`, `donations`, `vehicle_maintenance`, `committee_decisions`, `municipal_sessions`, `request_form_data`, `waste_reports`, `project_contributions`, `building_permits`, `poll_responses`

- **Likely Unused Tables:**
  `documents`, `documents_forms`, `appreciation_certificates`, `municipality_assets`, `external_data_sources`, `municipality_resources`, `associations`

- **System / Log Tables:**
  `activity_log`, `citizen_sessions`, `currency_conversion_log`

*(Note: No tables are recommended for deletion at this stage. Needs manual review.)*

---

## 4. Tables With No Clear PHP Usage
Based on a structural text scan of the PHP source code, the following tables exist in the database but have limited or no explicit hardcoded queries accessing them:

| Table Name | Records | Possible Purpose | Recommendation |
|------------|---------|------------------|----------------|
| `faqs` | 10 | Public FAQs | Verify if used dynamically via `public_content_management.php` |
| `magic_links` | 2 | Passwordless citizen login | Review if the feature was deprecated |
| `donation_campaigns` | 0 | Campaign tracking | Keep if donations module is planned |
| `documents_forms` | 0 | Internal documentation | Monitor for removal |
| `polls` / `poll_responses` | 0 | Public voting system | Feature seems incomplete, review |

---

## 5. PHP Files Referencing Missing Tables
⚠️ **CRITICAL FINDING:** The following tables are referenced in PHP SQL queries but **DO NOT EXIST** in the `tekrit_municipality` database. Accessing these functions will trigger SQL errors.

1. **`user_activity_log`**
   - Referenced in: `includes/auth_helper.php`
2. **`whatsapp_log`**
   - Referenced in: `includes/WhatsAppService.php`, `modules/whatsapp_pending_messages.php`
3. **`municipal_forms`**
   - Referenced in: `modules/building_permit.php`
4. **`inventory_items`** & **`inventory_movements`**
   - Referenced in: `modules/inventory.php`
5. **`request_workflow_stages`** & **`request_stage_tracking`**
   - Referenced in: `public/citizen-requests-advanced.php`, `public/track-request-advanced.php`

---

## 6. Duplicate or Overlapping Tables
| Table A | Table B | Overlap | Risk | Suggested Future Action |
|---------|---------|---------|------|-------------------------|
| `projects` (1 row) | `development_projects` (5 rows) | Both store municipality project details. | Public website shows old table; Admin edits new table. | Migrate `development_projects` data to `projects` and redirect frontend queries. |
| `system_settings` (14 rows) | `website_settings` (24 rows) | Config key-value pairs. | Configuration drift; unclear where to edit frontend vs backend settings. | Consolidate into a single `settings` table with a `scope` column. |
| `citizens` (5 rows) | `citizens_accounts` (2 rows) | Citizen profile info vs Login accounts. | Desync of citizen contact info (phone/national ID). | Unify into `citizens` table with an `is_registered` flag. |
| `documents` (0 rows) | `documents_forms` (0 rows) | Archive storage. | Fragmentation of file taxonomy. | Merge into one `archive_documents` table. |

---

## 7. Projects Tables Detailed Analysis
- **`projects`**: Contains 1 record. Used heavily by `modules/projects_unified.php`. This table is newer, uses robust foreign keys (`type_id`, `budget_currency_id`), and is designed for the admin panel.
- **`development_projects`**: Contains 5 records. Used by `public/projects.php` and `public/project-detail.php`. This is the older table that feeds the live public website.
- **`project_types`**: Contains 5 records. Feeds the dropdowns in the unified admin panel.
- **`project_phases`**: Contains 0 records. Extends the unified `projects` table.
- **`project_contributions`**: Contains 0 records. Used in `modules/contributions.php`.

**Risk of Unification:** If we unify them abruptly, the public website will break because it relies on columns like `project_base_cost` which differ from the new `projects` table structure. A careful data migration script is required.

---

## 8. Foreign Keys and Relationships
- **Real MySQL Foreign Keys:** InnoDB FK constraints exist extensively around the `users` table (e.g., `user_permissions` -> `users`, `departments` -> `users`), ensuring referential integrity for staff administration.
- **Logical Relationships in PHP:** Many tables rely on logical (application-level) relationships without strict DB constraints. For example, `citizen_requests` maps to `citizens_accounts`, but orphaned requests can exist if an account is manually deleted via raw SQL.
- **Missing Foreign Keys:** 
  - `telegram_log` should link to `citizens_accounts`.
  - Financial tables lack strict cascading deletes, which could leave orphaned invoices if a `supplier` is deleted.

---

## 9. Data Quality Notes
- **Low Usage Variance:** The vast majority of records reside in permission and system log tables, indicating the system is freshly deployed or in testing.
- **Split Sources of Truth:** Project data is split between two tables. 
- **Orphan Potential:** Because of the missing tables (`inventory_items`, `whatsapp_log`), several admin modules are currently broken and cannot generate valid data.

---

## 10. Security Notes Related to Database
- **Sensitive Fields:** 
  - The `users` table contains a `password` field (needs verification that bcrypt/argon2 is used).
  - `citizens_accounts` contains `access_code`.
  - `magic_links` contains authentication `token`s.
- **Logs:** `telegram_log` and `citizen_requests` contain Plaintext PII (Personally Identifiable Information) like phone numbers and full names. Ensure these tables are excluded from casual analytical dumps.

---

## 11. Recommended Future Database Cleanup Plan (Safe)
- **Phase A: Backup and Verification:** Create a master SQL dump.
- **Phase B: Missing Tables Fix:** Create the 7 missing tables identified in Section 5 using schemas inferred from their respective PHP files so the modules stop erroring out.
- **Phase C: Projects Table Unification:** Map `development_projects` data to `projects`, update the `public/projects.php` queries, and securely archive the old table.
- **Phase D: Settings Table Cleanup:** Consolidate `system_settings` and `website_settings` into a single, unified key-value store.
- **Phase E: Relationship and Foreign Key Improvement:** Add strict InnoDB cascading rules to citizen and financial tables to prevent orphan records.
- **Phase F: Testing:** Run a full regression test across all modules.

---

## 12. Final Recommendation
**Do NOT begin refactoring PHP modules yet.** 

Before touching any PHP logic, **Phase B of the cleanup plan must be executed**: you must create the 7 missing tables (e.g., `user_activity_log`, `inventory_items`, `whatsapp_log`). Leaving these tables missing means that the PHP code referencing them is fundamentally broken. Fix the database schema gaps first, unify the `projects` tables second, and only then proceed with PHP code refactoring.
