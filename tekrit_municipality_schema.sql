-- مخطط قاعدة البيانات الشامل لنظام إدارة بلدية تكريت
CREATE DATABASE IF NOT EXISTS tekrit_municipality 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tekrit_municipality;

-- الجداول الأساسية
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE currencies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    code VARCHAR(5) UNIQUE NOT NULL,
    symbol VARCHAR(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    exchange_rate_to_lbp DECIMAL(10, 4),
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE reference_data (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(50) NOT NULL,
    value VARCHAR(255) NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (type)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) UNIQUE NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

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
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE citizens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(255) NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    national_id VARCHAR(20) UNIQUE,
    address VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    phone VARCHAR(20),
    email VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- الجدول المالي المحوري
CREATE TABLE financial_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type ENUM('revenue', 'expense') NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    currency_id INT NOT NULL,
    transaction_date DATETIME NOT NULL,
    description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    category_id INT NOT NULL,
    department_id INT,
    recorded_by_user_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE RESTRICT,
    FOREIGN KEY (category_id) REFERENCES reference_data(id) ON DELETE RESTRICT,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- جداول الأقسام المختلفة
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
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE RESTRICT,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE complaints (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject VARCHAR(255) NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    details TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    citizen_id INT,
    date_submitted DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('new', 'in_progress', 'completed', 'rejected') DEFAULT 'new',
    category_id INT NOT NULL,
    assigned_department_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES reference_data(id) ON DELETE RESTRICT,
    FOREIGN KEY (assigned_department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- بيانات أولية
INSERT INTO currencies (name, code, symbol, exchange_rate_to_lbp) VALUES
('ليرة لبنانية', 'LBP', 'ل.ل', 1.0000),
('دولار أمريكي', 'USD', '$', 89500.0000);

INSERT INTO roles (name, description) VALUES
('admin', 'مدير النظام'),
('mayor', 'رئيس البلدية'),
('employee', 'موظف'),
('citizen', 'مواطن');

INSERT INTO reference_data (type, value, description) VALUES
('complaint_category', 'نفايات وتنظيف', 'شكاوى النظافة'),
('complaint_category', 'طرق وبنية تحتية', 'مشاكل الطرق'),
('expense_category', 'رواتب وأجور', 'مصروفات الموظفين'),
('revenue_category', 'ضرائب وجبايات', 'الإيرادات');

INSERT INTO departments (name, description) VALUES
('الإدارة العامة', 'الإدارة والتنسيق'),
('الموارد البشرية', 'إدارة الموظفين'),
('الإدارة المالية', 'الحسابات والميزانية'),
('النظافة والبيئة', 'خدمات النظافة');

INSERT INTO users (username, password, full_name, role_id, department_id) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدير النظام', 1, 1); 