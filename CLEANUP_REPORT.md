# Cleanup Report - Tekrit Municipality Project

This report documents the cleanup, deduplication, and archiving tasks completed during Phase 7 of the Tekrit Municipality project.

---

## 1. What was Removed (Deleted)

- **Duplicate Nested Project Folder:** `tekrit_municipality/tekrit_municipality/` (deleted recursively).
  - This nested folder contained 153 old duplicate files from previous development phases and was not referenced in any active code.

---

## 2. What was Archived

### Duplicate Module Variants
Moved to `_archive/legacy_modules/`:
- `modules/council_management_new.php`
- `modules/public_content_management.php.backup.2025-06-22-16-30-16`
- `modules/update_citizen_request.php.backup.2025-06-22-16-30-16`
- `modules/view_citizen_request.php.backup.2025-06-22-16-30-16`
- `index_backup.php`
- `index_safe.php`

### Legacy WhatsApp Logic
Moved to `_archive/whatsapp_legacy/`:
- `includes/WhatsAppService.php`
- `modules/whatsapp_pending_messages.php`
- `modules/whatsapp_settings.php`

### Loose Root Documentation / Fix Files
Moved to `docs/history/`:
- All loose `.html` files in the root (59 files total)
- All loose `.md` files in the root (35 summary/plan files total)
- `fix_modal_position.js`
- `fix_modal_zindex.css`

Moved to `_maintenance/_unused/`:
- `DIRECT_FIX.php`
- `facilities_edit_fix.php`
- `quick_fix_content_management.php`
- `update_initiatives_database.php`
- `update_lebanon_facilities.php`
- `update_news_system.php`

Moved to `database/`:
- 10 loose `.sql` setup and migration scripts that were sitting in the project root.

---

## 3. Unused Maintenance Scripts (Flagged & Moved)

All scripts under the following directories were not referenced anywhere or run by cron. They have been moved under `_maintenance/_unused/` subfolders for safety:
- **Unused Tests (`_maintenance/_unused/tests/`):** 22 scripts (e.g., `test_all_systems.php`, `test_csrf_final.php`, etc.)
- **Unused Debugs (`_maintenance/_unused/debug/`):** 7 scripts (e.g., `debug_citizens_page.php`, `debug_facilities.php`, etc.)
- **Unused Checks (`_maintenance/_unused/checks/`):** 10 scripts (e.g., `check_dashboard_links.php`, `check_facilities_data.php`, etc.)

---

## 4. Manual Human Review Required

### Oversized Uploaded Images (> 1MB)
The following images in `uploads/news/` and `uploads/council_members/` are too large and should be optimized/compressed for production:
- `uploads/news/news_featured_1750249063_7245.jpg` (4.35 MB)
- `uploads/news/news_featured_1750249159_7418.jpg` (4.17 MB)
- `uploads/news/news_featured_1750288916_1034.png` (1.07 MB)
- `uploads/news/news_gallery_1750249063_3022.jpg` (4.17 MB)
- `uploads/news/news_gallery_1750249063_4259.jpg` (4.35 MB)
- `uploads/news/news_gallery_1750249063_6951.jpg` (4.63 MB)
- `uploads/news/news_gallery_1750249159_5835.png` (1.07 MB)
- `uploads/news/news_gallery_1750249159_9342.png` (1.56 MB)
- `uploads/council_members/member_1749767301_684b54851f1b1.JPG` (4.63 MB)
- `uploads/council_members/member_1749854129_684ca7b100161.JPG` (4.35 MB)

### Untouched Legacy Phase Snapshots (`_archive/`)
The following snapshot folders under `_archive/` were kept untouched as requested:
- `before_budget_activation_and_actuals_fix`
- `before_budget_line_visibility_and_search_fix`
- `before_cashbox_currency_mismatch_fix`
- `before_cashbox_details_cancellation_fix`
- `before_cashbox_error_fix`
- `before_cashbox_management`
- `before_cashbox_statement_ui_and_cancel_fix`
- `before_cashbox_statement_ux_fix`
- `before_manual_financial_actions_fix`
- `before_official_budget_reimport_and_editing_fix`
- `before_official_municipal_budget_model`
- `before_phase4`
- `before_phase5e`
- `before_phase5f`
- `before_phase6c`
- `before_phase6c_quality`
- `before_phase6d`
- `before_phase6e`
- `before_phase6e_validation`
- `before_phase6f`
- `before_phase6g`
- `before_treasury_budget_link_and_budget_cleanup_fix`
- `before_treasury_project_column_fix`

---

## 5. Prepared SQL Script (`database/cleanup_phase7_dedup.sql`)

The migration SQL script has been successfully created but **not executed**. You can run it manually when ready:

```sql
-- Phase 7: Database Cleanup & Deduplication Migration
-- Date: 2026-06-12
-- Target: Tekrit Municipality Database

SET FOREIGN_KEY_CHECKS=0;

-- 1. Drop backup development projects table from Phase 5E
DROP TABLE IF EXISTS `_backup_development_projects_phase5e`;

-- 2. Permanently resolve the Arabic character-encoding bug on municipal_forms table
ALTER TABLE `municipal_forms` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 3. Correct default value of status to proper Arabic 'مقدم'
ALTER TABLE `municipal_forms` 
    MODIFY COLUMN `status` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'مقدم';

SET FOREIGN_KEY_CHECKS=1;
```
