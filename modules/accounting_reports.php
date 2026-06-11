<?php
// modules/accounting_reports.php

require_once '../includes/auth.php';
require_once '../config/database.php';

$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES 'utf8mb4'");
header('Content-Type: text/html; charset=utf-8');

// Filters
$f_year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$f_month = isset($_GET['month']) ? $_GET['month'] : date('m'); // allow empty
$f_currency = !empty($_GET['currency']) ? intval($_GET['currency']) : null;
$f_committee = !empty($_GET['committee']) ? intval($_GET['committee']) : null;
$f_project = !empty($_GET['project']) ? intval($_GET['project']) : null;
$f_cashbox = !empty($_GET['cashbox']) ? intval($_GET['cashbox']) : null;

// Base Where Clause for financial_transactions
$where = ["ft.status != 'ملغى'"];
$params = [];

if ($f_year > 0) {
    $where[] = "YEAR(ft.transaction_date) = ?";
    $params[] = $f_year;
}
if ($f_month !== '' && intval($f_month) > 0) {
    $where[] = "MONTH(ft.transaction_date) = ?";
    $params[] = intval($f_month);
}
if ($f_currency) {
    $where[] = "ft.currency_id = ?";
    $params[] = $f_currency;
}
if ($f_committee) {
    $where[] = "ft.committee_id = ?";
    $params[] = $f_committee;
}
if ($f_project) {
    $where[] = "ft.project_id = ?";
    $params[] = $f_project;
}
if ($f_cashbox) {
    $where[] = "ft.cashbox_id = ?";
    $params[] = $f_cashbox;
}

$whereSql = "WHERE " . implode(' AND ', $where);

// Helper function for visual warnings
function getProgressColor($percent, $threshold) {
    if ($percent >= 100) return 'bg-red-800 text-white';
    if ($percent >= $threshold) return 'bg-red-500 text-white';
    if ($percent >= 70) return 'bg-yellow-400 text-gray-800';
    return 'bg-green-500 text-white';
}
function getProgressText($percent, $threshold) {
    if ($percent >= 100) return 'text-red-800 font-bold';
    if ($percent >= $threshold) return 'text-red-600 font-bold';
    if ($percent >= 70) return 'text-yellow-600 font-bold';
    return 'text-green-600 font-bold';
}

// Data Fetching

// 1. Period Summary
$stmt = $db->prepare("
    SELECT c.currency_name, c.currency_symbol, 
           SUM(CASE WHEN ft.type='إيراد' THEN ft.amount ELSE 0 END) as total_income,
           SUM(CASE WHEN ft.type='مصروف' THEN ft.amount ELSE 0 END) as total_expense,
           COUNT(CASE WHEN ft.type='إيراد' THEN 1 END) as income_count,
           COUNT(CASE WHEN ft.type='مصروف' THEN 1 END) as expense_count
    FROM financial_transactions ft
    JOIN currencies c ON ft.currency_id = c.id
    $whereSql
    GROUP BY c.id
");
$stmt->execute($params);
$period_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Cashbox Report
// Since historical balances require ledger reconstruction, we show current balance and period net.
$stmt = $db->prepare("
    SELECT cb.name, c.currency_symbol, cb.current_balance,
           COALESCE(SUM(CASE WHEN ft.type='إيراد' THEN ft.amount ELSE 0 END), 0) as period_income,
           COALESCE(SUM(CASE WHEN ft.type='مصروف' THEN ft.amount ELSE 0 END), 0) as period_expense
    FROM accounting_cashboxes cb
    JOIN currencies c ON cb.currency_id = c.id
    LEFT JOIN financial_transactions ft ON cb.id = ft.cashbox_id 
         AND " . str_replace("ft.", "ft.", implode(" AND ", $where)) . "
    " . ($f_cashbox ? "WHERE cb.id = " . intval($f_cashbox) : "") . "
    " . ($f_currency ? ($f_cashbox ? " AND " : "WHERE ") . "cb.currency_id = " . intval($f_currency) : "") . "
    GROUP BY cb.id
");
// We need to inject the params safely for the LEFT JOIN conditions
// Alternatively, just filter financial_transactions inside a subquery or join with all params.
// Let's use a simpler approach: Calculate period totals per cashbox from financial_transactions directly, then join with cashboxes.
$stmt = $db->prepare("
    SELECT cb.name, c.currency_symbol, cb.current_balance,
           COALESCE(pt.period_income, 0) as period_income,
           COALESCE(pt.period_expense, 0) as period_expense
    FROM accounting_cashboxes cb
    JOIN currencies c ON cb.currency_id = c.id
    LEFT JOIN (
        SELECT cashbox_id, 
               SUM(CASE WHEN type='إيراد' THEN amount ELSE 0 END) as period_income,
               SUM(CASE WHEN type='مصروف' THEN amount ELSE 0 END) as period_expense
        FROM financial_transactions ft
        $whereSql
        GROUP BY cashbox_id
    ) pt ON cb.id = pt.cashbox_id
    WHERE 1=1
    " . ($f_cashbox ? " AND cb.id = " . intval($f_cashbox) : "") . "
    " . ($f_currency ? " AND cb.currency_id = " . intval($f_currency) : "") . "
");
$stmt->execute($params);
$cashboxes_report = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Committee Spending
$committee_where = [];
$c_params = [];
if ($f_year > 0) { $committee_where[] = "b.fiscal_year = ?"; $c_params[] = $f_year; }
if ($f_currency) { $committee_where[] = "b.currency_id = ?"; $c_params[] = $f_currency; }
if ($f_committee) { $committee_where[] = "b.committee_id = ?"; $c_params[] = $f_committee; }
$c_whereSql = count($committee_where) > 0 ? "WHERE " . implode(' AND ', $committee_where) : "";

$stmt = $db->prepare("
    SELECT b.id, b.title, mc.committee_name, c.currency_symbol, b.total_allocated, b.fiscal_year,
           (SELECT COALESCE(SUM(amount), 0) FROM financial_transactions ft WHERE ft.type='مصروف' AND ft.committee_id=b.committee_id AND ft.currency_id=b.currency_id AND ft.status != 'ملغى' AND YEAR(ft.transaction_date)=b.fiscal_year) as total_spent
    FROM accounting_committee_budgets b
    JOIN municipal_committees mc ON b.committee_id = mc.id
    JOIN currencies c ON b.currency_id = c.id
    $c_whereSql
");
$stmt->execute($c_params);
$committee_spending = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Budget vs Actual (Items)
$stmt = $db->prepare("
    SELECT i.item_name, b.title as budget_title, mc.committee_name, c.currency_symbol, i.allocated_amount, i.warning_threshold_percent,
           (SELECT COALESCE(SUM(amount), 0) FROM financial_transactions ft WHERE ft.committee_budget_item_id = i.id AND ft.type='مصروف' AND ft.status != 'ملغى') as total_spent
    FROM accounting_committee_budget_items i
    JOIN accounting_committee_budgets b ON i.committee_budget_id = b.id
    JOIN municipal_committees mc ON b.committee_id = mc.id
    JOIN currencies c ON b.currency_id = c.id
    $c_whereSql
");
$stmt->execute($c_params);
$budget_actual = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Income by Category
$stmt = $db->prepare("
    SELECT ft.category, c.currency_symbol, SUM(ft.amount) as total
    FROM financial_transactions ft
    JOIN currencies c ON ft.currency_id = c.id
    $whereSql AND ft.type='إيراد'
    GROUP BY ft.category, c.id
    ORDER BY total DESC
");
$stmt->execute($params);
$income_cat = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Expense by Category
$stmt = $db->prepare("
    SELECT ft.category, c.currency_symbol, SUM(ft.amount) as total
    FROM financial_transactions ft
    JOIN currencies c ON ft.currency_id = c.id
    $whereSql AND ft.type='مصروف'
    GROUP BY ft.category, c.id
    ORDER BY total DESC
");
$stmt->execute($params);
$expense_cat = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 7. Recent Transactions
$stmt = $db->prepare("
    SELECT ft.*, c.currency_symbol, mc.committee_name, p.project_name, cb.name as cashbox_name, r.receipt_number, v.voucher_number
    FROM financial_transactions ft
    LEFT JOIN currencies c ON ft.currency_id = c.id
    LEFT JOIN municipal_committees mc ON ft.committee_id = mc.id
    LEFT JOIN projects p ON ft.project_id = p.id
    LEFT JOIN accounting_cashboxes cb ON ft.cashbox_id = cb.id
    LEFT JOIN accounting_receipts r ON ft.receipt_id = r.id
    LEFT JOIN accounting_payment_vouchers v ON ft.voucher_id = v.id
    $whereSql
    ORDER BY ft.transaction_date DESC, ft.id DESC
    LIMIT 30
");
$stmt->execute($params);
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch dropdown lists
$currencies = $db->query("SELECT id, currency_name, currency_symbol FROM currencies WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);
$cashboxes = $db->query("SELECT id, name FROM accounting_cashboxes WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);
$committees = $db->query("SELECT id, committee_name FROM municipal_committees WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);
$projects = [];
try { $projects = $db->query("SELECT id, project_name FROM projects")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>التقارير المالية</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; padding: 0; }
            .shadow { box-shadow: none !important; border: 1px solid #e5e7eb; }
        }
    </style>
</head>
<body class="bg-slate-100 p-6 text-slate-800">
    <div class="max-w-7xl mx-auto">
        
        <div class="flex justify-between items-center mb-6 no-print">
            <h1 class="text-3xl font-bold text-slate-800">التقارير المالية</h1>
            <div class="space-x-2 space-x-reverse">
                <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">🖨️ طباعة التقرير</button>
                <a href="../comprehensive_dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">العودة للوحة التحكم</a>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white p-6 rounded shadow mb-8 no-print">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium mb-1">السنة</label>
                    <input type="number" name="year" value="<?= htmlspecialchars($f_year) ?>" class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">الشهر</label>
                    <select name="month" class="w-full p-2 border rounded">
                        <option value="">-- كل الأشهر --</option>
                        <?php for($m=1; $m<=12; $m++): ?>
                            <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" <?= ($f_month == str_pad($m, 2, '0', STR_PAD_LEFT)) ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?> (<?= $m ?>)</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">العملة</label>
                    <select name="currency" class="w-full p-2 border rounded">
                        <option value="">-- كل العملات --</option>
                        <?php foreach($currencies as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($f_currency == $c['id']) ? 'selected' : '' ?>><?= $c['currency_name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">الصندوق</label>
                    <select name="cashbox" class="w-full p-2 border rounded">
                        <option value="">-- كل الصناديق --</option>
                        <?php foreach($cashboxes as $cb): ?>
                            <option value="<?= $cb['id'] ?>" <?= ($f_cashbox == $cb['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cb['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">اللجنة</label>
                    <select name="committee" class="w-full p-2 border rounded">
                        <option value="">-- كل اللجان --</option>
                        <?php foreach($committees as $com): ?>
                            <option value="<?= $com['id'] ?>" <?= ($f_committee == $com['id']) ? 'selected' : '' ?>><?= htmlspecialchars($com['committee_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="w-full bg-indigo-600 text-white p-2 rounded hover:bg-indigo-700 font-bold">تطبيق الفلاتر</button>
                </div>
            </form>
        </div>

        <div class="mb-8 print:block hidden">
            <h2 class="text-xl font-bold text-center border-b pb-2 mb-4">التقرير المالي لبلدية تكريت</h2>
            <p class="text-center text-sm text-gray-600 mb-4">السنة: <?= $f_year ?: 'الكل' ?> | الشهر: <?= $f_month ?: 'الكل' ?></p>
        </div>

        <!-- 1. Period Summary -->
        <div class="mb-8">
            <h2 class="text-xl font-bold mb-4 border-r-4 border-indigo-600 pr-2">الملخص المالي (للفترة المحددة)</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach($period_summary as $sum): 
                    $net = $sum['total_income'] - $sum['total_expense'];
                ?>
                <div class="bg-white p-6 rounded shadow border-t-4 border-indigo-500">
                    <h3 class="font-bold text-lg mb-4"><?= htmlspecialchars($sum['currency_name']) ?></h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">المداخيل (<?= $sum['income_count'] ?> حركة)</p>
                            <p class="font-bold text-green-600 text-xl"><?= number_format($sum['total_income'], 2) ?> <?= $sum['currency_symbol'] ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">المصاريف (<?= $sum['expense_count'] ?> حركة)</p>
                            <p class="font-bold text-red-600 text-xl"><?= number_format($sum['total_expense'], 2) ?> <?= $sum['currency_symbol'] ?></p>
                        </div>
                        <div class="col-span-2 pt-2 border-t">
                            <p class="text-sm text-gray-500">الصافي للفترة</p>
                            <p class="font-bold text-2xl <?= $net >= 0 ? 'text-indigo-600' : 'text-red-600' ?>" dir="ltr">
                                <?= number_format($net, 2) ?> <?= $sum['currency_symbol'] ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(empty($period_summary)): ?>
                    <div class="col-span-2 bg-gray-50 p-4 text-center text-gray-500 rounded">لا توجد حركات مالية في هذه الفترة.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 2. Cashbox Report -->
        <div class="mb-8">
            <h2 class="text-xl font-bold mb-4 border-r-4 border-blue-500 pr-2">تقرير الصناديق</h2>
            <div class="bg-white rounded shadow overflow-x-auto">
                <table class="w-full text-right text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="p-3">الصندوق</th>
                            <th class="p-3">مداخيل الفترة</th>
                            <th class="p-3">مصاريف الفترة</th>
                            <th class="p-3">صافي الفترة</th>
                            <th class="p-3">الرصيد الفعلي الحالي</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($cashboxes_report as $cb): 
                            $period_net = $cb['period_income'] - $cb['period_expense'];
                        ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="p-3 font-bold"><?= htmlspecialchars($cb['name']) ?></td>
                            <td class="p-3 text-green-600"><?= number_format($cb['period_income'], 2) ?></td>
                            <td class="p-3 text-red-600"><?= number_format($cb['period_expense'], 2) ?></td>
                            <td class="p-3 font-bold <?= $period_net >= 0 ? 'text-blue-600' : 'text-red-600' ?>" dir="ltr"><?= number_format($period_net, 2) ?></td>
                            <td class="p-3 font-bold text-lg bg-indigo-50 border-r" dir="ltr"><?= number_format($cb['current_balance'], 2) ?> <?= $cb['currency_symbol'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- 5. Income by Category -->
            <div>
                <h2 class="text-xl font-bold mb-4 border-r-4 border-green-500 pr-2">المداخيل حسب التصنيف</h2>
                <div class="bg-white rounded shadow overflow-x-auto">
                    <table class="w-full text-right text-sm">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="p-3">التصنيف</th>
                                <th class="p-3">الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($income_cat as $cat): ?>
                            <tr class="border-b">
                                <td class="p-3"><?= htmlspecialchars($cat['category'] ?: 'غير محدد') ?></td>
                                <td class="p-3 font-bold text-green-600"><?= number_format($cat['total'], 2) ?> <?= $cat['currency_symbol'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 6. Expense by Category -->
            <div>
                <h2 class="text-xl font-bold mb-4 border-r-4 border-red-500 pr-2">المصاريف حسب التصنيف</h2>
                <div class="bg-white rounded shadow overflow-x-auto">
                    <table class="w-full text-right text-sm">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="p-3">التصنيف</th>
                                <th class="p-3">الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($expense_cat as $cat): ?>
                            <tr class="border-b">
                                <td class="p-3"><?= htmlspecialchars($cat['category'] ?: 'غير محدد') ?></td>
                                <td class="p-3 font-bold text-red-600"><?= number_format($cat['total'], 2) ?> <?= $cat['currency_symbol'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 3. Committee Spending -->
        <div class="mb-8">
            <h2 class="text-xl font-bold mb-4 border-r-4 border-purple-500 pr-2">تقرير صرف اللجان السنوي</h2>
            <div class="bg-white rounded shadow overflow-x-auto">
                <table class="w-full text-right text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="p-3">السنة</th>
                            <th class="p-3">اللجنة / الميزانية</th>
                            <th class="p-3">المخصص</th>
                            <th class="p-3">المصروف</th>
                            <th class="p-3">المتبقي</th>
                            <th class="p-3">الاستهلاك</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($committee_spending as $cb): 
                            $remaining = $cb['total_allocated'] - $cb['total_spent'];
                            $percent = $cb['total_allocated'] > 0 ? ($cb['total_spent'] / $cb['total_allocated']) * 100 : 0;
                            $p_color = getProgressColor($percent, 90);
                            $t_color = getProgressText($percent, 90);
                        ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="p-3 font-bold"><?= $cb['fiscal_year'] ?></td>
                            <td class="p-3">
                                <div><?= htmlspecialchars($cb['committee_name']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($cb['title']) ?></div>
                            </td>
                            <td class="p-3 text-indigo-600 font-bold"><?= number_format($cb['total_allocated'], 2) ?> <?= $cb['currency_symbol'] ?></td>
                            <td class="p-3 text-red-600"><?= number_format($cb['total_spent'], 2) ?></td>
                            <td class="p-3 text-green-600 font-bold"><?= number_format($remaining, 2) ?></td>
                            <td class="p-3">
                                <span class="<?= $p_color ?> px-2 py-1 rounded text-xs font-bold"><?= number_format($percent, 1) ?>%</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($committee_spending)): ?>
                            <tr><td colspan="6" class="p-4 text-center text-gray-500">لا يوجد ميزانيات لجان لهذه السنة/الفلاتر.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4. Budget vs Actual -->
        <div class="mb-8">
            <h2 class="text-xl font-bold mb-4 border-r-4 border-orange-500 pr-2">موازنة اللجان مقارنة بالصرف الفعلي (لكل بند)</h2>
            <div class="bg-white rounded shadow overflow-x-auto">
                <table class="w-full text-right text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="p-3">اللجنة / الميزانية</th>
                            <th class="p-3">البند</th>
                            <th class="p-3">المخصص</th>
                            <th class="p-3">المصروف الفعلي</th>
                            <th class="p-3">المتبقي</th>
                            <th class="p-3">مؤشر الاستهلاك</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($budget_actual as $ba): 
                            $rem = $ba['allocated_amount'] - $ba['total_spent'];
                            $pct = $ba['allocated_amount'] > 0 ? ($ba['total_spent'] / $ba['allocated_amount']) * 100 : 0;
                        ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="p-3 text-gray-600"><?= htmlspecialchars($ba['committee_name']) ?></td>
                            <td class="p-3 font-bold"><?= htmlspecialchars($ba['item_name']) ?></td>
                            <td class="p-3 text-indigo-600 font-bold"><?= number_format($ba['allocated_amount'], 2) ?> <?= $ba['currency_symbol'] ?></td>
                            <td class="p-3 text-red-600"><?= number_format($ba['total_spent'], 2) ?></td>
                            <td class="p-3 font-bold <?= $rem >= 0 ? 'text-green-600' : 'text-red-600' ?>"><?= number_format($rem, 2) ?></td>
                            <td class="p-3 w-48">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs <?= getProgressText($pct, $ba['warning_threshold_percent']) ?>"><?= number_format($pct, 1) ?>%</span>
                                    <?php if($pct >= $ba['warning_threshold_percent']): ?>
                                        <span class="text-xs text-red-600">⚠️ تجاوز التحذير</span>
                                    <?php endif; ?>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="<?= getProgressColor($pct, $ba['warning_threshold_percent']) ?> h-2 rounded-full" style="width: <?= min($pct, 100) ?>%"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 7. Recent Transactions -->
        <div class="mb-8">
            <h2 class="text-xl font-bold mb-4 border-r-4 border-gray-600 pr-2">آخر الحركات المالية (آخر 30 حركة للفترة)</h2>
            <div class="bg-white rounded shadow overflow-x-auto">
                <table class="w-full text-right text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="p-2">التاريخ</th>
                            <th class="p-2">النوع</th>
                            <th class="p-2">الصندوق</th>
                            <th class="p-2">التصنيف</th>
                            <th class="p-2">المبلغ</th>
                            <th class="p-2">مستند</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent as $r): ?>
                        <tr class="border-b">
                            <td class="p-2"><?= date('Y-m-d', strtotime($r['transaction_date'])) ?></td>
                            <td class="p-2">
                                <?php if ($r['type'] == 'إيراد'): ?>
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">مدخول</span>
                                <?php else: ?>
                                    <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs">مصروف</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-2 text-gray-600 text-xs"><?= htmlspecialchars($r['cashbox_name']) ?></td>
                            <td class="p-2"><?= htmlspecialchars($r['category']) ?></td>
                            <td class="p-2 font-bold" dir="ltr"><?= number_format($r['amount'], 2) ?> <?= $r['currency_symbol'] ?></td>
                            <td class="p-2 text-xs text-blue-600"><?= htmlspecialchars($r['receipt_number'] ?: $r['voucher_number']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($recent)): ?>
                            <tr><td colspan="6" class="p-4 text-center text-gray-500">لا يوجد حركات</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</body>
</html>
