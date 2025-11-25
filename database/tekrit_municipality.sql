-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 25, 2025 at 02:10 PM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 8.0.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tekrit_municipality`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_add_contribution` (IN `p_project_id` INT, IN `p_contributor_name` VARCHAR(255), IN `p_contributor_phone` VARCHAR(50), IN `p_contributor_email` VARCHAR(100), IN `p_contribution_amount` DECIMAL(15,2), IN `p_currency_id` INT, IN `p_contribution_date` DATE, IN `p_payment_method` VARCHAR(50), IN `p_reference_number` VARCHAR(100), IN `p_is_anonymous` TINYINT(1), IN `p_created_by` INT, OUT `p_contribution_id` INT, OUT `p_message` VARCHAR(500))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_contribution_id = 0;
        SET p_message = 'خطأ في إضافة المساهمة';
    END;
    
    START TRANSACTION;
    
    -- التحقق من أن المشروع يقبل مساهمات
    IF NOT EXISTS (SELECT 1 FROM `projects` WHERE `id` = p_project_id AND `allow_public_contributions` = 1) THEN
        SET p_contribution_id = 0;
        SET p_message = 'هذا المشروع لا يقبل مساهمات شعبية';
        ROLLBACK;
    ELSE
        -- إضافة المساهمة
        INSERT INTO `project_contributions`
        (
            `project_id`, `contributor_name`, `contributor_phone`, `contributor_email`,
            `contribution_amount`, `currency_id`, `contribution_date`, `payment_method`,
            `reference_number`, `is_anonymous`, `is_verified`, `verified_by`,
            `verified_date`, `created_by`
        )
        VALUES
        (
            p_project_id, p_contributor_name, p_contributor_phone, p_contributor_email,
            p_contribution_amount, p_currency_id, p_contribution_date, p_payment_method,
            p_reference_number, p_is_anonymous, 1, p_created_by,
            NOW(), p_created_by
        );
        
        SET p_contribution_id = LAST_INSERT_ID();
        SET p_message = 'تم إضافة المساهمة بنجاح';
        
        COMMIT;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cleanup_expired_links` ()   BEGIN
    DECLARE deleted_links INT;
    DECLARE deleted_sessions INT;
    DELETE FROM magic_links WHERE expires_at < NOW() AND used = 0;
    SET deleted_links = ROW_COUNT();
    DELETE FROM citizen_sessions WHERE expires_at < NOW();
    SET deleted_sessions = ROW_COUNT();
    SELECT deleted_links as deleted_magic_links, deleted_sessions as deleted_sessions, NOW() as cleanup_time;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_create_magic_link` (IN `p_citizen_id` INT, IN `p_phone` VARCHAR(20), IN `p_validity_hours` INT)   BEGIN
    DECLARE v_token VARCHAR(64);
    DECLARE v_expires_at DATETIME;
    SET v_token = SHA2(CONCAT(p_phone, NOW(), RAND()), 256);
    SET v_expires_at = DATE_ADD(NOW(), INTERVAL p_validity_hours HOUR);
    INSERT INTO magic_links (citizen_id, token, phone, expires_at, used) 
    VALUES (p_citizen_id, v_token, p_phone, v_expires_at, 0);
    SELECT v_token as token, v_expires_at as expires_at;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_citizen_stats` (IN `p_citizen_id` INT)   BEGIN
    DECLARE v_phone VARCHAR(20);
    SELECT phone INTO v_phone FROM citizens_accounts WHERE id = p_citizen_id;
    SELECT 
        ca.id, ca.phone, ca.name, ca.email, ca.created_at, ca.last_login, ca.login_count,
        COUNT(DISTINCT cr.id) as total_requests,
        SUM(CASE WHEN cr.status = 'جديد' THEN 1 ELSE 0 END) as new_requests,
        SUM(CASE WHEN cr.status = 'قيد المراجعة' THEN 1 ELSE 0 END) as under_review,
        SUM(CASE WHEN cr.status = 'قيد التنفيذ' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN cr.status = 'مكتمل' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN cr.status = 'مرفوض' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN cr.status = 'ملغي' THEN 1 ELSE 0 END) as cancelled,
        COUNT(DISTINCT cm.id) as total_messages,
        SUM(CASE WHEN cm.is_read = 0 THEN 1 ELSE 0 END) as unread_messages,
        SUM(CASE WHEN cm.priority = 'عاجل' THEN 1 ELSE 0 END) as urgent_messages,
        COUNT(DISTINCT wl.id) as total_whatsapp_messages,
        SUM(CASE WHEN wl.status = 'sent' THEN 1 ELSE 0 END) as sent_whatsapp,
        SUM(CASE WHEN wl.status = 'delivered' THEN 1 ELSE 0 END) as delivered_whatsapp,
        SUM(CASE WHEN wl.status = 'failed' THEN 1 ELSE 0 END) as failed_whatsapp,
        MAX(cr.created_at) as last_request_date,
        MAX(cm.created_at) as last_message_date
    FROM citizens_accounts ca
    LEFT JOIN citizen_requests cr ON ca.phone = cr.citizen_phone
    LEFT JOIN citizen_messages cm ON ca.id = cm.citizen_id
    LEFT JOIN whatsapp_log wl ON ca.id = wl.citizen_id
    WHERE ca.id = p_citizen_id
    GROUP BY ca.id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_or_create_citizen_account` (IN `p_phone` VARCHAR(20), IN `p_name` VARCHAR(255), IN `p_email` VARCHAR(255), IN `p_national_id` VARCHAR(50), IN `p_telegram_chat_id` VARCHAR(100), IN `p_telegram_username` VARCHAR(100))   BEGIN
    DECLARE v_citizen_id INT;
    DECLARE v_access_code VARCHAR(20);
    
    SELECT id INTO v_citizen_id 
    FROM citizens_accounts 
    WHERE phone = p_phone 
    LIMIT 1;
    
    IF v_citizen_id IS NULL THEN
        SET v_access_code = CONCAT('TKT-', UPPER(SUBSTRING(MD5(CONCAT(p_phone, NOW(), RAND())), 1, 8)));
        
        INSERT INTO citizens_accounts (
            phone, name, email, national_id, 
            telegram_chat_id, telegram_username, 
            permanent_access_code, created_at
        ) VALUES (
            p_phone, p_name, p_email, p_national_id,
            p_telegram_chat_id, p_telegram_username,
            v_access_code, NOW()
        );
        
        SET v_citizen_id = LAST_INSERT_ID();
    ELSE
        UPDATE citizens_accounts 
        SET 
            name = COALESCE(p_name, name),
            email = COALESCE(p_email, email),
            national_id = COALESCE(p_national_id, national_id),
            telegram_chat_id = COALESCE(p_telegram_chat_id, telegram_chat_id),
            telegram_username = COALESCE(p_telegram_username, telegram_username),
            updated_at = NOW()
        WHERE id = v_citizen_id;
        
        SELECT permanent_access_code INTO v_access_code
        FROM citizens_accounts
        WHERE id = v_citizen_id;
        
        IF v_access_code IS NULL THEN
            SET v_access_code = CONCAT('TKT-', UPPER(SUBSTRING(MD5(CONCAT(p_phone, NOW(), RAND())), 1, 8)));
            UPDATE citizens_accounts 
            SET permanent_access_code = v_access_code
            WHERE id = v_citizen_id;
        END IF;
    END IF;
    
    SELECT v_citizen_id as citizen_id, v_access_code as access_code;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_validate_magic_link` (IN `p_token` VARCHAR(64), IN `p_ip_address` VARCHAR(45), IN `p_user_agent` TEXT)   BEGIN
    DECLARE v_citizen_id INT;
    DECLARE v_is_valid BOOLEAN DEFAULT FALSE;
    SELECT citizen_id, (used = 0 AND expires_at > NOW()) as is_valid
    INTO v_citizen_id, v_is_valid
    FROM magic_links
    WHERE token = p_token
    LIMIT 1;
    IF v_is_valid THEN
        UPDATE magic_links
        SET used = 1, used_at = NOW(), ip_address = p_ip_address, user_agent = p_user_agent
        WHERE token = p_token;
        INSERT INTO citizen_sessions (citizen_id, session_token, ip_address, user_agent, expires_at) 
        VALUES (v_citizen_id, SHA2(CONCAT(v_citizen_id, NOW(), RAND()), 256), p_ip_address, p_user_agent, DATE_ADD(NOW(), INTERVAL 7 DAY));
        UPDATE citizens_accounts
        SET last_login = NOW(), login_count = login_count + 1
        WHERE id = v_citizen_id;
        SELECT ca.*, cs.session_token, cs.expires_at as session_expires_at
        FROM citizens_accounts ca
        JOIN citizen_sessions cs ON ca.id = cs.citizen_id
        WHERE ca.id = v_citizen_id
        ORDER BY cs.created_at DESC
        LIMIT 1;
    ELSE
        SELECT NULL as id, 'invalid_link' as error;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdateComplaintStatus` (IN `p_complaint_id` INT, IN `p_new_status` VARCHAR(50), IN `p_user_id` INT, IN `p_comment` TEXT, IN `p_visible_to_citizen` BOOLEAN)   BEGIN
    DECLARE v_old_status VARCHAR(50);
    
    -- الحصول على الحالة القديمة
    SELECT status INTO v_old_status FROM complaints WHERE id = p_complaint_id;
    
    -- تحديث حالة الشكوى
    UPDATE complaints 
    SET status = p_new_status, 
        updated_at = CURRENT_TIMESTAMP,
        assigned_to = IFNULL(p_user_id, assigned_to)
    WHERE id = p_complaint_id;
    
    -- إضافة تحديث
    IF p_comment IS NOT NULL AND p_comment != '' THEN
        INSERT INTO complaint_updates 
        (complaint_id, updated_by, update_type, update_text, is_visible_to_citizen)
        VALUES 
        (p_complaint_id, p_user_id, 
         IF(v_old_status != p_new_status, 'status_change', 'comment'),
         p_comment, 
         IFNULL(p_visible_to_citizen, 1));
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appreciation_certificates`
--

CREATE TABLE `appreciation_certificates` (
  `id` int(11) NOT NULL,
  `certificate_number` varchar(50) DEFAULT NULL,
  `donor_id` int(11) NOT NULL,
  `donation_id` int(11) DEFAULT NULL,
  `certificate_type` enum('شهادة شكر','شهادة تقدير','درع تكريم','خطاب شكر') DEFAULT 'شهادة شكر',
  `title` varchar(500) NOT NULL,
  `content` text NOT NULL,
  `issued_date` date NOT NULL,
  `issued_by_user_id` int(11) DEFAULT NULL,
  `delivered` tinyint(1) DEFAULT 0,
  `delivery_date` date DEFAULT NULL,
  `delivery_method` enum('يدوياً','بريد','إيميل','أخرى') DEFAULT NULL,
  `template_used` varchar(100) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `associations`
--

CREATE TABLE `associations` (
  `id` int(11) NOT NULL,
  `association_code` varchar(50) NOT NULL COMMENT 'رمز الجمعية',
  `name` varchar(255) NOT NULL COMMENT 'اسم الجمعية/المقاول',
  `type` enum('جمعية','مقاول','شركة','أخرى') DEFAULT 'جمعية',
  `registration_number` varchar(100) DEFAULT NULL COMMENT 'رقم التسجيل',
  `registration_date` date DEFAULT NULL COMMENT 'تاريخ التسجيل',
  `contact_person` varchar(255) DEFAULT NULL COMMENT 'الشخص المسؤول',
  `phone` varchar(50) DEFAULT NULL,
  `mobile` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `specialization` varchar(255) DEFAULT NULL COMMENT 'التخصص',
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `budgets`
--

CREATE TABLE `budgets` (
  `id` int(11) NOT NULL,
  `budget_code` varchar(50) NOT NULL COMMENT 'رمز الميزانية',
  `name` varchar(255) NOT NULL COMMENT 'اسم الميزانية',
  `fiscal_year` int(4) NOT NULL COMMENT 'السنة المالية',
  `start_date` date NOT NULL COMMENT 'تاريخ البداية',
  `end_date` date NOT NULL COMMENT 'تاريخ النهاية',
  `total_amount` decimal(20,2) NOT NULL COMMENT 'المبلغ الإجمالي',
  `currency_id` int(11) NOT NULL DEFAULT 1 COMMENT 'العملة الأساسية',
  `committee_id` int(11) DEFAULT NULL COMMENT 'اللجنة المرتبطة بالميزانية',
  `status` enum('مسودة','معتمد','مغلق','ملغي') DEFAULT 'مسودة',
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `budgets`
--

INSERT INTO `budgets` (`id`, `budget_code`, `name`, `fiscal_year`, `start_date`, `end_date`, `total_amount`, `currency_id`, `committee_id`, `status`, `description`, `created_by`, `approved_by`, `approved_date`, `created_at`, `updated_at`) VALUES
(5, 'BUD-AI-2025-1764030430', 'ميزانية لجنة الشباب والرياضة - 2026', 2026, '2026-01-01', '2026-12-31', '0.00', 1, NULL, 'مسودة', 'ميزانية تم إنشاؤها تلقائياً باستخدام الذكاء الاصطناعي\nإجمالي الإيرادات: 0.00\nإجمالي المصاريف: 0.00\nالرصيد: 0.00', 1, NULL, NULL, '2025-11-25 00:27:10', '2025-11-25 00:27:10'),
(6, 'BUD-3-2026', 'ميزانية لجنة البيئة والنظافة - 2026', 2026, '2026-01-01', '2026-12-31', '88000.00', 2, 3, 'مسودة', 'ميزانية تم إنشاؤها تلقائياً للجنة لجنة البيئة والنظافة', 1, NULL, NULL, '2025-11-25 00:27:52', '2025-11-25 00:27:52');

-- --------------------------------------------------------

--
-- Table structure for table `budget_items`
--

CREATE TABLE `budget_items` (
  `id` int(11) NOT NULL,
  `budget_id` int(11) NOT NULL,
  `item_code` varchar(50) NOT NULL COMMENT 'رمز البند',
  `name` varchar(255) NOT NULL COMMENT 'اسم البند',
  `description` text DEFAULT NULL COMMENT 'وصف البند',
  `item_type` enum('إيراد','مصروف') DEFAULT 'مصروف',
  `category` varchar(100) DEFAULT NULL COMMENT 'التصنيف: رواتب، صيانة، مشاريع...',
  `allocated_amount` decimal(15,2) NOT NULL COMMENT 'المبلغ المخصص',
  `currency_id` int(11) DEFAULT 1 COMMENT 'عملة البند',
  `spent_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'المبلغ المصروف',
  `remaining_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'المبلغ المتبقي',
  `percentage_used` decimal(5,2) DEFAULT 0.00 COMMENT 'نسبة الاستخدام',
  `parent_item_id` int(11) DEFAULT NULL COMMENT 'البند الرئيسي (للبنود الفرعية)',
  `related_committee_id` int(11) DEFAULT NULL COMMENT 'اللجنة المرتبطة',
  `related_project_id` int(11) DEFAULT NULL COMMENT 'المشروع المرتبط',
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `budget_items`
--

INSERT INTO `budget_items` (`id`, `budget_id`, `item_code`, `name`, `description`, `item_type`, `category`, `allocated_amount`, `currency_id`, `spent_amount`, `remaining_amount`, `percentage_used`, `parent_item_id`, `related_committee_id`, `related_project_id`, `is_active`, `notes`, `created_at`, `updated_at`) VALUES
(16, 6, 'ITEM-3-001', 'رواتب وأجور - لجنة البيئة والنظافة', 'رواتب الموظفين والعمال التابعين للجنة', 'مصروف', 'رواتب', '50000.00', 2, '0.00', '50000.00', '0.00', NULL, NULL, NULL, 1, NULL, '2025-11-25 00:27:52', '2025-11-25 00:27:52'),
(17, 6, 'ITEM-3-002', 'مستلزمات وقرطاسية - لجنة البيئة والنظافة', 'مواد مكتبية ومستلزمات عمل اللجنة', 'مصروف', 'تشغيلية', '5000.00', 2, '0.00', '5000.00', '0.00', NULL, NULL, NULL, 1, NULL, '2025-11-25 00:27:52', '2025-11-25 00:27:52'),
(18, 6, 'ITEM-3-003', 'صيانة وخدمات - لجنة البيئة والنظافة', 'صيانة المعدات والأصول التابعة للجنة', 'مصروف', 'صيانة', '10000.00', 2, '0.00', '10000.00', '0.00', NULL, NULL, NULL, 1, NULL, '2025-11-25 00:27:52', '2025-11-25 00:27:52'),
(19, 6, 'ITEM-3-004', 'مشتريات ومعدات - لجنة البيئة والنظافة', 'شراء معدات وأدوات عمل جديدة', 'مصروف', 'مشتريات', '15000.00', 2, '0.00', '15000.00', '0.00', NULL, NULL, NULL, 1, NULL, '2025-11-25 00:27:52', '2025-11-25 00:27:52'),
(20, 6, 'ITEM-3-005', 'خدمات استشارية - لجنة البيئة والنظافة', 'خدمات استشارية وتدريب', 'مصروف', 'خدمات', '8000.00', 2, '0.00', '8000.00', '0.00', NULL, NULL, NULL, 1, NULL, '2025-11-25 00:27:52', '2025-11-25 00:27:52');

-- --------------------------------------------------------

--
-- Table structure for table `budget_item_templates`
--

CREATE TABLE `budget_item_templates` (
  `id` int(11) NOT NULL,
  `committee_id` int(11) NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `item_type` enum('إيراد','مصروف') DEFAULT 'مصروف',
  `category` varchar(100) DEFAULT NULL,
  `default_amount` decimal(15,2) DEFAULT 0.00,
  `currency_id` int(11) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='قوالب بنود الميزانية للجان';

--
-- Dumping data for table `budget_item_templates`
--

INSERT INTO `budget_item_templates` (`id`, `committee_id`, `item_code`, `name`, `description`, `item_type`, `category`, `default_amount`, `currency_id`, `is_active`, `created_at`) VALUES
(1, 1, 'ITEM-1-001', 'رواتب وأجور - لجنة الشؤون المالية', 'رواتب الموظفين والعمال التابعين للجنة', 'مصروف', 'رواتب', '50000.00', 1, 1, '2025-11-03 18:18:20'),
(2, 2, 'ITEM-2-001', 'رواتب وأجور - لجنة التخطيط والتطوير', 'رواتب الموظفين والعمال التابعين للجنة', 'مصروف', 'رواتب', '50000.00', 1, 1, '2025-11-03 18:18:20'),
(3, 3, 'ITEM-3-001', 'رواتب وأجور - لجنة البيئة والنظافة', 'رواتب الموظفين والعمال التابعين للجنة', 'مصروف', 'رواتب', '50000.00', 1, 1, '2025-11-03 18:18:20'),
(4, 4, 'ITEM-4-001', 'رواتب وأجور - لجنة الخدمات العامة', 'رواتب الموظفين والعمال التابعين للجنة', 'مصروف', 'رواتب', '50000.00', 1, 1, '2025-11-03 18:18:20'),
(5, 5, 'ITEM-5-001', 'رواتب وأجور - لجنة الطوارئ', 'رواتب الموظفين والعمال التابعين للجنة', 'مصروف', 'رواتب', '50000.00', 1, 1, '2025-11-03 18:18:20'),
(8, 1, 'ITEM-1-002', 'مستلزمات وقرطاسية - لجنة الشؤون المالية', 'مواد مكتبية ومستلزمات عمل اللجنة', 'مصروف', 'تشغيلية', '5000.00', 1, 1, '2025-11-03 18:18:20'),
(9, 2, 'ITEM-2-002', 'مستلزمات وقرطاسية - لجنة التخطيط والتطوير', 'مواد مكتبية ومستلزمات عمل اللجنة', 'مصروف', 'تشغيلية', '5000.00', 1, 1, '2025-11-03 18:18:20'),
(10, 3, 'ITEM-3-002', 'مستلزمات وقرطاسية - لجنة البيئة والنظافة', 'مواد مكتبية ومستلزمات عمل اللجنة', 'مصروف', 'تشغيلية', '5000.00', 1, 1, '2025-11-03 18:18:20'),
(11, 4, 'ITEM-4-002', 'مستلزمات وقرطاسية - لجنة الخدمات العامة', 'مواد مكتبية ومستلزمات عمل اللجنة', 'مصروف', 'تشغيلية', '5000.00', 1, 1, '2025-11-03 18:18:20'),
(12, 5, 'ITEM-5-002', 'مستلزمات وقرطاسية - لجنة الطوارئ', 'مواد مكتبية ومستلزمات عمل اللجنة', 'مصروف', 'تشغيلية', '5000.00', 1, 1, '2025-11-03 18:18:20'),
(15, 1, 'ITEM-1-003', 'صيانة وخدمات - لجنة الشؤون المالية', 'صيانة المعدات والأصول التابعة للجنة', 'مصروف', 'صيانة', '10000.00', 1, 1, '2025-11-03 18:18:20'),
(16, 2, 'ITEM-2-003', 'صيانة وخدمات - لجنة التخطيط والتطوير', 'صيانة المعدات والأصول التابعة للجنة', 'مصروف', 'صيانة', '10000.00', 1, 1, '2025-11-03 18:18:20'),
(17, 3, 'ITEM-3-003', 'صيانة وخدمات - لجنة البيئة والنظافة', 'صيانة المعدات والأصول التابعة للجنة', 'مصروف', 'صيانة', '10000.00', 1, 1, '2025-11-03 18:18:20'),
(18, 4, 'ITEM-4-003', 'صيانة وخدمات - لجنة الخدمات العامة', 'صيانة المعدات والأصول التابعة للجنة', 'مصروف', 'صيانة', '10000.00', 1, 1, '2025-11-03 18:18:20'),
(19, 5, 'ITEM-5-003', 'صيانة وخدمات - لجنة الطوارئ', 'صيانة المعدات والأصول التابعة للجنة', 'مصروف', 'صيانة', '10000.00', 1, 1, '2025-11-03 18:18:20'),
(22, 1, 'ITEM-1-004', 'مشتريات ومعدات - لجنة الشؤون المالية', 'شراء معدات وأدوات عمل جديدة', 'مصروف', 'مشتريات', '15000.00', 1, 1, '2025-11-03 18:18:20'),
(23, 2, 'ITEM-2-004', 'مشتريات ومعدات - لجنة التخطيط والتطوير', 'شراء معدات وأدوات عمل جديدة', 'مصروف', 'مشتريات', '15000.00', 1, 1, '2025-11-03 18:18:20'),
(24, 3, 'ITEM-3-004', 'مشتريات ومعدات - لجنة البيئة والنظافة', 'شراء معدات وأدوات عمل جديدة', 'مصروف', 'مشتريات', '15000.00', 1, 1, '2025-11-03 18:18:20'),
(25, 4, 'ITEM-4-004', 'مشتريات ومعدات - لجنة الخدمات العامة', 'شراء معدات وأدوات عمل جديدة', 'مصروف', 'مشتريات', '15000.00', 1, 1, '2025-11-03 18:18:20'),
(26, 5, 'ITEM-5-004', 'مشتريات ومعدات - لجنة الطوارئ', 'شراء معدات وأدوات عمل جديدة', 'مصروف', 'مشتريات', '15000.00', 1, 1, '2025-11-03 18:18:20'),
(29, 1, 'ITEM-1-005', 'خدمات استشارية - لجنة الشؤون المالية', 'خدمات استشارية وتدريب', 'مصروف', 'خدمات', '8000.00', 1, 1, '2025-11-03 18:18:20'),
(30, 2, 'ITEM-2-005', 'خدمات استشارية - لجنة التخطيط والتطوير', 'خدمات استشارية وتدريب', 'مصروف', 'خدمات', '8000.00', 1, 1, '2025-11-03 18:18:20'),
(31, 3, 'ITEM-3-005', 'خدمات استشارية - لجنة البيئة والنظافة', 'خدمات استشارية وتدريب', 'مصروف', 'خدمات', '8000.00', 1, 1, '2025-11-03 18:18:20'),
(32, 4, 'ITEM-4-005', 'خدمات استشارية - لجنة الخدمات العامة', 'خدمات استشارية وتدريب', 'مصروف', 'خدمات', '8000.00', 1, 1, '2025-11-03 18:18:20'),
(33, 5, 'ITEM-5-005', 'خدمات استشارية - لجنة الطوارئ', 'خدمات استشارية وتدريب', 'مصروف', 'خدمات', '8000.00', 1, 1, '2025-11-03 18:18:20');

-- --------------------------------------------------------

--
-- Table structure for table `building_permits`
--

CREATE TABLE `building_permits` (
  `id` int(11) NOT NULL,
  `permit_number` varchar(50) NOT NULL,
  `applicant_name` varchar(200) NOT NULL,
  `applicant_phone` varchar(20) DEFAULT NULL,
  `applicant_email` varchar(100) DEFAULT NULL,
  `applicant_address` text DEFAULT NULL,
  `citizen_id` int(11) DEFAULT NULL,
  `property_location` text NOT NULL,
  `property_area` decimal(10,2) DEFAULT NULL,
  `building_type` enum('سكني','تجاري','صناعي','خدمي','مختلط') DEFAULT 'سكني',
  `construction_type` enum('جديد','توسع','تجديد','هدم وإعادة بناء') DEFAULT 'جديد',
  `floors_count` int(11) DEFAULT 1,
  `estimated_cost` decimal(12,2) DEFAULT NULL,
  `currency_id` int(11) DEFAULT 1,
  `contractor_name` varchar(200) DEFAULT NULL,
  `contractor_license` varchar(100) DEFAULT NULL,
  `engineer_name` varchar(200) DEFAULT NULL,
  `engineer_license` varchar(100) DEFAULT NULL,
  `architectural_plans_path` varchar(500) DEFAULT NULL,
  `structural_plans_path` varchar(500) DEFAULT NULL,
  `application_date` date DEFAULT curdate(),
  `review_date` date DEFAULT NULL,
  `approval_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `permit_fees` decimal(10,2) DEFAULT NULL,
  `status` enum('مقدم','قيد المراجعة','يحتاج تعديل','موافق عليه','مرفوض','منتهي الصلاحية') DEFAULT 'مقدم',
  `review_notes` text DEFAULT NULL,
  `approval_conditions` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `issued_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `campaign_donations`
--

CREATE TABLE `campaign_donations` (
  `id` int(11) NOT NULL,
  `campaign_id` int(11) NOT NULL,
  `donation_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `citizens`
--

CREATE TABLE `citizens` (
  `id` int(11) NOT NULL,
  `citizen_number` varchar(50) DEFAULT NULL,
  `full_name` varchar(200) NOT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `grandfather_name` varchar(100) DEFAULT NULL,
  `surname` varchar(100) DEFAULT NULL,
  `mother_name` varchar(150) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `birth_place` varchar(150) DEFAULT NULL,
  `gender` enum('ذكر','أنثى') NOT NULL,
  `marital_status` enum('أعزب','متزوج','مطلق','أرمل') DEFAULT 'أعزب',
  `nationality` varchar(50) DEFAULT 'عراقي',
  `religion` varchar(50) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `area` varchar(100) DEFAULT NULL,
  `neighborhood` varchar(100) DEFAULT NULL,
  `street` varchar(200) DEFAULT NULL,
  `house_number` varchar(50) DEFAULT NULL,
  `building_type` enum('بيت','شقة','فيلا','أخرى') DEFAULT 'بيت',
  `phone` varchar(50) DEFAULT NULL,
  `mobile` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `profession` varchar(150) DEFAULT NULL,
  `workplace` varchar(200) DEFAULT NULL,
  `monthly_income` decimal(12,2) DEFAULT NULL,
  `income_currency_id` int(11) DEFAULT NULL,
  `residence_status` enum('مقيم دائم','مقيم مؤقت','نازح','لاجئ') DEFAULT 'مقيم دائم',
  `social_status` enum('عادي','متقاعد','معاق','أرملة','يتيم','عاطل','طالب') DEFAULT 'عادي',
  `family_members_count` int(11) DEFAULT 1,
  `dependents_count` int(11) DEFAULT 0,
  `special_needs` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `verification_status` enum('مؤكد','غير مؤكد','قيد المراجعة') DEFAULT 'غير مؤكد',
  `registration_date` date DEFAULT curdate(),
  `last_update_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `citizens`
--

INSERT INTO `citizens` (`id`, `citizen_number`, `full_name`, `father_name`, `grandfather_name`, `surname`, `mother_name`, `birth_date`, `birth_place`, `gender`, `marital_status`, `nationality`, `religion`, `district`, `area`, `neighborhood`, `street`, `house_number`, `building_type`, `phone`, `mobile`, `email`, `profession`, `workplace`, `monthly_income`, `income_currency_id`, `residence_status`, `social_status`, `family_members_count`, `dependents_count`, `special_needs`, `notes`, `is_active`, `verification_status`, `registration_date`, `last_update_date`, `created_at`, `updated_at`) VALUES
(1, '12345678901', 'أحمد محمد علي الجبوري', '', '', '', '', '0000-00-00', '', 'ذكر', 'أعزب', 'عراقي', '', 'صلاح الدين', 'تكريت', '', '', '', 'بيت', '07701234567', '', '', '', '', '2000.00', 2, 'مقيم دائم', 'عادي', 1, 0, '', '', 1, 'مؤكد', '2025-06-05', '2025-11-03', '2025-06-04 22:27:38', '2025-11-03 10:33:11'),
(2, '12345678902', 'فاطمة خالد حسن التكريتي', '', '', '', '', '0000-00-00', '', 'أنثى', 'أعزب', 'عراقي', '', 'صلاح الدين', 'تكريت', '', '', '', 'بيت', '07709876543', '', '', '', '', '1500000.00', 1, 'مقيم دائم', 'عادي', 1, 0, '', '', 1, 'مؤكد', '2025-06-05', '2025-11-03', '2025-06-04 22:27:38', '2025-11-03 10:34:15'),
(3, '12345678903', 'عبد الله سعد جبار المهداوي', NULL, NULL, NULL, NULL, NULL, NULL, 'ذكر', 'أعزب', 'عراقي', NULL, 'صلاح الدين', 'تكريت', NULL, NULL, NULL, 'بيت', '07712345678', NULL, NULL, NULL, NULL, NULL, NULL, 'مقيم دائم', 'عادي', 1, 0, NULL, NULL, 1, 'غير مؤكد', '2025-06-05', NULL, '2025-06-04 22:27:38', '2025-06-04 22:27:38'),
(5, '12345678905', 'حسام الدين طارق نوري الدليمي', NULL, NULL, NULL, NULL, NULL, NULL, 'ذكر', 'أعزب', 'عراقي', NULL, 'صلاح الدين', 'تكريت', NULL, NULL, NULL, 'بيت', '07787654321', NULL, NULL, NULL, NULL, NULL, NULL, 'مقيم دائم', 'عادي', 1, 0, NULL, NULL, 1, 'قيد المراجعة', '2025-06-05', NULL, '2025-06-04 22:27:38', '2025-06-04 22:27:38'),
(7, '12342343242', 'Ziad El Rez', 'عزالدين', 'محمود', 'ابو العز', 'وفاء مثلج', '1981-01-01', 'تكريت', 'ذكر', 'متزوج', 'لبناني', 'مسلم', 'عكار', '', '', '', '', 'بيت', '03495685', '', 'ziadelrez@gmail.com', 'استشاري', 'طرابلس', '2000.00', 2, 'مقيم مؤقت', 'عادي', 5, 2, '', '', 1, 'غير مؤكد', '2025-06-05', '2025-11-03', '2025-06-04 22:47:41', '2025-11-03 10:32:34');

-- --------------------------------------------------------

--
-- Table structure for table `citizens_accounts`
--

CREATE TABLE `citizens_accounts` (
  `id` int(11) NOT NULL,
  `phone` varchar(20) NOT NULL COMMENT 'رقم الهاتف (فريد)',
  `telegram_chat_id` varchar(100) DEFAULT NULL COMMENT 'Telegram Chat ID',
  `telegram_username` varchar(100) DEFAULT NULL COMMENT 'Telegram Username',
  `permanent_access_code` varchar(20) DEFAULT NULL COMMENT 'رمز دخول ثابت للمواطن',
  `name` varchar(100) NOT NULL COMMENT 'اسم المواطن',
  `email` varchar(100) DEFAULT NULL COMMENT 'البريد الإلكتروني (اختياري)',
  `address` varchar(255) DEFAULT NULL COMMENT 'العنوان',
  `national_id` varchar(50) DEFAULT NULL COMMENT 'الرقم الوطني',
  `whatsapp_notifications` tinyint(1) DEFAULT 1 COMMENT 'تفعيل إشعارات WhatsApp',
  `website_notifications` tinyint(1) DEFAULT 1 COMMENT 'تفعيل إشعارات الموقع',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'الحساب نشط',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ الإنشاء',
  `last_login` timestamp NULL DEFAULT NULL COMMENT 'آخر تسجيل دخول',
  `login_count` int(11) DEFAULT 0 COMMENT 'عدد مرات تسجيل الدخول'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='حسابات المواطنين';

--
-- Dumping data for table `citizens_accounts`
--

INSERT INTO `citizens_accounts` (`id`, `phone`, `telegram_chat_id`, `telegram_username`, `permanent_access_code`, `name`, `email`, `address`, `national_id`, `whatsapp_notifications`, `website_notifications`, `is_active`, `created_at`, `last_login`, `login_count`) VALUES
(1, '03670065', '8480545659', NULL, 'TKT-90257', 'وسيم الحسن', '', 'تكريت العاصمة', '43343434', 1, 1, 1, '2025-11-11 18:42:17', '2025-11-18 21:31:04', 2),
(2, '03495685', '5368863036', 'ZiadElRez', 'TKT-72089', 'ziad el rex', 'ziadelrez@gmail.com', 'TEKRIT - AKKAR -  NORTH LEBANON', '43343434', 1, 1, 1, '2025-11-11 23:52:05', '2025-11-24 13:28:31', 0);

-- --------------------------------------------------------

--
-- Table structure for table `citizen_messages`
--

CREATE TABLE `citizen_messages` (
  `id` int(11) NOT NULL,
  `citizen_id` int(11) DEFAULT NULL COMMENT 'معرف المواطن (NULL = رسالة عامة)',
  `message_type` enum('عام','خاص','تحديث طلب','إشعار','تذكير') DEFAULT 'عام' COMMENT 'نوع الرسالة',
  `title` varchar(200) NOT NULL COMMENT 'عنوان الرسالة',
  `message` text NOT NULL COMMENT 'نص الرسالة',
  `request_id` int(11) DEFAULT NULL COMMENT 'معرف الطلب (إذا كانت مرتبطة بطلب)',
  `priority` enum('عادي','مهم','عاجل') DEFAULT 'عادي' COMMENT 'الأولوية',
  `is_read` tinyint(1) DEFAULT 0 COMMENT 'تم القراءة',
  `read_at` timestamp NULL DEFAULT NULL COMMENT 'تاريخ القراءة',
  `sent_via_whatsapp` tinyint(1) DEFAULT 0 COMMENT 'تم الإرسال عبر WhatsApp',
  `whatsapp_sent_at` timestamp NULL DEFAULT NULL COMMENT 'تاريخ الإرسال عبر WhatsApp',
  `created_by` int(11) DEFAULT NULL COMMENT 'معرف الموظف المرسل',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ الإنشاء'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='رسائل البلدية للمواطنين';

--
-- Triggers `citizen_messages`
--
DELIMITER $$
CREATE TRIGGER `tr_log_citizen_message` AFTER INSERT ON `citizen_messages` FOR EACH ROW BEGIN
    DECLARE v_telegram_chat_id VARCHAR(100);
    
    SELECT telegram_chat_id INTO v_telegram_chat_id
    FROM citizens_accounts
    WHERE id = NEW.citizen_id;
    
    IF v_telegram_chat_id IS NOT NULL THEN
        INSERT INTO telegram_log (
            citizen_id, telegram_chat_id, message_type, 
            message, status, created_at
        ) VALUES (
            NEW.citizen_id, v_telegram_chat_id, 'message',
            CONCAT('? رسالة من البلدية\n\n', NEW.title, '\n\n', NEW.message),
            'pending', NOW()
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `citizen_opinions`
--

CREATE TABLE `citizen_opinions` (
  `id` int(11) NOT NULL,
  `citizen_name` varchar(255) NOT NULL,
  `citizen_email` varchar(255) DEFAULT NULL,
  `opinion_type` enum('مقال','رأي','اقتراح','استطلاع') NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `is_published` tinyint(1) DEFAULT 0,
  `approval_status` enum('قيد المراجعة','موافق عليه','مرفوض') DEFAULT 'قيد المراجعة',
  `admin_notes` text DEFAULT NULL,
  `publish_date` date DEFAULT NULL,
  `likes_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `citizen_requests`
--

CREATE TABLE `citizen_requests` (
  `id` int(11) NOT NULL,
  `tracking_number` varchar(50) NOT NULL,
  `citizen_id` int(11) DEFAULT NULL,
  `citizen_name` varchar(255) NOT NULL,
  `citizen_phone` varchar(20) NOT NULL,
  `citizen_email` varchar(255) DEFAULT NULL,
  `citizen_address` text DEFAULT NULL,
  `national_id` varchar(20) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL COMMENT 'معرف المشروع للمساهمة',
  `request_type_id` int(11) NOT NULL,
  `request_type` varchar(255) NOT NULL,
  `request_title` varchar(500) NOT NULL,
  `request_description` text NOT NULL,
  `priority_level` enum('عادي','مهم','عاجل') NOT NULL DEFAULT 'عادي',
  `status` enum('جديد','قيد المراجعة','قيد التنفيذ','مكتمل','مرفوض','معلق') NOT NULL DEFAULT 'جديد',
  `attachments` text DEFAULT NULL,
  `assigned_to_department_id` int(11) DEFAULT NULL,
  `assigned_to_committee_id` int(11) DEFAULT NULL,
  `assigned_to_user_id` int(11) DEFAULT NULL,
  `assigned_to` varchar(255) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `response_text` text DEFAULT NULL,
  `citizen_rating` int(11) DEFAULT NULL,
  `citizen_feedback` text DEFAULT NULL,
  `estimated_completion_date` date DEFAULT NULL,
  `actual_completion_date` date DEFAULT NULL,
  `completion_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `citizen_requests`
--

INSERT INTO `citizen_requests` (`id`, `tracking_number`, `citizen_id`, `citizen_name`, `citizen_phone`, `citizen_email`, `citizen_address`, `national_id`, `project_id`, `request_type_id`, `request_type`, `request_title`, `request_description`, `priority_level`, `status`, `attachments`, `assigned_to_department_id`, `assigned_to_committee_id`, `assigned_to_user_id`, `assigned_to`, `admin_notes`, `response_text`, `citizen_rating`, `citizen_feedback`, `estimated_completion_date`, `actual_completion_date`, `completion_date`, `created_at`, `updated_at`) VALUES
(3, 'REQ2025-9764', NULL, 'اختبار نهائي', '07901234567', 'test@example.com', 'عنوان اختبار', '123456789', NULL, 1, 'إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال', 'طلب اختبار نهائي', 'هذا طلب اختبار للتأكد من عمل النظام بعد الإصلاح', 'عادي', 'مكتمل', NULL, 1, NULL, 41, NULL, 'Done', NULL, NULL, NULL, NULL, NULL, '2025-07-11 01:08:06', '2025-06-22 15:52:45', '2025-07-10 22:08:06'),
(4, 'REQ2025-5510', NULL, 'xidngijngin', '123', '', 'يسيشسي', '', 1, 1, 'المساهمة في المشروع', 'شسيشسيشي', 'شسيشسيشس', 'عادي', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-06-22 15:53:20', '2025-06-22 15:53:20'),
(5, 'REQ2025-8277', NULL, 'wassim el rez', 'صصصصصص', 'ziadelrez@gmail.com', 'شسشسسي', '', 5, 1, 'المساهمة في المشروع', 'شسيشسيشسي', 'شسيشسيشسيشسيشسي', 'عادي', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-06-22 15:54:03', '2025-06-22 15:54:03'),
(6, 'REQ2025-4896', NULL, 'rewere', 'rerr', '', 'erere', '', 3, 1, 'المساهمة في المشروع', 'erer', 'errr', 'عادي', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-06-27 22:09:44', '2025-06-27 22:09:44'),
(7, 'REQ2025-0955', NULL, 'يبسيبسيبسيبس', 'بسيبسيبسيب', '', '', '', NULL, 5, 'إفادة محتويات وإشغال', 'سيبسيبسيبس', 'سبسبسيبسي', 'عادي', 'قيد المراجعة', NULL, 1, NULL, 40, NULL, 'مازالت الامور قيد الدرس', NULL, NULL, NULL, NULL, NULL, NULL, '2025-06-28 21:01:33', '2025-07-10 21:30:17'),
(8, 'REQ-2025-11870', NULL, 'xidngijngin', '343434', 'ziadelrez@gmail.com', 'سبسيبسيب', '', NULL, 32, '', 'سيبسيبسبسب', 'سيبسيبسبسب', 'عادي', 'جديد', NULL, 17, NULL, 44, NULL, 'يييييسيسي', NULL, NULL, NULL, NULL, NULL, NULL, '2025-06-28 22:11:24', '2025-07-10 20:02:11'),
(9, 'REQ-2025-81358', NULL, 'كريم عبد الجبار', '03419548', 'ziadelrez@gmail.com', 'سيبسيبسبسب', '43343434', NULL, 39, '', 'تكريت الطريق العام', 'اعتراض على ماذا', 'عادي', 'مكتمل', NULL, 17, NULL, 44, NULL, 'تمت المعالجة', NULL, NULL, NULL, NULL, NULL, '2025-07-07 02:22:56', '2025-07-06 22:13:05', '2025-07-06 23:22:56'),
(10, 'REQ-2025-48555', NULL, 'احمد الرز', '03262739', '', 'تكريت', '', NULL, 33, '', 'تكريت', 'طلب مستعجل', 'مهم', 'جديد', NULL, 17, NULL, 44, NULL, 'تمت المعالجة بالفعل', NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-10 19:29:24', '2025-07-11 23:56:44'),
(11, 'REQ-2025-77753', NULL, 'احمد الرز', '03262739', 'ziadelrez@gmail.com', 'تكريت - الجندي', '', NULL, 1, '', 'طلب عام', 'طلب للبلدية', 'عادي', 'قيد المراجعة', NULL, 1, NULL, 43, NULL, 'تم استلام الطلب', NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-12 11:44:53', '2025-07-12 11:46:42'),
(12, 'REQ-2025-15879', NULL, 'wassim el rez', '03419548', 'ziadelrez@gmail.com', 'ddd', '', NULL, 25, '', 'eee', 'weee', 'مهم', 'جديد', NULL, 17, NULL, 44, NULL, 'ddddddddddddddddddddddddd', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-01 20:11:24', '2025-11-01 22:14:38'),
(13, 'REQ-2025-13522', NULL, 'wassim el rez', '03262739', 'ziadelrez@gmail.com', 'قققققققق', '43343434', NULL, 1, '', 'تكريت', 'سييسسيسي', 'عادي', 'قيد المراجعة', NULL, 17, NULL, 44, NULL, 'قثقثفقثف', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-09 23:51:30', '2025-11-09 23:53:24'),
(14, 'REQ-2025-84314', NULL, 'ziad el rex', '03670065', 'ziadelrez@gmail.com', 'شيشيش', '2332', NULL, 1, '', 'تكريت', 'صصصصصصصصصصصصصصصصصص', 'مهم', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-10 01:16:56', '2025-11-10 01:16:56'),
(15, 'REQ-2025-86731', NULL, 'ziad el rex', '03670065', 'ziadelrez@gmail.com', 'شيشيش', '2332', NULL, 1, '', 'تكريت', 'صصصصصصصصصصصصصصصصصص', 'مهم', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-11 18:35:04', '2025-11-11 18:35:04'),
(16, 'REQ-2025-79513', NULL, 'زياد الرز', '03670065', 'ziadelrez@gmail.com', 'تكريت - الرملية', '4512414151', NULL, 2, '', 'تكريت الرملية', 'اريد افدة تشغيل', 'مهم', 'جديد', NULL, 17, NULL, 44, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-11 18:36:21', '2025-11-11 18:37:29'),
(17, 'REQ-2025-94540', NULL, 'وسيم الحسن', '03670065', 'imathacademyleb@gmail.com', 'تكريت الرملية', '43343434', NULL, 2, '', 'تكريت', 'افادة اعادة تشغيل', 'مهم', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-11 18:42:17', '2025-11-11 18:42:17'),
(18, 'REQ-2025-06677', NULL, 'وسيم الحسن', '03670065', 'imathacademyleb@gmail.com', 'تكريت الرملية', '43343434', NULL, 2, '', 'تكريت', 'افادة اعادة تشغيل', 'مهم', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-11 18:48:56', '2025-11-11 18:48:56'),
(19, 'REQ-2025-78792', NULL, 'جميل العلي', '03670065', 'amal.talkhawi@gmail.com', 'تكريت - الرملية', '2343434', NULL, 2, '', 'تكريت - اول الرملية بجانب افران المحبة', 'افادة طلب اشغال', 'مهم', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-11 20:14:28', '2025-11-11 20:14:28'),
(20, 'REQ-2025-05034', NULL, 'wassim el rez', '03670065', 'ziadelrez@gmail.com', 'ثصقثقصثقص', '43343434', NULL, 1, '', 'صثقثصق', 'صثقصثقثص', 'مهم', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-11 20:16:26', '2025-11-11 20:16:26'),
(21, 'REQ-2025-63377', NULL, 'ziad el rex', '03670065', 'ziadelrez@gmail.com', '3434', '4343', NULL, 2, '', '343', '34343', 'مهم', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-11 20:27:46', '2025-11-11 20:27:46'),
(22, 'REQ-2025-35455', NULL, 'wassim el rez', '03670065', 'ziadelrez@gmail.com', '6765766', 'غع56767', NULL, 2, '', '6757تبلتت', 'بتتب', 'عاجل', 'قيد التنفيذ', NULL, 17, NULL, 44, NULL, 'نعم تم الموضوع - من قبل لجنة الاغال', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-11 20:39:00', '2025-11-11 22:01:01'),
(23, 'REQ-2025-30450', NULL, 'وسيم الحسن', '03670065', 'ziadelrez@gmail.com', 'صثقثقص', '2343', NULL, 1, '', 'صثقصثقثصق', 'صثقثصق', 'مهم', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-11 23:42:16', '2025-11-11 23:42:16'),
(24, 'REQ-2025-25286', NULL, 'وسيم الحسن', '03670065', 'ziadelrez@gmail.com', '34435345435435', '3343', NULL, 2, '', '344534', '34543543', 'مهم', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-11 23:47:44', '2025-11-11 23:47:44'),
(25, 'REQ-2025-36407', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', '64564654', '4556', NULL, 2, '', 'تكريت', '456464', 'عادي', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-11 23:52:05', '2025-11-11 23:52:05'),
(26, 'REQ-2025-53311', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', '3344', '23344', NULL, 1, '', '344', '344', 'عادي', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-11 23:54:05', '2025-11-11 23:54:05'),
(27, 'REQ-2025-22673', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', '234324234', '234', NULL, 2, '', 'qwewqqqqqqqqqqqqqqqqqqqq', 'qqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq', 'مهم', 'جديد', NULL, 1, 3, NULL, NULL, 'werwerewrwrwerwerwerewrwee', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-12 00:11:09', '2025-11-12 21:05:25'),
(28, 'REQ-2025-64132', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', '345345345', '34543535', NULL, 2, '', '34534534', '345435435', 'عادي', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-12 00:12:19', '2025-11-12 00:12:19'),
(29, 'REQ-2025-96546', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', '34534535', '345', NULL, 1, '', 'تكريت', '3454353', 'عادي', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-12 00:19:22', '2025-11-12 00:19:22'),
(30, 'REQ-2025-88514', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', '5446565', '455', NULL, 2, '', 'tekrit', '45645654', 'عادي', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-12 00:25:13', '2025-11-12 00:25:13'),
(31, 'REQ-2025-37978', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', '4645646464', '4564564', NULL, 1, '', 'تكريت', 'فثقفثقف', 'عادي', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-12 00:30:13', '2025-11-12 00:30:13'),
(32, 'REQ-2025-89535', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', 'ثقف', 'ثقفثقفث', NULL, 1, '', 'ثق', 'فق', 'عادي', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-12 00:31:09', '2025-11-12 00:31:09'),
(33, 'REQ-2025-04525', NULL, 'وسيم الحسن', '03670065', 'ziadelrez@gmail.com', '45646', '46456', NULL, 1, '', 'تكريت', '456456', 'عادي', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-12 00:34:38', '2025-11-12 00:34:38'),
(34, 'REQ-2025-82377', NULL, 'وسيم الحسن', '03670065', 'ziadelrez@gmail.com', '687686', '768678', NULL, 2, '', 'تكريت', '678768', 'عادي', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-12 00:36:59', '2025-11-12 00:36:59'),
(35, 'REQ-2025-27494', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', 'للل', '43343434', NULL, 2, '', 'للل', 'لل', 'عادي', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-12 00:37:49', '2025-11-12 00:37:49'),
(36, 'REQ-2025-21461', NULL, 'وسيم الحسن', '03670065', 'ziadelrez@gmail.com', 'للل', '43343434', NULL, 1, '', 'للل', 'للل', 'عادي', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-12 00:42:48', '2025-11-12 00:42:48'),
(37, 'REQ-2025-37438', NULL, 'وسيم الحسن', '03670065', 'ziadelrez@gmail.com', 'للل', '43343434', NULL, 1, '', 'للل', 'للل', 'عادي', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-12 00:46:42', '2025-11-12 00:46:42'),
(38, 'REQ-2025-19755', NULL, 'وسيم الحسن', '03670065', 'ziadelrez@gmail.com', 'للل', '43343434', NULL, 1, '', 'للل', 'للل', 'عادي', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-12 00:49:28', '2025-11-12 00:49:28'),
(39, 'REQ-2025-56009', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', '454454545', '55555', NULL, 1, '', '454545', '454545', 'عادي', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-12 10:18:50', '2025-11-12 10:18:50'),
(40, 'REQ-2025-00905', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', 'مممم', '43343434', NULL, 1, '', 'تكريت', 'خخخ', 'عادي', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-12 10:27:32', '2025-11-12 10:27:32'),
(41, 'REQ-2025-22860', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', 'TEKRIT - AKKAR -  NORTH LEBANON', '43343434', NULL, 1, '', 'تكريت', 'WERERWER', 'عادي', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-12 11:35:52', '2025-11-12 11:35:52'),
(42, 'REQ-2025-94488', NULL, 'وسيم الحسن', '03670065', 'ziadelrez@gmail.com', 'تكريت العاصمة', '43343434', NULL, 2, '', 'يييبي', 'يبيبي', 'عادي', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-12 17:49:57', '2025-11-12 17:49:57'),
(43, 'REQ-2025-12067', NULL, 'وسيم الحسن', '03670065', '', 'تكريت العاصمة', '43343434', NULL, 2, '', 'عع', 'عخخ', 'عادي', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-12 17:51:33', '2025-11-12 17:51:33'),
(44, 'REQ-2025-69014', NULL, 'وسيم الحسن', '03670065', '', 'تكريت العاصمة', '43343434', NULL, 2, '', 'tekrit', 'qweqweqewe', 'عاجل', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-17 22:26:11', '2025-11-17 22:26:11'),
(45, 'REQ-2025-24837', NULL, 'وسيم الحسن', '03670065', '', 'تكريت العاصمة', '43343434', NULL, 2, '', 'tekrit', 'qweqweqewe', 'عاجل', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-17 22:26:22', '2025-11-17 22:26:22'),
(46, 'REQ-2025-41966', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', 'TEKRIT - AKKAR -  NORTH LEBANON', '43343434', NULL, 1, '', 'تكريت -rttrytryrty', 'rtytrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrr', 'عاجل', 'جديد', NULL, 1, 3, NULL, NULL, 'asasddddddddddddddddda\r\nasdsadsada\r\nsadasdasd\r\nasdsadsad\r\nasdsadsadasdasdasdasdasdad', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-17 22:29:47', '2025-11-17 22:36:27'),
(47, 'REQ-2025-13642', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', 'TEKRIT - AKKAR -  NORTH LEBANON', '43343434', NULL, 2, '', 'شسيشسي', 'ssssssssssssssssssssssss', 'مهم', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-17 22:38:02', '2025-11-17 22:38:02'),
(48, 'REQ-2025-51041', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', 'TEKRIT - AKKAR -  NORTH LEBANON', '43343434', NULL, 1, '', 'ertrtert', 'ertertert', 'عاجل', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-18 21:05:59', '2025-11-18 21:05:59'),
(49, 'REQ-2025-06082', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', 'TEKRIT - AKKAR -  NORTH LEBANON', '43343434', NULL, 1, '', 'ertrtert', 'ertertert', 'عاجل', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-18 21:06:21', '2025-11-18 21:06:21'),
(50, 'REQ-2025-30360', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', 'TEKRIT - AKKAR -  NORTH LEBANON', '43343434', NULL, 1, '', 'ertrtert', 'ertertert', 'عاجل', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-18 21:06:43', '2025-11-18 21:06:43'),
(51, 'REQ-2025-66110', NULL, 'وسيم الحسن', '03670065', '', 'تكريت العاصمة', '43343434', NULL, 1, '', 'asdasdasdasd', 'ثقفقثفثقف', 'عاجل', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-18 21:27:27', '2025-11-18 21:27:27'),
(52, 'REQ-2025-38487', NULL, 'وسيم الحسن', '03670065', '', 'تكريت العاصمة', '43343434', NULL, 1, '', 'asdasdasdasd', 'ثقفقثفثقف', 'عاجل', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-18 21:27:50', '2025-11-18 21:27:50'),
(53, 'REQ-2025-05763', NULL, 'وسيم الحسن', '03670065', '', 'تكريت العاصمة', '43343434', NULL, 1, '', 'asdasdasdasd', 'ثقفقثفثقف', 'عاجل', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-18 21:28:13', '2025-11-18 21:28:13'),
(54, 'REQ-2025-65563', NULL, 'وسيم الحسن', '03670065', '', 'تكريت العاصمة', '43343434', NULL, 1, '', 'asdasdasdasd', 'ثقفقثفثقف', 'عاجل', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-18 21:28:36', '2025-11-18 21:28:36'),
(55, 'REQ-2025-18225', NULL, 'وسيم الحسن', '03670065', '', 'تكريت العاصمة', '43343434', NULL, 1, '', 'asdasdasdasd', 'ثقفقثفثقف', 'عاجل', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-18 21:28:59', '2025-11-18 21:28:59'),
(56, 'REQ-2025-23181', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', 'TEKRIT - AKKAR -  NORTH LEBANON', '43343434', NULL, 2, '', 'تكريت', '34345435', 'عاجل', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-18 21:49:23', '2025-11-18 21:49:23'),
(57, 'REQ-2025-59218', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', 'TEKRIT - AKKAR -  NORTH LEBANON', '43343434', NULL, 1, '', 'tekrit', 'صثصثث', 'عاجل', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-18 21:59:25', '2025-11-18 21:59:25'),
(58, 'REQ-2025-29926', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', 'TEKRIT - AKKAR -  NORTH LEBANON', '43343434', NULL, 1, '', 'تكريت', 'ي', 'مهم', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-18 22:17:23', '2025-11-18 22:17:23'),
(59, 'REQ-2025-52578', NULL, 'وسيم الحسن', '03670065', '', 'تكريت العاصمة', '43343434', NULL, 2, '', 'تكريت', 'صثصثص', 'عاجل', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-18 22:26:38', '2025-11-18 22:26:38'),
(60, 'REQ-2025-87346', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', 'TEKRIT - AKKAR -  NORTH LEBANON', '43343434', NULL, 1, '', 'tekrit', 'ححح', 'عاجل', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-18 22:27:48', '2025-11-18 22:27:48'),
(61, 'REQ-2025-98760', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', 'TEKRIT - AKKAR -  NORTH LEBANON', '43343434', NULL, 1, '', 'شسيشسي', 'بيللي', 'عاجل', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-18 22:31:38', '2025-11-18 22:31:38'),
(62, 'REQ-2025-78853', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', 'TEKRIT - AKKAR -  NORTH LEBANON', '43343434', NULL, 1, '', 'Thgg', 'Ghhh', 'عاجل', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-18 23:00:03', '2025-11-18 23:00:03'),
(63, 'REQ-2025-41970', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', 'TEKRIT - AKKAR -  NORTH LEBANON', '43343434', NULL, 1, '', 'Uhyh', 'Hhh', 'عاجل', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-18 23:28:46', '2025-11-18 23:28:46'),
(64, 'REQ-2025-34051', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', 'TEKRIT - AKKAR -  NORTH LEBANON', '43343434', NULL, 2, '', 'تنامنتمتنم', 'نتمتنمتنمتنمتن', 'عاجل', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-19 00:02:08', '2025-11-19 00:02:08'),
(65, 'REQ-2025-69493', NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', 'TEKRIT - AKKAR -  NORTH LEBANON', '43343434', NULL, 4, '', 'tekrit', 'عععععععععععععععععععع', 'عاجل', 'جديد', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-22 20:18:43', '2025-11-22 20:18:43');

-- --------------------------------------------------------

--
-- Table structure for table `citizen_sessions`
--

CREATE TABLE `citizen_sessions` (
  `id` int(11) NOT NULL,
  `citizen_id` int(11) NOT NULL COMMENT 'معرف المواطن',
  `session_token` varchar(64) NOT NULL COMMENT 'رمز الجلسة',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'عنوان IP',
  `user_agent` text DEFAULT NULL COMMENT 'معلومات المتصفح',
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'آخر نشاط',
  `expires_at` datetime NOT NULL COMMENT 'تاريخ انتهاء الجلسة',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ الإنشاء'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جلسات تسجيل دخول المواطنين';

-- --------------------------------------------------------

--
-- Table structure for table `cleaning_reports`
--

CREATE TABLE `cleaning_reports` (
  `id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `area_name` varchar(200) NOT NULL,
  `team_leader` varchar(100) DEFAULT NULL,
  `team_size` int(11) DEFAULT 1,
  `work_hours` decimal(4,2) DEFAULT NULL,
  `waste_collected` decimal(8,2) DEFAULT NULL,
  `equipment_used` text DEFAULT NULL,
  `issues_encountered` text DEFAULT NULL,
  `completion_status` enum('مكتمل','جزئي','لم يبدأ','معطل') DEFAULT 'مكتمل',
  `weather_conditions` varchar(100) DEFAULT NULL,
  `citizen_feedback` text DEFAULT NULL,
  `photos_attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`photos_attachments`)),
  `supervisor_notes` text DEFAULT NULL,
  `submitted_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `committee_decisions`
--

CREATE TABLE `committee_decisions` (
  `id` int(11) NOT NULL,
  `committee_id` int(11) NOT NULL,
  `session_id` int(11) DEFAULT NULL,
  `decision_number` varchar(100) DEFAULT NULL,
  `decision_title` varchar(255) NOT NULL,
  `decision_text` longtext NOT NULL,
  `status` enum('قيد المتابعة','منفذ','مرفوض','معلق') DEFAULT 'قيد المتابعة',
  `due_date` date DEFAULT NULL,
  `implemented_at` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `committee_finance_summary`
--

CREATE TABLE `committee_finance_summary` (
  `committee_id` int(11) NOT NULL,
  `opening_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_income` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_expense` decimal(15,2) NOT NULL DEFAULT 0.00,
  `current_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `committee_finance_summary`
--

INSERT INTO `committee_finance_summary` (`committee_id`, `opening_balance`, `total_income`, `total_expense`, `current_balance`, `last_updated`) VALUES
(1, '0.00', '0.00', '0.00', '0.00', '2025-11-12 22:45:02'),
(2, '0.00', '0.00', '0.00', '0.00', '2025-11-12 22:45:02'),
(3, '0.00', '0.00', '0.00', '0.00', '2025-11-12 22:45:02'),
(4, '0.00', '0.00', '0.00', '0.00', '2025-11-12 22:45:02'),
(5, '0.00', '0.00', '0.00', '0.00', '2025-11-12 22:45:02');

-- --------------------------------------------------------

--
-- Table structure for table `committee_finance_transactions`
--

CREATE TABLE `committee_finance_transactions` (
  `id` int(11) NOT NULL,
  `committee_id` int(11) NOT NULL,
  `transaction_date` date NOT NULL,
  `transaction_type` enum('إيراد','مصروف') NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `currency_id` int(10) UNSIGNED DEFAULT NULL,
  `exchange_rate` decimal(15,6) DEFAULT 1.000000,
  `description` varchar(255) DEFAULT NULL,
  `reference_type` varchar(100) DEFAULT NULL,
  `reference_id` int(10) UNSIGNED DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `committee_members`
--

CREATE TABLE `committee_members` (
  `id` int(11) NOT NULL,
  `committee_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `member_role` enum('رئيس','نائب الرئيس','سكرتير','عضو','مقرر') DEFAULT 'عضو',
  `join_date` date NOT NULL,
  `leave_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `committee_sessions`
--

CREATE TABLE `committee_sessions` (
  `id` int(11) NOT NULL,
  `committee_id` int(11) NOT NULL,
  `session_number` varchar(100) DEFAULT NULL,
  `session_title` varchar(255) NOT NULL,
  `session_date` date NOT NULL,
  `session_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `agenda` text DEFAULT NULL,
  `minutes` longtext DEFAULT NULL,
  `attachments` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `committee_sessions`
--

INSERT INTO `committee_sessions` (`id`, `committee_id`, `session_number`, `session_title`, `session_date`, `session_time`, `location`, `agenda`, `minutes`, `attachments`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 2, '234234', 'بسبسبس', '2025-11-12', '10:00:00', 'قاعة الاجتماعات الرئيسية', 'hikig', 'gjhgjgjhg', NULL, 1, '2025-11-12 23:03:46', '2025-11-12 23:03:46'),
(3, 3, '65456', 'بسبسبس4444444', '2025-11-13', '22:01:00', 'بلدة تكريت', 'زياد الرز', 'زياد الرز       يييييييييييييييييييييييييي', NULL, 1, '2025-11-12 23:36:00', '2025-11-14 22:07:45');

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `id` int(11) NOT NULL,
  `citizen_id` int(11) DEFAULT NULL COMMENT 'معرف حساب المواطن',
  `complaint_number` varchar(50) DEFAULT NULL,
  `citizen_name` varchar(100) NOT NULL,
  `citizen_phone` varchar(20) DEFAULT NULL,
  `citizen_email` varchar(100) DEFAULT NULL,
  `citizen_address` text DEFAULT NULL,
  `complaint_type` enum('خدمات عامة','نظافة','صيانة','مالية','أخرى') DEFAULT 'خدمات عامة',
  `subject` varchar(500) NOT NULL,
  `description` text NOT NULL,
  `priority` enum('عادية','متوسطة','عالية','عاجلة') DEFAULT 'عادية',
  `status` enum('جديدة','قيد المراجعة','قيد المعالجة','مكتملة','مرفوضة') DEFAULT 'جديدة',
  `department` varchar(100) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `response` text DEFAULT NULL,
  `resolution_date` date DEFAULT NULL,
  `satisfaction_rating` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `complaints`
--

INSERT INTO `complaints` (`id`, `citizen_id`, `complaint_number`, `citizen_name`, `citizen_phone`, `citizen_email`, `citizen_address`, `complaint_type`, `subject`, `description`, `priority`, `status`, `department`, `assigned_to`, `response`, `resolution_date`, `satisfaction_rating`, `created_at`, `updated_at`) VALUES
(1, 2, NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', 'TEKRIT - AKKAR -  NORTH LEBANON', 'خدمات عامة', 'Request to Configure PTR Record for IP 87.117.238.50', 'yuiiui', 'عالية', 'قيد المراجعة', NULL, 42, 'نعمل على مراجعة الشكوى', NULL, NULL, '2025-11-22 22:22:43', '2025-11-22 22:56:26'),
(2, 2, NULL, 'ziad el rex', '03495685', 'ziadelrez@gmail.com', 'TEKRIT - AKKAR -  NORTH LEBANON', 'خدمات عامة', 'test for msg', 'ققققققققققققق', 'متوسطة', 'جديدة', NULL, NULL, NULL, NULL, NULL, '2025-11-22 23:06:19', '2025-11-22 23:06:19');

-- --------------------------------------------------------

--
-- Stand-in structure for view `complaints_detailed`
-- (See below for the actual view)
--
CREATE TABLE `complaints_detailed` (
`id` int(11)
,`citizen_id` int(11)
,`complaint_number` varchar(50)
,`citizen_name` varchar(100)
,`citizen_phone` varchar(20)
,`citizen_email` varchar(100)
,`citizen_address` text
,`complaint_type` enum('خدمات عامة','نظافة','صيانة','مالية','أخرى')
,`subject` varchar(500)
,`description` text
,`priority` enum('عادية','متوسطة','عالية','عاجلة')
,`status` enum('جديدة','قيد المراجعة','قيد المعالجة','مكتملة','مرفوضة')
,`department` varchar(100)
,`assigned_to` int(11)
,`response` text
,`resolution_date` date
,`satisfaction_rating` int(11)
,`created_at` timestamp
,`updated_at` timestamp
,`citizen_phone_from_account` varchar(20)
,`citizen_name_from_account` varchar(100)
,`telegram_chat_id` varchar(100)
,`telegram_username` varchar(100)
,`assigned_user_name` varchar(100)
,`updates_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `complaint_categories`
--

CREATE TABLE `complaint_categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `category_description` text DEFAULT NULL,
  `responsible_department_id` int(11) DEFAULT NULL,
  `priority_level` enum('منخفض','متوسط','عالي','عاجل') DEFAULT 'متوسط',
  `expected_resolution_days` int(11) DEFAULT 7,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `complaint_categories`
--

INSERT INTO `complaint_categories` (`id`, `category_name`, `category_description`, `responsible_department_id`, `priority_level`, `expected_resolution_days`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'نفايات', 'شكاوى متعلقة بجمع ومعالجة النفايات', NULL, 'متوسط', 3, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(2, 'طرق', 'شكاوى متعلقة بحالة الطرق والبنية التحتية', NULL, 'عالي', 14, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(3, 'مياه', 'شكاوى متعلقة بالمياه والصرف الصحي', NULL, 'عاجل', 1, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(4, 'إنارة', 'شكاوى متعلقة بالإنارة العامة', NULL, 'متوسط', 5, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(5, 'صيانة', 'شكاوى متعلقة بصيانة المرافق العامة', NULL, 'متوسط', 7, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(6, 'أخرى', 'شكاوى أخرى غير مصنفة', NULL, 'منخفض', 10, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22');

-- --------------------------------------------------------

--
-- Table structure for table `complaint_statuses`
--

CREATE TABLE `complaint_statuses` (
  `id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL,
  `status_description` text DEFAULT NULL,
  `status_color` varchar(7) DEFAULT '#007bff',
  `is_final_status` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `complaint_statuses`
--

INSERT INTO `complaint_statuses` (`id`, `status_name`, `status_description`, `status_color`, `is_final_status`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'جديدة', 'شكوى مستلمة ولم تتم مراجعتها بعد', '#17a2b8', 0, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(2, 'قيد المعالجة', 'شكوى قيد المراجعة والمعالجة', '#ffc107', 0, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(3, 'مكتملة', 'تم حل الشكوى بنجاح', '#28a745', 1, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(4, 'مؤجلة', 'تم تأجيل معالجة الشكوى', '#6c757d', 0, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(5, 'مرفوضة', 'تم رفض الشكوى', '#dc3545', 1, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22');

-- --------------------------------------------------------

--
-- Table structure for table `complaint_updates`
--

CREATE TABLE `complaint_updates` (
  `id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL COMMENT 'معرف الموظف المسؤول',
  `update_type` enum('status_change','comment','municipality_response','admin_note','data_update') NOT NULL DEFAULT 'comment',
  `update_text` text NOT NULL COMMENT 'نص التحديث',
  `is_visible_to_citizen` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'مرئي للمواطن',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `complaint_updates`
--

INSERT INTO `complaint_updates` (`id`, `complaint_id`, `updated_by`, `update_type`, `update_text`, `is_visible_to_citizen`, `created_at`) VALUES
(1, 1, NULL, 'status_change', 'تم استلام الشكوى وهي قيد المراجعة', 1, '2025-11-22 22:22:43'),
(2, 1, 1, 'municipality_response', 'تم تغيير الحالة من \'جديدة\' إلى \'قيد المراجعة\'\n\nرد من البلدية:\nنعمل على مراجعة الشكوى', 1, '2025-11-22 22:56:26'),
(3, 2, NULL, 'status_change', 'تم استلام الشكوى وهي قيد المراجعة', 1, '2025-11-22 23:06:19');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `sender_name` varchar(100) NOT NULL,
  `sender_email` varchar(100) NOT NULL,
  `sender_phone` varchar(20) DEFAULT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('جديد','قيد المراجعة','تم الرد','مغلق') DEFAULT 'جديد'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `sender_name`, `sender_email`, `sender_phone`, `subject`, `message`, `created_at`, `status`) VALUES
(1, 'ziad', 'ziadelrez@gmail.com', '03495685', 'test', 'الموضوع هو مجرد تجربة', '2025-06-12 21:22:55', 'جديد'),
(2, 'ziad', 'imathacademyleb@gmail.com', '', 'ssss', 'ss', '2025-06-20 10:58:15', 'جديد'),
(3, 'ziad', 'ziadelrez@gmail.com', '', 'ssss', 'ضصثضصثضصثضصث', '2025-06-20 11:15:06', 'جديد'),
(4, 'ziad', 'ziadelrez@gmail.com', '', 'ssss', 'ضصثضصث', '2025-06-20 19:31:39', 'جديد'),
(5, 'ziad', 'ziadelrez@gmail.com', '03495685', 'ssss', 'dssdfsdfsf', '2025-07-13 19:48:40', 'جديد'),
(6, 'ziad', 'ziadelrez@gmail.com', '03495685', 'test for msg', 'yrd', '2025-11-01 22:20:48', 'جديد');

-- --------------------------------------------------------

--
-- Table structure for table `contract_types`
--

CREATE TABLE `contract_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(50) NOT NULL,
  `type_description` text DEFAULT NULL,
  `payment_frequency` enum('monthly','daily','hourly','project') DEFAULT 'monthly',
  `benefits_included` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contract_types`
--

INSERT INTO `contract_types` (`id`, `type_name`, `type_description`, `payment_frequency`, `benefits_included`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'عقد شهري', 'عقد عمل بأجر شهري ثابت', 'monthly', 1, 1, '2025-06-07 20:31:22', '2025-06-08 21:46:52'),
(2, 'عقد يومي', 'عقد عمل بأجر يومي', 'daily', 1, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(3, 'عقد مشروع', 'عقد لمشروع محدد', 'project', 1, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(4, 'عقد تدريب', 'عقد تدريب مؤقت', 'monthly', 1, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(5, 'دائم', 'عقد عمل دائم', 'monthly', 1, 1, '2025-06-07 21:18:09', '2025-06-07 21:18:09'),
(6, 'مؤقت', 'عقد عمل مؤقت', 'monthly', 1, 1, '2025-06-07 21:18:09', '2025-06-07 21:18:09'),
(7, 'تدريب', 'فترة تدريب', 'monthly', 1, 1, '2025-06-07 21:18:09', '2025-06-07 21:18:09'),
(8, 'متطوع', 'متطوع', 'monthly', 1, 1, '2025-11-22 19:48:12', '2025-11-22 19:48:12');

-- --------------------------------------------------------

--
-- Table structure for table `council_members`
--

CREATE TABLE `council_members` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `position` enum('رئيس البلدية','نائب رئيس البلدية','عضو مجلس','سكرتير المجلس','أمين المال') NOT NULL,
  `specialization` varchar(255) DEFAULT NULL,
  `biography` text DEFAULT NULL,
  `education` text DEFAULT NULL,
  `experience` text DEFAULT NULL,
  `profile_picture` varchar(500) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `appointment_date` date DEFAULT NULL,
  `term_start_date` date DEFAULT NULL,
  `term_end_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `council_members`
--

INSERT INTO `council_members` (`id`, `full_name`, `position`, `specialization`, `biography`, `education`, `experience`, `profile_picture`, `phone`, `email`, `appointment_date`, `term_start_date`, `term_end_date`, `is_active`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 'د. أحمد محمد العلي', 'رئيس البلدية', 'إدارة عامة وتطوير المدن', 'رئيس بلدية تكريت، حاصل على دكتوراه في الإدارة العامة من جامعة بغداد. يتمتع بخبرة واسعة في إدارة المشاريع التطويرية والخدمات البلدية.', 'دكتوراه إدارة عامة - جامعة بغداد، ماجستير تخطيط حضري - الجامعة التكنولوجية', 'أكثر من 15 عامًا في الإدارة العامة والتطوير الحضري، شارك في تطوير العديد من المشاريع الاستراتيجية', 'uploads/council_members/member_1762035189_690685f5625ec.png', '', '', '2022-01-15', '2022-01-15', '2026-01-15', 1, 1, '2025-06-10 20:10:30', '2025-11-01 22:13:09'),
(2, 'م. فاطمة حسن الجبوري', 'عضو مجلس', 'هندسة مدنية وبنية تحتية', 'نائب رئيس بلدية تكريت، مهندسة مدنية متخصصة في مشاريع البنية التحتية والتطوير الحضري.', 'بكالوريوس هندسة مدنية - جامعة تكريت، ماجستير إدارة مشاريع - الجامعة المستنصرية', '12 عامًا في مجال الهندسة المدنية ومشاريع البنية التحتية', 'uploads/council_members/member_1749767301_684b54851f1b1.JPG', '', '', '2022-01-15', '2022-01-15', '2026-01-15', 1, 2, '2025-06-10 20:10:30', '2025-06-12 22:28:21'),
(3, 'أ. سعد عبدالله الطائي', 'عضو مجلس', 'القانون والشؤون الإدارية', 'عضو مجلس البلدية، محامي وخبير في القانون الإداري والشؤون القانونية للبلديات.', 'بكالوريوس قانون - جامعة بغداد، دبلوم عالي في القانون الإداري', '10 سنوات في المحاماة والاستشارات القانونية، خبير في قوانين البلديات', 'uploads/council_members/member_1749854129_684ca7b100161.JPG', '', '', '2022-01-15', '2022-01-15', '2026-01-15', 1, 3, '2025-06-10 20:10:30', '2025-06-13 22:35:29');

-- --------------------------------------------------------

--
-- Table structure for table `currencies`
--

CREATE TABLE `currencies` (
  `id` int(11) NOT NULL,
  `currency_code` varchar(10) NOT NULL,
  `currency_name` varchar(100) NOT NULL,
  `currency_symbol` varchar(10) NOT NULL,
  `exchange_rate_to_iqd` decimal(10,4) DEFAULT 1.0000,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_default` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `currencies`
--

INSERT INTO `currencies` (`id`, `currency_code`, `currency_name`, `currency_symbol`, `exchange_rate_to_iqd`, `is_active`, `created_at`, `updated_at`, `is_default`) VALUES
(1, 'LBP', 'ليرة لبنانية', 'ل.ل', '1.0000', 1, '2025-06-04 22:35:34', '2025-06-10 19:17:41', 0),
(2, 'USD', 'دولار أمريكي', '$', '0.0001', 1, '2025-06-04 22:35:34', '2025-07-12 00:23:31', 1);

-- --------------------------------------------------------

--
-- Table structure for table `currency_conversion_log`
--

CREATE TABLE `currency_conversion_log` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `from_currency_id` int(11) NOT NULL,
  `to_currency_id` int(11) NOT NULL,
  `original_amount` decimal(15,2) NOT NULL,
  `converted_amount` decimal(15,2) NOT NULL,
  `exchange_rate` decimal(10,6) NOT NULL,
  `conversion_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `conversion_type` enum('manual','automatic') DEFAULT 'automatic',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `department_name` varchar(255) NOT NULL,
  `department_description` text DEFAULT NULL,
  `department_manager` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `department_name`, `department_description`, `department_manager`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'الإدارة العامة', 'الإدارة العامة والتنسيق', 'زياد الرز', 1, '2025-06-06 20:20:30', '2025-06-06 20:25:56'),
(2, 'الإدارة المالية', 'إدارة الشؤون المالية والمحاسبة', 'حمادة طالب', 1, '2025-06-06 20:20:30', '2025-06-06 20:30:59'),
(3, 'الهندسة', 'الهندسة والتخطيط العمراني', NULL, 1, '2025-06-06 20:20:30', '2025-06-06 20:20:30'),
(4, 'الموارد البشرية', 'إدارة شؤون الموظفين', NULL, 1, '2025-06-06 20:20:30', '2025-06-06 20:20:30'),
(5, 'القانونية', 'الشؤون القانونية والقضائية', NULL, 1, '2025-06-06 20:20:30', '2025-06-06 20:20:30'),
(6, 'خدمة المواطنين', 'خدمات المواطنين والمراجعين', '', 1, '2025-06-06 20:20:30', '2025-07-13 19:38:46'),
(13, 'تقنية المعلومات', 'تقنية المعلومات والأنظمة الإلكترونية', NULL, 1, '2025-06-06 20:21:50', '2025-06-06 20:21:50'),
(14, 'الصحة والبيئة', 'الصحة العامة والبيئة والنظافة', NULL, 1, '2025-06-06 20:21:50', '2025-06-06 20:21:50'),
(15, 'النقل والمواصلات', 'النقل والمواصلات العامة', NULL, 1, '2025-06-06 20:21:50', '2025-06-06 20:21:50'),
(16, 'النظافة', 'خدمات النظافة العامة', NULL, 1, '2025-06-06 20:21:50', '2025-06-06 20:21:50'),
(17, 'الصيانة', 'صيانة المرافق والمعدات والمباني', NULL, 1, '2025-06-06 20:21:50', '2025-06-06 20:21:50'),
(18, 'المياه', 'إدارة الموارد المائية والصرف الصحي', NULL, 1, '2025-06-06 20:21:50', '2025-06-06 20:21:50'),
(19, 'الطوارئ', 'إدارة حالات الطوارئ والأزمات', NULL, 1, '2025-06-06 20:21:50', '2025-06-06 20:21:50'),
(35, 'قسم الهندسة والتخطيط', NULL, NULL, 1, '2025-06-28 21:37:13', '2025-06-28 21:37:13'),
(36, 'قسم الخدمات العامة', NULL, NULL, 1, '2025-06-28 21:37:13', '2025-06-28 21:37:13'),
(37, 'قسم الشؤون المالية', NULL, NULL, 1, '2025-06-28 21:37:13', '2025-06-28 21:37:13'),
(38, 'قسم الشؤون الإدارية', NULL, NULL, 1, '2025-06-28 21:37:13', '2025-06-28 21:37:13'),
(39, 'قسم البيئة والصحة', 'ewrwewerwerw', 'زياد الرز', 1, '2025-06-28 21:37:13', '2025-11-01 22:53:43');

-- --------------------------------------------------------

--
-- Table structure for table `development_projects`
--

CREATE TABLE `development_projects` (
  `id` int(11) NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `project_description` text NOT NULL,
  `project_goal` text NOT NULL,
  `project_location` varchar(255) NOT NULL,
  `project_cost` decimal(15,2) NOT NULL,
  `project_duration` varchar(100) DEFAULT NULL,
  `beneficiaries_count` int(11) DEFAULT NULL,
  `beneficiaries_description` text DEFAULT NULL,
  `project_status` enum('مطروح','قيد التنفيذ','منفذ','متوقف','ملغي') DEFAULT 'مطروح',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `completion_percentage` int(11) DEFAULT 0,
  `funding_source` varchar(255) DEFAULT NULL,
  `contractor` varchar(255) DEFAULT NULL,
  `project_images` text DEFAULT NULL,
  `before_images` text DEFAULT NULL,
  `after_images` text DEFAULT NULL,
  `allow_contributions` tinyint(1) DEFAULT 0,
  `contributions_target` decimal(15,2) DEFAULT 0.00,
  `contributions_collected` decimal(15,2) DEFAULT 0.00,
  `responsible_department_id` int(11) DEFAULT NULL,
  `project_manager_id` int(11) DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `currency_id` int(11) DEFAULT 1 COMMENT 'مرجع لجدول العملات',
  `is_municipality_project` tinyint(1) DEFAULT 1 COMMENT 'هل هو مشروع بلدية (1) أم لا (0)',
  `donor_organization` varchar(255) DEFAULT NULL COMMENT 'اسم الجهة المانحة',
  `donor_contribution` decimal(15,2) DEFAULT 0.00 COMMENT 'مساهمة الجهة المانحة',
  `contributors_names` text DEFAULT NULL COMMENT 'أسماء المساهمين',
  `contributors_contribution` decimal(15,2) DEFAULT 0.00 COMMENT 'قيمة مساهمة المساهمين',
  `municipality_contribution` decimal(15,2) DEFAULT 0.00 COMMENT 'مساهمة البلدية',
  `total_project_cost` decimal(15,2) DEFAULT 0.00 COMMENT 'التكلفة الإجمالية للمشروع',
  `funding_notes` text DEFAULT NULL COMMENT 'ملاحظات حول التمويل',
  `project_base_cost` decimal(15,2) DEFAULT 0.00 COMMENT 'تكلفة المشروع الأساسية',
  `project_base_cost_currency_id` int(11) DEFAULT 1 COMMENT 'عملة التكلفة الأساسية',
  `municipality_contribution_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'مبلغ مساهمة البلدية',
  `municipality_contribution_currency_id` int(11) DEFAULT 1 COMMENT 'عملة مساهمة البلدية',
  `donor_contribution_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'مبلغ مساهمة الجهة المانحة',
  `donor_contribution_currency_id` int(11) DEFAULT 1 COMMENT 'عملة مساهمة الجهة المانحة',
  `donors_contribution_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'مبلغ مساهمة المتبرعين',
  `donors_contribution_currency_id` int(11) DEFAULT 1 COMMENT 'عملة مساهمة المتبرعين',
  `donors_list` text DEFAULT NULL COMMENT 'قائمة أسماء المتبرعين',
  `total_contributions_iqd` decimal(15,2) DEFAULT 0.00 COMMENT 'إجمالي المساهمات',
  `remaining_cost_iqd` decimal(15,2) DEFAULT 0.00 COMMENT 'التكلفة المتبقية',
  `funding_completion_percentage` decimal(5,2) DEFAULT 0.00 COMMENT 'نسبة اكتمال التمويل',
  `funding_calculation_date` timestamp NULL DEFAULT NULL COMMENT 'تاريخ آخر حساب للتمويل',
  `auto_currency_conversion` tinyint(1) DEFAULT 1 COMMENT 'تحويل العملات تلقائياً'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `development_projects`
--

INSERT INTO `development_projects` (`id`, `project_name`, `project_description`, `project_goal`, `project_location`, `project_cost`, `project_duration`, `beneficiaries_count`, `beneficiaries_description`, `project_status`, `start_date`, `end_date`, `completion_percentage`, `funding_source`, `contractor`, `project_images`, `before_images`, `after_images`, `allow_contributions`, `contributions_target`, `contributions_collected`, `responsible_department_id`, `project_manager_id`, `is_featured`, `created_at`, `currency_id`, `is_municipality_project`, `donor_organization`, `donor_contribution`, `contributors_names`, `contributors_contribution`, `municipality_contribution`, `total_project_cost`, `funding_notes`, `project_base_cost`, `project_base_cost_currency_id`, `municipality_contribution_amount`, `municipality_contribution_currency_id`, `donor_contribution_amount`, `donor_contribution_currency_id`, `donors_contribution_amount`, `donors_contribution_currency_id`, `donors_list`, `total_contributions_iqd`, `remaining_cost_iqd`, `funding_completion_percentage`, `funding_calculation_date`, `auto_currency_conversion`) VALUES
(1, 'تطوير شبكة الصرف الصحي', 'مشروع شامل لتطوير وتحديث شبكة الصرف الصحي في المدينة لتحسين الخدمات البيئية', 'تحسين الخدمات البيئية والصحية للمواطنين', 'وسط مدينة تكريت', '500000000.00', '12 شهر', 15000, NULL, 'قيد التنفيذ', '2024-01-01', NULL, 0, NULL, NULL, NULL, NULL, NULL, 1, '0.00', '0.00', NULL, NULL, 1, '2025-06-10 05:35:28', 2, 1, NULL, '0.00', NULL, '0.00', '0.00', '0.00', NULL, '0.00', 2, '0.00', 2, '0.00', 2, '0.00', 2, NULL, '0.00', '0.00', '0.00', NULL, 1),
(2, 'إنشاء حديقة عامة جديدة', 'إنشاء حديقة عامة حديثة مع مرافق ترفيهية للعائلات', 'توفير مساحات خضراء وترفيهية للمواطنين', 'حي الجمهورية', '150000000.00', '8 أشهر', 5000, NULL, 'مطروح', '2024-02-01', NULL, 0, NULL, NULL, NULL, NULL, NULL, 1, '0.00', '0.00', NULL, NULL, 1, '2025-06-10 05:35:28', 2, 1, NULL, '0.00', NULL, '0.00', '0.00', '0.00', NULL, '0.00', 2, '0.00', 2, '0.00', 2, '0.00', 2, NULL, '0.00', '0.00', '0.00', NULL, 1),
(3, 'تحديث نظام الإنارة العامة', 'استبدال أعمدة الإنارة القديمة بتقنية LED الحديثة', 'تحسين الإنارة وتوفير الطاقة', 'جميع أحياء المدينة', '1500.00', '6 أشهر', 25, NULL, 'مطروح', '2025-01-01', NULL, 0, 'مختلط', NULL, NULL, NULL, NULL, 1, '0.00', '0.00', NULL, NULL, 0, '2025-06-10 05:35:28', 2, 1, 'فقغفغقفغ', '0.00', NULL, '0.00', '0.00', '0.00', '', '1500.00', 2, '200.00', 2, '500.00', 2, '500.00', 2, 'نناانتان', '0.12', '0.03', '80.00', '2025-06-20 10:47:47', 1),
(4, 'تطوير شبكة الصرف الصحي', 'مشروع شامل لتطوير وتحديث شبكة الصرف الصحي في المدينة لتحسين الخدمات البيئية', 'تحسين الخدمات البيئية والصحية للمواطنين', 'وسط مدينة تكريت', '1150.00', '12 شهر', 12, NULL, 'قيد التنفيذ', '2025-10-10', NULL, 0, 'مختلط', NULL, NULL, NULL, NULL, 1, '0.00', '0.00', 17, NULL, 0, '2025-06-10 05:45:48', 2, 1, 'البنك الدولي', '0.00', NULL, '0.00', '0.00', '1150.00', 'كان جيدا', '1150.00', 2, '450.00', 2, '400.00', 2, '300.00', 2, 'احمد الرز', '1150.00', '0.00', '100.00', '2025-06-13 20:07:39', 1),
(9, 'إنشاء حديقة عامة جديدة', 'مشروع انارة المنطقة', 'مشروع انارة المنطقة', 'وسط مدينة تكريت', '821.00', '8 أشهر', 123, NULL, 'مطروح', '2025-09-01', NULL, 0, 'مانح', NULL, NULL, NULL, NULL, 1, '0.00', '0.00', 14, NULL, 0, '2025-07-12 10:15:18', 2, 1, 'UNDP', '0.00', NULL, '0.00', '0.00', '821.00', '', '821.00', 2, '0.00', 2, '121.00', 2, '0.00', 2, '', '121.00', '700.00', '14.74', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `document_title` varchar(500) NOT NULL,
  `document_number` varchar(100) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `subcategory` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `file_name` varchar(500) DEFAULT NULL,
  `file_path` varchar(1000) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `upload_date` date DEFAULT curdate(),
  `creation_date` date DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `security_level` enum('عام','محدود','سري','سري جداً') DEFAULT 'عام',
  `access_permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`access_permissions`)),
  `uploaded_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `status` enum('مسودة','معتمد','مؤرشف','محذوف') DEFAULT 'مسودة',
  `version_number` int(11) DEFAULT 1,
  `parent_document_id` int(11) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documents_forms`
--

CREATE TABLE `documents_forms` (
  `id` int(11) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `document_description` text DEFAULT NULL,
  `document_type` enum('نموذج طلب','موازنة','قرار مجلس','مناقصة','دليل خدمات','أخرى') NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `download_count` int(11) DEFAULT 0,
  `is_public` tinyint(1) DEFAULT 1,
  `requires_login` tinyint(1) DEFAULT 0,
  `department_id` int(11) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `donations`
--

CREATE TABLE `donations` (
  `id` int(11) NOT NULL,
  `donation_number` varchar(50) DEFAULT NULL,
  `donor_name` varchar(200) NOT NULL,
  `donor_type` enum('فرد','شركة','منظمة','جهة حكومية','منظمة دولية') NOT NULL,
  `source_type` enum('دعم حكومي','مساهمة مجتمعية','هبة خارجية','أخرى') DEFAULT 'أخرى' COMMENT 'نوع المصدر',
  `budget_item_id` int(11) DEFAULT NULL COMMENT 'بند الميزانية (إيراد)',
  `donor_phone` varchar(20) DEFAULT NULL,
  `donor_email` varchar(100) DEFAULT NULL,
  `donor_address` text DEFAULT NULL,
  `donation_type` enum('نقدي','عيني','خدمي') NOT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `currency_id` int(11) DEFAULT 1,
  `items_description` text DEFAULT NULL,
  `estimated_value` decimal(15,2) DEFAULT NULL,
  `estimated_value_currency_id` int(11) DEFAULT 1,
  `purpose` varchar(500) DEFAULT NULL,
  `allocated_to_project_id` int(11) DEFAULT NULL,
  `allocated_to_department` varchar(100) DEFAULT NULL,
  `status` enum('مستلم','قيد المراجعة','موافق عليه','قيد التوزيع','موزع','مرفوض') DEFAULT 'مستلم',
  `received_by_user_id` int(11) DEFAULT NULL,
  `received_date` date DEFAULT NULL,
  `approval_date` date DEFAULT NULL,
  `distribution_date` date DEFAULT NULL,
  `tax_exemption_number` varchar(100) DEFAULT NULL,
  `receipt_issued` tinyint(1) DEFAULT 0,
  `thank_you_sent` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `donation_campaigns`
--

CREATE TABLE `donation_campaigns` (
  `id` int(11) NOT NULL,
  `campaign_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `target_amount` decimal(15,2) DEFAULT NULL,
  `target_currency_id` int(11) DEFAULT 1,
  `raised_amount` decimal(15,2) DEFAULT 0.00,
  `raised_currency_id` int(11) DEFAULT 1,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `campaign_type` enum('طارئة','موسمية','مشروع محدد','عامة') DEFAULT 'عامة',
  `target_audience` varchar(500) DEFAULT NULL,
  `responsible_person_id` int(11) DEFAULT NULL,
  `status` enum('مخططة','نشطة','منتهية','ملغية') DEFAULT 'مخططة',
  `success_rate` decimal(5,2) GENERATED ALWAYS AS (case when `target_amount` > 0 then `raised_amount` / `target_amount` * 100 else 0 end) STORED,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `donation_statuses`
--

CREATE TABLE `donation_statuses` (
  `id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL,
  `status_description` text DEFAULT NULL,
  `status_color` varchar(7) DEFAULT '#007bff',
  `is_final_status` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `donation_statuses`
--

INSERT INTO `donation_statuses` (`id`, `status_name`, `status_description`, `status_color`, `is_final_status`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'مستلم', 'تم استلام التبرع', '#17a2b8', 0, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(2, 'قيد المراجعة', 'التبرع قيد المراجعة والتقييم', '#ffc107', 0, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(3, 'موافق عليه', 'تم الموافقة على التبرع', '#28a745', 0, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(4, 'قيد التوزيع', 'التبرع قيد التوزيع أو الاستخدام', '#6f42c1', 0, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(5, 'موزع', 'تم توزيع التبرع بالكامل', '#28a745', 1, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(6, 'مرفوض', 'تم رفض التبرع', '#dc3545', 1, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22');

-- --------------------------------------------------------

--
-- Table structure for table `donation_types`
--

CREATE TABLE `donation_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(50) NOT NULL,
  `type_description` text DEFAULT NULL,
  `requires_documentation` tinyint(1) DEFAULT 1,
  `tax_deductible` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `donation_types`
--

INSERT INTO `donation_types` (`id`, `type_name`, `type_description`, `requires_documentation`, `tax_deductible`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'نقدي', 'تبرع نقدي بالعملة المحلية', 1, 1, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(2, 'عيني', 'تبرع بمواد أو أشياء عينية', 1, 1, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(3, 'خدمي', 'تبرع بخدمات أو عمل تطوعي', 0, 0, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22');

-- --------------------------------------------------------

--
-- Table structure for table `donors`
--

CREATE TABLE `donors` (
  `id` int(11) NOT NULL,
  `donor_code` varchar(50) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `type` enum('فرد','شركة','منظمة','جهة حكومية','منظمة دولية') NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `website` varchar(200) DEFAULT NULL,
  `tax_number` varchar(100) DEFAULT NULL,
  `registration_number` varchar(100) DEFAULT NULL,
  `preferred_donation_areas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferred_donation_areas`)),
  `total_donations` decimal(15,2) DEFAULT 0.00,
  `total_donations_iqd` decimal(15,2) DEFAULT 0.00,
  `first_donation_date` date DEFAULT NULL,
  `last_donation_date` date DEFAULT NULL,
  `donation_count` int(11) DEFAULT 0,
  `status` enum('نشط','غير نشط','محظور') DEFAULT 'نشط',
  `rating` enum('ممتاز','جيد جداً','جيد','مقبول') DEFAULT 'جيد',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `donors`
--

INSERT INTO `donors` (`id`, `donor_code`, `name`, `type`, `contact_person`, `phone`, `email`, `address`, `website`, `tax_number`, `registration_number`, `preferred_donation_areas`, `total_donations`, `total_donations_iqd`, `first_donation_date`, `last_donation_date`, `donation_count`, `status`, `rating`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'DNR001', 'شركة النور للتجارة', 'شركة', NULL, '07701234567', 'info@noortrading.com', NULL, NULL, NULL, NULL, NULL, '5000000.00', '0.00', NULL, NULL, 0, 'نشط', 'جيد', NULL, '2025-06-04 22:27:38', '2025-06-04 22:27:38'),
(2, 'DNR002', 'أحمد محمد علي', 'فرد', NULL, '07709876543', 'ahmed.mohammed@gmail.com', NULL, NULL, NULL, NULL, NULL, '2000000.00', '0.00', NULL, NULL, 0, 'نشط', 'جيد', NULL, '2025-06-04 22:27:38', '2025-06-04 22:27:38'),
(3, 'DNR003', 'منظمة الهلال الأحمر', 'منظمة', NULL, '07812345678', 'contact@redcrescent.org', NULL, NULL, NULL, NULL, NULL, '10000000.00', '0.00', NULL, NULL, 0, 'نشط', 'جيد', NULL, '2025-06-04 22:27:38', '2025-06-04 22:27:38'),
(4, 'DNR004', 'شركة البناء المتطورة', 'شركة', NULL, '07723456789', 'info@advancedbuild.com', NULL, NULL, NULL, NULL, NULL, '3000000.00', '0.00', NULL, NULL, 0, 'نشط', 'جيد', NULL, '2025-06-04 22:27:38', '2025-06-04 22:27:38'),
(5, 'DNR005', 'جامعة تكريت', '', NULL, '07734567890', 'contact@tu.edu.iq', NULL, NULL, NULL, NULL, NULL, '1500000.00', '0.00', NULL, NULL, 0, 'نشط', 'جيد', NULL, '2025-06-04 22:27:38', '2025-06-04 22:27:38');

-- --------------------------------------------------------

--
-- Table structure for table `donor_organizations`
--

CREATE TABLE `donor_organizations` (
  `id` int(11) NOT NULL,
  `donor_code` varchar(50) NOT NULL,
  `organization_name` varchar(300) NOT NULL,
  `organization_name_en` varchar(300) DEFAULT NULL,
  `donor_type` enum('حكومي محلي','حكومي فيدرالي','منظمة دولية','منظمة خيرية دولية','منظمة خيرية محلية','شركة محلية','شركة دولية','بنك','سفارة','أفراد','جمعية','اتحاد','مؤسسة تعليمية','مؤسسة طبية','أخرى') NOT NULL,
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `fax` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `website` varchar(200) DEFAULT NULL,
  `contact_person_name` varchar(150) DEFAULT NULL,
  `contact_person_title` varchar(100) DEFAULT NULL,
  `contact_person_phone` varchar(50) DEFAULT NULL,
  `contact_person_email` varchar(150) DEFAULT NULL,
  `registration_number` varchar(100) DEFAULT NULL,
  `tax_number` varchar(100) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `establishment_date` date DEFAULT NULL,
  `specialization` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`specialization`)),
  `funding_areas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`funding_areas`)),
  `average_donation_amount` decimal(15,2) DEFAULT NULL,
  `maximum_donation_amount` decimal(15,2) DEFAULT NULL,
  `preferred_currency_id` int(11) DEFAULT 1,
  `total_donations_count` int(11) DEFAULT 0,
  `total_donations_amount` decimal(20,2) DEFAULT 0.00,
  `first_donation_date` date DEFAULT NULL,
  `last_donation_date` date DEFAULT NULL,
  `reliability_rating` enum('ممتاز','جيد جداً','جيد','مقبول','ضعيف') DEFAULT 'جيد',
  `partnership_level` enum('استراتيجي','مستمر','مؤقت','لمرة واحدة') DEFAULT 'مؤقت',
  `documentation_quality` enum('ممتاز','جيد','مقبول','ضعيف') DEFAULT 'مقبول',
  `status` enum('نشط','غير نشط','معلق','محظور','منتهي الشراكة') DEFAULT 'نشط',
  `notes` text DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `updated_by_user_id` int(11) DEFAULT NULL,
  `last_contact_date` date DEFAULT NULL,
  `next_contact_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `total_donations_lbp` decimal(20,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `external_data_sources`
--

CREATE TABLE `external_data_sources` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `provider` varchar(100) DEFAULT NULL COMMENT 'مزود الخدمة',
  `api_endpoint` text NOT NULL,
  `api_key` varchar(255) DEFAULT NULL,
  `authentication_type` enum('none','api_key','oauth','basic') DEFAULT 'none',
  `request_method` enum('GET','POST') DEFAULT 'GET',
  `request_headers` text DEFAULT NULL COMMENT 'JSON',
  `request_body` text DEFAULT NULL COMMENT 'JSON',
  `response_format` enum('json','xml','csv','html') DEFAULT 'json',
  `data_mapping` text DEFAULT NULL COMMENT 'JSON mapping',
  `rate_limit` int(11) DEFAULT 100 COMMENT 'عدد الطلبات في الساعة',
  `is_active` tinyint(1) DEFAULT 1,
  `last_used` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `facilities`
--

CREATE TABLE `facilities` (
  `id` int(11) NOT NULL,
  `name_ar` varchar(255) NOT NULL,
  `name_en` varchar(255) NOT NULL,
  `category_id` int(11) NOT NULL,
  `description_ar` text DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `contact_person_ar` varchar(100) DEFAULT NULL,
  `contact_person_en` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address_ar` text DEFAULT NULL,
  `address_en` text DEFAULT NULL,
  `working_hours_ar` varchar(200) DEFAULT NULL,
  `working_hours_en` varchar(200) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `views_count` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `facilities`
--

INSERT INTO `facilities` (`id`, `name_ar`, `name_en`, `category_id`, `description_ar`, `description_en`, `latitude`, `longitude`, `contact_person_ar`, `contact_person_en`, `phone`, `email`, `address_ar`, `address_en`, `working_hours_ar`, `working_hours_en`, `website`, `image_path`, `is_active`, `is_featured`, `views_count`, `created_by`, `created_at`, `updated_at`) VALUES
(6, 'بلدية تكريت', 'Tikrit Municipality', 6, 'المقر الرئيسي لبلدية تكريت - تقديم الخدمات البلدية للمواطنين', 'Tikrit Municipality Main Office - Providing municipal services to citizens', '34.51803979', '36.15902244', 'مكتب المسؤول', '', '03495685', 'sdjfhkjh@gmail.com', 'تكريت - بجانب المسجد الكبير', '', '', '', '', 'facility_1750257226.jpg', 1, 1, 3, 1, '2025-06-18 13:51:01', '2025-06-18 14:35:48'),
(7, 'Mr. Whish', '', 13, 'مركز whish لتحويل الاموال - داخل وخارج لبنان', '', '34.52411619', '36.15443584', 'Ziad El Rez', 'Ziad El Rez', '03495685', 'ziadelrez@gmail.com', 'التكريت - الساحة', '', '', '', '', 'facility_1750266016.png', 1, 1, 3, 1, '2025-06-18 17:00:16', '2025-06-18 17:04:45');

-- --------------------------------------------------------

--
-- Table structure for table `facility_categories`
--

CREATE TABLE `facility_categories` (
  `id` int(11) NOT NULL,
  `name_ar` varchar(100) NOT NULL,
  `name_en` varchar(100) NOT NULL,
  `icon` varchar(50) DEFAULT 'default-marker',
  `color` varchar(7) DEFAULT '#3498db',
  `description_ar` text DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `facility_categories`
--

INSERT INTO `facility_categories` (`id`, `name_ar`, `name_en`, `icon`, `color`, `description_ar`, `description_en`, `is_active`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 'مدارس', 'Schools', 'school', '#e74c3c', NULL, NULL, 1, 1, '2025-06-18 13:02:13', '2025-06-18 13:02:13'),
(2, 'مساجد', 'Mosques', 'mosque', '#2ecc71', NULL, NULL, 1, 2, '2025-06-18 13:02:13', '2025-06-18 13:02:13'),
(3, 'مراكز صحية', 'Health Centers', 'hospital', '#3498db', NULL, NULL, 1, 3, '2025-06-18 13:02:13', '2025-06-18 13:02:13'),
(4, 'محلات تجارية', 'Commercial Shops', 'store', '#f39c12', NULL, NULL, 1, 4, '2025-06-18 13:02:13', '2025-06-18 13:02:13'),
(5, 'مطاعم ومقاهي', 'Restaurants & Cafes', 'restaurant', '#e67e22', NULL, NULL, 1, 5, '2025-06-18 13:02:13', '2025-06-18 13:02:13'),
(6, 'مؤسسات حكومية', 'Government Institutions', 'government', '#9b59b6', NULL, NULL, 1, 6, '2025-06-18 13:02:13', '2025-06-18 13:02:13'),
(7, 'بنوك وصرافات', 'Banks & ATMs', 'bank', '#1abc9c', NULL, NULL, 1, 7, '2025-06-18 13:02:13', '2025-06-18 13:02:13'),
(8, 'محطات وقود', 'Gas Stations', 'gas-station', '#34495e', NULL, NULL, 1, 8, '2025-06-18 13:02:13', '2025-06-18 13:02:13'),
(9, 'حدائق ومتنزهات', 'Parks & Gardens', 'park', '#27ae60', NULL, NULL, 1, 9, '2025-06-18 13:02:13', '2025-06-18 13:02:13'),
(10, 'مراكز رياضية', 'Sports Centers', 'sports', '#f1c40f', NULL, NULL, 1, 10, '2025-06-18 13:02:13', '2025-06-18 13:02:13'),
(11, 'صيدليات', 'Pharmacies', 'pharmacy', '#e74c3c', NULL, NULL, 1, 11, '2025-06-18 13:02:13', '2025-06-18 13:02:13'),
(12, 'فنادق ونزل', 'Hotels & Lodges', 'hotel', '#8e44ad', NULL, NULL, 1, 12, '2025-06-18 13:02:13', '2025-06-18 13:02:13'),
(13, 'خدمات عامة', 'Public Services', 'service', '#95a5a6', NULL, NULL, 1, 13, '2025-06-18 13:02:13', '2025-06-18 13:02:13'),
(14, 'مواقف سيارات', 'Parking Areas', 'parking', '#7f8c8d', NULL, NULL, 1, 14, '2025-06-18 13:02:13', '2025-06-18 13:02:13'),
(15, 'أسواق', 'Markets', 'market', '#d35400', NULL, NULL, 1, 15, '2025-06-18 13:02:13', '2025-06-18 13:02:13');

-- --------------------------------------------------------

--
-- Table structure for table `facility_ratings`
--

CREATE TABLE `facility_ratings` (
  `id` int(11) NOT NULL,
  `facility_id` int(11) NOT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `user_email` varchar(100) DEFAULT NULL,
  `rating` tinyint(4) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text DEFAULT NULL,
  `is_approved` tinyint(1) DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `id` int(11) NOT NULL,
  `question` varchar(500) NOT NULL,
  `answer` text NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `view_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faqs`
--

INSERT INTO `faqs` (`id`, `question`, `answer`, `category`, `display_order`, `is_featured`, `view_count`, `created_at`) VALUES
(1, 'كيف يمكنني تقديم طلب إلكتروني؟', 'يمكنك تقديم طلبك من خلال قسم \"طلبات المواطنين\" في الموقع، وستحصل على رقم تتبع لمتابعة حالة طلبك.', 'خدمات', 1, 0, 0, '2025-06-10 05:16:08'),
(2, 'هل يمكنني متابعة حالة طلبي؟', 'نعم، يمكنك متابعة حالة طلبك باستخدام رقم التتبع المرسل إليك عبر الرسائل النصية أو البريد الإلكتروني.', 'خدمات', 2, 0, 0, '2025-06-10 05:16:08'),
(3, 'ما هي ساعات عمل البلدية؟', 'ساعات العمل الرسمية من السبت إلى الخميس من 8:00 صباحاً حتى 2:00 مساءً.', 'عام', 3, 0, 0, '2025-06-10 05:16:08'),
(4, 'كيف يمكنني المشاركة في المشاريع التطوعية؟', 'يمكنك التسجيل في المبادرات من خلال قسم \"المبادرات الشبابية والبيئية\" أو الاتصال بنا مباشرة.', 'مشاركة', 4, 0, 0, '2025-06-10 05:16:08'),
(5, 'أين يمكنني تحميل النماذج المطلوبة؟', 'جميع النماذج متوفرة في قسم \"مركز التحميل\" ويمكن تحميلها مجاناً.', 'خدمات', 5, 0, 0, '2025-06-10 05:16:08'),
(6, 'كيف يمكنني تقديم طلب إلكتروني؟', 'يمكنك تقديم طلبك من خلال قسم \"طلبات المواطنين\" في الموقع، وستحصل على رقم تتبع لمتابعة حالة طلبك.', 'خدمات', 1, 0, 0, '2025-06-10 05:31:30'),
(7, 'هل يمكنني متابعة حالة طلبي؟', 'نعم، يمكنك متابعة حالة طلبك باستخدام رقم التتبع المرسل إليك عبر الرسائل النصية أو البريد الإلكتروني.', 'خدمات', 2, 0, 0, '2025-06-10 05:31:30'),
(8, 'ما هي ساعات عمل البلدية؟', 'ساعات العمل الرسمية من السبت إلى الخميس من 8:00 صباحاً حتى 2:00 مساءً.', 'عام', 3, 0, 0, '2025-06-10 05:31:30'),
(9, 'كيف يمكنني المشاركة في المشاريع التطوعية؟', 'يمكنك التسجيل في المبادرات من خلال قسم \"المبادرات الشبابية والبيئية\" أو الاتصال بنا مباشرة.', 'مشاركة', 4, 0, 0, '2025-06-10 05:31:30'),
(10, 'أين يمكنني تحميل النماذج المطلوبة؟', 'جميع النماذج متوفرة في قسم \"مركز التحميل\" ويمكن تحميلها مجاناً.', 'خدمات', 5, 0, 0, '2025-06-10 05:31:30');

-- --------------------------------------------------------

--
-- Table structure for table `financial_transactions`
--

CREATE TABLE `financial_transactions` (
  `id` int(11) NOT NULL,
  `transaction_date` date NOT NULL,
  `type` enum('إيراد','مصروف') NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency_id` int(11) DEFAULT 1,
  `exchange_rate` decimal(10,4) DEFAULT 1.0000,
  `reference_number` varchar(50) DEFAULT NULL,
  `payment_method` enum('نقد','شيك','تحويل مصرفي','بطاقة ائتمان','أخرى') DEFAULT 'نقد',
  `bank_name` varchar(100) DEFAULT NULL,
  `check_number` varchar(50) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `related_project_id` int(11) DEFAULT NULL,
  `related_donation_id` int(11) DEFAULT NULL,
  `budget_item_id` int(11) DEFAULT NULL COMMENT 'بند الميزانية',
  `supplier_invoice_id` int(11) DEFAULT NULL COMMENT 'فاتورة المورد',
  `committee_id` int(11) DEFAULT NULL,
  `tax_collection_id` int(11) DEFAULT NULL COMMENT 'الجباية الضريبية',
  `association_id` int(11) DEFAULT NULL COMMENT 'الجمعية/المقاول',
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `status` enum('مسودة','معتمد','ملغي') DEFAULT 'مسودة',
  `is_approved` tinyint(1) DEFAULT 0 COMMENT 'معتمد؟',
  `approved_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `amount_in_lbp` decimal(15,2) GENERATED ALWAYS AS (`amount` * `exchange_rate`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `financial_transactions`
--

INSERT INTO `financial_transactions` (`id`, `transaction_date`, `type`, `category`, `description`, `amount`, `currency_id`, `exchange_rate`, `reference_number`, `payment_method`, `bank_name`, `check_number`, `department`, `related_project_id`, `related_donation_id`, `budget_item_id`, `supplier_invoice_id`, `committee_id`, `tax_collection_id`, `association_id`, `created_by`, `approved_by`, `status`, `is_approved`, `approved_date`, `created_at`, `updated_at`) VALUES
(2, '2025-06-05', 'مصروف', 'رواتب', 'ققققق', '15000000.00', 1, '1.0000', '3434', 'نقد', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 'مسودة', 0, NULL, '2025-06-04 22:42:52', '2025-06-04 22:42:52'),
(3, '2025-11-02', 'إيراد', 'مساهمة مع البلدية', 'مساهمة مع البلدية في جمع النفايات', '1200.00', 2, '0.0001', '', 'نقد', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 'مسودة', 0, NULL, '2025-11-01 23:02:03', '2025-11-01 23:02:03'),
(4, '2025-11-02', 'إيراد', 'مساهمة مع البلدية', 'مساهمة مع البلدية في جمع النفايات', '1200.00', 2, '0.0001', '', 'نقد', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 'مسودة', 0, NULL, '2025-11-01 23:04:25', '2025-11-01 23:04:25'),
(5, '2025-11-02', 'إيراد', 'رواتب', 'سسسسسسسسسسسس', '1400.00', 2, '0.0001', '7458', 'نقد', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 'مسودة', 0, NULL, '2025-11-01 23:09:15', '2025-11-01 23:09:15'),
(6, '2025-11-02', 'مصروف', 'رواتب', 'يييي', '5000000.00', 1, '1.0000', '34', 'نقد', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 'مسودة', 0, NULL, '2025-11-01 23:18:45', '2025-11-01 23:18:45'),
(8, '2025-11-03', 'مصروف', 'فثقف', 'ثقفثف', '1500.00', 2, '0.0001', '545454', 'نقد', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 'معتمد', 0, NULL, '2025-11-03 19:20:25', '2025-11-03 19:20:25'),
(10, '2025-11-03', 'مصروف', 'مساهمة مع البلدية', 'لاتلاتال', '50.00', 2, '0.0001', '3353', 'نقد', '', '', NULL, NULL, NULL, 2, NULL, NULL, NULL, NULL, 1, NULL, 'معتمد', 0, NULL, '2025-11-03 19:29:12', '2025-11-03 19:33:18'),
(11, '2025-11-03', 'مصروف', 'دفع فاتورة مورد', 'دفع فاتورة #INV-2025-001 - Ziad El Rez', '200.00', 2, '1.0000', '', 'نقد', '', NULL, NULL, NULL, NULL, 10, 1, NULL, NULL, NULL, 1, NULL, 'معتمد', 0, NULL, '2025-11-03 20:10:04', '2025-11-03 20:10:04'),
(12, '2025-11-03', 'مصروف', 'دفع فاتورة مورد', 'دفع فاتورة #INV-2025-002 - Ziad El Rez', '800.00', 2, '1.0000', '', 'نقد', '', NULL, NULL, 2, NULL, NULL, 4, NULL, NULL, NULL, 1, NULL, 'معتمد', 0, NULL, '2025-11-03 20:43:18', '2025-11-03 20:43:18'),
(13, '2025-11-19', 'مصروف', 'دفع فاتورة مورد', 'دفع فاتورة #INV-2025-003 - Ziad El Rez', '250.00', 2, '1.0000', '', 'نقد', '', NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL, NULL, 1, NULL, 'معتمد', 0, NULL, '2025-11-19 20:44:55', '2025-11-19 20:44:55');

-- --------------------------------------------------------

--
-- Table structure for table `fiscal_periods`
--

CREATE TABLE `fiscal_periods` (
  `id` int(11) NOT NULL,
  `period_name` varchar(100) NOT NULL COMMENT 'اسم الفترة: Q1 2025، يناير 2025...',
  `period_type` enum('يومي','أسبوعي','شهري','ربع سنوي','سنوي') DEFAULT 'شهري',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `fiscal_year` int(4) NOT NULL,
  `is_closed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `fiscal_periods`
--

INSERT INTO `fiscal_periods` (`id`, `period_name`, `period_type`, `start_date`, `end_date`, `fiscal_year`, `is_closed`, `created_at`) VALUES
(1, 'الربع الأول 2025', 'ربع سنوي', '2025-01-01', '2025-03-31', 2025, 0, '2025-11-03 11:42:52'),
(2, 'الربع الثاني 2025', 'ربع سنوي', '2025-04-01', '2025-06-30', 2025, 0, '2025-11-03 11:42:52'),
(3, 'الربع الثالث 2025', 'ربع سنوي', '2025-07-01', '2025-09-30', 2025, 0, '2025-11-03 11:42:52'),
(4, 'الربع الرابع 2025', 'ربع سنوي', '2025-10-01', '2025-12-31', 2025, 0, '2025-11-03 11:42:52');

-- --------------------------------------------------------

--
-- Table structure for table `important_links`
--

CREATE TABLE `important_links` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name_ar` varchar(255) NOT NULL,
  `name_en` varchar(255) DEFAULT NULL,
  `description_ar` text DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `phone_2` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `address_ar` text DEFAULT NULL,
  `address_en` text DEFAULT NULL,
  `location_lat` decimal(10,8) DEFAULT NULL,
  `location_lng` decimal(11,8) DEFAULT NULL,
  `working_hours_ar` varchar(255) DEFAULT NULL,
  `working_hours_en` varchar(255) DEFAULT NULL,
  `is_government` tinyint(1) DEFAULT 0,
  `is_emergency` tinyint(1) DEFAULT 0,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `important_links`
--

INSERT INTO `important_links` (`id`, `category_id`, `name_ar`, `name_en`, `description_ar`, `description_en`, `phone`, `phone_2`, `email`, `website`, `address_ar`, `address_en`, `location_lat`, `location_lng`, `working_hours_ar`, `working_hours_en`, `is_government`, `is_emergency`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 7, 'الصليب الأحمر اللبناني', 'Lebanese Red Cross', NULL, NULL, '140', NULL, 'info@redcross.org.lb', 'https://www.redcross.org.lb', 'بيروت، لبنان', NULL, NULL, NULL, NULL, NULL, 1, 1, 1, 1, '2025-11-24 14:13:19', '2025-11-24 14:13:19'),
(2, 8, 'الدفاع المدني', 'Civil Defense', NULL, NULL, '125', NULL, NULL, NULL, 'لبنان', NULL, NULL, NULL, NULL, NULL, 1, 1, 1, 1, '2025-11-24 14:13:19', '2025-11-24 14:13:19'),
(3, 9, 'الأمن العام', 'General Security', NULL, NULL, '1717', NULL, NULL, NULL, 'لبنان', NULL, NULL, NULL, NULL, NULL, 1, 0, 1, 1, '2025-11-24 14:13:19', '2025-11-24 14:13:19'),
(4, 10, 'الطوارئ الطبية', 'Medical Emergency', NULL, NULL, '140', NULL, NULL, NULL, 'لبنان', NULL, NULL, NULL, NULL, NULL, 1, 1, 1, 0, '2025-11-24 14:13:19', '2025-11-24 14:41:38'),
(5, 2, 'غير محدد', NULL, 'مستشفى', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '24/7', NULL, 1, 1, 0, 1, '2025-11-24 18:25:08', '2025-11-24 18:42:56'),
(6, 10, 'الدفاع المدني', 'Civil Defense', 'خدمات الطوارئ والإنقاذ', NULL, '125', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 1, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(7, 10, 'الصليب الأحمر اللبناني', 'Lebanese Red Cross', 'خدمات إسعاف طارئة', NULL, '140', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 2, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(8, 10, 'قوى الأمن الداخلي', 'Internal Security Forces', 'الأمن والطوارئ', NULL, '112', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 3, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(9, 10, 'إطفائية بيروت', 'Beirut Fire Brigade', 'إطفاء الحرائق', NULL, '175', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 4, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(10, 2, 'مستشفى رفيق الحريري الحكومي', 'Rafik Hariri University Hospital', NULL, NULL, '01-830000', NULL, NULL, 'http://www.rhuh.gov.lb', 'بيروت - الجناح', NULL, NULL, NULL, NULL, NULL, 1, 0, 1, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(11, 2, 'مستشفى بيروت الحكومي', 'Beirut Governmental Hospital', NULL, NULL, '01-830000', NULL, NULL, NULL, 'بيروت - القرنطينة', NULL, NULL, NULL, NULL, NULL, 1, 0, 2, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(12, 2, 'مستشفى صيدا الحكومي', 'Saida Governmental Hospital', NULL, NULL, '07-722444', NULL, NULL, NULL, 'صيدا', NULL, NULL, NULL, NULL, NULL, 1, 0, 3, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(13, 2, 'مستشفى طرابلس الحكومي', 'Tripoli Governmental Hospital', NULL, NULL, '06-447447', NULL, NULL, NULL, 'طرابلس', NULL, NULL, NULL, NULL, NULL, 1, 0, 4, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(14, 2, 'مستشفى النبطية الحكومي', 'Nabatieh Governmental Hospital', NULL, NULL, '07-765000', NULL, NULL, NULL, 'النبطية', NULL, NULL, NULL, NULL, NULL, 1, 0, 5, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(15, 2, 'مستشفى بعلبك الحكومي', 'Baalbek Governmental Hospital', NULL, NULL, '08-370214', NULL, NULL, NULL, 'بعلبك', NULL, NULL, NULL, NULL, NULL, 1, 0, 6, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(16, 1, 'رئاسة مجلس الوزراء', 'Prime Minister Office', NULL, NULL, '01-983300', NULL, 'info@pcm.gov.lb', 'http://www.pcm.gov.lb', 'بيروت - السراي الحكومي', NULL, NULL, NULL, NULL, NULL, 1, 0, 1, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(17, 1, 'وزارة الصحة العامة', 'Ministry of Public Health', NULL, NULL, '01-615250', NULL, 'info@moph.gov.lb', 'https://www.moph.gov.lb', 'بيروت - المتحف', NULL, NULL, NULL, NULL, NULL, 1, 0, 2, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(18, 1, 'وزارة التربية والتعليم العالي', 'Ministry of Education', NULL, NULL, '01-771500', NULL, 'info@mehe.gov.lb', 'https://www.mehe.gov.lb', 'بيروت - الأونيسكو', NULL, NULL, NULL, NULL, NULL, 1, 0, 3, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(19, 1, 'وزارة الداخلية والبلديات', 'Ministry of Interior', NULL, NULL, '01-751000', NULL, 'info@interior.gov.lb', 'http://www.interior.gov.lb', 'بيروت - صنايع', NULL, NULL, NULL, NULL, NULL, 1, 0, 4, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(20, 1, 'وزارة المالية', 'Ministry of Finance', NULL, NULL, '01-956000', NULL, 'info@finance.gov.lb', 'http://www.finance.gov.lb', 'بيروت - رياض الصلح', NULL, NULL, NULL, NULL, NULL, 1, 0, 5, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(21, 1, 'وزارة الخارجية والمغتربين', 'Ministry of Foreign Affairs', NULL, NULL, '01-333000', NULL, 'protocol@mfa.gov.lb', 'http://www.mfa.gov.lb', 'بيروت - السان سيمون', NULL, NULL, NULL, NULL, NULL, 1, 0, 6, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(22, 1, 'وزارة العدل', 'Ministry of Justice', NULL, NULL, '01-422021', NULL, 'info@justice.gov.lb', 'http://www.justice.gov.lb', 'بيروت - عدلية بيروت', NULL, NULL, NULL, NULL, NULL, 1, 0, 7, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(23, 1, 'وزارة الاتصالات', 'Ministry of Telecommunications', NULL, NULL, '01-366405', NULL, 'info@mpt.gov.lb', 'http://www.mpt.gov.lb', 'بيروت - الأونيسكو', NULL, NULL, NULL, NULL, NULL, 1, 0, 8, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(24, 4, 'الجامعة اللبنانية', 'Lebanese University', NULL, NULL, '01-612830', NULL, 'info@ul.edu.lb', 'http://www.ul.edu.lb', 'بيروت - المتحف', NULL, NULL, NULL, NULL, NULL, 1, 0, 1, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(25, 5, 'الجامعة الأميركية في بيروت', 'American University of Beirut', NULL, NULL, '01-350000', NULL, 'info@aub.edu.lb', 'https://www.aub.edu.lb', 'بيروت - رأس بيروت', NULL, NULL, NULL, NULL, NULL, 0, 0, 2, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(26, 5, 'جامعة القديس يوسف', 'Saint Joseph University', NULL, NULL, '01-421000', NULL, 'info@usj.edu.lb', 'https://www.usj.edu.lb', 'بيروت - الأشرفية', NULL, NULL, NULL, NULL, NULL, 0, 0, 3, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(27, 5, 'الجامعة اللبنانية الأميركية', 'Lebanese American University', NULL, NULL, '01-786456', NULL, 'admissions@lau.edu.lb', 'https://www.lau.edu.lb', 'بيروت - بيروت', NULL, NULL, NULL, NULL, NULL, 0, 0, 4, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(28, 4, 'جامعة بيروت العربية', 'Beirut Arab University', NULL, NULL, '01-300110', NULL, 'info@bau.edu.lb', 'http://www.bau.edu.lb', 'بيروت - الطريق الجديدة', NULL, NULL, NULL, NULL, NULL, 0, 0, 5, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(29, 6, 'سفارة الولايات المتحدة الأميركية', 'US Embassy', NULL, NULL, '04-543600', NULL, 'beirutacs@state.gov', 'https://lb.usembassy.gov', 'عوكر - لبنان', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(30, 6, 'سفارة فرنسا', 'French Embassy', NULL, NULL, '01-420000', NULL, 'info@ambafrance-lb.org', 'https://lb.ambafrance.org', 'بيروت - الصيفي', NULL, NULL, NULL, NULL, NULL, 0, 0, 2, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(31, 6, 'سفارة المملكة المتحدة', 'UK Embassy', NULL, NULL, '01-960800', NULL, 'ukvisas@fco.gov.uk', 'https://www.gov.uk/world/lebanon', 'بيروت - السراي الصغير', NULL, NULL, NULL, NULL, NULL, 0, 0, 3, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(32, 6, 'سفارة ألمانيا', 'German Embassy', NULL, NULL, '01-958000', NULL, 'info@beirut.diplo.de', 'https://beirut.diplo.de', 'بيروت - الربوة', NULL, NULL, NULL, NULL, NULL, 0, 0, 4, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(33, 6, 'سفارة إيطاليا', 'Italian Embassy', NULL, NULL, '01-959501', NULL, 'ambasciata.beirut@esteri.it', 'https://ambbeirut.esteri.it', 'بيروت - الربوة', NULL, NULL, NULL, NULL, NULL, 0, 0, 5, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(34, 6, 'سفارة إسبانيا', 'Spanish Embassy', NULL, NULL, '01-800450', NULL, 'emb.beirut@maec.es', 'http://www.exteriores.gob.es/Embajadas/BEIRUT', 'بيروت - الحازمية', NULL, NULL, NULL, NULL, NULL, 0, 0, 6, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(35, 6, 'سفارة كندا', 'Canadian Embassy', NULL, NULL, '04-726700', NULL, 'beirut-cs@international.gc.ca', 'https://www.canadainternational.gc.ca/lebanon-liban', 'جل الديب - لبنان', NULL, NULL, NULL, NULL, NULL, 0, 0, 7, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(36, 6, 'سفارة روسيا', 'Russian Embassy', NULL, NULL, '01-803041', NULL, 'rusembleb@rusemb.org.lb', 'https://lebanon.mid.ru', 'بيروت - كفر عبدا', NULL, NULL, NULL, NULL, NULL, 0, 0, 8, 1, '2025-11-24 19:23:40', '2025-11-24 19:23:40'),
(37, 11, 'كهرباء لبنان', 'Electricite du Liban', NULL, NULL, '01-442820', NULL, NULL, 'http://www.edl.gov.lb', 'بيروت - الشياح', NULL, NULL, NULL, NULL, NULL, 1, 0, 1, 1, '2025-11-24 19:23:41', '2025-11-24 19:23:41'),
(38, 11, 'أوجيرو', 'Ogero', NULL, NULL, '1515', NULL, NULL, 'https://www.ogero.gov.lb', 'بيروت - الأونيسكو', NULL, NULL, NULL, NULL, NULL, 1, 0, 2, 1, '2025-11-24 19:23:41', '2025-11-24 19:23:41'),
(39, 11, 'مياه بيروت وجبل لبنان', 'Beirut Water', NULL, NULL, '01-649300', NULL, NULL, 'http://www.bmlwe.gov.lb', 'بيروت', NULL, NULL, NULL, NULL, NULL, 1, 0, 3, 1, '2025-11-24 19:23:41', '2025-11-24 19:23:41'),
(40, 11, 'مطار رفيق الحريري الدولي', 'Beirut Airport', NULL, NULL, '01-628000', NULL, NULL, 'https://www.beirutairport.gov.lb', 'بيروت - المطار', NULL, NULL, NULL, NULL, NULL, 1, 0, 4, 1, '2025-11-24 19:23:41', '2025-11-24 19:23:41'),
(41, 11, 'مرفأ بيروت', 'Port of Beirut', NULL, NULL, '01-580381', NULL, NULL, 'http://www.portdebeyrouth.com', 'بيروت - المرفأ', NULL, NULL, NULL, NULL, NULL, 1, 0, 5, 1, '2025-11-24 19:23:41', '2025-11-24 19:23:41');

-- --------------------------------------------------------

--
-- Table structure for table `important_link_categories`
--

CREATE TABLE `important_link_categories` (
  `id` int(11) NOT NULL,
  `name_ar` varchar(255) NOT NULL,
  `name_en` varchar(255) DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `color` varchar(7) DEFAULT '#3b82f6',
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `important_link_categories`
--

INSERT INTO `important_link_categories` (`id`, `name_ar`, `name_en`, `icon`, `color`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'وزارات', 'Ministries', '🏛️', '#1e40af', 1, 1, '2025-11-24 14:13:19', '2025-11-24 14:13:19'),
(2, 'مستشفيات حكومية', 'Government Hospitals', '🏥', '#dc2626', 2, 1, '2025-11-24 14:13:19', '2025-11-24 14:13:19'),
(3, 'مستشفيات خاصة', 'Private Hospitals', '🏨', '#ea580c', 3, 1, '2025-11-24 14:13:19', '2025-11-24 14:13:19'),
(4, 'جامعات حكومية', 'Public Universities', '🎓', '#2563eb', 4, 1, '2025-11-24 14:13:19', '2025-11-24 14:13:19'),
(5, 'جامعات خاصة', 'Private Universities', '📚', '#7c3aed', 5, 1, '2025-11-24 14:13:19', '2025-11-24 14:13:19'),
(6, 'سفارات', 'Embassies', '🌍', '#059669', 6, 1, '2025-11-24 14:13:19', '2025-11-24 14:13:19'),
(7, 'صليب أحمر', 'Red Cross', '➕', '#dc2626', 7, 1, '2025-11-24 14:13:19', '2025-11-24 14:13:19'),
(8, 'دفاع مدني', 'Civil Defense', '🚒', '#ea580c', 8, 1, '2025-11-24 14:13:19', '2025-11-24 14:13:19'),
(9, 'أمن عام', 'General Security', '👮', '#1e40af', 9, 1, '2025-11-24 14:13:19', '2025-11-24 14:13:19'),
(10, 'خدمات طوارئ', 'Emergency Services', '🚨', '#dc2626', 10, 1, '2025-11-24 14:13:19', '2025-11-24 14:13:19'),
(11, 'مؤسسات عامة', 'Public Institutions', '🏢', '#475569', 11, 1, '2025-11-24 14:13:19', '2025-11-24 14:13:19'),
(12, 'أخرى', 'Others', '📋', '#64748b', 12, 1, '2025-11-24 14:13:19', '2025-11-24 14:13:19');

-- --------------------------------------------------------

--
-- Table structure for table `important_link_fetch_logs`
--

CREATE TABLE `important_link_fetch_logs` (
  `id` int(11) NOT NULL,
  `source_id` int(11) NOT NULL,
  `fetch_type` enum('auto','manual') DEFAULT 'auto',
  `status` enum('success','failed','partial') DEFAULT 'success',
  `items_fetched` int(11) DEFAULT 0 COMMENT 'عدد العناصر المستخرجة',
  `items_imported` int(11) DEFAULT 0 COMMENT 'عدد العناصر المستوردة',
  `items_updated` int(11) DEFAULT 0 COMMENT 'عدد العناصر المحدثة',
  `error_message` text DEFAULT NULL,
  `execution_time` decimal(10,2) DEFAULT NULL COMMENT 'وقت التنفيذ بالثواني',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `important_link_fetch_logs`
--

INSERT INTO `important_link_fetch_logs` (`id`, `source_id`, `fetch_type`, `status`, `items_fetched`, `items_imported`, `items_updated`, `error_message`, `execution_time`, `created_at`) VALUES
(1, 1, 'auto', 'failed', 0, 0, 0, 'خطأ في الاتصال: Failed to connect to www.pcm.gov.lb port 443: Timed out', '21.57', '2025-11-24 15:02:09'),
(2, 1, 'auto', 'failed', 0, 0, 0, 'خطأ في الاتصال: Failed to connect to www.pcm.gov.lb port 443: Timed out', '21.41', '2025-11-24 15:02:59'),
(3, 4, 'auto', 'failed', 0, 0, 0, 'خطأ HTTP: 404', '0.55', '2025-11-24 15:03:00'),
(4, 4, 'auto', 'failed', 0, 0, 0, 'خطأ HTTP 404: <!DOCTYPE html>\n<html lang=\"en\">\n<head>\n<title>404 Page Not Found</title>\n<style type=\"text/css\">\n\n:', '0.26', '2025-11-24 15:04:54'),
(5, 4, 'auto', 'failed', 0, 0, 0, 'الرابط غير موجود (404). تحقق من صحة رابط API أو قد يكون الرابط قد تغير. (الاستجابة هي صفحة HTML وليست JSON - الرابط قد يكون خاطئاً)', '0.32', '2025-11-24 15:06:42'),
(6, 4, 'auto', 'failed', 0, 0, 0, 'الرابط غير موجود (404). تحقق من صحة رابط API أو قد يكون الرابط قد تغير. (الاستجابة هي صفحة HTML وليست JSON - الرابط قد يكون خاطئاً)', '0.27', '2025-11-24 15:06:51'),
(7, 4, 'auto', 'failed', 0, 0, 0, 'الرابط غير موجود (404). تحقق من صحة رابط API أو قد يكون الرابط قد تغير. (الاستجابة هي صفحة HTML وليست JSON - الرابط قد يكون خاطئاً)', '0.26', '2025-11-24 15:07:21'),
(8, 3, 'auto', 'failed', 0, 0, 0, 'رابط scraping غير محدد', '0.00', '2025-11-24 15:10:03'),
(9, 1, 'auto', 'failed', 0, 0, 0, 'خطأ في الاتصال: Connection timed out after 10009 milliseconds (URL: https://www.pcm.gov.lb/arabic/api/facilities)', '10.01', '2025-11-24 17:49:52'),
(10, 1, 'auto', 'failed', 0, 0, 0, 'خطأ في الاتصال: Connection timed out after 10009 milliseconds (URL: https://www.pcm.gov.lb/arabic/api/facilities)', '10.01', '2025-11-24 18:24:23'),
(11, 5, 'auto', 'failed', 0, 0, 0, 'خطأ في الاتصال: Failed to connect to www.tra.gov.lb port 443: Connection refused', '3.29', '2025-11-24 18:24:57'),
(12, 6, 'auto', 'success', 2296, 1, 2295, NULL, '3.66', '2025-11-24 18:25:10'),
(13, 5, 'auto', 'failed', 0, 0, 0, 'خطأ في الاتصال: Failed to connect to www.tra.gov.lb port 443: Connection refused', '3.23', '2025-11-24 18:25:40'),
(14, 8, 'auto', 'failed', 0, 0, 0, 'رابط الملف غير محدد', '0.00', '2025-11-24 18:25:44'),
(15, 3, 'auto', 'failed', 0, 0, 0, 'الرابط غير موجود (404). تحقق من صحة رابط API أو قد يكون الرابط قد تغير. (الاستجابة هي صفحة HTML وليست JSON - الرابط قد يكون خاطئاً)', '0.78', '2025-11-24 18:25:52'),
(16, 6, 'auto', 'success', 2296, 0, 2296, NULL, '4.04', '2025-11-24 18:30:50'),
(17, 8, 'auto', 'failed', 0, 0, 0, 'ملاحظة: تم تخطي رابط الملف غير محدد عنصر (بدون اسم أو فئة صحيحة)', NULL, '2025-11-24 18:42:47'),
(18, 6, 'auto', 'success', 2296, 0, 2296, NULL, '3.93', '2025-11-24 18:42:56'),
(19, 1, 'auto', 'failed', 0, 0, 0, '10.01', NULL, '2025-11-24 19:03:12'),
(20, 2, 'auto', 'failed', 0, 0, 0, '1', NULL, '2025-11-24 19:03:13'),
(21, 3, 'auto', 'failed', 0, 0, 0, '0.59', NULL, '2025-11-24 19:03:14'),
(22, 4, 'auto', 'failed', 0, 0, 0, '0.35', NULL, '2025-11-24 19:03:14'),
(23, 5, 'auto', 'failed', 0, 0, 0, '3.69', NULL, '2025-11-24 19:03:18'),
(24, 6, 'auto', 'failed', 0, 0, 0, 'ملاحظة: تم تخطي المصدر غير موجود أو غير نشط عنصر (بدون اسم أو فئة صحيحة)', NULL, '2025-11-24 19:03:18'),
(25, 7, 'auto', 'success', 0, 0, 0, NULL, '1.33', '2025-11-24 19:03:19'),
(26, 8, 'auto', 'failed', 0, 0, 0, 'ملاحظة: تم تخطي المصدر غير موجود أو غير نشط عنصر (بدون اسم أو فئة صحيحة)', NULL, '2025-11-24 19:03:19');

-- --------------------------------------------------------

--
-- Table structure for table `important_link_sources`
--

CREATE TABLE `important_link_sources` (
  `id` int(11) NOT NULL,
  `name_ar` varchar(255) NOT NULL COMMENT 'اسم المصدر بالعربي',
  `name_en` varchar(255) DEFAULT NULL COMMENT 'اسم المصدر بالإنجليزي',
  `source_type` enum('api','scraping','manual','csv_import') NOT NULL DEFAULT 'api' COMMENT 'نوع المصدر',
  `fetch_method` enum('api','html_scraper','file_import','manual') DEFAULT 'api',
  `file_format` enum('json','csv','xlsx','xls','pdf','html') DEFAULT 'json',
  `file_url` text DEFAULT NULL COMMENT 'رابط ملف للتحميل',
  `api_url` text DEFAULT NULL COMMENT 'رابط API',
  `api_key` varchar(255) DEFAULT NULL COMMENT 'مفتاح API (مشفّر)',
  `scraping_url` text DEFAULT NULL COMMENT 'رابط للـ scraping',
  `scraping_selector` text DEFAULT NULL COMMENT 'CSS selector للـ scraping',
  `category_id` int(11) DEFAULT NULL COMMENT 'الفئة الافتراضية',
  `source_category_id` int(11) DEFAULT NULL,
  `update_frequency` enum('hourly','daily','weekly','monthly','manual') DEFAULT 'daily' COMMENT 'تكرار التحديث',
  `last_update` timestamp NULL DEFAULT NULL COMMENT 'آخر تحديث',
  `next_update` timestamp NULL DEFAULT NULL COMMENT 'التحديث القادم',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'نشط/غير نشط',
  `auto_import` tinyint(1) DEFAULT 1 COMMENT 'استيراد تلقائي',
  `mapping_config` text DEFAULT NULL COMMENT 'إعدادات ربط الحقول (JSON)',
  `parser_config` text DEFAULT NULL COMMENT 'إعدادات parser (JSON)',
  `headers_config` text DEFAULT NULL COMMENT 'HTTP Headers (JSON)',
  `error_log` text DEFAULT NULL COMMENT 'سجل الأخطاء',
  `success_count` int(11) DEFAULT 0 COMMENT 'عدد النجاحات',
  `error_count` int(11) DEFAULT 0 COMMENT 'عدد الأخطاء',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `important_link_sources`
--

INSERT INTO `important_link_sources` (`id`, `name_ar`, `name_en`, `source_type`, `fetch_method`, `file_format`, `file_url`, `api_url`, `api_key`, `scraping_url`, `scraping_selector`, `category_id`, `source_category_id`, `update_frequency`, `last_update`, `next_update`, `is_active`, `auto_import`, `mapping_config`, `parser_config`, `headers_config`, `error_log`, `success_count`, `error_count`, `created_at`, `updated_at`) VALUES
(1, 'دليل الحكومة اللبنانية', 'Lebanese Government Directory', 'api', 'api', 'json', NULL, 'https://www.pcm.gov.lb/arabic/api/facilities', NULL, NULL, NULL, NULL, NULL, 'weekly', '2025-11-24 19:03:12', '2025-12-01 18:03:12', 0, 1, NULL, NULL, NULL, 'معطل: API غير موجود أو الموقع لا يستجيب. يحتاج مصدر بيانات بديل.', 0, 5, '2025-11-24 15:00:39', '2025-11-24 19:23:25'),
(2, 'وزارة الصحة العامة', 'Ministry of Public Health', 'api', 'api', 'json', NULL, 'https://www.moph.gov.lb/api/hospitals', NULL, NULL, NULL, 2, NULL, 'monthly', '2025-11-24 19:03:13', '2025-12-24 18:03:13', 0, 1, NULL, NULL, NULL, 'معطل: API غير موجود أو الموقع لا يستجيب. يحتاج مصدر بيانات بديل.', 0, 1, '2025-11-24 15:00:39', '2025-11-24 19:23:25'),
(3, 'وزارة التربية والتعليم', 'Ministry of Education', 'scraping', 'html_scraper', 'json', NULL, NULL, NULL, 'https://www.mehe.gov.lb/universities', NULL, NULL, NULL, 'monthly', '2025-11-24 19:03:14', '2025-12-24 18:03:14', 0, 0, NULL, NULL, NULL, 'معطل: الرابط يعيد HTML وليس JSON. يحتاج تحويل إلى scraping مع selectors صحيحة.', 0, 3, '2025-11-24 15:00:39', '2025-11-24 19:23:25'),
(4, 'السفارات في لبنان', 'Embassies in Lebanon', 'api', 'api', 'json', NULL, 'https://www.general-security.gov.lb/api/embassies', NULL, NULL, NULL, 6, NULL, 'monthly', '2025-11-24 19:03:14', '2025-12-24 18:03:14', 0, 1, NULL, NULL, NULL, 'معطل: API غير موجود أو الموقع لا يستجيب. يحتاج مصدر بيانات بديل.', 0, 6, '2025-11-24 15:00:39', '2025-11-24 19:23:25'),
(5, 'دليل الحكومة اللبنانية - TRA', 'Lebanese Government Directory - TRA', 'scraping', 'html_scraper', 'html', NULL, NULL, NULL, 'https://www.tra.gov.lb/useful-links-governmental-institutions', '{\"item_selector\": \"//table//tr[position()>1]\", \"fields\": {\"name_ar\": \".//td[1]\", \"website\": \".//td[2]//a/@href\", \"description_ar\": \".//td[3]\"}}', 1, 1, 'monthly', '2025-11-24 19:03:18', '2025-12-24 18:03:18', 0, 1, NULL, NULL, NULL, 'معطل: API غير موجود أو الموقع لا يستجيب. يحتاج مصدر بيانات بديل.', 0, 3, '2025-11-24 18:23:41', '2025-11-24 19:23:25'),
(6, 'مستشفيات حكومية - Open Data Lebanon', 'Public Hospitals - Open Data', 'api', 'file_import', 'xlsx', 'https://www.opendatalebanon.org/job/list-of-public-hospitals-in-lebanon/', 'https://www.opendatalebanon.org/job/list-of-public-hospitals-in-lebanon/', NULL, NULL, NULL, 2, 2, 'monthly', '2025-11-24 19:03:18', '2025-12-24 18:03:18', 0, 1, NULL, NULL, NULL, 'المصدر غير موجود أو غير نشط', 3, 1, '2025-11-24 18:23:41', '2025-11-24 19:03:18'),
(7, 'مستشفيات - وزارة الصحة', 'Hospitals - MOPH', 'scraping', 'html_scraper', 'html', NULL, NULL, NULL, 'https://www.moph.gov.lb/en/HealthFacilities/index/3/188/8?facility_type=1', '{\r\n        \"item_selector\": \"//table//tr[position()>1]\",\r\n        \"fields\": {\r\n            \"name_en\": \".//td[1]//a\",\r\n            \"name_ar\": \".//td[3]\",\r\n            \"phone\": \".//td[5]\",\r\n            \"address_ar\": \".//td[4]\"\r\n        }\r\n    }', 2, 2, 'monthly', '2025-11-24 19:03:19', '2025-12-24 18:03:19', 1, 1, '{\r\n        \"name_ar\": \"name_ar\",\r\n        \"name_en\": \"name_en\",\r\n        \"phone\": \"phone\",\r\n        \"address_ar\": \"address_ar\"\r\n    }', NULL, NULL, 'المصدر الوحيد العامل - تم إصلاح selectors بناءً على فحص الصفحة', 1, 0, '2025-11-24 18:23:41', '2025-11-24 19:23:25'),
(8, 'السفارات في لبنان - AUB', 'Embassies in Lebanon - AUB', 'api', 'file_import', 'pdf', NULL, NULL, NULL, NULL, NULL, 6, 4, 'monthly', '2025-11-24 19:03:19', '2025-12-24 18:03:19', 0, 1, NULL, NULL, NULL, 'المصدر غير موجود أو غير نشط', 0, 3, '2025-11-24 18:23:41', '2025-11-24 19:03:19');

-- --------------------------------------------------------

--
-- Table structure for table `initiative_activities`
--

CREATE TABLE `initiative_activities` (
  `id` int(11) NOT NULL,
  `initiative_id` int(11) NOT NULL,
  `activity_name` varchar(255) NOT NULL,
  `activity_description` text DEFAULT NULL,
  `activity_date` date NOT NULL,
  `activity_time` time DEFAULT NULL,
  `activity_location` varchar(255) DEFAULT NULL,
  `required_volunteers` int(11) DEFAULT 0,
  `registered_volunteers` int(11) DEFAULT 0,
  `activity_status` enum('مجدولة','قيد التنفيذ','مكتملة','ملغية') DEFAULT 'مجدولة',
  `activity_notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `initiative_evaluations`
--

CREATE TABLE `initiative_evaluations` (
  `id` int(11) NOT NULL,
  `initiative_id` int(11) NOT NULL,
  `evaluator_name` varchar(255) NOT NULL,
  `evaluator_email` varchar(255) DEFAULT NULL,
  `evaluator_type` enum('متطوع','مستفيد','مراقب خارجي','إدارة البلدية') NOT NULL,
  `overall_rating` int(11) NOT NULL CHECK (`overall_rating` >= 1 and `overall_rating` <= 5),
  `organization_rating` int(11) DEFAULT NULL CHECK (`organization_rating` >= 1 and `organization_rating` <= 5),
  `impact_rating` int(11) DEFAULT NULL CHECK (`impact_rating` >= 1 and `impact_rating` <= 5),
  `communication_rating` int(11) DEFAULT NULL CHECK (`communication_rating` >= 1 and `communication_rating` <= 5),
  `feedback_text` text DEFAULT NULL,
  `suggestions` text DEFAULT NULL,
  `would_participate_again` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `initiative_images`
--

CREATE TABLE `initiative_images` (
  `id` int(11) NOT NULL,
  `initiative_id` int(11) NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `image_name` varchar(255) NOT NULL,
  `image_description` text DEFAULT NULL,
  `image_type` enum('رئيسية','معرض','نشاط','نتائج') DEFAULT 'معرض',
  `display_order` int(11) DEFAULT 0,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `initiative_images`
--

INSERT INTO `initiative_images` (`id`, `initiative_id`, `image_path`, `image_name`, `image_description`, `image_type`, `display_order`, `file_size`, `uploaded_by`, `is_active`, `created_at`) VALUES
(9, 12, 'initiative_12_gallery_1750289992_0.png', 'Screenshot (2).png', NULL, 'معرض', 0, 1564315, 1, 1, '2025-06-18 23:39:52'),
(10, 12, 'initiative_12_gallery_1750289992_1.png', 'Screenshot 2024-07-11 153349.png', NULL, 'معرض', 1, 661985, 1, 1, '2025-06-18 23:39:52'),
(12, 12, 'initiative_12_gallery_1752316219_0.png', 'logo11.png', NULL, 'معرض', 0, 316454, 1, 1, '2025-07-12 10:30:19');

-- --------------------------------------------------------

--
-- Table structure for table `initiative_volunteers`
--

CREATE TABLE `initiative_volunteers` (
  `id` int(11) NOT NULL,
  `initiative_id` int(11) NOT NULL,
  `volunteer_name` varchar(255) NOT NULL,
  `volunteer_phone` varchar(20) NOT NULL,
  `volunteer_email` varchar(255) DEFAULT NULL,
  `volunteer_age` int(11) DEFAULT NULL,
  `volunteer_gender` enum('ذكر','أنثى') NOT NULL,
  `volunteer_address` text DEFAULT NULL,
  `volunteer_skills` text DEFAULT NULL,
  `volunteer_experience` text DEFAULT NULL,
  `motivation` text DEFAULT NULL,
  `availability` text DEFAULT NULL,
  `emergency_contact_name` varchar(255) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `registration_status` enum('قيد المراجعة','مقبول','مرفوض','في قائمة الانتظار') DEFAULT 'قيد المراجعة',
  `approval_date` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `attendance_count` int(11) DEFAULT 0,
  `performance_rating` enum('ممتاز','جيد جداً','جيد','مقبول','ضعيف') DEFAULT NULL,
  `certificate_issued` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `initiative_volunteers`
--

INSERT INTO `initiative_volunteers` (`id`, `initiative_id`, `volunteer_name`, `volunteer_phone`, `volunteer_email`, `volunteer_age`, `volunteer_gender`, `volunteer_address`, `volunteer_skills`, `volunteer_experience`, `motivation`, `availability`, `emergency_contact_name`, `emergency_contact_phone`, `registration_status`, `approval_date`, `approved_by`, `rejection_reason`, `notes`, `attendance_count`, `performance_rating`, `certificate_issued`, `created_at`, `updated_at`, `email`, `phone`) VALUES
(2, 12, 'wassim', '03495685', 'imathacademyleb@gmail.com', NULL, 'ذكر', NULL, NULL, 'nothin', 'love', NULL, NULL, NULL, 'مقبول', NULL, NULL, NULL, NULL, 0, NULL, 0, '2025-06-13 20:54:18', '2025-06-13 20:54:18', '', ''),
(4, 12, 'Ziad El Rez', '', NULL, NULL, 'ذكر', NULL, NULL, 'sd', 'sdsd', NULL, NULL, NULL, 'مقبول', NULL, NULL, NULL, NULL, 0, NULL, 0, '2025-06-20 09:52:23', '2025-06-20 09:52:23', 'ziadelrez@gmail.com', '03495685'),
(5, 12, 'wassim', '', NULL, NULL, 'ذكر', NULL, NULL, 'dasdd', 'asdsd', NULL, NULL, NULL, 'مقبول', NULL, NULL, NULL, NULL, 0, NULL, 0, '2025-06-20 09:53:20', '2025-06-20 09:53:20', 'wassiom@gmail.com', '03485968'),
(6, 12, 'بشيع', '', NULL, NULL, 'ذكر', NULL, NULL, 'sds', 'dsdsd', NULL, NULL, NULL, 'مقبول', NULL, NULL, NULL, NULL, 0, NULL, 0, '2025-06-20 11:16:20', '2025-06-20 11:16:20', 'sami@gmial.com', '4152484'),
(7, 12, 'jamil', '', NULL, NULL, 'ذكر', NULL, NULL, 'dsf', 'sdfdsf', NULL, NULL, NULL, 'مقبول', NULL, NULL, NULL, NULL, 0, NULL, 0, '2025-06-20 19:32:11', '2025-06-20 19:32:11', 'jamil@gmail.com', '4512145'),
(8, 12, 'fadi', '', NULL, NULL, 'ذكر', NULL, NULL, '346', 'ert', NULL, NULL, NULL, 'مقبول', NULL, NULL, NULL, NULL, 0, NULL, 0, '2025-11-01 22:19:25', '2025-11-01 22:19:25', 'fadi@gmail.com', '435564');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_payments`
--

CREATE TABLE `invoice_payments` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `committee_id` int(11) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `payment_amount` decimal(15,2) NOT NULL,
  `payment_method` enum('نقد','شيك','تحويل مصرفي','بطاقة ائتمان','أخرى') DEFAULT 'نقد',
  `reference_number` varchar(100) DEFAULT NULL COMMENT 'رقم المرجع/الشيك',
  `bank_name` varchar(255) DEFAULT NULL,
  `financial_transaction_id` int(11) DEFAULT NULL COMMENT 'ربط مع المعاملة المالية',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoice_payments`
--

INSERT INTO `invoice_payments` (`id`, `invoice_id`, `committee_id`, `payment_date`, `payment_amount`, `payment_method`, `reference_number`, `bank_name`, `financial_transaction_id`, `notes`, `created_by`, `created_at`) VALUES
(1, 1, NULL, '2025-11-03', '200.00', 'نقد', '', '', 11, '', 1, '2025-11-03 20:10:04'),
(2, 4, NULL, '2025-11-03', '800.00', 'نقد', '', '', 12, '', 1, '2025-11-03 20:43:18'),
(3, 5, 4, '2025-11-19', '250.00', 'نقد', '', '', 13, '', 1, '2025-11-19 20:44:55');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `username`, `ip_address`, `user_agent`, `success`, `attempted_at`, `user_id`) VALUES
(1, 'test_user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 0, '2025-11-15 01:17:06', NULL),
(2, 'test_user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 0, '2025-11-15 01:19:50', NULL),
(3, 'test_user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 0, '2025-11-15 01:20:47', NULL),
(4, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-17 09:12:51', 1),
(5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-17 19:11:17', 1),
(6, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-17 20:00:40', 1),
(7, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-17 22:02:45', 1),
(8, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 0, '2025-11-18 00:28:36', NULL),
(9, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-18 00:28:42', 1),
(10, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-18 00:35:58', 1),
(11, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-18 22:57:45', 1),
(12, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-18 23:34:04', 1),
(13, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-19 01:44:07', 1),
(14, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-19 21:35:18', 1),
(15, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-20 00:03:54', 1),
(16, 'ziad', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-20 00:06:17', 49),
(17, 'ziad', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-20 00:16:45', 49),
(18, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-20 00:35:00', 1),
(19, 'ziad', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-20 01:00:12', 49),
(20, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-20 01:01:08', 1),
(21, 'ziad', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-20 22:00:56', 49),
(22, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-20 22:01:23', 1),
(23, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 0, '2025-11-21 22:35:27', NULL),
(24, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-21 22:35:39', 1),
(25, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-22 21:49:06', 1),
(26, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-22 23:52:47', 1),
(27, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-23 00:28:49', 1),
(28, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-23 00:36:01', 1),
(29, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-23 00:55:56', 1),
(30, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-23 01:00:06', 1),
(31, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-24 11:47:37', 1),
(32, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-24 15:15:13', 1),
(33, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-24 16:34:51', 1),
(34, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-24 19:49:13', 1),
(35, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 0, '2025-11-24 20:24:27', NULL),
(36, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-24 20:24:33', 1),
(37, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, '2025-11-25 00:18:57', 1);

-- --------------------------------------------------------

--
-- Table structure for table `magic_links`
--

CREATE TABLE `magic_links` (
  `id` int(11) NOT NULL,
  `citizen_id` int(11) NOT NULL COMMENT 'معرف المواطن',
  `token` varchar(64) NOT NULL COMMENT 'الرمز الفريد للرابط',
  `phone` varchar(20) NOT NULL COMMENT 'رقم الهاتف',
  `used` tinyint(1) DEFAULT 0 COMMENT 'تم استخدام الرابط',
  `used_at` timestamp NULL DEFAULT NULL COMMENT 'تاريخ الاستخدام',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'عنوان IP',
  `user_agent` text DEFAULT NULL COMMENT 'معلومات المتصفح',
  `expires_at` datetime NOT NULL COMMENT 'تاريخ انتهاء الصلاحية',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ الإنشاء'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='روابط الدخول السحرية';

--
-- Dumping data for table `magic_links`
--

INSERT INTO `magic_links` (`id`, `citizen_id`, `token`, `phone`, `used`, `used_at`, `ip_address`, `user_agent`, `expires_at`, `created_at`) VALUES
(1, 1, '8688c6f132e38e4ef4e8dcdfd842df09de3dbced98b96d2e0fd4be4012122ae7', '03670065', 1, '2025-11-11 19:01:40', NULL, NULL, '2025-11-12 20:42:17', '2025-11-11 18:42:17'),
(2, 1, '2590416aca130bae0a70a33d3d94aa5dbc3687b8c6d539b5d5c8a5766ccd2330', '03670065', 0, NULL, NULL, NULL, '2025-11-12 20:48:57', '2025-11-11 18:48:57');

-- --------------------------------------------------------

--
-- Table structure for table `map_settings`
--

CREATE TABLE `map_settings` (
  `id` int(11) NOT NULL,
  `setting_name` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_description` text DEFAULT NULL,
  `data_type` enum('string','number','boolean','json') DEFAULT 'string',
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `map_settings`
--

INSERT INTO `map_settings` (`id`, `setting_name`, `setting_value`, `setting_description`, `data_type`, `is_public`, `created_at`, `updated_at`) VALUES
(1, 'map_center_lat', '33.8869', 'خط العرض لمركز الخريطة (تكريت)', 'number', 1, '2025-06-18 13:02:13', '2025-06-18 16:30:51'),
(2, 'map_center_lng', '35.5131', 'خط الطول لمركز الخريطة (تكريت)', 'number', 1, '2025-06-18 13:02:13', '2025-06-18 16:30:51'),
(3, 'map_zoom_level', '9', 'مستوى التكبير الافتراضي للخريطة', 'number', 1, '2025-06-18 13:02:13', '2025-06-18 16:30:51'),
(4, 'google_maps_api_key', '', 'مفتاح Google Maps API', 'string', 0, '2025-06-18 13:02:13', '2025-06-18 13:02:13'),
(5, 'enable_user_location', '1', 'تفعيل تحديد موقع المستخدم', 'boolean', 1, '2025-06-18 13:02:13', '2025-06-18 13:02:13'),
(6, 'show_directions', '1', 'عرض خاصية الاتجاهات', 'boolean', 1, '2025-06-18 13:02:13', '2025-06-18 13:02:13'),
(7, 'enable_clustering', '1', 'تفعيل تجميع النقاط المتقاربة', 'boolean', 1, '2025-06-18 13:02:13', '2025-06-18 13:02:13'),
(8, 'max_facilities_per_page', '50', 'الحد الأقصى للمرافق في الصفحة الواحدة', 'number', 0, '2025-06-18 13:02:13', '2025-06-18 13:02:13'),
(9, 'enable_ratings', '1', 'تفعيل نظام التقييمات', 'boolean', 1, '2025-06-18 13:02:13', '2025-06-18 13:02:13'),
(10, 'auto_approve_ratings', '0', 'الموافقة التلقائية على التقييمات', 'boolean', 0, '2025-06-18 13:02:13', '2025-06-18 13:02:13'),
(11, 'map_style', 'default', 'نمط الخريطة', 'string', 1, '2025-06-18 13:02:13', '2025-06-18 13:02:13'),
(12, 'enable_search', '1', 'تفعيل البحث في الخريطة', 'boolean', 1, '2025-06-18 13:02:13', '2025-06-18 13:02:13'),
(13, 'enable_filters', '1', 'تفعيل فلاتر الفئات', 'boolean', 1, '2025-06-18 13:02:13', '2025-06-18 13:02:13'),
(14, 'default_language', 'ar', 'اللغة الافتراضية (ar/en)', 'string', 1, '2025-06-18 13:02:13', '2025-06-18 13:02:13');

-- --------------------------------------------------------

--
-- Table structure for table `municipality_assets`
--

CREATE TABLE `municipality_assets` (
  `id` int(11) NOT NULL,
  `asset_name` varchar(255) NOT NULL,
  `asset_description` text DEFAULT NULL,
  `asset_type` enum('منقول','غير منقول') NOT NULL,
  `asset_category` varchar(100) DEFAULT NULL,
  `asset_location` varchar(255) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_cost` decimal(15,2) DEFAULT NULL,
  `current_value` decimal(15,2) DEFAULT NULL,
  `asset_condition` enum('ممتاز','جيد','مقبول','يحتاج صيانة','تالف') DEFAULT 'جيد',
  `responsible_department_id` int(11) DEFAULT NULL,
  `asset_images` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `municipality_resources`
--

CREATE TABLE `municipality_resources` (
  `id` int(11) NOT NULL,
  `resource_name` varchar(255) NOT NULL,
  `resource_type` enum('رسوم','جباية','هبات','عوائد مشاريع','موازنة حكومية','شراكات') NOT NULL,
  `resource_description` text DEFAULT NULL,
  `annual_target` decimal(15,2) DEFAULT NULL,
  `collected_amount` decimal(15,2) DEFAULT 0.00,
  `collection_year` int(11) NOT NULL,
  `resource_status` enum('نشط','معلق','متوقف') DEFAULT 'نشط',
  `responsible_department_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `municipal_committees`
--

CREATE TABLE `municipal_committees` (
  `id` int(11) NOT NULL,
  `committee_name` varchar(255) NOT NULL,
  `committee_description` text DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `committee_type` enum('دائمة','مؤقتة','استشارية','تنفيذية') DEFAULT 'دائمة',
  `chairman_id` int(11) DEFAULT NULL,
  `secretary_id` int(11) DEFAULT NULL,
  `formation_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `meeting_frequency` enum('أسبوعية','شهرية','ربع سنوية','حسب الحاجة') DEFAULT 'شهرية',
  `responsibilities` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `municipal_committees`
--

INSERT INTO `municipal_committees` (`id`, `committee_name`, `committee_description`, `department_id`, `committee_type`, `chairman_id`, `secretary_id`, `formation_date`, `is_active`, `meeting_frequency`, `responsibilities`, `created_at`) VALUES
(1, 'لجنة الشؤون المالية', 'مراجعة الميزانيات والأمور المالية للبلدية', NULL, 'دائمة', NULL, NULL, '2025-06-10', 1, 'شهرية', 'مراجعة الميزانية والموافقة على المصروفات', '2025-06-09 21:12:37'),
(2, 'لجنة التخطيط والتطوير', 'التخطيط للمشاريع التطويرية ومتابعة تنفيذها', NULL, 'دائمة', NULL, NULL, '2025-06-10', 1, 'شهرية', 'وضع خطط التطوير ومراجعة المشاريع', '2025-06-09 21:12:37'),
(3, 'لجنة البيئة والنظافة', 'الإشراف على شؤون البيئة والنظافة العامة', 1, 'دائمة', NULL, NULL, '2025-06-10', 1, 'شهرية', 'مراقبة النظافة وحماية البيئة', '2025-06-09 21:12:37'),
(4, 'لجنة الخدمات العامة', 'الإشراف على الخدمات المقدمة للمواطنين', NULL, 'دائمة', NULL, NULL, '2025-06-10', 1, 'شهرية', 'تطوير الخدمات ومتابعة جودتها', '2025-06-09 21:12:37'),
(5, 'لجنة الطوارئ', 'التعامل مع الحالات الطارئة والأزمات', NULL, 'مؤقتة', NULL, NULL, '2025-06-10', 1, 'حسب الحاجة', 'إدارة الأزمات والطوارئ', '2025-06-09 21:12:37');

-- --------------------------------------------------------

--
-- Table structure for table `municipal_decisions`
--

CREATE TABLE `municipal_decisions` (
  `id` int(11) NOT NULL,
  `decision_number` varchar(100) NOT NULL,
  `session_id` int(11) NOT NULL,
  `decision_title` varchar(255) NOT NULL,
  `decision_text` text NOT NULL,
  `decision_type` enum('إداري','مالي','فني','قانوني','تنظيمي','أخرى') DEFAULT 'إداري',
  `priority_level` enum('عادي','مهم','عاجل','طارئ') DEFAULT 'عادي',
  `voting_result` enum('بالإجماع','بالأغلبية','مرفوض','مؤجل') DEFAULT 'بالأغلبية',
  `votes_for` int(11) DEFAULT 0,
  `votes_against` int(11) DEFAULT 0,
  `votes_abstain` int(11) DEFAULT 0,
  `implementation_deadline` date DEFAULT NULL,
  `responsible_department_id` int(11) DEFAULT NULL,
  `responsible_person_id` int(11) DEFAULT NULL,
  `implementation_status` enum('قيد الانتظار','قيد التنفيذ','مكتمل','متأخر','معلق','ملغي') DEFAULT 'قيد الانتظار',
  `implementation_progress` int(11) DEFAULT 0,
  `implementation_notes` text DEFAULT NULL,
  `budget_required` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `municipal_sessions`
--

CREATE TABLE `municipal_sessions` (
  `id` int(11) NOT NULL,
  `session_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_title` varchar(255) NOT NULL,
  `session_type` enum('عادية','طارئة','استثنائية','لجنة') DEFAULT 'عادية',
  `committee_id` int(11) DEFAULT NULL,
  `session_date` date NOT NULL,
  `session_time` time NOT NULL,
  `location` varchar(255) DEFAULT 'قاعة الاجتماعات الرئيسية',
  `agenda` text NOT NULL,
  `session_status` enum('مجدولة','جارية','مكتملة','مؤجلة','ملغية') DEFAULT 'مجدولة',
  `chairperson_id` int(11) DEFAULT NULL,
  `secretary_id` int(11) DEFAULT NULL,
  `quorum_required` int(11) DEFAULT 5,
  `attendees_count` int(11) DEFAULT 0,
  `session_minutes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `news_activities`
--

CREATE TABLE `news_activities` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `news_type` enum('رسمية','مناسبات محلية','أنشطة اجتماعية','إعلام رسمي') DEFAULT 'رسمية',
  `featured_image` varchar(255) DEFAULT NULL,
  `publish_date` date NOT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `is_published` tinyint(1) DEFAULT 1,
  `views_count` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `news_activities`
--

INSERT INTO `news_activities` (`id`, `title`, `content`, `news_type`, `featured_image`, `publish_date`, `is_featured`, `is_published`, `views_count`, `created_by`, `created_at`) VALUES
(2, 'حملة تنظيف واسعة في أحياء المدينة', 'انطلقت حملة تنظيف شاملة في جميع أحياء مدينة تكريت بمشاركة فرق البلدية والمتطوعين، تهدف الحملة إلى تحسين المظهر العام للمدينة.', 'أنشطة اجتماعية', 'news_gallery_1750249063_6951.jpg', '2024-01-08', 1, 1, 3, NULL, '2025-06-10 05:35:28'),
(3, 'الاحتفال باليوم العالمي للبيئة', 'نظمت بلدية تكريت فعالية خاصة بمناسبة اليوم العالمي للبيئة، تضمنت ورش توعوية وحملة زراعة أشجار في الحدائق العامة.', 'مناسبات محلية', 'news_gallery_1750249063_4259.jpg', '2024-01-05', 1, 1, 1, NULL, '2025-06-10 05:35:28'),
(4, 'افتتاح مشروع تطوير شارع الجمهورية', 'تم افتتاح مشروع تطوير وتأهيل شارع الجمهورية بحضور محافظ صلاح الدين ورئيس البلدية، ويشمل المشروع إعادة تأهيل الطريق وإنشاء أرصفة جديدة وتحسين الإنارة.', 'رسمية', 'news_featured_1750288916_1034.png', '2024-01-10', 1, 1, 23, NULL, '2025-06-10 05:45:48'),
(5, 'حملة تنظيف واسعة في أحياء المدينة', 'انطلقت حملة تنظيف شاملة في جميع أحياء مدينة تكريت بمشاركة فرق البلدية والمتطوعين، تهدف الحملة إلى تحسين المظهر العام للمدينة.', 'أنشطة اجتماعية', 'news_featured_1750249159_7418.jpg', '2024-01-08', 1, 1, 0, NULL, '2025-06-10 05:45:48');

-- --------------------------------------------------------

--
-- Table structure for table `news_images`
--

CREATE TABLE `news_images` (
  `id` int(11) NOT NULL,
  `news_id` int(11) NOT NULL,
  `image_filename` varchar(255) NOT NULL,
  `image_title` varchar(255) DEFAULT NULL,
  `image_description` text DEFAULT NULL,
  `image_type` enum('gallery','content','attachment') DEFAULT 'gallery' COMMENT 'نوع الصورة',
  `display_order` int(11) DEFAULT 0,
  `image_size` int(11) DEFAULT NULL COMMENT 'حجم الصورة بالبايت',
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `uploaded_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `news_images`
--

INSERT INTO `news_images` (`id`, `news_id`, `image_filename`, `image_title`, `image_description`, `image_type`, `display_order`, `image_size`, `upload_date`, `uploaded_by`, `is_active`, `created_at`, `updated_at`) VALUES
(10, 4, 'news_gallery_1752183167_2105.png', NULL, NULL, 'gallery', 1, 140205, '2025-07-10 21:32:47', 1, 1, '2025-07-10 21:32:47', '2025-07-10 21:32:47'),
(11, 4, 'news_gallery_1752183167_4524.png', NULL, NULL, 'gallery', 2, 316454, '2025-07-10 21:32:47', 1, 1, '2025-07-10 21:32:47', '2025-07-10 21:32:47'),
(12, 4, 'news_gallery_1752183167_2718.png', NULL, NULL, 'gallery', 3, 227650, '2025-07-10 21:32:47', 1, 1, '2025-07-10 21:32:47', '2025-07-10 21:32:47'),
(14, 4, 'news_gallery_1752185065_8637.png', NULL, NULL, 'gallery', 4, 174933, '2025-07-10 22:04:25', 1, 1, '2025-07-10 22:04:25', '2025-07-10 22:04:25'),
(15, 4, 'news_gallery_1752185065_6478.png', NULL, NULL, 'gallery', 5, 326898, '2025-07-10 22:04:25', 1, 1, '2025-07-10 22:04:25', '2025-07-10 22:04:25');

-- --------------------------------------------------------

--
-- Table structure for table `news_image_settings`
--

CREATE TABLE `news_image_settings` (
  `id` int(11) NOT NULL,
  `setting_name` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `news_image_settings`
--

INSERT INTO `news_image_settings` (`id`, `setting_name`, `setting_value`, `setting_description`, `created_at`, `updated_at`) VALUES
(1, 'max_file_size', '5242880', 'الحد الأقصى لحجم الصورة بالبايت (5MB)', '2025-06-18 11:54:48', '2025-06-18 11:54:48'),
(2, 'allowed_extensions', 'jpg,jpeg,png,gif,webp', 'امتدادات الصور المسموحة', '2025-06-18 11:54:48', '2025-06-18 11:54:48'),
(3, 'featured_image_width', '800', 'عرض الصورة الرئيسية بالبكسل', '2025-06-18 11:54:48', '2025-06-18 11:54:48'),
(4, 'featured_image_height', '600', 'ارتفاع الصورة الرئيسية بالبكسل', '2025-06-18 11:54:48', '2025-06-18 11:54:48'),
(5, 'gallery_image_width', '600', 'عرض صور المعرض بالبكسل', '2025-06-18 11:54:48', '2025-06-18 11:54:48'),
(6, 'gallery_image_height', '400', 'ارتفاع صور المعرض بالبكسل', '2025-06-18 11:54:48', '2025-06-18 11:54:48'),
(7, 'thumbnail_width', '150', 'عرض الصور المصغرة بالبكسل', '2025-06-18 11:54:48', '2025-06-18 11:54:48'),
(8, 'thumbnail_height', '100', 'ارتفاع الصور المصغرة بالبكسل', '2025-06-18 11:54:48', '2025-06-18 11:54:48'),
(9, 'max_images_per_news', '10', 'الحد الأقصى لعدد الصور لكل خبر', '2025-06-18 11:54:48', '2025-06-18 11:54:48'),
(10, 'auto_generate_thumbnails', '1', 'إنشاء صور مصغرة تلقائياً', '2025-06-18 11:54:48', '2025-06-18 11:54:48'),
(11, 'watermark_enabled', '0', 'تفعيل العلامة المائية', '2025-06-18 11:54:48', '2025-06-18 11:54:48'),
(12, 'compress_images', '1', 'ضغط الصور تلقائياً', '2025-06-18 11:54:48', '2025-06-18 11:54:48');

-- --------------------------------------------------------

--
-- Table structure for table `notification_preferences`
--

CREATE TABLE `notification_preferences` (
  `id` int(11) NOT NULL,
  `citizen_id` int(11) NOT NULL COMMENT 'معرف المواطن',
  `whatsapp_enabled` tinyint(1) DEFAULT 1 COMMENT 'تفعيل WhatsApp',
  `website_enabled` tinyint(1) DEFAULT 1 COMMENT 'تفعيل إشعارات الموقع',
  `notify_on_status_change` tinyint(1) DEFAULT 1 COMMENT 'إشعار عند تغيير حالة الطلب',
  `notify_on_new_message` tinyint(1) DEFAULT 1 COMMENT 'إشعار عند رسالة جديدة',
  `notify_on_general_news` tinyint(1) DEFAULT 1 COMMENT 'إشعار بالأخبار العامة',
  `notify_on_completion` tinyint(1) DEFAULT 1 COMMENT 'إشعار عند إنجاز الطلب',
  `notify_on_reminder` tinyint(1) DEFAULT 1 COMMENT 'إشعار بالتذكيرات',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'تاريخ التحديث'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='إعدادات الإشعارات';

--
-- Dumping data for table `notification_preferences`
--

INSERT INTO `notification_preferences` (`id`, `citizen_id`, `whatsapp_enabled`, `website_enabled`, `notify_on_status_change`, `notify_on_new_message`, `notify_on_general_news`, `notify_on_completion`, `notify_on_reminder`, `updated_at`) VALUES
(1, 1, 1, 1, 1, 1, 1, 1, 1, '2025-11-11 18:42:17');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `permission_name` varchar(100) NOT NULL,
  `display_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL COMMENT 'الفئة: general_admin, finance, projects, etc.',
  `module_name` varchar(50) NOT NULL,
  `page_url` varchar(255) DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `parent_permission_id` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `permission_name`, `display_name`, `description`, `category`, `module_name`, `page_url`, `icon`, `parent_permission_id`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(27, 'municipality_view', 'عرض معلومات البلدية', 'عرض معلومات وإحصائيات البلدية', 'general_admin', 'municipality', 'modules/municipality_management.php', '🏛️', NULL, 10, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(28, 'municipality_edit', 'تعديل معلومات البلدية', 'تعديل بيانات البلدية الأساسية', 'general_admin', 'municipality', 'modules/municipality_management.php', '✏️', NULL, 11, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(29, 'council_view', 'عرض أعضاء المجلس', 'عرض قائمة أعضاء المجلس البلدي', 'general_admin', 'council', 'modules/council_management.php', '👥', NULL, 20, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(30, 'council_add', 'إضافة عضو مجلس', 'إضافة عضو جديد للمجلس البلدي', 'general_admin', 'council', 'modules/council_management.php', '➕', NULL, 21, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(31, 'council_edit', 'تعديل بيانات الأعضاء', 'تعديل معلومات أعضاء المجلس', 'general_admin', 'council', 'modules/council_management.php', '✏️', NULL, 22, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(32, 'council_delete', 'حذف عضو مجلس', 'حذف عضو من المجلس البلدي', 'general_admin', 'council', 'modules/council_management.php', '🗑️', NULL, 23, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(33, 'hr_view', 'عرض الموظفين', 'عرض قائمة الموظفين والمعلومات', 'general_admin', 'hr', 'modules/hr.php', '👔', NULL, 30, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(34, 'hr_add', 'إضافة موظف', 'إضافة موظف جديد للنظام', 'general_admin', 'hr', 'modules/hr.php', '➕', NULL, 31, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(35, 'hr_edit', 'تعديل بيانات موظف', 'تعديل معلومات الموظفين', 'general_admin', 'hr', 'modules/hr.php', '✏️', NULL, 32, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(36, 'hr_delete', 'حذف موظف', 'حذف موظف من النظام', 'general_admin', 'hr', 'modules/hr.php', '🗑️', NULL, 33, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(37, 'hr_salary_view', 'عرض الرواتب', 'عرض رواتب الموظفين', 'general_admin', 'hr', 'modules/hr.php', '💰', NULL, 34, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(38, 'hr_salary_edit', 'تعديل الرواتب', 'تعديل رواتب الموظفين', 'general_admin', 'hr', 'modules/hr.php', '💵', NULL, 35, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(39, 'permissions_view', 'عرض الصلاحيات', 'عرض صلاحيات المستخدمين', 'general_admin', 'permissions', 'modules/permissions.php', '🔐', NULL, 40, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(40, 'permissions_manage', 'إدارة الصلاحيات', 'منح وإلغاء صلاحيات المستخدمين', 'general_admin', 'permissions', 'modules/permissions.php', '🔑', NULL, 41, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(41, 'users_manage', 'إدارة المستخدمين', 'إضافة وتعديل وحذف المستخدمين', 'general_admin', 'core', 'modules/permissions.php', '👤', NULL, 42, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(42, 'financial_dashboard_view', 'عرض لوحة التحكم المالية', 'عرض الإحصائيات والتقارير المالية', 'finance', 'finance', 'modules/financial_dashboard.php', '📊', NULL, 100, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(43, 'finance_view', 'عرض المعاملات المالية', 'عرض جميع المعاملات المالية', 'finance', 'finance', 'modules/finance.php', '💵', NULL, 110, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(44, 'finance_add', 'إضافة معاملة مالية', 'إضافة معاملة مالية جديدة', 'finance', 'finance', 'modules/finance.php', '➕', NULL, 111, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(45, 'finance_edit', 'تعديل معاملة مالية', 'تعديل معاملات مالية موجودة', 'finance', 'finance', 'modules/finance.php', '✏️', NULL, 112, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(46, 'finance_delete', 'حذف معاملة مالية', 'حذف معاملات مالية', 'finance', 'finance', 'modules/finance.php', '🗑️', NULL, 113, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(47, 'budgets_view', 'عرض الميزانيات', 'عرض الميزانيات والبنود', 'finance', 'budgets', 'modules/budgets.php', '📊', NULL, 120, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(48, 'budgets_add', 'إضافة ميزانية', 'إنشاء ميزانية جديدة', 'finance', 'budgets', 'modules/budgets.php', '➕', NULL, 121, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(49, 'budgets_edit', 'تعديل ميزانية', 'تعديل الميزانيات والبنود', 'finance', 'budgets', 'modules/budgets.php', '✏️', NULL, 122, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(50, 'budgets_delete', 'حذف ميزانية', 'حذف ميزانية', 'finance', 'budgets', 'modules/budgets.php', '🗑️', NULL, 123, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(51, 'suppliers_view', 'عرض الموردين', 'عرض قائمة الموردين', 'finance', 'suppliers', 'modules/suppliers.php', '🏪', NULL, 130, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(52, 'suppliers_add', 'إضافة مورد', 'إضافة مورد جديد', 'finance', 'suppliers', 'modules/suppliers.php', '➕', NULL, 131, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(53, 'suppliers_edit', 'تعديل بيانات مورد', 'تعديل معلومات الموردين', 'finance', 'suppliers', 'modules/suppliers.php', '✏️', NULL, 132, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(54, 'suppliers_delete', 'حذف مورد', 'حذف مورد من النظام', 'finance', 'suppliers', 'modules/suppliers.php', '🗑️', NULL, 133, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(55, 'invoices_view', 'عرض الفواتير', 'عرض فواتير الموردين', 'finance', 'invoices', 'modules/invoices.php', '📄', NULL, 140, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(56, 'invoices_add', 'إضافة فاتورة', 'إضافة فاتورة جديدة', 'finance', 'invoices', 'modules/invoices.php', '➕', NULL, 141, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(57, 'invoices_edit', 'تعديل فاتورة', 'تعديل فواتير موجودة', 'finance', 'invoices', 'modules/invoices.php', '✏️', NULL, 142, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(58, 'invoices_delete', 'حذف فاتورة', 'حذف فاتورة', 'finance', 'invoices', 'modules/invoices.php', '🗑️', NULL, 143, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(59, 'invoices_pay', 'تسديد فاتورة', 'تسديد أو تسجيل دفعة لفاتورة', 'finance', 'invoices', 'modules/invoices.php', '💰', NULL, 144, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(60, 'tax_view', 'عرض سجلات الجباية', 'عرض سجلات الضرائب والرسوم', 'finance', 'tax', 'modules/tax_collection.php', '🧾', NULL, 150, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(61, 'tax_add', 'إضافة سجل جباية', 'إضافة سجل ضرائب جديد', 'finance', 'tax', 'modules/tax_collection.php', '➕', NULL, 151, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(62, 'tax_edit', 'تعديل سجل جباية', 'تعديل سجلات الجباية', 'finance', 'tax', 'modules/tax_collection.php', '✏️', NULL, 152, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(63, 'tax_delete', 'حذف سجل جباية', 'حذف سجل جباية', 'finance', 'tax', 'modules/tax_collection.php', '🗑️', NULL, 153, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(64, 'donations_view', 'عرض التبرعات', 'عرض سجلات التبرعات', 'finance', 'finance', 'modules/donations.php', '💖', NULL, 160, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(65, 'donations_add', 'إضافة تبرع', 'إضافة تبرع جديد', 'finance', 'finance', 'modules/donations.php', '➕', NULL, 161, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(66, 'donations_edit', 'تعديل تبرع', 'تعديل سجل تبرع', 'finance', 'finance', 'modules/donations.php', '✏️', NULL, 162, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(67, 'contributions_view', 'عرض المساهمات', 'عرض المساهمات الشعبية', 'finance', 'finance', 'modules/contributions.php', '🤝', NULL, 170, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(68, 'contributions_add', 'إضافة مساهمة', 'إضافة مساهمة شعبية', 'finance', 'finance', 'modules/contributions.php', '➕', NULL, 171, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(69, 'currencies_manage', 'إدارة العملات', 'إدارة العملات وأسعار الصرف', 'finance', 'finance', 'modules/currencies.php', '💱', NULL, 180, 1, '2025-11-19 21:55:12', '2025-11-19 21:55:12'),
(70, 'projects_view', 'عرض المشاريع', 'عرض قائمة المشاريع', 'projects', 'projects', 'modules/projects_unified.php', '🏗️', NULL, 200, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(71, 'projects_add', 'إضافة مشروع', 'إضافة مشروع جديد', 'projects', 'projects', 'modules/projects_unified.php', '➕', NULL, 201, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(72, 'projects_edit', 'تعديل مشروع', 'تعديل معلومات المشاريع', 'projects', 'projects', 'modules/projects_unified.php', '✏️', NULL, 202, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(73, 'projects_delete', 'حذف مشروع', 'حذف مشروع', 'projects', 'projects', 'modules/projects_unified.php', '🗑️', NULL, 203, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(74, 'projects_status_change', 'تغيير حالة مشروع', 'تغيير حالة تقدم المشروع', 'projects', 'projects', 'modules/projects_unified.php', '🔄', NULL, 204, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(75, 'projects_finance_view', 'عرض المالية للمشاريع', 'عرض التفاصيل المالية للمشاريع', 'projects', 'projects', 'modules/projects_finance.php', '💵', NULL, 210, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(76, 'projects_finance_edit', 'تعديل مالية المشاريع', 'تعديل الميزانيات والنفقات', 'projects', 'projects', 'modules/projects_finance.php', '✏️', NULL, 211, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(77, 'contracts_view', 'عرض العقود', 'عرض العقود والمناقصات', 'projects', 'contracts', 'modules/contracts.php', '📋', NULL, 220, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(78, 'contracts_add', 'إضافة عقد', 'إضافة عقد أو مناقصة جديدة', 'projects', 'contracts', 'modules/contracts.php', '➕', NULL, 221, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(79, 'contracts_edit', 'تعديل عقد', 'تعديل تفاصيل العقود', 'projects', 'contracts', 'modules/contracts.php', '✏️', NULL, 222, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(80, 'contracts_delete', 'حذف عقد', 'حذف عقد', 'projects', 'contracts', 'modules/contracts.php', '🗑️', NULL, 223, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(81, 'donors_view', 'عرض المنظمات المانحة', 'عرض قائمة المنظمات المانحة', 'projects', 'donors', 'modules/donor_organizations.php', '🏛️', NULL, 230, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(82, 'donors_add', 'إضافة منظمة مانحة', 'إضافة منظمة مانحة جديدة', 'projects', 'donors', 'modules/donor_organizations.php', '➕', NULL, 231, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(83, 'donors_edit', 'تعديل منظمة مانحة', 'تعديل بيانات المنظمات', 'projects', 'donors', 'modules/donor_organizations.php', '✏️', NULL, 232, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(84, 'citizens_view', 'عرض بيانات المواطنين', 'عرض قائمة وسجلات المواطنين', 'citizens', 'citizens', 'modules/citizens.php', '👨‍👩‍👧‍👦', NULL, 300, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(85, 'citizens_add', 'إضافة مواطن', 'إضافة مواطن جديد', 'citizens', 'citizens', 'modules/citizens.php', '➕', NULL, 301, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(86, 'citizens_edit', 'تعديل بيانات مواطن', 'تعديل معلومات المواطنين', 'citizens', 'citizens', 'modules/citizens.php', '✏️', NULL, 302, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(87, 'citizens_delete', 'حذف مواطن', 'حذف سجل مواطن', 'citizens', 'citizens', 'modules/citizens.php', '🗑️', NULL, 303, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(88, 'citizen_accounts_view', 'عرض حسابات المواطنين', 'عرض حسابات وكلمات المرور', 'citizens', 'citizens', 'modules/citizens_accounts.php', '👤', NULL, 310, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(89, 'citizen_accounts_manage', 'إدارة حسابات المواطنين', 'إنشاء وتعديل حسابات المواطنين', 'citizens', 'citizens', 'modules/citizens_accounts.php', '🔑', NULL, 311, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(90, 'complaints_view', 'عرض الشكاوى', 'عرض شكاوى المواطنين', 'citizens', 'complaints', 'modules/complaints.php', '📢', NULL, 320, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(91, 'complaints_edit', 'معالجة الشكاوى', 'الرد على ومعالجة الشكاوى', 'citizens', 'complaints', 'modules/complaints.php', '✏️', NULL, 321, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(92, 'complaints_delete', 'حذف شكوى', 'حذف شكوى من النظام', 'citizens', 'complaints', 'modules/complaints.php', '🗑️', NULL, 322, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(93, 'permits_view', 'عرض رخص البناء', 'عرض طلبات رخص البناء', 'citizens', 'permits', 'modules/building_permit.php', '📝', NULL, 330, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(94, 'permits_add', 'إضافة رخصة بناء', 'إضافة طلب رخصة جديد', 'citizens', 'permits', 'modules/building_permit.php', '➕', NULL, 331, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(95, 'permits_edit', 'معالجة رخص البناء', 'مراجعة وتعديل الطلبات', 'citizens', 'permits', 'modules/building_permit.php', '✏️', NULL, 332, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(96, 'permits_approve', 'الموافقة على رخص البناء', 'الموافقة أو رفض الطلبات', 'citizens', 'permits', 'modules/building_permit.php', '✅', NULL, 333, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(97, 'violations_view', 'عرض المخالفات', 'عرض المخالفات البلدية', 'citizens', 'violations', 'modules/violations.php', '⚠️', NULL, 340, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(98, 'violations_add', 'إضافة مخالفة', 'إضافة مخالفة جديدة', 'citizens', 'violations', 'modules/violations.php', '➕', NULL, 341, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(99, 'violations_edit', 'تعديل مخالفة', 'تعديل تفاصيل المخالفات', 'citizens', 'violations', 'modules/violations.php', '✏️', NULL, 342, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(100, 'violations_delete', 'حذف مخالفة', 'حذف مخالفة', 'citizens', 'violations', 'modules/violations.php', '🗑️', NULL, 343, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(101, 'vehicles_view', 'عرض الآليات', 'عرض قائمة الآليات والمعدات', 'services', 'vehicles', 'modules/vehicles.php', '🚚', NULL, 400, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(102, 'vehicles_add', 'إضافة آلية', 'إضافة آلية أو معدة جديدة', 'services', 'vehicles', 'modules/vehicles.php', '➕', NULL, 401, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(103, 'vehicles_edit', 'تعديل بيانات آلية', 'تعديل معلومات الآليات', 'services', 'vehicles', 'modules/vehicles.php', '✏️', NULL, 402, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(104, 'vehicles_delete', 'حذف آلية', 'حذف آلية من النظام', 'services', 'vehicles', 'modules/vehicles.php', '🗑️', NULL, 403, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(105, 'drivers_view', 'عرض السائقين', 'عرض قائمة السائقين', 'services', 'vehicles', 'modules/drivers_section.php', '🚗', NULL, 410, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(106, 'drivers_add', 'إضافة سائق', 'إضافة سائق جديد', 'services', 'vehicles', 'modules/drivers_section.php', '➕', NULL, 411, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(107, 'drivers_edit', 'تعديل بيانات سائق', 'تعديل معلومات السائقين', 'services', 'vehicles', 'modules/drivers_section.php', '✏️', NULL, 412, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(108, 'maintenance_view', 'عرض سجلات الصيانة', 'عرض سجلات الصيانة والتصليحات', 'services', 'maintenance', 'modules/maintenance.php', '🔧', NULL, 420, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(109, 'maintenance_add', 'إضافة سجل صيانة', 'إضافة طلب صيانة جديد', 'services', 'maintenance', 'modules/maintenance.php', '➕', NULL, 421, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(110, 'maintenance_edit', 'تعديل سجل صيانة', 'تعديل سجلات الصيانة', 'services', 'maintenance', 'modules/maintenance.php', '✏️', NULL, 422, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(111, 'waste_view', 'عرض إدارة النفايات', 'عرض سجلات وجداول النفايات', 'services', 'waste', 'modules/waste.php', '🗑️', NULL, 430, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(112, 'waste_edit', 'إدارة جمع النفايات', 'إدارة جداول وطرق جمع النفايات', 'services', 'waste', 'modules/waste.php', '✏️', NULL, 431, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(113, 'inventory_view', 'عرض المخزون', 'عرض المواد والمخزون', 'services', 'inventory', 'modules/inventory.php', '📦', NULL, 440, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(114, 'inventory_add', 'إضافة مادة للمخزون', 'إضافة مادة جديدة للمخزون', 'services', 'inventory', 'modules/inventory.php', '➕', NULL, 441, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(115, 'inventory_edit', 'تعديل المخزون', 'تعديل الكميات والمواد', 'services', 'inventory', 'modules/inventory.php', '✏️', NULL, 442, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(116, 'inventory_delete', 'حذف مادة من المخزون', 'حذف مادة', 'services', 'inventory', 'modules/inventory.php', '🗑️', NULL, 443, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(117, 'facilities_view', 'عرض المرافق', 'عرض المرافق العامة', 'maps', 'facilities', 'modules/facilities_management.php', '🏢', NULL, 500, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(118, 'facilities_add', 'إضافة مرفق', 'إضافة مرفق عام جديد', 'maps', 'facilities', 'modules/facilities_management.php', '➕', NULL, 501, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(119, 'facilities_edit', 'تعديل مرفق', 'تعديل معلومات المرافق', 'maps', 'facilities', 'modules/facilities_management.php', '✏️', NULL, 502, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(120, 'facilities_delete', 'حذف مرفق', 'حذف مرفق عام', 'maps', 'facilities', 'modules/facilities_management.php', '🗑️', NULL, 503, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(121, 'facility_categories_manage', 'إدارة فئات المرافق', 'إدارة تصنيفات المرافق', 'maps', 'facilities', 'modules/facilities_categories.php', '📂', NULL, 510, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(122, 'map_settings_manage', 'إدارة إعدادات الخريطة', 'إدارة إعدادات الخرائط التفاعلية', 'maps', 'maps', 'modules/map_settings.php', '🗺️', NULL, 520, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(123, 'website_view', 'عرض محتوى الموقع', 'عرض محتوى الموقع العام', 'website', 'website', 'modules/public_content_management.php', '🌐', NULL, 600, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(124, 'website_edit', 'تعديل محتوى الموقع', 'تعديل محتوى الموقع العام', 'website', 'website', 'modules/public_content_management.php', '✏️', NULL, 601, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(125, 'contact_view', 'عرض رسائل اتصل بنا', 'عرض رسائل المواطنين', 'website', 'website', 'modules/contact_management.php', '📞', NULL, 610, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(126, 'contact_reply', 'الرد على رسائل اتصل بنا', 'الرد على استفسارات المواطنين', 'website', 'website', 'modules/contact_management.php', '✉️', NULL, 611, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(127, 'telegram_view', 'عرض رسائل Telegram', 'عرض رسائل Telegram الواردة', 'website', 'telegram', 'modules/telegram_messages.php', '✈️', NULL, 620, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(128, 'telegram_send', 'إرسال رسائل Telegram', 'إرسال رسائل عبر Telegram', 'website', 'telegram', 'modules/telegram_messages.php', '📤', NULL, 621, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(129, 'telegram_settings', 'إعدادات Telegram', 'إدارة إعدادات Telegram Bot', 'website', 'telegram', 'modules/telegram_settings.php', '⚙️', NULL, 622, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(130, 'sms_view', 'عرض الرسائل النصية', 'عرض سجلات الرسائل النصية', 'website', 'sms', 'modules/sms.php', '📱', NULL, 630, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(131, 'sms_send', 'إرسال رسائل نصية', 'إرسال رسائل نصية للمواطنين', 'website', 'sms', 'modules/sms.php', '📤', NULL, 631, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(132, 'reports_view', 'عرض التقارير', 'عرض جميع التقارير', 'reports', 'reports', 'modules/reports.php', '📊', NULL, 700, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(133, 'reports_financial', 'تقارير مالية', 'إنشاء وعرض التقارير المالية', 'reports', 'reports', 'modules/reports.php', '💰', NULL, 701, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(134, 'reports_administrative', 'تقارير إدارية', 'إنشاء وعرض التقارير الإدارية', 'reports', 'reports', 'modules/reports.php', '📋', NULL, 702, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(135, 'reports_service', 'تقارير الخدمات', 'إنشاء وعرض تقارير الخدمات', 'reports', 'reports', 'modules/reports.php', '🚚', NULL, 703, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(136, 'reports_export', 'تصدير التقارير', 'تصدير التقارير بصيغ مختلفة', 'reports', 'reports', 'modules/reports.php', '📥', NULL, 704, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(137, 'archive_view', 'عرض الأرشيف', 'عرض الملفات المؤرشفة', 'reports', 'archive', 'modules/archive.php', '📁', NULL, 710, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(138, 'archive_add', 'إضافة للأرشيف', 'إضافة ملفات للأرشيف', 'reports', 'archive', 'modules/archive.php', '➕', NULL, 711, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(139, 'archive_delete', 'حذف من الأرشيف', 'حذف ملفات من الأرشيف', 'reports', 'archive', 'modules/archive.php', '🗑️', NULL, 712, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(140, 'audit_log_view', 'عرض سجلات التدقيق', 'عرض سجلات النشاطات والتغييرات', 'reports', 'reports', NULL, '🔍', NULL, 720, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(141, 'settings_view', 'عرض إعدادات النظام', 'عرض إعدادات النظام العامة', 'settings', 'settings', 'modules/system_settings.php', '⚙️', NULL, 800, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(142, 'settings_manage', 'إدارة إعدادات النظام', 'تعديل إعدادات النظام', 'settings', 'settings', 'modules/system_settings.php', '🔧', NULL, 801, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(143, 'reference_tables_manage', 'إدارة الجداول المرجعية', 'إدارة البيانات المرجعية', 'settings', 'settings', 'all_tables_manager.php', '🗄️', NULL, 810, 1, '2025-11-19 21:55:13', '2025-11-19 21:55:13'),
(144, 'important_links_view', 'إدارة روابط مهمة', 'عرض وإدارة روابط مهمة (المرافق العامة)', 'website', 'important_links', 'modules/important_links_management.php', '🔗', NULL, 640, 1, '2025-11-24 14:38:31', '2025-11-24 14:38:31');

-- --------------------------------------------------------

--
-- Table structure for table `polls`
--

CREATE TABLE `polls` (
  `id` int(11) NOT NULL,
  `poll_title` varchar(255) NOT NULL,
  `poll_description` text DEFAULT NULL,
  `poll_question` varchar(500) NOT NULL,
  `poll_options` text NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_multiple_choice` tinyint(1) DEFAULT 0,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_votes` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `poll_responses`
--

CREATE TABLE `poll_responses` (
  `id` int(11) NOT NULL,
  `poll_id` int(11) NOT NULL,
  `voter_ip` varchar(45) DEFAULT NULL,
  `selected_options` text NOT NULL,
  `additional_comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `id` int(11) NOT NULL,
  `position_name` varchar(100) NOT NULL,
  `position_description` text DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `salary_range_min` decimal(15,2) DEFAULT 0.00,
  `salary_range_max` decimal(15,2) DEFAULT 0.00,
  `required_qualifications` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `positions`
--

INSERT INTO `positions` (`id`, `position_name`, `position_description`, `department_id`, `salary_range_min`, `salary_range_max`, `required_qualifications`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'مدير عام', 'يدير جميع أعمال البلدية ويتخذ القرارات الإستراتيجية', NULL, '800000.00', '1200000.00', 'شهادة عليا + خبرة إدارية 10 سنوات', 1, '2025-06-07 20:31:22', '2025-06-07 21:36:50'),
(2, 'مدير قسم', 'يدير قسمه المختص ويشرف على الموظفين', NULL, '600000.00', '900000.00', 'شهادة جامعية + خبرة إدارية 5 سنوات', 1, '2025-06-07 20:31:22', '2025-06-07 21:36:50'),
(3, 'مهندس مدني', 'يقوم بالأعمال الإدارية والتنسيق', NULL, '400000.00', '600000.00', 'شهادة جامعية + خبرة إدارية سنتين', 1, '2025-06-07 20:31:22', '2025-06-07 21:36:50'),
(4, 'محاسب', 'يقوم بأعمال الكتابة والسكرتارية', NULL, '300000.00', '500000.00', 'شهادة إعدادية + دورة كتابة', 1, '2025-06-07 20:31:22', '2025-06-07 21:36:50'),
(5, 'موظف استقبال', 'يدير الحسابات المالية والميزانية', NULL, '350000.00', '550000.00', 'شهادة جامعية محاسبة + خبرة محاسبية', 1, '2025-06-07 20:31:22', '2025-06-07 21:36:50'),
(6, 'فني صيانة', 'يقوم بالأعمال الهندسية والتصميم', NULL, '450000.00', '650000.00', 'شهادة هندسة + رخصة مزاولة مهنة', 1, '2025-06-07 20:31:22', '2025-06-07 21:36:50'),
(7, 'مستشار قانوني', 'يقوم بأعمال الصيانة والنظافة', NULL, '300000.00', '450000.00', 'شهادة ابتدائية + اللياقة البدنية', 1, '2025-06-07 20:31:22', '2025-06-07 21:36:50'),
(8, 'مطور أنظمة', 'يقوم بنقل الموظفين والمعدات', NULL, '400000.00', '600000.00', 'رخصة قيادة + خبرة قيادة 3 سنوات', 1, '2025-06-07 20:31:22', '2025-06-08 22:00:35');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `project_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `project_type` varchar(100) DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `budget` decimal(15,2) DEFAULT NULL,
  `budget_currency_id` int(11) DEFAULT 1,
  `actual_cost` decimal(15,2) DEFAULT 0.00,
  `actual_cost_currency_id` int(11) DEFAULT 1,
  `progress_percentage` int(11) DEFAULT 0,
  `status` enum('مخطط','قيد التنفيذ','مكتمل','متوقف','ملغي') DEFAULT 'مخطط',
  `association_id` int(11) DEFAULT NULL COMMENT 'الجمعية المنفذة',
  `total_budget` decimal(15,2) DEFAULT 0.00 COMMENT 'الميزانية الإجمالية',
  `spent_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'المبلغ المصروف',
  `remaining_budget` decimal(15,2) DEFAULT 0.00 COMMENT 'الميزانية المتبقية',
  `budget_item_id` int(11) DEFAULT NULL COMMENT 'بند الميزانية المرتبط',
  `contract_number` varchar(100) DEFAULT NULL COMMENT 'رقم العقد',
  `contract_date` date DEFAULT NULL COMMENT 'تاريخ العقد',
  `contractor` varchar(200) DEFAULT NULL,
  `donor_name` varchar(200) DEFAULT NULL,
  `donor_type` enum('حكومي','خاص','منظمة دولية','منظمة خيرية','أفراد','أخرى') DEFAULT 'حكومي',
  `donor_contact` varchar(100) DEFAULT NULL,
  `funding_type` enum('كامل','جزئي','مشترك') DEFAULT 'كامل',
  `manager_id` int(11) DEFAULT NULL,
  `priority` enum('عالية','متوسطة','منخفضة') DEFAULT 'متوسطة',
  `notes` text DEFAULT NULL,
  `allow_public_contributions` tinyint(1) DEFAULT 0 COMMENT 'هل يقبل مساهمات شعبية؟',
  `contributions_target` decimal(15,2) DEFAULT 0.00 COMMENT 'هدف المساهمات',
  `contributions_collected` decimal(15,2) DEFAULT 0.00 COMMENT 'المبلغ المُجمّع',
  `contributions_currency_id` int(11) DEFAULT 1 COMMENT 'عملة المساهمات',
  `is_public` tinyint(1) DEFAULT 0 COMMENT 'هل يُعرض للعامة؟',
  `is_featured` tinyint(1) DEFAULT 0 COMMENT 'هل مشروع مميز؟',
  `project_goal` text DEFAULT NULL COMMENT 'هدف المشروع',
  `beneficiaries_count` int(11) DEFAULT 0 COMMENT 'عدد المستفيدين',
  `beneficiaries_description` text DEFAULT NULL COMMENT 'وصف المستفيدين',
  `main_image` varchar(500) DEFAULT NULL COMMENT 'الصورة الرئيسية',
  `gallery_images` text DEFAULT NULL COMMENT 'معرض الصور (JSON)',
  `before_images` text DEFAULT NULL COMMENT 'صور قبل (JSON)',
  `after_images` text DEFAULT NULL COMMENT 'صور بعد (JSON)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `currency_id` int(11) DEFAULT NULL COMMENT 'عملة المشروع',
  `target_amount` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `project_name`, `description`, `project_type`, `location`, `start_date`, `end_date`, `budget`, `budget_currency_id`, `actual_cost`, `actual_cost_currency_id`, `progress_percentage`, `status`, `association_id`, `total_budget`, `spent_amount`, `remaining_budget`, `budget_item_id`, `contract_number`, `contract_date`, `contractor`, `donor_name`, `donor_type`, `donor_contact`, `funding_type`, `manager_id`, `priority`, `notes`, `allow_public_contributions`, `contributions_target`, `contributions_collected`, `contributions_currency_id`, `is_public`, `is_featured`, `project_goal`, `beneficiaries_count`, `beneficiaries_description`, `main_image`, `gallery_images`, `before_images`, `after_images`, `created_at`, `updated_at`, `currency_id`, `target_amount`) VALUES
(2, 'إنشاء حديقة عامة جديدة في تكريت وسط البلدة', 'إنشاء حديقة عامة جديدة في تكريت وسط البلدة لكي يزورها العديد من الزوار ويستمتعوا بالوقت بها', 'بيئي', 'وسط البلدة', '2026-01-01', '2026-05-01', '2300.00', 2, '0.00', 1, 0, 'مخطط', NULL, '0.00', '800.00', '0.00', NULL, NULL, NULL, '', NULL, 'حكومي', NULL, 'كامل', 1, 'متوسطة', '', 1, '500.00', '0.00', 2, 1, 1, 'بيئي بحت', 600, '', NULL, NULL, NULL, NULL, '2025-11-03 19:47:41', '2025-11-03 21:47:13', 2, '2300.00');

-- --------------------------------------------------------

--
-- Table structure for table `project_contributions`
--

CREATE TABLE `project_contributions` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL COMMENT 'رقم المشروع',
  `contributor_name` varchar(255) NOT NULL COMMENT 'اسم المساهم',
  `contributor_phone` varchar(50) DEFAULT NULL COMMENT 'هاتف المساهم',
  `contributor_email` varchar(100) DEFAULT NULL COMMENT 'بريد المساهم',
  `contributor_address` text DEFAULT NULL COMMENT 'عنوان المساهم',
  `contribution_amount` decimal(15,2) NOT NULL COMMENT 'مبلغ المساهمة',
  `currency_id` int(11) NOT NULL COMMENT 'العملة',
  `contribution_date` date NOT NULL COMMENT 'تاريخ المساهمة',
  `payment_method` enum('نقد','شيك','تحويل مصرفي','بطاقة ائتمان','أخرى') DEFAULT 'نقد' COMMENT 'طريقة الدفع',
  `bank_name` varchar(200) DEFAULT NULL COMMENT 'اسم البنك',
  `check_number` varchar(100) DEFAULT NULL COMMENT 'رقم الشيك',
  `reference_number` varchar(100) DEFAULT NULL COMMENT 'الرقم المرجعي',
  `receipt_number` varchar(100) DEFAULT NULL COMMENT 'رقم الإيصال',
  `notes` text DEFAULT NULL COMMENT 'ملاحظات',
  `is_anonymous` tinyint(1) DEFAULT 0 COMMENT 'مساهمة مجهولة؟',
  `is_verified` tinyint(1) DEFAULT 0 COMMENT 'تم التحقق؟',
  `verified_by` int(11) DEFAULT NULL COMMENT 'تم التحقق بواسطة',
  `verified_date` datetime DEFAULT NULL COMMENT 'تاريخ التحقق',
  `financial_transaction_id` int(11) DEFAULT NULL COMMENT 'رقم المعاملة المالية المرتبطة',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ الإنشاء',
  `created_by` int(11) DEFAULT NULL COMMENT 'تم الإنشاء بواسطة'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول مساهمات المشاريع';

--
-- Triggers `project_contributions`
--
DELIMITER $$
CREATE TRIGGER `after_contribution_delete` AFTER DELETE ON `project_contributions` FOR EACH ROW BEGIN
    -- تحديث المبلغ المُجمّع في المشروع (طرح)
    UPDATE `projects` 
    SET `contributions_collected` = `contributions_collected` - OLD.`contribution_amount`
    WHERE `id` = OLD.`project_id`;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_contribution_insert` AFTER INSERT ON `project_contributions` FOR EACH ROW BEGIN
    -- تحديث المبلغ المُجمّع في المشروع
    UPDATE `projects` 
    SET `contributions_collected` = `contributions_collected` + NEW.`contribution_amount`
    WHERE `id` = NEW.`project_id`;
    
    -- إنشاء معاملة مالية تلقائياً (إذا تم التحقق)
    IF NEW.`is_verified` = 1 THEN
        INSERT INTO `financial_transactions` 
        (
            `transaction_date`,
            `type`,
            `category`,
            `description`,
            `amount`,
            `currency_id`,
            `payment_method`,
            `reference_number`,
            `related_project_id`,
            `created_by`,
            `status`
        )
        VALUES 
        (
            NEW.`contribution_date`,
            'إيراد',
            'مساهمات شعبية',
            CONCAT('مساهمة من: ', NEW.`contributor_name`, ' في مشروع رقم ', NEW.`project_id`),
            NEW.`contribution_amount`,
            NEW.`currency_id`,
            NEW.`payment_method`,
            NEW.`reference_number`,
            NEW.`project_id`,
            NEW.`created_by`,
            'معتمد'
        );
        
        -- تحديث رقم المعاملة المالية في المساهمة
        UPDATE `project_contributions`
        SET `financial_transaction_id` = LAST_INSERT_ID()
        WHERE `id` = NEW.`id`;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `project_phases`
--

CREATE TABLE `project_phases` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `phase_number` int(11) NOT NULL,
  `phase_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `planned_start_date` date DEFAULT NULL,
  `planned_end_date` date DEFAULT NULL,
  `actual_start_date` date DEFAULT NULL,
  `actual_end_date` date DEFAULT NULL,
  `planned_cost` decimal(15,2) DEFAULT NULL,
  `actual_cost` decimal(15,2) DEFAULT 0.00,
  `currency_id` int(11) DEFAULT 1,
  `progress_percentage` int(11) DEFAULT 0,
  `status` enum('مخطط','قيد التنفيذ','مكتمل','متأخر','متوقف','ملغي') DEFAULT 'مخطط',
  `executing_entity` varchar(200) DEFAULT NULL,
  `beneficiaries` text DEFAULT NULL,
  `beneficiaries_count` int(11) DEFAULT 0,
  `necessity_level` enum('عالية جداً','عالية','متوسطة','منخفضة') DEFAULT 'متوسطة',
  `impact_assessment` text DEFAULT NULL,
  `responsible_user_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_types`
--

CREATE TABLE `project_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(50) NOT NULL,
  `type_description` text DEFAULT NULL,
  `typical_duration_months` int(11) DEFAULT 12,
  `typical_budget_range` varchar(100) DEFAULT NULL,
  `required_permits` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `project_types`
--

INSERT INTO `project_types` (`id`, `type_name`, `type_description`, `typical_duration_months`, `typical_budget_range`, `required_permits`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'بنية تحتية', 'مشاريع البنية التحتية كالطرق والجسور', 18, '100 مليون - 1 مليار ل.ل', NULL, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(2, 'خدمات عامة', 'مشاريع تحسين الخدمات العامة', 12, '50 - 500 مليون ل.ل', NULL, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(3, 'بيئي', 'مشاريع حماية البيئة والاستدامة', 24, '20 - 200 مليون ل.ل', NULL, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(4, 'تطوير عمراني', 'مشاريع التطوير العمراني والتخطيط', 36, '500 مليون - 2 مليار ل.ل', NULL, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(5, 'تكنولوجي', 'مشاريع التحول الرقمي والتكنولوجيا', 6, '10 - 100 مليون ل.ل', NULL, 1, '2025-06-07 20:31:22', '2025-06-08 22:20:51');

-- --------------------------------------------------------

--
-- Table structure for table `reference_data`
--

CREATE TABLE `reference_data` (
  `id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `value` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reference_data`
--

INSERT INTO `reference_data` (`id`, `type`, `value`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'complaint_category', 'مشاكل النظافة', 'شكاوى متعلقة بالنظافة وجمع النفايات', 1, '2025-11-02 00:41:54', '2025-11-02 00:41:54'),
(2, 'complaint_category', 'مشاكل الإنارة', 'شكاوى متعلقة بإنارة الشوارع', 1, '2025-11-02 00:41:54', '2025-11-02 00:41:54'),
(3, 'complaint_category', 'مشاكل الطرق', 'شكاوى متعلقة بحالة الطرق والبنية التحتية', 1, '2025-11-02 00:41:54', '2025-11-02 00:41:54'),
(4, 'complaint_category', 'مشاكل المياه', 'شكاوى متعلقة بالمياه والصرف الصحي', 1, '2025-11-02 00:41:54', '2025-11-02 00:41:54'),
(5, 'expense_category', 'رواتب', 'رواتب الموظفين', 1, '2025-11-02 00:41:54', '2025-11-02 00:41:54'),
(6, 'expense_category', 'صيانة', 'صيانة المرافق والمعدات', 1, '2025-11-02 00:41:54', '2025-11-02 00:41:54'),
(7, 'expense_category', 'وقود', 'وقود الآليات والمركبات', 1, '2025-11-02 00:41:54', '2025-11-02 00:41:54'),
(8, 'expense_category', 'مشتريات', 'مشتريات عامة', 1, '2025-11-02 00:41:54', '2025-11-02 00:41:54'),
(9, 'violation_type', 'مخالفة بناء', 'مخالفات البناء غير المرخص', 1, '2025-11-02 00:41:54', '2025-11-02 00:41:54'),
(10, 'violation_type', 'مخالفة نظافة', 'مخالفات النظافة العامة', 1, '2025-11-02 00:41:54', '2025-11-02 00:41:54'),
(11, 'violation_type', 'مخالفة إشغال', 'مخالفات إشغال الأملاك العامة', 1, '2025-11-02 00:41:54', '2025-11-02 00:41:54'),
(12, 'leave_type', 'إجازة سنوية', 'إجازة سنوية اعتيادية', 1, '2025-11-02 00:41:54', '2025-11-02 00:41:54'),
(13, 'leave_type', 'إجازة مرضية', 'إجازة مرضية', 1, '2025-11-02 00:41:54', '2025-11-02 00:41:54'),
(14, 'leave_type', 'إجازة طارئة', 'إجازة طارئة', 1, '2025-11-02 00:41:54', '2025-11-02 00:41:54');

-- --------------------------------------------------------

--
-- Table structure for table `request_documents`
--

CREATE TABLE `request_documents` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `request_documents`
--

INSERT INTO `request_documents` (`id`, `request_id`, `document_name`, `original_filename`, `file_path`, `file_size`, `file_type`, `is_required`, `uploaded_at`) VALUES
(1, 8, 'مستند مرفق', '1000610269.jpg', '../uploads/requests/8/1751148684_0.jpg', 284105, 'image/jpeg', 0, '2025-06-28 22:11:24'),
(2, 8, 'مستند مرفق', 'WhatsApp Image 2025-06-19 at 11.57.39 AM.jpeg', '../uploads/requests/8/1751148684_1.jpeg', 42163, 'image/jpeg', 0, '2025-06-28 22:11:24'),
(3, 9, 'مستند مرفق', 'Gemini_Generated_Image_pzbkqhpzbkqhpzbk.png', '../uploads/requests/9/1751839985_0.png', 387147, 'image/png', 0, '2025-07-06 22:13:05'),
(4, 9, 'مستند مرفق', 'logo11.png', '../uploads/requests/9/1751839985_1.png', 316454, 'image/png', 0, '2025-07-06 22:13:05'),
(5, 9, 'مستند مرفق', 'pngegg (6).png', '../uploads/requests/9/1751839985_2.png', 227650, 'image/png', 0, '2025-07-06 22:13:05'),
(6, 9, 'مستند مرفق', 'pngwing.com (29).png', '../uploads/requests/9/1751839985_3.png', 326898, 'image/png', 0, '2025-07-06 22:13:05'),
(7, 10, 'مستند مرفق', 'Gemini_Generated_Image_pzbkqhpzbkqhpzbk.png', '../uploads/requests/10/1752175764_0.png', 387147, 'image/png', 0, '2025-07-10 19:29:24'),
(8, 10, 'مستند مرفق', 'logo11.png', '../uploads/requests/10/1752175764_1.png', 316454, 'image/png', 0, '2025-07-10 19:29:24'),
(9, 10, 'مستند مرفق', 'pngegg (6).png', '../uploads/requests/10/1752175764_2.png', 227650, 'image/png', 0, '2025-07-10 19:29:24'),
(10, 10, 'مستند مرفق', 'pngwing.com (29).png', '../uploads/requests/10/1752175764_3.png', 326898, 'image/png', 0, '2025-07-10 19:29:24'),
(11, 11, 'مستند مرفق', 'pngegg (6).png', '../uploads/requests/11/1752320693_0.png', 227650, 'image/png', 0, '2025-07-12 11:44:53'),
(12, 11, 'مستند مرفق', 'pngwing.com (29).png', '../uploads/requests/11/1752320693_1.png', 326898, 'image/png', 0, '2025-07-12 11:44:53'),
(13, 12, 'مستند مرفق', 'BAU_FICHE_PAGE2_FRENCH.png', '../uploads/requests/12/1762027884_0.png', 49737, 'image/png', 0, '2025-11-01 20:11:24'),
(14, 13, 'مستند مرفق', 'IMG_20230324_032709_464.jpg', '../uploads/requests/13/1762732290_0.jpg', 4466860, 'image/jpeg', 0, '2025-11-09 23:51:30'),
(15, 13, 'مستند مرفق', 'IMG_20230324_032730_127.jpg', '../uploads/requests/13/1762732290_1.jpg', 3882358, 'image/jpeg', 0, '2025-11-09 23:51:30'),
(16, 13, 'مستند مرفق', 'IMG_20230324_032734_969.jpg', '../uploads/requests/13/1762732290_2.jpg', 3738451, 'image/jpeg', 0, '2025-11-09 23:51:30'),
(17, 14, 'مستند مرفق', '20241220_114351.jpg', '../uploads/requests/14/1762737416_0.jpg', 925799, 'image/jpeg', 0, '2025-11-10 01:16:56'),
(18, 15, 'مستند مرفق', '20241220_114351.jpg', '../uploads/requests/15/1762886104_0.jpg', 925799, 'image/jpeg', 0, '2025-11-11 18:35:04'),
(19, 16, 'مستند مرفق', 'BAU_FICHE_Page_1.jpeg', '../uploads/requests/16/1762886181_0.jpeg', 129557, 'image/jpeg', 0, '2025-11-11 18:36:21'),
(20, 17, 'مستند مرفق', 'BAU_FICHE_FRENCH.png', '../uploads/requests/17/1762886537_0.png', 71145, 'image/png', 0, '2025-11-11 18:42:17'),
(21, 18, 'مستند مرفق', 'BAU_FICHE_FRENCH.png', '../uploads/requests/18/1762886936_0.png', 71145, 'image/png', 0, '2025-11-11 18:48:56'),
(22, 19, 'مستند مرفق', 'BAU_FICHE_PAGE2_FRENCH.png', '../uploads/requests/19/1762892068_0.png', 49737, 'image/png', 0, '2025-11-11 20:14:28'),
(23, 20, 'مستند مرفق', 'BAU_FICHE_PAGE2_FRENCH.png', '../uploads/requests/20/1762892186_0.png', 49737, 'image/png', 0, '2025-11-11 20:16:26'),
(24, 21, 'مستند مرفق', 'BAU_FICHE_Page_2.jpeg', '../uploads/requests/21/1762892866_0.jpeg', 113207, 'image/jpeg', 0, '2025-11-11 20:27:46'),
(25, 22, 'مستند مرفق', 'BAU_FICHE_Page_2.jpeg', '../uploads/requests/22/1762893540_0.jpeg', 113207, 'image/jpeg', 0, '2025-11-11 20:39:00'),
(26, 23, 'مستند مرفق', 'BAU_FICHE_Page_2.jpeg', '../uploads/requests/23/1762904536_0.jpeg', 113207, 'image/jpeg', 0, '2025-11-11 23:42:16'),
(27, 39, 'مستند مرفق', 'BAU_FICHE_PAGE2_FRENCH.png', '../uploads/requests/39/1762942730_0.png', 49737, 'image/png', 0, '2025-11-12 10:18:50'),
(28, 44, 'مستند مرفق', 'ChatGPT Image Nov 7, 2025, 10_34_07 PM.png', '../uploads/requests/44/1763418371_0.png', 1659940, 'image/png', 0, '2025-11-17 22:26:11'),
(29, 45, 'مستند مرفق', 'ChatGPT Image Nov 7, 2025, 10_34_07 PM.png', '../uploads/requests/45/1763418382_0.png', 1659940, 'image/png', 0, '2025-11-17 22:26:22'),
(30, 46, 'مستند مرفق', 'ChatGPT Image Nov 7, 2025, 11_04_09 PM.png', '../uploads/requests/46/1763418587_0.png', 1737100, 'image/png', 0, '2025-11-17 22:29:47'),
(31, 47, 'مستند مرفق', 'ChatGPT Image Nov 7, 2025, 10_52_28 PM.png', '../uploads/requests/47/1763419082_0.png', 2323704, 'image/png', 0, '2025-11-17 22:38:02'),
(32, 51, 'مستند مرفق', 'ChatGPT Image Nov 7, 2025, 10_34_07 PM.png', '../uploads/requests/51/1763501247_0.png', 1659940, 'image/png', 0, '2025-11-18 21:27:27'),
(33, 52, 'مستند مرفق', 'ChatGPT Image Nov 7, 2025, 10_34_07 PM.png', '../uploads/requests/52/1763501270_0.png', 1659940, 'image/png', 0, '2025-11-18 21:27:50'),
(34, 53, 'مستند مرفق', 'ChatGPT Image Nov 7, 2025, 10_34_07 PM.png', '../uploads/requests/53/1763501293_0.png', 1659940, 'image/png', 0, '2025-11-18 21:28:13'),
(35, 54, 'مستند مرفق', 'ChatGPT Image Nov 7, 2025, 10_34_07 PM.png', '../uploads/requests/54/1763501316_0.png', 1659940, 'image/png', 0, '2025-11-18 21:28:36'),
(36, 55, 'مستند مرفق', 'ChatGPT Image Nov 7, 2025, 10_34_07 PM.png', '../uploads/requests/55/1763501339_0.png', 1659940, 'image/png', 0, '2025-11-18 21:28:59'),
(37, 57, 'مستند مرفق', 'BLUE_hand.png', '../uploads/requests/57/1763503165_0.png', 696154, 'image/png', 0, '2025-11-18 21:59:25'),
(38, 59, 'مستند مرفق', 'ChatGPT Image Nov 7, 2025, 10_34_07 PM.png', '../uploads/requests/59/1763504798_0.png', 1659940, 'image/png', 0, '2025-11-18 22:26:38'),
(39, 61, 'مستند مرفق', 'ChatGPT Image Nov 7, 2025, 11_04_09 PM.png', '../uploads/requests/61/1763505098_0.png', 1737100, 'image/png', 0, '2025-11-18 22:31:38'),
(40, 63, 'مستند مرفق', '20251117_230850.jpg', '../uploads/requests/63/1763508526_0.jpg', 2736928, 'image/jpeg', 0, '2025-11-18 23:28:46'),
(41, 64, 'مستند مرفق', 'WhatsApp Image 2025-11-09 at 12.18.21 AM.jpeg', '../uploads/requests/64/1763510528_0.jpeg', 75665, 'image/jpeg', 0, '2025-11-19 00:02:08'),
(42, 65, 'مستند مرفق', 'Camo Studio Snapshot 2024-11-22 - 00-36-29.jpg', '../uploads/requests/65/1763842723_0.jpg', 134485, 'image/jpeg', 0, '2025-11-22 20:18:43');

-- --------------------------------------------------------

--
-- Table structure for table `request_form_data`
--

CREATE TABLE `request_form_data` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `field_name` varchar(255) NOT NULL,
  `field_value` text DEFAULT NULL,
  `field_type` varchar(50) DEFAULT 'text',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `request_ratings`
--

CREATE TABLE `request_ratings` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `feedback` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `request_ratings`
--

INSERT INTO `request_ratings` (`id`, `request_id`, `rating`, `feedback`, `created_at`) VALUES
(1, 9, 5, 'شسيشسيشيسيشسيشسي', '2025-07-06 23:15:30');

-- --------------------------------------------------------

--
-- Table structure for table `request_types`
--

CREATE TABLE `request_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(255) NOT NULL,
  `type_description` text DEFAULT NULL,
  `required_documents` text DEFAULT NULL,
  `form_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`form_fields`)),
  `processing_time` varchar(100) DEFAULT NULL,
  `fees` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cost` decimal(10,2) DEFAULT 0.00 COMMENT 'تكلفة الخدمة',
  `cost_currency_id` int(11) DEFAULT 1 COMMENT 'معرف العملة',
  `name_ar` varchar(255) DEFAULT NULL COMMENT 'الاسم بالعربية',
  `name_en` varchar(255) DEFAULT NULL COMMENT 'الاسم بالإنجليزية'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `request_types`
--

INSERT INTO `request_types` (`id`, `type_name`, `type_description`, `required_documents`, `form_fields`, `processing_time`, `fees`, `is_active`, `display_order`, `created_at`, `updated_at`, `cost`, `cost_currency_id`, `name_ar`, `name_en`) VALUES
(1, 'إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال', 'طلب إعلام البلدية عن تغيير في إشغال العقار أو تبديل الشاغلين أو تعديل وجهة الاستعمال', '[\"\\u0635\\u0648\\u0631\\u0629 \\u0639\\u0646 \\u0627\\u0644\\u0647\\u0648\\u064a\\u0629\",\"\\u0635\\u0648\\u0631\\u0629 \\u0639\\u0646 \\u0627\\u0644\\u0633\\u062c\\u0644 \\u0627\\u0644\\u0639\\u062f\\u0644\\u064a\"]', NULL, '3-5 أيام عمل', '25000.00', 1, 1, '2025-06-22 11:11:04', '2025-11-09 22:50:41', '1500000.00', 1, 'إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال', 'إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال'),
(2, 'إفادة إشغال', 'إفادة تثبت إشغال العقار من قبل المالك أو المستأجر', '[]', NULL, '2-3 أيام عمل', '15000.00', 1, 2, '2025-06-22 11:11:04', '2025-07-13 19:45:05', '20.00', 2, 'إفادة إشغال', 'إفادة إشغال'),
(3, 'إفادة إنجاز بناء مخالف', 'إفادة تثبت إنجاز بناء مخالف للأنظمة البلدية', 'صورة عن سند الملكية، مخططات البناء، تقرير مهندس', NULL, '5-7 أيام عمل', '50000.00', 1, 3, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'إفادة إنجاز بناء مخالف', 'إفادة إنجاز بناء مخالف'),
(4, 'إفادة شغور', 'إفادة تثبت شغور العقار وعدم وجود ساكنين فيه', 'صورة عن سند الملكية، هوية المالك', NULL, '2-3 أيام عمل', '15000.00', 1, 4, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'إفادة شغور', 'إفادة شغور'),
(5, 'إفادة محتويات وإشغال', 'إفادة تثبت محتويات العقار وحالة الإشغال', 'صورة عن سند الملكية، هوية طالب الإفادة', NULL, '3-4 أيام عمل', '20000.00', 1, 5, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'إفادة محتويات وإشغال', 'إفادة محتويات وإشغال'),
(6, 'إفادة محتويات', 'إفادة تثبت محتويات العقار فقط', 'صورة عن سند الملكية، هوية طالب الإفادة', NULL, '2-3 أيام عمل', '15000.00', 1, 6, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'إفادة محتويات', 'إفادة محتويات'),
(7, 'إفادة نقل ملكية', 'إفادة مطلوبة لإجراءات نقل ملكية العقار', 'صورة عن سند الملكية، هوية المالك الحالي والجديد، عقد البيع', NULL, '3-5 أيام عمل', '30000.00', 1, 7, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'إفادة نقل ملكية', 'إفادة نقل ملكية'),
(8, 'الترخيص لأماكن الاجتماع وأندية المراهنات', 'ترخيص لفتح وتشغيل أماكن الاجتماع وأندية المراهنات', 'طلب خطي، مخططات المكان، موافقات أمنية، رخصة تجارية', NULL, '10-15 يوم عمل', '200000.00', 1, 8, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'الترخيص لأماكن الاجتماع وأندية المراهنات', 'الترخيص لأماكن الاجتماع وأندية المراهنات'),
(9, 'الترخيص لمحلات ومحطات توزيع المحروقات السائلة', 'ترخيص لفتح وتشغيل محطات ومحلات بيع المحروقات', 'طلب خطي، مخططات المحطة، موافقات بيئية وأمنية، رخصة تجارية', NULL, '15-20 يوم عمل', '500000.00', 1, 9, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'الترخيص لمحلات ومحطات توزيع المحروقات السائلة', 'الترخيص لمحلات ومحطات توزيع المحروقات السائلة'),
(10, 'الحصول على وصل بالتصريح لمؤسسة مصنفة من الفئة الثالثة (غير صناعية)', 'تصريح للمؤسسات المصنفة من الفئة الثالثة غير الصناعية', 'طلب خطي، وصف المؤسسة، دراسة تأثير بيئي، رخصة تجارية', NULL, '7-10 أيام عمل', '100000.00', 1, 10, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'الحصول على وصل بالتصريح لمؤسسة مصنفة من الفئة الثالثة (غير صناعية)', 'الحصول على وصل بالتصريح لمؤسسة مصنفة من الفئة الثالثة (غير صناعية)'),
(11, 'إيصال إرتفاق وتخطيط', 'إيصال يثبت الارتفاقات والتخطيط للعقار', 'صورة عن سند الملكية، مخطط مساحي، هوية المالك', NULL, '5-7 أيام عمل', '40000.00', 1, 11, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'إيصال إرتفاق وتخطيط', 'إيصال إرتفاق وتخطيط'),
(12, 'تسوية مخالفة بناء', 'طلب تسوية مخالفة بناء مع دفع الغرامات المترتبة', 'صورة عن سند الملكية، مخططات البناء، تقرير مهندس، إقرار بالمخالفة', NULL, '10-15 يوم عمل', '100000.00', 1, 12, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'تسوية مخالفة بناء', 'تسوية مخالفة بناء'),
(13, 'شكوى أو مراجعة', 'تقديم شكوى أو مراجعة للبلدية حول أي موضوع', 'طلب خطي مفصل، مستندات داعمة (إن وجدت)', NULL, '3-7 أيام عمل', '0.00', 1, 13, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'شكوى أو مراجعة', 'شكوى أو مراجعة'),
(14, 'طلب إعفاء محل إقامة معوق من الرسوم على القيمة التأجيرية', 'طلب إعفاء من الرسوم للمعوقين', 'شهادة إعاقة، إثبات إقامة، صورة عن سند الملكية أو عقد إيجار', NULL, '5-7 أيام عمل', '0.00', 1, 14, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'طلب إعفاء محل إقامة معوق من الرسوم على القيمة التأجيرية', 'طلب إعفاء محل إقامة معوق من الرسوم على القيمة التأجيرية'),
(15, 'طلب إفادة إنجاز بناء قبل 1964', 'إفادة تثبت إنجاز البناء قبل عام 1964', 'صورة عن سند الملكية، مستندات تثبت تاريخ البناء، هوية المالك', NULL, '7-10 أيام عمل', '25000.00', 1, 15, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'طلب إفادة إنجاز بناء قبل 1964', 'طلب إفادة إنجاز بناء قبل 1964'),
(16, 'طلب إفادة أو بيان', 'طلب إفادة أو بيان عام من البلدية', 'طلب خطي يحدد نوع الإفادة المطلوبة، هوية طالب الإفادة', NULL, '2-5 أيام عمل', '10000.00', 1, 16, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'طلب إفادة أو بيان', 'طلب إفادة أو بيان'),
(17, 'طلب إفادة عدم وجود بناء', 'إفادة تثبت عدم وجود بناء على قطعة الأرض', 'صورة عن سند الملكية، مخطط مساحي، هوية المالك', NULL, '3-5 أيام عمل', '20000.00', 1, 17, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'طلب إفادة عدم وجود بناء', 'طلب إفادة عدم وجود بناء'),
(18, 'طلب الموافقة على حفريات في الشوارع العامة', 'طلب موافقة لإجراء حفريات في الشوارع العامة', 'طلب خطي مفصل، مخططات الحفريات، ضمانة مالية، تأمين', NULL, '5-10 أيام عمل', '75000.00', 1, 18, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'طلب الموافقة على حفريات في الشوارع العامة', 'طلب الموافقة على حفريات في الشوارع العامة'),
(19, 'طلب براءة ذمة بلدية', 'شهادة تثبت عدم وجود مستحقات مالية على المكلف', 'هوية طالب الشهادة، صورة عن سند الملكية (إن وجد)', NULL, '1-2 يوم عمل', '5000.00', 1, 19, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'طلب براءة ذمة بلدية', 'طلب براءة ذمة بلدية'),
(20, 'طلب تجديد ترخيص بالبناء', 'تجديد ترخيص بناء منتهي الصلاحية', 'الترخيص الأصلي، مخططات محدثة، هوية المالك', NULL, '3-5 أيام عمل', '30000.00', 1, 20, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'طلب تجديد ترخيص بالبناء', 'طلب تجديد ترخيص بالبناء'),
(21, 'طلب تخمين الثمن البيعي للمتر المربع من أرض العقار', 'تقدير قيمة المتر المربع من أرض العقار', 'صورة عن سند الملكية، مخطط مساحي، هوية المالك', NULL, '5-7 أيام عمل', '35000.00', 1, 21, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'طلب تخمين الثمن البيعي للمتر المربع من أرض العقار', 'طلب تخمين الثمن البيعي للمتر المربع من أرض العقار'),
(22, 'طلب ترخيص إضافة بناء', 'ترخيص لإضافة بناء جديد على عقار موجود', 'مخططات الإضافة، صورة عن سند الملكية، ترخيص البناء الأصلي', NULL, '7-10 أيام عمل', '50000.00', 1, 22, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'طلب ترخيص إضافة بناء', 'طلب ترخيص إضافة بناء'),
(23, 'طلب ترخيص باحتراف المهن بالتجول', 'ترخيص لممارسة مهنة متنقلة ضمن نطاق البلدية', 'طلب خطي، شهادة حسن سلوك، إثبات كفاءة مهنية، صورة شخصية', NULL, '3-5 أيام عمل', '25000.00', 1, 23, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'طلب ترخيص باحتراف المهن بالتجول', 'طلب ترخيص باحتراف المهن بالتجول'),
(24, 'طلب ترخيص بإشغال الأملاك العمومية البلدية', 'ترخيص لاستخدام الأملاك العامة البلدية', 'طلب خطي مفصل، مخططات الاستخدام، ضمانة مالية', NULL, '7-10 أيام عمل', '100000.00', 1, 24, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'طلب ترخيص بإشغال الأملاك العمومية البلدية', 'طلب ترخيص بإشغال الأملاك العمومية البلدية'),
(25, 'طلب ترخيص بالبناء', 'ترخيص لبناء مشروع جديد', 'مخططات معمارية وإنشائية، صورة عن سند الملكية، دراسة تأثير بيئي', NULL, '10-15 يوم عمل', '75000.00', 1, 25, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'طلب ترخيص بالبناء', 'طلب ترخيص بالبناء'),
(26, 'طلب ترخيص بالترميم أو إضافة بناء', 'ترخيص لترميم بناء موجود أو إضافة عليه', 'مخططات الترميم/الإضافة، صورة عن سند الملكية، ترخيص البناء الأصلي', NULL, '7-10 أيام عمل', '40000.00', 1, 26, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'طلب ترخيص بالترميم أو إضافة بناء', 'طلب ترخيص بالترميم أو إضافة بناء'),
(27, 'طلب ترخيص للإعلان الدائم والمؤقت', 'ترخيص لوضع إعلانات دائمة أو مؤقتة', 'نموذج الإعلان، موقع الإعلان، مدة العرض، رسوم الترخيص', NULL, '3-5 أيام عمل', '20000.00', 1, 27, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'طلب ترخيص للإعلان الدائم والمؤقت', 'طلب ترخيص للإعلان الدائم والمؤقت'),
(28, 'طلب تسجيل عقد إيجار', 'تسجيل عقد إيجار لدى البلدية', 'عقد الإيجار الأصلي، هوية المؤجر والمستأجر، صورة عن سند الملكية', NULL, '2-3 أيام عمل', '15000.00', 1, 28, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'طلب تسجيل عقد إيجار', 'طلب تسجيل عقد إيجار'),
(29, 'طلب تصريح بالترميم أو بناء التصاوين أو بناء جدران دعم', 'تصريح لأعمال ترميم أو بناء تصاوين أو جدران دعم', 'مخططات الأعمال، صورة عن سند الملكية، تقرير مهندس', NULL, '5-7 أيام عمل', '30000.00', 1, 29, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'طلب تصريح بالترميم أو بناء التصاوين أو بناء جدران دعم', 'طلب تصريح بالترميم أو بناء التصاوين أو بناء جدران دعم'),
(30, 'طلب تقسيط رسوم بلدية', 'طلب تقسيط المستحقات المالية المترتبة على المكلف', 'كشف بالمستحقات، إثبات دخل، ضمانة (إن لزم)', NULL, '3-5 أيام عمل', '0.00', 1, 30, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'طلب تقسيط رسوم بلدية', 'طلب تقسيط رسوم بلدية'),
(31, 'طلب ربط مجرور المياه المبتذلة بالمجرور العام', 'طلب ربط شبكة الصرف الصحي بالشبكة العامة', 'مخططات الربط، صورة عن سند الملكية، موافقة فنية', NULL, '7-10 أيام عمل', '150000.00', 1, 31, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'طلب ربط مجرور المياه المبتذلة بالمجرور العام', 'طلب ربط مجرور المياه المبتذلة بالمجرور العام'),
(32, 'طلب رخصة إشغال (سكن)', 'رخصة إشغال للمباني السكنية', 'شهادة إنجاز، مخططات البناء، ترخيص البناء الأصلي', NULL, '5-7 أيام عمل', '25000.00', 1, 32, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'طلب رخصة إشغال (سكن)', 'طلب رخصة إشغال (سكن)'),
(33, 'طلب رخصة ثكنة قرميد', 'رخصة لبناء ثكنة من القرميد', 'مخططات الثكنة، صورة عن سند الملكية، موافقة بيئية', NULL, '5-7 أيام عمل', '20000.00', 1, 33, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'طلب رخصة ثكنة قرميد', 'طلب رخصة ثكنة قرميد'),
(34, 'طلب شراء فضلة عقار من الأملاك البلدية', 'طلب شراء قطعة أرض فاضلة من أملاك البلدية', 'طلب خطي، إثبات هوية، تقدير قيمة الأرض', NULL, '10-15 يوم عمل', '0.00', 1, 34, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'طلب شراء فضلة عقار من الأملاك البلدية', 'طلب شراء فضلة عقار من الأملاك البلدية'),
(35, 'طلب صورة طبق الأصل', 'طلب صورة مصدقة عن وثيقة موجودة في أرشيف البلدية', 'طلب خطي يحدد الوثيقة المطلوبة، هوية طالب الصورة', NULL, '1-2 يوم عمل', '5000.00', 1, 35, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'طلب صورة طبق الأصل', 'طلب صورة طبق الأصل'),
(36, 'طلب ضم طريق خاص إلى الملك العام البلدي', 'طلب ضم طريق خاص إلى الأملاك العامة البلدية', 'مخطط الطريق، صورة عن سند الملكية، موافقة المالكين المجاورين', NULL, '15-20 يوم عمل', '0.00', 1, 36, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'طلب ضم طريق خاص إلى الملك العام البلدي', 'طلب ضم طريق خاص إلى الملك العام البلدي'),
(38, 'طلبات مختلفة من موظفي البلدية (طلب مساعدة مرضية، إدارية، مالية، مختلف)', 'طلبات داخلية من موظفي البلدية', 'حسب نوع الطلب، تقارير طبية (للمساعدة المرضية)', NULL, '3-7 أيام عمل', '0.00', 1, 38, '2025-06-22 11:11:04', '2025-07-12 11:19:33', '0.00', 1, 'طلبات مختلفة من موظفي البلدية (طلب مساعدة مرضية، إدارية، مالية، مختلف)', 'طلبات مختلفة من موظفي البلدية (طلب مساعدة مرضية، إدارية، مالية، مختلف)'),
(39, 'اعتراض على الرسوم البلدية', 'تقديم اعتراض على قيمة الرسوم المفروضة', 'كشف الرسوم المعترض عليها، مبررات الاعتراض، مستندات داعمة', NULL, '7-10 أيام عمل', '0.00', 1, 39, '2025-06-22 11:11:04', '2025-11-21 20:38:35', '0.00', 1, 'اعتراض على الرسوم البلدية', 'اعتراض على الرسوم البلدية');

-- --------------------------------------------------------

--
-- Table structure for table `request_updates`
--

CREATE TABLE `request_updates` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `update_type` varchar(100) NOT NULL DEFAULT 'تحديث',
  `update_text` text NOT NULL,
  `updated_by` varchar(255) DEFAULT NULL,
  `is_visible_to_citizen` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `request_updates`
--

INSERT INTO `request_updates` (`id`, `request_id`, `update_type`, `update_text`, `updated_by`, `is_visible_to_citizen`, `created_at`) VALUES
(1, 8, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-06-28 22:11:24'),
(2, 9, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-07-06 22:13:05'),
(3, 9, 'تحديث الحالة', 'تم تحديث حالة الطلب إلى: مكتمل\nملاحظات: تمت المعالجة', '1', 1, '2025-07-06 23:14:42'),
(4, 9, 'تحديث الحالة', 'تم تحديث حالة الطلب إلى: مكتمل\nملاحظات: تمت المعالجة', '1', 1, '2025-07-06 23:22:56'),
(5, 10, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-07-10 19:29:24'),
(6, 8, 'تحديث الحالة', 'تم تحديث حالة الطلب إلى: جديد\nملاحظات: يييييسيسي', '1', 1, '2025-07-10 20:02:11'),
(7, 10, 'تحديث الحالة', 'تم تحديث حالة الطلب إلى: جديد\nملاحظات: سيسيسيسيسي', '1', 1, '2025-07-10 20:02:35'),
(8, 10, 'تحديث الحالة', 'تم تحديث حالة الطلب إلى: جديد\nملاحظات: تمت المعالجة بالفعل', '1', 1, '2025-07-10 20:02:55'),
(9, 7, 'تحديث الحالة', 'تم تحديث حالة الطلب إلى: قيد المراجعة\nملاحظات: مازالت الامور قيد الدرس', '1', 1, '2025-07-10 21:30:17'),
(10, 3, 'تحديث الحالة', 'تم تحديث حالة الطلب إلى: جديد\nملاحظات: Done', '1', 1, '2025-07-10 22:07:27'),
(11, 3, 'تحديث الحالة', 'تم تحديث حالة الطلب إلى: مكتمل\nملاحظات: Done', '1', 1, '2025-07-10 22:08:06'),
(12, 10, 'تحديث الحالة', 'تم تحديث حالة الطلب إلى: جديد\nملاحظات: تمت المعالجة بالفعل', '1', 1, '2025-07-11 23:56:44'),
(13, 11, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-07-12 11:44:53'),
(14, 11, 'تحديث الحالة', 'تم تحديث حالة الطلب إلى: قيد المراجعة\nملاحظات: تم استلام الطلب', '1', 1, '2025-07-12 11:46:42'),
(15, 12, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-01 20:11:24'),
(16, 12, 'تحديث الحالة', 'تم تحديث حالة الطلب إلى: جديد\nملاحظات: ddddddddddddddddddddddddd', '1', 1, '2025-11-01 22:14:38'),
(17, 13, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-09 23:51:30'),
(18, 13, 'تحديث الحالة', 'تم تحديث حالة الطلب إلى: قيد المراجعة\nملاحظات: قثقثفقثف', '1', 1, '2025-11-09 23:53:24'),
(19, 14, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-10 01:16:56'),
(20, 15, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-11 18:35:04'),
(21, 16, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-11 18:36:21'),
(22, 16, 'تحديث الحالة', 'تم تحديث حالة الطلب إلى: جديد', '1', 1, '2025-11-11 18:37:29'),
(23, 17, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-11 18:42:17'),
(24, 18, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-11 18:48:56'),
(25, 19, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-11 20:14:28'),
(26, 20, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-11 20:16:26'),
(27, 21, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-11 20:27:46'),
(28, 22, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-11 20:39:00'),
(29, 22, 'تحديث الحالة', 'تم تحديث حالة الطلب إلى: جديد\nملاحظات: نعم تم الموضوع', '1', 1, '2025-11-11 21:53:17'),
(30, 22, 'تحديث الحالة', 'تم تحديث حالة الطلب إلى: قيد التنفيذ\nملاحظات: نعم تم الموضوع - من قبل لجنة الاغال', '1', 1, '2025-11-11 22:01:01'),
(31, 23, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-11 23:42:16'),
(32, 24, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-11 23:47:44'),
(33, 25, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-11 23:52:05'),
(34, 26, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-11 23:54:05'),
(35, 27, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-12 00:11:09'),
(36, 28, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-12 00:12:19'),
(37, 29, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-12 00:19:22'),
(38, 30, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-12 00:25:13'),
(39, 31, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-12 00:30:13'),
(40, 32, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-12 00:31:09'),
(41, 33, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-12 00:34:38'),
(42, 34, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-12 00:36:59'),
(43, 35, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-12 00:37:49'),
(44, 36, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-12 00:42:48'),
(45, 37, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-12 00:46:42'),
(46, 38, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-12 00:49:28'),
(47, 39, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-12 10:18:50'),
(48, 40, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-12 10:27:32'),
(49, 41, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-12 11:35:52'),
(50, 42, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-12 17:49:57'),
(51, 43, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-12 17:51:33'),
(52, 27, 'تحديث الحالة', 'تم تحديث حالة الطلب إلى: جديد\nملاحظات: werwerewrwrwerwerwerewrwee', '1', 1, '2025-11-12 21:05:25'),
(53, 44, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-17 22:26:11'),
(54, 45, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-17 22:26:22'),
(55, 46, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-17 22:29:47'),
(56, 46, 'تحديث الحالة', 'تم تحديث حالة الطلب إلى: جديد\nملاحظات: asasddddddddddddddddda\r\nasdsadsada\r\nsadasdasd\r\nasdsadsad\r\nasdsadsadasdasdasdasdasdad', '1', 1, '2025-11-17 22:36:27'),
(57, 47, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-17 22:38:02'),
(58, 48, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-18 21:05:59'),
(59, 49, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-18 21:06:21'),
(60, 50, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-18 21:06:43'),
(61, 51, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-18 21:27:27'),
(62, 52, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-18 21:27:50'),
(63, 53, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-18 21:28:13'),
(64, 54, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-18 21:28:36'),
(65, 55, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-18 21:28:59'),
(66, 56, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-18 21:49:23'),
(67, 57, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-18 21:59:25'),
(68, 58, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-18 22:17:23'),
(69, 59, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-18 22:26:38'),
(70, 60, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-18 22:27:48'),
(71, 61, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-18 22:31:38'),
(72, 62, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-18 23:00:03'),
(73, 63, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-18 23:28:46'),
(74, 64, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-19 00:02:08'),
(75, 65, 'تحديث حالة', 'تم استلام الطلب وهو قيد المراجعة', 'النظام', 1, '2025-11-22 20:18:43');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(200) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'admin', 'مدير النظام', '2025-06-09 10:52:43'),
(2, 'mayor', 'رئيس البلدية', '2025-06-09 10:52:43'),
(3, 'employee', 'موظف', '2025-06-09 10:52:43');

-- --------------------------------------------------------

--
-- Table structure for table `source_categories`
--

CREATE TABLE `source_categories` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL COMMENT 'كود الفئة',
  `name_ar` varchar(255) NOT NULL,
  `name_en` varchar(255) DEFAULT NULL,
  `description_ar` text DEFAULT NULL,
  `target_table` varchar(100) DEFAULT NULL COMMENT 'الجدول المستهدف',
  `mapper_class` varchar(100) DEFAULT NULL COMMENT 'اسم class المعالج',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `source_categories`
--

INSERT INTO `source_categories` (`id`, `code`, `name_ar`, `name_en`, `description_ar`, `target_table`, `mapper_class`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'GOV_DIRECTORY', 'دليل الحكومة اللبنانية', 'Lebanese Government Directory', 'وزارات ومؤسسات رسمية', 'important_links', 'GovernmentDirectoryMapper', 1, '2025-11-24 18:05:16', '2025-11-24 18:05:16'),
(2, 'PUBLIC_HOSPITALS', 'مستشفيات حكومية', 'Public Hospitals', 'مستشفيات حكومية من وزارة الصحة', 'important_links', 'HospitalsMapper', 1, '2025-11-24 18:05:16', '2025-11-24 18:05:16'),
(3, 'EDUCATION_SCHOOLS', 'مدارس رسمية / جامعات', 'Public Schools / Universities', 'مدارس وجامعات رسمية', 'important_links', 'EducationMapper', 1, '2025-11-24 18:05:16', '2025-11-24 18:05:16'),
(4, 'EMBASSIES', 'سفارات', 'Embassies', 'سفارات وقنصليات في لبنان', 'important_links', 'EmbassiesMapper', 1, '2025-11-24 18:05:16', '2025-11-24 18:05:16'),
(5, 'PRIVATE_HOSPITALS', 'مستشفيات خاصة', 'Private Hospitals', 'مستشفيات خاصة', 'important_links', 'HospitalsMapper', 1, '2025-11-24 18:05:16', '2025-11-24 18:05:16');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `supplier_code` varchar(50) NOT NULL COMMENT 'رمز المورد',
  `name` varchar(255) NOT NULL COMMENT 'اسم المورد',
  `contact_person` varchar(255) DEFAULT NULL COMMENT 'الشخص المسؤول',
  `phone` varchar(50) DEFAULT NULL COMMENT 'رقم الهاتف',
  `mobile` varchar(50) DEFAULT NULL COMMENT 'رقم الموبايل',
  `email` varchar(100) DEFAULT NULL COMMENT 'البريد الإلكتروني',
  `address` text DEFAULT NULL COMMENT 'العنوان',
  `service_type` varchar(255) NOT NULL COMMENT 'نوع الخدمة أو المواد',
  `tax_number` varchar(100) DEFAULT NULL COMMENT 'الرقم الضريبي',
  `commercial_registration` varchar(100) DEFAULT NULL COMMENT 'السجل التجاري',
  `payment_terms` varchar(255) DEFAULT NULL COMMENT 'شروط الدفع',
  `bank_account` varchar(100) DEFAULT NULL COMMENT 'رقم الحساب البنكي',
  `bank_name` varchar(255) DEFAULT NULL COMMENT 'اسم البنك',
  `is_active` tinyint(1) DEFAULT 1 COMMENT '1=نشط، 0=غير نشط',
  `notes` text DEFAULT NULL COMMENT 'ملاحظات',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `supplier_code`, `name`, `contact_person`, `phone`, `mobile`, `email`, `address`, `service_type`, `tax_number`, `commercial_registration`, `payment_terms`, `bank_account`, `bank_name`, `is_active`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'SUP001', 'Ziad El Rez', 'Ziad El Rez', '03495685', '03495685', 'ziadelrez@gmail.com', 'Tripoli , North Lebanon', 'مواد بناء', '', '', '30 يوم نقد', '', '', 1, '', '2025-11-03 12:09:25', '2025-11-03 12:09:25');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_invoices`
--

CREATE TABLE `supplier_invoices` (
  `id` int(11) NOT NULL,
  `invoice_number` varchar(100) NOT NULL COMMENT 'رقم الفاتورة',
  `supplier_id` int(11) NOT NULL,
  `invoice_date` date NOT NULL COMMENT 'تاريخ الفاتورة',
  `due_date` date NOT NULL COMMENT 'تاريخ الاستحقاق',
  `total_amount` decimal(15,2) NOT NULL COMMENT 'المبلغ الإجمالي',
  `currency_id` int(11) NOT NULL COMMENT 'العملة',
  `exchange_rate` decimal(10,4) DEFAULT 1.0000 COMMENT 'سعر الصرف',
  `status` enum('غير مدفوع','مدفوع جزئياً','مدفوع بالكامل','متأخر','ملغي') DEFAULT 'غير مدفوع',
  `paid_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'المبلغ المدفوع',
  `remaining_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'المبلغ المتبقي',
  `description` text DEFAULT NULL COMMENT 'وصف الفاتورة',
  `file_path` varchar(500) DEFAULT NULL COMMENT 'مسار ملف الفاتورة',
  `payment_date` date DEFAULT NULL COMMENT 'تاريخ آخر دفعة',
  `related_project_id` int(11) DEFAULT NULL COMMENT 'المشروع المرتبط',
  `budget_item_id` int(11) DEFAULT NULL COMMENT 'بند الميزانية',
  `committee_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_invoices`
--

INSERT INTO `supplier_invoices` (`id`, `invoice_number`, `supplier_id`, `invoice_date`, `due_date`, `total_amount`, `currency_id`, `exchange_rate`, `status`, `paid_amount`, `remaining_amount`, `description`, `file_path`, `payment_date`, `related_project_id`, `budget_item_id`, `committee_id`, `created_by`, `approved_by`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'INV-2025-001', 1, '2025-11-03', '2025-11-05', '200.00', 2, '1.0000', 'مدفوع بالكامل', '200.00', '0.00', 'شراء معدات', NULL, '2025-11-03', NULL, 10, NULL, 1, NULL, '', '2025-11-03 19:52:09', '2025-11-03 20:10:04'),
(4, 'INV-2025-002', 1, '2025-11-04', '2025-11-05', '800.00', 2, '1.0000', 'مدفوع بالكامل', '800.00', '0.00', 'فاتورة انشاء حديقة في وسط البلدة', NULL, '2025-11-03', 2, NULL, NULL, 1, NULL, '', '2025-11-03 20:40:38', '2025-11-03 20:43:18'),
(5, 'INV-2025-003', 1, '2025-11-19', '2025-12-01', '520.00', 2, '1.0000', 'مدفوع جزئياً', '250.00', '270.00', '', NULL, '2025-11-19', NULL, NULL, 4, 1, NULL, '', '2025-11-19 20:44:20', '2025-11-19 20:44:55');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_description`, `created_at`, `updated_at`) VALUES
(1, 'default_currency_id', '2', 'معرف العملة الافتراضية للنظام', '2025-06-05 23:35:57', '2025-07-12 00:09:01'),
(2, 'system_name', 'بلدية تكريت', 'اسم النظام', '2025-06-05 23:35:57', '2025-07-12 00:09:01'),
(3, 'admin_email', 'admin@tekrit.gov.iq', 'بريد المدير الإلكتروني', '2025-06-05 23:35:57', '2025-07-12 00:09:01'),
(4, 'currency_display_decimals', '2', '?????? ?????????????? ?????????????? ???????? ??????????????', '2025-06-05 23:35:57', '2025-06-05 23:35:57'),
(18, 'site_title', 'بلدية تكريت', 'عنوان الموقع', '2025-06-10 21:20:49', '2025-06-10 21:20:49'),
(19, 'site_description', 'الموقع الرسمي لبلدية تكريت', 'وصف الموقع', '2025-06-10 21:20:49', '2025-06-10 21:20:49'),
(20, 'welcome_message', 'أهلاً وسهلاً بكم في الموقع الرسمي لبلدية تكريت', 'رسالة الترحيب', '2025-06-10 21:20:49', '2025-06-10 21:20:49'),
(30, 'ai_provider', 'openai', 'نوع مزود خدمة الذكاء الاصطناعي', '2025-11-24 20:10:47', '2025-11-25 00:13:56'),
(31, 'ai_api_key', 'WmVsbXZyRFVuT2xkVExHWHFvSklaVHdkKzZkQWZndVpKZnREVzBINnE0NzBBQ0Y1cU4yYmhldGdvQXJkTWxGSVJ2VlMwZGVKZWF3NTlrT1NUYTBMcWMzWFRYWGdsbVBwbkdyVDBOQ2wyMVNablFDRmNmejhuR0pvK3BhbmNJUWVzNXBYNHdObUMzaTVaekZQRk9MZXVxajZMblJXWjRpSmNXY3R4K1JQU0J4T2JVb0NzRnJOUCtPNE5UVGY3TTdneUhOdnlXbUhNRHFXWThJQUNZVkoyeXE0Sm52bHUvQ3dxWHcwRGVHK1d5az06OpvIBnr1r/SMqOt59doWB6A=', 'مفتاح API للذكاء الاصطناعي (مشفر)', '2025-11-24 20:10:47', '2025-11-25 00:13:56'),
(32, 'ai_model', 'gpt-4o', 'نموذج الذكاء الاصطناعي المستخدم', '2025-11-24 20:10:47', '2025-11-25 00:13:56'),
(33, 'ai_enabled', '1', 'تفعيل الذكاء الاصطناعي', '2025-11-24 20:10:47', '2025-11-25 00:13:56'),
(34, 'ai_image_provider', 'auto', 'نوع خدمة إنشاء الصور', '2025-11-24 20:10:47', '2025-11-25 00:13:56'),
(35, 'ai_temperature', '0.7', 'درجة الإبداع للذكاء الاصطناعي (0.0 - 1.0)', '2025-11-24 20:10:47', '2025-11-24 20:10:47'),
(36, 'ai_max_tokens', '2000', 'الحد الأقصى لعدد الكلمات في رد الذكاء الاصطناعي', '2025-11-24 20:10:47', '2025-11-24 20:10:47');

-- --------------------------------------------------------

--
-- Table structure for table `tax_collections`
--

CREATE TABLE `tax_collections` (
  `id` int(11) NOT NULL,
  `collection_number` varchar(50) DEFAULT NULL,
  `citizen_id` int(11) NOT NULL,
  `tax_type_id` int(11) NOT NULL,
  `base_amount` decimal(12,2) NOT NULL,
  `discount_amount` decimal(12,2) DEFAULT 0.00,
  `penalty_amount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL,
  `currency_id` int(11) DEFAULT 1,
  `exchange_rate` decimal(10,4) DEFAULT 1.0000,
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `payment_date` date DEFAULT NULL,
  `payment_status` enum('مستحق','مدفوع جزئياً','مدفوع كاملاً','متأخر','معفى','ملغي') DEFAULT 'مستحق',
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `remaining_amount` decimal(12,2) GENERATED ALWAYS AS (`total_amount` - `paid_amount`) STORED,
  `payment_method` enum('نقد','شيك','تحويل مصرفي','بطاقة ائتمان','أخرى') DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `receipt_number` varchar(100) DEFAULT NULL,
  `service_description` text DEFAULT NULL,
  `location_details` text DEFAULT NULL,
  `period_from` date DEFAULT NULL,
  `period_to` date DEFAULT NULL,
  `issued_by_user_id` int(11) DEFAULT NULL,
  `collected_by_user_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `amount_in_lbp` decimal(15,2) GENERATED ALWAYS AS (`total_amount` * `exchange_rate`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tax_collections`
--

INSERT INTO `tax_collections` (`id`, `collection_number`, `citizen_id`, `tax_type_id`, `base_amount`, `discount_amount`, `penalty_amount`, `total_amount`, `currency_id`, `exchange_rate`, `issue_date`, `due_date`, `payment_date`, `payment_status`, `paid_amount`, `payment_method`, `reference_number`, `receipt_number`, `service_description`, `location_details`, `period_from`, `period_to`, `issued_by_user_id`, `collected_by_user_id`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'TAX20252116', 7, 10, '10000000.00', '0.00', '0.00', '10000000.00', 1, '1.0000', '2025-11-02', '2025-12-02', '2025-11-02', 'ملغي', '7500000.00', 'نقد', '34343', '3434', 'ثثثثثثثثثثث', '', NULL, NULL, 1, 1, 'لابابلابلا', '2025-11-01 23:24:19', '2025-11-01 23:35:53'),
(2, 'TAX20253229', 1, 9, '750000.00', '0.00', '0.00', '750000.00', 1, '1.0000', '2025-11-02', '2025-12-02', NULL, 'مستحق', '0.00', NULL, NULL, NULL, '', '', NULL, NULL, 1, NULL, NULL, '2025-11-01 23:38:43', '2025-11-01 23:38:43');

-- --------------------------------------------------------

--
-- Table structure for table `tax_types`
--

CREATE TABLE `tax_types` (
  `id` int(11) NOT NULL,
  `tax_code` varchar(50) NOT NULL,
  `tax_name` varchar(200) NOT NULL,
  `tax_name_en` varchar(200) DEFAULT NULL,
  `category` enum('رسوم خدمات','ضرائب','غرامات','تراخيص','إشغالات','أخرى') NOT NULL,
  `description` text DEFAULT NULL,
  `calculation_method` enum('مبلغ ثابت','نسبة مئوية','حسب المساحة','حسب القيمة','حسب المدة','معقد') DEFAULT 'مبلغ ثابت',
  `base_amount` decimal(12,2) DEFAULT NULL,
  `percentage_rate` decimal(5,2) DEFAULT NULL,
  `currency_id` int(11) DEFAULT 1,
  `payment_frequency` enum('مرة واحدة','سنوي','نصف سنوي','ربع سنوي','شهري','أسبوعي','يومي') DEFAULT 'مرة واحدة',
  `due_period_days` int(11) DEFAULT 30,
  `applies_to` longtext DEFAULT NULL CHECK (json_valid(`applies_to`)),
  `minimum_amount` decimal(12,2) DEFAULT NULL,
  `maximum_amount` decimal(12,2) DEFAULT NULL,
  `discount_available` tinyint(1) DEFAULT 0,
  `discount_percentage` decimal(5,2) DEFAULT NULL,
  `exemption_criteria` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `effective_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `legal_basis` text DEFAULT NULL,
  `approval_number` varchar(100) DEFAULT NULL,
  `approval_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tax_description` text DEFAULT NULL,
  `tax_rate` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tax_types`
--

INSERT INTO `tax_types` (`id`, `tax_code`, `tax_name`, `tax_name_en`, `category`, `description`, `calculation_method`, `base_amount`, `percentage_rate`, `currency_id`, `payment_frequency`, `due_period_days`, `applies_to`, `minimum_amount`, `maximum_amount`, `discount_available`, `discount_percentage`, `exemption_criteria`, `is_active`, `effective_date`, `expiry_date`, `legal_basis`, `approval_number`, `approval_date`, `notes`, `created_by_user_id`, `created_at`, `updated_at`, `tax_description`, `tax_rate`) VALUES
(9, 'FEE001_LB', 'ظبط سرعة', NULL, '', 'Ï▒Ï│┘ê┘à ÏÑÏ┤Ï║Ïº┘ä Ïº┘äÏÀÏ▒┘è┘é Ïº┘äÏ╣Ïº┘à ┘ä┘äÏú┘åÏ┤ÏÀÏ® Ïº┘äÏ¬Ï¼ÏºÏ▒┘èÏ®', 'مبلغ ثابت', '750000.00', NULL, 1, 'مرة واحدة', 30, NULL, NULL, NULL, 0, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-06-04 22:35:34', '2025-11-01 22:58:21', 'ظبط سرعة قيادة', '999.99'),
(10, 'FIN001_LB', 'ضريبة الدخل', NULL, '', 'Ï║Ï▒Ïº┘àÏ® ┘àÏº┘ä┘èÏ® ┘ä┘äÏ¿┘åÏºÏí Ï¿Ï»┘ê┘å Ï¬Ï▒Ï«┘èÏÁ', 'مبلغ ثابت', '10000000.00', NULL, 1, 'مرة واحدة', 30, NULL, NULL, NULL, 0, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-06-04 22:35:34', '2025-11-01 22:57:51', '344بيلبليبليب', '34.00');

-- --------------------------------------------------------

--
-- Table structure for table `telegram_log`
--

CREATE TABLE `telegram_log` (
  `id` int(11) NOT NULL,
  `citizen_id` int(11) DEFAULT NULL,
  `telegram_chat_id` varchar(255) DEFAULT NULL,
  `telegram_username` varchar(100) DEFAULT NULL COMMENT 'Telegram Username',
  `request_id` int(11) DEFAULT NULL,
  `message_type` enum('welcome','status_update','completion','message','notification') NOT NULL DEFAULT 'message',
  `message` text NOT NULL,
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `telegram_log`
--

INSERT INTO `telegram_log` (`id`, `citizen_id`, `telegram_chat_id`, `telegram_username`, `request_id`, `message_type`, `message`, `status`, `sent_at`, `error_message`, `created_at`, `updated_at`) VALUES
(1, 26, '5368863036', NULL, 26, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-53311\r\n📝 نوع الطلب: طلب اختبار\r\n📅 التاريخ: 2025-11-12\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-C670E2A8\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.', 'failed', NULL, 'فشل الإرسال', '2025-11-12 01:58:08', '2025-11-12 01:58:08'),
(2, 26, '5368863036', NULL, 26, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-53311\r\n📝 نوع الطلب: طلب اختبار\r\n📅 التاريخ: 2025-11-12\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-C670E2A8\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.', 'failed', NULL, 'فشل الإرسال', '2025-11-12 01:58:24', '2025-11-12 01:58:25'),
(3, 26, '5368863036', NULL, 26, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-53311\r\n📝 نوع الطلب: طلب اختبار\r\n📅 التاريخ: 2025-11-12\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-C670E2A8\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.', 'failed', NULL, 'HTTP Error 400', '2025-11-12 02:02:57', '2025-11-12 02:02:57'),
(4, 2, '5368863036', NULL, 999, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-TEST\r\n📝 نوع الطلب: طلب اختبار\r\n📅 التاريخ: 2025-11-12\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-C670E2A8\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.', 'failed', NULL, 'HTTP Error 400', '2025-11-12 02:05:52', '2025-11-12 02:05:53'),
(5, 2, '5368863036', NULL, 999, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-TEST\r\n📝 نوع الطلب: طلب اختبار\r\n📅 التاريخ: 2025-11-12\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-C670E2A8\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n• تتبع الطلب: http://localhost:8080\\/public/track-request.php?tracking=REQ-2025-TEST\n• حسابك الشخصي: http://localhost:8080\\/public/citizen-dashboard.php?code=TKT-C670E2A8', 'sent', '2025-11-12 02:14:15', NULL, '2025-11-12 02:14:15', '2025-11-12 02:14:15'),
(7, 2, '5368863036', NULL, 31, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-37978\r\n📝 نوع الطلب: إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال\r\n📅 التاريخ: 2025-11-12\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-C670E2A8\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 <b>الروابط:</b>\n• <a href=\"http://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-37978\">تتبع الطلب</a>\n• <a href=\"http://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-C670E2A8\">حسابك الشخصي</a>', 'sent', '2025-11-12 02:30:13', NULL, '2025-11-12 02:30:13', '2025-11-12 02:30:13'),
(8, 2, '5368863036', NULL, 32, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-89535\r\n📝 نوع الطلب: إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال\r\n📅 التاريخ: 2025-11-12\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-C670E2A8\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 <b>الروابط:</b>\n• <a href=\"http://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-89535\">تتبع الطلب</a>\n• <a href=\"http://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-C670E2A8\">حسابك الشخصي</a>', 'sent', '2025-11-12 02:31:09', NULL, '2025-11-12 02:31:09', '2025-11-12 02:31:09'),
(9, 1, '8480545659', NULL, 33, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-04525\r\n📝 نوع الطلب: إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال\r\n📅 التاريخ: 2025-11-12\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-121683E2\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 <b>الروابط:</b>\n• تتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-04525\n\n• حسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-121683E2', 'sent', '2025-11-12 02:34:38', NULL, '2025-11-12 02:34:38', '2025-11-12 02:34:38'),
(10, 1, '8480545659', NULL, 34, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-82377\r\n📝 نوع الطلب: إفادة إشغال\r\n📅 التاريخ: 2025-11-12\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-121683E2\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n• تتبع الطلب\n• حسابك الشخصي', 'failed', NULL, 'HTTP Error 400', '2025-11-12 02:36:59', '2025-11-12 02:36:59'),
(11, 2, '5368863036', NULL, 35, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-27494\r\n📝 نوع الطلب: إفادة إشغال\r\n📅 التاريخ: 2025-11-12\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-C670E2A8\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n• تتبع الطلب\n• حسابك الشخصي', 'failed', NULL, 'HTTP Error 400', '2025-11-12 02:37:49', '2025-11-12 02:37:49'),
(12, 2, '5368863036', NULL, 999, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-TEST\r\n📝 نوع الطلب: طلب اختبار\r\n📅 التاريخ: 2025-11-12\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-C670E2A8\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n• تتبع الطلب\n• حسابك الشخصي', 'sent', '2025-11-12 02:39:47', NULL, '2025-11-12 02:39:47', '2025-11-12 02:39:47'),
(13, 1, '8480545659', NULL, 36, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-21461\r\n📝 نوع الطلب: إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال\r\n📅 التاريخ: 2025-11-12\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-121683E2\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-21461\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-121683E2', 'sent', '2025-11-12 02:42:48', NULL, '2025-11-12 02:42:48', '2025-11-12 02:42:48'),
(14, 1, '8480545659', NULL, 37, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-37438\r\n📝 نوع الطلب: إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال\r\n📅 التاريخ: 2025-11-12\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-121683E2\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-37438\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-121683E2', 'sent', '2025-11-12 02:46:42', NULL, '2025-11-12 02:46:42', '2025-11-12 02:46:42'),
(15, 1, '8480545659', NULL, 38, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-19755\r\n📝 نوع الطلب: إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال\r\n📅 التاريخ: 2025-11-12\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-121683E2\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-19755\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-121683E2', 'sent', '2025-11-12 02:49:29', NULL, '2025-11-12 02:49:28', '2025-11-12 02:49:29'),
(16, 2, '5368863036', NULL, 39, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-56009\r\n📝 نوع الطلب: إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال\r\n📅 التاريخ: 2025-11-12\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-C670E2A8\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-56009\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-C670E2A8', 'sent', '2025-11-12 12:18:50', NULL, '2025-11-12 12:18:50', '2025-11-12 12:18:50'),
(17, 2, '5368863036', NULL, 40, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-00905\r\n📝 نوع الطلب: إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال\r\n📅 التاريخ: 2025-11-12\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-C670E2A8\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-00905\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-C670E2A8', 'sent', '2025-11-12 12:27:32', NULL, '2025-11-12 12:27:32', '2025-11-12 12:27:32'),
(18, 2, '5368863036', NULL, 41, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-22860\r\n📝 نوع الطلب: إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال\r\n📅 التاريخ: 2025-11-12\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-K48BE\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-22860\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-K48BE', 'sent', '2025-11-12 13:35:52', NULL, '2025-11-12 13:35:52', '2025-11-12 13:35:52'),
(19, 1, '8480545659', NULL, 42, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-94488\r\n📝 نوع الطلب: إفادة إشغال\r\n📅 التاريخ: 2025-11-12\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-90257\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-94488\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-90257', 'sent', '2025-11-12 19:49:58', NULL, '2025-11-12 19:49:57', '2025-11-12 19:49:58'),
(20, 1, '8480545659', NULL, 43, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-12067\r\n📝 نوع الطلب: إفادة إشغال\r\n📅 التاريخ: 2025-11-12\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-90257\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-12067\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-90257', 'sent', '2025-11-12 19:51:34', NULL, '2025-11-12 19:51:33', '2025-11-12 19:51:34'),
(21, 1, '8480545659', NULL, 44, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-69014\r\n📝 نوع الطلب: إفادة إشغال\r\n📅 التاريخ: 2025-11-17\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-90257\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-69014\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-90257', 'failed', NULL, 'خطأ في الاتصال: Could not resolve host: api.telegram.org', '2025-11-18 00:26:11', '2025-11-18 00:26:22'),
(22, 1, '8480545659', NULL, 45, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-24837\r\n📝 نوع الطلب: إفادة إشغال\r\n📅 التاريخ: 2025-11-17\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-90257\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-24837\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-90257', 'failed', NULL, 'خطأ في الاتصال: Could not resolve host: api.telegram.org', '2025-11-18 00:26:22', '2025-11-18 00:26:33'),
(23, 2, '5368863036', NULL, 46, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-41966\r\n📝 نوع الطلب: إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال\r\n📅 التاريخ: 2025-11-17\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-72089\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-41966\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-72089', 'failed', NULL, 'خطأ في الاتصال: Could not resolve host: api.telegram.org', '2025-11-18 00:29:47', '2025-11-18 00:29:58'),
(24, 2, '5368863036', NULL, 47, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-13642\r\n📝 نوع الطلب: إفادة إشغال\r\n📅 التاريخ: 2025-11-17\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-72089\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-13642\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-72089', 'failed', NULL, 'خطأ في الاتصال: Could not resolve host: api.telegram.org', '2025-11-18 00:38:02', '2025-11-18 00:38:13'),
(25, 2, '5368863036', NULL, 48, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-51041\r\n📝 نوع الطلب: إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال\r\n📅 التاريخ: 2025-11-18\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-72089\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-51041\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-72089', 'failed', NULL, 'خطأ في الاتصال: Resolving timed out after 10007 milliseconds', '2025-11-18 23:05:59', '2025-11-18 23:06:10'),
(26, 2, '5368863036', NULL, 49, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-06082\r\n📝 نوع الطلب: إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال\r\n📅 التاريخ: 2025-11-18\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-72089\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-06082\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-72089', 'failed', NULL, 'خطأ في الاتصال: Resolving timed out after 10012 milliseconds', '2025-11-18 23:06:21', '2025-11-18 23:06:32'),
(27, 2, '5368863036', NULL, 50, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-30360\r\n📝 نوع الطلب: إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال\r\n📅 التاريخ: 2025-11-18\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-72089\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-30360\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-72089', 'failed', NULL, 'خطأ في الاتصال: Resolving timed out after 10001 milliseconds', '2025-11-18 23:06:43', '2025-11-18 23:06:55'),
(28, 1, '8480545659', NULL, 51, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-66110\r\n📝 نوع الطلب: إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال\r\n📅 التاريخ: 2025-11-18\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-90257\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-66110\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-90257', 'sent', '2025-11-18 23:27:38', NULL, '2025-11-18 23:27:27', '2025-11-18 23:27:38'),
(29, 1, '8480545659', NULL, 52, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-38487\r\n📝 نوع الطلب: إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال\r\n📅 التاريخ: 2025-11-18\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-90257\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-38487\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-90257', 'sent', '2025-11-18 23:28:01', NULL, '2025-11-18 23:27:50', '2025-11-18 23:28:01'),
(30, 1, '8480545659', NULL, 53, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-05763\r\n📝 نوع الطلب: إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال\r\n📅 التاريخ: 2025-11-18\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-90257\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-05763\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-90257', 'sent', '2025-11-18 23:28:25', NULL, '2025-11-18 23:28:13', '2025-11-18 23:28:25'),
(31, 1, '8480545659', NULL, 54, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-65563\r\n📝 نوع الطلب: إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال\r\n📅 التاريخ: 2025-11-18\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-90257\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-65563\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-90257', 'sent', '2025-11-18 23:28:48', NULL, '2025-11-18 23:28:36', '2025-11-18 23:28:48'),
(32, 1, '8480545659', NULL, 55, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-18225\r\n📝 نوع الطلب: إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال\r\n📅 التاريخ: 2025-11-18\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-90257\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-18225\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-90257', 'sent', '2025-11-18 23:29:11', NULL, '2025-11-18 23:28:59', '2025-11-18 23:29:11'),
(33, 2, '5368863036', NULL, 56, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-23181\r\n📝 نوع الطلب: إفادة إشغال\r\n📅 التاريخ: 2025-11-18\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-72089\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-23181\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-72089', 'sent', '2025-11-18 23:49:34', NULL, '2025-11-18 23:49:23', '2025-11-18 23:49:34'),
(34, 2, '5368863036', NULL, 57, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-59218\r\n📝 نوع الطلب: إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال\r\n📅 التاريخ: 2025-11-18\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-72089\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.', 'sent', '2025-11-18 23:59:36', NULL, '2025-11-18 23:59:25', '2025-11-18 23:59:36'),
(35, 2, '5368863036', NULL, 58, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-29926\r\n📝 نوع الطلب: إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال\r\n📅 التاريخ: 2025-11-18\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-72089\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.', 'sent', '2025-11-19 00:17:35', NULL, '2025-11-19 00:17:23', '2025-11-19 00:17:35'),
(36, 1, '8480545659', NULL, 59, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-52578\r\n📝 نوع الطلب: إفادة إشغال\r\n📅 التاريخ: 2025-11-18\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-90257\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-52578\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-90257', 'failed', NULL, 'خطأ في الاتصال: Connection timed out after 5003 milliseconds', '2025-11-19 00:26:38', '2025-11-19 00:26:43'),
(37, 2, '5368863036', NULL, 60, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-87346\r\n📝 نوع الطلب: إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال\r\n📅 التاريخ: 2025-11-18\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-72089\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-87346\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-72089', 'sent', '2025-11-19 00:28:00', NULL, '2025-11-19 00:27:48', '2025-11-19 00:28:00'),
(38, 2, '5368863036', NULL, 61, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-98760\r\n📝 نوع الطلب: إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال\r\n📅 التاريخ: 2025-11-18\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-72089\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-98760\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-72089', 'sent', '2025-11-19 00:31:50', NULL, '2025-11-19 00:31:38', '2025-11-19 00:31:50'),
(39, 2, '5368863036', NULL, 62, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-78853\r\n📝 نوع الطلب: إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال\r\n📅 التاريخ: 2025-11-19\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-72089\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://squarishly-unforestalled-shawn.ngrok-free.dev/tekrit_municipality/public/track-request.php?tracking=REQ-2025-78853\n\nحسابك الشخصي:\nhttp://squarishly-unforestalled-shawn.ngrok-free.dev/tekrit_municipality/public/citizen-dashboard.php?code=TKT-72089', 'sent', '2025-11-19 01:00:15', NULL, '2025-11-19 01:00:03', '2025-11-19 01:00:15'),
(40, 2, '5368863036', NULL, 63, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-41970\r\n📝 نوع الطلب: إعلام عن إشغال أو تبديل بالشاغلين أو تعديل في وجهة الاستعمال\r\n📅 التاريخ: 2025-11-19\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-72089\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://squarishly-unforestalled-shawn.ngrok-free.dev/tekrit_municipality/public/track-request.php?tracking=REQ-2025-41970\n\nحسابك الشخصي:\nhttp://squarishly-unforestalled-shawn.ngrok-free.dev/tekrit_municipality/public/citizen-dashboard.php?code=TKT-72089', 'sent', '2025-11-19 01:28:58', NULL, '2025-11-19 01:28:46', '2025-11-19 01:28:58'),
(41, 2, '5368863036', NULL, 64, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-34051\r\n📝 نوع الطلب: إفادة إشغال\r\n📅 التاريخ: 2025-11-19\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-72089\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-34051\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-72089', 'sent', '2025-11-19 02:02:20', NULL, '2025-11-19 02:02:08', '2025-11-19 02:02:20'),
(42, 2, '5368863036', NULL, 65, 'welcome', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: REQ-2025-69493\r\n📝 نوع الطلب: إفادة شغور\r\n📅 التاريخ: 2025-11-22\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 TKT-72089\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.\n\n🔗 الروابط:\n\nتتبع الطلب:\nhttp://localhost:8080/tekrit_municipality/public/track-request.php?tracking=REQ-2025-69493\n\nحسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-72089', 'sent', '2025-11-22 22:18:55', NULL, '2025-11-22 22:18:43', '2025-11-22 22:18:55'),
(43, 2, '5368863036', NULL, 1, '', '✅ مرحباً بك في بلدية تكريت - عكار!\n\n📢 تم تقديم شكواك بنجاح:\n\n🔢 رقم الشكوى: SHK-2025-00001\n📝 الموضوع: Request to Configure PTR Record for IP 87.117.238.50\n📂 الفئة: نفايات\n📅 التاريخ: 2025-11-22\n\n💡 يمكنك تتبع شكواك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\n🔐 TKT-72089\n\nسيتم إبلاغك بأي تحديثات على شكواك.\n\n🔗 حسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-72089\n\n🔗 حسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-72089', 'sent', '2025-11-23 00:22:54', NULL, '2025-11-23 00:22:43', '2025-11-23 00:22:54'),
(44, 2, '5368863036', NULL, 2, '', '✅ مرحباً بك في بلدية تكريت - عكار!\n\n📢 تم تقديم شكواك بنجاح:\n\n🔢 رقم الشكوى: SHK-2025-00002\n📝 الموضوع: test for msg\n📂 الفئة: أخرى\n📅 التاريخ: 2025-11-23\n\n💡 يمكنك تتبع شكواك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\n🔐 TKT-72089\n\nسيتم إبلاغك بأي تحديثات على شكواك.\n\n🔗 حسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-72089\n\n🔗 حسابك الشخصي:\nhttp://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?code=TKT-72089', 'sent', '2025-11-23 01:06:31', NULL, '2025-11-23 01:06:19', '2025-11-23 01:06:31');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `user_type` enum('admin','manager','employee') DEFAULT 'employee',
  `department` varchar(50) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `position` varchar(50) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT 0.00,
  `salary_currency_id` int(11) DEFAULT 7,
  `contract_type` enum('monthly','daily') DEFAULT 'monthly',
  `hire_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `position_id` int(11) DEFAULT NULL,
  `user_type_id` int(11) DEFAULT NULL,
  `contract_type_id` int(11) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `phone`, `user_type`, `department`, `department_id`, `position`, `salary`, `salary_currency_id`, `contract_type`, `hire_date`, `is_active`, `last_login`, `created_at`, `updated_at`, `position_id`, `user_type_id`, `contract_type_id`, `role_id`) VALUES
(1, 'admin', '$2y$10$wAzZ3UVKiKzU9kyawSO0..DKbKK6nZeIph7qnkKukWRALbbJmarj6', 'أدمن النظام', 'admin@tekrit-akkar.gov.lb', '', 'admin', 'الإدارة العامة', 1, '', '0.00', 1, 'monthly', NULL, 1, '2025-06-05 22:09:55', '2025-06-04 22:35:34', '2025-11-18 19:24:16', NULL, 4, 5, 1),
(39, 'sara_citizen', '$2y$10$Wb6BJDetc4HWCa5VoRSw..fliWhj6q8LAoyN3qIGWKTuxejOpqLye', 'سارة إبراهيم يوسف', 'sara@tekrit.gov.iq', '07901234571', 'employee', 'خدمة المواطنين', 6, 'موظفة استقبال ومتابعة', '1000000.00', 1, 'monthly', '2023-04-01', 1, NULL, '2025-06-06 21:02:40', '2025-06-09 10:52:43', NULL, 4, 5, 1),
(40, 'mohammed_security', '$2y$10$eM6reSoNfhoG3CxwMqVY8uD06vfCxaD/Z4JrbEZJg2RuU4SO2.LeG', 'محمد عبدالله حسن', 'mohammed@tekrit.gov.iq', '07901234572', 'employee', 'الأمن والسلامة', 1, 'ضابط أمن وحماية', '1300000.00', 1, 'monthly', '2023-04-15', 1, NULL, '2025-06-06 21:02:40', '2025-06-09 10:52:43', NULL, 4, 5, 1),
(41, 'aisha_planning', '$2y$10$kX2iBudjhls4W0HLWkSuNOLNXQkCdSBzHx4W0/PrvMOUKcI8t7dHq', 'عائشة عمر أحمد', 'aisha@tekrit.gov.iq', '07901234573', 'employee', 'التخطيط والتطوير', 1, 'مخططة مدن ومشاريع', '1600000.00', 1, 'monthly', '2023-05-01', 1, NULL, '2025-06-06 21:02:40', '2025-06-09 10:52:43', NULL, 4, 5, 1),
(42, 'hassan_municipal', '$2y$10$y0g1FlZkNp/jeG6nEGC2xOgVRaeWCBNiBKSu7ymXhLJ5wMq4f1.wa', 'حسن محمد علي', 'hassan@tekrit.gov.iq', '07901234574', 'employee', 'الخدمات البلدية', 1, 'مشرف نظافة وصيانة', '1100000.00', 1, 'monthly', '2023-05-15', 1, NULL, '2025-06-06 21:02:40', '2025-06-09 10:52:43', NULL, 4, 5, 1),
(44, 'ali_it', '$2y$10$rOkmb1MdxoyvfVCq/VuUYeA5N7E3L7Q6r8YJyDWRpjU8UYaQ3MMgi', 'علي حسن محمود', 'ali@tekrit.gov.iq', '07901234576', 'employee', 'الإدارة العامة', 17, 'مطور أنظمة', '1500.00', 2, 'monthly', '2023-06-15', 1, NULL, '2025-06-06 21:02:41', '2025-08-02 20:48:16', 8, 4, 5, 1),
(47, 'ali', '$2y$10$K1ZkPmjrpUm1Rpr9s2Bw1.JJRkfDQOpA3OfW8xzPIIw2cnkbSEB06', 'على حسين محمود', 'ali@tekrit.com', '03262739', 'employee', NULL, 16, 'مسؤؤل', '1400.00', 1, 'monthly', '2025-06-07', 1, NULL, '2025-06-06 22:53:41', '2025-06-09 10:52:43', 3, 10, 5, 1),
(48, 'walid', '$2y$10$p09TF7AYNmCH6Qg/.kSu0.yELRpjdLw9QPO7P1PCqcmEPU1hiwCza', 'WALID AL HASSAN', 'WALID@GMAIL.COM', '02151254', 'employee', NULL, 18, NULL, '1200.00', 2, 'monthly', '2025-06-09', 1, NULL, '2025-06-08 22:35:35', '2025-06-09 12:28:35', 7, 10, 4, 1),
(49, 'ziad', '$2y$10$mWsiPRHvObWdQ5fSiZfrqOvG18V8DkiP74eFzgWharKOCa1oSDine', 'ziad ezzedin el rez', 'ziadelrez@gmail.com', '03495685', 'employee', NULL, 1, NULL, '3600.00', 2, 'monthly', '2025-11-01', 1, NULL, '2025-11-01 22:24:28', '2025-11-21 20:36:40', 2, 4, 5, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `granted_by_user_id` int(11) NOT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_permissions`
--

INSERT INTO `user_permissions` (`id`, `user_id`, `permission_id`, `granted_by_user_id`, `granted_at`, `is_active`) VALUES
(612, 49, 42, 1, '2025-11-19 22:59:57', 1),
(614, 1, 27, 1, '2025-11-24 14:49:48', 1),
(615, 1, 28, 1, '2025-11-24 14:49:48', 1),
(616, 1, 29, 1, '2025-11-24 14:49:48', 1),
(617, 1, 30, 1, '2025-11-24 14:49:48', 1),
(618, 1, 31, 1, '2025-11-24 14:49:48', 1),
(619, 1, 32, 1, '2025-11-24 14:49:48', 1),
(620, 1, 33, 1, '2025-11-24 14:49:48', 1),
(621, 1, 34, 1, '2025-11-24 14:49:48', 1),
(622, 1, 35, 1, '2025-11-24 14:49:48', 1),
(623, 1, 36, 1, '2025-11-24 14:49:48', 1),
(624, 1, 37, 1, '2025-11-24 14:49:48', 1),
(625, 1, 38, 1, '2025-11-24 14:49:48', 1),
(626, 1, 39, 1, '2025-11-24 14:49:48', 1),
(627, 1, 40, 1, '2025-11-24 14:49:48', 1),
(628, 1, 41, 1, '2025-11-24 14:49:48', 1),
(629, 1, 42, 1, '2025-11-24 14:49:48', 1),
(630, 1, 43, 1, '2025-11-24 14:49:48', 1),
(631, 1, 44, 1, '2025-11-24 14:49:48', 1),
(632, 1, 45, 1, '2025-11-24 14:49:48', 1),
(633, 1, 46, 1, '2025-11-24 14:49:48', 1),
(634, 1, 47, 1, '2025-11-24 14:49:48', 1),
(635, 1, 48, 1, '2025-11-24 14:49:48', 1),
(636, 1, 49, 1, '2025-11-24 14:49:48', 1),
(637, 1, 50, 1, '2025-11-24 14:49:48', 1),
(638, 1, 51, 1, '2025-11-24 14:49:48', 1),
(639, 1, 52, 1, '2025-11-24 14:49:48', 1),
(640, 1, 53, 1, '2025-11-24 14:49:48', 1),
(641, 1, 54, 1, '2025-11-24 14:49:48', 1),
(642, 1, 55, 1, '2025-11-24 14:49:48', 1),
(643, 1, 56, 1, '2025-11-24 14:49:48', 1),
(644, 1, 57, 1, '2025-11-24 14:49:48', 1),
(645, 1, 58, 1, '2025-11-24 14:49:48', 1),
(646, 1, 59, 1, '2025-11-24 14:49:48', 1),
(647, 1, 60, 1, '2025-11-24 14:49:48', 1),
(648, 1, 61, 1, '2025-11-24 14:49:48', 1),
(649, 1, 62, 1, '2025-11-24 14:49:48', 1),
(650, 1, 63, 1, '2025-11-24 14:49:48', 1),
(651, 1, 64, 1, '2025-11-24 14:49:48', 1),
(652, 1, 65, 1, '2025-11-24 14:49:48', 1),
(653, 1, 66, 1, '2025-11-24 14:49:48', 1),
(654, 1, 67, 1, '2025-11-24 14:49:48', 1),
(655, 1, 68, 1, '2025-11-24 14:49:48', 1),
(656, 1, 69, 1, '2025-11-24 14:49:48', 1),
(657, 1, 70, 1, '2025-11-24 14:49:48', 1),
(658, 1, 71, 1, '2025-11-24 14:49:48', 1),
(659, 1, 72, 1, '2025-11-24 14:49:48', 1),
(660, 1, 73, 1, '2025-11-24 14:49:48', 1),
(661, 1, 74, 1, '2025-11-24 14:49:48', 1),
(662, 1, 75, 1, '2025-11-24 14:49:48', 1),
(663, 1, 76, 1, '2025-11-24 14:49:48', 1),
(664, 1, 77, 1, '2025-11-24 14:49:48', 1),
(665, 1, 78, 1, '2025-11-24 14:49:48', 1),
(666, 1, 79, 1, '2025-11-24 14:49:48', 1),
(667, 1, 80, 1, '2025-11-24 14:49:48', 1),
(668, 1, 81, 1, '2025-11-24 14:49:48', 1),
(669, 1, 82, 1, '2025-11-24 14:49:48', 1),
(670, 1, 83, 1, '2025-11-24 14:49:48', 1),
(671, 1, 84, 1, '2025-11-24 14:49:48', 1),
(672, 1, 85, 1, '2025-11-24 14:49:48', 1),
(673, 1, 86, 1, '2025-11-24 14:49:48', 1),
(674, 1, 87, 1, '2025-11-24 14:49:48', 1),
(675, 1, 88, 1, '2025-11-24 14:49:48', 1),
(676, 1, 89, 1, '2025-11-24 14:49:48', 1),
(677, 1, 90, 1, '2025-11-24 14:49:48', 1),
(678, 1, 91, 1, '2025-11-24 14:49:48', 1),
(679, 1, 92, 1, '2025-11-24 14:49:48', 1),
(680, 1, 93, 1, '2025-11-24 14:49:48', 1),
(681, 1, 94, 1, '2025-11-24 14:49:48', 1),
(682, 1, 95, 1, '2025-11-24 14:49:48', 1),
(683, 1, 96, 1, '2025-11-24 14:49:48', 1),
(684, 1, 97, 1, '2025-11-24 14:49:48', 1),
(685, 1, 98, 1, '2025-11-24 14:49:48', 1),
(686, 1, 99, 1, '2025-11-24 14:49:48', 1),
(687, 1, 100, 1, '2025-11-24 14:49:48', 1),
(688, 1, 101, 1, '2025-11-24 14:49:48', 1),
(689, 1, 102, 1, '2025-11-24 14:49:48', 1),
(690, 1, 103, 1, '2025-11-24 14:49:48', 1),
(691, 1, 104, 1, '2025-11-24 14:49:48', 1),
(692, 1, 105, 1, '2025-11-24 14:49:48', 1),
(693, 1, 106, 1, '2025-11-24 14:49:48', 1),
(694, 1, 107, 1, '2025-11-24 14:49:48', 1),
(695, 1, 108, 1, '2025-11-24 14:49:48', 1),
(696, 1, 109, 1, '2025-11-24 14:49:48', 1),
(697, 1, 110, 1, '2025-11-24 14:49:48', 1),
(698, 1, 111, 1, '2025-11-24 14:49:48', 1),
(699, 1, 112, 1, '2025-11-24 14:49:48', 1),
(700, 1, 113, 1, '2025-11-24 14:49:48', 1),
(701, 1, 114, 1, '2025-11-24 14:49:48', 1),
(702, 1, 115, 1, '2025-11-24 14:49:48', 1),
(703, 1, 116, 1, '2025-11-24 14:49:48', 1),
(704, 1, 117, 1, '2025-11-24 14:49:48', 1),
(705, 1, 118, 1, '2025-11-24 14:49:48', 1),
(706, 1, 119, 1, '2025-11-24 14:49:48', 1),
(707, 1, 120, 1, '2025-11-24 14:49:48', 1),
(708, 1, 121, 1, '2025-11-24 14:49:48', 1),
(709, 1, 122, 1, '2025-11-24 14:49:48', 1),
(710, 1, 123, 1, '2025-11-24 14:49:48', 1),
(711, 1, 124, 1, '2025-11-24 14:49:48', 1),
(712, 1, 125, 1, '2025-11-24 14:49:48', 1),
(713, 1, 126, 1, '2025-11-24 14:49:48', 1),
(714, 1, 127, 1, '2025-11-24 14:49:48', 1),
(715, 1, 128, 1, '2025-11-24 14:49:48', 1),
(716, 1, 129, 1, '2025-11-24 14:49:48', 1),
(717, 1, 130, 1, '2025-11-24 14:49:48', 1),
(718, 1, 131, 1, '2025-11-24 14:49:48', 1),
(719, 1, 144, 1, '2025-11-24 14:49:48', 1),
(720, 1, 132, 1, '2025-11-24 14:49:48', 1),
(721, 1, 133, 1, '2025-11-24 14:49:48', 1),
(722, 1, 134, 1, '2025-11-24 14:49:48', 1),
(723, 1, 135, 1, '2025-11-24 14:49:48', 1),
(724, 1, 136, 1, '2025-11-24 14:49:48', 1),
(725, 1, 137, 1, '2025-11-24 14:49:48', 1),
(726, 1, 138, 1, '2025-11-24 14:49:48', 1),
(727, 1, 139, 1, '2025-11-24 14:49:48', 1),
(728, 1, 140, 1, '2025-11-24 14:49:48', 1),
(729, 1, 141, 1, '2025-11-24 14:49:48', 1),
(730, 1, 142, 1, '2025-11-24 14:49:48', 1),
(731, 1, 143, 1, '2025-11-24 14:49:48', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_types`
--

CREATE TABLE `user_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(50) NOT NULL,
  `type_description` text DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_types`
--

INSERT INTO `user_types` (`id`, `type_name`, `type_description`, `permissions`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'مدير النظام', 'صلاحيات كاملة على جميع أجزاء النظام', '{\"admin\":true}', 1, '2025-06-07 20:31:22', '2025-06-08 21:16:37'),
(2, 'مدير', 'صلاحيات إدارية على الأقسام المختصة', '{\"manager\":true}', 1, '2025-06-07 20:31:22', '2025-06-07 21:34:47'),
(3, 'موظف', 'صلاحيات أساسية للعمل اليومي', '{\"employee\":true}', 1, '2025-06-07 20:31:22', '2025-06-07 21:34:47'),
(4, 'إداري', 'موظف إداري في النظام', '{\"employee\":true}', 1, '2025-06-07 21:18:09', '2025-06-07 21:34:47'),
(5, 'مواطن', 'مواطن عادي يستخدم النظام', '{\"citizen\":true}', 1, '2025-06-07 21:18:09', '2025-06-08 22:02:09'),
(10, 'tttrtr', 'صصثصث', '{\"admin\":true}', 1, '2025-06-08 22:33:35', '2025-06-08 22:33:35');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL,
  `vehicle_number` varchar(50) NOT NULL,
  `vehicle_type` varchar(100) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `year_made` year(4) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_cost` decimal(12,2) DEFAULT NULL,
  `current_value` decimal(12,2) DEFAULT NULL,
  `fuel_type` enum('بنزين','ديزل','كهربائي','هجين') DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `driver_name` varchar(100) DEFAULT NULL,
  `status` enum('جاهز','قيد الصيانة','معطل','خارج الخدمة') DEFAULT 'جاهز',
  `last_maintenance` date DEFAULT NULL,
  `next_maintenance` date DEFAULT NULL,
  `maintenance_cost` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_maintenance`
--

CREATE TABLE `vehicle_maintenance` (
  `id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `maintenance_date` date NOT NULL,
  `maintenance_type` enum('صيانة دورية','إصلاح','فحص','استبدال قطع') DEFAULT 'صيانة دورية',
  `description` text DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `garage_name` varchar(200) DEFAULT NULL,
  `next_maintenance_date` date DEFAULT NULL,
  `odometer_reading` int(11) DEFAULT NULL,
  `parts_replaced` text DEFAULT NULL,
  `performed_by` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_statuses`
--

CREATE TABLE `vehicle_statuses` (
  `id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL,
  `status_description` text DEFAULT NULL,
  `status_color` varchar(7) DEFAULT '#007bff',
  `is_operational` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehicle_statuses`
--

INSERT INTO `vehicle_statuses` (`id`, `status_name`, `status_description`, `status_color`, `is_operational`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'جاهز', 'المركبة جاهزة للاستخدام', '#28a745', 1, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(2, 'قيد الصيانة', 'المركبة قيد الصيانة الدورية', '#ffc107', 0, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(3, 'معطل', 'المركبة معطلة وتحتاج إصلاح', '#dc3545', 0, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(4, 'خارج الخدمة', 'المركبة خارج الخدمة نهائياً', '#6c757d', 0, 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22');

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_types`
--

CREATE TABLE `vehicle_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(50) NOT NULL,
  `type_description` text DEFAULT NULL,
  `fuel_type` enum('بنزين','ديزل','غاز','كهربائي','هجين') DEFAULT 'بنزين',
  `maintenance_frequency_days` int(11) DEFAULT 30,
  `average_fuel_consumption` decimal(5,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehicle_types`
--

INSERT INTO `vehicle_types` (`id`, `type_name`, `type_description`, `fuel_type`, `maintenance_frequency_days`, `average_fuel_consumption`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'سيارة إدارية', 'سيارة للاستخدام الإداري', 'بنزين', 30, '0.00', 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(2, 'شاحنة نفايات', 'شاحنة لجمع النفايات', 'ديزل', 15, '0.00', 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(3, 'معدة ثقيلة', 'معدات البناء والحفر', 'ديزل', 20, '0.00', 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(4, 'سيارة إسعاف', 'سيارة إسعاف للطوارئ', 'بنزين', 15, '0.00', 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(5, 'دراجة نارية', 'دراجة نارية للتنقل السريع', 'بنزين', 20, '0.00', 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22'),
(6, 'حافلة', 'حافلة لنقل الموظفين', 'ديزل', 25, '0.00', 1, '2025-06-07 20:31:22', '2025-06-07 20:31:22');

-- --------------------------------------------------------

--
-- Table structure for table `volunteer_attendance`
--

CREATE TABLE `volunteer_attendance` (
  `id` int(11) NOT NULL,
  `activity_id` int(11) NOT NULL,
  `volunteer_id` int(11) NOT NULL,
  `attendance_status` enum('حاضر','غائب','متأخر','اعتذر') DEFAULT 'حاضر',
  `attendance_time` datetime DEFAULT NULL,
  `departure_time` datetime DEFAULT NULL,
  `performance_notes` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_citizens_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_citizens_summary` (
`id` int(11)
,`name` varchar(100)
,`phone` varchar(20)
,`telegram_chat_id` varchar(100)
,`telegram_username` varchar(100)
,`permanent_access_code` varchar(20)
,`email` varchar(100)
,`national_id` varchar(50)
,`created_at` timestamp
,`last_login` timestamp
,`total_requests` bigint(21)
,`active_requests` bigint(21)
,`total_messages` bigint(21)
,`total_telegram_messages` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_citizen_messages_detailed`
-- (See below for the actual view)
--
CREATE TABLE `v_citizen_messages_detailed` (
`id` int(11)
,`citizen_id` int(11)
,`message_type` enum('عام','خاص','تحديث طلب','إشعار','تذكير')
,`title` varchar(200)
,`message` text
,`request_id` int(11)
,`priority` enum('عادي','مهم','عاجل')
,`is_read` tinyint(1)
,`read_at` timestamp
,`sent_via_whatsapp` tinyint(1)
,`whatsapp_sent_at` timestamp
,`created_by` int(11)
,`created_at` timestamp
,`citizen_name` varchar(100)
,`citizen_phone` varchar(20)
,`sender_name` varchar(100)
,`tracking_number` varchar(50)
,`request_title` varchar(500)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_contributions_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_contributions_summary` (
`id` int(11)
,`project_id` int(11)
,`project_name` varchar(200)
,`contributor_name` varchar(255)
,`contribution_amount` decimal(15,2)
,`currency_symbol` varchar(10)
,`currency_code` varchar(10)
,`contribution_date` date
,`payment_method` enum('نقد','شيك','تحويل مصرفي','بطاقة ائتمان','أخرى')
,`is_verified` tinyint(1)
,`is_anonymous` tinyint(1)
,`verified_by_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_projects_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_projects_summary` (
`id` int(11)
,`project_name` varchar(200)
,`project_type` varchar(100)
,`status` enum('مخطط','قيد التنفيذ','مكتمل','متوقف','ملغي')
,`location` varchar(200)
,`budget` decimal(15,2)
,`budget_currency` varchar(10)
,`spent_amount` decimal(15,2)
,`total_budget` decimal(15,2)
,`contributions_target` decimal(15,2)
,`contributions_collected` decimal(15,2)
,`contributions_currency` varchar(10)
,`progress_percentage` int(11)
,`is_public` tinyint(1)
,`allow_public_contributions` tinyint(1)
,`contributions_count` bigint(21)
,`transactions_count` bigint(21)
,`contributions_progress_percentage` decimal(21,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_telegram_log_detailed`
-- (See below for the actual view)
--
CREATE TABLE `v_telegram_log_detailed` (
`id` int(11)
,`citizen_id` int(11)
,`citizen_name` varchar(100)
,`citizen_phone` varchar(20)
,`telegram_chat_id` varchar(255)
,`telegram_username` varchar(100)
,`request_id` int(11)
,`tracking_number` varchar(50)
,`request_type` varchar(255)
,`message_type` enum('welcome','status_update','completion','message','notification')
,`message` text
,`status` enum('pending','sent','failed')
,`sent_at` datetime
,`error_message` text
,`created_at` datetime
);

-- --------------------------------------------------------

--
-- Table structure for table `waste_collection_schedules`
--

CREATE TABLE `waste_collection_schedules` (
  `id` int(11) NOT NULL,
  `route_name` varchar(200) NOT NULL,
  `area` varchar(200) NOT NULL,
  `schedule_type` enum('يومي','أسبوعي','نصف شهري','شهري') DEFAULT 'أسبوعي',
  `collection_day` enum('الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `assigned_team` varchar(100) DEFAULT NULL,
  `vehicle_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `waste_collection_schedules`
--

INSERT INTO `waste_collection_schedules` (`id`, `route_name`, `area`, `schedule_type`, `collection_day`, `start_time`, `end_time`, `assigned_team`, `vehicle_id`, `notes`, `is_active`, `created_by_user_id`, `created_at`, `updated_at`) VALUES
(1, 'المسار الأول - المركز', 'المنطقة المركزية', 'أسبوعي', 'الأحد', '06:00:00', '10:00:00', 'فريق النظافة الأول', NULL, NULL, 1, 1, '2025-06-05 20:17:18', '2025-06-05 20:17:18'),
(2, 'المسار الثاني - التجاري', 'الحي التجاري', 'أسبوعي', 'الاثنين', '07:00:00', '11:00:00', 'فريق النظافة الثاني', NULL, NULL, 1, 1, '2025-06-05 20:17:18', '2025-06-05 20:17:18'),
(3, 'المسار الثالث - السكني الشمالي', 'المنطقة السكنية الشمالية', 'أسبوعي', 'الثلاثاء', '06:30:00', '10:30:00', 'فريق النظافة الأول', NULL, NULL, 1, 1, '2025-06-05 20:17:18', '2025-06-05 20:17:18'),
(4, 'الطريق العام', 'شارع الحمرا', '', 'الثلاثاء', '06:00:00', '09:00:00', 'مشهور', NULL, 'يبيسبي', 1, 1, '2025-06-05 20:18:12', '2025-06-05 20:18:12');

-- --------------------------------------------------------

--
-- Table structure for table `waste_reports`
--

CREATE TABLE `waste_reports` (
  `id` int(11) NOT NULL,
  `area` varchar(200) NOT NULL,
  `report_type` enum('شكوى نظافة','طلب جمع إضافي','تلف حاوية','تسرب','أخرى') NOT NULL,
  `description` text NOT NULL,
  `reporter_name` varchar(100) DEFAULT NULL,
  `reporter_phone` varchar(20) DEFAULT NULL,
  `priority` enum('عادية','متوسطة','عالية','عاجلة') DEFAULT 'عادية',
  `location_details` text DEFAULT NULL,
  `status` enum('مفتوح','قيد المعالجة','منجز','مؤجل','مرفوض') DEFAULT 'مفتوح',
  `assigned_team` varchar(100) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `website_settings`
--

CREATE TABLE `website_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `website_settings`
--

INSERT INTO `website_settings` (`id`, `setting_key`, `setting_value`, `setting_description`, `updated_at`) VALUES
(1, 'site_title', 'بلدية تكريت', 'عنوان الموقع', '2025-06-10 05:16:08'),
(2, 'site_description', 'الموقع الرسمي لبلدية تكريت - خدمات إلكترونية للمواطنين', 'وصف الموقع', '2025-06-10 05:16:08'),
(3, 'contact_phone', '+9613194685', 'رقم الهاتف', '2025-06-12 21:22:16'),
(4, 'contact_email', 'info@tikrit-municipality.gov.iq', 'البريد الإلكتروني', '2025-06-10 05:16:08'),
(5, 'contact_address', 'مركز مدينة تكريت، محافظة الشمال - عكار', 'العنوان', '2025-06-12 21:22:16'),
(6, 'office_hours', 'السبت - الخميس: 8:00 ص - 2:00 م', 'ساعات العمل', '2025-06-10 05:16:08'),
(7, 'facebook_url', '', 'رابط فيسبوك', '2025-06-10 05:16:08'),
(8, 'twitter_url', '', 'رابط تويتر', '2025-06-10 05:16:08'),
(9, 'youtube_url', '', 'رابط يوتيوب', '2025-06-10 05:16:08'),
(10, 'welcome_message', 'أهلاً وسهلاً بكم في الموقع الرسمي لبلدية تكريت', 'رسالة الترحيب', '2025-06-10 05:16:08'),
(31, 'default_currency_id', '2', NULL, '2025-07-12 00:25:09'),
(36, 'emergency_phone', '9616487458', NULL, '2025-06-12 21:22:16'),
(37, 'contact_location_lat', '34.5180397861147', NULL, '2025-06-12 21:29:21'),
(38, 'contact_location_lng', '36.159022443926425', NULL, '2025-06-12 21:29:21'),
(39, 'contact_location_name', 'موقع بلدية تكريت - عكار', NULL, '2025-06-12 21:29:21'),
(91, 'municipality_phone', '06-123-456', 'رقم هاتف البلدية', '2025-11-10 00:57:39'),
(92, 'municipality_whatsapp_name', 'بلدية تكريت', 'اسم حساب WhatsApp Business', '2025-11-10 00:57:39'),
(103, 'telegram_bot_enabled', '1', 'تفعيل Telegram Bot', '2025-11-11 19:35:59'),
(104, 'telegram_bot_token', '8321707685:AAFddLPC76vLl2rawRDzvRDA9LJYYU5ZzVc', 'Telegram Bot Token من BotFather', '2025-11-11 22:33:25'),
(105, 'telegram_bot_username', 'TekritAkkarBot', 'اسم المستخدم للبوت', '2025-11-11 19:35:59'),
(106, 'telegram_welcome_template', '✅ مرحباً بك في بلدية تكريت - عكار!\r\n\r\n📋 تم تقديم طلبكم بنجاح:\r\n\r\n🔢 رقم التتبع: {tracking_number}\r\n📝 نوع الطلب: {request_type}\r\n📅 التاريخ: {date}\r\n\r\n💡 يمكنك تتبع طلبك والدخول لحسابك الشخصي باستخدام الرمز الثابت:\r\n🔐 {access_code}\r\n\r\nسيتم إبلاغكم بأي تحديثات على طلبكم.', 'قالب رسالة الترحيب', '2025-11-11 22:33:25'),
(107, 'telegram_status_update_template', '📢 تحديث حالة الطلب\r\n\r\n🔢 رقم التتبع: {tracking_number}\r\n📝 نوع الطلب: {request_type}\r\n\r\n🔄 الحالة الجديدة: {new_status}\r\n\r\n💬 ملاحظات: {notes}', 'قالب رسالة تحديث الحالة', '2025-11-11 22:33:25'),
(108, 'telegram_completion_template', '✅ تم إنجاز طلبكم!\r\n\r\n🔢 رقم التتبع: {tracking_number}\r\n📝 نوع الطلب: {request_type}\r\n📅 تاريخ الإنجاز: {completion_date}\r\n\r\n💬 {notes}\r\n\r\nشكراً لتعاملكم مع بلدية تكريت - عكار 🏛️', 'قالب رسالة إنجاز الطلب', '2025-11-11 22:33:25'),
(109, 'telegram_webhook_url', 'https://squarishly-unforestalled-shawn.ngrok-free.dev/tekrit_municipality/public/telegram_webhook.php', 'رابط Webhook لاستقبال رسائل Telegram', '2025-11-11 23:28:10');

-- --------------------------------------------------------

--
-- Table structure for table `youth_environmental_initiatives`
--

CREATE TABLE `youth_environmental_initiatives` (
  `id` int(11) NOT NULL,
  `initiative_name` varchar(255) NOT NULL,
  `initiative_description` text NOT NULL,
  `initiative_type` enum('شبابية','بيئية','مجتمعية','تطوعية') NOT NULL,
  `initiative_goals` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `benefits` text DEFAULT NULL,
  `target_audience` varchar(255) DEFAULT NULL,
  `required_volunteers` int(11) DEFAULT NULL,
  `max_volunteers` int(11) DEFAULT 50,
  `registered_volunteers` int(11) DEFAULT 0,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `registration_deadline` date DEFAULT NULL,
  `initiative_status` enum('مفتوحة للتسجيل','قيد التنفيذ','مكتملة','ملغية') DEFAULT 'مفتوحة للتسجيل',
  `coordinator_name` varchar(255) DEFAULT NULL,
  `coordinator_phone` varchar(20) DEFAULT NULL,
  `coordinator_email` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `budget` decimal(15,2) DEFAULT 0.00,
  `initiative_images` text DEFAULT NULL,
  `main_image` varchar(500) DEFAULT NULL,
  `success_story` text DEFAULT NULL,
  `impact_description` text DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `auto_approval` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'مخطط'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `youth_environmental_initiatives`
--

INSERT INTO `youth_environmental_initiatives` (`id`, `initiative_name`, `initiative_description`, `initiative_type`, `initiative_goals`, `requirements`, `benefits`, `target_audience`, `required_volunteers`, `max_volunteers`, `registered_volunteers`, `start_date`, `end_date`, `registration_deadline`, `initiative_status`, `coordinator_name`, `coordinator_phone`, `coordinator_email`, `location`, `budget`, `initiative_images`, `main_image`, `success_story`, `impact_description`, `is_featured`, `created_at`, `updated_at`, `is_active`, `auto_approval`, `created_by`, `status`) VALUES
(12, 'مبادرة وقف الموتوسيكلات في البلدة', 'وقف استخدام الموتو', 'مجتمعية', 'الحد من الازعاج', 'العمل مع الاهالي', 'الحد من الازعاج', 'الشباب بين 20-25 سنة', 20, 23, 5, '2025-06-14', '2025-06-20', '2025-06-13', 'مفتوحة للتسجيل', 'زباد الرز', '03495685', 'ziadelrez@gmail.com', 'بلدة تكريت', '150000000.00', NULL, 'initiative_main_12_1750289992.png', '', '', 0, '2025-06-13 20:51:52', '2025-11-01 22:19:25', 1, 1, NULL, 'نشط');

-- --------------------------------------------------------

--
-- Structure for view `complaints_detailed`
--
DROP TABLE IF EXISTS `complaints_detailed`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `complaints_detailed`  AS SELECT `c`.`id` AS `id`, `c`.`citizen_id` AS `citizen_id`, `c`.`complaint_number` AS `complaint_number`, `c`.`citizen_name` AS `citizen_name`, `c`.`citizen_phone` AS `citizen_phone`, `c`.`citizen_email` AS `citizen_email`, `c`.`citizen_address` AS `citizen_address`, `c`.`complaint_type` AS `complaint_type`, `c`.`subject` AS `subject`, `c`.`description` AS `description`, `c`.`priority` AS `priority`, `c`.`status` AS `status`, `c`.`department` AS `department`, `c`.`assigned_to` AS `assigned_to`, `c`.`response` AS `response`, `c`.`resolution_date` AS `resolution_date`, `c`.`satisfaction_rating` AS `satisfaction_rating`, `c`.`created_at` AS `created_at`, `c`.`updated_at` AS `updated_at`, `ca`.`phone` AS `citizen_phone_from_account`, `ca`.`name` AS `citizen_name_from_account`, `ca`.`telegram_chat_id` AS `telegram_chat_id`, `ca`.`telegram_username` AS `telegram_username`, `u`.`full_name` AS `assigned_user_name`, count(`cu`.`id`) AS `updates_count` FROM (((`complaints` `c` left join `citizens_accounts` `ca` on(`c`.`citizen_id` = `ca`.`id`)) left join `users` `u` on(`c`.`assigned_to` = `u`.`id`)) left join `complaint_updates` `cu` on(`c`.`id` = `cu`.`complaint_id` and `cu`.`is_visible_to_citizen` = 1)) GROUP BY `c`.`id``id`  ;

-- --------------------------------------------------------

--
-- Structure for view `v_citizens_summary`
--
DROP TABLE IF EXISTS `v_citizens_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_citizens_summary`  AS SELECT `ca`.`id` AS `id`, `ca`.`name` AS `name`, `ca`.`phone` AS `phone`, `ca`.`telegram_chat_id` AS `telegram_chat_id`, `ca`.`telegram_username` AS `telegram_username`, `ca`.`permanent_access_code` AS `permanent_access_code`, `ca`.`email` AS `email`, `ca`.`national_id` AS `national_id`, `ca`.`created_at` AS `created_at`, `ca`.`last_login` AS `last_login`, count(distinct `cr`.`id`) AS `total_requests`, count(distinct case when `cr`.`status` in ('جديد','قيد المراجعة','قيد التنفيذ') then `cr`.`id` end) AS `active_requests`, count(distinct `cm`.`id`) AS `total_messages`, count(distinct `tl`.`id`) AS `total_telegram_messages` FROM (((`citizens_accounts` `ca` left join `citizen_requests` `cr` on(`ca`.`phone` = `cr`.`citizen_phone`)) left join `citizen_messages` `cm` on(`ca`.`id` = `cm`.`citizen_id`)) left join `telegram_log` `tl` on(`ca`.`id` = `tl`.`citizen_id`)) GROUP BY `ca`.`id``id`  ;

-- --------------------------------------------------------

--
-- Structure for view `v_citizen_messages_detailed`
--
DROP TABLE IF EXISTS `v_citizen_messages_detailed`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_citizen_messages_detailed`  AS SELECT `cm`.`id` AS `id`, `cm`.`citizen_id` AS `citizen_id`, `cm`.`message_type` AS `message_type`, `cm`.`title` AS `title`, `cm`.`message` AS `message`, `cm`.`request_id` AS `request_id`, `cm`.`priority` AS `priority`, `cm`.`is_read` AS `is_read`, `cm`.`read_at` AS `read_at`, `cm`.`sent_via_whatsapp` AS `sent_via_whatsapp`, `cm`.`whatsapp_sent_at` AS `whatsapp_sent_at`, `cm`.`created_by` AS `created_by`, `cm`.`created_at` AS `created_at`, `ca`.`name` AS `citizen_name`, `ca`.`phone` AS `citizen_phone`, `u`.`full_name` AS `sender_name`, `cr`.`tracking_number` AS `tracking_number`, `cr`.`request_title` AS `request_title` FROM (((`citizen_messages` `cm` left join `citizens_accounts` `ca` on(`cm`.`citizen_id` = `ca`.`id`)) left join `users` `u` on(`cm`.`created_by` = `u`.`id`)) left join `citizen_requests` `cr` on(`cm`.`request_id` = `cr`.`id`))  ;

-- --------------------------------------------------------

--
-- Structure for view `v_contributions_summary`
--
DROP TABLE IF EXISTS `v_contributions_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_contributions_summary`  AS SELECT `pc`.`id` AS `id`, `pc`.`project_id` AS `project_id`, `p`.`project_name` AS `project_name`, `pc`.`contributor_name` AS `contributor_name`, `pc`.`contribution_amount` AS `contribution_amount`, `c`.`currency_symbol` AS `currency_symbol`, `c`.`currency_code` AS `currency_code`, `pc`.`contribution_date` AS `contribution_date`, `pc`.`payment_method` AS `payment_method`, `pc`.`is_verified` AS `is_verified`, `pc`.`is_anonymous` AS `is_anonymous`, `u`.`full_name` AS `verified_by_name` FROM (((`project_contributions` `pc` join `projects` `p` on(`pc`.`project_id` = `p`.`id`)) join `currencies` `c` on(`pc`.`currency_id` = `c`.`id`)) left join `users` `u` on(`pc`.`verified_by` = `u`.`id`))  ;

-- --------------------------------------------------------

--
-- Structure for view `v_projects_summary`
--
DROP TABLE IF EXISTS `v_projects_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_projects_summary`  AS SELECT `p`.`id` AS `id`, `p`.`project_name` AS `project_name`, `p`.`project_type` AS `project_type`, `p`.`status` AS `status`, `p`.`location` AS `location`, `p`.`budget` AS `budget`, `bc`.`currency_symbol` AS `budget_currency`, `p`.`spent_amount` AS `spent_amount`, `p`.`total_budget` AS `total_budget`, `p`.`contributions_target` AS `contributions_target`, `p`.`contributions_collected` AS `contributions_collected`, `cc`.`currency_symbol` AS `contributions_currency`, `p`.`progress_percentage` AS `progress_percentage`, `p`.`is_public` AS `is_public`, `p`.`allow_public_contributions` AS `allow_public_contributions`, (select count(0) from `project_contributions` where `project_contributions`.`project_id` = `p`.`id`) AS `contributions_count`, (select count(0) from `financial_transactions` where `financial_transactions`.`related_project_id` = `p`.`id`) AS `transactions_count`, CASE WHEN `p`.`contributions_target` > 0 THEN round(`p`.`contributions_collected` / `p`.`contributions_target` * 100,2) ELSE 0 END AS `contributions_progress_percentage` FROM ((`projects` `p` left join `currencies` `bc` on(`p`.`budget_currency_id` = `bc`.`id`)) left join `currencies` `cc` on(`p`.`contributions_currency_id` = `cc`.`id`))  ;

-- --------------------------------------------------------

--
-- Structure for view `v_telegram_log_detailed`
--
DROP TABLE IF EXISTS `v_telegram_log_detailed`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_telegram_log_detailed`  AS SELECT `tl`.`id` AS `id`, `tl`.`citizen_id` AS `citizen_id`, `ca`.`name` AS `citizen_name`, `ca`.`phone` AS `citizen_phone`, `tl`.`telegram_chat_id` AS `telegram_chat_id`, `tl`.`telegram_username` AS `telegram_username`, `tl`.`request_id` AS `request_id`, `cr`.`tracking_number` AS `tracking_number`, `rt`.`type_name` AS `request_type`, `tl`.`message_type` AS `message_type`, `tl`.`message` AS `message`, `tl`.`status` AS `status`, `tl`.`sent_at` AS `sent_at`, `tl`.`error_message` AS `error_message`, `tl`.`created_at` AS `created_at` FROM (((`telegram_log` `tl` left join `citizens_accounts` `ca` on(`tl`.`citizen_id` = `ca`.`id`)) left join `citizen_requests` `cr` on(`tl`.`request_id` = `cr`.`id`)) left join `request_types` `rt` on(`cr`.`request_type_id` = `rt`.`id`)) ORDER BY `tl`.`created_at` AS `DESCdesc` ASC  ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `appreciation_certificates`
--
ALTER TABLE `appreciation_certificates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `certificate_number` (`certificate_number`),
  ADD KEY `donor_id` (`donor_id`),
  ADD KEY `donation_id` (`donation_id`),
  ADD KEY `issued_by_user_id` (`issued_by_user_id`);

--
-- Indexes for table `associations`
--
ALTER TABLE `associations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `association_code` (`association_code`),
  ADD KEY `idx_association_code` (`association_code`),
  ADD KEY `idx_type` (`type`);

--
-- Indexes for table `budgets`
--
ALTER TABLE `budgets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `budget_code` (`budget_code`),
  ADD KEY `currency_id` (`currency_id`),
  ADD KEY `idx_fiscal_year` (`fiscal_year`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `fk_budget_committee` (`committee_id`);

--
-- Indexes for table `budget_items`
--
ALTER TABLE `budget_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `budget_id` (`budget_id`),
  ADD KEY `parent_item_id` (`parent_item_id`),
  ADD KEY `idx_item_code` (`item_code`),
  ADD KEY `idx_item_type` (`item_type`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `fk_budget_item_currency` (`currency_id`);

--
-- Indexes for table `budget_item_templates`
--
ALTER TABLE `budget_item_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `currency_id` (`currency_id`),
  ADD KEY `idx_committee` (`committee_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `building_permits`
--
ALTER TABLE `building_permits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permit_number` (`permit_number`),
  ADD KEY `currency_id` (`currency_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `issued_by` (`issued_by`),
  ADD KEY `idx_permits_status` (`status`),
  ADD KEY `idx_permits_date` (`application_date`),
  ADD KEY `idx_permits_citizen` (`citizen_id`);

--
-- Indexes for table `campaign_donations`
--
ALTER TABLE `campaign_donations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_campaign_donation` (`campaign_id`,`donation_id`),
  ADD KEY `donation_id` (`donation_id`);

--
-- Indexes for table `citizens`
--
ALTER TABLE `citizens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `citizen_number` (`citizen_number`),
  ADD KEY `idx_citizens_citizen_number` (`citizen_number`),
  ADD KEY `idx_citizens_full_name` (`full_name`),
  ADD KEY `idx_citizens_area` (`area`),
  ADD KEY `idx_citizens_verification` (`verification_status`);

--
-- Indexes for table `citizens_accounts`
--
ALTER TABLE `citizens_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD UNIQUE KEY `permanent_access_code` (`permanent_access_code`),
  ADD KEY `idx_phone` (`phone`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_telegram_chat_id` (`telegram_chat_id`),
  ADD KEY `idx_permanent_access_code` (`permanent_access_code`);

--
-- Indexes for table `citizen_messages`
--
ALTER TABLE `citizen_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_citizen` (`citizen_id`),
  ADD KEY `idx_read` (`is_read`),
  ADD KEY `idx_type` (`message_type`),
  ADD KEY `idx_request` (`request_id`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `citizen_opinions`
--
ALTER TABLE `citizen_opinions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `citizen_requests`
--
ALTER TABLE `citizen_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tracking_number` (`tracking_number`),
  ADD UNIQUE KEY `idx_project_id` (`project_id`),
  ADD KEY `idx_citizen_id` (`citizen_id`),
  ADD KEY `idx_request_type_id` (`request_type_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority_level` (`priority_level`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_citizen_phone` (`citizen_phone`),
  ADD KEY `idx_tracking_number` (`tracking_number`),
  ADD KEY `idx_priority` (`priority_level`),
  ADD KEY `idx_request_type` (`request_type_id`),
  ADD KEY `idx_phone_tracking` (`citizen_phone`,`tracking_number`),
  ADD KEY `idx_status_created` (`status`,`created_at`),
  ADD KEY `idx_citizen_requests_assigned_committee` (`assigned_to_committee_id`);

--
-- Indexes for table `citizen_sessions`
--
ALTER TABLE `citizen_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `idx_citizen` (`citizen_id`),
  ADD KEY `idx_token` (`session_token`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `cleaning_reports`
--
ALTER TABLE `cleaning_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `submitted_by` (`submitted_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `committee_decisions`
--
ALTER TABLE `committee_decisions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_committee_decisions_committee` (`committee_id`),
  ADD KEY `idx_committee_decisions_session` (`session_id`);

--
-- Indexes for table `committee_finance_summary`
--
ALTER TABLE `committee_finance_summary`
  ADD PRIMARY KEY (`committee_id`);

--
-- Indexes for table `committee_finance_transactions`
--
ALTER TABLE `committee_finance_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_finance_transactions_committee` (`committee_id`),
  ADD KEY `idx_finance_transactions_date` (`transaction_date`);

--
-- Indexes for table `committee_members`
--
ALTER TABLE `committee_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `committee_id` (`committee_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `committee_sessions`
--
ALTER TABLE `committee_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_committee_sessions_committee` (`committee_id`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `complaint_number` (`complaint_number`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `idx_complaints_status` (`status`),
  ADD KEY `idx_complaints_type` (`complaint_type`),
  ADD KEY `idx_complaints_date` (`created_at`),
  ADD KEY `idx_citizen_id` (`citizen_id`),
  ADD KEY `idx_complaint_number` (`complaint_number`);

--
-- Indexes for table `complaint_categories`
--
ALTER TABLE `complaint_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `responsible_department_id` (`responsible_department_id`);

--
-- Indexes for table `complaint_statuses`
--
ALTER TABLE `complaint_statuses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `complaint_updates`
--
ALTER TABLE `complaint_updates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_complaint_id` (`complaint_id`),
  ADD KEY `idx_updated_by` (`updated_by`),
  ADD KEY `idx_update_type` (`update_type`),
  ADD KEY `idx_visible_to_citizen` (`is_visible_to_citizen`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contract_types`
--
ALTER TABLE `contract_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `council_members`
--
ALTER TABLE `council_members`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `currencies`
--
ALTER TABLE `currencies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `currency_code` (`currency_code`),
  ADD KEY `idx_currencies_code` (`currency_code`),
  ADD KEY `idx_currencies_active` (`is_active`);

--
-- Indexes for table `currency_conversion_log`
--
ALTER TABLE `currency_conversion_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `from_currency_id` (`from_currency_id`),
  ADD KEY `to_currency_id` (`to_currency_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `department_name` (`department_name`);

--
-- Indexes for table `development_projects`
--
ALTER TABLE `development_projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `responsible_department_id` (`responsible_department_id`),
  ADD KEY `project_manager_id` (`project_manager_id`),
  ADD KEY `idx_projects_currency` (`currency_id`),
  ADD KEY `idx_projects_municipality` (`is_municipality_project`),
  ADD KEY `idx_projects_funding_source` (`funding_source`),
  ADD KEY `idx_projects_base_cost_currency` (`project_base_cost_currency_id`),
  ADD KEY `idx_projects_municipality_currency` (`municipality_contribution_currency_id`),
  ADD KEY `idx_projects_donor_currency` (`donor_contribution_currency_id`),
  ADD KEY `idx_projects_donors_currency` (`donors_contribution_currency_id`),
  ADD KEY `idx_projects_funding_completion` (`funding_completion_percentage`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `document_number` (`document_number`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `parent_document_id` (`parent_document_id`),
  ADD KEY `idx_documents_category` (`category`),
  ADD KEY `idx_documents_status` (`status`),
  ADD KEY `idx_documents_date` (`upload_date`);

--
-- Indexes for table `documents_forms`
--
ALTER TABLE `documents_forms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `donations`
--
ALTER TABLE `donations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `donation_number` (`donation_number`),
  ADD KEY `allocated_to_project_id` (`allocated_to_project_id`),
  ADD KEY `received_by_user_id` (`received_by_user_id`),
  ADD KEY `currency_id` (`currency_id`),
  ADD KEY `estimated_value_currency_id` (`estimated_value_currency_id`),
  ADD KEY `idx_donations_type` (`donation_type`),
  ADD KEY `idx_donations_status` (`status`),
  ADD KEY `idx_donations_donor_type` (`donor_type`),
  ADD KEY `idx_donations_date` (`received_date`);

--
-- Indexes for table `donation_campaigns`
--
ALTER TABLE `donation_campaigns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `target_currency_id` (`target_currency_id`),
  ADD KEY `raised_currency_id` (`raised_currency_id`),
  ADD KEY `responsible_person_id` (`responsible_person_id`);

--
-- Indexes for table `donation_statuses`
--
ALTER TABLE `donation_statuses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `donation_types`
--
ALTER TABLE `donation_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `donors`
--
ALTER TABLE `donors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `donor_code` (`donor_code`);

--
-- Indexes for table `donor_organizations`
--
ALTER TABLE `donor_organizations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `donor_code` (`donor_code`),
  ADD KEY `preferred_currency_id` (`preferred_currency_id`),
  ADD KEY `created_by_user_id` (`created_by_user_id`),
  ADD KEY `updated_by_user_id` (`updated_by_user_id`),
  ADD KEY `idx_donor_organizations_type` (`donor_type`),
  ADD KEY `idx_donor_organizations_status` (`status`),
  ADD KEY `idx_donor_organizations_code` (`donor_code`);

--
-- Indexes for table `external_data_sources`
--
ALTER TABLE `external_data_sources`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `facilities`
--
ALTER TABLE `facilities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_facility_active` (`is_active`),
  ADD KEY `idx_facility_category` (`category_id`),
  ADD KEY `idx_facility_featured` (`is_featured`),
  ADD KEY `idx_facility_location` (`latitude`,`longitude`);

--
-- Indexes for table `facility_categories`
--
ALTER TABLE `facility_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category_active` (`is_active`),
  ADD KEY `idx_category_order` (`display_order`);

--
-- Indexes for table `facility_ratings`
--
ALTER TABLE `facility_ratings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rating_facility` (`facility_id`),
  ADD KEY `idx_rating_approved` (`is_approved`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `financial_transactions`
--
ALTER TABLE `financial_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `related_project_id` (`related_project_id`),
  ADD KEY `related_donation_id` (`related_donation_id`),
  ADD KEY `idx_financial_date` (`transaction_date`),
  ADD KEY `idx_financial_type` (`type`),
  ADD KEY `idx_financial_currency` (`currency_id`),
  ADD KEY `idx_financial_status` (`status`),
  ADD KEY `idx_budget_item` (`budget_item_id`),
  ADD KEY `idx_supplier_invoice` (`supplier_invoice_id`),
  ADD KEY `idx_tax_collection` (`tax_collection_id`),
  ADD KEY `idx_association` (`association_id`),
  ADD KEY `idx_financial_transactions_committee` (`committee_id`);

--
-- Indexes for table `fiscal_periods`
--
ALTER TABLE `fiscal_periods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fiscal_year` (`fiscal_year`),
  ADD KEY `idx_dates` (`start_date`,`end_date`);

--
-- Indexes for table `important_links`
--
ALTER TABLE `important_links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category_id` (`category_id`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_is_emergency` (`is_emergency`),
  ADD KEY `idx_display_order` (`display_order`);

--
-- Indexes for table `important_link_categories`
--
ALTER TABLE `important_link_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_display_order` (`display_order`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `important_link_fetch_logs`
--
ALTER TABLE `important_link_fetch_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_source_id` (`source_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `important_link_sources`
--
ALTER TABLE `important_link_sources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category_id` (`category_id`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_next_update` (`next_update`),
  ADD KEY `idx_source_category_id` (`source_category_id`);

--
-- Indexes for table `initiative_activities`
--
ALTER TABLE `initiative_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `initiative_id` (`initiative_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `initiative_evaluations`
--
ALTER TABLE `initiative_evaluations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `initiative_id` (`initiative_id`);

--
-- Indexes for table `initiative_images`
--
ALTER TABLE `initiative_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `initiative_id` (`initiative_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `initiative_volunteers`
--
ALTER TABLE `initiative_volunteers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_volunteer_per_initiative` (`initiative_id`,`email`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `invoice_payments`
--
ALTER TABLE `invoice_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `fk_invoice_payments_committee` (`committee_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_attempted_at` (`attempted_at`),
  ADD KEY `idx_success` (`success`),
  ADD KEY `idx_username_ip_time` (`username`,`ip_address`,`attempted_at`);

--
-- Indexes for table `magic_links`
--
ALTER TABLE `magic_links`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_citizen` (`citizen_id`),
  ADD KEY `idx_expires` (`expires_at`),
  ADD KEY `idx_used` (`used`);

--
-- Indexes for table `map_settings`
--
ALTER TABLE `map_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);

--
-- Indexes for table `municipality_assets`
--
ALTER TABLE `municipality_assets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `responsible_department_id` (`responsible_department_id`);

--
-- Indexes for table `municipality_resources`
--
ALTER TABLE `municipality_resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `responsible_department_id` (`responsible_department_id`);

--
-- Indexes for table `municipal_committees`
--
ALTER TABLE `municipal_committees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `committee_name` (`committee_name`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `chairman_id` (`chairman_id`),
  ADD KEY `secretary_id` (`secretary_id`);

--
-- Indexes for table `municipal_decisions`
--
ALTER TABLE `municipal_decisions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `decision_number` (`decision_number`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `responsible_department_id` (`responsible_department_id`),
  ADD KEY `responsible_person_id` (`responsible_person_id`);

--
-- Indexes for table `municipal_sessions`
--
ALTER TABLE `municipal_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `committee_id` (`committee_id`),
  ADD KEY `chairperson_id` (`chairperson_id`),
  ADD KEY `secretary_id` (`secretary_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `news_activities`
--
ALTER TABLE `news_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_news_activities_featured` (`featured_image`),
  ADD KEY `idx_news_activities_publish_date` (`publish_date`,`is_published`);

--
-- Indexes for table `news_images`
--
ALTER TABLE `news_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `idx_news_images_news_id` (`news_id`),
  ADD KEY `idx_news_images_active` (`is_active`),
  ADD KEY `idx_news_images_order` (`display_order`);

--
-- Indexes for table `news_image_settings`
--
ALTER TABLE `news_image_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);

--
-- Indexes for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_citizen` (`citizen_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permission_name` (`permission_name`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `polls`
--
ALTER TABLE `polls`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `poll_responses`
--
ALTER TABLE `poll_responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `poll_id` (`poll_id`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `budget_currency_id` (`budget_currency_id`),
  ADD KEY `actual_cost_currency_id` (`actual_cost_currency_id`),
  ADD KEY `idx_projects_status` (`status`),
  ADD KEY `idx_projects_donor_type` (`donor_type`),
  ADD KEY `idx_projects_manager` (`manager_id`),
  ADD KEY `idx_association` (`association_id`),
  ADD KEY `idx_budget_item` (`budget_item_id`),
  ADD KEY `idx_is_public` (`is_public`),
  ADD KEY `idx_allow_contributions` (`allow_public_contributions`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_project_type` (`project_type`),
  ADD KEY `fk_contributions_currency` (`contributions_currency_id`);

--
-- Indexes for table `project_contributions`
--
ALTER TABLE `project_contributions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `financial_transaction_id` (`financial_transaction_id`),
  ADD KEY `idx_project_id` (`project_id`),
  ADD KEY `idx_contribution_date` (`contribution_date`),
  ADD KEY `idx_is_verified` (`is_verified`),
  ADD KEY `idx_currency_id` (`currency_id`);

--
-- Indexes for table `project_phases`
--
ALTER TABLE `project_phases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `currency_id` (`currency_id`),
  ADD KEY `responsible_user_id` (`responsible_user_id`),
  ADD KEY `idx_project_phases_project` (`project_id`),
  ADD KEY `idx_project_phases_status` (`status`);

--
-- Indexes for table `project_types`
--
ALTER TABLE `project_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reference_data`
--
ALTER TABLE `reference_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`type`);

--
-- Indexes for table `request_documents`
--
ALTER TABLE `request_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_request_id` (`request_id`),
  ADD KEY `idx_document_name` (`document_name`);

--
-- Indexes for table `request_form_data`
--
ALTER TABLE `request_form_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_request_id` (`request_id`),
  ADD KEY `idx_field_name` (`field_name`);

--
-- Indexes for table `request_ratings`
--
ALTER TABLE `request_ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_rating` (`request_id`);

--
-- Indexes for table `request_types`
--
ALTER TABLE `request_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `type_name` (`type_name`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_display_order` (`display_order`),
  ADD KEY `fk_request_types_currency` (`cost_currency_id`);

--
-- Indexes for table `request_updates`
--
ALTER TABLE `request_updates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_request_id` (`request_id`),
  ADD KEY `idx_update_type` (`update_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `source_categories`
--
ALTER TABLE `source_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_code` (`supplier_code`),
  ADD KEY `idx_supplier_code` (`supplier_code`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `supplier_invoices`
--
ALTER TABLE `supplier_invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `currency_id` (`currency_id`),
  ADD KEY `idx_invoice_number` (`invoice_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_invoice_date` (`invoice_date`),
  ADD KEY `fk_supplier_invoices_committee` (`committee_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `tax_collections`
--
ALTER TABLE `tax_collections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `collection_number` (`collection_number`),
  ADD KEY `tax_type_id` (`tax_type_id`),
  ADD KEY `currency_id` (`currency_id`),
  ADD KEY `issued_by_user_id` (`issued_by_user_id`),
  ADD KEY `collected_by_user_id` (`collected_by_user_id`),
  ADD KEY `idx_tax_collections_citizen` (`citizen_id`),
  ADD KEY `idx_tax_collections_status` (`payment_status`),
  ADD KEY `idx_tax_collections_date` (`issue_date`);

--
-- Indexes for table `tax_types`
--
ALTER TABLE `tax_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tax_code` (`tax_code`),
  ADD KEY `currency_id` (`currency_id`),
  ADD KEY `created_by_user_id` (`created_by_user_id`),
  ADD KEY `idx_tax_types_category` (`category`),
  ADD KEY `idx_tax_types_code` (`tax_code`),
  ADD KEY `idx_tax_types_active` (`is_active`);

--
-- Indexes for table `telegram_log`
--
ALTER TABLE `telegram_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_citizen_id` (`citizen_id`),
  ADD KEY `idx_telegram_chat_id` (`telegram_chat_id`),
  ADD KEY `idx_request_id` (`request_id`),
  ADD KEY `idx_status` (`status`,`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_username` (`username`),
  ADD UNIQUE KEY `username_unique` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_department` (`department`),
  ADD KEY `idx_users_user_type` (`user_type`),
  ADD KEY `fk_users_currency` (`salary_currency_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_permission` (`user_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`),
  ADD KEY `granted_by_user_id` (`granted_by_user_id`);

--
-- Indexes for table `user_types`
--
ALTER TABLE `user_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vehicle_number` (`vehicle_number`),
  ADD KEY `idx_vehicles_status` (`status`),
  ADD KEY `idx_vehicles_department` (`department`),
  ADD KEY `idx_vehicles_number` (`vehicle_number`);

--
-- Indexes for table `vehicle_maintenance`
--
ALTER TABLE `vehicle_maintenance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vehicle_id` (`vehicle_id`);

--
-- Indexes for table `vehicle_statuses`
--
ALTER TABLE `vehicle_statuses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vehicle_types`
--
ALTER TABLE `vehicle_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `volunteer_attendance`
--
ALTER TABLE `volunteer_attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `activity_id` (`activity_id`),
  ADD KEY `volunteer_id` (`volunteer_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `waste_collection_schedules`
--
ALTER TABLE `waste_collection_schedules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `waste_reports`
--
ALTER TABLE `waste_reports`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `website_settings`
--
ALTER TABLE `website_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `youth_environmental_initiatives`
--
ALTER TABLE `youth_environmental_initiatives`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appreciation_certificates`
--
ALTER TABLE `appreciation_certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `associations`
--
ALTER TABLE `associations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `budgets`
--
ALTER TABLE `budgets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `budget_items`
--
ALTER TABLE `budget_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `budget_item_templates`
--
ALTER TABLE `budget_item_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `building_permits`
--
ALTER TABLE `building_permits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `campaign_donations`
--
ALTER TABLE `campaign_donations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `citizens`
--
ALTER TABLE `citizens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `citizens_accounts`
--
ALTER TABLE `citizens_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `citizen_messages`
--
ALTER TABLE `citizen_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `citizen_opinions`
--
ALTER TABLE `citizen_opinions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `citizen_requests`
--
ALTER TABLE `citizen_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `citizen_sessions`
--
ALTER TABLE `citizen_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cleaning_reports`
--
ALTER TABLE `cleaning_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `committee_decisions`
--
ALTER TABLE `committee_decisions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `committee_finance_transactions`
--
ALTER TABLE `committee_finance_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `committee_members`
--
ALTER TABLE `committee_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `committee_sessions`
--
ALTER TABLE `committee_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `complaint_categories`
--
ALTER TABLE `complaint_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `complaint_statuses`
--
ALTER TABLE `complaint_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `complaint_updates`
--
ALTER TABLE `complaint_updates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `contract_types`
--
ALTER TABLE `contract_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `council_members`
--
ALTER TABLE `council_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `currencies`
--
ALTER TABLE `currencies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `currency_conversion_log`
--
ALTER TABLE `currency_conversion_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `development_projects`
--
ALTER TABLE `development_projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `documents_forms`
--
ALTER TABLE `documents_forms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `donations`
--
ALTER TABLE `donations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `donation_campaigns`
--
ALTER TABLE `donation_campaigns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `donation_statuses`
--
ALTER TABLE `donation_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `donation_types`
--
ALTER TABLE `donation_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `donors`
--
ALTER TABLE `donors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `donor_organizations`
--
ALTER TABLE `donor_organizations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `external_data_sources`
--
ALTER TABLE `external_data_sources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `facilities`
--
ALTER TABLE `facilities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `facility_categories`
--
ALTER TABLE `facility_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `facility_ratings`
--
ALTER TABLE `facility_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faqs`
--
ALTER TABLE `faqs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `financial_transactions`
--
ALTER TABLE `financial_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `fiscal_periods`
--
ALTER TABLE `fiscal_periods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `important_links`
--
ALTER TABLE `important_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `important_link_categories`
--
ALTER TABLE `important_link_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `important_link_fetch_logs`
--
ALTER TABLE `important_link_fetch_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `important_link_sources`
--
ALTER TABLE `important_link_sources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `initiative_activities`
--
ALTER TABLE `initiative_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `initiative_evaluations`
--
ALTER TABLE `initiative_evaluations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `initiative_images`
--
ALTER TABLE `initiative_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `initiative_volunteers`
--
ALTER TABLE `initiative_volunteers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `invoice_payments`
--
ALTER TABLE `invoice_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `magic_links`
--
ALTER TABLE `magic_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `map_settings`
--
ALTER TABLE `map_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `municipality_assets`
--
ALTER TABLE `municipality_assets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `municipality_resources`
--
ALTER TABLE `municipality_resources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `municipal_committees`
--
ALTER TABLE `municipal_committees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `municipal_decisions`
--
ALTER TABLE `municipal_decisions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `municipal_sessions`
--
ALTER TABLE `municipal_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `news_activities`
--
ALTER TABLE `news_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `news_images`
--
ALTER TABLE `news_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `news_image_settings`
--
ALTER TABLE `news_image_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=145;

--
-- AUTO_INCREMENT for table `polls`
--
ALTER TABLE `polls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `poll_responses`
--
ALTER TABLE `poll_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `project_contributions`
--
ALTER TABLE `project_contributions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_phases`
--
ALTER TABLE `project_phases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_types`
--
ALTER TABLE `project_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `reference_data`
--
ALTER TABLE `reference_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `request_documents`
--
ALTER TABLE `request_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `request_form_data`
--
ALTER TABLE `request_form_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `request_ratings`
--
ALTER TABLE `request_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `request_types`
--
ALTER TABLE `request_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `request_updates`
--
ALTER TABLE `request_updates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `source_categories`
--
ALTER TABLE `source_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `supplier_invoices`
--
ALTER TABLE `supplier_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=134;

--
-- AUTO_INCREMENT for table `tax_collections`
--
ALTER TABLE `tax_collections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tax_types`
--
ALTER TABLE `tax_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `telegram_log`
--
ALTER TABLE `telegram_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=732;

--
-- AUTO_INCREMENT for table `user_types`
--
ALTER TABLE `user_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vehicle_maintenance`
--
ALTER TABLE `vehicle_maintenance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vehicle_statuses`
--
ALTER TABLE `vehicle_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `vehicle_types`
--
ALTER TABLE `vehicle_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `volunteer_attendance`
--
ALTER TABLE `volunteer_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `waste_collection_schedules`
--
ALTER TABLE `waste_collection_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `waste_reports`
--
ALTER TABLE `waste_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `website_settings`
--
ALTER TABLE `website_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=201;

--
-- AUTO_INCREMENT for table `youth_environmental_initiatives`
--
ALTER TABLE `youth_environmental_initiatives`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `appreciation_certificates`
--
ALTER TABLE `appreciation_certificates`
  ADD CONSTRAINT `appreciation_certificates_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appreciation_certificates_ibfk_2` FOREIGN KEY (`donation_id`) REFERENCES `donations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `appreciation_certificates_ibfk_3` FOREIGN KEY (`issued_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `budgets`
--
ALTER TABLE `budgets`
  ADD CONSTRAINT `budgets_ibfk_1` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  ADD CONSTRAINT `fk_budget_committee` FOREIGN KEY (`committee_id`) REFERENCES `municipal_committees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `budget_items`
--
ALTER TABLE `budget_items`
  ADD CONSTRAINT `budget_items_ibfk_1` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `budget_items_ibfk_2` FOREIGN KEY (`parent_item_id`) REFERENCES `budget_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_budget_item_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `budget_item_templates`
--
ALTER TABLE `budget_item_templates`
  ADD CONSTRAINT `budget_item_templates_ibfk_1` FOREIGN KEY (`committee_id`) REFERENCES `municipal_committees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `budget_item_templates_ibfk_2` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`);

--
-- Constraints for table `building_permits`
--
ALTER TABLE `building_permits`
  ADD CONSTRAINT `building_permits_ibfk_1` FOREIGN KEY (`citizen_id`) REFERENCES `citizens` (`id`),
  ADD CONSTRAINT `building_permits_ibfk_2` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  ADD CONSTRAINT `building_permits_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `building_permits_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `building_permits_ibfk_5` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `campaign_donations`
--
ALTER TABLE `campaign_donations`
  ADD CONSTRAINT `campaign_donations_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `donation_campaigns` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `campaign_donations_ibfk_2` FOREIGN KEY (`donation_id`) REFERENCES `donations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `citizen_messages`
--
ALTER TABLE `citizen_messages`
  ADD CONSTRAINT `citizen_messages_ibfk_1` FOREIGN KEY (`citizen_id`) REFERENCES `citizens_accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `citizen_messages_ibfk_2` FOREIGN KEY (`request_id`) REFERENCES `citizen_requests` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `citizen_messages_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `citizen_requests`
--
ALTER TABLE `citizen_requests`
  ADD CONSTRAINT `fk_citizen_requests_citizen` FOREIGN KEY (`citizen_id`) REFERENCES `citizens` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_citizen_requests_committee` FOREIGN KEY (`assigned_to_committee_id`) REFERENCES `municipal_committees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_citizen_requests_request_type` FOREIGN KEY (`request_type_id`) REFERENCES `request_types` (`id`);

--
-- Constraints for table `citizen_sessions`
--
ALTER TABLE `citizen_sessions`
  ADD CONSTRAINT `citizen_sessions_ibfk_1` FOREIGN KEY (`citizen_id`) REFERENCES `citizens_accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cleaning_reports`
--
ALTER TABLE `cleaning_reports`
  ADD CONSTRAINT `cleaning_reports_ibfk_1` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `cleaning_reports_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `committee_decisions`
--
ALTER TABLE `committee_decisions`
  ADD CONSTRAINT `fk_committee_decisions_committee` FOREIGN KEY (`committee_id`) REFERENCES `municipal_committees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_committee_decisions_session` FOREIGN KEY (`session_id`) REFERENCES `committee_sessions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `committee_finance_summary`
--
ALTER TABLE `committee_finance_summary`
  ADD CONSTRAINT `fk_finance_summary_committee` FOREIGN KEY (`committee_id`) REFERENCES `municipal_committees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `committee_finance_transactions`
--
ALTER TABLE `committee_finance_transactions`
  ADD CONSTRAINT `fk_finance_transactions_committee` FOREIGN KEY (`committee_id`) REFERENCES `municipal_committees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `committee_members`
--
ALTER TABLE `committee_members`
  ADD CONSTRAINT `committee_members_ibfk_1` FOREIGN KEY (`committee_id`) REFERENCES `municipal_committees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `committee_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `committee_sessions`
--
ALTER TABLE `committee_sessions`
  ADD CONSTRAINT `fk_committee_sessions_committee` FOREIGN KEY (`committee_id`) REFERENCES `municipal_committees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_complaints_citizen` FOREIGN KEY (`citizen_id`) REFERENCES `citizens_accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `complaint_categories`
--
ALTER TABLE `complaint_categories`
  ADD CONSTRAINT `complaint_categories_ibfk_1` FOREIGN KEY (`responsible_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `complaint_updates`
--
ALTER TABLE `complaint_updates`
  ADD CONSTRAINT `fk_complaint_updates_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_complaint_updates_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `currency_conversion_log`
--
ALTER TABLE `currency_conversion_log`
  ADD CONSTRAINT `currency_conversion_log_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `development_projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `currency_conversion_log_ibfk_2` FOREIGN KEY (`from_currency_id`) REFERENCES `currencies` (`id`),
  ADD CONSTRAINT `currency_conversion_log_ibfk_3` FOREIGN KEY (`to_currency_id`) REFERENCES `currencies` (`id`);

--
-- Constraints for table `development_projects`
--
ALTER TABLE `development_projects`
  ADD CONSTRAINT `development_projects_ibfk_1` FOREIGN KEY (`responsible_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `development_projects_ibfk_2` FOREIGN KEY (`project_manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_projects_base_cost_currency` FOREIGN KEY (`project_base_cost_currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_projects_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_projects_donor_currency` FOREIGN KEY (`donor_contribution_currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_projects_donors_currency` FOREIGN KEY (`donors_contribution_currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_projects_municipality_currency` FOREIGN KEY (`municipality_contribution_currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `documents_ibfk_3` FOREIGN KEY (`parent_document_id`) REFERENCES `documents` (`id`);

--
-- Constraints for table `documents_forms`
--
ALTER TABLE `documents_forms`
  ADD CONSTRAINT `documents_forms_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `documents_forms_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `donations`
--
ALTER TABLE `donations`
  ADD CONSTRAINT `donations_ibfk_1` FOREIGN KEY (`allocated_to_project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `donations_ibfk_2` FOREIGN KEY (`received_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `donations_ibfk_3` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  ADD CONSTRAINT `donations_ibfk_4` FOREIGN KEY (`estimated_value_currency_id`) REFERENCES `currencies` (`id`);

--
-- Constraints for table `donation_campaigns`
--
ALTER TABLE `donation_campaigns`
  ADD CONSTRAINT `donation_campaigns_ibfk_1` FOREIGN KEY (`target_currency_id`) REFERENCES `currencies` (`id`),
  ADD CONSTRAINT `donation_campaigns_ibfk_2` FOREIGN KEY (`raised_currency_id`) REFERENCES `currencies` (`id`),
  ADD CONSTRAINT `donation_campaigns_ibfk_3` FOREIGN KEY (`responsible_person_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `donor_organizations`
--
ALTER TABLE `donor_organizations`
  ADD CONSTRAINT `donor_organizations_ibfk_1` FOREIGN KEY (`preferred_currency_id`) REFERENCES `currencies` (`id`),
  ADD CONSTRAINT `donor_organizations_ibfk_2` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `donor_organizations_ibfk_3` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `facilities`
--
ALTER TABLE `facilities`
  ADD CONSTRAINT `facilities_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `facility_categories` (`id`);

--
-- Constraints for table `facility_ratings`
--
ALTER TABLE `facility_ratings`
  ADD CONSTRAINT `facility_ratings_ibfk_1` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `financial_transactions`
--
ALTER TABLE `financial_transactions`
  ADD CONSTRAINT `financial_transactions_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `financial_transactions_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `financial_transactions_ibfk_3` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  ADD CONSTRAINT `financial_transactions_ibfk_4` FOREIGN KEY (`related_project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `financial_transactions_ibfk_5` FOREIGN KEY (`related_donation_id`) REFERENCES `donations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_financial_transactions_committee` FOREIGN KEY (`committee_id`) REFERENCES `municipal_committees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `important_links`
--
ALTER TABLE `important_links`
  ADD CONSTRAINT `fk_important_links_category` FOREIGN KEY (`category_id`) REFERENCES `important_link_categories` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `important_link_fetch_logs`
--
ALTER TABLE `important_link_fetch_logs`
  ADD CONSTRAINT `fk_logs_source` FOREIGN KEY (`source_id`) REFERENCES `important_link_sources` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `important_link_sources`
--
ALTER TABLE `important_link_sources`
  ADD CONSTRAINT `fk_sources_category` FOREIGN KEY (`category_id`) REFERENCES `important_link_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sources_source_category` FOREIGN KEY (`source_category_id`) REFERENCES `source_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `initiative_activities`
--
ALTER TABLE `initiative_activities`
  ADD CONSTRAINT `initiative_activities_ibfk_1` FOREIGN KEY (`initiative_id`) REFERENCES `youth_environmental_initiatives` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `initiative_activities_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `initiative_evaluations`
--
ALTER TABLE `initiative_evaluations`
  ADD CONSTRAINT `initiative_evaluations_ibfk_1` FOREIGN KEY (`initiative_id`) REFERENCES `youth_environmental_initiatives` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `initiative_images`
--
ALTER TABLE `initiative_images`
  ADD CONSTRAINT `initiative_images_ibfk_1` FOREIGN KEY (`initiative_id`) REFERENCES `youth_environmental_initiatives` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `initiative_images_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `initiative_volunteers`
--
ALTER TABLE `initiative_volunteers`
  ADD CONSTRAINT `initiative_volunteers_ibfk_1` FOREIGN KEY (`initiative_id`) REFERENCES `youth_environmental_initiatives` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `initiative_volunteers_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `invoice_payments`
--
ALTER TABLE `invoice_payments`
  ADD CONSTRAINT `fk_invoice_payments_committee` FOREIGN KEY (`committee_id`) REFERENCES `municipal_committees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `invoice_payments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `supplier_invoices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `magic_links`
--
ALTER TABLE `magic_links`
  ADD CONSTRAINT `magic_links_ibfk_1` FOREIGN KEY (`citizen_id`) REFERENCES `citizens_accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `municipality_assets`
--
ALTER TABLE `municipality_assets`
  ADD CONSTRAINT `municipality_assets_ibfk_1` FOREIGN KEY (`responsible_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `municipality_resources`
--
ALTER TABLE `municipality_resources`
  ADD CONSTRAINT `municipality_resources_ibfk_1` FOREIGN KEY (`responsible_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `municipal_committees`
--
ALTER TABLE `municipal_committees`
  ADD CONSTRAINT `municipal_committees_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `municipal_committees_ibfk_2` FOREIGN KEY (`chairman_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `municipal_committees_ibfk_3` FOREIGN KEY (`secretary_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `municipal_decisions`
--
ALTER TABLE `municipal_decisions`
  ADD CONSTRAINT `municipal_decisions_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `municipal_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `municipal_decisions_ibfk_2` FOREIGN KEY (`responsible_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `municipal_decisions_ibfk_3` FOREIGN KEY (`responsible_person_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `municipal_sessions`
--
ALTER TABLE `municipal_sessions`
  ADD CONSTRAINT `municipal_sessions_ibfk_1` FOREIGN KEY (`committee_id`) REFERENCES `municipal_committees` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `municipal_sessions_ibfk_2` FOREIGN KEY (`chairperson_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `municipal_sessions_ibfk_3` FOREIGN KEY (`secretary_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `municipal_sessions_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `news_activities`
--
ALTER TABLE `news_activities`
  ADD CONSTRAINT `news_activities_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `news_images`
--
ALTER TABLE `news_images`
  ADD CONSTRAINT `news_images_ibfk_1` FOREIGN KEY (`news_id`) REFERENCES `news_activities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `news_images_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD CONSTRAINT `notification_preferences_ibfk_1` FOREIGN KEY (`citizen_id`) REFERENCES `citizens_accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `poll_responses`
--
ALTER TABLE `poll_responses`
  ADD CONSTRAINT `poll_responses_ibfk_1` FOREIGN KEY (`poll_id`) REFERENCES `polls` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `positions`
--
ALTER TABLE `positions`
  ADD CONSTRAINT `positions_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `fk_contributions_currency` FOREIGN KEY (`contributions_currency_id`) REFERENCES `currencies` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `projects_ibfk_2` FOREIGN KEY (`budget_currency_id`) REFERENCES `currencies` (`id`),
  ADD CONSTRAINT `projects_ibfk_3` FOREIGN KEY (`actual_cost_currency_id`) REFERENCES `currencies` (`id`);

--
-- Constraints for table `project_contributions`
--
ALTER TABLE `project_contributions`
  ADD CONSTRAINT `project_contributions_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_contributions_ibfk_2` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  ADD CONSTRAINT `project_contributions_ibfk_3` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `project_contributions_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `project_contributions_ibfk_5` FOREIGN KEY (`financial_transaction_id`) REFERENCES `financial_transactions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `project_phases`
--
ALTER TABLE `project_phases`
  ADD CONSTRAINT `project_phases_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_phases_ibfk_2` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  ADD CONSTRAINT `project_phases_ibfk_3` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `request_documents`
--
ALTER TABLE `request_documents`
  ADD CONSTRAINT `fk_request_documents_request` FOREIGN KEY (`request_id`) REFERENCES `citizen_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `request_form_data`
--
ALTER TABLE `request_form_data`
  ADD CONSTRAINT `fk_request_form_data_request` FOREIGN KEY (`request_id`) REFERENCES `citizen_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `request_ratings`
--
ALTER TABLE `request_ratings`
  ADD CONSTRAINT `request_ratings_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `citizen_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `request_types`
--
ALTER TABLE `request_types`
  ADD CONSTRAINT `fk_request_types_currency` FOREIGN KEY (`cost_currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `request_updates`
--
ALTER TABLE `request_updates`
  ADD CONSTRAINT `fk_request_updates_request` FOREIGN KEY (`request_id`) REFERENCES `citizen_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_invoices`
--
ALTER TABLE `supplier_invoices`
  ADD CONSTRAINT `fk_supplier_invoices_committee` FOREIGN KEY (`committee_id`) REFERENCES `municipal_committees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `supplier_invoices_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `supplier_invoices_ibfk_2` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`);

--
-- Constraints for table `tax_collections`
--
ALTER TABLE `tax_collections`
  ADD CONSTRAINT `tax_collections_ibfk_1` FOREIGN KEY (`citizen_id`) REFERENCES `citizens` (`id`),
  ADD CONSTRAINT `tax_collections_ibfk_2` FOREIGN KEY (`tax_type_id`) REFERENCES `tax_types` (`id`),
  ADD CONSTRAINT `tax_collections_ibfk_3` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  ADD CONSTRAINT `tax_collections_ibfk_4` FOREIGN KEY (`issued_by_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `tax_collections_ibfk_5` FOREIGN KEY (`collected_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `tax_types`
--
ALTER TABLE `tax_types`
  ADD CONSTRAINT `tax_types_ibfk_1` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  ADD CONSTRAINT `tax_types_ibfk_2` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_currency` FOREIGN KEY (`salary_currency_id`) REFERENCES `currencies` (`id`),
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_3` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permissions_ibfk_3` FOREIGN KEY (`granted_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `vehicle_maintenance`
--
ALTER TABLE `vehicle_maintenance`
  ADD CONSTRAINT `vehicle_maintenance_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `volunteer_attendance`
--
ALTER TABLE `volunteer_attendance`
  ADD CONSTRAINT `volunteer_attendance_ibfk_1` FOREIGN KEY (`activity_id`) REFERENCES `initiative_activities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `volunteer_attendance_ibfk_2` FOREIGN KEY (`volunteer_id`) REFERENCES `initiative_volunteers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `volunteer_attendance_ibfk_3` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
