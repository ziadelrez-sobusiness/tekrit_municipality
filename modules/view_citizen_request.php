<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth->requireLogin();
if (!$auth->checkPermission('employee')) {
    die('غير مسموح لك بالوصول لهذه الصفحة');
}

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

$request_id = $_GET['id'] ?? 0;

// جلب تفاصيل الطلب مع نوع الطلب الصحيح
$stmt = $db->prepare("
    SELECT cr.id, cr.tracking_number, cr.citizen_name, cr.citizen_phone, 
           cr.citizen_email, cr.citizen_address, cr.national_id, 
           cr.request_type_id, rt.type_name as request_type, rt.type_description,
           cr.request_title, cr.request_description, cr.priority_level, cr.status, cr.project_id,
           cr.assigned_to_department_id, cr.assigned_to_committee_id, cr.assigned_to_user_id, cr.attachments,
           cr.admin_notes, cr.citizen_rating, cr.citizen_feedback, cr.response_text,
           cr.created_at, cr.completion_date, cr.updated_at,
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
$request = $stmt->fetch();

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

// جلب تحديثات الطلب
$updates_stmt = $db->prepare("
    SELECT ru.*, u.full_name as updated_by_name 
    FROM request_updates ru 
    LEFT JOIN users u ON ru.updated_by = u.id 
    WHERE ru.request_id = ?
    ORDER BY ru.created_at DESC
");
$updates_stmt->execute([$request_id]);
$updates = $updates_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل الطلب - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="max-w-4xl mx-auto py-6 px-4">
        <div class="bg-white shadow rounded-lg p-6">
            <div class="flex justify-between items-center mb-6 no-print">
                <h1 class="text-2xl font-bold text-gray-900">📝 تفاصيل طلب المواطن</h1>
                <button onclick="window.close()" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">
                    ✖️ إغلاق
                </button>
            </div>

            <!-- معلومات التتبع -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <span class="text-sm font-medium text-blue-600">رقم التتبع:</span>
                        <div class="text-lg font-bold text-blue-800"><?= htmlspecialchars($request['tracking_number']) ?></div>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-blue-600">الحالة:</span>
                        <span class="inline-block px-3 py-1 text-sm font-semibold rounded-full <?php 
                            switch($request['status']) {
                                case 'جديد': echo 'bg-blue-100 text-blue-800'; break;
                                case 'قيد المراجعة': echo 'bg-yellow-100 text-yellow-800'; break;
                                case 'قيد التنفيذ': echo 'bg-purple-100 text-purple-800'; break;
                                case 'مكتمل': echo 'bg-green-100 text-green-800'; break;
                                case 'مرفوض': echo 'bg-red-100 text-red-800'; break;
                                default: echo 'bg-gray-100 text-gray-800';
                            }
                        ?>">
                            <?= $request['status'] ?>
                        </span>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-blue-600">الأولوية:</span>
                        <span class="inline-block px-3 py-1 text-sm font-semibold rounded-full <?php 
                            switch($request['priority_level']) {
                                case 'عاجل': echo 'bg-red-100 text-red-800'; break;
                                case 'مهم': echo 'bg-orange-100 text-orange-800'; break;
                                default: echo 'bg-green-100 text-green-800';
                            }
                        ?>">
                            <?= $request['priority_level'] ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- معلومات المواطن -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-800">👤 معلومات المواطن</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-gray-600 mb-1">الاسم الكامل</label>
                        <div class="text-gray-900"><?= htmlspecialchars($request['citizen_name']) ?></div>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-gray-600 mb-1">رقم الهاتف</label>
                        <div class="text-gray-900"><?= htmlspecialchars($request['citizen_phone']) ?></div>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-gray-600 mb-1">البريد الإلكتروني</label>
                        <div class="text-gray-900"><?= htmlspecialchars($request['citizen_email'] ?: 'غير محدد') ?></div>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-gray-600 mb-1">العنوان</label>
                        <div class="text-gray-900"><?= htmlspecialchars($request['citizen_address'] ?: 'غير محدد') ?></div>
                    </div>
                    <?php if ($request['national_id']): ?>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-gray-600 mb-1">الرقم الوطني</label>
                        <div class="text-gray-900"><?= htmlspecialchars($request['national_id']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- تفاصيل الطلب -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-800">📋 تفاصيل الطلب</h3>
                <div class="space-y-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-gray-600 mb-1">نوع الطلب</label>
                        <div class="text-gray-900 font-medium"><?= htmlspecialchars($request['request_type'] ?: 'غير محدد') ?></div>
                        <?php if ($request['type_description']): ?>
                            <div class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($request['type_description']) ?></div>
                        <?php endif; ?>
                        <?php if ($request['request_type'] == 'المساهمة في المشروع' && $request['project_name']): ?>
                            <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded">
                                <div class="text-sm font-medium text-blue-800">🏗️ معلومات المشروع:</div>
                                <div class="text-blue-900 font-bold"><?= htmlspecialchars($request['project_name']) ?></div>
                                <div class="text-xs text-blue-600">حالة المشروع: <?= htmlspecialchars($request['project_status']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-gray-600 mb-1">عنوان الطلب</label>
                        <div class="text-gray-900 font-medium"><?= htmlspecialchars($request['request_title']) ?></div>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-gray-600 mb-1">تفاصيل الطلب</label>
                        <div class="text-gray-900 whitespace-pre-wrap"><?= htmlspecialchars($request['request_description']) ?></div>
                    </div>
                </div>
            </div>

            <!-- المستندات المرفقة -->
            <?php if (!empty($documents)): ?>
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-800">📎 المستندات المرفقة</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                        
                        <div class="border rounded-lg p-4 bg-white hover:bg-gray-50 transition-colors">
                            <div class="flex items-start gap-3">
                                <div class="text-2xl flex-shrink-0">
                                    <?= $isImage ? '🖼️' : '📄' ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <?php if ($fileExists && $isImage): ?>
                                        <div class="mb-3">
                                            <img src="<?= htmlspecialchars($webPath) ?>" 
                                                 alt="<?= htmlspecialchars($originalName) ?>" 
                                                 class="max-w-full h-auto border rounded shadow-sm cursor-pointer hover:shadow-md transition-shadow" 
                                                 style="max-height: 250px;"
                                                 onclick="window.open('<?= htmlspecialchars($webPath) ?>', '_blank')"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                            <div style="display: none;" class="bg-red-50 border border-red-200 rounded p-2 text-red-700 text-sm">
                                                ❌ فشل في تحميل الصورة
                                            </div>
                                        </div>
                                    <?php elseif (!$isImage): ?>
                                        <div class="font-semibold text-gray-900 mb-2 break-words">
                                            <?= htmlspecialchars($originalName) ?>
                                        </div>
                                        <?php if ($fileSize > 0): ?>
                                            <div class="text-sm text-gray-600 mb-2">
                                                حجم الملف: <?= number_format($fileSize / 1024 / 1024, 2) ?> MB
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif (!$fileExists): ?>
                                        <div class="font-semibold text-gray-900 mb-2 break-words">
                                            <?= htmlspecialchars($originalName) ?>
                                        </div>
                                        <div class="mb-3 bg-red-50 border border-red-200 rounded p-2 text-red-700 text-sm">
                                            ❌ الملف غير موجود
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="flex gap-2">
                                        <?php if ($fileExists): ?>
                                            <a href="<?= htmlspecialchars($webPath) ?>" target="_blank" 
                                               class="inline-flex items-center gap-1 bg-blue-600 text-white px-3 py-1.5 rounded text-sm hover:bg-blue-700 transition-colors">
                                                <span>عرض</span>
                                                <span>🔗</span>
                                            </a>
                                            <a href="<?= htmlspecialchars($webPath) ?>" download 
                                               class="inline-flex items-center gap-1 bg-green-600 text-white px-3 py-1.5 rounded text-sm hover:bg-green-700 transition-colors">
                                                <span>تحميل</span>
                                                <span>⬇️</span>
                                            </a>
                                        <?php else: ?>
                                            <span class="inline-block bg-red-100 text-red-800 px-3 py-1.5 rounded text-sm">
                                                الملف غير متاح
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- تحديثات الطلب -->
            <?php if (!empty($updates)): ?>
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-800">📝 تاريخ التحديثات</h3>
                <div class="space-y-4">
                    <?php foreach ($updates as $update): ?>
                        <div class="bg-gray-50 border-l-4 border-blue-500 p-4 rounded-lg">
                            <div class="flex justify-between items-start mb-2">
                                <span class="font-semibold text-gray-900"><?= htmlspecialchars($update['update_type']) ?></span>
                                <span class="text-sm text-gray-600"><?= date('Y/m/d H:i', strtotime($update['created_at'])) ?></span>
                            </div>
                            <div class="text-gray-700 mb-2">
                                <?= htmlspecialchars($update['update_text']) ?>
                            </div>
                            <?php if ($update['updated_by_name']): ?>
                                <div class="text-xs text-gray-500">
                                    بواسطة: <?= htmlspecialchars($update['updated_by_name']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- المعلومات الإدارية -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-800">🏢 المعلومات الإدارية</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-gray-600 mb-1">القسم المسؤول</label>
                        <div class="text-gray-900"><?= htmlspecialchars($request['department_name'] ?: 'غير محدد') ?></div>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-gray-600 mb-1">اللجنة المسؤولة</label>
                        <div class="text-gray-900"><?= htmlspecialchars($request['committee_name'] ?: 'غير محدد') ?></div>
                    </div>
                </div>
            </div>

            <!-- الملاحظات الإدارية -->
            <?php if ($request['admin_notes']): ?>
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-800">📝 الملاحظات الإدارية</h3>
                <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg">
                    <div class="text-gray-900 whitespace-pre-wrap"><?= htmlspecialchars($request['admin_notes']) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- الرد الإداري -->
            <?php if ($request['response_text']): ?>
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-800">💬 الرد الإداري</h3>
                <div class="bg-green-50 border border-green-200 p-4 rounded-lg">
                    <div class="text-gray-900 whitespace-pre-wrap"><?= htmlspecialchars($request['response_text']) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- التواريخ المهمة -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-800">📅 التواريخ المهمة</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-blue-600 mb-1">تاريخ الإرسال</label>
                        <div class="text-blue-900 font-medium"><?= date('Y/m/d H:i', strtotime($request['created_at'])) ?></div>
                        <div class="text-xs text-blue-600">منذ <?= $request['days_since_created'] ?> يوم</div>
                    </div>
                    <?php if ($request['updated_at'] && $request['updated_at'] != $request['created_at']): ?>
                    <div class="bg-yellow-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-yellow-600 mb-1">آخر تحديث</label>
                        <div class="text-yellow-900 font-medium"><?= date('Y/m/d H:i', strtotime($request['updated_at'])) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($request['completion_date']): ?>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-green-600 mb-1">تاريخ الإنجاز</label>
                        <div class="text-green-900 font-medium"><?= date('Y/m/d H:i', strtotime($request['completion_date'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- الإجراءات -->
            <div class="border-t pt-6 no-print">
                <div class="flex justify-center space-x-4 space-x-reverse">
                    <button onclick="updateRequest(<?= $request['id'] ?>)" class="px-6 py-3 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        ✏️ تحديث الطلب
                    </button>
                    <a href="../public/track-request.php?tracking_number=<?= $request['tracking_number'] ?>" target="_blank" class="px-6 py-3 bg-green-600 text-white rounded-md hover:bg-green-700">
                        🔗 عرض صفحة التتبع
                    </a>
                    <button onclick="window.print()" class="px-6 py-3 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                        🖨️ طباعة
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateRequest(requestId) {
            window.open('update_citizen_request.php?id=' + requestId, '_blank', 'width=900,height=700');
        }
    </script>
</body>
</html>