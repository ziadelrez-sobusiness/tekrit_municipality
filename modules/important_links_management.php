<?php
// تحميل CSRF Protection
if (file_exists(__DIR__ . '/../includes/csrf_middleware.php')) {
    require_once __DIR__ . '/../includes/csrf_middleware.php';
}

require_once '../config/database.php';
require_once '../includes/auth.php';

// التحقق من الصلاحيات
$auth->requireLogin();
if (!$auth->checkPermission('employee')) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

$success_message = '';
$error_message = '';

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!csrf_protect(false)) {
        $error_message = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        $action = $_POST['action'] ?? '';
        
        // إضافة رابط جديد
        if ($action == 'add_link') {
            $category_id = intval($_POST['category_id']);
            $name_ar = trim($_POST['name_ar']);
            $name_en = trim($_POST['name_en'] ?? '');
            $description_ar = trim($_POST['description_ar'] ?? '');
            $description_en = trim($_POST['description_en'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $phone_2 = trim($_POST['phone_2'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $website = trim($_POST['website'] ?? '');
            $address_ar = trim($_POST['address_ar'] ?? '');
            $address_en = trim($_POST['address_en'] ?? '');
            $location_lat = !empty($_POST['location_lat']) ? floatval($_POST['location_lat']) : null;
            $location_lng = !empty($_POST['location_lng']) ? floatval($_POST['location_lng']) : null;
            $working_hours_ar = trim($_POST['working_hours_ar'] ?? '');
            $working_hours_en = trim($_POST['working_hours_en'] ?? '');
            $is_government = isset($_POST['is_government']) ? 1 : 0;
            $is_emergency = isset($_POST['is_emergency']) ? 1 : 0;
            $display_order = intval($_POST['display_order'] ?? 0);
            
            if (!empty($name_ar) && $category_id > 0) {
                try {
                    $stmt = $db->prepare("
                        INSERT INTO important_links 
                        (category_id, name_ar, name_en, description_ar, description_en, phone, phone_2, email, website, 
                         address_ar, address_en, location_lat, location_lng, working_hours_ar, working_hours_en, 
                         is_government, is_emergency, display_order, is_active) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([
                        $category_id, $name_ar, $name_en, $description_ar, $description_en, 
                        $phone, $phone_2, $email, $website, $address_ar, $address_en, 
                        $location_lat, $location_lng, $working_hours_ar, $working_hours_en, 
                        $is_government, $is_emergency, $display_order
                    ]);
                    $success_message = "تم إضافة الرابط بنجاح";
                } catch (Exception $e) {
                    $error_message = "خطأ في إضافة الرابط: " . $e->getMessage();
                }
            } else {
                $error_message = "يرجى ملء الحقول الإجبارية (الاسم بالعربي والفئة)";
            }
        }
        
        // تعديل رابط
        elseif ($action == 'edit_link') {
            $link_id = intval($_POST['link_id']);
            $category_id = intval($_POST['category_id']);
            $name_ar = trim($_POST['name_ar']);
            $name_en = trim($_POST['name_en'] ?? '');
            $description_ar = trim($_POST['description_ar'] ?? '');
            $description_en = trim($_POST['description_en'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $phone_2 = trim($_POST['phone_2'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $website = trim($_POST['website'] ?? '');
            $address_ar = trim($_POST['address_ar'] ?? '');
            $address_en = trim($_POST['address_en'] ?? '');
            $location_lat = !empty($_POST['location_lat']) ? floatval($_POST['location_lat']) : null;
            $location_lng = !empty($_POST['location_lng']) ? floatval($_POST['location_lng']) : null;
            $working_hours_ar = trim($_POST['working_hours_ar'] ?? '');
            $working_hours_en = trim($_POST['working_hours_en'] ?? '');
            $is_government = isset($_POST['is_government']) ? 1 : 0;
            $is_emergency = isset($_POST['is_emergency']) ? 1 : 0;
            $display_order = intval($_POST['display_order'] ?? 0);
            
            if (!empty($name_ar) && $category_id > 0) {
                try {
                    $stmt = $db->prepare("
                        UPDATE important_links SET 
                        category_id = ?, name_ar = ?, name_en = ?, description_ar = ?, description_en = ?, 
                        phone = ?, phone_2 = ?, email = ?, website = ?, address_ar = ?, address_en = ?, 
                        location_lat = ?, location_lng = ?, working_hours_ar = ?, working_hours_en = ?, 
                        is_government = ?, is_emergency = ?, display_order = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $category_id, $name_ar, $name_en, $description_ar, $description_en, 
                        $phone, $phone_2, $email, $website, $address_ar, $address_en, 
                        $location_lat, $location_lng, $working_hours_ar, $working_hours_en, 
                        $is_government, $is_emergency, $display_order, $link_id
                    ]);
                    $success_message = "تم تحديث الرابط بنجاح";
                } catch (Exception $e) {
                    $error_message = "خطأ في تحديث الرابط: " . $e->getMessage();
                }
            } else {
                $error_message = "يرجى ملء الحقول الإجبارية";
            }
        }
        
        // حذف رابط
        elseif ($action == 'delete_link') {
            $link_id = intval($_POST['link_id']);
            try {
                $stmt = $db->prepare("DELETE FROM important_links WHERE id = ?");
                $stmt->execute([$link_id]);
                $success_message = "تم حذف الرابط بنجاح";
            } catch (Exception $e) {
                $error_message = "خطأ في حذف الرابط: " . $e->getMessage();
            }
        }
        
        // تغيير حالة النشاط
        elseif ($action == 'toggle_active') {
            $link_id = intval($_POST['link_id']);
            try {
                $stmt = $db->prepare("UPDATE important_links SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$link_id]);
                $success_message = "تم تحديث الحالة بنجاح";
            } catch (Exception $e) {
                $error_message = "خطأ في تحديث الحالة: " . $e->getMessage();
            }
        }
        
        // إضافة فئة
        elseif ($action == 'add_category') {
            $name_ar = trim($_POST['name_ar']);
            $name_en = trim($_POST['name_en'] ?? '');
            $icon = trim($_POST['icon'] ?? '📋');
            $color = trim($_POST['color'] ?? '#3b82f6');
            $display_order = intval($_POST['display_order'] ?? 0);
            
            if (!empty($name_ar)) {
                try {
                    $stmt = $db->prepare("
                        INSERT INTO important_link_categories 
                        (name_ar, name_en, icon, color, display_order, is_active) 
                        VALUES (?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([$name_ar, $name_en, $icon, $color, $display_order]);
                    $success_message = "تم إضافة الفئة بنجاح";
                } catch (Exception $e) {
                    $error_message = "خطأ في إضافة الفئة: " . $e->getMessage();
                }
            }
        }
        
        // تعديل فئة
        elseif ($action == 'edit_category') {
            $category_id = intval($_POST['category_id']);
            $name_ar = trim($_POST['name_ar']);
            $name_en = trim($_POST['name_en'] ?? '');
            $icon = trim($_POST['icon'] ?? '📋');
            $color = trim($_POST['color'] ?? '#3b82f6');
            $display_order = intval($_POST['display_order'] ?? 0);
            
            if (!empty($name_ar)) {
                try {
                    $stmt = $db->prepare("
                        UPDATE important_link_categories SET 
                        name_ar = ?, name_en = ?, icon = ?, color = ?, display_order = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name_ar, $name_en, $icon, $color, $display_order, $category_id]);
                    $success_message = "تم تحديث الفئة بنجاح";
                } catch (Exception $e) {
                    $error_message = "خطأ في تحديث الفئة: " . $e->getMessage();
                }
            }
        }
        
        // حذف فئة
        elseif ($action == 'delete_category') {
            $category_id = intval($_POST['category_id']);
            try {
                // التحقق من وجود روابط مرتبطة
                $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM important_links WHERE category_id = ?");
                $check_stmt->execute([$category_id]);
                $count = $check_stmt->fetch()['count'];
                
                if ($count > 0) {
                    $error_message = "لا يمكن حذف الفئة لأنها تحتوي على " . $count . " رابط. يرجى نقل أو حذف الروابط أولاً.";
                } else {
                    $stmt = $db->prepare("DELETE FROM important_link_categories WHERE id = ?");
                    $stmt->execute([$category_id]);
                    $success_message = "تم حذف الفئة بنجاح";
                }
            } catch (Exception $e) {
                $error_message = "خطأ في حذف الفئة: " . $e->getMessage();
            }
        }
    }
}

// جلب البيانات
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$page = intval($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(il.name_ar LIKE ? OR il.name_en LIKE ? OR il.description_ar LIKE ? OR il.phone LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($category_filter) {
    $where_conditions[] = "il.category_id = ?";
    $params[] = $category_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// جلب إجمالي العدد
$count_query = "SELECT COUNT(*) as total FROM important_links il $where_clause";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// جلب الروابط
$query = "
    SELECT 
        il.*,
        ilc.name_ar as category_name_ar,
        ilc.name_en as category_name_en,
        ilc.icon as category_icon,
        ilc.color as category_color
    FROM important_links il 
    INNER JOIN important_link_categories ilc ON il.category_id = ilc.id
    $where_clause
    ORDER BY il.is_emergency DESC, il.display_order ASC, il.name_ar ASC 
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$links = $stmt->fetchAll();

// جلب الفئات
$categories = $db->query("SELECT * FROM important_link_categories ORDER BY display_order ASC, name_ar ASC")->fetchAll();

// جلب إحصائيات
$stats = [
    'total_links' => $db->query("SELECT COUNT(*) as count FROM important_links")->fetch()['count'],
    'active_links' => $db->query("SELECT COUNT(*) as count FROM important_links WHERE is_active = 1")->fetch()['count'],
    'total_categories' => $db->query("SELECT COUNT(*) as count FROM important_link_categories")->fetch()['count'],
    'emergency_links' => $db->query("SELECT COUNT(*) as count FROM important_links WHERE is_emergency = 1")->fetch()['count']
];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة روابط مهمة - بلدية تكريت</title>
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
    <header class="bg-white shadow-lg border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center h-auto md:h-16 py-4 md:py-0 gap-4">
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-900">🔗 إدارة روابط مهمة</h1>
                    <p class="text-sm text-gray-500">إدارة المرافق العامة والخدمات المهمة</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button onclick="showAddLinkModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 text-sm md:text-base whitespace-nowrap">
                        ➕ إضافة رابط جديد
                    </button>
                    <button onclick="showCategoryModal()" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 text-sm md:text-base whitespace-nowrap">
                        📂 إدارة الفئات
                    </button>
                    <a href="important_links_sources_management.php" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 text-sm md:text-base whitespace-nowrap">
                        📡 إدارة المصادر
                    </a>
                    <a href="../public/important-links.php" target="_blank" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm md:text-base whitespace-nowrap">
                        🌐 عرض الصفحة العامة
                    </a>
                    <a href="../comprehensive_dashboard.php" class="text-gray-600 hover:text-gray-900 text-sm md:text-base whitespace-nowrap">
                        🏠 العودة
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-8">
        
        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                ✅ <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                ❌ <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 mb-6 md:mb-8">
            <div class="bg-white p-4 md:p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="text-2xl md:text-3xl text-blue-500 ml-2 md:ml-3">🔗</div>
                    <div>
                        <p class="text-xs md:text-sm text-gray-600">إجمالي الروابط</p>
                        <p class="text-xl md:text-2xl font-bold"><?= $stats['total_links'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-4 md:p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="text-2xl md:text-3xl text-green-500 ml-2 md:ml-3">✅</div>
                    <div>
                        <p class="text-xs md:text-sm text-gray-600">روابط نشطة</p>
                        <p class="text-xl md:text-2xl font-bold"><?= $stats['active_links'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-4 md:p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="text-2xl md:text-3xl text-purple-500 ml-2 md:ml-3">📂</div>
                    <div>
                        <p class="text-xs md:text-sm text-gray-600">الفئات</p>
                        <p class="text-xl md:text-2xl font-bold"><?= $stats['total_categories'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-4 md:p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="text-2xl md:text-3xl text-red-500 ml-2 md:ml-3">🚨</div>
                    <div>
                        <p class="text-xs md:text-sm text-gray-600">خدمات طوارئ</p>
                        <p class="text-xl md:text-2xl font-bold"><?= $stats['emergency_links'] ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="bg-white p-4 md:p-6 rounded-lg shadow mb-6 md:mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">البحث</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="البحث في الاسم أو الوصف..."
                           class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">الفئة</label>
                    <select name="category" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                        <option value="">جميع الفئات</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['icon'] . ' ' . $cat['name_ar']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 text-sm md:text-base">
                        🔍 بحث
                    </button>
                    <a href="important_links_management.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 text-sm md:text-base">
                        🔄 إعادة
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Links Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs md:text-sm font-medium text-gray-700 uppercase">الاسم</th>
                            <th class="px-4 py-3 text-right text-xs md:text-sm font-medium text-gray-700 uppercase">الفئة</th>
                            <th class="px-4 py-3 text-right text-xs md:text-sm font-medium text-gray-700 uppercase">الهاتف</th>
                            <th class="px-4 py-3 text-right text-xs md:text-sm font-medium text-gray-700 uppercase">الحالة</th>
                            <th class="px-4 py-3 text-right text-xs md:text-sm font-medium text-gray-700 uppercase">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($links)): ?>
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                    لا توجد روابط
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($links as $link): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div class="text-sm md:text-base font-medium text-gray-900">
                                            <?= htmlspecialchars($link['name_ar']) ?>
                                        </div>
                                        <?php if ($link['is_emergency']): ?>
                                            <span class="inline-block mt-1 bg-red-100 text-red-800 px-2 py-1 rounded text-xs">🚨 طوارئ</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs md:text-sm" 
                                              style="background-color: <?= htmlspecialchars($link['category_color']) ?>20; color: <?= htmlspecialchars($link['category_color']) ?>;">
                                            <?= htmlspecialchars($link['category_icon']) ?> <?= htmlspecialchars($link['category_name_ar']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm md:text-base text-gray-600">
                                        <?= htmlspecialchars($link['phone'] ?: '-') ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 rounded text-xs md:text-sm <?= $link['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                            <?= $link['is_active'] ? 'نشط' : 'غير نشط' ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <button onclick="editLink(<?= htmlspecialchars(json_encode($link)) ?>)" 
                                                    class="bg-blue-600 text-white px-2 md:px-3 py-1 rounded text-xs md:text-sm hover:bg-blue-700">
                                                ✏️ تعديل
                                            </button>
                                            <button onclick="toggleActive(<?= $link['id'] ?>, <?= $link['is_active'] ? 'false' : 'true' ?>)" 
                                                    class="bg-yellow-600 text-white px-2 md:px-3 py-1 rounded text-xs md:text-sm hover:bg-yellow-700">
                                                <?= $link['is_active'] ? '👁️ إخفاء' : '👁️ إظهار' ?>
                                            </button>
                                            <button onclick="deleteLink(<?= $link['id'] ?>, '<?= htmlspecialchars($link['name_ar']) ?>')" 
                                                    class="bg-red-600 text-white px-2 md:px-3 py-1 rounded text-xs md:text-sm hover:bg-red-700">
                                                🗑️ حذف
                                            </button>
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
                <div class="bg-gray-50 px-4 py-3 border-t border-gray-200">
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-2">
                        <div class="text-sm text-gray-700">
                            صفحة <?= $page ?> من <?= $total_pages ?> (<?= $total_records ?> رابط)
                        </div>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $category_filter ? '&category=' . $category_filter : '' ?>" 
                                   class="px-3 py-1 bg-white border border-gray-300 rounded text-sm hover:bg-gray-50">
                                    السابق
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $category_filter ? '&category=' . $category_filter : '' ?>" 
                                   class="px-3 py-1 border rounded text-sm <?= $i == $page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-300 hover:bg-gray-50' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $category_filter ? '&category=' . $category_filter : '' ?>" 
                                   class="px-3 py-1 bg-white border border-gray-300 rounded text-sm hover:bg-gray-50">
                                    التالي
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal: Add/Edit Link -->
    <div id="linkModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 z-50 items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-4 md:p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl md:text-2xl font-bold" id="modalTitle">إضافة رابط جديد</h2>
                    <button onclick="closeLinkModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>
                
                <form method="POST" id="linkForm">
                    <?php echo csrf_input('csrf_token'); ?>
                    <input type="hidden" name="action" id="linkAction" value="add_link">
                    <input type="hidden" name="link_id" id="linkId">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">الفئة *</label>
                            <select name="category_id" id="linkCategoryId" required 
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                                <option value="">اختر الفئة</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['icon'] . ' ' . $cat['name_ar']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">ترتيب العرض</label>
                            <input type="number" name="display_order" id="linkDisplayOrder" value="0" 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">الاسم بالعربي *</label>
                            <input type="text" name="name_ar" id="linkNameAr" required 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">الاسم بالإنجليزي</label>
                            <input type="text" name="name_en" id="linkNameEn" 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">الوصف بالعربي</label>
                            <textarea name="description_ar" id="linkDescriptionAr" rows="3" 
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">الوصف بالإنجليزي</label>
                            <textarea name="description_en" id="linkDescriptionEn" rows="3" 
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base"></textarea>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">الهاتف</label>
                            <input type="tel" name="phone" id="linkPhone" 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">هاتف إضافي</label>
                            <input type="tel" name="phone_2" id="linkPhone2" 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">البريد الإلكتروني</label>
                            <input type="email" name="email" id="linkEmail" 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">الموقع الإلكتروني</label>
                            <input type="url" name="website" id="linkWebsite" 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">العنوان بالعربي</label>
                            <textarea name="address_ar" id="linkAddressAr" rows="2" 
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">العنوان بالإنجليزي</label>
                            <textarea name="address_en" id="linkAddressEn" rows="2" 
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base"></textarea>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">خط العرض</label>
                            <input type="number" step="any" name="location_lat" id="linkLat" 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">خط الطول</label>
                            <input type="number" step="any" name="location_lng" id="linkLng" 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">ساعات العمل بالعربي</label>
                            <input type="text" name="working_hours_ar" id="linkWorkingHoursAr" 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">ساعات العمل بالإنجليزي</label>
                            <input type="text" name="working_hours_en" id="linkWorkingHoursEn" 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                        </div>
                    </div>
                    
                    <div class="flex flex-wrap gap-4 mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_government" id="linkIsGovernment" value="1" 
                                   class="ml-2">
                            <span class="text-sm md:text-base">مرفق حكومي</span>
                        </label>
                        
                        <label class="flex items-center">
                            <input type="checkbox" name="is_emergency" id="linkIsEmergency" value="1" 
                                   class="ml-2">
                            <span class="text-sm md:text-base">خدمة طوارئ</span>
                        </label>
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button type="button" onclick="closeLinkModal()" 
                                class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 text-sm md:text-base">
                            إلغاء
                        </button>
                        <button type="submit" 
                                class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 text-sm md:text-base">
                            💾 حفظ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Categories Management -->
    <div id="categoryModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 z-50 items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-4 md:p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl md:text-2xl font-bold">إدارة الفئات</h2>
                    <button onclick="closeCategoryModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>
                
                <!-- Add Category Form -->
                <div class="bg-gray-50 p-4 rounded-lg mb-6">
                    <h3 class="font-bold mb-3">إضافة فئة جديدة</h3>
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php echo csrf_input('csrf_token'); ?>
                        <input type="hidden" name="action" value="add_category">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">الاسم بالعربي *</label>
                            <input type="text" name="name_ar" required 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">الاسم بالإنجليزي</label>
                            <input type="text" name="name_en" 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">الأيقونة (Emoji)</label>
                            <input type="text" name="icon" value="📋" maxlength="2" 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base text-center text-2xl">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">اللون</label>
                            <input type="color" name="color" value="#3b82f6" 
                                   class="w-full border border-gray-300 rounded-md h-10">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">ترتيب العرض</label>
                            <input type="number" name="display_order" value="0" 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                        </div>
                        
                        <div class="md:col-span-2">
                            <button type="submit" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 text-sm md:text-base">
                                ➕ إضافة فئة
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Categories List -->
                <div>
                    <h3 class="font-bold mb-3">الفئات الحالية</h3>
                    <div class="space-y-2">
                        <?php foreach ($categories as $cat): ?>
                            <div class="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <span class="text-2xl"><?= htmlspecialchars($cat['icon']) ?></span>
                                    <div>
                                        <div class="font-bold"><?= htmlspecialchars($cat['name_ar']) ?></div>
                                        <div class="text-sm text-gray-500">ترتيب: <?= $cat['display_order'] ?></div>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="editCategory(<?= htmlspecialchars(json_encode($cat)) ?>)" 
                                            class="bg-blue-600 text-white px-3 py-1 rounded text-xs md:text-sm hover:bg-blue-700">
                                        ✏️
                                    </button>
                                    <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذه الفئة؟')" class="inline">
                                        <?php echo csrf_input('csrf_token'); ?>
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                        <button type="submit" 
                                                class="bg-red-600 text-white px-3 py-1 rounded text-xs md:text-sm hover:bg-red-700">
                                            🗑️
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showAddLinkModal() {
            document.getElementById('linkModal').classList.add('active');
            document.getElementById('modalTitle').textContent = 'إضافة رابط جديد';
            document.getElementById('linkForm').reset();
            document.getElementById('linkAction').value = 'add_link';
            document.getElementById('linkId').value = '';
        }
        
        function editLink(link) {
            document.getElementById('linkModal').classList.add('active');
            document.getElementById('modalTitle').textContent = 'تعديل رابط';
            document.getElementById('linkAction').value = 'edit_link';
            document.getElementById('linkId').value = link.id;
            document.getElementById('linkCategoryId').value = link.category_id;
            document.getElementById('linkNameAr').value = link.name_ar || '';
            document.getElementById('linkNameEn').value = link.name_en || '';
            document.getElementById('linkDescriptionAr').value = link.description_ar || '';
            document.getElementById('linkDescriptionEn').value = link.description_en || '';
            document.getElementById('linkPhone').value = link.phone || '';
            document.getElementById('linkPhone2').value = link.phone_2 || '';
            document.getElementById('linkEmail').value = link.email || '';
            document.getElementById('linkWebsite').value = link.website || '';
            document.getElementById('linkAddressAr').value = link.address_ar || '';
            document.getElementById('linkAddressEn').value = link.address_en || '';
            document.getElementById('linkLat').value = link.location_lat || '';
            document.getElementById('linkLng').value = link.location_lng || '';
            document.getElementById('linkWorkingHoursAr').value = link.working_hours_ar || '';
            document.getElementById('linkWorkingHoursEn').value = link.working_hours_en || '';
            document.getElementById('linkDisplayOrder').value = link.display_order || 0;
            document.getElementById('linkIsGovernment').checked = link.is_government == 1;
            document.getElementById('linkIsEmergency').checked = link.is_emergency == 1;
        }
        
        function closeLinkModal() {
            document.getElementById('linkModal').classList.remove('active');
        }
        
        function toggleActive(id, currentState) {
            if (confirm('هل تريد تغيير حالة هذا الرابط؟')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <?php echo csrf_input('csrf_token'); ?>
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="link_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deleteLink(id, name) {
            if (confirm('هل أنت متأكد من حذف الرابط: ' + name + '؟')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <?php echo csrf_input('csrf_token'); ?>
                    <input type="hidden" name="action" value="delete_link">
                    <input type="hidden" name="link_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function showCategoryModal() {
            document.getElementById('categoryModal').classList.add('active');
        }
        
        function closeCategoryModal() {
            document.getElementById('categoryModal').classList.remove('active');
        }
        
        function editCategory(cat) {
            // يمكن إضافة modal منفصل لتعديل الفئات
            const nameAr = prompt('الاسم بالعربي:', cat.name_ar);
            if (nameAr) {
                const nameEn = prompt('الاسم بالإنجليزي:', cat.name_en || '');
                const icon = prompt('الأيقونة (Emoji):', cat.icon || '📋');
                const color = prompt('اللون (hex):', cat.color || '#3b82f6');
                const order = prompt('ترتيب العرض:', cat.display_order || 0);
                
                if (nameAr) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <?php echo csrf_input('csrf_token'); ?>
                        <input type="hidden" name="action" value="edit_category">
                        <input type="hidden" name="category_id" value="${cat.id}">
                        <input type="hidden" name="name_ar" value="${nameAr}">
                        <input type="hidden" name="name_en" value="${nameEn}">
                        <input type="hidden" name="icon" value="${icon}">
                        <input type="hidden" name="color" value="${color}">
                        <input type="hidden" name="display_order" value="${order}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        }
        
        // إغلاق الـ modals عند النقر خارجها
        window.onclick = function(event) {
            const linkModal = document.getElementById('linkModal');
            const categoryModal = document.getElementById('categoryModal');
            if (event.target == linkModal) {
                closeLinkModal();
            }
            if (event.target == categoryModal) {
                closeCategoryModal();
            }
        }
    </script>
</body>
</html>

