# Tekrit Municipality Website - Cleanup, Security & Refactoring Plan

> **Date:** 2026-04-29  
> **Based on:** `PROJECT_STRUCTURE_AND_DATABASE_ANALYSIS.md`  
> **Project:** Tekrit Municipality (Integration Municipality)  
> **Server:** XAMPP | URL: `http://localhost:8080/tekrit_municipality/`  
> **Database:** `tekrit_municipality` (MySQL/InnoDB)  
> **Status:** PLAN ONLY — No modifications have been made.

---

## 1. Current Situation Summary

The Tekrit Municipality Website is a comprehensive municipal management system built with PHP and MySQL. It serves two audiences: citizens (public website) and staff (admin dashboard).

### System Scale
- **96 database tables** + 5 views (101 total)
- **98 module files** in `modules/` (admin panel)
- **17+ public pages** in `public/` (citizen-facing)
- **118 permissions** with role-based access control
- **17 functional modules** (Finance, Citizens, Projects, Complaints, etc.)

### Architecture
- **Backend:** Custom PHP with PDO (no framework)
- **Frontend:** TailwindCSS (CDN), Chart.js, AlpineJS, Cairo font
- **Security:** SessionManager, CSRF Protection, Login Attempts Tracker
- **Integrations:** Telegram notifications, Google Gemini AI for budget generation

### Key Strengths
- ✅ Exclusively uses PDO with prepared statements (no `mysqli_` anywhere)
- ✅ Well-implemented security classes (SessionManager, CsrfProtection)
- ✅ Comprehensive permission system (118 granular permissions)
- ✅ Multi-currency support across financial modules
- ✅ Telegram integration for citizen notifications

### Key Weaknesses
- ❌ No MVC pattern — logic and presentation mixed in every file
- ❌ 74 setup/fix/debug/test PHP files exposed in root directory
- ❌ 22 backup/old/fixed module files in `modules/`
- ❌ 97 documentation files (.md/.txt) cluttering root directory
- ❌ A full duplicate project folder (`tekrit_municipality/` inside itself)
- ❌ PHP file inside uploads folder (executable)
- ❌ Several files exceed 50KB+ (one is 256KB)

---

## 2. Critical Issues Found

### 🔴 CRITICAL (Immediate security risk)

| # | Issue | Location | Risk |
|---|-------|----------|------|
| C1 | **PHP file inside uploads folder** | `uploads/test_security.php` | An attacker could execute arbitrary PHP code by accessing this file directly via browser. |
| C2 | **No .htaccess in uploads folder** | `uploads/` and all subdirectories | Without .htaccess, any uploaded PHP file can be executed. This is a Remote Code Execution (RCE) vector. |
| C3 | **74 setup/fix/debug/test scripts publicly accessible** | Root directory (see Section 4) | Scripts like `fix_admin_password.php`, `drop_citizen_tables.php`, `debug_permissions_ajax.php` can be accessed by anyone and could expose or destroy data. |
| C4 | **Login page auto-copies username to password field** | `login.php` (JavaScript section) | This indicates passwords may be identical to usernames, and the auto-fill behavior undermines security. |
| C5 | **Database uses root user with no password** | `config/config.php`, `config/database.php` | If deployed to production as-is, the entire MySQL server is unprotected. |

### 🟡 HIGH (Structural / operational risk)

| # | Issue | Location | Risk |
|---|-------|----------|------|
| H1 | **Duplicate project folder** | `tekrit_municipality/tekrit_municipality/` (153 files) | Confusing for developers, wastes disk space, and old versions of files could be served accidentally. |
| H2 | **22 backup/fixed/old module files in production** | `modules/` directory | Backup files may contain outdated logic or security holes and can be accessed directly. |
| H3 | **Two different Database classes** | `config/database.php` + `includes/Database.php` | Potential autoloading conflict. Different files include different versions, leading to inconsistent behavior. |
| H4 | **Two project tables doing the same thing** | `projects` table + `development_projects` table | Data split between tables. Public website reads `development_projects`, admin uses `projects`. Changes in one don't reflect in the other. |
| H5 | **Extremely large PHP files** | `modules/public_content_management.php` (256KB), `modules/municipality_management.php` (99KB), `public/citizen-requests.php` (95KB), `modules/committee_dashboard.php` (90KB), `comprehensive_dashboard.php` (89KB), `modules/budgets.php` (88.5KB), `modules/citizens.php` (82KB) | Files this large are unmaintainable, hard to debug, and cause IDE performance issues. |
| H6 | **Admin panel files lack centralized auth guard** | `admin/` directory (4 files), various `modules/` files | Each file independently checks authentication. Missing the check in one file = unauthorized access. |
| H7 | **Login error messages distinguish between "user not found" and "wrong password"** | `login.php` | Allows username enumeration — an attacker can determine which usernames exist. |
| H8 | **97 documentation files in root directory** | Root directory (.md and .txt files) | Massive clutter that makes the project hard to navigate and maintain. |

---

## 3. Immediate Security Fixes

These fixes should be applied BEFORE any other cleanup work. Each item is designed to be safe and non-breaking.

### Fix 3.1: Remove PHP file from uploads folder

| Field | Detail |
|-------|--------|
| **Problem** | `uploads/test_security.php` is an executable PHP file inside the uploads folder |
| **Risk Level** | 🔴 CRITICAL — Remote Code Execution |
| **File Affected** | `uploads/test_security.php` |
| **Recommended Action** | Move (not delete) to `_archive/removed_from_uploads/test_security.php` |
| **Safe to do immediately?** | ✅ YES — This file is a test script, not used by any module |
| **Verification** | Visit `http://localhost:8080/tekrit_municipality/uploads/test_security.php` — should return 404 |

### Fix 3.2: Add .htaccess to uploads folder

| Field | Detail |
|-------|--------|
| **Problem** | No protection against PHP execution inside uploads directory |
| **Risk Level** | 🔴 CRITICAL — Remote Code Execution prevention |
| **Folders Affected** | `uploads/`, `public/uploads/` |
| **Recommended Action** | Create `.htaccess` files (see Section 5 for exact content) |
| **Safe to do immediately?** | ✅ YES — Only blocks PHP execution, does not affect images/documents |
| **Verification** | Existing image/document uploads should still be viewable; any `.php` file in uploads should return 403 |

### Fix 3.3: Protect dangerous root scripts

| Field | Detail |
|-------|--------|
| **Problem** | 74 setup/fix/debug/test scripts are publicly accessible in the root directory |
| **Risk Level** | 🔴 CRITICAL — Data exposure/destruction |
| **Files Affected** | See full list in Section 4 |
| **Recommended Action** | Move ALL listed files to `_maintenance/` folder, then add `.htaccess` to `_maintenance/` blocking all web access |
| **Safe to do immediately?** | ⚠️ BACKUP FIRST — Some setup scripts may be needed in the future, but should never be web-accessible |
| **Verification** | Visit any moved script URL — should return 404. All normal pages (login, dashboard, public) should work fine. |

### Fix 3.4: Fix login page security issues

| Field | Detail |
|-------|--------|
| **Problem** | JavaScript auto-copies username to password field |
| **Risk Level** | 🔴 CRITICAL — Undermines password security |
| **File Affected** | `login.php` |
| **Recommended Action** | Remove the JavaScript code that copies username to password. Unify error messages to a single generic message: "Invalid username or password" |
| **Safe to do immediately?** | ⚠️ BACKUP FIRST — Requires modifying `login.php` |
| **Verification** | Login page should work normally, password field should be empty by default, error messages should not reveal whether username exists |

### Fix 3.5: Protect config directory

| Field | Detail |
|-------|--------|
| **Problem** | Config files containing database credentials are potentially accessible |
| **Risk Level** | 🟡 HIGH — Credential exposure |
| **Folder Affected** | `config/` |
| **Recommended Action** | Add `.htaccess` to `config/` denying all direct web access |
| **Safe to do immediately?** | ✅ YES — Config files are included by PHP, not accessed via browser |
| **Verification** | Visit `http://localhost:8080/tekrit_municipality/config/config.php` — should return 403. All pages should still work. |

### Fix 3.6: Protect includes directory

| Field | Detail |
|-------|--------|
| **Problem** | Include files can potentially be accessed directly |
| **Risk Level** | 🟡 HIGH — Code exposure |
| **Folder Affected** | `includes/` |
| **Recommended Action** | Add `.htaccess` to `includes/` denying all direct web access |
| **Safe to do immediately?** | ✅ YES — Include files are only used via PHP `require`/`include` |
| **Verification** | All pages should work. Direct URL access to any include file should return 403. |

### Fix 3.7: Protect logs directory

| Field | Detail |
|-------|--------|
| **Problem** | Log files may contain sensitive information and are accessible via browser |
| **Risk Level** | 🟡 HIGH — Information disclosure |
| **Folder Affected** | `logs/` |
| **Recommended Action** | Verify `.htaccess` exists and denies all access. If `.gitignore` exists but no `.htaccess`, create one. |
| **Safe to do immediately?** | ✅ YES |
| **Verification** | Visit `http://localhost:8080/tekrit_municipality/logs/` — should return 403 |

---

## 4. Safe Cleanup Plan

### IMPORTANT RULES:
1. **Never delete files directly** — always move them first
2. **Create `_archive/` folder** for backup files (prefix with `_` so it sorts to top)
3. **Create `_maintenance/` folder** for setup/debug scripts
4. **Test the website after each batch of moves**
5. **Keep a log of every file moved** (date, from, to)

---

### 4.1 Root Directory PHP Files — Classification

#### ✅ FILES TO KEEP (in current location — these are active system files):

| File | Reason |
|------|--------|
| `index.php` | Entry point redirect |
| `login.php` | Employee login page |
| `logout.php` | Logout handler |
| `reset_password.php` | Password reset |
| `comprehensive_dashboard.php` | Main admin dashboard |
| `all_tables_manager.php` | Reference table manager |

#### 📂 FILES TO MOVE TO `_maintenance/setup/`:

These are database setup and migration scripts. They should not be web-accessible but may be needed for future deployments.

```
setup_advanced_citizen_requests.php
setup_advanced_system.php
setup_citizen_accounts_system.php
setup_contact_table.php
setup_facilities_map.php
setup_financial_system.php
install_stored_procedures.php
migrate_to_telegram.php
run_database_update.php
execute_create_committees.php
execute_projects_unification.php
check_and_create_tables.php
add_column_now.php
add_income_currency_column.php
add_initial_data.php
add_telegram_admin_chat_id.php
add_whatsapp_settings.php
```

#### 📂 FILES TO MOVE TO `_maintenance/fixes/`:

These are one-time fix scripts. Already applied, kept for reference only.

```
fix_admin_password.php
fix_api_limit_issue.php
fix_citizens_add_currency.php
fix_citizen_requests.php
fix_edit_facility.php
fix_facilities_map.php
fix_income_currency_FINAL.php
fix_login_debug.php
fix_map_auto_zoom.php
fix_map_data.php
fix_modal_zindex.php
fix_news_system.php
fix_old_invoice_payments.php
fix_popup_issue.php
fix_popup_simple.php
fix_request_status_column.php
fix_tax_types_table.php
```

#### 📂 FILES TO MOVE TO `_maintenance/debug/`:

Debug and diagnostic scripts. Should never be web-accessible.

```
debug_citizens_page.php
debug_facilities.php
debug_map_issue.php
debug_news_images.php
debug_permissions_ajax.php
debug_projects_dropdown.php
debug_telegram_message.php
```

#### 📂 FILES TO MOVE TO `_maintenance/checks/`:

Database structure verification scripts.

```
check_citizens_accounts_columns.php
check_columns.php
check_dashboard_links.php
check_facilities_data.php
check_map_settings.php
check_news_table.php
check_new_system.php
check_permissions_db.php
check_table_structure.php
check_telegram_settings.php
```

#### 📂 FILES TO MOVE TO `_maintenance/tests/`:

Test scripts that validated system functionality.

```
test_all_systems.php
test_api_quick.php
test_coordinates.php
test_csrf_final.php
test_facilities_api.php
test_facilities_system.php
test_gemini_direct.php
test_initiative_complete.php
test_initiative_edit.php
test_initiative_form.php
test_initiative_images.php
test_map.php
test_map_no_api.php
test_news_system_quick.php
test_new_image_system.php
test_phase1_security.php
test_phase1_systems.php
test_phase2_auth.php
test_phase3_api.php
test_phase4_csrf.php
test_redirect.php
test_telegram_citizen.php
```

#### 📂 FILES TO MOVE TO `_maintenance/dangerous/`:

Scripts that can drop tables or modify admin access. HIGHEST PRIORITY to remove from web root.

```
drop_citizen_tables.php
fix_admin_password.php
```

---

### 4.2 Root Directory Documentation Files — Classification

#### 📂 ALL TO MOVE TO `docs/history/`:

All 97 documentation files (.md and .txt) in the root directory should be moved to `docs/history/`. These are historical records of fixes and implementations.

**Markdown files (84 files):**
```
ACTION_ITEMS_CHECKLIST.md
ADMIN_FACILITIES_NAVIGATION.md
ALL_PHASES_SUMMARY.md
ALL_TABLES_MANAGER_ARABIC_UPDATE.md
ALL_TABLES_MANAGER_DOCUMENTATION.md
API_FIX_SUMMARY.md
AUTO_INVOICE_NUMBER.md
AUTO_UPDATE_CITIZEN_DATA.md
BACKEND_ANALYSIS_REPORT.md
BUDGETS_REPORT_GUIDE.md
BUDGET_ITEMS_EDIT_DELETE_READY.md
BUDGET_SYSTEM_UPDATE_COMPLETE.md
BUDGET_TRACKING_FIXED.md
BUDGET_UPDATE_INSTRUCTIONS.md
CITIZENS_CURRENCY_UPDATE.md
CITIZEN_ACCOUNTS_DATABASE_DOCUMENTATION.md
CITIZEN_ACCOUNTS_SETUP_README.md
CITIZEN_ACCOUNTS_USER_GUIDE.md
CITIZEN_REQUESTS_ADVANCED_DASHBOARD.md
CITIZEN_REQUESTS_FIX_SUMMARY.md
COMPLETE_SYSTEM_READY.md
COMPREHENSIVE_WEBSITE_AUDIT_REPORT.md
CONTRIBUTION_REQUESTS_FINAL_FIX.md
CONTRIBUTION_REQUESTS_FIX.md
CRUD_COMPLETE.md
CSRF_COMPLETE_STATUS.md
CSRF_COMPLETION_STATUS.md
CSRF_FIXES_SUMMARY.md
CSRF_IMPLEMENTATION_GUIDE.md
CSRF_PROTECTION_FINAL_REPORT.md
CURRENCY_SELECTION_ADDED.md
DASHBOARD_LINKS_REPORT.md
DATABASE_UPDATE_SUMMARY.md
edit_facility_status.md
ENHANCED_CITIZEN_REQUESTS_IMPLEMENTATION.md
FACILITIES_MAP_COMPLETE.md
FACILITIES_MAP_GUIDE.md
FINAL_FIX_COLUMN_NAMES.md
FINAL_FIX_INVOICES.md
FINAL_IMPLEMENTATION_SUMMARY.md
FINAL_SOLUTION_SUMMARY.md
FINAL_SUMMARY.md
FINANCE_BUDGET_LINK_GUIDE.md
FINANCE_EDIT_DELETE_COMPLETE.md
FINANCIAL_SYSTEM_COMPLETE.md
FINANCIAL_SYSTEM_COMPLETE_GUIDE.md
FIX_ALL_PROJECTS_ISSUES.md
FIX_CITIZEN_ID_COLUMN.md
FIX_INVOICES_ISSUE.md
FIX_LOADEDACCESSCODE_ERROR.md
FIX_OLD_PAYMENTS_INSTRUCTIONS.md
FIX_STORED_PROCEDURES.md
HERO_IMAGE_INSTRUCTIONS.md
IMPLEMENTATION_GUIDE.md
IMPLEMENTATION_SUMMARY.md
INVOICES_ENHANCEMENT_COMPLETE.md
MAP_AUTO_ZOOM_FIX.md
MAP_FIX_SUMMARY.md
MENU_UPDATED_REPORT.md
MOBILE_RESPONSIVE_FIX_SUMMARY.md
PHASE1_COMPLETE.md
PHASE1_COMPLETE_SUMMARY.md
PHASE1_FINAL_STATUS.md
PHASE1_SECURITY_IMPLEMENTATION.md
PHASE2_COMPLETE.md
PHASE2_IMPROVEMENT_PLAN.md
PHASE3_COMPLETE.md
PHASE4_COMPLETE.md
PHASES_STATUS_REPORT.md
PHONE_PROTECTION_SYSTEM.md
PHONE_VERIFICATION_SCENARIOS.md
PROBLEM_SOLVED_FINAL.md
PROJECTS_UNIFICATION_PLAN.md
PROJECTS_UNIFICATION_STEPS.md
PROJECT_ANALYSIS_REPORT.md
PROJECT_TRACKING_ISSUE.md
PUBLIC_CONTENT_MANAGEMENT_FIX.md
README_CITIZEN_ACCOUNTS.md
README_IMPORTANT_LINKS_GUIDE.md
README_STORED_PROCEDURES.md
RECAPTCHA_ACTIVATION_STATUS.md
RECAPTCHA_FINAL_SOLUTION.md
RECAPTCHA_SETUP_GUIDE.md
RECAPTCHA_TIMEOUT_SOLUTION.md
REQUEST_STATUS_FIX_SUMMARY.md
REQUEST_TYPES_EDIT_FIX_SUMMARY.md
SETUP_TRIGGERS_INSTRUCTIONS.md
SOBUSINESS_FOOTER_README.md
SOLUTION_SUMMARY.md
SOURCES_ANALYSIS_REPORT.md
SYSTEM_INTEGRATION_ANALYSIS.md
TELEGRAM_MIGRATION_COMPLETE.md
TRACK_REQUEST_FIX.md
UNAPPROVE_BUDGET_FEATURE.md
UNIFICATION_SUCCESS.md
UPDATE_CITIZEN_REQUEST_FIX.md
VIEW_CITIZEN_REQUEST_FIX.md
WHATSAPP_INTEGRATION_GUIDE.md
```

**Text files (13 files):**
```
ALL_TABLES_MANAGER_SUMMARY.txt
FINAL_FIX_READY.txt
FINAL_SOLUTION.txt
FIX_SETUP_COMPLETE.txt
HERO_IMAGE_INSTRUCTIONS.txt
MISSING_PAGES_SUMMARY.txt
OPEN_BUDGET_UPDATE.txt
PHASE_2_COMPLETE_SUMMARY.txt
READY_TO_TEST.txt
START_DATABASE_SETUP.txt
START_HERE_UPDATED.txt
STORED_PROCEDURES_SOLUTION.txt
TABLES_FIX_SUMMARY.txt
```

#### ✅ FILES TO KEEP in root:
```
PROJECT_STRUCTURE_AND_DATABASE_ANALYSIS.md (current analysis)
PROJECT_CLEANUP_SECURITY_AND_REFACTORING_PLAN.md (this file)
```

---

### 4.3 Module Backup Files — Classification

#### 📂 FILES TO MOVE TO `_archive/modules_backup/`:

All 22 backup/old/fixed module files. These are superseded by the current active version.

```
modules/budget_example.php
modules/budget_example_backup.php
modules/contact_management_backup.php
modules/council_management_backup.php
modules/council_management_complete.php
modules/council_management_enhanced.php
modules/council_management_final.php
modules/council_management_fixed.php
modules/council_management_original.php
modules/council_management_working.php
modules/facilities_api_backup.php
modules/facilities_api_final.php
modules/facilities_api_fixed.php
modules/facilities_management_backup.php
modules/get_employee_backup.php
modules/hr_backup.php
modules/important_links_sources_examples.php
modules/municipality_management_complete.php
modules/municipality_management_fixed.php
modules/public_content_management_backup.php
modules/system_settings_old.php
modules/vehicles_protected_example.php
```

---

### 4.4 Duplicate Project Folder

#### 📂 MOVE TO `_archive/old_project_copy/`:

| Item | Detail |
|------|--------|
| **Folder** | `tekrit_municipality/tekrit_municipality/` |
| **Contains** | 153 files — a complete old copy of the project |
| **Action** | Move entire folder to `_archive/old_project_copy/` |
| **Risk** | LOW — This is a nested copy, not used by the active system |
| **Backup first?** | ✅ YES — Verify no symlinks or includes point to this folder before moving |

**Verification before moving:**
Run this grep to ensure no active file references this nested folder:
```bash
grep -r "tekrit_municipality/tekrit_municipality" --include="*.php" .
```
If no results, safe to move.

---

### 4.5 Files That MUST NOT Be Touched

The following files and directories are critical to the running system and must NOT be modified during cleanup:

| File/Folder | Reason |
|-------------|--------|
| `index.php` | Main entry point |
| `login.php` | Active login system (modify only in Phase 2 for security fix) |
| `logout.php` | Active logout handler |
| `reset_password.php` | Active password reset |
| `comprehensive_dashboard.php` | Active admin dashboard |
| `all_tables_manager.php` | Active table manager |
| `config/config.php` | Core configuration |
| `config/database.php` | Database connection |
| `includes/` (all files) | Core system libraries |
| `modules/` (active files only) | Active admin modules |
| `public/` (all files) | Active public website |
| `admin/` (all files) | Active admin pages |
| `api/` (all files) | Active API endpoints |
| `ajax/` (all files) | Active AJAX handlers |
| `cron/` (all files) | Active cron jobs |
| `uploads/` (subdirectories and their contents) | User uploaded files |
| `database/` or `sql/` | SQL schema files |
| `cache/` | Runtime cache |
| `logs/` | Application logs |

---

## 5. Uploads Folder Protection Plan

### 5.1 Create `.htaccess` for `uploads/`

Create the file `uploads/.htaccess` with the following content:

```apache
# ===========================================================
# SECURITY: Prevent PHP execution in uploads directory
# Tekrit Municipality - Upload Protection
# ===========================================================

# Disable PHP execution completely
<FilesMatch "\.ph(p[3457]?|t|tml)$">
    Require all denied
</FilesMatch>

# Also block via handler
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<IfModule mod_php7.c>
    php_flag engine off
</IfModule>
<IfModule mod_php8.c>
    php_flag engine off
</IfModule>

# Block dangerous file extensions
<FilesMatch "\.(php|phtml|php3|php4|php5|php7|phps|pht|cgi|pl|py|sh|bat|exe|cmd|com|vbs|js|jsp|asp|aspx|htaccess|htpasswd|ini|log|sql|bak|swp|config)$">
    Require all denied
</FilesMatch>

# Allow only safe file types to be served
<FilesMatch "\.(jpg|jpeg|png|gif|webp|svg|bmp|ico|pdf|doc|docx|xls|xlsx|ppt|pptx|txt|csv|zip|rar)$">
    Require all granted
</FilesMatch>

# Prevent directory listing
Options -Indexes

# Prevent script execution via SetHandler
RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .phps .pht
RemoveType .php .phtml .php3 .php4 .php5 .php7 .phps .pht

# Force download for non-image files (optional extra protection)
<FilesMatch "\.(pdf|doc|docx|xls|xlsx)$">
    Header set Content-Disposition attachment
</FilesMatch>
```

### 5.2 Create `.htaccess` for `public/uploads/`

Create the same file at `public/uploads/.htaccess` with identical content.

### 5.3 Verification Steps

After creating the `.htaccess` files:

1. **Test image access:** Visit `http://localhost:8080/tekrit_municipality/uploads/news/[any-image].jpg` — should display normally
2. **Test PHP block:** If `test_security.php` hasn't been moved yet, visit `http://localhost:8080/tekrit_municipality/uploads/test_security.php` — should return 403 Forbidden
3. **Test directory listing:** Visit `http://localhost:8080/tekrit_municipality/uploads/` — should return 403 (no directory listing)
4. **Test admin functions:** Upload a news image via admin panel — should work normally
5. **Test citizen uploads:** Submit a citizen request with an attachment — should work normally

---

## 6. Database Protection Plan

### 6.1 Current Problem

The project uses `root` user with **no password** for MySQL connection:

```php
// config/config.php (CURRENT - INSECURE)
define('DB_HOST', 'localhost');
define('DB_NAME', 'tekrit_municipality');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 6.2 Local Development Fix

Even for local development, create a dedicated database user:

```sql
-- Run in phpMyAdmin or MySQL CLI:
CREATE USER 'tekrit_user'@'localhost' IDENTIFIED BY 'Tekrit_Mun!c1p@l_2026';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, 
      CREATE TEMPORARY TABLES, EXECUTE ON tekrit_municipality.* 
      TO 'tekrit_user'@'localhost';
FLUSH PRIVILEGES;
```

Then update `config/config.php`:
```php
define('DB_USER', 'tekrit_user');
define('DB_PASS', 'Tekrit_Mun!c1p@l_2026');
```

### 6.3 Production/Hosting Preparation

For future deployment to a live server:

1. **Environment-based configuration:**
   Create `config/env.php` (add to `.gitignore`):
   ```php
   <?php
   // This file should NEVER be committed to version control
   define('DB_HOST', 'production-host');
   define('DB_NAME', 'tekrit_municipality');
   define('DB_USER', 'prod_tekrit_user');
   define('DB_PASS', 'STRONG_RANDOM_PASSWORD_HERE');
   ```

2. **Update config.php to use env.php:**
   ```php
   <?php
   // Load environment-specific settings
   $env_file = __DIR__ . '/env.php';
   if (file_exists($env_file)) {
       require_once $env_file;
   } else {
       // Fallback for local development
       define('DB_HOST', 'localhost');
       define('DB_NAME', 'tekrit_municipality');
       define('DB_USER', 'tekrit_user');
       define('DB_PASS', 'local_dev_password');
   }
   ```

3. **Protect config directory with .htaccess:**
   ```apache
   # config/.htaccess
   <IfModule mod_authz_core.c>
       Require all denied
   </IfModule>
   <IfModule !mod_authz_core.c>
       Order Deny,Allow
       Deny from all
   </IfModule>
   ```

4. **Add to .gitignore:**
   ```
   config/env.php
   config/api_keys.php
   ```

### 6.4 Database Backup Strategy

Before ANY changes:
```bash
# Full backup command (run from XAMPP shell):
mysqldump -u root tekrit_municipality > backup_YYYY-MM-DD.sql

# Structure only:
mysqldump -u root --no-data tekrit_municipality > schema_backup_YYYY-MM-DD.sql
```

---

## 7. Admin Panel Protection Plan

### 7.1 Current State

The admin panel uses an ad-hoc approach where each file independently includes authentication checks. There is no centralized auth gate.

**Current pattern (inconsistent across files):**
```php
// Some files do this:
require_once '../includes/auth.php';
$auth = new Auth();
if (!$auth->isLoggedIn()) { header('Location: ../login.php'); exit; }

// Other files do this:
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }

// Some admin/ files may not check at all
```

### 7.2 Recommended Solution: Central Auth Guard

Create a single file that ALL admin/module pages must include:

**Create `includes/admin_guard.php`:**
```php
<?php
/**
 * Admin Guard - Central Authentication & Authorization
 * Include this at the top of EVERY admin/module file.
 * This ensures consistent security across all admin pages.
 */

// Prevent direct access
if (!defined('ADMIN_GUARD_LOADED')) {
    define('ADMIN_GUARD_LOADED', true);
}

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/SessionManager.php';
    SessionManager::init();
}

// Include required auth files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/CsrfProtection.php';

// Check authentication
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    // Handle AJAX requests differently
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'redirect' => '../login.php']);
        exit;
    }
    header('Location: ' . (strpos($_SERVER['SCRIPT_NAME'], '/modules/') !== false 
        || strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false 
        ? '../login.php' : 'login.php'));
    exit;
}

// Validate session security
if (class_exists('SessionManager') && !SessionManager::validate()) {
    $auth->logout();
    header('Location: ../login.php?error=session_expired');
    exit;
}

// Make auth available globally
$GLOBALS['auth'] = $auth;
$GLOBALS['current_user_id'] = $_SESSION['user_id'] ?? null;
$GLOBALS['current_user_type'] = $_SESSION['user_type'] ?? null;
```

### 7.3 Implementation Plan

**Phase 1 (Non-breaking):** Create `admin_guard.php` as described above.

**Phase 2 (Gradual rollout):** Update each admin/module file to use it:
```php
// At the very top of each modules/*.php and admin/*.php file:
require_once __DIR__ . '/../includes/admin_guard.php';
```

**Phase 3 (Specific permission checks):** Add permission checks to sensitive pages:
```php
// Example for modules/finance.php:
require_once __DIR__ . '/../includes/admin_guard.php';
requirePermission('manage_finance'); // From auth_helper.php
```

### 7.4 Admin Directory Protection

Add `.htaccess` to `admin/`:
```apache
# Redirect to login if accessed without proper session
# This is a backup protection layer
<IfModule mod_rewrite.c>
    RewriteEngine On
    # Block direct access to PHP files without referer from the same domain
    # (PHP-level auth is the primary protection)
</IfModule>
```

### 7.5 Direct File Access Prevention

Add to each module and admin PHP file (as first line after `<?php`):
```php
<?php
// Prevent direct access if not properly authenticated
if (!defined('ADMIN_GUARD_LOADED') && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    require_once __DIR__ . '/../includes/admin_guard.php';
}
```

---

## 8. Refactoring Plan for Large Files

### 8.1 `modules/public_content_management.php` (256KB) — HIGHEST PRIORITY

This is the **largest file in the project** and should be the first refactoring target.

**Current structure (estimated):** One massive file handling news, FAQs, website settings, council members, and general content — all with full CRUD + HTML templates.

**Recommended split:**

| New File | Content | Est. Size |
|----------|---------|-----------|
| `modules/content/news_section.php` | News CRUD operations + HTML | ~50KB |
| `modules/content/faq_section.php` | FAQ management + HTML | ~30KB |
| `modules/content/council_section.php` | Council member management + HTML | ~30KB |
| `modules/content/website_settings_section.php` | Website settings forms + HTML | ~40KB |
| `modules/content/content_tabs.php` | Main tab container that includes sections | ~10KB |
| `modules/content/content_helpers.php` | Shared PHP functions for content | ~20KB |
| `modules/public_content_management.php` | Slim router file that loads the appropriate section | ~5KB |

**Migration approach:**
1. Create `modules/content/` directory
2. Extract each tab/section into its own file
3. Keep original file as a loader/router
4. Test each section individually
5. Only after ALL sections work, remove duplicated code from original

---

### 8.2 `modules/municipality_management.php` (99KB)

**Recommended split:**

| New File | Content |
|----------|---------|
| `modules/municipality/municipal_info.php` | Municipality basic info management |
| `modules/municipality/sessions_management.php` | Municipal session CRUD |
| `modules/municipality/decisions_management.php` | Decision records management |
| `modules/municipality/departments_overview.php` | Department summary/stats |
| `modules/municipality_management.php` | Router/loader (keep as entry point) |

---

### 8.3 `public/citizen-requests.php` (95KB)

**Recommended split:**

| New File | Content |
|----------|---------|
| `public/citizen-requests.php` | Main form page (keep as entry, slimmed down) |
| `public/includes/request_form_handler.php` | Form submission PHP logic |
| `public/includes/request_validation.php` | Input validation rules |
| `public/assets/js/citizen-requests.js` | JavaScript form logic (extracted from inline) |
| `ajax/process-citizen-request.php` | AJAX processing endpoint |

---

### 8.4 `modules/committee_dashboard.php` (90KB)

**Recommended split:**

| New File | Content |
|----------|---------|
| `modules/committees/committee_list.php` | Committee listing and management |
| `modules/committees/committee_members.php` | Member management |
| `modules/committees/committee_sessions.php` | Session management |
| `modules/committees/committee_finance.php` | Financial summary |
| `modules/committees/committee_decisions.php` | Decision records |
| `modules/committee_dashboard.php` | Router/loader |

---

### 8.5 `comprehensive_dashboard.php` (89KB)

**Recommended split:**

| New File | Content |
|----------|---------|
| `dashboard/dashboard_stats.php` | KPI statistics queries and rendering |
| `dashboard/dashboard_charts.php` | Chart.js initialization and data |
| `dashboard/dashboard_recent.php` | Recent activity lists |
| `dashboard/dashboard_alerts.php` | System alerts and notifications |
| `includes/dashboard_helpers.php` | Shared dashboard query functions |
| `comprehensive_dashboard.php` | Main layout that includes sections |

---

### 8.6 `modules/budgets.php` (88.5KB)

**Recommended split:**

| New File | Content |
|----------|---------|
| `modules/budgets/budget_list.php` | Budget listing |
| `modules/budgets/budget_form.php` | Create/edit budget form |
| `modules/budgets/budget_items.php` | Budget line items management |
| `modules/budgets/budget_ai.php` | AI generation integration |
| `modules/budgets/budget_report.php` | Budget reporting |
| `modules/budgets.php` | Router/loader |

---

### General Refactoring Rules

1. **Always keep the original file name** as the entry point (for URL compatibility)
2. **Extract inline JavaScript** to external `.js` files
3. **Extract inline CSS** to external `.css` files
4. **Separate AJAX handlers** from page rendering logic
5. **Use `require_once`** in the main file to load sub-components
6. **Never change URLs** — the file name the browser requests should stay the same
7. **Test after each extraction** before proceeding to the next

---

## 9. Database Refactoring Plan

### 9.1 Projects Table Duplication

**Current state:**
- `projects` table (1 record) — used by `modules/projects_unified.php` (newer)
- `development_projects` table (5 records) — used by `public/projects.php` and `public/index.php` (older)

**These tables have different schemas:**

| Column | `projects` | `development_projects` |
|--------|-----------|----------------------|
| Name | `name` | `project_name` |
| Description | `description` | `project_description` |
| Status | `status` | `project_status` |
| Budget | `budget` | `project_base_cost` |
| Completion | N/A | `completion_percentage` |
| Location | N/A | `project_location` |
| Type | `type_id` (FK) | N/A |
| Manager | `manager_id` (FK) | N/A |

**Recommended plan (DO NOT execute yet):**

1. **Step 1:** Add missing columns to `projects` table:
   ```sql
   ALTER TABLE projects 
     ADD COLUMN project_location VARCHAR(255) AFTER description,
     ADD COLUMN completion_percentage DECIMAL(5,2) DEFAULT 0 AFTER project_location;
   ```

2. **Step 2:** Migrate data from `development_projects` to `projects`:
   ```sql
   INSERT INTO projects (name, description, status, budget, project_location, completion_percentage)
   SELECT project_name, project_description, project_status, project_base_cost, 
          project_location, completion_percentage
   FROM development_projects;
   ```

3. **Step 3:** Update `public/projects.php` and `public/index.php` to read from `projects` table

4. **Step 4:** Create a view for backward compatibility:
   ```sql
   CREATE VIEW development_projects_view AS 
   SELECT id, name AS project_name, description AS project_description, 
          status AS project_status, budget AS project_base_cost,
          project_location, completion_percentage
   FROM projects;
   ```

5. **Step 5:** Rename old table (don't drop):
   ```sql
   RENAME TABLE development_projects TO _deprecated_development_projects;
   ```

---

### 9.2 Dual Database Classes

**Current state:**
- `config/database.php` — Simple PDO connection class with `getConnection()` method
- `includes/Database.php` — Enhanced Singleton pattern with CRUD helpers, transactions, error handling

**Problem:** Different files include different classes. Some use the simple one, some use the enhanced one.

**Recommended plan:**

1. **Step 1:** Audit which files use which class:
   ```bash
   grep -r "require.*config/database" --include="*.php" . --files-with-matches
   grep -r "require.*includes/Database" --include="*.php" . --files-with-matches
   ```

2. **Step 2:** Ensure `includes/Database.php` is backward-compatible with `config/database.php` interface (it should have a `getConnection()` method)

3. **Step 3:** Gradually migrate files to use `includes/Database.php`

4. **Step 4:** Once all files migrated, rename `config/database.php` to `config/database_legacy.php`

5. **Step 5:** Create a wrapper in `config/database.php` that just includes the enhanced version:
   ```php
   <?php
   // Legacy compatibility - all new code should use includes/Database.php
   require_once __DIR__ . '/../includes/Database.php';
   ```

---

### 9.3 Unused Empty Tables

**Tables with 0 records that have no PHP file association:**

| Table | Records | Associated PHP | Action |
|-------|---------|---------------|--------|
| `citizen_opinions` | 0 | None found | REVIEW — may be unused |
| `initiative_evaluations` | 0 | None found | REVIEW — may be unused |
| `volunteer_attendance` | 0 | None found | REVIEW — may be unused |
| `polls` | 0 | None found | REVIEW — may be unused |
| `poll_responses` | 0 | None found | REVIEW — may be unused |
| `municipality_assets` | 0 | None found | REVIEW — may be unused |
| `municipality_resources` | 0 | None found | REVIEW — may be unused |
| `appreciation_certificates` | 0 | None found | REVIEW — may be unused |
| `associations` | 0 | None found | REVIEW — may be unused |
| `external_data_sources` | 0 | None found | REVIEW — may be unused |
| `currency_conversion_log` | 0 | None found | REVIEW — may be unused |
| `activity_log` | 0 | None found | REVIEW — may be for future use |

**DO NOT DROP these tables now.** Instead:
1. Create a prefix rename for clearly unused tables after confirming:
   ```sql
   RENAME TABLE polls TO _unused_polls;
   ```
2. This makes them invisible to normal operations but preservable
3. After 3 months with no issues, they can be dropped

---

### 9.4 Inconsistent Naming

| Pattern | Examples | Recommendation |
|---------|----------|---------------|
| Snake_case (correct) | `citizen_requests`, `financial_transactions` | ✅ Keep as standard |
| Plural tables | `users`, `departments`, `projects` | ✅ Keep as standard |
| Inconsistent prefix | `ref_need_categories` vs `reference_data` | Standardize to one prefix |
| View prefix inconsistency | `complaints_detailed` vs `v_citizens_summary` | Standardize to `v_` prefix |
| Relationship tables | `user_permissions`, `committee_members` | ✅ Already follow convention |

**Plan:** Do NOT rename tables. Instead, create Views with standardized names if needed for new code.

---

## 10. Recommended Folder Structure

### Current vs Proposed Structure

```
tekrit_municipality/                      # PROJECT ROOT
│
├── index.php                             # ✅ KEEP - Entry point
├── login.php                             # ✅ KEEP - Employee login
├── logout.php                            # ✅ KEEP - Logout
├── reset_password.php                    # ✅ KEEP - Password reset
├── comprehensive_dashboard.php           # ✅ KEEP - Admin dashboard
├── all_tables_manager.php                # ✅ KEEP - Table manager
├── .htaccess                             # 🆕 CREATE - URL rewrites & security
│
├── config/                               # ✅ KEEP (add .htaccess)
│   ├── .htaccess                         # 🆕 Deny all web access
│   ├── config.php
│   ├── database.php
│   ├── api_config.php
│   └── env.php                           # 🆕 Environment-specific (gitignored)
│
├── includes/                             # ✅ KEEP (add .htaccess)
│   ├── .htaccess                         # 🆕 Deny all web access
│   ├── admin_guard.php                   # 🆕 Central auth guard
│   ├── auth.php
│   ├── auth_helper.php
│   ├── Database.php
│   ├── SessionManager.php
│   ├── CsrfProtection.php
│   ├── [... all other includes ...]
│   └── mappers/
│
├── modules/                              # ✅ KEEP (remove backup files)
│   ├── content/                          # 🆕 Split from public_content_management.php
│   ├── municipality/                     # 🆕 Split from municipality_management.php
│   ├── committees/                       # 🆕 Split from committee_dashboard.php
│   ├── budgets/                          # 🆕 Split from budgets.php
│   ├── finance.php
│   ├── citizens.php
│   ├── complaints.php
│   └── [... active modules only ...]
│
├── dashboard/                            # 🆕 Split from comprehensive_dashboard.php
│   ├── dashboard_stats.php
│   ├── dashboard_charts.php
│   └── dashboard_recent.php
│
├── admin/                                # ✅ KEEP (add auth guard)
│   ├── initiatives.php
│   ├── add_initiative.php
│   ├── edit_initiative.php
│   └── manage_initiative_images.php
│
├── public/                               # ✅ KEEP
│   ├── includes/
│   ├── assets/
│   │   ├── css/
│   │   ├── js/                           # 🆕 Extracted JS from inline scripts
│   │   └── images/
│   ├── uploads/
│   │   └── .htaccess                     # 🆕 Block PHP execution
│   └── [... public pages ...]
│
├── api/                                  # ✅ KEEP
├── ajax/                                 # ✅ KEEP
├── cron/                                 # ✅ KEEP
│
├── uploads/                              # ✅ KEEP (add .htaccess)
│   ├── .htaccess                         # 🆕 Block PHP execution
│   ├── council_members/
│   ├── documents/
│   ├── facilities/
│   ├── initiatives/
│   ├── news/
│   └── requests/
│
├── database/                             # ✅ KEEP
├── scripts/                              # ✅ KEEP
├── cache/                                # ✅ KEEP
│
├── logs/                                 # ✅ KEEP (verify .htaccess)
│   └── .htaccess                         # 🆕 Deny all web access
│
├── docs/                                 # 📂 REORGANIZED
│   ├── AI_INTEGRATION_GUIDE.md
│   ├── history/                          # 🆕 Moved from root (97 doc files)
│   └── guides/                           # 🆕 Active user/dev guides
│
├── js/                                   # ✅ KEEP
│
├── _archive/                             # 🆕 Created during cleanup
│   ├── modules_backup/                   # Backup module files (22 files)
│   ├── old_project_copy/                 # Duplicate project folder (153 files)
│   └── removed_from_uploads/             # test_security.php
│
├── _maintenance/                         # 🆕 Created during cleanup
│   ├── .htaccess                         # 🆕 Deny ALL web access
│   ├── setup/                            # Setup/migration scripts (17 files)
│   ├── fixes/                            # Fix scripts (17 files)
│   ├── debug/                            # Debug scripts (7 files)
│   ├── checks/                           # Check scripts (10 files)
│   ├── tests/                            # Test scripts (22 files)
│   └── dangerous/                        # Drop/password reset scripts (2 files)
│
├── PROJECT_STRUCTURE_AND_DATABASE_ANALYSIS.md
└── PROJECT_CLEANUP_SECURITY_AND_REFACTORING_PLAN.md
```

### Key Changes Summary

| Change | Files Moved | Impact |
|--------|-------------|--------|
| Create `_maintenance/` | 74 PHP scripts from root | NONE — These are not used at runtime |
| Create `_archive/` | 22 backup modules + 153 duplicate files | NONE — These are not actively used |
| Move docs to `docs/history/` | 97 .md/.txt files | NONE — Documentation files |
| Add `.htaccess` files | 6 new files | IMPROVES security, no functional impact |
| Create `admin_guard.php` | 1 new file | Requires gradual integration |
| Future: Split large files | Refactoring (later phase) | Must be tested individually |

---

## 11. Step-by-Step Execution Roadmap

### Phase 1: Backup and Safety (Day 1)

| # | Task | Command/Action | Risk |
|---|------|---------------|------|
| 1.1 | Create full project backup | `xcopy /E /I tekrit_municipality tekrit_municipality_BACKUP_2026-04-29` | None |
| 1.2 | Create full database backup | `mysqldump -u root tekrit_municipality > backup_2026-04-29.sql` | None |
| 1.3 | Document current state | Screenshot all working pages | None |
| 1.4 | Run Testing Checklist (Section 12) | Verify everything works | None |
| 1.5 | Save backup off-site | Copy to external drive or cloud | None |

**✅ GATE: Do not proceed to Phase 2 until backup is confirmed complete and restorable.**

---

### Phase 2: Immediate Security Fixes (Day 1-2)

| # | Task | Details | Risk |
|---|------|---------|------|
| 2.1 | Create `_archive/` folder | `mkdir _archive` | None |
| 2.2 | Create `_maintenance/` folder with subfolders | `mkdir _maintenance, _maintenance/setup, _maintenance/fixes, _maintenance/debug, _maintenance/checks, _maintenance/tests, _maintenance/dangerous` | None |
| 2.3 | Move `uploads/test_security.php` | Move to `_archive/removed_from_uploads/` | None |
| 2.4 | Create `uploads/.htaccess` | Content from Section 5 | Low — test uploads after |
| 2.5 | Create `public/uploads/.htaccess` | Same content | Low — test uploads after |
| 2.6 | Add `_maintenance/.htaccess` | `Require all denied` | None — folder is new |
| 2.7 | Create `config/.htaccess` | `Require all denied` | Low — test page loads |
| 2.8 | Create `includes/.htaccess` | `Require all denied` | Low — test page loads |
| 2.9 | Verify `logs/.htaccess` exists | Create if missing | None |
| 2.10 | **RUN TESTING CHECKLIST** | Full test of all functions | — |

**✅ GATE: Do not proceed to Phase 3 until all tests pass.**

---

### Phase 3: File Cleanup (Day 2-3)

| # | Task | Files | Risk |
|---|------|-------|------|
| 3.1 | Move dangerous scripts | `drop_citizen_tables.php`, `fix_admin_password.php` → `_maintenance/dangerous/` | None |
| 3.2 | Move setup scripts | 17 files → `_maintenance/setup/` | None |
| 3.3 | Move fix scripts | 17 files → `_maintenance/fixes/` | None |
| 3.4 | Move debug scripts | 7 files → `_maintenance/debug/` | None |
| 3.5 | Move check scripts | 10 files → `_maintenance/checks/` | None |
| 3.6 | Move test scripts | 22 files → `_maintenance/tests/` | None |
| 3.7 | **RUN TESTING CHECKLIST** | Verify nothing broke | — |
| 3.8 | Move backup modules | 22 files → `_archive/modules_backup/` | Low — verify active modules still work |
| 3.9 | Move documentation files | 97 files → `docs/history/` | None |
| 3.10 | Verify nested duplicate folder references | `grep -r "tekrit_municipality/tekrit_municipality"` | — |
| 3.11 | Move duplicate folder | `tekrit_municipality/` → `_archive/old_project_copy/` | Low — verify no includes reference it |
| 3.12 | **RUN TESTING CHECKLIST** | Full test after all cleanup | — |

**✅ GATE: Do not proceed until site works perfectly.**

---

### Phase 4: Admin/Auth Hardening (Day 4-5)

| # | Task | Details | Risk |
|---|------|---------|------|
| 4.1 | Create `includes/admin_guard.php` | Content from Section 7.2 | None — new file |
| 4.2 | Add guard to `admin/*.php` files (4 files) | Add `require_once` at top of each file | Low — test admin pages |
| 4.3 | Test admin pages | Login/logout, create initiative | — |
| 4.4 | Gradually add guard to `modules/*.php` | Start with least-used modules first | Medium — test each module |
| 4.5 | Fix `login.php` security issues | Remove password auto-copy, unify error messages | Medium — backup login.php first |
| 4.6 | **RUN TESTING CHECKLIST** | Focus on login/logout and admin functions | — |

**✅ GATE: All admin pages must work with proper auth checks.**

---

### Phase 5: Database Cleanup (Day 6-7)

| # | Task | Details | Risk |
|---|------|---------|------|
| 5.1 | Create dedicated MySQL user | SQL from Section 6.2 | Low |
| 5.2 | Update config to use new user | Modify `config/config.php` | Medium — test DB connections |
| 5.3 | Test all database operations | CRUD on main modules | — |
| 5.4 | Audit Database class usage | Grep for both classes | None — research only |
| 5.5 | Plan projects table unification | Document exact migration SQL | None — planning only |
| 5.6 | Rename clearly unused tables with `_unused_` prefix | Only after confirming no PHP references | Low |
| 5.7 | **RUN TESTING CHECKLIST** | Focus on database operations | — |

---

### Phase 6: Code Refactoring (Week 2+)

| # | Task | Details | Risk |
|---|------|---------|------|
| 6.1 | Split `public_content_management.php` | Create `modules/content/` subdirectory | High — extensive testing needed |
| 6.2 | Split `comprehensive_dashboard.php` | Create `dashboard/` directory | High — main admin page |
| 6.3 | Split `citizen-requests.php` | Extract JS and AJAX handlers | Medium |
| 6.4 | Split `municipality_management.php` | Create `modules/municipality/` | Medium |
| 6.5 | Split `committee_dashboard.php` | Create `modules/committees/` | Medium |
| 6.6 | Split `budgets.php` | Create `modules/budgets/` | Medium |
| 6.7 | Unify Database class | Migrate to single class | Medium |
| 6.8 | Unify projects tables | Execute migration from Section 9.1 | High — affects public + admin |
| 6.9 | Extract inline JavaScript | Move to `.js` files | Low but tedious |

**⚠️ NOTE: Each item in Phase 6 should be done one at a time with full testing between each.**

---

### Phase 7: Verification (After each phase)

| # | Task | Details |
|---|------|---------|
| 7.1 | Run full Testing Checklist (Section 12) | All 10 test areas |
| 7.2 | Check browser console for JavaScript errors | Open DevTools on each page |
| 7.3 | Check PHP error logs | `logs/errors_*.log` and `logs/app_*.log` |
| 7.4 | Verify all uploads work | Test image display on news, initiatives |
| 7.5 | Verify all AJAX functions | Test inline editing, form submissions |
| 7.6 | Compare page screenshots | With pre-change screenshots from Phase 1 |

---

## 12. Testing Checklist

Run this checklist after **every phase** to verify nothing is broken.

### 12.1 Public Website Tests

| # | Test | URL | Expected Result | ✅/❌ |
|---|------|-----|-----------------|-------|
| P1 | Homepage loads | `/public/index.php` | Page loads with news, projects, initiatives | |
| P2 | News listing | `/public/news.php` | List of news articles displayed | |
| P3 | News detail | `/public/news-detail.php?id=1` | Single news article with images | |
| P4 | Projects listing | `/public/projects.php` | List of development projects | |
| P5 | Project detail | `/public/project-detail.php?id=1` | Project details with contribution form | |
| P6 | Initiatives | `/public/initiatives.php` | List of active initiatives | |
| P7 | Initiative detail | `/public/initiative-detail.php?id=1` | Initiative with volunteer registration | |
| P8 | Council page | `/public/council.php` | Council members displayed | |
| P9 | Committees page | `/public/committees.php` | Committees listed | |
| P10 | Contact form | `/public/contact.php` | Form renders, submit works | |
| P11 | Facilities map | `/public/facilities-map.php` | Map loads with markers | |
| P12 | Important links | `/public/important-links.php` | Links categorized and displayed | |
| P13 | Citizen requests form | `/public/citizen-requests.php` | Form renders with all fields | |
| P14 | Citizen complaints | `/public/citizen-complaints.php` | Form renders | |
| P15 | Track request | `/public/track-request.php` | Tracking form works | |
| P16 | Citizen login | `/public/login.php` | Login page loads | |
| P17 | Images display | Check news/initiative images | Images load correctly from uploads/ | |

### 12.2 Authentication Tests

| # | Test | URL | Expected Result | ✅/❌ |
|---|------|-----|-----------------|-------|
| A1 | Login page loads | `/login.php` | Form displays | |
| A2 | Valid login | Enter admin credentials | Redirects to dashboard | |
| A3 | Invalid login | Enter wrong password | Error message shown (generic) | |
| A4 | Logout | Click logout | Redirects to public homepage | |
| A5 | Session persistence | Navigate between admin pages | Stay logged in | |
| A6 | Direct module access (not logged in) | `/modules/finance.php` (while logged out) | Redirects to login | |
| A7 | Password reset | `/reset_password.php` | Form loads | |

### 12.3 Admin Dashboard Tests

| # | Test | URL | Expected Result | ✅/❌ |
|---|------|-----|-----------------|-------|
| D1 | Dashboard loads | `/comprehensive_dashboard.php` | Stats, charts, menu display | |
| D2 | Menu navigation | Click each menu category | Sections load correctly | |
| D3 | Statistics accurate | Compare with database counts | Numbers match | |

### 12.4 Module Tests (Critical Modules)

| # | Test | Module | Expected Result | ✅/❌ |
|---|------|--------|-----------------|-------|
| M1 | Finance page | `/modules/finance.php` | Transactions list + add form | |
| M2 | Citizens page | `/modules/citizens.php` | Citizens list + CRUD | |
| M3 | Complaints page | `/modules/complaints.php` | Complaints with status updates | |
| M4 | News management | `/modules/news_management_new.php` | News CRUD + image upload | |
| M5 | Projects management | `/modules/projects_unified.php` | Projects CRUD | |
| M6 | Budgets | `/modules/budgets.php` | Budget creation + items | |
| M7 | HR | `/modules/hr.php` | Employee management | |
| M8 | Permissions | `/modules/permissions.php` | Permission assignment | |
| M9 | System settings | `/modules/system_settings.php` | Settings form | |
| M10 | Content management | `/modules/public_content_management.php` | All tabs work | |

### 12.5 Upload Tests

| # | Test | Action | Expected Result | ✅/❌ |
|---|------|--------|-----------------|-------|
| U1 | Upload news image | Add news with image in admin | Image saves and displays | |
| U2 | Upload citizen request doc | Submit request with attachment | File saves correctly | |
| U3 | Upload council member photo | Add/edit council member | Photo displays | |
| U4 | Existing images display | View news/initiatives pages | All existing images load | |
| U5 | PHP file blocked | Try to access any .php in uploads/ | Returns 403 | |

### 12.6 Database Operation Tests

| # | Test | Operation | Expected Result | ✅/❌ |
|---|------|-----------|-----------------|-------|
| DB1 | Create record | Add a new citizen | Record appears in list | |
| DB2 | Read records | View any list page | Data loads correctly | |
| DB3 | Update record | Edit any existing record | Changes saved | |
| DB4 | Delete record | Delete a test record | Record removed | |
| DB5 | AJAX operations | Use inline edit features | Updates persist | |
| DB6 | Search/filter | Use search on citizens page | Results filter correctly | |

---

## 13. Developer Notes

### Critical Rules During Cleanup

1. **NEVER modify an active PHP file during the cleanup phase.** Only move files that are duplicates, backups, tests, or documentation. If you're unsure whether a file is active, DO NOT move it.

2. **Always move, never delete.** Use `_archive/` and `_maintenance/` folders so you can restore any file instantly. Only consider permanent deletion after 30+ days.

3. **Test after every batch of changes.** Don't move 50 files at once. Move them in groups of 5-10 and test between groups.

4. **The `modules/` folder is loaded dynamically.** The `comprehensive_dashboard.php` includes module files based on the menu configuration. Check `includes/menu_config.php` to see which files are actually referenced.

5. **Some "backup" files might be referenced.** Before moving any `_backup.php` file, search for it:
   ```bash
   grep -r "backup" --include="*.php" modules/ --files-with-matches
   ```

6. **The admin panel is partially SPA.** Some modules are loaded via AJAX/fetch into the dashboard. Moving a module file will break its URL. Only rename module files if you update `menu_config.php` to match.

7. **Watch for relative paths.** Many files use `../includes/` or `../config/`. Moving files to subdirectories will break these paths.

8. **Database changes are the riskiest.** Do not modify the database schema during the cleanup phase. Database changes belong to Phase 5 and Phase 6 only.

### Common Pitfalls to Avoid

| Pitfall | Why It's Dangerous |
|---------|-------------------|
| Moving a module file without checking menu_config.php | Dashboard will show broken link |
| Adding .htaccess without testing | Could block all PHP execution, not just uploads |
| Changing database connection without testing | All pages will break simultaneously |
| Moving setup scripts that are referenced by docs | Developers lose instructions for deployment |
| Renaming files that other files include via `require_once` | Fatal PHP errors |
| Moving JS/CSS files without updating HTML references | Broken formatting/functionality |

### How to Verify a File is Safe to Move

Run these checks before moving any PHP file:

```bash
# 1. Check if any other PHP file includes/requires this file:
grep -r "filename_without_extension" --include="*.php" . --files-with-matches

# 2. Check if it appears in menu_config.php:
grep "filename" includes/menu_config.php

# 3. Check if any JavaScript references it:
grep -r "filename" --include="*.js" .

# 4. Check if any AJAX calls reference it:
grep -r "filename" --include="*.php" . | grep -i "fetch\|ajax\|xmlhttp"

# 5. Check Apache access logs for recent access:
# (If available in XAMPP logs)
```

If ALL checks return empty, the file is safe to move.

### Emergency Restore Procedure

If something breaks after a change:

1. **Check PHP error logs:** `logs/errors_*.log`
2. **Check Apache error log:** `C:\xampp\apache\logs\error.log`
3. **Restore from _archive or _maintenance:**
   ```bash
   # Move the file back to its original location
   move _maintenance\setup\filename.php .\filename.php
   ```
4. **Restore database from backup if needed:**
   ```bash
   mysql -u root tekrit_municipality < backup_2026-04-29.sql
   ```
5. **If all else fails:** Restore entire project from the Phase 1 backup folder.

---

> **END OF PLAN**  
> **Status:** Ready for review. No modifications have been made to the project.  
> **Next step:** After reviewing this plan, approve each phase individually before execution begins.
