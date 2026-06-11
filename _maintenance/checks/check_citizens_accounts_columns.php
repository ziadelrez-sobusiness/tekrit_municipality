<?php
/**
 * فحص أعمدة جدول citizens_accounts
 */

try {
    $db = new PDO('mysql:host=localhost;dbname=tekrit_municipality;charset=utf8mb4', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1 style='font-family: Arial; color: #333;'>🔍 فحص جدول citizens_accounts</h1>";
    
    // عرض أعمدة الجدول
    echo "<h2>أعمدة الجدول:</h2>";
    $columns = $db->query("SHOW COLUMNS FROM citizens_accounts")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div style='background: #e8f5e9; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>";
    echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #4caf50; color: white;'>";
    echo "<th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
    echo "</tr>";
    
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td><strong>" . $col['Field'] . "</strong></td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . $col['Extra'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    echo "</div>";
    
    // عرض بيانات نموذجية
    echo "<h2>بيانات نموذجية (أول 5 سجلات):</h2>";
    $data = $db->query("SELECT * FROM citizens_accounts LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($data) > 0) {
        echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 8px;'>";
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%; font-size: 12px;'>";
        
        // العناوين
        echo "<tr style='background: #2196f3; color: white;'>";
        foreach (array_keys($data[0]) as $header) {
            echo "<th>" . $header . "</th>";
        }
        echo "</tr>";
        
        // البيانات
        foreach ($data as $row) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        
        echo "</table>";
        echo "</div>";
    } else {
        echo "<div style='background: #fff3e0; padding: 15px; border-radius: 8px;'>";
        echo "⚠️ الجدول فارغ - لا توجد بيانات";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #ffebee; padding: 15px; border-radius: 8px;'>";
    echo "❌ خطأ: " . $e->getMessage();
    echo "</div>";
}
?>

