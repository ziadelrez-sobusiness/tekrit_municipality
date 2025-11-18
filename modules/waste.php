<?php
// تحميل CSRF Protection
if (file_exists(__DIR__ . '/../includes/csrf_middleware.php')) {
    require_once __DIR__ . '/../includes/csrf_middleware.php';
}

require_once '../includes/auth.php';
require_once '../config/database.php';

// التأكد من تسجيل الدخول
$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();
$user = $auth->getUserInfo();

$message = '';
$error = '';

// معالجة إضافة جدولة جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_schedule'])) {
    if (!csrf_protect(false)) {
        $error = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        $route_name = trim($_POST['route_name']);
        $area = trim($_POST['area']);
        $schedule_type = $_POST['schedule_type'];
        $collection_day = $_POST['collection_day'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $assigned_team = trim($_POST['assigned_team']);
        $vehicle_id = !empty($_POST['vehicle_id']) ? intval($_POST['vehicle_id']) : null;
        $notes = trim($_POST['notes']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (!empty($route_name) && !empty($area)) {
            try {
                $query = "INSERT INTO waste_collection_schedules (route_name, area, schedule_type, collection_day, start_time, end_time, assigned_team, vehicle_id, notes, is_active, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$route_name, $area, $schedule_type, $collection_day, $start_time, $end_time, $assigned_team, $vehicle_id, $notes, $is_active, $user['id']]);
                $message = 'تم إضافة جدولة جمع النفايات بنجاح!';
            } catch (PDOException $e) {
                $error = 'خطأ في إضافة الجدولة: ' . $e->getMessage();
            }
        } else {
            $error = 'يرجى تعبئة الحقول المطلوبة';
        }
    }
}

// معالجة إضافة تقرير نظافة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_report'])) {
    if (!csrf_protect(false)) {
        $error = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        $area = trim($_POST['area']);
        $report_type = $_POST['report_type'];
        $description = trim($_POST['description']);
        $reporter_name = trim($_POST['reporter_name']);
        $reporter_phone = trim($_POST['reporter_phone']);
        $priority = $_POST['priority'];
        $location_details = trim($_POST['location_details']);
        
        if (!empty($area) && !empty($description)) {
            try {
                $query = "INSERT INTO waste_reports (area, report_type, description, reporter_name, reporter_phone, priority, location_details, status, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, 'مفتوح', ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$area, $report_type, $description, $reporter_name, $reporter_phone, $priority, $location_details, $user['id']]);
                $message = 'تم إضافة تقرير النظافة بنجاح!';
            } catch (PDOException $e) {
                $error = 'خطأ في إضافة التقرير: ' . $e->getMessage();
            }
        } else {
            $error = 'يرجى تعبئة الحقول المطلوبة';
        }
    }
}

// معالجة تحديث حالة التقرير
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_report_status'])) {
    if (!csrf_protect(false)) {
        $error = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        $report_id = intval($_POST['report_id']);
        $new_status = $_POST['new_status'];
        $admin_notes = trim($_POST['admin_notes']);
        
        try {
            $query = "UPDATE waste_reports SET status = ?, admin_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$new_status, $admin_notes, $report_id]);
            $message = 'تم تحديث حالة التقرير بنجاح!';
        } catch (PDOException $e) {
            $error = 'خطأ في تحديث التقرير: ' . $e->getMessage();
        }
    }
}

// جلب الجداول والتقارير
try {
    $filter_area = $_GET['area'] ?? '';
    $filter_day = $_GET['day'] ?? '';
    $filter_type = $_GET['type'] ?? '';
    
    // جلب جداول جمع النفايات
    $where_conditions = [];
    $params = [];
    
    if (!empty($filter_area)) {
        $where_conditions[] = "area LIKE ?";
        $params[] = "%$filter_area%";
    }
    
    if (!empty($filter_day)) {
        $where_conditions[] = "collection_day = ?";
        $params[] = $filter_day;
    }
    
    if (!empty($filter_type)) {
        $where_conditions[] = "schedule_type = ?";
        $params[] = $filter_type;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $stmt = $db->prepare("
        SELECT wcs.*, v.name as vehicle_name, v.license_plate 
        FROM waste_collection_schedules wcs 
        LEFT JOIN vehicles v ON wcs.vehicle_id = v.id 
        $where_clause
        ORDER BY wcs.collection_day, wcs.start_time 
        LIMIT 100
    ");
    $stmt->execute($params);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب تقارير النظافة
    $stmt = $db->query("
        SELECT * FROM waste_reports 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات النظافة
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_schedules,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_schedules
        FROM waste_collection_schedules
    ");
    $schedule_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $db->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM waste_reports 
        GROUP BY status
    ");
    $report_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // جلب الآليات المتاحة
    $stmt = $db->query("SELECT id, name, license_plate FROM vehicles WHERE status = 'جاهز' AND (department = 'النظافة' OR department IS NULL) ORDER BY name");
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $schedules = [];
    $reports = [];
    $schedule_stats = ['total_schedules' => 0, 'active_schedules' => 0];
    $report_stats = [];
    $vehicles = [];
}

$days = ['السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'];
$schedule_types = ['النفايات المنزلية', 'النفايات التجارية', 'النفايات الطبية', 'المخلفات الإنشائية'];
$report_types = ['تجمع نفايات', 'حاوية معطلة', 'رائحة كريهة', 'نظافة شوارع', 'أخرى'];
$priorities = ['منخفضة', 'متوسطة', 'عالية', 'عاجلة'];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة النفايات - بلدية تكريت</title>
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
                <h1 class="text-3xl font-bold text-slate-800">إدارة النفايات والنظافة</h1>
                <div class="flex gap-3">
                    <button onclick="openModal('addScheduleModal')" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        🗓️ جدولة جديدة
                    </button>
                    <button onclick="openModal('addReportModal')" class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition">
                        📝 تقرير نظافة
                    </button>
                    <a href="../comprehensive_dashboard.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                        ← العودة للوحة التحكم
                    </a>
                </div>
            </div>
            <p class="text-slate-600 mt-2">إدارة جداول جمع النفايات ومتابعة تقارير النظافة</p>
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

        <!-- إحصائيات النظافة -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">جداول الجمع</p>
                        <p class="text-2xl font-bold text-blue-600"><?= $schedule_stats['total_schedules'] ?></p>
                    </div>
                    <div class="bg-blue-100 text-blue-600 p-3 rounded-full">🗓️</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">الجداول النشطة</p>
                        <p class="text-2xl font-bold text-green-600"><?= $schedule_stats['active_schedules'] ?></p>
                    </div>
                    <div class="bg-green-100 text-green-600 p-3 rounded-full">✅</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">تقارير مفتوحة</p>
                        <p class="text-2xl font-bold text-orange-600"><?= $report_stats['مفتوح'] ?? 0 ?></p>
                    </div>
                    <div class="bg-orange-100 text-orange-600 p-3 rounded-full">📋</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">تقارير منجزة</p>
                        <p class="text-2xl font-bold text-purple-600"><?= $report_stats['منجز'] ?? 0 ?></p>
                    </div>
                    <div class="bg-purple-100 text-purple-600 p-3 rounded-full">✔️</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- جدول اليوم -->
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h3 class="font-semibold mb-4">جدول اليوم (<?= date('l') ?>)</h3>
                <div class="space-y-3">
                    <?php 
                    $today = date('l');
                    $today_schedules = array_filter($schedules, function($schedule) use ($today) {
                        return $schedule['collection_day'] === $today && $schedule['is_active'];
                    });
                    foreach (array_slice($today_schedules, 0, 5) as $schedule): 
                    ?>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="font-medium text-sm"><?= htmlspecialchars($schedule['route_name']) ?></p>
                            <p class="text-xs text-gray-600"><?= htmlspecialchars($schedule['area']) ?></p>
                            <p class="text-xs text-gray-500"><?= $schedule['start_time'] ?> - <?= $schedule['end_time'] ?></p>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($today_schedules)): ?>
                        <p class="text-gray-500 text-sm">لا توجد جداول لليوم</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- تقارير عاجلة -->
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h3 class="font-semibold mb-4">تقارير عاجلة</h3>
                <div class="space-y-3">
                    <?php 
                    $urgent_reports = array_filter($reports, function($report) {
                        return $report['priority'] === 'عاجلة' && $report['status'] === 'مفتوح';
                    });
                    foreach (array_slice($urgent_reports, 0, 5) as $report): 
                    ?>
                        <div class="bg-red-50 p-3 rounded border-l-4 border-red-400">
                            <p class="font-medium text-sm text-red-800"><?= htmlspecialchars($report['area']) ?></p>
                            <p class="text-xs text-red-600"><?= htmlspecialchars($report['report_type']) ?></p>
                            <p class="text-xs text-red-500"><?= date('Y-m-d H:i', strtotime($report['created_at'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($urgent_reports)): ?>
                        <p class="text-gray-500 text-sm">لا توجد تقارير عاجلة</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- فلاتر البحث -->
        <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
            <h3 class="font-semibold mb-4">فلترة جداول الجمع</h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">المنطقة</label>
                    <input type="text" name="area" value="<?= htmlspecialchars($filter_area) ?>"
                           placeholder="اسم المنطقة"
                           class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">يوم الجمع</label>
                    <select name="day" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">جميع الأيام</option>
                        <?php foreach ($days as $day): ?>
                            <option value="<?= $day ?>" <?= ($filter_day === $day) ? 'selected' : '' ?>><?= $day ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نوع النفايات</label>
                    <select name="type" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">جميع الأنواع</option>
                        <?php foreach ($schedule_types as $type): ?>
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

        <!-- جدول جداول الجمع -->
        <div class="bg-white rounded-lg shadow-sm mb-8">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold">جداول جمع النفايات</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th class="px-6 py-3">اسم الطريق</th>
                            <th class="px-6 py-3">المنطقة</th>
                            <th class="px-6 py-3">نوع النفايات</th>
                            <th class="px-6 py-3">يوم الجمع</th>
                            <th class="px-6 py-3">الوقت</th>
                            <th class="px-6 py-3">الفريق</th>
                            <th class="px-6 py-3">الآلية</th>
                            <th class="px-6 py-3">الحالة</th>
                            <th class="px-6 py-3">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $schedule): ?>
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium"><?= htmlspecialchars($schedule['route_name']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($schedule['area']) ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars($schedule['schedule_type']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4"><?= htmlspecialchars($schedule['collection_day']) ?></td>
                                <td class="px-6 py-4"><?= $schedule['start_time'] ?> - <?= $schedule['end_time'] ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($schedule['assigned_team'] ?? '-') ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($schedule['vehicle_name'] ?? 'غير محدد') ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded 
                                        <?= $schedule['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= $schedule['is_active'] ? 'نشط' : 'غير نشط' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex gap-2">
                                        <button onclick="viewSchedule(<?= $schedule['id'] ?>)" 
                                                class="bg-blue-100 text-blue-600 px-2 py-1 rounded text-xs hover:bg-blue-200">
                                            عرض
                                        </button>
                                        <button onclick="editSchedule(<?= $schedule['id'] ?>)" 
                                                class="bg-yellow-100 text-yellow-600 px-2 py-1 rounded text-xs hover:bg-yellow-200">
                                            تعديل
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($schedules)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-8 text-gray-500">
                                    لا توجد جداول مطابقة للفلتر المحدد
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- جدول تقارير النظافة -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold">تقارير النظافة</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th class="px-6 py-3">المنطقة</th>
                            <th class="px-6 py-3">نوع التقرير</th>
                            <th class="px-6 py-3">الوصف</th>
                            <th class="px-6 py-3">المبلغ</th>
                            <th class="px-6 py-3">الأولوية</th>
                            <th class="px-6 py-3">الحالة</th>
                            <th class="px-6 py-3">التاريخ</th>
                            <th class="px-6 py-3">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($reports, 0, 20) as $report): ?>
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium"><?= htmlspecialchars($report['area']) ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded bg-purple-100 text-purple-800">
                                        <?= htmlspecialchars($report['report_type']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="max-w-xs truncate" title="<?= htmlspecialchars($report['description']) ?>">
                                        <?= htmlspecialchars(substr($report['description'], 0, 50)) ?>...
                                    </div>
                                </td>
                                <td class="px-6 py-4"><?= htmlspecialchars($report['reporter_name'] ?? 'غير محدد') ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded 
                                        <?= $report['priority'] === 'عاجلة' ? 'bg-red-100 text-red-800' : 
                                           ($report['priority'] === 'عالية' ? 'bg-orange-100 text-orange-800' : 
                                           ($report['priority'] === 'متوسطة' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800')) ?>">
                                        <?= htmlspecialchars($report['priority']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded 
                                        <?= $report['status'] === 'مفتوح' ? 'bg-blue-100 text-blue-800' : 
                                           ($report['status'] === 'قيد المعالجة' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') ?>">
                                        <?= htmlspecialchars($report['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4"><?= date('Y-m-d', strtotime($report['created_at'])) ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex gap-2">
                                        <button onclick="viewReport(<?= $report['id'] ?>)" 
                                                class="bg-blue-100 text-blue-600 px-2 py-1 rounded text-xs hover:bg-blue-200">
                                            عرض
                                        </button>
                                        <button onclick="updateReport(<?= $report['id'] ?>)" 
                                                class="bg-green-100 text-green-600 px-2 py-1 rounded text-xs hover:bg-green-200">
                                            تحديث
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($reports)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-8 text-gray-500">
                                    لا توجد تقارير نظافة
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal إضافة جدولة جديدة -->
    <div id="addScheduleModal" class="modal fixed inset-0 bg-black bg-opacity-50 justify-center items-center z-50">
        <div class="bg-white p-6 rounded-lg max-w-3xl w-full mx-4 max-h-96 overflow-y-auto">
            <h3 class="text-xl font-semibold mb-4">إضافة جدولة جمع نفايات</h3>
            
            <form method="POST" class="space-y-4">
                <?php echo csrf_input('csrf_token'); ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">اسم الطريق *</label>
                        <input type="text" name="route_name" required 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">المنطقة *</label>
                        <input type="text" name="area" required 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نوع النفايات</label>
                        <select name="schedule_type" class="w-full p-2 border border-gray-300 rounded-md">
                            <?php foreach ($schedule_types as $type): ?>
                                <option value="<?= $type ?>"><?= $type ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">يوم الجمع</label>
                        <select name="collection_day" class="w-full p-2 border border-gray-300 rounded-md">
                            <?php foreach ($days as $day): ?>
                                <option value="<?= $day ?>"><?= $day ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">وقت البداية</label>
                        <input type="time" name="start_time" value="06:00"
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">وقت الانتهاء</label>
                        <input type="time" name="end_time" value="18:00"
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الفريق المخصص</label>
                        <input type="text" name="assigned_team" 
                               placeholder="اسم فريق النظافة"
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الآلية</label>
                        <select name="vehicle_id" class="w-full p-2 border border-gray-300 rounded-md">
                            <option value="">اختر الآلية</option>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <option value="<?= $vehicle['id'] ?>"><?= htmlspecialchars($vehicle['name']) ?> - <?= htmlspecialchars($vehicle['license_plate']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label>
                    <textarea name="notes" rows="3"
                              class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" name="is_active" checked class="mr-2">
                    <label class="text-sm text-gray-700">نشط</label>
                </div>
                
                <div class="flex gap-4 pt-4">
                    <button type="submit" name="add_schedule" 
                            class="flex-1 bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition">
                        إضافة الجدولة
                    </button>
                    <button type="button" onclick="closeModal('addScheduleModal')" 
                            class="px-6 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition">
                        إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal إضافة تقرير نظافة -->
    <div id="addReportModal" class="modal fixed inset-0 bg-black bg-opacity-50 justify-center items-center z-50">
        <div class="bg-white p-6 rounded-lg max-w-2xl w-full mx-4 max-h-96 overflow-y-auto">
            <h3 class="text-xl font-semibold mb-4">إضافة تقرير نظافة</h3>
            
            <form method="POST" class="space-y-4">
                <?php echo csrf_input('csrf_token'); ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">المنطقة *</label>
                        <input type="text" name="area" required 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نوع التقرير</label>
                        <select name="report_type" class="w-full p-2 border border-gray-300 rounded-md">
                            <?php foreach ($report_types as $type): ?>
                                <option value="<?= $type ?>"><?= $type ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">وصف المشكلة *</label>
                    <textarea name="description" rows="3" required
                              class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">اسم المبلغ</label>
                        <input type="text" name="reporter_name" 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                        <input type="tel" name="reporter_phone" 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">مستوى الأولوية</label>
                        <select name="priority" class="w-full p-2 border border-gray-300 rounded-md">
                            <?php foreach ($priorities as $priority): ?>
                                <option value="<?= $priority ?>"><?= $priority ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">تفاصيل الموقع</label>
                        <input type="text" name="location_details" 
                               placeholder="معلومات إضافية عن الموقع"
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <div class="flex gap-4 pt-4">
                    <button type="submit" name="add_report" 
                            class="flex-1 bg-orange-600 text-white py-2 px-4 rounded-md hover:bg-orange-700 transition">
                        إضافة التقرير
                    </button>
                    <button type="button" onclick="closeModal('addReportModal')" 
                            class="px-6 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition">
                        إلغاء
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
        
        function viewSchedule(id) {
            alert('عرض تفاصيل الجدولة #' + id);
        }
        
        function editSchedule(id) {
            alert('تعديل الجدولة #' + id);
        }
        
        function viewReport(id) {
            alert('عرض تفاصيل التقرير #' + id);
        }
        
        function updateReport(id) {
            alert('تحديث حالة التقرير #' + id);
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
