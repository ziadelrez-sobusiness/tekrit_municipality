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
                    ORDER BY p.module_name, p.sort_order, p.display_name
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
        (SELECT COUNT(*) FROM permissions) as total_permissions,
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
            padding: 4px 8px; 
            border-radius: 6px; 
            font-size: 0.75rem; 
        }
        .status-inactive { 
            background-color: #fef3c7; 
            color: #92400e; 
            padding: 4px 8px; 
            border-radius: 6px; 
            font-size: 0.75rem; 
        }
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .selected-user {
            border-color: #3b82f6 !important;
            background-color: #eff6ff !important;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="../comprehensive_dashboard.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition ml-4">
                        ← العودة للوحة التحكم
                    </a>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">إدارة الصلاحيات</h1>
                        <p class="text-sm text-gray-500">تحكم في صلاحيات المستخدمين</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4 space-x-reverse">
                    <div class="text-sm text-gray-700">
                        مرحباً، <span class="font-semibold"><?= htmlspecialchars($user['full_name']) ?></span>
                    </div>
                    <a href="../logout.php" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition">
                        تسجيل الخروج
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-white mb-2">نظام إدارة الصلاحيات والمستخدمين</h1>
            <p class="text-blue-100">تحكم شامل في صلاحيات النظام</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <span class="text-2xl">👥</span>
                    </div>
                    <div class="mr-4">
                        <p class="text-sm font-medium text-gray-600">المستخدمون النشطون</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $stats['active_users'] ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <span class="text-2xl">🔑</span>
                    </div>
                    <div class="mr-4">
                        <p class="text-sm font-medium text-gray-600">إجمالي الصلاحيات</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $stats['total_permissions'] ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <span class="text-2xl">⚡</span>
                    </div>
                    <div class="mr-4">
                        <p class="text-sm font-medium text-gray-600">صلاحيات ممنوحة</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $stats['total_user_permissions'] ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                        <span class="text-2xl">📊</span>
                    </div>
                    <div class="mr-4">
                        <p class="text-sm font-medium text-gray-600">مستخدمون بصلاحيات</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $stats['users_with_permissions'] ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            <!-- Users List -->
            <div class="lg:col-span-2 bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6">
                    <h3 class="text-lg font-semibold flex items-center">
                        <span class="text-2xl ml-2">👥</span> المستخدمون
                    </h3>
                    <button onclick="refreshUsers()" class="mt-2 bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-3 py-1 rounded text-sm transition">
                        🔄 تحديث
                    </button>
                </div>

                <div class="p-4">
                    <div class="mb-4">
                        <input type="text" 
                               placeholder="🔍 البحث عن مستخدم..." 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               onkeyup="searchUsers(this.value)">
                    </div>

                    <div id="usersList" class="space-y-3 max-h-96 overflow-y-auto">
                        <?php foreach ($users as $user): ?>
                        <div class="user-item border border-gray-200 rounded-lg p-4 cursor-pointer hover:border-blue-500 hover:shadow-md transition"
                             data-user-id="<?= $user['id'] ?>" onclick="selectUser(<?= $user['id'] ?>)">
                            <div class="flex justify-between items-start">
                                <div class="flex-grow-1">
                                    <h6 class="font-semibold text-gray-900 mb-1"><?= htmlspecialchars($user['full_name']) ?></h6>
                                    <p class="text-sm text-gray-500">@<?= htmlspecialchars($user['username']) ?></p>
                                    <p class="text-sm text-blue-600"><?= htmlspecialchars($user['department']) ?></p>
                                    <p class="text-xs text-gray-400">
                                        <span class="text-sm">🔑</span> <?= $user['permissions_count'] ?> صلاحية
                                    </p>
                                </div>
                                <div class="text-left">
                                    <span class="<?= $user['is_active'] == 1 ? 'status-active' : 'status-inactive' ?>">
                                        <?= $user['is_active'] == 1 ? 'نشط' : 'غير نشط' ?>
                                    </span>
                                    <div class="mt-2">
                                        <small class="text-gray-500"><?= htmlspecialchars($user['user_type']) ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Permissions Section -->
            <div class="lg:col-span-3 bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-green-600 to-blue-600 text-white p-6">
                    <h3 class="text-lg font-semibold flex items-center justify-between">
                        <span><span class="text-2xl ml-2">🔐</span> صلاحيات المستخدم</span>
                        <button id="saveBtn" onclick="savePermissions()" 
                                style="display: none;" 
                                class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-4 py-2 rounded transition">
                            💾 حفظ التغييرات
                        </button>
                    </h3>
                </div>

                <div id="permissionsSection" class="relative">
                    <div id="permissionsContent" class="p-6">
                        <div class="text-center py-12">
                            <div class="text-6xl mb-4">👤</div>
                            <h5 class="text-xl font-semibold text-gray-700 mb-2">اختر مستخدماً لعرض وتعديل صلاحياته</h5>
                            <p class="text-gray-500">يمكنك البحث عن المستخدم في القائمة الجانبية واختياره</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedUserId = null;
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
            
            // تحميل صلاحيات المستخدم
            loadUserPermissions(userId);
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
                    document.getElementById('saveBtn').style.display = 'block';
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
            // تنظيم الصلاحيات حسب الوحدات
            const modules = {};
            permissions.forEach(perm => {
                if (!modules[perm.module_name]) {
                    modules[perm.module_name] = {
                        parents: [],
                        children: []
                    };
                }
                
                if (perm.parent_permission_id === null) {
                    modules[perm.module_name].parents.push(perm);
                } else {
                    modules[perm.module_name].children.push(perm);
                }
            });

            let html = '';
            Object.keys(modules).forEach(moduleName => {
                const moduleData = modules[moduleName];
                const moduleNameAr = getModuleNameArabic(moduleName);
                
                html += `
                    <div class="border border-gray-200 rounded-lg mb-4 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                            <div class="flex justify-between items-center">
                                <span class="font-semibold text-gray-800">📁 ${moduleNameAr}</span>
                                <div class="space-x-2 space-x-reverse">
                                    <button class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition" 
                                            onclick="selectAllModule('${moduleName}')">
                                        تحديد الكل
                                    </button>
                                    <button class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded text-sm transition" 
                                            onclick="deselectAllModule('${moduleName}')">
                                        إلغاء الكل
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 space-y-3">
                `;
                
                // عرض الصلاحيات الرئيسية أولاً
                moduleData.parents.forEach(perm => {
                    html += createPermissionHTML(perm, moduleName, false);
                });
                
                // ثم عرض الصلاحيات الفرعية
                moduleData.children.forEach(perm => {
                    html += createPermissionHTML(perm, moduleName, true);
                });
                
                html += `
                        </div>
                    </div>
                `;
            });

            document.getElementById('permissionsContent').innerHTML = html;
        }

        function createPermissionHTML(perm, moduleName, isSub) {
            return `
                <div class="flex items-center p-3 ${isSub ? 'mr-6 bg-gray-50' : 'bg-white'} border border-gray-200 rounded-lg hover:bg-blue-50 transition" data-module="${moduleName}">
                    <input type="checkbox" 
                           class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500" 
                           id="perm_${perm.id}"
                           value="${perm.id}"
                           ${perm.granted == 1 ? 'checked' : ''}
                           onchange="togglePermission(${perm.id})">
                    <label for="perm_${perm.id}" class="mr-3 flex-1 cursor-pointer">
                        <div class="flex items-center">
                            <span class="text-lg ml-2">${perm.icon || '🔑'}</span>
                            <div>
                                <div class="font-medium text-gray-900">${perm.display_name}</div>
                                ${perm.description ? `<div class="text-sm text-gray-500">${perm.description}</div>` : ''}
                            </div>
                        </div>
                    </label>
                    ${perm.page_url ? `<span class="text-xs text-blue-500">🔗 ${perm.page_url}</span>` : ''}
                </div>
            `;
        }

        function getModuleNameArabic(moduleName) {
            const moduleNames = {
                'core': 'النظام الأساسي',
                'hr': 'الموارد البشرية',
                'vehicles': 'الآليات والمعدات',
                'finance': 'الشؤون المالية',
                'services': 'خدمة المواطنين',
                'waste': 'إدارة النظافة',
                'reports': 'التقارير',
                'settings': 'الإعدادات'
            };
            return moduleNames[moduleName] || moduleName;
        }

        function togglePermission(permissionId) {
            const checkbox = document.getElementById(`perm_${permissionId}`);
            const permission = userPermissions.find(p => p.id == permissionId);
            if (permission) {
                permission.granted = checkbox.checked ? 1 : 0;
            }
        }

        function selectAllModule(moduleName) {
            document.querySelectorAll(`[data-module="${moduleName}"] input[type="checkbox"]`).forEach(checkbox => {
                checkbox.checked = true;
                togglePermission(checkbox.value);
            });
        }

        function deselectAllModule(moduleName) {
            document.querySelectorAll(`[data-module="${moduleName}"] input[type="checkbox"]`).forEach(checkbox => {
                checkbox.checked = false;
                togglePermission(checkbox.value);
            });
        }

        function savePermissions() {
            if (!selectedUserId) {
                alert('يرجى اختيار مستخدم أولاً');
                return;
            }

            const selectedPermissions = [];
            document.querySelectorAll('input[type="checkbox"]:checked').forEach(checkbox => {
                selectedPermissions.push(checkbox.value);
            });

            const saveBtn = document.getElementById('saveBtn');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '⏳ جاري الحفظ...';
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
                    showSuccess('تم حفظ الصلاحيات بنجاح! ✅');
                    refreshUsers();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                showError('خطأ في حفظ الصلاحيات: ' + error.message);
            })
            .finally(() => {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            });
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
                const statusText = user.is_active == 1 ? 'نشط' : 'غير نشط';
                
                html += `
                    <div class="user-item border border-gray-200 rounded-lg p-4 cursor-pointer hover:border-blue-500 hover:shadow-md transition"
                         data-user-id="${user.id}" onclick="selectUser(${user.id})">
                        <div class="flex justify-between items-start">
                            <div class="flex-grow-1">
                                <h6 class="font-semibold text-gray-900 mb-1">${user.full_name}</h6>
                                <p class="text-sm text-gray-500">@${user.username}</p>
                                <p class="text-sm text-blue-600">${user.department}</p>
                                <p class="text-xs text-gray-400">
                                    <span class="text-sm">🔑</span> ${user.permissions_count} صلاحية
                                </p>
                            </div>
                            <div class="text-left">
                                <span class="${statusClass}">
                                    ${statusText}
                                </span>
                                <div class="mt-2">
                                    <small class="text-gray-500">${user.user_type}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            document.getElementById('usersList').innerHTML = html;
            
            // إعادة تحديد المستخدم إذا كان محدداً
            if (selectedUserId) {
                const selectedElement = document.querySelector(`[data-user-id="${selectedUserId}"]`);
                if (selectedElement) {
                    selectedElement.classList.add('selected-user');
                }
            }
        }

        function searchUsers(searchTerm) {
            const userItems = document.querySelectorAll('.user-item');
            userItems.forEach(item => {
                const userName = item.querySelector('h6').textContent.toLowerCase();
                const userLogin = item.querySelector('.text-gray-500').textContent.toLowerCase();
                const userDept = item.querySelector('.text-blue-600').textContent.toLowerCase();
                
                if (userName.includes(searchTerm.toLowerCase()) || 
                    userLogin.includes(searchTerm.toLowerCase()) || 
                    userDept.includes(searchTerm.toLowerCase())) {
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
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <h5 class="mt-2 text-gray-600">جاري تحميل الصلاحيات...</h5>
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

        function showSuccess(message) {
            const toast = document.createElement('div');
            toast.className = 'fixed top-4 left-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
            toast.innerHTML = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 3000);
        }

        function showError(message) {
            const toast = document.createElement('div');
            toast.className = 'fixed top-4 left-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
            toast.innerHTML = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 5000);
        }
    </script>
</body>
</html>
