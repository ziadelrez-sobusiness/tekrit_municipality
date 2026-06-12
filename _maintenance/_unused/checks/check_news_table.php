<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h1>🔍 فحص جدول الأخبار والأنشطة</h1>";

// فحص بنية الجدول
echo "<h2>📋 بنية الجدول news_activities:</h2>";
try {
    $stmt = $db->query('DESCRIBE news_activities');
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>اسم الحقل</th><th>النوع</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطأ: " . $e->getMessage() . "</p>";
}

// فحص البيانات
echo "<h2>📰 عينة من الأخبار:</h2>";
try {
    $stmt = $db->query('SELECT * FROM news_activities LIMIT 3');
    $news = $stmt->fetchAll();
    
    if (empty($news)) {
        echo "<p>⚠️ لا توجد أخبار في الجدول</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>العنوان</th><th>النوع</th><th>تاريخ النشر</th><th>مميز</th></tr>";
        foreach ($news as $item) {
            echo "<tr>";
            echo "<td>" . $item['id'] . "</td>";
            echo "<td>" . substr($item['title'], 0, 50) . "...</td>";
            echo "<td>" . ($item['news_type'] ?? 'غير محدد') . "</td>";
            echo "<td>" . ($item['publish_date'] ?? 'غير محدد') . "</td>";
            echo "<td>" . ($item['is_featured'] ? 'نعم' : 'لا') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطأ في جلب البيانات: " . $e->getMessage() . "</p>";
}
?> 