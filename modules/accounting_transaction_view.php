<?php
// modules/accounting_transaction_view.php
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

$id        = intval($_GET['transaction_id'] ?? $_GET['id'] ?? 0);
$edit_mode = isset($_GET['edit']) && $_GET['edit'] == '1';
$message = ''; $error = '';

if (!$id) { die('معرف الحركة غير صحيح.'); }

// --- Fetch (includes municipal_budget_line join) ---
function fetchTx($db, $id) {
    $stmt = $db->prepare("
        SELECT ft.*,
            c.currency_symbol, c.currency_name, c.exchange_rate_to_iqd,
            u.full_name as created_by_name,
            mc.committee_name,
            p.project_name,
            cb.name as cashbox_name, cb.currency_id as cashbox_currency_id,
            bi.name as budget_item_name,
            cbi.item_name as committee_budget_item_name,
            r.receipt_number, r.payer_name, r.payer_type, r.payment_method as r_pm, r.status as r_status,
            v.voucher_number, v.payee_name, v.payee_type, v.payment_method as v_pm, v.status as v_status,
            cu.full_name as cancelled_by_name,
            mbl.section_type as mbl_section_type, mbl.section_name as mbl_section_name,
            mbl.chapter_number as mbl_chapter_number, mbl.chapter_name as mbl_chapter_name,
            mbl.item_number as mbl_item_number, mbl.item_name as mbl_item_name,
            mb.fiscal_year as mbl_fiscal_year
        FROM financial_transactions ft
        LEFT JOIN currencies c ON ft.currency_id = c.id
        LEFT JOIN users u ON ft.created_by = u.id
        LEFT JOIN municipal_committees mc ON ft.committee_id = mc.id
        LEFT JOIN projects p ON ft.project_id = p.id
        LEFT JOIN accounting_cashboxes cb ON ft.cashbox_id = cb.id
        LEFT JOIN budget_items bi ON ft.budget_item_id = bi.id
        LEFT JOIN accounting_committee_budget_items cbi ON ft.committee_budget_item_id = cbi.id
        LEFT JOIN accounting_receipts r ON ft.receipt_id = r.id
        LEFT JOIN accounting_payment_vouchers v ON ft.voucher_id = v.id
        LEFT JOIN users cu ON ft.cancelled_by_user_id = cu.id
        LEFT JOIN municipal_budget_lines mbl ON ft.municipal_budget_line_id = mbl.id
        LEFT JOIN municipal_budgets mb ON mbl.municipal_budget_id = mb.id
        WHERE ft.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$tx = fetchTx($db, $id);
if (!$tx) { die('الحركة غير موجودة.'); }

$is_cancelled = in_array($tx['status'] ?? '', ['ملغى','cancelled']);
$is_income    = in_array($tx['type'] ?? '', ['إيراد', 'مدخول', 'income']);

if (!isset($_SESSION['edit_nonce_' . $id])) {
    $_SESSION['edit_nonce_' . $id] = bin2hex(random_bytes(16));
}

// Handle safe edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_edit'])) {
    $submitted_nonce  = $_POST['edit_nonce'] ?? '';
    $expected_nonce   = $_SESSION['edit_nonce_' . $id] ?? '';
    $csrf_ok = !empty($expected_nonce) && !empty($submitted_nonce) && hash_equals($expected_nonce, $submitted_nonce);
    if (!$csrf_ok) {
        $std_token = $_POST['csrf_token'] ?? '';
        $sess_token = $_SESSION['csrf_token'] ?? '';
        $csrf_ok = !empty($sess_token) && !empty($std_token) && hash_equals($sess_token, $std_token);
    }
    if (!$csrf_ok) {
        $error = 'خطأ أمني. يرجى تحديث الصفحة.';
    } elseif ($is_cancelled) {
        $error = 'لا يمكن تعديل حركة ملغاة.';
    } else {
        $new_desc    = trim($_POST['description'] ?? '');
        $new_pm      = $_POST['payment_method'] ?? '';
        $new_com     = !empty($_POST['committee_id']) ? intval($_POST['committee_id']) : null;
        $new_proj    = !empty($_POST['project_id'])   ? intval($_POST['project_id'])   : null;
        $new_mbl_id  = !empty($_POST['municipal_budget_line_id']) ? intval($_POST['municipal_budget_line_id']) : null;

        // Recalculate budget LBP values when budget line changes
        $new_mbl_bud_id  = null;
        $new_budget_rate = null;
        $new_budget_lbp  = null;
        if ($new_mbl_id) {
            $mbl_row = $db->prepare("SELECT municipal_budget_id FROM municipal_budget_lines WHERE id=?");
            $mbl_row->execute([$new_mbl_id]);
            $new_mbl_bud_id = $mbl_row->fetchColumn() ?: null;
            $curr_row = $db->prepare("SELECT currency_symbol, exchange_rate_to_iqd FROM currencies WHERE id=?");
            $curr_row->execute([$tx['currency_id']]);
            $curr_info = $curr_row->fetch(PDO::FETCH_ASSOC);
            if ($curr_info && in_array($curr_info['currency_symbol'], ['LBP', 'ل.ل.', 'ل.ل'])) {
                $new_budget_rate = 1;
                $new_budget_lbp  = floatval($tx['amount']);
            } else {
                $new_budget_rate = floatval($curr_info['exchange_rate_to_iqd'] ?? 89500);
                $new_budget_lbp  = floatval($tx['amount']) * $new_budget_rate;
            }
        }

        $db->prepare("UPDATE financial_transactions SET description=?, payment_method=?, committee_id=?, project_id=?, municipal_budget_line_id=?, municipal_budget_id=?, budget_exchange_rate=?, budget_amount_lbp=?, updated_at=NOW() WHERE id=?")
           ->execute([$new_desc, $new_pm, $new_com, $new_proj, $new_mbl_id, $new_mbl_bud_id, $new_budget_rate, $new_budget_lbp, $id]);

        try {
            $db->prepare("INSERT INTO accounting_audit_log (user_id, action, entity_type, entity_id, new_data) VALUES (?,?,?,?,?)")
               ->execute([$user['id'], 'update_transaction_budget_line', 'financial_transactions', $id,
                 json_encode(['description'=>$new_desc,'payment_method'=>$new_pm,'municipal_budget_line_id'=>$new_mbl_id,'budget_amount_lbp'=>$new_budget_lbp], JSON_UNESCAPED_UNICODE)]);
        } catch(Exception $e){}

        $message   = 'تم تعديل الحركة بنجاح.';
        $edit_mode = false;
        $tx        = fetchTx($db, $id);
        $is_income = in_array($tx['type'] ?? '', ['إيراد', 'مدخول', 'income']);
    }
    $_SESSION['edit_nonce_' . $id] = bin2hex(random_bytes(16));
}

$committees_list = $db->query("SELECT id, committee_name FROM municipal_committees WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);
$projects_list = [];
try { $projects_list = $db->query("SELECT id, project_name FROM projects LIMIT 200")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}

// Load budget lines filtered by tx type for the edit form
$mbl_for_edit = [];
try {
    $mbl_section = $is_income ? 'income' : 'expense';
    $mbl_stmt = $db->prepare("
        SELECT l.id, l.section_type, l.chapter_number, l.chapter_name, l.item_number, l.item_name, b.fiscal_year
        FROM municipal_budget_lines l
        JOIN municipal_budgets b ON l.municipal_budget_id = b.id
        WHERE b.status IN ('draft','approved') AND l.is_active = 1 AND l.section_type = ?
        ORDER BY b.fiscal_year DESC, l.sort_order ASC
    ");
    $mbl_stmt->execute([$mbl_section]);
    $mbl_for_edit = $mbl_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e){}

$payer_payee = $is_income ? ($tx['payer_name'] ?? '') : ($tx['payee_name'] ?? '');
$pt          = $is_income ? ($tx['payer_type'] ?? '') : ($tx['payee_type'] ?? '');
$pm          = $is_income ? ($tx['r_pm'] ?? $tx['payment_method'] ?? '') : ($tx['v_pm'] ?? $tx['payment_method'] ?? '');
$doc_num     = $is_income ? ($tx['receipt_number'] ?? '') : ($tx['voucher_number'] ?? '');
$back_url    = "accounting_cashbox_statement.php" . ($tx['cashbox_id'] ? "?cashbox_id={$tx['cashbox_id']}" : "");

function pmLabel2($pm) {
    $map=['cash'=>'نقدي','bank_transfer'=>'تحويل بنكي','check'=>'شيك','other'=>'أخرى','نقد'=>'نقدي','نقدي'=>'نقدي'];
    return $map[$pm] ?? ($pm ?: 'غير محدد');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>تفاصيل الحركة #<?= $id ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<style>body { font-family: 'Cairo', sans-serif; }</style>
</head>
<body class="bg-slate-100 p-6 text-slate-800">
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-5">
        <div>
            <h1 class="text-2xl font-bold">تفاصيل الحركة المالية</h1>
            <p class="text-sm text-gray-500"># <?= $id ?> — <?= htmlspecialchars($tx['cashbox_name']??'') ?></p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <?php if (!$is_cancelled && !$edit_mode): ?>
                <a href="?transaction_id=<?= $id ?>&edit=1" class="bg-yellow-500 text-white px-3 py-2 rounded hover:bg-yellow-600 text-sm">✎ تعديل</a>
                <a href="accounting_transaction_cancel.php?transaction_id=<?= $id ?>&from_cashbox=<?= $tx['cashbox_id'] ?>"
                   class="bg-red-600 text-white px-3 py-2 rounded hover:bg-red-700 text-sm font-bold"
                   onclick="return confirm('هل تريد إلغاء هذه الحركة المالية؟')">⊘ إلغاء الحركة</a>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($back_url) ?>" class="bg-indigo-600 text-white px-3 py-2 rounded hover:bg-indigo-700 text-sm">← كشف الصندوق</a>
        </div>
    </div>

    <?php if ($message): ?><div class="bg-green-100 text-green-800 p-3 rounded mb-4 border border-green-200"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-100 text-red-800 p-3 rounded mb-4 border border-red-200"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Cancelled banner -->
    <?php if ($is_cancelled): ?>
    <div class="bg-red-50 border-r-4 border-red-600 p-4 mb-4 rounded">
        <p class="font-bold text-red-700 text-lg">⊘ هذه الحركة ملغاة</p>
        <div class="grid grid-cols-2 gap-2 text-sm text-red-600 mt-2">
            <div><span class="text-gray-500">تاريخ الإلغاء:</span> <?= $tx['cancelled_at'] ?? 'غير متوفر' ?></div>
            <div><span class="text-gray-500">بواسطة:</span> <?= htmlspecialchars($tx['cancelled_by_name'] ?? 'غير معروف') ?></div>
            <div class="col-span-2"><span class="text-gray-500">السبب:</span> <?= htmlspecialchars($tx['cancellation_reason'] ?? '') ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Details -->
    <div class="bg-white rounded shadow p-6 mb-4">
        <h2 class="font-bold text-gray-700 border-b pb-2 mb-4">بيانات الحركة</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
            <div><span class="text-xs text-gray-500 block">النوع</span>
                <span class="font-bold text-lg <?= $is_income?'text-green-600':'text-red-600' ?>"><?= $is_income?'مدخول ↑':'مصروف ↓' ?></span>
            </div>
            <div><span class="text-xs text-gray-500 block">المبلغ</span>
                <span class="font-bold text-xl" dir="ltr"><?= number_format($tx['amount'],2) ?> <?= $tx['currency_symbol'] ?></span>
            </div>
            <div><span class="text-xs text-gray-500 block">التاريخ</span><span class="font-semibold"><?= $tx['transaction_date'] ?></span></div>
            <div><span class="text-xs text-gray-500 block">الصندوق</span><?= htmlspecialchars($tx['cashbox_name']??'') ?></div>
            <div><span class="text-xs text-gray-500 block">التصنيف</span><?= htmlspecialchars($tx['category']??'') ?></div>
            <div><span class="text-xs text-gray-500 block">الحالة</span>
                <?= $is_cancelled
                    ? '<span class="bg-red-100 text-red-700 px-2 py-1 rounded font-bold">ملغى</span>'
                    : '<span class="bg-green-100 text-green-700 px-2 py-1 rounded font-bold">فعّال</span>' ?>
            </div>
            <div><span class="text-xs text-gray-500 block"><?= $is_income?'اسم الدافع':'اسم المستفيد' ?></span><?= htmlspecialchars($payer_payee ?: 'غير محدد') ?></div>
            <div><span class="text-xs text-gray-500 block">طريقة الدفع</span><?= pmLabel2($pm) ?></div>
            <div><span class="text-xs text-gray-500 block"><?= $is_income?'رقم سند القبض':'رقم سند الصرف' ?></span>
                <span class="font-mono"><?= htmlspecialchars($doc_num ?: '—') ?></span>
                <?php if ($is_income && $tx['r_status']): ?>
                    <span class="text-xs <?= $tx['r_status']==='cancelled'?'text-red-500':'text-green-500' ?> mr-1">(<?= $tx['r_status'] ?>)</span>
                <?php elseif (!$is_income && $tx['v_status']): ?>
                    <span class="text-xs <?= in_array($tx['v_status'],['cancelled'])? 'text-red-500':'text-green-500' ?> mr-1">(<?= $tx['v_status'] ?>)</span>
                <?php endif; ?>
            </div>
            <div><span class="text-xs text-gray-500 block">اللجنة</span><?= htmlspecialchars($tx['committee_name']??'—') ?></div>
            <div><span class="text-xs text-gray-500 block">المشروع</span><?= htmlspecialchars($tx['project_name']??'—') ?></div>
            <div><span class="text-xs text-gray-500 block">بند ميزانية اللجنة</span><?= htmlspecialchars($tx['committee_budget_item_name']??$tx['budget_item_name']??'—') ?></div>
            <div><span class="text-xs text-gray-500 block">أدخلها</span><?= htmlspecialchars($tx['created_by_name']??'') ?></div>
            <div><span class="text-xs text-gray-500 block">تاريخ الإدخال</span><?= $tx['created_at'] ?></div>
            <div class="md:col-span-3"><span class="text-xs text-gray-500 block">ملاحظات</span><?= htmlspecialchars($tx['description']??'') ?></div>
        </div>
    </div>

    <!-- Official Municipal Budget Line -->
    <div class="bg-white rounded shadow p-5 mb-4 border-r-4 <?= $tx['mbl_item_name'] ? 'border-indigo-500' : 'border-gray-200' ?>">
        <h2 class="font-bold text-gray-700 border-b pb-2 mb-3">🏛️ بند الموازنة البلدية الرسمية</h2>
        <?php if ($tx['mbl_item_name']): ?>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
            <div><span class="text-xs text-gray-500 block">القسم</span>
                <span class="font-bold <?= $tx['mbl_section_type']==='income'?'text-green-600':'text-red-600' ?>">
                    <?= $tx['mbl_section_type']==='income' ? 'قسم الواردات' : 'قسم النفقات' ?>
                </span>
            </div>
            <div><span class="text-xs text-gray-500 block">الباب</span>
                <span class="font-semibold"><?= htmlspecialchars(($tx['mbl_chapter_number'] ? $tx['mbl_chapter_number'].' - ' : '') . ($tx['mbl_chapter_name']??'')) ?></span>
            </div>
            <div><span class="text-xs text-gray-500 block">الفصل / البند</span>
                <span class="font-semibold"><?= htmlspecialchars(($tx['mbl_item_number'] ? $tx['mbl_item_number'].' - ' : '') . ($tx['mbl_item_name']??'')) ?></span>
            </div>
            <div><span class="text-xs text-gray-500 block">السنة المالية</span><?= htmlspecialchars($tx['mbl_fiscal_year'] ?? '—') ?></div>
            <?php if ($tx['budget_exchange_rate'] && !in_array($tx['currency_symbol']??'', ['LBP','ل.ل.','ل.ل'])): ?>
            <div><span class="text-xs text-gray-500 block">سعر الصرف المستخدم</span><span dir="ltr"><?= number_format($tx['budget_exchange_rate'], 2) ?></span></div>
            <?php endif; ?>
            <?php if ($tx['budget_amount_lbp']): ?>
            <div><span class="text-xs text-gray-500 block">المبلغ بالليرة (للموازنة)</span>
                <span class="font-bold text-indigo-700" dir="ltr"><?= number_format($tx['budget_amount_lbp'], 0) ?> ل.ل.</span>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <p class="text-gray-400 text-sm">هذه الحركة غير مرتبطة ببند من الموازنة الرسمية. يمكن الربط باستخدام زر التعديل أعلاه.</p>
        <?php endif; ?>
    </div>

    <!-- Print links -->
    <?php if (!$is_cancelled): ?>
    <div class="mb-4 flex gap-2">
        <?php if ($is_income && $doc_num): ?>
            <a href="print_receipt.php?transaction_id=<?= $id ?>" target="_blank" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 text-sm">🖨️ طباعة سند قبض</a>
        <?php elseif (!$is_income && $doc_num): ?>
            <a href="print_voucher.php?transaction_id=<?= $id ?>" target="_blank" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 text-sm">🖨️ طباعة سند صرف</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Edit Form -->
    <?php if ($edit_mode && !$is_cancelled): ?>
    <div class="bg-white rounded shadow p-6 mb-4 border-t-4 border-yellow-400">
        <h2 class="font-bold text-yellow-700 mb-1">✎ تعديل البيانات الوصفية</h2>
        <p class="text-xs text-red-600 mb-1">⚠️ لا يمكن تعديل المبلغ أو العملة أو الصندوق أو نوع الحركة.</p>
        <p class="text-xs text-indigo-700 mb-4 bg-indigo-50 p-2 rounded">🏛️ يمكن تعديل بند الموازنة إذا تم اختياره بالخطأ، دون تعديل المبلغ أو الصندوق أو العملة.</p>
        <form method="POST">
            <input type="hidden" name="edit_nonce" value="<?= htmlspecialchars($_SESSION['edit_nonce_' . $id] ?? '') ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">طريقة الدفع</label>
                    <select name="payment_method" class="w-full p-2 border rounded">
                        <?php foreach(['cash'=>'نقدي','bank_transfer'=>'تحويل بنكي','check'=>'شيك','other'=>'أخرى'] as $v=>$l): ?>
                            <option value="<?=$v?>" <?= ($tx['payment_method']==$v)?'selected':'' ?>><?=$l?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">اللجنة</label>
                    <select name="committee_id" class="w-full p-2 border rounded">
                        <option value="">-- بدون --</option>
                        <?php foreach($committees_list as $com): ?>
                            <option value="<?=$com['id']?>" <?= $tx['committee_id']==$com['id']?'selected':'' ?>><?= htmlspecialchars($com['committee_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">المشروع</label>
                    <select name="project_id" class="w-full p-2 border rounded">
                        <option value="">-- بدون --</option>
                        <?php foreach($projects_list as $proj): ?>
                            <option value="<?=$proj['id']?>" <?= $tx['project_id']==$proj['id']?'selected':'' ?>><?= htmlspecialchars($proj['project_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">ملاحظات</label>
                    <textarea name="description" rows="2" class="w-full p-2 border rounded"><?= htmlspecialchars($tx['description']??'') ?></textarea>
                </div>
            </div>
            <!-- Municipal Budget Line Selector -->
            <div class="mt-4 bg-indigo-50 border border-indigo-200 rounded p-4">
                <label class="block text-sm font-bold mb-1 text-indigo-800">🏛️ بند الموازنة الرسمية (<?= $is_income ? 'واردات' : 'نفقات' ?> فقط)</label>
                <input type="text" id="edit-mbl-search" placeholder="ابحث باسم البند أو رقم الفصل أو اسم الباب..." class="w-full p-2 border rounded mb-1 text-sm" oninput="filterEditMbl()" autocomplete="off">
                <select name="municipal_budget_line_id" id="edit-mbl-select" class="w-full p-2 border border-indigo-300 rounded text-sm">
                    <option value="">— بدون ربط بالموازنة الرسمية —</option>
                    <?php foreach($mbl_for_edit as $mline):
                        $sec_l = $mline['section_type']==='income'?'قسم الواردات':'قسم النفقات';
                        $chap_l = $mline['chapter_number'] ? 'الباب '.$mline['chapter_number'].' - '.$mline['chapter_name'] : $mline['chapter_name'];
                        $item_l = $mline['item_number'] ? 'الفصل '.$mline['item_number'].' - '.$mline['item_name'] : $mline['item_name'];
                        $lbl = '['.$mline['fiscal_year'].'] '.$sec_l.' | '.$chap_l.' | '.$item_l;
                        $srch = strtolower(($mline['chapter_number']??'').' '.($mline['chapter_name']??'').' '.($mline['item_number']??'').' '.($mline['item_name']??'').' '.$mline['fiscal_year']);
                    ?>
                    <option value="<?= $mline['id'] ?>" data-search="<?= htmlspecialchars($srch) ?>" <?= $tx['municipal_budget_line_id']==$mline['id']?'selected':'' ?>><?= htmlspecialchars($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-indigo-600 mt-1">اختر بند <strong><?= $is_income?'واردات':'نفقات' ?></strong> فقط. سيتم إعادة تحسيب المبلغ بالليرة تلقائياً عند الحفظ.</p>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="submit" name="save_edit" class="bg-yellow-500 text-white px-6 py-2 rounded hover:bg-yellow-600 font-bold">حفظ التعديلات</button>
                <a href="?transaction_id=<?=$id?>" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">إلغاء</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <script>
    function filterEditMbl() {
        const q = document.getElementById('edit-mbl-search').value.toLowerCase().trim();
        const sel = document.getElementById('edit-mbl-select');
        Array.from(sel.options).forEach(opt => {
            if (!opt.value) return;
            opt.hidden = q && !(opt.dataset.search || opt.text.toLowerCase()).includes(q);
        });
    }
    </script>
</div>
</body>
</html>
