<?php
/**
 * سكريبت لتحديث بنود الميزانيات بناءً على الدفعات القديمة
 * هذا السكريبت يعالج الفواتير التي تم دفعها قبل تفعيل التحديث التلقائي
 */

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES 'utf8mb4'");

echo "<!DOCTYPE html>
<html lang='ar' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <title>تحديث بيانات الدفعات القديمة</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2563eb; border-bottom: 3px solid #2563eb; padding-bottom: 10px; }
        .success { background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .info { background: #dbeafe; border-left: 4px solid #3b82f6; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .warning { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 10px 0; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: right; border: 1px solid #ddd; }
        th { background: #f3f4f6; font-weight: bold; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin: 20px 0; }
        .stat-box { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; }
        .stat-number { font-size: 2em; font-weight: bold; }
        .stat-label { font-size: 0.9em; opacity: 0.9; }
        .btn { display: inline-block; padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        .btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔧 تحديث بيانات الدفعات القديمة</h1>";

try {
    $db->beginTransaction();
    
    $updated_budgets = 0;
    $updated_projects = 0;
    $total_payments = 0;
    $errors = [];
    
    echo "<div class='info'>📊 <strong>جاري فحص جميع الدفعات...</strong></div>";
    
    // جلب جميع الدفعات مع بيانات الفواتير
    $stmt = $db->query("
        SELECT 
            ip.id as payment_id,
            ip.payment_amount,
            ip.payment_date,
            si.id as invoice_id,
            si.invoice_number,
            si.budget_item_id,
            si.related_project_id,
            bi.name as budget_item_name,
            bi.spent_amount as current_spent,
            bi.allocated_amount,
            b.name as budget_name,
            b.budget_code
        FROM invoice_payments ip
        INNER JOIN supplier_invoices si ON ip.invoice_id = si.id
        LEFT JOIN budget_items bi ON si.budget_item_id = bi.id
        LEFT JOIN budgets b ON bi.budget_id = b.id
        ORDER BY ip.payment_date
    ");
    
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_payments = count($payments);
    
    echo "<div class='info'>✅ تم العثور على <strong>$total_payments</strong> دفعة</div>";
    
    if ($total_payments == 0) {
        echo "<div class='warning'>⚠️ لا توجد دفعات لمعالجتها</div>";
        $db->rollBack();
        echo "<a href='modules/budgets.php' class='btn'>← العودة للميزانيات</a>";
        echo "</div></body></html>";
        exit();
    }
    
    // أولاً: إعادة تعيين جميع المصروفات إلى الصفر
    echo "<div class='warning'>🔄 <strong>إعادة تعيين المصروفات...</strong></div>";
    
    $stmt = $db->query("UPDATE budget_items SET spent_amount = 0, remaining_amount = allocated_amount");
    echo "<div class='success'>✅ تم إعادة تعيين جميع بنود الميزانيات</div>";
    
    $stmt = $db->query("UPDATE projects SET spent_amount = 0");
    echo "<div class='success'>✅ تم إعادة تعيين جميع المشاريع</div>";
    
    // الآن: إعادة حساب المصروفات من جميع الدفعات
    echo "<h2>💰 إعادة حساب المصروفات</h2>";
    echo "<table>";
    echo "<tr>
            <th>التاريخ</th>
            <th>الفاتورة</th>
            <th>المبلغ</th>
            <th>البند</th>
            <th>الحالة</th>
          </tr>";
    
    $budget_totals = [];
    $project_totals = [];
    
    foreach ($payments as $payment) {
        $payment_amount = floatval($payment['payment_amount']);
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($payment['payment_date']) . "</td>";
        echo "<td>" . htmlspecialchars($payment['invoice_number']) . "</td>";
        echo "<td>" . number_format($payment_amount, 2) . " $</td>";
        
        // تحديث بند الميزانية
        if (!empty($payment['budget_item_id'])) {
            if (!isset($budget_totals[$payment['budget_item_id']])) {
                $budget_totals[$payment['budget_item_id']] = 0;
            }
            $budget_totals[$payment['budget_item_id']] += $payment_amount;
            
            echo "<td>" . htmlspecialchars($payment['budget_item_name']) . "</td>";
            echo "<td style='color: green;'>✅ محدّث</td>";
            $updated_budgets++;
        } else {
            echo "<td style='color: gray;'>-</td>";
            echo "<td style='color: gray;'>⊘ بدون بند</td>";
        }
        
        // تحديث المشروع
        if (!empty($payment['related_project_id'])) {
            if (!isset($project_totals[$payment['related_project_id']])) {
                $project_totals[$payment['related_project_id']] = 0;
            }
            $project_totals[$payment['related_project_id']] += $payment_amount;
            $updated_projects++;
        }
        
        echo "</tr>";
    }
    
    echo "</table>";
    
    // تطبيق التحديثات على بنود الميزانية
    echo "<h2>📊 تطبيق التحديثات على بنود الميزانية</h2>";
    foreach ($budget_totals as $budget_item_id => $total_amount) {
        $stmt = $db->prepare("
            UPDATE budget_items 
            SET spent_amount = ?,
                remaining_amount = allocated_amount - ?
            WHERE id = ?
        ");
        $stmt->execute([$total_amount, $total_amount, $budget_item_id]);
        
        // جلب معلومات البند
        $stmt = $db->prepare("
            SELECT bi.name, bi.allocated_amount, bi.spent_amount, bi.remaining_amount,
                   b.budget_code, b.name as budget_name
            FROM budget_items bi
            LEFT JOIN budgets b ON bi.budget_id = b.id
            WHERE bi.id = ?
        ");
        $stmt->execute([$budget_item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<div class='success'>";
        echo "✅ <strong>" . htmlspecialchars($item['budget_code']) . " - " . htmlspecialchars($item['name']) . "</strong><br>";
        echo "💰 المخصص: " . number_format($item['allocated_amount'], 2) . " $<br>";
        echo "💸 المصروف: " . number_format($item['spent_amount'], 2) . " $<br>";
        echo "✅ المتبقي: " . number_format($item['remaining_amount'], 2) . " $";
        echo "</div>";
    }
    
    // تطبيق التحديثات على المشاريع
    if (!empty($project_totals)) {
        echo "<h2>🏗️ تطبيق التحديثات على المشاريع</h2>";
        foreach ($project_totals as $project_id => $total_amount) {
            $stmt = $db->prepare("UPDATE projects SET spent_amount = ? WHERE id = ?");
            $stmt->execute([$total_amount, $project_id]);
            
            echo "<div class='success'>✅ تم تحديث المشروع #$project_id - المصروف: " . number_format($total_amount, 2) . " $</div>";
        }
    }
    
    $db->commit();
    
    // الإحصائيات النهائية
    echo "<h2>📈 النتائج النهائية</h2>";
    echo "<div class='stats'>";
    echo "<div class='stat-box'>";
    echo "<div class='stat-number'>$total_payments</div>";
    echo "<div class='stat-label'>إجمالي الدفعات</div>";
    echo "</div>";
    echo "<div class='stat-box'>";
    echo "<div class='stat-number'>" . count($budget_totals) . "</div>";
    echo "<div class='stat-label'>البنود المحدثة</div>";
    echo "</div>";
    echo "<div class='stat-box'>";
    echo "<div class='stat-number'>" . count($project_totals) . "</div>";
    echo "<div class='stat-label'>المشاريع المحدثة</div>";
    echo "</div>";
    echo "</div>";
    
    echo "<div class='success' style='font-size: 1.2em; text-align: center; padding: 20px;'>";
    echo "🎉 <strong>تم تحديث جميع البيانات بنجاح!</strong>";
    echo "</div>";
    
    echo "<div style='text-align: center;'>";
    echo "<a href='modules/budgets.php' class='btn'>✅ العودة للميزانيات</a>";
    echo "</div>";
    
} catch (Exception $e) {
    $db->rollBack();
    echo "<div class='error'>";
    echo "<strong>❌ خطأ:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>


