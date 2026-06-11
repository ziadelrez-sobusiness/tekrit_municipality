<?php
require_once 'config/database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html dir='rtl'><head><meta charset='UTF-8'>";
echo "<style>body{font-family:Arial;padding:20px;background:#f0f0f0;} .box{background:white;padding:20px;margin:10px 0;border-radius:5px;border-left:5px solid #007bff;} .success{border-color:#28a745;background:#d4edda;} .error{border-color:#dc3545;background:#f8d7da;}</style>";
echo "</head><body>";

echo "<h1>🔧 إضافة عمود income_currency_id</h1>";

$database = new Database();
$db = $database->getConnection();

// الخطوة 1: فحص العمود
echo "<div class='box'><h3>الخطوة 1: فحص العمود</h3>";
try {
    $result = $db->query("SHOW COLUMNS FROM citizens LIKE 'income_currency_id'");
    $exists = $result->rowCount() > 0;
    
    if ($exists) {
        echo "<p style='color:green;'>✅ العمود موجود بالفعل!</p>";
    } else {
        echo "<p style='color:red;'>❌ العمود غير موجود - سيتم إضافته...</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>خطأ: " . $e->getMessage() . "</p>";
    $exists = false;
}
echo "</div>";

// الخطوة 2: إضافة العمود
if (!$exists) {
    echo "<div class='box'><h3>الخطوة 2: إضافة العمود</h3>";
    try {
        $sql = "ALTER TABLE citizens ADD COLUMN income_currency_id INT(11) NULL";
        $db->exec($sql);
        echo "<p style='color:green;font-size:20px;'><b>✅✅✅ تم إضافة العمود بنجاح! ✅✅✅</b></p>";
        echo "<p>SQL: <code>$sql</code></p>";
    } catch (Exception $e) {
        echo "<p style='color:red;'><b>❌ فشل: " . $e->getMessage() . "</b></p>";
    }
    echo "</div>";
}

// الخطوة 3: التحقق النهائي
echo "<div class='box'><h3>الخطوة 3: التحقق النهائي</h3>";
try {
    $result = $db->query("SHOW COLUMNS FROM citizens LIKE 'income_currency_id'");
    if ($result->rowCount() > 0) {
        $col = $result->fetch(PDO::FETCH_ASSOC);
        echo "<div class='success'>";
        echo "<h2 style='color:green;'>🎉 نجح! العمود موجود الآن!</h2>";
        echo "<table border='1' style='width:100%;border-collapse:collapse;'>";
        echo "<tr><th style='padding:10px;background:#28a745;color:white;'>المعلومة</th><th style='padding:10px;background:#28a745;color:white;'>القيمة</th></tr>";
        foreach ($col as $key => $value) {
            echo "<tr><td style='padding:10px;'><b>$key</b></td><td style='padding:10px;'>$value</td></tr>";
        }
        echo "</table>";
        echo "</div>";
    } else {
        echo "<div class='error'>";
        echo "<p style='color:red;font-size:18px;'><b>❌ العمود ما زال غير موجود!</b></p>";
        echo "<p>يرجى نسخ هذا الأمر وتنفيذه في phpMyAdmin:</p>";
        echo "<pre style='background:#333;color:#0f0;padding:15px;border-radius:5px;'>ALTER TABLE citizens ADD COLUMN income_currency_id INT(11) NULL;</pre>";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>خطأ: " . $e->getMessage() . "</p>";
}
echo "</div>";

// الخطوة 4: عرض بنية الجدول
echo "<div class='box'><h3>الخطوة 4: بنية جدول citizens</h3>";
try {
    $result = $db->query("DESCRIBE citizens");
    echo "<table border='1' style='width:100%;border-collapse:collapse;'>";
    echo "<tr style='background:#007bff;color:white;'><th style='padding:8px;'>Field</th><th style='padding:8px;'>Type</th><th style='padding:8px;'>Null</th><th style='padding:8px;'>Key</th><th style='padding:8px;'>Default</th></tr>";
    $found = false;
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        if ($row['Field'] === 'income_currency_id') {
            echo "<tr style='background:#d4edda;font-weight:bold;'>";
            $found = true;
        } else {
            echo "<tr>";
        }
        echo "<td style='padding:8px;'>{$row['Field']}</td>";
        echo "<td style='padding:8px;'>{$row['Type']}</td>";
        echo "<td style='padding:8px;'>{$row['Null']}</td>";
        echo "<td style='padding:8px;'>{$row['Key']}</td>";
        echo "<td style='padding:8px;'>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    if ($found) {
        echo "<p style='color:green;font-size:18px;'><b>✅ العمود income_currency_id موجود في الجدول!</b></p>";
    } else {
        echo "<p style='color:red;font-size:18px;'><b>❌ العمود income_currency_id غير موجود في الجدول!</b></p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>خطأ: " . $e->getMessage() . "</p>";
}
echo "</div>";

// روابط
echo "<div style='text-align:center;margin-top:30px;'>";
echo "<a href='modules/citizens.php' style='display:inline-block;padding:15px 30px;background:#28a745;color:white;text-decoration:none;border-radius:5px;margin:5px;font-size:18px;'>📋 فتح صفحة المواطنين</a>";
echo "<a href='add_column_now.php' style='display:inline-block;padding:15px 30px;background:#007bff;color:white;text-decoration:none;border-radius:5px;margin:5px;font-size:18px;'>🔄 تحديث الصفحة</a>";
echo "</div>";

echo "</body></html>";

