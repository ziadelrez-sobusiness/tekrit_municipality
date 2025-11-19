# تحديث نظام الصلاحيات - بلدية تكريت

## 📋 نظرة عامة

هذا التحديث يقوم بإعادة هيكلة كاملة لنظام الصلاحيات ليتطابق تماماً مع تقسيمات القائمة الرئيسية في `comprehensive_dashboard.php`.

## ✨ المميزات الجديدة

### 1. **تقسيم الصلاحيات إلى 9 فئات رئيسية**

| الفئة | الاسم بالعربية | عدد الصلاحيات |
|------|----------------|---------------|
| `general_admin` | 🏛️ الإدارة العامة | 13 صلاحية |
| `finance` | 💰 النظام المالي | 31 صلاحية |
| `projects` | 🏗️ المشاريع والعقود | 12 صلاحية |
| `citizens` | 👥 خدمات المواطنين | 18 صلاحية |
| `services` | 🚚 الخدمات والصيانة | 15 صلاحية |
| `maps` | 🗺️ الخرائط والمرافق | 6 صلاحيات |
| `website` | 🌐 الموقع والاتصالات | 8 صلاحيات |
| `reports` | 📊 التقارير والأرشفة | 9 صلاحيات |
| `settings` | ⚙️ الإعدادات | 3 صلاحيات |

**إجمالي: 115+ صلاحية شاملة**

### 2. **جدول Permissions المحسّن**

```sql
CREATE TABLE permissions (
    id                    INT PRIMARY KEY AUTO_INCREMENT,
    permission_key        VARCHAR(100) UNIQUE NOT NULL,
    display_name          VARCHAR(255) NOT NULL,
    description           TEXT,
    category              VARCHAR(50) NOT NULL,     -- الفئة الرئيسية
    module_name           VARCHAR(50),              -- الوحدة
    page_url              VARCHAR(255),             -- رابط الصفحة
    icon                  VARCHAR(20),              -- أيقونة emoji
    parent_permission_id  INT NULL,                 -- للصلاحيات الفرعية
    sort_order            INT DEFAULT 0,            -- ترتيب العرض
    is_active             TINYINT(1) DEFAULT 1,
    created_at            DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 3. **تحسينات صفحة الصلاحيات**

- ✅ قراءة الصلاحيات من قاعدة البيانات حسب الفئة
- ✅ ترتيب تلقائي للفئات (1-9)
- ✅ عرض عدد الصلاحيات في كل فئة (مثال: 5/10)
- ✅ ألوان مميزة لكل فئة
- ✅ قوالب صلاحيات جاهزة (مدير، محاسب، إلخ)
- ✅ نسخ الصلاحيات بين المستخدمين

## 🚀 خطوات التطبيق

### الخطوة 1: نسخ احتياطي (مهم جداً!)

```bash
# نسخ احتياطي لقاعدة البيانات
mysqldump -u root -p tekrit_municipality > backup_before_permissions_update_$(date +%Y%m%d_%H%M%S).sql
```

### الخطوة 2: تطبيق التحديث

```bash
cd /path/to/tekrit_municipality
mysql -u root -p tekrit_municipality < database/update_permissions_with_categories.sql
```

أو من phpMyAdmin:
1. افتح phpMyAdmin
2. اختر قاعدة بيانات `tekrit_municipality`
3. اذهب إلى تبويب "SQL"
4. افتح ملف `database/update_permissions_with_categories.sql`
5. انسخ المحتوى والصقه
6. اضغط "تنفيذ" أو "Go"

### الخطوة 3: التحقق من التطبيق

```sql
-- التحقق من عدد الصلاحيات
SELECT COUNT(*) as total FROM permissions;

-- عرض الصلاحيات حسب الفئة
SELECT category, COUNT(*) as count
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
    END;

-- عرض أول 10 صلاحيات
SELECT id, permission_key, display_name, category
FROM permissions
LIMIT 10;
```

**النتيجة المتوقعة:**
```
+------------------+-------+
| category         | count |
+------------------+-------+
| general_admin    |    13 |
| finance          |    31 |
| projects         |    12 |
| citizens         |    18 |
| services         |    15 |
| maps             |     6 |
| website          |     8 |
| reports          |     9 |
| settings         |     3 |
+------------------+-------+
```

## 📊 أمثلة على الصلاحيات الجديدة

### الإدارة العامة
```
- municipality_view       : عرض معلومات البلدية
- council_add             : إضافة عضو مجلس
- hr_salary_edit          : تعديل الرواتب
- permissions_manage      : إدارة الصلاحيات
```

### النظام المالي
```
- financial_dashboard_view: عرض لوحة التحكم المالية
- finance_add             : إضافة معاملة مالية
- budgets_edit            : تعديل ميزانية
- invoices_pay            : تسديد فاتورة
- tax_view                : عرض سجلات الجباية
```

### المشاريع والعقود
```
- projects_view           : عرض المشاريع
- projects_status_change  : تغيير حالة مشروع
- contracts_add           : إضافة عقد
- donors_view             : عرض المنظمات المانحة
```

### خدمات المواطنين
```
- citizens_view           : عرض بيانات المواطنين
- complaints_edit         : معالجة الشكاوى
- permits_approve         : الموافقة على رخص البناء
- violations_add          : إضافة مخالفة
```

### الخدمات والصيانة
```
- vehicles_view           : عرض الآليات
- maintenance_add         : إضافة سجل صيانة
- waste_edit              : إدارة جمع النفايات
- inventory_view          : عرض المخزون
```

## 🔧 استخدام القوالب الجاهزة

الصفحة تحتوي على 5 قوالب جاهزة للصلاحيات:

### 1. 👑 مدير النظام
```php
'admin' => [
    'permissions_manage', 'users_manage',
    'finance_view', 'finance_add', 'finance_edit', 'finance_delete',
    'budgets_view', 'budgets_add', 'budgets_edit',
    'projects_view', 'projects_add', 'projects_edit',
    'reports_view', 'settings_manage'
]
```

### 2. 💰 محاسب
```php
'accountant' => [
    'finance_view', 'finance_add', 'finance_edit',
    'budgets_view', 'budgets_add', 'budgets_edit',
    'suppliers_view', 'invoices_view', 'invoices_add',
    'reports_view'
]
```

### 3. 👔 مدير موارد بشرية
```php
'hr_manager' => [
    'hr_view', 'hr_add', 'hr_edit',
    'employees_view', 'employees_add', 'employees_edit',
    'reports_view'
]
```

### 4. 👥 مدير خدمات
```php
'service_manager' => [
    'citizens_view', 'complaints_view', 'complaints_edit',
    'permits_view', 'permits_edit',
    'waste_view', 'waste_edit',
    'reports_view'
]
```

### 5. 👁️ مراقب (عرض فقط)
```php
'viewer' => [
    'finance_view', 'budgets_view',
    'projects_view', 'reports_view'
]
```

## 🎯 الفرق بين النظام القديم والجديد

| الميزة | النظام القديم | النظام الجديد ✅ |
|-------|---------------|-----------------|
| عدد الصلاحيات | ~20 صلاحية | 115+ صلاحية |
| التقسيمات | غير منظمة | 9 فئات واضحة |
| الربط بالقائمة | لا يوجد | مطابق 100% |
| أيقونات | محدودة | emoji لكل صلاحية |
| قوالب جاهزة | ❌ | ✅ 5 قوالب |
| نسخ صلاحيات | ❌ | ✅ |
| فلترة حسب الفئة | ❌ | ✅ |
| ترتيب منطقي | ❌ | ✅ |

## 📝 ملاحظات مهمة

### ⚠️ تحذيرات

1. **النسخ الاحتياطي ضروري**: هذا السكربت يقوم بحذف جدول `permissions` القديم وإنشاؤه من جديد
2. **فقدان الصلاحيات الحالية**: جميع ربطات `user_permissions` القديمة ستُحذف
3. **التطبيق في بيئة التطوير أولاً**: اختبر السكربت على نسخة تجريبية قبل الإنتاج

### ✅ توصيات

1. قم بتطبيق التحديث في وقت هادئ (خارج ساعات العمل)
2. احتفظ بنسخة احتياطية لمدة 7 أيام على الأقل
3. أعد إعطاء الصلاحيات للمستخدمين بعد التطبيق
4. استخدم القوالب الجاهزة لتسريع العملية

## 🆘 استرجاع النسخة الاحتياطية

في حال حدوث أي مشكلة:

```bash
# إيقاف السيرفر (اختياري)
systemctl stop apache2

# استرجاع النسخة الاحتياطية
mysql -u root -p tekrit_municipality < backup_before_permissions_update_YYYYMMDD_HHMMSS.sql

# إعادة تشغيل السيرفر
systemctl start apache2
```

## 📞 الدعم

في حال واجهت أي مشكلة:
1. تحقق من ملف الـ error log: `/var/log/mysql/error.log`
2. تأكد من وجود جدول `users` في قاعدة البيانات
3. تحقق من صلاحيات المستخدم على قاعدة البيانات

---

**آخر تحديث:** 2025-11-19
**الإصدار:** 2.0
**المطور:** Claude Code
