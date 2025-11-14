<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=tekrit_municipality;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query("DESCRIBE citizens_accounts");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "الأعمدة الموجودة في جدول citizens_accounts:\n\n";
    foreach ($columns as $col) {
        echo "- " . $col . "\n";
    }
    
} catch (Exception $e) {
    echo "خطأ: " . $e->getMessage();
}

