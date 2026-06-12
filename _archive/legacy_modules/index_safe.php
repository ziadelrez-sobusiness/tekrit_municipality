<?php
// تعطيل عرض الأخطاء للمستخدم النهائي
error_reporting(0);
ini_set('display_errors', 0);

// التحقق من وجود المجلد والملف
if (!is_dir('public')) {
    die('خطأ: مجلد public غير موجود');
}

if (!file_exists('public/index.php')) {
    die('خطأ: ملف public/index.php غير موجود');
}

// محاولة إعادة التوجيه
try {
    header('Location: public/index.php', true, 302);
    exit();
} catch (Exception $e) {
    // في حالة فشل إعادة التوجيه، عرض رابط يدوي
    echo '<!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>بلدية تكريت</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
            .btn { background: #4F46E5; color: white; padding: 15px 30px; 
                   text-decoration: none; border-radius: 5px; display: inline-block; }
        </style>
    </head>
    <body>
        <h1>🏛️ مرحباً بكم في بلدية تكريت</h1>
        <p>يتم توجيهكم إلى الموقع الرسمي...</p>
        <a href="public/index.php" class="btn">انقر هنا للدخول إلى الموقع</a>
        <script>
            // إعادة توجيه تلقائي بعد ثانيتين
            setTimeout(function() {
                window.location.href = "public/index.php";
            }, 2000);
        </script>
    </body>
    </html>';
}
?> 