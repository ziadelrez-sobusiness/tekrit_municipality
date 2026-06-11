<?php
require_once 'config/database.php';

header('Content-Type: text/html; charset=utf-8');
$database = new Database();
$db = $database->getConnection();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>تشخيص صفحة المواطنين</title>";
echo "<style>body{font-family:Arial;padding:20px;direction:rtl;} .success{color:green;} .error{color:red;} .warning{color:orange;} table{border-collapse:collapse;width:100%;margin:10px 0;} th,td{border:1px solid #ddd;padding:8px;text-align:right;} th{background:#f2f2f2;}</style>";
echo "</head><body>";

echo "<h1>🔍 تشخيص صفحة المواطنين</h1>";
echo "<hr>";

// 1. فحص جدول العملات
echo "<h2>1️⃣ فحص جدول العملات (currencies)</h2>";
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM currencies");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count > 0) {
        echo "<p class='success'>✅ جدول العملات موجود ويحتوي على $count عملة</p>";
        
        $stmt = $db->query("SELECT * FROM currencies WHERE is_active = 1");
        $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p>العملات النشطة: " . count($currencies) . "</p>";
        echo "<table><tr><th>ID</th><th>الاسم</th><th>الرمز</th><th>الكود</th><th>نشط</th></tr>";
        
        $stmt = $db->query("SELECT * FROM currencies");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $active = $row['is_active'] ? '✅' : '❌';
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['currency_name']}</td>";
            echo "<td>{$row['currency_symbol']}</td>";
            echo "<td>{$row['currency_code']}</td>";
            echo "<td>$active</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>⚠️ جدول العملات فارغ - لا توجد عملات</p>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>❌ خطأ في جدول العملات: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// 2. فحص جدول المواطنين
echo "<h2>2️⃣ فحص جدول المواطنين (citizens)</h2>";
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM citizens");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p class='success'>✅ جدول المواطنين موجود ويحتوي على $count مواطن</p>";
} catch (PDOException $e) {
    echo "<p class='error'>❌ خطأ في جدول المواطنين: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// 3. فحص عمود income_currency_id
echo "<h2>3️⃣ فحص عمود income_currency_id</h2>";
try {
    $stmt = $db->query("SHOW COLUMNS FROM citizens LIKE 'income_currency_id'");
    $columnExists = $stmt->rowCount() > 0;
    
    if ($columnExists) {
        echo "<p class='success'>✅ عمود income_currency_id موجود</p>";
        
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<table><tr><th>المعلومة</th><th>القيمة</th></tr>";
        echo "<tr><td>اسم العمود</td><td>{$column['Field']}</td></tr>";
        echo "<tr><td>نوع البيانات</td><td>{$column['Type']}</td></tr>";
        echo "<tr><td>Null</td><td>{$column['Null']}</td></tr>";
        echo "<tr><td>Key</td><td>{$column['Key']}</td></tr>";
        echo "<tr><td>Default</td><td>{$column['Default']}</td></tr>";
        echo "</table>";
    } else {
        echo "<p class='error'>❌ عمود income_currency_id غير موجود</p>";
        echo "<p class='warning'>📝 يجب تشغيل: <a href='add_income_currency_column.php' target='_blank'>add_income_currency_column.php</a></p>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>❌ خطأ: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// 4. فحص بنية جدول citizens
echo "<h2>4️⃣ بنية جدول citizens</h2>";
try {
    $stmt = $db->query("DESCRIBE citizens");
    echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    $hasIncomeColumn = false;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['Field'] === 'income_currency_id') {
            echo "<tr style='background:#d4edda;font-weight:bold;'>";
            $hasIncomeColumn = true;
        } else {
            echo "<tr>";
        }
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    if (!$hasIncomeColumn) {
        echo "<p class='error'>⚠️ عمود income_currency_id غير موجود في الجدول</p>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>❌ خطأ: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// 5. اختبار الاستعلام
echo "<h2>5️⃣ اختبار استعلام جلب المواطنين</h2>";
try {
    $columnsStmt = $db->query("SHOW COLUMNS FROM citizens LIKE 'income_currency_id'");
    $columnExists = $columnsStmt->rowCount() > 0;
    
    if ($columnExists) {
        $stmt = $db->prepare("
            SELECT c.*, cur.currency_symbol, cur.currency_code 
            FROM citizens c
            LEFT JOIN currencies cur ON c.income_currency_id = cur.id
            ORDER BY c.created_at DESC 
            LIMIT 5
        ");
        $stmt->execute();
        $citizens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p class='success'>✅ الاستعلام نجح - تم جلب " . count($citizens) . " مواطنين</p>";
        
        if (count($citizens) > 0) {
            echo "<h3>عينة من البيانات:</h3>";
            echo "<table><tr><th>ID</th><th>الاسم</th><th>الراتب</th><th>عملة ID</th><th>رمز العملة</th></tr>";
            foreach ($citizens as $citizen) {
                echo "<tr>";
                echo "<td>{$citizen['id']}</td>";
                echo "<td>{$citizen['full_name']}</td>";
                echo "<td>" . ($citizen['monthly_income'] ?? '-') . "</td>";
                echo "<td>" . ($citizen['income_currency_id'] ?? '-') . "</td>";
                echo "<td>" . ($citizen['currency_symbol'] ?? '-') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        $stmt = $db->prepare("SELECT * FROM citizens ORDER BY created_at DESC LIMIT 5");
        $stmt->execute();
        $citizens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p class='warning'>⚠️ الاستعلام بدون عمود income_currency_id - تم جلب " . count($citizens) . " مواطنين</p>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>❌ خطأ في الاستعلام: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// 6. الخلاصة والتوصيات
echo "<h2>📋 الخلاصة والتوصيات</h2>";
$stmt = $db->query("SELECT COUNT(*) as count FROM currencies WHERE is_active = 1");
$currenciesCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $db->query("SHOW COLUMNS FROM citizens LIKE 'income_currency_id'");
$hasColumn = $stmt->rowCount() > 0;

if ($currenciesCount == 0) {
    echo "<p class='error'>❌ <b>المشكلة 1:</b> لا توجد عملات نشطة في جدول currencies</p>";
    echo "<p>✅ <b>الحل:</b> افتح <a href='all_tables_manager.php' target='_blank'>إدارة الجداول المرجعية</a> وأضف العملات (ليرة لبنانية، دولار، يورو)</p>";
}

if (!$hasColumn) {
    echo "<p class='error'>❌ <b>المشكلة 2:</b> عمود income_currency_id غير موجود في جدول citizens</p>";
    echo "<p>✅ <b>الحل:</b> افتح <a href='add_income_currency_column.php' target='_blank' style='font-weight:bold;font-size:18px;color:blue;'>add_income_currency_column.php</a> لإضافة العمود</p>";
}

if ($currenciesCount > 0 && $hasColumn) {
    echo "<p class='success' style='font-size:18px;'>✅✅✅ <b>كل شيء جاهز!</b> يمكنك الآن استخدام <a href='modules/citizens.php' target='_blank'>صفحة المواطنين</a></p>";
}

echo "</body></html>";

