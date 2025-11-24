<?php
// تحميل أنظمة الأمان
if (file_exists(__DIR__ . '/../includes/auto_security.php')) {
    require_once __DIR__ . '/../includes/auto_security.php';
}

require_once '../includes/auth.php';
require_once '../config/database.php';

$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES 'utf8mb4'");
$db->exec("SET CHARACTER SET utf8mb4");

$user = $auth->getUserInfo();
$message = '';
$error = '';

// جلب معلومات اللجنة إذا تم تحديدها
$selected_committee_id = $_GET['committee_id'] ?? null;
$selected_committee_name = $_GET['committee_name'] ?? null;

// جلب معلومات الميزانية للتعديل
$edit_budget_id = $_GET['edit_budget'] ?? null;
$edit_budget = null;

if ($edit_budget_id) {
    $stmt = $db->prepare("SELECT * FROM budgets WHERE id = ?");
    $stmt->execute([$edit_budget_id]);
    $edit_budget = $stmt->fetch(PDO::FETCH_ASSOC);
}

// جلب معلومات البند للتعديل
$edit_item_id = $_GET['edit_item'] ?? null;
$edit_item = null;

if ($edit_item_id) {
    $stmt = $db->prepare("SELECT * FROM budget_items WHERE id = ?");
    $stmt->execute([$edit_item_id]);
    $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // تأكد من أننا نعرض الميزانية المرتبطة
    if ($edit_item) {
        $selected_budget_id = $edit_item['budget_id'];
    }
}

// تحميل CSRF Protection
if (file_exists(__DIR__ . '/../includes/csrf_middleware.php')) {
    require_once __DIR__ . '/../includes/csrf_middleware.php';
}

// معالجة إنشاء ميزانية تلقائية من قوالب اللجنة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_auto_budget'])) {
    // التحقق من CSRF
    if (!csrf_protect(false)) {
        $error = $error ?? 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        try {
        $committee_id = intval($_POST['committee_id']);
        $currency_id = intval($_POST['currency_id']); // العملة المحددة من المستخدم
        $fiscal_year = intval($_POST['fiscal_year']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        
        // جلب اسم اللجنة
        $stmt = $db->prepare("SELECT committee_name FROM municipal_committees WHERE id = ?");
        $stmt->execute([$committee_id]);
        $committee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$committee) {
            throw new Exception('اللجنة غير موجودة');
        }
        
        // حساب المبلغ الإجمالي من القوالب (بنفس العملة المحددة)
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(default_amount), 0) as total
            FROM budget_item_templates
            WHERE committee_id = ? AND is_active = 1
        ");
        $stmt->execute([$committee_id]);
        $template_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $total_amount = $template_data['total'];
        
        // إنشاء رمز الميزانية
        $budget_code = 'BUD-' . $committee_id . '-' . $fiscal_year;
        $budget_name = 'ميزانية ' . $committee['committee_name'] . ' - ' . $fiscal_year;
        $description = 'ميزانية تم إنشاؤها تلقائياً للجنة ' . $committee['committee_name'];
        
        // إنشاء الميزانية بالعملة المحددة
        $stmt = $db->prepare("
            INSERT INTO budgets (
                budget_code, name, fiscal_year, start_date, end_date, 
                total_amount, currency_id, committee_id, 
                description, created_by, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'مسودة')
        ");
        $stmt->execute([
            $budget_code, $budget_name, $fiscal_year, $start_date, $end_date,
            $total_amount, $currency_id, $committee_id,
            $description, $user['id']
        ]);
        
        $budget_id = $db->lastInsertId();
        
        // نسخ البنود من القوالب مع تطبيق العملة المحددة على جميع البنود
        $stmt = $db->prepare("
            INSERT INTO budget_items (
                budget_id, item_code, name, description, 
                item_type, category, allocated_amount, currency_id,
                remaining_amount, spent_amount
            )
            SELECT 
                ?, item_code, name, description,
                item_type, category, default_amount, ?,
                default_amount, 0
            FROM budget_item_templates
            WHERE committee_id = ? AND is_active = 1
        ");
        $stmt->execute([$budget_id, $currency_id, $committee_id]);
        $items_count = $stmt->rowCount();
        
            $message = "تم إنشاء الميزانية بنجاح مع $items_count بند تلقائياً بالعملة المحددة!";
        } catch (Exception $e) {
            $error = 'خطأ في إنشاء الميزانية التلقائية: ' . $e->getMessage();
        }
    }
}

// معالجة تعديل ميزانية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_budget'])) {
    if (!csrf_protect(false)) {
        $error = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        try {
            $budget_id = intval($_POST['budget_id']);
            $budget_code = trim($_POST['budget_code']);
            $name = trim($_POST['name']);
            $fiscal_year = intval($_POST['fiscal_year']);
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $total_amount = floatval($_POST['total_amount']);
            $currency_id = intval($_POST['currency_id']);
            $committee_id = !empty($_POST['committee_id']) ? intval($_POST['committee_id']) : null;
            $description = trim($_POST['description']);
            
            $stmt = $db->prepare("UPDATE budgets SET budget_code = ?, name = ?, fiscal_year = ?, start_date = ?, end_date = ?, total_amount = ?, currency_id = ?, committee_id = ?, description = ? WHERE id = ?");
            $stmt->execute([$budget_code, $name, $fiscal_year, $start_date, $end_date, $total_amount, $currency_id, $committee_id, $description, $budget_id]);
            
            $message = 'تم تحديث الميزانية بنجاح!';
            header("Location: budgets.php?budget_id=$budget_id" . ($selected_committee_id ? "&committee_id=$selected_committee_id&committee_name=" . urlencode($selected_committee_name) : ""));
            exit();
        } catch (PDOException $e) {
            $error = 'خطأ في تحديث الميزانية: ' . $e->getMessage();
        }
    }
}

// معالجة إضافة ميزانية يدوية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_budget'])) {
    // التحقق من CSRF
    if (!csrf_protect(false)) {
        $error = $error ?? 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        try {
        $budget_code = trim($_POST['budget_code']);
        $name = trim($_POST['name']);
        $fiscal_year = intval($_POST['fiscal_year']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $total_amount = floatval($_POST['total_amount']);
        $currency_id = intval($_POST['currency_id']);
        $committee_id = !empty($_POST['committee_id']) ? intval($_POST['committee_id']) : null;
        $description = trim($_POST['description']);
        
        $stmt = $db->prepare("INSERT INTO budgets (budget_code, name, fiscal_year, start_date, end_date, total_amount, currency_id, committee_id, description, created_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'مسودة')");
        $stmt->execute([$budget_code, $name, $fiscal_year, $start_date, $end_date, $total_amount, $currency_id, $committee_id, $description, $user['id']]);
        
            $message = 'تم إضافة الميزانية بنجاح!';
        } catch (PDOException $e) {
            $error = 'خطأ في إضافة الميزانية: ' . $e->getMessage();
        }
    }
}

// معالجة حذف بند
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_budget_item'])) {
    // التحقق من CSRF
    if (!csrf_protect(false)) {
        $error = $error ?? 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        try {
            $item_id = intval($_POST['item_id']);
            $budget_id = intval($_POST['budget_id']);
            
            $stmt = $db->prepare("DELETE FROM budget_items WHERE id = ?");
            $stmt->execute([$item_id]);
            
            $message = 'تم حذف البند بنجاح!';
            header("Location: budgets.php?budget_id=$budget_id" . ($selected_committee_id ? "&committee_id=$selected_committee_id&committee_name=" . urlencode($selected_committee_name) : ""));
            exit();
        } catch (PDOException $e) {
            $error = 'خطأ في حذف البند: ' . $e->getMessage();
        }
    }
}

// معالجة تعديل بند
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_budget_item'])) {
    // التحقق من CSRF
    if (!csrf_protect(false)) {
        $error = $error ?? 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        try {
        $item_id = intval($_POST['item_id']);
        $budget_id = intval($_POST['budget_id']);
        $item_code = trim($_POST['item_code']);
        $name = trim($_POST['item_name']);
        $description = trim($_POST['item_description']);
        $item_type = $_POST['item_type'];
        $category = trim($_POST['category']);
        $allocated_amount = floatval($_POST['allocated_amount']);
        $currency_id = intval($_POST['item_currency_id']);
        
        // حساب المتبقي = المخصص - المصروف
        $stmt = $db->prepare("SELECT spent_amount FROM budget_items WHERE id = ?");
        $stmt->execute([$item_id]);
        $current_item = $stmt->fetch(PDO::FETCH_ASSOC);
        $spent_amount = $current_item['spent_amount'] ?? 0;
        $remaining_amount = $allocated_amount - $spent_amount;
        
        $stmt = $db->prepare("UPDATE budget_items SET item_code = ?, name = ?, description = ?, item_type = ?, category = ?, allocated_amount = ?, currency_id = ?, remaining_amount = ? WHERE id = ?");
        $stmt->execute([$item_code, $name, $description, $item_type, $category, $allocated_amount, $currency_id, $remaining_amount, $item_id]);
        
            $message = 'تم تحديث البند بنجاح!';
            header("Location: budgets.php?budget_id=$budget_id" . ($selected_committee_id ? "&committee_id=$selected_committee_id&committee_name=" . urlencode($selected_committee_name) : ""));
            exit();
        } catch (PDOException $e) {
            $error = 'خطأ في تحديث البند: ' . $e->getMessage();
        }
    }
}

// معالجة إضافة بند
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_budget_item'])) {
    // التحقق من CSRF
    if (!csrf_protect(false)) {
        $error = $error ?? 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        try {
        $budget_id = intval($_POST['budget_id']);
        $item_code = trim($_POST['item_code']);
        $name = trim($_POST['item_name']);
        $description = trim($_POST['item_description']);
        $item_type = $_POST['item_type'];
        $category = trim($_POST['category']);
        $allocated_amount = floatval($_POST['allocated_amount']);
        $currency_id = intval($_POST['item_currency_id']);
        $parent_item_id = !empty($_POST['parent_item_id']) ? intval($_POST['parent_item_id']) : null;
        
        $remaining_amount = $allocated_amount;
        
        $stmt = $db->prepare("INSERT INTO budget_items (budget_id, item_code, name, description, item_type, category, allocated_amount, currency_id, remaining_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$budget_id, $item_code, $name, $description, $item_type, $category, $allocated_amount, $currency_id, $remaining_amount]);
        
            $message = 'تم إضافة البند بنجاح!';
        } catch (PDOException $e) {
            $error = 'خطأ في إضافة البند: ' . $e->getMessage();
        }
    }
}

// معالجة حذف ميزانية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_budget'])) {
    // التحقق من CSRF
    if (!csrf_protect(false)) {
        $error = $error ?? 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        try {
        $budget_id = intval($_POST['budget_id']);
        
        // حذف البنود أولاً
        $stmt = $db->prepare("DELETE FROM budget_items WHERE budget_id = ?");
        $stmt->execute([$budget_id]);
        
        // حذف الميزانية
        $stmt = $db->prepare("DELETE FROM budgets WHERE id = ?");
        $stmt->execute([$budget_id]);
        
            $message = 'تم حذف الميزانية بنجاح!';
            header("Location: budgets.php" . ($selected_committee_id ? "?committee_id=$selected_committee_id&committee_name=" . urlencode($selected_committee_name) : ""));
            exit();
        } catch (PDOException $e) {
            $error = 'خطأ في حذف الميزانية: ' . $e->getMessage();
        }
    }
}

// معالجة اعتماد الميزانية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_budget'])) {
    // التحقق من CSRF
    if (!csrf_protect(false)) {
        $error = $error ?? 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        try {
        $budget_id = intval($_POST['budget_id']);
        
        $stmt = $db->prepare("UPDATE budgets SET status = 'معتمد', approved_by = ?, approved_date = CURRENT_DATE WHERE id = ?");
        $stmt->execute([$user['id'], $budget_id]);
        
            $message = 'تم اعتماد الميزانية بنجاح!';
        } catch (PDOException $e) {
            $error = 'خطأ في اعتماد الميزانية: ' . $e->getMessage();
        }
    }
}

// معالجة إلغاء اعتماد الميزانية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unapprove_budget'])) {
    // التحقق من CSRF
    if (!csrf_protect(false)) {
        $error = $error ?? 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        try {
        $budget_id = intval($_POST['budget_id']);
        
        $stmt = $db->prepare("UPDATE budgets SET status = 'مسودة', approved_by = NULL, approved_date = NULL WHERE id = ?");
        $stmt->execute([$budget_id]);
        
            $message = 'تم إلغاء اعتماد الميزانية بنجاح! يمكنك الآن تعديلها أو حذفها.';
        } catch (PDOException $e) {
            $error = 'خطأ في إلغاء اعتماد الميزانية: ' . $e->getMessage();
        }
    }
}

// جلب الميزانيات
$filter_year = $_GET['year'] ?? '';
$filter_status = $_GET['status'] ?? '';

$where_conditions = [];
$params = [];

// فلترة حسب اللجنة إذا تم تحديدها
if (!empty($selected_committee_id)) {
    $where_conditions[] = "b.committee_id = ?";
    $params[] = $selected_committee_id;
}

if (!empty($filter_year)) {
    $where_conditions[] = "fiscal_year = ?";
    $params[] = $filter_year;
}

if (!empty($filter_status)) {
    $where_conditions[] = "status = ?";
    $params[] = $filter_status;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$stmt = $db->prepare("
    SELECT b.*, 
           c.currency_code,
           c.currency_symbol,
           mc.committee_name,
           u.full_name as created_by_name,
           (SELECT COUNT(*) FROM budget_items WHERE budget_id = b.id) as items_count,
           (SELECT SUM(allocated_amount) FROM budget_items WHERE budget_id = b.id) as total_allocated,
           (SELECT SUM(spent_amount) FROM budget_items WHERE budget_id = b.id) as total_spent
    FROM budgets b
    LEFT JOIN currencies c ON b.currency_id = c.id
    LEFT JOIN municipal_committees mc ON b.committee_id = mc.id
    LEFT JOIN users u ON b.created_by = u.id
    $where_clause
    ORDER BY b.fiscal_year DESC, b.created_at DESC
");
$stmt->execute($params);
$budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// إحصائيات
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_budgets,
        SUM(CASE WHEN status = 'مسودة' THEN 1 ELSE 0 END) as draft_count,
        SUM(CASE WHEN status = 'معتمد' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status = 'مغلق' THEN 1 ELSE 0 END) as closed_count
    FROM budgets
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// جلب السنوات المالية
$stmt = $db->query("SELECT DISTINCT fiscal_year FROM budgets ORDER BY fiscal_year DESC");
$fiscal_years = $stmt->fetchAll(PDO::FETCH_COLUMN);

// جلب العملات
$stmt = $db->query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY currency_code");
$currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب اللجان من الجدول الموجود (municipal_committees)
$committees = [];
try {
    $stmt = $db->query("SELECT id, committee_name, committee_description, committee_type, chairman_id, is_active 
                        FROM municipal_committees 
                        WHERE is_active = 1 
                        ORDER BY committee_name");
    $committees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // في حالة عدم وجود الجدول، سنستمر بدون لجان
    $committees = [];
}

// جلب الميزانية المحددة للتفاصيل
$selected_budget_id = $_GET['budget_id'] ?? 0;
$selected_budget = null;
$budget_items = [];

if ($selected_budget_id) {
    // جلب تفاصيل الميزانية
    $stmt = $db->prepare("
        SELECT b.*, 
               c.currency_code,
               c.currency_symbol,
               mc.committee_name
        FROM budgets b
        LEFT JOIN currencies c ON b.currency_id = c.id
        LEFT JOIN municipal_committees mc ON b.committee_id = mc.id
        WHERE b.id = ?
    ");
    $stmt->execute([$selected_budget_id]);
    $selected_budget = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // جلب بنود الميزانية
    if ($selected_budget) {
        $stmt = $db->prepare("
            SELECT bi.*,
                   c.currency_code,
                   c.currency_symbol,
                   (SELECT name FROM budget_items WHERE id = bi.parent_item_id) as parent_name,
                   (SELECT COUNT(*) FROM budget_items WHERE parent_item_id = bi.id) as children_count
            FROM budget_items bi
            LEFT JOIN currencies c ON bi.currency_id = c.id
            WHERE bi.budget_id = ?
            ORDER BY bi.item_type, bi.category, bi.id
        ");
        $stmt->execute([$selected_budget_id]);
        $budget_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // جلب الفواتير المرتبطة بكل بند
        $budget_item_invoices = [];
        foreach ($budget_items as $item) {
            $stmt = $db->prepare("
                SELECT si.*, 
                       s.name as supplier_name,
                       c.currency_symbol,
                       c.currency_code
                FROM supplier_invoices si
                LEFT JOIN suppliers s ON si.supplier_id = s.id
                LEFT JOIN currencies c ON si.currency_id = c.id
                WHERE si.budget_item_id = ?
                ORDER BY si.invoice_date DESC
            ");
            $stmt->execute([$item['id']]);
            $budget_item_invoices[$item['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الميزانيات - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #e0f2ff 0%, #e5ecff 50%, #f7f9ff 100%);
        }
        .modal { display: none !important; }
        .modal.active { display: flex !important; }
        .glass-card {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        }
        .stat-card {
            border-radius: 14px;
            padding: 18px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.6);
        }
        .search-input {
            border-radius: 9999px;
            padding-inline: 1.5rem;
            background: rgba(243, 244, 246, 0.8);
        }
    </style>
</head>
<body>
    <div class="max-w-7xl mx-auto px-4 py-8 space-y-6">
        <!-- Header -->
        <div class="glass-card p-6">
            <div class="flex items-center justify-between gap-6 flex-wrap">
                <div>
                    <?php if ($selected_committee_name): ?>
                        <h1 class="text-3xl font-bold text-gray-800">💰 ميزانية <?= htmlspecialchars($selected_committee_name) ?></h1>
                        <p class="text-gray-600 mt-2">إدارة ميزانية اللجنة والبنود مع تتبع الإنفاق</p>
                    <?php else: ?>
                        <h1 class="text-3xl font-bold text-gray-800">💰 إدارة الميزانيات</h1>
                        <p class="text-gray-600 mt-2">إدارة الميزانيات السنوية والبنود مع تتبع الإنفاق</p>
                    <?php endif; ?>
                </div>
                <div class="flex gap-3">
                    <a href="budgets_report.php" target="_blank"
                       class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition shadow-lg flex items-center gap-2">
                        📊 التقرير الشامل
                    </a>
                    <button onclick="openAIWizard()" class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-3 rounded-lg hover:from-purple-700 hover:to-indigo-700 transition shadow-lg flex items-center gap-2">
                        🤖 إنشاء بالذكاء الاصطناعي
                    </button>
                    <?php if (!empty($committees)): ?>
                    <button onclick="openModal('createAutoBudgetModal')" class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition shadow-lg">
                        ⚡ إنشاء تلقائية (قوالب)
                    </button>
                    <?php endif; ?>
                    <button onclick="openModal('addBudgetModal')" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition shadow-lg">
                        ➕ إضافة ميزانية <?php echo (!empty($committees)) ? 'يدوياً' : 'جديدة'; ?>
                    </button>
                    <?php if ($selected_committee_id): ?>
                        <a href="municipality_management.php?tab=committees" class="bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition shadow-lg">
                            ← العودة للجان
                        </a>
                    <?php else: ?>
                        <a href="../comprehensive_dashboard.php" class="bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition shadow-lg">
                            ← العودة
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="glass-card border border-green-200 bg-green-50/80 text-green-700 px-5 py-4 rounded-lg">
                ✅ <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="glass-card border border-red-200 bg-red-50/80 text-red-700 px-5 py-4 rounded-lg">
                ❌ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($edit_item): ?>
            <div class="glass-card border border-purple-200 bg-purple-50/80 text-purple-800 px-5 py-4 rounded-lg">
                ✏️ <strong>وضع التعديل:</strong> تعديل البند "<?= htmlspecialchars($edit_item['name']) ?>"
                <a href="budgets.php?budget_id=<?= $edit_item['budget_id'] ?><?= $selected_committee_id ? '&committee_id=' . $selected_committee_id . '&committee_name=' . urlencode($selected_committee_name) : '' ?>" class="underline font-bold">إلغاء التعديل</a>
            </div>
        <?php elseif ($edit_budget): ?>
            <div class="glass-card border border-purple-200 bg-purple-50/80 text-purple-800 px-5 py-4 rounded-lg">
                ✏️ <strong>وضع التعديل:</strong> تعديل ميزانية <?= htmlspecialchars($edit_budget['name']) ?>
                <a href="budgets.php<?= $selected_committee_id ? '?committee_id=' . $selected_committee_id . '&committee_name=' . urlencode($selected_committee_name) : '' ?>" class="underline font-bold">إلغاء التعديل</a>
            </div>
        <?php elseif ($selected_committee_name): ?>
            <div class="glass-card border border-blue-200 bg-blue-50/80 text-blue-800 px-5 py-4 rounded-lg">
                ℹ️ <strong>تعرض الآن:</strong> ميزانيات لجنة <?= htmlspecialchars($selected_committee_name) ?> فقط.
                <a href="budgets.php" class="underline font-bold">عرض جميع الميزانيات</a>
            </div>
        <?php elseif (empty($committees)): ?>
            <div class="glass-card border border-yellow-200 bg-yellow-50/80 text-yellow-800 px-5 py-4 rounded-lg">
                ⚠️ <strong>ملاحظة:</strong> لا توجد لجان مضافة في النظام. 
                لتفعيل ميزة "الإنشاء التلقائي للميزانيات"، يرجى 
                <a href="municipality_management.php?tab=committees" class="underline font-bold">إضافة اللجان من هنا</a>.
            </div>
        <?php endif; ?>

        <!-- إحصائيات -->
        <div class="glass-card p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="stat-card border-r-4 border-blue-500">
                    <p class="text-sm text-gray-500">إجمالي الميزانيات</p>
                    <p class="text-3xl font-bold text-blue-600"><?= number_format($stats['total_budgets']) ?></p>
                </div>
                
                <div class="stat-card border-r-4 border-yellow-500">
                    <p class="text-sm text-gray-500">مسودات</p>
                    <p class="text-3xl font-bold text-yellow-600"><?= number_format($stats['draft_count']) ?></p>
                </div>
                
                <div class="stat-card border-r-4 border-green-500">
                    <p class="text-sm text-gray-500">معتمدة</p>
                    <p class="text-3xl font-bold text-green-600"><?= number_format($stats['approved_count']) ?></p>
                </div>
                
                <div class="stat-card border-r-4 border-gray-500">
                    <p class="text-sm text-gray-500">مغلقة</p>
                    <p class="text-3xl font-bold text-gray-600"><?= number_format($stats['closed_count']) ?></p>
                </div>
            </div>
        </div>

        <!-- فلاتر -->
        <div class="glass-card p-6">
            <h3 class="font-semibold mb-4 text-lg">🔍 البحث والفلترة</h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-2">السنة المالية</label>
                    <select name="year" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">جميع السنوات</option>
                        <?php foreach ($fiscal_years as $year): ?>
                            <option value="<?= $year ?>" <?= ($filter_year == $year) ? 'selected' : '' ?>><?= $year ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">الحالة</label>
                    <select name="status" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">جميع الحالات</option>
                        <option value="مسودة" <?= ($filter_status === 'مسودة') ? 'selected' : '' ?>>مسودة</option>
                        <option value="معتمد" <?= ($filter_status === 'معتمد') ? 'selected' : '' ?>>معتمد</option>
                        <option value="مغلق" <?= ($filter_status === 'مغلق') ? 'selected' : '' ?>>مغلق</option>
                    </select>
                </div>
                
                <div class="flex items-end gap-2 md:col-span-2">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700">
                        بحث
                    </button>
                    <a href="budgets.php" class="bg-gray-500 text-white py-2 px-4 rounded-lg hover:bg-gray-600">
                        إعادة
                    </a>
                </div>
            </form>
        </div>

        <!-- الميزانيات -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- قائمة الميزانيات -->
            <div class="lg:col-span-1">
                <div class="glass-card overflow-hidden">
                    <div class="p-6 border-b bg-gray-50">
                        <h2 class="text-xl font-semibold">📋 الميزانيات (<?= count($budgets) ?>)</h2>
                    </div>
                    
                    <div class="divide-y max-h-[600px] overflow-y-auto">
                        <?php if (empty($budgets)): ?>
                            <div class="p-8 text-center text-gray-500">
                                📭 لا توجد ميزانيات
                            </div>
                        <?php else: ?>
                            <?php foreach ($budgets as $budget): ?>
                            <div class="p-4 hover:bg-gray-50 cursor-pointer <?= ($selected_budget_id == $budget['id']) ? 'bg-blue-50 border-r-4 border-blue-500' : '' ?>"
                                 onclick="window.location.href='budgets.php?budget_id=<?= $budget['id'] ?>'">
                                <div class="flex items-start justify-between mb-2">
                                    <div>
                                        <h3 class="font-bold text-blue-600"><?= htmlspecialchars($budget['budget_code']) ?></h3>
                                        <p class="text-sm font-semibold"><?= htmlspecialchars($budget['name']) ?></p>
                                    </div>
                                    <?php
                                    $statusColors = [
                                        'مسودة' => 'bg-yellow-100 text-yellow-800',
                                        'معتمد' => 'bg-green-100 text-green-800',
                                        'مغلق' => 'bg-gray-100 text-gray-800'
                                    ];
                                    $statusClass = $statusColors[$budget['status']] ?? 'bg-gray-100';
                                    ?>
                                    <span class="px-2 py-1 rounded text-xs font-semibold <?= $statusClass ?>">
                                        <?= htmlspecialchars($budget['status']) ?>
                                    </span>
                                </div>
                                
                                <div class="text-sm text-gray-600 space-y-1">
                                    <div>📅 السنة المالية: <strong><?= $budget['fiscal_year'] ?></strong></div>
                                    <?php if (!empty($budget['committee_name'])): ?>
                                    <div>🏛️ اللجنة: <strong><?= htmlspecialchars($budget['committee_name']) ?></strong></div>
                                    <?php endif; ?>
                                    <div>💰 الإجمالي: <strong><?= number_format($budget['total_amount'], 2) ?> <?= htmlspecialchars($budget['currency_symbol']) ?></strong></div>
                                    <div>📊 البنود: <strong><?= $budget['items_count'] ?></strong></div>
                                    <?php if ($budget['total_allocated']): ?>
                                    <div>💸 المصروف: <strong class="text-red-600"><?= number_format($budget['total_spent'], 2) ?></strong> / <?= number_format($budget['total_allocated'], 2) ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mt-3 flex gap-2" onclick="event.stopPropagation();">
                                    <?php if ($budget['status'] == 'مسودة'): ?>
                                        <button onclick="editBudget(<?= $budget['id'] ?>)" 
                                                class="flex-1 bg-blue-600 text-white text-xs py-2 rounded hover:bg-blue-700">
                                            ✏️ تعديل
                                        </button>
                                        <form method="POST" class="flex-1" onsubmit="return confirm('هل أنت متأكد من حذف هذه الميزانية؟');">
                                            <?php echo csrf_input('csrf_token'); ?>
                                            <input type="hidden" name="delete_budget" value="1">
                                            <input type="hidden" name="budget_id" value="<?= $budget['id'] ?>">
                                            <button type="submit" class="w-full bg-red-600 text-white text-xs py-2 rounded hover:bg-red-700">
                                                🗑️ حذف
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($budget['status'] == 'مسودة'): ?>
                                <form method="POST" class="mt-2" onclick="event.stopPropagation();">
                                    <?php echo csrf_input('csrf_token'); ?>
                                    <input type="hidden" name="budget_id" value="<?= $budget['id'] ?>">
                                    <button type="submit" name="approve_budget" 
                                            class="w-full bg-green-600 text-white text-sm py-2 rounded hover:bg-green-700">
                                        ✅ اعتماد الميزانية
                                    </button>
                                </form>
                                <?php elseif ($budget['status'] == 'معتمد'): ?>
                                <form method="POST" class="mt-2" onclick="event.stopPropagation();" 
                                      onsubmit="return confirm('هل أنت متأكد من إلغاء اعتماد هذه الميزانية؟ سيمكنك بعدها تعديلها أو حذفها.');">
                                    <?php echo csrf_input('csrf_token'); ?>
                                    <input type="hidden" name="budget_id" value="<?= $budget['id'] ?>">
                                    <button type="submit" name="unapprove_budget" 
                                            class="w-full bg-orange-600 text-white text-sm py-2 rounded hover:bg-orange-700">
                                        ↩️ إلغاء الاعتماد
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- تفاصيل البنود -->
            <div class="lg:col-span-2">
                <?php if ($selected_budget_id): ?>
                    <?php if ($selected_budget): ?>
                        <div class="glass-card mb-6 overflow-hidden">
                            <div class="p-6 border-b bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-t-lg">
                                <h2 class="text-2xl font-bold"><?= htmlspecialchars($selected_budget['name']) ?></h2>
                                <p class="text-sm opacity-90 mt-1">السنة المالية <?= $selected_budget['fiscal_year'] ?> | <?= date('Y-m-d', strtotime($selected_budget['start_date'])) ?> - <?= date('Y-m-d', strtotime($selected_budget['end_date'])) ?></p>
                            </div>
                    <?php else: ?>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
                            <div class="text-4xl mb-3">⚠️</div>
                            <h3 class="text-xl font-bold text-yellow-800 mb-2">الميزانية غير موجودة</h3>
                            <p class="text-yellow-700">الميزانية رقم <?= $selected_budget_id ?> غير موجودة في النظام.</p>
                            <a href="budgets.php" class="inline-block mt-4 bg-yellow-600 text-white px-6 py-2 rounded-lg hover:bg-yellow-700">
                                العودة للقائمة
                            </a>
                        </div>
                    <?php endif; ?>
                    
                <?php if ($selected_budget && !empty($budget_items)): ?>
                    
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold">📊 بنود الميزانية (<?= count($budget_items) ?>)</h3>
                            <button onclick="openAddItemModal(<?= $selected_budget_id ?>)" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
                                ➕ إضافة بند
                            </button>
                        </div>
                        
                        <!-- الرسم البياني -->
                        <div class="glass-card mb-6 p-4">
                            <canvas id="budgetChart" height="80"></canvas>
                        </div>
                        
                        <!-- جدول البنود -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="text-right p-3">الرمز</th>
                                        <th class="text-right p-3">اسم البند</th>
                                        <th class="text-right p-3">النوع</th>
                                        <th class="text-right p-3">التصنيف</th>
                                        <th class="text-right p-3">المخصص</th>
                                        <th class="text-right p-3">المصروف</th>
                                        <th class="text-right p-3">المتبقي</th>
                                        <th class="text-right p-3">النسبة</th>
                                        <?php if ($selected_budget['status'] == 'مسودة'): ?>
                                        <th class="text-center p-3">الإجراءات</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <?php foreach ($budget_items as $item): 
                                        $percentage = $item['allocated_amount'] > 0 ? ($item['spent_amount'] / $item['allocated_amount']) * 100 : 0;
                                        $progressColor = $percentage < 50 ? 'bg-green-500' : ($percentage < 80 ? 'bg-yellow-500' : 'bg-red-500');
                                    ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="p-3 font-mono text-blue-600 font-bold"><?= htmlspecialchars($item['item_code']) ?></td>
                                        <td class="p-3">
                                            <div class="font-semibold"><?= htmlspecialchars($item['name']) ?></div>
                                            <?php if ($item['parent_name']): ?>
                                                <div class="text-xs text-gray-500">↳ تابع لـ: <?= htmlspecialchars($item['parent_name']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3">
                                            <span class="px-2 py-1 rounded text-xs <?= $item['item_type'] == 'إيراد' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                <?= htmlspecialchars($item['item_type']) ?>
                                            </span>
                                        </td>
                                        <td class="p-3">
                                            <span class="px-2 py-1 rounded text-xs bg-purple-100 text-purple-800">
                                                <?= htmlspecialchars($item['category']) ?>
                                            </span>
                                        </td>
                                        <td class="p-3 font-semibold"><?= number_format($item['allocated_amount'], 2) ?> <?= htmlspecialchars($item['currency_symbol'] ?? '') ?></td>
                                        <td class="p-3 text-red-600 font-semibold"><?= number_format($item['spent_amount'], 2) ?> <?= htmlspecialchars($item['currency_symbol'] ?? '') ?></td>
                                        <td class="p-3 text-green-600 font-semibold"><?= number_format($item['remaining_amount'], 2) ?> <?= htmlspecialchars($item['currency_symbol'] ?? '') ?></td>
                                        <td class="p-3">
                                            <div class="flex items-center gap-2">
                                                <div class="flex-1 bg-gray-200 rounded-full h-2">
                                                    <div class="<?= $progressColor ?> h-2 rounded-full" style="width: <?= min($percentage, 100) ?>%"></div>
                                                </div>
                                                <span class="text-xs font-bold"><?= number_format($percentage, 1) ?>%</span>
                                            </div>
                                        </td>
                                        <?php if ($selected_budget['status'] == 'مسودة'): ?>
                                        <td class="p-3">
                                            <div class="flex gap-2 justify-center">
                                                <button onclick="editBudgetItem(<?= $item['id'] ?>)" 
                                                        class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 text-xs">
                                                    ✏️ تعديل
                                                </button>
                                                <form method="POST" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا البند؟');">
                                                    <?php echo csrf_input('csrf_token'); ?>
                                                    <input type="hidden" name="delete_budget_item" value="1">
                                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                    <input type="hidden" name="budget_id" value="<?= $selected_budget_id ?>">
                                                    <button type="submit" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 text-xs">
                                                        🗑️ حذف
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    
                                    <!-- صف الفواتير المرتبطة -->
                                    <?php if (isset($budget_item_invoices[$item['id']]) && !empty($budget_item_invoices[$item['id']])): ?>
                                    <tr class="bg-blue-50">
                                        <td colspan="<?= ($selected_budget['status'] == 'مسودة') ? '9' : '8' ?>" class="p-0">
                                            <div class="p-4">
                                                <div class="flex items-center justify-between mb-3">
                                                    <h4 class="font-bold text-sm text-blue-800">
                                                        📄 الفواتير المرتبطة (<?= count($budget_item_invoices[$item['id']]) ?>)
                                                    </h4>
                                                    <button onclick="toggleInvoices('invoices-<?= $item['id'] ?>')" 
                                                            class="text-blue-600 hover:text-blue-800 text-xs font-semibold">
                                                        عرض/إخفاء
                                                    </button>
                                                </div>
                                                <div id="invoices-<?= $item['id'] ?>" class="hidden">
                                                    <table class="w-full text-xs border">
                                                        <thead class="bg-blue-100">
                                                            <tr>
                                                                <th class="text-right p-2">رقم الفاتورة</th>
                                                                <th class="text-right p-2">المورد</th>
                                                                <th class="text-right p-2">التاريخ</th>
                                                                <th class="text-right p-2">المبلغ الإجمالي</th>
                                                                <th class="text-right p-2">المبلغ المدفوع</th>
                                                                <th class="text-right p-2">المتبقي</th>
                                                                <th class="text-center p-2">الحالة</th>
                                                                <th class="text-center p-2">الإجراءات</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="bg-white/90 divide-y">
                                                            <?php foreach ($budget_item_invoices[$item['id']] as $invoice): ?>
                                                            <tr class="hover:bg-gray-50">
                                                                <td class="p-2 font-mono font-bold text-blue-600">
                                                                    <?= htmlspecialchars($invoice['invoice_number']) ?>
                                                                </td>
                                                                <td class="p-2"><?= htmlspecialchars($invoice['supplier_name']) ?></td>
                                                                <td class="p-2"><?= $invoice['invoice_date'] ?></td>
                                                                <td class="p-2 font-semibold">
                                                                    <?= number_format($invoice['total_amount'], 2) ?> <?= $invoice['currency_symbol'] ?>
                                                                </td>
                                                                <td class="p-2 text-green-600 font-semibold">
                                                                    <?= number_format($invoice['paid_amount'], 2) ?> <?= $invoice['currency_symbol'] ?>
                                                                </td>
                                                                <td class="p-2 text-red-600 font-semibold">
                                                                    <?= number_format($invoice['remaining_amount'], 2) ?> <?= $invoice['currency_symbol'] ?>
                                                                </td>
                                                                <td class="p-2 text-center">
                                                                    <?php
                                                                    $statusColors = [
                                                                        'غير مدفوع' => 'bg-red-100 text-red-800',
                                                                        'مدفوع جزئياً' => 'bg-yellow-100 text-yellow-800',
                                                                        'مدفوع بالكامل' => 'bg-green-100 text-green-800',
                                                                        'متأخر' => 'bg-red-100 text-red-800'
                                                                    ];
                                                                    $statusClass = $statusColors[$invoice['status']] ?? 'bg-gray-100 text-gray-800';
                                                                    ?>
                                                                    <span class="px-2 py-1 rounded text-xs font-semibold <?= $statusClass ?>">
                                                                        <?= htmlspecialchars($invoice['status']) ?>
                                                                    </span>
                                                                </td>
                                                                <td class="p-2 text-center">
                                                                    <a href="invoices.php?invoice_id=<?= $invoice['id'] ?>" 
                                                                       class="text-blue-600 hover:text-blue-800 font-semibold">
                                                                        عرض →
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <script>
                    // رسم بياني للبنود
                    const ctx = document.getElementById('budgetChart').getContext('2d');
                    const budgetData = <?= json_encode(array_map(function($item) {
                        return [
                            'label' => $item['name'],
                            'allocated' => floatval($item['allocated_amount']),
                            'spent' => floatval($item['spent_amount'])
                        ];
                    }, $budget_items), JSON_UNESCAPED_UNICODE) ?>;
                    
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: budgetData.map(d => d.label),
                            datasets: [
                                {
                                    label: 'المخصص',
                                    data: budgetData.map(d => d.allocated),
                                    backgroundColor: 'rgba(59, 130, 246, 0.8)'
                                },
                                {
                                    label: 'المصروف',
                                    data: budgetData.map(d => d.spent),
                                    backgroundColor: 'rgba(239, 68, 68, 0.8)'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: {
                                legend: { display: true, position: 'top' },
                                title: { display: true, text: 'مقارنة المخصص مع المصروف' }
                            }
                        }
                    });
                </script>
                
                    <?php elseif ($selected_budget && empty($budget_items)): ?>
                        <!-- الميزانية موجودة لكن لا توجد بنود -->
                        <div class="glass-card p-12 text-center">
                            <div class="text-6xl mb-4">📋</div>
                            <h3 class="text-xl font-semibold text-gray-700 mb-2">لا توجد بنود بعد</h3>
                            <p class="text-gray-500 mb-4">هذه الميزانية لا تحتوي على بنود حتى الآن</p>
                            <button onclick="openAddItemModal(<?= $selected_budget_id ?>)" 
                                    class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700">
                                ➕ إضافة بند جديد
                            </button>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="glass-card p-12 text-center">
                        <div class="text-6xl mb-4">📊</div>
                        <h3 class="text-xl font-semibold text-gray-700 mb-2">اختر ميزانية لعرض البنود</h3>
                        <p class="text-gray-500">اضغط على أي ميزانية من القائمة لعرض بنودها وتفاصيلها</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal إنشاء ميزانية تلقائية -->
    <div id="createAutoBudgetModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="glass-card w-full max-w-2xl">
            <div class="bg-purple-600 text-white px-6 py-4 rounded-t-lg">
                <h3 class="text-xl font-semibold">⚡ إنشاء ميزانية تلقائية من قوالب اللجنة</h3>
            </div>
            
            <form method="POST" class="p-6 space-y-4">
                <?php echo csrf_input('csrf_token'); ?>
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-4">
                    <p class="text-sm text-purple-800">
                        <strong>📌 ملاحظة:</strong> عند اختيار لجنة وعملة، سيتم إنشاء الميزانية تلقائياً مع جميع البنود المحددة مسبقاً لهذه اللجنة بالعملة المحددة، مما يوفر الوقت والجهد.
                    </p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2">اللجنة *</label>
                        <select name="committee_id" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                            <option value="">-- اختر اللجنة --</option>
                            <?php foreach ($committees as $committee): ?>
                                <option value="<?= $committee['id'] ?>">
                                    <?= htmlspecialchars($committee['committee_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">العملة *</label>
                        <select name="currency_id" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                            <?php foreach ($currencies as $currency): ?>
                                <option value="<?= $currency['id'] ?>" <?= ($currency['is_default']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">💡 ستُطبق على الميزانية وجميع بنودها</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">السنة المالية *</label>
                        <input type="number" name="fiscal_year" required value="<?= date('Y') ?>" min="2020" max="2100"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">تاريخ البداية *</label>
                        <input type="date" name="start_date" required value="<?= date('Y') ?>-01-01"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">تاريخ النهاية *</label>
                        <input type="date" name="end_date" required value="<?= date('Y') ?>-12-31"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                    </div>
                </div>
                
                <div class="flex gap-3 mt-6">
                    <button type="submit" name="create_auto_budget" class="flex-1 bg-purple-600 text-white py-3 rounded-lg hover:bg-purple-700 font-semibold">
                        ⚡ إنشاء الميزانية تلقائياً
                    </button>
                    <button type="button" onclick="closeModal('createAutoBudgetModal')" class="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600">
                        إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal إضافة ميزانية -->
    <div id="addBudgetModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="glass-card w-full max-w-3xl">
            <div class="bg-green-600 text-white px-6 py-4 rounded-t-lg">
                <h3 class="text-xl font-semibold">➕ إضافة ميزانية جديدة</h3>
            </div>
            
            <form method="POST" class="p-6 space-y-4">
                <?php echo csrf_input('csrf_token'); ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">رمز الميزانية *</label>
                        <input type="text" name="budget_code" required placeholder="BUD-2025"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">السنة المالية *</label>
                        <input type="number" name="fiscal_year" required value="<?= date('Y') ?>" min="2020" max="2100"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2">اسم الميزانية *</label>
                        <input type="text" name="name" required placeholder="الميزانية العامة لعام 2025"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">تاريخ البداية *</label>
                        <input type="date" name="start_date" required 
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">تاريخ النهاية *</label>
                        <input type="date" name="end_date" required 
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">المبلغ الإجمالي *</label>
                        <input type="number" name="total_amount" required step="0.01" min="0"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">اللجنة</label>
                        <select name="committee_id" class="w-full px-4 py-2 border rounded-lg">
                            <option value="">-- لا توجد لجنة --</option>
                            <?php foreach ($committees as $committee): ?>
                                <option value="<?= $committee['id'] ?>">
                                    <?= htmlspecialchars($committee['committee_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">العملة *</label>
                        <select name="currency_id" required class="w-full px-4 py-2 border rounded-lg">
                            <?php foreach ($currencies as $currency): ?>
                                <option value="<?= $currency['id'] ?>" <?= ($currency['is_default']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2">الوصف</label>
                        <textarea name="description" rows="2" 
                                  class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500"></textarea>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="closeModal('addBudgetModal')" 
                            class="px-6 py-2 text-gray-600 hover:text-gray-800 border rounded-lg">
                        إلغاء
                    </button>
                    <button type="submit" name="add_budget" 
                            class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                        ✅ إضافة الميزانية
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- نموذج تعديل الميزانية -->
    <?php if ($edit_budget): ?>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="glass-card w-full max-w-3xl max-h-[90vh] overflow-y-auto">
            <div class="bg-purple-600 text-white px-6 py-4 rounded-t-lg sticky top-0">
                <h3 class="text-xl font-semibold">✏️ تعديل الميزانية</h3>
            </div>
            
            <form method="POST" class="p-6 space-y-4">
                <?php echo csrf_input('csrf_token'); ?>
                <input type="hidden" name="budget_id" value="<?= $edit_budget['id'] ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">رمز الميزانية *</label>
                        <input type="text" name="budget_code" required 
                               value="<?= htmlspecialchars($edit_budget['budget_code']) ?>"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">السنة المالية *</label>
                        <input type="number" name="fiscal_year" required min="2020" max="2100"
                               value="<?= $edit_budget['fiscal_year'] ?>"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2">اسم الميزانية *</label>
                        <input type="text" name="name" required 
                               value="<?= htmlspecialchars($edit_budget['name']) ?>"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">تاريخ البداية *</label>
                        <input type="date" name="start_date" required 
                               value="<?= $edit_budget['start_date'] ?>"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">تاريخ النهاية *</label>
                        <input type="date" name="end_date" required 
                               value="<?= $edit_budget['end_date'] ?>"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">المبلغ الإجمالي *</label>
                        <input type="number" name="total_amount" required step="0.01" min="0"
                               value="<?= $edit_budget['total_amount'] ?>"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">اللجنة</label>
                        <select name="committee_id" class="w-full px-4 py-2 border rounded-lg">
                            <option value="">-- لا توجد لجنة --</option>
                            <?php foreach ($committees as $committee): ?>
                                <option value="<?= $committee['id'] ?>" 
                                        <?= ($edit_budget['committee_id'] == $committee['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($committee['committee_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">العملة *</label>
                        <select name="currency_id" required class="w-full px-4 py-2 border rounded-lg">
                            <?php foreach ($currencies as $currency): ?>
                                <option value="<?= $currency['id'] ?>" 
                                        <?= ($edit_budget['currency_id'] == $currency['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2">الوصف</label>
                        <textarea name="description" rows="2" 
                                  class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500"><?= htmlspecialchars($edit_budget['description'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <a href="budgets.php<?= $selected_committee_id ? '?committee_id=' . $selected_committee_id . '&committee_name=' . urlencode($selected_committee_name) : '' ?>" 
                       class="px-6 py-2 text-gray-600 hover:text-gray-800 border rounded-lg inline-block">
                        إلغاء
                    </a>
                    <button type="submit" name="edit_budget" 
                            class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700">
                        💾 حفظ التعديلات
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal إضافة بند -->
    <div id="addItemModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="glass-card w-full max-w-3xl">
            <div class="bg-blue-600 text-white px-6 py-4 rounded-t-lg">
                <h3 class="text-xl font-semibold">➕ إضافة بند للميزانية</h3>
            </div>
            
            <form method="POST" class="p-6 space-y-4">
                <?php echo csrf_input('csrf_token'); ?>
                <input type="hidden" name="budget_id" id="item_budget_id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">رمز البند *</label>
                        <input type="text" name="item_code" required placeholder="ITEM-001"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">نوع البند *</label>
                        <select name="item_type" required class="w-full px-4 py-2 border rounded-lg">
                            <option value="مصروف">مصروف</option>
                            <option value="إيراد">إيراد</option>
                        </select>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2">اسم البند *</label>
                        <input type="text" name="item_name" required 
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">التصنيف *</label>
                        <select name="category" required class="w-full px-4 py-2 border rounded-lg">
                            <option value="رواتب">رواتب</option>
                            <option value="صيانة">صيانة</option>
                            <option value="مشاريع">مشاريع</option>
                            <option value="خدمات">خدمات</option>
                            <option value="مشتريات">مشتريات</option>
                            <option value="مواد استهلاكية">مواد استهلاكية</option>
                            <option value="أخرى">أخرى</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">المبلغ المخصص *</label>
                        <input type="number" name="allocated_amount" required step="0.01" min="0"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">العملة *</label>
                        <select name="item_currency_id" required class="w-full px-4 py-2 border rounded-lg">
                            <?php foreach ($currencies as $currency): ?>
                                <option value="<?= $currency['id'] ?>" <?= ($currency['is_default']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2">وصف البند</label>
                        <textarea name="item_description" rows="2" 
                                  class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="closeModal('addItemModal')" 
                            class="px-6 py-2 text-gray-600 hover:text-gray-800 border rounded-lg">
                        إلغاء
                    </button>
                    <button type="submit" name="add_budget_item" 
                            class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                        ✅ إضافة البند
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- نموذج تعديل البند -->
    <?php if ($edit_item): ?>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="glass-card w-full max-w-3xl max-h-[90vh] overflow-y-auto">
            <div class="bg-purple-600 text-white px-6 py-4 rounded-t-lg sticky top-0">
                <h3 class="text-xl font-semibold">✏️ تعديل البند</h3>
            </div>
            
            <form method="POST" class="p-6 space-y-4">
                <?php echo csrf_input('csrf_token'); ?>
                <input type="hidden" name="item_id" value="<?= $edit_item['id'] ?>">
                <input type="hidden" name="budget_id" value="<?= $edit_item['budget_id'] ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">رمز البند *</label>
                        <input type="text" name="item_code" required 
                               value="<?= htmlspecialchars($edit_item['item_code']) ?>"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">نوع البند *</label>
                        <select name="item_type" required class="w-full px-4 py-2 border rounded-lg">
                            <option value="مصروف" <?= $edit_item['item_type'] == 'مصروف' ? 'selected' : '' ?>>مصروف</option>
                            <option value="إيراد" <?= $edit_item['item_type'] == 'إيراد' ? 'selected' : '' ?>>إيراد</option>
                        </select>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2">اسم البند *</label>
                        <input type="text" name="item_name" required 
                               value="<?= htmlspecialchars($edit_item['name']) ?>"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">التصنيف *</label>
                        <select name="category" required class="w-full px-4 py-2 border rounded-lg">
                            <option value="رواتب" <?= $edit_item['category'] == 'رواتب' ? 'selected' : '' ?>>رواتب</option>
                            <option value="صيانة" <?= $edit_item['category'] == 'صيانة' ? 'selected' : '' ?>>صيانة</option>
                            <option value="مشاريع" <?= $edit_item['category'] == 'مشاريع' ? 'selected' : '' ?>>مشاريع</option>
                            <option value="خدمات" <?= $edit_item['category'] == 'خدمات' ? 'selected' : '' ?>>خدمات</option>
                            <option value="مشتريات" <?= $edit_item['category'] == 'مشتريات' ? 'selected' : '' ?>>مشتريات</option>
                            <option value="مواد استهلاكية" <?= $edit_item['category'] == 'مواد استهلاكية' ? 'selected' : '' ?>>مواد استهلاكية</option>
                            <option value="أخرى" <?= $edit_item['category'] == 'أخرى' ? 'selected' : '' ?>>أخرى</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">المبلغ المخصص *</label>
                        <input type="number" name="allocated_amount" required step="0.01" min="0"
                               value="<?= $edit_item['allocated_amount'] ?>"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                        <p class="text-xs text-gray-500 mt-1">المصروف حالياً: <?= number_format($edit_item['spent_amount'], 2) ?></p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">العملة *</label>
                        <select name="item_currency_id" required class="w-full px-4 py-2 border rounded-lg">
                            <?php foreach ($currencies as $currency): ?>
                                <option value="<?= $currency['id'] ?>" 
                                        <?= ($edit_item['currency_id'] == $currency['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2">وصف البند</label>
                        <textarea name="item_description" rows="2" 
                                  class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500"><?= htmlspecialchars($edit_item['description'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <a href="budgets.php?budget_id=<?= $edit_item['budget_id'] ?><?= $selected_committee_id ? '&committee_id=' . $selected_committee_id . '&committee_name=' . urlencode($selected_committee_name) : '' ?>" 
                       class="px-6 py-2 text-gray-600 hover:text-gray-800 border rounded-lg inline-block">
                        إلغاء
                    </a>
                    <button type="submit" name="edit_budget_item" 
                            class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700">
                        💾 حفظ التعديلات
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- تضمين مكونات الذكاء الاصطناعي -->
    <?php include 'budget_ai_component.php'; ?>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function openAddItemModal(budgetId) {
            document.getElementById('item_budget_id').value = budgetId;
            openModal('addItemModal');
        }
        
        function editBudget(budgetId) {
            window.location.href = 'budgets.php?edit_budget=' + budgetId<?= $selected_committee_id ? " + '&committee_id=" . $selected_committee_id . "&committee_name=" . urlencode($selected_committee_name) . "'" : "" ?>;
        }
        
        function editBudgetItem(itemId) {
            window.location.href = 'budgets.php?edit_item=' + itemId<?= $selected_committee_id ? " + '&committee_id=" . $selected_committee_id . "&committee_name=" . urlencode($selected_committee_name) . "'" : "" ?>;
        }
        
        function toggleInvoices(id) {
            const element = document.getElementById(id);
            if (element) {
                element.classList.toggle('hidden');
            }
        }

        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('active');
                }
            });
        }
    </script>
</body>
</html>

