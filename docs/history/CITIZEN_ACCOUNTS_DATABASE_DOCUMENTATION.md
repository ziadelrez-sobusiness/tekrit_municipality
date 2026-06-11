# 📚 توثيق قاعدة بيانات نظام الحساب الشخصي للمواطن

## 🏛️ بلدية تكريت - عكار، شمال لبنان

**التاريخ:** 2025-11-10  
**الإصدار:** 1.0  
**المطور:** AI Assistant

---

## 📋 جدول المحتويات

1. [نظرة عامة](#نظرة-عامة)
2. [الجداول الرئيسية](#الجداول-الرئيسية)
3. [العلاقات بين الجداول](#العلاقات-بين-الجداول)
4. [Views](#views)
5. [Stored Procedures](#stored-procedures)
6. [Triggers](#triggers)
7. [الإعدادات](#الإعدادات)
8. [التثبيت](#التثبيت)

---

## 🎯 نظرة عامة

نظام الحساب الشخصي للمواطن يتيح لكل مواطن:
- الدخول لحسابه الشخصي بسهولة
- متابعة جميع طلباته
- استقبال رسائل وإشعارات من البلدية
- التواصل مع البلدية
- استقبال إشعارات WhatsApp مجانية

---

## 📊 الجداول الرئيسية

### 1. `citizens_accounts` - حسابات المواطنين

**الوصف:** يحتوي على معلومات حسابات المواطنين الأساسية

| العمود | النوع | الوصف | ملاحظات |
|--------|------|-------|---------|
| `id` | INT | المعرف الفريد | Primary Key, Auto Increment |
| `phone` | VARCHAR(20) | رقم الهاتف | UNIQUE, NOT NULL |
| `name` | VARCHAR(100) | اسم المواطن | NOT NULL |
| `email` | VARCHAR(100) | البريد الإلكتروني | NULL (اختياري) |
| `address` | VARCHAR(255) | العنوان | NULL |
| `national_id` | VARCHAR(50) | الرقم الوطني | NULL |
| `whatsapp_notifications` | BOOLEAN | تفعيل WhatsApp | DEFAULT 1 |
| `website_notifications` | BOOLEAN | تفعيل إشعارات الموقع | DEFAULT 1 |
| `is_active` | BOOLEAN | الحساب نشط | DEFAULT 1 |
| `created_at` | TIMESTAMP | تاريخ الإنشاء | DEFAULT CURRENT_TIMESTAMP |
| `last_login` | TIMESTAMP | آخر تسجيل دخول | NULL |
| `login_count` | INT | عدد مرات الدخول | DEFAULT 0 |

**الفهارس:**
- `idx_phone` على `phone`
- `idx_active` على `is_active`
- `idx_created` على `created_at`

---

### 2. `magic_links` - روابط الدخول السحرية

**الوصف:** روابط فريدة للدخول السريع من WhatsApp

| العمود | النوع | الوصف | ملاحظات |
|--------|------|-------|---------|
| `id` | INT | المعرف الفريد | Primary Key |
| `citizen_id` | INT | معرف المواطن | Foreign Key |
| `token` | VARCHAR(64) | الرمز الفريد | UNIQUE, NOT NULL |
| `phone` | VARCHAR(20) | رقم الهاتف | NOT NULL |
| `used` | BOOLEAN | تم الاستخدام | DEFAULT 0 |
| `used_at` | TIMESTAMP | تاريخ الاستخدام | NULL |
| `ip_address` | VARCHAR(45) | عنوان IP | NULL |
| `user_agent` | TEXT | معلومات المتصفح | NULL |
| `expires_at` | TIMESTAMP | تاريخ الانتهاء | NOT NULL |
| `created_at` | TIMESTAMP | تاريخ الإنشاء | DEFAULT CURRENT_TIMESTAMP |

**الفهارس:**
- `idx_token` على `token`
- `idx_citizen` على `citizen_id`
- `idx_expires` على `expires_at`
- `idx_used` على `used`

**العلاقات:**
- `citizen_id` → `citizens_accounts(id)` ON DELETE CASCADE

---

### 3. `citizen_messages` - رسائل البلدية للمواطنين

**الوصف:** رسائل وإشعارات من البلدية للمواطنين

| العمود | النوع | الوصف | ملاحظات |
|--------|------|-------|---------|
| `id` | INT | المعرف الفريد | Primary Key |
| `citizen_id` | INT | معرف المواطن | NULL = رسالة عامة |
| `message_type` | ENUM | نوع الرسالة | عام، خاص، تحديث طلب، إشعار، تذكير |
| `title` | VARCHAR(200) | عنوان الرسالة | NOT NULL |
| `message` | TEXT | نص الرسالة | NOT NULL |
| `request_id` | INT | معرف الطلب | NULL |
| `priority` | ENUM | الأولوية | عادي، مهم، عاجل |
| `is_read` | BOOLEAN | تم القراءة | DEFAULT 0 |
| `read_at` | TIMESTAMP | تاريخ القراءة | NULL |
| `sent_via_whatsapp` | BOOLEAN | أرسل عبر WhatsApp | DEFAULT 0 |
| `whatsapp_sent_at` | TIMESTAMP | تاريخ إرسال WhatsApp | NULL |
| `created_by` | INT | معرف الموظف | NULL |
| `created_at` | TIMESTAMP | تاريخ الإنشاء | DEFAULT CURRENT_TIMESTAMP |

**الفهارس:**
- `idx_citizen` على `citizen_id`
- `idx_read` على `is_read`
- `idx_type` على `message_type`
- `idx_request` على `request_id`
- `idx_priority` على `priority`

**العلاقات:**
- `citizen_id` → `citizens_accounts(id)` ON DELETE CASCADE
- `request_id` → `citizen_requests(id)` ON DELETE SET NULL
- `created_by` → `users(id)` ON DELETE SET NULL

---

### 4. `whatsapp_log` - سجل رسائل WhatsApp

**الوصف:** سجل كامل لجميع رسائل WhatsApp المرسلة

| العمود | النوع | الوصف | ملاحظات |
|--------|------|-------|---------|
| `id` | INT | المعرف الفريد | Primary Key |
| `phone` | VARCHAR(20) | رقم المستلم | NOT NULL |
| `message` | TEXT | نص الرسالة | NOT NULL |
| `message_type` | VARCHAR(50) | نوع الرسالة | NULL |
| `request_id` | INT | معرف الطلب | NULL |
| `citizen_id` | INT | معرف المواطن | NULL |
| `status` | ENUM | حالة الإرسال | pending, sent, failed, delivered, read |
| `error_message` | TEXT | رسالة الخطأ | NULL |
| `sent_at` | TIMESTAMP | تاريخ الإرسال | NULL |
| `delivered_at` | TIMESTAMP | تاريخ التسليم | NULL |
| `read_at` | TIMESTAMP | تاريخ القراءة | NULL |
| `created_at` | TIMESTAMP | تاريخ الإنشاء | DEFAULT CURRENT_TIMESTAMP |

**الفهارس:**
- `idx_phone` على `phone`
- `idx_status` على `status`
- `idx_request` على `request_id`
- `idx_citizen` على `citizen_id`

---

### 5. `notification_preferences` - إعدادات الإشعارات

**الوصف:** تفضيلات كل مواطن للإشعارات

| العمود | النوع | الوصف | ملاحظات |
|--------|------|-------|---------|
| `id` | INT | المعرف الفريد | Primary Key |
| `citizen_id` | INT | معرف المواطن | UNIQUE |
| `whatsapp_enabled` | BOOLEAN | تفعيل WhatsApp | DEFAULT 1 |
| `website_enabled` | BOOLEAN | تفعيل الموقع | DEFAULT 1 |
| `notify_on_status_change` | BOOLEAN | إشعار تغيير الحالة | DEFAULT 1 |
| `notify_on_new_message` | BOOLEAN | إشعار رسالة جديدة | DEFAULT 1 |
| `notify_on_general_news` | BOOLEAN | إشعار أخبار عامة | DEFAULT 1 |
| `notify_on_completion` | BOOLEAN | إشعار الإنجاز | DEFAULT 1 |
| `notify_on_reminder` | BOOLEAN | إشعار التذكيرات | DEFAULT 1 |
| `updated_at` | TIMESTAMP | تاريخ التحديث | ON UPDATE CURRENT_TIMESTAMP |

---

### 6. `citizen_sessions` - جلسات المواطنين

**الوصف:** جلسات تسجيل الدخول النشطة

| العمود | النوع | الوصف | ملاحظات |
|--------|------|-------|---------|
| `id` | INT | المعرف الفريد | Primary Key |
| `citizen_id` | INT | معرف المواطن | NOT NULL |
| `session_token` | VARCHAR(64) | رمز الجلسة | UNIQUE |
| `ip_address` | VARCHAR(45) | عنوان IP | NULL |
| `user_agent` | TEXT | معلومات المتصفح | NULL |
| `last_activity` | TIMESTAMP | آخر نشاط | ON UPDATE CURRENT_TIMESTAMP |
| `expires_at` | TIMESTAMP | تاريخ الانتهاء | NOT NULL |
| `created_at` | TIMESTAMP | تاريخ الإنشاء | DEFAULT CURRENT_TIMESTAMP |

---

## 🔗 العلاقات بين الجداول

```
citizens_accounts (1) ←→ (∞) magic_links
citizens_accounts (1) ←→ (∞) citizen_messages
citizens_accounts (1) ←→ (∞) whatsapp_log
citizens_accounts (1) ←→ (1) notification_preferences
citizens_accounts (1) ←→ (∞) citizen_sessions

citizen_requests (1) ←→ (∞) citizen_messages
citizen_requests (1) ←→ (∞) whatsapp_log

users (1) ←→ (∞) citizen_messages
```

---

## 👁️ Views

### 1. `v_citizens_summary`

**الوصف:** ملخص شامل لكل مواطن مع إحصائياته

**الأعمدة:**
- معلومات المواطن الأساسية
- `total_requests` - إجمالي الطلبات
- `new_requests` - الطلبات الجديدة
- `active_requests` - الطلبات النشطة
- `completed_requests` - الطلبات المكتملة
- `total_messages` - إجمالي الرسائل
- `unread_messages` - الرسائل غير المقروءة

**الاستخدام:**
```sql
SELECT * FROM v_citizens_summary WHERE phone = '96103123456';
```

---

### 2. `v_citizen_messages_detailed`

**الوصف:** رسائل المواطنين مع تفاصيل كاملة

**الأعمدة:**
- جميع أعمدة `citizen_messages`
- `citizen_name` - اسم المواطن
- `citizen_phone` - رقم الهاتف
- `sender_name` - اسم الموظف المرسل
- `tracking_number` - رقم التتبع
- `request_title` - عنوان الطلب

---

### 3. `v_whatsapp_log_detailed`

**الوصف:** سجل WhatsApp مع تفاصيل المواطن والطلب

**الأعمدة:**
- جميع أعمدة `whatsapp_log`
- `citizen_name` - اسم المواطن
- `tracking_number` - رقم التتبع
- `request_title` - عنوان الطلب
- `request_status` - حالة الطلب

---

## 🔧 Stored Procedures

### 1. `sp_get_or_create_citizen_account`

**الوصف:** إنشاء حساب جديد أو جلب الحساب الموجود

**المدخلات:**
- `p_phone` - رقم الهاتف
- `p_name` - الاسم
- `p_email` - البريد الإلكتروني
- `p_address` - العنوان
- `p_national_id` - الرقم الوطني

**المخرجات:**
- `citizen_id` - معرف المواطن

**الاستخدام:**
```sql
CALL sp_get_or_create_citizen_account('96103123456', 'أحمد محمد', 'ahmad@example.com', 'تكريت', '123456789');
```

---

### 2. `sp_cleanup_expired_links`

**الوصف:** تنظيف الروابط والجلسات المنتهية

**المخرجات:**
- `deleted_records` - عدد السجلات المحذوفة

**الاستخدام:**
```sql
CALL sp_cleanup_expired_links();
```

**ملاحظة:** يُنصح بتشغيله يومياً عبر Cron Job

---

### 3. `sp_get_citizen_stats`

**الوصف:** إحصائيات تفصيلية لمواطن معين

**المدخلات:**
- `p_citizen_id` - معرف المواطن

**المخرجات:**
- `total_requests` - إجمالي الطلبات
- `new_requests` - الطلبات الجديدة
- `active_requests` - الطلبات النشطة
- `completed_requests` - الطلبات المكتملة
- `total_messages` - إجمالي الرسائل
- `unread_messages` - الرسائل غير المقروءة
- `avg_completion_days` - متوسط أيام الإنجاز

**الاستخدام:**
```sql
CALL sp_get_citizen_stats(1);
```

---

## ⚡ Triggers

### 1. `tr_update_login_count`

**الحدث:** AFTER INSERT على `citizen_sessions`

**الوظيفة:** تحديث `last_login` و `login_count` في جدول `citizens_accounts`

---

### 2. `tr_log_citizen_message`

**الحدث:** AFTER INSERT على `citizen_messages`

**الوظيفة:** إنشاء سجل في `whatsapp_log` إذا كان WhatsApp مفعل

---

## ⚙️ الإعدادات في `website_settings`

| المفتاح | القيمة الافتراضية | الوصف |
|---------|-------------------|-------|
| `whatsapp_enabled` | 1 | تفعيل/تعطيل WhatsApp |
| `whatsapp_business_number` | '' | رقم WhatsApp للبلدية |
| `whatsapp_api_method` | manual | طريقة الإرسال |
| `whatsapp_welcome_template` | نص الترحيب | قالب رسالة الترحيب |
| `whatsapp_status_update_template` | نص التحديث | قالب تحديث الحالة |
| `whatsapp_completion_template` | نص الإنجاز | قالب الإنجاز |
| `whatsapp_reminder_template` | نص التذكير | قالب التذكير |
| `whatsapp_general_message_template` | نص عام | قالب الرسائل العامة |
| `municipality_phone` | 06-123-456 | رقم هاتف البلدية |
| `municipality_whatsapp_name` | بلدية تكريت | اسم حساب WhatsApp |

---

## 🚀 التثبيت

### الطريقة 1: عبر واجهة الويب

1. افتح المتصفح وانتقل إلى:
   ```
   http://localhost:8080/tekrit_municipality/setup_citizen_accounts_system.php
   ```

2. اتبع التعليمات على الشاشة

3. تحقق من نجاح التثبيت

### الطريقة 2: عبر phpMyAdmin

1. افتح phpMyAdmin

2. اختر قاعدة البيانات `tekrit_municipality`

3. اذهب إلى تبويب "SQL"

4. افتح ملف `database/citizen_accounts_system.sql`

5. انسخ المحتوى والصقه في phpMyAdmin

6. اضغط "تنفيذ" (Go)

### الطريقة 3: عبر سطر الأوامر

```bash
mysql -u root -p tekrit_municipality < database/citizen_accounts_system.sql
```

---

## 🔒 الأمان

### إجراءات الأمان المطبقة:

1. **Magic Links:**
   - صلاحية محدودة (7 أيام افتراضياً)
   - استخدام لمرة واحدة
   - تسجيل IP و User Agent

2. **الجلسات:**
   - انتهاء تلقائي بعد فترة عدم نشاط
   - تسجيل كامل للنشاط

3. **البيانات الحساسة:**
   - تشفير كلمات المرور (إن وجدت)
   - عدم تخزين بيانات حساسة غير ضرورية

4. **الصلاحيات:**
   - Foreign Keys مع CASCADE/SET NULL
   - فهارس على الأعمدة الحساسة

---

## 📊 الصيانة

### مهام دورية موصى بها:

#### يومياً:
```sql
-- تنظيف الروابط والجلسات المنتهية
CALL sp_cleanup_expired_links();
```

#### أسبوعياً:
```sql
-- تحديث الإحصائيات
ANALYZE TABLE citizens_accounts, magic_links, citizen_messages, whatsapp_log;
```

#### شهرياً:
```sql
-- أرشفة الرسائل القديمة (اختياري)
DELETE FROM citizen_messages 
WHERE is_read = 1 
AND created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);

-- أرشفة سجل WhatsApp القديم
DELETE FROM whatsapp_log 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);
```

---

## 📞 الدعم

للمساعدة أو الاستفسارات:
- 📧 البريد الإلكتروني: support@tekrit.gov.lb
- 📱 الهاتف: 06-123-456
- 🏛️ العنوان: بلدية تكريت - عكار، شمال لبنان

---

**آخر تحديث:** 2025-11-10  
**الإصدار:** 1.0  
**الحالة:** ✅ جاهز للإنتاج

