-- الجزء الثالث والأخير من ملف قاعدة البيانات الموحد
-- باقي الجداول والفهارس والبيانات التجريبية

USE tekrit_municipality;

-- ==========================================
-- الآليات والمعدات
-- ==========================================
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

-- ==========================================
-- صيانة الآليات
-- ==========================================
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

-- ==========================================
-- الإيرادات والمصروفات
-- ==========================================
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

-- ==========================================
-- الأرشيف الإلكتروني
-- ==========================================
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_title VARCHAR(500) NOT NULL,
    document_number VARCHAR(100) UNIQUE,
    category VARCHAR(100),
    subcategory VARCHAR(100),
    description TEXT,
    file_name VARCHAR(500),
    file_path VARCHAR(1000),
    file_size BIGINT,
    file_type VARCHAR(50),
    upload_date DATE DEFAULT (CURRENT_DATE),
    creation_date DATE,
    department VARCHAR(100),
    tags JSON,
    security_level ENUM('عام', 'محدود', 'سري', 'سري جداً') DEFAULT 'عام',
    access_permissions JSON,
    uploaded_by INT,
    approved_by INT,
    status ENUM('مسودة', 'معتمد', 'مؤرشف', 'محذوف') DEFAULT 'مسودة',
    version_number INT DEFAULT 1,
    parent_document_id INT,
    expiry_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (parent_document_id) REFERENCES documents(id)
);

-- ==========================================
-- جداول جمع النفايات
-- ==========================================
CREATE TABLE IF NOT EXISTS waste_collection_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    area_name VARCHAR(200) NOT NULL,
    collection_day ENUM('الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت') NOT NULL,
    collection_time TIME,
    vehicle_id INT,
    driver_name VARCHAR(100),
    route_description TEXT,
    frequency ENUM('يومي', 'يوم بعد يوم', 'أسبوعي', 'نصف شهري') DEFAULT 'أسبوعي',
    waste_type ENUM('منزلية', 'تجارية', 'صناعية', 'طبية', 'خطرة', 'مختلطة') DEFAULT 'منزلية',
    estimated_load DECIMAL(8,2),
    special_instructions TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    supervisor_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY (supervisor_id) REFERENCES users(id)
);

-- ==========================================
-- تقارير النظافة
-- ==========================================
CREATE TABLE IF NOT EXISTS cleaning_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_date DATE NOT NULL,
    area_name VARCHAR(200) NOT NULL,
    team_leader VARCHAR(100),
    team_size INT DEFAULT 1,
    work_hours DECIMAL(4,2),
    waste_collected DECIMAL(8,2),
    equipment_used TEXT,
    issues_encountered TEXT,
    completion_status ENUM('مكتمل', 'جزئي', 'لم يبدأ', 'معطل') DEFAULT 'مكتمل',
    weather_conditions VARCHAR(100),
    citizen_feedback TEXT,
    photos_attachments JSON,
    supervisor_notes TEXT,
    submitted_by INT,
    approved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (submitted_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- ==========================================
-- رخص البناء
-- ==========================================
CREATE TABLE IF NOT EXISTS building_permits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    permit_number VARCHAR(50) UNIQUE NOT NULL,
    applicant_name VARCHAR(200) NOT NULL,
    applicant_phone VARCHAR(20),
    applicant_email VARCHAR(100),
    applicant_address TEXT,
    citizen_id INT,
    property_location TEXT NOT NULL,
    property_area DECIMAL(10,2),
    building_type ENUM('سكني', 'تجاري', 'صناعي', 'خدمي', 'مختلط') DEFAULT 'سكني',
    construction_type ENUM('جديد', 'توسع', 'تجديد', 'هدم وإعادة بناء') DEFAULT 'جديد',
    floors_count INT DEFAULT 1,
    estimated_cost DECIMAL(12,2),
    currency_id INT DEFAULT 1,
    contractor_name VARCHAR(200),
    contractor_license VARCHAR(100),
    engineer_name VARCHAR(200),
    engineer_license VARCHAR(100),
    architectural_plans_path VARCHAR(500),
    structural_plans_path VARCHAR(500),
    application_date DATE DEFAULT (CURRENT_DATE),
    review_date DATE,
    approval_date DATE,
    expiry_date DATE,
    permit_fees DECIMAL(10,2),
    status ENUM('مقدم', 'قيد المراجعة', 'يحتاج تعديل', 'موافق عليه', 'مرفوض', 'منتهي الصلاحية') DEFAULT 'مقدم',
    review_notes TEXT,
    approval_conditions TEXT,
    rejection_reason TEXT,
    reviewed_by INT,
    approved_by INT,
    issued_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (citizen_id) REFERENCES citizens(id),
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (issued_by) REFERENCES users(id)
);

-- ==========================================
-- سجل النشاطات
-- ==========================================
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    table_name VARCHAR(100),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ==========================================
-- إنشاء الفهارس لتحسين الأداء
-- ==========================================

-- فهارس المستخدمين
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_department ON users(department);
CREATE INDEX IF NOT EXISTS idx_users_user_type ON users(user_type);

-- فهارس العملات
CREATE INDEX IF NOT EXISTS idx_currencies_code ON currencies(currency_code);
CREATE INDEX IF NOT EXISTS idx_currencies_active ON currencies(is_active);

-- فهارس المواطنين
CREATE INDEX IF NOT EXISTS idx_citizens_citizen_number ON citizens(citizen_number);
CREATE INDEX IF NOT EXISTS idx_citizens_full_name ON citizens(full_name);
CREATE INDEX IF NOT EXISTS idx_citizens_area ON citizens(area);
CREATE INDEX IF NOT EXISTS idx_citizens_verification ON citizens(verification_status);

-- فهارس أنواع الجباية
CREATE INDEX IF NOT EXISTS idx_tax_types_category ON tax_types(category);
CREATE INDEX IF NOT EXISTS idx_tax_types_code ON tax_types(tax_code);
CREATE INDEX IF NOT EXISTS idx_tax_types_active ON tax_types(is_active);

-- فهارس عمليات الجباية
CREATE INDEX IF NOT EXISTS idx_tax_collections_citizen ON tax_collections(citizen_id);
CREATE INDEX IF NOT EXISTS idx_tax_collections_status ON tax_collections(payment_status);
CREATE INDEX IF NOT EXISTS idx_tax_collections_date ON tax_collections(issue_date);

-- فهارس الجهات المانحة
CREATE INDEX IF NOT EXISTS idx_donor_organizations_type ON donor_organizations(donor_type);
CREATE INDEX IF NOT EXISTS idx_donor_organizations_status ON donor_organizations(status);
CREATE INDEX IF NOT EXISTS idx_donor_organizations_code ON donor_organizations(donor_code);

-- فهارس المشاريع
CREATE INDEX IF NOT EXISTS idx_projects_status ON projects(status);
CREATE INDEX IF NOT EXISTS idx_projects_donor_type ON projects(donor_type);
CREATE INDEX IF NOT EXISTS idx_projects_manager ON projects(manager_id);

-- فهارس مراحل المشاريع
CREATE INDEX IF NOT EXISTS idx_project_phases_project ON project_phases(project_id);
CREATE INDEX IF NOT EXISTS idx_project_phases_status ON project_phases(status);

-- فهارس التبرعات
CREATE INDEX IF NOT EXISTS idx_donations_type ON donations(donation_type);
CREATE INDEX IF NOT EXISTS idx_donations_status ON donations(status);
CREATE INDEX IF NOT EXISTS idx_donations_donor_type ON donations(donor_type);
CREATE INDEX IF NOT EXISTS idx_donations_date ON donations(received_date);

-- فهارس المعاملات المالية
CREATE INDEX IF NOT EXISTS idx_financial_date ON financial_transactions(transaction_date);
CREATE INDEX IF NOT EXISTS idx_financial_type ON financial_transactions(type);
CREATE INDEX IF NOT EXISTS idx_financial_currency ON financial_transactions(currency_id);
CREATE INDEX IF NOT EXISTS idx_financial_status ON financial_transactions(status);

-- فهارس الآليات
CREATE INDEX IF NOT EXISTS idx_vehicles_status ON vehicles(status);
CREATE INDEX IF NOT EXISTS idx_vehicles_department ON vehicles(department);
CREATE INDEX IF NOT EXISTS idx_vehicles_number ON vehicles(vehicle_number);

-- فهارس الوثائق
CREATE INDEX IF NOT EXISTS idx_documents_category ON documents(category);
CREATE INDEX IF NOT EXISTS idx_documents_status ON documents(status);
CREATE INDEX IF NOT EXISTS idx_documents_date ON documents(upload_date);

-- فهارس الشكاوى
CREATE INDEX IF NOT EXISTS idx_complaints_status ON complaints(status);
CREATE INDEX IF NOT EXISTS idx_complaints_type ON complaints(complaint_type);
CREATE INDEX IF NOT EXISTS idx_complaints_date ON complaints(created_at);

-- فهارس رخص البناء
CREATE INDEX IF NOT EXISTS idx_permits_status ON building_permits(status);
CREATE INDEX IF NOT EXISTS idx_permits_date ON building_permits(application_date);
CREATE INDEX IF NOT EXISTS idx_permits_citizen ON building_permits(citizen_id);

-- ==========================================
-- إدراج البيانات التجريبية
-- ==========================================

-- بيانات تجريبية لأنواع الجباية
INSERT IGNORE INTO tax_types (tax_code, tax_name, category, description, base_amount, currency_id, created_by_user_id) VALUES
('RES001', 'رسوم رخصة بناء', 'تراخيص', 'رسوم استخراج رخصة بناء للمنازل السكنية', 50000, 1, 1),
('RES002', 'رسوم النظافة', 'رسوم خدمات', 'رسوم خدمات النظافة الشهرية', 25000, 1, 1),
('TAX001', 'ضريبة المحلات التجارية', 'ضرائب', 'ضريبة سنوية على المحلات التجارية', 100000, 1, 1),
('FEE001', 'رسوم إشغال طريق', 'إشغالات', 'رسوم إشغال الطريق العام للأنشطة التجارية', 15000, 1, 1),
('FIN001', 'غرامة مخالفة بناء', 'غرامات', 'غرامة مالية للبناء بدون ترخيص', 200000, 1, 1);

-- بيانات تجريبية للمواطنين
INSERT IGNORE INTO citizens (citizen_number, full_name, gender, district, area, phone, verification_status) VALUES
('12345678901', 'أحمد محمد علي الجبوري', 'ذكر', 'صلاح الدين', 'تكريت', '07701234567', 'مؤكد'),
('12345678902', 'فاطمة خالد حسن التكريتي', 'أنثى', 'صلاح الدين', 'تكريت', '07709876543', 'مؤكد'),
('12345678903', 'عبد الله سعد جبار المهداوي', 'ذكر', 'صلاح الدين', 'تكريت', '07712345678', 'غير مؤكد'),
('12345678904', 'زينب عباس حميد العبيدي', 'أنثى', 'صلاح الدين', 'تكريت', '07798765432', 'مؤكد'),
('12345678905', 'حسام الدين طارق نوري الدليمي', 'ذكر', 'صلاح الدين', 'تكريت', '07787654321', 'قيد المراجعة');

-- بيانات تجريبية للجهات المانحة
INSERT IGNORE INTO donor_organizations (donor_code, organization_name, donor_type, country, city, status, created_by_user_id) VALUES
('UNDP001', 'برنامج الأمم المتحدة الإنمائي', 'منظمة دولية', 'العراق', 'بغداد', 'نشط', 1),
('USAID001', 'الوكالة الأمريكية للتنمية الدولية', 'منظمة دولية', 'الولايات المتحدة', 'بغداد', 'نشط', 1),
('MOF001', 'وزارة المالية العراقية', 'حكومي فيدرالي', 'العراق', 'بغداد', 'نشط', 1),
('EU001', 'الاتحاد الأوروبي', 'منظمة دولية', 'بلجيكا', 'بروكسل', 'نشط', 1),
('REDCR001', 'منظمة الهلال الأحمر العراقي', 'منظمة خيرية محلية', 'العراق', 'بغداد', 'نشط', 1);

-- بيانات تجريبية للتبرعات
INSERT IGNORE INTO donations (donation_number, donor_name, donor_type, donor_phone, donation_type, amount, currency_id, purpose, status, received_by_user_id, received_date) VALUES
('DON2025001', 'شركة النور للتجارة', 'شركة', '07701234567', 'نقدي', 5000000, 1, 'دعم مشاريع البنية التحتية', 'موافق عليه', 1, '2025-01-01'),
('DON2025002', 'أحمد محمد علي', 'فرد', '07709876543', 'عيني', 0, 1, 'تبرع بمعدات طبية للمركز الصحي', 'مستلم', 1, '2025-01-02'),
('DON2025003', 'منظمة الهلال الأحمر', 'منظمة', '07812345678', 'نقدي', 10000000, 1, 'دعم برامج الإغاثة والطوارئ', 'قيد التوزيع', 1, '2025-01-03'),
('DON2025004', 'شركة البناء المتطورة', 'شركة', '07723456789', 'خدمي', 0, 1, 'خدمات هندسية مجانية لمشروع المدرسة', 'موافق عليه', 1, '2025-01-04'),
('DON2025005', 'جامعة تكريت', 'مؤسسة تعليمية', '07734567890', 'عيني', 0, 1, 'تبرع بأجهزة حاسوب للمكتبة العامة', 'مستلم', 1, '2025-01-05');

-- بيانات تجريبية للمتبرعين
INSERT IGNORE INTO donors (donor_code, name, type, phone, email, status, total_donations) VALUES
('DNR001', 'شركة النور للتجارة', 'شركة', '07701234567', 'info@noortrading.com', 'نشط', 5000000),
('DNR002', 'أحمد محمد علي', 'فرد', '07709876543', 'ahmed.mohammed@gmail.com', 'نشط', 2000000),
('DNR003', 'منظمة الهلال الأحمر', 'منظمة', '07812345678', 'contact@redcrescent.org', 'نشط', 10000000),
('DNR004', 'شركة البناء المتطورة', 'شركة', '07723456789', 'info@advancedbuild.com', 'نشط', 3000000),
('DNR005', 'جامعة تكريت', 'مؤسسة تعليمية', '07734567890', 'contact@tu.edu.iq', 'نشط', 1500000);

COMMIT;

-- انتهاء ملف قاعدة البيانات الموحد
-- هذا الملف يحتوي على جميع الجداول والفهارس والبيانات من الملفات الأربعة الأصلية 