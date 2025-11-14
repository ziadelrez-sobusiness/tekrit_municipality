USE tekrit_municipality;

-- إضافة أعمدة العملة ونوع العقد لجدول المستخدمين
ALTER TABLE users 
ADD COLUMN salary_currency_id INT(11) DEFAULT 7 AFTER salary,
ADD COLUMN contract_type ENUM('monthly', 'daily') DEFAULT 'monthly' AFTER salary_currency_id;

-- إضافة مفتاح خارجي للعملة
ALTER TABLE users 
ADD CONSTRAINT fk_users_currency 
FOREIGN KEY (salary_currency_id) REFERENCES currencies(id);

-- تحديث البيانات الموجودة لتستخدم الدينار العراقي كافتراضي
UPDATE users SET salary_currency_id = 7 WHERE salary_currency_id IS NULL;

-- إضافة موظف تجريبي للاختبار
INSERT INTO users (username, password, full_name, email, phone, department, position, user_type, hire_date, salary, salary_currency_id, contract_type, is_active) 
VALUES 
('ahmad.hassan', '$2y$10$example.hash.here', 'أحمد حسن محمد', 'ahmad@tekrit.gov.iq', '07801234567', 'الإدارة المالية', 'محاسب', 'employee', '2024-01-15', 800000.00, 7, 'monthly', 1),
('fatima.ali', '$2y$10$example.hash.here', 'فاطمة علي أحمد', 'fatima@tekrit.gov.iq', '07701234568', 'الموارد البشرية', 'موظفة إدارية', 'employee', '2024-02-01', 750000.00, 7, 'monthly', 1),
('omar.khalil', '$2y$10$example.hash.here', 'عمر خليل إبراهيم', 'omar@tekrit.gov.iq', '07901234569', 'الهندسة', 'مهندس', 'employee', '2024-01-10', 1200000.00, 7, 'monthly', 1)
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

SELECT 'تم تحديث جدول المستخدمين وإضافة موظفين تجريبيين' as message; 