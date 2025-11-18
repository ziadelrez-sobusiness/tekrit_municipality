<?php
header('Content-Type: text/html; charset=utf-8');

// تحميل CSRF Protection
if (file_exists(__DIR__ . '/../includes/csrf_middleware.php')) {
    require_once __DIR__ . '/../includes/csrf_middleware.php';
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/currency_helper.php';
require_once __DIR__ . '/../includes/settings_helper.php';

// التأكد من تسجيل الدخول وصلاحية الوصول
$auth->requireLogin();
if (!$auth->checkPermission('employee')) {
    header('Location: ../comprehensive_dashboard.php?error=no_permission');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user = $auth->getUserInfo();

// تعيين الترميز مرة واحدة فقط
try {
    $db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (PDOException $e) {
    error_log("Database charset setting error: " . $e->getMessage());
}

// التحقق من وجود الجداول المطلوبة
$departments_table_exists = false;
$currencies_table_exists = false;
try {
    $db->query("SELECT 1 FROM departments LIMIT 1");
    $departments_table_exists = true;
} catch (PDOException $e) {
    error_log("Departments table check failed: " . $e->getMessage());
}

try {
    $db->query("SELECT 1 FROM currencies LIMIT 1");
    $currencies_table_exists = true;
} catch (PDOException $e) {
    error_log("Currencies table check failed: " . $e->getMessage());
}

$message = '';
$error = '';

// معالجة إضافة موظف جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    if (!csrf_protect(false)) {
        $error = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $department_id = intval($_POST['department_id'] ?? 0);
        $position_id = intval($_POST['position_id'] ?? 0);
        $user_type_id = intval($_POST['user_type_id'] ?? 0);
        $hire_date = $_POST['hire_date'];
        $salary = floatval($_POST['salary']);
        $salary_currency_id = intval($_POST['salary_currency_id']);
        $contract_type_id = intval($_POST['contract_type_id'] ?? 0);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        if (!empty($username) && !empty($full_name) && $department_id > 0 && $position_id > 0 && $user_type_id > 0 && $contract_type_id > 0) {
        try {
            // التحقق من وجود اسم المستخدم مسبقاً
            $check_stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $check_stmt->execute([$username]);
            $user_exists = $check_stmt->fetchColumn();
            
            if ($user_exists > 0) {
                $error = 'اسم المستخدم "' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '" موجود مسبقاً. يرجى اختيار اسم مستخدم آخر.';
            } else {
                // إذا لم يكن موجود، قم بالإدراج
                                 $query = "INSERT INTO users (username, password, full_name, email, phone, department_id, position_id, user_type_id, hire_date, salary, salary_currency_id, contract_type_id, is_active) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
                 $stmt = $db->prepare($query);
                 $stmt->execute([$username, $password, $full_name, $email, $phone, $department_id, $position_id, $user_type_id, $hire_date, $salary, $salary_currency_id, $contract_type_id]);
                $message = 'تم إضافة الموظف "' . htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') . '" بنجاح!';
            }
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $error = 'اسم المستخدم موجود مسبقاً (خطأ قاعدة البيانات)';
                } else {
                    $error = 'خطأ في إضافة الموظف: ' . $e->getMessage();
                }
            }
        } else {
            $error = 'يرجى تعبئة جميع الحقول المطلوبة: اسم المستخدم، الاسم الكامل، القسم، المنصب، نوع المستخدم، ونوع العقد';
        }
    }
}

// معالجة تحديث بيانات الموظف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_employee'])) {
    if (!csrf_protect(false)) {
        $error = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        $employee_id = intval($_POST['employee_id']);
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $department_id = intval($_POST['department_id'] ?? 0);
        $position_id = intval($_POST['position_id'] ?? 0);
        $user_type_id = intval($_POST['user_type_id'] ?? 0);
        $salary = floatval($_POST['salary']);
        $salary_currency_id = intval($_POST['salary_currency_id']);
        $contract_type_id = intval($_POST['contract_type_id'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (!empty($username) && !empty($full_name) && $department_id > 0 && $position_id > 0 && $user_type_id > 0 && $contract_type_id > 0) {
            try {
                // التحقق من وجود اسم المستخدم إذا تم تغييره
                $check_stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
                $check_stmt->execute([$employee_id]);
                $current_username = $check_stmt->fetchColumn();
                
                if ($username !== $current_username) {
                    $check_duplicate = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
                    $check_duplicate->execute([$username, $employee_id]);
                    if ($check_duplicate->fetchColumn() > 0) {
                        $error = 'اسم المستخدم "' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '" موجود مسبقاً';
                        goto skip_update;
                    }
                }
                
                // تحديث مع أو بدون كلمة مرور
                if (!empty($_POST['password'])) {
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $query = "UPDATE users SET username = ?, password = ?, full_name = ?, email = ?, phone = ?, department_id = ?, position_id = ?, user_type_id = ?, salary = ?, salary_currency_id = ?, contract_type_id = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$username, $password, $full_name, $email, $phone, $department_id, $position_id, $user_type_id, $salary, $salary_currency_id, $contract_type_id, $is_active, $employee_id]);
                } else {
                    $query = "UPDATE users SET username = ?, full_name = ?, email = ?, phone = ?, department_id = ?, position_id = ?, user_type_id = ?, salary = ?, salary_currency_id = ?, contract_type_id = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$username, $full_name, $email, $phone, $department_id, $position_id, $user_type_id, $salary, $salary_currency_id, $contract_type_id, $is_active, $employee_id]);
                }
                $message = 'تم تحديث بيانات الموظف بنجاح!';
            } catch (PDOException $e) {
                $error = 'خطأ في تحديث الموظف: ' . $e->getMessage();
            }
            skip_update:
        } else {
            $error = 'يرجى تعبئة جميع الحقول المطلوبة';
        }
    }
}

// جلب الموظفين مع معلومات العملة
try {
    $filter_department = $_GET['department'] ?? '';
    $filter_status = $_GET['status'] ?? '';
    $filter_name = $_GET['name'] ?? '';
    
    $where_conditions = [];
    $params = [];
    
    if (!empty($filter_department)) {
        $where_conditions[] = "u.department_id = ?";
        $params[] = $filter_department;
    }
    
    if ($filter_status !== '') {
        $where_conditions[] = "u.is_active = ?";
        $params[] = $filter_status;
    }
    
    if (!empty($filter_name)) {
        $where_conditions[] = "(u.full_name LIKE ? OR u.username LIKE ?)";
        $params[] = '%' . $filter_name . '%';
        $params[] = '%' . $filter_name . '%';
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
  $select_query = "
    SELECT DISTINCT u.*, 
           COALESCE(c.currency_symbol, 'ل.ل') as currency_symbol, 
           COALESCE(c.currency_name, 'ليرة لبنانية') as currency_name,
           COALESCE(p.position_name, 'غير محدد') as position_name_for_display,
           COALESCE(ut.type_name, 'غير محدد') as user_type_name_for_display,
           COALESCE(ct.type_name, 'غير محدد') as contract_type_name_for_display";

if ($departments_table_exists) {
    $select_query .= ", COALESCE(d.department_name, 'غير محدد') as department_name_for_display";
} else {
    $select_query .= ", COALESCE(u.department_id, 'غير محدد') as department_name_for_display";
}

$select_query .= "
    FROM users u";

if ($currencies_table_exists) {
    $select_query .= " LEFT JOIN currencies c ON u.salary_currency_id = c.id";
}

if ($departments_table_exists) {
    $select_query .= " LEFT JOIN departments d ON u.department_id = d.id";
}

// ربط الجداول الجديدة
$select_query .= " LEFT JOIN positions p ON u.position_id = p.id
                   LEFT JOIN user_types ut ON u.user_type_id = ut.id
                   LEFT JOIN contract_types ct ON u.contract_type_id = ct.id";

$select_query .= "
    $where_clause
    GROUP BY u.id
    ORDER BY u.id DESC 
    LIMIT 100";
    
    $stmt = $db->prepare($select_query);
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تأكد من وجود البيانات وصحة الترميز
    foreach ($employees as &$employee) {
        $employee['full_name'] = $employee['full_name'] ?? 'غير محدد';
        $employee['department_name_for_display'] = $employee['department_name_for_display'] ?? 'غير محدد';
        $employee['position'] = $employee['position'] ?? 'غير محدد';
        $employee['salary'] = $employee['salary'] ?? 0;
        $employee['salary_currency_id'] = $employee['salary_currency_id'] ?? 1;
    }
    
    // إحصائيات الموظفين
    $stats_query = "
        SELECT 
            COALESCE(" . ($departments_table_exists ? "d.department_name" : "u.department_id") . ", 'غير محدد') as department,
            COUNT(*) as count,
            SUM(CASE WHEN u.salary_currency_id = 1 THEN u.salary 
                     ELSE u.salary * COALESCE(c.exchange_rate_to_iqd, 1)
                END) as total_salary_base_currency
        FROM users u";
    
    if ($currencies_table_exists) {
        $stats_query .= " LEFT JOIN currencies c ON u.salary_currency_id = c.id";
    } else {
        $stats_query .= " LEFT JOIN (SELECT 1 as id, 1 as exchange_rate_to_iqd) c ON u.salary_currency_id = c.id";
    }
    
    if ($departments_table_exists) {
        $stats_query .= " LEFT JOIN departments d ON u.department_id = d.id";
    }
    
    $stats_query .= "
        WHERE u.is_active = 1
        GROUP BY COALESCE(" . ($departments_table_exists ? "d.department_name" : "u.department_id") . ", 'غير محدد')
        ORDER BY count DESC";
    
    $stmt = $db->query($stats_query);
    $department_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات عامة
    $general_stats_query = "
        SELECT 
            COUNT(*) as total_employees,
            SUM(CASE WHEN u.is_active = 1 THEN 1 ELSE 0 END) as active_employees,
            SUM(CASE WHEN u.is_active = 1 AND u.salary_currency_id = 1 THEN u.salary 
                     WHEN u.is_active = 1 THEN u.salary * COALESCE(c.exchange_rate_to_iqd, 1)
                     ELSE 0 
                END) as total_salary_cost
        FROM users u";
    
    if ($currencies_table_exists) {
        $general_stats_query .= " LEFT JOIN currencies c ON u.salary_currency_id = c.id";
    } else {
        $general_stats_query .= " LEFT JOIN (SELECT 1 as id, 1 as exchange_rate_to_iqd) c ON u.salary_currency_id = c.id";
    }
    
    $stmt = $db->query($general_stats_query);
    $general_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $employees = [];
    $department_stats = [];
    $general_stats = ['total_employees' => 0, 'active_employees' => 0, 'total_salary_cost' => 0];
    $error = 'خطأ في جلب بيانات الموظفين: ' . $e->getMessage();
}

// جلب الأقسام من قاعدة البيانات
$departments = [];
$departments_by_id = [];
if ($departments_table_exists) {
    try {
        $departments_result = $db->query("SELECT id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name")->fetchAll();
        foreach ($departments_result as $dept) {
            $departments[$dept['id']] = $dept['department_name'];
            $departments_by_id[$dept['id']] = $dept['department_name'];
        }
    } catch (PDOException $e) {
        error_log("Failed to fetch departments: " . $e->getMessage());
    }
}

$user_types = ['admin' => 'مدير النظام', 'manager' => 'مدير', 'employee' => 'موظف'];
$contract_types = ['monthly' => 'شهرية', 'daily' => 'يومية'];

// الحصول على العملة الافتراضية لعرض الإحصائيات
$default_currency = getDefaultCurrency();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الموارد البشرية - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="../public/assets/css/tekrit-theme.css" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .modal { display: none; }
        .modal.active { display: flex; }
        .currency-amount {
            font-family: 'Courier New', monospace;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-slate-100">
    <!-- Navigation Bar -->
    <nav class="tekrit-header shadow-lg mb-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <img src="../public/assets/images/Tekrit_LOGO.jpg" alt="شعار بلدية تكريت" class="tekrit-logo ml-4">
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">نظام الموارد البشرية</h1>
                        <p class="text-sm text-gray-600">إدارة شاملة لموظفي البلدية</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4 space-x-reverse">
                    <a href="../comprehensive_dashboard.php" class="btn-primary-orange">
                        🏠 العودة للوحة التحكم
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="min-h-screen p-6">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-slate-800">الموارد البشرية</h1>
                <div class="flex gap-3">
                    <button onclick="openModal('addEmployeeModal')" class="btn-primary-orange">
                        👤➕ إضافة موظف
                    </button>
                </div>
            </div>
            <p class="text-slate-600 mt-2">إدارة بيانات الموظفين والرواتب والحضور</p>
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

        <!-- إحصائيات الموظفين -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">إجمالي الموظفين</p>
                        <p class="text-2xl font-bold text-blue-600"><?= $general_stats['total_employees'] ?></p>
                    </div>
                    <div class="bg-blue-100 text-blue-600 p-3 rounded-full">👥</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">الموظفون النشطون</p>
                        <p class="text-2xl font-bold text-green-600"><?= $general_stats['active_employees'] ?></p>
                    </div>
                    <div class="bg-green-100 text-green-600 p-3 rounded-full">✅</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">إجمالي الرواتب</p>
                        <p class="text-2xl font-bold text-purple-600 currency-amount">
                            <?= formatCurrency($general_stats['total_salary_cost'], $default_currency['id']) ?>
                        </p>
                    </div>
                    <div class="bg-purple-100 text-purple-600 p-3 rounded-full">💰</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">متوسط الراتب</p>
                        <p class="text-2xl font-bold text-orange-600 currency-amount">
                            <?= $general_stats['active_employees'] > 0 ? formatCurrency($general_stats['total_salary_cost'] / $general_stats['active_employees'], $default_currency['id']) : '0' ?>
                        </p>
                    </div>
                    <div class="bg-orange-100 text-orange-600 p-3 rounded-full">📊</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            <!-- الموظفون حسب القسم -->
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h3 class="font-semibold mb-4">الموظفون حسب القسم</h3>
                <div class="space-y-3">
                    <?php foreach ($department_stats as $dept): ?>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600"><?= htmlspecialchars($dept['department']) ?></span>
                            <div class="flex items-center gap-2">
                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs"><?= $dept['count'] ?></span>
                                <span class="text-xs text-gray-500 currency-amount"><?= formatCurrency($dept['total_salary_base_currency'], $default_currency['id']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- إجراءات سريعة -->
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h3 class="font-semibold mb-4">إجراءات سريعة</h3>
                <div class="space-y-3">
                    <button class="w-full bg-blue-50 hover:bg-blue-100 text-blue-700 py-2 px-4 rounded-md text-sm transition">
                        📋 تقرير الحضور اليومي
                    </button>
                    <button class="w-full bg-green-50 hover:bg-green-100 text-green-700 py-2 px-4 rounded-md text-sm transition">
                        💰 حساب الرواتب الشهرية
                    </button>
                    <button class="w-full bg-yellow-50 hover:bg-yellow-100 text-yellow-700 py-2 px-4 rounded-md text-sm transition">
                        📊 تقرير الإجازات
                    </button>
                    <a href="../manage_tables.php" class="w-full bg-indigo-50 hover:bg-indigo-100 text-indigo-700 py-2 px-4 rounded-md text-sm transition block text-center">
                        🎛️ إدارة الجداول
                    </a>
                    <button class="w-full bg-purple-50 hover:bg-purple-100 text-purple-700 py-2 px-4 rounded-md text-sm transition">
                        📈 تقييمات الأداء
                    </button>
                </div>
            </div>

            <!-- التقويم -->
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h3 class="font-semibold mb-4">التقويم والأحداث</h3>
                <div class="space-y-3 text-sm">
                    <div class="bg-red-50 p-3 rounded">
                        <p class="font-medium text-red-800">إجازة رسمية</p>
                        <p class="text-red-600">15 يناير 2025</p>
                    </div>
                    <div class="bg-blue-50 p-3 rounded">
                        <p class="font-medium text-blue-800">اجتماع شهري</p>
                        <p class="text-blue-600">20 يناير 2025</p>
                    </div>
                    <div class="bg-green-50 p-3 rounded">
                        <p class="font-medium text-green-800">تدريب الموظفين</p>
                        <p class="text-green-600">25 يناير 2025</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- فلاتر البحث -->
        <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
            <h3 class="font-semibold mb-4">بحث وفلترة الموظفين</h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">البحث بالاسم</label>
                    <input type="text" name="name" id="searchName"
                           value="<?= htmlspecialchars($filter_name, ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="ابحث بالاسم أو اسم المستخدم..."
                           onkeyup="searchEmployees()"
                           class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    <small class="text-gray-500">اكتب للبحث فوري أو اضغط 'بحث' للفلترة الكاملة</small>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">القسم</label>
                    <select name="department" class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        <option value="">جميع الأقسام</option>
                        <?php foreach ($departments as $id => $name): ?>
                            <option value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>" <?= ($filter_department == $id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الحالة</label>
                    <select name="status" class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        <option value="">جميع الحالات</option>
                        <option value="1" <?= ($filter_status === '1') ? 'selected' : '' ?>>نشط</option>
                        <option value="0" <?= ($filter_status === '0') ? 'selected' : '' ?>>غير نشط</option>
                    </select>
                </div>
                
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                        🔍 بحث
                    </button>
                    <a href="?" class="bg-gray-100 text-gray-700 py-2 px-3 rounded-md hover:bg-gray-200 transition">
                        ↻
                    </a>
                </div>
            </form>
        </div>

        <!-- جدول الموظفين -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold">قائمة الموظفين</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th class="px-6 py-3">الرقم</th>
                            <th class="px-6 py-3">الاسم</th>
                            <th class="px-6 py-3">القسم</th>
                            <th class="px-6 py-3">المنصب</th>
                            <th class="px-6 py-3">النوع</th>
                            <th class="px-6 py-3">نوع العقد</th>
                            <th class="px-6 py-3">الراتب</th>
                            <th class="px-6 py-3">تاريخ التوظيف</th>
                            <th class="px-6 py-3">الحالة</th>
                            <th class="px-6 py-3">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $displayed_ids = []; foreach ($employees as $employee): 
						if(in_array($employee['id'], $displayed_ids)) continue;
    $displayed_ids[] = $employee['id'];
						?>
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium">#<?= $employee['id'] ?></td>
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium"><?= htmlspecialchars($employee['full_name'], ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($employee['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($employee['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars($employee['department_name_for_display'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-800">
                                        <?= htmlspecialchars($employee['position_name_for_display'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded bg-orange-100 text-orange-800">
                                        <?= htmlspecialchars($employee['user_type_name_for_display'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded bg-purple-100 text-purple-800">
                                        <?= htmlspecialchars($employee['contract_type_name_for_display'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-semibold text-green-600 currency-amount">
                                    <?= formatCurrency($employee['salary'], $employee['salary_currency_id']) ?>
                                </td>
                                <td class="px-6 py-4"><?= $employee['hire_date'] ? date('Y-m-d', strtotime($employee['hire_date'])) : '-' ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded 
                                        <?= $employee['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= $employee['is_active'] ? 'نشط' : 'غير نشط' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex gap-2">
                                        <button onclick="viewEmployee(<?= $employee['id'] ?>)" 
                                                class="bg-blue-100 text-blue-600 px-2 py-1 rounded text-xs hover:bg-blue-200">
                                            عرض
                                        </button>
                                        <button onclick="editEmployee(<?= $employee['id'] ?>)" 
                                                class="bg-yellow-100 text-yellow-600 px-2 py-1 rounded text-xs hover:bg-yellow-200">
                                            تعديل
                                        </button>
                                        <button onclick="deleteEmployee(<?= $employee['id'] ?>, '<?= htmlspecialchars($employee['full_name'], ENT_QUOTES, 'UTF-8') ?>')" 
                                                class="bg-red-100 text-red-600 px-2 py-1 rounded text-xs hover:bg-red-200">
                                            حذف
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($employees)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-8 text-gray-500">
                                    لا توجد بيانات موظفين مطابقة للفلتر المحدد
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal إضافة موظف جديد -->
    <div id="addEmployeeModal" class="modal fixed inset-0 bg-black bg-opacity-50 justify-center items-center z-50">
        <div class="bg-white p-6 rounded-lg max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-semibold mb-4">إضافة موظف جديد</h3>
            
            <form method="POST" class="space-y-4">
                <?php echo csrf_input('csrf_token'); ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">اسم المستخدم *</label>
                        <input type="text" name="username" id="username" required 
                               value=""
                               autocomplete="off" 
                               autocapitalize="off"
                               spellcheck="false"
                               placeholder="أدخل اسم مستخدم فريد"
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                               onblur="checkUsername()" onkeyup="checkUsernameDelayed()">
                        <div id="usernameStatus" class="text-sm mt-1"></div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور *</label>
                        <input type="password" name="password" required 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الاسم الكامل *</label>
                        <input type="text" name="full_name" required 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                        <input type="email" name="email" 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                        <input type="tel" name="phone" 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">القسم *</label>
                        <select name="department_id" required 
                                class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">اختر القسم</option>
                            <?php foreach ($departments as $id => $name): ?>
                                <option value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">المنصب *</label>
                        <select name="position_id" required class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">اختر المنصب</option>
                            <?php
                            // جلب المناصب من الجدول
                            try {
                                $positions_stmt = $db->query("SELECT id, position_name FROM positions WHERE is_active = 1 ORDER BY position_name");
                                $positions = $positions_stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($positions as $position) {
                                    echo "<option value='{$position['id']}'>" . htmlspecialchars($position['position_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                                }
                            } catch (Exception $e) {
                                echo "<option value=''>خطأ في تحميل المناصب</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نوع المستخدم *</label>
                        <select name="user_type_id" required class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">اختر نوع المستخدم</option>
                            <?php
                            // جلب أنواع المستخدمين من الجدول
                            try {
                                $user_types_stmt = $db->query("SELECT id, type_name FROM user_types WHERE is_active = 1 ORDER BY type_name");
                                $user_types = $user_types_stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($user_types as $type) {
                                    echo "<option value='{$type['id']}'>" . htmlspecialchars($type['type_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                                }
                            } catch (Exception $e) {
                                echo "<option value=''>خطأ في تحميل أنواع المستخدمين</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ التوظيف</label>
                        <input type="date" name="hire_date" value="<?= date('Y-m-d') ?>"
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نوع العقد *</label>
                        <select name="contract_type_id" required class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">اختر نوع العقد</option>
                            <?php
                            // جلب أنواع العقود من الجدول
                            try {
                                $contract_types_stmt = $db->query("SELECT id, type_name FROM contract_types WHERE is_active = 1 ORDER BY type_name");
                                $contract_types = $contract_types_stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($contract_types as $contract_type) {
                                    echo "<option value='{$contract_type['id']}'>" . htmlspecialchars($contract_type['type_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                                }
                            } catch (Exception $e) {
                                echo "<option value=''>خطأ في تحميل أنواع العقود</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الراتب</label>
                        <input type="number" step="0.01" min="0" name="salary" 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">عملة الراتب</label>
                        <?= getCurrencySelect('salary_currency_id', null, 'w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500', true) ?>
                    </div>
                </div>
                
                <div class="flex gap-4 pt-4">
                    <button type="submit" name="add_employee" 
                            class="flex-1 bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition">
                        إضافة الموظف
                    </button>
                    <button type="button" onclick="closeModal('addEmployeeModal')" 
                            class="px-6 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition">
                        إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal تعديل موظف -->
    <div id="editEmployeeModal" class="modal fixed inset-0 bg-black bg-opacity-50 justify-center items-center z-50">
        <div class="bg-white p-6 rounded-lg max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <h3 id="editModalTitle" class="text-xl font-semibold mb-4">تعديل الموظف</h3>
            
            <form method="POST" class="space-y-4">
                <?php echo csrf_input('csrf_token'); ?>
                <input type="hidden" name="employee_id" id="edit_employee_id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">اسم المستخدم *</label>
                        <input type="text" name="username" id="edit_username" required 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور الجديدة</label>
                        <input type="password" name="password" placeholder="اتركها فارغة للاحتفاظ بكلمة المرور الحالية"
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-xs text-gray-500 mt-1">اتركها فارغة إذا كنت لا تريد تغيير كلمة المرور</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الاسم الكامل *</label>
                        <input type="text" name="full_name" id="edit_full_name" required 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                        <input type="email" name="email" id="edit_email" 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                        <input type="tel" name="phone" id="edit_phone" 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">القسم *</label>
                        <select name="department_id" id="edit_department_id" required 
                                class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">اختر القسم</option>
                            <?php foreach ($departments as $id => $name): ?>
                                <option value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">المنصب *</label>
                        <select name="position_id" id="edit_position_id" required class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">اختر المنصب</option>
                            <?php
                            try {
                                $positions_stmt = $db->query("SELECT id, position_name FROM positions WHERE is_active = 1 ORDER BY position_name");
                                $positions = $positions_stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($positions as $position) {
                                    echo "<option value='{$position['id']}'>" . htmlspecialchars($position['position_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                                }
                            } catch (Exception $e) {
                                echo "<option value=''>خطأ في تحميل المناصب</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نوع المستخدم *</label>
                        <select name="user_type_id" id="edit_user_type_id" required class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">اختر نوع المستخدم</option>
                            <?php
                            try {
                                $user_types_stmt = $db->query("SELECT id, type_name FROM user_types WHERE is_active = 1 ORDER BY type_name");
                                $user_types = $user_types_stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($user_types as $type) {
                                    echo "<option value='{$type['id']}'>" . htmlspecialchars($type['type_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                                }
                            } catch (Exception $e) {
                                echo "<option value=''>خطأ في تحميل أنواع المستخدمين</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نوع العقد *</label>
                        <select name="contract_type_id" id="edit_contract_type_id" required class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">اختر نوع العقد</option>
                            <?php
                            try {
                                $contract_types_stmt = $db->query("SELECT id, type_name FROM contract_types WHERE is_active = 1 ORDER BY type_name");
                                $contract_types = $contract_types_stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($contract_types as $contract_type) {
                                    echo "<option value='{$contract_type['id']}'>" . htmlspecialchars($contract_type['type_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                                }
                            } catch (Exception $e) {
                                echo "<option value=''>خطأ في تحميل أنواع العقود</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="edit_is_active" class="mr-2 block text-sm text-gray-900">الموظف نشط</label>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الراتب</label>
                        <input type="number" step="0.01" min="0" name="salary" id="edit_salary" 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">عملة الراتب</label>
                        <select name="salary_currency_id" id="edit_salary_currency_id" class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="1">ليرة لبنانية (ل.ل)</option>
                            <option value="2">دولار أمريكي ($)</option>
                            <option value="3">يورو (€)</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex gap-4 pt-4">
                    <button type="submit" name="update_employee" 
                            class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                        💾 حفظ التغييرات
                    </button>
                    <button type="button" onclick="closeModal('editEmployeeModal')" 
                            class="px-6 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition">
                        إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let usernameCheckTimeout;
        
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
            
            if (modalId === 'addEmployeeModal') {
                clearEmployeeForm();
            }
        }
        
        function clearEmployeeForm() {
            document.getElementById('username').value = '';
            document.querySelector('input[name="password"]').value = '';
            document.querySelector('input[name="full_name"]').value = '';
            document.querySelector('input[name="email"]').value = '';
            document.querySelector('input[name="phone"]').value = '';
            document.querySelector('select[name="department_id"]').value = '';
            document.querySelector('select[name="position_id"]').value = '';
            document.querySelector('select[name="user_type_id"]').value = '';
            document.querySelector('input[name="hire_date"]').value = '<?= date('Y-m-d') ?>';
            document.querySelector('select[name="contract_type_id"]').value = '';
            document.querySelector('input[name="salary"]').value = '';
            document.querySelector('select[name="salary_currency_id"]').selectedIndex = 0;
            document.getElementById('usernameStatus').innerHTML = '';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function checkUsername() {
            const username = document.getElementById('username').value.trim();
            const statusDiv = document.getElementById('usernameStatus');
            
            if (username.length < 3) {
                statusDiv.innerHTML = '<span class="text-yellow-600">⚠️ اسم المستخدم يجب أن يكون 3 أحرف على الأقل</span>';
                return;
            }
            
            statusDiv.innerHTML = '<span class="text-blue-600">🔍 جاري التحقق...</span>';
            
            fetch(`check_username.php?username=${encodeURIComponent(username)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.available) {
                        statusDiv.innerHTML = '<span class="text-green-600">✅ ' + data.message + '</span>';
                    } else {
                        statusDiv.innerHTML = '<span class="text-red-600">❌ ' + data.message + '</span>';
                    }
                })
                .catch(error => {
                    statusDiv.innerHTML = '<span class="text-red-600">❌ خطأ في التحقق من اسم المستخدم</span>';
                });
        }
        
        function checkUsernameDelayed() {
            clearTimeout(usernameCheckTimeout);
            usernameCheckTimeout = setTimeout(checkUsername, 1000);
        }
        
        function showCustomMessage(message, type = 'info') {
            const messageBox = document.getElementById('customMessageBox');
            const messageContent = document.getElementById('customMessageContent');
            
            if (!messageBox) {
                const box = document.createElement('div');
                box.id = 'customMessageBox';
                box.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 opacity-0 transition-opacity duration-300';
                box.innerHTML = `
                    <div class="bg-white rounded-lg p-6 max-w-md mx-4 transform scale-95 transition-transform duration-300">
                        <div id="customMessageContent" class="text-right mb-4"></div>
                        <button onclick="closeCustomMessage()" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                            موافق
                        </button>
                    </div>
                `;
                document.body.appendChild(box);
                
                window.closeCustomMessage = function() {
                    const box = document.getElementById('customMessageBox');
                    box.style.opacity = '0';
                    box.querySelector('div').style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        box.style.display = 'none';
                    }, 300);
                };
            }
            
            const messageBox2 = document.getElementById('customMessageBox');
            const messageContent2 = document.getElementById('customMessageContent');
            
            messageContent2.innerHTML = message;
            
            messageBox2.style.display = 'flex';
            setTimeout(() => {
                messageBox2.style.opacity = '1';
                messageBox2.querySelector('div').style.transform = 'scale(1)';
            }, 10);
        }
        
        function viewEmployee(id) {
            fetch(`get_employee.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const employee = data.employee;
                        showCustomMessage(`
                            <h3 class="text-lg font-semibold mb-3 text-blue-600">تفاصيل الموظف</h3>
                            <div class="space-y-2 text-sm">
                                <p><strong>الاسم:</strong> ${employee.full_name}</p>
                                <p><strong>القسم:</strong> ${employee.department}</p>
                                <p><strong>المنصب:</strong> ${employee.position}</p>
                                <p><strong>نوع العقد:</strong> ${employee.contract_type}</p>
                                <p><strong>الراتب:</strong> ${employee.salary} ${employee.currency_symbol}</p>
                                <p><strong>الحالة:</strong> <span class="${employee.is_active ? 'text-green-600' : 'text-red-600'}">${employee.is_active ? 'نشط' : 'غير نشط'}</span></p>
                            </div>
                        `, 'info');
                    } else {
                        showCustomMessage('خطأ في جلب بيانات الموظف', 'error');
                    }
                })
                .catch(error => {
                    showCustomMessage('خطأ في الاتصال بالخادم', 'error');
                });
        }
        
        function editEmployee(id) {
            console.log('بدء تعديل الموظف:', id);
            
            // جلب بيانات الموظف وعرضها في modal
            fetch(`get_employee.php?id=${id}&full_data=1`)
                .then(response => {
                    console.log('استجابة الخادم:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('بيانات مُستلمة:', data);
                    if (data.success) {
                        populateEditModal(data.employee);
                        openModal('editEmployeeModal');
                    } else {
                        showCustomMessage('خطأ في جلب بيانات الموظف: ' + (data.message || 'خطأ غير معروف'), 'error');
                    }
                })
                .catch(error => {
                    console.error('خطأ في fetch:', error);
                    showCustomMessage('خطأ في الاتصال بالخادم: ' + error.message, 'error');
                });
        }
        
        function populateEditModal(employee) {
            console.log('بيانات الموظف المُستلمة:', employee);
            
            try {
                // التحقق من وجود كل عنصر قبل تعديله
                const setElementValue = (id, value) => {
                    const element = document.getElementById(id);
                    if (element) {
                        if (element.type === 'checkbox') {
                            element.checked = value == 1;
                        } else {
                            element.value = value || '';
                        }
                    } else {
                        console.warn(`العنصر غير موجود: ${id}`);
                    }
                };
                
                setElementValue('edit_employee_id', employee.id);
                setElementValue('edit_username', employee.username);
                setElementValue('edit_full_name', employee.full_name);
                setElementValue('edit_email', employee.email);
                setElementValue('edit_phone', employee.phone);
                setElementValue('edit_department_id', employee.department_id);
                setElementValue('edit_position_id', employee.position_id);
                setElementValue('edit_user_type_id', employee.user_type_id);
                setElementValue('edit_contract_type_id', employee.contract_type_id);
                setElementValue('edit_salary', employee.salary);
                setElementValue('edit_salary_currency_id', employee.salary_currency_id);
                setElementValue('edit_is_active', employee.is_active);
                
                // تحديث عنوان Modal
                const titleElement = document.getElementById('editModalTitle');
                if (titleElement) {
                    titleElement.textContent = `تعديل الموظف: ${employee.full_name}`;
                }
                
                console.log('تم ملء البيانات بنجاح');
                
            } catch (error) {
                console.error('خطأ في ملء نموذج التعديل:', error);
                showCustomMessage('خطأ في ملء البيانات: ' + error.message, 'error');
            }
        }
        
        function deleteEmployee(id, fullName) {
            if (confirm(`هل أنت متأكد من حذف الموظف "${fullName}"؟\n\n⚠️ تحذير: هذا الإجراء لا يمكن التراجع عنه!`)) {
                if (confirm(`تأكيد نهائي: سيتم حذف الموظف "${fullName}" نهائياً من النظام.\n\nهل تريد المتابعة؟`)) {
                    // إرسال طلب الحذف
                    fetch('delete_employee.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            employee_id: id
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showCustomMessage(`✅ تم حذف الموظف "${fullName}" بنجاح`, 'success');
                            // إعادة تحميل الصفحة بعد 2 ثانية
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        } else {
                            showCustomMessage(`❌ خطأ في حذف الموظف: ${data.message}`, 'error');
                        }
                    })
                    .catch(error => {
                        showCustomMessage('❌ خطأ في الاتصال بالخادم', 'error');
                    });
                }
            }
        }
        
        // بحث فوري في الجدول
        function searchEmployees() {
            const searchTerm = document.getElementById('searchName').value.toLowerCase();
            const table = document.querySelector('tbody');
            const rows = table.querySelectorAll('tr');
            
            let visibleCount = 0;
            
            rows.forEach(row => {
                // تجنب صف "لا توجد بيانات"
                if (row.querySelector('td[colspan]')) {
                    return;
                }
                
                const employeeName = row.querySelector('td:nth-child(2) p:first-child')?.textContent.toLowerCase();
                const username = row.querySelector('td:nth-child(2) p:nth-child(2)')?.textContent.toLowerCase();
                
                if (!searchTerm || 
                    (employeeName && employeeName.includes(searchTerm)) || 
                    (username && username.includes(searchTerm))) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // إظهار رسالة إذا لم يتم العثور على نتائج  
            const noResultsRow = table.querySelector('.no-results-row');
            if (visibleCount === 0 && searchTerm) {
                if (!noResultsRow) {
                    const newRow = document.createElement('tr');
                    newRow.className = 'no-results-row';
                    newRow.innerHTML = `
                        <td colspan="10" class="text-center py-8 text-gray-500 bg-yellow-50">
                            لم يتم العثور على موظفين يطابقون البحث "${searchTerm}"
                        </td>
                    `;
                    table.appendChild(newRow);
                }
            } else if (noResultsRow) {
                noResultsRow.remove();
            }
        }
        
        // مسح البحث
        function clearSearch() {
            document.getElementById('searchName').value = '';
            searchEmployees();
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
