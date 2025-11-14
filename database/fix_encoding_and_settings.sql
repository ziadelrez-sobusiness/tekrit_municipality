USE tekrit_municipality;

-- إنشاء جدول إعدادات النظام
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- إدراج الإعدادات الافتراضية
INSERT INTO system_settings (setting_key, setting_value, setting_description) VALUES
('default_currency_id', '7', 'معرف العملة الافتراضية للنظام'),
('system_name', 'نظام إدارة بلدية تكريت', 'اسم النظام'),
('admin_email', 'admin@tekrit.gov.iq', 'بريد المدير الإلكتروني'),
('currency_display_decimals', '2', 'عدد الخانات العشرية لعرض العملات')
ON DUPLICATE KEY UPDATE 
setting_value = VALUES(setting_value),
setting_description = VALUES(setting_description);

-- تحديث أسماء الموظفين لتكون واضحة
UPDATE users SET full_name = 'مدير النظام' WHERE username = 'admin';
UPDATE users SET full_name = 'موظف تجريبي' WHERE username = 'test';
UPDATE users SET full_name = 'زياد الرز', department = 'الإدارة المالية' WHERE username = 'alaa';
UPDATE users SET full_name = 'كريم الحسن', department = 'الهندسة' WHERE username = 'karim123';
UPDATE users SET full_name = 'وسيم العلي', department = 'الموارد البشرية' WHERE username = 'wassim';
UPDATE users SET full_name = 'أحمد حسن محمد', department = 'الإدارة المالية' WHERE username = 'ahmad.hassan';
UPDATE users SET full_name = 'فاطمة علي أحمد', department = 'الموارد البشرية' WHERE username = 'fatima.ali';
UPDATE users SET full_name = 'عمر خليل إبراهيم', department = 'الهندسة' WHERE username = 'omar.khalil';

-- التأكد من أن جميع الموظفين لديهم contract_type
UPDATE users SET contract_type = 'monthly' WHERE contract_type IS NULL;

-- التأكد من أن جميع الموظفين لديهم salary_currency_id
UPDATE users SET salary_currency_id = 7 WHERE salary_currency_id IS NULL;

SELECT 'تم إصلاح ترميز الأسماء وإنشاء جدول الإعدادات' as message; 