-- ============================================
-- نظام Audit Log - سجل التتبع والمراجعة
-- تاريخ الإنشاء: 19 نوفمبر 2025
-- ============================================

-- جدول سجل التغييرات (Audit Log)
CREATE TABLE IF NOT EXISTS audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    table_name VARCHAR(100) NOT NULL COMMENT 'اسم الجدول',
    record_id INT NOT NULL COMMENT 'ID السجل المعدل',
    action ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL COMMENT 'نوع العملية',
    old_values JSON COMMENT 'القيم القديمة (قبل التعديل)',
    new_values JSON COMMENT 'القيم الجديدة (بعد التعديل)',
    user_id INT COMMENT 'المستخدم الذي أجرى العملية',
    user_name VARCHAR(255) COMMENT 'اسم المستخدم',
    ip_address VARCHAR(45) COMMENT 'عنوان IP',
    user_agent TEXT COMMENT 'متصفح المستخدم',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_table_record (table_name, record_id),
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT='سجل التغييرات والمراجعة';

-- جدول الجداول المفقودة للنظام الكامل
CREATE TABLE IF NOT EXISTS inventory_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_code VARCHAR(50) UNIQUE NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    category VARCHAR(100),
    unit VARCHAR(50),
    minimum_stock INT DEFAULT 0,
    current_stock INT DEFAULT 0,
    unit_price DECIMAL(15, 2) DEFAULT 0,
    currency_id INT,
    location VARCHAR(255),
    notes TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_movements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    movement_type ENUM('إضافة', 'سحب') NOT NULL,
    quantity INT NOT NULL,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ============================================
-- Triggers للتتبع التلقائي
-- ============================================

-- مثال: Trigger لتتبع التغييرات في جدول المعاملات المالية
DELIMITER $$

CREATE TRIGGER financial_transactions_audit_insert
AFTER INSERT ON financial_transactions
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (table_name, record_id, action, new_values, user_id, user_name, ip_address)
    VALUES (
        'financial_transactions',
        NEW.id,
        'INSERT',
        JSON_OBJECT(
            'type', NEW.type,
            'amount', NEW.amount,
            'currency_id', NEW.currency_id,
            'transaction_date', NEW.transaction_date,
            'description', NEW.description,
            'status', NEW.status
        ),
        NEW.created_by,
        (SELECT full_name FROM users WHERE id = NEW.created_by LIMIT 1),
        COALESCE(@user_ip, '0.0.0.0')
    );
END$$

CREATE TRIGGER financial_transactions_audit_update
AFTER UPDATE ON financial_transactions
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (table_name, record_id, action, old_values, new_values, user_id, user_name, ip_address)
    VALUES (
        'financial_transactions',
        NEW.id,
        'UPDATE',
        JSON_OBJECT(
            'type', OLD.type,
            'amount', OLD.amount,
            'currency_id', OLD.currency_id,
            'transaction_date', OLD.transaction_date,
            'description', OLD.description,
            'status', OLD.status
        ),
        JSON_OBJECT(
            'type', NEW.type,
            'amount', NEW.amount,
            'currency_id', NEW.currency_id,
            'transaction_date', NEW.transaction_date,
            'description', NEW.description,
            'status', NEW.status
        ),
        NEW.created_by,
        (SELECT full_name FROM users WHERE id = NEW.created_by LIMIT 1),
        COALESCE(@user_ip, '0.0.0.0')
    );
END$$

CREATE TRIGGER financial_transactions_audit_delete
AFTER DELETE ON financial_transactions
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (table_name, record_id, action, old_values, user_id, user_name, ip_address)
    VALUES (
        'financial_transactions',
        OLD.id,
        'DELETE',
        JSON_OBJECT(
            'type', OLD.type,
            'amount', OLD.amount,
            'currency_id', OLD.currency_id,
            'transaction_date', OLD.transaction_date,
            'description', OLD.description,
            'status', OLD.status
        ),
        OLD.created_by,
        (SELECT full_name FROM users WHERE id = OLD.created_by LIMIT 1),
        COALESCE(@user_ip, '0.0.0.0')
    );
END$$

DELIMITER ;

-- ============================================
-- استعلامات مفيدة لنظام Audit Log
-- ============================================

-- جلب آخر 100 تغيير
-- SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 100;

-- جلب التغييرات لجدول معين
-- SELECT * FROM audit_log WHERE table_name = 'financial_transactions' ORDER BY created_at DESC;

-- جلب التغييرات لسجل معين
-- SELECT * FROM audit_log WHERE table_name = 'financial_transactions' AND record_id = 123 ORDER BY created_at DESC;

-- جلب التغييرات لمستخدم معين
-- SELECT * FROM audit_log WHERE user_id = 1 ORDER BY created_at DESC;

-- إحصائيات التغييرات
-- SELECT action, COUNT(*) as count FROM audit_log GROUP BY action;
-- SELECT table_name, COUNT(*) as count FROM audit_log GROUP BY table_name ORDER BY count DESC;
