<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

$complaint = null;
$error_message = '';
$success_message = '';
$updates = [];

// معالجة البحث عن الشكوى
if ($_SERVER['REQUEST_METHOD'] == 'POST' || isset($_GET['complaint_number'])) {
    // دعم الطريقتين: حقول منفصلة أو رقم كامل
    if (isset($_POST['prefix']) && isset($_POST['year']) && isset($_POST['number'])) {
        // بناء رقم التتبع من الحقول المنفصلة
        $prefix = trim($_POST['prefix'] ?? 'SHK-');
        $year = trim($_POST['year'] ?? '');
        $number = trim($_POST['number'] ?? '');
        $complaint_number = $prefix . $year . '-' . str_pad($number, 5, '0', STR_PAD_LEFT);
    } else {
        // الطريقة القديمة: رقم كامل
        $complaint_number = trim($_POST['complaint_number'] ?? $_GET['complaint_number'] ?? '');
    }
    
    if (!empty($complaint_number)) {
        try {
            // فحص الأعمدة الفعلية في الجدول
            $columnsStmt = $db->query("SHOW COLUMNS FROM complaints");
            $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
            $hasCategory = in_array('category', $columns);
            $hasComplaintType = in_array('complaint_type', $columns);
            
            // بناء SELECT clause ديناميكياً
            $selectFields = ["c.*"];
            $selectFields[] = "ca.phone as citizen_phone_from_account";
            $selectFields[] = "ca.name as citizen_name_from_account";
            $selectFields[] = "u.full_name as assigned_user_name";
            
            // إضافة category_display
            if ($hasCategory && $hasComplaintType) {
                $selectFields[] = "COALESCE(c.category, c.complaint_type, 'غير محدد') as category_display";
            } elseif ($hasCategory) {
                $selectFields[] = "COALESCE(c.category, 'غير محدد') as category_display";
            } elseif ($hasComplaintType) {
                $selectFields[] = "COALESCE(c.complaint_type, 'غير محدد') as category_display";
            } else {
                $selectFields[] = "'غير محدد' as category_display";
            }
            
            $selectClause = implode(", ", $selectFields);
            
            // البحث عن الشكوى
            $sql = "
                SELECT $selectClause
                FROM complaints c
                LEFT JOIN citizens_accounts ca ON c.citizen_id = ca.id
                LEFT JOIN users u ON c.assigned_to = u.id
                WHERE c.complaint_number = ?
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute([$complaint_number]);
            $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($complaint) {
                // جلب سجل التحديثات (المرئية للمواطن فقط)
                $updatesStmt = $db->prepare("
                    SELECT cu.*, u.full_name as updated_by_name
                    FROM complaint_updates cu
                    LEFT JOIN users u ON cu.updated_by = u.id
                    WHERE cu.complaint_id = ? AND cu.is_visible_to_citizen = 1
                    ORDER BY cu.created_at DESC
                ");
                $updatesStmt->execute([$complaint['id']]);
                $updates = $updatesStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $error_message = 'لم يتم العثور على شكوى بهذا الرقم. يرجى التأكد من رقم التتبع.';
            }
        } catch (Exception $e) {
            $error_message = 'خطأ في البحث: ' . $e->getMessage();
        }
    } else {
        $error_message = 'يرجى إدخال رقم التتبع.';
    }
}

// دالة لتحديد لون الحالة
function getStatusColor($status) {
    switch($status) {
        case 'جديدة': return 'badge-red';
        case 'قيد المراجعة': return 'badge-yellow';
        case 'قيد المعالجة': return 'badge-purple';
        case 'مكتملة': return 'badge-green';
        case 'مرفوضة': return 'badge-red';
        case 'مؤجلة': return 'badge-gray';
        default: return 'badge-gray';
    }
}

// دالة لتحديد نسبة التقدم
function getProgressPercentage($status) {
    switch($status) {
        case 'جديدة': return 20;
        case 'قيد المراجعة': return 40;
        case 'قيد المعالجة': return 70;
        case 'مكتملة': return 100;
        case 'مرفوضة': return 100;
        case 'مؤجلة': return 50;
        default: return 0;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تتبع الشكوى - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Cairo', sans-serif;
            background-color: #f8fafc;
        }
        
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
            background-color: #dc2626;
            color: white;
            border: 1px solid #dc2626;
        }
        .btn-primary:hover {
            background-color: #b91c1c;
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
            color: #dc2626;
            border: 1px solid #dc2626;
        }
        .btn-outline:hover {
            background-color: #fef2f2;
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .badge-red {
            background-color: #fee2e2;
            color: #991b1b;
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
        .badge-gray {
            background-color: #f3f4f6;
            color: #374151;
        }
        
        .progress {
            height: 0.5rem;
            background-color: #e5e7eb;
            border-radius: 0.25rem;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }
        .progress-bar {
            height: 100%;
            background-color: #dc2626;
            transition: width 0.3s;
        }
        
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
            background-color: #dc2626;
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
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }
        .form-text {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
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
                <h1 class="text-3xl font-bold text-gray-900 mb-2">تتبع الشكوى</h1>
                <p class="text-gray-600">بلدية تكريت - عكار</p>
            </div>

            <!-- Search Form -->
            <?php if (!$complaint): ?>
                <div class="card max-w-md mx-auto mb-8 sm:max-w-lg">
                    <div class="card-header">
                        <h2 class="text-xl font-semibold text-gray-900">البحث عن الشكوى</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="tracking-form">
                            <div class="mb-4">
                                <!-- Desktop and Tablet Layout -->
                                <div class="hidden sm:block" dir="ltr">
                                    <div class="flex items-center justify-center gap-2">
                                        <div class="flex-shrink-0">
                                            <span class="text-red-800 font-bold text-xl">SHK-</span>
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
                                            <label for="number-mobile" class="form-label required">رقم الشكوى</label>
                                            <input type="text" id="number-mobile" name="number" required
                                                   class="form-control text-center font-mono"
                                                   placeholder="00001"
                                                   maxlength="5"
                                                   pattern="[0-9]+"
                                                   value="<?= htmlspecialchars($_POST['number'] ?? '') ?>">
                                            <div class="form-text">أدخل رقم الشكوى (5 أرقام)</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-4 p-3 bg-red-50 rounded-lg border border-red-200">
                                <div class="text-sm text-red-800 text-center">
                                    <strong>رقم التتبع الكامل:</strong>
                                    <span id="full-tracking-number" class="font-mono text-lg font-bold" dir="ltr">SHK-<?= date('Y') ?>-00000</span>
                                </div>
                            </div>
                            <input type="hidden" id="prefix" name="prefix" value="SHK-">
                            <button type="submit" class="btn btn-primary w-full">البحث</button>
                        </form>
                    </div>
                </div>
                
                <script>
                    // تحديث رقم التتبع الكامل تلقائياً
                    document.addEventListener('DOMContentLoaded', function() {
                        const prefix = 'SHK-';
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

            <!-- Complaint Details -->
            <?php if ($complaint): ?>
                <!-- Progress Bar -->
                <div class="card mb-6">
                    <div class="card-body">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-2">
                            <h3 class="text-lg font-semibold text-gray-900">حالة الشكوى</h3>
                            <span class="badge <?= getStatusColor($complaint['status']) ?> self-start sm:self-auto">
                                <?= htmlspecialchars($complaint['status']) ?>
                            </span>
                        </div>

                        <div class="progress mb-3">
                            <div class="progress-bar" style="width: <?= getProgressPercentage($complaint['status']) ?>%"></div>
                        </div>

                        <div class="grid grid-cols-2 sm:flex sm:justify-between gap-2 text-xs sm:text-sm text-gray-600 text-center">
                            <span class="sm:text-right">تم التقديم</span>
                            <span>قيد المراجعة</span>
                            <span>قيد المعالجة</span>
                            <span class="sm:text-left">مكتملة</span>
                        </div>
                    </div>
                </div>

                <!-- Complaint Information -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Basic Info -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="text-lg font-semibold text-gray-900">معلومات الشكوى</h3>
                        </div>
                        <div class="card-body">
                            <div class="space-y-3">
                                <div class="flex flex-col sm:flex-row sm:justify-between gap-1">
                                    <span class="font-medium text-gray-700">رقم التتبع:</span>
                                    <span class="text-red-600 font-mono break-all"><?= htmlspecialchars($complaint['complaint_number'] ?? '#' . $complaint['id']) ?></span>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:justify-between gap-1">
                                    <span class="font-medium text-gray-700">الموضوع:</span>
                                    <span class="break-words"><?= htmlspecialchars($complaint['subject']) ?></span>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:justify-between gap-1">
                                    <span class="font-medium text-gray-700">الفئة:</span>
                                    <span><?= htmlspecialchars($complaint['category_display'] ?? 'غير محدد') ?></span>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                                    <span class="font-medium text-gray-700">الأولوية:</span>
                                    <span class="badge <?= $complaint['priority'] == 'عاجلة' ? 'badge-red' : ($complaint['priority'] == 'عالية' ? 'badge-yellow' : 'badge-gray') ?> self-start sm:self-auto">
                                        <?= htmlspecialchars($complaint['priority'] ?? 'متوسطة') ?>
                                    </span>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:justify-between gap-1">
                                    <span class="font-medium text-gray-700">تاريخ التقديم:</span>
                                    <span class="text-sm sm:text-base"><?= date('Y-m-d H:i', strtotime($complaint['created_at'] ?? $complaint['date_submitted'] ?? 'now')) ?></span>
                                </div>
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
                                    <span class="break-words"><?= htmlspecialchars($complaint['citizen_name_from_account'] ?? $complaint['citizen_name'] ?? $complaint['complainant_name'] ?? 'غير محدد') ?></span>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:justify-between gap-1">
                                    <span class="font-medium text-gray-700">الهاتف:</span>
                                    <span dir="ltr" class="text-left sm:text-right"><?= htmlspecialchars($complaint['citizen_phone_from_account'] ?? $complaint['citizen_phone'] ?? $complaint['complainant_phone'] ?? 'غير محدد') ?></span>
                                </div>
                                <?php if (!empty($complaint['citizen_email'])): ?>
                                    <div class="flex flex-col sm:flex-row sm:justify-between gap-1">
                                        <span class="font-medium text-gray-700">البريد الإلكتروني:</span>
                                        <span dir="ltr" class="text-left sm:text-right break-all"><?= htmlspecialchars($complaint['citizen_email']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Complaint Description -->
                <div class="card mb-6">
                    <div class="card-header">
                        <h3 class="text-lg font-semibold text-gray-900">وصف الشكوى</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-gray-700 whitespace-pre-wrap"><?= htmlspecialchars($complaint['description'] ?? $complaint['details'] ?? '') ?></p>
                    </div>
                </div>

                <!-- Updates Timeline -->
                <?php if (!empty($updates)): ?>
                    <div class="card mb-6">
                        <div class="card-header">
                            <h3 class="text-lg font-semibold text-gray-900">سجل التحديثات</h3>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <?php foreach ($updates as $update): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-content">
                                            <div class="timeline-header">
                                                <span class="timeline-title"><?= htmlspecialchars($update['update_type'] ?? 'تحديث') ?></span>
                                                <span class="timeline-date"><?= date('Y-m-d H:i', strtotime($update['created_at'])) ?></span>
                                            </div>
                                            <div class="timeline-body">
                                                <?= nl2br(htmlspecialchars($update['update_text'])) ?>
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

                <!-- Actions -->
                <div class="card no-print">
                    <div class="card-body">
                        <div class="flex flex-col sm:flex-row sm:flex-wrap gap-3 sm:gap-4">
                            <a href="citizen-complaints.php" class="btn btn-primary w-full sm:w-auto">تقديم شكوى جديدة</a>
                            <button onclick="window.print()" class="btn btn-secondary w-full sm:w-auto">طباعة</button>
                            <form method="POST" class="w-full sm:w-auto">
                                <input type="hidden" name="complaint_number" value="">
                                <button type="submit" class="btn btn-outline w-full sm:w-auto">البحث عن شكوى أخرى</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

