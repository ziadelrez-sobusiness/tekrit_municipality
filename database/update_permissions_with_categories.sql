-- تحديث جدول الصلاحيات ليتضمن الفئات المطابقة للقائمة الرئيسية
-- بلدية تكريت - نظام الصلاحيات المحسّن

USE tekrit_municipality;

-- التأكد من وجود جدول user_permissions
CREATE TABLE IF NOT EXISTS user_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    permission_id INT NOT NULL,
    granted_by_user_id INT,
    granted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    UNIQUE KEY unique_user_permission (user_id, permission_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- حذف جدول permissions القديم إن وجد وإنشاؤه من جديد
DROP TABLE IF EXISTS user_permissions;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS permissions;

-- إنشاء جدول الصلاحيات المحسّن
CREATE TABLE permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    permission_key VARCHAR(100) UNIQUE NOT NULL COMMENT 'مفتاح فريد للصلاحية مثل finance_view',
    display_name VARCHAR(255) NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'الاسم المعروض بالعربية',
    description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'وصف الصلاحية',
    category VARCHAR(50) NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'الفئة: general_admin, finance, projects, etc.',
    module_name VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'اسم الوحدة',
    page_url VARCHAR(255) COMMENT 'رابط الصفحة المرتبطة',
    icon VARCHAR(20) COMMENT 'أيقونة emoji',
    parent_permission_id INT NULL COMMENT 'الصلاحية الأب للصلاحيات الفرعية',
    sort_order INT DEFAULT 0 COMMENT 'ترتيب العرض',
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    INDEX idx_category (category),
    INDEX idx_module (module_name)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- إعادة إنشاء جدول user_permissions
CREATE TABLE user_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    permission_id INT NOT NULL,
    granted_by_user_id INT,
    granted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    UNIQUE KEY unique_user_permission (user_id, permission_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- إدخال الصلاحيات حسب الفئات العشرة المطابقة للقائمة الرئيسية
-- ═══════════════════════════════════════════════════════════════════════════════

-- ═══════════════════════════════════════════════════════════════════════════════
-- الفئة 1: 🏛️ الإدارة العامة (general_admin)
-- ═══════════════════════════════════════════════════════════════════════════════

INSERT INTO permissions (permission_key, display_name, description, category, module_name, page_url, icon, sort_order) VALUES
-- إدارة البلدية
('municipality_view', 'عرض معلومات البلدية', 'عرض معلومات وإحصائيات البلدية', 'general_admin', 'municipality', 'modules/municipality_management.php', '🏛️', 10),
('municipality_edit', 'تعديل معلومات البلدية', 'تعديل بيانات البلدية الأساسية', 'general_admin', 'municipality', 'modules/municipality_management.php', '✏️', 11),

-- إدارة المجلس البلدي
('council_view', 'عرض أعضاء المجلس', 'عرض قائمة أعضاء المجلس البلدي', 'general_admin', 'council', 'modules/council_management.php', '👥', 20),
('council_add', 'إضافة عضو مجلس', 'إضافة عضو جديد للمجلس البلدي', 'general_admin', 'council', 'modules/council_management.php', '➕', 21),
('council_edit', 'تعديل بيانات الأعضاء', 'تعديل معلومات أعضاء المجلس', 'general_admin', 'council', 'modules/council_management.php', '✏️', 22),
('council_delete', 'حذف عضو مجلس', 'حذف عضو من المجلس البلدي', 'general_admin', 'council', 'modules/council_management.php', '🗑️', 23),

-- الموارد البشرية
('hr_view', 'عرض الموظفين', 'عرض قائمة الموظفين والمعلومات', 'general_admin', 'hr', 'modules/hr.php', '👔', 30),
('hr_add', 'إضافة موظف', 'إضافة موظف جديد للنظام', 'general_admin', 'hr', 'modules/hr.php', '➕', 31),
('hr_edit', 'تعديل بيانات موظف', 'تعديل معلومات الموظفين', 'general_admin', 'hr', 'modules/hr.php', '✏️', 32),
('hr_delete', 'حذف موظف', 'حذف موظف من النظام', 'general_admin', 'hr', 'modules/hr.php', '🗑️', 33),
('hr_salary_view', 'عرض الرواتب', 'عرض رواتب الموظفين', 'general_admin', 'hr', 'modules/hr.php', '💰', 34),
('hr_salary_edit', 'تعديل الرواتب', 'تعديل رواتب الموظفين', 'general_admin', 'hr', 'modules/hr.php', '💵', 35),

-- إدارة الصلاحيات
('permissions_view', 'عرض الصلاحيات', 'عرض صلاحيات المستخدمين', 'general_admin', 'permissions', 'modules/permissions.php', '🔐', 40),
('permissions_manage', 'إدارة الصلاحيات', 'منح وإلغاء صلاحيات المستخدمين', 'general_admin', 'permissions', 'modules/permissions.php', '🔑', 41),
('users_manage', 'إدارة المستخدمين', 'إضافة وتعديل وحذف المستخدمين', 'general_admin', 'core', 'modules/permissions.php', '👤', 42);

-- ═══════════════════════════════════════════════════════════════════════════════
-- الفئة 2: 💰 النظام المالي (finance)
-- ═══════════════════════════════════════════════════════════════════════════════

INSERT INTO permissions (permission_key, display_name, description, category, module_name, page_url, icon, sort_order) VALUES
-- لوحة التحكم المالية
('financial_dashboard_view', 'عرض لوحة التحكم المالية', 'عرض الإحصائيات والتقارير المالية', 'finance', 'finance', 'modules/financial_dashboard.php', '📊', 100),

-- المعاملات المالية
('finance_view', 'عرض المعاملات المالية', 'عرض جميع المعاملات المالية', 'finance', 'finance', 'modules/finance.php', '💵', 110),
('finance_add', 'إضافة معاملة مالية', 'إضافة معاملة مالية جديدة', 'finance', 'finance', 'modules/finance.php', '➕', 111),
('finance_edit', 'تعديل معاملة مالية', 'تعديل معاملات مالية موجودة', 'finance', 'finance', 'modules/finance.php', '✏️', 112),
('finance_delete', 'حذف معاملة مالية', 'حذف معاملات مالية', 'finance', 'finance', 'modules/finance.php', '🗑️', 113),

-- الميزانيات
('budgets_view', 'عرض الميزانيات', 'عرض الميزانيات والبنود', 'finance', 'budgets', 'modules/budgets.php', '📊', 120),
('budgets_add', 'إضافة ميزانية', 'إنشاء ميزانية جديدة', 'finance', 'budgets', 'modules/budgets.php', '➕', 121),
('budgets_edit', 'تعديل ميزانية', 'تعديل الميزانيات والبنود', 'finance', 'budgets', 'modules/budgets.php', '✏️', 122),
('budgets_delete', 'حذف ميزانية', 'حذف ميزانية', 'finance', 'budgets', 'modules/budgets.php', '🗑️', 123),

-- الموردين
('suppliers_view', 'عرض الموردين', 'عرض قائمة الموردين', 'finance', 'suppliers', 'modules/suppliers.php', '🏪', 130),
('suppliers_add', 'إضافة مورد', 'إضافة مورد جديد', 'finance', 'suppliers', 'modules/suppliers.php', '➕', 131),
('suppliers_edit', 'تعديل بيانات مورد', 'تعديل معلومات الموردين', 'finance', 'suppliers', 'modules/suppliers.php', '✏️', 132),
('suppliers_delete', 'حذف مورد', 'حذف مورد من النظام', 'finance', 'suppliers', 'modules/suppliers.php', '🗑️', 133),

-- الفواتير
('invoices_view', 'عرض الفواتير', 'عرض فواتير الموردين', 'finance', 'invoices', 'modules/invoices.php', '📄', 140),
('invoices_add', 'إضافة فاتورة', 'إضافة فاتورة جديدة', 'finance', 'invoices', 'modules/invoices.php', '➕', 141),
('invoices_edit', 'تعديل فاتورة', 'تعديل فواتير موجودة', 'finance', 'invoices', 'modules/invoices.php', '✏️', 142),
('invoices_delete', 'حذف فاتورة', 'حذف فاتورة', 'finance', 'invoices', 'modules/invoices.php', '🗑️', 143),
('invoices_pay', 'تسديد فاتورة', 'تسديد أو تسجيل دفعة لفاتورة', 'finance', 'invoices', 'modules/invoices.php', '💰', 144),

-- الجباية
('tax_view', 'عرض سجلات الجباية', 'عرض سجلات الضرائب والرسوم', 'finance', 'tax', 'modules/tax_collection.php', '🧾', 150),
('tax_add', 'إضافة سجل جباية', 'إضافة سجل ضرائب جديد', 'finance', 'tax', 'modules/tax_collection.php', '➕', 151),
('tax_edit', 'تعديل سجل جباية', 'تعديل سجلات الجباية', 'finance', 'tax', 'modules/tax_collection.php', '✏️', 152),
('tax_delete', 'حذف سجل جباية', 'حذف سجل جباية', 'finance', 'tax', 'modules/tax_collection.php', '🗑️', 153),

-- التبرعات
('donations_view', 'عرض التبرعات', 'عرض سجلات التبرعات', 'finance', 'finance', 'modules/donations.php', '💖', 160),
('donations_add', 'إضافة تبرع', 'إضافة تبرع جديد', 'finance', 'finance', 'modules/donations.php', '➕', 161),
('donations_edit', 'تعديل تبرع', 'تعديل سجل تبرع', 'finance', 'finance', 'modules/donations.php', '✏️', 162),

-- المساهمات الشعبية
('contributions_view', 'عرض المساهمات', 'عرض المساهمات الشعبية', 'finance', 'finance', 'modules/contributions.php', '🤝', 170),
('contributions_add', 'إضافة مساهمة', 'إضافة مساهمة شعبية', 'finance', 'finance', 'modules/contributions.php', '➕', 171),

-- العملات
('currencies_manage', 'إدارة العملات', 'إدارة العملات وأسعار الصرف', 'finance', 'finance', 'modules/currencies.php', '💱', 180);

-- ═══════════════════════════════════════════════════════════════════════════════
-- الفئة 3: 🏗️ المشاريع والعقود (projects)
-- ═══════════════════════════════════════════════════════════════════════════════

INSERT INTO permissions (permission_key, display_name, description, category, module_name, page_url, icon, sort_order) VALUES
-- إدارة المشاريع
('projects_view', 'عرض المشاريع', 'عرض قائمة المشاريع', 'projects', 'projects', 'modules/projects_unified.php', '🏗️', 200),
('projects_add', 'إضافة مشروع', 'إضافة مشروع جديد', 'projects', 'projects', 'modules/projects_unified.php', '➕', 201),
('projects_edit', 'تعديل مشروع', 'تعديل معلومات المشاريع', 'projects', 'projects', 'modules/projects_unified.php', '✏️', 202),
('projects_delete', 'حذف مشروع', 'حذف مشروع', 'projects', 'projects', 'modules/projects_unified.php', '🗑️', 203),
('projects_status_change', 'تغيير حالة مشروع', 'تغيير حالة تقدم المشروع', 'projects', 'projects', 'modules/projects_unified.php', '🔄', 204),

-- التتبع المالي للمشاريع
('projects_finance_view', 'عرض المالية للمشاريع', 'عرض التفاصيل المالية للمشاريع', 'projects', 'projects', 'modules/projects_finance.php', '💵', 210),
('projects_finance_edit', 'تعديل مالية المشاريع', 'تعديل الميزانيات والنفقات', 'projects', 'projects', 'modules/projects_finance.php', '✏️', 211),

-- العقود والمناقصات
('contracts_view', 'عرض العقود', 'عرض العقود والمناقصات', 'projects', 'contracts', 'modules/contracts.php', '📋', 220),
('contracts_add', 'إضافة عقد', 'إضافة عقد أو مناقصة جديدة', 'projects', 'contracts', 'modules/contracts.php', '➕', 221),
('contracts_edit', 'تعديل عقد', 'تعديل تفاصيل العقود', 'projects', 'contracts', 'modules/contracts.php', '✏️', 222),
('contracts_delete', 'حذف عقد', 'حذف عقد', 'projects', 'contracts', 'modules/contracts.php', '🗑️', 223),

-- المنظمات المانحة
('donors_view', 'عرض المنظمات المانحة', 'عرض قائمة المنظمات المانحة', 'projects', 'donors', 'modules/donor_organizations.php', '🏛️', 230),
('donors_add', 'إضافة منظمة مانحة', 'إضافة منظمة مانحة جديدة', 'projects', 'donors', 'modules/donor_organizations.php', '➕', 231),
('donors_edit', 'تعديل منظمة مانحة', 'تعديل بيانات المنظمات', 'projects', 'donors', 'modules/donor_organizations.php', '✏️', 232);

-- ═══════════════════════════════════════════════════════════════════════════════
-- الفئة 4: 👥 خدمات المواطنين (citizens)
-- ═══════════════════════════════════════════════════════════════════════════════

INSERT INTO permissions (permission_key, display_name, description, category, module_name, page_url, icon, sort_order) VALUES
-- إدارة المواطنين
('citizens_view', 'عرض بيانات المواطنين', 'عرض قائمة وسجلات المواطنين', 'citizens', 'citizens', 'modules/citizens.php', '👨‍👩‍👧‍👦', 300),
('citizens_add', 'إضافة مواطن', 'إضافة مواطن جديد', 'citizens', 'citizens', 'modules/citizens.php', '➕', 301),
('citizens_edit', 'تعديل بيانات مواطن', 'تعديل معلومات المواطنين', 'citizens', 'citizens', 'modules/citizens.php', '✏️', 302),
('citizens_delete', 'حذف مواطن', 'حذف سجل مواطن', 'citizens', 'citizens', 'modules/citizens.php', '🗑️', 303),

-- حسابات المواطنين
('citizen_accounts_view', 'عرض حسابات المواطنين', 'عرض حسابات وكلمات المرور', 'citizens', 'citizens', 'modules/citizens_accounts.php', '👤', 310),
('citizen_accounts_manage', 'إدارة حسابات المواطنين', 'إنشاء وتعديل حسابات المواطنين', 'citizens', 'citizens', 'modules/citizens_accounts.php', '🔑', 311),

-- الشكاوى
('complaints_view', 'عرض الشكاوى', 'عرض شكاوى المواطنين', 'citizens', 'complaints', 'modules/complaints.php', '📢', 320),
('complaints_edit', 'معالجة الشكاوى', 'الرد على ومعالجة الشكاوى', 'citizens', 'complaints', 'modules/complaints.php', '✏️', 321),
('complaints_delete', 'حذف شكوى', 'حذف شكوى من النظام', 'citizens', 'complaints', 'modules/complaints.php', '🗑️', 322),

-- رخص البناء
('permits_view', 'عرض رخص البناء', 'عرض طلبات رخص البناء', 'citizens', 'permits', 'modules/building_permit.php', '📝', 330),
('permits_add', 'إضافة رخصة بناء', 'إضافة طلب رخصة جديد', 'citizens', 'permits', 'modules/building_permit.php', '➕', 331),
('permits_edit', 'معالجة رخص البناء', 'مراجعة وتعديل الطلبات', 'citizens', 'permits', 'modules/building_permit.php', '✏️', 332),
('permits_approve', 'الموافقة على رخص البناء', 'الموافقة أو رفض الطلبات', 'citizens', 'permits', 'modules/building_permit.php', '✅', 333),

-- المخالفات
('violations_view', 'عرض المخالفات', 'عرض المخالفات البلدية', 'citizens', 'violations', 'modules/violations.php', '⚠️', 340),
('violations_add', 'إضافة مخالفة', 'إضافة مخالفة جديدة', 'citizens', 'violations', 'modules/violations.php', '➕', 341),
('violations_edit', 'تعديل مخالفة', 'تعديل تفاصيل المخالفات', 'citizens', 'violations', 'modules/violations.php', '✏️', 342),
('violations_delete', 'حذف مخالفة', 'حذف مخالفة', 'citizens', 'violations', 'modules/violations.php', '🗑️', 343);

-- ═══════════════════════════════════════════════════════════════════════════════
-- الفئة 5: 🚚 الخدمات والصيانة (services)
-- ═══════════════════════════════════════════════════════════════════════════════

INSERT INTO permissions (permission_key, display_name, description, category, module_name, page_url, icon, sort_order) VALUES
-- الآليات
('vehicles_view', 'عرض الآليات', 'عرض قائمة الآليات والمعدات', 'services', 'vehicles', 'modules/vehicles.php', '🚚', 400),
('vehicles_add', 'إضافة آلية', 'إضافة آلية أو معدة جديدة', 'services', 'vehicles', 'modules/vehicles.php', '➕', 401),
('vehicles_edit', 'تعديل بيانات آلية', 'تعديل معلومات الآليات', 'services', 'vehicles', 'modules/vehicles.php', '✏️', 402),
('vehicles_delete', 'حذف آلية', 'حذف آلية من النظام', 'services', 'vehicles', 'modules/vehicles.php', '🗑️', 403),

-- السائقين
('drivers_view', 'عرض السائقين', 'عرض قائمة السائقين', 'services', 'vehicles', 'modules/drivers_section.php', '🚗', 410),
('drivers_add', 'إضافة سائق', 'إضافة سائق جديد', 'services', 'vehicles', 'modules/drivers_section.php', '➕', 411),
('drivers_edit', 'تعديل بيانات سائق', 'تعديل معلومات السائقين', 'services', 'vehicles', 'modules/drivers_section.php', '✏️', 412),

-- الصيانة
('maintenance_view', 'عرض سجلات الصيانة', 'عرض سجلات الصيانة والتصليحات', 'services', 'maintenance', 'modules/maintenance.php', '🔧', 420),
('maintenance_add', 'إضافة سجل صيانة', 'إضافة طلب صيانة جديد', 'services', 'maintenance', 'modules/maintenance.php', '➕', 421),
('maintenance_edit', 'تعديل سجل صيانة', 'تعديل سجلات الصيانة', 'services', 'maintenance', 'modules/maintenance.php', '✏️', 422),

-- النفايات
('waste_view', 'عرض إدارة النفايات', 'عرض سجلات وجداول النفايات', 'services', 'waste', 'modules/waste.php', '🗑️', 430),
('waste_edit', 'إدارة جمع النفايات', 'إدارة جداول وطرق جمع النفايات', 'services', 'waste', 'modules/waste.php', '✏️', 431),

-- المخزون
('inventory_view', 'عرض المخزون', 'عرض المواد والمخزون', 'services', 'inventory', 'modules/inventory.php', '📦', 440),
('inventory_add', 'إضافة مادة للمخزون', 'إضافة مادة جديدة للمخزون', 'services', 'inventory', 'modules/inventory.php', '➕', 441),
('inventory_edit', 'تعديل المخزون', 'تعديل الكميات والمواد', 'services', 'inventory', 'modules/inventory.php', '✏️', 442),
('inventory_delete', 'حذف مادة من المخزون', 'حذف مادة', 'services', 'inventory', 'modules/inventory.php', '🗑️', 443);

-- ═══════════════════════════════════════════════════════════════════════════════
-- الفئة 6: 🗺️ الخرائط والمرافق (maps)
-- ═══════════════════════════════════════════════════════════════════════════════

INSERT INTO permissions (permission_key, display_name, description, category, module_name, page_url, icon, sort_order) VALUES
-- إدارة المرافق
('facilities_view', 'عرض المرافق', 'عرض المرافق العامة', 'maps', 'facilities', 'modules/facilities_management.php', '🏢', 500),
('facilities_add', 'إضافة مرفق', 'إضافة مرفق عام جديد', 'maps', 'facilities', 'modules/facilities_management.php', '➕', 501),
('facilities_edit', 'تعديل مرفق', 'تعديل معلومات المرافق', 'maps', 'facilities', 'modules/facilities_management.php', '✏️', 502),
('facilities_delete', 'حذف مرفق', 'حذف مرفق عام', 'maps', 'facilities', 'modules/facilities_management.php', '🗑️', 503),

-- فئات المرافق
('facility_categories_manage', 'إدارة فئات المرافق', 'إدارة تصنيفات المرافق', 'maps', 'facilities', 'modules/facilities_categories.php', '📂', 510),

-- إعدادات الخريطة
('map_settings_manage', 'إدارة إعدادات الخريطة', 'إدارة إعدادات الخرائط التفاعلية', 'maps', 'maps', 'modules/map_settings.php', '🗺️', 520);

-- ═══════════════════════════════════════════════════════════════════════════════
-- الفئة 7: 🌐 الموقع والاتصالات (website)
-- ═══════════════════════════════════════════════════════════════════════════════

INSERT INTO permissions (permission_key, display_name, description, category, module_name, page_url, icon, sort_order) VALUES
-- إدارة الموقع العام
('website_view', 'عرض محتوى الموقع', 'عرض محتوى الموقع العام', 'website', 'website', 'modules/public_content_management.php', '🌐', 600),
('website_edit', 'تعديل محتوى الموقع', 'تعديل محتوى الموقع العام', 'website', 'website', 'modules/public_content_management.php', '✏️', 601),

-- اتصل بنا
('contact_view', 'عرض رسائل اتصل بنا', 'عرض رسائل المواطنين', 'website', 'website', 'modules/contact_management.php', '📞', 610),
('contact_reply', 'الرد على رسائل اتصل بنا', 'الرد على استفسارات المواطنين', 'website', 'website', 'modules/contact_management.php', '✉️', 611),

-- Telegram
('telegram_view', 'عرض رسائل Telegram', 'عرض رسائل Telegram الواردة', 'website', 'telegram', 'modules/telegram_messages.php', '✈️', 620),
('telegram_send', 'إرسال رسائل Telegram', 'إرسال رسائل عبر Telegram', 'website', 'telegram', 'modules/telegram_messages.php', '📤', 621),
('telegram_settings', 'إعدادات Telegram', 'إدارة إعدادات Telegram Bot', 'website', 'telegram', 'modules/telegram_settings.php', '⚙️', 622),

-- الرسائل النصية SMS
('sms_view', 'عرض الرسائل النصية', 'عرض سجلات الرسائل النصية', 'website', 'sms', 'modules/sms.php', '📱', 630),
('sms_send', 'إرسال رسائل نصية', 'إرسال رسائل نصية للمواطنين', 'website', 'sms', 'modules/sms.php', '📤', 631);

-- ═══════════════════════════════════════════════════════════════════════════════
-- الفئة 8: 📊 التقارير والأرشفة (reports)
-- ═══════════════════════════════════════════════════════════════════════════════

INSERT INTO permissions (permission_key, display_name, description, category, module_name, page_url, icon, sort_order) VALUES
-- التقارير الموحدة
('reports_view', 'عرض التقارير', 'عرض جميع التقارير', 'reports', 'reports', 'modules/reports.php', '📊', 700),
('reports_financial', 'تقارير مالية', 'إنشاء وعرض التقارير المالية', 'reports', 'reports', 'modules/reports.php', '💰', 701),
('reports_administrative', 'تقارير إدارية', 'إنشاء وعرض التقارير الإدارية', 'reports', 'reports', 'modules/reports.php', '📋', 702),
('reports_service', 'تقارير الخدمات', 'إنشاء وعرض تقارير الخدمات', 'reports', 'reports', 'modules/reports.php', '🚚', 703),
('reports_export', 'تصدير التقارير', 'تصدير التقارير بصيغ مختلفة', 'reports', 'reports', 'modules/reports.php', '📥', 704),

-- الأرشيف الإلكتروني
('archive_view', 'عرض الأرشيف', 'عرض الملفات المؤرشفة', 'reports', 'archive', 'modules/archive.php', '📁', 710),
('archive_add', 'إضافة للأرشيف', 'إضافة ملفات للأرشيف', 'reports', 'archive', 'modules/archive.php', '➕', 711),
('archive_delete', 'حذف من الأرشيف', 'حذف ملفات من الأرشيف', 'reports', 'archive', 'modules/archive.php', '🗑️', 712),

-- Audit Log
('audit_log_view', 'عرض سجلات التدقيق', 'عرض سجلات النشاطات والتغييرات', 'reports', 'reports', NULL, '🔍', 720);

-- ═══════════════════════════════════════════════════════════════════════════════
-- الفئة 9: ⚙️ الإعدادات (settings)
-- ═══════════════════════════════════════════════════════════════════════════════

INSERT INTO permissions (permission_key, display_name, description, category, module_name, page_url, icon, sort_order) VALUES
-- إعدادات النظام
('settings_view', 'عرض إعدادات النظام', 'عرض إعدادات النظام العامة', 'settings', 'settings', 'modules/system_settings.php', '⚙️', 800),
('settings_manage', 'إدارة إعدادات النظام', 'تعديل إعدادات النظام', 'settings', 'settings', 'modules/system_settings.php', '🔧', 801),

-- الجداول المرجعية
('reference_tables_manage', 'إدارة الجداول المرجعية', 'إدارة البيانات المرجعية', 'settings', 'settings', 'all_tables_manager.php', '🗄️', 810);

-- ═══════════════════════════════════════════════════════════════════════════════
-- رسالة إتمام
-- ═══════════════════════════════════════════════════════════════════════════════

SELECT 'تم إنشاء جدول الصلاحيات المحسّن بنجاح!' as message;
SELECT CONCAT('إجمالي الصلاحيات: ', COUNT(*)) as total_permissions FROM permissions;
SELECT category as الفئة, COUNT(*) as عدد_الصلاحيات
FROM permissions
GROUP BY category
ORDER BY
    CASE category
        WHEN 'general_admin' THEN 1
        WHEN 'finance' THEN 2
        WHEN 'projects' THEN 3
        WHEN 'citizens' THEN 4
        WHEN 'services' THEN 5
        WHEN 'maps' THEN 6
        WHEN 'website' THEN 7
        WHEN 'reports' THEN 8
        WHEN 'settings' THEN 9
        ELSE 10
    END;
