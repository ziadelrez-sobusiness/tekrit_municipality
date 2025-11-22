<?php
/**
 * صفحة تفاصيل الشكوى للمواطن
 * بلدية تكريت - عكار، شمال لبنان
 */

header('Content-Type: text/html; charset=utf-8');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    ?>
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>خطأ في الاتصال</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-50 p-8">
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-lg p-6 text-center">
            <div class="text-5xl mb-4">⚠️</div>
            <h1 class="text-xl font-bold text-red-600 mb-4">خطأ في الاتصال بقاعدة البيانات</h1>
            <p class="text-gray-700 mb-4">يرجى التحقق من أن MySQL مشغل</p>
            <a href="index.php" class="inline-block bg-blue-600 text-white px-4 py-2 rounded">العودة</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$error_message = '';
$complaint = null;
$updates = [];

// الحصول على رقم الشكوى أو ID
$complaint_number = $_GET['number'] ?? '';
$complaint_id = $_GET['id'] ?? '';

if (!empty($complaint_number) || !empty($complaint_id)) {
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
        
        // جلب تفاصيل الشكوى
        // إذا كان number رقم، جرب البحث بـ ID أولاً (لأن complaint_number قد يكون فارغاً)
        if (!empty($complaint_number)) {
            // محاولة البحث بـ ID إذا كان number رقم صحيح
            if (is_numeric($complaint_number)) {
                $sql = "
                    SELECT $selectClause
                    FROM complaints c
                    LEFT JOIN citizens_accounts ca ON c.citizen_id = ca.id
                    LEFT JOIN users u ON c.assigned_to = u.id
                    WHERE c.id = ?
                ";
                $stmt = $db->prepare($sql);
                $stmt->execute([intval($complaint_number)]);
                $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // إذا لم نجد بـ ID، جرب البحث بـ complaint_number
                if (!$complaint) {
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
                }
            } else {
                // إذا كان number نص (مثل SHK-2025-00001)، ابحث بـ complaint_number
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
            }
        } else {
            // البحث بـ ID مباشرة
            $sql = "
                SELECT $selectClause
                FROM complaints c
                LEFT JOIN citizens_accounts ca ON c.citizen_id = ca.id
                LEFT JOIN users u ON c.assigned_to = u.id
                WHERE c.id = ?
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute([intval($complaint_id)]);
            $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
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
            $error_message = "لم يتم العثور على الشكوى";
        }
        
    } catch (Exception $e) {
        $error_message = "خطأ في جلب البيانات: " . $e->getMessage();
    }
} else {
    $error_message = "رقم الشكوى مطلوب";
}

// دالة لتحديد لون الحالة
function getStatusColor($status) {
    switch($status) {
        case 'جديدة': return 'red';
        case 'قيد المراجعة': return 'yellow';
        case 'قيد المعالجة': return 'blue';
        case 'مكتملة': return 'green';
        case 'مؤجلة': return 'gray';
        case 'مرفوضة': return 'red';
        default: return 'gray';
    }
}

// دالة لتحديد أيقونة الأولوية
function getPriorityIcon($priority) {
    switch($priority) {
        case 'عالية': return '🔴';
        case 'متوسطة': return '🟠';
        case 'منخفضة': return '🟢';
        default: return '⚪';
    }
}

// دالة لتحديد نوع التحديث
function getUpdateTypeLabel($type) {
    switch($type) {
        case 'status_change': return 'تغيير الحالة';
        case 'municipality_response': return 'رد من البلدية';
        case 'comment': return 'تعليق';
        case 'admin_note': return 'ملاحظة إدارية';
        case 'data_update': return 'تحديث البيانات';
        default: return 'تحديث';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل الشكوى - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .timeline-item:before {
            content: '';
            position: absolute;
            right: 19px;
            top: 30px;
            bottom: -20px;
            width: 2px;
            background: #e5e7eb;
        }
        .timeline-item:last-child:before {
            display: none;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8 max-w-5xl">
        
        <?php if ($error_message): ?>
            <!-- رسالة خطأ -->
            <div class="bg-red-50 border-2 border-red-400 rounded-xl shadow-lg p-8 text-center">
                <div class="text-6xl mb-4">❌</div>
                <h2 class="text-2xl font-bold text-red-800 mb-3"><?= htmlspecialchars($error_message) ?></h2>
                <div class="flex gap-3 justify-center mt-6">
                    <a href="citizen-dashboard.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition">
                        👤 حسابي الشخصي
                    </a>
                    <a href="citizen-complaints.php" class="bg-green-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-green-700 transition">
                        ➕ شكوى جديدة
                    </a>
                </div>
            </div>
        <?php elseif ($complaint): ?>
            
            <!-- رأس الصفحة -->
            <div class="bg-white rounded-2xl shadow-xl p-8 mb-8">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800 mb-2">
                            <?= htmlspecialchars($complaint['subject']) ?>
                        </h1>
                        <p class="text-gray-600">
                            🔢 رقم الشكوى: <span class="font-bold"><?= htmlspecialchars($complaint['complaint_number'] ?: '#' . $complaint['id']) ?></span>
                        </p>
                    </div>
                    <div class="text-6xl">📢</div>
                </div>
                
                <!-- الحالة والأولوية -->
                <div class="flex gap-4 flex-wrap">
                    <?php $statusColor = getStatusColor($complaint['status']); ?>
                    <div class="bg-<?= $statusColor ?>-50 border-2 border-<?= $statusColor ?>-300 rounded-lg px-4 py-2">
                        <span class="text-<?= $statusColor ?>-800 font-bold">
                            الحالة: <?= htmlspecialchars($complaint['status']) ?>
                        </span>
                    </div>
                    <div class="bg-gray-50 border-2 border-gray-300 rounded-lg px-4 py-2">
                        <span class="text-gray-800 font-bold">
                            <?= getPriorityIcon($complaint['priority']) ?> الأولوية: <?= htmlspecialchars($complaint['priority']) ?>
                        </span>
                    </div>
                    <div class="bg-blue-50 border-2 border-blue-300 rounded-lg px-4 py-2">
                        <span class="text-blue-800 font-bold">
                            📂 الفئة: <?= htmlspecialchars($complaint['category_display'] ?? $complaint['category'] ?? $complaint['complaint_type'] ?? 'غير محدد') ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- معلومات الشكوى -->
            <div class="bg-white rounded-2xl shadow-xl p-8 mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">📋 معلومات الشكوى</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">👤 اسم المشتكي</p>
                        <p class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($complaint['citizen_name'] ?? $complaint['complainant_name'] ?? 'غير محدد') ?></p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-600 mb-1">📞 رقم الهاتف</p>
                        <p class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($complaint['citizen_phone'] ?? $complaint['complainant_phone'] ?? 'غير محدد') ?></p>
                    </div>
                    
                    <?php if ($complaint['citizen_email'] ?? $complaint['complainant_email']): ?>
                    <div>
                        <p class="text-sm text-gray-600 mb-1">📧 البريد الإلكتروني</p>
                        <p class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($complaint['citizen_email'] ?? $complaint['complainant_email']) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($complaint['citizen_address'] ?? $complaint['complainant_address']): ?>
                    <div>
                        <p class="text-sm text-gray-600 mb-1">📍 العنوان</p>
                        <p class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($complaint['citizen_address'] ?? $complaint['complainant_address']) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div>
                        <p class="text-sm text-gray-600 mb-1">📅 تاريخ التقديم</p>
                        <p class="text-lg font-semibold text-gray-800"><?= date('Y-m-d H:i', strtotime($complaint['created_at'])) ?></p>
                    </div>
                    
                    <?php if ($complaint['assigned_user_name']): ?>
                    <div>
                        <p class="text-sm text-gray-600 mb-1">👨‍💼 مسند إلى</p>
                        <p class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($complaint['assigned_user_name']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <p class="text-sm text-gray-600 mb-2">📝 وصف الشكوى</p>
                    <p class="text-gray-800 leading-relaxed whitespace-pre-wrap"><?= htmlspecialchars($complaint['description'] ?? $complaint['details'] ?? '') ?></p>
                </div>
                
                <?php if ($complaint['response']): ?>
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <p class="text-sm text-gray-600 mb-2">💬 رد البلدية</p>
                    <div class="bg-blue-50 border-r-4 border-blue-500 rounded-lg p-4">
                        <p class="text-gray-800 leading-relaxed whitespace-pre-wrap"><?= htmlspecialchars($complaint['response']) ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- سجل التحديثات -->
            <div class="bg-white rounded-2xl shadow-xl p-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">📊 سجل التحديثات</h2>
                
                <?php if (empty($updates)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <p class="text-lg">لا توجد تحديثات حتى الآن</p>
                    </div>
                <?php else: ?>
                    <div class="relative">
                        <?php foreach ($updates as $index => $update): ?>
                            <div class="timeline-item relative pr-12 pb-8">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold ml-4 relative z-10">
                                        <?= $index + 1 ?>
                                    </div>
                                    <div class="flex-1 bg-gray-50 rounded-lg p-4 border-r-4 border-blue-500">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-sm font-semibold text-blue-600">
                                                <?= getUpdateTypeLabel($update['update_type']) ?>
                                            </span>
                                            <span class="text-xs text-gray-500">
                                                <?= date('Y-m-d H:i', strtotime($update['created_at'])) ?>
                                            </span>
                                        </div>
                                        <?php if ($update['updated_by_name']): ?>
                                            <p class="text-xs text-gray-600 mb-2">
                                                بواسطة: <?= htmlspecialchars($update['updated_by_name']) ?>
                                            </p>
                                        <?php endif; ?>
                                        <p class="text-gray-800 leading-relaxed whitespace-pre-wrap">
                                            <?= htmlspecialchars($update['update_text']) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- أزرار الإجراءات -->
            <div class="mt-8 flex gap-4 justify-center">
                <a href="citizen-dashboard.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition">
                    👤 حسابي الشخصي
                </a>
                <a href="citizen-complaints.php" class="bg-green-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-green-700 transition">
                    ➕ شكوى جديدة
                </a>
            </div>
            
        <?php endif; ?>
    </div>
</body>
</html>

