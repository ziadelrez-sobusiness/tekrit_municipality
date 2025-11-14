<?php
/**
 * صفحة إدارة الجداول المرجعية الشاملة
 * بلدية تكريت - عكار، شمال لبنان
 * 
 * هذه الصفحة تدير جميع الجداول المرجعية في النظام بشكل موحد
 */

require_once 'includes/auth.php';
require_once 'config/database.php';

$auth = new Auth();

// التحقق من تسجيل الدخول
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user = $auth->getCurrentUser();
$database = new Database();
$db = $database->getConnection();

// تعيين ترميز UTF-8 للاتصال بقاعدة البيانات
$db->exec("SET NAMES 'utf8mb4'");
$db->exec("SET CHARACTER SET utf8mb4");

// رسائل النظام
$success_message = '';
$error_message = '';

// تعريف جميع الجداول المرجعية المتاحة في النظام
$reference_tables = [
    'reference_data' => [
        'name' => 'البيانات المرجعية العامة',
        'icon' => '📊',
        'description' => 'جدول مرجعي شامل لجميع أنواع البيانات المرجعية',
        'columns' => ['id', 'type', 'value', 'description', 'is_active', 'created_at', 'updated_at'],
        'editable_columns' => ['type', 'value', 'description', 'is_active'],
        'display_columns' => ['id', 'type', 'value', 'description', 'is_active'],
        'searchable_columns' => ['type', 'value', 'description'],
        'has_type' => true,
        'column_labels' => [
            'id' => 'المعرف',
            'type' => 'النوع',
            'value' => 'القيمة',
            'description' => 'الوصف',
            'is_active' => 'الحالة',
            'created_at' => 'تاريخ الإنشاء',
            'updated_at' => 'تاريخ التحديث'
        ]
    ],
    'roles' => [
        'name' => 'الأدوار والصلاحيات',
        'icon' => '👤',
        'description' => 'أدوار المستخدمين في النظام',
        'columns' => ['id', 'name', 'description', 'created_at', 'updated_at'],
        'editable_columns' => ['name', 'description'],
        'display_columns' => ['id', 'name', 'description'],
        'searchable_columns' => ['name', 'description'],
        'has_type' => false,
        'column_labels' => [
            'id' => 'المعرف',
            'name' => 'اسم الدور',
            'description' => 'الوصف',
            'created_at' => 'تاريخ الإنشاء',
            'updated_at' => 'تاريخ التحديث'
        ]
    ],
    'departments' => [
        'name' => 'الأقسام الإدارية',
        'icon' => '🏢',
        'description' => 'أقسام البلدية',
        'columns' => ['id', 'department_name', 'department_description', 'department_manager', 'is_active', 'created_at', 'updated_at'],
        'editable_columns' => ['department_name', 'department_description', 'department_manager', 'is_active'],
        'display_columns' => ['id', 'department_name', 'department_description', 'is_active'],
        'searchable_columns' => ['department_name', 'department_description'],
        'has_type' => false,
        'column_labels' => [
            'id' => 'المعرف',
            'department_name' => 'اسم القسم',
            'department_description' => 'وصف القسم',
            'department_manager' => 'مدير القسم',
            'is_active' => 'الحالة',
            'created_at' => 'تاريخ الإنشاء',
            'updated_at' => 'تاريخ التحديث'
        ]
    ],
    'currencies' => [
        'name' => 'العملات',
        'icon' => '💱',
        'description' => 'العملات المستخدمة في النظام',
        'columns' => ['id', 'currency_name', 'currency_code', 'currency_symbol', 'exchange_rate_to_lbp', 'is_active', 'created_at', 'updated_at'],
        'editable_columns' => ['currency_name', 'currency_code', 'currency_symbol', 'exchange_rate_to_lbp', 'is_active'],
        'display_columns' => ['id', 'currency_name', 'currency_code', 'currency_symbol', 'exchange_rate_to_lbp', 'is_active'],
        'searchable_columns' => ['currency_name', 'currency_code'],
        'has_type' => false,
        'column_labels' => [
            'id' => 'المعرف',
            'currency_name' => 'اسم العملة',
            'currency_code' => 'رمز العملة',
            'currency_symbol' => 'رمز العملة',
            'exchange_rate_to_lbp' => 'سعر الصرف (ل.ل)',
            'is_active' => 'الحالة',
            'created_at' => 'تاريخ الإنشاء',
            'updated_at' => 'تاريخ التحديث'
        ]
    ],
    'tax_types' => [
        'name' => 'أنواع الضرائب',
        'icon' => '📋',
        'description' => 'أنواع الضرائب والرسوم البلدية',
        'columns' => ['id', 'tax_name', 'tax_description', 'tax_rate', 'is_active', 'created_at', 'updated_at'],
        'editable_columns' => ['tax_name', 'tax_description', 'tax_rate', 'is_active'],
        'display_columns' => ['id', 'tax_name', 'tax_description', 'tax_rate', 'is_active'],
        'searchable_columns' => ['tax_name', 'tax_description'],
        'has_type' => false,
        'column_labels' => [
            'id' => 'المعرف',
            'tax_name' => 'اسم الضريبة',
            'tax_description' => 'وصف الضريبة',
            'tax_rate' => 'نسبة الضريبة (%)',
            'is_active' => 'الحالة',
            'created_at' => 'تاريخ الإنشاء',
            'updated_at' => 'تاريخ التحديث'
        ]
    ],
    'request_types' => [
        'name' => 'أنواع طلبات المواطنين',
        'icon' => '📝',
        'description' => 'أنواع الطلبات التي يمكن للمواطنين تقديمها',
        'columns' => ['id', 'type_name', 'name_ar', 'name_en', 'type_description', 'cost', 'cost_currency_id', 'is_active', 'display_order'],
        'editable_columns' => ['type_name', 'name_ar', 'name_en', 'type_description', 'cost', 'cost_currency_id', 'is_active', 'display_order'],
        'display_columns' => ['id', 'name_ar', 'type_name', 'cost', 'cost_currency_id', 'is_active', 'display_order'],
        'searchable_columns' => ['type_name', 'name_ar', 'name_en', 'type_description'],
        'has_type' => false,
        'column_labels' => [
            'id' => 'المعرف',
            'type_name' => 'الاسم الداخلي',
            'name_ar' => 'اسم الطلب (عربي)',
            'name_en' => 'اسم الطلب (إنجليزي)',
            'type_description' => 'وصف الطلب',
            'cost' => 'رسوم الطلب',
            'cost_currency_id' => 'عملة الرسوم',
            'is_active' => 'الحالة',
            'display_order' => 'ترتيب الظهور'
        ],
        'order_clause' => 'ORDER BY rt.display_order ASC, rt.id DESC'
    ]
];

// الحصول على الجدول المحدد
$selected_table = $_GET['table'] ?? 'reference_data';
$selected_type = $_GET['type'] ?? '';

// التحقق من صحة الجدول المحدد
if (!isset($reference_tables[$selected_table])) {
    $selected_table = 'reference_data';
}

$table_info = $reference_tables[$selected_table];

/**
 * الحصول على تسمية عربية للعمود عند توفرها
 */
function getColumnLabel($column, $table_info) {
    return $table_info['column_labels'][$column] ?? ucfirst(str_replace('_', ' ', $column));
}

// معالجة العمليات (CRUD)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add':
                // إضافة سجل جديد
                $columns = [];
                $values = [];
                $placeholders = [];
                
                foreach ($table_info['editable_columns'] as $column) {
                    if (isset($_POST[$column])) {
                        $columns[] = $column;
                        $values[] = $_POST[$column];
                        $placeholders[] = '?';
                    }
                }
                
                if (!empty($columns)) {
                    $sql = "INSERT INTO {$selected_table} (" . implode(', ', $columns) . ") 
                            VALUES (" . implode(', ', $placeholders) . ")";
                    $stmt = $db->prepare($sql);
                    $stmt->execute($values);
                    $success_message = "تم إضافة السجل بنجاح";
                }
                break;
                
            case 'edit':
                // تعديل سجل
                $id = $_POST['id'] ?? 0;
                $updates = [];
                $values = [];
                
                foreach ($table_info['editable_columns'] as $column) {
                    if (isset($_POST[$column])) {
                        $updates[] = "{$column} = ?";
                        $values[] = $_POST[$column];
                    }
                }
                
                if (!empty($updates) && $id > 0) {
                    $values[] = $id;
                    $sql = "UPDATE {$selected_table} SET " . implode(', ', $updates) . " WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute($values);
                    $success_message = "تم تحديث السجل بنجاح";
                }
                break;
                
            case 'delete':
                // حذف سجل
                $id = $_POST['id'] ?? 0;
                if ($id > 0) {
                    $sql = "DELETE FROM {$selected_table} WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$id]);
                    $success_message = "تم حذف السجل بنجاح";
                }
                break;
                
            case 'toggle_status':
                // تبديل حالة التفعيل
                $id = $_POST['id'] ?? 0;
                if ($id > 0 && in_array('is_active', $table_info['columns'])) {
                    $sql = "UPDATE {$selected_table} SET is_active = NOT is_active WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$id]);
                    $success_message = "تم تحديث الحالة بنجاح";
                }
                break;
        }
    } catch (PDOException $e) {
        $error_message = "خطأ في العملية: " . $e->getMessage();
    }
}

// جلب البيانات مع البحث والفلترة
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// بناء استعلام البحث
$where_conditions = [];
$search_params = [];

if (!empty($search)) {
    $search_conditions = [];
    foreach ($table_info['searchable_columns'] as $column) {
        $column_name = ($selected_table === 'request_types') ? "rt.{$column}" : $column;
        $search_conditions[] = "{$column_name} LIKE ?";
        $search_params[] = "%{$search}%";
    }
    $where_conditions[] = "(" . implode(' OR ', $search_conditions) . ")";
}

// فلترة حسب النوع (للجداول التي تحتوي على حقل type)
if ($table_info['has_type'] && !empty($selected_type)) {
    $column_name = ($selected_table === 'request_types') ? "rt.type" : "type";
    $where_conditions[] = "{$column_name} = ?";
    $search_params[] = $selected_type;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// إعداد جمل FROM و ORDER BY حسب الجدول
$from_clause = "FROM {$selected_table}";
$join_clause = '';
$order_clause = 'ORDER BY id DESC';

if ($selected_table === 'request_types') {
    $from_clause = 'FROM request_types rt';
    $join_clause = ' LEFT JOIN currencies c ON rt.cost_currency_id = c.id';
    $order_clause = $table_info['order_clause'] ?? 'ORDER BY rt.display_order ASC, rt.id DESC';
}

// عد إجمالي السجلات
$count_sql = "SELECT COUNT(*) as total {$from_clause} {$where_clause}";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($search_params);
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// جلب البيانات
if ($selected_table === 'request_types') {
    $sql = "
        SELECT 
            rt.*, 
            c.currency_name AS cost_currency_name, 
            c.currency_code AS cost_currency_code, 
            c.currency_symbol AS cost_currency_symbol 
        {$from_clause}
        {$join_clause}
        {$where_clause}
        {$order_clause}
        LIMIT {$per_page} OFFSET {$offset}
    ";
} else {
    $sql = "SELECT * {$from_clause} {$where_clause} {$order_clause} LIMIT {$per_page} OFFSET {$offset}";
}
$stmt = $db->prepare($sql);
$stmt->execute($search_params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب قائمة العملات عند العمل على جدول request_types
$currencies = [];
if ($selected_table === 'request_types') {
    $currencies_stmt = $db->query("SELECT id, currency_name, currency_code, currency_symbol FROM currencies WHERE is_active = 1 ORDER BY currency_name ASC");
    $currencies = $currencies_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// جلب أنواع البيانات المرجعية (للجدول reference_data)
$reference_types = [];
if ($selected_table === 'reference_data') {
    $types_stmt = $db->query("SELECT DISTINCT type FROM reference_data ORDER BY type");
    $reference_types = $types_stmt->fetchAll(PDO::FETCH_COLUMN);
}

// تعيين header للترميز الصحيح
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الجداول المرجعية - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .modal { display: none; }
        .modal.active { display: flex; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-indigo-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4 space-x-reverse">
                    <h1 class="text-2xl font-bold">📊 إدارة الجداول المرجعية</h1>
                </div>
                <div class="flex items-center space-x-4 space-x-reverse">
                    <span class="text-sm">أهلاً، <?= htmlspecialchars($user['full_name'] ?? 'المستخدم') ?></span>
                    <a href="comprehensive_dashboard.php" class="bg-white text-indigo-600 px-4 py-2 rounded hover:bg-gray-100 transition">
                        🏠 لوحة التحكم
                    </a>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition">خروج</a>
                </div>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-4 py-8">
        <!-- رسائل النظام -->
        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                ✅ <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                ❌ <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- اختيار الجدول -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">🗂️ اختر الجدول المرجعي:</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($reference_tables as $table_key => $table_data): ?>
                    <a href="?table=<?= $table_key ?>" 
                       class="p-4 rounded-lg border-2 transition-all <?= $selected_table === $table_key ? 'border-indigo-600 bg-indigo-50' : 'border-gray-200 hover:border-indigo-400' ?>">
                        <div class="flex items-center space-x-3 space-x-reverse">
                            <span class="text-3xl"><?= $table_data['icon'] ?></span>
                            <div>
                                <h3 class="font-bold text-gray-800"><?= $table_data['name'] ?></h3>
                                <p class="text-sm text-gray-600"><?= $table_data['description'] ?></p>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- معلومات الجدول الحالي -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">
                        <?= $table_info['icon'] ?> <?= $table_info['name'] ?>
                    </h2>
                    <p class="text-gray-600"><?= $table_info['description'] ?></p>
                    <p class="text-sm text-gray-500 mt-2">
                        إجمالي السجلات: <strong><?= $total_records ?></strong>
                    </p>
                </div>
                <button onclick="openAddModal()" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg transition">
                    ➕ إضافة سجل جديد
                </button>
            </div>

            <!-- البحث والفلترة -->
            <div class="flex gap-4 mb-4">
                <form method="GET" class="flex-1 flex gap-2">
                    <input type="hidden" name="table" value="<?= $selected_table ?>">
                    <?php if ($table_info['has_type'] && !empty($selected_type)): ?>
                        <input type="hidden" name="type" value="<?= htmlspecialchars($selected_type) ?>">
                    <?php endif; ?>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="🔍 البحث..." 
                           class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg transition">
                        بحث
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="?table=<?= $selected_table ?><?= !empty($selected_type) ? '&type=' . urlencode($selected_type) : '' ?>" 
                           class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition">
                            ✖ إلغاء
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- فلترة حسب النوع (للبيانات المرجعية) -->
            <?php if ($table_info['has_type'] && !empty($reference_types)): ?>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">فلترة حسب النوع:</label>
                    <div class="flex flex-wrap gap-2">
                        <a href="?table=<?= $selected_table ?>" 
                           class="px-3 py-1 rounded-full text-sm <?= empty($selected_type) ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                            الكل
                        </a>
                        <?php foreach ($reference_types as $type): ?>
                            <a href="?table=<?= $selected_table ?>&type=<?= urlencode($type) ?>" 
                               class="px-3 py-1 rounded-full text-sm <?= $selected_type === $type ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                                <?= htmlspecialchars($type) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- جدول البيانات -->
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-100">
                            <?php foreach ($table_info['display_columns'] as $column): ?>
                                <th class="p-3 text-right border font-semibold text-gray-700">
                                    <?= getColumnLabel($column, $table_info) ?>
                                </th>
                            <?php endforeach; ?>
                            <th class="p-3 text-center border font-semibold text-gray-700">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="<?= count($table_info['display_columns']) + 1 ?>" class="p-8 text-center text-gray-500">
                                    📭 لا توجد سجلات
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($records as $record): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <?php foreach ($table_info['display_columns'] as $column): ?>
                                        <td class="p-3 border">
                                            <?php
                                            $value = $record[$column] ?? '';
                                            if ($column === 'is_active') {
                                                echo $value ? '<span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm">نشط</span>' : '<span class="bg-red-100 text-red-800 px-2 py-1 rounded text-sm">غير نشط</span>';
                                            } elseif (in_array($column, ['created_at', 'updated_at'])) {
                                                echo $value ? date('Y-m-d H:i', strtotime($value)) : '-';
                                            } elseif ($selected_table === 'request_types' && $column === 'cost') {
                                                if ($value === '' || $value === null) {
                                                    echo '-';
                                                } else {
                                                    $formatted_cost = is_numeric($value) ? number_format((float)$value, 0, '.', ',') : $value;
                                                    $symbol = $record['cost_currency_symbol'] ?? '';
                                                    $code = $record['cost_currency_code'] ?? '';
                                                    $currency_suffix = trim($symbol . ' ' . $code);
                                                    echo htmlspecialchars($formatted_cost) . ($currency_suffix ? ' ' . htmlspecialchars($currency_suffix) : '');
                                                }
                                            } elseif ($selected_table === 'request_types' && $column === 'cost_currency_id') {
                                                if (!empty($record['cost_currency_name'])) {
                                                    echo htmlspecialchars($record['cost_currency_name']) . ' (' . htmlspecialchars($record['cost_currency_code']) . ') ' . htmlspecialchars($record['cost_currency_symbol']);
                                                } else {
                                                    echo '-';
                                                }
                                            } else {
                                                echo htmlspecialchars($value);
                                            }
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="p-3 border text-center">
                                        <div class="flex justify-center gap-2">
                                            <button onclick='editRecord(<?= json_encode($record) ?>)' 
                                                    class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition">
                                                ✏️ تعديل
                                            </button>
                                            <?php if (in_array('is_active', $table_info['columns'])): ?>
                                                <form method="POST" class="inline" onsubmit="return confirm('هل تريد تبديل حالة التفعيل؟')">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="id" value="<?= $record['id'] ?>">
                                                    <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm transition">
                                                        🔄 تبديل
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا السجل؟')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $record['id'] ?>">
                                                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm transition">
                                                    🗑️ حذف
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="mt-6 flex justify-center gap-2">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?table=<?= $selected_table ?>&page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($selected_type) ? '&type=' . urlencode($selected_type) : '' ?>" 
                           class="px-4 py-2 rounded <?= $page === $i ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal إضافة سجل -->
    <div id="addModal" class="modal fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold text-gray-800">➕ إضافة سجل جديد</h3>
                <button onclick="closeAddModal()" class="text-gray-500 hover:text-gray-700 text-2xl">✖</button>
            </div>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add">
                <?php foreach ($table_info['editable_columns'] as $column): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?= getColumnLabel($column, $table_info) ?>:
                        </label>
                        <?php if ($column === 'is_active'): ?>
                            <select name="<?= $column ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="1">نشط</option>
                                <option value="0">غير نشط</option>
                            </select>
                        <?php elseif ($column === 'description' || $column === 'type_description'): ?>
                            <textarea name="<?= $column ?>" rows="3" 
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                        <?php elseif ($selected_table === 'request_types' && $column === 'cost_currency_id'): ?>
                            <?php if (!empty($currencies)): ?>
                                <select name="<?= $column ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <?php foreach ($currencies as $index => $currency): ?>
                                        <option value="<?= $currency['id'] ?>" <?= $index === 0 ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_code']) ?>) <?= htmlspecialchars($currency['currency_symbol']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <div class="p-3 bg-yellow-100 text-yellow-800 rounded">
                                    ⚠️ لا توجد عملات مفعلة حالياً، يرجى إضافة عملة من قسم العملات أولاً.
                                </div>
                            <?php endif; ?>
                        <?php elseif ($selected_table === 'request_types' && $column === 'cost'): ?>
                            <input type="number" name="<?= $column ?>" min="0" step="0.01"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <?php elseif ($selected_table === 'request_types' && $column === 'display_order'): ?>
                            <input type="number" name="<?= $column ?>" min="0" step="1"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <?php else: ?>
                            <input type="text" name="<?= $column ?>" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <div class="flex gap-4">
                    <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg transition">
                        ✅ حفظ
                    </button>
                    <button type="button" onclick="closeAddModal()" class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-lg transition">
                        ✖ إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal تعديل سجل -->
    <div id="editModal" class="modal fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold text-gray-800">✏️ تعديل السجل</h3>
                <button onclick="closeEditModal()" class="text-gray-500 hover:text-gray-700 text-2xl">✖</button>
            </div>
            <form method="POST" id="editForm" class="space-y-4">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <?php foreach ($table_info['editable_columns'] as $column): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?= getColumnLabel($column, $table_info) ?>:
                        </label>
                        <?php if ($column === 'is_active'): ?>
                            <select name="<?= $column ?>" id="edit_<?= $column ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="1">نشط</option>
                                <option value="0">غير نشط</option>
                            </select>
                        <?php elseif ($column === 'description' || $column === 'type_description'): ?>
                            <textarea name="<?= $column ?>" id="edit_<?= $column ?>" rows="3" 
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                        <?php elseif ($selected_table === 'request_types' && $column === 'cost_currency_id'): ?>
                            <?php if (!empty($currencies)): ?>
                                <select name="<?= $column ?>" id="edit_<?= $column ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="">اختر العملة</option>
                                    <?php foreach ($currencies as $currency): ?>
                                        <option value="<?= $currency['id'] ?>">
                                            <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_code']) ?>) <?= htmlspecialchars($currency['currency_symbol']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <div class="p-3 bg-yellow-100 text-yellow-800 rounded">
                                    ⚠️ لا توجد عملات مفعلة حالياً، يرجى إضافة عملة من قسم العملات أولاً.
                                </div>
                            <?php endif; ?>
                        <?php elseif ($selected_table === 'request_types' && $column === 'cost'): ?>
                            <input type="number" name="<?= $column ?>" id="edit_<?= $column ?>" min="0" step="0.01"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <?php elseif ($selected_table === 'request_types' && $column === 'display_order'): ?>
                            <input type="number" name="<?= $column ?>" id="edit_<?= $column ?>" min="0" step="1"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <?php else: ?>
                            <input type="text" name="<?= $column ?>" id="edit_<?= $column ?>" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <div class="flex gap-4">
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition">
                        ✅ حفظ التعديلات
                    </button>
                    <button type="button" onclick="closeEditModal()" class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-lg transition">
                        ✖ إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }

        function openEditModal() {
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        function editRecord(record) {
            document.getElementById('edit_id').value = record.id;
            
            <?php foreach ($table_info['editable_columns'] as $column): ?>
                const field_<?= $column ?> = document.getElementById('edit_<?= $column ?>');
                if (field_<?= $column ?>) {
                    field_<?= $column ?>.value = record.<?= $column ?> || '';
                }
            <?php endforeach; ?>
            
            openEditModal();
        }

        // إغلاق المودال عند الضغط خارجه
        document.getElementById('addModal').addEventListener('click', function(e) {
            if (e.target === this) closeAddModal();
        });

        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeEditModal();
        });
    </script>

    <footer class="bg-gray-800 text-white py-6 mt-12">
        <div class="container mx-auto px-4 text-center">
            <p>🏛️ بلدية تكريت - عكار، شمال لبنان 🇱🇧</p>
            <p class="text-sm text-gray-400 mt-2">نظام إدارة البلدية الإلكتروني</p>
        </div>
    </footer>
</body>
</html>

