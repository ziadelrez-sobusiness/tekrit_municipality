-- ملف ترقية قاعدة البيانات لإضافة دعم العملات والتبرعات المحدث
-- يجب تشغيل هذا الملف على قاعدة البيانات الموجودة

USE tekrit_municipality;

-- إنشاء جدول العملات إذا لم يكن موجوداً
CREATE TABLE IF NOT EXISTS currencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    currency_code VARCHAR(10) NOT NULL UNIQUE,
    currency_name VARCHAR(100) NOT NULL,
    currency_symbol VARCHAR(10) NOT NULL,
    exchange_rate_to_iqd DECIMAL(10,4) DEFAULT 1.0000,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- إدراج العملات الأساسية
INSERT IGNORE INTO currencies (currency_code, currency_name, currency_symbol, exchange_rate_to_iqd) VALUES
('IQD', 'الدينار العراقي', 'د.ع', 1.0000),
('USD', 'الدولار الأمريكي', '$', 1310.0000),
('EUR', 'اليورو', '€', 1420.0000),
('SAR', 'الريال السعودي', 'ر.س', 349.3333),
('TRY', 'الليرة التركية', '₺', 41.2500),
('IRR', 'الريال الإيراني', 'ر.إ', 0.0311);

-- تحديث جدول المشاريع لإضافة حقول الجهة المانحة والعملة
ALTER TABLE projects 
ADD COLUMN IF NOT EXISTS budget_currency_id INT DEFAULT 1,
ADD COLUMN IF NOT EXISTS actual_cost_currency_id INT DEFAULT 1,
ADD COLUMN IF NOT EXISTS donor_name VARCHAR(200),
ADD COLUMN IF NOT EXISTS donor_type ENUM('حكومي', 'خاص', 'منظمة دولية', 'منظمة خيرية', 'أفراد', 'أخرى') DEFAULT 'حكومي',
ADD COLUMN IF NOT EXISTS donor_contact VARCHAR(100),
ADD COLUMN IF NOT EXISTS funding_type ENUM('كامل', 'جزئي', 'مشترك') DEFAULT 'كامل';

-- إضافة المفاتيح الخارجية لجدول المشاريع
ALTER TABLE projects 
ADD CONSTRAINT IF NOT EXISTS fk_projects_budget_currency 
    FOREIGN KEY (budget_currency_id) REFERENCES currencies(id),
ADD CONSTRAINT IF NOT EXISTS fk_projects_actual_cost_currency 
    FOREIGN KEY (actual_cost_currency_id) REFERENCES currencies(id);

-- تحديث جدول التبرعات
DROP TABLE IF EXISTS donations;
CREATE TABLE donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donation_number VARCHAR(50) UNIQUE,
    donor_name VARCHAR(200) NOT NULL,
    donor_type ENUM('فرد', 'شركة', 'منظمة', 'جهة حكومية', 'منظمة دولية') NOT NULL,
    donor_phone VARCHAR(20),
    donor_email VARCHAR(100),
    donor_address TEXT,
    donation_type ENUM('نقدي', 'عيني', 'خدمي') NOT NULL,
    amount DECIMAL(15,2),
    currency_id INT DEFAULT 1,
    items_description TEXT,
    estimated_value DECIMAL(15,2),
    estimated_value_currency_id INT DEFAULT 1,
    purpose VARCHAR(500),
    allocated_to_project_id INT,
    allocated_to_department VARCHAR(100),
    status ENUM('مستلم', 'قيد المراجعة', 'موافق عليه', 'قيد التوزيع', 'موزع', 'مرفوض') DEFAULT 'مستلم',
    received_by_user_id INT,
    received_date DATE,
    approval_date DATE,
    distribution_date DATE,
    tax_exemption_number VARCHAR(100),
    receipt_issued BOOLEAN DEFAULT FALSE,
    thank_you_sent BOOLEAN DEFAULT FALSE,
    notes TEXT,
    attachments JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (allocated_to_project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (received_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (estimated_value_currency_id) REFERENCES currencies(id)
);

-- تحديث جدول المعاملات المالية لإضافة دعم العملات
ALTER TABLE financial_transactions 
ADD COLUMN IF NOT EXISTS currency_id INT DEFAULT 1,
ADD COLUMN IF NOT EXISTS exchange_rate DECIMAL(10,4) DEFAULT 1.0000,
ADD COLUMN IF NOT EXISTS amount_in_iqd DECIMAL(12,2) GENERATED ALWAYS AS (amount * exchange_rate) STORED,
ADD COLUMN IF NOT EXISTS payment_method ENUM('نقد', 'شيك', 'تحويل مصرفي', 'بطاقة ائتمان', 'أخرى') DEFAULT 'نقد',
ADD COLUMN IF NOT EXISTS bank_name VARCHAR(100),
ADD COLUMN IF NOT EXISTS check_number VARCHAR(50),
ADD COLUMN IF NOT EXISTS related_project_id INT,
ADD COLUMN IF NOT EXISTS related_donation_id INT,
ADD COLUMN IF NOT EXISTS approved_by INT;

-- إضافة المفاتيح الخارجية للمعاملات المالية
ALTER TABLE financial_transactions 
ADD CONSTRAINT IF NOT EXISTS fk_financial_currency 
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
ADD CONSTRAINT IF NOT EXISTS fk_financial_approved_by 
    FOREIGN KEY (approved_by) REFERENCES users(id),
ADD CONSTRAINT IF NOT EXISTS fk_financial_project 
    FOREIGN KEY (related_project_id) REFERENCES projects(id) ON DELETE SET NULL,
ADD CONSTRAINT IF NOT EXISTS fk_financial_donation 
    FOREIGN KEY (related_donation_id) REFERENCES donations(id) ON DELETE SET NULL;

-- إنشاء جدول إدارة المتبرعين
CREATE TABLE IF NOT EXISTS donors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donor_code VARCHAR(50) UNIQUE,
    name VARCHAR(200) NOT NULL,
    type ENUM('فرد', 'شركة', 'منظمة', 'جهة حكومية', 'منظمة دولية') NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    website VARCHAR(200),
    tax_number VARCHAR(100),
    registration_number VARCHAR(100),
    preferred_donation_areas JSON,
    total_donations DECIMAL(15,2) DEFAULT 0,
    total_donations_iqd DECIMAL(15,2) DEFAULT 0,
    first_donation_date DATE,
    last_donation_date DATE,
    donation_count INT DEFAULT 0,
    status ENUM('نشط', 'غير نشط', 'محظور') DEFAULT 'نشط',
    rating ENUM('ممتاز', 'جيد جداً', 'جيد', 'مقبول') DEFAULT 'جيد',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- إنشاء جدول شهادات الشكر والتقدير
CREATE TABLE IF NOT EXISTS appreciation_certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    certificate_number VARCHAR(50) UNIQUE,
    donor_id INT NOT NULL,
    donation_id INT,
    certificate_type ENUM('شهادة شكر', 'شهادة تقدير', 'درع تكريم', 'خطاب شكر') DEFAULT 'شهادة شكر',
    title VARCHAR(500) NOT NULL,
    content TEXT NOT NULL,
    issued_date DATE NOT NULL,
    issued_by_user_id INT,
    delivered BOOLEAN DEFAULT FALSE,
    delivery_date DATE,
    delivery_method ENUM('يدوياً', 'بريد', 'إيميل', 'أخرى'),
    template_used VARCHAR(100),
    file_path VARCHAR(500),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES donors(id) ON DELETE CASCADE,
    FOREIGN KEY (donation_id) REFERENCES donations(id) ON DELETE SET NULL,
    FOREIGN KEY (issued_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- إنشاء جدول حملات التبرع
CREATE TABLE IF NOT EXISTS donation_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_name VARCHAR(200) NOT NULL,
    description TEXT,
    target_amount DECIMAL(15,2),
    target_currency_id INT DEFAULT 1,
    raised_amount DECIMAL(15,2) DEFAULT 0,
    raised_currency_id INT DEFAULT 1,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    campaign_type ENUM('طارئة', 'موسمية', 'مشروع محدد', 'عامة') DEFAULT 'عامة',
    target_audience VARCHAR(500),
    responsible_person_id INT,
    status ENUM('مخططة', 'نشطة', 'منتهية', 'ملغية') DEFAULT 'مخططة',
    success_rate DECIMAL(5,2) GENERATED ALWAYS AS (
        CASE 
            WHEN target_amount > 0 THEN (raised_amount / target_amount) * 100
            ELSE 0
        END
    ) STORED,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (target_currency_id) REFERENCES currencies(id),
    FOREIGN KEY (raised_currency_id) REFERENCES currencies(id),
    FOREIGN KEY (responsible_person_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ربط التبرعات بالحملات
CREATE TABLE IF NOT EXISTS campaign_donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    donation_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES donation_campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (donation_id) REFERENCES donations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_campaign_donation (campaign_id, donation_id)
);

-- إضافة فهارس جديدة لتحسين الأداء
CREATE INDEX IF NOT EXISTS idx_currencies_code ON currencies(currency_code);
CREATE INDEX IF NOT EXISTS idx_donations_type ON donations(donation_type);
CREATE INDEX IF NOT EXISTS idx_donations_status ON donations(status);
CREATE INDEX IF NOT EXISTS idx_donations_donor_type ON donations(donor_type);
CREATE INDEX IF NOT EXISTS idx_projects_donor_type ON projects(donor_type);
CREATE INDEX IF NOT EXISTS idx_financial_currency ON financial_transactions(currency_id);
CREATE INDEX IF NOT EXISTS idx_donors_type ON donors(type);
CREATE INDEX IF NOT EXISTS idx_donors_status ON donors(status);

-- تحديث أسماء الأعمدة في الجداول الموجودة إذا لزم الأمر
-- هذا التحديث آمن ولن يؤثر على البيانات الموجودة

-- إضافة بيانات تجريبية للتبرعات
INSERT IGNORE INTO donations (donation_number, donor_name, donor_type, donor_phone, donation_type, amount, currency_id, purpose, status, received_by_user_id, received_date) VALUES
('DON2025001', 'شركة النور للتجارة', 'شركة', '07701234567', 'نقدي', 5000000, 1, 'دعم مشاريع البنية التحتية', 'موافق عليه', 1, '2025-01-01'),
('DON2025002', 'أحمد محمد علي', 'فرد', '07709876543', 'عيني', 0, 1, 'تبرع بمعدات طبية للمركز الصحي', 'مستلم', 1, '2025-01-02'),
('DON2025003', 'منظمة الهلال الأحمر', 'منظمة', '07812345678', 'نقدي', 10000000, 1, 'دعم برامج الإغاثة والطوارئ', 'قيد التوزيع', 1, '2025-01-03');

-- إضافة بيانات تجريبية للمتبرعين
INSERT IGNORE INTO donors (donor_code, name, type, phone, email, status, total_donations) VALUES
('DNR001', 'شركة النور للتجارة', 'شركة', '07701234567', 'info@noortrading.com', 'نشط', 5000000),
('DNR002', 'أحمد محمد علي', 'فرد', '07709876543', 'ahmed.mohammed@gmail.com', 'نشط', 2000000),
('DNR003', 'منظمة الهلال الأحمر', 'منظمة', '07812345678', 'contact@redcrescent.org', 'نشط', 10000000);

COMMIT; 