<?php
/**
 * نظام إدارة الأخبار المحدث
 * يدعم النظام الجديد للصور المنفصلة
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'news_image_manager.php';

// التحقق من الصلاحيات
$auth->requireLogin();
if (!$auth->checkPermission('employee')) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

$imageManager = new NewsImageManager($db);

$success_message = '';
$error_message = '';

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add_news') {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $news_type = $_POST['news_type'];
        $publish_date = $_POST['publish_date'];
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        
        if (!empty($title) && !empty($content)) {
            try {
                $db->beginTransaction();
                
                // إضافة الخبر
                $stmt = $db->prepare("INSERT INTO news_activities (title, content, news_type, publish_date, is_featured, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $content, $news_type, $publish_date, $is_featured, $auth->getCurrentUser()['id']]);
                $news_id = $db->lastInsertId();
                
                $messages = [];
                
                // رفع الصورة الرئيسية
                if (!empty($_FILES['featured_image']['name'])) {
                    $result = $imageManager->uploadFeaturedImage($_FILES['featured_image'], $news_id);
                    if (!$result['success']) {
                        $messages[] = "فشل رفع الصورة الرئيسية: " . $result['error'];
                    }
                }
                
                // رفع صور المعرض
                if (!empty($_FILES['gallery_images']['name'][0])) {
                    $result = $imageManager->uploadGalleryImages($_FILES['gallery_images'], $news_id, $auth->getCurrentUser()['id']);
                    if (!empty($result['errors'])) {
                        $messages[] = "أخطاء في صور المعرض: " . implode(', ', $result['errors']);
                    }
                    if ($result['total_uploaded'] > 0) {
                        $messages[] = "تم رفع {$result['total_uploaded']} صورة للمعرض";
                    }
                }
                
                $db->commit();
                $success_message = "تم إضافة الخبر بنجاح" . (empty($messages) ? "" : ". " . implode(". ", $messages));
            } catch (Exception $e) {
                $db->rollBack();
                $error_message = "خطأ في إضافة الخبر: " . $e->getMessage();
            }
        }
    }
    
    elseif ($action == 'edit_news') {
        $news_id = $_POST['news_id'];
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $news_type = $_POST['news_type'];
        $publish_date = $_POST['publish_date'];
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        
        if (!empty($title) && !empty($content)) {
            try {
                $db->beginTransaction();
                
                // تحديث بيانات الخبر
                $stmt = $db->prepare("UPDATE news_activities SET title = ?, content = ?, news_type = ?, publish_date = ?, is_featured = ? WHERE id = ?");
                $stmt->execute([$title, $content, $news_type, $publish_date, $is_featured, $news_id]);
                
                $messages = [];
                
                // تحديث الصورة الرئيسية
                if (!empty($_FILES['featured_image']['name'])) {
                    $result = $imageManager->uploadFeaturedImage($_FILES['featured_image'], $news_id);
                    if (!$result['success']) {
                        $messages[] = "فشل تحديث الصورة الرئيسية: " . $result['error'];
                    }
                }
                
                // إضافة صور جديدة للمعرض
                if (!empty($_FILES['gallery_images']['name'][0])) {
                    $result = $imageManager->uploadGalleryImages($_FILES['gallery_images'], $news_id, $auth->getCurrentUser()['id']);
                    if (!empty($result['errors'])) {
                        $messages[] = "أخطاء في صور المعرض: " . implode(', ', $result['errors']);
                    }
                    if ($result['total_uploaded'] > 0) {
                        $messages[] = "تم رفع {$result['total_uploaded']} صورة جديدة";
                    }
                }
                
                // حذف الصور المحددة
                if (!empty($_POST['delete_gallery_images'])) {
                    $deleted_count = 0;
                    foreach ($_POST['delete_gallery_images'] as $image_id) {
                        $result = $imageManager->deleteImage($image_id, $auth->getCurrentUser()['id']);
                        if ($result['success']) {
                            $deleted_count++;
                        }
                    }
                    if ($deleted_count > 0) {
                        $messages[] = "تم حذف $deleted_count صورة";
                    }
                }
                
                $db->commit();
                $success_message = "تم تحديث الخبر بنجاح" . (empty($messages) ? "" : ". " . implode(". ", $messages));
            } catch (Exception $e) {
                $db->rollBack();
                $error_message = "خطأ في تحديث الخبر: " . $e->getMessage();
            }
        }
    }
    
    elseif ($action == 'delete_news') {
        $news_id = $_POST['news_id'];
        
        try {
            $db->beginTransaction();
            
            // جلب صور الخبر لحذفها
            $images = $imageManager->getNewsImages($news_id);
            
            // حذف الصورة الرئيسية
            if ($images['featured']) {
                $file_path = '../uploads/news/' . $images['featured'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            
            // حذف صور المعرض
            foreach ($images['gallery'] as $image) {
                $imageManager->deleteImage($image['id'], $auth->getCurrentUser()['id']);
            }
            
            // حذف الخبر
            $stmt = $db->prepare("DELETE FROM news_activities WHERE id = ?");
            $stmt->execute([$news_id]);
            
            $db->commit();
            $success_message = "تم حذف الخبر بنجاح";
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = "خطأ في حذف الخبر: " . $e->getMessage();
        }
    }
    
    elseif ($action == 'delete_image') {
        $image_id = $_POST['image_id'];
        
        $result = $imageManager->deleteImage($image_id, $auth->getCurrentUser()['id']);
        
        header('Content-Type: application/json');
        echo json_encode($result);
        exit();
    }
    
    elseif ($action == 'update_image_order') {
        $news_id = $_POST['news_id'];
        $image_orders = json_decode($_POST['image_orders'], true);
        
        $result = $imageManager->updateImageOrder($news_id, $image_orders);
        
        header('Content-Type: application/json');
        echo json_encode($result);
        exit();
    }
    
    elseif ($action == 'update_image_info') {
        $image_id = $_POST['image_id'];
        $title = trim($_POST['image_title']);
        $description = trim($_POST['image_description']);
        
        $result = $imageManager->updateImageInfo($image_id, $title, $description);
        
        header('Content-Type: application/json');
        echo json_encode($result);
        exit();
    }
}

// جلب الأخبار
$page = max(1, $_GET['page'] ?? 1);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// إحصاءات النظام
$stats = $imageManager->getStatistics();

$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(title LIKE ? OR content LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($type_filter) {
    $where_conditions[] = "news_type = ?";
    $params[] = $type_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// جلب إجمالي العدد
$count_query = "SELECT COUNT(*) as total FROM news_activities $where_clause";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// جلب الأخبار مع معلومات الصور
$query = "
    SELECT 
        n.*,
        u.full_name as creator_name,
        (SELECT COUNT(*) FROM news_images ni WHERE ni.news_id = n.id AND ni.is_active = 1) as gallery_count
    FROM news_activities n 
    LEFT JOIN users u ON n.created_by = u.id 
    $where_clause
    ORDER BY n.created_at DESC 
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$news = $stmt->fetchAll();

// أنواع الأخبار
$news_types = $db->query("SELECT DISTINCT news_type FROM news_activities ORDER BY news_type")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الأخبار - نظام محدث</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .sortable { cursor: move; }
        .image-preview { max-width: 100px; max-height: 100px; object-fit: cover; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-lg border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">إدارة الأخبار</h1>
                    <p class="text-sm text-gray-500">النظام المحدث مع الصور المنفصلة</p>
                </div>
                <div class="flex items-center space-x-4 space-x-reverse">
                    <button onclick="showAddNewsModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
                        ➕ إضافة خبر جديد
                    </button>
                    <a href="../admin/dashboard.php" class="text-gray-600 hover:text-gray-900">
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
                    <div class="text-3xl text-blue-500 ml-3">📰</div>
                    <div>
                        <p class="text-sm text-gray-600">إجمالي الأخبار</p>
                        <p class="text-2xl font-bold"><?= $total_records ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="text-3xl text-green-500 ml-3">🖼️</div>
                    <div>
                        <p class="text-sm text-gray-600">إجمالي الصور</p>
                        <p class="text-2xl font-bold"><?= $stats['total_images'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="text-3xl text-yellow-500 ml-3">📸</div>
                    <div>
                        <p class="text-sm text-gray-600">أخبار لها صور</p>
                        <p class="text-2xl font-bold"><?= $stats['news_with_featured'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="text-3xl text-purple-500 ml-3">💾</div>
                    <div>
                        <p class="text-sm text-gray-600">حجم الملفات</p>
                        <p class="text-2xl font-bold"><?= round($stats['total_size'] / 1024 / 1024, 1) ?> MB</p>
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
                           placeholder="البحث في العنوان أو المحتوى..."
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">نوع الخبر</label>
                    <select name="type" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        <option value="">جميع الأنواع</option>
                        <?php foreach ($news_types as $type): ?>
                            <option value="<?= htmlspecialchars($type) ?>" <?= $type_filter == $type ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type) ?>
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
        
        <!-- News List -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">العنوان</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">النوع</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">الصورة الرئيسية</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">صور المعرض</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">تاريخ النشر</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">المشاهدات</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($news as $item): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <?php if ($item['featured_image']): ?>
                                            <img src="../uploads/news/<?= htmlspecialchars($item['featured_image']) ?>" 
                                                 alt="<?= htmlspecialchars($item['title']) ?>"
                                                 class="image-preview rounded-md ml-3">
                                        <?php else: ?>
                                            <div class="w-16 h-16 bg-gray-200 rounded-md flex items-center justify-center ml-3">
                                                <span class="text-gray-500 text-2xl">📰</span>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($item['title']) ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?= htmlspecialchars(substr($item['content'], 0, 100)) ?>...
                                            </div>
                                            <?php if ($item['is_featured']): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 mt-1">
                                                    ⭐ مميز
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                        <?= htmlspecialchars($item['news_type']) ?>
                                    </span>
                                </td>
                                
                                <td class="px-6 py-4 text-center">
                                    <?php if ($item['featured_image']): ?>
                                        <span class="text-green-500">✅ موجودة</span>
                                    <?php else: ?>
                                        <span class="text-gray-400">❌ غير موجودة</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-6 py-4 text-center">
                                    <?php if ($item['gallery_count'] > 0): ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            🖼️ <?= $item['gallery_count'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400">لا توجد</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-6 py-4 text-center text-sm text-gray-500">
                                    <?= date('Y/m/d', strtotime($item['publish_date'])) ?>
                                </td>
                                
                                <td class="px-6 py-4 text-center text-sm text-gray-500">
                                    <?= number_format($item['views_count']) ?>
                                </td>
                                
                                <td class="px-6 py-4 text-center">
                                    <div class="flex justify-center space-x-2 space-x-reverse">
                                        <button onclick="editNews(<?= $item['id'] ?>)" 
                                                class="text-indigo-600 hover:text-indigo-900 text-sm">
                                            ✏️ تعديل
                                        </button>
                                        <button onclick="manageImages(<?= $item['id'] ?>)" 
                                                class="text-green-600 hover:text-green-900 text-sm">
                                            🖼️ الصور
                                        </button>
                                        <button onclick="deleteNews(<?= $item['id'] ?>)" 
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
                        <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $type_filter ? '&type=' . urlencode($type_filter) : '' ?>" 
                           class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            ← السابق
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $type_filter ? '&type=' . urlencode($type_filter) : '' ?>" 
                           class="px-3 py-2 border rounded-md <?= $i == $page ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white border-gray-300 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $type_filter ? '&type=' . urlencode($type_filter) : '' ?>" 
                           class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            التالي →
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modals here... -->
    <!-- سأقوم بإضافة المودالز في الجزء التالي -->
    
    <script>
        // JavaScript functions here...
        function editNews(id) {
            // تحميل بيانات الخبر وعرض مودال التعديل
            console.log('Edit news:', id);
        }
        
        function manageImages(id) {
            // عرض مودال إدارة الصور
            console.log('Manage images for news:', id);
        }
        
        function deleteNews(id) {
            if (confirm('هل أنت متأكد من حذف هذا الخبر؟ سيتم حذف جميع الصور المرتبطة به.')) {
                // حذف الخبر
                console.log('Delete news:', id);
            }
        }
        
        function showAddNewsModal() {
            // عرض مودال إضافة خبر جديد
            console.log('Show add news modal');
        }
    </script>
</body>
</html> 