-- جدول تسجيل محاولات تسجيل الدخول
-- يساعد في منع هجمات Brute Force

CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT,
    `success` TINYINT(1) NOT NULL DEFAULT 0,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `user_id` INT DEFAULT NULL,
    INDEX `idx_username` (`username`),
    INDEX `idx_ip_address` (`ip_address`),
    INDEX `idx_attempted_at` (`attempted_at`),
    INDEX `idx_success` (`success`),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- فهرس مركب للبحث السريع
CREATE INDEX `idx_username_ip_time` ON `login_attempts` (`username`, `ip_address`, `attempted_at`);

-- حذف المحاولات القديمة (أكثر من 30 يوم) تلقائياً
-- يمكن استخدام Event Scheduler أو Cron Job لهذا الغرض

