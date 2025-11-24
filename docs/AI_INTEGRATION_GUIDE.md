# دليل تكامل الذكاء الاصطناعي 🤖
## نظام إدارة بلدية تكريت

---

## المحتويات
1. [نظرة عامة](#نظرة-عامة)
2. [الإعداد والتكوين](#الإعداد-والتكوين)
3. [الميزات المتاحة](#الميزات-المتاحة)
4. [كيفية الاستخدام](#كيفية-الاستخدام)
5. [الأمان والخصوصية](#الأمان-والخصوصية)
6. [استكشاف الأخطاء](#استكشاف-الأخطاء)

---

## نظرة عامة

تم تكامل الذكاء الاصطناعي في نظام إدارة البلدية لتحسين الكفاءة وتسهيل المهام اليومية. يدعم النظام:

- **OpenAI (ChatGPT)** - GPT-4, GPT-4 Turbo, GPT-3.5 Turbo
- **Google Gemini** - Gemini Pro, Gemini 1.5 Pro
- **Anthropic Claude** - Claude 3 Opus, Sonnet, Haiku
- **مزودات مخصصة** - دعم API مخصص

---

## الإعداد والتكوين

### 1. تنفيذ SQL للإعدادات

```bash
mysql -u root -p tekrit_municipality < sql/add_ai_settings.sql
```

### 2. تفعيل الذكاء الاصطناعي

1. الدخول إلى: **إعدادات النظام** → **الذكاء الاصطناعي**
2. اختيار نوع مزود الخدمة (OpenAI, Gemini, Claude)
3. إدخال مفتاح API
4. اختيار النموذج المناسب
5. تفعيل الذكاء الاصطناعي
6. حفظ الإعدادات

**ملاحظة:** يتم تشفير مفتاح API تلقائياً لضمان الأمان.

---

## الميزات المتاحة

### 1. إنشاء الميزانيات 💰

**المسار:** `modules/budgets.php`

#### ميزات:
- إنشاء ميزانية عامة للبلدية
- إنشاء ميزانية للجان المحددة
- معالج تفاعلي بالأسئلة
- توصيات تلقائية لتحسين الكفاءة

#### الأسئلة المطروحة:
- عدد السكان
- مصادر الإيرادات المتوقعة
- القطاعات ذات الأولوية
- عدد الموظفين ومتوسط الرواتب
- المشاريع القائمة والجديدة
- الديون والالتزامات

#### القانون المعتمد:
قانون البلديات اللبناني رقم 118/1977

---

### 2. إدارة المشاريع 🏗️

**كيفية الاستخدام:**

```html
<!-- إضافة في أي صفحة مشاريع -->
<script src="../js/ai_helper.js"></script>
<script>
// إضافة زر AI لحقل الوصف
aiHelper.addAIButtonToField('project_description');

// أو استخدام مباشر
async function generateDescription() {
    const title = document.getElementById('project_title').value;
    const result = await aiHelper.generateProjectDescription(
        title,
        'keywords',
        'budget'
    );
    document.getElementById('project_description').value = result.content;
}
</script>
```

---

### 3. إدارة الأخبار 📰

**الميزات:**
- إنشاء مقالات إخبارية تلقائياً
- إنشاء صور للأخبار باستخدام DALL-E
- تحسين المحتوى

**مثال:**

```javascript
// إنشاء مقال
const article = await aiHelper.generateNewsArticle(
    'افتتاح مركز صحي جديد',
    'تم افتتاح مركز صحي حديث...',
    'formal'
);

// إنشاء صورة
const image = await aiHelper.generateNewsImage(
    'افتتاح مركز صحي جديد',
    'مبنى حديث، مرافق طبية'
);
```

---

### 4. الردود على الطلبات والشكاوى 📧

**الاستخدام:**

```javascript
const response = await aiHelper.generateResponse(
    'محتوى الطلب أو الشكوى',
    'complaint', // أو 'general'
    'معلومات إضافية'
);
```

**يتضمن الرد:**
1. الاعتراف بالطلب/الشكوى
2. توضيح الإجراءات المتخذة
3. الالتزام بالمتابعة
4. شكر المواطن

---

### 5. تحسين النصوص ✨

**الخيارات المتاحة:**
- تصحيح الأخطاء النحوية والإملائية
- جعل النص أكثر احترافية
- اختصار النص
- توسيع النص

**مثال:**

```javascript
// تحسين النص
const improved = await aiHelper.improveText(
    'النص الأصلي',
    'professional'
);

// توسيع النص
const expanded = await aiHelper.expandText(
    'نص مختصر',
    'السياق'
);
```

---

## الأمان والخصوصية

### تشفير API Keys
- يتم تشفير جميع مفاتيح API باستخدام AES-256-CBC
- المفاتيح محمية في قاعدة البيانات
- لا يمكن قراءة المفاتيح بشكل مباشر

### الصلاحيات
- فقط المديرون يمكنهم الوصول لإعدادات AI
- يتم تتبع جميع طلبات AI للتدقيق

### الخصوصية
- لا يتم مشاركة بيانات حساسة مع مزودي AI
- يمكن تعطيل AI في أي وقت

---

## استكشاف الأخطاء

### المشكلة: "الذكاء الاصطناعي غير مفعل"
**الحل:**
1. التأكد من تفعيل AI في الإعدادات
2. التحقق من صحة مفتاح API
3. التأكد من اختيار النموذج المناسب

### المشكلة: "خطأ في الاتصال بالـ API"
**الحل:**
1. التحقق من الاتصال بالإنترنت
2. التأكد من صحة مفتاح API
3. التحقق من حدود الاستخدام لدى المزود

### المشكلة: "استجابة بطيئة"
**الحل:**
1. استخدام نماذج أسرع (مثل GPT-3.5 Turbo)
2. تقليل عدد الكلمات المطلوبة
3. التحقق من حالة خوادم المزود

---

## ملفات النظام

### الملفات الأساسية:
- `includes/ai_helper.php` - دوال مساعدة PHP
- `includes/ai_service.php` - خدمة الاتصال بـ API
- `includes/ai_budget_questions.php` - أسئلة الميزانيات
- `js/ai_helper.js` - دوال JavaScript للواجهة

### API Endpoints:
- `api/ai_budget_generate.php` - إنشاء الميزانيات
- `api/ai_content_generate.php` - إنشاء المحتوى

### الصفحات المحدثة:
- `modules/system_settings.php` - إعدادات AI
- `modules/budgets.php` - دعم AI للميزانيات

---

## أمثلة الاستخدام

### مثال كامل: إضافة AI لصفحة جديدة

```html
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <title>صفحة مع دعم AI</title>
</head>
<body>
    <form>
        <label>العنوان:</label>
        <input type="text" id="title">

        <label>الوصف:</label>
        <textarea id="description"></textarea>

        <button type="button" onclick="useAI()">
            🤖 استخدم الذكاء الاصطناعي
        </button>
    </form>

    <script src="../js/ai_helper.js"></script>
    <script>
        // إضافة زر AI تلقائي
        aiHelper.addAIButtonToField('description');

        // دالة مخصصة
        async function useAI() {
            const title = document.getElementById('title').value;
            if (!title) {
                alert('يرجى إدخال العنوان أولاً');
                return;
            }

            try {
                const result = await aiHelper.generateProjectDescription(title);
                document.getElementById('description').value = result.content;
            } catch (error) {
                alert('خطأ: ' + error.message);
            }
        }
    </script>
</body>
</html>
```

---

## الدعم والمساعدة

للمساعدة أو الإبلاغ عن مشاكل:
- البريد الإلكتروني: support@tekrit.gov.iq
- الهاتف: +964-XXX-XXXX

---

## المصادر المرجعية

### قانون البلديات اللبناني:
- [قانون البلديات رقم 118/1977](https://nclw.gov.lb)
- [معهد المالية - الإدارة المالية البلدية](http://www.institutdesfinances.gov.lb)

### وثائق API:
- [OpenAI API Documentation](https://platform.openai.com/docs)
- [Google Gemini API](https://ai.google.dev/docs)
- [Anthropic Claude API](https://docs.anthropic.com)

---

**تاريخ آخر تحديث:** 2025-11-24
**الإصدار:** 1.0.0
