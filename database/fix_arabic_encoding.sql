-- سكريبت إصلاح الترميز والتحسينات النهائية
-- تاريخ الإنشاء: ديسمبر 2024

-- ضبط ترميز قاعدة البيانات
ALTER DATABASE tekrit_municipality CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- التأكد من ترميز جميع الجداول
ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE projects CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE financial_transactions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE vehicles CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE waste_collection_schedules CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE waste_reports CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE complaints CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE documents CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE donations CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE currencies CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE municipal_forms CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE vehicle_maintenance CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- تحديث جدول المستخدمين للتأكد من وجود حقل last_login
ALTER TABLE users 
MODIFY COLUMN last_login TIMESTAMP NULL DEFAULT NULL,
ADD INDEX idx_last_login (last_login),
ADD INDEX idx_department (department),
ADD INDEX idx_is_active (is_active);

-- إضافة فهارس للأداء
ALTER TABLE financial_transactions 
ADD INDEX idx_transaction_date (transaction_date),
ADD INDEX idx_type (type),
ADD INDEX idx_currency_id (currency_id);

ALTER TABLE projects 
ADD INDEX idx_status (status),
ADD INDEX idx_project_type (project_type),
ADD INDEX idx_manager_id (manager_id);

ALTER TABLE donations 
ADD INDEX idx_status (status),
ADD INDEX idx_donation_type (donation_type),
ADD INDEX idx_received_date (received_date);

-- تحديث العملات لضمان دقة البيانات
UPDATE currencies SET 
    currency_name = 'الليرة اللبنانية',
    currency_symbol = 'ل.ل',
    exchange_rate_to_iqd = 1.0
WHERE currency_code = 'LBP';

UPDATE currencies SET 
    currency_name = 'الدولار الأمريكي',
    currency_symbol = '$',
    exchange_rate_to_iqd = 89500.0
WHERE currency_code = 'USD';

UPDATE currencies SET 
    currency_name = 'اليورو',
    currency_symbol = '€',
    exchange_rate_to_iqd = 97800.0
WHERE currency_code = 'EUR';

-- إصلاح العملات في المعاملات المالية
UPDATE financial_transactions ft
SET amount_in_lbp = ft.amount * (
    SELECT c.exchange_rate_to_iqd 
    FROM currencies c 
    WHERE c.id = ft.currency_id
)
WHERE ft.amount_in_lbp IS NULL OR ft.amount_in_lbp = 0;

-- تحديث calculated amounts في جدول التبرعات إذا كان موجوداً
UPDATE donations d
SET 
    amount_in_lbp = d.amount * (
        SELECT c.exchange_rate_to_iqd 
        FROM currencies c 
        WHERE c.id = d.currency_id
    ),
    estimated_value_in_lbp = d.estimated_value * (
        SELECT c.exchange_rate_to_iqd 
        FROM currencies c 
        WHERE c.id = d.estimated_value_currency_id
    )
WHERE (d.amount_in_lbp IS NULL OR d.amount_in_lbp = 0) AND d.amount > 0;

-- تحسين أداء الاستعلامات
OPTIMIZE TABLE users;
OPTIMIZE TABLE financial_transactions;
OPTIMIZE TABLE projects;
OPTIMIZE TABLE donations;
OPTIMIZE TABLE vehicles;
OPTIMIZE TABLE complaints;

-- إضافة بيانات تجريبية للاختبار إذا لم تكن موجودة
INSERT IGNORE INTO currencies (currency_code, currency_name, currency_symbol, exchange_rate_to_iqd, is_active) VALUES
('LBP', 'الليرة اللبنانية', 'ل.ل', 1.0, 1),
('USD', 'الدولار الأمريكي', '$', 89500.0, 1),
('EUR', 'اليورو', '€', 97800.0, 1),
('SAR', 'الريال السعودي', 'ر.س', 23900.0, 1),
('TRY', 'الليرة التركية', '₺', 4820.0, 1);

-- التأكد من وجود المستخدم الافتراضي
INSERT IGNORE INTO users (
    username, password, full_name, email, department, position, 
    user_type, hire_date, salary, is_active, created_at
) VALUES (
    'admin', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'مدير النظام',
    'admin@tekrit.municipality',
    'تقنية المعلومات',
    'مدير النظام',
    'admin',
    CURDATE(),
    0,
    1,
    NOW()
);

-- إنشاء إجراء مخزن لحساب الإحصائيات الشهرية
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS GetMonthlyFinancialStats(IN target_year INT, IN target_month INT)
BEGIN
    SELECT 
        type as transaction_type,
        COUNT(*) as transaction_count,
        SUM(amount_in_lbp) as total_amount_lbp,
        AVG(amount_in_lbp) as avg_amount_lbp
    FROM financial_transactions 
    WHERE YEAR(transaction_date) = target_year 
    AND MONTH(transaction_date) = target_month
    GROUP BY type;
END //

DELIMITER ;

-- تحديث timestamps للسجلات التي لا تحتوي على تواريخ
UPDATE users SET created_at = NOW() WHERE created_at IS NULL;
UPDATE projects SET created_at = NOW() WHERE created_at IS NULL;
UPDATE financial_transactions SET created_at = NOW() WHERE created_at IS NULL;

-- إضافة قيود لضمان جودة البيانات
ALTER TABLE financial_transactions 
ADD CONSTRAINT chk_amount_positive CHECK (amount > 0),
ADD CONSTRAINT chk_valid_type CHECK (type IN ('إيراد', 'مصروف'));

-- التأكد من أن جميع المشاريع لها مدراء صالحين
UPDATE projects p 
SET manager_id = (SELECT id FROM users WHERE user_type IN ('admin', 'manager') AND is_active = 1 LIMIT 1)
WHERE manager_id IS NULL OR manager_id NOT IN (
    SELECT id FROM users WHERE is_active = 1
);

-- تحديث آخر دخول لجميع المستخدمين النشطين إذا كان فارغاً
UPDATE users 
SET last_login = NOW() 
WHERE is_active = 1 AND last_login IS NULL;

-- إضافة سجل العملية النهائية
INSERT INTO system_logs (log_type, description, created_at) VALUES 
('SYSTEM_UPDATE', 'تم تطبيق إصلاحات الترميز والتحسينات النهائية', NOW())
ON DUPLICATE KEY UPDATE 
description = 'تم تطبيق إصلاحات الترميز والتحسينات النهائية', 
created_at = NOW();

-- رسالة إتمام
SELECT 'تم تطبيق جميع الإصلاحات والتحسينات بنجاح' as status; 