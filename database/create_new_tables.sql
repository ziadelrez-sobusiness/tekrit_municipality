-- إنشاء الجداول الجديدة لنظام بلدية تكريت

USE tekrit_municipality;

-- جدول العملات إذا لم يكن موجوداً
CREATE TABLE IF NOT EXISTS currencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    currency_code VARCHAR(10) UNIQUE NOT NULL,
    currency_name VARCHAR(100) NOT NULL,
    currency_symbol VARCHAR(10) NOT NULL,
    exchange_rate_to_iqd DECIMAL(10,4) DEFAULT 1.0000,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- إدراج العملات الأساسية
INSERT IGNORE INTO currencies (currency_code, currency_name, currency_symbol, exchange_rate_to_iqd) VALUES
('IQD', 'الدينار العراقي', 'د.ع', 1.0000),
('USD', 'الدولار الأمريكي', '$', 1310.0000),
('EUR', 'اليورو', '€', 1420.0000);

-- جدول الجهات المانحة
CREATE TABLE IF NOT EXISTS donor_organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donor_code VARCHAR(50) UNIQUE NOT NULL,
    organization_name VARCHAR(300) NOT NULL,
    organization_name_en VARCHAR(300),
    donor_type ENUM('حكومي محلي', 'حكومي فيدرالي', 'منظمة دولية', 'منظمة خيرية دولية', 'منظمة خيرية محلية', 'شركة محلية', 'شركة دولية', 'بنك', 'سفارة', 'أفراد', 'جمعية', 'اتحاد', 'مؤسسة تعليمية', 'مؤسسة طبية', 'أخرى') NOT NULL,
    country VARCHAR(100),
    city VARCHAR(100),
    address TEXT,
    phone VARCHAR(50),
    email VARCHAR(150),
    website VARCHAR(200),
    contact_person_name VARCHAR(150),
    contact_person_title VARCHAR(100),
    contact_person_phone VARCHAR(50),
    contact_person_email VARCHAR(150),
    specialization JSON,
    preferred_currency_id INT DEFAULT 1,
    total_donations_count INT DEFAULT 0,
    total_donations_amount DECIMAL(20,2) DEFAULT 0,
    total_donations_iqd DECIMAL(20,2) DEFAULT 0,
    reliability_rating ENUM('ممتاز', 'جيد جداً', 'جيد', 'مقبول', 'ضعيف') DEFAULT 'جيد',
    partnership_level ENUM('استراتيجي', 'مستمر', 'مؤقت', 'لمرة واحدة') DEFAULT 'مؤقت',
    status ENUM('نشط', 'غير نشط', 'معلق', 'محظور', 'منتهي الشراكة') DEFAULT 'نشط',
    notes TEXT,
    created_by_user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (preferred_currency_id) REFERENCES currencies(id),
    FOREIGN KEY (created_by_user_id) REFERENCES users(id)
);

-- جدول المواطنين
CREATE TABLE IF NOT EXISTS citizens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    citizen_number VARCHAR(50) UNIQUE,
    full_name VARCHAR(200) NOT NULL,
    father_name VARCHAR(100),
    grandfather_name VARCHAR(100),
    surname VARCHAR(100),
    mother_name VARCHAR(150),
    birth_date DATE,
    birth_place VARCHAR(150),
    gender ENUM('ذكر', 'أنثى') NOT NULL,
    marital_status ENUM('أعزب', 'متزوج', 'مطلق', 'أرمل') DEFAULT 'أعزب',
    nationality VARCHAR(50) DEFAULT 'عراقي',
    religion VARCHAR(50),
    district VARCHAR(100),
    area VARCHAR(100),
    neighborhood VARCHAR(100),
    street VARCHAR(200),
    house_number VARCHAR(50),
    building_type ENUM('بيت', 'شقة', 'فيلا', 'أخرى') DEFAULT 'بيت',
    phone VARCHAR(50),
    mobile VARCHAR(50),
    email VARCHAR(150),
    profession VARCHAR(150),
    workplace VARCHAR(200),
    monthly_income DECIMAL(12,2),
    residence_status ENUM('مقيم دائم', 'مقيم مؤقت', 'نازح', 'لاجئ') DEFAULT 'مقيم دائم',
    social_status ENUM('عادي', 'متقاعد', 'معاق', 'أرملة', 'يتيم', 'عاطل', 'طالب') DEFAULT 'عادي',
    family_members_count INT DEFAULT 1,
    dependents_count INT DEFAULT 0,
    special_needs TEXT,
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    verification_status ENUM('مؤكد', 'غير مؤكد', 'قيد المراجعة') DEFAULT 'غير مؤكد',
    registration_date DATE DEFAULT (CURRENT_DATE),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- جدول أنواع الجباية
CREATE TABLE IF NOT EXISTS tax_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tax_code VARCHAR(50) UNIQUE NOT NULL,
    tax_name VARCHAR(200) NOT NULL,
    tax_name_en VARCHAR(200),
    category ENUM('رسوم خدمات', 'ضرائب', 'غرامات', 'تراخيص', 'إشغالات', 'أخرى') NOT NULL,
    description TEXT,
    calculation_method ENUM('مبلغ ثابت', 'نسبة مئوية', 'حسب المساحة', 'حسب القيمة', 'حسب المدة', 'معقد') DEFAULT 'مبلغ ثابت',
    base_amount DECIMAL(12,2),
    percentage_rate DECIMAL(5,2),
    currency_id INT DEFAULT 1,
    payment_frequency ENUM('مرة واحدة', 'سنوي', 'نصف سنوي', 'ربع سنوي', 'شهري', 'أسبوعي', 'يومي') DEFAULT 'مرة واحدة',
    due_period_days INT DEFAULT 30,
    applies_to JSON,
    minimum_amount DECIMAL(12,2),
    maximum_amount DECIMAL(12,2),
    discount_available BOOLEAN DEFAULT FALSE,
    discount_percentage DECIMAL(5,2),
    exemption_criteria VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    effective_date DATE,
    expiry_date DATE,
    legal_basis TEXT,
    approval_number VARCHAR(100),
    approval_date DATE,
    notes TEXT,
    created_by_user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (created_by_user_id) REFERENCES users(id)
);

-- جدول عمليات الجباية
CREATE TABLE IF NOT EXISTS tax_collections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    collection_number VARCHAR(50) UNIQUE,
    citizen_id INT NOT NULL,
    tax_type_id INT NOT NULL,
    base_amount DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    penalty_amount DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL,
    currency_id INT DEFAULT 1,
    exchange_rate DECIMAL(10,4) DEFAULT 1.0000,
    amount_in_iqd DECIMAL(12,2) GENERATED ALWAYS AS (total_amount * exchange_rate) STORED,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    payment_date DATE,
    payment_status ENUM('مستحق', 'مدفوع جزئياً', 'مدفوع كاملاً', 'متأخر', 'معفى', 'ملغي') DEFAULT 'مستحق',
    paid_amount DECIMAL(12,2) DEFAULT 0,
    remaining_amount DECIMAL(12,2) GENERATED ALWAYS AS (total_amount - paid_amount) STORED,
    payment_method ENUM('نقد', 'شيك', 'تحويل مصرفي', 'بطاقة ائتمان', 'أخرى'),
    reference_number VARCHAR(100),
    receipt_number VARCHAR(100),
    service_description TEXT,
    location_details TEXT,
    period_from DATE,
    period_to DATE,
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

-- إضافة بعض البيانات التجريبية
INSERT IGNORE INTO tax_types (tax_code, tax_name, category, description, base_amount, currency_id, created_by_user_id) VALUES
('RES001', 'رسوم رخصة بناء', 'تراخيص', 'رسوم استخراج رخصة بناء للمنازل السكنية', 50000, 1, 1),
('RES002', 'رسوم النظافة', 'رسوم خدمات', 'رسوم خدمات النظافة الشهرية', 25000, 1, 1),
('TAX001', 'ضريبة المحلات التجارية', 'ضرائب', 'ضريبة سنوية على المحلات التجارية', 100000, 1, 1);

INSERT IGNORE INTO citizens (citizen_number, full_name, gender, district, area) VALUES
('12345678901', 'أحمد محمد علي الجبوري', 'ذكر', 'صلاح الدين', 'تكريت'),
('12345678902', 'فاطمة خالد حسن التكريتي', 'أنثى', 'صلاح الدين', 'تكريت'),
('12345678903', 'عبد الله سعد جبار المهداوي', 'ذكر', 'صلاح الدين', 'تكريت');

INSERT IGNORE INTO donor_organizations (donor_code, organization_name, donor_type, country, city, status, created_by_user_id) VALUES
('UNDP001', 'برنامج الأمم المتحدة الإنمائي', 'منظمة دولية', 'العراق', 'بغداد', 'نشط', 1),
('USAID001', 'الوكالة الأمريكية للتنمية الدولية', 'منظمة دولية', 'الولايات المتحدة', 'بغداد', 'نشط', 1),
('MOF001', 'وزارة المالية العراقية', 'حكومي فيدرالي', 'العراق', 'بغداد', 'نشط', 1);

COMMIT; 