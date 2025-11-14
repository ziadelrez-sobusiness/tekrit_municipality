-- جدول تحديثات وتعليقات طلبات المواطنين
CREATE TABLE IF NOT EXISTS request_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    update_text TEXT NOT NULL,
    update_type ENUM('تحديث حالة', 'تعليق', 'رد البلدية', 'ملاحظة إدارية', 'تحديث بيانات') DEFAULT 'تعليق',
    updated_by INT,
    is_visible_to_citizen BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES citizen_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_request_id (request_id),
    INDEX idx_created_at (created_at),
    INDEX idx_visible_to_citizen (is_visible_to_citizen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إضافة عمود تاريخ الإنجاز المتوقع إلى جدول طلبات المواطنين إذا لم يكن موجوداً
ALTER TABLE citizen_requests 
ADD COLUMN IF NOT EXISTS estimated_completion_date DATE NULL AFTER completion_date;

-- إضافة فهارس لتحسين الأداء
ALTER TABLE citizen_requests 
ADD INDEX IF NOT EXISTS idx_request_status (request_status),
ADD INDEX IF NOT EXISTS idx_priority_level (priority_level),
ADD INDEX IF NOT EXISTS idx_created_at (created_at),
ADD INDEX IF NOT EXISTS idx_tracking_number (tracking_number); 