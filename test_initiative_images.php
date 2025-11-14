<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

// جلب المبادرات مع معرفاتها
$stmt = $db->prepare("SELECT id, initiative_name FROM youth_environmental_initiatives LIMIT 10");
$stmt->execute();
$initiatives = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>اختبار صور المبادرات</h2>";
echo "<p>اختبار جلب وعرض صور المبادرات من قاعدة البيانات</p>";

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background-color: #f2f2f2;'>";
echo "<th style='padding: 10px;'>ID</th>";
echo "<th style='padding: 10px;'>اسم المبادرة</th>";
echo "<th style='padding: 10px;'>اختبار الصور</th>";
echo "</tr>";

foreach ($initiatives as $initiative) {
    echo "<tr>";
    echo "<td style='padding: 10px;'>" . htmlspecialchars($initiative['id']) . "</td>";
    echo "<td style='padding: 10px;'>" . htmlspecialchars($initiative['initiative_name']) . "</td>";
    echo "<td style='padding: 10px;'><button onclick=\"testImages(" . $initiative['id'] . ")\">اختبار الصور</button></td>";
    echo "</tr>";
}

echo "</table>";

// اختبار مباشر للصور
echo "<h3>اختبار مباشر للصور</h3>";
echo "<div id='imageResults'></div>";

// عرض مسارات الصور الموجودة
echo "<h3>الصور الموجودة في uploads/initiatives/</h3>";
$imagesPath = 'uploads/initiatives/';
if (is_dir($imagesPath)) {
    $files = scandir($imagesPath);
    echo "<ul>";
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "<li><a href='$imagesPath$file' target='_blank'>$file</a></li>";
        }
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'>مجلد الصور غير موجود!</p>";
}
?>

<script>
function testImages(initiativeId) {
    console.log('🔍 اختبار صور المبادرة:', initiativeId);
    
    fetch('modules/get_initiative_images.php?id=' + initiativeId)
        .then(response => {
            console.log('📡 استجابة الخادم:', response.status);
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(images => {
            console.log('📦 صور المبادرة:', images);
            
            let html = '<h4>صور المبادرة ' + initiativeId + ':</h4>';
            
            if (images.length === 0) {
                html += '<p>لا توجد صور لهذه المبادرة</p>';
            } else {
                html += '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
                images.forEach(image => {
                    html += `
                        <div style="border: 1px solid #ccc; padding: 10px; margin: 5px;">
                            <img src="${image.image_path}" 
                                 alt="${image.image_name}" 
                                 style="width: 100px; height: 100px; object-fit: cover;">
                            <p><strong>اسم الصورة:</strong> ${image.image_name}</p>
                            <p><strong>نوع الصورة:</strong> ${image.image_type}</p>
                            <p><strong>المسار:</strong> ${image.image_path}</p>
                        </div>
                    `;
                });
                html += '</div>';
            }
            
            document.getElementById('imageResults').innerHTML = html;
        })
        .catch(error => {
            console.error('❌ خطأ:', error);
            document.getElementById('imageResults').innerHTML = 
                '<p style="color: red;">خطأ في جلب الصور: ' + error.message + '</p>';
        });
}
</script> 