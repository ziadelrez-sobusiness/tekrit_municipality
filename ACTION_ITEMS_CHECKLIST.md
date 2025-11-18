# قائمة المهام والإجراءات الموصى بها
## Action Items Checklist

**بناءً على التقرير الشامل - تاريخ: 18 نوفمبر 2025**

---

## 🔥 إجراءات فورية (خلال أسبوع)

### 1. تنظيف الملفات المكررة

#### ✅ الملفات المكررة للحذف:

```bash
# ملفات council_management
modules/council_management_backup.php
modules/council_management_complete.php
modules/council_management_enhanced.php
modules/council_management_fixed.php
modules/council_management_new.php
modules/council_management_original.php
modules/council_management_working.php
# احتفظ فقط بـ: modules/council_management_final.php

# ملفات contact
public/contact_backup.php
public/contact_backup_original.php
public/contact_fixed.php
public/contact_old.php
public/contact_with_map.php
# احتفظ فقط بـ: public/contact.php

# ملفات citizen-requests
public/citizen-requests-old-backup.php
public/citizen-requests-enhanced.php
# احتفظ فقط بـ: public/citizen-requests.php

# ملفات أخرى
modules/budget_example_backup.php
modules/contact_management_backup.php
modules/facilities_management_backup.php
modules/facilities_api_backup.php
modules/facilities_api_fixed.php
public/council_backup.php
public/project-detail-backup.php
```

**الأمر:**
```bash
# قم بمراجعة كل ملف قبل الحذف
# تأكد أن الملف الرئيسي يحتوي على كل الميزات
```

### 2. نقل ملفات الاختبار

#### 📁 إنشاء مجلد للاختبارات

```bash
mkdir -p testing/
```

#### 📦 نقل ملفات الاختبار

```bash
# نقل جميع ملفات test_
mv test_*.php testing/
mv debug_*.php testing/
mv check_*.php testing/
mv fix_*.php testing/
```

**الملفات المتأثرة (~35 ملف):**
- test_all_systems.php
- test_api_quick.php
- debug_citizens_page.php
- check_dashboard_links.php
- fix_admin_password.php
- وغيرها...

### 3. تنظيم ملفات التوثيق

#### 📁 إنشاء مجلد للتوثيق

```bash
mkdir -p documentation/
mkdir -p documentation/guides/
mkdir -p documentation/fixes/
mkdir -p documentation/setup/
```

#### 📦 نقل ملفات التوثيق

```bash
# نقل ملفات الدليل
mv *_GUIDE.md documentation/guides/
mv *_GUIDE.html documentation/guides/
mv README_*.md documentation/guides/

# نقل ملفات الإصلاح
mv *_FIX*.md documentation/fixes/
mv *_FIX*.html documentation/fixes/
mv *_FIXED*.html documentation/fixes/

# نقل ملفات الإعداد
mv START_*.html documentation/setup/
mv SETUP_*.html documentation/setup/
mv INSTALL_*.html documentation/setup/

# نقل باقي الملفات
mv *.md documentation/ (ما عدا README.md)
mv *.html documentation/ (ما عدا الملفات الأساسية)
```

### 4. إنشاء ملف .env

#### 📝 إنشاء .env

```bash
# في الجذر الرئيسي
touch .env
chmod 600 .env
```

#### 📄 محتوى .env

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=tekrit_municipality
DB_USER=root
DB_PASS=

# Security
APP_ENV=production
APP_DEBUG=false
APP_KEY=generate_random_32_character_key_here

# reCAPTCHA
RECAPTCHA_SITE_KEY=your_site_key_here
RECAPTCHA_SECRET_KEY=your_secret_key_here

# Telegram
TELEGRAM_BOT_TOKEN=your_bot_token_here
TELEGRAM_CHAT_ID=your_chat_id_here

# Email (إذا كان موجود)
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your_email@example.com
MAIL_PASSWORD=your_password
```

#### 🔧 تحديث config/database.php

```php
<?php
// تحميل .env إذا كان موجود
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        $this->host = $_ENV['DB_HOST'] ?? "localhost";
        $this->db_name = $_ENV['DB_NAME'] ?? "tekrit_municipality";
        $this->username = $_ENV['DB_USER'] ?? "root";
        $this->password = $_ENV['DB_PASS'] ?? "";
    }

    // باقي الكود...
}
```

#### 🔒 تحديث .gitignore

```bash
echo ".env" >> .gitignore
```

### 5. إعداد Error Logging

#### 📁 إنشاء نظام التسجيل

```bash
# التأكد من وجود مجلد logs
mkdir -p logs/
chmod 755 logs/

# إنشاء .gitignore في logs
echo "*" > logs/.gitignore
echo "!.gitignore" >> logs/.gitignore
```

#### 📝 إنشاء includes/error_handler.php

```php
<?php
/**
 * نظام تسجيل الأخطاء
 */

// تعطيل عرض الأخطاء في Production
if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production') {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// دالة تسجيل الأخطاء
function logError($message, $type = 'ERROR') {
    $logFile = __DIR__ . '/../logs/error_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$type}] {$message}" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// معالج الأخطاء
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    logError("Error [{$errno}]: {$errstr} in {$errfile} on line {$errline}");
});

// معالج الاستثناءات
set_exception_handler(function($exception) {
    logError("Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());

    // عرض رسالة عامة للمستخدم
    if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production') {
        echo "حدث خطأ غير متوقع. يرجى المحاولة لاحقاً.";
    }
});
```

#### 🔧 تضمينه في init_security.php

```php
// في includes/init_security.php
require_once __DIR__ . '/error_handler.php';
```

---

## 📅 إجراءات قصيرة المدى (خلال شهر)

### 6. تحسين الصور

#### 🖼️ تحويل الصور إلى WebP

```bash
# تثبيت cwebp (إذا لم يكن مثبت)
# Ubuntu/Debian
sudo apt-get install webp

# تحويل الصور
for img in public/assets/images/*.{jpg,png}; do
    cwebp -q 80 "$img" -o "${img%.*}.webp"
done
```

#### ⚡ إضافة Lazy Loading

```html
<!-- في index.php والصفحات الأخرى -->
<img src="image.jpg" loading="lazy" alt="...">
```

#### 🔧 إنشاء دالة مساعدة للصور

```php
// في includes/helpers.php
function getOptimizedImage($path, $alt = '') {
    $webp = str_replace(['.jpg', '.png'], '.webp', $path);
    $original = $path;

    if (file_exists(__DIR__ . '/../public/' . $webp)) {
        return "<picture>
            <source srcset='{$webp}' type='image/webp'>
            <img src='{$original}' alt='{$alt}' loading='lazy'>
        </picture>";
    }

    return "<img src='{$original}' alt='{$alt}' loading='lazy'>";
}
```

### 7. Minification

#### 📦 تثبيت أدوات Minification

```bash
# تثبيت Node.js و npm (إذا لم يكن مثبت)

# تثبيت minify tools
npm install -g csso-cli
npm install -g uglify-js
```

#### 🗜️ تصغير CSS

```bash
# تصغير ملفات CSS
csso public/assets/css/tekrit-theme.css -o public/assets/css/tekrit-theme.min.css
csso public/assets/css/enhanced-style.css -o public/assets/css/enhanced-style.min.css
```

#### 🗜️ تصغير JS

```bash
# تصغير ملفات JavaScript
uglifyjs public/assets/js/loading-screen.js -c -m -o public/assets/js/loading-screen.min.js
uglifyjs public/assets/js/enhanced-requests.js -c -m -o public/assets/js/enhanced-requests.min.js
```

#### 🔧 تحديث الروابط

```html
<!-- استخدام الملفات المصغرة في Production -->
<?php if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production'): ?>
    <link href="assets/css/tekrit-theme.min.css" rel="stylesheet">
<?php else: ?>
    <link href="assets/css/tekrit-theme.css" rel="stylesheet">
<?php endif; ?>
```

### 8. Gzip Compression

#### 🔧 إعداد .htaccess

```apache
# في public/.htaccess
<IfModule mod_deflate.c>
    # تفعيل Gzip compression
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json

    # ضغط الخطوط
    AddOutputFilterByType DEFLATE application/x-font-ttf application/x-font-opentype application/font-woff application/font-woff2

    # ضغط SVG
    AddOutputFilterByType DEFLATE image/svg+xml
</IfModule>

# Browser Caching
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType text/javascript "access plus 1 month"
    ExpiresByType application/pdf "access plus 1 month"
    ExpiresByType image/x-icon "access plus 1 year"
</IfModule>
```

### 9. تحسين SEO

#### 📝 إضافة meta tags محسّنة

```php
// في includes/seo_meta.php
function getSEOMeta($page = 'home') {
    $meta = [
        'home' => [
            'title' => 'بلدية تكريت - الموقع الرسمي | خدمات إلكترونية للمواطنين',
            'description' => 'الموقع الرسمي لبلدية تكريت يوفر خدمات إلكترونية شاملة للمواطنين بما في ذلك تقديم الطلبات، متابعة المشاريع، والأخبار.',
            'keywords' => 'بلدية تكريت، خدمات بلدية، طلبات المواطنين، مشاريع تكريت، أخبار البلدية',
            'og_image' => 'assets/images/Tekrit_LOGO.png'
        ],
        // يمكن إضافة المزيد من الصفحات
    ];

    return $meta[$page] ?? $meta['home'];
}
?>

<!-- في header -->
<?php
$seo = getSEOMeta($current_page);
?>
<title><?= $seo['title'] ?></title>
<meta name="description" content="<?= $seo['description'] ?>">
<meta name="keywords" content="<?= $seo['keywords'] ?>">

<!-- Open Graph للمشاركة على وسائل التواصل -->
<meta property="og:title" content="<?= $seo['title'] ?>">
<meta property="og:description" content="<?= $seo['description'] ?>">
<meta property="og:image" content="<?= $seo['og_image'] ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?>">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $seo['title'] ?>">
<meta name="twitter:description" content="<?= $seo['description'] ?>">
<meta name="twitter:image" content="<?= $seo['og_image'] ?>">

<!-- Schema.org -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "GovernmentOrganization",
  "name": "بلدية تكريت",
  "url": "https://<?= $_SERVER['HTTP_HOST'] ?>",
  "logo": "<?= $seo['og_image'] ?>",
  "contactPoint": {
    "@type": "ContactPoint",
    "contactType": "Customer Service",
    "telephone": "+964-XXX-XXX-XXXX"
  }
}
</script>
```

### 10. Google Analytics

#### 📊 إضافة Google Analytics

```html
<!-- في includes/header.php قبل </head> -->
<?php if (isset($_ENV['GOOGLE_ANALYTICS_ID'])): ?>
<!-- Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= $_ENV['GOOGLE_ANALYTICS_ID'] ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '<?= $_ENV['GOOGLE_ANALYTICS_ID'] ?>');
</script>
<?php endif; ?>
```

---

## 🚀 إجراءات طويلة المدى (خلال 3 أشهر)

### 11. نظام Caching متقدم (Redis)

### 12. Progressive Web App (PWA)

### 13. Push Notifications

### 14. Multi-language Support

### 15. Advanced Analytics Dashboard

---

## ✅ Checklist للمتابعة

### المرحلة 1: تنظيف (Week 1)
- [ ] حذف الملفات المكررة
- [ ] نقل ملفات الاختبار
- [ ] تنظيم التوثيق
- [ ] إنشاء .env file
- [ ] إعداد Error Logging

### المرحلة 2: تحسين الأداء (Week 2-4)
- [ ] تحسين الصور (WebP + Lazy Loading)
- [ ] Minification (CSS + JS)
- [ ] Gzip Compression
- [ ] Browser Caching

### المرحلة 3: SEO والتسويق (Week 5-8)
- [ ] تحسين SEO Meta Tags
- [ ] إضافة Google Analytics
- [ ] Schema.org markup
- [ ] Sitemap.xml

### المرحلة 4: ميزات متقدمة (Month 3+)
- [ ] Redis Caching
- [ ] PWA
- [ ] Push Notifications
- [ ] Multi-language

---

**ملاحظة مهمة:** قم بإنشاء نسخة احتياطية كاملة من الموقع وقاعدة البيانات قبل تنفيذ أي من هذه التغييرات.

```bash
# Backup
tar -czf backup_$(date +%Y%m%d).tar.gz tekrit_municipality/
mysqldump -u root -p tekrit_municipality > backup_db_$(date +%Y%m%d).sql
```
