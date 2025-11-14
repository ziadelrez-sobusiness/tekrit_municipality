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

// معالجة إضافة شكوى جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_complaint'])) {
    $subject = trim($_POST['subject']);
    $details = trim($_POST['details']);
    $complainant_name = trim($_POST['complainant_name']);
    $complainant_phone = trim($_POST['complainant_phone']);
    $complainant_address = trim($_POST['complainant_address']);
    $category = $_POST['category'];
    $priority = $_POST['priority'];
    $assigned_department = $_POST['assigned_department'];
    
    if (!empty($subject) && !empty($details) && !empty($category)) {
        try {
            $query = "INSERT INTO complaints (subject, details, complainant_name, complainant_phone, complainant_address, category, priority, assigned_department, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'جديدة')";
            $stmt = $db->prepare($query);
            $stmt->execute([$subject, $details, $complainant_name, $complainant_phone, $complainant_address, $category, $priority, $assigned_department]);
            $message = 'تم إضافة الشكوى بنجاح!';
        } catch (PDOException $e) {
            $error = 'خطأ في إضافة الشكوى: ' . $e->getMessage();
        }
    } else {
        $error = 'يرجى تعبئة الحقول المطلوبة';
    }
}

// معالجة تحديث حالة الشكوى
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_complaint'])) {
    $complaint_id = intval($_POST['complaint_id']);
    $new_status = $_POST['new_status'];
    $response = trim($_POST['response']);
    $assigned_to = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
    
    try {
        $query = "UPDATE complaints SET status = ?, response = ?, assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$new_status, $response, $assigned_to, $complaint_id]);
        $message = 'تم تحديث حالة الشكوى بنجاح!';
    } catch (PDOException $e) {
        $error = 'خطأ في تحديث الشكوى: ' . $e->getMessage();
    }
}

// جلب الشكاوى
try {
    $filter_status = $_GET['status'] ?? '';
    $filter_category = $_GET['category'] ?? '';
    
    $where_conditions = [];
    $params = [];
    
    if (!empty($filter_status)) {
        $where_conditions[] = "c.status = ?";
        $params[] = $filter_status;
    }
    
    if (!empty($filter_category)) {
        $where_conditions[] = "c.category = ?";
        $params[] = $filter_category;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $stmt = $db->prepare("
        SELECT c.*, u.full_name as assigned_name 
        FROM complaints c 
        LEFT JOIN users u ON c.assigned_to = u.id 
        $where_clause
        ORDER BY c.created_at DESC 
        LIMIT 50
    ");
    $stmt->execute($params);
    $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب إحصائيات الشكاوى
    $stmt = $db->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM complaints 
        GROUP BY status
    ");
    $status_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // جلب الموظفين للتوزيع
    $stmt = $db->query("SELECT id, full_name, department FROM users WHERE is_active = 1 ORDER BY full_name");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $complaints = [];
    $status_stats = [];
    $employees = [];
}

$departments = ['الهندسة', 'النظافة', 'الصيانة', 'المياه', 'الكهرباء', 'خدمة المواطنين'];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الشكاوى - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <h1 class="text-3xl font-bold text-slate-800">إدارة الشكاوى</h1>
                <div class="flex gap-3">
                    <button onclick="openModal('addComplaintModal')" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        ➕ إضافة شكوى جديدة
                    </button>
                    <a href="../comprehensive_dashboard.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                        ← العودة للوحة التحكم
                    </a>
                </div>
            </div>
            <p class="text-slate-600 mt-2">إدارة شكاوى المواطنين ومتابعة حلولها</p>
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

        <!-- إحصائيات الشكاوى -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">شكاوى جديدة</p>
                        <p class="text-2xl font-bold text-red-600"><?= $status_stats['جديدة'] ?? 0 ?></p>
                    </div>
                    <div class="bg-red-100 text-red-600 p-3 rounded-full">📢</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">قيد المعالجة</p>
                        <p class="text-2xl font-bold text-yellow-600"><?= $status_stats['قيد المعالجة'] ?? 0 ?></p>
                    </div>
                    <div class="bg-yellow-100 text-yellow-600 p-3 rounded-full">⏳</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">مكتملة</p>
                        <p class="text-2xl font-bold text-green-600"><?= $status_stats['مكتملة'] ?? 0 ?></p>
                    </div>
                    <div class="bg-green-100 text-green-600 p-3 rounded-full">✅</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">مؤجلة</p>
                        <p class="text-2xl font-bold text-gray-600"><?= $status_stats['مؤجلة'] ?? 0 ?></p>
                    </div>
                    <div class="bg-gray-100 text-gray-600 p-3 rounded-full">⏸️</div>
                </div>
            </div>
        </div>

        <!-- فلاتر البحث -->
        <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
            <h3 class="font-semibold mb-4">فلترة الشكاوى</h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الحالة</label>
                    <select name="status" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">جميع الحالات</option>
                        <option value="جديدة" <?= ($filter_status === 'جديدة') ? 'selected' : '' ?>>جديدة</option>
                        <option value="قيد المعالجة" <?= ($filter_status === 'قيد المعالجة') ? 'selected' : '' ?>>قيد المعالجة</option>
                        <option value="مكتملة" <?= ($filter_status === 'مكتملة') ? 'selected' : '' ?>>مكتملة</option>
                        <option value="مؤجلة" <?= ($filter_status === 'مؤجلة') ? 'selected' : '' ?>>مؤجلة</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الفئة</label>
                    <select name="category" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">جميع الفئات</option>
                        <option value="نفايات" <?= ($filter_category === 'نفايات') ? 'selected' : '' ?>>نفايات</option>
                        <option value="طرق" <?= ($filter_category === 'طرق') ? 'selected' : '' ?>>طرق</option>
                        <option value="مياه" <?= ($filter_category === 'مياه') ? 'selected' : '' ?>>مياه</option>
                        <option value="إنارة" <?= ($filter_category === 'إنارة') ? 'selected' : '' ?>>إنارة</option>
                        <option value="صيانة" <?= ($filter_category === 'صيانة') ? 'selected' : '' ?>>صيانة</option>
                        <option value="أخرى" <?= ($filter_category === 'أخرى') ? 'selected' : '' ?>>أخرى</option>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                        تطبيق الفلتر
                    </button>
                </div>
            </form>
        </div>

        <!-- جدول الشكاوى -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold">قائمة الشكاوى</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th class="px-6 py-3">الرقم</th>
                            <th class="px-6 py-3">الموضوع</th>
                            <th class="px-6 py-3">اسم المشتكي</th>
                            <th class="px-6 py-3">الفئة</th>
                            <th class="px-6 py-3">الأولوية</th>
                            <th class="px-6 py-3">الحالة</th>
                            <th class="px-6 py-3">مسند إلى</th>
                            <th class="px-6 py-3">التاريخ</th>
                            <th class="px-6 py-3">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($complaints as $complaint): ?>
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium">#<?= $complaint['id'] ?></td>
                                <td class="px-6 py-4" title="<?= htmlspecialchars($complaint['subject']) ?>">
                                    <?= mb_substr(htmlspecialchars($complaint['subject']), 0, 30) ?>...
                                </td>
                                <td class="px-6 py-4"><?= htmlspecialchars($complaint['complainant_name'] ?? 'غير محدد') ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars($complaint['category']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded 
                                        <?= $complaint['priority'] === 'عالية' ? 'bg-red-100 text-red-800' : 
                                           ($complaint['priority'] === 'متوسطة' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') ?>">
                                        <?= htmlspecialchars($complaint['priority']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded 
                                        <?= $complaint['status'] === 'جديدة' ? 'bg-red-100 text-red-800' : 
                                           ($complaint['status'] === 'قيد المعالجة' ? 'bg-yellow-100 text-yellow-800' : 
                                           ($complaint['status'] === 'مكتملة' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800')) ?>">
                                        <?= htmlspecialchars($complaint['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4"><?= htmlspecialchars($complaint['assigned_name'] ?? 'غير محدد') ?></td>
                                <td class="px-6 py-4"><?= date('Y-m-d', strtotime($complaint['created_at'])) ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex gap-2">
                                        <button onclick="viewComplaint(<?= $complaint['id'] ?>)" 
                                                class="bg-blue-100 text-blue-600 px-2 py-1 rounded text-xs hover:bg-blue-200">
                                            عرض
                                        </button>
                                        <button onclick="updateComplaint(<?= $complaint['id'] ?>)" 
                                                class="bg-yellow-100 text-yellow-600 px-2 py-1 rounded text-xs hover:bg-yellow-200">
                                            تحديث
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($complaints)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-8 text-gray-500">
                                    لا توجد شكاوى مطابقة للفلتر المحدد
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal إضافة شكوى جديدة -->
    <div id="addComplaintModal" class="modal fixed inset-0 bg-black bg-opacity-50 justify-center items-center z-50">
        <div class="bg-white p-6 rounded-lg max-w-2xl w-full mx-4 max-h-96 overflow-y-auto">
            <h3 class="text-xl font-semibold mb-4">إضافة شكوى جديدة</h3>
            
            <form method="POST" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">موضوع الشكوى *</label>
                        <input type="text" name="subject" required 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الفئة *</label>
                        <select name="category" required 
                                class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">اختر الفئة</option>
                            <option value="نفايات">نفايات</option>
                            <option value="طرق">طرق</option>
                            <option value="مياه">مياه</option>
                            <option value="إنارة">إنارة</option>
                            <option value="صيانة">صيانة</option>
                            <option value="أخرى">أخرى</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تفاصيل الشكوى *</label>
                    <textarea name="details" required rows="3"
                              class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">اسم المشتكي</label>
                        <input type="text" name="complainant_name" 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                        <input type="tel" name="complainant_phone" 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">العنوان</label>
                    <textarea name="complainant_address" rows="2"
                              class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الأولوية</label>
                        <select name="priority" class="w-full p-2 border border-gray-300 rounded-md">
                            <option value="منخفضة">منخفضة</option>
                            <option value="متوسطة" selected>متوسطة</option>
                            <option value="عالية">عالية</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">القسم المختص</label>
                        <select name="assigned_department" class="w-full p-2 border border-gray-300 rounded-md">
                            <option value="">اختر القسم</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept ?>"><?= $dept ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="flex gap-4 pt-4">
                    <button type="submit" name="add_complaint" 
                            class="flex-1 bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition">
                        إضافة الشكوى
                    </button>
                    <button type="button" onclick="closeModal('addComplaintModal')" 
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
        
        function viewComplaint(id) {
            // إضافة منطق عرض تفاصيل الشكوى
            alert('عرض تفاصيل الشكوى #' + id);
        }
        
        function updateComplaint(id) {
            // إضافة منطق تحديث الشكوى
            alert('تحديث الشكوى #' + id);
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
