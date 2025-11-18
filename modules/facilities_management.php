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
        
        if ($action == 'add_facility') {
            $name_ar = trim($_POST['name_ar']);
            $name_en = trim($_POST['name_en']);
            $category_id = $_POST['category_id'];
            $description_ar = trim($_POST['description_ar']);
            $description_en = trim($_POST['description_en']);
            $latitude = floatval($_POST['latitude']);
            $longitude = floatval($_POST['longitude']);
            $contact_person_ar = trim($_POST['contact_person_ar']);
            $contact_person_en = trim($_POST['contact_person_en']);
            $phone = trim($_POST['phone']);
            $email = trim($_POST['email']);
            $address_ar = trim($_POST['address_ar']);
            $address_en = trim($_POST['address_en']);
            $working_hours_ar = trim($_POST['working_hours_ar']);
            $working_hours_en = trim($_POST['working_hours_en']);
            $website = trim($_POST['website']);
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            
            // معالجة رفع الصورة
            $image_path = '';
            if (!empty($_FILES['facility_image']['name'])) {
                $upload_dir = '../uploads/facilities/';
                $file_ext = strtolower(pathinfo($_FILES['facility_image']['name'], PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($file_ext, $allowed_ext)) {
                    $image_path = 'facility_' . time() . '.' . $file_ext;
                    move_uploaded_file($_FILES['facility_image']['tmp_name'], $upload_dir . $image_path);
                }
            }
            
            if (!empty($name_ar) && !empty($latitude) && !empty($longitude)) {
                try {
                    $stmt = $db->prepare("INSERT INTO facilities (name_ar, name_en, category_id, description_ar, description_en, latitude, longitude, contact_person_ar, contact_person_en, phone, email, address_ar, address_en, working_hours_ar, working_hours_en, website, image_path, is_featured, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name_ar, $name_en, $category_id, $description_ar, $description_en, $latitude, $longitude, $contact_person_ar, $contact_person_en, $phone, $email, $address_ar, $address_en, $working_hours_ar, $working_hours_en, $website, $image_path, $is_featured, $auth->getCurrentUser()['id']]);
                    $success_message = "تم إضافة المرفق بنجاح";
                } catch (Exception $e) {
                    $error_message = "خطأ في إضافة المرفق: " . $e->getMessage();
                }
            } else {
                $error_message = "يرجى ملء الحقول الإجبارية (الاسم بالعربي، خط العرض، خط الطول)";
            }
        }
        
        elseif ($action == 'edit_facility') {
            $facility_id = $_POST['facility_id'];
            $name_ar = trim($_POST['name_ar']);
            $name_en = trim($_POST['name_en']);
            $category_id = $_POST['category_id'];
            $description_ar = trim($_POST['description_ar']);
            $description_en = trim($_POST['description_en']);
            $latitude = floatval($_POST['latitude']);
            $longitude = floatval($_POST['longitude']);
            $contact_person_ar = trim($_POST['contact_person_ar']);
            $contact_person_en = trim($_POST['contact_person_en']);
            $phone = trim($_POST['phone']);
            $email = trim($_POST['email']);
            $address_ar = trim($_POST['address_ar']);
            $address_en = trim($_POST['address_en']);
            $working_hours_ar = trim($_POST['working_hours_ar']);
            $working_hours_en = trim($_POST['working_hours_en']);
            $website = trim($_POST['website']);
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            
            // جلب البيانات الحالية
            $current_stmt = $db->prepare("SELECT image_path FROM facilities WHERE id = ?");
            $current_stmt->execute([$facility_id]);
            $current_data = $current_stmt->fetch();
            $image_path = $current_data['image_path'];
            
            // معالجة رفع صورة جديدة
            if (!empty($_FILES['facility_image']['name'])) {
                $upload_dir = '../uploads/facilities/';
                $file_ext = strtolower(pathinfo($_FILES['facility_image']['name'], PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($file_ext, $allowed_ext)) {
                    // حذف الصورة القديمة
                    if ($image_path && file_exists($upload_dir . $image_path)) {
                        unlink($upload_dir . $image_path);
                    }
                    
                    $image_path = 'facility_' . time() . '.' . $file_ext;
                    move_uploaded_file($_FILES['facility_image']['tmp_name'], $upload_dir . $image_path);
                }
            }
            
            if (!empty($name_ar) && !empty($latitude) && !empty($longitude)) {
                try {
                    $stmt = $db->prepare("UPDATE facilities SET name_ar = ?, name_en = ?, category_id = ?, description_ar = ?, description_en = ?, latitude = ?, longitude = ?, contact_person_ar = ?, contact_person_en = ?, phone = ?, email = ?, address_ar = ?, address_en = ?, working_hours_ar = ?, working_hours_en = ?, website = ?, image_path = ?, is_featured = ? WHERE id = ?");
                    $stmt->execute([$name_ar, $name_en, $category_id, $description_ar, $description_en, $latitude, $longitude, $contact_person_ar, $contact_person_en, $phone, $email, $address_ar, $address_en, $working_hours_ar, $working_hours_en, $website, $image_path, $is_featured, $facility_id]);
                    $success_message = "تم تحديث المرفق بنجاح";
                } catch (Exception $e) {
                    $error_message = "خطأ في تحديث المرفق: " . $e->getMessage();
                }
            }
        }
        
        elseif ($action == 'delete_facility') {
            $facility_id = $_POST['facility_id'];
            
            try {
                // جلب مسار الصورة قبل الحذف
                $stmt = $db->prepare("SELECT image_path FROM facilities WHERE id = ?");
                $stmt->execute([$facility_id]);
                $facility = $stmt->fetch();
                
                // حذف المرفق
                $stmt = $db->prepare("DELETE FROM facilities WHERE id = ?");
                $stmt->execute([$facility_id]);
                
                // حذف الصورة إن وجدت
                if ($facility && $facility['image_path']) {
                    $image_file = '../uploads/facilities/' . $facility['image_path'];
                    if (file_exists($image_file)) {
                        unlink($image_file);
                    }
                }
                
                $success_message = "تم حذف المرفق بنجاح";
            } catch (Exception $e) {
                $error_message = "خطأ في حذف المرفق: " . $e->getMessage();
            }
        }
        
        elseif ($action == 'toggle_status') {
            $facility_id = $_POST['facility_id'];
            $new_status = $_POST['new_status'];
            
            try {
                $stmt = $db->prepare("UPDATE facilities SET is_active = ? WHERE id = ?");
                $stmt->execute([$new_status, $facility_id]);
                
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit();
            } catch (Exception $e) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit();
            }
        }
    }
}

// جلب المرافق مع التصفح
$page = max(1, $_GET['page'] ?? 1);
$per_page = 15;
$offset = ($page - 1) * $per_page;

$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(f.name_ar LIKE ? OR f.name_en LIKE ? OR f.description_ar LIKE ? OR f.description_en LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter) {
    $where_conditions[] = "f.category_id = ?";
    $params[] = $category_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// جلب إجمالي العدد
$count_query = "SELECT COUNT(*) as total FROM facilities f $where_clause";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// جلب المرافق
$query = "
    SELECT 
        f.*,
        fc.name_ar as category_name_ar,
        fc.name_en as category_name_en,
        fc.icon as category_icon,
        fc.color as category_color,
        u.full_name as creator_name
    FROM facilities f 
    LEFT JOIN facility_categories fc ON f.category_id = fc.id
    LEFT JOIN users u ON f.created_by = u.id
    $where_clause
    ORDER BY f.is_featured DESC, f.created_at DESC 
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$facilities = $stmt->fetchAll();

// جلب الفئات للفلاتر والنماذج
$categories = $db->query("SELECT * FROM facility_categories WHERE is_active = 1 ORDER BY display_order, name_ar")->fetchAll();

// جلب إحصائيات سريعة
$stats = [
    'total_facilities' => $db->query("SELECT COUNT(*) as count FROM facilities WHERE is_active = 1")->fetch()['count'],
    'total_categories' => $db->query("SELECT COUNT(*) as count FROM facility_categories WHERE is_active = 1")->fetch()['count'],
    'featured_facilities' => $db->query("SELECT COUNT(*) as count FROM facilities WHERE is_featured = 1 AND is_active = 1")->fetch()['count'],
    'total_views' => $db->query("SELECT SUM(views_count) as total FROM facilities")->fetch()['total'] ?? 0
];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة خريطة المرافق والخدمات</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .image-preview { max-width: 80px; max-height: 80px; object-fit: cover; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-lg border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">🗺️ إدارة خريطة المرافق والخدمات</h1>
                    <p class="text-sm text-gray-500">إدارة مواقع المحلات والمؤسسات والخدمات</p>
                </div>
                <div class="flex items-center space-x-4 space-x-reverse">
                    <button onclick="showAddFacilityModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
                        ➕ إضافة مرفق جديد
                    </button>
                    <a href="../public/facilities-map.php" target="_blank" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                        🌍 عرض الخريطة العامة
                    </a>
                    <a href="../comprehensive_dashboard.php" class="text-gray-600 hover:text-gray-900">
                        🏠 العودة للوحة التحكم
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="text-3xl text-blue-500 ml-3">🏢</div>
                    <div>
                        <p class="text-sm text-gray-600">إجمالي المرافق</p>
                        <p class="text-2xl font-bold"><?= $stats['total_facilities'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="text-3xl text-green-500 ml-3">📂</div>
                    <div>
                        <p class="text-sm text-gray-600">الفئات</p>
                        <p class="text-2xl font-bold"><?= $stats['total_categories'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="text-3xl text-yellow-500 ml-3">⭐</div>
                    <div>
                        <p class="text-sm text-gray-600">مرافق مميزة</p>
                        <p class="text-2xl font-bold"><?= $stats['featured_facilities'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="text-3xl text-purple-500 ml-3">👁️</div>
                    <div>
                        <p class="text-sm text-gray-600">إجمالي المشاهدات</p>
                        <p class="text-2xl font-bold"><?= number_format($stats['total_views']) ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="bg-white p-6 rounded-lg shadow mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">البحث</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="البحث في الاسم أو الوصف..."
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">الفئة</label>
                    <select name="category" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        <option value="">جميع الفئات</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" <?= $category_filter == $category['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name_ar']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 ml-2">
                        🔍 بحث
                    </button>
                    <a href="?" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
                        🗑️ مسح
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Facilities List -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">المرفق</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">الفئة</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">الموقع</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">التواصل</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">الحالة</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($facilities as $facility): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <?php if ($facility['image_path']): ?>
                                            <img src="../uploads/facilities/<?= htmlspecialchars($facility['image_path']) ?>" 
                                                 alt="<?= htmlspecialchars($facility['name_ar']) ?>"
                                                 class="image-preview rounded-md ml-3">
                                        <?php else: ?>
                                            <div class="w-16 h-16 bg-gray-200 rounded-md flex items-center justify-center ml-3">
                                                <span class="text-gray-500 text-2xl">🏢</span>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($facility['name_ar']) ?>
                                            </div>
                                            <?php if ($facility['name_en']): ?>
                                                <div class="text-sm text-gray-500">
                                                    <?= htmlspecialchars($facility['name_en']) ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($facility['is_featured']): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 mt-1">
                                                    ⭐ مميز
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium text-white" 
                                          style="background-color: <?= htmlspecialchars($facility['category_color']) ?>">
                                        <?= htmlspecialchars($facility['category_name_ar']) ?>
                                    </span>
                                </td>
                                
                                <td class="px-6 py-4 text-center text-sm text-gray-500">
                                    <div><?= $facility['latitude'] ?>, <?= $facility['longitude'] ?></div>
                                    <?php if ($facility['address_ar']): ?>
                                        <div class="text-xs"><?= htmlspecialchars(substr($facility['address_ar'], 0, 30)) ?>...</div>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-6 py-4 text-center text-sm text-gray-500">
                                    <?php if ($facility['phone']): ?>
                                        <div>📞 <?= htmlspecialchars($facility['phone']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($facility['contact_person_ar']): ?>
                                        <div class="text-xs"><?= htmlspecialchars($facility['contact_person_ar']) ?></div>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-6 py-4 text-center">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" 
                                               class="form-checkbox h-5 w-5 text-indigo-600" 
                                               <?= $facility['is_active'] ? 'checked' : '' ?>
                                               onchange="toggleFacilityStatus(<?= $facility['id'] ?>, this.checked)">
                                        <span class="mr-2 text-sm">
                                            <?= $facility['is_active'] ? 'نشط' : 'معطل' ?>
                                        </span>
                                    </label>
                                </td>
                                
                                <td class="px-6 py-4 text-center">
                                    <div class="flex justify-center space-x-2 space-x-reverse">
                                        <button onclick="editFacility(<?= $facility['id'] ?>)" 
                                                class="text-indigo-600 hover:text-indigo-900 text-sm">
                                            ✏️ تعديل
                                        </button>
                                        <button onclick="viewOnMap(<?= $facility['latitude'] ?>, <?= $facility['longitude'] ?>)" 
                                                class="text-green-600 hover:text-green-900 text-sm">
                                            🗺️ الخريطة
                                        </button>
                                        <button onclick="deleteFacility(<?= $facility['id'] ?>)" 
                                                class="text-red-600 hover:text-red-900 text-sm">
                                            🗑️ حذف
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-8 flex justify-center">
                <nav class="flex items-center space-x-2 space-x-reverse">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $category_filter ? '&category=' . urlencode($category_filter) : '' ?>" 
                           class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            ← السابق
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $category_filter ? '&category=' . urlencode($category_filter) : '' ?>" 
                           class="px-3 py-2 border rounded-md <?= $i == $page ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white border-gray-300 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $category_filter ? '&category=' . urlencode($category_filter) : '' ?>" 
                           class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            التالي →
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Facility Modal -->
    <div id="addFacilityModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-screen overflow-y-auto">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-medium text-gray-900">إضافة مرفق جديد</h3>
                        <button type="button" onclick="closeAddFacilityModal()" class="text-gray-400 hover:text-gray-600">
                            <span class="sr-only">إغلاق</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <?php echo csrf_input('csrf_token'); ?>
                        <input type="hidden" name="action" value="add_facility">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">الاسم بالعربية *</label>
                                <input type="text" name="name_ar" required 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">الاسم بالإنجليزية</label>
                                <input type="text" name="name_en" 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">الفئة *</label>
                                <select name="category_id" required 
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">اختر الفئة</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name_ar']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">صورة المرفق</label>
                                <input type="file" name="facility_image" accept="image/*" 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">خط العرض (Latitude) *</label>
                                <input type="number" name="latitude" step="any" required 
                                       placeholder="33.8869"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <p class="text-xs text-gray-500 mt-1">يمكنك الحصول على الإحداثيات من Google Maps</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">خط الطول (Longitude) *</label>
                                <input type="number" name="longitude" step="any" required 
                                       placeholder="35.5131"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <p class="text-xs text-gray-500 mt-1">
                                    <a href="https://www.google.com/maps" target="_blank" class="text-indigo-600 hover:text-indigo-800">
                                        🗺️ فتح Google Maps للحصول على الإحداثيات
                                    </a>
                                </p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">الوصف بالعربية</label>
                                <textarea name="description_ar" rows="3" 
                                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">الوصف بالإنجليزية</label>
                                <textarea name="description_en" rows="3" 
                                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">جهة الاتصال (عربي)</label>
                                <input type="text" name="contact_person_ar" 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">جهة الاتصال (إنجليزي)</label>
                                <input type="text" name="contact_person_en" 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">رقم الهاتف</label>
                                <input type="text" name="phone" 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">البريد الإلكتروني</label>
                                <input type="email" name="email" 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">العنوان (عربي)</label>
                                <input type="text" name="address_ar" 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">العنوان (إنجليزي)</label>
                                <input type="text" name="address_en" 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">ساعات العمل (عربي)</label>
                                <input type="text" name="working_hours_ar" placeholder="من 9 صباحاً إلى 5 مساءً"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">ساعات العمل (إنجليزي)</label>
                                <input type="text" name="working_hours_en" placeholder="9 AM - 5 PM"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">الموقع الإلكتروني</label>
                                <input type="url" name="website" 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" name="is_featured" id="is_featured" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="is_featured" class="mr-2 block text-sm text-gray-900">مرفق مميز</label>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3 space-x-reverse pt-6 border-t">
                            <button type="button" onclick="closeAddFacilityModal()" 
                                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                إلغاء
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                إضافة المرفق
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Facility Modal -->
    <div id="editFacilityModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-screen overflow-y-auto">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-medium text-gray-900">تعديل المرفق</h3>
                        <button type="button" onclick="closeEditFacilityModal()" class="text-gray-400 hover:text-gray-600">
                            <span class="sr-only">إغلاق</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <?php echo csrf_input('csrf_token'); ?>
                        <input type="hidden" name="action" value="edit_facility">
                        <input type="hidden" name="facility_id" id="edit_facility_id">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">الاسم بالعربية *</label>
                                <input type="text" name="name_ar" id="edit_name_ar" required 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">الاسم بالإنجليزية</label>
                                <input type="text" name="name_en" id="edit_name_en"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">الفئة *</label>
                                <select name="category_id" id="edit_category_id" required 
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">اختر الفئة</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name_ar']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">صورة المرفق</label>
                                <div id="current_image_preview" class="mb-2"></div>
                                <input type="file" name="facility_image" accept="image/*" 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2">
                                <p class="text-xs text-gray-500 mt-1">اختر صورة جديدة لاستبدال الحالية</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">خط العرض (Latitude) *</label>
                                <input type="number" name="latitude" id="edit_latitude" step="any" required 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">خط الطول (Longitude) *</label>
                                <input type="number" name="longitude" id="edit_longitude" step="any" required 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">الوصف بالعربية</label>
                                <textarea name="description_ar" id="edit_description_ar" rows="3" 
                                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">الوصف بالإنجليزية</label>
                                <textarea name="description_en" id="edit_description_en" rows="3" 
                                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">جهة الاتصال (عربي)</label>
                                <input type="text" name="contact_person_ar" id="edit_contact_person_ar"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">جهة الاتصال (إنجليزي)</label>
                                <input type="text" name="contact_person_en" id="edit_contact_person_en"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">رقم الهاتف</label>
                                <input type="text" name="phone" id="edit_phone"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">البريد الإلكتروني</label>
                                <input type="email" name="email" id="edit_email"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">العنوان (عربي)</label>
                                <input type="text" name="address_ar" id="edit_address_ar"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">العنوان (إنجليزي)</label>
                                <input type="text" name="address_en" id="edit_address_en"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">ساعات العمل (عربي)</label>
                                <input type="text" name="working_hours_ar" id="edit_working_hours_ar"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">ساعات العمل (إنجليزي)</label>
                                <input type="text" name="working_hours_en" id="edit_working_hours_en"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">الموقع الإلكتروني</label>
                                <input type="url" name="website" id="edit_website"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" name="is_featured" id="edit_is_featured" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="edit_is_featured" class="mr-2 block text-sm text-gray-900">مرفق مميز</label>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3 space-x-reverse pt-6 border-t">
                            <button type="button" onclick="closeEditFacilityModal()" 
                                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                إلغاء
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                تحديث المرفق
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showAddFacilityModal() {
            document.getElementById('addFacilityModal').classList.remove('hidden');
        }

        function closeAddFacilityModal() {
            document.getElementById('addFacilityModal').classList.add('hidden');
        }

        function toggleFacilityStatus(facilityId, isActive) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=toggle_status&facility_id=${facilityId}&new_status=${isActive ? 1 : 0}`
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('خطأ في تحديث الحالة: ' + data.error);
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ في تحديث الحالة');
                location.reload();
            });
        }

        function editFacility(facilityId) {
            // جلب بيانات المرفق
            fetch(`facilities_api.php?action=get_facility_details&facility_id=${facilityId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.facility) {
                        populateEditForm(data.facility);
                        document.getElementById('editFacilityModal').classList.remove('hidden');
                    } else {
                        alert('خطأ في جلب بيانات المرفق: ' + (data.error || 'غير محدد'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('حدث خطأ في جلب بيانات المرفق');
                });
        }

        function populateEditForm(facility) {
            document.getElementById('edit_facility_id').value = facility.id;
            document.getElementById('edit_name_ar').value = facility.name_ar || '';
            document.getElementById('edit_name_en').value = facility.name_en || '';
            document.getElementById('edit_category_id').value = facility.category_id || '';
            document.getElementById('edit_description_ar').value = facility.description_ar || '';
            document.getElementById('edit_description_en').value = facility.description_en || '';
            document.getElementById('edit_latitude').value = facility.latitude || '';
            document.getElementById('edit_longitude').value = facility.longitude || '';
            document.getElementById('edit_contact_person_ar').value = facility.contact_person_ar || '';
            document.getElementById('edit_contact_person_en').value = facility.contact_person_en || '';
            document.getElementById('edit_phone').value = facility.phone || '';
            document.getElementById('edit_email').value = facility.email || '';
            document.getElementById('edit_address_ar').value = facility.address_ar || '';
            document.getElementById('edit_address_en').value = facility.address_en || '';
            document.getElementById('edit_working_hours_ar').value = facility.working_hours_ar || '';
            document.getElementById('edit_working_hours_en').value = facility.working_hours_en || '';
            document.getElementById('edit_website').value = facility.website || '';
            document.getElementById('edit_is_featured').checked = facility.is_featured == 1;
            
            // عرض الصورة الحالية إن وجدت
            const currentImageDiv = document.getElementById('current_image_preview');
            if (facility.image_path) {
                currentImageDiv.innerHTML = `
                    <img src="../uploads/facilities/${facility.image_path}" 
                         alt="الصورة الحالية" 
                         class="w-20 h-20 object-cover rounded-md">
                    <p class="text-xs text-gray-500 mt-1">الصورة الحالية</p>
                `;
            } else {
                currentImageDiv.innerHTML = '<p class="text-xs text-gray-500">لا توجد صورة</p>';
            }
        }

        function closeEditFacilityModal() {
            document.getElementById('editFacilityModal').classList.add('hidden');
        }

        function deleteFacility(facilityId) {
            if (confirm('هل أنت متأكد من حذف هذا المرفق؟ سيتم حذف جميع البيانات المرتبطة به.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_facility">
                    <input type="hidden" name="facility_id" value="${facilityId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function viewOnMap(lat, lng) {
            const url = `https://www.google.com/maps?q=${lat},${lng}`;
            window.open(url, '_blank');
        }

        // إغلاق المودال عند النقر خارجه
        document.getElementById('addFacilityModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddFacilityModal();
            }
        });
    </script>
</body>
</html> 