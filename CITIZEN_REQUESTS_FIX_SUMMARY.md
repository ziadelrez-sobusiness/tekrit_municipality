# إصلاح صفحة تقديم الطلبات - citizen-requests.php

## المشكلة الأصلية
عند اختيار نوع طلب في صفحة `public/citizen-requests.php`، لم تكن تظهر بيانات الطلب مثل:
- 💰 تكلفة الطلب
- 📄 المستندات المطلوبة  
- ⚙️ الحقول الديناميكية

## تحليل المشكلة

### السبب الرئيسي
التحديثات التي أجريناها على قاعدة البيانات (إضافة أعمدة `cost`, `cost_currency_id`, `name_ar`, `name_en`) لم تنعكس بشكل صحيح على صفحة تقديم الطلبات.

### الأسباب التفصيلية

1. **استعلام قاعدة البيانات القديم**
   ```php
   // الكود القديم
   SELECT * FROM request_types WHERE is_active = 1
   ```
   - لا يجلب معلومات العملة
   - لا يعالج JSON بشكل صحيح

2. **استخدام أسماء أعمدة خطأ**
   ```php
   // الكود القديم
   <?php if ($type['fees'] > 0): ?>
   ```
   - استخدام `fees` بدلاً من `cost`

3. **JavaScript غير محدث**
   ```javascript
   // مشكلة في معالجة required_documents
   required_documents: '<?= htmlspecialchars($type['required_documents']) ?>'
   ```
   - التعامل مع JSON كنص عادي

## الحلول المطبقة

### 1. تحديث استعلام قاعدة البيانات

**قبل الإصلاح:**
```php
$stmt = $db->query("SELECT * FROM request_types WHERE is_active = 1 ORDER BY display_order, type_name");
```

**بعد الإصلاح:**
```php
$stmt = $db->query("
    SELECT rt.*, c.currency_symbol, c.currency_code 
    FROM request_types rt 
    LEFT JOIN currencies c ON rt.cost_currency_id = c.id 
    WHERE rt.is_active = 1 
    ORDER BY rt.display_order, rt.type_name
");
```

### 2. معالجة البيانات في PHP

**إضافة معالجة شاملة:**
```php
// تحويل البيانات للتأكد من صحة JSON
foreach ($request_types as &$type) {
    // معالجة التكلفة والعملة
    if (empty($type['cost'])) {
        $type['cost'] = 0;
    }
    if (empty($type['currency_symbol'])) {
        $type['currency_symbol'] = 'د.ع';
    }
    
    // معالجة required_documents
    if (!empty($type['required_documents'])) {
        $decoded = json_decode($type['required_documents'], true);
        if ($decoded && is_array($decoded)) {
            $type['required_documents_array'] = $decoded;
        } else {
            $type['required_documents_array'] = array_filter(explode("\n", $type['required_documents']));
        }
    } else {
        $type['required_documents_array'] = [];
    }
    
    // معالجة form_fields
    if (!empty($type['form_fields'])) {
        $decoded = json_decode($type['form_fields'], true);
        $type['form_fields_array'] = $decoded ?: [];
    } else {
        $type['form_fields_array'] = [];
    }
}
```

### 3. إصلاح عرض التكلفة في HTML

**قبل الإصلاح:**
```php
<?php if ($type['fees'] > 0): ?>
    <p class="text-sm text-green-600 font-semibold mt-1">الرسوم: <?= number_format($type['fees']) ?> ل.ل</p>
<?php endif; ?>
```

**بعد الإصلاح:**
```php
<?php if ($type['cost'] > 0): ?>
    <p class="text-sm text-green-600 font-semibold mt-1">
        الرسوم: <?= number_format($type['cost'], 2) ?> <?= htmlspecialchars($type['currency_symbol']) ?>
    </p>
<?php endif; ?>
```

### 4. تحديث البيانات المرسلة إلى JavaScript

**قبل الإصلاح:**
```javascript
const requestTypesData = {
    <?php foreach ($request_types as $type): ?>
    <?= $type['id'] ?>: {
        name: '<?= htmlspecialchars($type['type_name']) ?>',
        description: '<?= htmlspecialchars($type['type_description']) ?>',
        required_documents: '<?= htmlspecialchars($type['required_documents']) ?>',
        form_fields: <?= $type['form_fields'] ? json_encode(json_decode($type['form_fields'], true)) : '[]' ?>,
        fees: <?= $type['fees'] ?>
    },
    <?php endforeach; ?>
};
```

**بعد الإصلاح:**
```javascript
const requestTypesData = {
    <?php foreach ($request_types as $type): ?>
    <?= $type['id'] ?>: {
        name: '<?= htmlspecialchars($type['type_name']) ?>',
        description: '<?= htmlspecialchars($type['type_description'] ?? '') ?>',
        required_documents: <?= json_encode($type['required_documents_array']) ?>,
        form_fields: <?= json_encode($type['form_fields_array']) ?>,
        cost: <?= $type['cost'] ?? 0 ?>,
        currency_symbol: '<?= htmlspecialchars($type['currency_symbol']) ?>'
    },
    <?php endforeach; ?>
};
```

### 5. تحسين دالة showRequiredDocuments

**إضافة معالجة أفضل للمصفوفات:**
```javascript
function showRequiredDocuments(typeId) {
    const typeData = requestTypesData[typeId];
    console.log('Showing required documents for type:', typeId, typeData);
    
    if (typeData && typeData.required_documents && typeData.required_documents.length > 0) {
        // التعامل مع المستندات كمصفوفة
        let docs = typeData.required_documents;
        if (typeof docs === 'string') {
            docs = docs.split('\n').filter(doc => doc.trim());
        }
        
        let documentsHTML = '';
        docs.forEach(doc => {
            if (doc && doc.trim()) {
                documentsHTML += `<div class="flex items-center mb-2">
                    <span class="text-amber-600 mr-2">📄</span>
                    <span>${doc.trim()}</span>
                </div>`;
            }
        });
        
        if (documentsHTML) {
            document.getElementById('documents-list').innerHTML = documentsHTML;
            document.getElementById('required-documents').style.display = 'block';
        }
    }
    
    // إظهار معلومات التكلفة
    showCostInfo(typeId);
}
```

### 6. إضافة دالة showCostInfo جديدة

```javascript
function showCostInfo(typeId) {
    const typeData = requestTypesData[typeId];
    
    // البحث عن منطقة لعرض معلومات التكلفة أو إنشاؤها
    let costInfoDiv = document.getElementById('cost-info');
    if (!costInfoDiv) {
        costInfoDiv = document.createElement('div');
        costInfoDiv.id = 'cost-info';
        costInfoDiv.className = 'bg-green-50 border border-green-200 rounded-lg p-4 mt-4';
        
        // إدراج div التكلفة بعد المستندات المطلوبة
        const requiredDocsDiv = document.getElementById('required-documents');
        requiredDocsDiv.parentNode.insertBefore(costInfoDiv, requiredDocsDiv.nextSibling);
    }
    
    if (typeData && typeData.cost > 0) {
        costInfoDiv.innerHTML = `
            <h3 class="font-bold text-green-800 mb-2">💰 معلومات التكلفة:</h3>
            <div class="text-green-700">
                <p class="text-lg font-semibold">التكلفة: ${parseFloat(typeData.cost).toLocaleString()} ${typeData.currency_symbol}</p>
                <p class="text-sm mt-1">يجب دفع الرسوم عند تقديم الطلب أو حسب تعليمات البلدية</p>
            </div>
        `;
        costInfoDiv.style.display = 'block';
    } else {
        costInfoDiv.style.display = 'none';
    }
}
```

### 7. تحسين دالة selectRequestType

**إضافة معالجة أخطاء شاملة:**
```javascript
function selectRequestType(typeId, typeName) {
    console.log('selectRequestType called with:', { typeId, typeName });
    console.log('Available request types data:', requestTypesData);
    
    try {
        // كود التحديد والتحقق...
        
        // التحقق من وجود بيانات النوع
        if (requestTypesData[typeId]) {
            console.log('Request type data found:', requestTypesData[typeId]);
            
            // إظهار المستندات المطلوبة
            showRequiredDocuments(typeId);
            
            // إنشاء الحقول الديناميكية
            generateDynamicFields(typeId);
        } else {
            console.error('No data found for request type:', typeId);
        }
        
    } catch (error) {
        console.error('Error in selectRequestType:', error);
    }
}
```

### 8. تحديث generateSummary

**استخدام cost بدلاً من fees:**
```javascript
// في generateSummary
${typeData.cost > 0 ? `<p class="text-green-600"><strong>التكلفة:</strong> ${parseFloat(typeData.cost).toLocaleString()} ${typeData.currency_symbol}</p>` : ''}
```

## النتيجة النهائية

### ما يعمل الآن ✅

1. **عرض التكلفة** - تظهر بالعملة الصحيحة (USD، IQD، إلخ)
2. **المستندات المطلوبة** - تظهر كقائمة منسقة مع رموز تعبيرية
3. **الحقول الديناميكية** - تعمل إذا كانت مُعرّفة لنوع الطلب
4. **رسائل التصحيح** - console.log مفصلة للمطورين
5. **معالجة الأخطاء** - أفضل ومنع الأخطاء JavaScript

### كيفية الاختبار

1. اذهب إلى `http://localhost:8080/tekrit_municipality/public/citizen-requests.php`
2. في الخطوة الثانية "اختيار نوع الطلب"
3. اختر أي نوع طلب
4. يجب أن تظهر فوراً:
   - 📄 المستندات المطلوبة (إن وجدت)
   - 💰 معلومات التكلفة مع العملة الصحيحة (إن وجدت)
   - حقول إضافية (إن وجدت)

### للمطورين

- افتح Developer Console (F12) لرؤية رسائل التصحيح
- جميع العمليات مُسجلة في console
- معالجة أخطاء شاملة تمنع تعطل النظام

## الملفات المتأثرة

- `public/citizen-requests.php` - الملف الرئيسي المُحدث
- `test_citizen_requests_fix.html` - ملف اختبار الإصلاح
- `CITIZEN_REQUESTS_FIX_SUMMARY.md` - هذا التقرير

---
**تاريخ الإصلاح**: 12 يوليو 2025  
**المطور**: AI Assistant  
**حالة النظام**: ✅ مكتمل وجاهز للاستخدام 