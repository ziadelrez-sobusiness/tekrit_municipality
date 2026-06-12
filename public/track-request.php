<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

$request = null;
$error_message = '';
$success_message = '';

// معالجة البحث عن الطلب
if ($_SERVER['REQUEST_METHOD'] == 'POST' || isset($_GET['tracking_number'])) {
    // دعم الطريقتين: حقول منفصلة أو رقم كامل
    if (isset($_POST['prefix']) && isset($_POST['year']) && isset($_POST['number'])) {
        // بناء رقم التتبع من الحقول المنفصلة
        $prefix = trim($_POST['prefix'] ?? 'REQ-');
        $year = trim($_POST['year'] ?? '');
        $number = trim($_POST['number'] ?? '');
        $tracking_number = $prefix . $year . '-' . str_pad($number, 5, '0', STR_PAD_LEFT);
    } else {
        // الطريقة القديمة: رقم كامل
        $tracking_number = trim($_POST['tracking_number'] ?? $_GET['tracking_number'] ?? '');
    }
    
    if (!empty($tracking_number)) {
        try {
            // جلب معلومات الطلب الأساسية
            $stmt = $db->prepare("
                SELECT cr.*, rt.type_name, rt.type_description, rt.processing_time, rt.fees
                FROM citizen_requests cr 
                LEFT JOIN request_types rt ON cr.request_type_id = rt.id
                WHERE cr.tracking_number = ?
            ");
            $stmt->execute([$tracking_number]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($request) {
                // جلب تحديثات الطلب
                $updates_stmt = $db->prepare("
                    SELECT ru.*, u.full_name as updated_by_name 
                    FROM request_updates ru 
                    LEFT JOIN users u ON ru.updated_by = u.id 
                    WHERE ru.request_id = ? AND ru.is_visible_to_citizen = 1
                    ORDER BY ru.created_at DESC
                ");
                $updates_stmt->execute([$request['id']]);
                $request['updates'] = $updates_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // جلب المستندات المرفقة
                $docs_stmt = $db->prepare("
                    SELECT * FROM request_documents 
                    WHERE request_id = ? 
                    ORDER BY uploaded_at DESC
                ");
                $docs_stmt->execute([$request['id']]);
                $request['documents'] = $docs_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // جلب البيانات الإضافية للنموذج
                $form_stmt = $db->prepare("
                    SELECT * FROM request_form_data 
                    WHERE request_id = ? 
                    ORDER BY field_name
                ");
                $form_stmt->execute([$request['id']]);
                $request['form_data'] = $form_stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } else {
                $error_message = 'لم يتم العثور على طلب بهذا الرقم. يرجى التأكد من رقم التتبع.';
            }
        } catch (Exception $e) {
            $error_message = 'خطأ في البحث: ' . $e->getMessage();
        }
    } else {
        $error_message = 'يرجى إدخال رقم التتبع.';
    }
}

// معالجة تقييم الطلب
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'rate_request') {
    $request_id = $_POST['request_id'];
    $rating = $_POST['rating'];
    $feedback = trim($_POST['feedback']);
    
    try {
        // التحقق من وجود الطلب وأنه مكتمل
        $check_stmt = $db->prepare("SELECT id FROM citizen_requests WHERE id = ? AND status = 'مكتمل'");
        $check_stmt->execute([$request_id]);
        
        if ($check_stmt->fetch()) {
            // إضافة التقييم
            $rating_stmt = $db->prepare("
                INSERT INTO request_ratings (request_id, rating, feedback, created_at) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE rating = VALUES(rating), feedback = VALUES(feedback), created_at = NOW()
            ");
            $rating_stmt->execute([$request_id, $rating, $feedback]);
            
            $success_message = 'تم حفظ تقييمكم بنجاح. شكراً لكم!';
        } else {
            $error_message = 'لا يمكن تقييم هذا الطلب.';
        }
    } catch (Exception $e) {
        $error_message = 'خطأ في حفظ التقييم: ' . $e->getMessage();
    }
}

// دالة لتحديد لون الحالة
function getStatusColor($status) {
    switch($status) {
        case 'جديد': return 'badge-blue';
        case 'قيد المراجعة': return 'badge-yellow';
        case 'قيد التنفيذ': return 'badge-purple';
        case 'مكتمل': return 'badge-green';
        case 'مرفوض': return 'badge-red';
        case 'معلق': return 'badge-gray';
        default: return 'badge-gray';
    }
}

function getImageUrl($filename) {
    // تحديد المسار الأساسي للمشروع
    $baseUrl = '/tekrit_municipality/';
    
    // إنشاء مسار الصورة
    $imagePath = '../uploads/requests/' . $filename;
    
    return $imagePath;
}
// دالة لتحديد نسبة التقدم الموحدة (مقدم ➔ قيد المراجعة ➔ مكتمل)
function getProgressPercentage($status) {
    switch($status) {
        case 'جديد':
        case 'مقدم':
            return 33;
        case 'قيد المراجعة':
        case 'قيد التنفيذ':
        case 'معلق':
            return 66;
        case 'مكتمل':
        case 'موافق عليه':
        case 'مرفوض':
            return 100;
        default:
            return 33;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تتبع الطلب - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Cairo', sans-serif;
            background-color: #f8fafc;
        }
        
        /* تنسيق البطاقات */
        .card {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background-color: #f9fafb;
        }
        .card-header h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #111827;
            margin: 0;
        }
        .card-body {
            padding: 1.5rem;
        }
        
        /* تنسيق الأزرار */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 600;
            transition: all 0.2s;
            cursor: pointer;
            font-size: 0.875rem;
        }
        .btn-primary {
            background-color: #2563eb;
            color: white;
            border: 1px solid #2563eb;
        }
        .btn-primary:hover {
            background-color: #1d4ed8;
        }
        .btn-secondary {
            background-color: #6b7280;
            color: white;
            border: 1px solid #6b7280;
        }
        .btn-secondary:hover {
            background-color: #4b5563;
        }
        .btn-outline {
            background-color: transparent;
            color: #2563eb;
            border: 1px solid #2563eb;
        }
        .btn-outline:hover {
            background-color: #f8fafc;
        }
        
        /* تنسيق الحالات */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .badge-blue {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .badge-yellow {
            background-color: #fef3c7;
            color: #92400e;
        }
        .badge-purple {
            background-color: #ede9fe;
            color: #5b21b6;
        }
        .badge-green {
            background-color: #dcfce7;
            color: #166534;
        }
        .badge-red {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .badge-gray {
            background-color: #f3f4f6;
            color: #374151;
        }
        
        /* تنسيق شريط التقدم */
        .progress {
            height: 0.5rem;
            background-color: #e5e7eb;
            border-radius: 0.25rem;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }
        .progress-bar {
            height: 100%;
            background-color: #3b82f6;
            transition: width 0.3s;
        }
        
        /* تنسيق الـ Timeline */
        .timeline {
            position: relative;
            padding-right: 1rem;
        }
        .timeline::before {
            content: '';
            position: absolute;
            right: 0.5rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: #e5e7eb;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
            padding-right: 1.5rem;
        }
        .timeline-item:last-child {
            padding-bottom: 0;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            right: 0;
            top: 0;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background-color: #3b82f6;
            transform: translateX(50%);
        }
        .timeline-content {
            background-color: white;
            border-radius: 0.375rem;
            padding: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .timeline-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        .timeline-title {
            font-weight: 600;
            color: #111827;
        }
        .timeline-date {
            color: #6b7280;
            font-size: 0.875rem;
        }
        .timeline-body {
            color: #374151;
            line-height: 1.5;
        }
        
        /* تنسيق الملفات المرفقة */
        .file-item {
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            padding: 0.75rem;
            transition: all 0.2s;
        }
        .file-item:hover {
            background-color: #f9fafb;
        }
        .file-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .file-icon {
            font-size: 1.5rem;
            color: #6b7280;
        }
        .file-details {
            flex: 1;
        }
        .file-name {
            font-weight: 500;
            color: #111827;
            margin-bottom: 0.25rem;
        }
        .file-size {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        /* تنسيق النماذج */
        .form-group {
            margin-bottom: 1rem;
        }
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }
        .form-label.required::after {
            content: ' *';
            color: #ef4444;
        }
        .form-control {
            display: block;
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            background-color: white;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .form-text {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        /* التنبيهات */
        .alert {
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }
        .alert-success {
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }
        .alert-danger {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
        .alert-icon {
            font-size: 1.25rem;
        }
        .alert-content {
            flex: 1;
        }
        .alert-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        /* نظام التقييم بالنجوم */
        .star-rating {
            display: flex;
            gap: 0.25rem;
            margin-bottom: 0.5rem;
        }
        .star {
            cursor: pointer;
            color: #d1d5db;
            transition: color 0.2s;
            font-size: 1.5rem;
        }
        .star:hover,
        .star.active {
            color: #f59e0b;
        }
        
        /* طباعة */
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background-color: white;
                font-size: 12pt;
            }
            .card {
                box-shadow: none;
                border: 1px solid #e5e7eb;
                page-break-inside: avoid;
            }
        }

        /* تنسيقات responsive للموبايل والتابلت */
        @media (max-width: 640px) {
            .container {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }

            .card {
                margin-bottom: 1rem;
            }

            .card-header {
                padding: 0.75rem 1rem;
            }

            .card-body {
                padding: 1rem;
            }

            h1 {
                font-size: 1.5rem !important;
            }

            .badge {
                font-size: 0.75rem;
            }

            .timeline-item {
                padding-right: 1rem;
            }

            .timeline-content {
                padding: 0.75rem;
            }

            .file-item {
                padding: 0.5rem;
            }
        }

        @media (min-width: 641px) and (max-width: 1024px) {
            /* تنسيقات للتابلت */
            .container {
                max-width: 720px;
            }
        }

        @media (min-width: 1025px) {
            /* تنسيقات للديسكتوب */
            .container {
                max-width: 1140px;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-8">
        <div class="container mx-auto px-4 max-w-6xl">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">تتبع الطلب</h1>
                <p class="text-gray-600">بلدية تكريت - عكار</p>
            </div>

            <!-- Search Form -->
            <?php if (!$request): ?>
                <div class="card max-w-md mx-auto mb-8 sm:max-w-lg">
                    <div class="card-header">
                        <h2 class="text-xl font-semibold text-gray-900">البحث عن الطلب</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="tracking-form">
                            <div class="mb-4">
                                <!-- Desktop and Tablet Layout -->
                                <div class="hidden sm:block" dir="ltr">
                                    <div class="flex items-center justify-center gap-2">
                                        <div class="flex-shrink-0">
                                            <span class="text-blue-800 font-bold text-xl">REQ-</span>
                                        </div>
                                        <div class="w-32">
                                            <label for="year" class="form-label required text-right">السنة</label>
                                            <input type="text" id="year" name="year" required
                                                   class="form-control text-center font-mono"
                                                   placeholder="2025"
                                                   maxlength="4"
                                                   pattern="[0-9]{4}"
                                                   value="<?= htmlspecialchars($_POST['year'] ?? date('Y')) ?>">
                                            <div class="form-text text-xs text-right">4 أرقام</div>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <span class="text-gray-600 font-bold text-xl">-</span>
                                        </div>
                                        <div class="w-32">
                                            <label for="number" class="form-label required text-right">الرقم</label>
                                            <input type="text" id="number" name="number" required
                                                   class="form-control text-center font-mono"
                                                   placeholder="00001"
                                                   maxlength="5"
                                                   pattern="[0-9]+"
                                                   value="<?= htmlspecialchars($_POST['number'] ?? '') ?>">
                                            <div class="form-text text-xs text-right">5 أرقام</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Mobile Layout -->
                                <div class="block sm:hidden" dir="rtl">
                                    <div class="space-y-4">
                                        <div class="form-group">
                                            <label for="year-mobile" class="form-label required">السنة</label>
                                            <input type="text" id="year-mobile" name="year" required
                                                   class="form-control text-center font-mono"
                                                   placeholder="2025"
                                                   maxlength="4"
                                                   pattern="[0-9]{4}"
                                                   value="<?= htmlspecialchars($_POST['year'] ?? date('Y')) ?>">
                                            <div class="form-text">أدخل 4 أرقام للسنة</div>
                                        </div>
                                        <div class="form-group">
                                            <label for="number-mobile" class="form-label required">رقم الطلب</label>
                                            <input type="text" id="number-mobile" name="number" required
                                                   class="form-control text-center font-mono"
                                                   placeholder="00001"
                                                   maxlength="5"
                                                   pattern="[0-9]+"
                                                   value="<?= htmlspecialchars($_POST['number'] ?? '') ?>">
                                            <div class="form-text">أدخل رقم الطلب (5 أرقام)</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                                <div class="text-sm text-blue-800 text-center">
                                    <strong>رقم التتبع الكامل:</strong>
                                    <span id="full-tracking-number" class="font-mono text-lg font-bold" dir="ltr">REQ-<?= date('Y') ?>-00000</span>
                                </div>
                            </div>
                            <input type="hidden" id="prefix" name="prefix" value="REQ-">
                            <button type="submit" class="btn btn-primary w-full">البحث</button>
                        </form>
                    </div>
                </div>
                
                <script>
                    // تحديث رقم التتبع الكامل تلقائياً
                    document.addEventListener('DOMContentLoaded', function() {
                        const prefix = 'REQ-';
                        const yearDesktop = document.getElementById('year');
                        const numberDesktop = document.getElementById('number');
                        const yearMobile = document.getElementById('year-mobile');
                        const numberMobile = document.getElementById('number-mobile');
                        const fullNumber = document.getElementById('full-tracking-number');

                        function updateFullNumber() {
                            // Get values from visible fields
                            let y, n;
                            if (window.innerWidth >= 640) {
                                // Desktop/Tablet
                                y = yearDesktop?.value || '<?= date('Y') ?>';
                                n = numberDesktop?.value || '';
                            } else {
                                // Mobile
                                y = yearMobile?.value || '<?= date('Y') ?>';
                                n = numberMobile?.value || '';
                            }

                            const formattedNumber = n ? String(n).padStart(5, '0') : '00000';
                            fullNumber.textContent = prefix + y + '-' + formattedNumber;
                        }

                        // Sync desktop and mobile inputs
                        function syncInputs(source, target) {
                            if (source && target) {
                                target.value = source.value;
                            }
                        }

                        // Add event listeners for desktop inputs
                        if (yearDesktop) {
                            yearDesktop.addEventListener('input', function() {
                                syncInputs(yearDesktop, yearMobile);
                                updateFullNumber();
                            });
                        }

                        if (numberDesktop) {
                            numberDesktop.addEventListener('input', function() {
                                syncInputs(numberDesktop, numberMobile);
                                updateFullNumber();
                            });
                        }

                        // Add event listeners for mobile inputs
                        if (yearMobile) {
                            yearMobile.addEventListener('input', function() {
                                syncInputs(yearMobile, yearDesktop);
                                updateFullNumber();
                            });
                        }

                        if (numberMobile) {
                            numberMobile.addEventListener('input', function() {
                                syncInputs(numberMobile, numberDesktop);
                                updateFullNumber();
                            });
                        }

                        // Update on window resize
                        window.addEventListener('resize', updateFullNumber);

                        // Initial update
                        updateFullNumber();
                    });
                </script>
            <?php endif; ?>

            <!-- Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <div class="alert-icon">✅</div>
                    <div class="alert-content">
                        <div class="alert-title">تم بنجاح!</div>
                        <p><?= $success_message ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <div class="alert-icon">⚠️</div>
                    <div class="alert-content">
                        <div class="alert-title">خطأ!</div>
                        <p><?= $error_message ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Request Details -->
            <?php if ($request): ?>
                <!-- Progress Bar -->
                <div class="card mb-6">
                    <div class="card-body">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-2">
                            <h3 class="text-lg font-semibold text-gray-900">حالة الطلب</h3>
                            <span class="badge <?= getStatusColor($request['status']) ?> self-start sm:self-auto">
                                <?= htmlspecialchars($request['status']) ?>
                            </span>
                        </div>

                        <div class="progress mb-3">
                            <div class="progress-bar" style="width: <?= getProgressPercentage($request['status']) ?>%"></div>
                        </div>

                        <div class="grid grid-cols-2 sm:flex sm:justify-between gap-2 text-xs sm:text-sm text-gray-600 text-center">
                            <span class="sm:text-right">تم التقديم</span>
                            <span>قيد المراجعة</span>
                            <span>قيد التنفيذ</span>
                            <span class="sm:text-left">مكتمل</span>
                        </div>
                    </div>
                </div>

                <!-- Request Information -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Basic Info -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="text-lg font-semibold text-gray-900">معلومات الطلب</h3>
                        </div>
                        <div class="card-body">
                            <div class="space-y-3">
                                <div class="flex flex-col sm:flex-row sm:justify-between gap-1">
                                    <span class="font-medium text-gray-700">رقم التتبع:</span>
                                    <span class="text-blue-600 font-mono break-all"><?= htmlspecialchars($request['tracking_number']) ?></span>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:justify-between gap-1">
                                    <span class="font-medium text-gray-700">نوع الطلب:</span>
                                    <span><?= htmlspecialchars($request['type_name'] ?: 'غير محدد') ?></span>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:justify-between gap-1">
                                    <span class="font-medium text-gray-700">عنوان الطلب:</span>
                                    <span class="break-words"><?= htmlspecialchars($request['request_title']) ?></span>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                                    <span class="font-medium text-gray-700">الأولوية:</span>
                                    <span class="badge <?= $request['priority_level'] == 'عاجل' ? 'badge-red' : ($request['priority_level'] == 'مهم' ? 'badge-yellow' : 'badge-gray') ?> self-start sm:self-auto">
                                        <?= htmlspecialchars($request['priority_level']) ?>
                                    </span>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:justify-between gap-1">
                                    <span class="font-medium text-gray-700">تاريخ التقديم:</span>
                                    <span class="text-sm sm:text-base"><?= date('Y-m-d H:i', strtotime($request['created_at'])) ?></span>
                                </div>
                                <?php if ($request['estimated_completion_date']): ?>
                                    <div class="flex flex-col sm:flex-row sm:justify-between gap-1">
                                        <span class="font-medium text-gray-700">التاريخ المتوقع للإنجاز:</span>
                                        <span class="text-sm sm:text-base"><?= date('Y-m-d', strtotime($request['estimated_completion_date'])) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Citizen Info -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="text-lg font-semibold text-gray-900">معلومات المواطن</h3>
                        </div>
                        <div class="card-body">
                            <div class="space-y-3">
                                <div class="flex flex-col sm:flex-row sm:justify-between gap-1">
                                    <span class="font-medium text-gray-700">الاسم:</span>
                                    <span class="break-words"><?= htmlspecialchars($request['citizen_name']) ?></span>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:justify-between gap-1">
                                    <span class="font-medium text-gray-700">الهاتف:</span>
                                    <span dir="ltr" class="text-left sm:text-right"><?= htmlspecialchars($request['citizen_phone']) ?></span>
                                </div>
                                <?php if ($request['citizen_email']): ?>
                                    <div class="flex flex-col sm:flex-row sm:justify-between gap-1">
                                        <span class="font-medium text-gray-700">البريد الإلكتروني:</span>
                                        <span dir="ltr" class="text-left sm:text-right break-all"><?= htmlspecialchars($request['citizen_email']) ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="flex flex-col sm:flex-row sm:justify-between gap-1">
                                    <span class="font-medium text-gray-700">العنوان:</span>
                                    <span class="break-words"><?= htmlspecialchars($request['citizen_address']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Request Description -->
                <div class="card mb-6">
                    <div class="card-header">
                        <h3 class="text-lg font-semibold text-gray-900">وصف الطلب</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-gray-700 whitespace-pre-wrap"><?= htmlspecialchars($request['request_description']) ?></p>
                    </div>
                </div>

                <!-- Additional Form Data -->
                <?php if (!empty($request['form_data'])): ?>
                    <div class="card mb-6">
                        <div class="card-header">
                            <h3 class="text-lg font-semibold text-gray-900">معلومات إضافية</h3>
                        </div>
                        <div class="card-body">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($request['form_data'] as $field): ?>
                                    <div>
                                        <span class="font-medium text-gray-700"><?= htmlspecialchars(str_replace('_', ' ', $field['field_name'])) ?>:</span>
                                        <span class="text-gray-900"><?= htmlspecialchars($field['field_value']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Documents -->
                <!-- في قسم عرض المستندات -->
<!-- استبدل قسم عرض المستندات بهذا الكود المصحح -->
<?php if (!empty($request['documents'])): ?>
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="text-lg font-semibold text-gray-900">المستندات المرفقة</h3>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($request['documents'] as $doc): ?>
                    <?php
                    // تحديد أسماء الحقول الصحيحة بناءً على ما هو متاح
                    $fileName = null;
                    $originalName = 'ملف غير معروف';
                    $fileSize = 0;
                    
                    // البحث عن اسم الملف في حقول مختلفة
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
                    
                    // إذا لم نجد اسم الملف، نتخطى
                    if (!$fileName) {
                        continue;
                    }
                    
                    // إنشاء المسار الصحيح مع request_id
                    // المسار النسبي للويب (من مجلد public إلى uploads)
                    $webPath = '../uploads/requests/' . $request['id'] . '/' . basename($fileName);
                    
                    // المسار الكامل على الخادم للتحقق من وجود الملف
                    $serverPath = dirname($_SERVER['SCRIPT_FILENAME']) . '/../uploads/requests/' . $request['id'] . '/' . basename($fileName);
                    
                    // التحقق من نوع الملف
                    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $isImage = in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
                    
                    // التحقق من وجود الملف
                    $fileExists = file_exists($serverPath);
                    ?>
                    
                    <div class="file-item border rounded p-4 bg-white hover:bg-gray-50 transition-colors">
                        <div class="flex items-start gap-3">
                            <div class="file-icon text-2xl flex-shrink-0">
                                <?php if ($isImage): ?>
                                    🖼️
                                <?php else: ?>
                                    📄
                                <?php endif; ?>
                            </div>
                            <div class="file-details flex-1 min-w-0">
                                
                                
                                <?php if ($fileExists && $isImage): ?>
                                    <div class="mb-3">
                                        <img src="<?= htmlspecialchars($webPath) ?>" 
                                             alt="<?= htmlspecialchars($originalName) ?>" 
                                             class="max-w-full h-auto border rounded shadow-sm cursor-pointer hover:shadow-md transition-shadow" 
                                             style="max-height: 250px;"
                                             onclick="window.open('<?= htmlspecialchars($webPath) ?>', '_blank')"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                        <div style="display: none;" class="bg-red-50 border border-red-200 rounded p-2 text-red-700 text-sm mt-2">
                                            ❌ فشل في تحميل الصورة
                                        </div>
                                    </div>
                                <?php elseif (!$fileExists): ?>
                                    <div class="mb-3 bg-red-50 border border-red-200 rounded p-2 text-red-700 text-sm">
                                        ❌ الملف غير موجود
                                    </div>
                                <?php endif; ?>
                                
                                <div class="flex gap-2">
                                    <?php if ($fileExists): ?>
                                        <a href="<?= htmlspecialchars($webPath) ?>" target="_blank" 
                                           class="inline-flex items-center gap-1 bg-blue-600 text-white px-3 py-1.5 rounded text-sm hover:bg-blue-700 transition-colors">
                                            <span>عرض الملف</span>
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
    </div>
<?php endif; ?>
                <!-- Updates Timeline -->
                <?php if (!empty($request['updates'])): ?>
                    <div class="card mb-6">
                        <div class="card-header">
                            <h3 class="text-lg font-semibold text-gray-900">تاريخ التحديثات</h3>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <?php foreach ($request['updates'] as $update): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-content">
                                            <div class="timeline-header">
                                                <span class="timeline-title"><?= htmlspecialchars($update['update_type']) ?></span>
                                                <span class="timeline-date"><?= date('Y-m-d H:i', strtotime($update['created_at'])) ?></span>
                                            </div>
                                            <div class="timeline-body">
                                                <?= htmlspecialchars($update['update_text']) ?>
                                                <?php if ($update['updated_by_name']): ?>
                                                    <div class="text-xs text-gray-500 mt-1">
                                                        بواسطة: <?= htmlspecialchars($update['updated_by_name']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Rating Section (for completed requests) -->
                <?php if ($request['status'] == 'مكتمل'): ?>
                    <div class="card mb-6">
                        <div class="card-header">
                            <h3 class="text-lg font-semibold text-gray-900">تقييم الخدمة</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="rating-form">
                                <input type="hidden" name="action" value="rate_request">
                                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                
                                <div class="form-group">
                                    <label class="form-label">تقييمكم للخدمة:</label>
                                    <div class="star-rating" id="star-rating">
                                        <span class="star" data-rating="1">★</span>
                                        <span class="star" data-rating="2">★</span>
                                        <span class="star" data-rating="3">★</span>
                                        <span class="star" data-rating="4">★</span>
                                        <span class="star" data-rating="5">★</span>
                                    </div>
                                    <input type="hidden" name="rating" id="rating-value">
                                </div>
                                
                                <div class="form-group">
                                    <label for="feedback" class="form-label">تعليقاتكم (اختياري):</label>
                                    <textarea id="feedback" name="feedback" rows="3" 
                                              class="form-control" placeholder="شاركونا رأيكم في الخدمة المقدمة"></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary" id="submit-rating" disabled>
                                    إرسال التقييم
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Actions -->
                <div class="card no-print">
                    <div class="card-body">
                        <div class="flex flex-col sm:flex-row sm:flex-wrap gap-3 sm:gap-4">
                            <a href="citizen-requests.php" class="btn btn-primary w-full sm:w-auto">تقديم طلب جديد</a>
                            <button onclick="window.print()" class="btn btn-secondary w-full sm:w-auto">طباعة</button>
                            <form method="POST" class="w-full sm:w-auto">
                                <input type="hidden" name="tracking_number" value="">
                                <button type="submit" class="btn btn-outline w-full sm:w-auto">البحث عن طلب آخر</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Star Rating System
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.star');
            const ratingValue = document.getElementById('rating-value');
            const submitButton = document.getElementById('submit-rating');
            
            if (stars.length > 0) {
                stars.forEach(star => {
                    star.addEventListener('click', function() {
                        const rating = this.getAttribute('data-rating');
                        ratingValue.value = rating;
                        
                        // Update star display
                        stars.forEach((s, index) => {
                            if (index < rating) {
                                s.classList.add('active');
                            } else {
                                s.classList.remove('active');
                            }
                        });
                        
                        // Enable submit button
                        if (submitButton) {
                            submitButton.disabled = false;
                        }
                    });
                    
                    star.addEventListener('mouseover', function() {
                        const rating = this.getAttribute('data-rating');
                        stars.forEach((s, index) => {
                            if (index < rating) {
                                s.style.color = '#F59E0B';
                            } else {
                                s.style.color = '#D1D5DB';
                            }
                        });
                    });
                });
                
                // Reset on mouse leave
                document.getElementById('star-rating').addEventListener('mouseleave', function() {
                    const currentRating = ratingValue.value;
                    stars.forEach((s, index) => {
                        if (index < currentRating) {
                            s.style.color = '#F59E0B';
                        } else {
                            s.style.color = '#D1D5DB';
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>