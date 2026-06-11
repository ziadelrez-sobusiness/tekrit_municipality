<?php
// modules/accounting_treasury.php

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

$message = '';
$error = '';

// معالجة إضافة مدخول
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_income'])) {
    if (!csrf_protect(false)) {
        $error = 'خطأ أمني. يرجى تحديث الصفحة.';
    } else {
        $amount = floatval($_POST['amount']);
        $currency_id = intval($_POST['currency_id']);
        $cashbox_id = intval($_POST['cashbox_id']);
        $category_id = intval($_POST['category_id']);
        $payer_name = trim($_POST['payer_name']);
        $payer_type = $_POST['payer_type'];
        $transaction_date = $_POST['transaction_date'];
        $payment_method = $_POST['payment_method'];
        $notes = trim($_POST['notes']);
        
        $committee_id = !empty($_POST['committee_id']) ? intval($_POST['committee_id']) : null;
        $project_id = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
        $budget_item_id = !empty($_POST['budget_item_id']) ? intval($_POST['budget_item_id']) : null;
        $committee_budget_item_id = !empty($_POST['committee_budget_item_id']) ? intval($_POST['committee_budget_item_id']) : null;

        if ($amount > 0 && $currency_id && $cashbox_id && $category_id && $payer_name) {
            try {
                $db->beginTransaction();

                // 1. Insert into financial_transactions
                $stmt = $db->prepare("INSERT INTO financial_transactions 
                    (type, category, amount, currency_id, cashbox_id, committee_id, project_id, budget_item_id, committee_budget_item_id, transaction_date, payment_method, description, created_by, status)
                    VALUES ('إيراد', (SELECT name_ar FROM accounting_categories WHERE id=?), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'معتمد')");
                $stmt->execute([$category_id, $amount, $currency_id, $cashbox_id, $committee_id, $project_id, $budget_item_id, $committee_budget_item_id, $transaction_date, $payment_method, $notes, $user['id']]);
                $transaction_id = $db->lastInsertId();

                // 2. Insert into accounting_receipts
                $receipt_number = 'REC-' . date('Ymd') . '-' . str_pad($transaction_id, 4, '0', STR_PAD_LEFT);
                $stmt = $db->prepare("INSERT INTO accounting_receipts 
                    (receipt_number, transaction_id, committee_id, project_id, budget_item_id, payer_name, payer_type, amount, currency_id, receipt_date, payment_method, received_by_user_id, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$receipt_number, $transaction_id, $committee_id, $project_id, $budget_item_id, $payer_name, $payer_type, $amount, $currency_id, $transaction_date, $payment_method, $user['id'], $notes]);
                $receipt_id = $db->lastInsertId();

                // 3. Update financial_transactions with receipt_id
                $stmt = $db->prepare("UPDATE financial_transactions SET receipt_id = ? WHERE id = ?");
                $stmt->execute([$receipt_id, $transaction_id]);

                // 4. Update cashbox balance
                $stmt = $db->prepare("UPDATE accounting_cashboxes SET current_balance = current_balance + ? WHERE id = ? AND currency_id = ?");
                $stmt->execute([$amount, $cashbox_id, $currency_id]);

                // 5. Audit Log
                $stmt = $db->prepare("INSERT INTO accounting_audit_log (user_id, action, entity_type, entity_id) VALUES (?, 'إضافة مدخول', 'financial_transactions', ?)");
                $stmt->execute([$user['id'], $transaction_id]);

                $db->commit();
                $message = 'تم إضافة المدخول وإصدار الإيصال بنجاح.';
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'خطأ أثناء الحفظ: ' . $e->getMessage();
            }
        } else {
            $error = 'يرجى تعبئة جميع الحقول المطلوبة بمبلغ أكبر من 0.';
        }
    }
}

// معالجة إضافة مصروف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    if (!csrf_protect(false)) {
        $error = 'خطأ أمني. يرجى تحديث الصفحة.';
    } else {
        $amount = floatval($_POST['amount']);
        $currency_id = intval($_POST['currency_id']);
        $cashbox_id = intval($_POST['cashbox_id']);
        $category_id = intval($_POST['category_id']);
        $payee_name = trim($_POST['payee_name']);
        $payee_type = $_POST['payee_type'];
        $transaction_date = $_POST['transaction_date'];
        $payment_method = $_POST['payment_method'];
        $notes = trim($_POST['notes']);
        
        $committee_id = !empty($_POST['committee_id']) ? intval($_POST['committee_id']) : null;
        $project_id = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
        $budget_item_id = !empty($_POST['budget_item_id']) ? intval($_POST['budget_item_id']) : null;
        $committee_budget_item_id = !empty($_POST['committee_budget_item_id']) ? intval($_POST['committee_budget_item_id']) : null;

        if ($amount > 0 && $currency_id && $cashbox_id && $category_id && $payee_name) {
            try {
                $db->beginTransaction();

                // 1. Insert into financial_transactions
                $stmt = $db->prepare("INSERT INTO financial_transactions 
                    (type, category, amount, currency_id, cashbox_id, committee_id, project_id, budget_item_id, committee_budget_item_id, transaction_date, payment_method, description, created_by, status)
                    VALUES ('مصروف', (SELECT name_ar FROM accounting_categories WHERE id=?), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'معتمد')");
                $stmt->execute([$category_id, $amount, $currency_id, $cashbox_id, $committee_id, $project_id, $budget_item_id, $committee_budget_item_id, $transaction_date, $payment_method, $notes, $user['id']]);
                $transaction_id = $db->lastInsertId();

                // 2. Insert into accounting_payment_vouchers
                $voucher_number = 'VOU-' . date('Ymd') . '-' . str_pad($transaction_id, 4, '0', STR_PAD_LEFT);
                $stmt = $db->prepare("INSERT INTO accounting_payment_vouchers 
                    (voucher_number, transaction_id, committee_id, project_id, budget_item_id, payee_name, payee_type, amount, currency_id, voucher_date, payment_method, paid_by_user_id, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$voucher_number, $transaction_id, $committee_id, $project_id, $budget_item_id, $payee_name, $payee_type, $amount, $currency_id, $transaction_date, $payment_method, $user['id'], $notes]);
                $voucher_id = $db->lastInsertId();

                // 3. Update financial_transactions with voucher_id
                $stmt = $db->prepare("UPDATE financial_transactions SET voucher_id = ? WHERE id = ?");
                $stmt->execute([$voucher_id, $transaction_id]);

                // 4. Update cashbox balance (Decrease)
                $stmt = $db->prepare("UPDATE accounting_cashboxes SET current_balance = current_balance - ? WHERE id = ? AND currency_id = ?");
                $stmt->execute([$amount, $cashbox_id, $currency_id]);

                // 5. Audit Log
                $stmt = $db->prepare("INSERT INTO accounting_audit_log (user_id, action, entity_type, entity_id) VALUES (?, 'إضافة مصروف', 'financial_transactions', ?)");
                $stmt->execute([$user['id'], $transaction_id]);

                $db->commit();
                $message = 'تم إضافة المصروف وإصدار سند الصرف بنجاح.';
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'خطأ أثناء الحفظ: ' . $e->getMessage();
            }
        } else {
            $error = 'يرجى تعبئة جميع الحقول المطلوبة بمبلغ أكبر من 0.';
        }
    }
}

// Fetch lists
$currencies = $db->query("SELECT id, currency_name, currency_symbol FROM currencies WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);
$cashboxes = $db->query("SELECT id, name, currency_id FROM accounting_cashboxes WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);
$committees = $db->query("SELECT id, committee_name FROM municipal_committees WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);
$projects = [];
try { $projects = $db->query("SELECT id, title as project_name FROM projects WHERE status IN ('قيد التخطيط', 'قيد التنفيذ', 'مخطط', 'مستمر')")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}
if(empty($projects)) {
    try { $projects = $db->query("SELECT id, project_name FROM projects WHERE status IN ('قيد التخطيط', 'قيد التنفيذ')")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}
}
$budget_items = [];
try { $budget_items = $db->query("SELECT id, name, item_code FROM budget_items")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}

$committee_budget_items = [];
try { 
    $committee_budget_items = $db->query("
        SELECT i.id, i.item_name, b.committee_id, b.currency_id, b.fiscal_year
        FROM accounting_committee_budget_items i
        JOIN accounting_committee_budgets b ON i.committee_budget_id = b.id
    ")->fetchAll(PDO::FETCH_ASSOC); 
} catch(Exception $e){}

$income_categories = $db->query("SELECT id, name_ar FROM accounting_categories WHERE type='income' AND is_active=1")->fetchAll(PDO::FETCH_ASSOC);
$expense_categories = $db->query("SELECT id, name_ar FROM accounting_categories WHERE type='expense' AND is_active=1")->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent transactions
$stmt = $db->query("
    SELECT ft.id, ft.transaction_date, ft.type, ft.amount, ft.category, 
           c.currency_symbol, mc.committee_name, p.title as project_name,
           u.full_name as created_by_name,
           r.receipt_number, v.voucher_number,
           cb.name as cashbox_name
    FROM financial_transactions ft
    LEFT JOIN currencies c ON ft.currency_id = c.id
    LEFT JOIN municipal_committees mc ON ft.committee_id = mc.id
    LEFT JOIN projects p ON ft.project_id = p.id
    LEFT JOIN users u ON ft.created_by = u.id
    LEFT JOIN accounting_receipts r ON ft.receipt_id = r.id
    LEFT JOIN accounting_payment_vouchers v ON ft.voucher_id = v.id
    LEFT JOIN accounting_cashboxes cb ON ft.cashbox_id = cb.id
    ORDER BY ft.id DESC LIMIT 20
");
$recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>الحركة المالية (الصندوق)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Cairo', sans-serif; }</style>
</head>
<body class="bg-slate-100 p-6">
    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-slate-800">الحركة المالية (الصندوق)</h1>
            <a href="../comprehensive_dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">العودة للوحة التحكم</a>
        </div>

        <?php if ($message): ?><div class="bg-green-100 text-green-800 p-4 rounded mb-4"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="bg-red-100 text-red-800 p-4 rounded mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <!-- Tabs -->
        <div class="flex border-b border-gray-300 mb-6">
            <button onclick="showTab('income')" id="tab-income" class="px-6 py-3 font-semibold text-indigo-600 border-b-2 border-indigo-600 bg-white">إضافة مدخول</button>
            <button onclick="showTab('expense')" id="tab-expense" class="px-6 py-3 font-semibold text-gray-500 hover:text-indigo-600 bg-gray-50">إضافة مصروف</button>
        </div>

        <!-- Income Tab -->
        <div id="content-income" class="bg-white p-6 rounded shadow mb-8">
            <form method="POST" action="">
                <?php echo csrf_input('csrf_token'); ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">المبلغ <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" name="amount" required class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">العملة <span class="text-red-500">*</span></label>
                        <select name="currency_id" required class="w-full p-2 border rounded" onchange="filterCashboxes(this.value, 'income-cashbox'); filterCommitteeBudgetItems('income')">
                            <option value="">اختر العملة...</option>
                            <?php foreach ($currencies as $curr): ?>
                                <option value="<?= $curr['id'] ?>"><?= $curr['currency_name'] ?> (<?= $curr['currency_symbol'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">الصندوق <span class="text-red-500">*</span></label>
                        <select name="cashbox_id" id="income-cashbox" required class="w-full p-2 border rounded">
                            <option value="">اختر العملة أولاً...</option>
                            <?php foreach ($cashboxes as $cb): ?>
                                <option value="<?= $cb['id'] ?>" data-currency="<?= $cb['currency_id'] ?>" class="hidden"><?= $cb['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">التصنيف (المدخول) <span class="text-red-500">*</span></label>
                        <select name="category_id" required class="w-full p-2 border rounded">
                            <option value="">اختر التصنيف...</option>
                            <?php foreach ($income_categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= $cat['name_ar'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">اسم الدافع <span class="text-red-500">*</span></label>
                        <input type="text" name="payer_name" required class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">نوع الدافع</label>
                        <select name="payer_type" required class="w-full p-2 border rounded">
                            <option value="citizen">مواطن</option>
                            <option value="government">جهة حكومية</option>
                            <option value="donor">متبرع</option>
                            <option value="organization">منظمة</option>
                            <option value="other">أخرى</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4 bg-gray-50 p-4 rounded border">
                    <div>
                        <label class="block text-sm font-medium mb-1">اللجنة (اختياري)</label>
                        <select name="committee_id" class="w-full p-2 border rounded" onchange="filterCommitteeBudgetItems('income')">
                            <option value="">-- بدون --</option>
                            <?php foreach ($committees as $com): ?>
                                <option value="<?= $com['id'] ?>"><?= htmlspecialchars($com['committee_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">بند ميزانية اللجنة</label>
                        <select name="committee_budget_item_id" class="w-full p-2 border rounded c-budget-item-select">
                            <option value="">-- اختر اللجنة والعملة --</option>
                            <?php foreach ($committee_budget_items as $item): ?>
                                <option value="<?= $item['id'] ?>" data-committee="<?= $item['committee_id'] ?>" data-currency="<?= $item['currency_id'] ?>" class="hidden">
                                    <?= htmlspecialchars($item['item_name']) ?> (<?= $item['fiscal_year'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">المشروع (اختياري)</label>
                        <select name="project_id" class="w-full p-2 border rounded">
                            <option value="">-- بدون --</option>
                            <?php foreach ($projects as $proj): ?>
                                <option value="<?= $proj['id'] ?>"><?= htmlspecialchars($proj['project_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">بند الميزانية (اختياري)</label>
                        <select name="budget_item_id" class="w-full p-2 border rounded">
                            <option value="">-- بدون --</option>
                            <?php foreach ($budget_items as $item): ?>
                                <option value="<?= $item['id'] ?>"><?= htmlspecialchars($item['item_code'] . ' - ' . $item['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">طريقة الدفع</label>
                        <select name="payment_method" class="w-full p-2 border rounded">
                            <option value="cash">نقدي</option>
                            <option value="bank_transfer">تحويل بنكي</option>
                            <option value="check">شيك</option>
                            <option value="other">أخرى</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">تاريخ المعاملة</label>
                        <input type="date" name="transaction_date" required value="<?= date('Y-m-d') ?>" class="w-full p-2 border rounded">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">ملاحظات</label>
                    <textarea name="notes" rows="2" class="w-full p-2 border rounded"></textarea>
                </div>

                <button type="submit" name="add_income" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 font-bold w-full md:w-auto">تسجيل المدخول وإصدار إيصال</button>
            </form>
        </div>

        <!-- Expense Tab -->
        <div id="content-expense" class="bg-white p-6 rounded shadow mb-8 hidden">
            <form method="POST" action="">
                <?php echo csrf_input('csrf_token'); ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">المبلغ <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" name="amount" required class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">العملة <span class="text-red-500">*</span></label>
                        <select name="currency_id" required class="w-full p-2 border rounded" onchange="filterCashboxes(this.value, 'expense-cashbox'); filterCommitteeBudgetItems('expense')">
                            <option value="">اختر العملة...</option>
                            <?php foreach ($currencies as $curr): ?>
                                <option value="<?= $curr['id'] ?>"><?= $curr['currency_name'] ?> (<?= $curr['currency_symbol'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">الصندوق <span class="text-red-500">*</span></label>
                        <select name="cashbox_id" id="expense-cashbox" required class="w-full p-2 border rounded">
                            <option value="">اختر العملة أولاً...</option>
                            <?php foreach ($cashboxes as $cb): ?>
                                <option value="<?= $cb['id'] ?>" data-currency="<?= $cb['currency_id'] ?>" class="hidden"><?= $cb['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">التصنيف (المصروف) <span class="text-red-500">*</span></label>
                        <select name="category_id" required class="w-full p-2 border rounded">
                            <option value="">اختر التصنيف...</option>
                            <?php foreach ($expense_categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= $cat['name_ar'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">اسم المستفيد <span class="text-red-500">*</span></label>
                        <input type="text" name="payee_name" required class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">نوع المستفيد</label>
                        <select name="payee_type" required class="w-full p-2 border rounded">
                            <option value="supplier">مورد</option>
                            <option value="employee">موظف</option>
                            <option value="contractor">متعهد</option>
                            <option value="citizen">مواطن</option>
                            <option value="other">أخرى</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4 bg-gray-50 p-4 rounded border">
                    <div>
                        <label class="block text-sm font-medium mb-1">اللجنة (اختياري)</label>
                        <select name="committee_id" class="w-full p-2 border rounded" onchange="filterCommitteeBudgetItems('expense')">
                            <option value="">-- بدون --</option>
                            <?php foreach ($committees as $com): ?>
                                <option value="<?= $com['id'] ?>"><?= htmlspecialchars($com['committee_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">بند ميزانية اللجنة</label>
                        <select name="committee_budget_item_id" class="w-full p-2 border rounded c-budget-item-select">
                            <option value="">-- اختر اللجنة والعملة --</option>
                            <?php foreach ($committee_budget_items as $item): ?>
                                <option value="<?= $item['id'] ?>" data-committee="<?= $item['committee_id'] ?>" data-currency="<?= $item['currency_id'] ?>" class="hidden">
                                    <?= htmlspecialchars($item['item_name']) ?> (<?= $item['fiscal_year'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">المشروع (اختياري)</label>
                        <select name="project_id" class="w-full p-2 border rounded">
                            <option value="">-- بدون --</option>
                            <?php foreach ($projects as $proj): ?>
                                <option value="<?= $proj['id'] ?>"><?= htmlspecialchars($proj['project_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">بند الميزانية (اختياري)</label>
                        <select name="budget_item_id" class="w-full p-2 border rounded">
                            <option value="">-- بدون --</option>
                            <?php foreach ($budget_items as $item): ?>
                                <option value="<?= $item['id'] ?>"><?= htmlspecialchars($item['item_code'] . ' - ' . $item['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">طريقة الدفع</label>
                        <select name="payment_method" class="w-full p-2 border rounded">
                            <option value="cash">نقدي</option>
                            <option value="bank_transfer">تحويل بنكي</option>
                            <option value="check">شيك</option>
                            <option value="other">أخرى</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">تاريخ المعاملة</label>
                        <input type="date" name="transaction_date" required value="<?= date('Y-m-d') ?>" class="w-full p-2 border rounded">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">ملاحظات</label>
                    <textarea name="notes" rows="2" class="w-full p-2 border rounded"></textarea>
                </div>

                <button type="submit" name="add_expense" class="bg-red-600 text-white px-6 py-2 rounded hover:bg-red-700 font-bold w-full md:w-auto">تسجيل المصروف وإصدار سند صرف</button>
            </form>
        </div>

        <!-- Recent Transactions -->
        <div class="bg-white p-6 rounded shadow">
            <h2 class="text-xl font-bold mb-4">آخر الحركات المالية</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-right text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="p-2">التاريخ</th>
                            <th class="p-2">النوع</th>
                            <th class="p-2">التصنيف</th>
                            <th class="p-2">الصندوق</th>
                            <th class="p-2">المبلغ</th>
                            <th class="p-2">العملة</th>
                            <th class="p-2">المستند</th>
                            <th class="p-2">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_transactions as $rt): ?>
                        <tr class="border-b">
                            <td class="p-2"><?= date('Y-m-d', strtotime($rt['transaction_date'])) ?></td>
                            <td class="p-2">
                                <?php if ($rt['type'] == 'إيراد'): ?>
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">مدخول</span>
                                <?php else: ?>
                                    <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs">مصروف</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-2"><?= htmlspecialchars($rt['category']) ?></td>
                            <td class="p-2"><?= htmlspecialchars($rt['cashbox_name']) ?></td>
                            <td class="p-2 font-bold"><?= number_format($rt['amount'], 2) ?></td>
                            <td class="p-2"><?= htmlspecialchars($rt['currency_symbol']) ?></td>
                            <td class="p-2 text-gray-600 text-xs">
                                <?php 
                                    if ($rt['receipt_number']) echo htmlspecialchars($rt['receipt_number']);
                                    else if ($rt['voucher_number']) echo htmlspecialchars($rt['voucher_number']);
                                    else echo '-';
                                ?>
                            </td>
                            <td class="p-2">
                                <?php if ($rt['receipt_number']): ?>
                                    <a href="print_receipt.php?id=<?= $rt['id'] ?>" target="_blank" class="text-blue-600 hover:underline">طباعة إيصال</a>
                                <?php elseif ($rt['voucher_number']): ?>
                                    <a href="print_voucher.php?id=<?= $rt['id'] ?>" target="_blank" class="text-blue-600 hover:underline">طباعة سند</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recent_transactions)): ?>
                        <tr><td colspan="8" class="p-4 text-center text-gray-500">لا توجد حركات مالية مسجلة</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            document.getElementById('content-income').classList.add('hidden');
            document.getElementById('content-expense').classList.add('hidden');
            
            document.getElementById('tab-income').className = 'px-6 py-3 font-semibold text-gray-500 hover:text-indigo-600 bg-gray-50 border-b-2 border-transparent';
            document.getElementById('tab-expense').className = 'px-6 py-3 font-semibold text-gray-500 hover:text-indigo-600 bg-gray-50 border-b-2 border-transparent';

            document.getElementById('content-' + tabName).classList.remove('hidden');
            document.getElementById('tab-' + tabName).className = 'px-6 py-3 font-semibold text-indigo-600 bg-white border-b-2 border-indigo-600';
        }

        function filterCashboxes(currencyId, selectId) {
            const select = document.getElementById(selectId);
            const options = select.querySelectorAll('option');
            let firstMatch = null;
            
            options.forEach(opt => {
                if (opt.value === "") return; // Skip placeholder
                if (opt.getAttribute('data-currency') === currencyId) {
                    opt.classList.remove('hidden');
                    if (!firstMatch) firstMatch = opt;
                } else {
                    opt.classList.add('hidden');
                }
            });

            if (currencyId) {
                select.options[0].text = "اختر الصندوق...";
                if (firstMatch) select.value = firstMatch.value;
            } else {
                select.options[0].text = "اختر العملة أولاً...";
                select.value = "";
            }
        }

        function filterCommitteeBudgetItems(context) {
            const container = document.getElementById('content-' + context);
            const committeeId = container.querySelector('[name="committee_id"]').value;
            const currencyId = container.querySelector('[name="currency_id"]').value;
            const select = container.querySelector('.c-budget-item-select');
            
            if (!select) return;

            const options = select.querySelectorAll('option');
            let firstMatch = null;
            let count = 0;
            
            options.forEach(opt => {
                if (opt.value === "") return;
                
                if (committeeId && currencyId && opt.getAttribute('data-committee') === committeeId && opt.getAttribute('data-currency') === currencyId) {
                    opt.classList.remove('hidden');
                    count++;
                    if (!firstMatch) firstMatch = opt;
                } else {
                    opt.classList.add('hidden');
                }
            });

            if (committeeId && currencyId) {
                if (count > 0) {
                    select.options[0].text = "-- اختر البند --";
                } else {
                    select.options[0].text = "-- لا توجد بنود مطابقة --";
                }
            } else {
                select.options[0].text = "-- اختر اللجنة والعملة --";
            }
            select.value = "";
        }
    </script>
</body>
</html>
