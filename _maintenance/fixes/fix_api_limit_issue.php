<?php
echo "<h1>🔧 إصلاح مشكلة LIMIT في API</h1>";

// قراءة محتوى الملف الحالي
$api_file = 'modules/facilities_api.php';
$content = file_get_contents($api_file);

// إصلاح مشكلة LIMIT
$fixed_content = str_replace(
    'LIMIT ?',
    'LIMIT $limit',
    $content
);

// إزالة إضافة limit للـ params
$fixed_content = str_replace(
    '$params[] = $limit;',
    '// تم إزالة $limit من params لتجنب مشكلة LIMIT',
    $fixed_content
);

// نفس الشيء للـ search_nearby
$fixed_content = str_replace(
    '$params[] = $radius;
            $params[] = $limit;',
    '$params[] = $radius;
            // تم إزالة $limit من params لتجنب مشكلة LIMIT',
    $fixed_content
);

$fixed_content = str_replace(
    'LIMIT ?
            ";
            
            $stmt = $db->prepare($query);',
    'LIMIT $limit
            ";
            
            $stmt = $db->prepare($query);',
    $fixed_content
);

// حفظ الملف المحدث
file_put_contents($api_file, $fixed_content);

echo "<p style='color: green;'>✅ تم إصلاح مشكلة LIMIT في API</p>";

// اختبار API
echo "<h2>🧪 اختبار API بعد الإصلاح:</h2>";

$api_url = 'http://localhost:8080/tekrit_municipality/modules/facilities_api.php?action=get_facilities';

$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'ignore_errors' => true
    ]
]);

$response = @file_get_contents($api_url, false, $context);

if ($response !== false) {
    $json = json_decode($response, true);
    
    if ($json && $json['success']) {
        echo "<p style='color: green; font-size: 18px; font-weight: bold;'>✅ API يعمل الآن بنجاح!</p>";
        
        if (isset($json['facilities'])) {
            $count = count($json['facilities']);
            echo "<p><strong>عدد المرافق المسترجعة:</strong> $count</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ ما زال هناك خطأ:</p>";
        if (isset($json['error'])) {
            echo "<p>{$json['error']}</p>";
        }
        echo "<pre>$response</pre>";
    }
} else {
    echo "<p style='color: red;'>❌ فشل في الوصول لـ API</p>";
}

echo "<h3>🔗 روابط الاختبار:</h3>";
echo "<a href='$api_url' target='_blank' style='background: #4caf50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🔗 اختبار API</a>";
echo "<a href='public/facilities-map.php' target='_blank' style='background: #2196f3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🗺️ الخريطة</a>";

echo "<h3>💡 ملاحظة:</h3>";
echo "<p>تم إصلاح مشكلة LIMIT عبر استخدام المتغير مباشرة في SQL بدلاً من parameter في prepared statement.</p>";
?> 