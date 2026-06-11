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

$id = intval($_GET['id'] ?? 0);
$edit_mode = isset($_GET['edit']) && $_GET['edit'] == '1';
$message = ''; $error = '';

if (!$id) { die('معرف الحركة غير صحيح.'); }

// Fetch transaction
$stmt = $db->prepare("
    SELECT ft.*,
        c.currency_symbol, c.currency_name,
        u.full_name as created_by_name,
        mc.committee_name,
        p.project_name,
        cb.name as cashbox_name,
        r.receipt_number, r.payer_name, r.payer_type, r.receipt_date, r.payment_method as r_payment_method,
        v.voucher_number, v.payee_name, v.payee_type, v.voucher_date, v.payment_method as v_payment_method,
        cu.full_name as cancelled_by_name
    FROM financial_transactions ft
    LEFT JOIN currencies c ON ft.currency_id = c.id
    LEFT JOIN users u ON ft.created_by = u.id
    LEFT JOIN municipal_committees mc ON ft.committee_id = mc.id
    LEFT JOIN projects p ON ft.project_id = p.id
    LEFT JOIN accounting_cashboxes cb ON ft.cashbox_id = cb.id
    LEFT JOIN accounting_receipts r ON ft.receipt_id = r.id
    LEFT JOIN accounting_payment_vouchers v ON ft.voucher_id = v.id
    LEFT JOIN users cu ON ft.cancelled_by_user_id = cu.id
    WHERE ft.id = ?
");
$stmt->execute([$id]);
$tx = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$tx) { die('الحركة غير موجودة.'); }

$is_cancelled = ($tx['status'] === 'ملغى');
$is_income = ($tx['type'] === 'إيراد');

// Handle edit POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_edit'])) {
    if (!csrf_protect(false)) {
        $error = 'خطأ أمني.';
    } elseif ($is_cancelled) {
        $error = 'لا يمكن تعديل حركة ملغاة.';
    } else {
        $new_description = trim($_POST['description']);
        $new_payment_method = $_POST['payment_method'];
        $new_committee_id   = !empty($_POST['committee_id']) ? intval($_POST['committee_id']) : null;
        $new_project_id     = !empty($_POST['project_id'])   ? intval($_POST['project_id'])   : null;

        $stmt = $db->prepare("UPDATE financial_transactions SET description=?, payment_method=?, committee_id=?, project_id=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$new_description, $new_payment_method, $new_committee_id, $new_project_id, $id]);

        // Audit
        $db->prepare("INSERT INTO accounting_audit_log (user_id, action, entity_type, entity_id, new_data) VALUES (?, 'تعديل حركة', 'financial_transactions', ?, ?)")
           ->execute([$user['id'], $id, json_encode(['description'=>$new_description, 'payment_method'=>$new_payment_method])]);

        $message = 'تم تعديل الحركة المالية بنجاح.';
        $edit_mode = false;
        // Refresh tx
        $stmt = $db->prepare("SELECT ft.*, c.currency_symbol, c.currency_name, u.full_name as created_by_name, mc.committee_name, p.project_name, cb.name as cashbox_name, r.receipt_number, r.payer_name, v.voucher_number, v.payee_name, cu.full_name as cancelled_by_name FROM financial_transactions ft LEFT JOIN currencies c ON ft.currency_id = c.id LEFT JOIN users u ON ft.created_by = u.id LEFT JOIN municipal_committees mc ON ft.committee_id = mc.id LEFT JOIN projects p ON ft.project_id = p.id LEFT JOIN accounting_cashboxes cb ON ft.cashbox_id = cb.id LEFT JOIN accounting_receipts r ON ft.receipt_id = r.id LEFT JOIN accounting_payment_vouchers v ON ft.voucher_id = v.id LEFT JOIN users cu ON ft.cancelled_by_user_id = cu.id WHERE ft.id = ?");
        $stmt->execute([$id]);
        $tx = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$committees_list = $db->query("SELECT id, committee_name FROM municipal_committees WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);
$projects_list = [];
try { $projects_list = $db->query("SELECT id, project_name FROM projects LIMIT 200")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>تفاصيل الحركة المالية #<?= $id ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<style>body { font-family: 'Cairo', sans-serif; }</style>
</head>
<body class="bg-slate-100 p-6 text-slate-800">
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-5">
        <h1 class="text-2xl font-bold">تفاصيل الحركة #<?= $id ?></h1>
        <div class="flex gap-2">
            <?php if (!$is_cancelled && !$edit_mode): ?>
                <a href="?id=<?= $id ?>&edit=1" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 text-sm">تعديل</a>
                <a href="accounting_transaction_cancel.php?id=<?= $id ?>" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 text-sm" onclick="return confirm('هل تريد إلغاء هذه الحركة؟')">إلغاء الحركة</a>
            <?php endif; ?>
            <a href="accounting_cashbox_statement.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 text-sm">كشف الصندوق</a>
        </div>
    </div>

    <?php if ($message): ?><div class="bg-green-100 text-green-800 p-3 rounded mb-4"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-100 text-red-800 p-3 rounded mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($is_cancelled): ?>
    <div class="bg-red-50 border-r-4 border-red-500 p-4 mb-4 rounded">
        <p class="font-bold text-red-700">⚠️ هذه الحركة ملغاة</p>
        <p class="text-sm text-red-600">تاريخ الإلغاء: <?= $tx['cancelled_at'] ?? 'غير متوفر' ?></p>
        <p class="text-sm text-red-600">بواسطة: <?= htmlspecialchars($tx['cancelled_by_name'] ?? 'غير معروف') ?></p>
        <p class="text-sm text-red-600">السبب: <?= htmlspecialchars($tx['cancellation_reason'] ?? '') ?></p>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded shadow p-6 mb-4">
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div><span class="text-xs text-gray-500">النوع</span><div class="font-bold <?= $is_income ? 'text-green-600' : 'text-red-600' ?>"><?= $is_income ? 'مدخول ↑' : 'مصروف ↓' ?></div></div>
            <div><span class="text-xs text-gray-500">المبلغ</span><div class="font-bold text-lg" dir="ltr"><?= number_format($tx['amount'],2) ?> <?= $tx['currency_symbol'] ?></div></div>
            <div><span class="text-xs text-gray-500">التاريخ</span><div class="font-semibold"><?= $tx['transaction_date'] ?></div></div>
            <div><span class="text-xs text-gray-500">الصندوق</span><div><?= htmlspecialchars($tx['cashbox_name'] ?? '') ?></div></div>
            <div><span class="text-xs text-gray-500">التصنيف</span><div><?= htmlspecialchars($tx['category'] ?? '') ?></div></div>
            <div><span class="text-xs text-gray-500">الحالة</span>
                <div><?= $is_cancelled ? '<span class="text-red-600 font-bold">ملغى</span>' : '<span class="text-green-600 font-bold">فعّال</span>' ?></div>
            </div>
        </div>
    </div>

    <?php if ($edit_mode && !$is_cancelled): ?>
    <!-- Edit Form -->
    <div class="bg-white rounded shadow p-6 mb-4">
        <h2 class="font-bold text-lg mb-4 text-yellow-700">⚠️ تعديل البيانات الوصفية فقط — لا يمكن تعديل المبلغ أو العملة أو الصندوق أو النوع</h2>
        <form method="POST">
            <?= csrf_input('csrf_token') ?>
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
                    <textarea name="description" rows="2" class="w-full p-2 border rounded"><?= htmlspecialchars($tx['description'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <button type="submit" name="save_edit" class="bg-yellow-500 text-white px-6 py-2 rounded hover:bg-yellow-600 font-bold">حفظ التعديلات</button>
                <a href="?id=<?=$id?>" class="bg-gray-200 text-gray-700 px-4 py-2 rounded">إلغاء</a>
            </div>
        </form>
    </div>
    <?php else: ?>
    <!-- View Details -->
    <div class="bg-white rounded shadow p-6 mb-4">
        <h2 class="font-bold mb-3 text-gray-700">بيانات الطرف الآخر</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div><span class="text-xs text-gray-500"><?= $is_income ? 'اسم الدافع' : 'اسم المستفيد' ?></span><div><?= htmlspecialchars($is_income ? ($tx['payer_name'] ?? '') : ($tx['payee_name'] ?? '')) ?></div></div>
            <div><span class="text-xs text-gray-500">طريقة الدفع</span><div><?= htmlspecialchars($tx['payment_method'] ?? 'غير محدد') ?></div></div>
            <div><span class="text-xs text-gray-500"><?= $is_income ? 'رقم الإيصال' : 'رقم سند الصرف' ?></span>
                <div class="font-mono"><?= htmlspecialchars($is_income ? ($tx['receipt_number'] ?? '-') : ($tx['voucher_number'] ?? '-')) ?></div>
            </div>
            <div><span class="text-xs text-gray-500">اللجنة</span><div><?= htmlspecialchars($tx['committee_name'] ?? '-') ?></div></div>
            <div><span class="text-xs text-gray-500">المشروع</span><div><?= htmlspecialchars($tx['project_name'] ?? '-') ?></div></div>
            <div><span class="text-xs text-gray-500">بواسطة</span><div><?= htmlspecialchars($tx['created_by_name'] ?? '') ?></div></div>
            <div class="md:col-span-3"><span class="text-xs text-gray-500">ملاحظات</span><div><?= htmlspecialchars($tx['description'] ?? '') ?></div></div>
        </div>
    </div>
    <?php if ($is_income && $tx['receipt_number']): ?>
    <div class="text-center"><a href="print_receipt.php?transaction_id=<?= $id ?>" target="_blank" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 text-sm">🖨️ طباعة الإيصال</a></div>
    <?php elseif (!$is_income && $tx['voucher_number']): ?>
    <div class="text-center"><a href="print_voucher.php?transaction_id=<?= $id ?>" target="_blank" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 text-sm">🖨️ طباعة سند الصرف</a></div>
    <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
