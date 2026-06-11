<?php
header('Content-Type: text/html; charset=utf-8');
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

echo '<h2>فحص بيانات الأخبار والصور</h2>';

// التحقق من جدول الأخبار
try {
    $stmt = $db->query("SELECT id, title, featured_image, is_published, created_at FROM news_activities WHERE is_published = 1 ORDER BY created_at DESC LIMIT 10");
    $news = $stmt->fetchAll();
    
    if (empty($news)) {
        echo '<p style="color: red;">لا توجد أخبار منشورة في قاعدة البيانات.</p>';
    } else {
        echo '<p style="color: green;">عدد الأخبار المنشورة: ' . count($news) . '</p>';
        
        echo '<table border="1" style="border-collapse: collapse; width: 100%; margin-top: 20px;">';
        echo '<tr><th>ID</th><th>العنوان</th><th>الصورة المميزة</th><th>حالة الصورة</th><th>تاريخ الإنشاء</th></tr>';
        
        foreach ($news as $item) {
            $image_path = 'uploads/news/' . $item['featured_image'];
            $image_exists = $item['featured_image'] && file_exists($image_path);
            
            echo '<tr>';
            echo '<td>' . $item['id'] . '</td>';
            echo '<td>' . htmlspecialchars($item['title']) . '</td>';
            echo '<td>' . ($item['featured_image'] ? htmlspecialchars($item['featured_image']) : 'لا توجد صورة') . '</td>';
            echo '<td style="color: ' . ($image_exists ? 'green' : 'red') . ';">' . ($image_exists ? 'موجودة' : 'غير موجودة') . '</td>';
            echo '<td>' . $item['created_at'] . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        
        // عرض مسار الصور
        echo '<h3>مسار الصور:</h3>';
        echo '<p>مجلد الصور: uploads/news/</p>';
        echo '<p>المسار المستخدم في الكود: ../uploads/news/</p>';
        
        // فحص مجلد الصور
        $upload_dir = 'uploads/news/';
        if (is_dir($upload_dir)) {
            echo '<h3>محتويات مجلد الصور:</h3>';
            $files = scandir($upload_dir);
            echo '<ul>';
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    echo '<li>' . $file . '</li>';
                }
            }
            echo '</ul>';
        } else {
            echo '<p style="color: red;">مجلد الصور غير موجود: ' . $upload_dir . '</p>';
        }
    }
    
} catch (Exception $e) {
    echo '<p style="color: red;">خطأ في الاستعلام: ' . $e->getMessage() . '</p>';
}
?> 