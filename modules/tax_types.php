<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// التأكد من تسجيل الدخول
$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();
$user = $auth->getUserInfo();

$message = '';
$error = '';

// معالجة إضافة نوع جباية جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tax_type'])) {
    $tax_code = trim($_POST['tax_code']);
    $tax_name = trim($_POST['tax_name']);
    $tax_name_en = trim($_POST['tax_name_en']);
    $category = $_POST['category'];
    $description = trim($_POST['description']);
    $calculation_method = $_POST['calculation_method'];
    $base_amount = floatval($_POST['base_amount']);
    $percentage_rate = !empty($_POST['percentage_rate']) ? floatval($_POST['percentage_rate']) : null;
    $currency_id = intval($_POST['currency_id']);
    $payment_frequency = $_POST['payment_frequency'];
    $due_period_days = intval($_POST['due_period_days']);
    $applies_to = !empty($_POST['applies_to']) ? json_encode($_POST['applies_to']) : null;
    $minimum_amount = !empty($_POST['minimum_amount']) ? floatval($_POST['minimum_amount']) : null;
    $maximum_amount = !empty($_POST['maximum_amount']) ? floatval($_POST['maximum_amount']) : null;
    $discount_available = isset($_POST['discount_available']) ? 1 : 0;
    $discount_percentage = !empty($_POST['discount_percentage']) ? floatval($_POST['discount_percentage']) : null;
    $exemption_criteria = !empty($_POST['exemption_criteria']) ? trim($_POST['exemption_criteria']) : null;
    $legal_basis = trim($_POST['legal_basis']);
    $approval_number = trim($_POST['approval_number']);
    $approval_date = !empty($_POST['approval_date']) ? $_POST['approval_date'] : null;
    $effective_date = !empty($_POST['effective_date']) ? $_POST['effective_date'] : null;
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $notes = trim($_POST['notes']);
    
    if (!empty($tax_code) && !empty($tax_name) && !empty($category)) {
        try {
            $query = "INSERT INTO tax_types (tax_code, tax_name, tax_name_en, category, description, calculation_method, base_amount, percentage_rate, currency_id, payment_frequency, due_period_days, applies_to, minimum_amount, maximum_amount, discount_available, discount_percentage, exemption_criteria, legal_basis, approval_number, approval_date, effective_date, expiry_date, notes, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$tax_code, $tax_name, $tax_name_en, $category, $description, $calculation_method, $base_amount, $percentage_rate, $currency_id, $payment_frequency, $due_period_days, $applies_to, $minimum_amount, $maximum_amount, $discount_available, $discount_percentage, $exemption_criteria, $legal_basis, $approval_number, $approval_date, $effective_date, $expiry_date, $notes, $user['id']]);
            $message = 'تم إضافة نوع الجباية بنجاح!';
        } catch (PDOException $e) {
            $error = 'خطأ في إضافة نوع الجباية: ' . $e->getMessage();
        }
    } else {
        $error = 'يرجى تعبئة الحقول المطلوبة';
    }
}

// معالجة تحديث حالة نوع الجباية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tax_type_status'])) {
    $tax_type_id = intval($_POST['tax_type_id']);
    $is_active = intval($_POST['is_active']);
    
    try {
        $query = "UPDATE tax_types SET is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$is_active, $tax_type_id]);
        $message = 'تم تحديث حالة نوع الجباية بنجاح!';
    } catch (PDOException $e) {
        $error = 'خطأ في تحديث نوع الجباية: ' . $e->getMessage();
    }
}

// جلب أنواع الجباية
try {
    $filter_category = $_GET['category'] ?? '';
    $filter_status = $_GET['status'] ?? '';
    $filter_method = $_GET['method'] ?? '';
    
    $where_conditions = [];
    $params = [];
    
    if (!empty($filter_category)) {
        $where_conditions[] = "tt.category = ?";
        $params[] = $filter_category;
    }
    
    if ($filter_status !== '') {
        $where_conditions[] = "tt.is_active = ?";
        $params[] = intval($filter_status);
    }
    
    if (!empty($filter_method)) {
        $where_conditions[] = "tt.calculation_method = ?";
        $params[] = $filter_method;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $stmt = $db->prepare("
        SELECT tt.*, 
               c.currency_symbol, c.currency_code,
               u.full_name as created_by_name
        FROM tax_types tt 
        LEFT JOIN currencies c ON tt.currency_id = c.id
        LEFT JOIN users u ON tt.created_by_user_id = u.id
        $where_clause
        ORDER BY tt.created_at DESC 
        LIMIT 50
    ");
    $stmt->execute($params);
    $tax_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات أنواع الجباية
    $stmt = $db->query("
        SELECT 
            category,
            COUNT(*) as count,
            AVG(base_amount) as avg_amount
        FROM tax_types 
        GROUP BY category
    ");
    $category_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات عامة
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_tax_types,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_count,
            COUNT(CASE WHEN discount_available = 1 THEN 1 END) as with_discount_count,
            AVG(base_amount) as avg_base_amount
        FROM tax_types
    ");
    $general_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // جلب العملات
    $stmt = $db->query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY currency_code");
    $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $tax_types = [];
    $category_stats = [];
    $general_stats = ['total_tax_types' => 0, 'active_count' => 0, 'with_discount_count' => 0, 'avg_base_amount' => 0];
    $currencies = [];
}

$categories = ['رسوم خدمات', 'ضرائب', 'غرامات', 'تراخيص', 'إشغالات', 'أخرى'];
$calculation_methods = ['مبلغ ثابت', 'نسبة مئوية', 'حسب المساحة', 'حسب القيمة', 'حسب المدة', 'معقد'];
$payment_frequencies = ['مرة واحدة', 'سنوي', 'نصف سنوي', 'ربع سنوي', 'شهري', 'أسبوعي', 'يومي'];
$applies_to_options = ['مواطنين', 'شركات', 'مؤسسات', 'زوار', 'مقاولين', 'تجار', 'أخرى'];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة أنواع الجباية - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .modal { display: none; }
        .modal.active { display: flex; }
    </style>
</head>
<body class="bg-slate-100">
    <div class="min-h-screen p-6">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-slate-800">إدارة أنواع الجباية</h1>
                <div class="flex gap-3">
                    <button onclick="openModal('addTaxTypeModal')" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        ➕ إضافة نوع جباية
                    </button>
                    <a href="../comprehensive_dashboard.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                        ← العودة للوحة التحكم
                    </a>
                </div>
            </div>
            <p class="text-slate-600 mt-2">إدارة أنواع الجباية والرسوم مع تحديد الكلفة وطرق الحساب</p>
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

        <!-- إحصائيات أنواع الجباية -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">إجمالي الأنواع</p>
                        <p class="text-2xl font-bold text-blue-600"><?= $general_stats['total_tax_types'] ?></p>
                    </div>
                    <div class="bg-blue-100 text-blue-600 p-3 rounded-full">📋</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">الأنواع النشطة</p>
                        <p class="text-2xl font-bold text-green-600"><?= $general_stats['active_count'] ?></p>
                    </div>
                    <div class="bg-green-100 text-green-600 p-3 rounded-full">✅</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">مع خصومات</p>
                        <p class="text-2xl font-bold text-yellow-600"><?= $general_stats['with_discount_count'] ?></p>
                    </div>
                    <div class="bg-yellow-100 text-yellow-600 p-3 rounded-full">🏷️</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">متوسط المبلغ</p>
                        <p class="text-2xl font-bold text-purple-600"><?= number_format($general_stats['avg_base_amount']) ?> ل.ل</p>
                    </div>
                    <div class="bg-purple-100 text-purple-600 p-3 rounded-full">💰</div>
                </div>
            </div>
        </div>

        <!-- إحصائيات حسب الفئة -->
        <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
            <h3 class="font-semibold mb-4">التوزيع حسب الفئة</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <?php foreach ($category_stats as $stat): ?>
                <div class="text-center p-4 bg-slate-50 rounded-lg">
                    <p class="text-sm text-slate-600"><?= htmlspecialchars($stat['category']) ?></p>
                    <p class="text-xl font-bold text-blue-600"><?= $stat['count'] ?></p>
                    <p class="text-xs text-slate-500">متوسط: <?= number_format($stat['avg_amount']) ?> ل.ل</p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- فلاتر البحث -->
        <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
            <h3 class="font-semibold mb-4">البحث والفلترة</h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الفئة</label>
                    <select name="category" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">جميع الفئات</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category ?>" <?= ($filter_category === $category) ? 'selected' : '' ?>><?= $category ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الحالة</label>
                    <select name="status" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">جميع الحالات</option>
                        <option value="1" <?= ($filter_status === '1') ? 'selected' : '' ?>>نشط</option>
                        <option value="0" <?= ($filter_status === '0') ? 'selected' : '' ?>>غير نشط</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">طريقة الحساب</label>
                    <select name="method" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">جميع الطرق</option>
                        <?php foreach ($calculation_methods as $method): ?>
                            <option value="<?= $method ?>" <?= ($filter_method === $method) ? 'selected' : '' ?>><?= $method ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                        تطبيق الفلتر
                    </button>
                </div>
            </form>
        </div>

        <!-- جدول أنواع الجباية -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="p-6 border-b">
                <h2 class="text-xl font-semibold">أنواع الجباية المسجلة</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="text-right p-4 font-semibold">كود الجباية</th>
                            <th class="text-right p-4 font-semibold">اسم الجباية</th>
                            <th class="text-right p-4 font-semibold">الفئة</th>
                            <th class="text-right p-4 font-semibold">طريقة الحساب</th>
                            <th class="text-right p-4 font-semibold">المبلغ الأساسي</th>
                            <th class="text-right p-4 font-semibold">تكرار الدفع</th>
                            <th class="text-right p-4 font-semibold">فترة الاستحقاق</th>
                            <th class="text-right p-4 font-semibold">الحالة</th>
                            <th class="text-right p-4 font-semibold">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tax_types as $tax_type): ?>
                        <tr class="border-b hover:bg-slate-50">
                            <td class="p-4 font-medium"><?= htmlspecialchars($tax_type['tax_code']) ?></td>
                            <td class="p-4">
                                <div class="font-medium"><?= htmlspecialchars($tax_type['tax_name']) ?></div>
                                <?php if (!empty($tax_type['tax_name_en'])): ?>
                                    <div class="text-sm text-slate-500"><?= htmlspecialchars($tax_type['tax_name_en']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-sm 
                                    <?= $tax_type['category'] === 'رسوم خدمات' ? 'bg-blue-100 text-blue-800' : 
                                       ($tax_type['category'] === 'ضرائب' ? 'bg-green-100 text-green-800' : 
                                       ($tax_type['category'] === 'غرامات' ? 'bg-red-100 text-red-800' : 
                                       ($tax_type['category'] === 'تراخيص' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'))) ?>">
                                    <?= htmlspecialchars($tax_type['category']) ?>
                                </span>
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-xs bg-yellow-100 text-yellow-800">
                                    <?= htmlspecialchars($tax_type['calculation_method']) ?>
                                </span>
                                <?php if ($tax_type['percentage_rate']): ?>
                                    <div class="text-xs text-slate-500 mt-1">نسبة: %<?= $tax_type['percentage_rate'] ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 font-semibold">
                                <?= number_format($tax_type['base_amount']) ?> <?= htmlspecialchars($tax_type['currency_symbol']) ?>
                                <?php if ($tax_type['minimum_amount'] || $tax_type['maximum_amount']): ?>
                                    <div class="text-xs text-slate-500">
                                        <?php if ($tax_type['minimum_amount']): ?>
                                            الحد الأدنى: <?= number_format($tax_type['minimum_amount']) ?>
                                        <?php endif; ?>
                                        <?php if ($tax_type['maximum_amount']): ?>
                                            الحد الأعلى: <?= number_format($tax_type['maximum_amount']) ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-xs bg-indigo-100 text-indigo-800">
                                    <?= htmlspecialchars($tax_type['payment_frequency']) ?>
                                </span>
                            </td>
                            <td class="p-4 text-sm">
                                <?= $tax_type['due_period_days'] ?> يوم
                                <?php if ($tax_type['discount_available']): ?>
                                    <div class="text-xs text-green-600">
                                        خصم: %<?= $tax_type['discount_percentage'] ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-sm 
                                    <?= $tax_type['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= $tax_type['is_active'] ? 'نشط' : 'غير نشط' ?>
                                </span>
                                <?php if ($tax_type['effective_date'] && strtotime($tax_type['effective_date']) > time()): ?>
                                    <div class="text-xs text-yellow-600 mt-1">
                                        يسري من: <?= date('Y-m-d', strtotime($tax_type['effective_date'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <div class="flex gap-2">
                                    <button onclick="viewTaxType(<?= $tax_type['id'] ?>)" 
                                            class="text-blue-600 hover:text-blue-800 text-sm">
                                        عرض
                                    </button>
                                    <button onclick="toggleStatus(<?= $tax_type['id'] ?>, <?= $tax_type['is_active'] ?>)" 
                                            class="text-yellow-600 hover:text-yellow-800 text-sm">
                                        <?= $tax_type['is_active'] ? 'إيقاف' : 'تفعيل' ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal إضافة نوع جباية جديد -->
    <div id="addTaxTypeModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-6xl max-h-screen overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold">إضافة نوع جباية جديد</h3>
                <button onclick="closeModal('addTaxTypeModal')" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            
            <form method="POST" class="space-y-6">
                <!-- المعلومات الأساسية -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3">المعلومات الأساسية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">كود الجباية *</label>
                            <input type="text" name="tax_code" required placeholder="مثال: RES001"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-2">اسم الجباية (بالعربية) *</label>
                            <input type="text" name="tax_name" required 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-2">اسم الجباية (بالإنجليزية)</label>
                            <input type="text" name="tax_name_en" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الفئة *</label>
                            <select name="category" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">اختر الفئة</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category ?>"><?= $category ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="md:col-span-3">
                            <label class="block text-sm font-medium mb-2">الوصف</label>
                            <textarea name="description" rows="3" 
                                      class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                    </div>
                </div>

                <!-- طريقة الحساب والمبالغ -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3">طريقة الحساب والمبالغ</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">طريقة الحساب *</label>
                            <select name="calculation_method" onchange="showCalculationFields(this.value)" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">اختر الطريقة</option>
                                <?php foreach ($calculation_methods as $method): ?>
                                    <option value="<?= $method ?>"><?= $method ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">المبلغ الأساسي *</label>
                            <input type="number" step="0.01" name="base_amount" required 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div id="percentage_field" style="display: none;">
                            <label class="block text-sm font-medium mb-2">النسبة المئوية</label>
                            <input type="number" step="0.01" name="percentage_rate" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
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
                            <label class="block text-sm font-medium mb-2">الحد الأدنى</label>
                            <input type="number" step="0.01" name="minimum_amount" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الحد الأعلى</label>
                            <input type="number" step="0.01" name="maximum_amount" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- فترات الدفع -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3">فترات الدفع</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">تكرار الدفع</label>
                            <select name="payment_frequency" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <?php foreach ($payment_frequencies as $frequency): ?>
                                    <option value="<?= $frequency ?>"><?= $frequency ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">فترة الاستحقاق (بالأيام)</label>
                            <input type="number" name="due_period_days" value="30" min="1" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- نطاق التطبيق -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3">نطاق التطبيق</h4>
                    <div>
                        <label class="block text-sm font-medium mb-2">ينطبق على</label>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                            <?php foreach ($applies_to_options as $option): ?>
                            <label class="flex items-center">
                                <input type="checkbox" name="applies_to[]" value="<?= $option ?>" 
                                       class="mr-2 text-blue-600">
                                <span class="text-sm"><?= $option ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- الخصومات والإعفاءات -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3">الخصومات والإعفاءات</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" name="discount_available" onchange="toggleDiscountField(this)" 
                                       class="mr-2 text-blue-600">
                                <span class="text-sm font-medium">يتوفر خصم</span>
                            </label>
                        </div>
                        
                        <div id="discount_percentage_field" style="display: none;">
                            <label class="block text-sm font-medium mb-2">نسبة الخصم (%)</label>
                            <input type="number" step="0.01" name="discount_percentage" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-2">معايير الإعفاء</label>
                            <input type="text" name="exemption_criteria" 
                                   placeholder="مثال: كبار السن، ذوي الاحتياجات الخاصة"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- المعلومات القانونية والتواريخ -->
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-3">المعلومات القانونية والتواريخ</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-2">الأساس القانوني</label>
                            <input type="text" name="legal_basis" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">رقم الموافقة</label>
                            <input type="text" name="approval_number" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">تاريخ الموافقة</label>
                            <input type="date" name="approval_date" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">تاريخ السريان</label>
                            <input type="date" name="effective_date" value="<?= date('Y-m-d') ?>" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">تاريخ الانتهاء</label>
                            <input type="date" name="expiry_date" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="md:col-span-3">
                            <label class="block text-sm font-medium mb-2">ملاحظات</label>
                            <textarea name="notes" rows="3" 
                                      class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeModal('addTaxTypeModal')" 
                            class="px-4 py-2 text-slate-600 hover:text-slate-800">
                        إلغاء
                    </button>
                    <button type="submit" name="add_tax_type" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        إضافة نوع الجباية
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function showCalculationFields(method) {
            const percentageField = document.getElementById('percentage_field');
            if (method === 'نسبة مئوية') {
                percentageField.style.display = 'block';
            } else {
                percentageField.style.display = 'none';
            }
        }

        function toggleDiscountField(checkbox) {
            const discountField = document.getElementById('discount_percentage_field');
            if (checkbox.checked) {
                discountField.style.display = 'block';
            } else {
                discountField.style.display = 'none';
            }
        }

        function viewTaxType(id) {
            alert('عرض تفاصيل نوع الجباية #' + id);
        }

        function toggleStatus(taxTypeId, currentStatus) {
            const newStatus = currentStatus ? 0 : 1;
            const statusText = newStatus ? 'تفعيل' : 'إيقاف';
            
            if (confirm('هل تريد ' + statusText + ' هذا النوع من الجباية؟')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="tax_type_id" value="${taxTypeId}">
                    <input type="hidden" name="is_active" value="${newStatus}">
                    <input type="hidden" name="update_tax_type_status" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html> 
