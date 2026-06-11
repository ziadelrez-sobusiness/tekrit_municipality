<?php
require_once 'config/database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html dir='rtl'><head><meta charset='UTF-8'>";
echo "<title>إعداد النظام المالي المتكامل</title>";
echo "<style>
body{font-family:'Cairo',Arial;padding:30px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;}
.container{max-width:1200px;margin:0 auto;background:white;padding:40px;border-radius:15px;box-shadow:0 10px 40px rgba(0,0,0,0.3);}
h1{color:#667eea;border-bottom:3px solid #667eea;padding-bottom:15px;margin-bottom:30px;}
h2{color:#764ba2;margin-top:30px;padding:10px;background:#f8f9fa;border-right:5px solid #764ba2;}
.success{background:#d4edda;color:#155724;padding:15px;margin:10px 0;border-radius:8px;border-right:5px solid #28a745;}
.error{background:#f8d7da;color:#721c24;padding:15px;margin:10px 0;border-radius:8px;border-right:5px solid #dc3545;}
.warning{background:#fff3cd;color:#856404;padding:15px;margin:10px 0;border-radius:8px;border-right:5px solid#ffc107;}
.info{background:#d1ecf1;color:#0c5460;padding:15px;margin:10px 0;border-radius:8px;border-right:5px solid #17a2b8;}
table{width:100%;border-collapse:collapse;margin:20px 0;}
th,td{padding:12px;text-align:right;border:1px solid #ddd;}
th{background:#667eea;color:white;font-weight:bold;}
tr:nth-child(even){background:#f8f9fa;}
.btn{display:inline-block;padding:15px 30px;margin:10px 5px;background:#28a745;color:white;text-decoration:none;border-radius:8px;font-size:16px;transition:all 0.3s;}
.btn:hover{background:#218838;transform:translateY(-2px);box-shadow:0 5px 15px rgba(40,167,69,0.4);}
.btn-primary{background:#007bff;} .btn-primary:hover{background:#0056b3;}
.progress{background:#e9ecef;border-radius:10px;height:30px;margin:10px 0;overflow:hidden;}
.progress-bar{background:#28a745;height:100%;line-height:30px;color:white;text-align:center;transition:width 0.3s;}
</style>";
echo "</head><body><div class='container'>";

echo "<h1>🚀 إعداد النظام المالي المتكامل - بلدية تكريت</h1>";

$database = new Database();
$db = $database->getConnection();

$totalSteps = 0;
$completedSteps = 0;
$errors = [];

// قراءة ملف SQL
$sqlFile = 'financial_system_database.sql';
if (!file_exists($sqlFile)) {
    echo "<div class='error'><h3>❌ ملف SQL غير موجود!</h3>";
    echo "<p>لم يتم العثور على الملف: <code>$sqlFile</code></p></div>";
    echo "</div></body></html>";
    exit;
}

$sql = file_get_contents($sqlFile);

// تقسيم الأوامر
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    function($stmt) {
        return !empty($stmt) && 
               !preg_match('/^--/', $stmt) && 
               !preg_match('/^\/\*/', $stmt) &&
               strlen($stmt) > 10;
    }
);

$totalSteps = count($statements);

echo "<div class='info'>";
echo "<h3>📊 معلومات التنفيذ</h3>";
echo "<p><strong>عدد الأوامر المراد تنفيذها:</strong> $totalSteps أمر</p>";
echo "<p><strong>قاعدة البيانات:</strong> tekrit_municipality</p>";
echo "</div>";

echo "<h2>⚙️ تنفيذ الأوامر</h2>";
echo "<div class='progress'><div class='progress-bar' id='progressBar' style='width:0%'>0%</div></div>";

echo "<table>";
echo "<tr><th>#</th><th>الأمر</th><th>الحالة</th><th>التفاصيل</th></tr>";

$stepNumber = 0;
foreach ($statements as $statement) {
    $stepNumber++;
    $completedSteps++;
    
    // استخراج نوع الأمر
    $commandType = 'غير معروف';
    if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $statement, $matches)) {
        $commandType = "إنشاء جدول: {$matches[1]}";
    } elseif (preg_match('/ALTER\s+TABLE\s+`?(\w+)`?/i', $statement, $matches)) {
        $commandType = "تعديل جدول: {$matches[1]}";
    } elseif (preg_match('/INSERT\s+(?:IGNORE\s+)?INTO\s+`?(\w+)`?/i', $statement, $matches)) {
        $commandType = "إدراج بيانات في: {$matches[1]}";
    }
    
    $shortStatement = strlen($statement) > 100 ? substr($statement, 0, 100) . '...' : $statement;
    
    echo "<tr>";
    echo "<td><strong>$stepNumber</strong></td>";
    echo "<td style='max-width:400px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;' title='" . htmlspecialchars($statement) . "'>$commandType</td>";
    
    try {
        $db->exec($statement);
        echo "<td style='color:green;font-weight:bold;'>✅ نجح</td>";
        echo "<td><span style='color:green;'>تم التنفيذ بنجاح</span></td>";
    } catch (PDOException $e) {
        $errorMsg = $e->getMessage();
        
        // تجاهل بعض الأخطاء المتوقعة
        if (strpos($errorMsg, 'Duplicate column name') !== false || 
            strpos($errorMsg, 'already exists') !== false) {
            echo "<td style='color:orange;font-weight:bold;'>⚠️ موجود</td>";
            echo "<td><span style='color:orange;'>العنصر موجود بالفعل</span></td>";
        } else {
            echo "<td style='color:red;font-weight:bold;'>❌ فشل</td>";
            echo "<td style='color:red;font-size:12px;'>" . htmlspecialchars(substr($errorMsg, 0, 100)) . "</td>";
            $errors[] = [
                'step' => $stepNumber,
                'command' => $commandType,
                'error' => $errorMsg
            ];
        }
    }
    
    echo "</tr>";
    
    // تحديث شريط التقدم
    $progress = round(($completedSteps / $totalSteps) * 100);
    echo "<script>document.getElementById('progressBar').style.width='$progress%';document.getElementById('progressBar').textContent='$progress%';</script>";
    flush();
}

echo "</table>";

// الخلاصة
echo "<h2>📋 خلاصة التنفيذ</h2>";

if (empty($errors)) {
    echo "<div class='success'>";
    echo "<h3>🎉 تم إعداد النظام المالي بنجاح!</h3>";
    echo "<p>✅ تم تنفيذ جميع الأوامر ($totalSteps أمر) بنجاح</p>";
    echo "<p>✅ تم إنشاء جميع الجداول المطلوبة</p>";
    echo "<p>✅ تم تعديل الجداول الحالية</p>";
    echo "<p>✅ تم إضافة البيانات الأولية</p>";
    echo "</div>";
} else {
    echo "<div class='warning'>";
    echo "<h3>⚠️ اكتمل التنفيذ مع بعض التحذيرات</h3>";
    echo "<p>عدد الأخطاء: " . count($errors) . "</p>";
    echo "<details>";
    echo "<summary style='cursor:pointer;font-weight:bold;'>عرض التفاصيل</summary>";
    echo "<table>";
    echo "<tr><th>#</th><th>الأمر</th><th>الخطأ</th></tr>";
    foreach ($errors as $error) {
        echo "<tr>";
        echo "<td>{$error['step']}</td>";
        echo "<td>{$error['command']}</td>";
        echo "<td style='font-size:12px;'>" . htmlspecialchars($error['error']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</details>";
    echo "</div>";
}

// التحقق من الجداول
echo "<h2>🔍 التحقق من الجداول المُنشأة</h2>";

$newTables = ['suppliers', 'supplier_invoices', 'invoice_payments', 'budgets', 'budget_items', 'associations', 'fiscal_periods'];

echo "<table>";
echo "<tr><th>الجدول</th><th>الحالة</th><th>عدد الأعمدة</th><th>عدد السجلات</th></tr>";

foreach ($newTables as $table) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $stmt = $db->query("DESCRIBE $table");
            $columnCount = $stmt->rowCount();
            
            $stmt = $db->query("SELECT COUNT(*) as count FROM $table");
            $recordCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo "<tr>";
            echo "<td><strong>$table</strong></td>";
            echo "<td style='color:green;'>✅ موجود</td>";
            echo "<td>$columnCount عمود</td>";
            echo "<td>$recordCount سجل</td>";
            echo "</tr>";
        } else {
            echo "<tr>";
            echo "<td><strong>$table</strong></td>";
            echo "<td style='color:red;'>❌ غير موجود</td>";
            echo "<td colspan='2'>-</td>";
            echo "</tr>";
        }
    } catch (PDOException $e) {
        echo "<tr>";
        echo "<td><strong>$table</strong></td>";
        echo "<td style='color:red;'>❌ خطأ</td>";
        echo "<td colspan='2'>" . htmlspecialchars($e->getMessage()) . "</td>";
        echo "</tr>";
    }
}

echo "</table>";

// الخطوات التالية
echo "<h2>🎯 الخطوات التالية</h2>";
echo "<div class='info'>";
echo "<ol style='line-height:2;'>";
echo "<li>✅ <strong>تم:</strong> إعداد قاعدة البيانات</li>";
echo "<li>⏭️ <strong>التالي:</strong> بناء واجهات إدارة الموردين</li>";
echo "<li>⏭️ بناء واجهات إدارة الفواتير</li>";
echo "<li>⏭️ بناء واجهات إدارة الميزانيات</li>";
echo "<li>⏭️ بناء لوحة التحكم المالية الشاملة</li>";
echo "</ol>";
echo "</div>";

// الروابط
echo "<div style='text-align:center;margin-top:40px;padding:30px;background:#f8f9fa;border-radius:10px;'>";
echo "<h3>🔗 الانتقال إلى</h3>";
echo "<a href='comprehensive_dashboard.php' class='btn btn-primary'>📊 لوحة التحكم الرئيسية</a>";
echo "<a href='modules/finance.php' class='btn'>💰 الإدارة المالية</a>";
echo "<a href='setup_financial_system.php' class='btn' style='background:#6c757d;'>🔄 إعادة التشغيل</a>";
echo "</div>";

echo "</div></body></html>";

