-- ملف قاعدة البيانات الموحد لنظام إدارة بلدية تكريت
-- يجمع جميع الجداول والبيانات من الملفات الأربعة
-- تاريخ الإنشاء: 2025-01-26

-- إنشاء قاعدة بيانات بلدية تكريت
CREATE DATABASE IF NOT EXISTS tekrit_municipality CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tekrit_municipality;

-- ==========================================
-- جدول أنواع العملات
-- ==========================================
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

-- ==========================================
-- جدول المستخدمين (الموظفين والإداريين)
-- ==========================================
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

-- ==========================================
-- جدول الجهات المانحة المفصل
-- ==========================================
CREATE TABLE IF NOT EXISTS donor_organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donor_code VARCHAR(50) UNIQUE NOT NULL,
    organization_name VARCHAR(300) NOT NULL,
    organization_name_en VARCHAR(300),
    donor_type ENUM('حكومي محلي', 'حكومي فيدرالي', 'منظمة دولية', 'منظمة خيرية دولية', 'منظمة خيرية محلية', 'شركة محلية', 'شركة دولية', 'بنك', 'سفارة', 'أفراد', 'جمعية', 'اتحاد', 'مؤسسة تعليمية', 'مؤسسة طبية', 'أخرى') NOT NULL,
    
    -- معلومات الاتصال
    country VARCHAR(100),
    city VARCHAR(100),
    address TEXT,
    postal_code VARCHAR(20),
    phone VARCHAR(50),
    fax VARCHAR(50),
    email VARCHAR(150),
    website VARCHAR(200),
    
    -- معلومات الاتصال الرئيسية
    contact_person_name VARCHAR(150),
    contact_person_title VARCHAR(100),
    contact_person_phone VARCHAR(50),
    contact_person_email VARCHAR(150),
    
    -- معلومات قانونية
    registration_number VARCHAR(100),
    tax_number VARCHAR(100),
    license_number VARCHAR(100),
    establishment_date DATE,
    
    -- تفاصيل التمويل
    specialization JSON, -- المجالات التي تدعمها
    funding_areas JSON, -- المناطق الجغرافية التي تدعمها
    average_donation_amount DECIMAL(15,2),
    maximum_donation_amount DECIMAL(15,2),
    preferred_currency_id INT DEFAULT 1,
    
    -- إحصائيات
    total_donations_count INT DEFAULT 0,
    total_donations_amount DECIMAL(20,2) DEFAULT 0,
    total_donations_iqd DECIMAL(20,2) DEFAULT 0,
    first_donation_date DATE,
    last_donation_date DATE,
    
    -- تقييم ومعلومات إضافية
    reliability_rating ENUM('ممتاز', 'جيد جداً', 'جيد', 'مقبول', 'ضعيف') DEFAULT 'جيد',
    partnership_level ENUM('استراتيجي', 'مستمر', 'مؤقت', 'لمرة واحدة') DEFAULT 'مؤقت',
    documentation_quality ENUM('ممتاز', 'جيد', 'مقبول', 'ضعيف') DEFAULT 'مقبول',
    
    -- حالة وملاحظات
    status ENUM('نشط', 'غير نشط', 'معلق', 'محظور', 'منتهي الشراكة') DEFAULT 'نشط',
    notes TEXT,
    internal_notes TEXT, -- ملاحظات داخلية لا تظهر للجهة المانحة
    
    -- مراجع
    created_by_user_id INT,
    updated_by_user_id INT,
    last_contact_date DATE,
    next_contact_date DATE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (preferred_currency_id) REFERENCES currencies(id),
    FOREIGN KEY (created_by_user_id) REFERENCES users(id),
    FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
);

-- ==========================================
-- جدول المواطنين
-- ==========================================
CREATE TABLE IF NOT EXISTS citizens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    citizen_number VARCHAR(50) UNIQUE, -- رقم البطاقة الموحدة
    full_name VARCHAR(200) NOT NULL,
    father_name VARCHAR(100),
    grandfather_name VARCHAR(100),
    surname VARCHAR(100),
    mother_name VARCHAR(150),
    
    -- معلومات شخصية
    birth_date DATE,
    birth_place VARCHAR(150),
    gender ENUM('ذكر', 'أنثى') NOT NULL,
    marital_status ENUM('أعزب', 'متزوج', 'مطلق', 'أرمل') DEFAULT 'أعزب',
    nationality VARCHAR(50) DEFAULT 'عراقي',
    religion VARCHAR(50),
    
    -- معلومات السكن
    district VARCHAR(100), -- المحافظة
    area VARCHAR(100), -- المنطقة
    neighborhood VARCHAR(100), -- الحي
    street VARCHAR(200), -- الشارع
    house_number VARCHAR(50), -- رقم الدار
    building_type ENUM('بيت', 'شقة', 'فيلا', 'أخرى') DEFAULT 'بيت',
    
    -- معلومات الاتصال
    phone VARCHAR(50),
    mobile VARCHAR(50),
    email VARCHAR(150),
    
    -- معلومات مهنية
    profession VARCHAR(150),
    workplace VARCHAR(200),
    monthly_income DECIMAL(12,2),
    
    -- حالة المواطن
    residence_status ENUM('مقيم دائم', 'مقيم مؤقت', 'نازح', 'لاجئ') DEFAULT 'مقيم دائم',
    social_status ENUM('عادي', 'متقاعد', 'معاق', 'أرملة', 'يتيم', 'عاطل', 'طالب') DEFAULT 'عادي',
    
    -- معلومات العائلة
    family_members_count INT DEFAULT 1,
    dependents_count INT DEFAULT 0,
    
    -- ملاحظات وحالات خاصة
    special_needs TEXT,
    notes TEXT,
    
    -- حالة السجل
    is_active BOOLEAN DEFAULT TRUE,
    verification_status ENUM('مؤكد', 'غير مؤكد', 'قيد المراجعة') DEFAULT 'غير مؤكد',
    
    -- تواريخ
    registration_date DATE DEFAULT (CURRENT_DATE),
    last_update_date DATE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ==========================================
-- جدول أنواع الجباية
-- ==========================================
CREATE TABLE IF NOT EXISTS tax_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tax_code VARCHAR(50) UNIQUE NOT NULL,
    tax_name VARCHAR(200) NOT NULL,
    tax_name_en VARCHAR(200),
    category ENUM('رسوم خدمات', 'ضرائب', 'غرامات', 'تراخيص', 'إشغالات', 'أخرى') NOT NULL,
    
    -- تفاصيل الجباية
    description TEXT,
    calculation_method ENUM('مبلغ ثابت', 'نسبة مئوية', 'حسب المساحة', 'حسب القيمة', 'حسب المدة', 'معقد') DEFAULT 'مبلغ ثابت',
    base_amount DECIMAL(12,2), -- المبلغ الأساسي
    percentage_rate DECIMAL(5,2), -- النسبة المئوية
    currency_id INT DEFAULT 1,
    
    -- فترات الدفع
    payment_frequency ENUM('مرة واحدة', 'سنوي', 'نصف سنوي', 'ربع سنوي', 'شهري', 'أسبوعي', 'يومي') DEFAULT 'مرة واحدة',
    due_period_days INT DEFAULT 30, -- فترة الاستحقاق بالأيام
    
    -- معايير التطبيق
    applies_to JSON, -- على من تطبق (مواطنين، شركات، زوار، الخ)
    minimum_amount DECIMAL(12,2),
    maximum_amount DECIMAL(12,2),
    
    -- خصومات وإعفاءات
    discount_available BOOLEAN DEFAULT FALSE,
    discount_percentage DECIMAL(5,2),
    exemption_criteria VARCHAR(500),
    
    -- حالة ووقت
    is_active BOOLEAN DEFAULT TRUE,
    effective_date DATE,
    expiry_date DATE,
    
    -- معلومات قانونية
    legal_basis TEXT, -- الأساس القانوني
    approval_number VARCHAR(100),
    approval_date DATE,
    
    notes TEXT,
    created_by_user_id INT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (created_by_user_id) REFERENCES users(id)
);

-- ملف سيتم متابعته في الجزء الثاني... 