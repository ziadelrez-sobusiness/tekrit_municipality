<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES 'utf8mb4'");
$db->exec("SET CHARACTER SET utf8mb4");
header('Content-Type: text/html; charset=utf-8');

$user = $auth->getUserInfo();
$message = '';
$error = '';

// معالجة تحديث المشروع
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project'])) {
    try {
        $project_id = intval($_POST['project_id']);
        $association_id = !empty($_POST['association_id']) ? intval($_POST['association_id']) : null;
        $total_budget = floatval($_POST['total_budget']);
        
        $stmt = $db->prepare("UPDATE projects SET association_id = ?, total_budget = ? WHERE id = ?");
        $stmt->execute([$association_id, $total_budget, $project_id]);
        
        $message = 'تم تحديث معلومات المشروع بنجاح!';
    } catch (PDOException $e) {
        $error = 'خطأ في التحديث: ' . $e->getMessage();
    }
}

// معالجة إضافة معاملة مالية للمشروع
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_transaction'])) {
    try {
        $project_id = intval($_POST['project_id']);
        $transaction_date = $_POST['transaction_date'];
        $type = $_POST['type'];
        $category = trim($_POST['category']);
        $description = trim($_POST['description']);
        $amount = floatval($_POST['amount']);
        $currency_id = intval($_POST['currency_id']);
        $payment_method = $_POST['payment_method'];
        $reference_number = trim($_POST['reference_number']);
        $budget_item_id = !empty($_POST['budget_item_id']) ? intval($_POST['budget_item_id']) : null;
        
        // إنشاء المعاملة المالية
        $stmt = $db->prepare("INSERT INTO financial_transactions 
            (transaction_date, type, category, description, amount, currency_id, payment_method, 
             reference_number, related_project_id, budget_item_id, created_by, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'معتمد')");
        $stmt->execute([$transaction_date, $type, $category, $description, $amount, $currency_id, 
                       $payment_method, $reference_number, $project_id, $budget_item_id, $user['id']]);
        
        // تحديث المبلغ المصروف في المشروع
        if ($type === 'مصروف') {
            $stmt = $db->prepare("UPDATE projects SET spent_amount = spent_amount + ? WHERE id = ?");
            $stmt->execute([$amount, $project_id]);
            
            // تحديث البند في الميزانية إن وجد
            if ($budget_item_id) {
                $stmt = $db->prepare("UPDATE budget_items 
                                     SET spent_amount = spent_amount + ?, 
                                         remaining_amount = remaining_amount - ? 
                                     WHERE id = ?");
                $stmt->execute([$amount, $amount, $budget_item_id]);
            }
        }
        
        $message = 'تم إضافة المعاملة المالية بنجاح!';
    } catch (PDOException $e) {
        $error = 'خطأ في إضافة المعاملة: ' . $e->getMessage();
    }
}

// جلب المشاريع مع معلوماتها المالية
$filter_status = $_GET['status'] ?? '';
$filter_association = $_GET['association'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($filter_status)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $filter_status;
}

if (!empty($filter_association)) {
    $where_conditions[] = "p.association_id = ?";
    $params[] = $filter_association;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$stmt = $db->prepare("
    SELECT p.*,
           a.name as association_name,
           c.currency_symbol,
           c.currency_code,
           (SELECT COUNT(*) FROM financial_transactions WHERE related_project_id = p.id) as transactions_count,
           (SELECT SUM(amount) FROM financial_transactions WHERE related_project_id = p.id AND type = 'إيراد') as total_revenue,
           (SELECT SUM(amount) FROM financial_transactions WHERE related_project_id = p.id AND type = 'مصروف') as total_expenses
    FROM projects p
    LEFT JOIN associations a ON p.association_id = a.id
    LEFT JOIN currencies c ON c.is_default = 1
    $where_clause
    ORDER BY p.start_date DESC
");
$stmt->execute($params);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// إضافة حقل name لكل مشروع (للتوافق)
foreach ($projects as &$project) {
    if (!isset($project['name'])) {
        $project['name'] = $project['project_name'] ?? $project['title'] ?? $project['project_title'] ?? 'مشروع #' . $project['id'];
    }
}
unset($project);

// إحصائيات
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_projects,
        SUM(CASE WHEN status = 'قيد التنفيذ' THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN status = 'مكتمل' THEN 1 ELSE 0 END) as completed_count,
        SUM(total_budget) as total_budget,
        SUM(spent_amount) as total_spent
    FROM projects
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// جلب الجمعيات
$stmt = $db->query("SELECT * FROM associations ORDER BY name");
$associations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب العملات
$stmt = $db->query("SELECT * FROM currencies WHERE is_active = 1");
$currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب المشروع المحدد للتفاصيل
$selected_project_id = $_GET['project_id'] ?? 0;
$project_transactions = [];
$budget_items = [];

if ($selected_project_id) {
    // جلب المعاملات المالية
    $stmt = $db->prepare("
        SELECT ft.*,
               c.currency_symbol,
               c.currency_code,
               u.full_name as created_by_name,
               bi.name as budget_item_name
        FROM financial_transactions ft
        LEFT JOIN currencies c ON ft.currency_id = c.id
        LEFT JOIN users u ON ft.created_by = u.id
        LEFT JOIN budget_items bi ON ft.budget_item_id = bi.id
        WHERE ft.related_project_id = ?
        ORDER BY ft.transaction_date DESC
    ");
    $stmt->execute([$selected_project_id]);
    $project_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب بنود الميزانية المرتبطة بالمشروع
    $stmt = $db->prepare("
        SELECT bi.*, b.name as budget_name, c.currency_symbol
        FROM budget_items bi
        LEFT JOIN budgets b ON bi.budget_id = b.id
        LEFT JOIN currencies c ON b.currency_id = c.id
        WHERE bi.related_project_id = ? OR bi.id IN 
              (SELECT DISTINCT budget_item_id FROM financial_transactions WHERE related_project_id = ? AND budget_item_id IS NOT NULL)
        ORDER BY bi.name
    ");
    $stmt->execute([$selected_project_id, $selected_project_id]);
    $budget_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب الفواتير المرتبطة بالمشروع
    $stmt = $db->prepare("
        SELECT si.*,
               s.name as supplier_name,
               c.currency_symbol,
               c.currency_code
        FROM supplier_invoices si
        LEFT JOIN suppliers s ON si.supplier_id = s.id
        LEFT JOIN currencies c ON si.currency_id = c.id
        WHERE si.related_project_id = ?
        ORDER BY si.invoice_date DESC
    ");
    $stmt->execute([$selected_project_id]);
    $project_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المشاريع - الجانب المالي - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .modal { display: none !important; }
        .modal.active { display: flex !important; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen p-6">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">🏗️ إدارة المشاريع - الجانب المالي</h1>
                    <p class="text-gray-600 mt-2">تتبع الميزانيات والمصروفات والجمعيات المنفذة</p>
                </div>
                <a href="../comprehensive_dashboard.php" class="bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition shadow-lg">
                    ← العودة
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 shadow">
                ✅ <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 shadow">
                ❌ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- إحصائيات -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
            <div class="bg-white p-6 rounded-lg shadow-sm border-r-4 border-blue-500">
                <p class="text-sm text-gray-500">إجمالي المشاريع</p>
                <p class="text-3xl font-bold text-blue-600"><?= number_format($stats['total_projects']) ?></p>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm border-r-4 border-yellow-500">
                <p class="text-sm text-gray-500">قيد التنفيذ</p>
                <p class="text-3xl font-bold text-yellow-600"><?= number_format($stats['active_count']) ?></p>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm border-r-4 border-green-500">
                <p class="text-sm text-gray-500">مكتمل</p>
                <p class="text-3xl font-bold text-green-600"><?= number_format($stats['completed_count']) ?></p>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm border-r-4 border-indigo-500">
                <p class="text-sm text-gray-500">إجمالي الميزانية</p>
                <p class="text-2xl font-bold text-indigo-600"><?= number_format($stats['total_budget'], 2) ?></p>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm border-r-4 border-red-500">
                <p class="text-sm text-gray-500">إجمالي المصروف</p>
                <p class="text-2xl font-bold text-red-600"><?= number_format($stats['total_spent'], 2) ?></p>
            </div>
        </div>

        <!-- اختيار المشروع والفلاتر -->
        <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
            <h3 class="font-semibold mb-4 text-lg">🔍 اختيار المشروع والبحث</h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-2">🏗️ المشروع (<?= count($projects) ?>)</label>
                    <select name="project_id" onchange="this.form.submit()" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">-- اختر مشروعاً --</option>
                        <?php foreach ($projects as $proj): ?>
                            <option value="<?= $proj['id'] ?>" <?= ($selected_project_id == $proj['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($proj['name']) ?> (<?= htmlspecialchars($proj['status']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">الحالة</label>
                    <select name="status" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">جميع الحالات</option>
                        <option value="قيد التخطيط" <?= ($filter_status === 'قيد التخطيط') ? 'selected' : '' ?>>قيد التخطيط</option>
                        <option value="قيد التنفيذ" <?= ($filter_status === 'قيد التنفيذ') ? 'selected' : '' ?>>قيد التنفيذ</option>
                        <option value="مكتمل" <?= ($filter_status === 'مكتمل') ? 'selected' : '' ?>>مكتمل</option>
                        <option value="متوقف" <?= ($filter_status === 'متوقف') ? 'selected' : '' ?>>متوقف</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">الجمعية المنفذة</label>
                    <select name="association" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">جميع الجمعيات</option>
                        <?php foreach ($associations as $assoc): ?>
                            <option value="<?= $assoc['id'] ?>" <?= ($filter_association == $assoc['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($assoc['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700">
                        بحث
                    </button>
                    <a href="projects_finance.php" class="bg-gray-500 text-white py-2 px-4 rounded-lg hover:bg-gray-600">
                        إعادة
                    </a>
                </div>
            </form>
        </div>

        <!-- المشاريع -->
        <div class="w-full">
            <!-- تفاصيل المشروع -->
            <div class="w-full">
                <?php if ($selected_project_id && !empty($project_transactions)): ?>
                <?php 
                $selected_project = array_filter($projects, fn($p) => $p['id'] == $selected_project_id);
                $selected_project = reset($selected_project);
                ?>
                
                <div class="bg-white rounded-lg shadow-sm mb-6">
                    <div class="p-6 border-b bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-t-lg">
                        <h2 class="text-2xl font-bold"><?= htmlspecialchars($selected_project['name']) ?></h2>
                        <p class="text-sm opacity-90 mt-1"><?= htmlspecialchars($selected_project['location']) ?> | <?= date('Y-m-d', strtotime($selected_project['start_date'])) ?></p>
                    </div>
                    
                    <div class="p-6">
                        <!-- معلومات المشروع -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                            <?php
                            // استخدام total_budget أو البحث عن حقول بديلة
                            $project_budget = 0;
                            $budget_source = '';
                            
                            if (!empty($selected_project['total_budget']) && $selected_project['total_budget'] > 0) {
                                $project_budget = $selected_project['total_budget'];
                                $budget_source = 'total_budget';
                            } elseif (isset($selected_project['target_amount']) && $selected_project['target_amount'] > 0) {
                                $project_budget = $selected_project['target_amount'];
                                $budget_source = 'target_amount';
                            } elseif (isset($selected_project['budget']) && $selected_project['budget'] > 0) {
                                $project_budget = $selected_project['budget'];
                                $budget_source = 'budget';
                            } elseif (isset($selected_project['estimated_cost']) && $selected_project['estimated_cost'] > 0) {
                                $project_budget = $selected_project['estimated_cost'];
                                $budget_source = 'estimated_cost';
                            }
                            
                            $project_spent = $selected_project['spent_amount'] ?? 0;
                            $project_remaining = $project_budget - $project_spent;
                            $project_progress = ($project_budget > 0) ? ($project_spent / $project_budget) * 100 : 0;
                            ?>
                            <div class="text-center p-4 bg-blue-50 rounded-lg">
                                <p class="text-sm text-gray-600">الميزانية</p>
                                <p class="text-xl font-bold text-blue-600"><?= number_format($project_budget, 0) ?> <?= htmlspecialchars($selected_project['currency_symbol'] ?? '$') ?></p>
                                <?php if ($budget_source === 'target_amount'): ?>
                                <p class="text-xs text-gray-500 mt-1">(هدف المساهمات)</p>
                                <?php elseif ($budget_source === 'estimated_cost'): ?>
                                <p class="text-xs text-gray-500 mt-1">(التكلفة المقدرة)</p>
                                <?php elseif ($project_budget == 0): ?>
                                <p class="text-xs text-red-500 mt-1">⚠️ لم يتم تحديد الميزانية</p>
                                <?php endif; ?>
                            </div>
                            <div class="text-center p-4 bg-red-50 rounded-lg">
                                <p class="text-sm text-gray-600">المصروف</p>
                                <p class="text-xl font-bold text-red-600"><?= number_format($project_spent, 0) ?> <?= htmlspecialchars($selected_project['currency_symbol'] ?? '$') ?></p>
                            </div>
                            <div class="text-center p-4 bg-green-50 rounded-lg">
                                <p class="text-sm text-gray-600">المتبقي</p>
                                <p class="text-xl font-bold text-green-600"><?= number_format($project_remaining, 0) ?> <?= htmlspecialchars($selected_project['currency_symbol'] ?? '$') ?></p>
                            </div>
                            <div class="text-center p-4 bg-purple-50 rounded-lg">
                                <p class="text-sm text-gray-600">المعاملات</p>
                                <p class="text-xl font-bold text-purple-600"><?= count($project_transactions) ?></p>
                            </div>
                        </div>
                        
                        <!-- شريط التقدم -->
                        <div class="mb-6 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-200">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-semibold text-gray-700">📊 نسبة الإنفاق</span>
                                <span class="text-lg font-bold text-blue-600"><?= number_format($project_progress, 1) ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
                                <div class="bg-gradient-to-r from-blue-500 to-indigo-600 h-4 rounded-full transition-all duration-300 flex items-center justify-end px-2" 
                                     style="width: <?= min($project_progress, 100) ?>%">
                                    <?php if ($project_progress > 10): ?>
                                    <span class="text-xs text-white font-bold"><?= number_format($project_progress, 0) ?>%</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mt-2 text-xs text-gray-600 text-center">
                                <?php if ($project_progress < 50): ?>
                                    ✅ المشروع في بداية التنفيذ
                                <?php elseif ($project_progress < 80): ?>
                                    ⚠️ المشروع في منتصف التنفيذ
                                <?php elseif ($project_progress < 100): ?>
                                    🔥 المشروع قارب على الانتهاء
                                <?php else: ?>
                                    🎉 تم استنفاد الميزانية
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- بنود الميزانية المرتبطة -->
                        <?php if (!empty($budget_items)): ?>
                        <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                            <h3 class="font-semibold mb-3">📊 بنود الميزانية المرتبطة</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <?php foreach ($budget_items as $item): 
                                    $item_progress = $item['allocated_amount'] > 0 ? ($item['spent_amount'] / $item['allocated_amount']) * 100 : 0;
                                ?>
                                <div class="p-3 bg-white rounded border">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <p class="font-semibold text-sm"><?= htmlspecialchars($item['name']) ?></p>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($item['budget_name']) ?></p>
                                        </div>
                                        <span class="text-xs font-bold"><?= number_format($item_progress, 0) ?>%</span>
                                    </div>
                                    <div class="text-xs text-gray-600">
                                        المصروف: <strong><?= number_format($item['spent_amount'], 0) ?></strong> / <?= number_format($item['allocated_amount'], 0) ?> <?= htmlspecialchars($item['currency_symbol']) ?>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-1.5 mt-2">
                                        <div class="bg-blue-500 h-1.5 rounded-full" style="width: <?= min($item_progress, 100) ?>%"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- الفواتير المرتبطة بالمشروع -->
                        <?php if (!empty($project_invoices)): ?>
                        <div class="mb-6 p-4 bg-blue-50 rounded-lg border-r-4 border-blue-500">
                            <h3 class="font-semibold mb-3 text-blue-900 text-lg">📄 فواتير المشروع (<?= count($project_invoices) ?>)</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm bg-white rounded border">
                                    <thead class="bg-blue-100">
                                        <tr>
                                            <th class="text-right p-3">رقم الفاتورة</th>
                                            <th class="text-right p-3">المورد</th>
                                            <th class="text-right p-3">تاريخ الفاتورة</th>
                                            <th class="text-right p-3">المبلغ الإجمالي</th>
                                            <th class="text-right p-3">المدفوع</th>
                                            <th class="text-right p-3">المتبقي</th>
                                            <th class="text-center p-3">الحالة</th>
                                            <th class="text-center p-3">الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        <?php foreach ($project_invoices as $invoice): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="p-3 font-mono font-bold text-blue-600">
                                                <?= htmlspecialchars($invoice['invoice_number']) ?>
                                            </td>
                                            <td class="p-3"><?= htmlspecialchars($invoice['supplier_name']) ?></td>
                                            <td class="p-3"><?= $invoice['invoice_date'] ?></td>
                                            <td class="p-3 font-semibold">
                                                <?= number_format($invoice['total_amount'], 2) ?> <?= $invoice['currency_symbol'] ?>
                                            </td>
                                            <td class="p-3 text-green-600 font-semibold">
                                                <?= number_format($invoice['paid_amount'], 2) ?> <?= $invoice['currency_symbol'] ?>
                                            </td>
                                            <td class="p-3 text-red-600 font-semibold">
                                                <?= number_format($invoice['remaining_amount'], 2) ?> <?= $invoice['currency_symbol'] ?>
                                            </td>
                                            <td class="p-3 text-center">
                                                <?php
                                                $statusColors = [
                                                    'غير مدفوع' => 'bg-red-100 text-red-800',
                                                    'مدفوع جزئياً' => 'bg-yellow-100 text-yellow-800',
                                                    'مدفوع بالكامل' => 'bg-green-100 text-green-800',
                                                    'متأخر' => 'bg-red-100 text-red-800'
                                                ];
                                                $statusClass = $statusColors[$invoice['status']] ?? 'bg-gray-100 text-gray-800';
                                                ?>
                                                <span class="px-2 py-1 rounded text-xs font-semibold <?= $statusClass ?>">
                                                    <?= htmlspecialchars($invoice['status']) ?>
                                                </span>
                                            </td>
                                            <td class="p-3 text-center">
                                                <a href="invoices.php?invoice_id=<?= $invoice['id'] ?>" 
                                                   class="text-blue-600 hover:text-blue-800 font-semibold text-sm">
                                                    عرض التفاصيل →
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="bg-gray-100 font-bold">
                                        <tr>
                                            <td colspan="3" class="p-3 text-left">الإجمالي:</td>
                                            <td class="p-3">
                                                <?php
                                                $total_invoices = array_sum(array_column($project_invoices, 'total_amount'));
                                                echo number_format($total_invoices, 2);
                                                ?> <?= $project_invoices[0]['currency_symbol'] ?? '' ?>
                                            </td>
                                            <td class="p-3 text-green-600">
                                                <?php
                                                $total_paid = array_sum(array_column($project_invoices, 'paid_amount'));
                                                echo number_format($total_paid, 2);
                                                ?> <?= $project_invoices[0]['currency_symbol'] ?? '' ?>
                                            </td>
                                            <td class="p-3 text-red-600">
                                                <?php
                                                $total_remaining = array_sum(array_column($project_invoices, 'remaining_amount'));
                                                echo number_format($total_remaining, 2);
                                                ?> <?= $project_invoices[0]['currency_symbol'] ?? '' ?>
                                            </td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- أزرار الإجراءات -->
                        <div class="flex gap-3 mb-6">
                            <button onclick="openUpdateProjectModal(<?= htmlspecialchars(json_encode($selected_project), ENT_QUOTES) ?>)" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                ✏️ تحديث معلومات المشروع
                            </button>
                            <button onclick="openAddTransactionModal(<?= $selected_project_id ?>)" 
                                    class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                                ➕ إضافة معاملة مالية
                            </button>
                        </div>
                        
                        <!-- جدول المعاملات -->
                        <h3 class="font-semibold mb-3 text-lg">💳 المعاملات المالية</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="text-right p-3">التاريخ</th>
                                        <th class="text-right p-3">النوع</th>
                                        <th class="text-right p-3">الفئة</th>
                                        <th class="text-right p-3">الوصف</th>
                                        <th class="text-right p-3">المبلغ</th>
                                        <th class="text-right p-3">بند الميزانية</th>
                                        <th class="text-right p-3">المرجع</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <?php foreach ($project_transactions as $trans): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="p-3"><?= date('Y-m-d', strtotime($trans['transaction_date'])) ?></td>
                                        <td class="p-3">
                                            <span class="px-2 py-1 rounded text-xs font-semibold <?= $trans['type'] == 'إيراد' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                <?= htmlspecialchars($trans['type']) ?>
                                            </span>
                                        </td>
                                        <td class="p-3"><?= htmlspecialchars($trans['category']) ?></td>
                                        <td class="p-3 text-sm"><?= htmlspecialchars($trans['description']) ?></td>
                                        <td class="p-3 font-semibold <?= $trans['type'] == 'إيراد' ? 'text-green-600' : 'text-red-600' ?>">
                                            <?= number_format($trans['amount'], 2) ?> <?= htmlspecialchars($trans['currency_symbol']) ?>
                                        </td>
                                        <td class="p-3 text-xs"><?= $trans['budget_item_name'] ? htmlspecialchars($trans['budget_item_name']) : '-' ?></td>
                                        <td class="p-3 text-xs font-mono"><?= htmlspecialchars($trans['reference_number']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($selected_project_id): ?>
                <div class="bg-white rounded-lg shadow-sm p-12 text-center">
                    <div class="text-6xl mb-4">📊</div>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">لا توجد معاملات مالية لهذا المشروع</h3>
                    <p class="text-gray-500 mb-4">ابدأ بإضافة معاملات مالية لتتبع ميزانية المشروع</p>
                    <button onclick="openAddTransactionModal(<?= $selected_project_id ?>)" 
                            class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700">
                        ➕ إضافة معاملة مالية
                    </button>
                </div>
                <?php else: ?>
                <div class="bg-white rounded-lg shadow-sm p-12 text-center">
                    <div class="text-6xl mb-4">🏗️</div>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">اختر مشروعاً لعرض التفاصيل</h3>
                    <p class="text-gray-500">اضغط على أي مشروع من القائمة لعرض معاملاته المالية</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal تحديث المشروع -->
    <div id="updateProjectModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg w-full max-w-2xl">
            <div class="bg-blue-600 text-white px-6 py-4 rounded-t-lg">
                <h3 class="text-xl font-semibold">✏️ تحديث معلومات المشروع</h3>
            </div>
            
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="project_id" id="update_project_id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2">اسم المشروع</label>
                        <input type="text" id="update_project_name" readonly 
                               class="w-full px-4 py-2 border rounded-lg bg-gray-50">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">الجمعية المنفذة</label>
                        <select name="association_id" id="update_association_id" class="w-full px-4 py-2 border rounded-lg">
                            <option value="">بدون جمعية</option>
                            <?php foreach ($associations as $assoc): ?>
                                <option value="<?= $assoc['id'] ?>"><?= htmlspecialchars($assoc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">إجمالي الميزانية *</label>
                        <input type="number" name="total_budget" id="update_total_budget" required step="0.01" min="0"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="closeModal('updateProjectModal')" 
                            class="px-6 py-2 text-gray-600 hover:text-gray-800 border rounded-lg">
                        إلغاء
                    </button>
                    <button type="submit" name="update_project" 
                            class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                        ✅ تحديث
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal إضافة معاملة -->
    <div id="addTransactionModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg w-full max-w-3xl">
            <div class="bg-green-600 text-white px-6 py-4 rounded-t-lg">
                <h3 class="text-xl font-semibold">➕ إضافة معاملة مالية للمشروع</h3>
            </div>
            
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="project_id" id="trans_project_id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">تاريخ المعاملة *</label>
                        <input type="date" name="transaction_date" required value="<?= date('Y-m-d') ?>"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">نوع المعاملة *</label>
                        <select name="type" required class="w-full px-4 py-2 border rounded-lg">
                            <option value="مصروف">مصروف</option>
                            <option value="إيراد">إيراد</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">الفئة *</label>
                        <input type="text" name="category" required placeholder="مثال: مواد بناء، أجور..."
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">المبلغ *</label>
                        <input type="number" name="amount" required step="0.01" min="0"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">العملة *</label>
                        <select name="currency_id" required class="w-full px-4 py-2 border rounded-lg">
                            <?php foreach ($currencies as $currency): ?>
                                <option value="<?= $currency['id'] ?>" <?= ($currency['is_default']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">طريقة الدفع *</label>
                        <select name="payment_method" required class="w-full px-4 py-2 border rounded-lg">
                            <option value="نقد">نقد</option>
                            <option value="شيك">شيك</option>
                            <option value="تحويل مصرفي">تحويل مصرفي</option>
                            <option value="بطاقة ائتمان">بطاقة ائتمان</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">بند الميزانية (اختياري)</label>
                        <select name="budget_item_id" class="w-full px-4 py-2 border rounded-lg">
                            <option value="">بدون بند</option>
                            <?php foreach ($budget_items as $item): ?>
                                <option value="<?= $item['id'] ?>"><?= htmlspecialchars($item['name']) ?> (<?= htmlspecialchars($item['budget_name']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">الرقم المرجعي</label>
                        <input type="text" name="reference_number" placeholder="INV-XXX"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2">الوصف *</label>
                        <textarea name="description" required rows="2" 
                                  class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500"></textarea>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="closeModal('addTransactionModal')" 
                            class="px-6 py-2 text-gray-600 hover:text-gray-800 border rounded-lg">
                        إلغاء
                    </button>
                    <button type="submit" name="add_transaction" 
                            class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                        ✅ إضافة المعاملة
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function openUpdateProjectModal(project) {
            document.getElementById('update_project_id').value = project.id;
            document.getElementById('update_project_name').value = project.name;
            document.getElementById('update_association_id').value = project.association_id || '';
            document.getElementById('update_total_budget').value = project.total_budget;
            openModal('updateProjectModal');
        }

        function openAddTransactionModal(projectId) {
            document.getElementById('trans_project_id').value = projectId;
            openModal('addTransactionModal');
        }

        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('active');
                }
            });
        }
    </script>
</body>
</html>

