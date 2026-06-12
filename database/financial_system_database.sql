-- ========================================
-- النظام المالي المتكامل لبلدية تكريت - عكار
-- Financial System Database Schema
-- ========================================

-- استخدام قاعدة البيانات
USE tekrit_municipality;

-- ========================================
-- 1. الجداول الجديدة
-- ========================================

-- جدول الموردون (Suppliers)
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `supplier_code` VARCHAR(50) UNIQUE NOT NULL COMMENT 'رمز المورد',
  `name` VARCHAR(255) NOT NULL COMMENT 'اسم المورد',
  `contact_person` VARCHAR(255) DEFAULT NULL COMMENT 'الشخص المسؤول',
  `phone` VARCHAR(50) DEFAULT NULL COMMENT 'رقم الهاتف',
  `mobile` VARCHAR(50) DEFAULT NULL COMMENT 'رقم الموبايل',
  `email` VARCHAR(100) DEFAULT NULL COMMENT 'البريد الإلكتروني',
  `address` TEXT DEFAULT NULL COMMENT 'العنوان',
  `service_type` VARCHAR(255) NOT NULL COMMENT 'نوع الخدمة أو المواد',
  `tax_number` VARCHAR(100) DEFAULT NULL COMMENT 'الرقم الضريبي',
  `commercial_registration` VARCHAR(100) DEFAULT NULL COMMENT 'السجل التجاري',
  `payment_terms` VARCHAR(255) DEFAULT NULL COMMENT 'شروط الدفع',
  `bank_account` VARCHAR(100) DEFAULT NULL COMMENT 'رقم الحساب البنكي',
  `bank_name` VARCHAR(255) DEFAULT NULL COMMENT 'اسم البنك',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT '1=نشط، 0=غير نشط',
  `notes` TEXT DEFAULT NULL COMMENT 'ملاحظات',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_supplier_code` (`supplier_code`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول فواتير الموردين (Supplier Invoices)
CREATE TABLE IF NOT EXISTS `supplier_invoices` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` VARCHAR(100) NOT NULL COMMENT 'رقم الفاتورة',
  `supplier_id` INT(11) NOT NULL,
  `invoice_date` DATE NOT NULL COMMENT 'تاريخ الفاتورة',
  `due_date` DATE NOT NULL COMMENT 'تاريخ الاستحقاق',
  `total_amount` DECIMAL(15,2) NOT NULL COMMENT 'المبلغ الإجمالي',
  `currency_id` INT(11) NOT NULL COMMENT 'العملة',
  `exchange_rate` DECIMAL(10,4) DEFAULT 1.0000 COMMENT 'سعر الصرف',
  `status` ENUM('غير مدفوع', 'مدفوع جزئياً', 'مدفوع بالكامل', 'متأخر', 'ملغي') DEFAULT 'غير مدفوع',
  `paid_amount` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'المبلغ المدفوع',
  `remaining_amount` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'المبلغ المتبقي',
  `description` TEXT DEFAULT NULL COMMENT 'وصف الفاتورة',
  `file_path` VARCHAR(500) DEFAULT NULL COMMENT 'مسار ملف الفاتورة',
  `payment_date` DATE DEFAULT NULL COMMENT 'تاريخ آخر دفعة',
  `related_project_id` INT(11) DEFAULT NULL COMMENT 'المشروع المرتبط',
  `budget_item_id` INT(11) DEFAULT NULL COMMENT 'بند الميزانية',
  `created_by` INT(11) DEFAULT NULL,
  `approved_by` INT(11) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`currency_id`) REFERENCES `currencies`(`id`) ON DELETE RESTRICT,
  KEY `idx_invoice_number` (`invoice_number`),
  KEY `idx_status` (`status`),
  KEY `idx_invoice_date` (`invoice_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول دفعات فواتير الموردين (Invoice Payments)
CREATE TABLE IF NOT EXISTS `invoice_payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` INT(11) NOT NULL,
  `payment_date` DATE NOT NULL,
  `payment_amount` DECIMAL(15,2) NOT NULL,
  `payment_method` ENUM('نقد','شيك','تحويل مصرفي','بطاقة ائتمان','أخرى') DEFAULT 'نقد',
  `reference_number` VARCHAR(100) DEFAULT NULL COMMENT 'رقم المرجع/الشيك',
  `bank_name` VARCHAR(255) DEFAULT NULL,
  `financial_transaction_id` INT(11) DEFAULT NULL COMMENT 'ربط مع المعاملة المالية',
  `notes` TEXT DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`invoice_id`) REFERENCES `supplier_invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول الميزانيات (Budgets)
CREATE TABLE IF NOT EXISTS `budgets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `budget_code` VARCHAR(50) UNIQUE NOT NULL COMMENT 'رمز الميزانية',
  `name` VARCHAR(255) NOT NULL COMMENT 'اسم الميزانية',
  `fiscal_year` INT(4) NOT NULL COMMENT 'السنة المالية',
  `start_date` DATE NOT NULL COMMENT 'تاريخ البداية',
  `end_date` DATE NOT NULL COMMENT 'تاريخ النهاية',
  `total_amount` DECIMAL(20,2) NOT NULL COMMENT 'المبلغ الإجمالي',
  `currency_id` INT(11) NOT NULL DEFAULT 1 COMMENT 'العملة الأساسية',
  `status` ENUM('مسودة', 'معتمد', 'مغلق', 'ملغي') DEFAULT 'مسودة',
  `description` TEXT DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `approved_by` INT(11) DEFAULT NULL,
  `approved_date` DATE DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`currency_id`) REFERENCES `currencies`(`id`) ON DELETE RESTRICT,
  KEY `idx_fiscal_year` (`fiscal_year`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول بنود الميزانية (Budget Items)
CREATE TABLE IF NOT EXISTS `budget_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `budget_id` INT(11) NOT NULL,
  `item_code` VARCHAR(50) NOT NULL COMMENT 'رمز البند',
  `name` VARCHAR(255) NOT NULL COMMENT 'اسم البند',
  `description` TEXT DEFAULT NULL COMMENT 'وصف البند',
  `item_type` ENUM('إيراد', 'مصروف') DEFAULT 'مصروف',
  `category` VARCHAR(100) DEFAULT NULL COMMENT 'التصنيف: رواتب، صيانة، مشاريع...',
  `allocated_amount` DECIMAL(15,2) NOT NULL COMMENT 'المبلغ المخصص',
  `spent_amount` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'المبلغ المصروف',
  `remaining_amount` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'المبلغ المتبقي',
  `percentage_used` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'نسبة الاستخدام',
  `parent_item_id` INT(11) DEFAULT NULL COMMENT 'البند الرئيسي (للبنود الفرعية)',
  `related_committee_id` INT(11) DEFAULT NULL COMMENT 'اللجنة المرتبطة',
  `related_project_id` INT(11) DEFAULT NULL COMMENT 'المشروع المرتبط',
  `is_active` TINYINT(1) DEFAULT 1,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`budget_id`) REFERENCES `budgets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`parent_item_id`) REFERENCES `budget_items`(`id`) ON DELETE SET NULL,
  KEY `idx_item_code` (`item_code`),
  KEY `idx_item_type` (`item_type`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول الجمعيات/المقاولون (Associations/Contractors)
CREATE TABLE IF NOT EXISTS `associations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `association_code` VARCHAR(50) UNIQUE NOT NULL COMMENT 'رمز الجمعية',
  `name` VARCHAR(255) NOT NULL COMMENT 'اسم الجمعية/المقاول',
  `type` ENUM('جمعية', 'مقاول', 'شركة', 'أخرى') DEFAULT 'جمعية',
  `registration_number` VARCHAR(100) DEFAULT NULL COMMENT 'رقم التسجيل',
  `registration_date` DATE DEFAULT NULL COMMENT 'تاريخ التسجيل',
  `contact_person` VARCHAR(255) DEFAULT NULL COMMENT 'الشخص المسؤول',
  `phone` VARCHAR(50) DEFAULT NULL,
  `mobile` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `specialization` VARCHAR(255) DEFAULT NULL COMMENT 'التخصص',
  `is_active` TINYINT(1) DEFAULT 1,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_association_code` (`association_code`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 2. تعديلات على الجداول الحالية
-- ========================================

-- تعديل جدول financial_transactions
ALTER TABLE `financial_transactions`
  ADD COLUMN IF NOT EXISTS `budget_item_id` INT(11) DEFAULT NULL COMMENT 'بند الميزانية' AFTER `related_donation_id`,
  ADD COLUMN IF NOT EXISTS `supplier_invoice_id` INT(11) DEFAULT NULL COMMENT 'فاتورة المورد' AFTER `budget_item_id`,
  ADD COLUMN IF NOT EXISTS `tax_collection_id` INT(11) DEFAULT NULL COMMENT 'الجباية الضريبية' AFTER `supplier_invoice_id`,
  ADD COLUMN IF NOT EXISTS `association_id` INT(11) DEFAULT NULL COMMENT 'الجمعية/المقاول' AFTER `tax_collection_id`,
  ADD COLUMN IF NOT EXISTS `is_approved` TINYINT(1) DEFAULT 0 COMMENT 'معتمد؟' AFTER `status`,
  ADD COLUMN IF NOT EXISTS `approved_date` DATE DEFAULT NULL AFTER `is_approved`;

-- تعديل جدول projects
ALTER TABLE `projects`
  ADD COLUMN IF NOT EXISTS `association_id` INT(11) DEFAULT NULL COMMENT 'الجمعية المنفذة' AFTER `status`,
  ADD COLUMN IF NOT EXISTS `total_budget` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'الميزانية الإجمالية' AFTER `association_id`,
  ADD COLUMN IF NOT EXISTS `spent_amount` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'المبلغ المصروف' AFTER `total_budget`,
  ADD COLUMN IF NOT EXISTS `remaining_budget` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'الميزانية المتبقية' AFTER `spent_amount`,
  ADD COLUMN IF NOT EXISTS `budget_item_id` INT(11) DEFAULT NULL COMMENT 'بند الميزانية المرتبط' AFTER `remaining_budget`,
  ADD COLUMN IF NOT EXISTS `contract_number` VARCHAR(100) DEFAULT NULL COMMENT 'رقم العقد' AFTER `budget_item_id`,
  ADD COLUMN IF NOT EXISTS `contract_date` DATE DEFAULT NULL COMMENT 'تاريخ العقد' AFTER `contract_number`;

-- تعديل جدول donations
ALTER TABLE `donations`
  ADD COLUMN IF NOT EXISTS `source_type` ENUM('دعم حكومي', 'مساهمة مجتمعية', 'هبة خارجية', 'أخرى') DEFAULT 'أخرى' COMMENT 'نوع المصدر' AFTER `donor_type`,
  ADD COLUMN IF NOT EXISTS `budget_item_id` INT(11) DEFAULT NULL COMMENT 'بند الميزانية (إيراد)' AFTER `source_type`;

-- ========================================
-- 3. جداول إضافية للتقارير والإحصائيات
-- ========================================

-- جدول الفترات المالية (Fiscal Periods) - لتسهيل التقارير
CREATE TABLE IF NOT EXISTS `fiscal_periods` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `period_name` VARCHAR(100) NOT NULL COMMENT 'اسم الفترة: Q1 2025، يناير 2025...',
  `period_type` ENUM('يومي', 'أسبوعي', 'شهري', 'ربع سنوي', 'سنوي') DEFAULT 'شهري',
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `fiscal_year` INT(4) NOT NULL,
  `is_closed` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fiscal_year` (`fiscal_year`),
  KEY `idx_dates` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 4. الفهارس والمفاتيح الإضافية
-- ========================================

-- إضافة فهارس لتحسين الأداء
ALTER TABLE `financial_transactions` 
  ADD KEY IF NOT EXISTS `idx_budget_item` (`budget_item_id`),
  ADD KEY IF NOT EXISTS `idx_supplier_invoice` (`supplier_invoice_id`),
  ADD KEY IF NOT EXISTS `idx_tax_collection` (`tax_collection_id`),
  ADD KEY IF NOT EXISTS `idx_association` (`association_id`);

ALTER TABLE `projects`
  ADD KEY IF NOT EXISTS `idx_association` (`association_id`),
  ADD KEY IF NOT EXISTS `idx_budget_item` (`budget_item_id`);

-- ========================================
-- 5. بيانات أولية (Initial Data)
-- ========================================

-- إدراج فترات مالية افتراضية لعام 2025
INSERT IGNORE INTO `fiscal_periods` (`period_name`, `period_type`, `start_date`, `end_date`, `fiscal_year`) VALUES
('الربع الأول 2025', 'ربع سنوي', '2025-01-01', '2025-03-31', 2025),
('الربع الثاني 2025', 'ربع سنوي', '2025-04-01', '2025-06-30', 2025),
('الربع الثالث 2025', 'ربع سنوي', '2025-07-01', '2025-09-30', 2025),
('الربع الرابع 2025', 'ربع سنوي', '2025-10-01', '2025-12-31', 2025);

-- ========================================
-- انتهى ملف قاعدة البيانات
-- ========================================

