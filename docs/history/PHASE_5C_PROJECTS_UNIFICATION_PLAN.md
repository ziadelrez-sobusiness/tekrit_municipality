# Phase 5C - Projects Tables Unification Plan

## 1. Current Situation
The Tekrit Municipality database currently contains two separate tables for managing projects, leading to data fragmentation and synchronization issues:

*   **`projects`**: The modern, unified table (currently containing 1 record). This table is the foundation of the admin panel's financial, invoicing, and contribution management systems.
*   **`development_projects`**: The legacy table (currently containing 5 records). This table is primarily used by the public website's homepage and the legacy public content management module.
*   **The Risk**: Having two tables causes severe synchronization problems. When admins create a project in the modern financial dashboard, it does not appear on the public homepage. Conversely, projects managed via the legacy content editor are isolated from the financial and invoice tracking systems. This duplication leads to inaccurate reporting and confusing workflows.

## 2. Files Using `projects`
These files are already integrated with the modern unified table:

| File | Operation | Notes |
| :--- | :--- | :--- |
| `modules/projects_unified.php` | CRUD | Modern admin project manager |
| `modules/projects.php` | Read | Admin listing |
| `modules/contributions.php` | Read | Admin contribution tracking |
| `modules/finance.php` | Read | Admin financial tracking |
| `modules/financial_dashboard.php`| Read | Admin analytics dashboard |
| `modules/invoices.php` | Read | Links invoices to projects |
| `modules/print_invoice.php` | Read | Renders project names on prints |
| `public/projects.php` | Read | Public project listing |
| `public/project-detail.php` | Read | Public project detail view |

## 3. Files Using `development_projects`
These files are still relying on the legacy table and must be updated:

| File | Operation | Notes |
| :--- | :--- | :--- |
| `public/index.php` | Read | Displays featured projects on the homepage |
| `modules/public_content_management.php` | CRUD | Legacy admin tool for editing public projects |
| `public/citizen-requests-advanced.php`| Read | Dropdown for linking a request to a project |
| `includes/CitizenRequest.php` | Read/Join | Backend logic for linking requests to projects |
| `includes/currency_formatter.php` | Read/Update | Automatic currency recalculation logic |

## 4. Column Comparison
To migrate data from `development_projects` to `projects`, the following column mapping is necessary:

| Meaning | `projects` column | `development_projects` column | Compatible? | Notes |
| :--- | :--- | :--- | :--- | :--- |
| ID | `id` | `id` | ⚠️ Collision | Both tables contain a record with ID = 2. Migration must handle this carefully to preserve FKs. |
| Project Name | `project_name` | `project_name` | ✅ Yes | Direct mapping. |
| Description | `description` | `project_description` | ✅ Yes | TEXT type mapping. |
| Goal | `project_goal` | `project_goal` | ✅ Yes | Direct mapping. |
| Status | `status` | `project_status` | ⚠️ Verify | Must ensure ENUM values exactly match. |
| Budget/Cost | `budget` | `project_cost` | ✅ Yes | DECIMAL mapping. |
| Currency | `budget_currency_id`| `currency_id` | ✅ Yes | INT mapping. |
| Location | `location` | `project_location` | ✅ Yes | VARCHAR mapping. |
| Dates | `start_date`, `end_date`| `start_date`, `end_date` | ✅ Yes | DATE mapping. |
| Completion % | `progress_percentage`| `completion_percentage` | ✅ Yes | INT mapping. |
| Manager | `manager_id` | `project_manager_id`| ✅ Yes | INT mapping. |
| Images | `main_image`, `gallery_images`| `project_images` | ⚠️ Needs logic | `projects` separates main/gallery. Old table uses one string. |
| Is Featured | `is_featured` | `is_featured` | ✅ Yes | Boolean mapping. |

## 5. Data Comparison
*   **`projects` count:** 1 record (ID: 2).
*   **`development_projects` count:** 5 records (IDs: 1, 2, 3, 4, 9).
*   **Conflict:** ID `2` exists in both tables. We cannot blindly import IDs without checking if foreign keys in `citizen_requests` rely on the old `development_projects` IDs.

## 6. Recommended Target Table
The target table must be **`projects`**. 
*Why?* The `projects` schema is significantly more robust, handling complex financial tracking, multi-currency support, specific invoice linkages, and advanced image gallery structures. The admin panel's core logic is already heavily built around it.

## 7. Safe Migration Strategy
1.  **Full Database Backup:** Execute a complete SQL dump before any operation.
2.  **Foreign Key Audit:** Check if any table (like `citizen_requests`) references `project_id = 2`. If so, we must assign the incoming legacy record a new ID (e.g., `1002`) and update the `citizen_requests` table to point to the new ID.
3.  **Data Migration:** Run a carefully mapped `INSERT INTO projects SELECT ... FROM development_projects` query. Handle the image string parsing if needed.
4.  **Update PHP Queries:** Modify `public/index.php`, `public/citizen-requests-advanced.php`, and `includes/CitizenRequest.php` to query `projects` instead of `development_projects`. Ensure `WHERE is_public = 1` is used.
5.  **Disable Legacy Editor:** Remove the `development_projects` section from `modules/public_content_management.php` to prevent further edits to the old table.
6.  **Test Public/Admin Pages:** Verify the homepage loads featured projects correctly from the unified table. Verify citizen requests can still link to projects.
7.  **Archive Old Table:** Rename `development_projects` to `_deprecated_development_projects` rather than dropping it immediately.

## 8. Proposed SQL Draft
```sql
-- DO NOT RUN YET
-- 1. Create a safety backup table
CREATE TABLE _backup_development_projects AS SELECT * FROM development_projects;

-- 2. Handle ID Collisions safely (Example: shifting old IDs by 1000 to avoid conflicts)
-- Note: If we do this, we MUST UPDATE foreign keys in citizen_requests!
-- UPDATE citizen_requests SET project_id = project_id + 1000 WHERE project_id IN (SELECT id FROM development_projects);

-- 3. Insert mapped data
INSERT INTO projects (
    id, 
    project_name, 
    description, 
    project_goal, 
    location, 
    budget, 
    budget_currency_id, 
    progress_percentage, 
    status, 
    manager_id, 
    is_featured, 
    start_date, 
    end_date,
    beneficiaries_count,
    beneficiaries_description,
    gallery_images,
    before_images,
    after_images,
    is_public
)
SELECT 
    id + 1000, -- Shifted to guarantee no PK collision with existing projects
    project_name, 
    project_description, 
    project_goal, 
    project_location, 
    project_cost, 
    currency_id, 
    completion_percentage, 
    project_status, 
    project_manager_id, 
    is_featured, 
    start_date, 
    end_date,
    beneficiaries_count,
    beneficiaries_description,
    project_images, -- Maps to gallery_images
    before_images,
    after_images,
    1 -- Assumes these are public projects
FROM development_projects;

-- 4. Archive old table
-- RENAME TABLE development_projects TO _deprecated_development_projects;
```

## 9. PHP Files That Will Need Future Updates
*   **`public/index.php`**: Must query `projects WHERE is_featured = 1 AND is_public = 1`.
*   **`public/citizen-requests-advanced.php`**: Dropdowns must populate from `projects`.
*   **`includes/CitizenRequest.php`**: JOIN logic must switch to `projects`.
*   **`modules/public_content_management.php`**: Remove the entire logic block related to `development_projects`.

## 10. Risks
*   **Foreign Key Breakage:** Shifting the ID of a `development_project` to avoid a collision will break existing `citizen_requests` if those foreign keys aren't updated synchronously.
*   **Image Path Errors:** If `project_images` in the old table relies on a different directory structure than `gallery_images` in the new table, images will broken on the frontend.
*   **ENUM Mismatch:** If an old project has a status of "معلق" but the new table only accepts "مخطط", the `INSERT` will fail.

## 11. Testing Checklist
*   [ ] Public homepage loads featured projects without SQL errors.
*   [ ] Admin `projects_unified.php` displays the newly merged 5 legacy projects.
*   [ ] Old citizen requests tied to projects load correctly in the admin tracking panel.
*   [ ] Creating a new citizen request successfully populates the "Select Project" dropdown.
*   [ ] Project images load successfully for merged projects.
*   [ ] Currency/Budget calculations in the financial dashboard include the merged projects.

## 12. Final Recommendation
**Migrate later, but soon.** Do not execute this immediately. Before executing, we MUST explicitly check the `citizen_requests` table to see if any requests are currently linked to `project_id = 1, 2, 3, 4, 9`. If they are, we must write a multi-step SQL transaction that updates the IDs in `development_projects` and `citizen_requests` simultaneously before migrating the data into `projects`. 

Once that FK audit is complete, the migration should be executed to permanently close the data fragmentation gap.
