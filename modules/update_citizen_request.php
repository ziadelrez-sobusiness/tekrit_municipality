<?php
header('Content-Type: text/html; charset=utf-8');

// تحميل CSRF Protection
if (file_exists(__DIR__ . '/../includes/csrf_middleware.php')) {
    require_once __DIR__ . '/../includes/csrf_middleware.php';
}

require_once '../includes/auth.php';
require_once '../config/database.php';

$auth->requireLogin();
if (!$auth->checkPermission('employee')) {
    die('غير مسموح لك بالوصول لهذه الصفحة');
}

$database = new Database();
$db = $database->getConnection();
// تأكد من تعيين الترميز لـ PDO في config/database.php
try {
    $db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (PDOException $e) {
    error_log("Database charset setting error in update_citizen_request.php: " . $e->getMessage());
}

$request_id = $_GET['id'] ?? 0;
$success_message = '';
$error_message = '';

// معالجة التحديث
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!csrf_protect(false)) {
        $error_message = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action == 'update_request') {
            $new_status = $_POST['status'];
            $assigned_department = $_POST['assigned_to_department_id'] ?: null;
            $assigned_committee = $_POST['assigned_to_committee_id'] ?: null;
            $priority_level = $_POST['priority_level'] ?? 'عادي';
            $admin_notes = trim($_POST['admin_notes']);
            
            try {
                // تحديث الطلب
                $stmt = $db->prepare("UPDATE citizen_requests SET status = ?, assigned_to_department_id = ?, assigned_to_committee_id = ?, assigned_to_user_id = NULL, priority_level = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_status, $assigned_department, $assigned_committee, $priority_level, $admin_notes, $request_id]);
                
                // إضافة تحديث في تاريخ التحديثات
                $update_text = "تم تحديث حالة الطلب إلى: " . htmlspecialchars($new_status, ENT_QUOTES, 'UTF-8');
                if ($admin_notes) {
                    $update_text .= "\nملاحظات: " . htmlspecialchars($admin_notes, ENT_QUOTES, 'UTF-8');
                }
                
                // الحصول على معرف المستخدم الحالي
                $current_user_id = $_SESSION['user_id'] ?? null;
                
                $update_stmt = $db->prepare("INSERT INTO request_updates (request_id, update_type, update_text, updated_by, is_visible_to_citizen, created_at) VALUES (?, 'تحديث الحالة', ?, ?, 1, NOW())");
                $update_stmt->execute([$request_id, $update_text, $current_user_id]);
                
                // إذا كان الطلب مكتملاً، تحديث تاريخ الإنجاز
                if ($new_status == 'مكتمل') {
                    $stmt = $db->prepare("UPDATE citizen_requests SET completion_date = NOW() WHERE id = ?");
                    $stmt->execute([$request_id]);
                }
                
                $success_message = "تم تحديث الطلب بنجاح";
                
                // إعادة جلب البيانات المحدثة (للعرض بعد التحديث)
                $stmt = $db->prepare("
                    SELECT cr.id, cr.tracking_number, cr.citizen_name, cr.citizen_phone, 
                           cr.citizen_email, cr.citizen_address, cr.national_id, 
                           cr.request_type_id, rt.type_name as request_type, rt.type_description,
                           cr.request_title, cr.request_description, cr.priority_level, cr.status, cr.project_id,
                           cr.assigned_to_department_id, cr.assigned_to_committee_id, cr.assigned_to_user_id, cr.attachments,
                           cr.admin_notes, cr.citizen_rating, cr.citizen_feedback,
                           cr.created_at, cr.updated_at, cr.completion_date,
                           d.department_name,
                           mc.committee_name,
                           u.full_name as assigned_to_name,
                           dp.project_name, dp.project_description, dp.project_status,
                           DATEDIFF(NOW(), cr.created_at) as days_since_created
                    FROM citizen_requests cr 
                    LEFT JOIN request_types rt ON cr.request_type_id = rt.id
                    LEFT JOIN departments d ON cr.assigned_to_department_id = d.id 
                    LEFT JOIN municipal_committees mc ON cr.assigned_to_committee_id = mc.id
                    LEFT JOIN users u ON cr.assigned_to_user_id = u.id 
                    LEFT JOIN development_projects dp ON cr.project_id = dp.id
                    WHERE cr.id = ?
                ");
                $stmt->execute([$request_id]);
                $request = $stmt->fetch(PDO::FETCH_ASSOC); // تأكد من جلبها كمصفوفة ترابطية
            } catch (PDOException $e) { // استخدام PDOException لتقاط أخطاء قاعدة البيانات
                if ($e->errorInfo[1] == 1062) { // Duplicate entry error
                    $error_message = "خطأ في تحديث الطلب: قيمة مكررة لبيانات فريدة. ربما تحاول إسناد قيمة موجودة بالفعل في حقل يجب أن يكون فريدًا.";
                } else {
                    error_log("خطأ في تحديث الطلب (PDOException): " . $e->getMessage());
                    $error_message = "خطأ في تحديث الطلب: " . $e->getMessage();
                }
            } catch (Exception $e) { // لأي استثناءات أخرى
                error_log("خطأ عام في تحديث الطلب: " . $e->getMessage());
                $error_message = "خطأ عام في تحديث الطلب: " . $e->getMessage();
            }
        }
    }
}

// جلب تفاصيل الطلب مع نوع الطلب الصحيح (فقط إذا لم يتم جلبها بعد التحديث أو إذا كان هناك خطأ)
if (!isset($request) || !$request) {
    $stmt = $db->prepare("
        SELECT cr.id, cr.tracking_number, cr.citizen_name, cr.citizen_phone, 
               cr.citizen_email, cr.citizen_address, cr.national_id, 
               cr.request_type_id, rt.type_name as request_type, rt.type_description,
               cr.request_title, cr.request_description, cr.priority_level, cr.status, cr.project_id,
               cr.assigned_to_department_id, cr.assigned_to_committee_id, cr.assigned_to_user_id, cr.attachments,
               cr.admin_notes, cr.citizen_rating, cr.citizen_feedback,
               cr.created_at, cr.updated_at, cr.completion_date,
               d.department_name, 
               mc.committee_name,
               u.full_name as assigned_to_name,
               dp.project_name, dp.project_description, dp.project_status,
               DATEDIFF(NOW(), cr.created_at) as days_since_created
        FROM citizen_requests cr 
        LEFT JOIN request_types rt ON cr.request_type_id = rt.id
        LEFT JOIN departments d ON cr.assigned_to_department_id = d.id 
        LEFT JOIN municipal_committees mc ON cr.assigned_to_committee_id = mc.id
        LEFT JOIN users u ON cr.assigned_to_user_id = u.id 
        LEFT JOIN development_projects dp ON cr.project_id = dp.id
        WHERE cr.id = ?
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$request) {
    die('الطلب غير موجود');
}

// جلب المستندات المرفقة
$docs_stmt = $db->prepare("
    SELECT * FROM request_documents 
    WHERE request_id = ? 
    ORDER BY uploaded_at DESC
");
$docs_stmt->execute([$request_id]);
$documents = $docs_stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب الأقسام واللجان
$departments = $db->query("SELECT id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name")->fetchAll(PDO::FETCH_ASSOC);
$committees = $db->query("SELECT id, committee_name, department_id FROM municipal_committees WHERE is_active = 1 ORDER BY committee_name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحديث طلب المواطن - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="max-w-6xl mx-auto py-6 px-4">
        <div class="bg-white shadow rounded-lg p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900">✏️ تحديث طلب المواطن</h1>
                <button onclick="window.close()" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">
                    ✖️ إغلاق
                </button>
            </div>

            <!-- الرسائل -->
            <?php if ($success_message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <p class="font-bold">✅ نجح! <?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <p class="font-bold">❌ خطأ! <?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            <?php endif; ?>

            <!-- معلومات الطلب الأساسية -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <span class="text-sm font-medium text-blue-600">رقم التتبع:</span>
                        <div class="text-lg font-bold text-blue-800"><?= htmlspecialchars($request['tracking_number'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-blue-600">المواطن:</span>
                        <div class="text-lg font-bold text-blue-800"><?= htmlspecialchars($request['citizen_name'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-blue-600">نوع الطلب:</span>
                        <div class="text-lg font-bold text-blue-800"><?= htmlspecialchars($request['request_type'] ?: 'غير محدد', ENT_QUOTES, 'UTF-8') ?></div>
                        <?php if ($request['type_description']): ?>
                            <div class="text-sm text-blue-600"><?= htmlspecialchars($request['type_description'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <?php if ($request['request_type'] == 'المساهمة في المشروع' && $request['project_name']): ?>
                            <div class="text-sm text-blue-600 mt-1">🏗️ المشروع: <?= htmlspecialchars($request['project_name'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-blue-600">تاريخ الإرسال:</span>
                        <div class="text-lg font-bold text-blue-800"><?= date('Y/m/d', strtotime($request['created_at'])) ?> (منذ <?= htmlspecialchars($request['days_since_created'], ENT_QUOTES, 'UTF-8') ?> يوم)</div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- العمود الأيسر: تفاصيل الطلب والمستندات -->
                <div class="space-y-6">
                    <!-- تفاصيل الطلب -->
                    <div>
                        <h3 class="text-lg font-semibold mb-4 text-gray-800">📋 تفاصيل الطلب</h3>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-600 mb-1">عنوان الطلب</label>
                                <div class="text-gray-900 font-medium"><?= htmlspecialchars($request['request_title'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">تفاصيل الطلب</label>
                                <div class="text-gray-900 whitespace-pre-wrap"><?= htmlspecialchars($request['request_description'] ?? 'لا توجد تفاصيل', ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- المستندات المرفقة -->
                    <?php if (!empty($documents)): ?>
                    <div>
                        <h3 class="text-lg font-semibold mb-4 text-gray-800">📎 المستندات المرفقة</h3>
                        <div class="space-y-3">
                            <?php foreach ($documents as $doc): ?>
                                <?php
                                // تحديد أسماء الحقول الصحيحة
                                $fileName = null;
                                $originalName = 'ملف غير معروف';
                                $fileSize = 0;
                                
                                // البحث عن اسم الملف
                                $possibleFileNameFields = ['file_name', 'filename', 'file_path', 'document_path', 'document_name', 'attachment_name'];
                                foreach ($possibleFileNameFields as $field) {
                                    if (isset($doc[$field]) && !empty($doc[$field])) {
                                        $fileName = $doc[$field];
                                        break;
                                    }
                                }
                                
                                // البحث عن الاسم الأصلي
                                $possibleOriginalNameFields = ['original_filename', 'original_name', 'document_name', 'title', 'name'];
                                foreach ($possibleOriginalNameFields as $field) {
                                    if (isset($doc[$field]) && !empty($doc[$field])) {
                                        $originalName = $doc[$field];
                                        break;
                                    }
                                }
                                
                                // البحث عن حجم الملف
                                $possibleSizeFields = ['file_size', 'size', 'filesize'];
                                foreach ($possibleSizeFields as $field) {
                                    if (isset($doc[$field]) && !empty($doc[$field])) {
                                        $fileSize = $doc[$field];
                                        break;
                                    }
                                }
                                
                                if (!$fileName) continue;
                                
                                // إنشاء المسار الصحيح
                                $webPath = '../uploads/requests/' . $request_id . '/' . basename($fileName);
                                $serverPath = dirname($_SERVER['SCRIPT_FILENAME']) . '/../uploads/requests/' . $request_id . '/' . basename($fileName);
                                
                                // التحقق من نوع الملف
                                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                                $isImage = in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
                                
                                // التحقق من وجود الملف
                                $fileExists = file_exists($serverPath);
                                ?>
                                
                                <div class="border rounded-lg p-3 bg-white hover:bg-gray-50 transition-colors">
                                    <div class="flex items-start gap-3">
                                        <div class="text-xl flex-shrink-0">
                                            <?= $isImage ? '🖼️' : '📄' ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <?php if ($fileExists && $isImage): ?>
                                                <div class="mb-2">
                                                    <img src="<?= htmlspecialchars($webPath, ENT_QUOTES, 'UTF-8') ?>" 
                                                         alt="<?= htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8') ?>" 
                                                         class="max-w-full h-auto border rounded shadow-sm cursor-pointer hover:shadow-md transition-shadow" 
                                                         style="max-height: 150px;"
                                                         onclick="window.open('<?= htmlspecialchars($webPath, ENT_QUOTES, 'UTF-8') ?>', '_blank')"
                                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                                    <div style="display: none;" class="bg-red-50 border border-red-200 rounded p-2 text-red-700 text-sm">
                                                        ❌ فشل في تحميل الصورة
                                                    </div>
                                                </div>
                                            <?php elseif (!$isImage): ?>
                                                <div class="font-medium text-gray-900 mb-1 break-words">
                                                    <?= htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                                <?php if ($fileSize > 0): ?>
                                                    <div class="text-sm text-gray-600 mb-2">
                                                        حجم الملف: <?= number_format($fileSize / 1024 / 1024, 2) ?> MB
                                                    </div>
                                                <?php endif; ?>
                                            <?php elseif (!$fileExists): ?>
                                                <div class="font-medium text-gray-900 mb-1 break-words">
                                                    <?= htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                                <div class="bg-red-50 border border-red-200 rounded p-2 text-red-700 text-sm">
                                                    ❌ الملف غير موجود
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($fileExists): ?>
                                                <div class="flex gap-2">
                                                    <a href="<?= htmlspecialchars($webPath, ENT_QUOTES, 'UTF-8') ?>" target="_blank" 
                                                       class="inline-flex items-center gap-1 bg-blue-600 text-white px-2 py-1 rounded text-xs hover:bg-blue-700 transition-colors">
                                                        <span>عرض</span>
                                                        <span>🔗</span>
                                                    </a>
                                                    <a href="<?= htmlspecialchars($webPath, ENT_QUOTES, 'UTF-8') ?>" download 
                                                       class="inline-flex items-center gap-1 bg-green-600 text-white px-2 py-1 rounded text-xs hover:bg-green-700 transition-colors">
                                                        <span>تحميل</span>
                                                        <span>⬇️</span>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- العمود الأيمن: نموذج التحديث -->
                <div>
                    <h3 class="text-lg font-semibold mb-4 text-gray-800">✏️ تحديث الطلب</h3>
                    
                    <!-- نموذج التحديث -->
                    <form method="POST" class="space-y-6">
                        <?php echo csrf_input('csrf_token'); ?>
                        <input type="hidden" name="action" value="update_request">
                        
                        <div class="space-y-4">
                            <!-- حالة الطلب -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">حالة الطلب</label>
                                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                    <option value="جديد" <?= ($request['status'] ?? '') == 'جديد' ? 'selected' : '' ?>>🆕 جديد</option>
                                    <option value="قيد المراجعة" <?= ($request['status'] ?? '') == 'قيد المراجعة' ? 'selected' : '' ?>>🔍 قيد المراجعة</option>
                                    <option value="قيد التنفيذ" <?= ($request['status'] ?? '') == 'قيد التنفيذ' ? 'selected' : '' ?>>⚙️ قيد التنفيذ</option>
                                    <option value="مكتمل" <?= ($request['status'] ?? '') == 'مكتمل' ? 'selected' : '' ?>>✅ مكتمل</option>
                                    <option value="مرفوض" <?= ($request['status'] ?? '') == 'مرفوض' ? 'selected' : '' ?>>❌ مرفوض</option>
                                    <option value="معلق" <?= ($request['status'] ?? '') == 'معلق' ? 'selected' : '' ?>>⏸️ معلق</option>
                                </select>
                            </div>

                            <!-- القسم المسؤول -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">القسم المسؤول</label>
                                <select name="assigned_to_department_id" id="department_select" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">-- اختر القسم --</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= htmlspecialchars($dept['id'], ENT_QUOTES, 'UTF-8') ?>" <?= ($request['assigned_to_department_id'] ?? '') == $dept['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dept['department_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- اللجنة المسؤولة -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">اللجنة المسؤولة</label>
                                <select name="assigned_to_committee_id" id="committee_select" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">-- اختر اللجنة --</option>
                                    <?php foreach ($committees as $committee): ?>
                                        <option value="<?= htmlspecialchars($committee['id'], ENT_QUOTES, 'UTF-8') ?>" data-department="<?= htmlspecialchars($committee['department_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>" <?= ($request['assigned_to_committee_id'] ?? '') == $committee['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($committee['committee_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- الأولوية -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">مستوى الأولوية</label>
                                <select name="priority_level" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="عادي" <?= ($request['priority_level'] ?? '') == 'عادي' ? 'selected' : '' ?>>🟢 عادي</option>
                                    <option value="مهم" <?= ($request['priority_level'] ?? '') == 'مهم' ? 'selected' : '' ?>>🟡 مهم</option>
                                    <option value="عاجل" <?= ($request['priority_level'] ?? '') == 'عاجل' ? 'selected' : '' ?>>� عاجل</option>
                                </select>
                            </div>
                        </div>

                        <!-- الملاحظات الإدارية والرد -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">الملاحظات الإدارية والرد على المواطن</label>
                            <textarea name="admin_notes" rows="6" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="اكتب ملاحظاتك والرد على المواطن هنا..."><?= htmlspecialchars($request['admin_notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            <p class="text-sm text-gray-500 mt-1">سيتم عرض هذا النص للمواطن في صفحة تتبع الطلب</p>
                        </div>

                        <!-- الحالة الحالية -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="font-medium text-gray-800 mb-2">الحالة الحالية:</h4>
                            
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between items-center">
                                    <span class="font-medium">الحالة:</span>
                                    <span class="px-2 py-1 rounded-full text-xs <?php 
                                        $status = $request['status'] ?? '';
                                        switch($status) {
                                            case 'جديد': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'قيد المراجعة': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'قيد التنفيذ': echo 'bg-purple-100 text-purple-800'; break;
                                            case 'مكتمل': echo 'bg-green-100 text-green-800'; break;
                                            case 'مرفوض': echo 'bg-red-100 text-red-800'; break;
                                            case 'معلق': echo 'bg-gray-100 text-gray-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                    ?>"><?= htmlspecialchars($request['status'] ?? 'غير محدد', ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="font-medium">الأولوية:</span>
                                    <span class="px-2 py-1 rounded-full text-xs <?php 
                                        $priority = $request['priority_level'] ?? '';
                                        switch($priority) {
                                            case 'عاجل': echo 'bg-red-100 text-red-800'; break;
                                            case 'مهم': echo 'bg-orange-100 text-orange-800'; break;
                                            case 'عادي': echo 'bg-green-100 text-green-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                    ?>"><?= htmlspecialchars($request['priority_level'] ?? 'عادي', ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="font-medium">القسم:</span>
                                    <span class="text-gray-600"><?= htmlspecialchars($request['department_name'] ?: 'غير محدد', ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="font-medium">اللجنة:</span>
                                    <span class="text-gray-600"><?= htmlspecialchars($request['committee_name'] ?: 'غير محدد', ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <?php if ($request['updated_at']): ?>
                                <div class="flex justify-between border-t pt-2">
                                    <span class="font-medium">آخر تحديث:</span>
                                    <span class="text-gray-600 text-xs"><?= date('Y/m/d H:i', strtotime($request['updated_at'])) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- أزرار الإجراءات -->
                        <div class="flex flex-col gap-3">
                            <button type="submit" class="w-full px-4 py-3 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                💾 حفظ التحديثات
                            </button>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" onclick="window.close()" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 text-sm">
                                    ❌ إلغاء
                                </button>
                                <button type="button" onclick="viewRequest(<?= htmlspecialchars($request['id'], ENT_QUOTES, 'UTF-8') ?>)" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm">
                                    👁️ عرض التفاصيل
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- نماذج الردود السريعة -->
                    <div class="mt-6 border-t pt-6">
                        <h4 class="text-sm font-semibold mb-3 text-gray-800">📝 نماذج الردود السريعة</h4>
                        <div class="grid grid-cols-1 gap-2">
                            <button onclick="insertQuickResponse('تم استلام طلبكم وسيتم مراجعته خلال 3 أيام عمل.')" class="p-2 bg-blue-50 text-blue-700 rounded-md hover:bg-blue-100 text-right text-sm">
                                📥 رد استلام الطلب
                            </button>
                            <button onclick="insertQuickResponse('طلبكم قيد المراجعة من قبل القسم المختص وسيتم التواصل معكم قريباً.')" class="p-2 bg-yellow-50 text-yellow-700 rounded-md hover:bg-yellow-100 text-right text-sm">
                                🔍 رد المراجعة
                            </button>
                            <button onclick="insertQuickResponse('تم البدء في تنفيذ طلبكم وسيتم إنجازه خلال المدة المحددة.')" class="p-2 bg-purple-50 text-purple-700 rounded-md hover:bg-purple-100 text-right text-sm">
                                ⚙️ رد التنفيذ
                            </button>
                            <button onclick="insertQuickResponse('تم إنجاز طلبكم بنجاح. شكراً لكم لثقتكم ببلدية تكريت.')" class="p-2 bg-green-50 text-green-700 rounded-md hover:bg-green-100 text-right text-sm">
                                ✅ رد الإنجاز
                            </button>
                            <button onclick="insertQuickResponse('نعتذر، لا يمكن تنفيذ طلبكم للأسباب التالية: [يرجى تحديد السبب]')" class="p-2 bg-red-50 text-red-700 rounded-md hover:bg-red-100 text-right text-sm">
                                ❌ رد الرفض
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function filterCommittees() {
            const deptSelect = document.getElementById('department_select');
            const committeeSelect = document.getElementById('committee_select');
            if (!deptSelect || !committeeSelect) return;
            
            const selectedDept = deptSelect.value;
            Array.from(committeeSelect.options).forEach(option => {
                if (option.value === '') {
                    option.style.display = 'block';
                    return;
                }
                
                const committeeDept = option.getAttribute('data-department');
                const shouldShow = !selectedDept || committeeDept === selectedDept;
                option.style.display = shouldShow ? 'block' : 'none';
            });
            
            if (committeeSelect.value) {
                const currentOption = committeeSelect.querySelector(`option[value="${committeeSelect.value}"]`);
                if (currentOption && currentOption.style.display === 'none') {
                    committeeSelect.value = '';
                }
            }
        }
        
        function insertQuickResponse(text) {
            const textarea = document.querySelector('textarea[name="admin_notes"]');
            const currentText = textarea.value;
            textarea.value = currentText ? currentText + '\n\n' + text : text;
            textarea.focus();
        }
        
        function viewRequest(requestId) {
            window.open('view_citizen_request.php?id=' + requestId, '_blank', 'width=1000,height=800');
        }
        
        // إخفاء الرسائل بعد 5 ثوان
        setTimeout(function() {
            const messages = document.querySelectorAll('.bg-green-100, .bg-red-100');
            messages.forEach(msg => msg.style.display = 'none');
        }, 5000);
        
        // تحديث المستخدمين عند تحميل الصفحة وبعد التحديث
        document.addEventListener('DOMContentLoaded', function() {
            // تأخير قليل لضمان تحميل جميع العناصر
            setTimeout(function() {
                filterCommittees();
            }, 100);
        });
        
        // تشغيل الفلتر فقط عند تغيير القسم يدوياً
        document.getElementById('department_select').addEventListener('change', function() {
            filterCommittees();
        });
    </script>
	
</body>
</html>
�