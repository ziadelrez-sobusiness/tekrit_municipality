-- ======================================================================
-- إنشاء جدول اللجان (committees)
-- ======================================================================

USE `tekrit_municipality`;

-- إنشاء جدول اللجان
CREATE TABLE IF NOT EXISTS `committees` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `committee_code` VARCHAR(50) NULL DEFAULT NULL,
    `committee_name` VARCHAR(255) NOT NULL,
    `committee_description` TEXT NULL DEFAULT NULL,
    `committee_type` VARCHAR(100) NULL DEFAULT NULL COMMENT 'نوع اللجنة: دائمة، مؤقتة، استشارية',
    `formation_date` DATE NULL DEFAULT NULL,
    `chairman_name` VARCHAR(255) NULL DEFAULT NULL COMMENT 'رئيس اللجنة',
    `members_count` INT NULL DEFAULT 0,
    `is_active` TINYINT(1) NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `committee_code` (`committee_code`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='جدول اللجان البلدية';

-- إضافة بيانات تجريبية للجان
INSERT INTO `committees` 
(`committee_code`, `committee_name`, `committee_description`, `committee_type`, `chairman_name`, `members_count`, `is_active`)
VALUES
('COM-001', 'لجنة النظافة والبيئة', 'مسؤولة عن نظافة المدينة والمحافظة على البيئة', 'دائمة', '', 5, 1),
('COM-002', 'لجنة المشاريع الإنمائية', 'تشرف على المشاريع التنموية والبنى التحتية', 'دائمة', '', 7, 1),
('COM-003', 'لجنة الخدمات الاجتماعية', 'تهتم بالشؤون الاجتماعية ومساعدة المحتاجين', 'دائمة', '', 4, 1),
('COM-004', 'لجنة الصحة والسلامة العامة', 'مسؤولة عن الصحة العامة والسلامة في المدينة', 'دائمة', '', 5, 1),
('COM-005', 'لجنة التراخيص والرقابة', 'تصدر التراخيص وتراقب المنشآت التجارية', 'دائمة', '', 6, 1),
('COM-006', 'لجنة الثقافة والتراث', 'تعنى بالشؤون الثقافية والحفاظ على التراث', 'دائمة', '', 4, 1),
('COM-007', 'لجنة التخطيط العمراني', 'تخطيط وتنظيم المدينة والمناطق السكنية', 'دائمة', '', 5, 1),
('COM-008', 'لجنة الطوارئ والكوارث', 'إدارة الطوارئ والاستجابة للكوارث', 'دائمة', '', 6, 1),
('COM-009', 'لجنة المياه والصرف الصحي', 'إدارة شبكات المياه والصرف الصحي', 'دائمة', '', 5, 1),
('COM-010', 'لجنة الطرق والجسور', 'صيانة وتطوير الطرق والجسور', 'دائمة', '', 5, 1)
ON DUPLICATE KEY UPDATE committee_name = VALUES(committee_name);

-- ======================================================================
-- نهاية السكريبت
-- ======================================================================

SELECT 'تم إنشاء جدول اللجان بنجاح!' as status;
SELECT COUNT(*) as total_committees FROM committees;


