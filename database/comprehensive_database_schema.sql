-- مخطط قاعدة البيانات الشامل لنظام إدارة بلدية تكريت
-- تم تصميمه وفقاً للمواصفات المطلوبة مع الترابط الكامل بين الجداول

CREATE DATABASE IF NOT EXISTS tekrit_municipality 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tekrit_municipality;

-- =====================================
-- الجداول الأساسية والمرجعية
-- =====================================

-- جدول الأدوار
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول الصلاحيات
CREATE TABLE permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول صلاحيات الأدوار
CREATE TABLE role_permissions (
    role_id INT,
    permission_id INT,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- جدول العملات
CREATE TABLE currencies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    code VARCHAR(5) UNIQUE NOT NULL,
    symbol VARCHAR(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    exchange_rate_to_lbp DECIMAL(10, 4),
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول البيانات المرجعية
CREATE TABLE reference_data (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(50) NOT NULL,
    value VARCHAR(255) NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (type)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =====================================
-- الجداول الإدارية والتنظيمية
-- =====================================

-- جدول الأقسام
CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) UNIQUE NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    manager_employee_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول المستخدمين
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(20),
    role_id INT NOT NULL,
    department_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول الموظفين
CREATE TABLE employees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    department_id INT NOT NULL,
    job_title VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    contract_type ENUM('monthly', 'daily') NOT NULL,
    hire_date DATE,
    salary DECIMAL(10, 2),
    salary_currency_id INT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT,
    FOREIGN KEY (salary_currency_id) REFERENCES currencies(id) ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- إضافة المفتاح الخارجي للمدير في الأقسام
ALTER TABLE departments 
ADD FOREIGN KEY (manager_employee_id) REFERENCES employees(id) ON DELETE SET NULL;

-- جدول المواطنين
CREATE TABLE citizens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(255) NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    national_id VARCHAR(20) UNIQUE,
    address VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    phone VARCHAR(20),
    email VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =====================================
-- الجداول المالية - المحور المركزي
-- =====================================

-- جدول القيود المالية - المحور المالي الرئيسي
CREATE TABLE financial_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type ENUM('revenue', 'expense') NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    currency_id INT NOT NULL,
    transaction_date DATETIME NOT NULL,
    description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    category_id INT NOT NULL,
    department_id INT,
    project_id INT,
    employee_id INT,
    citizen_id INT,
    recorded_by_user_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE RESTRICT,
    FOREIGN KEY (category_id) REFERENCES reference_data(id) ON DELETE RESTRICT,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_type_date (type, transaction_date),
    INDEX idx_category (category_id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول الميزانيات
CREATE TABLE budgets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fiscal_year YEAR NOT NULL,
    department_id INT,
    allocated_amount DECIMAL(15, 2) NOT NULL,
    currency_id INT NOT NULL,
    description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    status ENUM('draft', 'approved', 'closed') DEFAULT 'draft',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT,
    FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =====================================
-- إدارة الأقسام المختلفة
-- =====================================

-- إدارة المشاريع
CREATE TABLE projects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) UNIQUE NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    start_date DATE,
    end_date DATE,
    status ENUM('planning', 'in_progress', 'completed', 'on_hold', 'canceled') DEFAULT 'planning',
    budget_allocated DECIMAL(15, 2),
    currency_id INT NOT NULL,
    department_id INT NOT NULL,
    manager_employee_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE RESTRICT,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT,
    FOREIGN KEY (manager_employee_id) REFERENCES employees(id) ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- إدارة الشكاوى
CREATE TABLE complaints (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject VARCHAR(255) NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    details TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    citizen_id INT,
    location_details VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    date_submitted DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('new', 'in_progress', 'completed', 'rejected') DEFAULT 'new',
    category_id INT NOT NULL,
    assigned_department_id INT,
    assigned_employee_id INT,
    resolution_details TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    resolution_date DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES reference_data(id) ON DELETE RESTRICT,
    FOREIGN KEY (assigned_department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_employee_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- إدارة الآليات
CREATE TABLE vehicles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    model VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    year YEAR,
    plate_number VARCHAR(20) UNIQUE,
    status ENUM('operational', 'maintenance', 'out_of_service') DEFAULT 'operational',
    purchase_cost DECIMAL(15, 2),
    purchase_date DATE,
    department_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- إدارة الجباية
CREATE TABLE collections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    collection_type_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    currency_id INT NOT NULL,
    collection_date DATETIME NOT NULL,
    citizen_id INT NOT NULL,
    description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    is_paid TINYINT(1) DEFAULT 0,
    recorded_by_user_id INT,
    financial_transaction_id INT UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (collection_type_id) REFERENCES reference_data(id) ON DELETE RESTRICT,
    FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE RESTRICT,
    FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE RESTRICT,
    FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (financial_transaction_id) REFERENCES financial_transactions(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =====================================
-- بيانات أولية أساسية للنظام
-- =====================================

-- إدراج العملات الأساسية
INSERT INTO currencies (name, code, symbol, exchange_rate_to_lbp) VALUES
('ليرة لبنانية', 'LBP', 'ل.ل', 1.0000),
('دولار أمريكي', 'USD', '$', 89500.0000),
('يورو', 'EUR', '€', 95000.0000);

-- إدراج الأدوار الأساسية
INSERT INTO roles (name, description) VALUES
('admin', 'مدير النظام - صلاحيات كاملة'),
('mayor', 'رئيس البلدية'),
('financial_manager', 'مدير مالي'),
('hr_manager', 'مدير موارد بشرية'),
('department_manager', 'مدير قسم'),
('employee', 'موظف عادي'),
('citizen', 'مواطن');

-- إدراج الصلاحيات الأساسية
INSERT INTO permissions (name, description) VALUES
('view_dashboard', 'عرض لوحة التحكم'),
('manage_users', 'إدارة المستخدمين'),
('manage_employees', 'إدارة الموظفين'),
('view_finances', 'عرض البيانات المالية'),
('manage_finances', 'إدارة المالية'),
('view_projects', 'عرض المشاريع'),
('manage_projects', 'إدارة المشاريع'),
('view_complaints', 'عرض الشكاوى'),
('manage_complaints', 'إدارة الشكاوى'),
('view_reports', 'عرض التقارير'),
('manage_settings', 'إدارة الإعدادات');

-- ربط صلاحيات المدير
INSERT INTO role_permissions (role_id, permission_id) 
SELECT 1, id FROM permissions;

-- إدراج البيانات المرجعية الأساسية
INSERT INTO reference_data (type, value, description) VALUES
-- فئات الشكاوى
('complaint_category', 'نفايات وتنظيف', 'شكاوى متعلقة بالنظافة العامة'),
('complaint_category', 'طرق وبنية تحتية', 'مشاكل الطرق والمرافق'),
('complaint_category', 'مياه وصرف صحي', 'مشاكل المياه والصرف'),
('complaint_category', 'إنارة عامة', 'أعطال الإنارة العامة'),
('complaint_category', 'أخرى', 'شكاوى متنوعة'),

-- فئات المصروفات والإيرادات
('expense_category', 'رواتب وأجور', 'مصروفات الموظفين'),
('expense_category', 'صيانة ومعدات', 'مصروفات الصيانة'),
('expense_category', 'وقود ومحروقات', 'تكاليف الوقود'),
('expense_category', 'مواد ومستلزمات', 'المواد المكتبية والاستهلاكية'),
('revenue_category', 'ضرائب وجبايات', 'الإيرادات من الضرائب'),
('revenue_category', 'رسوم رخص', 'رسوم الرخص والمعاملات'),
('revenue_category', 'تبرعات', 'التبرعات والمساعدات'),

-- أنواع الجباية
('collection_type', 'ضريبة الأملاك', 'ضريبة على العقارات'),
('collection_type', 'رسوم المياه', 'رسوم استهلاك المياه'),
('collection_type', 'رسوم الإشغال', 'رسوم إشغال الطرق العامة'),
('collection_type', 'رسوم النظافة', 'رسوم خدمات النظافة'),

-- أنواع النماذج البلدية
('form_type', 'رخصة بناء', 'طلب رخصة بناء جديد'),
('form_type', 'رخصة إشغال', 'رخصة إشغال الطريق العام'),
('form_type', 'تصديق وثائق', 'تصديق وثائق رسمية'),

-- أنواع المخالفات
('violation_type', 'بناء مخالف', 'مخالفات البناء'),
('violation_type', 'إشغال غير مرخص', 'إشغال الطريق العام'),
('violation_type', 'مخالفات بيئية', 'التلوث والنفايات'),

-- فئات المخزون
('inventory_category', 'مواد مكتبية', 'أدوات ومستلزمات مكتبية'),
('inventory_category', 'مواد بناء', 'مواد للبناء والصيانة'),
('inventory_category', 'وقود ومحروقات', 'الوقود والزيوت'),
('inventory_category', 'قطع غيار', 'قطع غيار الآليات');

-- إدراج الأقسام الأساسية
INSERT INTO departments (name, description) VALUES
('الإدارة العامة', 'الإدارة والتنسيق العام'),
('الموارد البشرية', 'إدارة شؤون الموظفين'),
('الإدارة المالية', 'الحسابات والميزانية'),
('الهندسة والمشاريع', 'تخطيط وتنفيذ المشاريع'),
('النظافة والبيئة', 'خدمات النظافة العامة'),
('الصيانة', 'صيانة المرافق والمعدات'),
('خدمة المواطنين', 'استقبال ومتابعة الطلبات'),
('النقل والآليات', 'إدارة الآليات والنقل');

-- إنشاء مستخدم مدير النظام الافتراضي
INSERT INTO users (username, password, full_name, email, role_id, department_id) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدير النظام', 'admin@tekrit-municipality.gov.iq', 1, 1);

COMMIT; 