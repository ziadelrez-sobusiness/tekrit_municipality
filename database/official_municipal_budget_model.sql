-- database/official_municipal_budget_model.sql

CREATE TABLE IF NOT EXISTS municipal_budget_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(255) NOT NULL,
    source_file_name VARCHAR(255),
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS municipal_budget_template_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    section_type ENUM('income', 'expense') NOT NULL,
    section_name VARCHAR(100) NOT NULL,
    chapter_number VARCHAR(50),
    chapter_name VARCHAR(255),
    item_number VARCHAR(50),
    item_name VARCHAR(255) NOT NULL,
    explanation TEXT,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_template_line (template_id, section_type, chapter_number, item_number),
    FOREIGN KEY (template_id) REFERENCES municipal_budget_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS municipal_budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fiscal_year INT NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    template_id INT NOT NULL,
    status ENUM('draft', 'approved', 'closed', 'cancelled') DEFAULT 'draft',
    notes TEXT,
    created_by_user_id INT,
    approved_by_user_id INT,
    approved_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES municipal_budget_templates(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS municipal_budget_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    municipal_budget_id INT NOT NULL,
    template_line_id INT,
    section_type ENUM('income', 'expense') NOT NULL,
    section_name VARCHAR(100) NOT NULL,
    chapter_number VARCHAR(50),
    chapter_name VARCHAR(255),
    item_number VARCHAR(50),
    item_name VARCHAR(255) NOT NULL,
    previous_estimate DECIMAL(15,2) DEFAULT 0,
    current_estimate DECIMAL(15,2) DEFAULT 0,
    actual_amount DECIMAL(15,2) DEFAULT 0,
    currency_id INT,
    explanation TEXT,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (municipal_budget_id) REFERENCES municipal_budgets(id) ON DELETE CASCADE,
    FOREIGN KEY (template_line_id) REFERENCES municipal_budget_template_lines(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Safe ALTER for financial_transactions
SET @dbname = DATABASE();

SET @count = (SELECT COUNT(*) FROM information_schema.COLUMNS 
              WHERE TABLE_SCHEMA = @dbname 
              AND TABLE_NAME = 'financial_transactions' 
              AND COLUMN_NAME = 'municipal_budget_line_id');
SET @sql = IF(@count = 0, 'ALTER TABLE financial_transactions ADD COLUMN municipal_budget_line_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @count2 = (SELECT COUNT(*) FROM information_schema.COLUMNS 
              WHERE TABLE_SCHEMA = @dbname 
              AND TABLE_NAME = 'financial_transactions' 
              AND COLUMN_NAME = 'municipal_budget_id');
SET @sql2 = IF(@count2 = 0, 'ALTER TABLE financial_transactions ADD COLUMN municipal_budget_id INT NULL', 'SELECT 1');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

