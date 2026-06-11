# 📖 دليل استخدام نظام الروابط المهمة - بلدية تكريت-عكار

---

## ✅ ما تم إنجازه:

### 1. ✅ إنشاء ملف API المفقود
**الملف:** `public/api/get_important_links.php`

هذا الملف يسمح بجلب البيانات بصيغة JSON للصفحة العامة.

**الاستخدام:**
```
http://yoursite.com/public/api/get_important_links.php
http://yoursite.com/public/api/get_important_links.php?category=1
http://yoursite.com/public/api/get_important_links.php?search=مستشفى
http://yoursite.com/public/api/get_important_links.php?emergency=1
```

---

### 2. ✅ سكريبت اختبار شامل للمصادر
**الملف:** `modules/test_all_sources.php`

هذا السكريبت يختبر جميع المصادر ويعطيك تقرير مفصل.

**كيفية الاستخدام:**
1. افتح المتصفح
2. اذهب إلى: `http://yoursite.com/modules/test_all_sources.php`
3. سيظهر لك تقرير شامل لكل مصدر:
   - ✅ المصادر التي تعمل
   - ❌ المصادر المعطلة
   - ما هو الخطأ بالضبط
   - اقتراحات للإصلاح

---

### 3. ✅ تقرير تحليل مفصل
**الملف:** `SOURCES_ANALYSIS_REPORT.md`

يحتوي على تحليل تفصيلي لكل مصدر من الـ 4 مصادر الموجودة.

---

### 4. ✅ ملف إصلاح SQL
**الملف:** `database/fix_sources_config.sql`

يحتوي على استعلامات SQL لإصلاح المصادر.

**كيفية التطبيق:**
```bash
mysql -u root -p your_database < database/fix_sources_config.sql
```

---

## 📋 المصادر الموجودة وحالتها:

### 1. 🟡 دليل الحكومة اللبنانية - TRA
- **الحالة:** يحتاج إعداد
- **المشكلة:** يحتاج scraping selectors
- **الحل:** تم إضافة selectors افتراضية في ملف الإصلاح

### 2. 🔴 مستشفيات حكومية - Open Data Lebanon
- **الحالة:** معطل مؤقتاً
- **المشكلة:** الرابط يشير لصفحة ويب وليس ملف Excel
- **الحل المطلوب:** إيجاد رابط مباشر للـ Excel أو تحويله لـ scraping

### 3. 🟡 مستشفيات - وزارة الصحة
- **الحالة:** يحتاج تفعيل
- **المشكلة:** auto_import معطل + يحتاج selectors
- **الحل:** تم تفعيله وإضافة selectors في ملف الإصلاح

### 4. 🔴 السفارات في لبنان - AUB
- **الحالة:** معطل
- **المشكلة:** لا يوجد رابط على الإطلاق
- **الحل المطلوب:** إيجاد مصدر بيانات للسفارات

---

## 🚀 خطوات التشغيل:

### الخطوة 1: تطبيق إصلاحات قاعدة البيانات
```bash
cd /path/to/tekrit_municipality
mysql -u root -p tekrit_municipality < database/fix_sources_config.sql
```

### الخطوة 2: اختبار المصادر
1. افتح المتصفح
2. اذهب إلى: `http://localhost:8080/tekrit_municipality/modules/test_all_sources.php`
3. انتظر حتى ينتهي الاختبار (قد يستغرق دقائق)
4. راجع النتائج

### الخطوة 3: اختبار الصفحة العامة
1. اذهب إلى: `http://localhost:8080/tekrit_municipality/public/important-links.php`
2. اضغط على زر "⚡ جلب البيانات"
3. يجب أن تظهر البيانات المحدثة من قاعدة البيانات

### الخطوة 4: اختبار API مباشرة
افتح في المتصفح:
```
http://localhost:8080/tekrit_municipality/public/api/get_important_links.php
```

يجب أن ترى JSON يحتوي على:
```json
{
    "success": true,
    "data": {
        "links": [...],
        "categories": [...],
        "stats": {...}
    }
}
```

---

## 🔧 إصلاح المشاكل:

### إذا ظهرت أخطاء في الاختبار:

#### خطأ 404 - Not Found
**السبب:** الرابط غير موجود أو تغير
**الحل:**
1. افتح الرابط في المتصفح مباشرة
2. إذا كان الرابط تغير، حدّث قاعدة البيانات:
```sql
UPDATE important_link_sources 
SET api_url = 'الرابط_الجديد' 
WHERE id = X;
```

#### خطأ JSON - Not valid JSON
**السبب:** الاستجابة HTML وليست JSON
**الحل:** غيّر نوع المصدر من `api` إلى `scraping`:
```sql
UPDATE important_link_sources 
SET source_type = 'scraping', 
    fetch_method = 'html_scraper',
    scraping_url = api_url,
    api_url = NULL
WHERE id = X;
```

#### خطأ "لا توجد فئة"
**السبب:** المصدر لا يحتوي على category_id
**الحل:**
```sql
UPDATE important_link_sources 
SET category_id = 1  -- أو الفئة المناسبة
WHERE id = X;
```

---

## 📚 استخدام صفحة إدارة المصادر:

### الوصول للصفحة:
```
http://localhost:8080/tekrit_municipality/modules/important_links_sources_management.php
```

### الوظائف المتاحة:

#### 1. إضافة مصدر جديد
- اضغط "➕ إضافة مصدر جديد"
- املأ البيانات:
  - **الاسم:** اسم واضح للمصدر
  - **النوع:** API / Scraping / CSV / يدوي
  - **الرابط:** رابط المصدر
  - **الفئة الافتراضية:** اختر الفئة
  - **التكرار:** كم مرة يتم التحديث
  
#### 2. اختبار مصدر
- اضغط "🧪 اختبار مصدر"
- أدخل رابط API
- اضغط "اختبار"
- سيظهر لك النتيجة فوراً

#### 3. عرض الأمثلة
- اضغط "📚 أمثلة APIs"
- ستجد أمثلة جاهزة يمكنك إضافتها

#### 4. جلب من مصدر محدد
- في جدول المصادر، اضغط "⚡ جلب" بجانب أي مصدر
- سيتم جلب البيانات فوراً

---

## 💡 نصائح مهمة:

### 1. اختبار المصادر دورياً
قم باختبار المصادر مرة كل شهر للتأكد من أنها ما زالت تعمل:
```
http://localhost:8080/tekrit_municipality/modules/test_all_sources.php
```

### 2. مراجعة سجل الأخطاء
راجع جدول `important_link_fetch_logs` للاطلاع على تاريخ العمليات:
```sql
SELECT * FROM important_link_fetch_logs 
ORDER BY created_at DESC 
LIMIT 20;
```

### 3. تفعيل الجلب التلقائي
للمصادر التي تعمل بشكل جيد، فعّل الجلب التلقائي:
```sql
UPDATE important_link_sources 
SET auto_import = 1 
WHERE id = X;
```

### 4. إضافة Cron Job
لتحديث البيانات تلقائياً كل يوم، أضف cron job:
```bash
# افتح crontab
crontab -e

# أضف هذا السطر (تشغيل كل يوم الساعة 3 صباحاً)
0 3 * * * php /path/to/tekrit_municipality/cron/update_sources.php
```

---

## 🐛 استكشاف الأخطاء:

### المشكلة: زر "جلب البيانات" لا يعمل
**الحل:**
1. افتح Developer Tools (F12)
2. انتقل لتبويب Console
3. اضغط على الزر
4. انظر للأخطاء
5. إذا ظهر "404 Not Found"، تأكد من وجود ملف `public/api/get_important_links.php`

### المشكلة: لا تظهر بيانات في الصفحة العامة
**الحل:**
1. تأكد من وجود روابط في قاعدة البيانات:
```sql
SELECT COUNT(*) FROM important_links WHERE is_active = 1;
```
2. إذا كان العدد 0، قم بجلب البيانات من المصادر

### المشكلة: جميع المصادر تفشل
**الحل:**
1. تحقق من اتصال الإنترنت
2. تحقق من إعدادات Firewall
3. تحقق من إعدادات PHP (curl, allow_url_fopen)

---

## 📞 الدعم:

إذا واجهت مشاكل:

1. **راجع التقرير:** `SOURCES_ANALYSIS_REPORT.md`
2. **شغّل سكريبت الاختبار:** `modules/test_all_sources.php`
3. **راجع السجلات:** جدول `important_link_fetch_logs`
4. **اتصل بالمطور** مع تفاصيل الخطأ

---

## ✨ الخطوات التالية المقترحة:

### قصيرة المدى:
- ✅ اختبار جميع المصادر
- ✅ إصلاح المصادر المعطلة
- ✅ إضافة بيانات أولية يدوياً

### متوسطة المدى:
- 🔄 البحث عن مصادر APIs أفضل
- 🔄 تحسين scraping selectors
- 🔄 إضافة مصادر جديدة

### طويلة المدى:
- 🚀 إضافة خريطة تفاعلية
- 🚀 تطبيق موبايل
- 🚀 نظام تقييمات من المواطنين

---

## 🎯 الملخص:

تم إنجاز:
✅ إنشاء ملف API للصفحة العامة
✅ إنشاء سكريبت اختبار شامل
✅ تحليل جميع المصادر الموجودة
✅ إنشاء ملف إصلاح SQL
✅ كتابة تقرير تفصيلي

الخطوة التالية:
👉 **اختبر المصادر باستخدام:**
   `modules/test_all_sources.php`

👉 **أخبرني بالنتائج وسأساعدك في الإصلاح!**

