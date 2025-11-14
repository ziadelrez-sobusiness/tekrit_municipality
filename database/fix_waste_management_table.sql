-- إصلاح بنية جدول إدارة النفايات
-- تاريخ الإنشاء: ديسمبر 2024

USE tekrit_municipality;

-- التحقق من وجود الجدول وإنشاؤه بالبنية الصحيحة
DROP TABLE IF EXISTS waste_collection_schedules;

CREATE TABLE waste_collection_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_name VARCHAR(200) NOT NULL,
    area VARCHAR(200) NOT NULL,
    schedule_type ENUM('يومي', 'أسبوعي', 'نصف شهري', 'شهري') DEFAULT 'أسبوعي',
    collection_day ENUM('الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    assigned_team VARCHAR(100),
    vehicle_id INT,
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_by_user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إنشاء جدول تقارير النفايات
DROP TABLE IF EXISTS waste_reports;

CREATE TABLE waste_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    area VARCHAR(200) NOT NULL,
    report_type ENUM('شكوى نظافة', 'طلب جمع إضافي', 'تلف حاوية', 'تسرب', 'أخرى') NOT NULL,
    description TEXT NOT NULL,
    reporter_name VARCHAR(100),
    reporter_phone VARCHAR(20),
    priority ENUM('عادية', 'متوسطة', 'عالية', 'عاجلة') DEFAULT 'عادية',
    location_details TEXT,
    status ENUM('مفتوح', 'قيد المعالجة', 'منجز', 'مؤجل', 'مرفوض') DEFAULT 'مفتوح',
    assigned_team VARCHAR(100),
    admin_notes TEXT,
    completion_date DATE,
    created_by_user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إدراج بيانات تجريبية لجداول جمع النفايات
INSERT INTO waste_collection_schedules (route_name, area, schedule_type, collection_day, start_time, end_time, assigned_team, vehicle_id, is_active, created_by_user_id) VALUES
('المسار الأول - المركز', 'المنطقة المركزية', 'أسبوعي', 'الأحد', '06:00:00', '10:00:00', 'فريق النظافة الأول', 1, 1, 1),
('المسار الثاني - التجاري', 'الحي التجاري', 'أسبوعي', 'الاثنين', '07:00:00', '11:00:00', 'فريق النظافة الثاني', 1, 1, 1),
('المسار الثالث - السكني الشمالي', 'المنطقة السكنية الشمالية', 'أسبوعي', 'الثلاثاء', '06:30:00', '10:30:00', 'فريق النظافة الأول', 1, 1, 1),
('المسار الرابع - الصناعي', 'المنطقة الصناعية', 'نصف شهري', 'الأربعاء', '08:00:00', '12:00:00', 'فريق النظافة الثالث', 1, 1, 1),
('المسار الخامس - السكني الجنوبي', 'المنطقة السكنية الجنوبية', 'أسبوعي', 'الخميس', '06:00:00', '10:00:00', 'فريق النظافة الثاني', 1, 1, 1);

-- إدراج بيانات تجريبية لتقارير النفايات
INSERT INTO waste_reports (area, report_type, description, reporter_name, reporter_phone, priority, location_details, status, created_by_user_id) VALUES
('المنطقة المركزية', 'شكوى نظافة', 'تراكم النفايات أمام المحلات التجارية في شارع الجمهورية', 'علي محمد حسن', '07701234567', 'عالية', 'شارع الجمهورية - أمام محل الإلكترونيات', 'قيد المعالجة', 1),
('الحي التجاري', 'طلب جمع إضافي', 'تراكم كمية كبيرة من النفايات بعد فعالية تجارية', 'سارة أحمد', '07709876543', 'متوسطة', 'ساحة السوق المركزي', 'مفتوح', 1),
('المنطقة السكنية الشمالية', 'تلف حاوية', 'حاوية النفايات مكسورة ولا يمكن استخدامها', 'محمد خالد', '07812345678', 'متوسطة', 'تقاطع شارع النور مع شارع السلام', 'مفتوح', 1);

-- إضافة فهارس لتحسين الأداء
CREATE INDEX idx_waste_schedules_area ON waste_collection_schedules(area);
CREATE INDEX idx_waste_schedules_day ON waste_collection_schedules(collection_day);
CREATE INDEX idx_waste_schedules_active ON waste_collection_schedules(is_active);
CREATE INDEX idx_waste_reports_status ON waste_reports(status);
CREATE INDEX idx_waste_reports_priority ON waste_reports(priority);
CREATE INDEX idx_waste_reports_area ON waste_reports(area);

-- رسالة تأكيد
SELECT 'تم إصلاح جداول إدارة النفايات بنجاح - الجداول جاهزة للاستخدام' as status; 