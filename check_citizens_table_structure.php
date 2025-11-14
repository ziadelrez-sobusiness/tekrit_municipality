<?php
header('Content-Type: text/html; charset=utf-8');

try {
    $pdo = new PDO("mysql:host=localhost;dbname=tekrit_municipality;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🔍 فحص جدول citizens_accounts</h2>";
    
    // Get table structure
    $stmt = $pdo->query("DESCRIBE citizens_accounts");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>الأعمدة الموجودة:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>اسم العمود</th><th>النوع</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($col['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($col['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check for primary key
    echo "<h3>المفتاح الأساسي:</h3>";
    $primaryKey = null;
    foreach ($columns as $col) {
        if ($col['Key'] === 'PRI') {
            $primaryKey = $col['Field'];
            echo "<p style='color: green;'>✅ المفتاح الأساسي: <strong>" . htmlspecialchars($primaryKey) . "</strong></p>";
            break;
        }
    }
    
    if (!$primaryKey) {
        echo "<p style='color: red;'>❌ لا يوجد مفتاح أساسي!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطأ: " . htmlspecialchars($e->getMessage()) . "</p>";
}

