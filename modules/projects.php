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

// معالجة إضافة مشروع جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $project_type = $_POST['project_type'];
    $budget = floatval($_POST['budget']);
    $budget_currency_id = intval($_POST['budget_currency_id']);
    $start_date = $_POST['start_date'];
    $expected_end_date = $_POST['expected_end_date'];
    $location = trim($_POST['location']);
    $contractor = trim($_POST['contractor']);
    $donor_name = trim($_POST['donor_name']);
    $donor_type = $_POST['donor_type'];
    $donor_contact = trim($_POST['donor_contact']);
    $funding_type = $_POST['funding_type'];
    
    if (!empty($name) && $budget > 0) {
        try {
            $query = "INSERT INTO projects (project_name, description, project_type, budget, budget_currency_id, start_date, end_date, location, contractor, donor_name, donor_type, donor_contact, funding_type, manager_id, status, progress_percentage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'مخطط', 0)";
            $stmt = $db->prepare($query);
            $stmt->execute([$name, $description, $project_type, $budget, $budget_currency_id, $start_date, $expected_end_date, $location, $contractor, $donor_name, $donor_type, $donor_contact, $funding_type, $user['id']]);
            $message = 'تم إضافة المشروع بنجاح!';
        } catch (PDOException $e) {
            $error = 'خطأ في إضافة المشروع: ' . $e->getMessage();
        }
    } else {
        $error = 'يرجى تعبئة الحقول المطلوبة';
    }
}

// معالجة تحديث تقدم المشروع
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_progress'])) {
    $project_id = intval($_POST['project_id']);
    $progress_percentage = intval($_POST['progress_percentage']);
    $status = $_POST['status'];
    $actual_cost = floatval($_POST['actual_cost']);
    $actual_cost_currency_id = intval($_POST['actual_cost_currency_id']);
    $actual_end_date = !empty($_POST['actual_end_date']) ? $_POST['actual_end_date'] : null;
    
    try {
        $query = "UPDATE projects SET progress_percentage = ?, status = ?, actual_cost = ?, actual_cost_currency_id = ?, end_date = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$progress_percentage, $status, $actual_cost, $actual_cost_currency_id, $actual_end_date, $project_id]);
        $message = 'تم تحديث تقدم المشروع بنجاح!';
    } catch (PDOException $e) {
        $error = 'خطأ في تحديث المشروع: ' . $e->getMessage();
    }
}

// جلب المشاريع
try {
    $filter_status = $_GET['status'] ?? '';
    $filter_type = $_GET['type'] ?? '';
    
    $where_conditions = [];
    $params = [];
    
    if (!empty($filter_status)) {
        $where_conditions[] = "p.status = ?";
        $params[] = $filter_status;
    }
    
    if (!empty($filter_type)) {
        $where_conditions[] = "p.project_type = ?";
        $params[] = $filter_type;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $stmt = $db->prepare("
        SELECT p.*, 
               u.full_name as manager_name,
               bc.currency_symbol as budget_currency_symbol,
               bc.currency_code as budget_currency_code,
               acc.currency_symbol as actual_cost_currency_symbol,
               acc.currency_code as actual_cost_currency_code
        FROM projects p 
        LEFT JOIN users u ON p.manager_id = u.id 
        LEFT JOIN currencies bc ON p.budget_currency_id = bc.id
        LEFT JOIN currencies acc ON p.actual_cost_currency_id = acc.id
        $where_clause
        ORDER BY p.created_at DESC 
        LIMIT 50
    ");
    $stmt->execute($params);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب إحصائيات المشاريع
    $stmt = $db->query("
        SELECT 
            status,
            COUNT(*) as count,
            SUM(budget * bc.exchange_rate_to_iqd) as total_budget_iqd,
            SUM(actual_cost * acc.exchange_rate_to_iqd) as total_spent_iqd
        FROM projects p
        LEFT JOIN currencies bc ON p.budget_currency_id = bc.id
        LEFT JOIN currencies acc ON p.actual_cost_currency_id = acc.id
        GROUP BY status
    ");
    $project_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات عامة
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_projects,
            SUM(budget * bc.exchange_rate_to_iqd) as total_budget_iqd,
            SUM(actual_cost * acc.exchange_rate_to_iqd) as total_spent_iqd,
            AVG(progress_percentage) as avg_progress
        FROM projects p
        LEFT JOIN currencies bc ON p.budget_currency_id = bc.id
        LEFT JOIN currencies acc ON p.actual_cost_currency_id = acc.id
    ");
    $general_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // جلب العملات
    $stmt = $db->query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY currency_code");
    $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $projects = [];
    $project_stats = [];
    $general_stats = ['total_projects' => 0, 'total_budget_iqd' => 0, 'total_spent_iqd' => 0, 'avg_progress' => 0];
    $currencies = [];
}

$project_types = ['تطوير', 'إنشاءات', 'صيانة', 'بنية تحتية', 'خدمات', 'بيئة'];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المشاريع - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .modal { display: none; }
        .modal.active { display: flex; }
        .progress-bar {
            background: linear-gradient(90deg, #10B981 0%, #3B82F6 100%);
        }
    </style>
</head>
<body class="bg-slate-100">
    <div class="min-h-screen p-6">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-slate-800">إدارة المشاريع</h1>
                <div class="flex gap-3">
                    <button onclick="openModal('addProjectModal')" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        ➕ مشروع جديد
                    </button>
                    <a href="../comprehensive_dashboard.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                        ← العودة للوحة التحكم
                    </a>
                </div>
            </div>
            <p class="text-slate-600 mt-2">متابعة وإدارة مشاريع البلدية والبنية التحتية</p>
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

        <!-- إحصائيات المشاريع -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">إجمالي المشاريع</p>
                        <p class="text-2xl font-bold text-blue-600"><?= $general_stats['total_projects'] ?></p>
                    </div>
                    <div class="bg-blue-100 text-blue-600 p-3 rounded-full">🏗️</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">إجمالي الميزانية</p>
                        <p class="text-2xl font-bold text-green-600"><?= number_format($general_stats['total_budget_iqd']) ?> ل.ل</p>
                    </div>
                    <div class="bg-green-100 text-green-600 p-3 rounded-full">💰</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">المبلغ المنفق</p>
                        <p class="text-2xl font-bold text-red-600"><?= number_format($general_stats['total_spent_iqd']) ?> ل.ل</p>
                    </div>
                    <div class="bg-red-100 text-red-600 p-3 rounded-full">📊</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">متوسط التقدم</p>
                        <p class="text-2xl font-bold text-purple-600"><?= round($general_stats['avg_progress']) ?>%</p>
                    </div>
                    <div class="bg-purple-100 text-purple-600 p-3 rounded-full">⚡</div>
                </div>
            </div>
        </div>

        <!-- فلاتر البحث -->
        <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
            <h3 class="font-semibold mb-4">فلترة المشاريع</h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الحالة</label>
                    <select name="status" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">جميع الحالات</option>
                        <option value="مخطط" <?= ($filter_status === 'مخطط') ? 'selected' : '' ?>>مخطط</option>
                        <option value="قيد التنفيذ" <?= ($filter_status === 'قيد التنفيذ') ? 'selected' : '' ?>>قيد التنفيذ</option>
                        <option value="مكتمل" <?= ($filter_status === 'مكتمل') ? 'selected' : '' ?>>مكتمل</option>
                        <option value="متوقف" <?= ($filter_status === 'متوقف') ? 'selected' : '' ?>>متوقف</option>
                        <option value="ملغي" <?= ($filter_status === 'ملغي') ? 'selected' : '' ?>>ملغي</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نوع المشروع</label>
                    <select name="type" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">جميع الأنواع</option>
                        <?php foreach ($project_types as $type): ?>
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

        <!-- جدول المشاريع -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold">قائمة المشاريع</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th class="px-6 py-3">الرقم</th>
                            <th class="px-6 py-3">اسم المشروع</th>
                            <th class="px-6 py-3">النوع</th>
                            <th class="px-6 py-3">الميزانية</th>
                            <th class="px-6 py-3">المنفق</th>
                            <th class="px-6 py-3">الجهة المانحة</th>
                            <th class="px-6 py-3">التقدم</th>
                            <th class="px-6 py-3">الحالة</th>
                            <th class="px-6 py-3">المدير</th>
                            <th class="px-6 py-3">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $project): ?>
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium">#<?= $project['id'] ?></td>
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium"><?= htmlspecialchars($project['project_name']) ?></p>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($project['location'] ?? '') ?></p>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars($project['project_type']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-semibold text-green-600">
                                    <?= number_format($project['budget']) ?> <?= htmlspecialchars($project['budget_currency_symbol']) ?>
                                </td>
                                <td class="px-6 py-4 font-semibold text-red-600">
                                    <?= number_format($project['actual_cost']) ?> <?= htmlspecialchars($project['actual_cost_currency_symbol']) ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($project['donor_name'])): ?>
                                        <div>
                                            <p class="font-medium text-xs"><?= htmlspecialchars($project['donor_name']) ?></p>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($project['donor_type']) ?></p>
                                            <span class="px-1 py-0.5 text-xs rounded bg-gray-100 text-gray-700">
                                                <?= htmlspecialchars($project['funding_type']) ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-500 text-xs">غير محدد</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                        <div class="progress-bar h-2.5 rounded-full" style="width: <?= $project['progress_percentage'] ?>%"></div>
                                    </div>
                                    <span class="text-xs text-gray-600"><?= $project['progress_percentage'] ?>%</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded 
                                        <?= $project['status'] === 'مخطط' ? 'bg-gray-100 text-gray-800' : 
                                           ($project['status'] === 'قيد التنفيذ' ? 'bg-yellow-100 text-yellow-800' : 
                                           ($project['status'] === 'مكتمل' ? 'bg-green-100 text-green-800' : 
                                           ($project['status'] === 'متوقف' ? 'bg-orange-100 text-orange-800' : 'bg-red-100 text-red-800'))) ?>">
                                        <?= htmlspecialchars($project['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4"><?= htmlspecialchars($project['manager_name'] ?? 'غير محدد') ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex gap-2">
                                        <button onclick="viewProject(<?= $project['id'] ?>)" 
                                                class="bg-blue-100 text-blue-600 px-2 py-1 rounded text-xs hover:bg-blue-200">
                                            عرض
                                        </button>
                                        <button onclick="updateProgress(<?= $project['id'] ?>)" 
                                                class="bg-yellow-100 text-yellow-600 px-2 py-1 rounded text-xs hover:bg-yellow-200">
                                            تحديث
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($projects)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-8 text-gray-500">
                                    لا توجد مشاريع مطابقة للفلتر المحدد
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal إضافة مشروع جديد -->
    <div id="addProjectModal" class="modal fixed inset-0 bg-black bg-opacity-50 justify-center items-center z-50">
        <div class="bg-white p-6 rounded-lg max-w-3xl w-full mx-4 max-h-96 overflow-y-auto">
            <h3 class="text-xl font-semibold mb-4">إضافة مشروع جديد</h3>
            
            <form method="POST" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">اسم المشروع *</label>
                        <input type="text" name="name" required 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نوع المشروع *</label>
                        <select name="project_type" required 
                                class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">اختر النوع</option>
                            <?php foreach ($project_types as $type): ?>
                                <option value="<?= $type ?>"><?= $type ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">وصف المشروع</label>
                    <textarea name="description" rows="3"
                              class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">الميزانية *</label>
                        <input type="number" step="0.01" min="0" name="budget" required 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">العملة</label>
                        <select name="budget_currency_id" class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <?php foreach ($currencies as $currency): ?>
                                <option value="<?= $currency['id'] ?>" <?= $currency['currency_code'] === 'IQD' ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الموقع</label>
                        <input type="text" name="location" 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">المقاول</label>
                        <input type="text" name="contractor" 
                               placeholder="اسم الشركة أو المقاول المنفذ"
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <!-- معلومات الجهة المانحة -->
                <div class="border-t pt-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3">معلومات الجهة المانحة</h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">اسم الجهة المانحة</label>
                            <input type="text" name="donor_name" 
                                   placeholder="اسم الشخص أو المؤسسة المانحة"
                                   class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">نوع الجهة المانحة</label>
                            <select name="donor_type" class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                <option value="حكومي">حكومي</option>
                                <option value="خاص">خاص</option>
                                <option value="منظمة دولية">منظمة دولية</option>
                                <option value="منظمة خيرية">منظمة خيرية</option>
                                <option value="أفراد">أفراد</option>
                                <option value="أخرى">أخرى</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">معلومات الاتصال</label>
                            <input type="text" name="donor_contact" 
                                   placeholder="رقم الهاتف أو الإيميل"
                                   class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">نوع التمويل</label>
                            <select name="funding_type" class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                <option value="كامل">تمويل كامل</option>
                                <option value="جزئي">تمويل جزئي</option>
                                <option value="مشترك">تمويل مشترك</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ البدء</label>
                        <input type="date" name="start_date" 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ الانتهاء المتوقع</label>
                        <input type="date" name="expected_end_date" 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <div class="flex gap-4 pt-4">
                    <button type="submit" name="add_project" 
                            class="flex-1 bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition">
                        إضافة المشروع
                    </button>
                    <button type="button" onclick="closeModal('addProjectModal')" 
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
        
        function viewProject(id) {
            // إضافة منطق عرض تفاصيل المشروع
            alert('عرض تفاصيل المشروع #' + id);
        }
        
        function updateProgress(id) {
            // إضافة منطق تحديث تقدم المشروع
            alert('تحديث تقدم المشروع #' + id);
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
