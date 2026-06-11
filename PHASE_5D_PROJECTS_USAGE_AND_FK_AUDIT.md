# Phase 5D - Projects Usage & FK Audit

**Date:** 2026-04-29  
**Project:** Tekrit Municipality Website  
**Status:** ✅ AUDIT COMPLETED

---

## 1. Real PHP Usage Confirmation

An exhaustive search of the codebase confirms exactly which table each core file relies on. The split is perfectly defined: the modern admin tools and dedicated public project pages use the new `projects` table, while the homepage and citizen requests still rely on the legacy `development_projects` table.

| File | Uses `projects`? | Uses `development_projects`? | Operation | Notes |
| :--- | :---: | :---: | :--- | :--- |
| `public/index.php` | ❌ | ✅ | Read | Displays featured projects on the homepage. |
| `public/projects.php` | ✅ | ❌ | Read | Public project listing. |
| `public/project-detail.php` | ✅ | ❌ | Read | Public project detail view. |
| `modules/projects_unified.php` | ✅ | ❌ | CRUD | Modern admin project manager. |
| `modules/projects.php` | ✅ | ❌ | Read | Admin listing. |
| `modules/contributions.php` | ✅ | ❌ | Read | Admin contribution tracking. |
| `modules/finance.php` | ✅ | ❌ | Read | Admin financial tracking. |
| `modules/financial_dashboard.php`| ✅ | ❌ | Read | Admin analytics dashboard. |
| `modules/invoices.php` | ✅ | ❌ | Read | Links invoices to projects. |
| `modules/public_content_management.php` | ❌ | ✅ | CRUD | Legacy admin tool for editing public projects. |
| `public/citizen-requests-advanced.php`| ❌ | ✅ | Read | Dropdown for linking a request to a project. |
| `includes/CitizenRequest.php` | ❌ | ✅ | Read/Join | Backend logic for linking requests. |
| `includes/currency_formatter.php` | ❌ | ✅ | Read/Update | Automatic currency recalculation logic. |

---

## 2. Audit of `project_id` References

We queried all tables containing a `project_id` or `related_project_id` column to see if they hold data matching the IDs (1, 2, 3, 4, 9).

| Table | Project Column | Matching IDs Found | Count | Risk |
| :--- | :--- | :--- | :--- | :--- |
| `citizen_requests` | `project_id` | `1`, `3` | 2 rows total | **High.** If we shift the legacy IDs during migration, we MUST update `citizen_requests.project_id` to match the new shifted IDs. |
| `financial_transactions` | `related_project_id` | `2` | 1 row | **Low.** This references the NEW `projects` table (ID 2). |
| `supplier_invoices` | `related_project_id` | `2` | 1 row | **Low.** This references the NEW `projects` table (ID 2). |
| `donations` | `allocated_to_project_id` | None | 0 | None. |
| `project_contributions` | `project_id` | None | 0 | None. |
| `budget_items` | `related_project_id` | None | 0 | None. |
| `project_phases` | `project_id` | None | 0 | None. |

---

## 3. ID Conflict Check

- **IDs currently existing in `projects`:** `[2]`
- **IDs currently existing in `development_projects`:** `[1, 2, 3, 4, 9]`
- **Overlap/Collision:** **ID `2` exists in BOTH tables.**
- **Conclusion:** We cannot migrate `development_projects` into `projects` while preserving their exact IDs without overwriting or failing on the primary key for ID `2`. 

---

## 4. Project Images Paths

| Column Type | `projects` Table | `development_projects` Table | Compatible? |
| :--- | :--- | :--- | :--- |
| **Main / Gallery** | `main_image`, `gallery_images` | `project_images` | ⚠️ Partially. `development_projects` stores everything in `project_images`. During migration, `project_images` should be mapped to `gallery_images` (or `main_image`). |
| **Before / After** | `before_images`, `after_images` | `before_images`, `after_images` | ✅ Yes. Direct mapping. |

---

## 5. Status Values Compatibility

The ENUM definitions slightly differ between the two tables:

- **`projects.status` ENUMs:** `('مخطط', 'قيد التنفيذ', 'مكتمل', 'متوقف', 'ملغي')`
- **`development_projects.project_status` ENUMs:** `('مقترح', 'قيد التنفيذ', 'منفذ', 'متوقف', 'ملغي')`

**Action Required:** During SQL migration, a mapping layer is required:
- Replace `'منفذ'` with `'مكتمل'`
- Replace `'مقترح'` with `'مخطط'`

---

## 6. Final Recommendation

**Recommendation: A. Safe to migrate with ID shifting.**

Because of the hard collision on ID `2`, and the fact that `citizen_requests` is actively relying on legacy IDs `1` and `3`, the only viable path forward is an ID shift combined with a cascade update.

**Proposed Execution Logic (For Phase 5E):**
1. Update `citizen_requests`: Add `1000` to `project_id` where it equals 1, 2, 3, 4, or 9.
2. `INSERT INTO projects` mapping legacy columns, shifting the ID by `+ 1000`, mapping `project_images` to `gallery_images`, and mapping the ENUM statuses (`منفذ` -> `مكتمل`).
3. Update `public/index.php`, `citizen-requests-advanced.php`, `includes/CitizenRequest.php`, and `currency_formatter.php` to query the `projects` table.
4. Rename `development_projects` to archive it.
