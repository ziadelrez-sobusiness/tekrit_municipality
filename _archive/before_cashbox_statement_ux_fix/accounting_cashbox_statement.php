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

// ---- Fetch cashbox list ----
$cashboxes_list = $db->query("SELECT cb.id, cb.name, cb.current_balance, c.currency_symbol, c.currency_name FROM accounting_cashboxes cb JOIN currencies c ON cb.currency_id = c.id WHERE cb.is_active=1 ORDER BY cb.id")->fetchAll(PDO::FETCH_ASSOC);

// ---- Filters ----
$sel_cashbox_id = isset($_GET['cashbox_id']) ? intval($_GET['cashbox_id']) : ($cashboxes_list[0]['id'] ?? 0);

// Quick date filters
$quick = $_GET['quick'] ?? '';
if ($quick === 'today') {
    $from_date = $to_date = date('Y-m-d');
} elseif ($quick === 'year') {
    $from_date = date('Y-01-01');
    $to_date = date('Y-m-d');
} elseif ($quick === 'all') {
    $from_date = '2000-01-01';
    $to_date = date('Y-m-d');
} else {
    $from_date = $_GET['from_date'] ?? date('Y-m-01');
    $to_date   = $_GET['to_date']   ?? date('Y-m-d');
}

$filter_type      = $_GET['filter_type'] ?? 'all';
$filter_committee = !empty($_GET['filter_committee']) ? intval($_GET['filter_committee']) : 0;
$filter_project   = !empty($_GET['filter_project'])   ? intval($_GET['filter_project'])   : 0;

// Find selected cashbox
$sel_cashbox = null;
foreach ($cashboxes_list as $cb) { if ($cb['id'] == $sel_cashbox_id) { $sel_cashbox = $cb; break; } }

// ---- Fetch dropdown lists ----
$committees_list = $db->query("SELECT id, committee_name FROM municipal_committees WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);
$projects_list = [];
try { $projects_list = $db->query("SELECT id, project_name FROM projects LIMIT 200")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}

// ---- Data ----
$transactions = [];
$total_income = 0; $total_expense = 0; $current_balance = 0;
$count_active = 0; $count_cancelled = 0;

if ($sel_cashbox_id) {
    $current_balance = floatval($sel_cashbox['current_balance'] ?? 0);

    // Active period totals
    $stmt = $db->prepare("SELECT
        COALESCE(SUM(CASE WHEN type='إيراد' THEN amount ELSE 0 END),0) as inc,
        COALESCE(SUM(CASE WHEN type='مصروف' THEN amount ELSE 0 END),0) as exp
        FROM financial_transactions
        WHERE cashbox_id=? AND transaction_date BETWEEN ? AND ?
        AND status NOT IN ('ملغى','cancelled')");
    $stmt->execute([$sel_cashbox_id, $from_date, $to_date]);
    $totrow = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_income  = floatval($totrow['inc']);
    $total_expense = floatval($totrow['exp']);

    // Count cancelled
    $stmt = $db->prepare("SELECT COUNT(*) FROM financial_transactions WHERE cashbox_id=? AND transaction_date BETWEEN ? AND ? AND status IN ('ملغى','cancelled')");
    $stmt->execute([$sel_cashbox_id, $from_date, $to_date]);
    $count_cancelled = intval($stmt->fetchColumn());

    // Build WHERE
    $where = ["ft.cashbox_id = :cbid", "ft.transaction_date BETWEEN :fd AND :td"];
    $params = [':cbid' => $sel_cashbox_id, ':fd' => $from_date, ':td' => $to_date];
    if ($filter_type === 'income')  $where[] = "ft.type = 'إيراد'";
    if ($filter_type === 'expense') $where[] = "ft.type = 'مصروف'";
    if ($filter_committee) { $where[] = "ft.committee_id = :com"; $params[':com'] = $filter_committee; }
    if ($filter_project)   { $where[] = "ft.project_id = :proj"; $params[':proj'] = $filter_project; }

    $sql = "SELECT ft.*,
        c.currency_symbol,
        u.full_name as created_by_name,
        mc.committee_name,
        p.project_name,
        bi.name as budget_item_name, bi.item_code,
        cbi.item_name as committee_budget_item_name,
        r.receipt_number, r.payer_name, r.payer_type, r.payment_method as r_pm,
        v.voucher_number, v.payee_name, v.payee_type, v.payment_method as v_pm,
        cu.full_name as cancelled_by_name,
        r.status as r_status, v.status as v_status,
        mbl.section_type as mbl_section_type,
        mbl.chapter_number as mbl_chapter_number,
        mbl.chapter_name as mbl_chapter_name,
        mbl.item_number as mbl_item_number,
        mbl.item_name as mbl_item_name
    FROM financial_transactions ft
    LEFT JOIN currencies c ON ft.currency_id = c.id
    LEFT JOIN users u ON ft.created_by = u.id
    LEFT JOIN municipal_committees mc ON ft.committee_id = mc.id
    LEFT JOIN projects p ON ft.project_id = p.id
    LEFT JOIN budget_items bi ON ft.budget_item_id = bi.id
    LEFT JOIN accounting_committee_budget_items cbi ON ft.committee_budget_item_id = cbi.id
    LEFT JOIN accounting_receipts r ON ft.receipt_id = r.id
    LEFT JOIN accounting_payment_vouchers v ON ft.voucher_id = v.id
    LEFT JOIN users cu ON ft.cancelled_by_user_id = cu.id
    LEFT JOIN municipal_budget_lines mbl ON ft.municipal_budget_line_id = mbl.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY ft.transaction_date DESC, ft.id DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count_active = count(array_filter($transactions, fn($t) => !in_array($t['status'] ?? '', ['ملغى','cancelled'])));
}

function pmLabel($pm) {
    $map = ['cash'=>'نقدي','bank_transfer'=>'تحويل بنكي','check'=>'شيك','other'=>'أخرى',
            'نقد'=>'نقدي','نقدي'=>'نقدي','شيك'=>'شيك','حوالة مصرفية'=>'تحويل بنكي'];
    return $map[$pm] ?? ($pm ?: 'غير محدد');
}
function payerTypeLabel($t) {
    $map=['citizen'=>'مواطن','government'=>'جهة حكومية','donor'=>'متبرع','organization'=>'منظمة',
          'supplier'=>'مورد','employee'=>'موظف','contractor'=>'متعهد','other'=>'أخرى'];
    return $map[$t] ?? ($t ?: '');
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
.cancelled-row { background-color: #fef2f2 !important; }
.cancelled-row td { text-decoration: line-through; opacity: 0.65; }
.cancelled-row .no-strike { text-decoration: none !important; }
@media print {
    .no-print { display: none !important; }
    body { background: white; padding: 10px; font-size: 12px; }
    .print-only { display: block !important; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #ddd; padding: 4px; }
}
.print-only { display: none; }
</style>
</head>
<body class="bg-slate-100 p-4 text-slate-800">
<div class="max-w-7xl mx-auto">

    <!-- Print Header -->
    <div class="print-only mb-6 text-center border-b-2 border-gray-800 pb-4">
        <h1 class="text-3xl font-bold mb-2">بلدية تكريت</h1>
        <h2 class="text-xl font-bold mb-2">كشف حركة الصندوق</h2>
        <?php if ($sel_cashbox): ?>
        <p class="text-lg font-bold">الصندوق: <?= htmlspecialchars($sel_cashbox['name']) ?> (<?= $sel_cashbox['currency_symbol'] ?>)</p>
        <?php endif; ?>
        <p class="text-sm mt-2">من تاريخ: <span dir="ltr"><?= $from_date ?></span> &nbsp;&nbsp;|&nbsp;&nbsp; إلى تاريخ: <span dir="ltr"><?= $to_date ?></span></p>
        <p class="text-xs mt-1 text-gray-500">تاريخ الطباعة: <span dir="ltr"><?= date('Y-m-d H:i') ?></span></p>
    </div>

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4 no-print">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">كشف حركة الصندوق</h1>
            <?php if ($sel_cashbox): ?>
            <p class="text-md text-indigo-600 font-semibold mt-1"><?= htmlspecialchars($sel_cashbox['name']) ?> (<?= $sel_cashbox['currency_symbol'] ?>)</p>
            <?php endif; ?>
        </div>
        <div class="flex gap-2 flex-wrap">
            <button onclick="window.print()" class="bg-indigo-600 text-white px-4 py-2 rounded shadow hover:bg-indigo-700 text-sm font-bold flex items-center gap-2">
                🖨️ طباعة كشف الصندوق
            </button>
            <a href="accounting_cashboxes.php" class="bg-gray-600 text-white px-4 py-2 rounded shadow hover:bg-gray-700 text-sm font-bold">إدارة الصناديق</a>
            <a href="../comprehensive_dashboard.php" class="bg-gray-500 text-white px-4 py-2 rounded shadow hover:bg-gray-600 text-sm font-bold">الرئيسية</a>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white p-4 rounded shadow mb-4 no-print">
        <form method="GET" class="space-y-3">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
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
                    <label class="block text-xs font-bold mb-1">نوع الحركة</label>
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
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 text-white p-2 rounded hover:bg-blue-700 text-sm font-bold">عرض الكشف</button>
                </div>
            </div>
            <!-- Quick filters -->
            <div class="flex gap-2 flex-wrap">
                <span class="text-xs text-gray-500 self-center">فلاتر سريعة:</span>
                <a href="?cashbox_id=<?= $sel_cashbox_id ?>&quick=today" class="text-xs bg-gray-100 hover:bg-gray-200 px-3 py-1 rounded <?= $quick==='today'?'bg-indigo-100 text-indigo-700 font-bold':'' ?>">اليوم</a>
                <a href="?cashbox_id=<?= $sel_cashbox_id ?>" class="text-xs bg-gray-100 hover:bg-gray-200 px-3 py-1 rounded <?= $quick===''&&isset($_GET['cashbox_id'])&&!isset($_GET['quick'])?'bg-indigo-100 text-indigo-700 font-bold':'' ?>">هذا الشهر</a>
                <a href="?cashbox_id=<?= $sel_cashbox_id ?>&quick=year" class="text-xs bg-gray-100 hover:bg-gray-200 px-3 py-1 rounded <?= $quick==='year'?'bg-indigo-100 text-indigo-700 font-bold':'' ?>">هذه السنة</a>
                <a href="?cashbox_id=<?= $sel_cashbox_id ?>&quick=all" class="text-xs bg-gray-100 hover:bg-gray-200 px-3 py-1 rounded <?= $quick==='all'?'bg-indigo-100 text-indigo-700 font-bold':'' ?>">كل الحركات</a>
            </div>
        </form>
    </div>

    <?php if ($sel_cashbox): ?>
    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        <div class="bg-indigo-50 border border-indigo-200 p-4 rounded shadow text-center">
            <div class="text-sm text-indigo-600 mb-1 font-bold">الرصيد الحالي</div>
            <div class="font-bold text-indigo-800 text-lg" dir="ltr"><?= number_format($current_balance, 2) ?> <?= $sel_cashbox['currency_symbol'] ?></div>
        </div>
        <div class="bg-green-50 border border-green-200 p-4 rounded shadow text-center">
            <div class="text-sm text-green-700 mb-1 font-bold">مجموع المداخيل</div>
            <div class="font-bold text-green-800 text-lg" dir="ltr">+ <?= number_format($total_income, 2) ?></div>
        </div>
        <div class="bg-red-50 border border-red-200 p-4 rounded shadow text-center">
            <div class="text-sm text-red-700 mb-1 font-bold">مجموع المصاريف</div>
            <div class="font-bold text-red-800 text-lg" dir="ltr">- <?= number_format($total_expense, 2) ?></div>
        </div>
        <?php $net = $total_income - $total_expense; ?>
        <div class="bg-blue-50 border border-blue-200 p-4 rounded shadow text-center">
            <div class="text-sm text-blue-700 mb-1 font-bold">صافي الحركة</div>
            <div class="font-bold text-lg <?= $net>=0?'text-green-800':'text-red-800' ?>" dir="ltr"><?= ($net>=0?'+ ':'') . number_format($net,2) ?></div>
        </div>
        <div class="bg-gray-50 border border-gray-300 p-4 rounded shadow text-center">
            <div class="text-sm text-gray-600 mb-1 font-bold">عدد الحركات</div>
            <div class="font-bold text-gray-800 text-lg"><?= $count_active ?></div>
        </div>
        <div class="bg-orange-50 border border-orange-200 p-4 rounded shadow text-center">
            <div class="text-sm text-orange-600 mb-1 font-bold">حركات ملغاة</div>
            <div class="font-bold text-orange-800 text-lg"><?= $count_cancelled ?></div>
        </div>
    </div>

    <div class="text-xs text-gray-500 mb-2 no-print">* الإجماليات تستثني الحركات الملغاة. الحركات الملغاة تظهر مشطوبة في الجدول.</div>

    <!-- Transactions Table -->
    <div class="bg-white rounded shadow border border-gray-200 overflow-x-auto w-full">
        <table class="w-full text-right text-sm">
            <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="p-2 whitespace-nowrap">#</th>
                    <th class="p-2 whitespace-nowrap">التاريخ</th>
                    <th class="p-2 whitespace-nowrap">نوع الحركة</th>
                    <th class="p-2 whitespace-nowrap">المبلغ</th>
                    <th class="p-2 whitespace-nowrap">العملة</th>
                    <th class="p-2 whitespace-nowrap">اسم الدافع/المستلم</th>
                    <th class="p-2 whitespace-nowrap">نوعه</th>
                    <th class="p-2 whitespace-nowrap">طريقة الدفع</th>
                    <th class="p-2 whitespace-nowrap">التصنيف</th>
                    <th class="p-2 whitespace-nowrap">اللجنة</th>
                    <th class="p-2 whitespace-nowrap">المشروع</th>
                    <th class="p-2 whitespace-nowrap">بند الميزانية</th>
                    <th class="p-2 whitespace-nowrap bg-indigo-700">🏛️ بند الموازنة الرسمية</th>
                    <th class="p-2 whitespace-nowrap">إيصال/سند</th>
                    <th class="p-2 whitespace-nowrap">ملاحظات</th>
                    <th class="p-2 whitespace-nowrap">أدخلها</th>
                    <th class="p-2 whitespace-nowrap">الحالة</th>
                    <th class="p-2 whitespace-nowrap no-print">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                <tr><td colspan="17" class="p-8 text-center text-gray-400">لا توجد حركات في هذه الفترة للصندوق المحدد.</td></tr>
                <?php endif; ?>
                <?php foreach ($transactions as $tx):
                    $is_cancelled = in_array($tx['status'] ?? '', ['ملغى','cancelled']);
                    $is_income    = in_array($tx['type'] ?? '', ['إيراد','مدخول','income']);
                    $payer_payee  = $is_income ? ($tx['payer_name'] ?? '') : ($tx['payee_name'] ?? '');
                    $pt           = $is_income ? ($tx['payer_type'] ?? '') : ($tx['payee_type'] ?? '');
                    $pm           = $is_income ? ($tx['r_pm'] ?? $tx['payment_method'] ?? '') : ($tx['v_pm'] ?? $tx['payment_method'] ?? '');
                    $doc_num      = $is_income ? ($tx['receipt_number'] ?? '') : ($tx['voucher_number'] ?? '');
                    $budget_label = $tx['committee_budget_item_name'] ?? $tx['budget_item_name'] ?? '';
                    if ($budget_label === '' && ($tx['item_code'] ?? '')) $budget_label = $tx['item_code'];
                    // Build municipal budget line display label
                    $mbl_label = '';
                    if ($tx['mbl_item_name'] ?? null) {
                        $sec_label = ($tx['mbl_section_type'] === 'income') ? 'قسم الواردات' : 'قسم النفقات';
                        $chap_part = $tx['mbl_chapter_number'] ? 'الباب ' . $tx['mbl_chapter_number'] . ' - ' . $tx['mbl_chapter_name'] : $tx['mbl_chapter_name'];
                        $item_part = $tx['mbl_item_number'] ? 'الفصل ' . $tx['mbl_item_number'] . ' - ' . $tx['mbl_item_name'] : $tx['mbl_item_name'];
                        $mbl_label = $sec_label . ' | ' . $chap_part . ' | ' . $item_part;
                    }
                ?>
                <tr class="border-b hover:bg-gray-50 <?= $is_cancelled ? 'cancelled-row' : '' ?>">
                    <td class="p-2 text-gray-400"><?= $tx['id'] ?></td>
                    <td class="p-2 whitespace-nowrap"><?= $tx['transaction_date'] ?></td>
                    <td class="p-2">
                        <?php if ($is_income): ?>
                            <span class="bg-green-100 text-green-700 px-1 py-0.5 rounded font-bold">مدخول</span>
                        <?php else: ?>
                            <span class="bg-red-100 text-red-700 px-1 py-0.5 rounded font-bold">مصروف</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-2 font-bold whitespace-nowrap" dir="ltr">
                        <span class="<?= $is_income?'text-green-600':'text-red-600' ?>">
                            <?= $is_income?'+':'-' ?> <?= number_format($tx['amount'], 2) ?>
                        </span>
                    </td>
                    <td class="p-2"><?= htmlspecialchars($tx['currency_symbol'] ?? '') ?></td>
                    <td class="p-2"><?= htmlspecialchars($payer_payee ?: 'غير محدد') ?></td>
                    <td class="p-2 text-gray-500"><?= payerTypeLabel($pt) ?></td>
                    <td class="p-2 whitespace-nowrap"><?= pmLabel($pm) ?></td>
                    <td class="p-2 text-gray-600"><?= htmlspecialchars($tx['category'] ?? '') ?></td>
                    <td class="p-2 text-gray-600 whitespace-nowrap"><?= htmlspecialchars($tx['committee_name'] ?? '') ?></td>
                    <td class="p-2 text-gray-600"><?= htmlspecialchars($tx['project_name'] ?? '') ?></td>
                    <td class="p-2 text-gray-500"><?= htmlspecialchars($budget_label ?: '—') ?></td>
                    <!-- Official municipal budget line -->
                    <td class="p-2 text-xs">
                        <?php if ($mbl_label): ?>
                            <span class="text-indigo-700 font-medium" title="<?= htmlspecialchars($mbl_label) ?>">
                                <?= htmlspecialchars(mb_substr($mbl_label, 0, 55)) ?><?= mb_strlen($mbl_label) > 55 ? '...' : '' ?>
                            </span>
                        <?php else: ?>
                            <span class="text-gray-300 text-xs">غير مرتبط بموازنة</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-2 font-mono whitespace-nowrap">
                        <?php if ($doc_num): ?>
                            <a href="accounting_transaction_view.php?transaction_id=<?= $tx['id'] ?>" class="text-indigo-600 hover:underline no-strike"><?= htmlspecialchars($doc_num) ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="p-2 text-gray-500 max-w-xs"><?= htmlspecialchars(mb_substr($tx['description'] ?? '', 0, 60)) ?></td>
                    <td class="p-2 text-gray-500 whitespace-nowrap"><?= htmlspecialchars($tx['created_by_name'] ?? '') ?></td>
                    <td class="p-2">
                        <?php if ($is_cancelled): ?>
                            <span class="bg-red-100 text-red-700 px-1 rounded no-strike">ملغى</span>
                        <?php else: ?>
                            <span class="bg-green-100 text-green-700 px-1 rounded">فعّال</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-2 no-print">
                        <div class="flex gap-1 flex-wrap no-strike justify-center">
                            <a href="accounting_transaction_view.php?transaction_id=<?= $tx['id'] ?>" title="عرض" class="bg-blue-100 text-blue-700 p-1.5 rounded hover:bg-blue-200 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                  <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                  <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                </svg>
                            </a>
                            <?php if (!$is_cancelled): ?>
                                <?php if ($is_income && $doc_num): ?>
                                    <a href="print_receipt.php?transaction_id=<?= $tx['id'] ?>" target="_blank" title="طباعة سند قبض" class="bg-indigo-100 text-indigo-700 p-1.5 rounded hover:bg-indigo-200 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                          <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                <?php elseif (!$is_income && $doc_num): ?>
                                    <a href="print_voucher.php?transaction_id=<?= $tx['id'] ?>" target="_blank" title="طباعة سند صرف" class="bg-purple-100 text-purple-700 p-1.5 rounded hover:bg-purple-200 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                          <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                <?php endif; ?>
                                <a href="accounting_transaction_view.php?transaction_id=<?= $tx['id'] ?>&edit=1" title="تعديل" class="bg-yellow-100 text-yellow-700 p-1.5 rounded hover:bg-yellow-200 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                      <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                    </svg>
                                </a>
                                <a href="accounting_transaction_cancel.php?transaction_id=<?= $tx['id'] ?>&from_cashbox=<?= $sel_cashbox_id ?>" title="إلغاء" class="bg-red-100 text-red-700 p-1.5 rounded hover:bg-red-200 flex items-center justify-center"
                                   onclick="return confirm('هل تريد إلغاء هذه الحركة المالية؟\nسيتم عكس رصيد الصندوق تلقائياً.')">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php else: ?>
                                <span class="text-gray-400 text-xs italic">ملغى</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if (!empty($transactions)): ?>
            <tfoot class="bg-gray-100 font-bold text-xs">
                <tr>
                    <td colspan="3" class="p-2">المجموع (بدون الملغى)</td>
                    <td class="p-2" dir="ltr">
                        <span class="text-green-600">+ <?= number_format($total_income,2) ?></span>
                        <span class="text-gray-400"> / </span>
                        <span class="text-red-600">- <?= number_format($total_expense,2) ?></span>
                        <span class="text-gray-500 mr-1"><?= $sel_cashbox['currency_symbol'] ?></span>
                    </td>
                    <td colspan="13"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
