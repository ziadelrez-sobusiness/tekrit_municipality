-- المرحلة 5B: إصلاح الجداول المفقودة
-- Date: 2026-04-29
-- Project: Tekrit Municipality
-- Description: إنشاء الجداول المفقودة والتي يتم استدعاؤها في كود PHP

SET FOREIGN_KEY_CHECKS=0;

-- 1. user_activity_log (from includes/auth_helper.php)
CREATE TABLE IF NOT EXISTS `user_activity_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `action` VARCHAR(255) NOT NULL,
    `details` TEXT,
    `ip_address` VARCHAR(45),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. whatsapp_log (from includes/WhatsAppService.php)
CREATE TABLE IF NOT EXISTS `whatsapp_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `phone` VARCHAR(20) NOT NULL,
    `message` TEXT NOT NULL,
    `message_type` VARCHAR(50),
    `request_id` INT DEFAULT NULL,
    `citizen_id` INT DEFAULT NULL,
    `status` VARCHAR(50) DEFAULT 'pending',
    `error_message` TEXT,
    `sent_at` TIMESTAMP NULL DEFAULT NULL,
    `delivered_at` TIMESTAMP NULL DEFAULT NULL,
    `read_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_whatsapp_status` (`status`),
    INDEX `idx_whatsapp_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. municipal_forms (from modules/building_permit.php)
CREATE TABLE IF NOT EXISTS `municipal_forms` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `form_type` VARCHAR(100) NOT NULL,
    `applicant_name` VARCHAR(255) NOT NULL,
    `applicant_phone` VARCHAR(50) NOT NULL,
    `applicant_address` TEXT,
    `application_data` JSON,
    `submission_date` DATE,
    `status` VARCHAR(50) DEFAULT 'مقدم',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_form_type` (`form_type`),
    INDEX `idx_submission_date` (`submission_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. inventory_items (from modules/inventory.php)
CREATE TABLE IF NOT EXISTS `inventory_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `item_code` VARCHAR(100) NOT NULL,
    `item_name` VARCHAR(255) NOT NULL,
    `category` VARCHAR(100),
    `unit` VARCHAR(50),
    `minimum_stock` INT DEFAULT 0,
    `current_stock` INT DEFAULT 0,
    `unit_price` DECIMAL(10,2) DEFAULT 0.00,
    `currency_id` INT DEFAULT NULL,
    `location` VARCHAR(255),
    `notes` TEXT,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX `idx_item_code` (`item_code`),
    INDEX `idx_inventory_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. inventory_movements (from modules/inventory.php)
CREATE TABLE IF NOT EXISTS `inventory_movements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `item_id` INT NOT NULL,
    `movement_type` VARCHAR(50) NOT NULL,
    `quantity` INT NOT NULL,
    `notes` TEXT,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`item_id`) REFERENCES `inventory_items`(`id`) ON DELETE CASCADE,
    INDEX `idx_movement_type` (`movement_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. request_workflow_stages (from public/citizen-requests-advanced.php)
CREATE TABLE IF NOT EXISTS `request_workflow_stages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `request_type_id` INT NOT NULL,
    `stage_name` VARCHAR(255) NOT NULL,
    `stage_description` TEXT,
    `stage_order` INT DEFAULT 1,
    `max_duration_days` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_workflow_request_type` (`request_type_id`),
    INDEX `idx_workflow_order` (`stage_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. request_stage_tracking (from public/track-request-advanced.php)
CREATE TABLE IF NOT EXISTS `request_stage_tracking` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `request_id` INT NOT NULL,
    `stage_id` INT NOT NULL,
    `status` VARCHAR(50) DEFAULT 'pending',
    `notes` TEXT,
    `rejection_reason` TEXT,
    `assigned_to` INT DEFAULT NULL,
    `started_at` TIMESTAMP NULL DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`stage_id`) REFERENCES `request_workflow_stages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_tracking_request` (`request_id`),
    INDEX `idx_tracking_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
