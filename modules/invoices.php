<?php
// تحميل CSRF Protection
if (file_exists(__DIR__ . '/../includes/csrf_middleware.php')) {
    require_once __DIR__ . '/../includes/csrf_middleware.php';
}

require_once '../includes/auth.php';
require_once '../config/database.php';

// تحميل دوال الأمان
if (file_exists(__DIR__ . '/../includes/form_helper.php')) {
    require_once __DIR__ . '/../includes/form_helper.php';
}

$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES 'utf8mb4'");
$db->exec("SET CHARACTER SET utf8mb4");

$user = $auth->getUserInfo();
$message = '';
$error = '';

$committees = $db->query("SELECT id, committee_name FROM municipal_committees WHERE is_active = 1 ORDER BY committee_name")->fetchAll(PDO::FETCH_ASSOC);

// معالجة إضافة فاتورة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_invoice'])) {
    if (!csrf_protect(false)) {
        $error = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        try {
            $invoice_number = trim($_POST['invoice_number']);
        $supplier_id = intval($_POST['supplier_id']);
        $invoice_date = $_POST['invoice_date'];
        $due_date = $_POST['due_date'];
        $total_amount = floatval($_POST['total_amount']);
        $currency_id = intval($_POST['currency_id']);
        $exchange_rate = floatval($_POST['exchange_rate']) ?: 1.0;
        $description = trim($_POST['description']);
        $related_project_id = !empty($_POST['related_project_id']) ? intval($_POST['related_project_id']) : null;
        $budget_item_id = !empty($_POST['budget_item_id']) ? intval($_POST['budget_item_id']) : null;
        $committee_id = !empty($_POST['committee_id']) ? intval($_POST['committee_id']) : null;
        $notes = trim($_POST['notes']);
        
        $remaining_amount = $total_amount;
        
        $stmt = $db->prepare("INSERT INTO supplier_invoices (invoice_number, supplier_id, invoice_date, due_date, total_amount, currency_id, exchange_rate, remaining_amount, description, related_project_id, budget_item_id, committee_id, created_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$invoice_number, $supplier_id, $invoice_date, $due_date, $total_amount, $currency_id, $exchange_rate, $remaining_amount, $description, $related_project_id, $budget_item_id, $committee_id, $user['id'], $notes]);
        
            $message = 'تم إضافة الفاتورة بنجاح!';
        } catch (PDOException $e) {
            $error = 'خطأ في إضافة الفاتورة: ' . $e->getMessage();
        }
    }
}

// معالجة تعديل فاتورة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_invoice'])) {
    if (!csrf_protect(false)) {
        $error = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        try {
        $invoice_id = intval($_POST['invoice_id']);
        $invoice_number = trim($_POST['invoice_number']);
        $supplier_id = intval($_POST['supplier_id']);
        $invoice_date = $_POST['invoice_date'];
        $due_date = $_POST['due_date'];
        $total_amount = floatval($_POST['total_amount']);
        $currency_id = intval($_POST['currency_id']);
        $exchange_rate = floatval($_POST['exchange_rate']) ?: 1.0;
        $description = trim($_POST['description']);
        $related_project_id = !empty($_POST['related_project_id']) ? intval($_POST['related_project_id']) : null;
        $budget_item_id = !empty($_POST['budget_item_id']) ? intval($_POST['budget_item_id']) : null;
        $committee_id = !empty($_POST['committee_id']) ? intval($_POST['committee_id']) : null;
        $notes = trim($_POST['notes']);
        
        // التحقق من أن الفاتورة لم تتم الدفع عليها
        $stmt = $db->prepare("SELECT paid_amount FROM supplier_invoices WHERE id = ?");
        $stmt->execute([$invoice_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($invoice['paid_amount'] > 0) {
            throw new Exception('لا يمكن تعديل فاتورة تمت الدفع عليها');
        }
        
        $remaining_amount = $total_amount;
        
        $stmt = $db->prepare("UPDATE supplier_invoices SET invoice_number = ?, supplier_id = ?, invoice_date = ?, due_date = ?, total_amount = ?, currency_id = ?, exchange_rate = ?, remaining_amount = ?, description = ?, related_project_id = ?, budget_item_id = ?, committee_id = ?, notes = ? WHERE id = ?");
        $stmt->execute([$invoice_number, $supplier_id, $invoice_date, $due_date, $total_amount, $currency_id, $exchange_rate, $remaining_amount, $description, $related_project_id, $budget_item_id, $committee_id, $notes, $invoice_id]);
        
            $message = 'تم تحديث الفاتورة بنجاح!';
        } catch (Exception $e) {
            $error = 'خطأ في تحديث الفاتورة: ' . $e->getMessage();
        }
    }
}

// معالجة تسجيل دفعة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    if (!csrf_protect(false)) {
        $error = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        try {
        $db->beginTransaction();
        
        $invoice_id = intval($_POST['invoice_id']);
        $payment_amount = floatval($_POST['payment_amount']);
        $payment_date = $_POST['payment_date'];
        $payment_method = $_POST['payment_method'];
        $reference_number = trim($_POST['reference_number']);
        $bank_name = trim($_POST['bank_name']);
        $payment_notes = trim($_POST['payment_notes']);
        
        // جلب بيانات الفاتورة
        $stmt = $db->prepare("SELECT si.*, c.currency_code, c.currency_symbol, s.name as supplier_name FROM supplier_invoices si LEFT JOIN currencies c ON si.currency_id = c.id LEFT JOIN suppliers s ON si.supplier_id = s.id WHERE si.id = ?");
        $stmt->execute([$invoice_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invoice) {
            throw new Exception('الفاتورة غير موجودة');
        }
        
        if ($payment_amount > $invoice['remaining_amount']) {
            throw new Exception('المبلغ المدفوع أكبر من المبلغ المتبقي');
        }
        
        // تسجيل الدفعة
        $stmt = $db->prepare("INSERT INTO invoice_payments (invoice_id, committee_id, payment_date, payment_amount, payment_method, reference_number, bank_name, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$invoice_id, $invoice['committee_id'], $payment_date, $payment_amount, $payment_method, $reference_number, $bank_name, $payment_notes, $user['id']]);
        $payment_id = $db->lastInsertId();
        
        // تحديث الفاتورة
        $new_paid_amount = $invoice['paid_amount'] + $payment_amount;
        $new_remaining_amount = $invoice['total_amount'] - $new_paid_amount;
        
        $new_status = 'غير مدفوع';
        if ($new_remaining_amount == 0) {
            $new_status = 'مدفوع بالكامل';
        } elseif ($new_paid_amount > 0) {
            $new_status = 'مدفوع جزئياً';
        }
        
        // التحقق من التأخير
        if ($new_status != 'مدفوع بالكامل' && strtotime($invoice['due_date']) < strtotime('today')) {
            $new_status = 'متأخر';
        }
        
        $stmt = $db->prepare("UPDATE supplier_invoices SET paid_amount = ?, remaining_amount = ?, status = ?, payment_date = ? WHERE id = ?");
        $stmt->execute([$new_paid_amount, $new_remaining_amount, $new_status, $payment_date, $invoice_id]);
        
        // إنشاء معاملة مالية تلقائياً
        $stmt = $db->prepare("INSERT INTO financial_transactions (transaction_date, type, category, description, amount, currency_id, exchange_rate, payment_method, reference_number, bank_name, supplier_invoice_id, budget_item_id, related_project_id, created_by, status) VALUES (?, 'مصروف', 'دفع فاتورة مورد', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'معتمد')");
        
        $transaction_description = "دفع فاتورة #{$invoice['invoice_number']} - {$invoice['supplier_name']}";
        
        $stmt->execute([
            $payment_date,
            $transaction_description,
            $payment_amount,
            $invoice['currency_id'],
            $invoice['exchange_rate'],
            $payment_method,
            $reference_number,
            $bank_name,
            $invoice_id,
            $invoice['budget_item_id'],
            $invoice['related_project_id'],
            $user['id']
        ]);
        
        $transaction_id = $db->lastInsertId();
        
        // ربط الدفعة بالمعاملة
        $stmt = $db->prepare("UPDATE invoice_payments SET financial_transaction_id = ? WHERE id = ?");
        $stmt->execute([$transaction_id, $payment_id]);
        
        // تحديث بند الميزانية إذا كان مرتبطاً
        if (!empty($invoice['budget_item_id'])) {
            $stmt = $db->prepare("UPDATE budget_items 
                                 SET spent_amount = spent_amount + ?, 
                                     remaining_amount = remaining_amount - ? 
                                 WHERE id = ?");
            $stmt->execute([$payment_amount, $payment_amount, $invoice['budget_item_id']]);
        }
        
        // تحديث المشروع إذا كان مرتبطاً
        if (!empty($invoice['related_project_id'])) {
            $stmt = $db->prepare("UPDATE projects 
                                 SET spent_amount = spent_amount + ? 
                                 WHERE id = ?");
            $stmt->execute([$payment_amount, $invoice['related_project_id']]);
        }
        
        $db->commit();
            $message = 'تم تسجيل الدفعة وإنشاء المعاملة المالية بنجاح!';
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'خطأ في تسجيل الدفعة: ' . $e->getMessage();
        }
    }
}

// معالجة حذف فاتورة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_invoice'])) {
    if (!csrf_protect(false)) {
        $error = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        try {
        $invoice_id = intval($_POST['invoice_id']);
        
        // التحقق من وجود دفعات
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM invoice_payments WHERE invoice_id = ?");
        $stmt->execute([$invoice_id]);
        $paymentsCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($paymentsCount > 0) {
            $error = "لا يمكن حذف الفاتورة لوجود $paymentsCount دفعة مرتبطة بها";
        } else {
            $stmt = $db->prepare("DELETE FROM supplier_invoices WHERE id = ?");
            $stmt->execute([$invoice_id]);
            $message = 'تم حذف الفاتورة بنجاح!';
        }
        } catch (PDOException $e) {
            $error = 'خطأ في حذف الفاتورة: ' . $e->getMessage();
        }
    }
}

// جلب بيانات الفاتورة للتعديل
$edit_invoice_id = $_GET['edit'] ?? 0;
$edit_invoice_data = null;

if ($edit_invoice_id) {
    $stmt = $db->prepare("SELECT * FROM supplier_invoices WHERE id = ?");
    $stmt->execute([$edit_invoice_id]);
    $edit_invoice_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

// الفلاتر
$filter_supplier = $_GET['supplier_id'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_from_date = $_GET['from_date'] ?? '';
$filter_to_date = $_GET['to_date'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($filter_supplier)) {
    $where_conditions[] = "si.supplier_id = ?";
    $params[] = $filter_supplier;
}

if (!empty($filter_status)) {
    $where_conditions[] = "si.status = ?";
    $params[] = $filter_status;
}

if (!empty($filter_from_date)) {
    $where_conditions[] = "si.invoice_date >= ?";
    $params[] = $filter_from_date;
}

if (!empty($filter_to_date)) {
    $where_conditions[] = "si.invoice_date <= ?";
    $params[] = $filter_to_date;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// جلب الفواتير
$stmt = $db->prepare("
    SELECT si.*, 
           s.name as supplier_name,
           s.supplier_code,
           c.currency_code,
           c.currency_symbol,
           bi.name as budget_item_name,
           mc.committee_name,
           (SELECT COUNT(*) FROM invoice_payments WHERE invoice_id = si.id) as payments_count
    FROM supplier_invoices si
    LEFT JOIN suppliers s ON si.supplier_id = s.id
    LEFT JOIN currencies c ON si.currency_id = c.id
    LEFT JOIN budget_items bi ON si.budget_item_id = bi.id
    LEFT JOIN municipal_committees mc ON si.committee_id = mc.id
    $where_clause
    ORDER BY si.invoice_date DESC, si.id DESC
    LIMIT 100
");
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// إضافة أسماء المشاريع إذا كانت موجودة
foreach ($invoices as &$invoice) {
    if (!empty($invoice['related_project_id'])) {
        try {
            $pstmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
            $pstmt->execute([$invoice['related_project_id']]);
            $project = $pstmt->fetch(PDO::FETCH_ASSOC);
            
            // تجربة أسماء أعمدة مختلفة
            $invoice['project_name'] = $project['name'] ?? $project['project_name'] ?? $project['title'] ?? 'مشروع #' . $invoice['related_project_id'];
        } catch (PDOException $e) {
            $invoice['project_name'] = 'مشروع #' . $invoice['related_project_id'];
        }
    } else {
        $invoice['project_name'] = null;
    }
}
unset($invoice);

// توليد رقم الفاتورة التلقائي
function generateInvoiceNumber($db) {
    $current_year = date('Y');
    
    // البحث عن آخر رقم فاتورة لهذه السنة
    $stmt = $db->prepare("
        SELECT invoice_number 
        FROM supplier_invoices 
        WHERE invoice_number LIKE ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute(["INV-{$current_year}-%"]);
    $last_invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($last_invoice) {
        // استخراج الرقم من آخر فاتورة
        $last_number = intval(substr($last_invoice['invoice_number'], -3));
        $new_number = $last_number + 1;
    } else {
        // أول فاتورة في هذه السنة
        $new_number = 1;
    }
    
    // تنسيق الرقم بثلاثة أرقام (001, 002, ...)
    return sprintf("INV-%s-%03d", $current_year, $new_number);
}

$next_invoice_number = generateInvoiceNumber($db);

// الإحصائيات
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_invoices,
        SUM(total_amount) as total_amount,
        SUM(paid_amount) as total_paid,
        SUM(remaining_amount) as total_remaining,
        SUM(CASE WHEN status = 'غير مدفوع' THEN 1 ELSE 0 END) as unpaid_count,
        SUM(CASE WHEN status = 'مدفوع جزئياً' THEN 1 ELSE 0 END) as partial_count,
        SUM(CASE WHEN status = 'مدفوع بالكامل' THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN status = 'متأخر' THEN 1 ELSE 0 END) as overdue_count
    FROM supplier_invoices
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// جلب الموردين
$stmt = $db->query("SELECT id, supplier_code, name FROM suppliers WHERE is_active = 1 ORDER BY name");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب العملات
$stmt = $db->query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY currency_code");
$currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب المشاريع
try {
    $stmt = $db->query("SELECT * FROM projects WHERE status != 'مكتمل' LIMIT 1");
    $sample_project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // تحديد اسم عمود الاسم
    $name_column = 'id';
    if ($sample_project) {
        if (isset($sample_project['name'])) {
            $name_column = 'name';
        } elseif (isset($sample_project['project_name'])) {
            $name_column = 'project_name';
        } elseif (isset($sample_project['title'])) {
            $name_column = 'title';
        }
    }
    
    // جلب جميع المشاريع باستخدام العمود الصحيح
    $stmt = $db->query("SELECT id, $name_column as project_display_name FROM projects WHERE status != 'مكتمل' ORDER BY $name_column");
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $projects = [];
}

// جلب بنود الميزانية
try {
    $stmt = $db->query("SELECT id, name, category FROM budget_items ORDER BY name");
    $budget_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // إذا كان هناك خطأ (مثل عدم وجود عمود is_active)، جرب بدونه
    try {
        $stmt = $db->query("SELECT id, name, category FROM budget_items ORDER BY name");
        $budget_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        $budget_items = [];
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة فواتير الموردين - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .modal { display: none !important; }
        .modal.active { display: flex !important; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen p-6">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">📄 إدارة فواتير الموردين</h1>
                    <p class="text-gray-600 mt-2">إدارة الفواتير والدفعات مع الربط التلقائي للمعاملات المالية</p>
                </div>
                <div class="flex gap-3">
                    <button onclick="openModal('addInvoiceModal')" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition shadow-lg">
                        ➕ إضافة فاتورة جديدة
                    </button>
                    <a href="suppliers.php" class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition shadow-lg">
                        🏪 الموردين
                    </a>
                    <a href="../comprehensive_dashboard.php" class="bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition shadow-lg">
                        ← العودة
                    </a>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 shadow">
                ✅ <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 shadow">
                ❌ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- إحصائيات -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white p-6 rounded-lg shadow-sm border-r-4 border-blue-500">
                <p class="text-sm text-gray-500">إجمالي الفواتير</p>
                <p class="text-3xl font-bold text-blue-600"><?= number_format($stats['total_invoices']) ?></p>
                <p class="text-sm text-gray-600 mt-2"><?= number_format($stats['total_amount'], 2) ?> ل.ل</p>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm border-r-4 border-green-500">
                <p class="text-sm text-gray-500">المدفوع</p>
                <p class="text-3xl font-bold text-green-600"><?= number_format($stats['paid_count']) ?></p>
                <p class="text-sm text-green-600 mt-2"><?= number_format($stats['total_paid'], 2) ?> ل.ل</p>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm border-r-4 border-yellow-500">
                <p class="text-sm text-gray-500">مدفوع جزئياً</p>
                <p class="text-3xl font-bold text-yellow-600"><?= number_format($stats['partial_count']) ?></p>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm border-r-4 border-red-500">
                <p class="text-sm text-gray-500">المتبقي / المتأخر</p>
                <p class="text-3xl font-bold text-red-600"><?= number_format($stats['overdue_count']) ?></p>
                <p class="text-sm text-red-600 mt-2"><?= number_format($stats['total_remaining'], 2) ?> ل.ل</p>
            </div>
        </div>

        <!-- فلاتر -->
        <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
            <h3 class="font-semibold mb-4 text-lg">🔍 البحث والفلترة</h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-2">المورد</label>
                    <select name="supplier_id" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">جميع الموردين</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= $supplier['id'] ?>" <?= ($filter_supplier == $supplier['id']) ? 'selected' : '' ?>>
                                [<?= htmlspecialchars($supplier['supplier_code']) ?>] <?= htmlspecialchars($supplier['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">الحالة</label>
                    <select name="status" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">جميع الحالات</option>
                        <option value="غير مدفوع" <?= ($filter_status === 'غير مدفوع') ? 'selected' : '' ?>>غير مدفوع</option>
                        <option value="مدفوع جزئياً" <?= ($filter_status === 'مدفوع جزئياً') ? 'selected' : '' ?>>مدفوع جزئياً</option>
                        <option value="مدفوع بالكامل" <?= ($filter_status === 'مدفوع بالكامل') ? 'selected' : '' ?>>مدفوع بالكامل</option>
                        <option value="متأخر" <?= ($filter_status === 'متأخر') ? 'selected' : '' ?>>متأخر</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">من تاريخ</label>
                    <input type="date" name="from_date" value="<?= htmlspecialchars($filter_from_date) ?>" 
                           class="w-full px-4 py-2 border rounded-lg">
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">إلى تاريخ</label>
                    <input type="date" name="to_date" value="<?= htmlspecialchars($filter_to_date) ?>" 
                           class="w-full px-4 py-2 border rounded-lg">
                </div>
                
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700">
                        بحث
                    </button>
                    <a href="invoices.php" class="bg-gray-500 text-white py-2 px-4 rounded-lg hover:bg-gray-600">
                        إعادة
                    </a>
                </div>
            </form>
        </div>

        <!-- جدول الفواتير -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="p-6 border-b bg-gray-50">
                <h2 class="text-xl font-semibold">📋 قائمة الفواتير (<?= count($invoices) ?>)</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="text-right p-4">رقم الفاتورة</th>
                            <th class="text-right p-4">المورد</th>
                            <th class="text-right p-4">اللجنة</th>
                            <th class="text-right p-4">تاريخ الفاتورة</th>
                            <th class="text-right p-4">الاستحقاق</th>
                            <th class="text-right p-4">المبلغ الإجمالي</th>
                            <th class="text-right p-4">المدفوع</th>
                            <th class="text-right p-4">المتبقي</th>
                            <th class="text-right p-4">الحالة</th>
                            <th class="text-right p-4">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-8 text-gray-500">
                                    📭 لا توجد فواتير
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($invoices as $invoice): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-4 font-bold text-blue-600"><?= htmlspecialchars($invoice['invoice_number']) ?></td>
                                <td class="p-4">
                                    <div class="font-semibold"><?= htmlspecialchars($invoice['supplier_name']) ?></div>
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($invoice['supplier_code']) ?></div>
                                </td>
                                <td class="p-4">
                                    <?php if (!empty($invoice['committee_name'])): ?>
                                        <span class="px-3 py-1 bg-blue-50 text-blue-700 rounded-full text-sm font-semibold">
                                            <?= htmlspecialchars($invoice['committee_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-sm text-gray-400">لا لجنة</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4"><?= date('Y-m-d', strtotime($invoice['invoice_date'])) ?></td>
                                <td class="p-4 <?= (strtotime($invoice['due_date']) < strtotime('today') && $invoice['status'] != 'مدفوع بالكامل') ? 'text-red-600 font-bold' : '' ?>">
                                    <?= date('Y-m-d', strtotime($invoice['due_date'])) ?>
                                </td>
                                <td class="p-4 font-semibold"><?= number_format($invoice['total_amount'], 2) ?> <?= htmlspecialchars($invoice['currency_symbol']) ?></td>
                                <td class="p-4 text-green-600 font-semibold"><?= number_format($invoice['paid_amount'], 2) ?> <?= htmlspecialchars($invoice['currency_symbol']) ?></td>
                                <td class="p-4 text-red-600 font-semibold"><?= number_format($invoice['remaining_amount'], 2) ?> <?= htmlspecialchars($invoice['currency_symbol']) ?></td>
                                <td class="p-4">
                                    <?php
                                    $statusColors = [
                                        'غير مدفوع' => 'bg-red-100 text-red-800',
                                        'مدفوع جزئياً' => 'bg-yellow-100 text-yellow-800',
                                        'مدفوع بالكامل' => 'bg-green-100 text-green-800',
                                        'متأخر' => 'bg-purple-100 text-purple-800'
                                    ];
                                    $statusClass = $statusColors[$invoice['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-3 py-1 rounded-full text-sm font-semibold <?= $statusClass ?>">
                                        <?= htmlspecialchars($invoice['status']) ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <div class="flex gap-2">
                                        <button onclick="viewInvoice(<?= $invoice['id'] ?>)" 
                                                class="text-blue-600 hover:text-blue-800 text-sm px-3 py-1 rounded bg-blue-100" title="عرض">
                                            👁️
                                        </button>
                                        <a href="print_invoice.php?id=<?= $invoice['id'] ?>" target="_blank"
                                           class="text-purple-600 hover:text-purple-800 text-sm px-3 py-1 rounded bg-purple-100" title="طباعة">
                                            🖨️
                                        </a>
                                        <?php if ($invoice['payments_count'] == 0): ?>
                                        <button onclick="editInvoice(<?= $invoice['id'] ?>)" 
                                                class="text-orange-600 hover:text-orange-800 text-sm px-3 py-1 rounded bg-orange-100" title="تعديل">
                                            ✏️
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($invoice['status'] != 'مدفوع بالكامل'): ?>
                                        <button onclick="addPayment(<?= $invoice['id'] ?>)" 
                                                class="text-green-600 hover:text-green-800 text-sm px-3 py-1 rounded bg-green-100" title="دفع">
                                            💰
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($invoice['payments_count'] == 0): ?>
                                        <button onclick="deleteInvoice(<?= $invoice['id'] ?>, '<?= htmlspecialchars($invoice['invoice_number']) ?>')" 
                                                class="text-red-600 hover:text-red-800 text-sm px-3 py-1 rounded bg-red-100" title="حذف">
                                            🗑️
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal إضافة فاتورة -->
    <div id="addInvoiceModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg w-full max-w-4xl max-h-screen overflow-y-auto">
            <div class="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">➕ إضافة فاتورة جديدة</h3>
                <button onclick="closeModal('addInvoiceModal')" class="text-gray-400 hover:text-gray-600 text-2xl">✕</button>
            </div>
            
            <form method="POST" class="p-6 space-y-6">
                <?php echo csrf_input('csrf_token'); ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">رقم الفاتورة *</label>
                        <input type="text" name="invoice_number" required 
                               value="<?= htmlspecialchars($next_invoice_number) ?>"
                               placeholder="<?= htmlspecialchars($next_invoice_number) ?>"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 bg-blue-50 font-mono font-bold"
                               readonly
                               title="رقم تلقائي - يمكن تعديله إذا لزم الأمر"
                               ondblclick="this.removeAttribute('readonly'); this.classList.remove('bg-blue-50')">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">المورد *</label>
                        <select name="supplier_id" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">اختر المورد</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= $supplier['id'] ?>">
                                    [<?= htmlspecialchars($supplier['supplier_code']) ?>] <?= htmlspecialchars($supplier['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">تاريخ الفاتورة *</label>
                        <input type="date" name="invoice_date" required value="<?= date('Y-m-d') ?>"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">تاريخ الاستحقاق *</label>
                        <input type="date" name="due_date" required 
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">المبلغ الإجمالي *</label>
                        <input type="number" name="total_amount" required step="0.01" min="0"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">العملة *</label>
                        <select name="currency_id" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <?php foreach ($currencies as $currency): ?>
                                <option value="<?= $currency['id'] ?>" <?= ($currency['is_default']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">سعر الصرف</label>
                        <input type="number" name="exchange_rate" step="0.0001" value="1.0000"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">المشروع المرتبط</label>
                        <select name="related_project_id" class="w-full px-4 py-2 border rounded-lg">
                            <option value="">بدون مشروع</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['project_display_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">اللجنة المرتبطة</label>
                        <select name="committee_id" class="w-full px-4 py-2 border rounded-lg">
                            <option value="">بدون لجنة</option>
                            <?php foreach ($committees as $committee): ?>
                                <option value="<?= $committee['id'] ?>"><?= htmlspecialchars($committee['committee_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">بند الميزانية</label>
                        <select name="budget_item_id" class="w-full px-4 py-2 border rounded-lg">
                            <option value="">بدون بند</option>
                            <?php foreach ($budget_items as $item): ?>
                                <option value="<?= $item['id'] ?>">
                                    <?= htmlspecialchars($item['name']) ?> (<?= htmlspecialchars($item['category']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">وصف الفاتورة</label>
                    <textarea name="description" rows="2" 
                              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">ملاحظات</label>
                    <textarea name="notes" rows="2" 
                              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="closeModal('addInvoiceModal')" 
                            class="px-6 py-2 text-gray-600 hover:text-gray-800 border rounded-lg">
                        إلغاء
                    </button>
                    <button type="submit" name="add_invoice" 
                            class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 shadow-lg">
                        ✅ إضافة الفاتورة
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal تسجيل دفعة -->
    <div id="paymentModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg w-full max-w-2xl">
            <div class="bg-green-600 text-white px-6 py-4 rounded-t-lg">
                <h3 class="text-xl font-semibold">💰 تسجيل دفعة للفاتورة</h3>
            </div>
            
            <form method="POST" class="p-6 space-y-4">
                <?php echo csrf_input('csrf_token'); ?>
                <input type="hidden" name="invoice_id" id="payment_invoice_id">
                
                <div class="bg-blue-50 p-4 rounded-lg mb-4">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div><strong>رقم الفاتورة:</strong> <span id="payment_invoice_number"></span></div>
                        <div><strong>المورد:</strong> <span id="payment_supplier_name"></span></div>
                        <div><strong>المبلغ الإجمالي:</strong> <span id="payment_total_amount"></span></div>
                        <div><strong>المتبقي:</strong> <span id="payment_remaining_amount" class="text-red-600 font-bold"></span></div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">المبلغ المدفوع *</label>
                        <input type="number" name="payment_amount" id="payment_amount" required step="0.01" min="0"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">تاريخ الدفع *</label>
                        <input type="date" name="payment_date" required value="<?= date('Y-m-d') ?>"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">طريقة الدفع *</label>
                        <select name="payment_method" required class="w-full px-4 py-2 border rounded-lg">
                            <option value="نقد">نقد</option>
                            <option value="شيك">شيك</option>
                            <option value="تحويل مصرفي">تحويل مصرفي</option>
                            <option value="بطاقة ائتمان">بطاقة ائتمان</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">رقم المرجع/الشيك</label>
                        <input type="text" name="reference_number" 
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2">اسم البنك</label>
                        <input type="text" name="bank_name" 
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2">ملاحظات</label>
                        <textarea name="payment_notes" rows="2" 
                                  class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500"></textarea>
                    </div>
                </div>
                
                <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg">
                    <p class="text-sm text-yellow-800">
                        ℹ️ <strong>ملاحظة:</strong> سيتم إنشاء معاملة مصروف تلقائياً في النظام المالي
                    </p>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="closeModal('paymentModal')" 
                            class="px-6 py-2 text-gray-600 hover:text-gray-800 border rounded-lg">
                        إلغاء
                    </button>
                    <button type="submit" name="add_payment" 
                            class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 shadow-lg">
                        💰 تسجيل الدفعة
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const invoicesData = <?= json_encode($invoices, JSON_UNESCAPED_UNICODE) ?>;
        
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function viewInvoice(id) {
            const invoice = invoicesData.find(i => i.id == id);
            if (!invoice) return;
            
            let details = `📄 تفاصيل الفاتورة\n\n`;
            details += `رقم الفاتورة: ${invoice.invoice_number}\n`;
            details += `المورد: ${invoice.supplier_name}\n`;
            details += `التاريخ: ${invoice.invoice_date}\n`;
            details += `الاستحقاق: ${invoice.due_date}\n`;
            details += `المبلغ الإجمالي: ${parseFloat(invoice.total_amount).toLocaleString()} ${invoice.currency_symbol}\n`;
            details += `المدفوع: ${parseFloat(invoice.paid_amount).toLocaleString()} ${invoice.currency_symbol}\n`;
            details += `المتبقي: ${parseFloat(invoice.remaining_amount).toLocaleString()} ${invoice.currency_symbol}\n`;
            details += `الحالة: ${invoice.status}\n`;
            details += `عدد الدفعات: ${invoice.payments_count}\n`;
            if (invoice.description) details += `\nالوصف: ${invoice.description}`;
            
            alert(details);
        }

        function addPayment(id) {
            const invoice = invoicesData.find(i => i.id == id);
            if (!invoice) return;
            
            openModal('paymentModal');
            
            document.getElementById('payment_invoice_id').value = invoice.id;
            document.getElementById('payment_invoice_number').textContent = invoice.invoice_number;
            document.getElementById('payment_supplier_name').textContent = invoice.supplier_name;
            document.getElementById('payment_total_amount').textContent = 
                parseFloat(invoice.total_amount).toLocaleString() + ' ' + invoice.currency_symbol;
            document.getElementById('payment_remaining_amount').textContent = 
                parseFloat(invoice.remaining_amount).toLocaleString() + ' ' + invoice.currency_symbol;
            
            // تعيين الحد الأقصى للمبلغ
            document.getElementById('payment_amount').max = invoice.remaining_amount;
            document.getElementById('payment_amount').value = invoice.remaining_amount;
        }

        function editInvoice(id) {
            window.location.href = 'invoices.php?edit=' + id;
        }
        
        function deleteInvoice(id, number) {
            if (confirm(`هل أنت متأكد من حذف الفاتورة "${number}"؟`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="invoice_id" value="${id}">
                    <input type="hidden" name="delete_invoice" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
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
    
    <!-- Modal تعديل الفاتورة -->
    <?php if ($edit_invoice_data): ?>
    <div class="fixed inset-0 bg-black bg-opacity-50 z-50 overflow-y-auto" style="padding: 20px 0;">
        <div class="min-h-screen flex items-center justify-center px-4">
            <div class="bg-white rounded-lg w-full max-w-4xl my-8 shadow-2xl">
                <div class="bg-orange-600 text-white px-6 py-4 rounded-t-lg sticky top-0 z-10">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-semibold">✏️ تعديل الفاتورة: <?= htmlspecialchars($edit_invoice_data['invoice_number']) ?></h3>
                        <a href="invoices.php" class="text-white hover:text-gray-200 text-2xl font-bold">&times;</a>
                    </div>
                </div>
                
                <form method="POST" class="p-6">
                    <?php echo csrf_input('csrf_token'); ?>
                    <input type="hidden" name="invoice_id" value="<?= $edit_invoice_data['id'] ?>">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- رقم الفاتورة -->
                        <div>
                            <label class="block text-sm font-medium mb-2">رقم الفاتورة *</label>
                            <input type="text" name="invoice_number" value="<?= htmlspecialchars($edit_invoice_data['invoice_number']) ?>" 
                                   class="w-full px-4 py-2 border rounded-lg" required>
                        </div>
                        
                        <!-- المورد -->
                        <div>
                            <label class="block text-sm font-medium mb-2">المورد *</label>
                            <select name="supplier_id" class="w-full px-4 py-2 border rounded-lg" required>
                                <option value="">اختر المورد</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= $supplier['id'] ?>" <?= ($edit_invoice_data['supplier_id'] == $supplier['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($supplier['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- تاريخ الفاتورة -->
                        <div>
                            <label class="block text-sm font-medium mb-2">تاريخ الفاتورة *</label>
                            <input type="date" name="invoice_date" value="<?= $edit_invoice_data['invoice_date'] ?>"
                                   class="w-full px-4 py-2 border rounded-lg" required>
                        </div>
                        
                        <!-- تاريخ الاستحقاق -->
                        <div>
                            <label class="block text-sm font-medium mb-2">تاريخ الاستحقاق *</label>
                            <input type="date" name="due_date" value="<?= $edit_invoice_data['due_date'] ?>"
                                   class="w-full px-4 py-2 border rounded-lg" required>
                        </div>
                        
                        <!-- المبلغ الإجمالي -->
                        <div>
                            <label class="block text-sm font-medium mb-2">المبلغ الإجمالي *</label>
                            <input type="number" step="0.01" name="total_amount" value="<?= $edit_invoice_data['total_amount'] ?>"
                                   class="w-full px-4 py-2 border rounded-lg" required>
                        </div>
                        
                        <!-- العملة -->
                        <div>
                            <label class="block text-sm font-medium mb-2">العملة *</label>
                            <select name="currency_id" class="w-full px-4 py-2 border rounded-lg" required>
                                <?php foreach ($currencies as $currency): ?>
                                <option value="<?= $currency['id'] ?>" <?= ($edit_invoice_data['currency_id'] == $currency['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($currency['currency_code']) ?> - <?= htmlspecialchars($currency['currency_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- سعر الصرف -->
                        <div>
                            <label class="block text-sm font-medium mb-2">سعر الصرف (اختياري)</label>
                            <input type="number" step="0.0001" name="exchange_rate" value="<?= $edit_invoice_data['exchange_rate'] ?>"
                                   class="w-full px-4 py-2 border rounded-lg" placeholder="1.0">
                        </div>
                        
                        <!-- المشروع -->
                        <div>
                            <label class="block text-sm font-medium mb-2">المشروع المرتبط (اختياري)</label>
                            <select name="related_project_id" class="w-full px-4 py-2 border rounded-lg">
                                <option value="">بدون مشروع</option>
                                <?php foreach ($projects as $project): ?>
                                <option value="<?= $project['id'] ?>" <?= ($edit_invoice_data['related_project_id'] == $project['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($project['project_display_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">اللجنة المرتبطة (اختياري)</label>
                            <select name="committee_id" class="w-full px-4 py-2 border rounded-lg">
                                <option value="">بدون لجنة</option>
                                <?php foreach ($committees as $committee): ?>
                                <option value="<?= $committee['id'] ?>" <?= ($edit_invoice_data['committee_id'] == $committee['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($committee['committee_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- بند الميزانية -->
                        <div>
                            <label class="block text-sm font-medium mb-2">بند الميزانية (اختياري)</label>
                            <select name="budget_item_id" class="w-full px-4 py-2 border rounded-lg">
                                <option value="">بدون بند</option>
                                <?php foreach ($budget_items as $item): ?>
                                <option value="<?= $item['id'] ?>" <?= ($edit_invoice_data['budget_item_id'] == $item['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($item['name']) ?> (<?= htmlspecialchars($item['category']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- الوصف -->
                    <div class="mt-6">
                        <label class="block text-sm font-medium mb-2">الوصف</label>
                        <textarea name="description" rows="3" class="w-full px-4 py-2 border rounded-lg"><?= htmlspecialchars($edit_invoice_data['description']) ?></textarea>
                    </div>
                    
                    <!-- الملاحظات -->
                    <div class="mt-6">
                        <label class="block text-sm font-medium mb-2">ملاحظات</label>
                        <textarea name="notes" rows="3" class="w-full px-4 py-2 border rounded-lg"><?= htmlspecialchars($edit_invoice_data['notes']) ?></textarea>
                    </div>
                    
                    <!-- أزرار الإجراءات -->
                    <div class="flex gap-3 mt-6 pt-6 border-t">
                        <button type="submit" name="edit_invoice" 
                                class="bg-orange-600 text-white px-6 py-3 rounded-lg hover:bg-orange-700 flex-1">
                            💾 حفظ التعديلات
                        </button>
                        <a href="invoices.php" 
                           class="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 text-center">
                            إلغاء
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>

