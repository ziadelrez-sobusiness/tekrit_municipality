<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فحص الشكاوى - Debug</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-2xl font-bold mb-4">🔍 فحص الشكاوى في قاعدة البيانات</h1>
        
        <?php
        try {
            // جلب جميع الشكاوى
            $stmt = $db->query("
                SELECT 
                    id,
                    complaint_number,
                    subject,
                    citizen_name,
                    citizen_phone,
                    status,
                    created_at
                FROM complaints 
                ORDER BY id DESC 
                LIMIT 20
            ");
            $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo '<div class="bg-white rounded-lg shadow p-6">';
            echo '<h2 class="text-xl font-bold mb-4">الشكاوى الموجودة (' . count($complaints) . ')</h2>';
            
            if (empty($complaints)) {
                echo '<p class="text-red-600">❌ لا توجد شكاوى في قاعدة البيانات</p>';
            } else {
                echo '<table class="w-full border-collapse border border-gray-300">';
                echo '<thead class="bg-gray-200">';
                echo '<tr>';
                echo '<th class="border border-gray-300 p-2">ID</th>';
                echo '<th class="border border-gray-300 p-2">رقم الشكوى</th>';
                echo '<th class="border border-gray-300 p-2">الموضوع</th>';
                echo '<th class="border border-gray-300 p-2">الاسم</th>';
                echo '<th class="border border-gray-300 p-2">الهاتف</th>';
                echo '<th class="border border-gray-300 p-2">الحالة</th>';
                echo '<th class="border border-gray-300 p-2">التاريخ</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
                
                foreach ($complaints as $comp) {
                    echo '<tr>';
                    echo '<td class="border border-gray-300 p-2">' . htmlspecialchars($comp['id']) . '</td>';
                    echo '<td class="border border-gray-300 p-2 font-mono">' . htmlspecialchars($comp['complaint_number'] ?? 'NULL') . '</td>';
                    echo '<td class="border border-gray-300 p-2">' . htmlspecialchars($comp['subject'] ?? '') . '</td>';
                    echo '<td class="border border-gray-300 p-2">' . htmlspecialchars($comp['citizen_name'] ?? '') . '</td>';
                    echo '<td class="border border-gray-300 p-2">' . htmlspecialchars($comp['citizen_phone'] ?? '') . '</td>';
                    echo '<td class="border border-gray-300 p-2">' . htmlspecialchars($comp['status'] ?? '') . '</td>';
                    echo '<td class="border border-gray-300 p-2">' . htmlspecialchars($comp['created_at'] ?? '') . '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody>';
                echo '</table>';
            }
            
            echo '</div>';
            
            // فحص التنسيقات المختلفة
            echo '<div class="bg-white rounded-lg shadow p-6 mt-6">';
            echo '<h2 class="text-xl font-bold mb-4">🔍 فحص التنسيقات</h2>';
            
            // البحث عن SHK-2025-00002
            $testNumber = 'SHK-2025-00002';
            $testStmt = $db->prepare("SELECT id, complaint_number FROM complaints WHERE complaint_number = ?");
            $testStmt->execute([$testNumber]);
            $testResult = $testStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($testResult) {
                echo '<p class="text-green-600">✅ تم العثور على: ' . $testNumber . ' (ID: ' . $testResult['id'] . ')</p>';
            } else {
                echo '<p class="text-red-600">❌ لم يتم العثور على: ' . $testNumber . '</p>';
            }
            
            // البحث الجزئي
            $partialStmt = $db->prepare("SELECT id, complaint_number FROM complaints WHERE complaint_number LIKE ?");
            $partialStmt->execute(['%2025-00002%']);
            $partialResults = $partialStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($partialResults)) {
                echo '<p class="text-blue-600">🔍 البحث الجزئي (%2025-00002%):</p>';
                echo '<ul class="list-disc list-inside">';
                foreach ($partialResults as $res) {
                    echo '<li>ID: ' . $res['id'] . ' - Number: ' . ($res['complaint_number'] ?? 'NULL') . '</li>';
                }
                echo '</ul>';
            }
            
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="bg-red-100 border border-red-400 text-red-700 p-4 rounded">';
            echo '<p>❌ خطأ: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
        ?>
        
        <div class="mt-6">
            <a href="track-complaint.php" class="inline-block bg-blue-600 text-white px-4 py-2 rounded">العودة إلى صفحة التتبع</a>
        </div>
    </div>
</body>
</html>


