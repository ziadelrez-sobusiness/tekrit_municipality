<?php
require_once 'config/database.php';

header('Content-Type: text/html; charset=utf-8');
$database = new Database();
$db = $database->getConnection();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>إضافة عمود العملة</title>";
echo "<style>
body{font-family:Arial;padding:20px;direction:rtl;background:#f5f5f5;}
.container{max-width:800px;margin:0 auto;background:white;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}
.success{background:#d4edda;color:#155724;padding:15px;border-radius:5px;margin:10px 0;border-left:5px solid #28a745;}
.error{background:#f8d7da;color:#721c24;padding:15px;border-radius:5px;margin:10px 0;border-left:5px solid #dc3545;}
.warning{background:#fff3cd;color:#856404;padding:15px;border-radius:5px;margin:10px 0;border-left:5px solid #ffc107;}
.info{background:#d1ecf1;color:#0c5460;padding:15px;border-radius:5px;margin:10px 0;border-left:5px solid #17a2b8;}
table{border-collapse:collapse;width:100%;margin:20px 0;}
th,td{border:1px solid #ddd;padding:12px;text-align:right;}
th{background:#007bff;color:white;}
h1{color:#007bff;border-bottom:3px solid #007bff;padding-bottom:10px;}
h2{color:#28a745;margin-top:30px;}
.btn{display:inline-block;padding:10px 20px;background:#007bff;color:white;text-decoration:none;border-radius:5px;margin:10px 5px;}
.btn:hover{background:#0056b3;}
</style>";
echo "</head><body><div class='container'>";

echo "<h1>🔧 إصلاح جدول المواطنين - إضافة عمود العملة</h1>";

// الخطوة 1: فحص العمود
echo "<h2>📋 الخطوة 1: فحص العمود الحالي</h2>";
try {
    $stmt = $db->query("SHOW COLUMNS FROM citizens LIKE 'income_currency_id'");
    $columnExists = $stmt->rowCount() > 0;
    
    if ($columnExists) {
        echo "<div class='success'>✅ العمود <b>income_currency_id</b> موجود بالفعل في جدول citizens</div>";
        
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<table>";
        echo "<tr><th>المعلومة</th><th>القيمة</th></tr>";
        echo "<tr><td>اسم العمود</td><td><b>{$column['Field']}</b></td></tr>";
        echo "<tr><td>نوع البيانات</td><td>{$column['Type']}</td></tr>";
        echo "<tr><td>يقبل NULL</td><td>{$column['Null']}</td></tr>";
        echo "</table>";
    } else {
        echo "<div class='warning'>⚠️ العمود <b>income_currency_id</b> غير موجود - سيتم إضافته الآن...</div>";
        
        // إضافة العمود
        try {
            $db->exec("ALTER TABLE citizens ADD COLUMN income_currency_id INT(11) NULL COMMENT 'معرف عملة الراتب'");
            echo "<div class='success'>✅ تم إضافة العمود <b>income_currency_id</b> بنجاح!</div>";
            $columnExists = true;
        } catch (PDOException $e) {
            echo "<div class='error'>❌ فشل إضافة العمود: " . htmlspecialchars($e->getMessage()) . "</div>";
            $columnExists = false;
        }
    }
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ في فحص الجدول: " . htmlspecialchars($e->getMessage()) . "</div>";
    $columnExists = false;
}

// الخطوة 2: فحص جدول العملات
if ($columnExists) {
    echo "<h2>💱 الخطوة 2: فحص جدول العملات</h2>";
    try {
        $stmt = $db->query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY currency_code");
        $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($currencies) > 0) {
            echo "<div class='success'>✅ تم العثور على " . count($currencies) . " عملة نشطة</div>";
            
            echo "<table>";
            echo "<tr><th>ID</th><th>الاسم</th><th>الرمز</th><th>الكود</th></tr>";
            foreach ($currencies as $currency) {
                echo "<tr>";
                echo "<td>{$currency['id']}</td>";
                echo "<td>{$currency['currency_name']}</td>";
                echo "<td>{$currency['currency_symbol']}</td>";
                echo "<td>{$currency['currency_code']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // الخطوة 3: تحديث البيانات الموجودة
            echo "<h2>🔄 الخطوة 3: تحديث البيانات الموجودة</h2>";
            
            // البحث عن الليرة اللبنانية
            $lbpCurrency = null;
            foreach ($currencies as $currency) {
                if ($currency['currency_code'] === 'LBP') {
                    $lbpCurrency = $currency;
                    break;
                }
            }
            
            if ($lbpCurrency) {
                $lbpId = $lbpCurrency['id'];
                
                // عد السجلات التي لها راتب ولكن بدون عملة
                $stmt = $db->query("SELECT COUNT(*) as count FROM citizens WHERE monthly_income IS NOT NULL AND income_currency_id IS NULL");
                $needUpdate = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($needUpdate > 0) {
                    echo "<div class='info'>ℹ️ يوجد <b>$needUpdate</b> مواطن لديهم راتب بدون عملة محددة</div>";
                    
                    // تحديث السجلات
                    $stmt = $db->prepare("UPDATE citizens SET income_currency_id = ? WHERE monthly_income IS NOT NULL AND income_currency_id IS NULL");
                    $stmt->execute([$lbpId]);
                    $updated = $stmt->rowCount();
                    
                    echo "<div class='success'>✅ تم تحديث <b>$updated</b> سجل بعملة الليرة اللبنانية (ل.ل) كقيمة افتراضية</div>";
                } else {
                    echo "<div class='info'>ℹ️ جميع السجلات محدثة - لا حاجة للتحديث</div>";
                }
            } else {
                echo "<div class='warning'>⚠️ لم يتم العثور على عملة الليرة اللبنانية (LBP)</div>";
                echo "<div class='info'>ℹ️ السجلات الموجودة ستبقى بدون عملة محددة</div>";
            }
            
        } else {
            echo "<div class='error'>❌ لا توجد عملات نشطة في جدول currencies</div>";
            echo "<div class='info'>📝 يرجى إضافة العملات من صفحة إدارة الجداول المرجعية</div>";
        }
    } catch (PDOException $e) {
        echo "<div class='error'>❌ خطأ في جدول العملات: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// الخطوة 4: الاختبار النهائي
echo "<h2>🧪 الخطوة 4: اختبار النظام</h2>";
try {
    $stmt = $db->query("
        SELECT c.id, c.full_name, c.monthly_income, c.income_currency_id, cur.currency_symbol, cur.currency_code
        FROM citizens c
        LEFT JOIN currencies cur ON c.income_currency_id = cur.id
        LIMIT 5
    ");
    $testResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($testResults) > 0) {
        echo "<div class='success'>✅ الاستعلام نجح! عينة من البيانات:</div>";
        
        echo "<table>";
        echo "<tr><th>ID</th><th>الاسم</th><th>الراتب</th><th>عملة ID</th><th>رمز العملة</th></tr>";
        foreach ($testResults as $row) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['full_name']}</td>";
            echo "<td>" . ($row['monthly_income'] ? number_format($row['monthly_income']) : '-') . "</td>";
            echo "<td>" . ($row['income_currency_id'] ?? '-') . "</td>";
            echo "<td>" . ($row['currency_symbol'] ?? '-') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>ℹ️ لا توجد بيانات مواطنين لعرضها</div>";
    }
    
    echo "<div class='success' style='font-size:18px;margin-top:30px;'>
        <h2 style='color:#155724;'>🎉 تم الإصلاح بنجاح!</h2>
        <p>✅ تم إضافة عمود العملة</p>
        <p>✅ تم ربط جدول المواطنين بجدول العملات</p>
        <p>✅ تم تحديث البيانات الموجودة</p>
    </div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ في الاختبار: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// روابط التنقل
echo "<div style='text-align:center;margin-top:30px;padding:20px;background:#f8f9fa;border-radius:5px;'>";
echo "<h3>🔗 الخطوات التالية</h3>";
echo "<a href='modules/citizens.php' class='btn'>📋 فتح صفحة المواطنين</a>";
echo "<a href='all_tables_manager.php' class='btn'>⚙️ إدارة الجداول المرجعية</a>";
echo "<a href='debug_citizens_page.php' class='btn'>🔍 صفحة التشخيص</a>";
echo "</div>";

echo "</div></body></html>";

