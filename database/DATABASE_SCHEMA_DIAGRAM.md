# 📊 مخطط قاعدة البيانات - نظام الحساب الشخصي للمواطن

## 🏗️ البنية العامة

```
┌─────────────────────────────────────────────────────────────────────┐
│                    نظام الحساب الشخصي للمواطن                      │
│                    بلدية تكريت - عكار، لبنان                       │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 🔗 العلاقات الرئيسية

```
                    ┌──────────────────────┐
                    │  citizens_accounts   │
                    │  ════════════════    │
                    │  • id (PK)           │
                    │  • phone (UNIQUE)    │
                    │  • name              │
                    │  • email             │
                    │  • whatsapp_enabled  │
                    │  • is_active         │
                    │  • last_login        │
                    │  • login_count       │
                    └──────────┬───────────┘
                               │
                ┌──────────────┼──────────────┐
                │              │              │
                ▼              ▼              ▼
    ┌───────────────┐  ┌──────────────┐  ┌─────────────────┐
    │  magic_links  │  │   citizen_   │  │  notification_  │
    │  ═══════════  │  │   sessions   │  │  preferences    │
    │  • id (PK)    │  │  ══════════  │  │  ═════════════  │
    │  • citizen_id │  │  • id (PK)   │  │  • id (PK)      │
    │  • token      │  │  • citizen_id│  │  • citizen_id   │
    │  • used       │  │  • session_  │  │  • whatsapp_    │
    │  • expires_at │  │    token     │  │    enabled      │
    └───────────────┘  │  • expires_at│  │  • notify_on_*  │
                       └──────────────┘  └─────────────────┘
                               │
                               ▼
                    ┌──────────────────┐
                    │ citizen_messages │
                    │ ════════════════ │
                    │ • id (PK)        │
                    │ • citizen_id     │◄─────┐
                    │ • request_id     │      │
                    │ • message_type   │      │
                    │ • title          │      │
                    │ • message        │      │
                    │ • is_read        │      │
                    │ • priority       │      │
                    │ • created_by     │      │
                    └──────────┬───────┘      │
                               │              │
                               ▼              │
                    ┌──────────────────┐      │
                    │  whatsapp_log    │      │
                    │  ══════════════  │      │
                    │  • id (PK)       │      │
                    │  • citizen_id    │──────┘
                    │  • request_id    │
                    │  • phone         │
                    │  • message       │
                    │  • status        │
                    │  • sent_at       │
                    │  • delivered_at  │
                    └──────────────────┘
```

---

## 📋 الجداول الموجودة مسبقاً (الربط)

```
┌──────────────────┐         ┌─────────────────┐
│ citizen_requests │◄────────┤ citizen_messages│
│ ════════════════ │         │ ═══════════════ │
│ • id (PK)        │         │ • request_id    │
│ • tracking_number│         └─────────────────┘
│ • citizen_phone  │
│ • status         │         ┌─────────────────┐
│ • request_type   │◄────────┤  whatsapp_log   │
└──────────────────┘         │ ═══════════════ │
                             │ • request_id    │
┌──────────────────┐         └─────────────────┘
│      users       │
│ ════════════════ │         ┌─────────────────┐
│ • id (PK)        │◄────────┤ citizen_messages│
│ • username       │         │ ═══════════════ │
│ • full_name      │         │ • created_by    │
└──────────────────┘         └─────────────────┘
```

---

## 🔄 دورة حياة المواطن في النظام

```
1. تقديم الطلب الأول
   │
   ▼
┌──────────────────────────────────────┐
│ إنشاء حساب تلقائي في                 │
│ citizens_accounts                    │
│ (phone, name, email)                 │
└──────────────┬───────────────────────┘
               │
               ▼
┌──────────────────────────────────────┐
│ إنشاء إعدادات افتراضية في            │
│ notification_preferences             │
└──────────────┬───────────────────────┘
               │
               ▼
┌──────────────────────────────────────┐
│ إرسال رسالة WhatsApp ترحيبية         │
│ + Magic Link للدخول                 │
└──────────────┬───────────────────────┘
               │
               ▼
┌──────────────────────────────────────┐
│ تسجيل في whatsapp_log                │
│ (status: pending → sent → delivered) │
└──────────────┬───────────────────────┘
               │
               ▼
2. المواطن يضغط على Magic Link
   │
   ▼
┌──────────────────────────────────────┐
│ التحقق من magic_links                │
│ (token valid? not used? not expired?)│
└──────────────┬───────────────────────┘
               │
               ▼
┌──────────────────────────────────────┐
│ إنشاء جلسة في citizen_sessions       │
│ (session_token, expires_at)          │
└──────────────┬───────────────────────┘
               │
               ▼
┌──────────────────────────────────────┐
│ تحديث last_login و login_count       │
│ في citizens_accounts                 │
│ (via Trigger)                        │
└──────────────┬───────────────────────┘
               │
               ▼
3. المواطن يدخل لحسابه الشخصي
   │
   ▼
┌──────────────────────────────────────┐
│ عرض جميع الطلبات من                  │
│ citizen_requests                     │
│ (WHERE citizen_phone = phone)        │
└──────────────┬───────────────────────┘
               │
               ▼
┌──────────────────────────────────────┐
│ عرض الرسائل من                       │
│ citizen_messages                     │
│ (WHERE citizen_id = id)              │
└──────────────────────────────────────┘
```

---

## 📨 دورة إرسال رسالة WhatsApp

```
1. موظف يرسل رسالة/تحديث
   │
   ▼
┌──────────────────────────────────────┐
│ INSERT INTO citizen_messages         │
│ (citizen_id, message, sent_via_      │
│  whatsapp = 1)                       │
└──────────────┬───────────────────────┘
               │
               ▼ (Trigger تلقائي)
┌──────────────────────────────────────┐
│ tr_log_citizen_message               │
│ يتحقق من إعدادات المواطن             │
└──────────────┬───────────────────────┘
               │
               ▼
┌──────────────────────────────────────┐
│ INSERT INTO whatsapp_log             │
│ (phone, message, status: pending)    │
└──────────────┬───────────────────────┘
               │
               ▼
2. Cron Job أو Script يدوي
   │
   ▼
┌──────────────────────────────────────┐
│ SELECT * FROM whatsapp_log           │
│ WHERE status = 'pending'             │
└──────────────┬───────────────────────┘
               │
               ▼
┌──────────────────────────────────────┐
│ إرسال عبر WhatsApp API/Manual        │
└──────────────┬───────────────────────┘
               │
               ▼
┌──────────────────────────────────────┐
│ UPDATE whatsapp_log                  │
│ SET status = 'sent', sent_at = NOW() │
└──────────────────────────────────────┘
```

---

## 🔐 دورة Magic Link

```
1. إنشاء Magic Link
   │
   ▼
┌──────────────────────────────────────┐
│ INSERT INTO magic_links              │
│ • token = random_bytes(32)           │
│ • expires_at = NOW() + 7 days        │
│ • used = 0                           │
└──────────────┬───────────────────────┘
               │
               ▼
┌──────────────────────────────────────┐
│ إرسال الرابط عبر WhatsApp             │
│ https://domain.com/login?token=XXX   │
└──────────────┬───────────────────────┘
               │
               ▼
2. المواطن يضغط على الرابط
   │
   ▼
┌──────────────────────────────────────┐
│ SELECT * FROM magic_links            │
│ WHERE token = ? AND used = 0         │
│ AND expires_at > NOW()               │
└──────────────┬───────────────────────┘
               │
               ├─ ✅ صالح
               │   │
               │   ▼
               │ ┌────────────────────┐
               │ │ UPDATE magic_links │
               │ │ SET used = 1       │
               │ │ used_at = NOW()    │
               │ └────────┬───────────┘
               │          │
               │          ▼
               │ ┌────────────────────┐
               │ │ إنشاء جلسة جديدة   │
               │ │ في citizen_sessions│
               │ └────────────────────┘
               │
               └─ ❌ غير صالح
                   │
                   ▼
                 ┌────────────────────┐
                 │ رسالة خطأ           │
                 │ "الرابط منتهي"     │
                 └────────────────────┘
```

---

## 🧹 دورة التنظيف التلقائي

```
Cron Job يومي
   │
   ▼
┌──────────────────────────────────────┐
│ CALL sp_cleanup_expired_links()      │
└──────────────┬───────────────────────┘
               │
               ├─────────────────┐
               │                 │
               ▼                 ▼
┌──────────────────────┐  ┌─────────────────────┐
│ DELETE FROM          │  │ DELETE FROM         │
│ magic_links          │  │ citizen_sessions    │
│ WHERE expires_at     │  │ WHERE expires_at    │
│ < NOW()              │  │ < NOW()             │
│ OR (used = 1 AND     │  └─────────────────────┘
│ used_at < NOW()-30d) │
└──────────────────────┘
```

---

## 📊 Views للتقارير

```
┌─────────────────────────────────────────────────────┐
│              v_citizens_summary                     │
│  ═══════════════════════════════════════════════   │
│  يجمع بيانات من:                                   │
│  • citizens_accounts                                │
│  • citizen_requests (COUNT)                         │
│  • citizen_messages (COUNT)                         │
│                                                     │
│  النتيجة:                                           │
│  • إجمالي الطلبات لكل مواطن                         │
│  • الطلبات الجديدة/النشطة/المكتملة                  │
│  • إجمالي الرسائل/غير المقروءة                      │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│         v_citizen_messages_detailed                 │
│  ═══════════════════════════════════════════════   │
│  يجمع بيانات من:                                   │
│  • citizen_messages                                 │
│  • citizens_accounts (name, phone)                  │
│  • users (sender name)                              │
│  • citizen_requests (tracking_number, title)        │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│          v_whatsapp_log_detailed                    │
│  ═══════════════════════════════════════════════   │
│  يجمع بيانات من:                                   │
│  • whatsapp_log                                     │
│  • citizens_accounts (name)                         │
│  • citizen_requests (tracking, title, status)       │
└─────────────────────────────────────────────────────┘
```

---

## 🎯 الفهارس (Indexes) لتحسين الأداء

```
citizens_accounts:
├── PRIMARY KEY (id)
├── UNIQUE KEY (phone)
├── INDEX (is_active)
└── INDEX (created_at)

magic_links:
├── PRIMARY KEY (id)
├── UNIQUE KEY (token)
├── INDEX (citizen_id)
├── INDEX (expires_at)
└── INDEX (used)

citizen_messages:
├── PRIMARY KEY (id)
├── INDEX (citizen_id)
├── INDEX (is_read)
├── INDEX (message_type)
├── INDEX (request_id)
├── INDEX (priority)
└── INDEX (created_at)

whatsapp_log:
├── PRIMARY KEY (id)
├── INDEX (phone)
├── INDEX (status)
├── INDEX (request_id)
├── INDEX (citizen_id)
└── INDEX (created_at)

citizen_requests (إضافات جديدة):
├── INDEX (phone, tracking_number)
└── INDEX (status, created_at)
```

---

## 🔒 Foreign Keys (العلاقات)

```
magic_links.citizen_id
  └─→ citizens_accounts.id (ON DELETE CASCADE)

citizen_messages.citizen_id
  └─→ citizens_accounts.id (ON DELETE CASCADE)

citizen_messages.request_id
  └─→ citizen_requests.id (ON DELETE SET NULL)

citizen_messages.created_by
  └─→ users.id (ON DELETE SET NULL)

whatsapp_log.citizen_id
  └─→ citizens_accounts.id (ON DELETE SET NULL)

whatsapp_log.request_id
  └─→ citizen_requests.id (ON DELETE SET NULL)

notification_preferences.citizen_id
  └─→ citizens_accounts.id (ON DELETE CASCADE)

citizen_sessions.citizen_id
  └─→ citizens_accounts.id (ON DELETE CASCADE)
```

---

## 📈 إحصائيات الجداول

| الجدول | الأعمدة | الفهارس | العلاقات | الحجم المتوقع |
|--------|---------|---------|-----------|---------------|
| `citizens_accounts` | 12 | 4 | 0 (parent) | متوسط |
| `magic_links` | 10 | 5 | 1 | كبير (تنظيف دوري) |
| `citizen_messages` | 13 | 7 | 3 | كبير |
| `whatsapp_log` | 12 | 6 | 2 | كبير جداً |
| `notification_preferences` | 9 | 1 | 1 | صغير |
| `citizen_sessions` | 8 | 4 | 1 | متوسط (تنظيف دوري) |

---

## 🎨 ألوان الحالات

```
whatsapp_log.status:
┌─────────┬──────────┬─────────────────┐
│ pending │ ⏳ أصفر  │ في الانتظار     │
│ sent    │ 📤 أزرق  │ تم الإرسال      │
│ failed  │ ❌ أحمر  │ فشل             │
│ delivered│ ✅ أخضر │ تم التسليم      │
│ read    │ 👁️ أخضر │ تم القراءة      │
└─────────┴──────────┴─────────────────┘

citizen_messages.priority:
┌─────────┬──────────┬─────────────────┐
│ عادي    │ ⚪ رمادي │ أولوية عادية    │
│ مهم     │ 🟡 أصفر │ أولوية متوسطة   │
│ عاجل    │ 🔴 أحمر │ أولوية عالية    │
└─────────┴──────────┴─────────────────┘
```

---

**📅 آخر تحديث:** 2025-11-10  
**🏛️ بلدية تكريت - عكار، شمال لبنان** 🇱🇧

