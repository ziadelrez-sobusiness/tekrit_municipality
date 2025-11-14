<?php
require_once 'config/database.php';

header('Content-Type: text/html; charset=utf-8');

$database = new Database();
$db = $database->getConnection();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>إضافة عمود العملة للراتب</title></head><body>";
echo "<h2>إضافة عمود income_currency_id لجدول المواطنين</h2>";

try {
    // فحص إذا كان العمود موجوداً
    $stmt = $db->query("SHOW COLUMNS FROM citizens LIKE 'income_currency_id'");
    $columnExists = $stmt->rowCount() > 0;
    
    if ($columnExists) {
        echo "<p style='color: orange;'>✓ العمود income_currency_id موجود بالفعل</p>";
    } else {
        // إضافة العمود
        try {
            $db->exec("ALTER TABLE citizens ADD COLUMN income_currency_id INT(11) NULL AFTER monthly_income");
            echo "<p style='color: green;'>✓ تم إضافة العمود income_currency_id بنجاح</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ خطأ في إضافة العمود: " . $e->getMessage() . "</p>";
        }
        
        // إضافة Foreign Key
        try {
            // التحقق من عدم وجود Foreign Key بنفس الاسم
            $stmt = $db->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS 
                               WHERE TABLE_SCHEMA = DATABASE() 
                               AND TABLE_NAME = 'citizens' 
                               AND CONSTRAINT_NAME = 'fk_citizens_currency'");
            
            if ($stmt->rowCount() == 0) {
                $db->exec("ALTER TABLE citizens ADD CONSTRAINT fk_citizens_currency 
                           FOREIGN KEY (income_currency_id) REFERENCES currencies(id) 
                           ON DELETE SET NULL ON UPDATE CASCADE");
                echo "<p style='color: green;'>✓ تم إضافة Foreign Key بنجاح</p>";
            } else {
                echo "<p style='color: orange;'>✓ Foreign Key موجود بالفعل</p>";
            }
        } catch (PDOException $e) {
            echo "<p style='color: orange;'>⚠️ تحذير في Foreign Key: " . $e->getMessage() . "</p>";
            echo "<p style='color: blue;'>ℹ️ العمود تم إضافته بنجاح، Foreign Key اختياري</p>";
        }
    }
    
    // تحديث السجلات الموجودة بالعملة الافتراضية (ليرة لبنانية)
    $stmt = $db->query("SELECT id FROM currencies WHERE currency_code = 'LBP' LIMIT 1");
    $lbpCurrency = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lbpCurrency) {
        $lbpId = $lbpCurrency['id'];
        $db->exec("UPDATE citizens SET income_currency_id = $lbpId WHERE monthly_income IS NOT NULL AND income_currency_id IS NULL");
        echo "<p style='color: green;'>✓ تم تحديث السجلات الموجودة بالليرة اللبنانية (ID: $lbpId)</p>";
    }
    
    // عرض البنية الجديدة
    echo "<h3>بنية جدول citizens المحدثة:</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    $stmt = $db->query('DESCRIBE citizens');
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ خطأ: " . $e->getMessage() . "</p>";
}

echo "</body></html>";

