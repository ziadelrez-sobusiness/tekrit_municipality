<?php
// الاتصال بقاعدة البيانات مباشرة
header('Content-Type: text/html; charset=utf-8');

try {
    $host = 'localhost';
    $dbname = 'tekrit_municipality';
    $username = 'root';
    $password = '';
    
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("SET NAMES 'utf8mb4'");
    
    echo "<!DOCTYPE html>";
    echo "<html dir='rtl' lang='ar'>";
    echo "<head><meta charset='UTF-8'><title>إصلاح مباشر</title>";
    echo "<style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #667eea; text-align: center; }
        .step { background: #f8f9fa; padding: 15px; margin: 10px 0; border-right: 4px solid #667eea; }
        .success { background: #d4edda; border-right-color: #28a745; }
        .error { background: #f8d7da; border-right-color: #dc3545; }
        .warning { background: #fff3cd; border-right-color: #ffc107; }
        .info { background: #d1ecf1; border-right-color: #17a2b8; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: right; border: 1px solid #ddd; }
        th { background: #667eea; color: white; }
        .button { display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
        .button:hover { background: #5568d3; }
        pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style></head>";
    echo "<body><div class='container'>";
    
    echo "<h1>🔧 إصلاح مباشر للمشروع رقم 2</h1>";
    
    // 1. فحص الأعمدة الموجودة
    echo "<div class='step'><h3>الخطوة 1: فحص بنية الجدول</h3>";
    $columns = $db->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);
    
    $has_target = in_array('target_amount', $columns);
    $has_collected = in_array('contributions_collected', $columns);
    $has_allow = in_array('allow_public_contributions', $columns);
    
    echo "<p>الأعمدة الموجودة:</p><ul>";
    echo "<li>target_amount: " . ($has_target ? "✅ موجود" : "❌ غير موجود") . "</li>";
    echo "<li>contributions_collected: " . ($has_collected ? "✅ موجود" : "❌ غير موجود") . "</li>";
    echo "<li>allow_public_contributions: " . ($has_allow ? "✅ موجود" : "❌ غير موجود") . "</li>";
    echo "</ul></div>";
    
    // 2. إضافة الأعمدة إذا لم تكن موجودة
    if (!$has_target || !$has_collected || !$has_allow) {
        echo "<div class='step warning'><h3>الخطوة 2: إضافة الأعمدة المفقودة</h3>";
        
        if (!$has_target) {
            $db->exec("ALTER TABLE `projects` ADD COLUMN `target_amount` DECIMAL(15,2) DEFAULT 0.00");
            echo "<p>✅ تم إضافة target_amount</p>";
        }
        
        if (!$has_collected) {
            $db->exec("ALTER TABLE `projects` ADD COLUMN `contributions_collected` DECIMAL(15,2) DEFAULT 0.00");
            echo "<p>✅ تم إضافة contributions_collected</p>";
        }
        
        if (!$has_allow) {
            $db->exec("ALTER TABLE `projects` ADD COLUMN `allow_public_contributions` TINYINT(1) DEFAULT 0");
            echo "<p>✅ تم إضافة allow_public_contributions</p>";
        }
        
        echo "</div>";
    } else {
        echo "<div class='step success'><h3>الخطوة 2: جميع الأعمدة موجودة ✅</h3></div>";
    }
    
    // 3. عرض بيانات المشروع قبل التحديث
    echo "<div class='step info'><h3>الخطوة 3: بيانات المشروع قبل التحديث</h3>";
    $stmt = $db->query("SELECT * FROM projects WHERE id = 2");
    $project_before = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($project_before) {
        echo "<table>";
        echo "<tr><th>الحقل</th><th>القيمة</th></tr>";
        
        $important_fields = ['id', 'project_name', 'target_amount', 'contributions_collected', 'currency_id', 'allow_public_contributions'];
        foreach ($important_fields as $field) {
            if (isset($project_before[$field])) {
                $value = $project_before[$field] ?? 'NULL';
                echo "<tr><td><strong>$field</strong></td><td>$value</td></tr>";
            }
        }
        echo "</table>";
    } else {
        echo "<p class='error'>❌ المشروع رقم 2 غير موجود!</p>";
    }
    echo "</div>";
    
    // 4. تحديث المشروع
    echo "<div class='step'><h3>الخطوة 4: تحديث البيانات</h3>";
    
    // الحصول على ID العملة USD
    $stmt = $db->query("SELECT id FROM currencies WHERE currency_code = 'USD' LIMIT 1");
    $usd_currency = $stmt->fetch(PDO::FETCH_ASSOC);
    $currency_id = $usd_currency ? $usd_currency['id'] : 2; // افتراضي 2 إذا لم يوجد
    
    $stmt = $db->prepare("UPDATE projects 
                          SET target_amount = ?,
                              contributions_collected = ?,
                              currency_id = ?,
                              allow_public_contributions = ?
                          WHERE id = ?");
    
    $result = $stmt->execute([2300, 0, $currency_id, 1, 2]);
    
    if ($result) {
        echo "<p class='success'>✅ تم تحديث المشروع بنجاح!</p>";
        echo "<ul>";
        echo "<li>target_amount: <strong>2300</strong></li>";
        echo "<li>contributions_collected: <strong>0</strong></li>";
        echo "<li>currency_id: <strong>$currency_id (USD)</strong></li>";
        echo "<li>allow_public_contributions: <strong>1 (مفعّل)</strong></li>";
        echo "</ul>";
    } else {
        echo "<p class='error'>❌ فشل التحديث!</p>";
    }
    echo "</div>";
    
    // 5. عرض بيانات المشروع بعد التحديث
    echo "<div class='step success'><h3>الخطوة 5: بيانات المشروع بعد التحديث</h3>";
    $stmt = $db->query("
        SELECT 
            p.id,
            p.project_name,
            p.target_amount,
            p.contributions_collected,
            (p.target_amount - p.contributions_collected) as remaining,
            p.allow_public_contributions,
            c.currency_code,
            c.currency_symbol,
            CASE 
                WHEN p.allow_public_contributions = 1 THEN '✅ مفعّل'
                ELSE '❌ معطّل'
            END as status
        FROM projects p
        LEFT JOIN currencies c ON p.currency_id = c.id
        WHERE p.id = 2
    ");
    $project_after = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($project_after) {
        echo "<table>";
        foreach ($project_after as $key => $value) {
            echo "<tr><td><strong>$key</strong></td><td>$value</td></tr>";
        }
        echo "</table>";
        
        // عرض ملخص جميل
        echo "<div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-top: 20px; text-align: center;'>";
        echo "<h2>🎉 النتيجة النهائية</h2>";
        echo "<div style='display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 15px;'>";
        echo "<div style='background: rgba(255,255,255,0.2); padding: 15px; border-radius: 5px;'>";
        echo "<h3>الهدف</h3>";
        echo "<h1>" . number_format($project_after['target_amount'], 0) . " " . $project_after['currency_symbol'] . "</h1>";
        echo "</div>";
        echo "<div style='background: rgba(255,255,255,0.2); padding: 15px; border-radius: 5px;'>";
        echo "<h3>المُجمّع</h3>";
        echo "<h1>" . number_format($project_after['contributions_collected'], 0) . " " . $project_after['currency_symbol'] . "</h1>";
        echo "</div>";
        echo "<div style='background: rgba(255,255,255,0.2); padding: 15px; border-radius: 5px;'>";
        echo "<h3>المتبقي</h3>";
        echo "<h1>" . number_format($project_after['remaining'], 0) . " " . $project_after['currency_symbol'] . "</h1>";
        echo "</div>";
        echo "<div style='background: rgba(255,255,255,0.2); padding: 15px; border-radius: 5px;'>";
        echo "<h3>الحالة</h3>";
        echo "<h1>" . $project_after['status'] . "</h1>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
    }
    echo "</div>";
    
    // 6. روابط سريعة
    echo "<div class='step' style='text-align: center;'>";
    echo "<h3>🔗 الخطوة التالية</h3>";
    echo "<p>الآن جرّب صفحة المساهمات:</p>";
    echo "<a href='modules/contributions.php?project_id=2' class='button' target='_blank'>🚀 فتح صفحة المساهمات</a>";
    echo "<a href='modules/projects_unified.php' class='button' target='_blank'>📊 صفحة المشاريع</a>";
    echo "</div>";
    
    echo "</div></body></html>";
    
} catch (PDOException $e) {
    echo "<div class='step error'>";
    echo "<h3>❌ خطأ في قاعدة البيانات</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>

