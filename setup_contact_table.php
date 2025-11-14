<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        $sql = "CREATE TABLE IF NOT EXISTS contact_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_name VARCHAR(100) NOT NULL,
            sender_email VARCHAR(100) NOT NULL,
            sender_phone VARCHAR(20),
            subject VARCHAR(200) NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('جديد', 'قيد المراجعة', 'تم الرد', 'مغلق') DEFAULT 'جديد'
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($sql);
        echo "تم إنشاء جدول contact_messages بنجاح!";
    } else {
        echo "فشل الاتصال بقاعدة البيانات";
    }
} catch(PDOException $e) {
    echo "خطأ: " . $e->getMessage();
}
?> 