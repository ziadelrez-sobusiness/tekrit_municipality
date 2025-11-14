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

// جلب جميع الميزانيات مع تفاصيل اللجان والبنود
$stmt = $db->query("
    SELECT 
        b.id as budget_id,
        b.budget_code,
        b.name as budget_name,
        b.fiscal_year,
        b.start_date,
        b.end_date,
        b.total_amount,
        b.status,
        b.created_at,
        mc.id as committee_id,
        mc.committee_name,
        c.currency_code,
        c.currency_symbol,
        c.currency_name,
        COUNT(DISTINCT bi.id) as items_count,
        COALESCE(SUM(bi.allocated_amount), 0) as total_allocated,
        COALESCE(SUM(bi.spent_amount), 0) as total_spent,
        COALESCE(SUM(bi.remaining_amount), 0) as total_remaining
    FROM budgets b
    LEFT JOIN municipal_committees mc ON b.committee_id = mc.id
    LEFT JOIN currencies c ON b.currency_id = c.id
    LEFT JOIN budget_items bi ON b.id = bi.budget_id
    GROUP BY b.id, mc.id, c.id
    ORDER BY mc.committee_name, b.fiscal_year DESC, b.created_at DESC
");
$budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// تجميع البيانات حسب اللجان
$committees_data = [];
$grand_totals = [
    'budgets_count' => 0,
    'items_count' => 0,
    'allocated' => [],
    'spent' => [],
    'remaining' => []
];

foreach ($budgets as $budget) {
    $committee_name = $budget['committee_name'] ?: 'بدون لجنة';
    $currency_code = $budget['currency_code'];
    
    if (!isset($committees_data[$committee_name])) {
        $committees_data[$committee_name] = [
            'committee_id' => $budget['committee_id'],
            'budgets' => [],
            'totals' => [
                'budgets_count' => 0,
                'items_count' => 0,
                'allocated' => [],
                'spent' => [],
                'remaining' => []
            ]
        ];
    }
    
    $committees_data[$committee_name]['budgets'][] = $budget;
    $committees_data[$committee_name]['totals']['budgets_count']++;
    $committees_data[$committee_name]['totals']['items_count'] += $budget['items_count'];
    
    // تجميع حسب العملة
    if (!isset($committees_data[$committee_name]['totals']['allocated'][$currency_code])) {
        $committees_data[$committee_name]['totals']['allocated'][$currency_code] = [
            'amount' => 0,
            'symbol' => $budget['currency_symbol'],
            'name' => $budget['currency_name']
        ];
        $committees_data[$committee_name]['totals']['spent'][$currency_code] = [
            'amount' => 0,
            'symbol' => $budget['currency_symbol'],
            'name' => $budget['currency_name']
        ];
        $committees_data[$committee_name]['totals']['remaining'][$currency_code] = [
            'amount' => 0,
            'symbol' => $budget['currency_symbol'],
            'name' => $budget['currency_name']
        ];
    }
    
    $committees_data[$committee_name]['totals']['allocated'][$currency_code]['amount'] += $budget['total_allocated'];
    $committees_data[$committee_name]['totals']['spent'][$currency_code]['amount'] += $budget['total_spent'];
    $committees_data[$committee_name]['totals']['remaining'][$currency_code]['amount'] += $budget['total_remaining'];
    
    // الإجمالي العام
    $grand_totals['budgets_count']++;
    $grand_totals['items_count'] += $budget['items_count'];
    
    if (!isset($grand_totals['allocated'][$currency_code])) {
        $grand_totals['allocated'][$currency_code] = [
            'amount' => 0,
            'symbol' => $budget['currency_symbol'],
            'name' => $budget['currency_name']
        ];
        $grand_totals['spent'][$currency_code] = [
            'amount' => 0,
            'symbol' => $budget['currency_symbol'],
            'name' => $budget['currency_name']
        ];
        $grand_totals['remaining'][$currency_code] = [
            'amount' => 0,
            'symbol' => $budget['currency_symbol'],
            'name' => $budget['currency_name']
        ];
    }
    
    $grand_totals['allocated'][$currency_code]['amount'] += $budget['total_allocated'];
    $grand_totals['spent'][$currency_code]['amount'] += $budget['total_spent'];
    $grand_totals['remaining'][$currency_code]['amount'] += $budget['total_remaining'];
}

// جلب بنود كل ميزانية للتفاصيل
$budget_items = [];
$stmt = $db->query("
    SELECT 
        bi.*,
        c.currency_symbol,
        c.currency_code
    FROM budget_items bi
    LEFT JOIN currencies c ON bi.currency_id = c.id
    ORDER BY bi.budget_id, bi.item_code
");
$all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($all_items as $item) {
    $budget_items[$item['budget_id']][] = $item;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير الميزانيات الشامل - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            .page-break {
                page-break-before: always;
            }
        }
        
        @page {
            size: A4 landscape;
            margin: 1cm;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- أزرار التحكم (لا تطبع) -->
    <div class="no-print fixed top-4 left-4 z-50 flex gap-2">
        <button onclick="window.print()" 
                class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 shadow-lg flex items-center gap-2">
            🖨️ طباعة التقرير
        </button>
        <a href="budgets.php" 
           class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 shadow-lg flex items-center gap-2">
            ← العودة
        </a>
    </div>

    <div class="container mx-auto p-8">
        <!-- رأس التقرير -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
            <div class="text-center border-b-4 border-blue-600 pb-6 mb-6">
                <h1 class="text-4xl font-bold text-blue-900 mb-2">🏛️ بلدية تكريت - عكار</h1>
                <h2 class="text-3xl font-bold text-gray-800 mb-4">تقرير الميزانيات الشامل</h2>
                <div class="flex justify-center gap-8 text-sm text-gray-600">
                    <div>📅 تاريخ التقرير: <strong><?= date('Y-m-d') ?></strong></div>
                    <div>🕐 الوقت: <strong><?= date('H:i') ?></strong></div>
                    <div>👤 المسؤول: <strong><?= htmlspecialchars($user['full_name']) ?></strong></div>
                </div>
            </div>

            <!-- الإحصائيات العامة -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white p-6 rounded-lg">
                    <div class="text-sm opacity-90">عدد الميزانيات</div>
                    <div class="text-3xl font-bold"><?= $grand_totals['budgets_count'] ?></div>
                </div>
                <div class="bg-gradient-to-br from-purple-500 to-purple-600 text-white p-6 rounded-lg">
                    <div class="text-sm opacity-90">عدد اللجان</div>
                    <div class="text-3xl font-bold"><?= count($committees_data) ?></div>
                </div>
                <div class="bg-gradient-to-br from-green-500 to-green-600 text-white p-6 rounded-lg">
                    <div class="text-sm opacity-90">إجمالي البنود</div>
                    <div class="text-3xl font-bold"><?= $grand_totals['items_count'] ?></div>
                </div>
                <div class="bg-gradient-to-br from-orange-500 to-orange-600 text-white p-6 rounded-lg">
                    <div class="text-sm opacity-90">نسبة الإنفاق</div>
                    <div class="text-3xl font-bold">
                        <?php
                        $total_allocated = 0;
                        $total_spent = 0;
                        foreach ($grand_totals['allocated'] as $curr) $total_allocated += $curr['amount'];
                        foreach ($grand_totals['spent'] as $curr) $total_spent += $curr['amount'];
                        $percentage = $total_allocated > 0 ? ($total_spent / $total_allocated) * 100 : 0;
                        echo number_format($percentage, 1);
                        ?>%
                    </div>
                </div>
            </div>

            <!-- الإجمالي العام حسب العملة -->
            <div class="bg-gray-50 p-6 rounded-lg">
                <h3 class="text-xl font-bold mb-4 text-gray-800">💰 الإجمالي العام حسب العملة</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <?php foreach ($grand_totals['allocated'] as $currency_code => $data): ?>
                    <div class="bg-white p-4 rounded-lg border-r-4 border-blue-500">
                        <div class="text-sm text-gray-600 mb-2"><?= htmlspecialchars($data['name']) ?></div>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span>💰 المخصص:</span>
                                <strong class="text-blue-600"><?= number_format($data['amount'], 2) ?> <?= $data['symbol'] ?></strong>
                            </div>
                            <div class="flex justify-between">
                                <span>💸 المصروف:</span>
                                <strong class="text-red-600"><?= number_format($grand_totals['spent'][$currency_code]['amount'], 2) ?> <?= $data['symbol'] ?></strong>
                            </div>
                            <div class="flex justify-between border-t pt-1">
                                <span>✅ المتبقي:</span>
                                <strong class="text-green-600"><?= number_format($grand_totals['remaining'][$currency_code]['amount'], 2) ?> <?= $data['symbol'] ?></strong>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- تفاصيل كل لجنة -->
        <?php foreach ($committees_data as $committee_name => $committee_data): ?>
        <div class="bg-white rounded-lg shadow-lg p-8 mb-8 page-break">
            <!-- عنوان اللجنة -->
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-6 rounded-lg mb-6">
                <h2 class="text-2xl font-bold mb-2">🏛️ <?= htmlspecialchars($committee_name) ?></h2>
                <div class="flex gap-6 text-sm opacity-90">
                    <div>📊 الميزانيات: <strong><?= $committee_data['totals']['budgets_count'] ?></strong></div>
                    <div>📋 البنود: <strong><?= $committee_data['totals']['items_count'] ?></strong></div>
                </div>
            </div>

            <!-- إحصائيات اللجنة -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <?php foreach ($committee_data['totals']['allocated'] as $currency_code => $data): ?>
                <div class="bg-gradient-to-br from-gray-50 to-gray-100 p-4 rounded-lg border-r-4 border-indigo-500">
                    <div class="text-sm text-gray-600 mb-2 font-semibold"><?= htmlspecialchars($data['name']) ?></div>
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <span>💰 المخصص:</span>
                            <strong class="text-blue-600"><?= number_format($data['amount'], 2) ?> <?= $data['symbol'] ?></strong>
                        </div>
                        <div class="flex justify-between">
                            <span>💸 المصروف:</span>
                            <strong class="text-red-600"><?= number_format($committee_data['totals']['spent'][$currency_code]['amount'], 2) ?> <?= $data['symbol'] ?></strong>
                        </div>
                        <div class="flex justify-between border-t pt-1">
                            <span>✅ المتبقي:</span>
                            <strong class="text-green-600"><?= number_format($committee_data['totals']['remaining'][$currency_code]['amount'], 2) ?> <?= $data['symbol'] ?></strong>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ميزانيات اللجنة -->
            <?php foreach ($committee_data['budgets'] as $budget): ?>
            <div class="mb-6 border rounded-lg overflow-hidden">
                <!-- رأس الميزانية -->
                <div class="bg-gray-100 p-4 border-b">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-lg font-bold text-gray-800"><?= htmlspecialchars($budget['budget_code']) ?></h3>
                            <p class="text-sm text-gray-600"><?= htmlspecialchars($budget['budget_name']) ?></p>
                        </div>
                        <div class="text-left">
                            <div class="text-sm text-gray-600">السنة المالية: <strong><?= $budget['fiscal_year'] ?></strong></div>
                            <div class="text-sm">
                                <?php
                                $statusColors = [
                                    'مسودة' => 'bg-yellow-100 text-yellow-800',
                                    'معتمد' => 'bg-green-100 text-green-800',
                                    'مغلق' => 'bg-gray-100 text-gray-800'
                                ];
                                $statusClass = $statusColors[$budget['status']] ?? 'bg-gray-100';
                                ?>
                                <span class="px-3 py-1 rounded text-xs font-semibold <?= $statusClass ?>">
                                    <?= htmlspecialchars($budget['status']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 flex gap-6 text-sm">
                        <div>📅 من: <strong><?= $budget['start_date'] ?></strong></div>
                        <div>📅 إلى: <strong><?= $budget['end_date'] ?></strong></div>
                        <div>💰 الإجمالي: <strong><?= number_format($budget['total_amount'], 2) ?> <?= $budget['currency_symbol'] ?></strong></div>
                        <div>📊 البنود: <strong><?= $budget['items_count'] ?></strong></div>
                    </div>
                </div>

                <!-- بنود الميزانية -->
                <?php if (isset($budget_items[$budget['budget_id']]) && !empty($budget_items[$budget['budget_id']])): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="text-right p-3 font-semibold">الرمز</th>
                                <th class="text-right p-3 font-semibold">اسم البند</th>
                                <th class="text-right p-3 font-semibold">النوع</th>
                                <th class="text-right p-3 font-semibold">التصنيف</th>
                                <th class="text-right p-3 font-semibold">المخصص</th>
                                <th class="text-right p-3 font-semibold">المصروف</th>
                                <th class="text-right p-3 font-semibold">المتبقي</th>
                                <th class="text-center p-3 font-semibold">النسبة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($budget_items[$budget['budget_id']] as $item): 
                                $percentage = $item['allocated_amount'] > 0 ? ($item['spent_amount'] / $item['allocated_amount']) * 100 : 0;
                                $progressColor = $percentage < 50 ? 'bg-green-500' : ($percentage < 80 ? 'bg-yellow-500' : 'bg-red-500');
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 font-mono text-xs text-blue-600"><?= htmlspecialchars($item['item_code']) ?></td>
                                <td class="p-3 font-semibold"><?= htmlspecialchars($item['name']) ?></td>
                                <td class="p-3">
                                    <span class="px-2 py-1 rounded text-xs <?= $item['item_type'] == 'إيراد' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= htmlspecialchars($item['item_type']) ?>
                                    </span>
                                </td>
                                <td class="p-3 text-xs"><?= htmlspecialchars($item['category']) ?></td>
                                <td class="p-3 font-semibold text-blue-600"><?= number_format($item['allocated_amount'], 2) ?> <?= $item['currency_symbol'] ?></td>
                                <td class="p-3 font-semibold text-red-600"><?= number_format($item['spent_amount'], 2) ?> <?= $item['currency_symbol'] ?></td>
                                <td class="p-3 font-semibold text-green-600"><?= number_format($item['remaining_amount'], 2) ?> <?= $item['currency_symbol'] ?></td>
                                <td class="p-3">
                                    <div class="flex items-center justify-center gap-2">
                                        <div class="w-20 bg-gray-200 rounded-full h-2">
                                            <div class="<?= $progressColor ?> h-2 rounded-full" style="width: <?= min($percentage, 100) ?>%"></div>
                                        </div>
                                        <span class="text-xs font-bold"><?= number_format($percentage, 1) ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-gray-100 font-bold">
                            <tr>
                                <td colspan="4" class="p-3 text-left">الإجمالي:</td>
                                <td class="p-3 text-blue-600"><?= number_format($budget['total_allocated'], 2) ?> <?= $budget['currency_symbol'] ?></td>
                                <td class="p-3 text-red-600"><?= number_format($budget['total_spent'], 2) ?> <?= $budget['currency_symbol'] ?></td>
                                <td class="p-3 text-green-600"><?= number_format($budget['total_remaining'], 2) ?> <?= $budget['currency_symbol'] ?></td>
                                <td class="p-3 text-center">
                                    <?php
                                    $budget_percentage = $budget['total_allocated'] > 0 ? ($budget['total_spent'] / $budget['total_allocated']) * 100 : 0;
                                    echo number_format($budget_percentage, 1) . '%';
                                    ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php else: ?>
                <div class="p-6 text-center text-gray-500">
                    📭 لا توجد بنود لهذه الميزانية
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

        <!-- تذييل التقرير -->
        <div class="bg-white rounded-lg shadow-lg p-8 text-center">
            <p class="text-gray-600 mb-4">تم إنشاء هذا التقرير بواسطة نظام إدارة بلدية تكريت - عكار</p>
            <div class="text-sm text-gray-500">
                <div>📅 <?= date('Y-m-d H:i:s') ?></div>
                <div>👤 <?= htmlspecialchars($user['full_name']) ?></div>
            </div>
            
            <div class="mt-6 pt-6 border-t flex justify-around text-sm">
                <div>
                    <div class="text-gray-600 mb-2">توقيع المسؤول المالي</div>
                    <div class="border-t-2 border-gray-400 w-48 mx-auto mt-8"></div>
                </div>
                <div>
                    <div class="text-gray-600 mb-2">توقيع رئيس البلدية</div>
                    <div class="border-t-2 border-gray-400 w-48 mx-auto mt-8"></div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>


