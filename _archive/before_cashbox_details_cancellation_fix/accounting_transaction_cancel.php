<?php
// modules/accounting_transaction_cancel.php
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
$error = ''; $message = '';

if (!$id) { die('معرف الحركة غير صحيح.'); }

// Fetch transaction
$stmt = $db->prepare("SELECT ft.*, c.currency_symbol, cb.name as cashbox_name, cb.current_balance as cashbox_balance FROM financial_transactions ft LEFT JOIN currencies c ON ft.currency_id = c.id LEFT JOIN accounting_cashboxes cb ON ft.cashbox_id = cb.id WHERE ft.id = ?");
$stmt->execute([$id]);
$tx = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tx) { die('الحركة غير موجودة.'); }

$is_cancelled = ($tx['status'] === 'ملغى');
$is_income = ($tx['type'] === 'إيراد');

// Handle cancellation POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_cancel'])) {
    if (!csrf_protect(false)) {
        $error = 'خطأ أمني.';
    } elseif ($is_cancelled) {
        $error = 'هذه الحركة ملغاة مسبقاً. لا يمكن إلغاؤها مرة أخرى.';
    } else {
        $reason = trim($_POST['cancellation_reason']);
        if (empty($reason)) {
            $error = 'يرجى إدخال سبب الإلغاء.';
        } else {
            try {
                $db->beginTransaction();

                // 1. Mark transaction as cancelled
                $stmt = $db->prepare("UPDATE financial_transactions SET status='ملغى', cancelled_at=NOW(), cancelled_by_user_id=?, cancellation_reason=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$user['id'], $reason, $id]);

                // 2. Reverse cashbox balance
                if ($is_income) {
                    // Was income: subtract from cashbox
                    $stmt = $db->prepare("UPDATE accounting_cashboxes SET current_balance = current_balance - ? WHERE id = ?");
                    $stmt->execute([$tx['amount'], $tx['cashbox_id']]);
                } else {
                    // Was expense: add back to cashbox
                    $stmt = $db->prepare("UPDATE accounting_cashboxes SET current_balance = current_balance + ? WHERE id = ?");
                    $stmt->execute([$tx['amount'], $tx['cashbox_id']]);
                }

                // 3. Cancel linked receipt
                if ($tx['receipt_id']) {
                    $db->prepare("UPDATE accounting_receipts SET status='cancelled' WHERE id=?")->execute([$tx['receipt_id']]);
                }

                // 4. Cancel linked voucher
                if ($tx['voucher_id']) {
                    $db->prepare("UPDATE accounting_payment_vouchers SET status='cancelled' WHERE id=?")->execute([$tx['voucher_id']]);
                }

                // 5. Audit log
                $audit_detail = json_encode([
                    'cancelled_transaction_id' => $id,
                    'type' => $tx['type'],
                    'amount' => $tx['amount'],
                    'currency_symbol' => $tx['currency_symbol'],
                    'cashbox_id' => $tx['cashbox_id'],
                    'reason' => $reason
                ]);
                $db->prepare("INSERT INTO accounting_audit_log (user_id, action, entity_type, entity_id, new_data) VALUES (?, 'إلغاء حركة مالية', 'financial_transactions', ?, ?)")
                   ->execute([$user['id'], $id, $audit_detail]);

                $db->commit();
                $message = 'تم إلغاء الحركة المالية بنجاح. تم تصحيح رصيد الصندوق تلقائياً.';
                $is_cancelled = true;

                // Reload cashbox balance
                $stmt = $db->prepare("SELECT current_balance FROM accounting_cashboxes WHERE id=?");
                $stmt->execute([$tx['cashbox_id']]);
                $new_balance = $stmt->fetchColumn();

            } catch (Exception $e) {
                $db->rollBack();
                $error = 'خطأ أثناء الإلغاء: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إلغاء الحركة المالية #<?= $id ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<style>body { font-family: 'Cairo', sans-serif; }</style>
</head>
<body class="bg-slate-100 p-6 text-slate-800">
<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-5">
        <h1 class="text-2xl font-bold text-red-700">⚠️ إلغاء الحركة المالية</h1>
        <a href="accounting_cashbox_statement.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 text-sm">كشف الصندوق</a>
    </div>

    <?php if ($message): ?>
    <div class="bg-green-100 border border-green-300 text-green-800 p-4 rounded mb-4">
        <p class="font-bold"><?= htmlspecialchars($message) ?></p>
        <?php if (isset($new_balance)): ?>
        <p class="text-sm mt-1">الرصيد الجديد للصندوق "<?= htmlspecialchars($tx['cashbox_name']) ?>": <strong><?= number_format($new_balance, 2) ?> <?= $tx['currency_symbol'] ?></strong></p>
        <?php endif; ?>
        <div class="mt-3"><a href="accounting_cashbox_statement.php" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 text-sm">عودة إلى الكشف</a></div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?><div class="bg-red-100 text-red-800 p-3 rounded mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($is_cancelled && !$message): ?>
    <div class="bg-orange-50 border border-orange-200 p-4 rounded mb-4">
        <p class="font-bold text-orange-700">هذه الحركة ملغاة مسبقاً ولا يمكن إلغاؤها مجدداً.</p>
        <a href="accounting_cashbox_statement.php" class="text-indigo-600 hover:underline text-sm mt-2 inline-block">عودة إلى الكشف</a>
    </div>
    <?php elseif (!$message): ?>
    <!-- Transaction Summary -->
    <div class="bg-white rounded shadow p-5 mb-5">
        <h2 class="font-bold mb-3 text-gray-700">بيانات الحركة المراد إلغاؤها</h2>
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div><span class="text-gray-500">المعرف:</span> <strong>#<?= $tx['id'] ?></strong></div>
            <div><span class="text-gray-500">النوع:</span> <strong class="<?= $is_income ? 'text-green-600' : 'text-red-600' ?>"><?= $is_income ? 'مدخول' : 'مصروف' ?></strong></div>
            <div><span class="text-gray-500">المبلغ:</span> <strong dir="ltr"><?= number_format($tx['amount'], 2) ?> <?= $tx['currency_symbol'] ?></strong></div>
            <div><span class="text-gray-500">التاريخ:</span> <strong><?= $tx['transaction_date'] ?></strong></div>
            <div><span class="text-gray-500">الصندوق:</span> <strong><?= htmlspecialchars($tx['cashbox_name'] ?? '') ?></strong></div>
            <div><span class="text-gray-500">الرصيد الحالي:</span> <strong dir="ltr"><?= number_format($tx['cashbox_balance'], 2) ?> <?= $tx['currency_symbol'] ?></strong></div>
        </div>
    </div>

    <!-- Balance Impact Warning -->
    <div class="bg-yellow-50 border-r-4 border-yellow-500 p-4 rounded mb-5">
        <h3 class="font-bold text-yellow-700 mb-1">تأثير الإلغاء على رصيد الصندوق:</h3>
        <?php if ($is_income): ?>
        <p class="text-sm text-yellow-700">كانت هذه الحركة <strong>مدخولاً</strong> بمبلغ <?= number_format($tx['amount'], 2) ?> <?= $tx['currency_symbol'] ?>. سيتم <strong>خصم هذا المبلغ</strong> من رصيد الصندوق تلقائياً.</p>
        <?php $projected = $tx['cashbox_balance'] - $tx['amount']; ?>
        <?php else: ?>
        <p class="text-sm text-yellow-700">كانت هذه الحركة <strong>مصروفاً</strong> بمبلغ <?= number_format($tx['amount'], 2) ?> <?= $tx['currency_symbol'] ?>. سيتم <strong>إعادة هذا المبلغ</strong> إلى رصيد الصندوق تلقائياً.</p>
        <?php $projected = $tx['cashbox_balance'] + $tx['amount']; ?>
        <?php endif; ?>
        <p class="text-sm mt-1">الرصيد بعد الإلغاء: <strong class="<?= $projected < 0 ? 'text-red-600' : 'text-green-600' ?>" dir="ltr"><?= number_format($projected, 2) ?> <?= $tx['currency_symbol'] ?></strong></p>
        <?php if ($projected < 0): ?>
        <p class="text-sm text-red-600 font-bold mt-1">⚠️ تحذير: سيصبح رصيد الصندوق سالباً بعد الإلغاء.</p>
        <?php endif; ?>
    </div>

    <!-- Cancellation Form -->
    <div class="bg-white rounded shadow p-5">
        <h2 class="font-bold text-red-700 mb-3">تأكيد الإلغاء</h2>
        <form method="POST">
            <?= csrf_input('csrf_token') ?>
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">سبب الإلغاء <span class="text-red-500">*</span></label>
                <textarea name="cancellation_reason" required rows="3" placeholder="مثال: خطأ في المبلغ، خطأ في الصندوق، تكرار..." class="w-full p-2 border border-red-300 rounded focus:outline-none focus:border-red-500"></textarea>
                <p class="text-xs text-gray-500 mt-1">هذا السبب سيُحفظ بشكل دائم في سجل التدقيق المالي.</p>
            </div>
            <div class="flex gap-3">
                <button type="submit" name="confirm_cancel" class="bg-red-600 text-white px-6 py-2 rounded hover:bg-red-700 font-bold" onclick="return confirm('هل أنت متأكد تماماً من إلغاء هذه الحركة؟ لا يمكن التراجع عن هذا الإجراء.')">تأكيد الإلغاء</button>
                <a href="accounting_transaction_view.php?id=<?= $id ?>" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">إلغاء</a>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
