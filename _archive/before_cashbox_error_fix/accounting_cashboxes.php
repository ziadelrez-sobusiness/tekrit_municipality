<?php
// modules/accounting_cashboxes.php

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

// Handle Toggle Active
if (isset($_GET['toggle']) && intval($_GET['toggle']) > 0) {
    if (!csrf_protect(false)) {
        $error = 'خطأ أمني. يرجى تحديث الصفحة.';
    } else {
        $id = intval($_GET['toggle']);
        $db->query("UPDATE accounting_cashboxes SET is_active = NOT is_active WHERE id = $id");
        header("Location: accounting_cashboxes.php?msg=toggled");
        exit;
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'toggled') {
    $message = 'تم تغيير حالة الصندوق بنجاح.';
}

// Handle Form Submissions (Add / Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_protect(false)) {
        $error = 'خطأ أمني. يرجى تحديث الصفحة.';
    } else {
        $name = trim($_POST['name']);
        $type = $_POST['type'];
        $notes = trim($_POST['notes']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (isset($_POST['add_cashbox'])) {
            $currency_id = intval($_POST['currency_id']);
            $opening_balance = floatval($_POST['opening_balance']);
            
            if ($name && $currency_id > 0 && $opening_balance >= 0) {
                // Check for duplicates
                $check = $db->prepare("SELECT id FROM accounting_cashboxes WHERE name=? AND currency_id=? AND is_active=1");
                $check->execute([$name, $currency_id]);
                if ($check->rowCount() > 0) {
                    $error = 'يوجد صندوق نشط بنفس الاسم والعملة.';
                } else {
                    $stmt = $db->prepare("INSERT INTO accounting_cashboxes (name, type, currency_id, opening_balance, current_balance, notes, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt->execute([$name, $type, $currency_id, $opening_balance, $opening_balance, $notes, $is_active])) {
                        $message = 'تم إضافة الصندوق بنجاح.';
                    } else {
                        $error = 'خطأ أثناء الإضافة.';
                    }
                }
            } else {
                $error = 'يرجى تعبئة جميع الحقول المطلوبة بشكل صحيح.';
            }
        } elseif (isset($_POST['edit_cashbox'])) {
            $id = intval($_POST['edit_id']);
            
            // Check if transactions exist
            $txn_check = $db->prepare("SELECT COUNT(*) FROM financial_transactions WHERE cashbox_id = ?");
            $txn_check->execute([$id]);
            $has_transactions = ($txn_check->fetchColumn() > 0);
            
            if ($name) {
                if (!$has_transactions && isset($_POST['opening_balance'])) {
                    $opening_balance = floatval($_POST['opening_balance']);
                    if ($opening_balance >= 0) {
                        $stmt = $db->prepare("UPDATE accounting_cashboxes SET name=?, type=?, opening_balance=?, current_balance=?, notes=?, is_active=? WHERE id=?");
                        $stmt->execute([$name, $type, $opening_balance, $opening_balance, $notes, $is_active, $id]);
                        $message = 'تم تعديل بيانات الصندوق والرصيد الافتتاحي بنجاح.';
                    } else {
                        $error = 'الرصيد الافتتاحي يجب أن يكون أكبر من أو يساوي 0.';
                    }
                } else {
                    // Just update non-balance fields
                    $stmt = $db->prepare("UPDATE accounting_cashboxes SET name=?, type=?, notes=?, is_active=? WHERE id=?");
                    $stmt->execute([$name, $type, $notes, $is_active, $id]);
                    $message = 'تم تعديل بيانات الصندوق بنجاح.';
                }
            } else {
                $error = 'الاسم مطلوب.';
            }
        }
    }
}

// Fetch lists
$currencies = $db->query("SELECT id, currency_name, currency_symbol FROM currencies WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);

// Fetch cashboxes
$cashboxes = $db->query("
    SELECT cb.*, c.currency_symbol, c.currency_name 
    FROM accounting_cashboxes cb
    JOIN currencies c ON cb.currency_id = c.id
    ORDER BY cb.is_active DESC, cb.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$edit_cashbox = null;
$edit_has_transactions = false;
if (isset($_GET['edit_id'])) {
    $stmt = $db->prepare("SELECT * FROM accounting_cashboxes WHERE id = ?");
    $stmt->execute([intval($_GET['edit_id'])]);
    $edit_cashbox = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($edit_cashbox) {
        $txn_check = $db->prepare("SELECT COUNT(*) FROM financial_transactions WHERE cashbox_id = ?");
        $txn_check->execute([$edit_cashbox['id']]);
        $edit_has_transactions = ($txn_check->fetchColumn() > 0);
    }
}

function getTypeLabel($type) {
    if ($type === 'cash') return 'نقدي';
    if ($type === 'bank') return 'حساب بنكي';
    return 'أخرى';
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إدارة الصناديق</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Cairo', sans-serif; }</style>
</head>
<body class="bg-slate-100 p-6 text-slate-800">
    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-slate-800">إدارة الصناديق</h1>
            <a href="../comprehensive_dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">العودة للوحة التحكم</a>
        </div>

        <div class="bg-blue-50 border-r-4 border-blue-500 text-blue-800 p-4 mb-6 rounded shadow-sm">
            <h3 class="font-bold mb-1">ℹ️ توضيح عن الصناديق:</h3>
            <p class="text-sm leading-relaxed">
                الصندوق هو المكان الذي تدخل إليه أو تخرج منه الأموال، مثل صندوق البلدية بالليرة أو صندوق البلدية بالدولار أو حساب بنك. كل صندوق مرتبط بعملة واحدة فقط، ولا يجوز خلط العملات. الرصيد الحالي يتغير تلقائيًا عند تسجيل مدخول أو مصروف.
            </p>
        </div>

        <?php if ($message): ?><div class="bg-green-100 text-green-800 p-4 rounded mb-4 border border-green-200"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="bg-red-100 text-red-800 p-4 rounded mb-4 border border-red-200"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <!-- Form -->
            <div class="md:col-span-1 bg-white p-6 rounded shadow h-fit">
                <?php if ($edit_cashbox): ?>
                    <h2 class="text-xl font-bold mb-4">تعديل صندوق</h2>
                    <?php if ($edit_has_transactions): ?>
                        <div class="bg-yellow-50 text-yellow-800 p-3 text-xs rounded mb-4 border border-yellow-200">
                            ⚠️ لا يمكن تعديل الرصيد الافتتاحي بعد وجود حركات مالية على هذا الصندوق. يمكن إضافة حركة تصحيحية من صفحة الحركة المالية.
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="accounting_cashboxes.php">
                        <?php echo csrf_input('csrf_token'); ?>
                        <input type="hidden" name="edit_id" value="<?= $edit_cashbox['id'] ?>">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">اسم الصندوق <span class="text-red-500">*</span></label>
                            <input type="text" name="name" value="<?= htmlspecialchars($edit_cashbox['name']) ?>" required class="w-full p-2 border rounded">
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">النوع</label>
                            <select name="type" class="w-full p-2 border rounded">
                                <option value="cash" <?= $edit_cashbox['type'] == 'cash' ? 'selected' : '' ?>>نقدي</option>
                                <option value="bank" <?= $edit_cashbox['type'] == 'bank' ? 'selected' : '' ?>>حساب بنكي</option>
                                <option value="other" <?= $edit_cashbox['type'] == 'other' ? 'selected' : '' ?>>أخرى</option>
                            </select>
                        </div>

                        <?php if (!$edit_has_transactions): ?>
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-1">الرصيد الافتتاحي</label>
                                <input type="number" step="0.01" name="opening_balance" value="<?= $edit_cashbox['opening_balance'] ?>" required class="w-full p-2 border rounded">
                            </div>
                        <?php else: ?>
                            <div class="mb-4 text-gray-500">
                                <label class="block text-sm font-medium mb-1">الرصيد الافتتاحي (مغلق)</label>
                                <input type="text" value="<?= number_format($edit_cashbox['opening_balance'], 2) ?>" disabled class="w-full p-2 border rounded bg-gray-100 cursor-not-allowed">
                            </div>
                        <?php endif; ?>

                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">ملاحظات</label>
                            <textarea name="notes" rows="2" class="w-full p-2 border rounded"><?= htmlspecialchars($edit_cashbox['notes']) ?></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_active" <?= $edit_cashbox['is_active'] ? 'checked' : '' ?> class="ml-2">
                                نشط
                            </label>
                        </div>
                        
                        <div class="flex gap-2">
                            <button type="submit" name="edit_cashbox" class="flex-1 bg-yellow-500 text-white p-2 rounded hover:bg-yellow-600 font-bold">حفظ التعديلات</button>
                            <a href="accounting_cashboxes.php" class="bg-gray-200 text-gray-800 p-2 rounded hover:bg-gray-300">إلغاء</a>
                        </div>
                    </form>

                <?php else: ?>
                    <h2 class="text-xl font-bold mb-4">إضافة صندوق جديد</h2>
                    <form method="POST" action="accounting_cashboxes.php">
                        <?php echo csrf_input('csrf_token'); ?>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">اسم الصندوق <span class="text-red-500">*</span></label>
                            <input type="text" name="name" placeholder="مثال: صندوق التبرعات" required class="w-full p-2 border rounded">
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">النوع <span class="text-red-500">*</span></label>
                            <select name="type" required class="w-full p-2 border rounded">
                                <option value="cash">نقدي</option>
                                <option value="bank">حساب بنكي</option>
                                <option value="other">أخرى</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">العملة <span class="text-red-500">*</span></label>
                            <select name="currency_id" required class="w-full p-2 border rounded">
                                <option value="">اختر العملة...</option>
                                <?php foreach($currencies as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= $c['currency_name'] ?> (<?= $c['currency_symbol'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">الرصيد الافتتاحي <span class="text-red-500">*</span></label>
                            <input type="number" step="0.01" name="opening_balance" value="0.00" required class="w-full p-2 border rounded">
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">ملاحظات</label>
                            <textarea name="notes" rows="2" class="w-full p-2 border rounded"></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_active" checked class="ml-2">
                                نشط
                            </label>
                        </div>
                        
                        <button type="submit" name="add_cashbox" class="w-full bg-blue-600 text-white p-2 rounded hover:bg-blue-700 font-bold">إضافة الصندوق</button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- List -->
            <div class="md:col-span-2 bg-white p-6 rounded shadow">
                <h2 class="text-xl font-bold mb-4">الصناديق الحالية</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-sm">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="p-2">الصندوق</th>
                                <th class="p-2">النوع</th>
                                <th class="p-2">الرصيد الافتتاحي</th>
                                <th class="p-2">الرصيد الحالي</th>
                                <th class="p-2">الحالة</th>
                                <th class="p-2">تاريخ الإنشاء</th>
                                <th class="p-2">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($cashboxes as $cb): ?>
                            <tr class="border-b hover:bg-gray-50 <?= !$cb['is_active'] ? 'opacity-50' : '' ?>">
                                <td class="p-2">
                                    <div class="font-bold text-indigo-700"><?= htmlspecialchars($cb['name']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($cb['notes']) ?></div>
                                </td>
                                <td class="p-2 text-xs"><?= getTypeLabel($cb['type']) ?></td>
                                <td class="p-2 text-gray-500" dir="ltr"><?= number_format($cb['opening_balance'], 2) ?> <?= $cb['currency_symbol'] ?></td>
                                <td class="p-2 font-bold" dir="ltr"><?= number_format($cb['current_balance'], 2) ?> <?= $cb['currency_symbol'] ?></td>
                                <td class="p-2">
                                    <?= $cb['is_active'] ? '<span class="text-green-600 text-xs font-bold">نشط</span>' : '<span class="text-red-600 text-xs">معطل</span>' ?>
                                </td>
                                <td class="p-2 text-xs text-gray-400"><?= date('Y-m-d', strtotime($cb['created_at'])) ?></td>
                                <td class="p-2 flex gap-2 text-xs">
                                    <a href="?edit_id=<?= $cb['id'] ?>" class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded hover:bg-yellow-200">تعديل</a>
                                    <?php 
                                        $toggle_token = csrf_token_html(false);
                                        preg_match('/value="([^"]+)"/', $toggle_token, $matches);
                                        $token_val = $matches[1] ?? '';
                                    ?>
                                    <a href="?toggle=<?= $cb['id'] ?>&csrf_token=<?= urlencode($token_val) ?>" class="bg-gray-200 text-gray-800 px-2 py-1 rounded hover:bg-gray-300" onclick="return confirm('تغيير حالة هذا الصندوق؟')">
                                        <?= $cb['is_active'] ? 'تعطيل' : 'تفعيل' ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</body>
</html>
