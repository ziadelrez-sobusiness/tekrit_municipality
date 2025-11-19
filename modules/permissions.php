<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/auth_helper.php';

// التأكد من تسجيل الدخول
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

// التحقق من صلاحية إدارة الصلاحيات (معطل مؤقتاً للمدير)
// requirePermission('permissions_manage');

$database = new Database();
$db = $database->getConnection();
$user = $auth->getUserInfo();

// معالجة طلبات AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_POST['action']) {
            case 'get_user_permissions':
                $user_id = intval($_POST['user_id']);
                $stmt = $db->prepare("
                    SELECT p.*,
                           CASE WHEN up.id IS NOT NULL THEN 1 ELSE 0 END as granted
                    FROM permissions p
                    LEFT JOIN user_permissions up ON p.id = up.permission_id AND up.user_id = ? AND up.is_active = 1
                    WHERE p.is_active = 1
                    ORDER BY
                        CASE p.category
                            WHEN 'general_admin' THEN 1
                            WHEN 'finance' THEN 2
                            WHEN 'projects' THEN 3
                            WHEN 'citizens' THEN 4
                            WHEN 'services' THEN 5
                            WHEN 'maps' THEN 6
                            WHEN 'website' THEN 7
                            WHEN 'reports' THEN 8
                            WHEN 'settings' THEN 9
                            ELSE 10
                        END,
                        p.sort_order,
                        p.display_name
                ");
                $stmt->execute([$user_id]);
                $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'permissions' => $permissions]);
                break;

            case 'update_user_permissions':
                $user_id = intval($_POST['user_id']);
                $permissions = json_decode($_POST['permissions'], true);

                if (!is_array($permissions)) {
                    $permissions = [];
                }

                $db->beginTransaction();

                // حذف الصلاحيات الحالية
                $stmt = $db->prepare("DELETE FROM user_permissions WHERE user_id = ?");
                $stmt->execute([$user_id]);

                // إضافة الصلاحيات الجديدة
                if (!empty($permissions)) {
                    $stmt = $db->prepare("INSERT INTO user_permissions (user_id, permission_id, granted_by_user_id, is_active) VALUES (?, ?, ?, 1)");
                    foreach ($permissions as $permission_id) {
                        $stmt->execute([$user_id, intval($permission_id), $_SESSION['user_id']]);
                    }
                }

                $db->commit();
                echo json_encode(['success' => true, 'message' => 'تم تحديث الصلاحيات بنجاح']);
                break;

            case 'copy_permissions':
                $source_user_id = intval($_POST['source_user_id']);
                $target_user_id = intval($_POST['target_user_id']);

                $db->beginTransaction();

                // حذف الصلاحيات الحالية للمستخدم المستهدف
                $stmt = $db->prepare("DELETE FROM user_permissions WHERE user_id = ?");
                $stmt->execute([$target_user_id]);

                // نسخ الصلاحيات من المستخدم المصدر
                $stmt = $db->prepare("
                    INSERT INTO user_permissions (user_id, permission_id, granted_by_user_id, is_active)
                    SELECT ?, permission_id, ?, 1
                    FROM user_permissions
                    WHERE user_id = ? AND is_active = 1
                ");
                $stmt->execute([$target_user_id, $_SESSION['user_id'], $source_user_id]);

                $db->commit();
                echo json_encode(['success' => true, 'message' => 'تم نسخ الصلاحيات بنجاح']);
                break;

            case 'apply_template':
                $user_id = intval($_POST['user_id']);
                $template = $_POST['template'];

                // تحديد الصلاحيات حسب القالب
                $templates = [
                    'admin' => ['permissions_manage', 'users_manage', 'finance_view', 'finance_add', 'finance_edit', 'finance_delete',
                                'budgets_view', 'budgets_add', 'budgets_edit', 'projects_view', 'projects_add', 'projects_edit',
                                'reports_view', 'settings_manage'],
                    'accountant' => ['finance_view', 'finance_add', 'finance_edit', 'budgets_view', 'budgets_add', 'budgets_edit',
                                     'suppliers_view', 'invoices_view', 'invoices_add', 'reports_view'],
                    'hr_manager' => ['hr_view', 'hr_add', 'hr_edit', 'employees_view', 'employees_add', 'employees_edit',
                                     'reports_view'],
                    'service_manager' => ['citizens_view', 'complaints_view', 'complaints_edit', 'permits_view', 'permits_edit',
                                          'waste_view', 'waste_edit', 'reports_view'],
                    'viewer' => ['finance_view', 'budgets_view', 'projects_view', 'reports_view']
                ];

                if (!isset($templates[$template])) {
                    throw new Exception('قالب غير موجود');
                }

                $db->beginTransaction();

                // حذف الصلاحيات الحالية
                $stmt = $db->prepare("DELETE FROM user_permissions WHERE user_id = ?");
                $stmt->execute([$user_id]);

                // جلب معرفات الصلاحيات المطلوبة
                $placeholders = str_repeat('?,', count($templates[$template]) - 1) . '?';
                $stmt = $db->prepare("SELECT id FROM permissions WHERE permission_key IN ($placeholders) AND is_active = 1");
                $stmt->execute($templates[$template]);
                $permission_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

                // إضافة الصلاحيات الجديدة
                if (!empty($permission_ids)) {
                    $stmt = $db->prepare("INSERT INTO user_permissions (user_id, permission_id, granted_by_user_id, is_active) VALUES (?, ?, ?, 1)");
                    foreach ($permission_ids as $permission_id) {
                        $stmt->execute([$user_id, $permission_id, $_SESSION['user_id']]);
                    }
                }

                $db->commit();
                echo json_encode(['success' => true, 'message' => 'تم تطبيق القالب بنجاح']);
                break;

            case 'get_all_users':
                $stmt = $db->query("
                    SELECT u.id, u.username, u.full_name, u.user_type, u.department, u.is_active,
                           COUNT(up.id) as permissions_count
                    FROM users u
                    LEFT JOIN user_permissions up ON u.id = up.user_id AND up.is_active = 1
                    GROUP BY u.id
                    ORDER BY u.user_type, u.full_name
                ");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'users' => $users]);
                break;
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// جلب جميع المستخدمين
$users_stmt = $db->query("
    SELECT u.id, u.username, u.full_name, u.user_type, u.department, u.is_active,
           COUNT(up.id) as permissions_count
    FROM users u
    LEFT JOIN user_permissions up ON u.id = up.user_id AND up.is_active = 1
    GROUP BY u.id
    ORDER BY u.user_type, u.full_name
");
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب إحصائيات
$stats_stmt = $db->query("
    SELECT
        (SELECT COUNT(*) FROM permissions WHERE is_active = 1) as total_permissions,
        (SELECT COUNT(*) FROM user_permissions WHERE is_active = 1) as total_user_permissions,
        (SELECT COUNT(DISTINCT user_id) FROM user_permissions WHERE is_active = 1) as users_with_permissions,
        (SELECT COUNT(*) FROM users WHERE is_active = 1) as active_users
");
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔐 إدارة الصلاحيات - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .card-hover {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .status-active {
            background-color: #dcfce7;
            color: #166534;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-inactive {
            background-color: #fef3c7;
            color: #92400e;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            backdrop-filter: blur(2px);
        }
        .selected-user {
            border-color: #6366f1 !important;
            background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%) !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1) !important;
        }
        .permission-category {
            background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
            border-left: 4px solid #6366f1;
        }
        .permission-item {
            transition: all 0.2s ease;
        }
        .permission-item:hover {
            background-color: #f0f9ff !important;
            border-color: #3b82f6 !important;
        }
        .nav-item {
            transition: all 0.2s ease;
        }
        .nav-item:hover {
            transform: translateX(-3px);
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-slide-in {
            animation: slideIn 0.3s ease-out;
        }
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%);
            border-top: 3px solid;
        }
        .stat-card-1 { border-color: #3b82f6; }
        .stat-card-2 { border-color: #10b981; }
        .stat-card-3 { border-color: #8b5cf6; }
        .stat-card-4 { border-color: #f59e0b; }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Navigation Bar -->
    <nav class="bg-gradient-to-r from-indigo-800 to-purple-800 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="../comprehensive_dashboard.php" class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-4 py-2 rounded-lg transition ml-4 flex items-center">
                        <span class="ml-2">←</span> العودة للوحة التحكم
                    </a>
                    <div class="mr-4">
                        <h1 class="text-xl font-bold text-white">إدارة الصلاحيات</h1>
                        <p class="text-sm text-indigo-100">نظام التحكم الشامل في صلاحيات المستخدمين</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4 space-x-reverse">
                    <div class="text-sm text-white bg-white bg-opacity-20 px-4 py-2 rounded-lg">
                        <span class="ml-2">👤</span>
                        <span class="font-semibold"><?= htmlspecialchars($user['full_name']) ?></span>
                    </div>
                    <a href="../logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition flex items-center">
                        <span class="ml-2">🚪</span> تسجيل الخروج
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8 animate-slide-in">
            <h1 class="text-4xl font-bold text-white mb-2">🔐 نظام إدارة الصلاحيات والمستخدمين</h1>
            <p class="text-indigo-100 text-lg">تحكم شامل ومتقدم في صلاحيات النظام</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="stat-card stat-card-1 p-6 rounded-xl shadow-lg card-hover animate-slide-in">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">المستخدمون النشطون</p>
                        <p class="text-3xl font-bold text-gray-900"><?= $stats['active_users'] ?></p>
                        <p class="text-xs text-blue-600 mt-1">مستخدم نشط</p>
                    </div>
                    <div class="p-4 rounded-full bg-blue-100">
                        <span class="text-3xl">👥</span>
                    </div>
                </div>
            </div>

            <div class="stat-card stat-card-2 p-6 rounded-xl shadow-lg card-hover animate-slide-in" style="animation-delay: 0.1s">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">إجمالي الصلاحيات</p>
                        <p class="text-3xl font-bold text-gray-900"><?= $stats['total_permissions'] ?></p>
                        <p class="text-xs text-green-600 mt-1">صلاحية متاحة</p>
                    </div>
                    <div class="p-4 rounded-full bg-green-100">
                        <span class="text-3xl">🔑</span>
                    </div>
                </div>
            </div>

            <div class="stat-card stat-card-3 p-6 rounded-xl shadow-lg card-hover animate-slide-in" style="animation-delay: 0.2s">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">صلاحيات ممنوحة</p>
                        <p class="text-3xl font-bold text-gray-900"><?= $stats['total_user_permissions'] ?></p>
                        <p class="text-xs text-purple-600 mt-1">صلاحية مفعلة</p>
                    </div>
                    <div class="p-4 rounded-full bg-purple-100">
                        <span class="text-3xl">⚡</span>
                    </div>
                </div>
            </div>

            <div class="stat-card stat-card-4 p-6 rounded-xl shadow-lg card-hover animate-slide-in" style="animation-delay: 0.3s">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">مستخدمون بصلاحيات</p>
                        <p class="text-3xl font-bold text-gray-900"><?= $stats['users_with_permissions'] ?></p>
                        <p class="text-xs text-orange-600 mt-1">لديهم صلاحيات</p>
                    </div>
                    <div class="p-4 rounded-full bg-orange-100">
                        <span class="text-3xl">📊</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            <!-- Users List -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold flex items-center">
                                <span class="text-2xl ml-2">👥</span> قائمة المستخدمين
                            </h3>
                            <button onclick="refreshUsers()" class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-3 py-1.5 rounded-lg text-sm transition flex items-center">
                                <span class="ml-1">🔄</span> تحديث
                            </button>
                        </div>

                        <div class="relative">
                            <input type="text"
                                   id="searchInput"
                                   placeholder="🔍 البحث عن مستخدم..."
                                   class="w-full px-4 py-2.5 pr-10 border-0 rounded-lg focus:ring-2 focus:ring-white text-gray-900 placeholder-gray-500"
                                   onkeyup="searchUsers(this.value)">
                            <span class="absolute left-3 top-3 text-gray-400">🔍</span>
                        </div>
                    </div>

                    <div id="usersList" class="p-4 space-y-3 max-h-[600px] overflow-y-auto">
                        <?php foreach ($users as $user_item): ?>
                        <div class="user-item nav-item border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-indigo-500 hover:shadow-md transition"
                             data-user-id="<?= $user_item['id'] ?>"
                             data-username="<?= htmlspecialchars($user_item['username']) ?>"
                             data-fullname="<?= htmlspecialchars($user_item['full_name']) ?>"
                             onclick="selectUser(<?= $user_item['id'] ?>)">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center text-white font-bold ml-3">
                                            <?= mb_substr($user_item['full_name'], 0, 1) ?>
                                        </div>
                                        <div>
                                            <h6 class="font-semibold text-gray-900"><?= htmlspecialchars($user_item['full_name']) ?></h6>
                                            <p class="text-sm text-gray-500">@<?= htmlspecialchars($user_item['username']) ?></p>
                                        </div>
                                    </div>
                                    <div class="mr-13">
                                        <p class="text-sm text-indigo-600 mb-1">
                                            <span class="ml-1">🏢</span><?= htmlspecialchars($user_item['department']) ?>
                                        </p>
                                        <p class="text-xs text-gray-500 flex items-center">
                                            <span class="ml-1">🔑</span>
                                            <span class="font-semibold text-gray-700"><?= $user_item['permissions_count'] ?></span>
                                            <span class="mr-1">صلاحية</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-left flex flex-col items-end">
                                    <span class="<?= $user_item['is_active'] == 1 ? 'status-active' : 'status-inactive' ?> mb-2">
                                        <?= $user_item['is_active'] == 1 ? '✓ نشط' : '⊗ غير نشط' ?>
                                    </span>
                                    <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600">
                                        <?= htmlspecialchars($user_item['user_type']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Permissions Section -->
            <div class="lg:col-span-3">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-green-600 to-blue-600 text-white p-6">
                        <div class="flex items-center justify-between flex-wrap gap-3">
                            <h3 class="text-lg font-semibold flex items-center">
                                <span class="text-2xl ml-2">🔐</span> صلاحيات المستخدم
                            </h3>
                            <div id="toolsSection" style="display: none;" class="flex items-center gap-2 flex-wrap">
                                <!-- قوالب جاهزة -->
                                <div x-data="{ open: false }" class="relative">
                                    <button @click="open = !open" class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-3 py-2 rounded-lg transition text-sm flex items-center">
                                        <span class="ml-1">📋</span> قوالب جاهزة
                                    </button>
                                    <div x-show="open" @click.away="open = false"
                                         class="absolute left-0 mt-2 w-48 bg-white rounded-lg shadow-xl z-50 overflow-hidden"
                                         style="display: none;">
                                        <button onclick="applyTemplate('admin')" class="w-full text-right px-4 py-2 hover:bg-gray-100 text-gray-700 text-sm">
                                            <span class="ml-2">👑</span> مدير النظام
                                        </button>
                                        <button onclick="applyTemplate('accountant')" class="w-full text-right px-4 py-2 hover:bg-gray-100 text-gray-700 text-sm">
                                            <span class="ml-2">💰</span> محاسب
                                        </button>
                                        <button onclick="applyTemplate('hr_manager')" class="w-full text-right px-4 py-2 hover:bg-gray-100 text-gray-700 text-sm">
                                            <span class="ml-2">👔</span> مدير موارد بشرية
                                        </button>
                                        <button onclick="applyTemplate('service_manager')" class="w-full text-right px-4 py-2 hover:bg-gray-100 text-gray-700 text-sm">
                                            <span class="ml-2">👥</span> مدير خدمات
                                        </button>
                                        <button onclick="applyTemplate('viewer')" class="w-full text-right px-4 py-2 hover:bg-gray-100 text-gray-700 text-sm">
                                            <span class="ml-2">👁️</span> مراقب (عرض فقط)
                                        </button>
                                    </div>
                                </div>

                                <!-- نسخ صلاحيات -->
                                <div x-data="{ open: false }" class="relative">
                                    <button @click="open = !open" class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-3 py-2 rounded-lg transition text-sm flex items-center">
                                        <span class="ml-1">📋</span> نسخ من مستخدم
                                    </button>
                                    <div x-show="open" @click.away="open = false"
                                         id="copyFromDropdown"
                                         class="absolute left-0 mt-2 w-64 bg-white rounded-lg shadow-xl z-50 max-h-64 overflow-y-auto"
                                         style="display: none;">
                                        <!-- سيتم ملؤها ديناميكياً -->
                                    </div>
                                </div>

                                <!-- حفظ -->
                                <button id="saveBtn" onclick="savePermissions()"
                                        style="display: none;"
                                        class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-4 py-2 rounded-lg transition text-sm flex items-center font-semibold">
                                    <span class="ml-1">💾</span> حفظ التغييرات
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="permissionsSection" class="relative min-h-[400px]">
                        <div id="permissionsContent" class="p-6">
                            <div class="text-center py-20">
                                <div class="text-8xl mb-4">🔐</div>
                                <h5 class="text-2xl font-semibold text-gray-700 mb-3">اختر مستخدماً لإدارة صلاحياته</h5>
                                <p class="text-gray-500 text-lg mb-6">يمكنك البحث عن المستخدم في القائمة الجانبية واختياره</p>
                                <div class="flex justify-center gap-4 text-sm text-gray-600">
                                    <div class="flex items-center">
                                        <span class="ml-2">✓</span> منح وإدارة الصلاحيات
                                    </div>
                                    <div class="flex items-center">
                                        <span class="ml-2">✓</span> استخدام القوالب الجاهزة
                                    </div>
                                    <div class="flex items-center">
                                        <span class="ml-2">✓</span> نسخ الصلاحيات بين المستخدمين
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toastContainer" class="fixed top-4 left-4 z-50"></div>

    <script>
        let selectedUserId = null;
        let selectedUserName = '';
        let userPermissions = [];

        function selectUser(userId) {
            // إزالة التحديد السابق
            document.querySelectorAll('.user-item').forEach(item => {
                item.classList.remove('selected-user');
            });

            // تحديد المستخدم الجديد
            const selectedItem = document.querySelector(`[data-user-id="${userId}"]`);
            selectedItem.classList.add('selected-user');
            selectedUserId = userId;
            selectedUserName = selectedItem.dataset.fullname;

            // إظهار الأدوات
            document.getElementById('toolsSection').style.display = 'flex';

            // تحميل صلاحيات المستخدم
            loadUserPermissions(userId);

            // تحديث قائمة نسخ الصلاحيات
            updateCopyFromDropdown();
        }

        function loadUserPermissions(userId) {
            showLoading();

            fetch('permissions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_user_permissions&user_id=${userId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    userPermissions = data.permissions;
                    displayPermissions(data.permissions);
                    document.getElementById('saveBtn').style.display = 'flex';
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                showError('خطأ في تحميل الصلاحيات: ' + error.message);
            })
            .finally(() => {
                hideLoading();
            });
        }

        function displayPermissions(permissions) {
            // أسماء الفئات بالعربية (مطابقة تماماً للقائمة الرئيسية)
            const categoryNames = {
                'general_admin': { name: '🏛️ الإدارة العامة', color: 'indigo' },
                'finance': { name: '💰 النظام المالي', color: 'green' },
                'projects': { name: '🏗️ المشاريع والعقود', color: 'blue' },
                'citizens': { name: '👥 خدمات المواطنين', color: 'purple' },
                'services': { name: '🚚 الخدمات والصيانة', color: 'orange' },
                'maps': { name: '🗺️ الخرائط والمرافق', color: 'teal' },
                'website': { name: '🌐 الموقع والاتصالات', color: 'cyan' },
                'reports': { name: '📊 التقارير والأرشفة', color: 'pink' },
                'settings': { name: '⚙️ الإعدادات', color: 'gray' }
            };

            // تجميع الصلاحيات حسب الفئة (من قاعدة البيانات مباشرة)
            const categories = {};

            permissions.forEach(perm => {
                const category = perm.category || 'other';

                if (!categories[category]) {
                    categories[category] = {
                        name: categoryNames[category]?.name || '📁 أخرى',
                        color: categoryNames[category]?.color || 'slate',
                        permissions: []
                    };
                }

                categories[category].permissions.push(perm);
            });

            let html = `<div class="space-y-4">`;

            // عرض كل فئة مع صلاحياتها
            Object.keys(categories).forEach(catKey => {
                const cat = categories[catKey];
                if (cat.permissions.length === 0) return;

                const checkedCount = cat.permissions.filter(p => p.granted == 1).length;

                html += `
                    <div class="permission-category border-2 border-gray-200 rounded-xl overflow-hidden shadow-sm">
                        <div class="bg-gradient-to-r from-${cat.color}-50 to-white px-5 py-4 border-b border-gray-200">
                            <div class="flex justify-between items-center">
                                <div>
                                    <span class="text-lg font-bold text-gray-800">${cat.name}</span>
                                    <span class="mr-2 text-sm text-gray-600">(${checkedCount}/${cat.permissions.length})</span>
                                </div>
                                <div class="flex gap-2">
                                    <button class="bg-${cat.color}-500 hover:bg-${cat.color}-600 text-white px-3 py-1.5 rounded-lg text-sm transition"
                                            onclick="selectAllCategory('${catKey}')">
                                        ✓ تحديد الكل
                                    </button>
                                    <button class="bg-gray-400 hover:bg-gray-500 text-white px-3 py-1.5 rounded-lg text-sm transition"
                                            onclick="deselectAllCategory('${catKey}')">
                                        ✗ إلغاء الكل
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 space-y-2">
                `;

                cat.permissions.forEach(perm => {
                    html += createPermissionHTML(perm, catKey);
                });

                html += `
                        </div>
                    </div>
                `;
            });

            html += `</div>`;

            document.getElementById('permissionsContent').innerHTML = html;
        }

        function createPermissionHTML(perm, category) {
            const isChecked = perm.granted == 1;
            return `
                <div class="permission-item flex items-center p-3 bg-white border-2 border-gray-200 rounded-lg hover:border-blue-400"
                     data-category="${category}" data-permission-id="${perm.id}">
                    <input type="checkbox"
                           class="w-5 h-5 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
                           id="perm_${perm.id}"
                           value="${perm.id}"
                           ${isChecked ? 'checked' : ''}
                           onchange="togglePermission(${perm.id})">
                    <label for="perm_${perm.id}" class="mr-3 flex-1 cursor-pointer">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <span class="text-2xl ml-3">${perm.icon || '🔑'}</span>
                                <div>
                                    <div class="font-semibold text-gray-900">${perm.display_name}</div>
                                    ${perm.description ? `<div class="text-sm text-gray-500 mt-0.5">${perm.description}</div>` : ''}
                                </div>
                            </div>
                            ${perm.page_url ? `<span class="text-xs text-blue-500 bg-blue-50 px-2 py-1 rounded">🔗 ${perm.page_url}</span>` : ''}
                        </div>
                    </label>
                </div>
            `;
        }

        function togglePermission(permissionId) {
            const checkbox = document.getElementById(`perm_${permissionId}`);
            const permission = userPermissions.find(p => p.id == permissionId);
            if (permission) {
                permission.granted = checkbox.checked ? 1 : 0;
            }
        }

        function selectAllCategory(category) {
            document.querySelectorAll(`[data-category="${category}"] input[type="checkbox"]`).forEach(checkbox => {
                checkbox.checked = true;
                togglePermission(checkbox.value);
            });
        }

        function deselectAllCategory(category) {
            document.querySelectorAll(`[data-category="${category}"] input[type="checkbox"]`).forEach(checkbox => {
                checkbox.checked = false;
                togglePermission(checkbox.value);
            });
        }

        function savePermissions() {
            if (!selectedUserId) {
                showWarning('يرجى اختيار مستخدم أولاً');
                return;
            }

            const selectedPermissions = [];
            document.querySelectorAll('input[type="checkbox"]:checked').forEach(checkbox => {
                selectedPermissions.push(checkbox.value);
            });

            const saveBtn = document.getElementById('saveBtn');
            const originalHTML = saveBtn.innerHTML;
            saveBtn.innerHTML = '<span class="ml-1">⏳</span> جاري الحفظ...';
            saveBtn.disabled = true;

            fetch('permissions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_user_permissions&user_id=${selectedUserId}&permissions=${JSON.stringify(selectedPermissions)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(`تم حفظ صلاحيات ${selectedUserName} بنجاح! ✅`);
                    refreshUsers();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                showError('خطأ في حفظ الصلاحيات: ' + error.message);
            })
            .finally(() => {
                saveBtn.innerHTML = originalHTML;
                saveBtn.disabled = false;
            });
        }

        function applyTemplate(template) {
            if (!selectedUserId) {
                showWarning('يرجى اختيار مستخدم أولاً');
                return;
            }

            const templateNames = {
                'admin': 'مدير النظام',
                'accountant': 'محاسب',
                'hr_manager': 'مدير موارد بشرية',
                'service_manager': 'مدير خدمات',
                'viewer': 'مراقب (عرض فقط)'
            };

            if (!confirm(`هل تريد تطبيق قالب "${templateNames[template]}" على ${selectedUserName}؟`)) {
                return;
            }

            showLoading();

            fetch('permissions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=apply_template&user_id=${selectedUserId}&template=${template}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(`تم تطبيق قالب "${templateNames[template]}" بنجاح! ✅`);
                    loadUserPermissions(selectedUserId);
                    refreshUsers();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                showError('خطأ في تطبيق القالب: ' + error.message);
            })
            .finally(() => {
                hideLoading();
            });
        }

        function copyPermissionsFrom(sourceUserId, sourceUserName) {
            if (!selectedUserId) {
                showWarning('يرجى اختيار مستخدم أولاً');
                return;
            }

            if (sourceUserId == selectedUserId) {
                showWarning('لا يمكن نسخ الصلاحيات من نفس المستخدم');
                return;
            }

            if (!confirm(`هل تريد نسخ صلاحيات "${sourceUserName}" إلى "${selectedUserName}"؟`)) {
                return;
            }

            showLoading();

            fetch('permissions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=copy_permissions&source_user_id=${sourceUserId}&target_user_id=${selectedUserId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(`تم نسخ الصلاحيات من "${sourceUserName}" بنجاح! ✅`);
                    loadUserPermissions(selectedUserId);
                    refreshUsers();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                showError('خطأ في نسخ الصلاحيات: ' + error.message);
            })
            .finally(() => {
                hideLoading();
            });
        }

        function updateCopyFromDropdown() {
            const dropdown = document.getElementById('copyFromDropdown');
            if (!dropdown) return;

            const allUsers = document.querySelectorAll('.user-item');
            let html = '';

            allUsers.forEach(userItem => {
                const userId = userItem.dataset.userId;
                const userName = userItem.dataset.fullname;
                const username = userItem.dataset.username;

                if (userId != selectedUserId) {
                    html += `
                        <button onclick="copyPermissionsFrom(${userId}, '${userName}')"
                                class="w-full text-right px-4 py-2 hover:bg-gray-100 text-gray-700 text-sm border-b border-gray-100">
                            <div class="font-semibold">${userName}</div>
                            <div class="text-xs text-gray-500">@${username}</div>
                        </button>
                    `;
                }
            });

            dropdown.innerHTML = html;
        }

        function refreshUsers() {
            fetch('permissions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_all_users'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateUsersList(data.users);
                }
            })
            .catch(error => {
                console.error('خطأ في تحديث المستخدمين:', error);
            });
        }

        function updateUsersList(users) {
            let html = '';
            users.forEach(user => {
                const statusClass = user.is_active == 1 ? 'status-active' : 'status-inactive';
                const statusText = user.is_active == 1 ? '✓ نشط' : '⊗ غير نشط';

                html += `
                    <div class="user-item nav-item border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-indigo-500 hover:shadow-md transition ${selectedUserId == user.id ? 'selected-user' : ''}"
                         data-user-id="${user.id}"
                         data-username="${user.username}"
                         data-fullname="${user.full_name}"
                         onclick="selectUser(${user.id})">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center text-white font-bold ml-3">
                                        ${user.full_name.substring(0, 1)}
                                    </div>
                                    <div>
                                        <h6 class="font-semibold text-gray-900">${user.full_name}</h6>
                                        <p class="text-sm text-gray-500">@${user.username}</p>
                                    </div>
                                </div>
                                <div class="mr-13">
                                    <p class="text-sm text-indigo-600 mb-1">
                                        <span class="ml-1">🏢</span>${user.department}
                                    </p>
                                    <p class="text-xs text-gray-500 flex items-center">
                                        <span class="ml-1">🔑</span>
                                        <span class="font-semibold text-gray-700">${user.permissions_count}</span>
                                        <span class="mr-1">صلاحية</span>
                                    </p>
                                </div>
                            </div>
                            <div class="text-left flex flex-col items-end">
                                <span class="${statusClass} mb-2">
                                    ${statusText}
                                </span>
                                <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600">
                                    ${user.user_type}
                                </span>
                            </div>
                        </div>
                    </div>
                `;
            });

            document.getElementById('usersList').innerHTML = html;

            if (selectedUserId) {
                updateCopyFromDropdown();
            }
        }

        function searchUsers(searchTerm) {
            const userItems = document.querySelectorAll('.user-item');
            userItems.forEach(item => {
                const userName = item.dataset.fullname.toLowerCase();
                const userLogin = item.dataset.username.toLowerCase();

                if (userName.includes(searchTerm.toLowerCase()) ||
                    userLogin.includes(searchTerm.toLowerCase())) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function showLoading() {
            const overlay = document.createElement('div');
            overlay.className = 'loading-overlay';
            overlay.id = 'loadingOverlay';
            overlay.innerHTML = `
                <div class="text-center">
                    <div class="inline-block animate-spin rounded-full h-16 w-16 border-b-4 border-indigo-600 mb-4"></div>
                    <h5 class="text-xl font-semibold text-gray-700">جاري تحميل الصلاحيات...</h5>
                    <p class="text-gray-500 mt-2">يرجى الانتظار</p>
                </div>
            `;
            document.getElementById('permissionsSection').appendChild(overlay);
        }

        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.remove();
            }
        }

        function showToast(message, type = 'info') {
            const colors = {
                'success': 'bg-green-500',
                'error': 'bg-red-500',
                'warning': 'bg-yellow-500',
                'info': 'bg-blue-500'
            };

            const icons = {
                'success': '✅',
                'error': '❌',
                'warning': '⚠️',
                'info': 'ℹ️'
            };

            const toast = document.createElement('div');
            toast.className = `${colors[type]} text-white px-6 py-4 rounded-lg shadow-2xl mb-3 flex items-center animate-slide-in`;
            toast.innerHTML = `
                <span class="text-2xl ml-3">${icons[type]}</span>
                <span class="font-semibold">${message}</span>
            `;

            document.getElementById('toastContainer').appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                toast.style.transition = 'all 0.3s ease';
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.remove();
                    }
                }, 300);
            }, 4000);
        }

        function showSuccess(message) {
            showToast(message, 'success');
        }

        function showError(message) {
            showToast(message, 'error');
        }

        function showWarning(message) {
            showToast(message, 'warning');
        }

        // تحسين تجربة البحث
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.focus();
            }
        });
    </script>
</body>
</html>
