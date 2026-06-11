# دليل إضافة CSRF Protection لجميع النماذج

## 📋 الوضع الحالي

تم إضافة CSRF Protection للملفات التالية:
- ✅ `public/citizen-requests.php` - كامل
- ✅ `public/contact.php` - كامل
- ✅ `modules/invoices.php` - كامل (جميع معالجات POST)
- ✅ `modules/budgets.php` - كامل (جميع معالجات POST)

## ⚠️ الملفات المتبقية

هناك **53 ملف** في `modules/` يحتوي على **145 نموذج POST** يحتاج إضافة CSRF.

## 🔧 كيفية الإضافة

### الخطوة 1: تحميل CSRF Middleware

في بداية الملف (بعد require auth):

```php
// تحميل CSRF Protection
if (file_exists(__DIR__ . '/../includes/csrf_middleware.php')) {
    require_once __DIR__ . '/../includes/csrf_middleware.php';
}
```

### الخطوة 2: إضافة CSRF Validation في معالجات POST

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_name'])) {
    // التحقق من CSRF
    if (!csrf_protect(false)) {
        $error = $error ?? 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        try {
            // باقي الكود...
        } catch (Exception $e) {
            $error = 'خطأ: ' . $e->getMessage();
        }
    }
}
```

### الخطوة 3: إضافة CSRF Field في النماذج

```php
<form method="POST">
    <?php echo csrf_input('csrf_token'); ?>
    <!-- باقي الحقول -->
</form>
```

## 📝 مثال كامل

### قبل:
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    try {
        $name = $_POST['name'];
        // ... باقي الكود
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
```

### بعد:
```php
// في بداية الملف
if (file_exists(__DIR__ . '/../includes/csrf_middleware.php')) {
    require_once __DIR__ . '/../includes/csrf_middleware.php';
}

// في معالج POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    if (!csrf_protect(false)) {
        $error = $error ?? 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        try {
            $name = $_POST['name'];
            // ... باقي الكود
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// في النموذج
<form method="POST">
    <?php echo csrf_input('csrf_token'); ?>
    <input type="text" name="name">
    <button type="submit" name="add_item">إضافة</button>
</form>
```

## 🚀 سكريبت تلقائي

تم إنشاء سكريبت `scripts/add_csrf_to_all_forms.php` لإضافة CSRF تلقائياً، لكنه يحتاج مراجعة يدوية.

## ⚠️ تحذيرات

1. **لا تستخدم السكريبت التلقائي مباشرة** - راجع التغييرات أولاً
2. **اختبر كل ملف بعد التعديل** - تأكد من عدم كسر أي شيء
3. **احتفظ بنسخ احتياطية** - السكريبت ينشئ نسخ احتياطية تلقائياً

---

**تاريخ الإنشاء:** <?= date('Y-m-d H:i:s') ?>







