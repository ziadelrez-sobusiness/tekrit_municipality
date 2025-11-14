# 📋 خطة توحيد نظام المشاريع والمساهمات

## 📅 التاريخ: 3 نوفمبر 2025

---

## ❌ المشكلة الحالية:

### التكرار والتضارب في البيانات:

#### 1. **جدول `projects`** (modules/projects.php)
- يحتوي على: project_name, description, budget, contractor, donor_name, status...
- **الاستخدام**: المشاريع الداخلية
- **المشكلة**: لا يوجد ربط مالي كامل

#### 2. **جدول `development_projects`** (public_content_management.php)
- يحتوي على: project_name, project_cost, contributions_target, contributions_collected...
- **الاستخدام**: المشاريع المعروضة للعامة + المساهمات
- **المشكلة**: منفصل تماماً عن النظام المالي

#### 3. **النظام المالي الجديد** (projects_finance.php)
- يستخدم جدول `projects` مع إضافات: total_budget, spent_amount, association_id
- **الاستخدام**: التتبع المالي للمشاريع
- **المشكلة**: لا يتعامل مع المساهمات الشعبية

---

## 🎯 الهدف:

### نظام موحد يشمل:
✅ **المشاريع الداخلية** (تنفذها البلدية)
✅ **المشاريع الإنمائية** (للعرض العام)
✅ **المساهمات الشعبية** (جمع تبرعات)
✅ **الربط المالي الكامل** (ميزانية، إنفاق، إيرادات)
✅ **التقارير الشاملة** (مالية + تقدم + شفافية)

---

## 🏗️ الحل المقترح:

### المرحلة 1: توحيد الجداول

#### الجدول الموحد: `projects` (محسّن)

```sql
ALTER TABLE projects 
-- الحقول الأساسية (موجودة)
-- project_name, description, project_type, location, start_date, end_date
-- budget, budget_currency_id, status, contractor, manager_id

-- إضافة حقول المساهمات
ADD COLUMN allow_public_contributions TINYINT(1) DEFAULT 0 AFTER notes,
ADD COLUMN contributions_target DECIMAL(15,2) DEFAULT 0 AFTER allow_public_contributions,
ADD COLUMN contributions_collected DECIMAL(15,2) DEFAULT 0 AFTER contributions_target,
ADD COLUMN contributions_currency_id INT DEFAULT 1 AFTER contributions_collected,

-- إضافة حقول العرض العام
ADD COLUMN is_public TINYINT(1) DEFAULT 0 AFTER contributions_currency_id,
ADD COLUMN is_featured TINYINT(1) DEFAULT 0 AFTER is_public,
ADD COLUMN project_goal TEXT AFTER is_featured,
ADD COLUMN beneficiaries_count INT AFTER project_goal,
ADD COLUMN beneficiaries_description TEXT AFTER beneficiaries_count,

-- إضافة حقول الصور
ADD COLUMN main_image VARCHAR(500) AFTER beneficiaries_description,
ADD COLUMN gallery_images TEXT AFTER main_image,
ADD COLUMN before_images TEXT AFTER gallery_images,
ADD COLUMN after_images TEXT AFTER before_images,

-- الحقول المالية الموجودة (من النظام المالي)
-- association_id, total_budget, spent_amount

-- إضافة فهرس
ADD INDEX idx_is_public (is_public),
ADD INDEX idx_allow_contributions (allow_public_contributions);
```

---

### المرحلة 2: جدول المساهمات (جديد)

```sql
CREATE TABLE IF NOT EXISTS project_contributions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    contributor_name VARCHAR(255) NOT NULL,
    contributor_phone VARCHAR(50),
    contributor_email VARCHAR(100),
    contributor_address TEXT,
    contribution_amount DECIMAL(15,2) NOT NULL,
    currency_id INT NOT NULL,
    contribution_date DATE NOT NULL,
    payment_method ENUM('نقد', 'شيك', 'تحويل مصرفي', 'بطاقة ائتمان') DEFAULT 'نقد',
    receipt_number VARCHAR(100),
    notes TEXT,
    is_anonymous TINYINT(1) DEFAULT 0,
    is_verified TINYINT(1) DEFAULT 0,
    verified_by INT,
    verified_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (verified_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### المرحلة 3: الربط المالي التلقائي

#### عند إضافة مساهمة:
1. **تسجيل في `project_contributions`**
2. **تحديث `projects.contributions_collected`**
3. **إنشاء معاملة إيراد في `financial_transactions`**:
   ```php
   INSERT INTO financial_transactions 
   (type, category, description, amount, currency_id, related_project_id, status)
   VALUES 
   ('إيراد', 'مساهمات شعبية', 'مساهمة في مشروع: [اسم المشروع]', [المبلغ], [العملة], [رقم المشروع], 'معتمد');
   ```

#### عند إضافة مصروف للمشروع:
1. **تسجيل في `financial_transactions`**
2. **تحديث `projects.spent_amount`**
3. **تحديث `budget_items.spent_amount`** (إذا كان مرتبطاً ببند)

---

## 📱 واجهات المستخدم المطلوبة:

### 1. **صفحة إدارة المشاريع الموحدة** (modules/projects_unified.php)

#### الميزات:
- ✅ عرض جميع المشاريع (داخلية + إنمائية)
- ✅ إضافة/تعديل/حذف مشاريع
- ✅ تحديد نوع المشروع (داخلي / عام / كلاهما)
- ✅ إعدادات المساهمات (السماح، الهدف، الحالة)
- ✅ رفع الصور (رئيسية، معرض، قبل/بعد)
- ✅ الربط المالي (ميزانية، جمعية منفذة، بند ميزانية)
- ✅ تتبع التقدم والحالة
- ✅ إحصائيات شاملة

---

### 2. **صفحة المساهمات** (modules/contributions.php)

#### الميزات:
- ✅ عرض جميع المساهمات
- ✅ إضافة مساهمة يدوياً
- ✅ التحقق من المساهمات
- ✅ طباعة إيصالات
- ✅ إحصائيات المساهمين
- ✅ تقارير المساهمات حسب المشروع

---

### 3. **الصفحة العامة للمشاريع** (public/projects.php - محسّنة)

#### الميزات:
- ✅ عرض المشاريع العامة فقط (is_public = 1)
- ✅ إمكانية المساهمة أونلاين
- ✅ عرض نسبة التقدم
- ✅ معرض الصور
- ✅ قائمة المساهمين (غير المجهولين)

---

### 4. **لوحة التحكم المالية** (تحديث financial_dashboard.php)

#### إضافات جديدة:
- ✅ قسم "المساهمات الشعبية"
- ✅ إحصائيات المساهمات حسب المشروع
- ✅ نسبة تحقيق أهداف المساهمات
- ✅ ربط المساهمات بالإيرادات

---

## 🔄 خطة الترحيل (Migration):

### الخطوة 1: نقل البيانات من `development_projects` إلى `projects`

```sql
-- إضافة الحقول الجديدة أولاً (انظر المرحلة 1)

-- نقل البيانات
INSERT INTO projects 
(project_name, description, project_type, location, start_date, end_date, 
 budget, budget_currency_id, status, contractor, notes,
 allow_public_contributions, contributions_target, contributions_collected,
 is_public, is_featured, project_goal, beneficiaries_count, beneficiaries_description,
 main_image, gallery_images, before_images, after_images)
SELECT 
    project_name,
    project_description as description,
    'إنمائي' as project_type,
    project_location as location,
    start_date,
    end_date,
    project_cost as budget,
    1 as budget_currency_id, -- افتراضياً ليرة لبنانية
    CASE project_status
        WHEN 'مطروح' THEN 'مخطط'
        WHEN 'قيد التنفيذ' THEN 'قيد التنفيذ'
        WHEN 'منفذ' THEN 'مكتمل'
        WHEN 'متوقف' THEN 'متوقف'
        WHEN 'ملغي' THEN 'ملغي'
    END as status,
    contractor,
    NULL as notes,
    allow_contributions as allow_public_contributions,
    contributions_target,
    contributions_collected,
    1 as is_public,
    is_featured,
    project_goal,
    beneficiaries_count,
    beneficiaries_description,
    NULL as main_image, -- يمكن معالجة الصور لاحقاً
    project_images as gallery_images,
    before_images,
    after_images
FROM development_projects
WHERE id NOT IN (SELECT id FROM projects); -- تجنب التكرار
```

### الخطوة 2: نقل المساهمات (إذا كانت موجودة)

```sql
-- إذا كان هناك جدول مساهمات قديم
INSERT INTO project_contributions
(project_id, contributor_name, contribution_amount, ...)
SELECT ...
FROM old_contributions_table;
```

### الخطوة 3: التحقق والتنظيف

```sql
-- التحقق من البيانات
SELECT COUNT(*) FROM projects WHERE is_public = 1; -- المشاريع العامة
SELECT COUNT(*) FROM projects WHERE allow_public_contributions = 1; -- المشاريع التي تقبل مساهمات

-- بعد التأكد، يمكن حذف الجدول القديم (اختياري)
-- DROP TABLE development_projects;
```

---

## 📊 التقارير الجديدة:

### 1. تقرير المشاريع الشامل
- إجمالي المشاريع (حسب النوع، الحالة)
- المشاريع النشطة
- الميزانية الكلية vs المصروفات
- نسبة الإنجاز

### 2. تقرير المساهمات
- إجمالي المساهمات (حسب المشروع)
- عدد المساهمين
- متوسط المساهمة
- المساهمات حسب الفترة الزمنية

### 3. تقرير مالي موحد
- الإيرادات (مساهمات + تبرعات + دعم حكومي)
- المصروفات (حسب المشروع)
- الرصيد المتبقي لكل مشروع

---

## 🎯 الفوائد:

### 1. **توحيد البيانات**
- ✅ مصدر واحد للحقيقة
- ✅ عدم تكرار الإدخال
- ✅ تقليل الأخطاء

### 2. **الربط المالي الكامل**
- ✅ كل معاملة مالية مسجلة
- ✅ تتبع دقيق للإنفاق
- ✅ تقارير مالية دقيقة

### 3. **الشفافية**
- ✅ عرض المشاريع للعامة
- ✅ تتبع المساهمات
- ✅ نشر التقدم

### 4. **التكامل**
- ✅ ربط مع الميزانيات
- ✅ ربط مع الموردين
- ✅ ربط مع الجمعيات المنفذة

---

## 🔧 خطة التنفيذ:

### المرحلة 1: إعداد قاعدة البيانات (يوم 1)
- [ ] إضافة الحقول الجديدة لجدول `projects`
- [ ] إنشاء جدول `project_contributions`
- [ ] نقل البيانات من `development_projects`
- [ ] التحقق من البيانات

### المرحلة 2: تطوير الواجهات (يوم 2-3)
- [ ] إنشاء `modules/projects_unified.php`
- [ ] إنشاء `modules/contributions.php`
- [ ] تحديث `public/projects.php`
- [ ] تحديث `modules/financial_dashboard.php`

### المرحلة 3: الربط التلقائي (يوم 4)
- [ ] تطبيق الربط التلقائي للمساهمات
- [ ] تطبيق الربط التلقائي للمصروفات
- [ ] إنشاء Triggers إذا لزم الأمر

### المرحلة 4: الاختبار والتوثيق (يوم 5)
- [ ] اختبار شامل
- [ ] توثيق النظام الجديد
- [ ] تدريب المستخدمين

---

## ⚠️ ملاحظات مهمة:

### 1. **النسخ الاحتياطي**
⚠️ **ضروري**: عمل نسخة احتياطية كاملة قبل البدء!

### 2. **التوقيت**
📅 يُفضل التنفيذ في وقت الصيانة (خارج ساعات العمل)

### 3. **التوافقية**
✅ الحل متوافق مع النظام المالي الموجود
✅ لا يتطلب تغييرات جذرية

### 4. **المرونة**
✅ يمكن تنفيذ المراحل بشكل تدريجي
✅ يمكن الإبقاء على الجداول القديمة مؤقتاً

---

## 📞 الخطوات التالية:

### ما أحتاج موافقتك عليه:

1. **هل توافق على الخطة؟**
2. **هل تريد البدء فوراً؟**
3. **هل هناك متطلبات إضافية؟**

### إذا وافقت، سأبدأ فوراً بـ:
1. ✅ إنشاء سكريبت قاعدة البيانات
2. ✅ بناء صفحة المشاريع الموحدة
3. ✅ بناء صفحة المساهمات
4. ✅ تحديث الصفحات المالية

---

**جاهز للبدء! 🚀**

هل تريد أن أبدأ بتنفيذ هذه الخطة؟


