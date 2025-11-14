# شاشة التحميل - Loading Screen

## 🎯 الهدف
إضافة شاشة تحميل جميلة ومتطورة لموقع بلدية تكريت تظهر عند فتح الموقع العام.

## 🎨 الميزات
- **رسوم متحركة للمدينة**: أيقونة مباني متحركة تنمو تدريجياً
- **تأثير النبض**: دائرة متوهجة خلف أيقونة المدينة
- **شريط التقدم**: يظهر تقدم التحميل
- **نقاط متحركة**: 3 نقاط تنبض بشكل متتالي
- **تصميم متجاوب**: يعمل على جميع الشاشات
- **ألوان متدرجة**: خلفية جميلة بألوان زرقاء وبنفسجية

## 📁 الملفات المطلوبة
1. `assets/css/loading-screen.css` - ملف الأنماط
2. `assets/js/loading-screen.js` - ملف JavaScript (اختياري)

## 🔧 طريقة الاستخدام

### 1. إضافة CSS
```html
<link href="assets/css/loading-screen.css" rel="stylesheet">
```

### 2. إضافة HTML
```html
<div class="loading-overlay" id="loadingScreen">
    <div class="loading-container">
        <div class="city-icon">
            <div class="city-circle">
                <div class="city-buildings">
                    <div class="building building-1"></div>
                    <div class="building building-2"></div>
                    <div class="building building-3"></div>
                    <div class="building building-4"></div>
                    <div class="building building-5"></div>
                </div>
            </div>
        </div>
        
        <h1 class="loading-text">بلدية تكريت</h1>
        <p class="loading-subtext">جاري التحميل
            <span class="loading-dots">
                <span></span>
                <span></span>
                <span></span>
            </span>
        </p>
        
        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>
    </div>
</div>
```

### 3. إضافة JavaScript
```javascript
class LoadingScreen {
    constructor() {
        this.loadingOverlay = document.getElementById('loadingScreen');
        this.minimumLoadTime = 2500; // 2.5 ثانية
        this.startTime = Date.now();
        this.init();
    }
    
    // باقي الكود...
}

const loadingScreen = new LoadingScreen();
```

## ⚙️ التخصيص

### تغيير مدة العرض
```javascript
this.minimumLoadTime = 3000; // 3 ثوانٍ
```

### تغيير الألوان
```css
.loading-overlay {
    background: linear-gradient(135deg, #your-color1, #your-color2);
}
```

### تغيير النص
```html
<h1 class="loading-text">اسم البلدية</h1>
```

## 🔍 اختبار شاشة التحميل
افتح: `http://localhost/tekrit_municipality/public/test-loading.html`

## 🎭 الأنيميشنز المتضمنة
1. **pulse**: تأثير النبض للدائرة الخلفية
2. **buildingGrow**: نمو المباني تدريجياً
3. **textGlow**: توهج النص
4. **loadingProgress**: حركة شريط التقدم
5. **dotPulse**: نبض النقاط
6. **rotate**: دوران الحلقة الخارجية

## 📱 التوافق
- ✅ Chrome, Firefox, Safari, Edge
- ✅ الهواتف المحمولة والأجهزة اللوحية
- ✅ جميع أحجام الشاشات

## 🚀 التحسينات المستقبلية
- إضافة أصوات (اختيارية)
- تخصيص الرسوم المتحركة حسب السرعة
- إضافة رسائل تحميل ديناميكية
- دعم الوضع الليلي 