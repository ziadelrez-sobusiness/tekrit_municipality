-- تحسينات نظام المبادرات
-- إضافة أعمدة جديدة لجدول المبادرات

ALTER TABLE youth_environmental_initiatives 
ADD COLUMN IF NOT EXISTS max_volunteers INT DEFAULT 50 AFTER required_volunteers,
ADD COLUMN IF NOT EXISTS registration_deadline DATE AFTER end_date,
ADD COLUMN IF NOT EXISTS location VARCHAR(255) AFTER coordinator_email,
ADD COLUMN IF NOT EXISTS budget DECIMAL(15,2) DEFAULT 0 AFTER location,
ADD COLUMN IF NOT EXISTS requirements TEXT AFTER initiative_goals,
ADD COLUMN IF NOT EXISTS benefits TEXT AFTER requirements,
ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1 AFTER is_featured,
ADD COLUMN IF NOT EXISTS auto_approval TINYINT(1) DEFAULT 1 AFTER is_active,
ADD COLUMN IF NOT EXISTS created_by INT AFTER auto_approval,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- إضافة مفتاح خارجي للمستخدم المنشئ
ALTER TABLE youth_environmental_initiatives 
ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;

-- جدول تسجيل المتطوعين في المبادرات
CREATE TABLE IF NOT EXISTS initiative_volunteers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    initiative_id INT NOT NULL,
    volunteer_name VARCHAR(255) NOT NULL,
    volunteer_phone VARCHAR(20) NOT NULL,
    volunteer_email VARCHAR(255),
    volunteer_age INT,
    volunteer_gender ENUM('ذكر', 'أنثى') NOT NULL,
    volunteer_address TEXT,
    volunteer_skills TEXT,
    volunteer_experience TEXT,
    motivation TEXT,
    availability TEXT,
    emergency_contact_name VARCHAR(255),
    emergency_contact_phone VARCHAR(20),
    registration_status ENUM('قيد المراجعة', 'مقبول', 'مرفوض', 'في قائمة الانتظار') DEFAULT 'قيد المراجعة',
    approval_date DATETIME NULL,
    approved_by INT NULL,
    rejection_reason TEXT,
    notes TEXT,
    attendance_count INT DEFAULT 0,
    performance_rating ENUM('ممتاز', 'جيد جداً', 'جيد', 'مقبول', 'ضعيف') NULL,
    certificate_issued TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (initiative_id) REFERENCES youth_environmental_initiatives(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول أنشطة المبادرات
CREATE TABLE IF NOT EXISTS initiative_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    initiative_id INT NOT NULL,
    activity_name VARCHAR(255) NOT NULL,
    activity_description TEXT,
    activity_date DATE NOT NULL,
    activity_time TIME,
    activity_location VARCHAR(255),
    required_volunteers INT DEFAULT 0,
    registered_volunteers INT DEFAULT 0,
    activity_status ENUM('مجدولة', 'قيد التنفيذ', 'مكتملة', 'ملغية') DEFAULT 'مجدولة',
    activity_notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (initiative_id) REFERENCES youth_environmental_initiatives(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول حضور المتطوعين للأنشطة
CREATE TABLE IF NOT EXISTS volunteer_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    activity_id INT NOT NULL,
    volunteer_id INT NOT NULL,
    attendance_status ENUM('حاضر', 'غائب', 'متأخر', 'اعتذر') DEFAULT 'حاضر',
    attendance_time DATETIME,
    departure_time DATETIME,
    performance_notes TEXT,
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (activity_id) REFERENCES initiative_activities(id) ON DELETE CASCADE,
    FOREIGN KEY (volunteer_id) REFERENCES initiative_volunteers(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول تقييم المبادرات
CREATE TABLE IF NOT EXISTS initiative_evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    initiative_id INT NOT NULL,
    evaluator_name VARCHAR(255) NOT NULL,
    evaluator_email VARCHAR(255),
    evaluator_type ENUM('متطوع', 'مستفيد', 'مراقب خارجي', 'إدارة البلدية') NOT NULL,
    overall_rating INT NOT NULL CHECK (overall_rating >= 1 AND overall_rating <= 5),
    organization_rating INT CHECK (organization_rating >= 1 AND organization_rating <= 5),
    impact_rating INT CHECK (impact_rating >= 1 AND impact_rating <= 5),
    communication_rating INT CHECK (communication_rating >= 1 AND communication_rating <= 5),
    feedback_text TEXT,
    suggestions TEXT,
    would_participate_again TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (initiative_id) REFERENCES youth_environmental_initiatives(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- إدراج بعض البيانات التجريبية للمبادرات
INSERT IGNORE INTO youth_environmental_initiatives (
    initiative_name, 
    initiative_description, 
    initiative_type, 
    initiative_goals, 
    requirements,
    benefits,
    target_audience, 
    required_volunteers, 
    max_volunteers,
    start_date, 
    end_date,
    registration_deadline,
    location,
    budget,
    coordinator_name, 
    coordinator_phone, 
    coordinator_email,
    is_featured,
    auto_approval
) VALUES 
(
    'حملة تنظيف نهر دجلة', 
    'مبادرة بيئية لتنظيف ضفاف نهر دجلة وإزالة النفايات والمخلفات لحماية البيئة المائية',
    'بيئية',
    'تنظيف 5 كيلومترات من ضفاف النهر، جمع وفرز النفايات، زراعة 100 شجرة، توعية 500 مواطن',
    'العمر من 16-50 سنة، اللياقة البدنية الجيدة، الالتزام بالحضور',
    'شهادة تطوع، وجبة غداء، قميص المبادرة، تدريب بيئي',
    'الشباب والطلاب والمهتمين بالبيئة',
    30,
    50,
    '2024-02-15',
    '2024-02-17',
    '2024-02-10',
    'ضفاف نهر دجلة - منطقة الكاظمية',
    2500000,
    'أحمد محمد علي',
    '07701234567',
    'ahmed.ali@tikrit.gov.iq',
    1,
    1
),
(
    'مبادرة تعليم الحاسوب للمسنين',
    'برنامج تطوعي لتعليم كبار السن استخدام الحاسوب والإنترنت والتطبيقات الذكية',
    'مجتمعية',
    'تدريب 100 مسن، إنشاء 5 مراكز تدريب، إعداد 20 متطوع مدرب',
    'خبرة في الحاسوب، الصبر في التعليم، التواصل الجيد مع كبار السن',
    'شهادة تدريب، خبرة تعليمية، شهادة تطوع معتمدة',
    'الشباب الجامعيين وخريجي الحاسوب',
    20,
    30,
    '2024-03-01',
    '2024-04-30',
    '2024-02-25',
    'مراكز الأحياء في تكريت',
    1500000,
    'فاطمة حسن محمود',
    '07712345678',
    'fatima.hassan@tikrit.gov.iq',
    1,
    0
),
(
    'مهرجان تكريت الثقافي',
    'مهرجان سنوي لإحياء التراث والثقافة المحلية وعرض المواهب الشبابية',
    'شبابية',
    'تنظيم 15 فعالية ثقافية، مشاركة 200 شاب وشابة، جذب 5000 زائر',
    'المواهب الفنية أو التنظيمية، الالتزام بالمواعيد، العمل الجماعي',
    'شهادة مشاركة، فرصة عرض المواهب، جوائز للمتميزين',
    'الشباب والفنانين والمهتمين بالثقافة',
    40,
    60,
    '2024-04-15',
    '2024-04-20',
    '2024-04-05',
    'المركز الثقافي في تكريت',
    5000000,
    'عمر سالم الجبوري',
    '07723456789',
    'omar.salem@tikrit.gov.iq',
    1,
    1
);

-- تحديث عداد المتطوعين المسجلين (بيانات تجريبية)
UPDATE youth_environmental_initiatives SET registered_volunteers = 15 WHERE initiative_name = 'حملة تنظيف نهر دجلة';
UPDATE youth_environmental_initiatives SET registered_volunteers = 8 WHERE initiative_name = 'مبادرة تعليم الحاسوب للمسنين';
UPDATE youth_environmental_initiatives SET registered_volunteers = 25 WHERE initiative_name = 'مهرجان تكريت الثقافي'; 