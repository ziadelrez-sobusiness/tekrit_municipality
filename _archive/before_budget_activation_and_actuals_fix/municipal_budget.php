<?php
// modules/municipal_budget.php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth->requireLogin();
$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES 'utf8mb4'");

$user = $_SESSION['user'] ?? null;
$is_admin = ($user && isset($user['role']) && $user['role'] === 'admin');

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// --- Delete Budget (only if no linked transactions) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_budget'])) {
    $del_id = intval($_POST['budget_id']);
    // Safety: count linked transactions
    $chk = $db->prepare("
        SELECT COUNT(*) FROM financial_transactions
        WHERE municipal_budget_id = ?
           OR municipal_budget_line_id IN (SELECT id FROM municipal_budget_lines WHERE municipal_budget_id = ?)
    ");
    $chk->execute([$del_id, $del_id]);
    $linked_count = $chk->fetchColumn();

    if ($linked_count > 0) {
        $error = 'لا يمكن حذف هذه الموازنة لأنها مرتبطة بـ ' . $linked_count . ' حركة مالية. يمكن إلغاؤها فقط.';
    } else {
        try {
            $db->beginTransaction();
            $db->prepare("DELETE FROM municipal_budget_lines WHERE municipal_budget_id = ?")->execute([$del_id]);
            $db->prepare("DELETE FROM municipal_budgets WHERE id = ?")->execute([$del_id]);
            $db->commit();
            $success = 'تم حذف الموازنة ببنودها بنجاح.';
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'حدث خطأ أثناء الحذف: ' . $e->getMessage();
        }
    }
}

// --- Cancel Budget ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_budget'])) {
    $cancel_id = intval($_POST['budget_id']);
    try {
        $db->prepare("UPDATE municipal_budgets SET status='cancelled' WHERE id=?")->execute([$cancel_id]);
        $success = 'تم إلغاء الموازنة. يمكن الاطلاع عليها للمراجعة لكنها لن تظهر ضمن الحركات المالية الجديدة.';
    } catch (Exception $e) {
        $error = 'حدث خطأ: ' . $e->getMessage();
    }
}

// Create Budget
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_budget'])) {
    $fiscal_year = intval($_POST['fiscal_year']);
    $title = trim($_POST['title']);
    $template_id = intval($_POST['template_id']);
    
    if (!$fiscal_year || !$title || !$template_id) {
        $error = 'يرجى تعبئة جميع الحقول المطلوبة.';
    } else {
        try {
            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO municipal_budgets (fiscal_year, title, template_id, created_by_user_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$fiscal_year, $title, $template_id, $user['id'] ?? null]);
            $budget_id = $db->lastInsertId();
            
            $stmt_tpl = $db->prepare("SELECT * FROM municipal_budget_template_lines WHERE template_id = ? AND is_active = 1 ORDER BY sort_order");
            $stmt_tpl->execute([$template_id]);
            $lines = $stmt_tpl->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt_ins = $db->prepare("INSERT INTO municipal_budget_lines (municipal_budget_id, template_line_id, section_type, section_name, chapter_number, chapter_name, item_number, item_name, explanation, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($lines as $line) {
                $stmt_ins->execute([
                    $budget_id,
                    $line['id'],
                    $line['section_type'],
                    $line['section_name'],
                    $line['chapter_number'],
                    $line['chapter_name'],
                    $line['item_number'],
                    $line['item_name'],
                    $line['explanation'],
                    $line['sort_order']
                ]);
            }
            $db->commit();
            header("Location: municipal_budget.php?action=view&id=$budget_id&success=1");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error = 'يوجد موازنة لهذه السنة المالية بالفعل.';
            } else {
                $error = 'حدث خطأ: ' . $e->getMessage();
            }
        }
    }
}

// Add Custom Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_custom_item'])) {
    $budget_id = intval($_POST['budget_id']);
    $section_type = $_POST['section_type'];
    $section_name = $section_type === 'income' ? 'قسم الواردات' : 'قسم النفقات';
    $chapter_number = $_POST['chapter_number'];
    $chapter_name = $_POST['chapter_name'];
    $item_number = $_POST['item_number'];
    $item_name = $_POST['item_name'];
    $current_estimate = floatval($_POST['current_estimate']);
    $explanation = $_POST['explanation'];

    try {
        $stmt_ins = $db->prepare("INSERT INTO municipal_budget_lines (municipal_budget_id, section_type, section_name, chapter_number, chapter_name, item_number, item_name, current_estimate, explanation, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 999)");
        $stmt_ins->execute([$budget_id, $section_type, $section_name, $chapter_number, $chapter_name, $item_number, $item_name, $current_estimate, $explanation]);
        $success = 'تمت إضافة البند بنجاح.';
    } catch (Exception $e) {
        $error = 'حدث خطأ أثناء إضافة البند.';
    }
}

// Update Budget Lines
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_budget']) && isset($_POST['budget_id'])) {
    $budget_id = intval($_POST['budget_id']);
    $estimates = $_POST['estimates'] ?? [];
    $explanations = $_POST['explanations'] ?? [];
    $item_names = $_POST['item_names'] ?? [];
    $item_numbers = $_POST['item_numbers'] ?? [];
    $chapter_names = $_POST['chapter_names'] ?? [];
    $is_actives = $_POST['is_active'] ?? [];
    
    $status = $db->prepare("SELECT status FROM municipal_budgets WHERE id=?");
    $status->execute([$budget_id]);
    if ($status->fetchColumn() === 'closed' && !$is_admin) {
        $error = 'لا يمكن تعديل موازنة مغلقة.';
    } else {
        try {
            $db->beginTransaction();
            $upd_stmt = $db->prepare("UPDATE municipal_budget_lines SET current_estimate = ?, explanation = ?, item_name = ?, item_number = ?, chapter_name = ?, is_active = ? WHERE id = ? AND municipal_budget_id = ?");
            foreach ($estimates as $line_id => $val) {
                $est = floatval($val);
                $exp = $explanations[$line_id] ?? '';
                $name = $item_names[$line_id] ?? '';
                $num = $item_numbers[$line_id] ?? '';
                $cname = $chapter_names[$line_id] ?? '';
                $active_status = isset($is_actives[$line_id]) ? 1 : 0;
                $upd_stmt->execute([$est, $exp, $name, $num, $cname, $active_status, intval($line_id), $budget_id]);
            }
            $db->commit();
            $success = 'تم حفظ التعديلات بنجاح. المبالغ والمسميات مخصصة لهذه السنة فقط ولا تؤثر على القالب الرسمي.';
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'حدث خطأ أثناء الحفظ.';
        }
    }
}

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = 'تم إنشاء الموازنة بنجاح.';
}

// ------------------------------------------------------------------
// VIEW logic
// ------------------------------------------------------------------
if ($action === 'view' && isset($_GET['id'])) {
    $budget_id = intval($_GET['id']);
    
    $stmt = $db->prepare("SELECT * FROM municipal_budgets WHERE id = ?");
    $stmt->execute([$budget_id]);
    $budget = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$budget) die('الموازنة غير موجودة.');
    
    // Fetch actuals from financial_transactions with currency conversion handling
    $stmt_actuals = $db->query("
        SELECT ft.municipal_budget_line_id, 
               SUM(COALESCE(ft.budget_amount_lbp, CASE WHEN c.currency_symbol IN ('LBP', 'ل.ل.') THEN ft.amount ELSE 0 END)) as actual_sum,
               SUM(CASE WHEN c.currency_symbol NOT IN ('LBP', 'ل.ل.') AND ft.budget_amount_lbp IS NULL THEN 1 ELSE 0 END) as missing_rate_count
        FROM financial_transactions ft
        LEFT JOIN currencies c ON ft.currency_id = c.id
        WHERE ft.status NOT IN ('ملغى','cancelled') AND ft.municipal_budget_line_id IS NOT NULL 
        GROUP BY ft.municipal_budget_line_id
    ");
    $actuals = [];
    $missing_rates = [];
    while ($row = $stmt_actuals->fetch(PDO::FETCH_ASSOC)) {
        $actuals[$row['municipal_budget_line_id']] = floatval($row['actual_sum']);
        $missing_rates[$row['municipal_budget_line_id']] = intval($row['missing_rate_count']);
    }
    
    $stmt_lines = $db->prepare("SELECT * FROM municipal_budget_lines WHERE municipal_budget_id = ? ORDER BY section_type DESC, sort_order ASC, id ASC");
    $stmt_lines->execute([$budget_id]);
    $lines = $stmt_lines->fetchAll(PDO::FETCH_ASSOC);
    
    $grouped = ['income' => [], 'expense' => []];
    $totals = [
        'income_est' => 0, 'income_act' => 0,
        'expense_est' => 0, 'expense_act' => 0
    ];
    $has_missing_rates = false;
    
    foreach ($lines as $line) {
        $sec = $line['section_type'];
        $chap = $line['chapter_name'] ?: 'أخرى';
        if (!isset($grouped[$sec][$chap])) {
            $grouped[$sec][$chap] = ['lines' => [], 'est_sum' => 0, 'act_sum' => 0, 'chapter_number' => $line['chapter_number']];
        }
        
        $act = $actuals[$line['id']] ?? 0;
        $line['actual_amount'] = $act;
        $line['missing_rate'] = ($missing_rates[$line['id']] ?? 0) > 0;
        if ($line['missing_rate']) $has_missing_rates = true;
        
        $grouped[$sec][$chap]['lines'][] = $line;
        
        if ($line['is_active']) {
            $grouped[$sec][$chap]['est_sum'] += $line['current_estimate'];
            $grouped[$sec][$chap]['act_sum'] += $act;
            $totals[$sec.'_est'] += $line['current_estimate'];
            $totals[$sec.'_act'] += $act;
        }
    }
    
    $is_print = isset($_GET['print']);
    if ($is_print) {
        require 'municipal_budget_print.php';
        exit;
    }
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>تفاصيل الموازنة البلدية</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Cairo', sans-serif; }
    .inactive-row { opacity: 0.6; background: #f9f9f9; }
</style>
<script>
    function renameChapter(section, oldName) {
        let newName = prompt("أدخل اسم الباب الجديد:", oldName);
        if (newName && newName.trim() !== "") {
            document.querySelectorAll('.chapter-input-' + section).forEach(el => {
                if (el.value === oldName) el.value = newName.trim();
            });
            alert("تم تغيير اسم الباب في الحقول. يجب الضغط على 'حفظ التعديلات' لتطبيق التغيير.");
        }
    }
    function showAddItem(section, defaultChapter) {
        document.getElementById('add_item_section_type').value = section;
        document.getElementById('add_item_chapter_name').value = defaultChapter;
        document.getElementById('add_item_modal').classList.remove('hidden');
    }
</script>
</head>
<body class="bg-slate-100 p-4 text-slate-800">
<div class="max-w-full mx-auto px-4">
    <div class="flex justify-between items-center mb-4 bg-white p-4 rounded shadow border-l-4 border-indigo-600">
        <div>
            <h1 class="text-2xl font-bold">الموازنة البلدية - <?= htmlspecialchars($budget['title']) ?> (<?= $budget['fiscal_year'] ?>)</h1>
            <p class="text-gray-500 text-sm mt-1">الحالة: <span class="font-bold"><?= $budget['status'] ?></span> | الموازنة البلدية الرسمية تُعدّ <span class="font-bold text-red-600">بالليرة اللبنانية</span> فقط.</p>
        </div>
        <div class="flex gap-2">
            <a href="?action=view&id=<?= $budget_id ?>&print=1" target="_blank" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">🖨️ طباعة الموازنة</a>
            <a href="municipal_budget.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">عودة للقائمة</a>
        </div>
    </div>
    
    <?php if($error): ?><div class="bg-red-100 text-red-700 p-3 mb-4 rounded shadow"><?= $error ?></div><?php endif; ?>
    <?php if($success): ?><div class="bg-green-100 text-green-700 p-3 mb-4 rounded shadow"><?= $success ?></div><?php endif; ?>
    <?php if($has_missing_rates): ?><div class="bg-yellow-100 text-yellow-800 p-3 mb-4 rounded shadow font-bold">⚠️ تنبيه: بعض الحركات المرتبطة بهذه الموازنة مسجلة بالدولار ولا يوجد لها سعر صرف مخزن. لم يتم احتسابها ضمن المجموع الفعلي (ل.ل.).</div><?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-green-50 p-4 rounded shadow border border-green-200 text-center">
            <div class="text-sm font-bold text-green-700">مجموع الواردات المقدرة (ل.ل.)</div>
            <div class="text-xl font-bold text-green-800" dir="ltr"><?= number_format($totals['income_est'], 2) ?></div>
            <div class="text-xs text-gray-500 mt-1">الفعلي المحصّل (ل.ل.): <span dir="ltr"><?= number_format($totals['income_act'], 2) ?></span></div>
        </div>
        <div class="bg-red-50 p-4 rounded shadow border border-red-200 text-center">
            <div class="text-sm font-bold text-red-700">مجموع النفقات المقدرة (ل.ل.)</div>
            <div class="text-xl font-bold text-red-800" dir="ltr"><?= number_format($totals['expense_est'], 2) ?></div>
            <div class="text-xs text-gray-500 mt-1">الفعلي المصروف (ل.ل.): <span dir="ltr"><?= number_format($totals['expense_act'], 2) ?></span></div>
        </div>
        <?php $net_est = $totals['income_est'] - $totals['expense_est']; $net_act = $totals['income_act'] - $totals['expense_act']; ?>
        <div class="bg-blue-50 p-4 rounded shadow border border-blue-200 text-center">
            <div class="text-sm font-bold text-blue-700">الفرق المقدر (فائض / عجز)</div>
            <div class="text-xl font-bold <?= $net_est>=0?'text-green-700':'text-red-700' ?>" dir="ltr"><?= number_format($net_est, 2) ?></div>
            <div class="text-xs text-gray-500 mt-1">الفرق الفعلي: <span dir="ltr" class="<?= $net_act>=0?'text-green-600':'text-red-600' ?>"><?= number_format($net_act, 2) ?></span></div>
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="budget_id" value="<?= $budget_id ?>">
        
        <?php foreach (['income' => 'قسم الواردات', 'expense' => 'قسم النفقات'] as $sec_key => $sec_title): ?>
        <div class="mb-8 bg-white p-4 rounded shadow">
            <div class="flex justify-between items-center mb-4 border-b-2 border-indigo-200 pb-2">
                <h2 class="text-xl font-bold text-indigo-800"><?= $sec_title ?></h2>
                <button type="button" onclick="showAddItem('<?= $sec_key ?>', '')" class="bg-indigo-100 text-indigo-800 px-3 py-1 rounded text-sm hover:bg-indigo-200 font-bold">+ إضافة باب / بند جديد</button>
            </div>
            
            <?php if (empty($grouped[$sec_key])): ?>
                <p class="text-gray-500 text-sm">لا يوجد بنود.</p>
            <?php else: ?>
                <?php foreach ($grouped[$sec_key] as $chap_name => $chap_data): ?>
                <div class="mb-6 border rounded overflow-hidden">
                    <div class="bg-gray-200 p-2 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-gray-800"><?= htmlspecialchars($chap_name) ?></h3>
                        <div>
                            <button type="button" onclick="renameChapter('<?= $sec_key ?>', '<?= htmlspecialchars(addslashes($chap_name)) ?>')" class="text-xs bg-gray-300 px-2 py-1 rounded hover:bg-gray-400">تعديل اسم الباب</button>
                            <button type="button" onclick="showAddItem('<?= $sec_key ?>', '<?= htmlspecialchars(addslashes($chap_name)) ?>')" class="text-xs bg-gray-300 px-2 py-1 rounded hover:bg-gray-400">+ بند</button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-right text-sm border-collapse">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="border p-2 w-10">فعال</th>
                                    <th class="border p-2 w-16">الفصل</th>
                                    <th class="border p-2 min-w-[200px]"><?= $sec_key==='income'?'نوع الواردات':'نوع النفقات' ?></th>
                                    <th class="border p-2 hidden">الباب المخفي</th>
                                    <th class="border p-2 w-32">التقدير (ل.ل.)</th>
                                    <th class="border p-2 w-32">الفعلي (ل.ل.)</th>
                                    <th class="border p-2 w-32">الفارق</th>
                                    <th class="border p-2 min-w-[200px]">شرح البند</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($chap_data['lines'] as $line): 
                                    $variance = $sec_key === 'income' ? ($line['actual_amount'] - $line['current_estimate']) : ($line['current_estimate'] - $line['actual_amount']);
                                    $var_color = $variance < 0 ? 'text-red-600' : 'text-green-600';
                                    $is_active = $line['is_active'];
                                ?>
                                <tr class="hover:bg-gray-50 border-b <?= !$is_active?'inactive-row':'' ?>">
                                    <td class="p-2 text-center">
                                        <input type="checkbox" name="is_active[<?= $line['id'] ?>]" value="1" <?= $is_active?'checked':'' ?> title="تفعيل/تعطيل البند">
                                    </td>
                                    <td class="p-1">
                                        <input type="text" name="item_numbers[<?= $line['id'] ?>]" value="<?= htmlspecialchars($line['item_number'] ?? '') ?>" class="w-full p-1 border rounded text-center text-xs">
                                    </td>
                                    <td class="p-1">
                                        <input type="text" name="item_names[<?= $line['id'] ?>]" value="<?= htmlspecialchars($line['item_name']) ?>" class="w-full p-1 border rounded font-bold text-xs" required>
                                    </td>
                                    <td class="p-1 hidden">
                                        <input type="hidden" name="chapter_names[<?= $line['id'] ?>]" value="<?= htmlspecialchars($line['chapter_name']) ?>" class="chapter-input-<?= $sec_key ?>">
                                    </td>
                                    <td class="p-1">
                                        <input type="number" step="1" name="estimates[<?= $line['id'] ?>]" value="<?= floatval($line['current_estimate']) ?>" class="w-full p-1 border rounded text-left font-mono" dir="ltr">
                                    </td>
                                    <td class="p-2 text-left font-bold" dir="ltr">
                                        <?= number_format($line['actual_amount'], 0) ?>
                                        <?php if($line['missing_rate']): ?><span title="يوجد حركات بالدولار بلا سعر صرف" class="text-red-500 cursor-pointer">⚠️</span><?php endif; ?>
                                    </td>
                                    <td class="p-2 text-left font-bold <?= $var_color ?>" dir="ltr"><?= number_format($variance, 0) ?></td>
                                    <td class="p-1">
                                        <input type="text" name="explanations[<?= $line['id'] ?>]" value="<?= htmlspecialchars($line['explanation'] ?? '') ?>" class="w-full p-1 border rounded text-xs">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-gray-50 font-bold">
                                <tr>
                                    <td colspan="4" class="border p-2 text-left">مجموع <?= htmlspecialchars($chap_name) ?></td>
                                    <td class="border p-2 text-left text-blue-700" dir="ltr"><?= number_format($chap_data['est_sum'], 0) ?></td>
                                    <td class="border p-2 text-left text-blue-700" dir="ltr"><?= number_format($chap_data['act_sum'], 0) ?></td>
                                    <td class="border p-2 text-left text-blue-700" dir="ltr"><?= number_format($sec_key === 'income' ? ($chap_data['act_sum'] - $chap_data['est_sum']) : ($chap_data['est_sum'] - $chap_data['act_sum']), 0) ?></td>
                                    <td class="border p-2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        
        <?php if ($budget['status'] !== 'closed' || $is_admin): ?>
        <div class="fixed bottom-0 left-0 w-full bg-white border-t border-gray-300 p-4 shadow-[0_-5px_10px_rgba(0,0,0,0.1)] flex justify-center z-40">
            <button type="submit" name="update_budget" class="bg-blue-600 text-white px-8 py-2 rounded text-lg font-bold hover:bg-blue-700 shadow transform transition hover:scale-105">💾 حفظ جميع التعديلات</button>
            <span class="text-gray-500 text-sm mr-4 mt-2">التعديلات تطبق على هذه الموازنة فقط ولا تغيّر القالب الرسمي الأساسي.</span>
        </div>
        <?php endif; ?>
    </form>
    <div class="h-20"></div>
</div>

<!-- Modal for adding custom item -->
<div id="add_item_modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded shadow-lg w-full max-w-lg">
        <h2 class="text-xl font-bold mb-4">إضافة باب / بند جديد</h2>
        <form method="POST">
            <input type="hidden" name="budget_id" value="<?= $budget_id ?>">
            <input type="hidden" name="section_type" id="add_item_section_type" value="">
            <div class="mb-3">
                <label class="block text-sm font-bold mb-1">اسم الباب (لإضافة باب جديد، اكتب اسماً جديداً)</label>
                <input type="text" name="chapter_name" id="add_item_chapter_name" class="w-full p-2 border rounded" required>
            </div>
            <div class="grid grid-cols-2 gap-2 mb-3">
                <div>
                    <label class="block text-sm font-bold mb-1">رقم الباب (اختياري)</label>
                    <input type="text" name="chapter_number" class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-bold mb-1">الفصل (رقم البند)</label>
                    <input type="text" name="item_number" class="w-full p-2 border rounded">
                </div>
            </div>
            <div class="mb-3">
                <label class="block text-sm font-bold mb-1">نوع النفقات/الواردات (اسم البند)</label>
                <input type="text" name="item_name" class="w-full p-2 border rounded" required>
            </div>
            <div class="mb-3">
                <label class="block text-sm font-bold mb-1">التقدير (ل.ل.)</label>
                <input type="number" name="current_estimate" step="1" value="0" class="w-full p-2 border rounded" required dir="ltr">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-bold mb-1">الشرح</label>
                <input type="text" name="explanation" class="w-full p-2 border rounded">
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('add_item_modal').classList.add('hidden')" class="bg-gray-400 text-white px-4 py-2 rounded">إلغاء</button>
                <button type="submit" name="add_custom_item" class="bg-indigo-600 text-white px-4 py-2 rounded font-bold">إضافة البند</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
<?php
    exit;
}
// ------------------------------------------------------------------
// LIST logic
// ------------------------------------------------------------------
$templates = $db->query("SELECT * FROM municipal_budget_templates WHERE is_active=1 ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->query("
    SELECT b.*, 
        COALESCE(SUM(CASE WHEN l.section_type='income' AND l.is_active=1 THEN l.current_estimate ELSE 0 END), 0) as total_income,
        COALESCE(SUM(CASE WHEN l.section_type='expense' AND l.is_active=1 THEN l.current_estimate ELSE 0 END), 0) as total_expense
    FROM municipal_budgets b
    LEFT JOIN municipal_budget_lines l ON b.id = l.municipal_budget_id
    GROUP BY b.id
    ORDER BY b.fiscal_year DESC
");
$budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>الموازنة البلدية</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<style>body { font-family: 'Cairo', sans-serif; }</style>
</head>
<body class="bg-slate-100 p-4 text-slate-800">
<div class="max-w-6xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-slate-800">الموازنة البلدية الرسمية</h1>
        <a href="../comprehensive_dashboard.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">الرئيسية</a>
    </div>

    <?php if($error): ?><div class="bg-red-100 text-red-700 p-3 mb-4 rounded shadow"><?= $error ?></div><?php endif; ?>

    <div class="bg-white p-6 rounded shadow mb-6 border-t-4 border-indigo-600">
        <h2 class="text-xl font-bold mb-4 border-b pb-2">إنشاء موازنة جديدة</h2>
        <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label class="block text-sm font-bold mb-1">السنة المالية</label>
                <input type="number" name="fiscal_year" value="<?= date('Y')+1 ?>" class="w-full p-2 border rounded" required>
            </div>
            <div>
                <label class="block text-sm font-bold mb-1">الاسم / العنوان</label>
                <input type="text" name="title" value="موازنة عام <?= date('Y')+1 ?>" class="w-full p-2 border rounded" required>
            </div>
            <div>
                <label class="block text-sm font-bold mb-1">النموذج المعتمد</label>
                <select name="template_id" class="w-full p-2 border rounded" required>
                    <?php foreach($templates as $tpl): ?>
                        <option value="<?= $tpl['id'] ?>"><?= htmlspecialchars($tpl['template_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" name="create_budget" class="w-full bg-indigo-600 text-white px-4 py-2 rounded font-bold hover:bg-indigo-700">إنشاء واستيراد البنود</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="w-full text-right">
            <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="p-3">السنة</th>
                    <th class="p-3">العنوان</th>
                    <th class="p-3">الواردات (ل.ل)</th>
                    <th class="p-3">النفقات (ل.ل)</th>
                    <th class="p-3">الرصيد (ل.ل)</th>
                    <th class="p-3">الحركات</th>
                    <th class="p-3">الحالة</th>
                    <th class="p-3">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($budgets)): ?>
                <tr><td colspan="8" class="p-4 text-center text-gray-500">لا يوجد موازنات. قم بإنشاء موازنة جديدة.</td></tr>
                <?php endif; ?>
                <?php foreach($budgets as $b):
                    $net = $b['total_income'] - $b['total_expense'];
                    // Count linked transactions for this budget
                    $lt = $db->prepare("SELECT COUNT(*) FROM financial_transactions WHERE municipal_budget_id = ? OR municipal_budget_line_id IN (SELECT id FROM municipal_budget_lines WHERE municipal_budget_id = ?)");
                    $lt->execute([$b['id'], $b['id']]);
                    $linked_tx = $lt->fetchColumn();
                ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-3 font-bold"><?= $b['fiscal_year'] ?></td>
                    <td class="p-3"><?= htmlspecialchars($b['title']) ?></td>
                    <td class="p-3 text-green-600 font-bold" dir="ltr"><?= number_format($b['total_income'], 0) ?></td>
                    <td class="p-3 text-red-600 font-bold" dir="ltr"><?= number_format($b['total_expense'], 0) ?></td>
                    <td class="p-3 font-bold <?= $net>=0?'text-green-700':'text-red-700' ?>" dir="ltr"><?= number_format($net, 0) ?></td>
                    <td class="p-3 text-center">
                        <?php if ($linked_tx > 0): ?>
                            <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded text-xs font-bold"><?= $linked_tx ?> حركة</span>
                        <?php else: ?>
                            <span class="text-gray-400 text-xs">لا يوجد</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3">
                        <span class="px-2 py-1 rounded text-xs <?= $b['status']==='draft'?'bg-yellow-100 text-yellow-800':($b['status']==='approved'?'bg-green-100 text-green-800':($b['status']==='cancelled'?'bg-red-100 text-red-800':'bg-gray-100 text-gray-800')) ?>">
                            <?= $b['status'] ?>
                        </span>
                    </td>
                    <td class="p-3">
                        <div class="flex flex-wrap gap-1">
                            <a href="?action=view&id=<?= $b['id'] ?>" class="bg-blue-100 text-blue-700 px-2 py-1 rounded hover:bg-blue-200 font-bold text-xs">عرض/تعديل</a>
                            <a href="?action=view&id=<?= $b['id'] ?>&print=1" target="_blank" class="bg-indigo-100 text-indigo-700 px-2 py-1 rounded hover:bg-indigo-200 font-bold text-xs">طباعة</a>
                            <?php if ($b['status'] !== 'cancelled'): ?>
                            <form method="POST" class="inline" onsubmit="return confirm('هل تريد إلغاء هذه الموازنة؟ لن يتم حذفها.');">
                                <input type="hidden" name="budget_id" value="<?= $b['id'] ?>">
                                <button type="submit" name="cancel_budget" class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded hover:bg-yellow-200 font-bold text-xs">إلغاء</button>
                            </form>
                            <?php endif; ?>
                            <?php if ($linked_tx == 0 && $b['status'] !== 'cancelled'): ?>
                            <form method="POST" class="inline" onsubmit="return confirm('سيتم حذف هذه الموازنة وجميع بنودها نهائياً. هل أنت متأكد؟');">
                                <input type="hidden" name="budget_id" value="<?= $b['id'] ?>">
                                <button type="submit" name="delete_budget" class="bg-red-100 text-red-700 px-2 py-1 rounded hover:bg-red-200 font-bold text-xs">حذف</button>
                            </form>
                            <?php elseif ($linked_tx > 0): ?>
                            <span class="text-xs text-gray-400 italic" title="لا يمكن الحذف - يوجد حركات مالية مرتبطة">🔒 محمي</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
