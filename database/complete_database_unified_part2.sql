-- الجزء الثاني من ملف قاعدة البيانات الموحد
-- تكملة الجداول والبيانات

USE tekrit_municipality;

-- ==========================================
-- جدول عمليات الجباية
-- ==========================================
CREATE TABLE IF NOT EXISTS tax_collections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    collection_number VARCHAR(50) UNIQUE,
    citizen_id INT NOT NULL,
    tax_type_id INT NOT NULL,
    
    -- تفاصيل المبلغ
    base_amount DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    penalty_amount DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL,
    currency_id INT DEFAULT 1,
    exchange_rate DECIMAL(10,4) DEFAULT 1.0000,
    amount_in_iqd DECIMAL(12,2) GENERATED ALWAYS AS (total_amount * exchange_rate) STORED,
    
    -- تواريخ
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    payment_date DATE,
    
    -- حالة الدفع
    payment_status ENUM('مستحق', 'مدفوع جزئياً', 'مدفوع كاملاً', 'متأخر', 'معفى', 'ملغي') DEFAULT 'مستحق',
    paid_amount DECIMAL(12,2) DEFAULT 0,
    remaining_amount DECIMAL(12,2) GENERATED ALWAYS AS (total_amount - paid_amount) STORED,
    
    -- طريقة الدفع
    payment_method ENUM('نقد', 'شيك', 'تحويل مصرفي', 'بطاقة ائتمان', 'أخرى'),
    reference_number VARCHAR(100),
    receipt_number VARCHAR(100),
    
    -- معلومات إضافية
    service_description TEXT, -- وصف الخدمة المقدمة
    location_details TEXT, -- تفاصيل الموقع إن وجد
    period_from DATE, -- من تاريخ (للرسوم الدورية)
    period_to DATE, -- إلى تاريخ
    
    -- الموظفين المسؤولين
    issued_by_user_id INT,
    collected_by_user_id INT,
    
    notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (citizen_id) REFERENCES citizens(id),
    FOREIGN KEY (tax_type_id) REFERENCES tax_types(id),
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (issued_by_user_id) REFERENCES users(id),
    FOREIGN KEY (collected_by_user_id) REFERENCES users(id)
);

-- ==========================================
-- جدول الشكاوى
-- ==========================================
CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_number VARCHAR(50) UNIQUE,
    citizen_name VARCHAR(100) NOT NULL,
    citizen_phone VARCHAR(20),
    citizen_email VARCHAR(100),
    citizen_address TEXT,
    complaint_type ENUM('خدمات عامة', 'نظافة', 'صيانة', 'مالية', 'أخرى') DEFAULT 'خدمات عامة',
    subject VARCHAR(500) NOT NULL,
    description TEXT NOT NULL,
    priority ENUM('عادية', 'متوسطة', 'عالية', 'عاجلة') DEFAULT 'عادية',
    status ENUM('جديدة', 'قيد المراجعة', 'قيد المعالجة', 'مكتملة', 'مرفوضة') DEFAULT 'جديدة',
    department VARCHAR(100),
    assigned_to INT,
    response TEXT,
    resolution_date DATE,
    satisfaction_rating INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);

-- ==========================================
-- جدول المشاريع
-- ==========================================
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(200) NOT NULL,
    description TEXT,
    project_type VARCHAR(100),
    location VARCHAR(200),
    start_date DATE,
    end_date DATE,
    budget DECIMAL(15,2),
    budget_currency_id INT DEFAULT 1,
    actual_cost DECIMAL(15,2) DEFAULT 0,
    actual_cost_currency_id INT DEFAULT 1,
    progress_percentage INT DEFAULT 0,
    status ENUM('مخطط', 'قيد التنفيذ', 'مكتمل', 'متوقف', 'ملغي') DEFAULT 'مخطط',
    contractor VARCHAR(200),
    donor_name VARCHAR(200),
    donor_type ENUM('حكومي', 'خاص', 'منظمة دولية', 'منظمة خيرية', 'أفراد', 'أخرى') DEFAULT 'حكومي',
    donor_contact VARCHAR(100),
    funding_type ENUM('كامل', 'جزئي', 'مشترك') DEFAULT 'كامل',
    manager_id INT,
    priority ENUM('عالية', 'متوسطة', 'منخفضة') DEFAULT 'متوسطة',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES users(id),
    FOREIGN KEY (budget_currency_id) REFERENCES currencies(id),
    FOREIGN KEY (actual_cost_currency_id) REFERENCES currencies(id)
);

-- ==========================================
-- جدول مراحل تنفيذ المشاريع
-- ==========================================
CREATE TABLE IF NOT EXISTS project_phases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    phase_number INT NOT NULL,
    phase_name VARCHAR(200) NOT NULL,
    description TEXT,
    
    -- التواريخ والتوقيتات
    planned_start_date DATE,
    planned_end_date DATE,
    actual_start_date DATE,
    actual_end_date DATE,
    
    -- التكاليف والمبالغ
    planned_cost DECIMAL(15,2),
    actual_cost DECIMAL(15,2) DEFAULT 0,
    currency_id INT DEFAULT 1,
    
    -- التقدم والحالة
    progress_percentage INT DEFAULT 0,
    status ENUM('مخطط', 'قيد التنفيذ', 'مكتمل', 'متأخر', 'متوقف', 'ملغي') DEFAULT 'مخطط',
    
    -- الجهة المنفذة والمستفيدون
    executing_entity VARCHAR(200), -- الجهة المنفذة
    beneficiaries TEXT, -- المستفيدون
    beneficiaries_count INT DEFAULT 0,
    
    -- معلومات الضرورة والأولوية
    necessity_level ENUM('عالية جداً', 'عالية', 'متوسطة', 'منخفضة') DEFAULT 'متوسطة',
    impact_assessment TEXT, -- تقييم الأثر المتوقع
    
    -- الموظف المسؤول
    responsible_user_id INT,
    
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (responsible_user_id) REFERENCES users(id)
);

-- ==========================================
-- جدول التبرعات المحدث
-- ==========================================
CREATE TABLE IF NOT EXISTS donations (
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

-- ==========================================
-- جدول إدارة المتبرعين
-- ==========================================
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

-- ==========================================
-- جدول شهادات الشكر والتقدير
-- ==========================================
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

-- ==========================================
-- جدول حملات التبرع
-- ==========================================
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

-- ==========================================
-- ربط التبرعات بالحملات
-- ==========================================
CREATE TABLE IF NOT EXISTS campaign_donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    donation_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES donation_campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (donation_id) REFERENCES donations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_campaign_donation (campaign_id, donation_id)
);

-- يتبع في الجزء الثالث... 