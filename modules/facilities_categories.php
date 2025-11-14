<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// التحقق من الصلاحيات
$auth->requireLogin();
if (!$auth->checkPermission('admin')) {
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
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add_category') {
        $name_ar = trim($_POST['name_ar']);
        $name_en = trim($_POST['name_en']);
        $icon = trim($_POST['icon']);
        $color = trim($_POST['color']);
        $description_ar = trim($_POST['description_ar']);
        $description_en = trim($_POST['description_en']);
        $display_order = intval($_POST['display_order']);
        
        if (!empty($name_ar) && !empty($icon) && !empty($color)) {
            try {
                $stmt = $db->prepare("INSERT INTO facility_categories (name_ar, name_en, icon, color, description_ar, description_en, display_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name_ar, $name_en, $icon, $color, $description_ar, $description_en, $display_order]);
                $success_message = "تم إضافة الفئة بنجاح";
            } catch (Exception $e) {
                $error_message = "خطأ في إضافة الفئة: " . $e->getMessage();
            }
        } else {
            $error_message = "يرجى ملء الحقول الإجبارية";
        }
    }
    
    elseif ($action == 'edit_category') {
        $category_id = $_POST['category_id'];
        $name_ar = trim($_POST['name_ar']);
        $name_en = trim($_POST['name_en']);
        $icon = trim($_POST['icon']);
        $color = trim($_POST['color']);
        $description_ar = trim($_POST['description_ar']);
        $description_en = trim($_POST['description_en']);
        $display_order = intval($_POST['display_order']);
        
        if (!empty($name_ar) && !empty($icon) && !empty($color)) {
            try {
                $stmt = $db->prepare("UPDATE facility_categories SET name_ar = ?, name_en = ?, icon = ?, color = ?, description_ar = ?, description_en = ?, display_order = ? WHERE id = ?");
                $stmt->execute([$name_ar, $name_en, $icon, $color, $description_ar, $description_en, $display_order, $category_id]);
                $success_message = "تم تحديث الفئة بنجاح";
            } catch (Exception $e) {
                $error_message = "خطأ في تحديث الفئة: " . $e->getMessage();
            }
        } else {
            $error_message = "يرجى ملء الحقول الإجبارية";
        }
    }
    
    elseif ($action == 'delete_category') {
        $category_id = $_POST['category_id'];
        
        try {
            // التحقق من وجود مرافق مرتبطة بهذه الفئة
            $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM facilities WHERE category_id = ?");
            $check_stmt->execute([$category_id]);
            $facility_count = $check_stmt->fetch()['count'];
            
            if ($facility_count > 0) {
                $error_message = "لا يمكن حذف هذه الفئة لأنها مرتبطة بـ $facility_count مرفق";
            } else {
                $stmt = $db->prepare("DELETE FROM facility_categories WHERE id = ?");
                $stmt->execute([$category_id]);
                $success_message = "تم حذف الفئة بنجاح";
            }
        } catch (Exception $e) {
            $error_message = "خطأ في حذف الفئة: " . $e->getMessage();
        }
    }
    
    elseif ($action == 'toggle_status') {
        $category_id = $_POST['category_id'];
        $new_status = $_POST['new_status'];
        
        try {
            $stmt = $db->prepare("UPDATE facility_categories SET is_active = ? WHERE id = ?");
            $stmt->execute([$new_status, $category_id]);
            
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

// جلب الفئات
$categories = $db->query("
    SELECT 
        fc.*,
        COUNT(f.id) as facility_count
    FROM facility_categories fc
    LEFT JOIN facilities f ON fc.id = f.category_id AND f.is_active = 1
    GROUP BY fc.id
    ORDER BY fc.display_order, fc.name_ar
")->fetchAll();

// قائمة الأيقونات المتاحة
$available_icons = [
    'school' => 'مدرسة',
    'mosque' => 'مسجد',
    'hospital' => 'مستشفى',
    'store' => 'متجر',
    'restaurant' => 'مطعم',
    'government' => 'حكومي',
    'bank' => 'بنك',
    'gas-station' => 'محطة وقود',
    'park' => 'حديقة',
    'sports' => 'رياضة',
    'pharmacy' => 'صيدلية',
    'hotel' => 'فندق',
    'service' => 'خدمة',
    'parking' => 'موقف',
    'market' => 'سوق',
    'library' => 'مكتبة',
    'cinema' => 'سينما',
    'shopping' => 'تسوق',
    'office' => 'مكتب',
    'factory' => 'مصنع'
];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة فئات المرافق</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .color-preview { width: 30px; height: 30px; border-radius: 50%; border: 2px solid #ddd; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-lg border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">📂 إدارة فئات المرافق</h1>
                    <p class="text-sm text-gray-500">إدارة أنواع المرافق والخدمات</p>
                </div>
                <div class="flex items-center space-x-4 space-x-reverse">
                    <button onclick="showAddCategoryModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
                        ➕ إضافة فئة جديدة
                    </button>
                    <a href="facilities_management.php" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                        🏢 إدارة المرافق
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
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="text-3xl text-blue-500 ml-3">📂</div>
                    <div>
                        <p class="text-sm text-gray-600">إجمالي الفئات</p>
                        <p class="text-2xl font-bold"><?= count($categories) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="text-3xl text-green-500 ml-3">✅</div>
                    <div>
                        <p class="text-sm text-gray-600">الفئات النشطة</p>
                        <p class="text-2xl font-bold"><?= count(array_filter($categories, fn($c) => $c['is_active'] == 1)) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="text-3xl text-purple-500 ml-3">🏢</div>
                    <div>
                        <p class="text-sm text-gray-600">إجمالي المرافق</p>
                        <p class="text-2xl font-bold"><?= array_sum(array_column($categories, 'facility_count')) ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Categories List -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-900">قائمة الفئات</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الترتيب</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الفئة</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">اللون والأيقونة</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">عدد المرافق</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">الحالة</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($categories as $category): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <span class="bg-gray-100 px-2 py-1 rounded text-xs">
                                        <?= $category['display_order'] ?>
                                    </span>
                                </td>
                                
                                <td class="px-6 py-4">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($category['name_ar']) ?>
                                        </div>
                                        <?php if ($category['name_en']): ?>
                                            <div class="text-sm text-gray-500">
                                                <?= htmlspecialchars($category['name_en']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($category['description_ar']): ?>
                                            <div class="text-xs text-gray-400 mt-1">
                                                <?= htmlspecialchars(substr($category['description_ar'], 0, 50)) ?>...
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4 text-center">
                                    <div class="flex items-center justify-center space-x-2 space-x-reverse">
                                        <div class="color-preview" style="background-color: <?= htmlspecialchars($category['color']) ?>"></div>
                                        <span class="text-sm text-gray-600"><?= htmlspecialchars($category['icon']) ?></span>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4 text-center">
                                    <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs font-medium">
                                        <?= $category['facility_count'] ?>
                                    </span>
                                </td>
                                
                                <td class="px-6 py-4 text-center">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" 
                                               class="form-checkbox h-5 w-5 text-indigo-600" 
                                               <?= $category['is_active'] ? 'checked' : '' ?>
                                               onchange="toggleCategoryStatus(<?= $category['id'] ?>, this.checked)">
                                        <span class="mr-2 text-sm">
                                            <?= $category['is_active'] ? 'نشط' : 'معطل' ?>
                                        </span>
                                    </label>
                                </td>
                                
                                <td class="px-6 py-4 text-center">
                                    <div class="flex justify-center space-x-2 space-x-reverse">
                                        <button onclick="editCategory(<?= htmlspecialchars(json_encode($category)) ?>)" 
                                                class="text-indigo-600 hover:text-indigo-900 text-sm">
                                            ✏️ تعديل
                                        </button>
                                        <?php if ($category['facility_count'] == 0): ?>
                                            <button onclick="deleteCategory(<?= $category['id'] ?>)" 
                                                    class="text-red-600 hover:text-red-900 text-sm">
                                                🗑️ حذف
                                            </button>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-sm">🗑️ حذف</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div id="addCategoryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-medium text-gray-900">إضافة فئة جديدة</h3>
                        <button type="button" onclick="closeAddCategoryModal()" class="text-gray-400 hover:text-gray-600">
                            <span class="sr-only">إغلاق</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add_category">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                                <label class="block text-sm font-medium text-gray-700 mb-2">الأيقونة *</label>
                                <select name="icon" required 
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">اختر الأيقونة</option>
                                    <?php foreach ($available_icons as $icon => $label): ?>
                                        <option value="<?= $icon ?>"><?= $label ?> (<?= $icon ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">اللون *</label>
                                <input type="color" name="color" value="#3498db" required 
                                       class="w-full h-10 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">ترتيب العرض</label>
                                <input type="number" name="display_order" value="0" min="0"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                        
                        <div class="flex justify-end space-x-3 space-x-reverse pt-6 border-t">
                            <button type="button" onclick="closeAddCategoryModal()" 
                                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                إلغاء
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                إضافة الفئة
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div id="editCategoryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-medium text-gray-900">تعديل الفئة</h3>
                        <button type="button" onclick="closeEditCategoryModal()" class="text-gray-400 hover:text-gray-600">
                            <span class="sr-only">إغلاق</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="edit_category">
                        <input type="hidden" name="category_id" id="edit_category_id">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                                <label class="block text-sm font-medium text-gray-700 mb-2">الأيقونة *</label>
                                <select name="icon" id="edit_icon" required 
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    <?php foreach ($available_icons as $icon => $label): ?>
                                        <option value="<?= $icon ?>"><?= $label ?> (<?= $icon ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">اللون *</label>
                                <input type="color" name="color" id="edit_color" required 
                                       class="w-full h-10 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">ترتيب العرض</label>
                                <input type="number" name="display_order" id="edit_display_order" min="0"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                        
                        <div class="flex justify-end space-x-3 space-x-reverse pt-6 border-t">
                            <button type="button" onclick="closeEditCategoryModal()" 
                                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                إلغاء
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                حفظ التغييرات
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showAddCategoryModal() {
            document.getElementById('addCategoryModal').classList.remove('hidden');
        }

        function closeAddCategoryModal() {
            document.getElementById('addCategoryModal').classList.add('hidden');
        }

        function editCategory(category) {
            // ملء البيانات في النموذج
            document.getElementById('edit_category_id').value = category.id;
            document.getElementById('edit_name_ar').value = category.name_ar;
            document.getElementById('edit_name_en').value = category.name_en || '';
            document.getElementById('edit_icon').value = category.icon;
            document.getElementById('edit_color').value = category.color;
            document.getElementById('edit_display_order').value = category.display_order;
            document.getElementById('edit_description_ar').value = category.description_ar || '';
            document.getElementById('edit_description_en').value = category.description_en || '';
            
            // إظهار النافذة المنبثقة
            document.getElementById('editCategoryModal').classList.remove('hidden');
        }

        function closeEditCategoryModal() {
            document.getElementById('editCategoryModal').classList.add('hidden');
        }

        function toggleCategoryStatus(categoryId, isActive) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=toggle_status&category_id=${categoryId}&new_status=${isActive ? 1 : 0}`
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

        function deleteCategory(categoryId) {
            if (confirm('هل أنت متأكد من حذف هذه الفئة؟')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_category">
                    <input type="hidden" name="category_id" value="${categoryId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // إغلاق النوافذ المنبثقة عند النقر خارجها
        document.getElementById('addCategoryModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddCategoryModal();
            }
        });

        document.getElementById('editCategoryModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditCategoryModal();
            }
        });
    </script>
</body>
</html> 