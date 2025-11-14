-- مخطط قاعدة البيانات الشامل لنظام إدارة بلدية تكريت
-- تم تصميمه وفقاً للمواصفات المطلوبة مع الترابط الكامل بين الجداول

-- إعداد قاعدة البيانات
CREATE DATABASE IF NOT EXISTS tekrit_municipality 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tekrit_municipality;

-- جدول الأدوار (roles)
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL COMMENT 'admin, mayor, financial_manager, employee, citizen',
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول الصلاحيات (permissions)
CREATE TABLE permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL COMMENT 'view_complaints, create_project, edit_salary',
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول صلاحيات الأدوار (role_permissions) - جدول وسيط
CREATE TABLE role_permissions (
    role_id INT,
    permission_id INT,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- جدول العملات (currencies)
CREATE TABLE currencies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    code VARCHAR(5) UNIQUE NOT NULL COMMENT 'LBP, USD, EUR',
    symbol VARCHAR(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    exchange_rate_to_lbp DECIMAL(10, 4) COMMENT 'سعر الصرف مقابل الليرة اللبنانية',
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول الأقسام (departments)
CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) UNIQUE NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    manager_employee_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول المستخدمين (users)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL COMMENT 'مخزن كـ Hash',
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

-- جدول الموظفين (employees)
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

-- تحديث foreign key للمدير في جدول الأقسام
ALTER TABLE departments 
ADD FOREIGN KEY (manager_employee_id) REFERENCES employees(id) ON DELETE SET NULL;

-- جدول المواطنين (citizens)
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

-- جدول الجداول المرجعية (reference_data)
CREATE TABLE reference_data (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(50) NOT NULL COMMENT 'complaint_category, expense_category, violation_type, etc.',
    value VARCHAR(255) NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (type)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول الأرشيف الإلكتروني (archives)
CREATE TABLE archives (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    document_date DATE,
    category_id INT,
    file_path VARCHAR(512) NOT NULL,
    file_type VARCHAR(50),
    uploaded_by_user_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES reference_data(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول المشاريع (projects)
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

-- جدول الموردين (suppliers)
CREATE TABLE suppliers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) UNIQUE NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    contact_person VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    phone VARCHAR(20),
    email VARCHAR(255),
    address TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول الجهات المانحة (donors)
CREATE TABLE donors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) UNIQUE NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    contact_person VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    phone VARCHAR(20),
    email VARCHAR(255),
    address TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول المكبات (landfills)
CREATE TABLE landfills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) UNIQUE NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    location VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول عناصر المخزون (inventory_items)
CREATE TABLE inventory_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) UNIQUE NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    category_id INT NOT NULL,
    unit VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'حبة، لتر، كيلو',
    current_stock DECIMAL(10, 2) DEFAULT 0,
    min_stock_level DECIMAL(10, 2) DEFAULT 0,
    location VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES reference_data(id) ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول الآليات (vehicles)
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

-- جدول عناصر البنية التحتية (infrastructure_items)
CREATE TABLE infrastructure_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type ENUM('road', 'water_network', 'electricity_network', 'street_light', 'building') NOT NULL,
    name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    location VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    status ENUM('good', 'needs_maintenance', 'damaged') DEFAULT 'good',
    installation_date DATE,
    last_maintenance_date DATE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول القيود المالية (financial_transactions) - المحور المالي
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
    employee_id INT COMMENT 'للرواتب',
    citizen_id INT COMMENT 'للجبايات/الرسوم',
    donation_id INT,
    collection_id INT,
    purchase_id INT,
    maintenance_id INT,
    contract_id INT,
    violation_id INT,
    form_id INT COMMENT 'لرسوم المعاملات',
    recorded_by_user_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE RESTRICT,
    FOREIGN KEY (category_id) REFERENCES reference_data(id) ON DELETE RESTRICT,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_type_date (type, transaction_date),
    INDEX idx_category (category_id),
    INDEX idx_department (department_id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول الميزانيات (budgets)
CREATE TABLE budgets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fiscal_year YEAR NOT NULL,
    department_id INT,
    project_id INT,
    allocated_amount DECIMAL(15, 2) NOT NULL,
    currency_id INT NOT NULL,
    description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    status ENUM('draft', 'approved', 'closed') DEFAULT 'draft',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول اللجان (committees)
CREATE TABLE committees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) UNIQUE NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    department_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول أعضاء اللجان (committee_members)
CREATE TABLE committee_members (
    committee_id INT,
    employee_id INT,
    role_on_committee VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'رئيس، عضو',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (committee_id, employee_id),
    FOREIGN KEY (committee_id) REFERENCES committees(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول الجلسات (meetings)
CREATE TABLE meetings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    committee_id INT NOT NULL,
    title VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    date_time DATETIME,
    location VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    agenda TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    minutes_of_meeting_archive_id INT UNIQUE,
    recorded_by_user_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (committee_id) REFERENCES committees(id) ON DELETE RESTRICT,
    FOREIGN KEY (minutes_of_meeting_archive_id) REFERENCES archives(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول القرارات (resolutions)
CREATE TABLE resolutions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    meeting_id INT NOT NULL,
    title VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    resolution_date DATE,
    status ENUM('draft', 'approved', 'implemented') DEFAULT 'draft',
    related_project_id INT,
    resolution_document_archive_id INT UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (related_project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (resolution_document_archive_id) REFERENCES archives(id) ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول الجبايات (collections)
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

-- جدول المشتريات (purchases)
CREATE TABLE purchases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    supplier_id INT NOT NULL,
    purchase_date DATE,
    total_amount DECIMAL(15, 2),
    currency_id INT NOT NULL,
    status ENUM('pending', 'completed', 'canceled') DEFAULT 'pending',
    invoice_archive_id INT UNIQUE,
    recorded_by_user_id INT,
    financial_transaction_id INT UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE RESTRICT,
    FOREIGN KEY (invoice_archive_id) REFERENCES archives(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (financial_transaction_id) REFERENCES financial_transactions(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول عناصر المشتريات (purchase_items)
CREATE TABLE purchase_items (
    purchase_id INT,
    item_id INT,
    quantity DECIMAL(10, 2),
    unit_price DECIMAL(10, 2),
    PRIMARY KEY (purchase_id, item_id),
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- جدول سجلات الصيانة الشاملة (maintenance_records)
CREATE TABLE maintenance_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type ENUM('vehicle', 'infrastructure', 'building') NOT NULL,
    related_id INT NOT NULL COMMENT 'ID للعنصر المصان',
    description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    maintenance_date DATETIME NOT NULL,
    cost DECIMAL(15, 2),
    currency_id INT NOT NULL,
    duration_hours DECIMAL(5, 2),
    performed_by_user_id INT,
    financial_transaction_id INT UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE RESTRICT,
    FOREIGN KEY (performed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (financial_transaction_id) REFERENCES financial_transactions(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول جداول جمع النفايات (waste_collection_schedules)
CREATE TABLE waste_collection_schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    area VARCHAR(255) NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    collection_day VARCHAR(100) NOT NULL,
    collection_time TIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول سجلات نقل النفايات (waste_transport_logs)
CREATE TABLE waste_transport_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    date DATE NOT NULL,
    volume_tons DECIMAL(10, 2),
    cost DECIMAL(10, 2),
    currency_id INT NOT NULL,
    landfill_id INT NOT NULL,
    vehicle_id INT,
    driver_employee_id INT,
    financial_transaction_id INT UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE RESTRICT,
    FOREIGN KEY (landfill_id) REFERENCES landfills(id) ON DELETE RESTRICT,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
    FOREIGN KEY (driver_employee_id) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (financial_transaction_id) REFERENCES financial_transactions(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول الشكاوى (complaints)
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
    archive_document_id INT UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES reference_data(id) ON DELETE RESTRICT,
    FOREIGN KEY (assigned_department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_employee_id) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (archive_document_id) REFERENCES archives(id) ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول النماذج البلدية (municipal_forms)
CREATE TABLE municipal_forms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    form_type_id INT NOT NULL,
    citizen_id INT NOT NULL,
    submission_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected', 'in_review') DEFAULT 'pending',
    details_json JSON COMMENT 'حقول النموذج المتغيرة',
    mukhtar_signature_archive_id INT,
    electronic_signature_status ENUM('pending', 'signed', 'rejected') DEFAULT 'pending',
    license_document_archive_id INT,
    financial_transaction_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (form_type_id) REFERENCES reference_data(id) ON DELETE RESTRICT,
    FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE RESTRICT,
    FOREIGN KEY (mukhtar_signature_archive_id) REFERENCES archives(id) ON DELETE SET NULL,
    FOREIGN KEY (license_document_archive_id) REFERENCES archives(id) ON DELETE SET NULL,
    FOREIGN KEY (financial_transaction_id) REFERENCES financial_transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول التبرعات (donations)
CREATE TABLE donations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donor_id INT NOT NULL,
    type ENUM('cash', 'in_kind') NOT NULL,
    amount DECIMAL(15, 2),
    currency_id INT,
    donation_date DATE NOT NULL,
    description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    allocated_to_project_id INT,
    allocated_to_department_id INT,
    usage_report_archive_id INT,
    financial_transaction_id INT UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES donors(id) ON DELETE RESTRICT,
    FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE RESTRICT,
    FOREIGN KEY (allocated_to_project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (allocated_to_department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (usage_report_archive_id) REFERENCES archives(id) ON DELETE SET NULL,
    FOREIGN KEY (financial_transaction_id) REFERENCES financial_transactions(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول الإعلانات (announcements)
CREATE TABLE announcements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    content TEXT NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    publish_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    type ENUM('decision', 'tender', 'meeting_schedule', 'general_announcement') NOT NULL,
    author_user_id INT,
    archive_document_id INT UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (archive_document_id) REFERENCES archives(id) ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول سجلات الرسائل النصية (sms_logs)
CREATE TABLE sms_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_phone VARCHAR(20) NOT NULL,
    message_content TEXT NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    send_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    message_type ENUM('citizen_alert', 'employee_notification', 'announcement_broadcast', 'form_status') NOT NULL,
    related_citizen_id INT,
    related_employee_id INT,
    related_announcement_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (related_citizen_id) REFERENCES citizens(id) ON DELETE SET NULL,
    FOREIGN KEY (related_employee_id) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (related_announcement_id) REFERENCES announcements(id) ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول المخالفات (violations)
CREATE TABLE violations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    violation_type_id INT NOT NULL,
    description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    location_details VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    violation_date DATE NOT NULL,
    citizen_id INT,
    status ENUM('new', 'pending_payment', 'paid', 'contested') DEFAULT 'new',
    fine_amount DECIMAL(10, 2),
    currency_id INT NOT NULL,
    issued_by_user_id INT,
    violation_photos_archive_id INT,
    financial_transaction_id INT UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (violation_type_id) REFERENCES reference_data(id) ON DELETE RESTRICT,
    FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE SET NULL,
    FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE RESTRICT,
    FOREIGN KEY (issued_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (violation_photos_archive_id) REFERENCES archives(id) ON DELETE SET NULL,
    FOREIGN KEY (financial_transaction_id) REFERENCES financial_transactions(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول العقود (contracts)
CREATE TABLE contracts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    contract_type_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    total_value DECIMAL(15, 2),
    currency_id INT NOT NULL,
    supplier_id INT NOT NULL,
    related_project_id INT,
    status ENUM('draft', 'signed', 'in_progress', 'completed', 'canceled') DEFAULT 'draft',
    contract_document_archive_id INT UNIQUE,
    recorded_by_user_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_type_id) REFERENCES reference_data(id) ON DELETE RESTRICT,
    FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE RESTRICT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (related_project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (contract_document_archive_id) REFERENCES archives(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- الجداول الوسيطة للعلاقات Many-to-Many

-- جدول موظفو المشاريع (project_employees)
CREATE TABLE project_employees (
    project_id INT,
    employee_id INT,
    role_on_project VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    PRIMARY KEY (project_id, employee_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جدول آليات المشاريع (project_vehicles)
CREATE TABLE project_vehicles (
    project_id INT,
    vehicle_id INT,
    usage_hours_or_km DECIMAL(10, 2),
    PRIMARY KEY (project_id, vehicle_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- إضافة Foreign Keys المتبقية للجدول المالي
ALTER TABLE financial_transactions 
ADD FOREIGN KEY (donation_id) REFERENCES donations(id) ON DELETE SET NULL,
ADD FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE SET NULL,
ADD FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE SET NULL,
ADD FOREIGN KEY (maintenance_id) REFERENCES maintenance_records(id) ON DELETE SET NULL,
ADD FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE SET NULL,
ADD FOREIGN KEY (violation_id) REFERENCES violations(id) ON DELETE SET NULL,
ADD FOREIGN KEY (form_id) REFERENCES municipal_forms(id) ON DELETE SET NULL; 