<?php
/**
 * إنشاء جدول login_attempts
 * تشغيل هذا الملف مرة واحدة لإنشاء الجدول
 */

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();
    
    // إنشاء الجدول
    $sql = "
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($sql);
    
    // إنشاء الفهرس المركب
    try {
        $db->exec("CREATE INDEX `idx_username_ip_time` ON `login_attempts` (`username`, `ip_address`, `attempted_at`)");
    } catch (PDOException $e) {
        // الفهرس موجود بالفعل - تجاهل الخطأ
    }
    
    $db->commit();
    
    echo "✅ تم إنشاء جدول login_attempts بنجاح!\n";
    echo "الجدول جاهز لتسجيل محاولات تسجيل الدخول.\n";
    
} catch (PDOException $e) {
    $db->rollBack();
    echo "❌ خطأ في إنشاء الجدول: " . $e->getMessage() . "\n";
    exit(1);
}

?>

