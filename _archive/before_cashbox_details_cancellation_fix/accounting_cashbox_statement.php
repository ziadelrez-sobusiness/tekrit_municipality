<?php
// modules/accounting_cashbox_statement.php
if (file_exists(__DIR__ . '/../includes/csrf_middleware.php')) {
    require_once __DIR__ . '/../includes/csrf_middleware.php';
}
require_once '../includes/auth.php';
require_once '../config/database.php';
$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES 'utf8mb4'");
header('Content-Type: text/html; charset=utf-8');
$user = $auth->getUserInfo();
$message = ''; $error = '';

// ---- Fetch filter data ----
$cashboxes_list = $db->query("SELECT cb.id, cb.name, c.currency_symbol, c.currency_name FROM accounting_cashboxes cb JOIN currencies c ON cb.currency_id = c.id WHERE cb.is_active=1 ORDER BY cb.id")->fetchAll(PDO::FETCH_ASSOC);
$committees_list = $db->query("SELECT id, committee_name FROM municipal_committees WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);
$projects_list = [];
try { $projects_list = $db->query("SELECT id, project_name FROM projects LIMIT 200")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}
$categories_list = $db->query("SELECT id, name_ar, type FROM accounting_categories WHERE is_active=1 ORDER BY type, name_ar")->fetchAll(PDO::FETCH_ASSOC);

// ---- Filter values ----
$sel_cashbox_id = isset($_GET['cashbox_id']) ? intval($_GET['cashbox_id']) : ($cashboxes_list[0]['id'] ?? 0);
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date   = $_GET['to_date']   ?? date('Y-m-d');
$filter_type = $_GET['filter_type'] ?? 'all';
$filter_committee = !empty($_GET['filter_committee']) ? intval($_GET['filter_committee']) : 0;
$filter_project   = !empty($_GET['filter_project'])   ? intval($_GET['filter_project'])   : 0;
$filter_category  = $_GET['filter_category'] ?? '';

// Find selected cashbox details
$sel_cashbox = null;
foreach ($cashboxes_list as $cb) { if ($cb['id'] == $sel_cashbox_id) { $sel_cashbox = $cb; break; } }

$transactions = [];
$total_income = 0; $total_expense = 0; $current_balance = 0; $opening_balance = 0;

if ($sel_cashbox_id) {
    // Current live balance
    $stmt = $db->prepare("SELECT current_balance FROM accounting_cashboxes WHERE id = ?");
    $stmt->execute([$sel_cashbox_id]);
    $current_balance = floatval($stmt->fetchColumn());

    // Opening balance: current_balance minus all active movements after to_date
    $stmt = $db->prepare("SELECT COALESCE(SUM(CASE WHEN type='إيراد' THEN amount WHEN type='مصروف' THEN -amount ELSE 0 END),0) FROM financial_transactions WHERE cashbox_id=? AND transaction_date > ? AND status != 'ملغى'");
    $stmt->execute([$sel_cashbox_id, $to_date]);
    $after_period_net = floatval($stmt->fetchColumn());
    $balance_at_end_of_period = $current_balance - $after_period_net;

    // Period income/expense (active only)
    $stmt = $db->prepare("SELECT COALESCE(SUM(CASE WHEN type='إيراد' THEN amount ELSE 0 END),0), COALESCE(SUM(CASE WHEN type='مصروف' THEN amount ELSE 0 END),0) FROM financial_transactions WHERE cashbox_id=? AND transaction_date BETWEEN ? AND ? AND status != 'ملغى'");
    $stmt->execute([$sel_cashbox_id, $from_date, $to_date]);
    [$total_income, $total_expense] = $stmt->fetch(PDO::FETCH_NUM);
    $total_income = floatval($total_income); $total_expense = floatval($total_expense);

    // Opening balance = balance at end of period - net movement in period
    $opening_balance = $balance_at_end_of_period - ($total_income - $total_expense);

    // Transactions query
    $where = ["ft.cashbox_id = :cbid", "ft.transaction_date BETWEEN :fd AND :td"];
    $params = [':cbid' => $sel_cashbox_id, ':fd' => $from_date, ':td' => $to_date];
    if ($filter_type === 'income')  $where[] = "ft.type = 'إيراد'";
    if ($filter_type === 'expense') $where[] = "ft.type = 'مصروف'";
    if ($filter_committee) { $where[] = "ft.committee_id = :com"; $params[':com'] = $filter_committee; }
    if ($filter_project)   { $where[] = "ft.project_id = :proj"; $params[':proj'] = $filter_project; }
    if ($filter_category)  { $where[] = "ft.category = :cat"; $params[':cat'] = $filter_category; }

    $sql = "SELECT ft.*, 
            c.currency_symbol, c.currency_name,
            u.full_name as created_by_name,
            mc.committee_name,
            p.project_name,
            r.receipt_number, r.payer_name,
            v.voucher_number, v.payee_name,
            cu.full_name as cancelled_by_name
        FROM financial_transactions ft
        LEFT JOIN currencies c ON ft.currency_id = c.id
        LEFT JOIN users u ON ft.created_by = u.id
        LEFT JOIN municipal_committees mc ON ft.committee_id = mc.id
        LEFT JOIN projects p ON ft.project_id = p.id
        LEFT JOIN accounting_receipts r ON ft.receipt_id = r.id
        LEFT JOIN accounting_payment_vouchers v ON ft.voucher_id = v.id
        LEFT JOIN users cu ON ft.cancelled_by_user_id = cu.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY ft.transaction_date DESC, ft.id DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function pmLabel($pm) {
    $map = ['cash'=>'نقدي','bank_transfer'=>'تحويل بنكي','check'=>'شيك','other'=>'أخرى','نقد'=>'نقدي','شيك'=>'شيك','حوالة مصرفية'=>'تحويل بنكي'];
    return $map[$pm] ?? ($pm ?: 'غير محدد');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>كشف حركة الصندوق</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body { font-family: 'Cairo', sans-serif; }
@media print {
    .no-print { display: none !important; }
    body { background: white; padding: 10px; }
}
.cancelled-row { background-color: #fef2f2 !important; text-decoration: line-through; opacity: 0.7; }
</style>
</head>
<body class="bg-slate-100 p-4 text-slate-800">
<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-5 no-print">
        <h1 class="text-2xl font-bold text-slate-800">كشف حركة الصندوق</h1>
        <div class="flex gap-2">
            <button onclick="window.print()" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 text-sm">🖨️ طباعة</button>
            <a href="../comprehensive_dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 text-sm">العودة</a>
        </div>
    </div>

    <?php if ($message): ?><div class="bg-green-100 text-green-800 p-3 rounded mb-4 border border-green-200"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-100 text-red-800 p-3 rounded mb-4 border border-red-200"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Filters -->
    <div class="bg-white p-5 rounded shadow mb-5 no-print">
        <form method="GET" class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-bold mb-1">الصندوق <span class="text-red-500">*</span></label>
                <select name="cashbox_id" class="w-full p-2 border rounded text-sm" onchange="this.form.submit()">
                    <?php foreach($cashboxes_list as $cb): ?>
                        <option value="<?= $cb['id'] ?>" <?= $cb['id'] == $sel_cashbox_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cb['name']) ?> (<?= $cb['currency_symbol'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold mb-1">من تاريخ</label>
                <input type="date" name="from_date" value="<?= $from_date ?>" class="w-full p-2 border rounded text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold mb-1">إلى تاريخ</label>
                <input type="date" name="to_date" value="<?= $to_date ?>" class="w-full p-2 border rounded text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold mb-1">النوع</label>
                <select name="filter_type" class="w-full p-2 border rounded text-sm">
                    <option value="all" <?= $filter_type=='all'?'selected':'' ?>>الكل</option>
                    <option value="income" <?= $filter_type=='income'?'selected':'' ?>>مداخيل فقط</option>
                    <option value="expense" <?= $filter_type=='expense'?'selected':'' ?>>مصاريف فقط</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold mb-1">اللجنة</label>
                <select name="filter_committee" class="w-full p-2 border rounded text-sm">
                    <option value="">الكل</option>
                    <?php foreach($committees_list as $com): ?>
                        <option value="<?= $com['id'] ?>" <?= $filter_committee==$com['id']?'selected':'' ?>><?= htmlspecialchars($com['committee_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold mb-1">المشروع</label>
                <select name="filter_project" class="w-full p-2 border rounded text-sm">
                    <option value="">الكل</option>
                    <?php foreach($projects_list as $proj): ?>
                        <option value="<?= $proj['id'] ?>" <?= $filter_project==$proj['id']?'selected':'' ?>><?= htmlspecialchars($proj['project_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold mb-1">التصنيف</label>
                <select name="filter_category" class="w-full p-2 border rounded text-sm">
                    <option value="">الكل</option>
                    <?php foreach($categories_list as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['name_ar']) ?>" <?= $filter_category==$cat['name_ar']?'selected':'' ?>><?= htmlspecialchars($cat['name_ar']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full bg-blue-600 text-white p-2 rounded hover:bg-blue-700 text-sm font-bold">عرض الكشف</button>
            </div>
        </form>
    </div>

    <?php if ($sel_cashbox): ?>
    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
        <div class="bg-white p-4 rounded shadow text-center">
            <div class="text-xs text-gray-500 mb-1">الرصيد الافتتاحي للفترة</div>
            <div class="font-bold text-gray-700" dir="ltr"><?= number_format($opening_balance, 2) ?> <?= $sel_cashbox['currency_symbol'] ?></div>
        </div>
        <div class="bg-green-50 p-4 rounded shadow text-center border border-green-200">
            <div class="text-xs text-green-600 mb-1">إجمالي المداخيل</div>
            <div class="font-bold text-green-700" dir="ltr">+ <?= number_format($total_income, 2) ?> <?= $sel_cashbox['currency_symbol'] ?></div>
        </div>
        <div class="bg-red-50 p-4 rounded shadow text-center border border-red-200">
            <div class="text-xs text-red-600 mb-1">إجمالي المصاريف</div>
            <div class="font-bold text-red-700" dir="ltr">- <?= number_format($total_expense, 2) ?> <?= $sel_cashbox['currency_symbol'] ?></div>
        </div>
        <div class="bg-blue-50 p-4 rounded shadow text-center border border-blue-200">
            <div class="text-xs text-blue-600 mb-1">صافي الحركة</div>
            <?php $net = $total_income - $total_expense; ?>
            <div class="font-bold <?= $net >= 0 ? 'text-green-700' : 'text-red-700' ?>" dir="ltr"><?= ($net >= 0 ? '+' : '') . number_format($net, 2) ?> <?= $sel_cashbox['currency_symbol'] ?></div>
        </div>
        <div class="bg-indigo-50 p-4 rounded shadow text-center border border-indigo-200">
            <div class="text-xs text-indigo-600 mb-1">الرصيد الحالي</div>
            <div class="font-bold text-indigo-700" dir="ltr"><?= number_format($current_balance, 2) ?> <?= $sel_cashbox['currency_symbol'] ?></div>
        </div>
    </div>

    <!-- Print header -->
    <div class="hidden print:block mb-4 text-center">
        <h2 class="text-xl font-bold">بلدية تكريت - كشف حركة الصندوق</h2>
        <p class="text-sm"><?= htmlspecialchars($sel_cashbox['name']) ?> | من <?= $from_date ?> إلى <?= $to_date ?></p>
    </div>

    <!-- Transactions Table -->
    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="w-full text-right text-xs">
            <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="p-2">#</th>
                    <th class="p-2">التاريخ</th>
                    <th class="p-2">النوع</th>
                    <th class="p-2">المبلغ</th>
                    <th class="p-2">الدافع/المستفيد</th>
                    <th class="p-2">التصنيف</th>
                    <th class="p-2">طريقة الدفع</th>
                    <th class="p-2">اللجنة</th>
                    <th class="p-2">المشروع</th>
                    <th class="p-2">إيصال/سند</th>
                    <th class="p-2">ملاحظات</th>
                    <th class="p-2">بواسطة</th>
                    <th class="p-2">الحالة</th>
                    <th class="p-2 no-print">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                <tr><td colspan="14" class="p-6 text-center text-gray-400">لا توجد حركات في هذه الفترة.</td></tr>
                <?php endif; ?>
                <?php foreach ($transactions as $tx): 
                    $is_cancelled = ($tx['status'] === 'ملغى');
                    $payer_payee = $tx['type'] === 'إيراد' ? ($tx['payer_name'] ?? '') : ($tx['payee_name'] ?? '');
                    $doc_number  = $tx['type'] === 'إيراد' ? ($tx['receipt_number'] ?? '') : ($tx['voucher_number'] ?? '');
                    $pm = $tx['payment_method'] ?? '';
                    // Pull payment_method from receipt/voucher if not on transaction
                    // (the column exists on financial_transactions too)
                ?>
                <tr class="border-b hover:bg-gray-50 <?= $is_cancelled ? 'cancelled-row' : '' ?>">
                    <td class="p-2 text-gray-400"><?= $tx['id'] ?></td>
                    <td class="p-2"><?= $tx['transaction_date'] ?></td>
                    <td class="p-2">
                        <?php if ($tx['type'] === 'إيراد'): ?>
                            <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded text-xs font-bold">مدخول</span>
                        <?php else: ?>
                            <span class="bg-red-100 text-red-700 px-2 py-0.5 rounded text-xs font-bold">مصروف</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-2 font-bold" dir="ltr">
                        <?php if ($tx['type'] === 'إيراد'): ?>
                            <span class="text-green-600">+ <?= number_format($tx['amount'], 2) ?></span>
                        <?php else: ?>
                            <span class="text-red-600">- <?= number_format($tx['amount'], 2) ?></span>
                        <?php endif; ?>
                        <span class="text-gray-400"><?= $tx['currency_symbol'] ?></span>
                    </td>
                    <td class="p-2"><?= htmlspecialchars($payer_payee) ?></td>
                    <td class="p-2 text-gray-600"><?= htmlspecialchars($tx['category'] ?? '') ?></td>
                    <td class="p-2"><?= pmLabel($pm) ?></td>
                    <td class="p-2 text-gray-600"><?= htmlspecialchars($tx['committee_name'] ?? '') ?></td>
                    <td class="p-2 text-gray-600"><?= htmlspecialchars($tx['project_name'] ?? '') ?></td>
                    <td class="p-2">
                        <?php if ($doc_number): ?>
                            <a href="accounting_transaction_view.php?id=<?= $tx['id'] ?>" class="text-indigo-600 hover:underline font-mono no-print"><?= htmlspecialchars($doc_number) ?></a>
                            <span class="hidden print:inline font-mono"><?= htmlspecialchars($doc_number) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="p-2 text-gray-500 max-w-xs truncate"><?= htmlspecialchars($tx['description'] ?? '') ?></td>
                    <td class="p-2 text-gray-500"><?= htmlspecialchars($tx['created_by_name'] ?? '') ?></td>
                    <td class="p-2">
                        <?php if ($is_cancelled): ?>
                            <span class="bg-red-100 text-red-700 px-2 py-0.5 rounded text-xs">ملغى</span>
                        <?php else: ?>
                            <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded text-xs">فعّال</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-2 no-print">
                        <div class="flex gap-1">
                            <a href="accounting_transaction_view.php?id=<?= $tx['id'] ?>" class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded hover:bg-blue-200 text-xs">عرض</a>
                            <?php if (!$is_cancelled): ?>
                                <a href="accounting_transaction_view.php?id=<?= $tx['id'] ?>&edit=1" class="bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded hover:bg-yellow-200 text-xs">تعديل</a>
                                <a href="accounting_transaction_cancel.php?id=<?= $tx['id'] ?>" class="bg-red-100 text-red-700 px-2 py-0.5 rounded hover:bg-red-200 text-xs" onclick="return confirm('هل تريد إلغاء هذه الحركة المالية؟ سيتم تصحيح رصيد الصندوق تلقائياً.')">إلغاء</a>
                            <?php else: ?>
                                <span class="text-gray-400 text-xs italic">ملغى <?= $tx['cancelled_at'] ? '(' . date('Y-m-d', strtotime($tx['cancelled_at'])) . ')' : '' ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if (!empty($transactions)): ?>
            <tfoot class="bg-gray-100 font-bold">
                <tr>
                    <td colspan="3" class="p-2 text-left">الإجمالي (بدون الملغى)</td>
                    <td class="p-2" dir="ltr">
                        <span class="text-green-600">+ <?= number_format($total_income, 2) ?></span> /
                        <span class="text-red-600">- <?= number_format($total_expense, 2) ?></span>
                        <span class="text-gray-500"><?= $sel_cashbox['currency_symbol'] ?></span>
                    </td>
                    <td colspan="10"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
