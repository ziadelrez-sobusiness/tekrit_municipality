<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// التأكد من تسجيل الدخول
$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();

// تعيين ترميز UTF-8 في البداية
$db->exec("SET NAMES 'utf8mb4'");
$db->exec("SET CHARACTER SET utf8mb4");
header('Content-Type: text/html; charset=utf-8');

$user = $auth->getUserInfo();

$message = '';
$error = '';

// معالجة حذف معاملة مالية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_transaction'])) {
    try {
        $transaction_id = intval($_POST['transaction_id']);
        
        // جلب تفاصيل المعاملة قبل الحذف
        $stmt = $db->prepare("SELECT type, amount, budget_item_id, supplier_invoice_id, related_project_id FROM financial_transactions WHERE id = ?");
        $stmt->execute([$transaction_id]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($transaction) {
            // التراجع عن التحديثات في بنود الميزانية
            if ($transaction['budget_item_id'] && $transaction['type'] === 'مصروف') {
                $stmt = $db->prepare("UPDATE budget_items 
                                     SET spent_amount = spent_amount - ?, 
                                         remaining_amount = remaining_amount + ? 
                                     WHERE id = ?");
                $stmt->execute([$transaction['amount'], $transaction['amount'], $transaction['budget_item_id']]);
            }
            
            // التراجع عن التحديثات في فواتير الموردين
            if ($transaction['supplier_invoice_id']) {
                $stmt = $db->prepare("UPDATE supplier_invoices 
                                     SET paid_amount = paid_amount - ? 
                                     WHERE id = ?");
                $stmt->execute([$transaction['amount'], $transaction['supplier_invoice_id']]);
                
                // تحديث حالة الفاتورة
                $stmt = $db->prepare("SELECT total_amount, paid_amount FROM supplier_invoices WHERE id = ?");
                $stmt->execute([$transaction['supplier_invoice_id']]);
                $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $new_status = 'غير مدفوع';
                if ($invoice['paid_amount'] >= $invoice['total_amount']) {
                    $new_status = 'مدفوع بالكامل';
                } elseif ($invoice['paid_amount'] > 0) {
                    $new_status = 'مدفوع جزئياً';
                }
                
                $stmt = $db->prepare("UPDATE supplier_invoices SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $transaction['supplier_invoice_id']]);
            }
            
            // التراجع عن التحديثات في المشاريع
            if ($transaction['related_project_id'] && $transaction['type'] === 'مصروف') {
                $stmt = $db->prepare("UPDATE projects SET spent_amount = spent_amount - ? WHERE id = ?");
                $stmt->execute([$transaction['amount'], $transaction['related_project_id']]);
            }
            
            // حذف المعاملة
            $stmt = $db->prepare("DELETE FROM financial_transactions WHERE id = ?");
            $stmt->execute([$transaction_id]);
            
            $message = 'تم حذف المعاملة المالية بنجاح وتحديث البنود المرتبطة!';
        } else {
            $error = 'المعاملة غير موجودة';
        }
    } catch (PDOException $e) {
        $error = 'خطأ في حذف المعاملة: ' . $e->getMessage();
    }
}

// معالجة تعديل معاملة مالية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_transaction'])) {
    try {
        $transaction_id = intval($_POST['transaction_id']);
        
        // جلب المعاملة القديمة
        $stmt = $db->prepare("SELECT * FROM financial_transactions WHERE id = ?");
        $stmt->execute([$transaction_id]);
        $old_transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$old_transaction) {
            throw new Exception('المعاملة غير موجودة');
        }
        
        // البيانات الجديدة
        $transaction_type = $_POST['transaction_type'];
        $category = $_POST['category'];
        $description = $_POST['description'];
        $amount = floatval($_POST['amount']);
        $currency_id = intval($_POST['currency_id']);
        $transaction_date = $_POST['transaction_date'];
        $payment_method = $_POST['payment_method'];
        $reference_number = $_POST['reference_number'];
        $bank_name = $_POST['bank_name'] ?? '';
        $check_number = $_POST['check_number'] ?? '';
        
        $budget_item_id = !empty($_POST['budget_item_id']) ? intval($_POST['budget_item_id']) : null;
        
        // التراجع عن التحديثات القديمة في بنود الميزانية
        if ($old_transaction['budget_item_id'] && $old_transaction['type'] === 'مصروف') {
            $stmt = $db->prepare("UPDATE budget_items 
                                 SET spent_amount = spent_amount - ?, 
                                     remaining_amount = remaining_amount + ? 
                                 WHERE id = ?");
            $stmt->execute([$old_transaction['amount'], $old_transaction['amount'], $old_transaction['budget_item_id']]);
        }
        
        // تحديث المعاملة
        $stmt = $db->prepare("SELECT exchange_rate_to_iqd FROM currencies WHERE id = ?");
        $stmt->execute([$currency_id]);
        $exchange_rate = $stmt->fetchColumn() ?: 1.0;
        
        $stmt = $db->prepare("UPDATE financial_transactions 
                             SET type = ?, category = ?, description = ?, amount = ?, 
                                 currency_id = ?, exchange_rate = ?, transaction_date = ?,
                                 payment_method = ?, reference_number = ?, bank_name = ?, 
                                 check_number = ?, budget_item_id = ?
                             WHERE id = ?");
        $stmt->execute([$transaction_type, $category, $description, $amount, $currency_id, 
                       $exchange_rate, $transaction_date, $payment_method, $reference_number, 
                       $bank_name, $check_number, $budget_item_id, $transaction_id]);
        
        // تطبيق التحديثات الجديدة في بنود الميزانية
        if ($budget_item_id && $transaction_type === 'مصروف') {
            $stmt = $db->prepare("UPDATE budget_items 
                                 SET spent_amount = spent_amount + ?, 
                                     remaining_amount = remaining_amount - ? 
                                 WHERE id = ?");
            $stmt->execute([$amount, $amount, $budget_item_id]);
        }
        
        $message = '✅ تم تحديث المعاملة المالية بنجاح!<br>📊 تم تحديث بنود الميزانية تلقائياً';
        
        // إعادة توجيه لتجنب إعادة الإرسال
        header("Location: finance.php");
        exit();
        
    } catch (Exception $e) {
        $error = 'خطأ في تحديث المعاملة: ' . $e->getMessage();
    }
}

// معالجة إضافة معاملة مالية جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_transaction'])) {
    $transaction_type = $_POST['transaction_type'];
    $category = $_POST['category'];
    $description = $_POST['description'];
    $amount = floatval($_POST['amount']);
    $currency_id = intval($_POST['currency_id']);
    $transaction_date = $_POST['transaction_date'];
    $payment_method = $_POST['payment_method'];
    $reference_number = $_POST['reference_number'];
    $bank_name = $_POST['bank_name'] ?? '';
    $check_number = $_POST['check_number'] ?? '';
    
    // الربط مع البنود الأخرى
    $budget_item_id = !empty($_POST['budget_item_id']) ? intval($_POST['budget_item_id']) : null;
    $supplier_invoice_id = !empty($_POST['supplier_invoice_id']) ? intval($_POST['supplier_invoice_id']) : null;
    $tax_collection_id = !empty($_POST['tax_collection_id']) ? intval($_POST['tax_collection_id']) : null;
    $related_project_id = !empty($_POST['related_project_id']) ? intval($_POST['related_project_id']) : null;
    $association_id = !empty($_POST['association_id']) ? intval($_POST['association_id']) : null;
    
    // جلب سعر الصرف للعملة المحددة
    $stmt = $db->prepare("SELECT exchange_rate_to_iqd FROM currencies WHERE id = ?");
    $stmt->execute([$currency_id]);
    $exchange_rate = $stmt->fetchColumn();
    if (!$exchange_rate) $exchange_rate = 1.0;
    
    if (!empty($category) && !empty($description) && $amount > 0) {
        try {
            $query = "INSERT INTO financial_transactions 
                     (type, category, description, amount, currency_id, exchange_rate, transaction_date, 
                      payment_method, reference_number, bank_name, check_number, created_by, status,
                      budget_item_id, supplier_invoice_id, tax_collection_id, related_project_id, association_id) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'معتمد', ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$transaction_type, $category, $description, $amount, $currency_id, $exchange_rate, 
                          $transaction_date, $payment_method, $reference_number, $bank_name, $check_number, 
                          $user['id'], $budget_item_id, $supplier_invoice_id, $tax_collection_id, 
                          $related_project_id, $association_id]);
            
            // تحديث البنود المرتبطة
            $budget_updated = false;
            if ($budget_item_id && $transaction_type === 'مصروف') {
                $stmt = $db->prepare("UPDATE budget_items 
                                     SET spent_amount = spent_amount + ?, 
                                         remaining_amount = remaining_amount - ? 
                                     WHERE id = ?");
                $stmt->execute([$amount, $amount, $budget_item_id]);
                $budget_updated = true;
            }
            
            if ($supplier_invoice_id) {
                $stmt = $db->prepare("UPDATE supplier_invoices 
                                     SET paid_amount = paid_amount + ? 
                                     WHERE id = ?");
                $stmt->execute([$amount, $supplier_invoice_id]);
                
                // تحديث حالة الفاتورة
                $stmt = $db->prepare("SELECT total_amount, paid_amount FROM supplier_invoices WHERE id = ?");
                $stmt->execute([$supplier_invoice_id]);
                $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $new_status = 'غير مدفوع';
                if ($invoice['paid_amount'] >= $invoice['total_amount']) {
                    $new_status = 'مدفوع بالكامل';
                } elseif ($invoice['paid_amount'] > 0) {
                    $new_status = 'مدفوع جزئياً';
                }
                
                $stmt = $db->prepare("UPDATE supplier_invoices SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $supplier_invoice_id]);
            }
            
            if ($related_project_id && $transaction_type === 'مصروف') {
                $stmt = $db->prepare("UPDATE projects SET spent_amount = spent_amount + ? WHERE id = ?");
                $stmt->execute([$amount, $related_project_id]);
            }
            
            if ($budget_updated) {
                $message = '✅ تم إضافة المعاملة المالية بنجاح!<br>📊 تم تحديث بند الميزانية تلقائياً (المصروف والمتبقي)';
            } else {
                $message = 'تم إضافة المعاملة المالية بنجاح!';
            }
        } catch (PDOException $e) {
            $error = 'خطأ في إضافة المعاملة: ' . $e->getMessage();
        }
    } else {
        $error = 'يرجى تعبئة جميع الحقول المطلوبة';
    }
}

// جلب العملات أولاً (قبل أي شيء)
$currencies = [];
try {
    $stmt = $db->query("SELECT id, currency_code, currency_name, currency_symbol FROM currencies WHERE is_active = 1 ORDER BY currency_code");
    $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'خطأ في جلب العملات: ' . $e->getMessage();
}

// جلب بنود الميزانية المعتمدة مع معلومات اللجنة
$budget_items = [];
try {
    $stmt = $db->query("
        SELECT bi.id, bi.name, bi.item_code, bi.item_type,
               b.name as budget_name, b.fiscal_year,
               mc.committee_name,
               bi.allocated_amount, bi.spent_amount, bi.remaining_amount,
               c.currency_symbol
        FROM budget_items bi
        LEFT JOIN budgets b ON bi.budget_id = b.id
        LEFT JOIN municipal_committees mc ON b.committee_id = mc.id
        LEFT JOIN currencies c ON bi.currency_id = c.id
        WHERE b.status IN ('معتمد', 'مسودة') AND bi.remaining_amount > 0
        ORDER BY mc.committee_name, b.fiscal_year DESC, bi.item_code
    ");
    $budget_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // الجدول قد لا يكون موجوداً بعد
}

// جلب فواتير الموردين غير المدفوعة بالكامل
$supplier_invoices = [];
try {
    $stmt = $db->query("
        SELECT si.id, si.invoice_number, s.name as supplier_name,
               si.total_amount, si.paid_amount, si.status,
               c.currency_symbol
        FROM supplier_invoices si
        LEFT JOIN suppliers s ON si.supplier_id = s.id
        LEFT JOIN currencies c ON si.currency_id = c.id
        WHERE si.status != 'مدفوع بالكامل'
        ORDER BY si.invoice_date DESC
    ");
    $supplier_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // الجدول قد لا يكون موجوداً بعد
}

// جلب المشاريع النشطة
$projects = [];
try {
    $stmt = $db->query("
        SELECT *
        FROM projects
        WHERE status IN ('قيد التخطيط', 'قيد التنفيذ')
        ORDER BY start_date DESC
    ");
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إضافة حقل name لكل مشروع (للتوافق)
    foreach ($projects as &$project) {
        if (!isset($project['name'])) {
            $project['name'] = $project['project_name'] ?? $project['title'] ?? $project['project_title'] ?? 'مشروع #' . $project['id'];
        }
    }
    unset($project);
} catch (PDOException $e) {
    // خطأ في جلب المشاريع
}

// جلب الجمعيات
$associations = [];
try {
    $stmt = $db->query("SELECT id, name FROM associations ORDER BY name");
    $associations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // الجدول قد لا يكون موجوداً بعد
}

// جلب معاملة للتعديل
$edit_transaction_id = $_GET['edit_transaction'] ?? null;
$edit_transaction = null;

if ($edit_transaction_id) {
    $stmt = $db->prepare("SELECT * FROM financial_transactions WHERE id = ?");
    $stmt->execute([$edit_transaction_id]);
    $edit_transaction = $stmt->fetch(PDO::FETCH_ASSOC);
}

// جلب المعاملات المالية
$transactions = [];
$monthly_stats = [];
$chart_data = [];

try {
    $stmt = $db->query("
        SELECT ft.*, 
               u.full_name as created_by_name,
               c.currency_symbol, c.currency_code, c.currency_name
        FROM financial_transactions ft 
        LEFT JOIN users u ON ft.created_by = u.id 
        LEFT JOIN currencies c ON ft.currency_id = c.id
        ORDER BY ft.transaction_date DESC, ft.created_at DESC 
        LIMIT 50
    ");
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات مالية حسب العملة
    $stmt = $db->query("
        SELECT 
            ft.type,
            c.currency_symbol,
            c.currency_code,
            SUM(ft.amount) as total_amount
        FROM financial_transactions ft
        LEFT JOIN currencies c ON ft.currency_id = c.id
        WHERE MONTH(ft.transaction_date) = MONTH(CURDATE()) 
        AND YEAR(ft.transaction_date) = YEAR(CURDATE())
        GROUP BY ft.type, c.currency_symbol, c.currency_code
        ORDER BY c.currency_code, ft.type
    ");
    $monthly_stats_detailed = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // الإيرادات والمصروفات لآخر 6 أشهر حسب العملة
    $stmt = $db->query("
        SELECT 
            DATE_FORMAT(ft.transaction_date, '%Y-%m') as month,
            ft.type,
            c.currency_symbol,
            c.currency_code,
            SUM(ft.amount) as total_amount
        FROM financial_transactions ft
        LEFT JOIN currencies c ON ft.currency_id = c.id
        WHERE ft.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(ft.transaction_date, '%Y-%m'), ft.type, c.currency_symbol, c.currency_code
        ORDER BY month, c.currency_code
    ");
    $chart_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error .= ' | خطأ في جلب البيانات: ' . $e->getMessage();
}

// تنظيم الإحصائيات الشهرية حسب العملة
$monthly_stats_by_currency = [];
foreach ($monthly_stats_detailed as $stat) {
    $currency = $stat['currency_symbol'] ?? 'غير محدد';
    $type = $stat['type'];
    
    if (!isset($monthly_stats_by_currency[$currency])) {
        $monthly_stats_by_currency[$currency] = [
            'إيراد' => 0,
            'مصروف' => 0,
            'currency_code' => $stat['currency_code'] ?? ''
        ];
    }
    
    $monthly_stats_by_currency[$currency][$type] = $stat['total_amount'];
}

// تنظيم بيانات الرسم البياني حسب العملة
$chart_data_by_currency = [];
foreach ($chart_data as $data) {
    $currency = $data['currency_symbol'] ?? 'غير محدد';
    
    if (!isset($chart_data_by_currency[$currency])) {
        $chart_data_by_currency[$currency] = [
            'months' => [],
            'revenues' => [],
            'expenses' => [],
            'currency_code' => $data['currency_code'] ?? ''
        ];
    }
    
    $month = $data['month'];
    if (!in_array($month, $chart_data_by_currency[$currency]['months'])) {
        $chart_data_by_currency[$currency]['months'][] = $month;
    }
}

// ملء البيانات لكل شهر
foreach ($chart_data_by_currency as $currency => &$currency_data) {
    foreach ($currency_data['months'] as $month) {
        $revenue = 0;
        $expense = 0;
        
        foreach ($chart_data as $data) {
            if (($data['currency_symbol'] ?? 'غير محدد') === $currency && $data['month'] === $month) {
                if ($data['type'] === 'إيراد') {
                    $revenue = $data['total_amount'];
                } else {
                    $expense = $data['total_amount'];
                }
            }
        }
        
        $currency_data['revenues'][] = $revenue;
        $currency_data['expenses'][] = $expense;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإدارة المالية - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .chart-container { position: relative; width: 100%; height: 300px; }
    </style>
</head>
<body class="bg-slate-100">
    <div class="min-h-screen p-6">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-slate-800">الإدارة المالية</h1>
                <a href="../comprehensive_dashboard.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                    ← العودة للوحة التحكم
                </a>
            </div>
            <p class="text-slate-600 mt-2">إدارة الإيرادات والمصروفات وتتبع الوضع المالي للبلدية</p>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Financial Stats by Currency -->
        <?php foreach ($monthly_stats_by_currency as $currency_symbol => $stats): ?>
        <div class="mb-6">
            <h3 class="text-lg font-semibold mb-3 text-slate-700">
                💱 الإحصائيات بالعملة: <?= htmlspecialchars($currency_symbol) ?> (<?= htmlspecialchars($stats['currency_code']) ?>)
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">إيرادات الشهر</p>
                            <p class="text-2xl font-bold text-green-600">
                                <?= number_format($stats['إيراد'], 2) ?> <?= htmlspecialchars($currency_symbol) ?>
                            </p>
                        </div>
                        <div class="bg-green-100 text-green-600 p-3 rounded-full">📈</div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">مصروفات الشهر</p>
                            <p class="text-2xl font-bold text-red-600">
                                <?= number_format($stats['مصروف'], 2) ?> <?= htmlspecialchars($currency_symbol) ?>
                            </p>
                        </div>
                        <div class="bg-red-100 text-red-600 p-3 rounded-full">📉</div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">الرصيد الصافي</p>
                            <?php $net_balance = $stats['إيراد'] - $stats['مصروف']; ?>
                            <p class="text-2xl font-bold <?= $net_balance >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                <?= number_format($net_balance, 2) ?> <?= htmlspecialchars($currency_symbol) ?>
                            </p>
                        </div>
                        <div class="bg-blue-100 text-blue-600 p-3 rounded-full">💰</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($monthly_stats_by_currency)): ?>
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded mb-6">
            ℹ️ لا توجد معاملات مالية لهذا الشهر
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Add Transaction Form -->
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h2 class="text-xl font-semibold mb-4">إضافة معاملة مالية جديدة</h2>
                
                <form method="POST" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">نوع المعاملة</label>
                            <select name="transaction_type" required class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">اختر النوع</option>
                                <option value="إيراد">إيراد</option>
                                <option value="مصروف">مصروف</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الفئة</label>
                            <input type="text" name="category" required 
                                   placeholder="مثال: ضرائب، رواتب، صيانة"
                                   class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الوصف</label>
                        <textarea name="description" required rows="3" 
                                  placeholder="وصف تفصيلي للمعاملة المالية"
                                  class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">المبلغ</label>
                            <input type="number" step="0.01" name="amount" required 
                                   class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">العملة</label>
                            <select name="currency_id" required class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">اختر العملة</option>
                                <?php if (!empty($currencies)): ?>
                                    <?php foreach ($currencies as $currency): ?>
                                        <option value="<?= $currency['id'] ?>">
                                            <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>لا توجد عملات متاحة</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">التاريخ</label>
                            <input type="date" name="transaction_date" required value="<?= date('Y-m-d') ?>"
                                   class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">طريقة الدفع</label>
                            <select name="payment_method" id="payment_method" onchange="togglePaymentFields()" class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">اختر الطريقة</option>
                                <option value="نقد">نقد</option>
                                <option value="شيك">شيك</option>
                                <option value="تحويل مصرفي">تحويل مصرفي</option>
                                <option value="بطاقة ائتمان">بطاقة ائتمان</option>
                                <option value="أخرى">أخرى</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">رقم المرجع</label>
                            <input type="text" name="reference_number" 
                                   placeholder="رقم الفاتورة أو المرجع"
                                   class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    
                    <!-- الربط مع البنود الأخرى -->
                    <div class="border-t pt-4 mt-4">
                        <h3 class="font-semibold text-sm mb-3 text-gray-700">🔗 الربط مع البنود (اختياري)</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php if (!empty($budget_items)): ?>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    📊 بند الميزانية 
                                    <span class="text-xs text-gray-500">(سيتم تحديث المصروف تلقائياً)</span>
                                </label>
                                <select name="budget_item_id" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                                    <option value="">-- بدون ربط بالميزانية --</option>
                                    <?php 
                                    $current_committee = '';
                                    foreach ($budget_items as $item): 
                                        if ($current_committee != $item['committee_name']) {
                                            if ($current_committee != '') echo '</optgroup>';
                                            $current_committee = $item['committee_name'];
                                            echo '<optgroup label="🏛️ ' . htmlspecialchars($current_committee ?: 'بدون لجنة') . '">';
                                        }
                                    ?>
                                        <option value="<?= $item['id'] ?>">
                                            <?= htmlspecialchars($item['item_code']) ?> - <?= htmlspecialchars($item['name']) ?> 
                                            (<?= $item['item_type'] ?>) 
                                            | متبقي: <?= number_format($item['remaining_amount'], 0) ?> <?= $item['currency_symbol'] ?>
                                        </option>
                                    <?php 
                                    endforeach; 
                                    if ($current_committee != '') echo '</optgroup>';
                                    ?>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">
                                    💡 اختر بند من الميزانية لتسجيل المصروف عليه وتحديث المبلغ المتبقي تلقائياً
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($supplier_invoices)): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">فاتورة مورد</label>
                                <select name="supplier_invoice_id" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                                    <option value="">-- بدون فاتورة --</option>
                                    <?php foreach ($supplier_invoices as $invoice): ?>
                                        <option value="<?= $invoice['id'] ?>">
                                            <?= htmlspecialchars($invoice['invoice_number']) ?> - <?= htmlspecialchars($invoice['supplier_name']) ?>
                                            (مدفوع: <?= number_format($invoice['paid_amount'], 0) ?>/<?= number_format($invoice['total_amount'], 0) ?> <?= htmlspecialchars($invoice['currency_symbol']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($projects)): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">مشروع</label>
                                <select name="related_project_id" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                                    <option value="">-- بدون مشروع --</option>
                                    <?php foreach ($projects as $project): ?>
                                        <option value="<?= $project['id'] ?>">
                                            <?= htmlspecialchars($project['name']) ?> (<?= htmlspecialchars($project['status']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($associations)): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">جمعية</label>
                                <select name="association_id" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                                    <option value="">-- بدون جمعية --</option>
                                    <?php foreach ($associations as $assoc): ?>
                                        <option value="<?= $assoc['id'] ?>">
                                            <?= htmlspecialchars($assoc['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- حقول إضافية للدفع -->
                    <div id="bank_fields" class="grid grid-cols-1 md:grid-cols-2 gap-4" style="display: none;">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">اسم البنك</label>
                            <input type="text" name="bank_name" 
                                   placeholder="اسم البنك المصدر"
                                   class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">رقم الشيك</label>
                            <input type="text" name="check_number" 
                                   placeholder="رقم الشيك"
                                   class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    
                    <button type="submit" name="add_transaction" 
                            class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 transition duration-200">
                        إضافة المعاملة المالية
                    </button>
                </form>
            </div>

            <!-- Financial Charts by Currency -->
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h2 class="text-xl font-semibold mb-4">الإيرادات والمصروفات الشهرية حسب العملة</h2>
                <?php foreach ($chart_data_by_currency as $currency_symbol => $currency_chart): ?>
                    <div class="mb-6">
                        <h3 class="text-md font-semibold mb-2 text-slate-600">
                            💱 <?= htmlspecialchars($currency_symbol) ?> (<?= htmlspecialchars($currency_chart['currency_code']) ?>)
                        </h3>
                        <div class="chart-container">
                            <canvas id="financeChart_<?= htmlspecialchars($currency_chart['currency_code']) ?>"></canvas>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($chart_data_by_currency)): ?>
                    <p class="text-gray-500 text-center py-8">لا توجد بيانات لعرضها</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="bg-white rounded-lg shadow-sm mt-8">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold">المعاملات المالية الأخيرة</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th class="px-6 py-3">التاريخ</th>
                            <th class="px-6 py-3">النوع</th>
                            <th class="px-6 py-3">الفئة</th>
                            <th class="px-6 py-3">الوصف</th>
                            <th class="px-6 py-3">المبلغ</th>
                            <th class="px-6 py-3">العملة</th>
                            <th class="px-6 py-3">طريقة الدفع</th>
                            <th class="px-6 py-3">المسؤول</th>
                            <th class="px-6 py-3">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <td class="px-6 py-4"><?= date('Y-m-d', strtotime($transaction['transaction_date'])) ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded <?= $transaction['type'] === 'إيراد' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= htmlspecialchars($transaction['type']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4"><?= htmlspecialchars($transaction['category']) ?></td>
                                <td class="px-6 py-4" title="<?= htmlspecialchars($transaction['description']) ?>">
                                    <?= mb_substr(htmlspecialchars($transaction['description']), 0, 50) ?>...
                                </td>
                                <td class="px-6 py-4 font-semibold <?= $transaction['type'] === 'إيراد' ? 'text-green-600' : 'text-red-600' ?>">
                                    <?= number_format($transaction['amount']) ?> <?= htmlspecialchars($transaction['currency_symbol']) ?>
                                    <?php if ($transaction['currency_id'] != 1): ?>
                                        <br><small class="text-gray-500">(<?= number_format($transaction['amount'] * $transaction['exchange_rate']) ?> ل.ل)</small>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4"><?= htmlspecialchars($transaction['currency_symbol']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($transaction['payment_method'] ?? '-') ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($transaction['created_by_name'] ?? 'غير محدد') ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex gap-2">
                                        <a href="?edit_transaction=<?= $transaction['id'] ?>" 
                                           class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                            ✏️ تعديل
                                        </a>
                                        <form method="POST" class="inline" 
                                              onsubmit="return confirm('⚠️ هل أنت متأكد من حذف هذه المعاملة؟\n\nسيتم التراجع عن أي تحديثات في بنود الميزانية.');">
                                            <input type="hidden" name="delete_transaction" value="1">
                                            <input type="hidden" name="transaction_id" value="<?= $transaction['id'] ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium">
                                                🗑️ حذف
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-8 text-gray-500">
                                    لا توجد معاملات مالية مسجلة
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // إنشاء الرسوم البيانية حسب العملة
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($chart_data_by_currency as $currency_symbol => $currency_chart): ?>
                const ctx_<?= $currency_chart['currency_code'] ?> = document.getElementById('financeChart_<?= $currency_chart['currency_code'] ?>');
                if (ctx_<?= $currency_chart['currency_code'] ?>) {
                    new Chart(ctx_<?= $currency_chart['currency_code'] ?>, {
                        type: 'bar',
                        data: {
                            labels: <?= json_encode($currency_chart['months']) ?>,
                            datasets: [
                                {
                                    label: 'الإيرادات',
                                    data: <?= json_encode($currency_chart['revenues']) ?>,
                                    backgroundColor: 'rgba(34, 197, 94, 0.7)',
                                    borderColor: 'rgba(34, 197, 94, 1)',
                                    borderWidth: 1
                                },
                                {
                                    label: 'المصروفات',
                                    data: <?= json_encode($currency_chart['expenses']) ?>,
                                    backgroundColor: 'rgba(239, 68, 68, 0.7)',
                                    borderColor: 'rgba(239, 68, 68, 1)',
                                    borderWidth: 1
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return value.toLocaleString() + ' <?= $currency_symbol ?>';
                                        }
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    position: 'top',
                                    labels: { font: { family: 'Cairo' } }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.dataset.label + ': ' + context.parsed.y.toLocaleString() + ' <?= $currency_symbol ?>';
                                        }
                                    },
                                    bodyFont: { family: 'Cairo' },
                                    titleFont: { family: 'Cairo' }
                                }
                            }
                        }
                    });
                }
            <?php endforeach; ?>
        });

        // دالة لإظهار/إخفاء حقول الدفع الإضافية
        function togglePaymentFields() {
            const paymentMethod = document.getElementById('payment_method').value;
            const bankFields = document.getElementById('bank_fields');
            
            if (paymentMethod === 'شيك' || paymentMethod === 'تحويل مصرفي') {
                bankFields.style.display = 'grid';
            } else {
                bankFields.style.display = 'none';
            }
        }
    </script>

    <!-- نموذج تعديل المعاملة المالية -->
    <?php if ($edit_transaction): ?>
    <div class="fixed inset-0 bg-black bg-opacity-50 z-50 overflow-y-auto" style="padding: 20px 0;">
        <div class="min-h-screen flex items-center justify-center px-4">
            <div class="bg-white rounded-lg w-full max-w-4xl my-8 shadow-2xl">
                <div class="bg-purple-600 text-white px-6 py-4 rounded-t-lg sticky top-0 z-10">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-semibold">✏️ تعديل المعاملة المالية</h3>
                        <a href="finance.php" class="text-white hover:text-gray-200 text-2xl font-bold">&times;</a>
                    </div>
                </div>
            
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="transaction_id" value="<?= $edit_transaction['id'] ?>">
                
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-3 mb-4">
                    <p class="text-sm text-purple-800">
                        ⚠️ <strong>ملاحظة:</strong> عند تعديل المعاملة، سيتم تحديث بنود الميزانية المرتبطة تلقائياً
                    </p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نوع المعاملة</label>
                        <select name="transaction_type" required class="w-full p-2 border border-gray-300 rounded-md">
                            <option value="إيراد" <?= $edit_transaction['type'] == 'إيراد' ? 'selected' : '' ?>>إيراد</option>
                            <option value="مصروف" <?= $edit_transaction['type'] == 'مصروف' ? 'selected' : '' ?>>مصروف</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الفئة</label>
                        <input type="text" name="category" required 
                               value="<?= htmlspecialchars($edit_transaction['category']) ?>"
                               class="w-full p-2 border border-gray-300 rounded-md">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الوصف</label>
                    <textarea name="description" required rows="3" 
                              class="w-full p-2 border border-gray-300 rounded-md"><?= htmlspecialchars($edit_transaction['description']) ?></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">المبلغ</label>
                        <input type="number" step="0.01" name="amount" required 
                               value="<?= $edit_transaction['amount'] ?>"
                               class="w-full p-2 border border-gray-300 rounded-md">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">العملة</label>
                        <select name="currency_id" required class="w-full p-2 border border-gray-300 rounded-md">
                            <?php foreach ($currencies as $currency): ?>
                                <option value="<?= $currency['id'] ?>" 
                                        <?= ($edit_transaction['currency_id'] == $currency['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">التاريخ</label>
                        <input type="date" name="transaction_date" required 
                               value="<?= $edit_transaction['transaction_date'] ?>"
                               class="w-full p-2 border border-gray-300 rounded-md">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">طريقة الدفع</label>
                        <select name="payment_method" required class="w-full p-2 border border-gray-300 rounded-md">
                            <option value="نقد" <?= $edit_transaction['payment_method'] == 'نقد' ? 'selected' : '' ?>>نقد</option>
                            <option value="شيك" <?= $edit_transaction['payment_method'] == 'شيك' ? 'selected' : '' ?>>شيك</option>
                            <option value="تحويل مصرفي" <?= $edit_transaction['payment_method'] == 'تحويل مصرفي' ? 'selected' : '' ?>>تحويل مصرفي</option>
                            <option value="بطاقة ائتمان" <?= $edit_transaction['payment_method'] == 'بطاقة ائتمان' ? 'selected' : '' ?>>بطاقة ائتمان</option>
                            <option value="أخرى" <?= $edit_transaction['payment_method'] == 'أخرى' ? 'selected' : '' ?>>أخرى</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">رقم المرجع</label>
                    <input type="text" name="reference_number" 
                           value="<?= htmlspecialchars($edit_transaction['reference_number'] ?? '') ?>"
                           class="w-full p-2 border border-gray-300 rounded-md">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">اسم البنك</label>
                        <input type="text" name="bank_name" 
                               value="<?= htmlspecialchars($edit_transaction['bank_name'] ?? '') ?>"
                               class="w-full p-2 border border-gray-300 rounded-md">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">رقم الشيك</label>
                        <input type="text" name="check_number" 
                               value="<?= htmlspecialchars($edit_transaction['check_number'] ?? '') ?>"
                               class="w-full p-2 border border-gray-300 rounded-md">
                    </div>
                </div>
                
                <!-- الربط مع البنود -->
                <?php if (!empty($budget_items)): ?>
                <div class="border-t pt-4 mt-4">
                    <h3 class="font-semibold text-sm mb-3 text-gray-700">🔗 الربط مع بنود الميزانية</h3>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">بند الميزانية</label>
                        <select name="budget_item_id" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                            <option value="">-- بدون ربط --</option>
                            <?php 
                            $current_committee = '';
                            foreach ($budget_items as $item): 
                                if ($current_committee != $item['committee_name']) {
                                    if ($current_committee != '') echo '</optgroup>';
                                    $current_committee = $item['committee_name'];
                                    echo '<optgroup label="🏛️ ' . htmlspecialchars($current_committee ?: 'بدون لجنة') . '">';
                                }
                            ?>
                                <option value="<?= $item['id'] ?>" 
                                        <?= ($edit_transaction['budget_item_id'] == $item['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($item['item_code']) ?> - <?= htmlspecialchars($item['name']) ?> 
                                    | متبقي: <?= number_format($item['remaining_amount'], 0) ?> <?= $item['currency_symbol'] ?>
                                </option>
                            <?php 
                            endforeach; 
                            if ($current_committee != '') echo '</optgroup>';
                            ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <a href="finance.php" 
                       class="px-6 py-2 text-gray-600 hover:text-gray-800 border rounded-lg inline-block">
                        إلغاء
                    </a>
                    <button type="submit" name="edit_transaction" 
                            class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700">
                        💾 حفظ التعديلات
                    </button>
                </div>
            </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</body>
</html> 
