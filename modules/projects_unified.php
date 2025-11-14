<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES 'utf8mb4'");
$db->exec("SET CHARACTER SET utf8mb4");
header('Content-Type: text/html; charset=utf-8');

$user = $auth->getUserInfo();
$message = '';
$error = '';

// معالجة إضافة مشروع
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project'])) {
    try {
        $project_name = trim($_POST['project_name']);
        $description = trim($_POST['description']);
        $project_type = $_POST['project_type'];
        $project_goal = trim($_POST['project_goal']);
        $location = trim($_POST['location']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        
        // الميزانية
        $budget = floatval($_POST['budget']);
        $budget_currency_id = intval($_POST['budget_currency_id']);
        
        // المساهمات
        $allow_public_contributions = isset($_POST['allow_public_contributions']) ? 1 : 0;
        $contributions_target = floatval($_POST['contributions_target']);
        $contributions_currency_id = intval($_POST['contributions_currency_id']);
        
        // الجهة المنفذة
        $contractor = trim($_POST['contractor']);
        $association_id = !empty($_POST['association_id']) ? intval($_POST['association_id']) : null;
        
        // المستفيدين
        $beneficiaries_count = intval($_POST['beneficiaries_count']);
        $beneficiaries_description = trim($_POST['beneficiaries_description']);
        
        // الإعدادات
        $is_public = isset($_POST['is_public']) ? 1 : 0;
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $status = $_POST['status'];
        $priority = $_POST['priority'];
        
        $notes = trim($_POST['notes']);
        
        $stmt = $db->prepare("INSERT INTO projects 
            (project_name, description, project_type, project_goal, location, start_date, end_date,
             budget, budget_currency_id, allow_public_contributions, contributions_target, contributions_currency_id,
             contractor, association_id, beneficiaries_count, beneficiaries_description,
             is_public, is_featured, status, priority, notes, manager_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([$project_name, $description, $project_type, $project_goal, $location, $start_date, $end_date,
                       $budget, $budget_currency_id, $allow_public_contributions, $contributions_target, $contributions_currency_id,
                       $contractor, $association_id, $beneficiaries_count, $beneficiaries_description,
                       $is_public, $is_featured, $status, $priority, $notes, $user['id']]);
        
        $message = 'تم إضافة المشروع بنجاح!';
    } catch (PDOException $e) {
        $error = 'خطأ في إضافة المشروع: ' . $e->getMessage();
    }
}

// معالجة تعديل مشروع
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_project'])) {
    try {
        $project_id = intval($_POST['project_id']);
        $project_name = trim($_POST['project_name']);
        $description = trim($_POST['description']);
        $project_type = $_POST['project_type'];
        $project_goal = trim($_POST['project_goal']);
        $location = trim($_POST['location']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        
        $budget = floatval($_POST['budget']);
        $budget_currency_id = intval($_POST['budget_currency_id']);
        
        $allow_public_contributions = isset($_POST['allow_public_contributions']) ? 1 : 0;
        $contributions_target = floatval($_POST['contributions_target']);
        $contributions_currency_id = intval($_POST['contributions_currency_id']);
        
        $contractor = trim($_POST['contractor']);
        $association_id = !empty($_POST['association_id']) ? intval($_POST['association_id']) : null;
        
        $beneficiaries_count = intval($_POST['beneficiaries_count']);
        $beneficiaries_description = trim($_POST['beneficiaries_description']);
        
        $is_public = isset($_POST['is_public']) ? 1 : 0;
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $status = $_POST['status'];
        $priority = $_POST['priority'];
        $progress_percentage = floatval($_POST['progress_percentage']);
        
        $notes = trim($_POST['notes']);
        
        $stmt = $db->prepare("UPDATE projects SET
            project_name = ?, description = ?, project_type = ?, project_goal = ?, location = ?,
            start_date = ?, end_date = ?, budget = ?, budget_currency_id = ?,
            allow_public_contributions = ?, contributions_target = ?, contributions_currency_id = ?,
            contractor = ?, association_id = ?, beneficiaries_count = ?, beneficiaries_description = ?,
            is_public = ?, is_featured = ?, status = ?, priority = ?, progress_percentage = ?, notes = ?
            WHERE id = ?");
        
        $stmt->execute([$project_name, $description, $project_type, $project_goal, $location,
                       $start_date, $end_date, $budget, $budget_currency_id,
                       $allow_public_contributions, $contributions_target, $contributions_currency_id,
                       $contractor, $association_id, $beneficiaries_count, $beneficiaries_description,
                       $is_public, $is_featured, $status, $priority, $progress_percentage, $notes, $project_id]);
        
        $message = 'تم تحديث المشروع بنجاح!';
    } catch (PDOException $e) {
        $error = 'خطأ في تحديث المشروع: ' . $e->getMessage();
    }
}

// معالجة حذف مشروع
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_project'])) {
    try {
        $project_id = intval($_POST['project_id']);
        
        // التحقق من عدم وجود مساهمات
        $stmt = $db->prepare("SELECT COUNT(*) FROM project_contributions WHERE project_id = ?");
        $stmt->execute([$project_id]);
        $contributions_count = $stmt->fetchColumn();
        
        if ($contributions_count > 0) {
            $error = 'لا يمكن حذف المشروع لأنه يحتوي على ' . $contributions_count . ' مساهمة. يرجى حذف المساهمات أولاً أو تغيير حالة المشروع إلى "ملغي".';
        } else {
            $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$project_id]);
            $message = 'تم حذف المشروع بنجاح!';
        }
    } catch (PDOException $e) {
        $error = 'خطأ في حذف المشروع: ' . $e->getMessage();
    }
}

// الفلاتر
$filter_type = $_GET['type'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_public = $_GET['public'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($filter_type)) {
    $where_conditions[] = "project_type = ?";
    $params[] = $filter_type;
}

if (!empty($filter_status)) {
    $where_conditions[] = "status = ?";
    $params[] = $filter_status;
}

if ($filter_public !== '') {
    $where_conditions[] = "is_public = ?";
    $params[] = $filter_public;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// جلب المشاريع
$stmt = $db->prepare("
    SELECT p.*,
           bc.currency_symbol as budget_currency,
           cc.currency_symbol as contributions_currency,
           a.name as association_name,
           (SELECT COUNT(*) FROM project_contributions WHERE project_id = p.id) as contributions_count
    FROM projects p
    LEFT JOIN currencies bc ON p.budget_currency_id = bc.id
    LEFT JOIN currencies cc ON p.contributions_currency_id = cc.id
    LEFT JOIN associations a ON p.association_id = a.id
    $where_clause
    ORDER BY p.created_at DESC
    LIMIT 100
");
$stmt->execute($params);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// إضافة حقل name للتوافق
foreach ($projects as &$project) {
    if (!isset($project['name'])) {
        $project['name'] = $project['project_name'];
    }
}
unset($project);

// الإحصائيات
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_projects,
        SUM(CASE WHEN status = 'قيد التنفيذ' THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN status = 'مكتمل' THEN 1 ELSE 0 END) as completed_count,
        SUM(CASE WHEN is_public = 1 THEN 1 ELSE 0 END) as public_count,
        SUM(CASE WHEN allow_public_contributions = 1 THEN 1 ELSE 0 END) as contributions_enabled_count
    FROM projects
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// جلب العملات
$stmt = $db->query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY currency_code");
$currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب الجمعيات
$stmt = $db->query("SELECT * FROM associations ORDER BY name");
$associations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المشاريع الموحدة - بلدية تكريت</title>
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
                    <h1 class="text-3xl font-bold text-gray-800">🏗️ إدارة المشاريع الموحدة</h1>
                    <p class="text-gray-600 mt-2">إدارة شاملة للمشاريع الداخلية والإنمائية مع دعم المساهمات</p>
                </div>
                <div class="flex gap-3">
                    <button onclick="openModal('addProjectModal')" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition shadow-lg">
                        ➕ إضافة مشروع جديد
                    </button>
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
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
            <div class="bg-white p-6 rounded-lg shadow-sm border-r-4 border-blue-500">
                <p class="text-sm text-gray-500">إجمالي المشاريع</p>
                <p class="text-3xl font-bold text-blue-600"><?= number_format($stats['total_projects']) ?></p>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm border-r-4 border-yellow-500">
                <p class="text-sm text-gray-500">قيد التنفيذ</p>
                <p class="text-3xl font-bold text-yellow-600"><?= number_format($stats['active_count']) ?></p>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm border-r-4 border-green-500">
                <p class="text-sm text-gray-500">مكتمل</p>
                <p class="text-3xl font-bold text-green-600"><?= number_format($stats['completed_count']) ?></p>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm border-r-4 border-purple-500">
                <p class="text-sm text-gray-500">مشاريع عامة</p>
                <p class="text-3xl font-bold text-purple-600"><?= number_format($stats['public_count']) ?></p>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm border-r-4 border-indigo-500">
                <p class="text-sm text-gray-500">تقبل مساهمات</p>
                <p class="text-3xl font-bold text-indigo-600"><?= number_format($stats['contributions_enabled_count']) ?></p>
            </div>
        </div>

        <!-- فلاتر -->
        <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
            <h3 class="font-semibold mb-4 text-lg">🔍 البحث والفلترة</h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-2">نوع المشروع</label>
                    <select name="type" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">جميع الأنواع</option>
                        <option value="إنمائي" <?= ($filter_type === 'إنمائي') ? 'selected' : '' ?>>إنمائي</option>
                        <option value="خدمي" <?= ($filter_type === 'خدمي') ? 'selected' : '' ?>>خدمي</option>
                        <option value="بنية تحتية" <?= ($filter_type === 'بنية تحتية') ? 'selected' : '' ?>>بنية تحتية</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">الحالة</label>
                    <select name="status" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">جميع الحالات</option>
                        <option value="مخطط" <?= ($filter_status === 'مخطط') ? 'selected' : '' ?>>مخطط</option>
                        <option value="قيد التنفيذ" <?= ($filter_status === 'قيد التنفيذ') ? 'selected' : '' ?>>قيد التنفيذ</option>
                        <option value="مكتمل" <?= ($filter_status === 'مكتمل') ? 'selected' : '' ?>>مكتمل</option>
                        <option value="متوقف" <?= ($filter_status === 'متوقف') ? 'selected' : '' ?>>متوقف</option>
                        <option value="ملغي" <?= ($filter_status === 'ملغي') ? 'selected' : '' ?>>ملغي</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">العرض العام</label>
                    <select name="public" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">الكل</option>
                        <option value="1" <?= ($filter_public === '1') ? 'selected' : '' ?>>عام</option>
                        <option value="0" <?= ($filter_public === '0') ? 'selected' : '' ?>>داخلي</option>
                    </select>
                </div>
                
                <div class="flex items-end gap-2 md:col-span-2">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700">
                        بحث
                    </button>
                    <a href="projects_unified.php" class="bg-gray-500 text-white py-2 px-4 rounded-lg hover:bg-gray-600">
                        إعادة
                    </a>
                    <a href="contributions.php" class="bg-purple-600 text-white py-2 px-4 rounded-lg hover:bg-purple-700">
                        💰 المساهمات
                    </a>
                </div>
            </form>
        </div>

        <!-- جدول المشاريع -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="text-right p-4 font-semibold">اسم المشروع</th>
                            <th class="text-right p-4 font-semibold">النوع</th>
                            <th class="text-right p-4 font-semibold">الموقع</th>
                            <th class="text-right p-4 font-semibold">الميزانية</th>
                            <th class="text-right p-4 font-semibold">المساهمات</th>
                            <th class="text-right p-4 font-semibold">الحالة</th>
                            <th class="text-right p-4 font-semibold">الإعدادات</th>
                            <th class="text-right p-4 font-semibold">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php if (empty($projects)): ?>
                        <tr>
                            <td colspan="8" class="p-8 text-center text-gray-500">
                                📭 لا توجد مشاريع
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($projects as $project): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="p-4">
                                    <div class="font-semibold text-blue-600"><?= htmlspecialchars($project['project_name']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars(substr($project['description'], 0, 50)) ?>...</div>
                                </td>
                                <td class="p-4">
                                    <span class="px-2 py-1 rounded text-xs font-semibold bg-purple-100 text-purple-800">
                                        <?= htmlspecialchars($project['project_type']) ?>
                                    </span>
                                </td>
                                <td class="p-4 text-sm"><?= htmlspecialchars($project['location']) ?></td>
                                <td class="p-4">
                                    <div class="font-semibold"><?= number_format($project['budget'], 0) ?> <?= htmlspecialchars($project['budget_currency']) ?></div>
                                </td>
                                <td class="p-4">
                                    <?php if ($project['allow_public_contributions']): ?>
                                        <div class="text-xs">
                                            <div class="font-semibold text-green-600"><?= number_format($project['contributions_collected'], 0) ?> <?= htmlspecialchars($project['contributions_currency']) ?></div>
                                            <div class="text-gray-500">من <?= number_format($project['contributions_target'], 0) ?></div>
                                            <div class="text-gray-500">(<?= $project['contributions_count'] ?> مساهم)</div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">لا يقبل</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4">
                                    <?php
                                    $statusColors = [
                                        'مخطط' => 'bg-gray-100 text-gray-800',
                                        'قيد التنفيذ' => 'bg-yellow-100 text-yellow-800',
                                        'مكتمل' => 'bg-green-100 text-green-800',
                                        'متوقف' => 'bg-red-100 text-red-800',
                                        'ملغي' => 'bg-gray-100 text-gray-600'
                                    ];
                                    $statusClass = $statusColors[$project['status']] ?? 'bg-gray-100';
                                    ?>
                                    <span class="px-2 py-1 rounded text-xs font-semibold <?= $statusClass ?>">
                                        <?= htmlspecialchars($project['status']) ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <div class="flex gap-1">
                                        <?php if ($project['is_public']): ?>
                                            <span class="px-2 py-1 rounded text-xs bg-blue-100 text-blue-800" title="عام">🌐</span>
                                        <?php endif; ?>
                                        <?php if ($project['is_featured']): ?>
                                            <span class="px-2 py-1 rounded text-xs bg-yellow-100 text-yellow-800" title="مميز">⭐</span>
                                        <?php endif; ?>
                                        <?php if ($project['allow_public_contributions']): ?>
                                            <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800" title="يقبل مساهمات">💰</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div class="flex gap-2">
                                        <button onclick="viewProject(<?= $project['id'] ?>)" 
                                                class="text-blue-600 hover:text-blue-800 text-sm" title="عرض">
                                            👁️
                                        </button>
                                        <button onclick="editProject(<?= $project['id'] ?>)" 
                                                class="text-indigo-600 hover:text-indigo-800 text-sm" title="تعديل">
                                            ✏️
                                        </button>
                                        <a href="projects_finance.php?project_id=<?= $project['id'] ?>" 
                                           class="text-green-600 hover:text-green-800 text-sm" title="التتبع المالي">
                                            💵
                                        </a>
                                        <?php if ($project['allow_public_contributions']): ?>
                                        <a href="contributions.php?project_id=<?= $project['id'] ?>" 
                                           class="text-purple-600 hover:text-purple-800 text-sm" title="المساهمات">
                                            💰
                                        </a>
                                        <?php endif; ?>
                                        <button onclick="deleteProject(<?= $project['id'] ?>, '<?= htmlspecialchars($project['project_name'], ENT_QUOTES) ?>')" 
                                                class="text-red-600 hover:text-red-800 text-sm" title="حذف">
                                            🗑️
                                        </button>
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

    <!-- Modal إضافة مشروع -->
    <div id="addProjectModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="bg-green-600 text-white px-6 py-4 rounded-t-lg sticky top-0">
                <h3 class="text-xl font-semibold">➕ إضافة مشروع جديد</h3>
            </div>
            
            <form method="POST" class="p-6 space-y-6">
                <!-- معلومات أساسية -->
                <div class="border-b pb-4">
                    <h4 class="font-semibold mb-4 text-gray-700">📋 المعلومات الأساسية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-2">اسم المشروع *</label>
                            <input type="text" name="project_name" required 
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-2">الوصف *</label>
                            <textarea name="description" required rows="3" 
                                      class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">نوع المشروع *</label>
                            <select name="project_type" required class="w-full px-4 py-2 border rounded-lg">
                                <option value="إنمائي">إنمائي</option>
                                <option value="خدمي">خدمي</option>
                                <option value="بنية تحتية">بنية تحتية</option>
                                <option value="صحي">صحي</option>
                                <option value="تعليمي">تعليمي</option>
                                <option value="ثقافي">ثقافي</option>
                                <option value="بيئي">بيئي</option>
                                <option value="أخرى">أخرى</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الموقع *</label>
                            <input type="text" name="location" required 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-2">هدف المشروع</label>
                            <textarea name="project_goal" rows="2" 
                                      class="w-full px-4 py-2 border rounded-lg"></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- الميزانية والعملة -->
                <div class="border-b pb-4">
                    <h4 class="font-semibold mb-4 text-gray-700">💰 الميزانية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">الميزانية *</label>
                            <input type="number" name="budget" required step="0.01" min="0" 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">العملة *</label>
                            <select name="budget_currency_id" required class="w-full px-4 py-2 border rounded-lg">
                                <?php foreach ($currencies as $currency): ?>
                                    <option value="<?= $currency['id'] ?>" <?= ($currency['is_default']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الجمعية المنفذة</label>
                            <select name="association_id" class="w-full px-4 py-2 border rounded-lg">
                                <option value="">بدون جمعية</option>
                                <?php foreach ($associations as $assoc): ?>
                                    <option value="<?= $assoc['id'] ?>"><?= htmlspecialchars($assoc['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- المساهمات -->
                <div class="border-b pb-4">
                    <h4 class="font-semibold mb-4 text-gray-700">💵 المساهمات الشعبية</h4>
                    <div class="mb-4">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="allow_public_contributions" class="w-4 h-4" onchange="toggleContributions(this)">
                            <span class="text-sm font-medium">السماح بالمساهمات الشعبية</span>
                        </label>
                    </div>
                    
                    <div id="contributionsFields" class="grid grid-cols-1 md:grid-cols-2 gap-4" style="display: none;">
                        <div>
                            <label class="block text-sm font-medium mb-2">هدف المساهمات</label>
                            <input type="number" name="contributions_target" step="0.01" min="0" value="0"
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">عملة المساهمات</label>
                            <select name="contributions_currency_id" class="w-full px-4 py-2 border rounded-lg">
                                <?php foreach ($currencies as $currency): ?>
                                    <option value="<?= $currency['id'] ?>" <?= ($currency['is_default']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- المستفيدين -->
                <div class="border-b pb-4">
                    <h4 class="font-semibold mb-4 text-gray-700">👥 المستفيدين</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">عدد المستفيدين</label>
                            <input type="number" name="beneficiaries_count" min="0" value="0"
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">المقاول / الجهة المنفذة</label>
                            <input type="text" name="contractor" 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-2">وصف المستفيدين</label>
                            <textarea name="beneficiaries_description" rows="2" 
                                      class="w-full px-4 py-2 border rounded-lg"></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- التواريخ والحالة -->
                <div class="border-b pb-4">
                    <h4 class="font-semibold mb-4 text-gray-700">📅 التواريخ والحالة</h4>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">تاريخ البدء</label>
                            <input type="date" name="start_date" 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">تاريخ الانتهاء المتوقع</label>
                            <input type="date" name="end_date" 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الحالة</label>
                            <select name="status" class="w-full px-4 py-2 border rounded-lg">
                                <option value="مخطط">مخطط</option>
                                <option value="قيد التنفيذ">قيد التنفيذ</option>
                                <option value="مكتمل">مكتمل</option>
                                <option value="متوقف">متوقف</option>
                                <option value="ملغي">ملغي</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الأولوية</label>
                            <select name="priority" class="w-full px-4 py-2 border rounded-lg">
                                <option value="عالية">عالية</option>
                                <option value="متوسطة" selected>متوسطة</option>
                                <option value="منخفضة">منخفضة</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- الإعدادات -->
                <div class="border-b pb-4">
                    <h4 class="font-semibold mb-4 text-gray-700">⚙️ الإعدادات</h4>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_public" class="w-4 h-4">
                            <span class="text-sm">عرض للعامة (يظهر في الموقع العام)</span>
                        </label>
                        
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_featured" class="w-4 h-4">
                            <span class="text-sm">مشروع مميز</span>
                        </label>
                    </div>
                </div>
                
                <!-- ملاحظات -->
                <div>
                    <label class="block text-sm font-medium mb-2">ملاحظات</label>
                    <textarea name="notes" rows="3" 
                              class="w-full px-4 py-2 border rounded-lg"></textarea>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="closeModal('addProjectModal')" 
                            class="px-6 py-2 text-gray-600 hover:text-gray-800 border rounded-lg">
                        إلغاء
                    </button>
                    <button type="submit" name="add_project" 
                            class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                        ✅ إضافة المشروع
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal عرض المشروع -->
    <div id="viewProjectModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="bg-blue-600 text-white px-6 py-4 rounded-t-lg sticky top-0">
                <h3 class="text-xl font-semibold">👁️ عرض تفاصيل المشروع</h3>
            </div>
            
            <div id="viewProjectContent" class="p-6">
                <!-- سيتم ملؤه بالـ JavaScript -->
            </div>
            
            <div class="flex justify-end gap-3 p-6 border-t">
                <button onclick="closeModal('viewProjectModal')" 
                        class="px-6 py-2 text-gray-600 hover:text-gray-800 border rounded-lg">
                    إغلاق
                </button>
            </div>
        </div>
    </div>

    <!-- Modal تعديل المشروع -->
    <div id="editProjectModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="bg-indigo-600 text-white px-6 py-4 rounded-t-lg sticky top-0">
                <h3 class="text-xl font-semibold">✏️ تعديل المشروع</h3>
            </div>
            
            <form method="POST" class="p-6 space-y-6" id="editProjectForm">
                <input type="hidden" name="project_id" id="edit_project_id">
                
                <!-- معلومات أساسية -->
                <div class="border-b pb-4">
                    <h4 class="font-semibold mb-4 text-gray-700">📋 المعلومات الأساسية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-2">اسم المشروع *</label>
                            <input type="text" name="project_name" id="edit_project_name" required 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-2">الوصف *</label>
                            <textarea name="description" id="edit_description" required rows="3" 
                                      class="w-full px-4 py-2 border rounded-lg"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">نوع المشروع *</label>
                            <select name="project_type" id="edit_project_type" required class="w-full px-4 py-2 border rounded-lg">
                                <option value="إنمائي">إنمائي</option>
                                <option value="خدمي">خدمي</option>
                                <option value="بنية تحتية">بنية تحتية</option>
                                <option value="صحي">صحي</option>
                                <option value="تعليمي">تعليمي</option>
                                <option value="ثقافي">ثقافي</option>
                                <option value="بيئي">بيئي</option>
                                <option value="أخرى">أخرى</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الموقع *</label>
                            <input type="text" name="location" id="edit_location" required 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-2">هدف المشروع</label>
                            <textarea name="project_goal" id="edit_project_goal" rows="2" 
                                      class="w-full px-4 py-2 border rounded-lg"></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- الميزانية -->
                <div class="border-b pb-4">
                    <h4 class="font-semibold mb-4 text-gray-700">💰 الميزانية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">الميزانية *</label>
                            <input type="number" name="budget" id="edit_budget" required step="0.01" min="0" 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">العملة *</label>
                            <select name="budget_currency_id" id="edit_budget_currency_id" required class="w-full px-4 py-2 border rounded-lg">
                                <?php foreach ($currencies as $currency): ?>
                                    <option value="<?= $currency['id'] ?>">
                                        <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الجمعية المنفذة</label>
                            <select name="association_id" id="edit_association_id" class="w-full px-4 py-2 border rounded-lg">
                                <option value="">بدون جمعية</option>
                                <?php foreach ($associations as $assoc): ?>
                                    <option value="<?= $assoc['id'] ?>"><?= htmlspecialchars($assoc['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- المساهمات -->
                <div class="border-b pb-4">
                    <h4 class="font-semibold mb-4 text-gray-700">💵 المساهمات الشعبية</h4>
                    <div class="mb-4">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="allow_public_contributions" id="edit_allow_public_contributions" 
                                   class="w-4 h-4" onchange="toggleEditContributions(this)">
                            <span class="text-sm font-medium">السماح بالمساهمات الشعبية</span>
                        </label>
                    </div>
                    
                    <div id="editContributionsFields" class="grid grid-cols-1 md:grid-cols-2 gap-4" style="display: none;">
                        <div>
                            <label class="block text-sm font-medium mb-2">هدف المساهمات</label>
                            <input type="number" name="contributions_target" id="edit_contributions_target" 
                                   step="0.01" min="0" value="0" class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">عملة المساهمات</label>
                            <select name="contributions_currency_id" id="edit_contributions_currency_id" class="w-full px-4 py-2 border rounded-lg">
                                <?php foreach ($currencies as $currency): ?>
                                    <option value="<?= $currency['id'] ?>">
                                        <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- المستفيدين -->
                <div class="border-b pb-4">
                    <h4 class="font-semibold mb-4 text-gray-700">👥 المستفيدين</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">عدد المستفيدين</label>
                            <input type="number" name="beneficiaries_count" id="edit_beneficiaries_count" 
                                   min="0" value="0" class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">المقاول / الجهة المنفذة</label>
                            <input type="text" name="contractor" id="edit_contractor" 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-2">وصف المستفيدين</label>
                            <textarea name="beneficiaries_description" id="edit_beneficiaries_description" rows="2" 
                                      class="w-full px-4 py-2 border rounded-lg"></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- التواريخ والحالة -->
                <div class="border-b pb-4">
                    <h4 class="font-semibold mb-4 text-gray-700">📅 التواريخ والحالة</h4>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">تاريخ البدء</label>
                            <input type="date" name="start_date" id="edit_start_date" 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">تاريخ الانتهاء المتوقع</label>
                            <input type="date" name="end_date" id="edit_end_date" 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الحالة</label>
                            <select name="status" id="edit_status" class="w-full px-4 py-2 border rounded-lg">
                                <option value="مخطط">مخطط</option>
                                <option value="قيد التنفيذ">قيد التنفيذ</option>
                                <option value="مكتمل">مكتمل</option>
                                <option value="متوقف">متوقف</option>
                                <option value="ملغي">ملغي</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">نسبة الإنجاز %</label>
                            <input type="number" name="progress_percentage" id="edit_progress_percentage" 
                                   min="0" max="100" step="0.01" value="0" class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-2">الأولوية</label>
                            <select name="priority" id="edit_priority" class="w-full px-4 py-2 border rounded-lg">
                                <option value="عالية">عالية</option>
                                <option value="متوسطة">متوسطة</option>
                                <option value="منخفضة">منخفضة</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- الإعدادات -->
                <div class="border-b pb-4">
                    <h4 class="font-semibold mb-4 text-gray-700">⚙️ الإعدادات</h4>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_public" id="edit_is_public" class="w-4 h-4">
                            <span class="text-sm">عرض للعامة (يظهر في الموقع العام)</span>
                        </label>
                        
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_featured" id="edit_is_featured" class="w-4 h-4">
                            <span class="text-sm">مشروع مميز</span>
                        </label>
                    </div>
                </div>
                
                <!-- ملاحظات -->
                <div>
                    <label class="block text-sm font-medium mb-2">ملاحظات</label>
                    <textarea name="notes" id="edit_notes" rows="3" 
                              class="w-full px-4 py-2 border rounded-lg"></textarea>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="closeModal('editProjectModal')" 
                            class="px-6 py-2 text-gray-600 hover:text-gray-800 border rounded-lg">
                        إلغاء
                    </button>
                    <button type="submit" name="edit_project" 
                            class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">
                        ✅ حفظ التعديلات
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal حذف المشروع -->
    <div id="deleteProjectModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg w-full max-w-md">
            <div class="bg-red-600 text-white px-6 py-4 rounded-t-lg">
                <h3 class="text-xl font-semibold">🗑️ تأكيد الحذف</h3>
            </div>
            
            <form method="POST" class="p-6">
                <input type="hidden" name="project_id" id="delete_project_id">
                
                <div class="text-center mb-6">
                    <div class="text-5xl mb-4">⚠️</div>
                    <p class="text-lg font-semibold text-gray-800 mb-2">هل أنت متأكد من حذف هذا المشروع؟</p>
                    <p class="text-gray-600 mb-4" id="delete_project_name"></p>
                    <p class="text-sm text-red-600">⚠️ هذه العملية لا يمكن التراجع عنها!</p>
                </div>
                
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeModal('deleteProjectModal')" 
                            class="px-6 py-2 text-gray-600 hover:text-gray-800 border rounded-lg">
                        إلغاء
                    </button>
                    <button type="submit" name="delete_project" 
                            class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700">
                        🗑️ نعم، احذف
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const projectsData = <?= json_encode($projects) ?>;
        
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function toggleContributions(checkbox) {
            const fields = document.getElementById('contributionsFields');
            fields.style.display = checkbox.checked ? 'grid' : 'none';
        }

        function toggleEditContributions(checkbox) {
            const fields = document.getElementById('editContributionsFields');
            fields.style.display = checkbox.checked ? 'grid' : 'none';
        }

        // عرض تفاصيل المشروع
        function viewProject(projectId) {
            const project = projectsData.find(p => p.id == projectId);
            if (!project) {
                alert('لم يتم العثور على المشروع');
                return;
            }
            
            const content = `
                <div class="space-y-6">
                    <div class="border-b pb-4">
                        <h4 class="font-bold text-xl text-indigo-600 mb-2">${project.project_name}</h4>
                        <p class="text-gray-600">${project.description || 'لا يوجد وصف'}</p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h5 class="font-semibold text-gray-700 mb-2">📋 معلومات أساسية</h5>
                            <div class="space-y-2 text-sm">
                                <p><strong>النوع:</strong> ${project.project_type}</p>
                                <p><strong>الموقع:</strong> ${project.location}</p>
                                <p><strong>الحالة:</strong> <span class="px-2 py-1 rounded bg-gray-100">${project.status}</span></p>
                                <p><strong>الأولوية:</strong> ${project.priority || 'متوسطة'}</p>
                                <p><strong>نسبة الإنجاز:</strong> ${project.progress_percentage || 0}%</p>
                            </div>
                        </div>
                        
                        <div>
                            <h5 class="font-semibold text-gray-700 mb-2">💰 الميزانية</h5>
                            <div class="space-y-2 text-sm">
                                <p><strong>الميزانية:</strong> ${parseFloat(project.budget).toLocaleString()} ${project.budget_currency || ''}</p>
                                <p><strong>المصروف:</strong> ${parseFloat(project.spent_amount || 0).toLocaleString()} ${project.budget_currency || ''}</p>
                                <p><strong>المقاول:</strong> ${project.contractor || 'غير محدد'}</p>
                                ${project.association_name ? `<p><strong>الجمعية:</strong> ${project.association_name}</p>` : ''}
                            </div>
                        </div>
                        
                        ${project.allow_public_contributions ? `
                        <div>
                            <h5 class="font-semibold text-gray-700 mb-2">💵 المساهمات</h5>
                            <div class="space-y-2 text-sm">
                                <p><strong>الهدف:</strong> ${parseFloat(project.contributions_target).toLocaleString()} ${project.contributions_currency || ''}</p>
                                <p><strong>المُجمّع:</strong> ${parseFloat(project.contributions_collected || 0).toLocaleString()} ${project.contributions_currency || ''}</p>
                                <p><strong>المتبقي:</strong> ${(parseFloat(project.contributions_target) - parseFloat(project.contributions_collected || 0)).toLocaleString()} ${project.contributions_currency || ''}</p>
                                <p><strong>عدد المساهمين:</strong> ${project.contributions_count || 0}</p>
                            </div>
                        </div>
                        ` : ''}
                        
                        <div>
                            <h5 class="font-semibold text-gray-700 mb-2">👥 المستفيدين</h5>
                            <div class="space-y-2 text-sm">
                                <p><strong>العدد:</strong> ${project.beneficiaries_count || 0}</p>
                                ${project.beneficiaries_description ? `<p><strong>الوصف:</strong> ${project.beneficiaries_description}</p>` : ''}
                            </div>
                        </div>
                        
                        <div>
                            <h5 class="font-semibold text-gray-700 mb-2">📅 التواريخ</h5>
                            <div class="space-y-2 text-sm">
                                <p><strong>البدء:</strong> ${project.start_date || 'غير محدد'}</p>
                                <p><strong>الانتهاء المتوقع:</strong> ${project.end_date || 'غير محدد'}</p>
                            </div>
                        </div>
                        
                        <div>
                            <h5 class="font-semibold text-gray-700 mb-2">⚙️ الإعدادات</h5>
                            <div class="space-y-2 text-sm">
                                <p>${project.is_public ? '✅' : '❌'} عام</p>
                                <p>${project.is_featured ? '✅' : '❌'} مميز</p>
                                <p>${project.allow_public_contributions ? '✅' : '❌'} يقبل مساهمات</p>
                            </div>
                        </div>
                    </div>
                    
                    ${project.project_goal ? `
                    <div class="border-t pt-4">
                        <h5 class="font-semibold text-gray-700 mb-2">🎯 هدف المشروع</h5>
                        <p class="text-gray-600 text-sm">${project.project_goal}</p>
                    </div>
                    ` : ''}
                    
                    ${project.notes ? `
                    <div class="border-t pt-4">
                        <h5 class="font-semibold text-gray-700 mb-2">📝 ملاحظات</h5>
                        <p class="text-gray-600 text-sm">${project.notes}</p>
                    </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('viewProjectContent').innerHTML = content;
            openModal('viewProjectModal');
        }

        // تعديل المشروع
        function editProject(projectId) {
            const project = projectsData.find(p => p.id == projectId);
            if (!project) {
                alert('لم يتم العثور على المشروع');
                return;
            }
            
            // ملء الحقول
            document.getElementById('edit_project_id').value = project.id;
            document.getElementById('edit_project_name').value = project.project_name;
            document.getElementById('edit_description').value = project.description || '';
            document.getElementById('edit_project_type').value = project.project_type;
            document.getElementById('edit_location').value = project.location;
            document.getElementById('edit_project_goal').value = project.project_goal || '';
            
            document.getElementById('edit_budget').value = project.budget;
            document.getElementById('edit_budget_currency_id').value = project.budget_currency_id;
            
            const allowContributions = project.allow_public_contributions == 1;
            document.getElementById('edit_allow_public_contributions').checked = allowContributions;
            document.getElementById('editContributionsFields').style.display = allowContributions ? 'grid' : 'none';
            document.getElementById('edit_contributions_target').value = project.contributions_target || 0;
            document.getElementById('edit_contributions_currency_id').value = project.contributions_currency_id;
            
            document.getElementById('edit_association_id').value = project.association_id || '';
            document.getElementById('edit_beneficiaries_count').value = project.beneficiaries_count || 0;
            document.getElementById('edit_contractor').value = project.contractor || '';
            document.getElementById('edit_beneficiaries_description').value = project.beneficiaries_description || '';
            
            document.getElementById('edit_start_date').value = project.start_date || '';
            document.getElementById('edit_end_date').value = project.end_date || '';
            document.getElementById('edit_status').value = project.status;
            document.getElementById('edit_progress_percentage').value = project.progress_percentage || 0;
            document.getElementById('edit_priority').value = project.priority || 'متوسطة';
            
            document.getElementById('edit_is_public').checked = project.is_public == 1;
            document.getElementById('edit_is_featured').checked = project.is_featured == 1;
            document.getElementById('edit_notes').value = project.notes || '';
            
            openModal('editProjectModal');
        }

        // حذف المشروع
        function deleteProject(projectId, projectName) {
            document.getElementById('delete_project_id').value = projectId;
            document.getElementById('delete_project_name').textContent = projectName;
            openModal('deleteProjectModal');
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

