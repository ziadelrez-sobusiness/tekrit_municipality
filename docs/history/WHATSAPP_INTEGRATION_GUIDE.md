# 📱 دليل تكامل WhatsApp مع نظام طلبات المواطنين

## 🎯 نظرة عامة

عندما يقوم المواطن بإنشاء طلب جديد، يحدث التالي **تلقائياً**:

1. ✅ يتم إنشاء الطلب في قاعدة البيانات
2. ✅ يتم إنشاء/تحديث حساب المواطن
3. ✅ يتم إنشاء رابط دخول سحري (Magic Link)
4. ✅ يتم تسجيل رسالة WhatsApp في قاعدة البيانات
5. 📱 يتم إرسال الرسالة للمواطن (حسب الطريقة المختارة)

---

## 📂 الملفات الجديدة

### 1. `includes/WhatsAppService.php`

**الوظيفة:** خدمة إرسال رسائل WhatsApp

**الميزات:**
- ✅ إرسال رسالة ترحيب عند إنشاء طلب جديد
- ✅ إرسال رسالة تحديث حالة الطلب
- ✅ إرسال رسالة إنجاز الطلب
- ✅ دعم 3 طرق للإرسال: يدوي، API، Webhook
- ✅ تسجيل جميع الرسائل في `whatsapp_log`
- ✅ استخدام قوالب قابلة للتخصيص

**الاستخدام:**
```php
require_once '../includes/WhatsAppService.php';

$whatsapp = new WhatsAppService($db);

// إرسال رسالة ترحيب
$result = $whatsapp->sendWelcomeMessage($citizenData, $requestData, $magicLink);
```

---

### 2. `includes/CitizenAccountHelper.php`

**الوظيفة:** إدارة حسابات المواطنين والروابط السحرية

**الميزات:**
- ✅ إنشاء أو جلب حساب مواطن تلقائياً
- ✅ إنشاء روابط دخول سحرية (Magic Links)
- ✅ دعم Stored Procedures أو SQL مباشر
- ✅ معالجة الأخطاء بشكل ذكي

**الاستخدام:**
```php
require_once '../includes/CitizenAccountHelper.php';

$helper = new CitizenAccountHelper($db);

// إنشاء/جلب حساب
$account = $helper->getOrCreateAccount($phone, $name, $email, $nationalId);

// إنشاء رابط سحري
$link = $helper->createMagicLink($account['citizen_id'], $phone, 24);
```

---

## 🔄 كيف يعمل النظام؟

### المرحلة 1: المواطن يقدم طلب

```
المواطن يملأ النموذج
        ↓
يضغط "إرسال الطلب"
        ↓
citizen-requests.php
```

### المرحلة 2: معالجة الطلب

```php
// في citizen-requests.php (بعد إنشاء الطلب)

// 1. إنشاء/جلب حساب المواطن
$helper = new CitizenAccountHelper($db);
$account = $helper->getOrCreateAccount(
    $citizen_phone, 
    $citizen_name, 
    $citizen_email, 
    $national_id
);

// 2. إنشاء رابط سحري
if ($account['success']) {
    $magicLink = $helper->createMagicLink(
        $account['citizen_id'], 
        $citizen_phone, 
        24  // صالح لمدة 24 ساعة
    );
}

// 3. إرسال رسالة WhatsApp
$whatsapp = new WhatsAppService($db);
$whatsappResult = $whatsapp->sendWelcomeMessage(
    [
        'name' => $citizen_name,
        'phone' => $citizen_phone,
        'citizen_id' => $account['citizen_id']
    ],
    [
        'request_id' => $request_id,
        'type_name' => $request_type_name,
        'tracking_number' => $tracking_number
    ],
    $magicLink['magic_link'] ?? null
);
```

### المرحلة 3: تسجيل في قاعدة البيانات

```sql
-- يتم تسجيل الرسالة في whatsapp_log
INSERT INTO whatsapp_log 
(phone, message, message_type, request_id, citizen_id, status) 
VALUES (?, ?, 'welcome', ?, ?, 'pending');
```

### المرحلة 4: الإرسال الفعلي

حسب الطريقة المختارة في `modules/whatsapp_settings.php`:

#### أ) الطريقة اليدوية (manual) - الافتراضية

```
الرسالة تُسجل في قاعدة البيانات
        ↓
الموظف يفتح لوحة التحكم
        ↓
يرى قائمة الرسائل المعلقة
        ↓
ينسخ الرسالة ويرسلها عبر WhatsApp Web
        ↓
يحدث حالة الرسالة إلى "sent"
```

#### ب) WhatsApp Business API (api)

```
الرسالة تُسجل في قاعدة البيانات
        ↓
يتم الاتصال بـ API تلقائياً
        ↓
يتم الإرسال عبر WhatsApp Business
        ↓
تحديث الحالة تلقائياً
```

#### ج) Webhook (webhook)

```
الرسالة تُسجل في قاعدة البيانات
        ↓
يتم إرسال POST request لـ Webhook
        ↓
الخدمة الخارجية تتولى الإرسال
        ↓
تحديث الحالة عبر Callback
```

---

## 📝 مثال على الرسالة المُرسلة

عندما يقدم المواطن "أحمد محمد" طلب "شهادة سكن"، يستلم:

```
مرحباً أحمد محمد!

✅ تم استلام طلبك بنجاح
📋 نوع الطلب: شهادة سكن
🔢 رقم التتبع: REQ-2025-12345
📅 التاريخ: 2025-11-11 15:30

🔐 للدخول لحسابك الشخصي:
👉 http://localhost:8080/tekrit_municipality/public/citizen-dashboard.php?token=abc123...

أو استخدم:
📱 الهاتف: 03123456
🔑 الرمز: 012345

━━━━━━━━━━━━━━━━━━━
💚 شكراً لثقتكم
🏛️ بلدية تكريت - في خدمتكم
```

---

## ⚙️ الإعدادات

### في `modules/whatsapp_settings.php`:

| الإعداد | الوصف | القيمة الافتراضية |
|---------|-------|-------------------|
| `whatsapp_enabled` | تفعيل/تعطيل WhatsApp | `1` (مفعل) |
| `whatsapp_business_number` | رقم WhatsApp للبلدية | فارغ |
| `whatsapp_api_method` | طريقة الإرسال | `manual` |
| `whatsapp_welcome_template` | قالب رسالة الترحيب | قالب افتراضي |
| `whatsapp_status_update_template` | قالب تحديث الحالة | قالب افتراضي |
| `whatsapp_completion_template` | قالب الإنجاز | قالب افتراضي |

---

## 🔧 التكامل مع citizen-requests.php

### الكود المطلوب إضافته:

بعد السطر 126 في `citizen-requests.php` (بعد `$db->commit();`):

```php
// إضافة هذا الكود بعد نجاح إنشاء الطلب
try {
    // تحميل المكتبات المطلوبة
    require_once '../includes/CitizenAccountHelper.php';
    require_once '../includes/WhatsAppService.php';
    
    // إنشاء/جلب حساب المواطن
    $accountHelper = new CitizenAccountHelper($db);
    $accountResult = $accountHelper->getOrCreateAccount(
        $citizen_phone,
        $citizen_name,
        $citizen_email,
        $national_id
    );
    
    // إنشاء رابط سحري
    $magicLink = null;
    if ($accountResult['success'] && $accountResult['citizen_id']) {
        $linkResult = $accountHelper->createMagicLink(
            $accountResult['citizen_id'],
            $citizen_phone,
            24 // صالح لمدة 24 ساعة
        );
        
        if ($linkResult['success']) {
            $magicLink = $linkResult['magic_link'];
        }
    }
    
    // الحصول على اسم نوع الطلب
    $typeStmt = $db->prepare("SELECT type_name FROM request_types WHERE id = ?");
    $typeStmt->execute([$request_type_id]);
    $typeData = $typeStmt->fetch(PDO::FETCH_ASSOC);
    $requestTypeName = $typeData['type_name'] ?? 'طلب';
    
    // إرسال رسالة WhatsApp
    $whatsappService = new WhatsAppService($db);
    $whatsappResult = $whatsappService->sendWelcomeMessage(
        [
            'name' => $citizen_name,
            'phone' => $citizen_phone,
            'citizen_id' => $accountResult['citizen_id'] ?? null
        ],
        [
            'request_id' => $request_id,
            'type_name' => $requestTypeName,
            'tracking_number' => $tracking_number
        ],
        $magicLink
    );
    
    // تحديث رسالة النجاح
    if ($whatsappResult['success']) {
        $success_message .= "<br>📱 سيتم إرسال تفاصيل الطلب على WhatsApp";
    }
    
} catch (Exception $e) {
    // تسجيل الخطأ لكن لا نعرض للمستخدم
    error_log("WhatsApp Integration Error: " . $e->getMessage());
}
```

---

## 📊 جداول قاعدة البيانات المستخدمة

### 1. `citizens_accounts`
- تخزين معلومات حسابات المواطنين

### 2. `magic_links`
- تخزين روابط الدخول السحرية

### 3. `whatsapp_log`
- تسجيل جميع رسائل WhatsApp المُرسلة

### 4. `notification_preferences`
- إعدادات الإشعارات لكل مواطن

### 5. `website_settings`
- إعدادات WhatsApp العامة

---

## 🧪 الاختبار

### 1. اختبار إنشاء حساب:

```php
$helper = new CitizenAccountHelper($db);
$result = $helper->getOrCreateAccount('03123456', 'اختبار', 'test@test.com', null);
print_r($result);
```

### 2. اختبار إنشاء رابط سحري:

```php
$link = $helper->createMagicLink(1, '03123456', 24);
print_r($link);
```

### 3. اختبار إرسال رسالة:

```php
$whatsapp = new WhatsAppService($db);
$result = $whatsapp->sendWelcomeMessage(
    ['name' => 'اختبار', 'phone' => '03123456', 'citizen_id' => 1],
    ['request_id' => 1, 'type_name' => 'اختبار', 'tracking_number' => 'TEST-001'],
    'http://test.com'
);
print_r($result);
```

---

## 🎯 الخطوات التالية

1. ✅ تثبيت Stored Procedures (إذا لم يتم بعد)
2. ✅ تحديث `citizen-requests.php` بالكود أعلاه
3. ✅ تكوين إعدادات WhatsApp في `modules/whatsapp_settings.php`
4. ✅ اختبار النظام بإنشاء طلب تجريبي
5. ✅ مراجعة الرسائل في `whatsapp_log`

---

## 📞 طرق الإرسال المتاحة

### الطريقة 1: يدوي (Manual) - مجاني 🆓

**المميزات:**
- ✅ مجاني 100%
- ✅ لا يحتاج إعداد تقني
- ✅ يستخدم WhatsApp Web العادي

**العيوب:**
- ❌ يتطلب تدخل يدوي
- ❌ غير تلقائي

**كيفية الاستخدام:**
1. افتح لوحة التحكم
2. اذهب إلى "رسائل WhatsApp المعلقة"
3. انسخ الرسالة
4. افتح WhatsApp Web
5. الصق وأرسل
6. حدّث الحالة إلى "sent"

---

### الطريقة 2: WhatsApp Business API - مدفوع 💰

**المميزات:**
- ✅ تلقائي 100%
- ✅ احترافي
- ✅ تقارير مفصلة

**العيوب:**
- ❌ يحتاج حساب WhatsApp Business
- ❌ يحتاج موافقة من Meta
- ❌ مدفوع

**التكلفة:** تختلف حسب البلد والحجم

---

### الطريقة 3: Webhook - مرن 🔧

**المميزات:**
- ✅ مرن جداً
- ✅ يمكن استخدام خدمات خارجية
- ✅ قابل للتخصيص

**العيوب:**
- ❌ يحتاج برمجة إضافية
- ❌ يعتمد على خدمة خارجية

---

## 🏛️ بلدية تكريت - عكار، شمال لبنان 🇱🇧

*نظام إدارة البلدية الإلكتروني*

