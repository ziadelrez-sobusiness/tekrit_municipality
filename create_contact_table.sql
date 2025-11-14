USE tekrit_municipality;

CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_name VARCHAR(100) NOT NULL,
    sender_email VARCHAR(100) NOT NULL,
    sender_phone VARCHAR(20),
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('جديد', 'قيد المراجعة', 'تم الرد', 'مغلق') DEFAULT 'جديد'
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 