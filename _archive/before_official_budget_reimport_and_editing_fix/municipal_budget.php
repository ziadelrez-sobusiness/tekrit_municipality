<?php
// modules/municipal_budget.php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth->requireLogin();
$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES 'utf8mb4'");

// Optional: Admin override logic
$user = $_SESSION['user'] ?? null;
$is_admin = ($user && isset($user['role']) && $user['role'] === 'admin');

// Handle actions
$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

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
            
            // Insert budget year
            $stmt = $db->prepare("INSERT INTO municipal_budgets (fiscal_year, title, template_id, created_by_user_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$fiscal_year, $title, $template_id, $user['id'] ?? null]);
            $budget_id = $db->lastInsertId();
            
            // Insert lines from template
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

// Update Budget Lines
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_budget']) && isset($_POST['budget_id'])) {
    $budget_id = intval($_POST['budget_id']);
    $estimates = $_POST['estimates'] ?? [];
    $explanations = $_POST['explanations'] ?? [];
    
    // Check status
    $status = $db->prepare("SELECT status FROM municipal_budgets WHERE id=?");
    $status->execute([$budget_id]);
    if ($status->fetchColumn() === 'closed' && !$is_admin) {
        $error = 'لا يمكن تعديل موازنة مغلقة.';
    } else {
        try {
            $db->beginTransaction();
            $upd = $db->prepare("UPDATE municipal_budget_lines SET current_estimate = ?, explanation = ? WHERE id = ? AND municipal_budget_id = ?");
            foreach ($estimates as $line_id => $val) {
                $est = floatval($val);
                $exp = $explanations[$line_id] ?? '';
                $upd->execute([$est, $exp, intval($line_id), $budget_id]);
            }
            $db->commit();
            $success = 'تم حفظ التعديلات بنجاح.';
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
    
    // Fetch actuals from financial_transactions
    // This query sums active transactions linked to each line
    $stmt_actuals = $db->query("SELECT municipal_budget_line_id, SUM(amount) as actual_sum FROM financial_transactions WHERE status NOT IN ('ملغى','cancelled') AND municipal_budget_line_id IS NOT NULL GROUP BY municipal_budget_line_id");
    $actuals = [];
    while ($row = $stmt_actuals->fetch(PDO::FETCH_ASSOC)) {
        $actuals[$row['municipal_budget_line_id']] = floatval($row['actual_sum']);
    }
    
    $stmt_lines = $db->prepare("SELECT * FROM municipal_budget_lines WHERE municipal_budget_id = ? AND is_active = 1 ORDER BY section_type DESC, sort_order ASC, id ASC");
    $stmt_lines->execute([$budget_id]);
    $lines = $stmt_lines->fetchAll(PDO::FETCH_ASSOC);
    
    $grouped = ['income' => [], 'expense' => []];
    $totals = [
        'income_est' => 0, 'income_act' => 0,
        'expense_est' => 0, 'expense_act' => 0
    ];
    
    foreach ($lines as $line) {
        $sec = $line['section_type'];
        $chap = $line['chapter_name'] ?: 'أخرى';
        if (!isset($grouped[$sec][$chap])) {
            $grouped[$sec][$chap] = ['lines' => [], 'est_sum' => 0, 'act_sum' => 0];
        }
        
        $act = $actuals[$line['id']] ?? 0;
        $line['actual_amount'] = $act;
        
        $grouped[$sec][$chap]['lines'][] = $line;
        $grouped[$sec][$chap]['est_sum'] += $line['current_estimate'];
        $grouped[$sec][$chap]['act_sum'] += $act;
        
        $totals[$sec.'_est'] += $line['current_estimate'];
        $totals[$sec.'_act'] += $act;
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
<style>body { font-family: 'Cairo', sans-serif; }</style>
</head>
<body class="bg-slate-100 p-4 text-slate-800">
<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-4">
        <div>
            <h1 class="text-2xl font-bold">الموازنة البلدية - <?= htmlspecialchars($budget['title']) ?> (<?= $budget['fiscal_year'] ?>)</h1>
            <p class="text-gray-500 text-sm">الحالة: <?= $budget['status'] ?></p>
        </div>
        <div class="flex gap-2">
            <a href="?action=view&id=<?= $budget_id ?>&print=1" target="_blank" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">🖨️ طباعة الموازنة</a>
            <a href="municipal_budget.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">عودة للقائمة</a>
        </div>
    </div>
    
    <?php if($error): ?><div class="bg-red-100 text-red-700 p-3 mb-4 rounded"><?= $error ?></div><?php endif; ?>
    <?php if($success): ?><div class="bg-green-100 text-green-700 p-3 mb-4 rounded"><?= $success ?></div><?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-green-50 p-4 rounded shadow border border-green-200 text-center">
            <div class="text-sm font-bold text-green-700">مجموع الواردات المقدرة</div>
            <div class="text-xl font-bold text-green-800" dir="ltr"><?= number_format($totals['income_est'], 2) ?></div>
            <div class="text-xs text-gray-500 mt-1">الفعلي: <span dir="ltr"><?= number_format($totals['income_act'], 2) ?></span></div>
        </div>
        <div class="bg-red-50 p-4 rounded shadow border border-red-200 text-center">
            <div class="text-sm font-bold text-red-700">مجموع النفقات المقدرة</div>
            <div class="text-xl font-bold text-red-800" dir="ltr"><?= number_format($totals['expense_est'], 2) ?></div>
            <div class="text-xs text-gray-500 mt-1">الفعلي: <span dir="ltr"><?= number_format($totals['expense_act'], 2) ?></span></div>
        </div>
        <?php $net_est = $totals['income_est'] - $totals['expense_est']; $net_act = $totals['income_act'] - $totals['expense_act']; ?>
        <div class="bg-blue-50 p-4 rounded shadow border border-blue-200 text-center">
            <div class="text-sm font-bold text-blue-700">الفرق (فائض / عجز) المقدر</div>
            <div class="text-xl font-bold <?= $net_est>=0?'text-green-700':'text-red-700' ?>" dir="ltr"><?= number_format($net_est, 2) ?></div>
            <div class="text-xs text-gray-500 mt-1">الفرق الفعلي: <span dir="ltr" class="<?= $net_act>=0?'text-green-600':'text-red-600' ?>"><?= number_format($net_act, 2) ?></span></div>
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="budget_id" value="<?= $budget_id ?>">
        
        <?php foreach (['income' => 'قسم الواردات', 'expense' => 'قسم النفقات'] as $sec_key => $sec_title): ?>
        <div class="mb-8 bg-white p-4 rounded shadow">
            <h2 class="text-xl font-bold mb-4 text-indigo-800 border-b-2 border-indigo-200 pb-2"><?= $sec_title ?></h2>
            
            <?php if (empty($grouped[$sec_key])): ?>
                <p class="text-gray-500 text-sm">لا يوجد بنود.</p>
            <?php else: ?>
                <?php foreach ($grouped[$sec_key] as $chap_name => $chap_data): ?>
                <div class="mb-6">
                    <h3 class="text-lg font-bold mb-2 bg-gray-100 p-2 rounded text-gray-800"><?= htmlspecialchars($chap_name) ?></h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-right text-sm border-collapse border border-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="border p-2 w-16">الباب</th>
                                    <th class="border p-2 w-16">الفصل</th>
                                    <th class="border p-2 min-w-[200px]"><?= $sec_key==='income'?'نوع الواردات':'نوع النفقات' ?></th>
                                    <th class="border p-2 w-32">التقدير الحالي</th>
                                    <th class="border p-2 w-32">الفعلي (التحصيل/الصرف)</th>
                                    <th class="border p-2 w-32">الفارق</th>
                                    <th class="border p-2 min-w-[200px]">شرح البند</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($chap_data['lines'] as $line): 
                                    $variance = $sec_key === 'income' ? ($line['actual_amount'] - $line['current_estimate']) : ($line['current_estimate'] - $line['actual_amount']);
                                    $var_color = $variance < 0 ? 'text-red-600' : 'text-green-600';
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="border p-2 text-center text-gray-500 font-mono"><?= htmlspecialchars($line['chapter_number'] ?? '-') ?></td>
                                    <td class="border p-2 text-center text-gray-500 font-mono"><?= htmlspecialchars($line['item_number'] ?? '-') ?></td>
                                    <td class="border p-2 font-bold"><?= htmlspecialchars($line['item_name']) ?></td>
                                    <td class="border p-1">
                                        <input type="number" step="0.01" name="estimates[<?= $line['id'] ?>]" value="<?= floatval($line['current_estimate']) ?>" class="w-full p-1 border rounded text-left" dir="ltr" <?= $budget['status']==='closed'&&!$is_admin?'readonly':'' ?>>
                                    </td>
                                    <td class="border p-2 text-left font-bold" dir="ltr"><?= number_format($line['actual_amount'], 2) ?></td>
                                    <td class="border p-2 text-left font-bold <?= $var_color ?>" dir="ltr"><?= number_format($variance, 2) ?></td>
                                    <td class="border p-1">
                                        <input type="text" name="explanations[<?= $line['id'] ?>]" value="<?= htmlspecialchars($line['explanation'] ?? '') ?>" class="w-full p-1 border rounded" <?= $budget['status']==='closed'&&!$is_admin?'readonly':'' ?>>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-gray-100 font-bold">
                                <tr>
                                    <td colspan="3" class="border p-2 text-left">مجموع <?= htmlspecialchars($chap_name) ?></td>
                                    <td class="border p-2 text-left" dir="ltr"><?= number_format($chap_data['est_sum'], 2) ?></td>
                                    <td class="border p-2 text-left" dir="ltr"><?= number_format($chap_data['act_sum'], 2) ?></td>
                                    <td class="border p-2 text-left" dir="ltr"><?= number_format($sec_key === 'income' ? ($chap_data['act_sum'] - $chap_data['est_sum']) : ($chap_data['est_sum'] - $chap_data['act_sum']), 2) ?></td>
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
        <div class="fixed bottom-0 left-0 w-full bg-white border-t border-gray-300 p-4 shadow-[0_-5px_10px_rgba(0,0,0,0.1)] flex justify-center z-50">
            <button type="submit" name="update_budget" class="bg-blue-600 text-white px-8 py-2 rounded text-lg font-bold hover:bg-blue-700 shadow">💾 حفظ التعديلات</button>
        </div>
        <?php endif; ?>
    </form>
    <div class="h-20"></div> <!-- spacing for fixed footer -->
</div>
</body>
</html>
<?php
    exit;
}
// ------------------------------------------------------------------
// LIST logic
// ------------------------------------------------------------------
$templates = $db->query("SELECT * FROM municipal_budget_templates WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->query("
    SELECT b.*, 
        COALESCE(SUM(CASE WHEN l.section_type='income' THEN l.current_estimate ELSE 0 END), 0) as total_income,
        COALESCE(SUM(CASE WHEN l.section_type='expense' THEN l.current_estimate ELSE 0 END), 0) as total_expense
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

    <?php if($error): ?><div class="bg-red-100 text-red-700 p-3 mb-4 rounded"><?= $error ?></div><?php endif; ?>

    <div class="bg-white p-6 rounded shadow mb-6">
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
                    <th class="p-3">الواردات المقدرة</th>
                    <th class="p-3">النفقات المقدرة</th>
                    <th class="p-3">الرصيد المقدر</th>
                    <th class="p-3">الحالة</th>
                    <th class="p-3">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($budgets)): ?>
                <tr><td colspan="7" class="p-4 text-center text-gray-500">لا يوجد موازنات. قم بإنشاء موازنة جديدة.</td></tr>
                <?php endif; ?>
                <?php foreach($budgets as $b): $net = $b['total_income'] - $b['total_expense']; ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-3 font-bold"><?= $b['fiscal_year'] ?></td>
                    <td class="p-3"><?= htmlspecialchars($b['title']) ?></td>
                    <td class="p-3 text-green-600 font-bold" dir="ltr"><?= number_format($b['total_income'],2) ?></td>
                    <td class="p-3 text-red-600 font-bold" dir="ltr"><?= number_format($b['total_expense'],2) ?></td>
                    <td class="p-3 font-bold <?= $net>=0?'text-green-700':'text-red-700' ?>" dir="ltr"><?= number_format($net,2) ?></td>
                    <td class="p-3">
                        <span class="px-2 py-1 rounded text-xs <?= $b['status']==='draft'?'bg-yellow-100 text-yellow-800':($b['status']==='approved'?'bg-green-100 text-green-800':'bg-gray-100 text-gray-800') ?>">
                            <?= $b['status'] ?>
                        </span>
                    </td>
                    <td class="p-3">
                        <a href="?action=view&id=<?= $b['id'] ?>" class="bg-blue-100 text-blue-700 px-3 py-1 rounded hover:bg-blue-200 font-bold">عرض وتعديل</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
