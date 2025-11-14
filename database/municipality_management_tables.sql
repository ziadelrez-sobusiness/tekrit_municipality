-- نظام إدارة البلدية الشامل
-- قواعد البيانات المطلوبة

-- جدول اللجان البلدية
CREATE TABLE IF NOT EXISTS municipal_committees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    committee_name VARCHAR(255) NOT NULL UNIQUE,
    committee_description TEXT,
    department_id INT,
    committee_type ENUM('دائمة', 'مؤقتة', 'استشارية', 'تنفيذية') DEFAULT 'دائمة',
    chairman_id INT,
    secretary_id INT,
    formation_date DATE,
    dissolution_date DATE NULL,
    is_active TINYINT(1) DEFAULT 1,
    meeting_frequency ENUM('أسبوعية', 'شهرية', 'ربع سنوية', 'حسب الحاجة') DEFAULT 'شهرية',
    responsibilities TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (chairman_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (secretary_id) REFERENCES users(id) ON DELETE SET NULL
);

-- جدول أعضاء اللجان
CREATE TABLE IF NOT EXISTS committee_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    committee_id INT NOT NULL,
    user_id INT NOT NULL,
    member_role ENUM('رئيس', 'نائب الرئيس', 'سكرتير', 'عضو', 'مقرر') DEFAULT 'عضو',
    join_date DATE NOT NULL,
    leave_date DATE NULL,
    is_active TINYINT(1) DEFAULT 1,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (committee_id) REFERENCES municipal_committees(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_active_member (committee_id, user_id, is_active)
);

-- جدول الجلسات البلدية
CREATE TABLE IF NOT EXISTS municipal_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_number VARCHAR(50) NOT NULL,
    session_title VARCHAR(255) NOT NULL,
    session_type ENUM('عادية', 'طارئة', 'استثنائية', 'لجنة') DEFAULT 'عادية',
    committee_id INT NULL,
    session_date DATE NOT NULL,
    session_time TIME NOT NULL,
    location VARCHAR(255) DEFAULT 'قاعة الاجتماعات الرئيسية',
    agenda TEXT NOT NULL,
    session_status ENUM('مجدولة', 'جارية', 'مكتملة', 'مؤجلة', 'ملغية') DEFAULT 'مجدولة',
    chairperson_id INT,
    secretary_id INT,
    quorum_required INT DEFAULT 5,
    attendees_count INT DEFAULT 0,
    session_minutes TEXT,
    attachments JSON,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (committee_id) REFERENCES municipal_committees(id) ON DELETE SET NULL,
    FOREIGN KEY (chairperson_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (secretary_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- جدول دعوات الجلسات
CREATE TABLE IF NOT EXISTS session_invitations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    invitee_id INT NOT NULL,
    invitation_type ENUM('عضو', 'ضيف', 'خبير', 'مراقب') DEFAULT 'عضو',
    sent_at TIMESTAMP NULL,
    response_status ENUM('لم يرد', 'موافق', 'اعتذار', 'مؤجل') DEFAULT 'لم يرد',
    response_date TIMESTAMP NULL,
    attendance_status ENUM('غير محدد', 'حاضر', 'غائب', 'متأخر') DEFAULT 'غير محدد',
    attendance_time TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES municipal_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (invitee_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_invitation (session_id, invitee_id)
);

-- جدول القرارات البلدية
CREATE TABLE IF NOT EXISTS municipal_decisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    decision_number VARCHAR(100) NOT NULL UNIQUE,
    session_id INT NOT NULL,
    decision_title VARCHAR(255) NOT NULL,
    decision_text TEXT NOT NULL,
    decision_type ENUM('إداري', 'مالي', 'فني', 'قانوني', 'تنظيمي', 'أخرى') DEFAULT 'إداري',
    priority_level ENUM('عادي', 'مهم', 'عاجل', 'طارئ') DEFAULT 'عادي',
    decision_category VARCHAR(100),
    voting_result ENUM('بالإجماع', 'بالأغلبية', 'مرفوض', 'مؤجل') DEFAULT 'بالأغلبية',
    votes_for INT DEFAULT 0,
    votes_against INT DEFAULT 0,
    votes_abstain INT DEFAULT 0,
    implementation_deadline DATE NULL,
    responsible_department_id INT,
    responsible_person_id INT,
    implementation_status ENUM('قيد الانتظار', 'قيد التنفيذ', 'مكتمل', 'متأخر', 'معلق', 'ملغي') DEFAULT 'قيد الانتظار',
    implementation_progress INT DEFAULT 0,
    implementation_notes TEXT,
    budget_required DECIMAL(15,2) DEFAULT 0,
    legal_review_required TINYINT(1) DEFAULT 0,
    legal_review_status ENUM('غير مطلوب', 'قيد المراجعة', 'موافق عليه', 'يحتاج تعديل') DEFAULT 'غير مطلوب',
    related_project_id INT NULL,
    attachments JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES municipal_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (responsible_department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (responsible_person_id) REFERENCES users(id) ON DELETE SET NULL
);

-- جدول متابعة تنفيذ القرارات
CREATE TABLE IF NOT EXISTS decision_follow_ups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    decision_id INT NOT NULL,
    follow_up_date DATE NOT NULL,
    follow_up_by INT NOT NULL,
    status_update ENUM('قيد الانتظار', 'قيد التنفيذ', 'مكتمل', 'متأخر', 'معلق', 'ملغي'),
    progress_percentage INT DEFAULT 0,
    notes TEXT,
    obstacles TEXT,
    next_action TEXT,
    next_follow_up_date DATE,
    attachments JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (decision_id) REFERENCES municipal_decisions(id) ON DELETE CASCADE,
    FOREIGN KEY (follow_up_by) REFERENCES users(id) ON DELETE CASCADE
);

-- جدول الهيكل التنظيمي
CREATE TABLE IF NOT EXISTS organizational_structure (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    parent_department_id INT NULL,
    level_order INT DEFAULT 1,
    hierarchy_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_department_id) REFERENCES departments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_department_structure (department_id)
);

-- إضافة بعض البيانات الأساسية
INSERT IGNORE INTO municipal_committees (committee_name, committee_description, committee_type, meeting_frequency, responsibilities) VALUES
('لجنة الشؤون المالية', 'مراجعة الميزانيات والأمور المالية للبلدية', 'دائمة', 'شهرية', 'مراجعة الميزانية، الموافقة على المصروفات الكبيرة، مراقبة الأداء المالي'),
('لجنة التخطيط والتطوير', 'التخطيط للمشاريع التطويرية ومتابعة تنفيذها', 'دائمة', 'شهرية', 'وضع خطط التطوير، مراجعة المشاريع، متابعة التنفيذ'),
('لجنة البيئة والنظافة', 'الإشراف على شؤون البيئة والنظافة العامة', 'دائمة', 'شهرية', 'مراقبة النظافة العامة، حماية البيئة، إدارة النفايات'),
('لجنة الخدمات العامة', 'الإشراف على الخدمات المقدمة للمواطنين', 'دائمة', 'شهرية', 'تطوير الخدمات، متابعة جودة الخدمة، حل الشكاوى'),
('لجنة الطوارئ', 'التعامل مع الحالات الطارئة والأزمات', 'مؤقتة', 'حسب الحاجة', 'إدارة الأزمات، التعامل مع الطوارئ، وضع خطط الطوارئ');

-- إضافة فهارس لتحسين الأداء
CREATE INDEX idx_committee_department ON municipal_committees(department_id);
CREATE INDEX idx_committee_active ON municipal_committees(is_active);
CREATE INDEX idx_member_committee ON committee_members(committee_id);
CREATE INDEX idx_member_user ON committee_members(user_id);
CREATE INDEX idx_session_date ON municipal_sessions(session_date);
CREATE INDEX idx_session_status ON municipal_sessions(session_status);
CREATE INDEX idx_decision_number ON municipal_decisions(decision_number);
CREATE INDEX idx_decision_status ON municipal_decisions(implementation_status);
CREATE INDEX idx_decision_deadline ON municipal_decisions(implementation_deadline);
CREATE INDEX idx_followup_date ON decision_follow_ups(follow_up_date); 