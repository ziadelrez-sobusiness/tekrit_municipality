# دليل إعداد reCAPTCHA - نظام بلدية تكريت

## نظرة عامة
تم إضافة نظام reCAPTCHA لحماية الصفحات المهمة من الهجمات الإلكترونية والبوتات الآلية. النظام يحمي الصفحات التالية:

- **login.php** - صفحة تسجيل دخول الموظفين
- **public/citizen-requests.php** - صفحة طلبات المواطنين
- **public/contact.php** - نموذج إرسال الرسائل
- **public/initiative-detail.php** - نموذج التسجيل في المبادرات

## الملفات المضافة

### 1. `includes/recaptcha_helper.php`
فئة مساعدة شاملة تحتوي على:
- إعدادات مفاتيح reCAPTCHA
- دوال التحقق من صحة reCAPTCHA
- دوال العرض والتنسيق
- معالجة أخطاء التحقق

## المفاتيح المستخدمة حالياً

⚠️ **تحذير مهم**: النظام يستخدم حالياً مفاتيح تجريبية من Google:

```php
// مفاتيح تجريبية - يجب استبدالها في الإنتاج
Site Key: 6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI
Secret Key: 6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe
```

**هذه المفاتيح للاختبار فقط وستقبل أي تحقق!**

## إعداد مفاتيح الإنتاج

### الخطوة 1: إنشاء مفاتيح reCAPTCHA
1. اذهب إلى [Google reCAPTCHA Admin Console](https://www.google.com/recaptcha/admin)
2. سجل الدخول بحساب Google
3. انقر على "إضافة موقع جديد" (+)
4. املأ المعلومات:
   - **التسمية**: بلدية تكريت
   - **نوع reCAPTCHA**: v2 "أنا لست روبوت"
   - **النطاقات**: أضف نطاق موقعك (مثل: tekrit-municipality.com أو localhost للاختبار)
5. وافق على شروط الخدمة
6. انقر "إرسال"

### الخطوة 2: استبدال المفاتيح
بعد إنشاء المفاتيح، عدّل الملف `includes/recaptcha_helper.php`:

```php
// استبدل هذه المفاتيح بمفاتيحك الحقيقية
private static $site_key = 'مفتاح_الموقع_الخاص_بك';
private static $secret_key = 'المفتاح_السري_الخاص_بك';
```

### الخطوة 3: اختبار التطبيق
1. احفظ التغييرات
2. اختبر النماذج للتأكد من عمل reCAPTCHA
3. تحقق من أن الرسائل الخطأ تظهر عند عدم إكمال التحقق

## آلية العمل

### 1. في رأس الصفحة
```php
// تحميل JavaScript
<?= RecaptchaHelper::renderScript() ?>
// تحميل CSS
<?= RecaptchaHelper::renderCSS() ?>
```

### 2. في النموذج
```php
<!-- عرض reCAPTCHA -->
<div class="recaptcha-container">
    <?= RecaptchaHelper::renderWidget() ?>
</div>
```

### 3. في معالجة النموذج
```php
// التحقق من reCAPTCHA
$recaptcha_result = verify_recaptcha($_POST, $_SERVER['REMOTE_ADDR'] ?? null);

if (!$recaptcha_result['success']) {
    $error = $recaptcha_result['error'];
} else {
    // متابعة معالجة النموذج
}
```

## رسائل الخطأ

النظام يوفر رسائل خطأ باللغة العربية:
- "يرجى التحقق من أنك لست روبوت" - عند عدم إكمال التحقق
- "انتهت صلاحية التحقق، يرجى المحاولة مرة أخرى" - عند انتهاء المهلة
- "فشل في الاتصال بخدمة التحقق" - عند مشاكل الشبكة

## التخصيص

### تغيير مظهر reCAPTCHA
يمكن تعديل CSS في `RecaptchaHelper::renderCSS()`:

```css
.g-recaptcha {
    transform: scale(0.9); /* تصغير الحجم */
    transform-origin: 0 0;
}

@media (max-width: 768px) {
    .g-recaptcha {
        transform: scale(0.8); /* حجم أصغر للهواتف */
    }
}
```

### استخدام مفاتيح مختلفة لبيئات مختلفة
```php
// في بداية الملف أو ملف الإعدادات
if ($_SERVER['HTTP_HOST'] === 'localhost') {
    // مفاتيح التطوير
    RecaptchaHelper::setKeys('مفتاح_التطوير', 'سر_التطوير');
} else {
    // مفاتيح الإنتاج
    RecaptchaHelper::setKeys('مفتاح_الإنتاج', 'سر_الإنتاج');
}
```

## الأمان

### نصائح أمنية
1. **لا تشارك المفتاح السري** - لا تضعه في JavaScript أو ملفات عامة
2. **اقصر النطاقات** - أضف نطاقاتك المحددة فقط في إعدادات Google
3. **راقب الاستخدام** - تحقق من إحصائيات reCAPTCHA في لوحة Google
4. **استخدم HTTPS** - تأكد من تشغيل الموقع على اتصال آمن

### التحقق من IP
النظام يرسل IP المستخدم لـ Google للمساعدة في اكتشاف الأنشطة المشبوهة:

```php
$recaptcha_result = verify_recaptcha($_POST, $_SERVER['REMOTE_ADDR'] ?? null);
```

## استكشاف الأخطاء

### مشاكل شائعة

1. **reCAPTCHA لا يظهر**
   - تحقق من تحميل JavaScript
   - تأكد من صحة مفتاح الموقع
   - تحقق من عدم حجب النطاق

2. **التحقق يفشل دائماً**
   - تحقق من صحة المفتاح السري
   - تأكد من صحة النطاق في إعدادات Google
   - تحقق من اتصال الخادم بالإنترنت

3. **مشاكل في التنسيق**
   - تحقق من تحميل CSS
   - عدّل scale في CSS حسب تصميمك

### تسجيل الأخطاء
لمساعدة في التشخيص، يمكن إضافة تسجيل:

```php
$recaptcha_result = verify_recaptcha($_POST, $_SERVER['REMOTE_ADDR'] ?? null);
if (!$recaptcha_result['success']) {
    error_log('reCAPTCHA failed: ' . print_r($recaptcha_result, true));
}
```

## الاختبار

### اختبار النظام
1. **اختبار التحقق الصحيح**: اكمل reCAPTCHA بشكل طبيعي
2. **اختبار الفشل**: ارسل النموذج بدون إكمال reCAPTCHA
3. **اختبار انتهاء الصلاحية**: انتظر عدة دقائق قبل إرسال النموذج
4. **اختبار أجهزة مختلفة**: تأكد من العمل على الهاتف والحاسوب

### بيئة التطوير
للاختبار المحلي، يمكن إضافة `localhost` إلى النطاقات المسموحة في Google reCAPTCHA Console.

## الدعم

للمساعدة أو الاستفسارات:
1. راجع [وثائق Google reCAPTCHA](https://developers.google.com/recaptcha/docs/display)
2. تحقق من إحصائيات الاستخدام في [لوحة reCAPTCHA](https://www.google.com/recaptcha/admin)
3. اطلع على أكواد الخطأ في وثائق Google

---

**ملاحظة**: هذا النظام يوفر حماية فعالة ضد الهجمات الآلية ويحسن أمان الموقع بشكل كبير. 