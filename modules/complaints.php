<?php
// تحميل CSRF Protection
if (file_exists(__DIR__ . '/../includes/csrf_middleware.php')) {
    require_once __DIR__ . '/../includes/csrf_middleware.php';
}

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
    if (!csrf_protect(false)) {
        $error = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
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
                // ملاحظة: قد يكون الجدول يستخدم citizen_name/citizen_phone أو complainant_name/complainant_phone
                // سنحاول استخدام citizen_* أولاً، وإذا فشل نستخدم complainant_*
                try {
                    $query = "INSERT INTO complaints (subject, details, citizen_name, citizen_phone, citizen_address, category, priority, assigned_department, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'جديدة')";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$subject, $details, $complainant_name, $complainant_phone, $complainant_address, $category, $priority, $assigned_department]);
                } catch (PDOException $e) {
                    // إذا فشل، جرب استخدام complainant_*
                    if (strpos($e->getMessage(), 'citizen_name') !== false || strpos($e->getMessage(), 'citizen_phone') !== false || strpos($e->getMessage(), 'Unknown column') !== false) {
                        $query = "INSERT INTO complaints (subject, details, complainant_name, complainant_phone, complainant_address, category, priority, assigned_department, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'جديدة')";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$subject, $details, $complainant_name, $complainant_phone, $complainant_address, $category, $priority, $assigned_department]);
                    } else {
                        throw $e;
                    }
                }
                $message = 'تم إضافة الشكوى بنجاح!';
            } catch (PDOException $e) {
                $error = 'خطأ في إضافة الشكوى: ' . $e->getMessage();
            }
        } else {
            $error = 'يرجى تعبئة الحقول المطلوبة';
        }
    }
}

// معالجة تحديث حالة الشكوى
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_complaint'])) {
    if (!csrf_protect(false)) {
        $error = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        $complaint_id = intval($_POST['complaint_id']);
        $new_status = $_POST['new_status'];
        $response = trim($_POST['response'] ?? '');
        $update_comment = trim($_POST['update_comment'] ?? '');
        $assigned_to = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
        $visible_to_citizen = isset($_POST['visible_to_citizen']) ? 1 : 0;
        
        try {
            $db->beginTransaction();
            
            // جلب الحالة القديمة
            $oldStmt = $db->prepare("SELECT status, citizen_id FROM complaints WHERE id = ?");
            $oldStmt->execute([$complaint_id]);
            $oldData = $oldStmt->fetch(PDO::FETCH_ASSOC);
            $old_status = $oldData['status'] ?? '';
            $citizen_id = $oldData['citizen_id'] ?? null;
            
            // تحديث الشكوى
            $query = "UPDATE complaints SET status = ?, response = ?, assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$new_status, $response, $assigned_to, $complaint_id]);
            
            // إضافة تحديث في complaint_updates
            $updateText = '';
            if ($old_status != $new_status) {
                $updateText = "تم تغيير الحالة من '{$old_status}' إلى '{$new_status}'";
            }
            
            if (!empty($response)) {
                if (!empty($updateText)) {
                    $updateText .= "\n\nرد من البلدية:\n" . $response;
                } else {
                    $updateText = "رد من البلدية:\n" . $response;
                }
            }
            
            if (!empty($update_comment)) {
                if (!empty($updateText)) {
                    $updateText .= "\n\n" . $update_comment;
                } else {
                    $updateText = $update_comment;
                }
            }
            
            if (!empty($updateText)) {
                $updateType = !empty($response) ? 'municipality_response' : ($old_status != $new_status ? 'status_change' : 'comment');
                
                $updateStmt = $db->prepare("
                    INSERT INTO complaint_updates 
                    (complaint_id, updated_by, update_type, update_text, is_visible_to_citizen, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $updateStmt->execute([
                    $complaint_id, 
                    $user['id'], 
                    $updateType, 
                    $updateText, 
                    $visible_to_citizen
                ]);
            }
            
            $db->commit();
            $message = 'تم تحديث حالة الشكوى بنجاح!';
            
            // إرسال إشعار Telegram
            try {
                require_once '../includes/TelegramService.php';
                require_once '../includes/CitizenAccountHelper.php';
                
                if ($citizen_id) {
                    $accountHelper = new CitizenAccountHelper($db);
                    $accountStmt = $db->prepare("SELECT * FROM citizens_accounts WHERE id = ?");
                    $accountStmt->execute([$citizen_id]);
                    $accountData = $accountStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($accountData && !empty($accountData['telegram_chat_id'])) {
                        $complaintStmt = $db->prepare("SELECT complaint_number, subject, COALESCE(category, complaint_type, 'غير محدد') as category FROM complaints WHERE id = ?");
                        $complaintStmt->execute([$complaint_id]);
                        $complaintData = $complaintStmt->fetch(PDO::FETCH_ASSOC);
                        
                        $telegramService = new TelegramService($db);
                        $telegramService->sendComplaintStatusUpdate(
                            [
                                'citizen_id' => $citizen_id,
                                'telegram_chat_id' => $accountData['telegram_chat_id'],
                                'telegram_username' => $accountData['telegram_username'],
                                'access_code' => $accountData['permanent_access_code']
                            ],
                            $complaintData,
                            $new_status,
                            $updateText
                        );
                    }
                }
            } catch (Exception $e) {
                error_log("Telegram notification error: " . $e->getMessage());
            }
            
        } catch (PDOException $e) {
            $db->rollBack();
            $error = 'خطأ في تحديث الشكوى: ' . $e->getMessage();
        }
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
        $where_conditions[] = "(c.category = ? OR c.complaint_type = ?)";
        $params[] = $filter_category;
        $params[] = $filter_category;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // محاولة جلب أسماء الأعمدة الفعلية أولاً
    try {
        $columnsStmt = $db->query("SHOW COLUMNS FROM complaints");
        $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
        $hasCategory = in_array('category', $columns);
        $hasComplaintType = in_array('complaint_type', $columns);
        $hasCitizenName = in_array('citizen_name', $columns);
        $hasComplainantName = in_array('complainant_name', $columns);
        $hasDescription = in_array('description', $columns);
        $hasDetails = in_array('details', $columns);
        $hasCreatedAt = in_array('created_at', $columns);
        $hasDateSubmitted = in_array('date_submitted', $columns);
    } catch (Exception $e) {
        error_log("Error checking columns: " . $e->getMessage());
        // افتراض وجود جميع الأعمدة
        $hasCategory = true;
        $hasComplaintType = true;
        $hasCitizenName = true;
        $hasComplainantName = true;
        $hasDescription = true;
        $hasDetails = true;
        $hasCreatedAt = true;
        $hasDateSubmitted = false;
    }
    
    // بناء SELECT clause ديناميكياً
    $selectFields = ["c.*"];
    $selectFields[] = "u.full_name as assigned_name";
    
    // اسم المشتكي
    if ($hasCitizenName && $hasComplainantName) {
        $selectFields[] = "COALESCE(c.citizen_name, c.complainant_name, 'غير محدد') as complainant_name_display";
    } elseif ($hasCitizenName) {
        $selectFields[] = "COALESCE(c.citizen_name, 'غير محدد') as complainant_name_display";
    } elseif ($hasComplainantName) {
        $selectFields[] = "COALESCE(c.complainant_name, 'غير محدد') as complainant_name_display";
    } else {
        $selectFields[] = "'غير محدد' as complainant_name_display";
    }
    
    // الفئة
    if ($hasCategory && $hasComplaintType) {
        $selectFields[] = "COALESCE(c.category, c.complaint_type, 'غير محدد') as category_display";
    } elseif ($hasCategory) {
        $selectFields[] = "COALESCE(c.category, 'غير محدد') as category_display";
    } elseif ($hasComplaintType) {
        $selectFields[] = "COALESCE(c.complaint_type, 'غير محدد') as category_display";
    } else {
        $selectFields[] = "'غير محدد' as category_display";
    }
    
    // الوصف
    if ($hasDescription && $hasDetails) {
        $selectFields[] = "COALESCE(c.description, c.details, '') as description_display";
    } elseif ($hasDescription) {
        $selectFields[] = "COALESCE(c.description, '') as description_display";
    } elseif ($hasDetails) {
        $selectFields[] = "COALESCE(c.details, '') as description_display";
    }
    
    // ORDER BY
    $orderBy = "ORDER BY ";
    if ($hasCreatedAt && $hasDateSubmitted) {
        $orderBy .= "COALESCE(c.created_at, c.date_submitted, NOW()) DESC";
    } elseif ($hasCreatedAt) {
        $orderBy .= "c.created_at DESC";
    } elseif ($hasDateSubmitted) {
        $orderBy .= "c.date_submitted DESC";
    } else {
        $orderBy .= "c.id DESC";
    }
    
    // استعلام محسّن مع دعم جميع أسماء الأعمدة
    $sql = "
        SELECT " . implode(", ", $selectFields) . "
        FROM complaints c 
        LEFT JOIN users u ON c.assigned_to = u.id 
        $where_clause
        $orderBy
        LIMIT 50
    ";
    
    error_log("Complaints Query: " . $sql);
    error_log("Complaints Params: " . json_encode($params, JSON_UNESCAPED_UNICODE));
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Complaints Found: " . count($complaints));
    } catch (PDOException $e) {
        error_log("Error executing complaints query: " . $e->getMessage());
        error_log("SQL: " . $sql);
        $complaints = [];
        $error = "خطأ في جلب الشكاوى: " . $e->getMessage();
    }
    
    // جلب إحصائيات الشكاوى
    try {
        $stmt = $db->query("
            SELECT 
                status,
                COUNT(*) as count
            FROM complaints 
            GROUP BY status
        ");
        $status_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        error_log("Error fetching status stats: " . $e->getMessage());
        $status_stats = [];
    }
    
    // جلب الموظفين للتوزيع
    try {
        $stmt = $db->query("SELECT id, full_name, department FROM users WHERE is_active = 1 ORDER BY full_name");
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching employees: " . $e->getMessage());
        $employees = [];
    }
    
} catch (PDOException $e) {
    error_log("Error fetching complaints: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());
    $complaints = [];
    $status_stats = [];
    $employees = [];
    $error = "خطأ في جلب الشكاوى: " . $e->getMessage();
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
                    <!--<button onclick="openModal('addComplaintModal')" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        ➕ إضافة شكوى جديدة
                    </button>-->
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
                                <td class="px-6 py-4">
                                    <?= htmlspecialchars($complaint['complainant_name_display'] ?? $complaint['complainant_name'] ?? $complaint['citizen_name'] ?? 'غير محدد') ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars($complaint['category_display'] ?? $complaint['category'] ?? $complaint['complaint_type'] ?? 'غير محدد') ?>
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
                                <td colspan="9" class="text-center py-8">
                                    <div class="text-gray-500 mb-2">
                                        <?php if (!empty($filter_status) || !empty($filter_category)): ?>
                                            لا توجد شكاوى مطابقة للفلتر المحدد
                                        <?php else: ?>
                                            لا توجد شكاوى في قاعدة البيانات
                                        <?php endif; ?>
                                    </div>
                                    <?php if (isset($error)): ?>
                                        <div class="text-red-600 text-sm mt-2">
                                            ⚠️ <?= htmlspecialchars($error) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php
                                    // عرض معلومات تشخيصية في وضع التطوير
                                    if (isset($_GET['debug'])) {
                                        try {
                                            $countStmt = $db->query("SELECT COUNT(*) as total FROM complaints");
                                            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                                            echo "<div class='text-xs text-gray-400 mt-2'>";
                                            echo "إجمالي الشكاوى في قاعدة البيانات: " . $total;
                                            echo "</div>";
                                        } catch (Exception $e) {
                                            echo "<div class='text-xs text-red-400 mt-2'>";
                                            echo "خطأ في جلب العدد: " . $e->getMessage();
                                            echo "</div>";
                                        }
                                    }
                                    ?>
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
                <?php echo csrf_input('csrf_token'); ?>
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

    <!-- Modal عرض الشكوى فقط -->
    <div id="viewComplaintModal" class="modal fixed inset-0 bg-black bg-opacity-50 justify-center items-center z-50">
        <div class="bg-white p-6 rounded-lg max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold">تفاصيل الشكوى</h3>
                <button onclick="closeModal('viewComplaintModal')" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            
            <div id="viewComplaintDetails">
                <!-- سيتم تحميل التفاصيل هنا -->
            </div>
            
            <div class="mt-6 flex gap-3 justify-end">
                <button onclick="closeModal('viewComplaintModal')" 
                        class="px-6 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition">
                    إغلاق
                </button>
            </div>
        </div>
    </div>

    <!-- Modal تحديث الشكوى -->
    <div id="updateComplaintModal" class="modal fixed inset-0 bg-black bg-opacity-50 justify-center items-center z-50">
        <div class="bg-white p-6 rounded-lg max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold">تحديث الشكوى</h3>
                <button onclick="closeModal('updateComplaintModal')" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            
            <div id="complaintDetails" class="mb-6">
                <!-- سيتم تحميل التفاصيل هنا -->
            </div>
            
            <form method="POST" id="updateComplaintForm" class="space-y-4">
                <?php echo csrf_input('csrf_token'); ?>
                <input type="hidden" name="complaint_id" id="update_complaint_id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الحالة الجديدة *</label>
                        <select name="new_status" id="update_new_status" required 
                                class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="جديدة">جديدة</option>
                            <option value="قيد المراجعة">قيد المراجعة</option>
                            <option value="قيد المعالجة">قيد المعالجة</option>
                            <option value="مكتملة">مكتملة</option>
                            <option value="مؤجلة">مؤجلة</option>
                            <option value="مرفوضة">مرفوضة</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">مسند إلى</label>
                        <select name="assigned_to" id="update_assigned_to" 
                                class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">غير محدد</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">رد البلدية</label>
                    <textarea name="response" id="update_response" rows="4"
                              class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                              placeholder="اكتب رد البلدية على الشكوى..."></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تعليق إضافي</label>
                    <textarea name="update_comment" id="update_comment" rows="3"
                              class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                              placeholder="أضف تعليقاً أو ملاحظة..."></textarea>
                </div>
                
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="visible_to_citizen" value="1" checked 
                               class="ml-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-gray-700">مرئي للمواطن</span>
                    </label>
                </div>
                
                <div class="flex gap-4 pt-4">
                    <button type="submit" name="update_complaint" 
                            class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                        💾 حفظ التحديث
                    </button>
                    <button type="button" onclick="closeModal('updateComplaintModal')" 
                            class="px-6 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition">
                        إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentComplaintData = null;
        
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            if (modalId === 'updateComplaintModal') {
                currentComplaintData = null;
                document.getElementById('complaintDetails').innerHTML = '';
            }
            if (modalId === 'viewComplaintModal') {
                document.getElementById('viewComplaintDetails').innerHTML = '';
            }
        }
        
        function viewComplaint(id) {
            // جلب تفاصيل الشكوى للعرض فقط
            fetch(`get_complaint_details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayViewComplaint(data.complaint, data.updates);
                        openModal('viewComplaintModal');
                    } else {
                        alert('خطأ في جلب تفاصيل الشكوى: ' + (data.error || 'خطأ غير معروف'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('حدث خطأ في جلب تفاصيل الشكوى');
                });
        }
        
        function displayViewComplaint(complaint, updates) {
            const detailsDiv = document.getElementById('viewComplaintDetails');
            const statusColors = {
                'جديدة': 'red',
                'قيد المراجعة': 'yellow',
                'قيد المعالجة': 'blue',
                'مكتملة': 'green',
                'مؤجلة': 'gray',
                'مرفوضة': 'red'
            };
            const statusColor = statusColors[complaint.status] || 'gray';
            
            // التأكد من عرض الموضوع بشكل صحيح
            const subject = complaint.subject || complaint.subject_display || 'بدون موضوع';
            
            let html = `
                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-lg font-bold text-gray-800">${escapeHtml(subject)}</h4>
                        <span class="bg-${statusColor}-100 text-${statusColor}-800 px-3 py-1 rounded text-sm font-bold">
                            ${escapeHtml(complaint.status || 'غير محدد')}
                        </span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-600 mb-2"><strong>رقم الشكوى:</strong> ${escapeHtml(complaint.complaint_number || '#' + complaint.id)}</p>
                            <p class="text-gray-600 mb-2"><strong>المشتكي:</strong> ${escapeHtml(complaint.complainant_name || complaint.citizen_name || 'غير محدد')}</p>
                            <p class="text-gray-600 mb-2"><strong>الهاتف:</strong> ${escapeHtml(complaint.complainant_phone || complaint.citizen_phone || 'غير محدد')}</p>
                        </div>
                        <div>
                            <p class="text-gray-600 mb-2"><strong>الفئة:</strong> ${escapeHtml(complaint.category || complaint.complaint_type || 'غير محدد')}</p>
                            <p class="text-gray-600 mb-2"><strong>الأولوية:</strong> ${escapeHtml(complaint.priority || 'غير محدد')}</p>
                            <p class="text-gray-600 mb-2"><strong>التاريخ:</strong> ${formatDate(complaint.created_at || complaint.date_submitted || '')}</p>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t border-gray-300">
                        <p class="text-sm text-gray-700 font-bold mb-2">الوصف:</p>
                        <p class="text-gray-800 whitespace-pre-wrap">${escapeHtml(complaint.description || complaint.details || '')}</p>
                    </div>
                </div>
            `;
            
            // عرض سجل التحديثات فقط (بدون إمكانية التعديل)
            if (updates && updates.length > 0) {
                html += `
                    <div class="mb-4">
                        <h5 class="font-bold text-gray-700 mb-3">📋 سجل التحديثات:</h5>
                        <div class="space-y-3 max-h-64 overflow-y-auto">
                `;
                updates.forEach((update, index) => {
                    html += `
                        <div class="bg-white border-r-4 border-blue-500 rounded p-3 text-sm shadow-sm">
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-semibold text-blue-600">${escapeHtml(getUpdateTypeLabel(update.update_type))}</span>
                                <span class="text-gray-500 text-xs">${formatDate(update.created_at)}</span>
                            </div>
                            <p class="text-gray-700 whitespace-pre-wrap">${escapeHtml(update.update_text || '')}</p>
                            ${update.updated_by_name ? `<p class="text-xs text-gray-500 mt-1">بواسطة: ${escapeHtml(update.updated_by_name)}</p>` : ''}
                        </div>
                    `;
                });
                html += `
                        </div>
                    </div>
                `;
            } else {
                html += `
                    <div class="mb-4 bg-gray-50 rounded-lg p-4 text-center text-gray-500">
                        لا توجد تحديثات حتى الآن
                    </div>
                `;
            }
            
            detailsDiv.innerHTML = html;
        }
        
        function updateComplaint(id) {
            // جلب تفاصيل الشكوى للتحديث
            fetch(`get_complaint_details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentComplaintData = data.complaint;
                        displayComplaintDetails(data.complaint, data.updates);
                        openModal('updateComplaintModal');
                    } else {
                        alert('خطأ في جلب تفاصيل الشكوى: ' + (data.error || 'خطأ غير معروف'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('حدث خطأ في جلب تفاصيل الشكوى');
                });
        }
        
        function displayComplaintDetails(complaint, updates) {
            const detailsDiv = document.getElementById('complaintDetails');
            const statusColors = {
                'جديدة': 'red',
                'قيد المراجعة': 'yellow',
                'قيد المعالجة': 'blue',
                'مكتملة': 'green',
                'مؤجلة': 'gray',
                'مرفوضة': 'red'
            };
            const statusColor = statusColors[complaint.status] || 'gray';
            
            // التأكد من عرض الموضوع بشكل صحيح
            const subject = complaint.subject || complaint.subject_display || 'بدون موضوع';
            
            let html = `
                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-lg font-bold text-gray-800">${escapeHtml(subject)}</h4>
                        <span class="bg-${statusColor}-100 text-${statusColor}-800 px-3 py-1 rounded text-sm font-bold">
                            ${escapeHtml(complaint.status || 'غير محدد')}
                        </span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-600 mb-2"><strong>رقم الشكوى:</strong> ${escapeHtml(complaint.complaint_number || '#' + complaint.id)}</p>
                            <p class="text-gray-600 mb-2"><strong>المشتكي:</strong> ${escapeHtml(complaint.complainant_name || complaint.citizen_name || 'غير محدد')}</p>
                            <p class="text-gray-600 mb-2"><strong>الهاتف:</strong> ${escapeHtml(complaint.complainant_phone || complaint.citizen_phone || 'غير محدد')}</p>
                        </div>
                        <div>
                            <p class="text-gray-600 mb-2"><strong>الفئة:</strong> ${escapeHtml(complaint.category || complaint.complaint_type || 'غير محدد')}</p>
                            <p class="text-gray-600 mb-2"><strong>الأولوية:</strong> ${escapeHtml(complaint.priority || 'غير محدد')}</p>
                            <p class="text-gray-600 mb-2"><strong>التاريخ:</strong> ${formatDate(complaint.created_at || complaint.date_submitted || '')}</p>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t border-gray-300">
                        <p class="text-sm text-gray-700 font-bold mb-2">الوصف:</p>
                        <p class="text-gray-800 whitespace-pre-wrap">${escapeHtml(complaint.description || complaint.details || '')}</p>
                    </div>
                </div>
            `;
            
            // عرض سجل التحديثات في مودال التحديث
            if (updates && updates.length > 0) {
                html += `
                    <div class="mb-4">
                        <h5 class="font-bold text-gray-700 mb-3">📋 سجل التحديثات:</h5>
                        <div class="space-y-3 max-h-48 overflow-y-auto">
                `;
                updates.forEach((update, index) => {
                    html += `
                        <div class="bg-white border-r-4 border-blue-500 rounded p-3 text-sm shadow-sm">
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-semibold text-blue-600">${escapeHtml(getUpdateTypeLabel(update.update_type))}</span>
                                <span class="text-gray-500 text-xs">${formatDate(update.created_at)}</span>
                            </div>
                            <p class="text-gray-700 whitespace-pre-wrap">${escapeHtml(update.update_text || '')}</p>
                            ${update.updated_by_name ? `<p class="text-xs text-gray-500 mt-1">بواسطة: ${escapeHtml(update.updated_by_name)}</p>` : ''}
                        </div>
                    `;
                });
                html += `
                        </div>
                    </div>
                `;
            } else {
                html += `
                    <div class="mb-4 bg-gray-50 rounded-lg p-4 text-center text-gray-500">
                        لا توجد تحديثات حتى الآن
                    </div>
                `;
            }
            
            detailsDiv.innerHTML = html;
            
            // تعبئة النموذج
            document.getElementById('update_complaint_id').value = complaint.id;
            document.getElementById('update_new_status').value = complaint.status;
            document.getElementById('update_assigned_to').value = complaint.assigned_to || '';
            document.getElementById('update_response').value = complaint.response || '';
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateString) {
            if (!dateString) return 'غير محدد';
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString; // إذا كان التاريخ غير صحيح، أرجعه كما هو
            
            // تنسيق التاريخ بالميلادي: YYYY-MM-DD HH:MM:SS
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const seconds = String(date.getSeconds()).padStart(2, '0');
            
            return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        }
        
        function getUpdateTypeLabel(type) {
            const labels = {
                'status_change': 'تغيير الحالة',
                'municipality_response': 'رد من البلدية',
                'comment': 'تعليق',
                'admin_note': 'ملاحظة إدارية',
                'data_update': 'تحديث البيانات'
            };
            return labels[type] || 'تحديث';
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
