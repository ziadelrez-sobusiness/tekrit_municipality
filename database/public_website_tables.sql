-- جداول الموقع الإلكتروني العام لبلدية تكريت

-- جدول الأخبار والأنشطة
CREATE TABLE IF NOT EXISTS news_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    news_type ENUM('رسمية', 'مناسبات محلية', 'أنشطة اجتماعية', 'إعلام رسمي') DEFAULT 'رسمية',
    featured_image VARCHAR(255),
    gallery_images TEXT,
    publish_date DATE NOT NULL,
    is_featured TINYINT(1) DEFAULT 0,
    is_published TINYINT(1) DEFAULT 1,
    views_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول المشاريع الإنمائية
CREATE TABLE IF NOT EXISTS development_projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(255) NOT NULL,
    project_description TEXT NOT NULL,
    project_goal TEXT NOT NULL,
    project_location VARCHAR(255) NOT NULL,
    project_cost DECIMAL(15,2) NOT NULL,
    project_duration VARCHAR(100),
    beneficiaries_count INT,
    beneficiaries_description TEXT,
    project_status ENUM('مطروح', 'قيد التنفيذ', 'منفذ', 'متوقف', 'ملغي') DEFAULT 'مطروح',
    start_date DATE,
    end_date DATE,
    completion_percentage INT DEFAULT 0,
    funding_source VARCHAR(255),
    contractor VARCHAR(255),
    project_images TEXT,
    before_images TEXT,
    after_images TEXT,
    allow_contributions TINYINT(1) DEFAULT 0,
    contributions_target DECIMAL(15,2) DEFAULT 0,
    contributions_collected DECIMAL(15,2) DEFAULT 0,
    responsible_department_id INT,
    project_manager_id INT,
    is_featured TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (responsible_department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (project_manager_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول طلبات المواطنين
CREATE TABLE IF NOT EXISTS citizen_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tracking_number VARCHAR(50) UNIQUE NOT NULL,
    citizen_name VARCHAR(255) NOT NULL,
    citizen_phone VARCHAR(20) NOT NULL,
    citizen_email VARCHAR(255),
    citizen_address TEXT,
    national_id VARCHAR(20),
    request_type ENUM('إفادة سكن', 'شكوى', 'بلاغ أعطال', 'استشارة هندسية', 'طلب خدمة', 'اقتراح', 'أخرى') NOT NULL,
    request_title VARCHAR(255) NOT NULL,
    request_description TEXT NOT NULL,
    priority_level ENUM('عادي', 'مهم', 'عاجل') DEFAULT 'عادي',
    request_status ENUM('جديد', 'قيد المراجعة', 'قيد التنفيذ', 'مكتمل', 'مرفوض', 'معلق') DEFAULT 'جديد',
    attachments TEXT,
    assigned_to_department_id INT,
    assigned_to_committee_id INT,
    assigned_to_user_id INT,
    admin_notes TEXT,
    citizen_rating INT,
    citizen_feedback TEXT,
    response_date DATETIME,
    completion_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to_department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to_committee_id) REFERENCES municipal_committees(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول ممتلكات البلدية
CREATE TABLE IF NOT EXISTS municipality_assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_name VARCHAR(255) NOT NULL,
    asset_description TEXT,
    asset_type ENUM('منقول', 'غير منقول') NOT NULL,
    asset_category VARCHAR(100),
    asset_location VARCHAR(255),
    purchase_date DATE,
    purchase_cost DECIMAL(15,2),
    current_value DECIMAL(15,2),
    asset_condition ENUM('ممتاز', 'جيد', 'مقبول', 'يحتاج صيانة', 'تالف') DEFAULT 'جيد',
    responsible_department_id INT,
    asset_images TEXT,
    notes TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (responsible_department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول موارد البلدية
CREATE TABLE IF NOT EXISTS municipality_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resource_name VARCHAR(255) NOT NULL,
    resource_type ENUM('رسوم', 'جباية', 'هبات', 'عوائد مشاريع', 'موازنة حكومية', 'شراكات') NOT NULL,
    resource_description TEXT,
    annual_target DECIMAL(15,2),
    collected_amount DECIMAL(15,2) DEFAULT 0,
    collection_year INT NOT NULL,
    resource_status ENUM('نشط', 'معلق', 'متوقف') DEFAULT 'نشط',
    responsible_department_id INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (responsible_department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول المبادرات الشبابية والبيئية
CREATE TABLE IF NOT EXISTS youth_environmental_initiatives (
    id INT AUTO_INCREMENT PRIMARY KEY,
    initiative_name VARCHAR(255) NOT NULL,
    initiative_description TEXT NOT NULL,
    initiative_type ENUM('شبابية', 'بيئية', 'مجتمعية', 'تطوعية') NOT NULL,
    initiative_goals TEXT,
    target_audience VARCHAR(255),
    required_volunteers INT,
    registered_volunteers INT DEFAULT 0,
    start_date DATE,
    end_date DATE,
    initiative_status ENUM('مفتوحة للتسجيل', 'قيد التنفيذ', 'مكتملة', 'ملغية') DEFAULT 'مفتوحة للتسجيل',
    coordinator_name VARCHAR(255),
    coordinator_phone VARCHAR(20),
    coordinator_email VARCHAR(255),
    initiative_images TEXT,
    success_story TEXT,
    impact_description TEXT,
    is_featured TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول الوثائق والنماذج
CREATE TABLE IF NOT EXISTS documents_forms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_name VARCHAR(255) NOT NULL,
    document_description TEXT,
    document_type ENUM('نموذج طلب', 'موازنة', 'قرار مجلس', 'مناقصة', 'دليل خدمات', 'أخرى') NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    download_count INT DEFAULT 0,
    is_public TINYINT(1) DEFAULT 1,
    requires_login TINYINT(1) DEFAULT 0,
    department_id INT,
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول الأسئلة الشائعة
CREATE TABLE IF NOT EXISTS faqs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(500) NOT NULL,
    answer TEXT NOT NULL,
    category VARCHAR(100),
    display_order INT DEFAULT 0,
    is_featured TINYINT(1) DEFAULT 0,
    view_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول آراء واقتراحات المواطنين
CREATE TABLE IF NOT EXISTS citizen_opinions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    citizen_name VARCHAR(255) NOT NULL,
    citizen_email VARCHAR(255),
    opinion_type ENUM('مقال', 'رأي', 'اقتراح', 'استطلاع') NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    is_published TINYINT(1) DEFAULT 0,
    approval_status ENUM('قيد المراجعة', 'موافق عليه', 'مرفوض') DEFAULT 'قيد المراجعة',
    admin_notes TEXT,
    publish_date DATE,
    likes_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول الاستطلاعات
CREATE TABLE IF NOT EXISTS polls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_title VARCHAR(255) NOT NULL,
    poll_description TEXT,
    poll_question VARCHAR(500) NOT NULL,
    poll_options TEXT NOT NULL, -- JSON format
    is_active TINYINT(1) DEFAULT 1,
    is_multiple_choice TINYINT(1) DEFAULT 0,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_votes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول إجابات الاستطلاعات
CREATE TABLE IF NOT EXISTS poll_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    voter_ip VARCHAR(45),
    selected_options TEXT NOT NULL, -- JSON format
    additional_comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول إعدادات الموقع
CREATE TABLE IF NOT EXISTS website_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(255) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- إدراج بعض الإعدادات الأساسية (مع تجاهل التكرار)
INSERT IGNORE INTO website_settings (setting_key, setting_value, setting_description) VALUES
('site_title', 'بلدية تكريت', 'عنوان الموقع'),
('site_description', 'الموقع الرسمي لبلدية تكريت - خدمات إلكترونية للمواطنين', 'وصف الموقع'),
('contact_phone', '+964-xxx-xxx-xxxx', 'رقم الهاتف'),
('contact_email', 'info@tikrit-municipality.gov.iq', 'البريد الإلكتروني'),
('contact_address', 'مركز مدينة تكريت، محافظة صلاح الدين، العراق', 'العنوان'),
('office_hours', 'السبت - الخميس: 8:00 ص - 2:00 م', 'ساعات العمل'),
('facebook_url', '', 'رابط فيسبوك'),
('twitter_url', '', 'رابط تويتر'),
('youtube_url', '', 'رابط يوتيوب'),
('welcome_message', 'أهلاً وسهلاً بكم في الموقع الرسمي لبلدية تكريت', 'رسالة الترحيب');

-- إدراج بعض الأسئلة الشائعة (مع تجاهل التكرار)
INSERT IGNORE INTO faqs (question, answer, category, display_order) VALUES
('كيف يمكنني تقديم طلب إلكتروني؟', 'يمكنك تقديم طلبك من خلال قسم "طلبات المواطنين" في الموقع، وستحصل على رقم تتبع لمتابعة حالة طلبك.', 'خدمات', 1),
('هل يمكنني متابعة حالة طلبي؟', 'نعم، يمكنك متابعة حالة طلبك باستخدام رقم التتبع المرسل إليك عبر الرسائل النصية أو البريد الإلكتروني.', 'خدمات', 2),
('ما هي ساعات عمل البلدية؟', 'ساعات العمل الرسمية من السبت إلى الخميس من 8:00 صباحاً حتى 2:00 مساءً.', 'عام', 3),
('كيف يمكنني المشاركة في المشاريع التطوعية؟', 'يمكنك التسجيل في المبادرات من خلال قسم "المبادرات الشبابية والبيئية" أو الاتصال بنا مباشرة.', 'مشاركة', 4),
('أين يمكنني تحميل النماذج المطلوبة؟', 'جميع النماذج متوفرة في قسم "مركز التحميل" ويمكن تحميلها مجاناً.', 'خدمات', 5); 