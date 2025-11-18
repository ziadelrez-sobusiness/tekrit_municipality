<?php
// تحميل CSRF Protection
if (file_exists(__DIR__ . '/../includes/csrf_middleware.php')) {
    require_once __DIR__ . '/../includes/csrf_middleware.php';
}

require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/auth_helper.php';

// التأكد من تسجيل الدخول
$auth->requireLogin();

// التحقق من صلاحية الوصول إلى إدارة الآليات
requirePermission('vehicles_view');

$database = new Database();
$db = $database->getConnection();
$user = $auth->getUserInfo();

$message = '';
$error = '';

// معالجة إضافة آلية جديدة - فقط للمستخدمين المخولين
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vehicle']) && hasPermission('vehicles_add')) {
    if (!csrf_protect(false)) {
        $error = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        $name = trim($_POST['name']);
        $type = trim($_POST['type']);
        $model = trim($_POST['model']);
        $year = intval($_POST['year']);
        $license_plate = trim($_POST['license_plate']);
        $department = $_POST['department'];
        $fuel_type = $_POST['fuel_type'];
        $acquisition_date = $_POST['acquisition_date'];
        $acquisition_cost = floatval($_POST['acquisition_cost']);
        $assigned_driver_id = !empty($_POST['assigned_driver_id']) ? intval($_POST['assigned_driver_id']) : null;
        
        if (!empty($name) && !empty($type) && !empty($license_plate)) {
            try {
                $query = "INSERT INTO vehicles (name, type, model, year, license_plate, department, fuel_type, acquisition_date, acquisition_cost, assigned_driver_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'جاهز')";
                $stmt = $db->prepare($query);
                $stmt->execute([$name, $type, $model, $year, $license_plate, $department, $fuel_type, $acquisition_date, $acquisition_cost, $assigned_driver_id]);
                $message = 'تم إضافة الآلية بنجاح!';
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) { // Duplicate entry
                    $error = 'رقم اللوحة موجود مسبقاً';
                } else {
                    $error = 'خطأ في إضافة الآلية: ' . $e->getMessage();
                }
            }
        } else {
            $error = 'يرجى تعبئة الحقول المطلوبة';
        }
    }
}

// معالجة إضافة صيانة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_maintenance'])) {
    if (!csrf_protect(false)) {
        $error = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        $vehicle_id = intval($_POST['vehicle_id']);
        $maintenance_type = trim($_POST['maintenance_type']);
        $description = trim($_POST['description']);
        $maintenance_date = $_POST['maintenance_date'];
        $cost = floatval($_POST['cost']);
        $performed_by = trim($_POST['performed_by']);
        $next_maintenance_date = $_POST['next_maintenance_date'];
        
        if ($vehicle_id > 0 && !empty($maintenance_type)) {
            try {
                $query = "INSERT INTO vehicle_maintenance (vehicle_id, maintenance_type, description, maintenance_date, cost, performed_by, next_maintenance_date, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$vehicle_id, $maintenance_type, $description, $maintenance_date, $cost, $performed_by, $next_maintenance_date, $user['id']]);
                
                // تحديث تاريخ الصيانة في جدول الآليات
                $update_query = "UPDATE vehicles SET last_maintenance_date = ?, next_maintenance_date = ? WHERE id = ?";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([$maintenance_date, $next_maintenance_date, $vehicle_id]);
                
                $message = 'تم إضافة سجل الصيانة بنجاح!';
            } catch (PDOException $e) {
                $error = 'خطأ في إضافة سجل الصيانة: ' . $e->getMessage();
            }
        } else {
            $error = 'يرجى تعبئة الحقول المطلوبة';
        }
    }
}

// معالجة تحديث حالة الآلية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!csrf_protect(false)) {
        $error = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        $vehicle_id = intval($_POST['vehicle_id']);
        $new_status = $_POST['new_status'];
        
        try {
            $query = "UPDATE vehicles SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$new_status, $vehicle_id]);
            $message = 'تم تحديث حالة الآلية بنجاح!';
        } catch (PDOException $e) {
            $error = 'خطأ في تحديث حالة الآلية: ' . $e->getMessage();
        }
    }
}

// جلب الآليات
try {
    $filter_department = $_GET['department'] ?? '';
    $filter_status = $_GET['status'] ?? '';
    $filter_type = $_GET['type'] ?? '';
    
    $where_conditions = [];
    $params = [];
    
    if (!empty($filter_department)) {
        $where_conditions[] = "v.department = ?";
        $params[] = $filter_department;
    }
    
    if (!empty($filter_status)) {
        $where_conditions[] = "v.status = ?";
        $params[] = $filter_status;
    }
    
    if (!empty($filter_type)) {
        $where_conditions[] = "v.type = ?";
        $params[] = $filter_type;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $stmt = $db->prepare("
        SELECT v.*, u.full_name as driver_name 
        FROM vehicles v 
        LEFT JOIN users u ON v.assigned_driver_id = u.id 
        $where_clause
        ORDER BY v.created_at DESC 
        LIMIT 100
    ");
    $stmt->execute($params);
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات الآليات
    $stmt = $db->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM vehicles 
        GROUP BY status
    ");
    $status_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // إحصائيات عامة
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_vehicles,
            SUM(acquisition_cost) as total_cost,
            AVG(YEAR(CURDATE()) - year) as avg_age
        FROM vehicles
    ");
    $general_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // جلب الموظفين (السائقين المحتملين) - جميع الموظفين النشطين
    $stmt = $db->query("
        SELECT id, full_name, department, position 
        FROM users 
        WHERE is_active = 1 
        ORDER BY 
            CASE 
                WHEN department IN ('النظافة', 'الصيانة', 'المياه', 'الطوارئ', 'الهندسة') THEN 1
                WHEN department IN ('الإدارة المالية', 'الموارد البشرية', 'القانونية') THEN 2
                ELSE 3
            END,
            full_name
    ");
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب الصيانات الأخيرة
    $stmt = $db->query("
        SELECT vm.*, v.name as vehicle_name, v.license_plate 
        FROM vehicle_maintenance vm 
        JOIN vehicles v ON vm.vehicle_id = v.id 
        ORDER BY vm.maintenance_date DESC 
        LIMIT 10
    ");
    $recent_maintenance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $vehicles = [];
    $status_stats = [];
    $general_stats = ['total_vehicles' => 0, 'total_cost' => 0, 'avg_age' => 0];
    $drivers = [];
    $recent_maintenance = [];
}

$departments = ['الإدارة المالية', 'الهندسة', 'الموارد البشرية', 'القانونية', 'خدمة المواطنين', 'تقنية المعلومات', 'النظافة', 'الصيانة', 'المياه', 'الطوارئ'];
$vehicle_types = ['سيارة', 'شاحنة', 'حافلة', 'معدة ثقيلة', 'آلية زراعية', 'دراجة نارية'];
$fuel_types = ['بنزين', 'ديزل', 'هجين', 'كهربائي', 'غاز'];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الآليات - بلدية تكريت</title>
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
                <h1 class="text-3xl font-bold text-slate-800">إدارة الآليات والمعدات</h1>
                <div class="flex gap-3">
                    <button onclick="openModal('addVehicleModal')" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        🚗➕ إضافة آلية
                    </button>
                    <button onclick="openModal('addMaintenanceModal')" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        🔧 صيانة
                    </button>
                    <button onclick="showDriversSection()" class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition">
                        👥 السائقون
                    </button>
                    <a href="../comprehensive_dashboard.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                        ← العودة للوحة التحكم
                    </a>
                </div>
            </div>
            <p class="text-slate-600 mt-2">متابعة وإدارة الآليات والمعدات والصيانة</p>
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

        <!-- إحصائيات الآليات -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">إجمالي الآليات</p>
                        <p class="text-2xl font-bold text-blue-600"><?= $general_stats['total_vehicles'] ?></p>
                    </div>
                    <div class="bg-blue-100 text-blue-600 p-3 rounded-full">🚗</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">آليات جاهزة</p>
                        <p class="text-2xl font-bold text-green-600"><?= $status_stats['جاهز'] ?? 0 ?></p>
                    </div>
                    <div class="bg-green-100 text-green-600 p-3 rounded-full">✅</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">قيد الصيانة</p>
                        <p class="text-2xl font-bold text-yellow-600"><?= $status_stats['قيد الصيانة'] ?? 0 ?></p>
                    </div>
                    <div class="bg-yellow-100 text-yellow-600 p-3 rounded-full">🔧</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">معطلة</p>
                        <p class="text-2xl font-bold text-red-600"><?= $status_stats['معطل'] ?? 0 ?></p>
                    </div>
                    <div class="bg-red-100 text-red-600 p-3 rounded-full">❌</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            <!-- الصيانات الأخيرة -->
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h3 class="font-semibold mb-4">آخر الصيانات</h3>
                <div class="space-y-3">
                    <?php foreach ($recent_maintenance as $maintenance): ?>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="font-medium text-sm"><?= htmlspecialchars($maintenance['vehicle_name']) ?></p>
                            <p class="text-xs text-gray-600"><?= htmlspecialchars($maintenance['maintenance_type']) ?></p>
                            <p class="text-xs text-gray-500"><?= date('Y-m-d', strtotime($maintenance['maintenance_date'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- إجراءات سريعة -->
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h3 class="font-semibold mb-4">إجراءات سريعة</h3>
                <div class="space-y-3">
                    <button class="w-full bg-blue-50 hover:bg-blue-100 text-blue-700 py-2 px-4 rounded-md text-sm transition">
                        📋 تقرير الآليات الشهري
                    </button>
                    <button class="w-full bg-yellow-50 hover:bg-yellow-100 text-yellow-700 py-2 px-4 rounded-md text-sm transition">
                        ⏰ جدولة الصيانة
                    </button>
                    <button class="w-full bg-green-50 hover:bg-green-100 text-green-700 py-2 px-4 rounded-md text-sm transition">
                        ⛽ تتبع استهلاك الوقود
                    </button>
                    <button class="w-full bg-purple-50 hover:bg-purple-100 text-purple-700 py-2 px-4 rounded-md text-sm transition">
                        📊 تحليل التكاليف
                    </button>
                </div>
            </div>

            <!-- إحصائيات إضافية -->
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h3 class="font-semibold mb-4">معلومات إضافية</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">القيمة الإجمالية:</span>
                        <span class="font-semibold"><?= number_format($general_stats['total_cost']) ?> ل.ل</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">متوسط العمر:</span>
                        <span class="font-semibold"><?= round($general_stats['avg_age']) ?> سنة</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">نسبة الجاهزية:</span>
                        <span class="font-semibold text-green-600">
                            <?= $general_stats['total_vehicles'] > 0 ? round(($status_stats['جاهز'] ?? 0) / $general_stats['total_vehicles'] * 100) : 0 ?>%
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- فلاتر البحث -->
        <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
            <h3 class="font-semibold mb-4">فلترة الآليات</h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">القسم</label>
                    <select name="department" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">جميع الأقسام</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept ?>" <?= ($filter_department === $dept) ? 'selected' : '' ?>><?= $dept ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الحالة</label>
                    <select name="status" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">جميع الحالات</option>
                        <option value="جاهز" <?= ($filter_status === 'جاهز') ? 'selected' : '' ?>>جاهز</option>
                        <option value="قيد الصيانة" <?= ($filter_status === 'قيد الصيانة') ? 'selected' : '' ?>>قيد الصيانة</option>
                        <option value="معطل" <?= ($filter_status === 'معطل') ? 'selected' : '' ?>>معطل</option>
                        <option value="خارج الخدمة" <?= ($filter_status === 'خارج الخدمة') ? 'selected' : '' ?>>خارج الخدمة</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">النوع</label>
                    <select name="type" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">جميع الأنواع</option>
                        <?php foreach ($vehicle_types as $type): ?>
                            <option value="<?= $type ?>" <?= ($filter_type === $type) ? 'selected' : '' ?>><?= $type ?></option>
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

        <!-- جدول الآليات -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold">قائمة الآليات</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th class="px-6 py-3">رقم اللوحة</th>
                            <th class="px-6 py-3">اسم الآلية</th>
                            <th class="px-6 py-3">النوع</th>
                            <th class="px-6 py-3">الموديل</th>
                            <th class="px-6 py-3">القسم</th>
                            <th class="px-6 py-3">السائق</th>
                            <th class="px-6 py-3">الحالة</th>
                            <th class="px-6 py-3">آخر صيانة</th>
                            <th class="px-6 py-3">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium"><?= htmlspecialchars($vehicle['license_plate']) ?></td>
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium"><?= htmlspecialchars($vehicle['name']) ?></p>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($vehicle['model']) ?> - <?= $vehicle['year'] ?></p>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars($vehicle['type']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4"><?= htmlspecialchars($vehicle['model'] ?? '-') ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded bg-purple-100 text-purple-800">
                                        <?= htmlspecialchars($vehicle['department']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4"><?= htmlspecialchars($vehicle['driver_name'] ?? 'غير محدد') ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded 
                                        <?= $vehicle['status'] === 'جاهز' ? 'bg-green-100 text-green-800' : 
                                           ($vehicle['status'] === 'قيد الصيانة' ? 'bg-yellow-100 text-yellow-800' : 
                                           ($vehicle['status'] === 'معطل' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')) ?>">
                                        <?= htmlspecialchars($vehicle['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?= $vehicle['last_maintenance_date'] ? date('Y-m-d', strtotime($vehicle['last_maintenance_date'])) : 'لا توجد' ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex gap-2">
                                        <button onclick="viewVehicle(<?= $vehicle['id'] ?>)" 
                                                class="bg-blue-100 text-blue-600 px-2 py-1 rounded text-xs hover:bg-blue-200">
                                            عرض
                                        </button>
                                        <button onclick="updateStatus(<?= $vehicle['id'] ?>)" 
                                                class="bg-yellow-100 text-yellow-600 px-2 py-1 rounded text-xs hover:bg-yellow-200">
                                            تحديث
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($vehicles)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-8 text-gray-500">
                                    لا توجد آليات مطابقة للفلتر المحدد
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal إضافة آلية جديدة -->
    <div id="addVehicleModal" class="modal fixed inset-0 bg-black bg-opacity-50 justify-center items-center z-50">
        <div class="bg-white p-6 rounded-lg max-w-3xl w-full mx-4 max-h-96 overflow-y-auto">
            <h3 class="text-xl font-semibold mb-4">إضافة آلية جديدة</h3>
            
            <form method="POST" class="space-y-4">
                <?php echo csrf_input('csrf_token'); ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">اسم الآلية *</label>
                        <input type="text" name="name" required 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">رقم اللوحة *</label>
                        <input type="text" name="license_plate" required 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">النوع *</label>
                        <select name="type" required 
                                class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">اختر النوع</option>
                            <?php foreach ($vehicle_types as $type): ?>
                                <option value="<?= $type ?>"><?= $type ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">القسم</label>
                        <select name="department" class="w-full p-2 border border-gray-300 rounded-md">
                            <option value="">اختر القسم</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept ?>"><?= $dept ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الموديل</label>
                        <input type="text" name="model" 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">سنة الصنع</label>
                        <input type="number" name="year" min="1990" max="2030"
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نوع الوقود</label>
                        <select name="fuel_type" class="w-full p-2 border border-gray-300 rounded-md">
                            <option value="">اختر نوع الوقود</option>
                            <?php foreach ($fuel_types as $fuel): ?>
                                <option value="<?= $fuel ?>"><?= $fuel ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">السائق المخصص</label>
                        <select name="assigned_driver_id" class="w-full p-2 border border-gray-300 rounded-md">
                            <option value="">اختر السائق</option>
                            <?php foreach ($drivers as $driver): ?>
                                <option value="<?= $driver['id'] ?>">
                                    <?= htmlspecialchars($driver['full_name']) ?> 
                                    <?php if (!empty($driver['department'])): ?>
                                        - (<?= htmlspecialchars($driver['department']) ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">
                            يظهر الموظفون من أقسام: النظافة، الصيانة، الهندسة، المياه، الطوارئ، والإدارة المالية
                        </p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ الشراء</label>
                        <input type="date" name="acquisition_date" 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">تكلفة الشراء (ل.ل)</label>
                        <input type="number" step="1000" min="0" name="acquisition_cost" 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <div class="flex gap-4 pt-4">
                    <button type="submit" name="add_vehicle" 
                            class="flex-1 bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition">
                        إضافة الآلية
                    </button>
                    <button type="button" onclick="closeModal('addVehicleModal')" 
                            class="px-6 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition">
                        إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal إضافة صيانة -->
    <div id="addMaintenanceModal" class="modal fixed inset-0 bg-black bg-opacity-50 justify-center items-center z-50">
        <div class="bg-white p-6 rounded-lg max-w-2xl w-full mx-4 max-h-96 overflow-y-auto">
            <h3 class="text-xl font-semibold mb-4">إضافة سجل صيانة</h3>
            
            <form method="POST" class="space-y-4">
                <?php echo csrf_input('csrf_token'); ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الآلية *</label>
                        <select name="vehicle_id" required 
                                class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">اختر الآلية</option>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <option value="<?= $vehicle['id'] ?>"><?= htmlspecialchars($vehicle['name']) ?> - <?= htmlspecialchars($vehicle['license_plate']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نوع الصيانة *</label>
                        <input type="text" name="maintenance_type" required 
                               placeholder="صيانة دورية، إصلاح، فحص..."
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">وصف الصيانة</label>
                    <textarea name="description" rows="3"
                              class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ الصيانة</label>
                        <input type="date" name="maintenance_date" value="<?= date('Y-m-d') ?>"
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">التكلفة (ل.ل)</label>
                        <input type="number" step="1000" min="0" name="cost" 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">المنفذ بواسطة</label>
                        <input type="text" name="performed_by" 
                               placeholder="اسم الفني أو الورشة"
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الصيانة القادمة</label>
                        <input type="date" name="next_maintenance_date" 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <div class="flex gap-4 pt-4">
                    <button type="submit" name="add_maintenance" 
                            class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                        إضافة سجل الصيانة
                    </button>
                    <button type="button" onclick="closeModal('addMaintenanceModal')" 
                            class="px-6 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition">
                        إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- قسم السائقين (مخفي افتراضياً) -->
    <div id="driversSection" class="fixed inset-0 bg-white z-50 overflow-y-auto" style="display: none;">
        <div class="p-6">
            <!-- Header قسم السائقين -->
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-3xl font-bold text-slate-800">👥 إدارة السائقين</h1>
                <div class="flex gap-3">
                    <button onclick="hideDriversSection()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        🚗 العودة للآليات
                    </button>
                    <a href="hr.php" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        👤➕ إضافة موظف
                    </a>
                </div>
            </div>

            <!-- إحصائيات السائقين -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow-sm border">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">إجمالي السائقين</p>
                            <p class="text-2xl font-bold text-blue-600" id="totalDrivers"><?= count($drivers) ?></p>
                        </div>
                        <div class="bg-blue-100 text-blue-600 p-3 rounded-full">👤</div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow-sm border">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">السائقون المشغولون</p>
                            <p class="text-2xl font-bold text-green-600" id="busyDrivers">
                                <?= count(array_filter($drivers, function($d) { 
                                    return !empty($d['full_name']) && !empty(array_filter($vehicles, function($v) use ($d) { 
                                        return $v['assigned_driver_id'] == $d['id']; 
                                    })); 
                                })) ?>
                            </p>
                        </div>
                        <div class="bg-green-100 text-green-600 p-3 rounded-full">🚗</div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow-sm border">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">السائقون المتاحون</p>
                            <p class="text-2xl font-bold text-yellow-600" id="availableDrivers">
                                <?= count(array_filter($drivers, function($d) { 
                                    return !empty($d['full_name']) && empty(array_filter($vehicles, function($v) use ($d) { 
                                        return $v['assigned_driver_id'] == $d['id']; 
                                    })); 
                                })) ?>
                            </p>
                        </div>
                        <div class="bg-yellow-100 text-yellow-600 p-3 rounded-full">⏱️</div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow-sm border">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">الآليات المخصصة</p>
                            <p class="text-2xl font-bold text-purple-600" id="assignedVehicles">
                                <?= count(array_filter($vehicles, function($v) { return !empty($v['assigned_driver_id']); })) ?>
                            </p>
                        </div>
                        <div class="bg-purple-100 text-purple-600 p-3 rounded-full">🔗</div>
                    </div>
                </div>
            </div>

            <!-- جدول السائقين -->
            <div class="bg-white rounded-lg shadow-sm border">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold">قائمة السائقين والآليات المخصصة</h2>
                    <p class="text-sm text-gray-600 mt-1">لإضافة أو تعديل سائق جديد، يرجى الذهاب إلى وحدة الموارد البشرية</p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-right">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th class="px-6 py-3">اسم السائق</th>
                                <th class="px-6 py-3">رقم الهاتف</th>
                                <th class="px-6 py-3">القسم</th>
                                <th class="px-6 py-3">المنصب</th>
                                <th class="px-6 py-3">الآليات المخصصة</th>
                                <th class="px-6 py-3">الحالة</th>
                                <th class="px-6 py-3">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($drivers as $driver): ?>
                                <?php 
                                $driver_vehicles = array_filter($vehicles, function($v) use ($driver) { 
                                    return $v['assigned_driver_id'] == $driver['id']; 
                                });
                                $vehicle_names = array_map(function($v) { 
                                    return $v['name'] . ' (' . $v['license_plate'] . ')'; 
                                }, $driver_vehicles);
                                ?>
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div>
                                            <p class="font-medium text-gray-900"><?= htmlspecialchars($driver['full_name']) ?></p>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($driver['email'] ?? 'لا يوجد إيميل') ?></p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600">
                                        <?= htmlspecialchars($driver['phone'] ?? 'غير محدد') ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">
                                            <?= htmlspecialchars($driver['department'] ?? 'غير محدد') ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600">
                                        <?= htmlspecialchars($driver['position'] ?? 'غير محدد') ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="max-w-xs">
                                            <?php if (!empty($vehicle_names)): ?>
                                                <p class="text-sm text-gray-700 truncate" title="<?= htmlspecialchars(implode(', ', $vehicle_names)) ?>">
                                                    <?= htmlspecialchars(implode(', ', $vehicle_names)) ?>
                                                </p>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-sm">لا توجد آليات مخصصة</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs rounded font-medium
                                            <?= !empty($driver_vehicles) ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                            <?= !empty($driver_vehicles) ? 'مشغول' : 'متاح' ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex gap-2">
                                            <button onclick="filterVehiclesByDriver(<?= $driver['id'] ?>)" 
                                                class="bg-blue-100 text-blue-600 px-2 py-1 rounded text-xs hover:bg-blue-200 transition">
                                                عرض الآليات
                                            </button>
                                            <a href="hr.php?edit=<?= $driver['id'] ?>" 
                                                class="bg-green-100 text-green-600 px-2 py-1 rounded text-xs hover:bg-green-200 transition">
                                                تعديل البيانات
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($drivers)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-8 text-gray-500">
                                        <div class="flex flex-col items-center">
                                            <div class="text-6xl mb-4">👤</div>
                                            <p class="text-lg font-medium mb-2">لا يوجد سائقون في النظام</p>
                                            <p class="text-sm">يمكنك إضافة موظفين جدد من وحدة الموارد البشرية</p>
                                            <a href="hr.php" class="mt-3 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                                                إضافة موظف جديد
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ملاحظات مهمة -->
            <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-blue-800 mb-3">📋 ملاحظات مهمة:</h3>
                <ul class="space-y-2 text-blue-700">
                    <li>• لإضافة سائق جديد، قم بإضافة موظف جديد في وحدة <strong>الموارد البشرية</strong></li>
                    <li>• لتخصيص آلية لسائق، استخدم نموذج "إضافة آلية" أو "تعديل آلية" في وحدة الآليات</li>
                    <li>• السائقون المتاحون هم الذين لا توجد لهم آليات مخصصة حالياً</li>
                    <li>• يمكن للسائق الواحد أن يكون مسؤولاً عن عدة آليات</li>
                    <li>• لتعديل بيانات السائق (الهاتف، القسم، المنصب)، استخدم وحدة الموارد البشرية</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function showDriversSection() {
            document.getElementById('driversSection').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function hideDriversSection() {
            document.getElementById('driversSection').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        function filterVehiclesByDriver(driverId) {
            hideDriversSection();
            // يمكن إضافة المزيد من الوظائف هنا لفلترة الآليات حسب السائق
            alert('سيتم عرض الآليات المخصصة للسائق #' + driverId);
        }
        
        function viewVehicle(id) {
            alert('عرض تفاصيل الآلية #' + id);
        }
        
        function updateStatus(id) {
            alert('تحديث حالة الآلية #' + id);
        }
        
        // إغلاق المودال عند النقر خارجه
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
