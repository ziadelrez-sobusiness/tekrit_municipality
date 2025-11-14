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
INSERT INTO currencies (currency_code, currency_name, currency_symbol, exchange_rate, is_default, is_active) VALUES
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

-- تحديث جدول الموظفين لإضافة العملة ونوع العقد
ALTER TABLE employees 
ADD COLUMN salary_currency_id INT DEFAULT 1 AFTER salary,
ADD COLUMN contract_type ENUM('monthly', 'daily') DEFAULT 'monthly' AFTER salary_currency_id,
ADD FOREIGN KEY (salary_currency_id) REFERENCES currencies(id);

-- تحديث جدول المركبات لإضافة العملة (للصيانة والتكاليف)
ALTER TABLE vehicles 
ADD COLUMN maintenance_cost_currency_id INT DEFAULT 1 AFTER maintenance_cost,
ADD FOREIGN KEY (maintenance_cost_currency_id) REFERENCES currencies(id);

-- إنشاء جدول مصاريف المركبات مع العملة
CREATE TABLE IF NOT EXISTS vehicle_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    expense_type ENUM('fuel', 'maintenance', 'insurance', 'registration', 'other') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    currency_id INT NOT NULL DEFAULT 1,
    description TEXT,
    expense_date DATE NOT NULL,
    receipt_number VARCHAR(100),
    added_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (added_by_user_id) REFERENCES users(id)
);

-- إنشاء جدول الميزانيات مع العملة
CREATE TABLE IF NOT EXISTS budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department VARCHAR(100) NOT NULL,
    budget_year YEAR NOT NULL,
    allocated_amount DECIMAL(15,2) NOT NULL,
    currency_id INT NOT NULL DEFAULT 1,
    spent_amount DECIMAL(15,2) DEFAULT 0.00,
    remaining_amount DECIMAL(15,2) GENERATED ALWAYS AS (allocated_amount - spent_amount) STORED,
    budget_status ENUM('active', 'completed', 'exceeded', 'cancelled') DEFAULT 'active',
    notes TEXT,
    created_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (created_by_user_id) REFERENCES users(id),
    UNIQUE KEY unique_dept_year (department, budget_year)
);

-- إنشاء جدول المصروفات العامة مع العملة
CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_category ENUM('office_supplies', 'utilities', 'maintenance', 'transportation', 'communication', 'other') NOT NULL,
    description TEXT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    currency_id INT NOT NULL DEFAULT 1,
    expense_date DATE NOT NULL,
    department VARCHAR(100),
    approved_by_user_id INT,
    receipt_number VARCHAR(100),
    budget_id INT,
    created_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (approved_by_user_id) REFERENCES users(id),
    FOREIGN KEY (created_by_user_id) REFERENCES users(id),
    FOREIGN KEY (budget_id) REFERENCES budgets(id)
);

-- إنشاء جدول الرواتب مع العملة والبونصات
CREATE TABLE IF NOT EXISTS payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    pay_period_start DATE NOT NULL,
    pay_period_end DATE NOT NULL,
    basic_salary DECIMAL(15,2) NOT NULL,
    salary_currency_id INT NOT NULL DEFAULT 1,
    overtime_hours DECIMAL(5,2) DEFAULT 0.00,
    overtime_rate DECIMAL(15,2) DEFAULT 0.00,
    overtime_amount DECIMAL(15,2) GENERATED ALWAYS AS (overtime_hours * overtime_rate) STORED,
    bonuses DECIMAL(15,2) DEFAULT 0.00,
    deductions DECIMAL(15,2) DEFAULT 0.00,
    net_salary DECIMAL(15,2) GENERATED ALWAYS AS (basic_salary + overtime_amount + bonuses - deductions) STORED,
    payment_status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    payment_date DATE,
    notes TEXT,
    processed_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (salary_currency_id) REFERENCES currencies(id),
    FOREIGN KEY (processed_by_user_id) REFERENCES users(id)
);

-- إنشاء جدول طلبات الخدمات مع الرسوم والعملة
CREATE TABLE IF NOT EXISTS service_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    citizen_name VARCHAR(200) NOT NULL,
    citizen_phone VARCHAR(20),
    citizen_email VARCHAR(200),
    citizen_address TEXT,
    service_type ENUM('building_permit', 'business_license', 'waste_collection', 'road_maintenance', 'water_connection', 'other') NOT NULL,
    description TEXT NOT NULL,
    service_fee DECIMAL(15,2) DEFAULT 0.00,
    fee_currency_id INT NOT NULL DEFAULT 1,
    payment_status ENUM('unpaid', 'paid', 'waived') DEFAULT 'unpaid',
    request_status ENUM('pending', 'in_progress', 'completed', 'rejected') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    assigned_to_user_id INT,
    completed_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (fee_currency_id) REFERENCES currencies(id),
    FOREIGN KEY (assigned_to_user_id) REFERENCES users(id)
);

-- إدراج بعض البيانات التجريبية للميزانيات
INSERT INTO budgets (department, budget_year, allocated_amount, currency_id, created_by_user_id) VALUES
('الموارد البشرية', 2024, 50000000.00, 1, 1),
('الآليات والمعدات', 2024, 30000000.00, 1, 1),
('الشؤون المالية', 2024, 20000000.00, 1, 1),
('خدمة المواطنين', 2024, 25000000.00, 1, 1),
('إدارة النظافة', 2024, 40000000.00, 1, 1);

-- إنشاء view للعملات النشطة
CREATE OR REPLACE VIEW active_currencies AS
SELECT id, currency_code, currency_name, currency_symbol, exchange_rate, is_default
FROM currencies 
WHERE is_active = TRUE 
ORDER BY is_default DESC, currency_name ASC;

-- إنشاء function للحصول على العملة الافتراضية
DELIMITER //
CREATE FUNCTION GetDefaultCurrencyId() 
RETURNS INT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE default_id INT;
    SELECT id INTO default_id FROM currencies WHERE is_default = TRUE LIMIT 1;
    RETURN COALESCE(default_id, 1);
END //
DELIMITER ;

-- تحديث البيانات الموجودة لتستخدم العملة الافتراضية
UPDATE employees SET salary_currency_id = GetDefaultCurrencyId() WHERE salary_currency_id IS NULL;
UPDATE vehicles SET maintenance_cost_currency_id = GetDefaultCurrencyId() WHERE maintenance_cost_currency_id IS NULL; 