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

// إنشاء مجلد الرفع إذا لم يكن موجوداً
$upload_dir = '../uploads/documents/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// معالجة رفع ملف جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = $_POST['category'];
    $department = $_POST['department'];
    $access_level = $_POST['access_level'];
    $tags = trim($_POST['tags']);
    
    if (!empty($title) && !empty($category) && isset($_FILES['document_file'])) {
        $file = $_FILES['document_file'];
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'jpg', 'png'];
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_extension, $allowed_types)) {
                $file_name = time() . '_' . uniqid() . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    try {
                        $query = "INSERT INTO documents (title, description, file_name, file_path, file_size, file_type, category, department, uploaded_by_user_id, access_level, tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $db->prepare($query);
                        $stmt->execute([
                            $title, 
                            $description, 
                            $file['name'], 
                            $file_path, 
                            $file['size'], 
                            $file_extension, 
                            $category, 
                            $department, 
                            $user['id'], 
                            $access_level, 
                            $tags
                        ]);
                        $message = 'تم رفع الملف بنجاح!';
                    } catch (PDOException $e) {
                        $error = 'خطأ في حفظ بيانات الملف: ' . $e->getMessage();
                        unlink($file_path); // حذف الملف في حالة فشل حفظ البيانات
                    }
                } else {
                    $error = 'خطأ في رفع الملف';
                }
            } else {
                $error = 'نوع الملف غير مدعوم. الأنواع المدعومة: PDF, DOC, DOCX, XLS, XLSX, TXT, JPG, PNG';
            }
        } else {
            $error = 'خطأ في رفع الملف: ' . $file['error'];
        }
    } else {
        $error = 'يرجى تعبئة الحقول المطلوبة واختيار ملف';
    }
}

// جلب الوثائق
try {
    $search = $_GET['search'] ?? '';
    $filter_category = $_GET['category'] ?? '';
    $filter_department = $_GET['department'] ?? '';
    
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(d.title LIKE ? OR d.description LIKE ? OR d.tags LIKE ?)";
        $search_term = "%$search%";
        $params = array_merge($params, [$search_term, $search_term, $search_term]);
    }
    
    if (!empty($filter_category)) {
        $where_conditions[] = "d.category = ?";
        $params[] = $filter_category;
    }
    
    if (!empty($filter_department)) {
        $where_conditions[] = "d.department = ?";
        $params[] = $filter_department;
    }
    
    // إضافة فلتر الصلاحيات (غير عرض الملفات السرية إلا للإداريين)
    if ($user['user_type'] !== 'admin') {
        $where_conditions[] = "d.access_level != 'سري'";
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $stmt = $db->prepare("
        SELECT d.*, u.full_name as uploaded_by_name 
        FROM documents d 
        LEFT JOIN users u ON d.uploaded_by_user_id = u.id 
        $where_clause
        ORDER BY d.created_at DESC 
        LIMIT 50
    ");
    $stmt->execute($params);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب إحصائيات الأرشيف
    $stmt = $db->query("
        SELECT 
            category,
            COUNT(*) as count,
            SUM(file_size) as total_size
        FROM documents 
        GROUP BY category
    ");
    $category_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات عامة
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_documents,
            SUM(file_size) as total_size
        FROM documents
    ");
    $general_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $documents = [];
    $category_stats = [];
    $general_stats = ['total_documents' => 0, 'total_size' => 0];
}

$categories = ['مالية', 'مراسلات', 'مشاريع', 'موظفين', 'قانونية', 'أخرى'];
$departments = ['الإدارة المالية', 'الهندسة', 'الموارد البشرية', 'القانونية', 'خدمة المواطنين', 'تقنية المعلومات'];

// دالة لتحويل حجم الملف
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الأرشيف الإلكتروني - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .modal { display: none; }
        .modal.active { display: flex; }
        .file-icon {
            width: 2rem;
            height: 2rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: bold;
            color: white;
        }
        .pdf { background-color: #dc2626; }
        .doc, .docx { background-color: #2563eb; }
        .xls, .xlsx { background-color: #059669; }
        .txt { background-color: #6b7280; }
        .jpg, .png { background-color: #7c3aed; }
        .default { background-color: #374151; }
    </style>
</head>
<body class="bg-slate-100">
    <div class="min-h-screen p-6">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-slate-800">الأرشيف الإلكتروني</h1>
                <div class="flex gap-3">
                    <button onclick="openModal('uploadModal')" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        📁 رفع ملف جديد
                    </button>
                    <a href="../comprehensive_dashboard.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                        ← العودة للوحة التحكم
                    </a>
                </div>
            </div>
            <p class="text-slate-600 mt-2">إدارة وتنظيم الوثائق والملفات الرسمية</p>
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

        <!-- إحصائيات الأرشيف -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">إجمالي الوثائق</p>
                        <p class="text-2xl font-bold text-blue-600"><?= $general_stats['total_documents'] ?></p>
                    </div>
                    <div class="bg-blue-100 text-blue-600 p-3 rounded-full">📄</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">حجم الأرشيف</p>
                        <p class="text-2xl font-bold text-green-600"><?= formatFileSize($general_stats['total_size']) ?></p>
                    </div>
                    <div class="bg-green-100 text-green-600 p-3 rounded-full">💾</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">الفئات</p>
                        <p class="text-2xl font-bold text-purple-600"><?= count($category_stats) ?></p>
                    </div>
                    <div class="bg-purple-100 text-purple-600 p-3 rounded-full">🗂️</div>
                </div>
            </div>
        </div>

        <!-- فلاتر البحث -->
        <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
            <h3 class="font-semibold mb-4">البحث والفلترة</h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">البحث</label>
                    <input type="text" name="search" 
                           value="<?= htmlspecialchars($search) ?>"
                           placeholder="البحث في العنوان، الوصف، أو الكلمات المفتاحية"
                           class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الفئة</label>
                    <select name="category" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">جميع الفئات</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category ?>" <?= ($filter_category === $category) ? 'selected' : '' ?>><?= $category ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">القسم</label>
                    <select name="department" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">جميع الأقسام</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept ?>" <?= ($filter_department === $dept) ? 'selected' : '' ?>><?= $dept ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                        🔍 بحث
                    </button>
                </div>
            </form>
        </div>

        <!-- جدول الوثائق -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold">قائمة الوثائق</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th class="px-6 py-3">النوع</th>
                            <th class="px-6 py-3">العنوان</th>
                            <th class="px-6 py-3">الفئة</th>
                            <th class="px-6 py-3">القسم</th>
                            <th class="px-6 py-3">الحجم</th>
                            <th class="px-6 py-3">الصلاحية</th>
                            <th class="px-6 py-3">رفع بواسطة</th>
                            <th class="px-6 py-3">التاريخ</th>
                            <th class="px-6 py-3">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $document): ?>
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="file-icon <?= $document['file_type'] ?>">
                                        <?= strtoupper($document['file_type']) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium"><?= htmlspecialchars($document['title']) ?></p>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($document['file_name']) ?></p>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars($document['category']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4"><?= htmlspecialchars($document['department'] ?? '-') ?></td>
                                <td class="px-6 py-4"><?= formatFileSize($document['file_size']) ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded 
                                        <?= $document['access_level'] === 'عام' ? 'bg-green-100 text-green-800' : 
                                           ($document['access_level'] === 'محدود' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                        <?= htmlspecialchars($document['access_level']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4"><?= htmlspecialchars($document['uploaded_by_name'] ?? 'غير محدد') ?></td>
                                <td class="px-6 py-4"><?= date('Y-m-d', strtotime($document['created_at'])) ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex gap-2">
                                        <a href="<?= htmlspecialchars($document['file_path']) ?>" target="_blank"
                                           class="bg-blue-100 text-blue-600 px-2 py-1 rounded text-xs hover:bg-blue-200">
                                            عرض
                                        </a>
                                        <a href="<?= htmlspecialchars($document['file_path']) ?>" download
                                           class="bg-green-100 text-green-600 px-2 py-1 rounded text-xs hover:bg-green-200">
                                            تحميل
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($documents)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-8 text-gray-500">
                                    لا توجد وثائق مطابقة لمعايير البحث
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal رفع ملف جديد -->
    <div id="uploadModal" class="modal fixed inset-0 bg-black bg-opacity-50 justify-center items-center z-50">
        <div class="bg-white p-6 rounded-lg max-w-2xl w-full mx-4 max-h-96 overflow-y-auto">
            <h3 class="text-xl font-semibold mb-4">رفع ملف جديد</h3>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">اختيار الملف *</label>
                    <input type="file" name="document_file" required 
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.png"
                           class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-gray-500 mt-1">الأنواع المدعومة: PDF, DOC, DOCX, XLS, XLSX, TXT, JPG, PNG (الحد الأقصى: 10MB)</p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">عنوان الوثيقة *</label>
                        <input type="text" name="title" required 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الفئة *</label>
                        <select name="category" required 
                                class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">اختر الفئة</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category ?>"><?= $category ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">وصف الوثيقة</label>
                    <textarea name="description" rows="3"
                              class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">القسم</label>
                        <select name="department" class="w-full p-2 border border-gray-300 rounded-md">
                            <option value="">اختر القسم</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept ?>"><?= $dept ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">مستوى الصلاحية</label>
                        <select name="access_level" class="w-full p-2 border border-gray-300 rounded-md">
                            <option value="محدود">محدود</option>
                            <option value="عام">عام</option>
                            <?php if ($user['user_type'] === 'admin'): ?>
                                <option value="سري">سري</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الكلمات المفتاحية</label>
                    <input type="text" name="tags" 
                           placeholder="كلمات مفصولة بفواصل لتسهيل البحث"
                           class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="flex gap-4 pt-4">
                    <button type="submit" name="upload_document" 
                            class="flex-1 bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition">
                        رفع الملف
                    </button>
                    <button type="button" onclick="closeModal('uploadModal')" 
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
