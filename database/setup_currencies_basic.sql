-- إنشاء جدول العملات
CREATE TABLE IF NOT EXISTS currencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    currency_code VARCHAR(10) NOT NULL UNIQUE,
    currency_name VARCHAR(100) NOT NULL,
    currency_symbol VARCHAR(10) NOT NULL,
    exchange_rate DECIMAL(15,6) DEFAULT 1.000000,
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- إدراج العملات الأساسية
INSERT IGNORE INTO currencies (currency_code, currency_name, currency_symbol, exchange_rate, is_default, is_active) VALUES
('IQD', 'الدينار العراقي', 'د.ع', 1.000000, TRUE, TRUE),
('USD', 'الدولار الأمريكي', '$', 0.000680, FALSE, TRUE),
('EUR', 'اليورو', '€', 0.000640, FALSE, TRUE),
('GBP', 'الجنيه الاسترليني', '£', 0.000550, FALSE, TRUE),
('SAR', 'الريال السعودي', 'ر.س', 0.002550, FALSE, TRUE),
('AED', 'الدرهم الإماراتي', 'د.إ', 0.002500, FALSE, TRUE),
('KWD', 'الدينار الكويتي', 'د.ك', 0.000210, FALSE, TRUE),
('QAR', 'الريال القطري', 'ر.ق', 0.002480, FALSE, TRUE),
('BHD', 'الدينار البحريني', 'د.ب', 0.000256, FALSE, TRUE),
('OMR', 'الريال العُماني', 'ر.ع', 0.000262, FALSE, TRUE),
('JOD', 'الدينار الأردني', 'د.أ', 0.000482, FALSE, TRUE),
('LBP', 'الليرة اللبنانية', 'ل.ل', 10.250000, FALSE, TRUE),
('EGP', 'الجنيه المصري', 'ج.م', 0.021000, FALSE, TRUE),
('TRY', 'الليرة التركية', '₺', 0.018500, FALSE, TRUE);

-- التحقق من وجود الأعمدة قبل إضافتها
SET @check_column = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'tekrit_municipality' 
    AND TABLE_NAME = 'users' 
    AND COLUMN_NAME = 'salary_currency_id');

SET @sql = IF(@check_column = 0, 
    'ALTER TABLE users ADD COLUMN salary_currency_id INT DEFAULT 1 AFTER salary',
    'SELECT "Column salary_currency_id already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @check_column2 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'tekrit_municipality' 
    AND TABLE_NAME = 'users' 
    AND COLUMN_NAME = 'contract_type');

SET @sql2 = IF(@check_column2 = 0, 
    'ALTER TABLE users ADD COLUMN contract_type ENUM(''monthly'', ''daily'') DEFAULT ''monthly'' AFTER salary_currency_id',
    'SELECT "Column contract_type already exists" as message');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- إضافة foreign key إذا لم تكن موجودة
SET @check_fk = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = 'tekrit_municipality' 
    AND TABLE_NAME = 'users' 
    AND CONSTRAINT_NAME LIKE '%salary_currency_id%');

SET @sql3 = IF(@check_fk = 0, 
    'ALTER TABLE users ADD FOREIGN KEY (salary_currency_id) REFERENCES currencies(id)',
    'SELECT "Foreign key for salary_currency_id already exists" as message');
PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

-- تحديث البيانات الموجودة
UPDATE users SET salary_currency_id = 1 WHERE salary_currency_id IS NULL;
UPDATE users SET contract_type = 'monthly' WHERE contract_type IS NULL; 