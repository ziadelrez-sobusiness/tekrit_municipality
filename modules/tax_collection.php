<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// التأكد من تسجيل الدخول
$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();

// تعيين ترميز UTF-8
$db->exec("SET NAMES 'utf8mb4'");
$db->exec("SET CHARACTER SET utf8mb4");
header('Content-Type: text/html; charset=utf-8');

$user = $auth->getUserInfo();

$message = '';
$error = '';

// معالجة إضافة عملية جباية جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_collection'])) {
    $citizen_id = intval($_POST['citizen_id']);
    $tax_type_id = intval($_POST['tax_type_id']);
    $base_amount = floatval($_POST['base_amount']);
    $discount_amount = floatval($_POST['discount_amount']) ?: 0;
    $penalty_amount = floatval($_POST['penalty_amount']) ?: 0;
    $total_amount = $base_amount - $discount_amount + $penalty_amount;
    $currency_id = intval($_POST['currency_id']);
    $issue_date = $_POST['issue_date'];
    $due_date = $_POST['due_date'];
    $service_description = trim($_POST['service_description']);
    $location_details = trim($_POST['location_details']);
    $period_from = !empty($_POST['period_from']) ? $_POST['period_from'] : null;
    $period_to = !empty($_POST['period_to']) ? $_POST['period_to'] : null;
    
    // جلب سعر الصرف مقابل الليرة اللبنانية
    $stmt = $db->prepare("SELECT exchange_rate_to_iqd FROM currencies WHERE id = ?");
    $stmt->execute([$currency_id]);
    $exchange_rate = $stmt->fetchColumn() ?: 1.0;
    
    if ($citizen_id && $tax_type_id && $total_amount > 0) {
        try {
            // توليد رقم الجباية
            $collection_number = 'TAX' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $query = "INSERT INTO tax_collections (collection_number, citizen_id, tax_type_id, base_amount, discount_amount, penalty_amount, total_amount, currency_id, exchange_rate, issue_date, due_date, service_description, location_details, period_from, period_to, issued_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$collection_number, $citizen_id, $tax_type_id, $base_amount, $discount_amount, $penalty_amount, $total_amount, $currency_id, $exchange_rate, $issue_date, $due_date, $service_description, $location_details, $period_from, $period_to, $user['id']]);
            
            $message = 'تم إصدار عملية الجباية بنجاح! رقم الجباية: ' . $collection_number;
        } catch (PDOException $e) {
            $error = 'خطأ في إصدار عملية الجباية: ' . $e->getMessage();
        }
    } else {
        $error = 'يرجى تعبئة الحقول المطلوبة والتأكد من صحة المبلغ';
    }
}

// معالجة تعديل عملية جباية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_collection'])) {
    $collection_id = intval($_POST['collection_id']);
    $base_amount = floatval($_POST['base_amount']);
    $discount_amount = floatval($_POST['discount_amount']) ?: 0;
    $penalty_amount = floatval($_POST['penalty_amount']) ?: 0;
    $total_amount = $base_amount - $discount_amount + $penalty_amount;
    $issue_date = $_POST['issue_date'];
    $due_date = $_POST['due_date'];
    $payment_status = $_POST['payment_status'];
    $service_description = trim($_POST['service_description']);
    $location_details = trim($_POST['location_details']);
    $period_from = !empty($_POST['period_from']) ? $_POST['period_from'] : null;
    $period_to = !empty($_POST['period_to']) ? $_POST['period_to'] : null;
    
    // معلومات الدفع
    $paid_amount = floatval($_POST['paid_amount']) ?: 0;
    $payment_method = !empty($_POST['payment_method']) ? $_POST['payment_method'] : null;
    $reference_number = !empty($_POST['reference_number']) ? trim($_POST['reference_number']) : null;
    $receipt_number = !empty($_POST['receipt_number']) ? trim($_POST['receipt_number']) : null;
    $payment_date = !empty($_POST['payment_date']) ? $_POST['payment_date'] : null;
    
    // تحديث حالة الدفع بناءً على المبلغ المدفوع
    if ($paid_amount >= $total_amount) {
        $payment_status = 'مدفوع كاملاً';
    } elseif ($paid_amount > 0) {
        $payment_status = 'مدفوع جزئياً';
    }
    
    try {
        $query = "UPDATE tax_collections SET 
                  base_amount = ?, 
                  discount_amount = ?, 
                  penalty_amount = ?, 
                  total_amount = ?, 
                  issue_date = ?,
                  due_date = ?, 
                  payment_status = ?,
                  service_description = ?, 
                  location_details = ?,
                  period_from = ?,
                  period_to = ?,
                  paid_amount = ?,
                  payment_method = ?,
                  reference_number = ?,
                  receipt_number = ?,
                  payment_date = ?,
                  updated_at = CURRENT_TIMESTAMP 
                  WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $base_amount, 
            $discount_amount, 
            $penalty_amount, 
            $total_amount, 
            $issue_date,
            $due_date, 
            $payment_status,
            $service_description, 
            $location_details,
            $period_from,
            $period_to,
            $paid_amount,
            $payment_method,
            $reference_number,
            $receipt_number,
            $payment_date,
            $collection_id
        ]);
        
        $message = 'تم تعديل عملية الجباية بنجاح!';
    } catch (PDOException $e) {
        $error = 'خطأ في تعديل عملية الجباية: ' . $e->getMessage();
    }
}

// معالجة إلغاء عملية جباية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_collection'])) {
    $collection_id = intval($_POST['collection_id']);
    $cancel_reason = trim($_POST['cancel_reason']);
    
    try {
        $query = "UPDATE tax_collections SET payment_status = 'ملغي', notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$cancel_reason, $collection_id]);
        
        $message = 'تم إلغاء عملية الجباية بنجاح!';
    } catch (PDOException $e) {
        $error = 'خطأ في إلغاء عملية الجباية: ' . $e->getMessage();
    }
}

// معالجة تسجيل دفعة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $collection_id = intval($_POST['collection_id']);
    $paid_amount = floatval($_POST['paid_amount']);
    $payment_method = $_POST['payment_method'];
    $reference_number = trim($_POST['reference_number']);
    $receipt_number = trim($_POST['receipt_number']);
    
    try {
        // جلب بيانات الجباية الحالية
        $stmt = $db->prepare("SELECT total_amount, paid_amount FROM tax_collections WHERE id = ?");
        $stmt->execute([$collection_id]);
        $collection = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($collection) {
            $new_paid_amount = $collection['paid_amount'] + $paid_amount;
            $payment_status = 'مدفوع جزئياً';
            
            if ($new_paid_amount >= $collection['total_amount']) {
                $payment_status = 'مدفوع كاملاً';
                $payment_date = date('Y-m-d');
            }
            
            $query = "UPDATE tax_collections SET paid_amount = ?, payment_status = ?, payment_method = ?, reference_number = ?, receipt_number = ?, payment_date = ?, collected_by_user_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$new_paid_amount, $payment_status, $payment_method, $reference_number, $receipt_number, $payment_date ?? null, $user['id'], $collection_id]);
            
            $message = 'تم تسجيل الدفعة بنجاح!';
        }
    } catch (PDOException $e) {
        $error = 'خطأ في تسجيل الدفعة: ' . $e->getMessage();
    }
}

// جلب عمليات الجباية
try {
    $filter_status = $_GET['status'] ?? '';
    $filter_type = $_GET['type'] ?? '';
    $filter_citizen = $_GET['citizen'] ?? '';
    
    $where_conditions = [];
    $params = [];
    
    if (!empty($filter_status)) {
        $where_conditions[] = "tc.payment_status = ?";
        $params[] = $filter_status;
    }
    
    if (!empty($filter_type)) {
        $where_conditions[] = "tt.category = ?";
        $params[] = $filter_type;
    }
    
    if (!empty($filter_citizen)) {
        $where_conditions[] = "c.full_name LIKE ?";
        $params[] = "%$filter_citizen%";
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $stmt = $db->prepare("
        SELECT tc.*, 
               c.full_name as citizen_name, c.citizen_number, c.phone as citizen_phone,
               tt.tax_name, tt.category as tax_category,
               cur.currency_symbol, cur.currency_code,
               u.full_name as issued_by_name
        FROM tax_collections tc 
        LEFT JOIN citizens c ON tc.citizen_id = c.id
        LEFT JOIN tax_types tt ON tc.tax_type_id = tt.id
        LEFT JOIN currencies cur ON tc.currency_id = cur.id
        LEFT JOIN users u ON tc.issued_by_user_id = u.id
        $where_clause
        ORDER BY tc.issue_date DESC, tc.created_at DESC 
        LIMIT 50
    ");
    $stmt->execute($params);
    $collections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات الجباية حسب العملة (استثناء الملغاة)
    $stmt = $db->query("
        SELECT 
            tc.payment_status,
            cur.currency_symbol,
            cur.currency_code,
            COUNT(*) as count,
            SUM(tc.total_amount) as total_amount,
            SUM(tc.paid_amount) as paid_amount
        FROM tax_collections tc
        LEFT JOIN currencies cur ON tc.currency_id = cur.id
        WHERE tc.payment_status != 'ملغي'
        GROUP BY tc.payment_status, cur.currency_symbol, cur.currency_code
        ORDER BY cur.currency_code, tc.payment_status
    ");
    $collection_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات عامة حسب العملة (استثناء الملغاة)
    $stmt = $db->query("
        SELECT 
            cur.currency_symbol,
            cur.currency_code,
            COUNT(*) as total_collections,
            SUM(tc.total_amount) as total_amount,
            SUM(tc.paid_amount) as paid_amount,
            SUM(tc.total_amount - tc.paid_amount) as outstanding_amount
        FROM tax_collections tc
        LEFT JOIN currencies cur ON tc.currency_id = cur.id
        WHERE tc.payment_status != 'ملغي'
        GROUP BY cur.currency_symbol, cur.currency_code
        ORDER BY cur.currency_code
    ");
    $general_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات العمليات الملغاة (منفصلة)
    $stmt = $db->query("
        SELECT 
            cur.currency_symbol,
            cur.currency_code,
            COUNT(*) as cancelled_count,
            SUM(tc.total_amount) as cancelled_amount
        FROM tax_collections tc
        LEFT JOIN currencies cur ON tc.currency_id = cur.id
        WHERE tc.payment_status = 'ملغي'
        GROUP BY cur.currency_symbol, cur.currency_code
        ORDER BY cur.currency_code
    ");
    $cancelled_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب العملات
    $stmt = $db->query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY currency_code");
    $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب المواطنين
    $stmt = $db->query("SELECT id, full_name, citizen_number FROM citizens WHERE is_active = 1 ORDER BY full_name LIMIT 100");
    $citizens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب أنواع الجباية
    $stmt = $db->query("SELECT * FROM tax_types WHERE is_active = 1 ORDER BY tax_name");
    $tax_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error .= ' | خطأ في جلب البيانات: ' . $e->getMessage();
    $collections = [];
    $collection_stats = [];
    $general_stats = [];
    $cancelled_stats = [];
    $currencies = [];
    $citizens = [];
    $tax_types = [];
}

$payment_statuses = ['مستحق', 'مدفوع جزئياً', 'مدفوع كاملاً', 'متأخر', 'معفى', 'ملغي'];
$tax_categories = ['رسوم خدمات', 'ضرائب', 'غرامات', 'تراخيص', 'إشغالات', 'أخرى'];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الجباية - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .modal { 
            display: none !important; 
        }
        .modal.active { 
            display: flex !important; 
        }
    </style>
</head>
<body class="bg-slate-100">
    <div class="min-h-screen p-6">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-slate-800">إدارة الجباية</h1>
                <div class="flex gap-3">
                    <button onclick="openModal('addCollectionModal')" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        ➕ إصدار جباية جديدة
                    </button>
                    <a href="../comprehensive_dashboard.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                        ← العودة للوحة التحكم
                    </a>
                </div>
            </div>
            <p class="text-slate-600 mt-2">إدارة جباية الرسوم والضرائب من المواطنين والمؤسسات</p>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- إحصائيات الجباية حسب العملة -->
        <?php foreach ($general_stats as $stats): ?>
        <div class="mb-6">
            <h3 class="text-lg font-semibold mb-3 text-slate-700">
                💱 إحصائيات الجباية بالعملة: <?= htmlspecialchars($stats['currency_symbol']) ?> (<?= htmlspecialchars($stats['currency_code']) ?>)
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">عدد العمليات</p>
                            <p class="text-2xl font-bold text-blue-600"><?= number_format($stats['total_collections']) ?></p>
                        </div>
                        <div class="bg-blue-100 text-blue-600 p-3 rounded-full">📊</div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">إجمالي المبالغ</p>
                            <p class="text-2xl font-bold text-blue-600">
                                <?= number_format($stats['total_amount'], 2) ?> <?= htmlspecialchars($stats['currency_symbol']) ?>
                            </p>
                        </div>
                        <div class="bg-blue-100 text-blue-600 p-3 rounded-full">💰</div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">المبلغ المحصل</p>
                            <p class="text-2xl font-bold text-green-600">
                                <?= number_format($stats['paid_amount'], 2) ?> <?= htmlspecialchars($stats['currency_symbol']) ?>
                            </p>
                        </div>
                        <div class="bg-green-100 text-green-600 p-3 rounded-full">✅</div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">المبلغ المستحق</p>
                            <p class="text-2xl font-bold text-red-600">
                                <?= number_format($stats['outstanding_amount'], 2) ?> <?= htmlspecialchars($stats['currency_symbol']) ?>
                            </p>
                        </div>
                        <div class="bg-red-100 text-red-600 p-3 rounded-full">⏰</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($general_stats)): ?>
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded mb-6">
            ℹ️ لا توجد عمليات جباية نشطة
        </div>
        <?php endif; ?>
        
        <!-- إحصائيات العمليات الملغاة -->
        <?php if (!empty($cancelled_stats)): ?>
        <div class="mb-6">
            <h3 class="text-lg font-semibold mb-3 text-red-700">❌ العمليات الملغاة (لا تُحتسب في الإحصائيات)</h3>
            <?php foreach ($cancelled_stats as $stats): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-sm text-red-600">العملة</p>
                        <p class="font-bold text-lg"><?= htmlspecialchars($stats['currency_symbol']) ?> (<?= htmlspecialchars($stats['currency_code']) ?>)</p>
                    </div>
                    <div>
                        <p class="text-sm text-red-600">عدد العمليات الملغاة</p>
                        <p class="font-bold text-lg"><?= number_format($stats['cancelled_count']) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-red-600">إجمالي المبالغ الملغاة</p>
                        <p class="font-bold text-lg"><?= number_format($stats['cancelled_amount'], 2) ?> <?= htmlspecialchars($stats['currency_symbol']) ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- فلاتر البحث -->
        <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
            <h3 class="font-semibold mb-4">البحث والفلترة</h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">حالة الدفع</label>
                    <select name="status" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">جميع الحالات</option>
                        <?php foreach ($payment_statuses as $status): ?>
                            <option value="<?= $status ?>" <?= ($filter_status === $status) ? 'selected' : '' ?>><?= $status ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نوع الجباية</label>
                    <select name="type" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">جميع الأنواع</option>
                        <?php foreach ($tax_categories as $category): ?>
                            <option value="<?= $category ?>" <?= ($filter_type === $category) ? 'selected' : '' ?>><?= $category ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">اسم المواطن</label>
                    <input type="text" name="citizen" value="<?= htmlspecialchars($filter_citizen) ?>" 
                           placeholder="ابحث باسم المواطن"
                           class="w-full p-2 border border-gray-300 rounded-md">
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                        تطبيق الفلتر
                    </button>
                </div>
            </form>
        </div>

        <!-- جدول عمليات الجباية -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="p-6 border-b">
                <h2 class="text-xl font-semibold">عمليات الجباية</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="text-right p-4 font-semibold">رقم الجباية</th>
                            <th class="text-right p-4 font-semibold">المواطن</th>
                            <th class="text-right p-4 font-semibold">نوع الجباية</th>
                            <th class="text-right p-4 font-semibold">المبلغ الكلي</th>
                            <th class="text-right p-4 font-semibold">المبلغ المدفوع</th>
                            <th class="text-right p-4 font-semibold">المبلغ المتبقي</th>
                            <th class="text-right p-4 font-semibold">حالة الدفع</th>
                            <th class="text-right p-4 font-semibold">تاريخ الاستحقاق</th>
                            <th class="text-right p-4 font-semibold">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($collections as $collection): ?>
                        <tr class="border-b hover:bg-slate-50">
                            <td class="p-4 font-medium"><?= htmlspecialchars($collection['collection_number']) ?></td>
                            <td class="p-4">
                                <div class="font-medium"><?= htmlspecialchars($collection['citizen_name']) ?></div>
                                <div class="text-sm text-slate-500"><?= htmlspecialchars($collection['citizen_number']) ?></div>
                                <?php if (!empty($collection['citizen_phone'])): ?>
                                    <div class="text-sm text-blue-600"><?= htmlspecialchars($collection['citizen_phone']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <div class="font-medium text-sm"><?= htmlspecialchars($collection['tax_name']) ?></div>
                                <span class="px-2 py-1 rounded text-xs bg-blue-100 text-blue-800">
                                    <?= htmlspecialchars($collection['tax_category']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 font-semibold text-green-600">
                                <?= number_format($collection['total_amount']) ?> <?= htmlspecialchars($collection['currency_symbol']) ?>
                                <?php if ($collection['currency_id'] != 1): ?>
                                    <br><small class="text-gray-500">(<?= number_format($collection['amount_in_lbp']) ?> ل.ل)</small>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 font-semibold text-green-600">
                                <?= number_format($collection['paid_amount']) ?> <?= htmlspecialchars($collection['currency_symbol']) ?>
                            </td>
                            <td class="p-4 font-semibold text-red-600">
                                <?= number_format($collection['remaining_amount']) ?> <?= htmlspecialchars($collection['currency_symbol']) ?>
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-sm 
                                    <?= $collection['payment_status'] === 'مدفوع كاملاً' ? 'bg-green-100 text-green-800' : 
                                       ($collection['payment_status'] === 'مدفوع جزئياً' ? 'bg-yellow-100 text-yellow-800' : 
                                       ($collection['payment_status'] === 'متأخر' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800')) ?>">
                                    <?= htmlspecialchars($collection['payment_status']) ?>
                                </span>
                            </td>
                            <td class="p-4 text-sm">
                                <?= date('Y-m-d', strtotime($collection['due_date'])) ?>
                                <?php if ($collection['payment_status'] !== 'مدفوع كاملاً' && strtotime($collection['due_date']) < time()): ?>
                                    <div class="text-red-600 text-xs">متأخر</div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <div class="flex gap-2 flex-wrap">
                                    <?php if ($collection['payment_status'] !== 'مدفوع كاملاً' && $collection['payment_status'] !== 'ملغي'): ?>
                                    <button onclick="recordPayment(<?= $collection['id'] ?>, <?= $collection['remaining_amount'] ?>)" 
                                            class="text-green-600 hover:text-green-800 text-sm font-medium">
                                        💰 تسجيل دفعة
                                    </button>
                                    <?php endif; ?>
                                    
                                    <button onclick="viewDetails(<?= $collection['id'] ?>)" 
                                            class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        👁️ عرض
                                    </button>
                                    
                                    <?php if ($collection['payment_status'] !== 'مدفوع كاملاً' && $collection['payment_status'] !== 'ملغي'): ?>
                                    <button onclick="editCollection(<?= $collection['id'] ?>)" 
                                            class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                        ✏️ تعديل
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($collection['payment_status'] !== 'ملغي'): ?>
                                    <button onclick="cancelCollection(<?= $collection['id'] ?>)" 
                                            class="text-red-600 hover:text-red-800 text-sm font-medium">
                                        ❌ إلغاء
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal إصدار جباية جديدة -->
    <div id="addCollectionModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-4xl max-h-screen overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold">إصدار جباية جديدة</h3>
                <button onclick="closeModal('addCollectionModal')" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            
            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">المواطن *</label>
                        <select name="citizen_id" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">اختر المواطن</option>
                            <?php foreach ($citizens as $citizen): ?>
                                <option value="<?= $citizen['id'] ?>">
                                    <?= htmlspecialchars($citizen['full_name']) ?> (<?= htmlspecialchars($citizen['citizen_number']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">نوع الجباية *</label>
                        <select name="tax_type_id" required onchange="loadTaxDetails(this.value)" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">اختر نوع الجباية</option>
                            <?php foreach ($tax_types as $tax_type): ?>
                                <option value="<?= $tax_type['id'] ?>" data-amount="<?= $tax_type['base_amount'] ?>">
                                    <?= htmlspecialchars($tax_type['tax_name']) ?> - <?= htmlspecialchars($tax_type['category']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">المبلغ الأساسي *</label>
                        <input type="number" step="0.01" name="base_amount" id="base_amount" required 
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">مبلغ الخصم</label>
                        <input type="number" step="0.01" name="discount_amount" value="0" 
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">مبلغ الغرامة</label>
                        <input type="number" step="0.01" name="penalty_amount" value="0" 
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">العملة</label>
                        <select name="currency_id" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <?php foreach ($currencies as $currency): ?>
                            <option value="<?= $currency['id'] ?>" <?= $currency['currency_code'] === 'IQD' ? 'selected' : '' ?>>
                                <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">تاريخ الإصدار *</label>
                        <input type="date" name="issue_date" value="<?= date('Y-m-d') ?>" required 
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">تاريخ الاستحقاق *</label>
                        <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required 
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">وصف الخدمة</label>
                    <textarea name="service_description" rows="3" 
                              class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">تفاصيل الموقع</label>
                        <input type="text" name="location_details" 
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">من تاريخ (للرسوم الدورية)</label>
                        <input type="date" name="period_from" 
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">إلى تاريخ (للرسوم الدورية)</label>
                        <input type="date" name="period_to" 
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeModal('addCollectionModal')" 
                            class="px-4 py-2 text-slate-600 hover:text-slate-800">
                        إلغاء
                    </button>
                    <button type="submit" name="add_collection" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        إصدار الجباية
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal عرض تفاصيل الجباية -->
    <div id="detailsModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-3xl max-h-screen overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold">تفاصيل عملية الجباية</h3>
                <button onclick="closeModal('detailsModal')" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            
            <div id="detailsContent" class="space-y-4">
                <!-- سيتم ملء التفاصيل هنا بواسطة JavaScript -->
            </div>
            
            <div class="flex justify-end mt-6">
                <button type="button" onclick="closeModal('detailsModal')" 
                        class="bg-slate-600 text-white px-4 py-2 rounded-lg hover:bg-slate-700">
                    إغلاق
                </button>
            </div>
        </div>
    </div>

    <!-- Modal تعديل عملية جباية -->
    <div id="editModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-4xl max-h-screen overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold">تعديل عملية الجباية</h3>
                <button onclick="closeModal('editModal')" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            
            <form method="POST" class="space-y-6">
                <input type="hidden" name="collection_id" id="edit_collection_id">
                
                <!-- معلومات أساسية (للعرض فقط) -->
                <div class="bg-slate-50 p-4 rounded-lg">
                    <h4 class="font-semibold mb-3 text-slate-700">المعلومات الأساسية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div>
                            <p class="text-slate-500">رقم الجباية</p>
                            <p class="font-medium" id="edit_display_number">-</p>
                        </div>
                        <div>
                            <p class="text-slate-500">المواطن</p>
                            <p class="font-medium" id="edit_display_citizen">-</p>
                        </div>
                        <div>
                            <p class="text-slate-500">نوع الجباية</p>
                            <p class="font-medium" id="edit_display_tax_type">-</p>
                        </div>
                    </div>
                </div>
                
                <!-- المبالغ المالية -->
                <div>
                    <h4 class="font-semibold mb-3">المبالغ المالية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">المبلغ الأساسي *</label>
                            <input type="number" step="0.01" name="base_amount" id="edit_base_amount" required 
                                   onchange="calculateEditTotal()"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الخصم</label>
                            <input type="number" step="0.01" name="discount_amount" id="edit_discount_amount" value="0"
                                   onchange="calculateEditTotal()"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الغرامة</label>
                            <input type="number" step="0.01" name="penalty_amount" id="edit_penalty_amount" value="0"
                                   onchange="calculateEditTotal()"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                        <div class="flex justify-between items-center">
                            <span class="font-semibold">المبلغ الإجمالي:</span>
                            <span class="text-xl font-bold text-blue-600" id="edit_total_display">0</span>
                        </div>
                    </div>
                </div>
                
                <!-- التواريخ -->
                <div>
                    <h4 class="font-semibold mb-3">التواريخ</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">تاريخ الإصدار</label>
                            <input type="date" name="issue_date" id="edit_issue_date"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">تاريخ الاستحقاق *</label>
                            <input type="date" name="due_date" id="edit_due_date" required
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">حالة الدفع</label>
                            <select name="payment_status" id="edit_payment_status"
                                    class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="مستحق">مستحق</option>
                                <option value="مدفوع جزئياً">مدفوع جزئياً</option>
                                <option value="متأخر">متأخر</option>
                                <option value="معفى">معفى</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- الفترة الزمنية -->
                <div>
                    <h4 class="font-semibold mb-3">الفترة الزمنية (اختياري)</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">من تاريخ</label>
                            <input type="date" name="period_from" id="edit_period_from"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">إلى تاريخ</label>
                            <input type="date" name="period_to" id="edit_period_to"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>
                
                <!-- التفاصيل -->
                <div>
                    <h4 class="font-semibold mb-3">التفاصيل</h4>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">وصف الخدمة</label>
                            <textarea name="service_description" id="edit_service_description" rows="3"
                                      class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">تفاصيل الموقع</label>
                            <textarea name="location_details" id="edit_location_details" rows="2"
                                      class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- معلومات الدفع -->
                <div>
                    <h4 class="font-semibold mb-3">معلومات الدفع</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">المبلغ المدفوع</label>
                            <input type="number" step="0.01" name="paid_amount" id="edit_paid_amount" 
                                   onchange="calculateEditRemaining()"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">طريقة الدفع</label>
                            <select name="payment_method" id="edit_payment_method"
                                    class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">لم يتم الدفع</option>
                                <option value="نقد">نقد</option>
                                <option value="شيك">شيك</option>
                                <option value="تحويل مصرفي">تحويل مصرفي</option>
                                <option value="بطاقة ائتمان">بطاقة ائتمان</option>
                                <option value="أخرى">أخرى</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">رقم المرجع</label>
                            <input type="text" name="reference_number" id="edit_reference_number"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">رقم الإيصال</label>
                            <input type="text" name="receipt_number" id="edit_receipt_number"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">تاريخ الدفع</label>
                            <input type="date" name="payment_date" id="edit_payment_date"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div class="mt-4 p-3 bg-green-50 rounded-lg">
                        <div class="flex justify-between items-center">
                            <span class="font-semibold text-red-600">المبلغ المتبقي:</span>
                            <span class="text-xl font-bold text-red-600" id="edit_remaining_display">0</span>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="closeModal('editModal')" 
                            class="px-6 py-2 text-slate-600 hover:text-slate-800 border rounded-lg">
                        إلغاء
                    </button>
                    <button type="submit" name="edit_collection" 
                            class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">
                        💾 حفظ التعديلات
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal إلغاء عملية جباية -->
    <div id="cancelModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-lg">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold text-red-600">⚠️ إلغاء عملية الجباية</h3>
                <button onclick="closeModal('cancelModal')" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="collection_id" id="cancel_collection_id">
                
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                    <p class="text-red-800 text-sm">
                        ⚠️ تحذير: إلغاء عملية الجباية سيغير حالتها إلى "ملغي" ولن يمكن التراجع عن هذا الإجراء.
                    </p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">سبب الإلغاء *</label>
                    <textarea name="cancel_reason" required rows="4" 
                              placeholder="يرجى توضيح سبب إلغاء عملية الجباية..."
                              class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-red-500"></textarea>
                </div>
                
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeModal('cancelModal')" 
                            class="px-4 py-2 text-slate-600 hover:text-slate-800">
                        تراجع
                    </button>
                    <button type="submit" name="cancel_collection" 
                            class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                        تأكيد الإلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal تسجيل دفعة -->
    <div id="paymentModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-lg">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold">تسجيل دفعة</h3>
                <button onclick="closeModal('paymentModal')" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="collection_id" id="payment_collection_id">
                
                <div>
                    <label class="block text-sm font-medium mb-2">مبلغ الدفعة *</label>
                    <input type="number" step="0.01" name="paid_amount" id="payment_amount" required 
                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">طريقة الدفع *</label>
                    <select name="payment_method" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">اختر طريقة الدفع</option>
                        <option value="نقد">نقد</option>
                        <option value="شيك">شيك</option>
                        <option value="تحويل مصرفي">تحويل مصرفي</option>
                        <option value="بطاقة ائتمان">بطاقة ائتمان</option>
                        <option value="أخرى">أخرى</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">رقم المرجع</label>
                    <input type="text" name="reference_number" 
                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">رقم الإيصال</label>
                    <input type="text" name="receipt_number" 
                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeModal('paymentModal')" 
                            class="px-4 py-2 text-slate-600 hover:text-slate-800">
                        إلغاء
                    </button>
                    <button type="submit" name="record_payment" 
                            class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                        تسجيل الدفعة
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // بيانات عمليات الجباية
        const collectionsData = <?= json_encode($collections, JSON_UNESCAPED_UNICODE) ?>;
        
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function loadTaxDetails(taxTypeId) {
            if (taxTypeId) {
                const option = document.querySelector(`option[value="${taxTypeId}"]`);
                const baseAmount = option.getAttribute('data-amount');
                document.getElementById('base_amount').value = baseAmount;
            }
        }

        function recordPayment(collectionId, remainingAmount) {
            document.getElementById('payment_collection_id').value = collectionId;
            document.getElementById('payment_amount').value = remainingAmount;
            document.getElementById('payment_amount').max = remainingAmount;
            openModal('paymentModal');
        }
        
        function calculateEditTotal() {
            const base = parseFloat(document.getElementById('edit_base_amount').value) || 0;
            const discount = parseFloat(document.getElementById('edit_discount_amount').value) || 0;
            const penalty = parseFloat(document.getElementById('edit_penalty_amount').value) || 0;
            const total = base - discount + penalty;
            
            const collection = collectionsData.find(c => c.id == document.getElementById('edit_collection_id').value);
            const currencySymbol = collection ? collection.currency_symbol : '';
            
            document.getElementById('edit_total_display').textContent = total.toLocaleString() + ' ' + currencySymbol;
            
            // إعادة حساب المبلغ المتبقي
            calculateEditRemaining();
        }
        
        function calculateEditRemaining() {
            const base = parseFloat(document.getElementById('edit_base_amount').value) || 0;
            const discount = parseFloat(document.getElementById('edit_discount_amount').value) || 0;
            const penalty = parseFloat(document.getElementById('edit_penalty_amount').value) || 0;
            const total = base - discount + penalty;
            
            const paid = parseFloat(document.getElementById('edit_paid_amount').value) || 0;
            const remaining = total - paid;
            
            const collection = collectionsData.find(c => c.id == document.getElementById('edit_collection_id').value);
            const currencySymbol = collection ? collection.currency_symbol : '';
            
            document.getElementById('edit_remaining_display').textContent = remaining.toLocaleString() + ' ' + currencySymbol;
            
            // تحديث حالة الدفع تلقائياً
            const statusSelect = document.getElementById('edit_payment_status');
            if (paid >= total && paid > 0) {
                statusSelect.value = 'مدفوع كاملاً';
            } else if (paid > 0) {
                statusSelect.value = 'مدفوع جزئياً';
            }
        }
        
        function editCollection(collectionId) {
            const collection = collectionsData.find(c => c.id == collectionId);
            if (!collection) {
                alert('لم يتم العثور على بيانات الجباية');
                return;
            }
            
            const remainingAmount = parseFloat(collection.total_amount) - parseFloat(collection.paid_amount);
            
            // ملء المعلومات الأساسية (للعرض فقط)
            document.getElementById('edit_display_number').textContent = collection.collection_number || '-';
            document.getElementById('edit_display_citizen').textContent = collection.citizen_name || '-';
            document.getElementById('edit_display_tax_type').textContent = collection.tax_name || '-';
            
            // ملء حقول التعديل
            document.getElementById('edit_collection_id').value = collection.id;
            document.getElementById('edit_base_amount').value = collection.base_amount;
            document.getElementById('edit_discount_amount').value = collection.discount_amount || 0;
            document.getElementById('edit_penalty_amount').value = collection.penalty_amount || 0;
            document.getElementById('edit_issue_date').value = collection.issue_date;
            document.getElementById('edit_due_date').value = collection.due_date;
            document.getElementById('edit_payment_status').value = collection.payment_status;
            document.getElementById('edit_service_description').value = collection.service_description || '';
            document.getElementById('edit_location_details').value = collection.location_details || '';
            document.getElementById('edit_period_from').value = collection.period_from || '';
            document.getElementById('edit_period_to').value = collection.period_to || '';
            
            // ملء معلومات الدفع
            document.getElementById('edit_paid_amount').value = collection.paid_amount || 0;
            document.getElementById('edit_payment_method').value = collection.payment_method || '';
            document.getElementById('edit_reference_number').value = collection.reference_number || '';
            document.getElementById('edit_receipt_number').value = collection.receipt_number || '';
            document.getElementById('edit_payment_date').value = collection.payment_date || '';
            
            // حساب المجموع والمتبقي
            calculateEditTotal();
            
            openModal('editModal');
        }
        
        function cancelCollection(collectionId) {
            const collection = collectionsData.find(c => c.id == collectionId);
            if (!collection) {
                alert('لم يتم العثور على بيانات الجباية');
                return;
            }
            
            if (confirm(`هل أنت متأكد من إلغاء عملية الجباية رقم ${collection.collection_number}؟`)) {
                document.getElementById('cancel_collection_id').value = collectionId;
                openModal('cancelModal');
            }
        }

        function viewDetails(collectionId) {
            const collection = collectionsData.find(c => c.id == collectionId);
            if (!collection) {
                alert('لم يتم العثور على بيانات الجباية');
                return;
            }
            
            const remainingAmount = parseFloat(collection.total_amount) - parseFloat(collection.paid_amount);
            const statusColors = {
                'مستحق': 'bg-yellow-100 text-yellow-800',
                'مدفوع جزئياً': 'bg-blue-100 text-blue-800',
                'مدفوع كاملاً': 'bg-green-100 text-green-800',
                'متأخر': 'bg-red-100 text-red-800',
                'معفى': 'bg-purple-100 text-purple-800',
                'ملغي': 'bg-gray-100 text-gray-800'
            };
            
            const detailsHTML = `
                <div class="bg-slate-50 p-4 rounded-lg">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-slate-500">رقم الجباية</p>
                            <p class="font-semibold">${collection.collection_number || '-'}</p>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">الحالة</p>
                            <span class="inline-block px-2 py-1 text-xs rounded ${statusColors[collection.payment_status] || 'bg-gray-100 text-gray-800'}">
                                ${collection.payment_status}
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="border-t pt-4">
                    <h4 class="font-semibold mb-3">معلومات المواطن</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-slate-500">الاسم</p>
                            <p class="font-medium">${collection.citizen_name || '-'}</p>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">رقم الهوية</p>
                            <p class="font-medium">${collection.citizen_number || '-'}</p>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">رقم الهاتف</p>
                            <p class="font-medium">${collection.citizen_phone || '-'}</p>
                        </div>
                    </div>
                </div>
                
                <div class="border-t pt-4">
                    <h4 class="font-semibold mb-3">تفاصيل الجباية</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-slate-500">نوع الجباية</p>
                            <p class="font-medium">${collection.tax_name || '-'}</p>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">الفئة</p>
                            <p class="font-medium">${collection.tax_category || '-'}</p>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">تاريخ الإصدار</p>
                            <p class="font-medium">${collection.issue_date || '-'}</p>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">تاريخ الاستحقاق</p>
                            <p class="font-medium">${collection.due_date || '-'}</p>
                        </div>
                    </div>
                </div>
                
                <div class="border-t pt-4">
                    <h4 class="font-semibold mb-3">المبالغ المالية</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-slate-500">المبلغ الأساسي</p>
                            <p class="font-medium">${parseFloat(collection.base_amount).toLocaleString()} ${collection.currency_symbol}</p>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">الخصم</p>
                            <p class="font-medium text-green-600">-${parseFloat(collection.discount_amount).toLocaleString()} ${collection.currency_symbol}</p>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">الغرامة</p>
                            <p class="font-medium text-red-600">+${parseFloat(collection.penalty_amount).toLocaleString()} ${collection.currency_symbol}</p>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">المبلغ الإجمالي</p>
                            <p class="font-bold text-lg text-blue-600">${parseFloat(collection.total_amount).toLocaleString()} ${collection.currency_symbol}</p>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">المبلغ المدفوع</p>
                            <p class="font-medium text-green-600">${parseFloat(collection.paid_amount).toLocaleString()} ${collection.currency_symbol}</p>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">المبلغ المتبقي</p>
                            <p class="font-bold text-lg text-red-600">${remainingAmount.toLocaleString()} ${collection.currency_symbol}</p>
                        </div>
                    </div>
                </div>
                
                ${collection.service_description ? `
                <div class="border-t pt-4">
                    <h4 class="font-semibold mb-2">وصف الخدمة</h4>
                    <p class="text-slate-700">${collection.service_description}</p>
                </div>
                ` : ''}
                
                ${collection.location_details ? `
                <div class="border-t pt-4">
                    <h4 class="font-semibold mb-2">تفاصيل الموقع</h4>
                    <p class="text-slate-700">${collection.location_details}</p>
                </div>
                ` : ''}
                
                ${collection.period_from && collection.period_to ? `
                <div class="border-t pt-4">
                    <h4 class="font-semibold mb-2">الفترة الزمنية</h4>
                    <p class="text-slate-700">من ${collection.period_from} إلى ${collection.period_to}</p>
                </div>
                ` : ''}
                
                ${collection.payment_method ? `
                <div class="border-t pt-4">
                    <h4 class="font-semibold mb-3">معلومات الدفع</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-slate-500">طريقة الدفع</p>
                            <p class="font-medium">${collection.payment_method}</p>
                        </div>
                        ${collection.payment_date ? `
                        <div>
                            <p class="text-sm text-slate-500">تاريخ الدفع</p>
                            <p class="font-medium">${collection.payment_date}</p>
                        </div>
                        ` : ''}
                        ${collection.reference_number ? `
                        <div>
                            <p class="text-sm text-slate-500">رقم المرجع</p>
                            <p class="font-medium">${collection.reference_number}</p>
                        </div>
                        ` : ''}
                        ${collection.receipt_number ? `
                        <div>
                            <p class="text-sm text-slate-500">رقم الإيصال</p>
                            <p class="font-medium">${collection.receipt_number}</p>
                        </div>
                        ` : ''}
                    </div>
                </div>
                ` : ''}
                
                <div class="border-t pt-4">
                    <div class="grid grid-cols-2 gap-4 text-sm text-slate-500">
                        <div>
                            <p>أصدرت بواسطة: <span class="font-medium text-slate-700">${collection.issued_by_name || '-'}</span></p>
                        </div>
                        <div>
                            <p>تاريخ الإنشاء: <span class="font-medium text-slate-700">${collection.created_at || '-'}</span></p>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('detailsContent').innerHTML = detailsHTML;
            openModal('detailsModal');
        }
    </script>
</body>
</html> 
