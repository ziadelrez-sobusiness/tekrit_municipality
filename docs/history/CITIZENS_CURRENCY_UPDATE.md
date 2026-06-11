# تحديث نظام العملات لصفحة المواطنين

## 📋 الملخص
تم ربط جدول المواطنين (`citizens`) بجدول العملات (`currencies`) للسماح باختيار عملة الراتب الشهري.

---

## 🗄️ تعديلات قاعدة البيانات

### 1. إضافة عمود `income_currency_id`
```sql
ALTER TABLE citizens 
ADD COLUMN income_currency_id INT(11) NULL 
AFTER monthly_income;

ALTER TABLE citizens 
ADD CONSTRAINT fk_citizens_currency 
FOREIGN KEY (income_currency_id) REFERENCES currencies(id) 
ON DELETE SET NULL ON UPDATE CASCADE;
```

### 2. تحديث السجلات الموجودة
```sql
-- تعيين الليرة اللبنانية كعملة افتراضية للرواتب الموجودة
UPDATE citizens 
SET income_currency_id = (SELECT id FROM currencies WHERE currency_code = 'LBP' LIMIT 1) 
WHERE monthly_income IS NOT NULL AND income_currency_id IS NULL;
```

---

## 💻 تعديلات الكود

### 1. ملف `modules/citizens.php`

#### أ. معالجة PHP (Backend)

**إضافة مواطن جديد:**
```php
$income_currency_id = !empty($_POST['income_currency_id']) ? intval($_POST['income_currency_id']) : null;
```

**استعلام INSERT:**
```php
INSERT INTO citizens (..., monthly_income, income_currency_id, ...) 
VALUES (?, ..., ?, ?, ...)
```

**تعديل مواطن:**
```php
UPDATE citizens SET ..., monthly_income = ?, income_currency_id = ?, ... WHERE id = ?
```

#### ب. استعلام جلب البيانات

**جلب المواطنين مع العملات:**
```php
SELECT c.*, cur.currency_symbol, cur.currency_code 
FROM citizens c
LEFT JOIN currencies cur ON c.income_currency_id = cur.id
ORDER BY c.created_at DESC 
LIMIT 50
```

**جلب العملات النشطة:**
```php
$stmt = $db->query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY currency_code");
$currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

#### ج. نموذج إضافة مواطن (HTML)

**تحديث قسم المعلومات المهنية:**
```html
<div class="grid grid-cols-1 md:grid-cols-4 gap-4">
    <div>
        <label>المهنة</label>
        <input type="text" name="profession">
    </div>
    
    <div>
        <label>مكان العمل</label>
        <input type="text" name="workplace">
    </div>
    
    <div>
        <label>الراتب الشهري</label>
        <input type="number" name="monthly_income" step="1000">
    </div>
    
    <div>
        <label>عملة الراتب</label>
        <select name="income_currency_id">
            <option value="">اختر العملة</option>
            <?php foreach ($currencies as $currency): ?>
                <option value="<?= $currency['id'] ?>">
                    <?= $currency['currency_name'] ?> (<?= $currency['currency_symbol'] ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
```

#### د. نموذج تعديل مواطن (HTML)

نفس التحديث مع إضافة:
```html
<select name="income_currency_id" id="edit_income_currency_id">
```

#### هـ. JavaScript

**عرض الراتب مع العملة:**
```javascript
// في دالة viewCitizen()
if (citizen.monthly_income) {
    const currencySymbol = citizen.currency_symbol || 'ل.ل';
    document.getElementById('view_monthly_income').textContent = 
        citizen.monthly_income.toLocaleString() + ' ' + currencySymbol;
} else {
    document.getElementById('view_monthly_income').textContent = '-';
}
```

**ملء نموذج التعديل:**
```javascript
// في دالة editCitizen()
document.getElementById('edit_income_currency_id').value = citizen.income_currency_id || '';
```

---

## 🎯 الميزات الجديدة

✅ **اختيار عملة الراتب** - يمكن اختيار ليرة لبنانية أو دولار أمريكي أو يورو  
✅ **عرض الراتب مع رمز العملة** - يظهر الراتب مع الرمز الصحيح (ل.ل أو $ أو €)  
✅ **Foreign Key** - ربط آمن مع جدول العملات  
✅ **قيمة افتراضية** - تحديث السجلات القديمة تلقائياً بالليرة اللبنانية  

---

## 📂 الملفات المطلوبة

### 1. ملف تحديث قاعدة البيانات
**الملف:** `add_income_currency_column.php`
**الاستخدام:** افتح من المتصفح لإضافة العمود وتحديث البيانات:
```
http://localhost:8080/tekrit_municipality/add_income_currency_column.php
```

### 2. ملف التحقق
**الملف:** `check_citizens_table.php`
**الاستخدام:** للتحقق من بنية الجدول بعد التحديث

---

## 🔧 خطوات التطبيق

1. **تشغيل ملف تحديث قاعدة البيانات:**
   ```
   افتح: http://localhost:8080/tekrit_municipality/add_income_currency_column.php
   ```

2. **التحقق من النجاح:**
   - يجب أن ترى رسالة "تم إضافة العمود income_currency_id بنجاح"
   - يجب أن ترى رسالة "تم إضافة Foreign Key بنجاح"
   - يجب أن ترى رسالة "تم تحديث السجلات الموجودة بالليرة اللبنانية"

3. **اختبار الصفحة:**
   ```
   افتح: http://localhost:8080/tekrit_municipality/modules/citizens.php
   ```

4. **اختبار الوظائف:**
   - ✅ إضافة مواطن جديد مع اختيار عملة الراتب
   - ✅ تعديل بيانات مواطن موجود مع تغيير عملة الراتب
   - ✅ عرض تفاصيل مواطن مع رمز العملة الصحيح

---

## 🌍 العملات المتوفرة

| العملة | الرمز | الكود |
|--------|------|------|
| ليرة لبنانية | ل.ل | LBP |
| دولار أمريكي | $ | USD |
| يورو | € | EUR |

---

## 📝 ملاحظات مهمة

⚠️ **قبل التطبيق:**
- تأكد من عمل نسخة احتياطية من قاعدة البيانات
- تأكد من وجود العملات في جدول `currencies`

✅ **بعد التطبيق:**
- جميع الرواتب الموجودة ستكون بالليرة اللبنانية افتراضياً
- يمكن تعديل العملة لأي مواطن من خلال نموذج التعديل
- السجلات الجديدة تحتاج لاختيار العملة يدوياً

---

## 🎉 تم بنجاح!

الآن نظام إدارة المواطنين يدعم العملات المتعددة للرواتب الشهرية!

