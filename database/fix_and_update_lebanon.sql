-- سكريپت إصلاح قاعدة البيانات وتحديث العملات لبلدية تكريت عكار - لبنان
-- تاريخ الإنشاء: 2025-01-26

USE tekrit_municipality;

-- إضافة مستخدم افتراضي إذا لم يكن موجود (لحل مشكلة foreign key)
INSERT IGNORE INTO users (id, username, password, full_name, email, user_type, department, is_active) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدير النظام', 'admin@tekrit-akkar.gov.lb', 'admin', 'الإدارة العامة', 1);

-- تحديث العملات لتصبح الليرة اللبنانية العملة الأساسية
-- مسح العملات الحالية
DELETE FROM currencies;

-- إدراج العملات الجديدة مع الليرة اللبنانية كعملة أساسية
INSERT INTO currencies (id, currency_code, currency_name, currency_symbol, exchange_rate_to_iqd, is_active) VALUES
(1, 'LBP', 'الليرة اللبنانية', 'ل.ل', 1.0000, 1),
(2, 'USD', 'الدولار الأمريكي', '$', 90000.0000, 1),
(3, 'EUR', 'اليورو', '€', 98000.0000, 1),
(4, 'SAR', 'الريال السعودي', 'ر.س', 24000.0000, 1),
(5, 'AED', 'الدرهم الإماراتي', 'د.إ', 24500.0000, 1),
(6, 'TRY', 'الليرة التركية', '₺', 2700.0000, 1);

-- تحديث العمود المحسوب في جدول المعاملات المالية
-- إزالة العمود المحسوب القديم وإضافة الجديد
ALTER TABLE financial_transactions 
DROP COLUMN IF EXISTS amount_in_iqd;

ALTER TABLE financial_transactions 
ADD COLUMN amount_in_lbp DECIMAL(15,2) GENERATED ALWAYS AS (amount * exchange_rate) STORED;

-- تحديث العمود المحسوب في جدول عمليات الجباية
ALTER TABLE tax_collections 
DROP COLUMN IF EXISTS amount_in_iqd;

ALTER TABLE tax_collections 
ADD COLUMN amount_in_lbp DECIMAL(15,2) GENERATED ALWAYS AS (total_amount * exchange_rate) STORED;

-- تحديث العمود المحسوب في جدول الجهات المانحة
ALTER TABLE donor_organizations 
DROP COLUMN IF EXISTS total_donations_iqd;

ALTER TABLE donor_organizations 
ADD COLUMN total_donations_lbp DECIMAL(20,2) DEFAULT 0;

-- تحديث أنواع الجباية مع الليرة اللبنانية
UPDATE tax_types SET currency_id = 1 WHERE currency_id IS NULL OR currency_id = 0;

-- تحديث المشاريع
UPDATE projects SET budget_currency_id = 1 WHERE budget_currency_id IS NULL OR budget_currency_id = 0;
UPDATE projects SET actual_cost_currency_id = 1 WHERE actual_cost_currency_id IS NULL OR actual_cost_currency_id = 0;

-- تحديث التبرعات
UPDATE donations SET currency_id = 1 WHERE currency_id IS NULL OR currency_id = 0;
UPDATE donations SET estimated_value_currency_id = 1 WHERE estimated_value_currency_id IS NULL OR estimated_value_currency_id = 0;

-- تحديث المعاملات المالية
UPDATE financial_transactions SET currency_id = 1 WHERE currency_id IS NULL OR currency_id = 0;
UPDATE financial_transactions SET exchange_rate = 1.0000 WHERE exchange_rate IS NULL OR exchange_rate = 0;

-- تحديث عمليات الجباية
UPDATE tax_collections SET currency_id = 1 WHERE currency_id IS NULL OR currency_id = 0;
UPDATE tax_collections SET exchange_rate = 1.0000 WHERE exchange_rate IS NULL OR exchange_rate = 0;

-- تحديث الجهات المانحة
UPDATE donor_organizations SET preferred_currency_id = 1 WHERE preferred_currency_id IS NULL OR preferred_currency_id = 0;

-- تحديث مراحل المشاريع
UPDATE project_phases SET currency_id = 1 WHERE currency_id IS NULL OR currency_id = 0;

-- تحديث حملات التبرع
UPDATE donation_campaigns SET target_currency_id = 1 WHERE target_currency_id IS NULL OR target_currency_id = 0;
UPDATE donation_campaigns SET raised_currency_id = 1 WHERE raised_currency_id IS NULL OR raised_currency_id = 0;

-- تحديث رخص البناء
UPDATE building_permits SET currency_id = 1 WHERE currency_id IS NULL OR currency_id = 0;

-- إضافة بيانات تجريبية محدثة لأنواع الجباية اللبنانية
INSERT IGNORE INTO tax_types (tax_code, tax_name, category, description, base_amount, currency_id, created_by_user_id) VALUES
('RES001_LB', 'رسوم رخصة بناء', 'تراخيص', 'رسوم استخراج رخصة بناء للمنازل السكنية', 2000000, 1, 1),
('RES002_LB', 'رسوم النظافة', 'رسوم خدمات', 'رسوم خدمات النظافة الشهرية', 500000, 1, 1),
('TAX001_LB', 'ضريبة المحلات التجارية', 'ضرائب', 'ضريبة سنوية على المحلات التجارية', 5000000, 1, 1),
('FEE001_LB', 'رسوم إشغال طريق', 'إشغالات', 'رسوم إشغال الطريق العام للأنشطة التجارية', 750000, 1, 1),
('FIN001_LB', 'غرامة مخالفة بناء', 'غرامات', 'غرامة مالية للبناء بدون ترخيص', 10000000, 1, 1);

-- إضافة بيانات تجريبية للمواطنين في عكار
INSERT IGNORE INTO citizens (citizen_number, full_name, gender, district, area, phone, verification_status) VALUES
('12345678901', 'أحمد محمد علي الكردي', 'ذكر', 'عكار', 'تكريت', '07701234567', 'مؤكد'),
('12345678902', 'فاطمة خالد حسن التكريتي', 'أنثى', 'عكار', 'تكريت', '07709876543', 'مؤكد'),
('12345678903', 'عبد الله سعد جبار المحمود', 'ذكر', 'عكار', 'تكريت', '07712345678', 'غير مؤكد'),
('12345678904', 'زينب عباس حميد العبيد', 'أنثى', 'عكار', 'تكريت', '07798765432', 'مؤكد'),
('12345678905', 'حسام الدين طارق نوري الدليمي', 'ذكر', 'عكار', 'تكريت', '07787654321', 'قيد المراجعة');

COMMIT;

-- رسائل النجاح
SELECT 'تم تحديث العملات بنجاح - الليرة اللبنانية هي العملة الأساسية الآن' as Success_Message; 