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

// الفلاتر
$filter_period = $_GET['period'] ?? 'current_month';
$filter_start_date = $_GET['start_date'] ?? '';
$filter_end_date = $_GET['end_date'] ?? '';

// تحديد الفترة الزمنية
$where_date = "";
$params = [];

switch ($filter_period) {
    case 'today':
        $where_date = "DATE(transaction_date) = CURDATE()";
        break;
    case 'current_month':
        $where_date = "MONTH(transaction_date) = MONTH(CURDATE()) AND YEAR(transaction_date) = YEAR(CURDATE())";
        break;
    case 'current_year':
        $where_date = "YEAR(transaction_date) = YEAR(CURDATE())";
        break;
    case 'custom':
        if (!empty($filter_start_date) && !empty($filter_end_date)) {
            $where_date = "transaction_date BETWEEN ? AND ?";
            $params = [$filter_start_date, $filter_end_date];
        } else {
            $where_date = "1=1";
        }
        break;
    default:
        $where_date = "MONTH(transaction_date) = MONTH(CURDATE()) AND YEAR(transaction_date) = YEAR(CURDATE())";
}

// إحصائيات الإيرادات والمصروفات حسب العملة
$stmt = $db->prepare("
    SELECT 
        ft.type,
        c.currency_symbol,
        c.currency_code,
        c.currency_name,
        SUM(ft.amount) as total_amount,
        COUNT(*) as transaction_count
    FROM financial_transactions ft
    LEFT JOIN currencies c ON ft.currency_id = c.id
    WHERE ft.status = 'معتمد' AND $where_date
    GROUP BY ft.type, c.currency_symbol, c.currency_code, c.currency_name
    ORDER BY c.currency_code, ft.type
");
$stmt->execute($params);
$revenue_expense = $stmt->fetchAll(PDO::FETCH_ASSOC);

// تنظيم البيانات حسب العملة
$financial_summary = [];
foreach ($revenue_expense as $row) {
    $currency_code = $row['currency_code'];
    if (!isset($financial_summary[$currency_code])) {
        $financial_summary[$currency_code] = [
            'currency_name' => $row['currency_name'],
            'currency_symbol' => $row['currency_symbol'],
            'revenue' => 0,
            'expense' => 0,
            'revenue_count' => 0,
            'expense_count' => 0
        ];
    }
    
    if ($row['type'] === 'إيراد') {
        $financial_summary[$currency_code]['revenue'] = floatval($row['total_amount']);
        $financial_summary[$currency_code]['revenue_count'] = intval($row['transaction_count']);
    } else {
        $financial_summary[$currency_code]['expense'] = floatval($row['total_amount']);
        $financial_summary[$currency_code]['expense_count'] = intval($row['transaction_count']);
    }
}

// المستحقات (ما لها) - من الجباية
$receivables = [];
try {
    $stmt = $db->query("
        SELECT 
            c.currency_symbol,
            c.currency_code,
            SUM(tc.total_amount - tc.paid_amount) as amount_due,
            COUNT(*) as count
        FROM tax_collections tc
        LEFT JOIN currencies c ON tc.currency_id = c.id
        WHERE tc.payment_status != 'مدفوع بالكامل' AND tc.payment_status != 'ملغي'
        GROUP BY c.currency_symbol, c.currency_code
    ");
    $receivables = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // الجدول قد لا يكون موجوداً
}

// الالتزامات (ما عليها) - من الفواتير
$payables = [];
try {
    $stmt = $db->query("
        SELECT 
            c.currency_symbol,
            c.currency_code,
            SUM(si.total_amount - si.paid_amount) as amount_due,
            COUNT(*) as count
        FROM supplier_invoices si
        LEFT JOIN currencies c ON si.currency_id = c.id
        WHERE si.status != 'مدفوع بالكامل'
        GROUP BY c.currency_symbol, c.currency_code
    ");
    $payables = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // الجدول قد لا يكون موجوداً
}

// إحصائيات المساهمات
$contributions_stats = [];
try {
    $stmt = $db->query("
        SELECT 
            c.currency_symbol,
            c.currency_code,
            COUNT(pc.id) as count,
            SUM(pc.contribution_amount) as total_amount,
            COUNT(DISTINCT pc.project_id) as projects_count,
            COUNT(DISTINCT pc.contributor_name) as contributors_count
        FROM project_contributions pc
        INNER JOIN currencies c ON pc.currency_id = c.id
        WHERE pc.is_verified = 1
        GROUP BY c.currency_symbol, c.currency_code
        ORDER BY total_amount DESC
    ");
    $contributions_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // الجدول قد لا يكون موجوداً
}

// إحصائيات المشاريع
$projects_stats = [];
try {
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_projects,
            SUM(CASE WHEN allow_public_contributions = 1 THEN 1 ELSE 0 END) as public_contribution_projects,
            SUM(CASE WHEN status = 'قيد التنفيذ' THEN 1 ELSE 0 END) as active_projects,
            SUM(CASE WHEN status = 'مكتمل' THEN 1 ELSE 0 END) as completed_projects
        FROM projects
    ");
    $projects_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $projects_stats = [
        'total_projects' => 0,
        'public_contribution_projects' => 0,
        'active_projects' => 0,
        'completed_projects' => 0
    ];
}

// حالة الميزانيات
$budget_status = [];
try {
    $stmt = $db->query("
        SELECT 
            b.name as budget_name,
            b.fiscal_year,
            c.currency_symbol,
            SUM(bi.allocated_amount) as total_allocated,
            SUM(bi.spent_amount) as total_spent,
            SUM(bi.remaining_amount) as total_remaining
        FROM budgets b
        LEFT JOIN budget_items bi ON b.id = bi.budget_id
        LEFT JOIN currencies c ON b.currency_id = c.id
        WHERE b.status = 'معتمد'
        GROUP BY b.id, b.name, b.fiscal_year, c.currency_symbol
        ORDER BY b.fiscal_year DESC
    ");
    $budget_status = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // الجدول قد لا يكون موجوداً
}

// أحدث المعاملات
$stmt = $db->prepare("
    SELECT 
        ft.*,
        c.currency_symbol,
        c.currency_code,
        u.full_name as created_by_name
    FROM financial_transactions ft
    LEFT JOIN currencies c ON ft.currency_id = c.id
    LEFT JOIN users u ON ft.created_by = u.id
    WHERE $where_date
    ORDER BY ft.transaction_date DESC, ft.created_at DESC
    LIMIT 20
");
$stmt->execute($params);
$recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// إحصائيات المشاريع
$project_stats = [];
try {
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_projects,
            SUM(CASE WHEN status = 'قيد التنفيذ' THEN 1 ELSE 0 END) as active_projects,
            SUM(total_budget) as total_budget,
            SUM(spent_amount) as total_spent
        FROM projects
    ");
    $project_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // الجدول قد لا يكون موجوداً
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم المالية - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen p-6">
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between no-print">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">📊 لوحة التحكم المالية الشاملة</h1>
                <p class="text-gray-600 mt-2">نظرة شاملة على الوضع المالي للبلدية</p>
            </div>
            <div class="flex gap-3">
                <button onclick="window.print()" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition shadow-lg">
                    🖨️ طباعة
                </button>
                <a href="../comprehensive_dashboard.php" class="bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition shadow-lg">
                    ← العودة
                </a>
            </div>
        </div>

        <!-- فلاتر الفترة الزمنية -->
        <div class="bg-white p-6 rounded-lg shadow-sm mb-6 no-print">
            <h3 class="font-semibold mb-4 text-lg">📅 اختر الفترة الزمنية</h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <select name="period" id="period" onchange="toggleCustomDates()" class="w-full px-4 py-2 border rounded-lg">
                        <option value="today" <?= ($filter_period === 'today') ? 'selected' : '' ?>>اليوم</option>
                        <option value="current_month" <?= ($filter_period === 'current_month') ? 'selected' : '' ?>>الشهر الحالي</option>
                        <option value="current_year" <?= ($filter_period === 'current_year') ? 'selected' : '' ?>>السنة الحالية</option>
                        <option value="custom" <?= ($filter_period === 'custom') ? 'selected' : '' ?>>فترة مخصصة</option>
                    </select>
                </div>
                
                <div id="custom_dates" style="display: <?= ($filter_period === 'custom') ? 'contents' : 'none' ?>;">
                    <div>
                        <input type="date" name="start_date" value="<?= $filter_start_date ?>" 
                               class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <input type="date" name="end_date" value="<?= $filter_end_date ?>" 
                               class="w-full px-4 py-2 border rounded-lg">
                    </div>
                </div>
                
                <div>
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700">
                        عرض
                    </button>
                </div>
            </form>
        </div>

        <!-- الملخص المالي العام -->
        <?php foreach ($financial_summary as $currency_code => $summary): ?>
        <div class="mb-6">
            <h2 class="text-xl font-bold mb-4 text-gray-800">
                💱 الملخص المالي بالعملة: <?= htmlspecialchars($summary['currency_name']) ?> (<?= htmlspecialchars($summary['currency_symbol']) ?>)
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <!-- الإيرادات -->
                <div class="bg-gradient-to-br from-green-500 to-green-600 text-white p-6 rounded-lg shadow-lg">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-3xl">📈</div>
                        <div class="bg-white bg-opacity-20 px-3 py-1 rounded-full text-xs">
                            <?= $summary['revenue_count'] ?> معاملة
                        </div>
                    </div>
                    <p class="text-sm opacity-90">إجمالي الإيرادات</p>
                    <p class="text-3xl font-bold mt-2">
                        <?= number_format($summary['revenue'], 2) ?> <?= htmlspecialchars($summary['currency_symbol']) ?>
                    </p>
                </div>
                
                <!-- المصروفات -->
                <div class="bg-gradient-to-br from-red-500 to-red-600 text-white p-6 rounded-lg shadow-lg">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-3xl">📉</div>
                        <div class="bg-white bg-opacity-20 px-3 py-1 rounded-full text-xs">
                            <?= $summary['expense_count'] ?> معاملة
                        </div>
                    </div>
                    <p class="text-sm opacity-90">إجمالي المصروفات</p>
                    <p class="text-3xl font-bold mt-2">
                        <?= number_format($summary['expense'], 2) ?> <?= htmlspecialchars($summary['currency_symbol']) ?>
                    </p>
                </div>
                
                <!-- الرصيد -->
                <?php $balance = $summary['revenue'] - $summary['expense']; ?>
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white p-6 rounded-lg shadow-lg">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-3xl">💰</div>
                        <div class="bg-white bg-opacity-20 px-3 py-1 rounded-full text-xs">
                            صافي
                        </div>
                    </div>
                    <p class="text-sm opacity-90">الرصيد الحالي</p>
                    <p class="text-3xl font-bold mt-2">
                        <?= number_format($balance, 2) ?> <?= htmlspecialchars($summary['currency_symbol']) ?>
                    </p>
                </div>
                
                <!-- النسبة المئوية -->
                <div class="bg-gradient-to-br from-purple-500 to-purple-600 text-white p-6 rounded-lg shadow-lg">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-3xl">📊</div>
                        <div class="bg-white bg-opacity-20 px-3 py-1 rounded-full text-xs">
                            نسبة
                        </div>
                    </div>
                    <p class="text-sm opacity-90">نسبة المصروفات/الإيرادات</p>
                    <?php $percentage = $summary['revenue'] > 0 ? ($summary['expense'] / $summary['revenue']) * 100 : 0; ?>
                    <p class="text-3xl font-bold mt-2">
                        <?= number_format($percentage, 1) ?>%
                    </p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($financial_summary)): ?>
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-lg mb-6">
            ℹ️ لا توجد معاملات مالية للفترة المحددة
        </div>
        <?php endif; ?>

        <!-- المستحقات والالتزامات -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- المستحقات (ما لها) -->
            <div class="bg-white rounded-lg shadow-sm">
                <div class="p-6 border-b bg-green-50">
                    <h3 class="text-lg font-bold text-green-800">💵 المستحقات (ما لها)</h3>
                    <p class="text-sm text-green-600">المبالغ المستحقة للبلدية من الضرائب والجباية</p>
                </div>
                <div class="p-6">
                    <?php if (!empty($receivables)): ?>
                        <?php foreach ($receivables as $rec): ?>
                        <div class="flex justify-between items-center p-4 bg-green-50 rounded-lg mb-3">
                            <div>
                                <p class="font-semibold text-green-800">المبلغ المستحق</p>
                                <p class="text-xs text-green-600"><?= $rec['count'] ?> عملية جباية</p>
                            </div>
                            <p class="text-2xl font-bold text-green-600">
                                <?= number_format($rec['amount_due'], 2) ?> <?= htmlspecialchars($rec['currency_symbol']) ?>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-gray-500 py-8">✅ لا توجد مستحقات غير محصلة</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- الالتزامات (ما عليها) -->
            <div class="bg-white rounded-lg shadow-sm">
                <div class="p-6 border-b bg-red-50">
                    <h3 class="text-lg font-bold text-red-800">💳 الالتزامات (ما عليها)</h3>
                    <p class="text-sm text-red-600">المبالغ المستحقة على البلدية للموردين</p>
                </div>
                <div class="p-6">
                    <?php if (!empty($payables)): ?>
                        <?php foreach ($payables as $pay): ?>
                        <div class="flex justify-between items-center p-4 bg-red-50 rounded-lg mb-3">
                            <div>
                                <p class="font-semibold text-red-800">المبلغ المستحق</p>
                                <p class="text-xs text-red-600"><?= $pay['count'] ?> فاتورة</p>
                            </div>
                            <p class="text-2xl font-bold text-red-600">
                                <?= number_format($pay['amount_due'], 2) ?> <?= htmlspecialchars($pay['currency_symbol']) ?>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-gray-500 py-8">✅ لا توجد التزامات غير مدفوعة</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- المساهمات الشعبية والمشاريع -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- المساهمات الشعبية -->
            <div class="bg-white rounded-lg shadow-sm">
                <div class="p-6 border-b bg-purple-50">
                    <h3 class="text-lg font-bold text-purple-800">💰 المساهمات الشعبية</h3>
                    <p class="text-sm text-purple-600">إجمالي المساهمات الشعبية في المشاريع</p>
                </div>
                <div class="p-6">
                    <?php if (!empty($contributions_stats)): ?>
                        <?php foreach ($contributions_stats as $cont_stat): ?>
                        <div class="p-4 bg-purple-50 rounded-lg mb-3">
                            <div class="flex justify-between items-center mb-2">
                                <p class="font-semibold text-purple-800">الإجمالي</p>
                                <p class="text-2xl font-bold text-purple-600">
                                    <?= number_format($cont_stat['total_amount'], 0) ?> <?= htmlspecialchars($cont_stat['currency_symbol']) ?>
                                </p>
                            </div>
                            <div class="grid grid-cols-3 gap-2 text-xs text-purple-600">
                                <div class="text-center">
                                    <p class="font-semibold"><?= number_format($cont_stat['count']) ?></p>
                                    <p>مساهمة</p>
                                </div>
                                <div class="text-center">
                                    <p class="font-semibold"><?= number_format($cont_stat['projects_count']) ?></p>
                                    <p>مشروع</p>
                                </div>
                                <div class="text-center">
                                    <p class="font-semibold"><?= number_format($cont_stat['contributors_count']) ?></p>
                                    <p>مساهم</p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <a href="contributions.php" class="block text-center text-purple-600 hover:text-purple-800 font-semibold mt-3">
                            عرض جميع المساهمات ←
                        </a>
                    <?php else: ?>
                        <p class="text-center text-gray-500 py-8">لا توجد مساهمات بعد</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- إحصائيات المشاريع -->
            <div class="bg-white rounded-lg shadow-sm">
                <div class="p-6 border-b bg-indigo-50">
                    <h3 class="text-lg font-bold text-indigo-800">🏗️ المشاريع</h3>
                    <p class="text-sm text-indigo-600">ملخص المشاريع والمبادرات</p>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="text-center p-4 bg-indigo-50 rounded-lg">
                            <p class="text-3xl font-bold text-indigo-600"><?= number_format($projects_stats['total_projects']) ?></p>
                            <p class="text-sm text-indigo-600 mt-1">إجمالي المشاريع</p>
                        </div>
                        
                        <div class="text-center p-4 bg-yellow-50 rounded-lg">
                            <p class="text-3xl font-bold text-yellow-600"><?= number_format($projects_stats['active_projects']) ?></p>
                            <p class="text-sm text-yellow-600 mt-1">قيد التنفيذ</p>
                        </div>
                        
                        <div class="text-center p-4 bg-green-50 rounded-lg">
                            <p class="text-3xl font-bold text-green-600"><?= number_format($projects_stats['completed_projects']) ?></p>
                            <p class="text-sm text-green-600 mt-1">مكتمل</p>
                        </div>
                        
                        <div class="text-center p-4 bg-purple-50 rounded-lg">
                            <p class="text-3xl font-bold text-purple-600"><?= number_format($projects_stats['public_contribution_projects']) ?></p>
                            <p class="text-sm text-purple-600 mt-1">يقبل مساهمات</p>
                        </div>
                    </div>
                    <a href="projects_unified.php" class="block text-center text-indigo-600 hover:text-indigo-800 font-semibold mt-4">
                        عرض جميع المشاريع ←
                    </a>
                </div>
            </div>
        </div>

        <!-- حالة الميزانيات -->
        <?php if (!empty($budget_status)): ?>
        <div class="bg-white rounded-lg shadow-sm mb-6">
            <div class="p-6 border-b bg-indigo-50">
                <h3 class="text-lg font-bold text-indigo-800">📊 حالة الميزانيات المعتمدة</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($budget_status as $budget): 
                        $percentage = $budget['total_allocated'] > 0 ? ($budget['total_spent'] / $budget['total_allocated']) * 100 : 0;
                        $progressColor = $percentage < 50 ? 'bg-green-500' : ($percentage < 80 ? 'bg-yellow-500' : 'bg-red-500');
                    ?>
                    <div class="border rounded-lg p-4">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <h4 class="font-bold text-gray-800"><?= htmlspecialchars($budget['budget_name']) ?></h4>
                                <p class="text-xs text-gray-500">السنة المالية <?= $budget['fiscal_year'] ?></p>
                            </div>
                            <span class="text-xs font-bold px-2 py-1 rounded <?= $progressColor ?> text-white">
                                <?= number_format($percentage, 1) ?>%
                            </span>
                        </div>
                        
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">المخصص:</span>
                                <span class="font-semibold"><?= number_format($budget['total_allocated'], 0) ?> <?= htmlspecialchars($budget['currency_symbol']) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">المصروف:</span>
                                <span class="font-semibold text-red-600"><?= number_format($budget['total_spent'], 0) ?> <?= htmlspecialchars($budget['currency_symbol']) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">المتبقي:</span>
                                <span class="font-semibold text-green-600"><?= number_format($budget['total_remaining'], 0) ?> <?= htmlspecialchars($budget['currency_symbol']) ?></span>
                            </div>
                        </div>
                        
                        <div class="mt-3 w-full bg-gray-200 rounded-full h-3">
                            <div class="<?= $progressColor ?> h-3 rounded-full transition-all" style="width: <?= min($percentage, 100) ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- إحصائيات المشاريع -->
        <?php if (!empty($project_stats) && $project_stats['total_projects'] > 0): ?>
        <div class="bg-white rounded-lg shadow-sm mb-6">
            <div class="p-6 border-b bg-orange-50">
                <h3 class="text-lg font-bold text-orange-800">🏗️ إحصائيات المشاريع</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="text-center p-4 bg-blue-50 rounded-lg">
                        <p class="text-sm text-gray-600">إجمالي المشاريع</p>
                        <p class="text-3xl font-bold text-blue-600"><?= $project_stats['total_projects'] ?></p>
                    </div>
                    <div class="text-center p-4 bg-yellow-50 rounded-lg">
                        <p class="text-sm text-gray-600">قيد التنفيذ</p>
                        <p class="text-3xl font-bold text-yellow-600"><?= $project_stats['active_projects'] ?></p>
                    </div>
                    <div class="text-center p-4 bg-indigo-50 rounded-lg">
                        <p class="text-sm text-gray-600">إجمالي الميزانية</p>
                        <p class="text-2xl font-bold text-indigo-600"><?= number_format($project_stats['total_budget'], 0) ?></p>
                    </div>
                    <div class="text-center p-4 bg-red-50 rounded-lg">
                        <p class="text-sm text-gray-600">إجمالي المصروف</p>
                        <p class="text-2xl font-bold text-red-600"><?= number_format($project_stats['total_spent'], 0) ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- أحدث المعاملات المالية -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="p-6 border-b bg-gray-50">
                <h3 class="text-lg font-bold text-gray-800">📋 أحدث المعاملات المالية (<?= count($recent_transactions) ?>)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="text-right p-3">التاريخ</th>
                            <th class="text-right p-3">النوع</th>
                            <th class="text-right p-3">الفئة</th>
                            <th class="text-right p-3">الوصف</th>
                            <th class="text-right p-3">المبلغ</th>
                            <th class="text-right p-3">الحالة</th>
                            <th class="text-right p-3">المنشئ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php if (empty($recent_transactions)): ?>
                        <tr>
                            <td colspan="7" class="p-8 text-center text-gray-500">لا توجد معاملات مالية للفترة المحددة</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($recent_transactions as $trans): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="p-3"><?= date('Y-m-d', strtotime($trans['transaction_date'])) ?></td>
                                <td class="p-3">
                                    <span class="px-2 py-1 rounded text-xs font-semibold <?= $trans['type'] == 'إيراد' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= htmlspecialchars($trans['type']) ?>
                                    </span>
                                </td>
                                <td class="p-3"><?= htmlspecialchars($trans['category']) ?></td>
                                <td class="p-3 text-xs"><?= htmlspecialchars($trans['description']) ?></td>
                                <td class="p-3 font-semibold <?= $trans['type'] == 'إيراد' ? 'text-green-600' : 'text-red-600' ?>">
                                    <?= number_format($trans['amount'], 2) ?> <?= htmlspecialchars($trans['currency_symbol']) ?>
                                </td>
                                <td class="p-3">
                                    <span class="px-2 py-1 rounded text-xs font-semibold <?= $trans['status'] == 'معتمد' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                        <?= htmlspecialchars($trans['status']) ?>
                                    </span>
                                </td>
                                <td class="p-3 text-xs"><?= htmlspecialchars($trans['created_by_name']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- تاريخ وتوقيت الطباعة -->
        <div class="mt-8 text-center text-gray-500 text-sm">
            📅 تاريخ الطباعة: <?= date('Y-m-d H:i:s') ?> | 🏛️ بلدية تكريت - عكار - لبنان
        </div>
    </div>

    <script>
        function toggleCustomDates() {
            const period = document.getElementById('period').value;
            const customDates = document.getElementById('custom_dates');
            customDates.style.display = (period === 'custom') ? 'contents' : 'none';
        }
    </script>
</body>
</html>

