# Phase 5B - Missing Tables Schema Plan

**Date:** 2026-04-29  
**Project:** Tekrit Municipality Website  

This document outlines the exact schema and reasoning for creating the 7 missing tables that were discovered during the Phase 5 Database Audit. These tables are referenced by existing PHP scripts but do not exist in the database, causing fatal SQL errors when those scripts execute.

All table creations use the `CREATE TABLE IF NOT EXISTS` syntax to ensure perfect safety and reversibility.

---

## 1. `user_activity_log`
- **Why it is needed:** To log user activities and authentication events.
- **Referenced by:** `includes/auth_helper.php` (`logUserActivity` function).
- **Proposed Columns:**
  - `id` (INT, Primary Key)
  - `user_id` (INT, FK to users)
  - `action` (VARCHAR)
  - `details` (TEXT)
  - `ip_address` (VARCHAR)
  - `created_at` (TIMESTAMP)
- **Proposed Indexes:** Foreign key constraint on `user_id` (`ON DELETE CASCADE`).
- **Assumptions:** Data retention is handled manually; logs are tied strictly to the `users` table.
- **Safety:** ✅ 100% safe. Logs only.

## 2. `whatsapp_log`
- **Why it is needed:** To track pending, sent, and failed WhatsApp notifications to citizens.
- **Referenced by:** `includes/WhatsAppService.php`, `modules/whatsapp_pending_messages.php`.
- **Proposed Columns:**
  - `id`, `phone`, `message`, `message_type`, `request_id`, `citizen_id`
  - `status` (VARCHAR - pending, sent, failed, etc.)
  - `error_message`, `sent_at`, `delivered_at`, `read_at`, `created_at`
- **Proposed Indexes:** Index on `status` and `created_at` for faster queue processing.
- **Assumptions:** `request_id` and `citizen_id` are nullable because not all WhatsApp messages are tied to a specific citizen request.
- **Safety:** ✅ 100% safe. Required to unbreak the WhatsApp admin panel.

## 3. `municipal_forms`
- **Why it is needed:** To store dynamic form submissions like Building Permits.
- **Referenced by:** `modules/building_permit.php`.
- **Proposed Columns:**
  - `id`, `form_type`, `applicant_name`, `applicant_phone`, `applicant_address`
  - `application_data` (JSON)
  - `submission_date`, `status`, `created_at`, `updated_at`
- **Proposed Indexes:** Index on `form_type` and `submission_date` to speed up module filtering.
- **Assumptions:** `application_data` is used to store dynamic JSON objects since the PHP script uses `json_encode()`.
- **Safety:** ✅ 100% safe.

## 4. `inventory_items`
- **Why it is needed:** Central tracking of municipality physical stock (stationary, building materials, fuel).
- **Referenced by:** `modules/inventory.php`.
- **Proposed Columns:**
  - `id`, `item_code`, `item_name`, `category`, `unit`
  - `minimum_stock`, `current_stock`, `unit_price`, `currency_id`
  - `location`, `notes`, `created_by`, `created_at`, `updated_at`
- **Proposed Indexes:** Unique index on `item_code`, standard index on `category`.
- **Assumptions:** `item_code` must be unique to prevent duplicate entries.
- **Safety:** ✅ 100% safe. Required for the Inventory module to load.

## 5. `inventory_movements`
- **Why it is needed:** To track additions and subtractions to `inventory_items` over time.
- **Referenced by:** `modules/inventory.php`.
- **Proposed Columns:**
  - `id`, `item_id`, `movement_type`, `quantity`, `notes`, `created_by`, `created_at`
- **Proposed Indexes:** Foreign Key on `item_id` (`ON DELETE CASCADE`), index on `movement_type`.
- **Assumptions:** If an inventory item is deleted, its movement history should also be deleted to prevent orphaned constraints.
- **Safety:** ✅ 100% safe.

## 6. `request_workflow_stages`
- **Why it is needed:** To define the sequential multi-step approval stages for complex citizen requests.
- **Referenced by:** `public/citizen-requests-advanced.php`, `public/track-request-advanced.php`.
- **Proposed Columns:**
  - `id`, `request_type_id`, `stage_name`, `stage_description`, `stage_order`, `max_duration_days`, `created_at`
- **Proposed Indexes:** Index on `request_type_id` and `stage_order`.
- **Assumptions:** Stage definitions apply globally per `request_type`.
- **Safety:** ✅ 100% safe.

## 7. `request_stage_tracking`
- **Why it is needed:** To track an individual citizen request's progress through the workflow stages.
- **Referenced by:** `public/citizen-requests-advanced.php`, `public/track-request-advanced.php`.
- **Proposed Columns:**
  - `id`, `request_id`, `stage_id`, `status`, `notes`, `rejection_reason`
  - `assigned_to`, `started_at`, `completed_at`, `created_at`
- **Proposed Indexes:** Foreign Keys on `stage_id` and `assigned_to`, standard index on `request_id` and `status`.
- **Assumptions:** If a stage definition is deleted, the tracking records should be removed to avoid GUI crashes.
- **Safety:** ✅ 100% safe.

---
**Conclusion:** All schemas derived exactly from the PHP logic expected by the active files. Executing the SQL will be non-destructive and highly targeted.
