<?php
header('Content-Type: text/html; charset=utf-8');
try {
    $pdo = new PDO("mysql:host=localhost;dbname=tekrit_municipality;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🔍 الأعمدة الموجودة في جدول citizens_accounts</h2>";
    
    $stmt = $pdo->query("DESCRIBE citizens_accounts");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #2563eb; color: white;'>";
    echo "<th style='padding: 10px;'>اسم العمود</th>";
    echo "<th style='padding: 10px;'>النوع</th>";
    echo "<th style='padding: 10px;'>Null</th>";
    echo "<th style='padding: 10px;'>Key</th>";
    echo "<th style='padding: 10px;'>Default</th>";
    echo "<th style='padding: 10px;'>Extra</th>";
    echo "</tr>";
    
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td style='padding: 10px;'><strong>" . htmlspecialchars($col['Field']) . "</strong></td>";
        echo "<td style='padding: 10px;'>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td style='padding: 10px;'>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td style='padding: 10px;'>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td style='padding: 10px;'>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "<td style='padding: 10px;'>" . htmlspecialchars($col['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>قائمة الأعمدة فقط:</h3>";
    echo "<ul>";
    foreach ($columns as $col) {
        echo "<li><code>" . htmlspecialchars($col['Field']) . "</code></li>";
    }
    echo "</ul>";
    
    // Sample data
    echo "<h3>عينة من البيانات:</h3>";
    $stmt = $pdo->query("SELECT * FROM citizens_accounts LIMIT 3");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($data) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #10b981; color: white;'>";
        foreach (array_keys($data[0]) as $key) {
            echo "<th style='padding: 10px;'>" . htmlspecialchars($key) . "</th>";
        }
        echo "</tr>";
        
        foreach ($data as $row) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td style='padding: 10px;'>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطأ: " . htmlspecialchars($e->getMessage()) . "</p>";
}

