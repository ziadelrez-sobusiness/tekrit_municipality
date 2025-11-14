-- إنشاء قاعدة بيانات بلدية تكريت
CREATE DATABASE IF NOT EXISTS tekrit_municipality CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tekrit_municipality;

-- جدول أنواع العملات
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
INSERT INTO currencies (currency_code, currency_name, currency_symbol, exchange_rate_to_iqd) VALUES
('IQD', 'الدينار العراقي', 'د.ع', 1.0000),
('USD', 'الدولار الأمريكي', '$', 1310.0000),
('EUR', 'اليورو', '€', 1420.0000),
('SAR', 'الريال السعودي', 'ر.س', 349.3333),
('TRY', 'الليرة التركية', '₺', 41.2500),
('IRR', 'الريال الإيراني', 'ر.إ', 0.0311);

-- جدول المستخدمين (الموظفين والإداريين)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20),
    user_type ENUM('admin', 'manager', 'employee') DEFAULT 'employee',
    department VARCHAR(50),
    position VARCHAR(50),
    salary DECIMAL(10,2) DEFAULT 0,
    hire_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- جدول الشكاوى
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

-- جدول المشاريع
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

-- جدول التبرعات المحدث
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

-- جدول الآليات والمعدات
CREATE TABLE IF NOT EXISTS vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_number VARCHAR(50) UNIQUE NOT NULL,
    vehicle_type VARCHAR(100),
    brand VARCHAR(100),
    model VARCHAR(100),
    year_made YEAR,
    purchase_date DATE,
    purchase_cost DECIMAL(12,2),
    current_value DECIMAL(12,2),
    fuel_type ENUM('بنزين', 'ديزل', 'كهربائي', 'هجين'),
    department VARCHAR(100),
    driver_name VARCHAR(100),
    status ENUM('جاهز', 'قيد الصيانة', 'معطل', 'خارج الخدمة') DEFAULT 'جاهز',
    last_maintenance DATE,
    next_maintenance DATE,
    maintenance_cost DECIMAL(10,2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- جدول صيانة الآليات
CREATE TABLE IF NOT EXISTS vehicle_maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    maintenance_date DATE NOT NULL,
    maintenance_type ENUM('صيانة دورية', 'إصلاح', 'فحص', 'استبدال قطع') DEFAULT 'صيانة دورية',
    description TEXT,
    cost DECIMAL(10,2),
    garage_name VARCHAR(200),
    next_maintenance_date DATE,
    odometer_reading INT,
    parts_replaced TEXT,
    performed_by VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
);

-- جدول الإيرادات والمصروفات
CREATE TABLE IF NOT EXISTS financial_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_date DATE NOT NULL,
    type ENUM('إيراد', 'مصروف') NOT NULL,
    category VARCHAR(100),
    description TEXT,
    amount DECIMAL(12,2) NOT NULL,
    currency_id INT DEFAULT 1,
    exchange_rate DECIMAL(10,4) DEFAULT 1.0000,
    amount_in_iqd DECIMAL(12,2) GENERATED ALWAYS AS (amount * exchange_rate) STORED,
    reference_number VARCHAR(50),
    payment_method ENUM('نقد', 'شيك', 'تحويل مصرفي', 'بطاقة ائتمان', 'أخرى') DEFAULT 'نقد',
    bank_name VARCHAR(100),
    check_number VARCHAR(50),
    department VARCHAR(50),
    related_project_id INT,
    related_donation_id INT,
    created_by INT,
    approved_by INT,
    status ENUM('مسودة', 'معتمد', 'ملغي') DEFAULT 'مسودة',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (related_project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (related_donation_id) REFERENCES donations(id) ON DELETE SET NULL
);

-- جدول إدارة المتبرعين
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

-- جدول شهادات الشكر والتقدير
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

-- جدول حملات التبرع
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

-- جدول النماذج البلدية
CREATE TABLE municipal_forms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_type VARCHAR(50) NOT NULL,
    applicant_name VARCHAR(100) NOT NULL,
    applicant_phone VARCHAR(20),
    applicant_address TEXT,
    application_data JSON NOT NULL, -- لحفظ بيانات النموذج المتغيرة
    status ENUM('مقدم', 'قيد المراجعة', 'موافق عليه', 'مرفوض', 'مكتمل') DEFAULT 'مقدم',
    submission_date DATE NOT NULL,
    review_date DATE,
    approval_date DATE,
    reviewed_by_user_id INT,
    notes TEXT,
    attachments JSON, -- لحفظ مسارات الملفات المرفقة
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- جدول الأرشيف الإلكتروني
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_title VARCHAR(500) NOT NULL,
    description TEXT,
    file_name VARCHAR(500),
    file_path VARCHAR(1000),
    file_size BIGINT,
    file_type VARCHAR(10),
    category VARCHAR(100),
    department VARCHAR(100),
    access_level ENUM('عام', 'محدود', 'سري') DEFAULT 'محدود',
    tags TEXT,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT,
    document_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    download_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- جدول إدارة النفايات
CREATE TABLE waste_management (
    id INT AUTO_INCREMENT PRIMARY KEY,
    area_name VARCHAR(100) NOT NULL,
    collection_schedule VARCHAR(100) NOT NULL,
    last_collection_date DATE,
    next_collection_date DATE,
    assigned_vehicle_id INT,
    assigned_team_leader_id INT,
    collection_cost DECIMAL(10,2),
    waste_type VARCHAR(50),
    status ENUM('مجدول', 'قيد التنفيذ', 'مكتمل', 'متأخر') DEFAULT 'مجدول',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_team_leader_id) REFERENCES users(id) ON DELETE SET NULL
);

-- جدول الرسائل النصية
CREATE TABLE sms_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_text TEXT NOT NULL,
    recipient_type ENUM('موظف', 'مواطن', 'مجموعة') NOT NULL,
    recipients JSON NOT NULL, -- أرقام الهواتف
    sent_by_user_id INT,
    status ENUM('مرسل', 'فشل', 'معلق') DEFAULT 'معلق',
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sent_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- جدول الإعلانات
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    announcement_type VARCHAR(50) NOT NULL,
    target_audience ENUM('عام', 'موظفين', 'مجلس بلدي') DEFAULT 'عام',
    publish_date DATE NOT NULL,
    expiry_date DATE,
    is_published BOOLEAN DEFAULT FALSE,
    published_by_user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (published_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- جدول صيانة البنية التحتية
CREATE TABLE infrastructure_maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    infrastructure_type VARCHAR(50) NOT NULL, -- طرق، مياه، كهرباء، إنارة
    location VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    maintenance_type ENUM('دورية', 'طارئة', 'وقائية') NOT NULL,
    status ENUM('مجدول', 'قيد التنفيذ', 'مكتمل', 'ملغي') DEFAULT 'مجدول',
    priority ENUM('عالية', 'متوسطة', 'منخفضة') DEFAULT 'متوسطة',
    scheduled_date DATE,
    completion_date DATE,
    estimated_cost DECIMAL(12,2),
    actual_cost DECIMAL(12,2),
    assigned_team_id INT,
    created_by_user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_team_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- جدول جلسات المستخدمين
CREATE TABLE user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- جدول سجل النشاطات
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- جدول جداول جمع النفايات
CREATE TABLE IF NOT EXISTS waste_collection_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    area_name VARCHAR(200) NOT NULL,
    collection_day ENUM('الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'),
    collection_time TIME,
    waste_type ENUM('منزلية', 'تجارية', 'طبية', 'إنشائية', 'إلكترونية') DEFAULT 'منزلية',
    team_name VARCHAR(100),
    vehicle_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    frequency ENUM('يومي', 'أسبوعي', 'نصف شهري', 'شهري') DEFAULT 'أسبوعي',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
);

-- جدول تقارير النظافة
CREATE TABLE IF NOT EXISTS cleaning_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_date DATE NOT NULL,
    area_name VARCHAR(200),
    report_type ENUM('شكوى', 'طلب تنظيف', 'صيانة', 'أخرى'),
    description TEXT,
    priority ENUM('عادية', 'متوسطة', 'عاجلة', 'طارئة') DEFAULT 'عادية',
    status ENUM('مفتوح', 'قيد المعالجة', 'منجز', 'مؤجل') DEFAULT 'مفتوح',
    assigned_team VARCHAR(100),
    completion_date DATE,
    reported_by VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- جدول طلبات رخص البناء
CREATE TABLE IF NOT EXISTS building_permits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    permit_number VARCHAR(50) UNIQUE,
    applicant_name VARCHAR(100) NOT NULL,
    applicant_phone VARCHAR(20),
    applicant_email VARCHAR(100),
    property_address TEXT NOT NULL,
    property_area DECIMAL(10,2),
    building_type ENUM('سكني', 'تجاري', 'صناعي', 'مختلط') DEFAULT 'سكني',
    construction_type ENUM('بناء جديد', 'إضافة', 'تعديل', 'ترميم'),
    estimated_cost DECIMAL(12,2),
    building_area DECIMAL(10,2),
    floors_count INT DEFAULT 1,
    status ENUM('جديد', 'قيد المراجعة', 'معتمد', 'مرفوض', 'منتهي الصلاحية') DEFAULT 'جديد',
    submission_date DATE DEFAULT (CURRENT_DATE),
    approval_date DATE,
    expiry_date DATE,
    fees_amount DECIMAL(10,2),
    fees_paid BOOLEAN DEFAULT FALSE,
    notes TEXT,
    reviewed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

-- إدراج بيانات تجريبية
INSERT INTO users (username, password, full_name, email, user_type, department, position, salary, hire_date) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدير النظام', 'admin@tekrit.gov.iq', 'admin', 'تكنولوجيا المعلومات', 'مدير النظام', 2000000, '2024-01-01'),
('ahmed.khalil', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'أحمد خليل محمد', 'ahmed@tekrit.gov.iq', 'manager', 'الشؤون المالية', 'مدير مالي', 1500000, '2024-01-15'),
('sara.hassan', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'سارة حسن علي', 'sara@tekrit.gov.iq', 'employee', 'الموارد البشرية', 'موظف إداري', 1000000, '2024-02-01'),
('mohammed.ali', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'محمد علي أحمد', 'mohammed@tekrit.gov.iq', 'employee', 'الخدمات العامة', 'مشرف نظافة', 800000, '2024-02-15');

-- إدراج بيانات مالية تجريبية
INSERT INTO financial_transactions (transaction_date, type, category, description, amount, department, created_by, status) VALUES
('2025-01-01', 'إيراد', 'رسوم رخص البناء', 'رسوم رخصة بناء منزل سكني', 500000, 'رخص البناء', 1, 'معتمد'),
('2025-01-02', 'مصروف', 'رواتب الموظفين', 'رواتب شهر ديسمبر 2024', 5000000, 'الموارد البشرية', 1, 'معتمد'),
('2025-01-03', 'إيراد', 'رسوم خدمات', 'رسوم تنظيف الأسواق', 200000, 'الخدمات العامة', 1, 'معتمد'),
('2025-01-04', 'مصروف', 'صيانة آليات', 'صيانة شاحنة النفايات رقم 101', 300000, 'إدارة الآليات', 1, 'معتمد');

-- إدراج مشاريع تجريبية
INSERT INTO projects (project_name, description, project_type, location, start_date, end_date, budget, status, progress_percentage, manager_id) VALUES
('تطوير شارع الجمهورية', 'مشروع تطوير وإعادة تأهيل شارع الجمهورية الرئيسي', 'بنية تحتية', 'وسط المدينة', '2024-01-01', '2024-06-30', 50000000, 'قيد التنفيذ', 65, 2),
('حديقة الأطفال المركزية', 'إنشاء حديقة ترفيهية للأطفال مع ألعاب حديثة', 'خدمات عامة', 'المنطقة المركزية', '2024-03-01', '2024-08-31', 25000000, 'قيد التنفيذ', 40, 2),
('محطة معالجة المياه', 'إنشاء محطة جديدة لمعالجة مياه الصرف الصحي', 'بيئية', 'المنطقة الصناعية', '2024-06-01', '2024-12-31', 75000000, 'مخطط', 10, 2);

-- إدراج آليات تجريبية
INSERT INTO vehicles (vehicle_number, vehicle_type, brand, model, year_made, purchase_date, purchase_cost, fuel_type, department, driver_name, status) VALUES
('101', 'شاحنة نفايات', 'إيسوزو', 'NPR 70', 2022, '2022-05-15', 45000000, 'ديزل', 'إدارة النفايات', 'عبد الله محمد', 'جاهز'),
('102', 'سيارة خدمية', 'تويوتا', 'هايلوكس', 2023, '2023-03-10', 25000000, 'بنزين', 'الخدمات العامة', 'خالد أحمد', 'جاهز'),
('201', 'حفارة', 'كاتربيلر', '320D', 2021, '2021-08-20', 120000000, 'ديزل', 'المشاريع', 'سعد علي', 'قيد الصيانة'),
('301', 'جرافة', 'كوماتسو', 'D65PX', 2020, '2020-11-12', 150000000, 'ديزل', 'المشاريع', 'فيصل حسن', 'جاهز');

-- إدراج جداول النفايات التجريبية
INSERT INTO waste_collection_schedules (area_name, collection_day, collection_time, waste_type, team_name, vehicle_id, frequency) VALUES
('المنطقة المركزية', 'الأحد', '06:00:00', 'منزلية', 'فريق النظافة الأول', 1, 'أسبوعي'),
('الحي التجاري', 'الاثنين', '07:00:00', 'تجارية', 'فريق النظافة الثاني', 1, 'أسبوعي'),
('المنطقة السكنية الشمالية', 'الثلاثاء', '06:30:00', 'منزلية', 'فريق النظافة الأول', 1, 'أسبوعي'),
('المنطقة الصناعية', 'الأربعاء', '08:00:00', 'إنشائية', 'فريق النظافة الثالث', 1, 'نصف شهري');

-- إدراج وثائق تجريبية
INSERT INTO documents (document_title, description, category, department, access_level, uploaded_by, document_date) VALUES
('اللائحة المالية للبلدية 2024', 'اللائحة المالية المحدثة للعام 2024', 'لوائح مالية', 'الشؤون المالية', 'عام', 1, '2024-01-01'),
('دليل إجراءات رخص البناء', 'دليل شامل لإجراءات الحصول على رخص البناء', 'إجراءات', 'رخص البناء', 'عام', 1, '2024-01-15'),
('تقرير الميزانية الربعية', 'تقرير الميزانية للربع الأول من 2024', 'تقارير مالية', 'الشؤون المالية', 'محدود', 2, '2024-03-31'),
('خطة التطوير العمراني', 'الخطة الاستراتيجية لتطوير المدينة 2024-2030', 'خطط', 'التخطيط العمراني', 'سري', 1, '2024-01-01');

-- إدراج شكاوى تجريبية
INSERT INTO complaints (complaint_number, citizen_name, citizen_phone, complaint_type, subject, description, priority, status, department) VALUES
('C2024001', 'علي محمد حسن', '07701234567', 'نظافة', 'تراكم النفايات في الحي السكني', 'هناك تراكم للنفايات في شارع الجمهورية منذ أسبوع ولم يتم جمعها', 'عالية', 'قيد المعالجة', 'إدارة النفايات'),
('C2024002', 'فاطمة أحمد علي', '07709876543', 'صيانة', 'تلف في إنارة الشارع', 'إنارة شارع البساتين متوقفة منذ شهر', 'متوسطة', 'جديدة', 'الخدمات العامة'),
('C2024003', 'محمد سعد خليل', '07812345678', 'خدمات عامة', 'طلب تنظيف السوق المركزي', 'السوق المركزي يحتاج تنظيف شامل', 'عادية', 'مكتملة', 'الخدمات العامة');

-- إدراج طلبات رخص البناء التجريبية
INSERT INTO building_permits (permit_number, applicant_name, applicant_phone, property_address, property_area, building_type, construction_type, estimated_cost, building_area, floors_count, status, fees_amount) VALUES
('BP2024001', 'أحمد محمد علي', '07701111111', 'حي الجمهورية - قطعة 15', 200.5, 'سكني', 'بناء جديد', 80000000, 180.0, 2, 'معتمد', 2000000),
('BP2024002', 'سارة حسن محمد', '07702222222', 'المنطقة التجارية - قطعة 8', 150.0, 'تجاري', 'بناء جديد', 120000000, 120.0, 1, 'قيد المراجعة', 3000000),
('BP2024003', 'خالد عبد الله', '07703333333', 'حي البساتين - قطعة 22', 300.0, 'سكني', 'إضافة', 25000000, 50.0, 1, 'جديد', 800000);

-- إنشاء فهارس لتحسين الأداء
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_department ON users(department);
CREATE INDEX idx_financial_date ON financial_transactions(transaction_date);
CREATE INDEX idx_financial_type ON financial_transactions(type);
CREATE INDEX idx_projects_status ON projects(status);
CREATE INDEX idx_vehicles_status ON vehicles(status);
CREATE INDEX idx_documents_category ON documents(category);
CREATE INDEX idx_complaints_status ON complaints(status);
CREATE INDEX idx_permits_status ON building_permits(status); 