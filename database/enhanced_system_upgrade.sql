-- ترقية شاملة لنظام بلدية تكريت
-- إضافة وحدات الجهات المانحة والمواطنين والجباية

USE tekrit_municipality;

-- جدول الجهات المانحة المفصل
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

-- جدول المواطنين
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

-- جدول أنواع الجباية
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
    exemption_criteria JSON, -- معايير الإعفاء
    
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

-- جدول عمليات الجباية
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
    approved_by_user_id INT,
    
    notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (citizen_id) REFERENCES citizens(id),
    FOREIGN KEY (tax_type_id) REFERENCES tax_types(id),
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (issued_by_user_id) REFERENCES users(id),
    FOREIGN KEY (collected_by_user_id) REFERENCES users(id),
    FOREIGN KEY (approved_by_user_id) REFERENCES users(id)
);

-- جدول مراحل تنفيذ المشاريع
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
    
    -- التكاليف
    planned_cost DECIMAL(15,2),
    actual_cost DECIMAL(15,2) DEFAULT 0,
    currency_id INT DEFAULT 1,
    
    -- الحالة والتقدم
    status ENUM('لم تبدأ', 'قيد التنفيذ', 'مكتملة', 'متأخرة', 'معلقة', 'ملغية') DEFAULT 'لم تبدأ',
    progress_percentage INT DEFAULT 0,
    
    -- الجهات المنفذة والمستفيدة
    executing_entity VARCHAR(200), -- الجهة المنفذة
    beneficiaries TEXT, -- المستفيدون
    target_beneficiaries_count INT, -- عدد المستفيدين المستهدف
    actual_beneficiaries_count INT DEFAULT 0, -- عدد المستفيدين الفعلي
    
    -- الحاجة والمبررات
    necessity_description TEXT, -- وصف الحاجة للمشروع
    expected_impact TEXT, -- التأثير المتوقع
    success_metrics TEXT, -- مؤشرات النجاح
    
    -- المخاطر والتحديات
    risks_identified TEXT,
    challenges_faced TEXT,
    mitigation_measures TEXT,
    
    -- الموافقات والمراجعات
    approved_by_user_id INT,
    reviewed_by_user_id INT,
    
    notes TEXT,
    attachments JSON,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (approved_by_user_id) REFERENCES users(id),
    FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id),
    
    UNIQUE KEY unique_project_phase (project_id, phase_number)
);

-- تحديث جدول المشاريع لإضافة حقول جديدة
ALTER TABLE projects 
ADD COLUMN IF NOT EXISTS executing_entity VARCHAR(200),
ADD COLUMN IF NOT EXISTS target_beneficiaries_count INT,
ADD COLUMN IF NOT EXISTS actual_beneficiaries_count INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS necessity_description TEXT,
ADD COLUMN IF NOT EXISTS expected_impact TEXT,
ADD COLUMN IF NOT EXISTS project_category ENUM('بنية تحتية', 'خدمات اجتماعية', 'تطوير اقتصادي', 'بيئة', 'تعليم', 'صحة', 'ثقافة', 'رياضة', 'أمان', 'تكنولوجيا', 'أخرى') DEFAULT 'بنية تحتية';

-- إضافة بعض البيانات التجريبية

-- إضافة أنواع جباية تجريبية
INSERT IGNORE INTO tax_types (tax_code, tax_name, category, description, calculation_method, base_amount, currency_id, payment_frequency, is_active, created_by_user_id) VALUES
('RES001', 'رسوم رخصة بناء', 'تراخيص', 'رسوم استخراج رخصة بناء للمنازل السكنية', 'حسب المساحة', 50000, 1, 'مرة واحدة', TRUE, 1),
('RES002', 'رسوم النظافة', 'رسوم خدمات', 'رسوم خدمات النظافة الشهرية', 'مبلغ ثابت', 25000, 1, 'شهري', TRUE, 1),
('TAX001', 'ضريبة المحلات التجارية', 'ضرائب', 'ضريبة سنوية على المحلات التجارية', 'حسب المساحة', 100000, 1, 'سنوي', TRUE, 1),
('FIN001', 'غرامة تأخير رخصة البناء', 'غرامات', 'غرامة على تأخير تجديد رخصة البناء', 'مبلغ ثابت', 150000, 1, 'مرة واحدة', TRUE, 1),
('OCC001', 'رسوم إشغال الطريق العام', 'إشغالات', 'رسوم استخدام الطريق العام للأعمال', 'حسب المدة', 10000, 1, 'يومي', TRUE, 1);

-- إضافة مواطنين تجريبيين
INSERT IGNORE INTO citizens (citizen_number, full_name, father_name, grandfather_name, surname, birth_date, gender, district, area, neighborhood, phone, profession, residence_status) VALUES
('12345678901', 'أحمد محمد علي الجبوري', 'محمد', 'علي', 'الجبوري', '1985-03-15', 'ذكر', 'صلاح الدين', 'تكريت', 'المركز', '07701234567', 'مهندس', 'مقيم دائم'),
('12345678902', 'فاطمة خالد حسن التكريتي', 'خالد', 'حسن', 'التكريتي', '1990-08-22', 'أنثى', 'صلاح الدين', 'تكريت', 'الضباط', '07709876543', 'طبيبة', 'مقيم دائم'),
('12345678903', 'عبد الله سعد جبار المهداوي', 'سعد', 'جبار', 'المهداوي', '1978-12-10', 'ذكر', 'صلاح الدين', 'تكريت', 'القادسية', '07812345678', 'تاجر', 'مقيم دائم');

-- إضافة جهات مانحة تجريبية
INSERT IGNORE INTO donor_organizations (donor_code, organization_name, donor_type, country, city, phone, email, contact_person_name, contact_person_email, specialization, status, created_by_user_id) VALUES
('UNDP001', 'برنامج الأمم المتحدة الإنمائي', 'منظمة دولية', 'العراق', 'بغداد', '+964-1-7901234', 'iraq@undp.org', 'جون سميث', 'john.smith@undp.org', '["التنمية المستدامة", "البنية التحتية", "التعليم"]', 'نشط', 1),
('USAID001', 'الوكالة الأمريكية للتنمية الدولية', 'منظمة دولية', 'الولايات المتحدة', 'بغداد', '+964-1-7901235', 'baghdad@usaid.gov', 'ماري جونسون', 'mary.johnson@usaid.gov', '["الديمقراطية", "التنمية الاقتصادية", "الصحة"]', 'نشط', 1),
('MOF001', 'وزارة المالية العراقية', 'حكومي فيدرالي', 'العراق', 'بغداد', '+964-1-5371000', 'info@mof.gov.iq', 'أحمد الوزير', 'ahmed.minister@mof.gov.iq', '["الميزانية العامة", "المشاريع الحكومية"]', 'نشط', 1);

-- إضافة فهارس للأداء
CREATE INDEX IF NOT EXISTS idx_citizens_citizen_number ON citizens(citizen_number);
CREATE INDEX IF NOT EXISTS idx_citizens_full_name ON citizens(full_name);
CREATE INDEX IF NOT EXISTS idx_citizens_area ON citizens(area);
CREATE INDEX IF NOT EXISTS idx_tax_types_category ON tax_types(category);
CREATE INDEX IF NOT EXISTS idx_tax_collections_citizen ON tax_collections(citizen_id);
CREATE INDEX IF NOT EXISTS idx_tax_collections_status ON tax_collections(payment_status);
CREATE INDEX IF NOT EXISTS idx_donor_organizations_type ON donor_organizations(donor_type);
CREATE INDEX IF NOT EXISTS idx_donor_organizations_status ON donor_organizations(status);
CREATE INDEX IF NOT EXISTS idx_project_phases_project ON project_phases(project_id);
CREATE INDEX IF NOT EXISTS idx_project_phases_status ON project_phases(status);

COMMIT; 