<?php
/**
 * صفحة تفاصيل الطلب للمواطن
 * بلدية تكريت - عكار، شمال لبنان
 */

header('Content-Type: text/html; charset=utf-8');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$error_message = '';
$request = null;
$updates = [];
$documents = [];

// الحصول على رقم التتبع
$tracking_number = $_GET['tracking'] ?? '';

if (!empty($tracking_number)) {
    try {
        // جلب تفاصيل الطلب
        $stmt = $db->prepare("
            SELECT cr.*, 
                   rt.type_name,
                   rt.cost, c.currency_symbol, c.currency_code
            FROM citizen_requests cr
            LEFT JOIN request_types rt ON cr.request_type_id = rt.id
            LEFT JOIN currencies c ON rt.cost_currency_id = c.id
            WHERE cr.tracking_number = ?
        ");
        $stmt->execute([$tracking_number]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($request) {
            // جلب سجل التحديثات
            $updatesStmt = $db->prepare("
                SELECT * FROM request_updates 
                WHERE request_id = ?
                ORDER BY created_at DESC
            ");
            $updatesStmt->execute([$request['id']]);
            $updates = $updatesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // جلب المستندات المرفقة
            if (!empty($request['documents'])) {
                $docs = json_decode($request['documents'], true);
                if (is_array($docs)) {
                    $documents = $docs;
                }
            }
        } else {
            $error_message = "لم يتم العثور على الطلب";
        }
        
    } catch (Exception $e) {
        $error_message = "خطأ في جلب البيانات: " . $e->getMessage();
    }
} else {
    $error_message = "رقم التتبع مطلوب";
}

// دالة لتحديد لون الحالة
function getStatusColor($status) {
    switch($status) {
        case 'جديد': return 'blue';
        case 'قيد المراجعة': return 'yellow';
        case 'قيد التنفيذ': return 'purple';
        case 'مكتمل': return 'green';
        case 'مرفوض': return 'red';
        case 'معلق': return 'orange';
        default: return 'gray';
    }
}

// دالة لتحديد أيقونة الأولوية
function getPriorityIcon($priority) {
    switch($priority) {
        case 'عاجل': return '🔴';
        case 'مهم': return '🟠';
        case 'عادي': return '🟢';
        default: return '⚪';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل الطلب - بلدية تكريت</title>
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
                    <a href="track-request.php" class="bg-green-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-green-700 transition">
                        🔍 تتبع طلب آخر
                    </a>
                </div>
            </div>
        <?php elseif ($request): ?>
            
            <!-- رأس الصفحة -->
            <div class="bg-white rounded-2xl shadow-xl p-8 mb-8">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800 mb-2">
                            <?= htmlspecialchars($request['request_title'] ?? $request['type_name']) ?>
                        </h1>
                        <p class="text-gray-600">
                            🔢 رقم التتبع: <span class="font-bold"><?= htmlspecialchars($request['tracking_number']) ?></span>
                        </p>
                    </div>
                    <div class="text-6xl">
                        <?= $request['icon'] ?? '📋' ?>
                    </div>
                </div>
                
                <!-- الحالة والأولوية -->
                <div class="flex gap-4 flex-wrap">
                    <?php $statusColor = getStatusColor($request['status']); ?>
                    <div class="bg-<?= $statusColor ?>-50 border-2 border-<?= $statusColor ?>-300 rounded-lg px-4 py-2">
                        <span class="text-<?= $statusColor ?>-800 font-bold">
                            📊 الحالة: <?= htmlspecialchars($request['status']) ?>
                        </span>
                    </div>
                    
                    <?php if (isset($request['priority'])): ?>
                    <div class="bg-gray-50 border-2 border-gray-300 rounded-lg px-4 py-2">
                        <span class="text-gray-800 font-bold">
                            <?= getPriorityIcon($request['priority']) ?> الأولوية: <?= htmlspecialchars($request['priority']) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($request['cost'] && $request['cost'] > 0): ?>
                        <div class="bg-green-50 border-2 border-green-300 rounded-lg px-4 py-2">
                            <span class="text-green-800 font-bold">
                                💰 التكلفة: <?= number_format($request['cost'], 0) ?> <?= $request['currency_symbol'] ?? 'د.ع' ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- معلومات الطلب -->
            <div class="bg-white rounded-2xl shadow-xl p-8 mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">📝 معلومات الطلب</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- معلومات المواطن -->
                    <div class="bg-blue-50 rounded-lg p-6">
                        <h3 class="text-lg font-bold text-blue-900 mb-4">👤 معلومات مقدم الطلب</h3>
                        <div class="space-y-3 text-sm">
                            <div>
                                <span class="text-blue-700 font-bold">الاسم:</span>
                                <span class="text-blue-900"><?= htmlspecialchars($request['citizen_name']) ?></span>
                            </div>
                            <div>
                                <span class="text-blue-700 font-bold">الهاتف:</span>
                                <span class="text-blue-900"><?= htmlspecialchars($request['citizen_phone']) ?></span>
                            </div>
                            <?php if ($request['citizen_email']): ?>
                                <div>
                                    <span class="text-blue-700 font-bold">البريد:</span>
                                    <span class="text-blue-900"><?= htmlspecialchars($request['citizen_email']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($request['citizen_address']): ?>
                                <div>
                                    <span class="text-blue-700 font-bold">العنوان:</span>
                                    <span class="text-blue-900"><?= htmlspecialchars($request['citizen_address']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- معلومات إدارية -->
                    <div class="bg-purple-50 rounded-lg p-6">
                        <h3 class="text-lg font-bold text-purple-900 mb-4">🏢 معلومات إدارية</h3>
                        <div class="space-y-3 text-sm">
                            <div>
                                <span class="text-purple-700 font-bold">تاريخ التقديم:</span>
                                <span class="text-purple-900"><?= date('Y-m-d H:i', strtotime($request['created_at'])) ?></span>
                            </div>
                            <?php if (isset($request['department_name']) && $request['department_name']): ?>
                                <div>
                                    <span class="text-purple-700 font-bold">القسم المختص:</span>
                                    <span class="text-purple-900"><?= htmlspecialchars($request['department_name']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($request['assigned_to_name']) && $request['assigned_to_name']): ?>
                                <div>
                                    <span class="text-purple-700 font-bold">الموظف المسؤول:</span>
                                    <span class="text-purple-900"><?= htmlspecialchars($request['assigned_to_name']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($request['expected_completion_date']) && $request['expected_completion_date']): ?>
                                <div>
                                    <span class="text-purple-700 font-bold">تاريخ الإنجاز المتوقع:</span>
                                    <span class="text-purple-900"><?= date('Y-m-d', strtotime($request['expected_completion_date'])) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- تفاصيل الطلب -->
                <?php if (isset($request['request_details']) && $request['request_details']): ?>
                    <div class="mt-6 bg-gray-50 rounded-lg p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-3">📄 تفاصيل الطلب</h3>
                        <p class="text-gray-700 whitespace-pre-wrap"><?= htmlspecialchars($request['request_details']) ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- ملاحظات داخلية (للموظفين فقط - لا تعرض للمواطن) -->
            </div>

            <!-- المستندات المرفقة -->
            <?php if (!empty($documents)): ?>
                <div class="bg-white rounded-2xl shadow-xl p-8 mb-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">📎 المستندات المرفقة</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($documents as $index => $doc): ?>
                            <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 transition">
                                <div class="flex items-center gap-3">
                                    <div class="text-4xl">📄</div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-bold text-gray-800 truncate">مستند <?= $index + 1 ?></p>
                                        <p class="text-xs text-gray-600 truncate"><?= basename($doc) ?></p>
                                    </div>
                                </div>
                                <a href="../<?= htmlspecialchars($doc) ?>" 
                                   target="_blank"
                                   class="block mt-3 bg-blue-600 text-white text-center py-2 rounded-lg hover:bg-blue-700 transition text-sm font-bold">
                                    👁️ عرض
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- سجل التحديثات -->
            <div class="bg-white rounded-2xl shadow-xl p-8 mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">📅 سجل التحديثات</h2>
                
                <?php if (empty($updates)): ?>
                    <div class="text-center py-12">
                        <div class="text-6xl mb-4">📭</div>
                        <p class="text-xl text-gray-600">لا توجد تحديثات حتى الآن</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($updates as $update): ?>
                            <div class="timeline-item relative pr-12">
                                <div class="absolute right-0 top-0 w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                                    <?= count($updates) - array_search($update, $updates) ?>
                                </div>
                                
                                <div class="bg-gray-50 rounded-lg p-6 border-r-4 border-blue-500">
                                    <div class="flex items-center justify-between mb-3">
                                        <h3 class="text-lg font-bold text-gray-800">
                                            <?= htmlspecialchars($update['update_type'] ?? $update['update_title'] ?? 'تحديث') ?>
                                        </h3>
                                        <span class="text-sm text-gray-500">
                                            <?= date('Y-m-d H:i', strtotime($update['created_at'])) ?>
                                        </span>
                                    </div>
                                    
                                    <?php if (isset($update['old_status']) && isset($update['new_status']) && $update['old_status'] && $update['new_status']): ?>
                                        <div class="flex items-center gap-2 mb-3">
                                            <span class="bg-gray-200 text-gray-800 px-3 py-1 rounded-full text-sm">
                                                <?= htmlspecialchars($update['old_status']) ?>
                                            </span>
                                            <span class="text-gray-500">→</span>
                                            <span class="bg-blue-500 text-white px-3 py-1 rounded-full text-sm font-bold">
                                                <?= htmlspecialchars($update['new_status']) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    // استخدام update_text أو update_description
                                    $updateText = $update['update_text'] ?? $update['update_description'] ?? '';
                                    if ($updateText): 
                                    ?>
                                        <p class="text-gray-700 whitespace-pre-wrap"><?= htmlspecialchars($updateText) ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($update['updated_by_name']) && $update['updated_by_name']): ?>
                                        <p class="text-xs text-gray-500 mt-3">
                                            👤 بواسطة: <?= htmlspecialchars($update['updated_by_name']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- أزرار الإجراءات -->
            <div class="bg-white rounded-2xl shadow-xl p-8">
                <div class="flex gap-4 justify-center flex-wrap">
                    <a href="citizen-dashboard.php" class="bg-blue-600 text-white px-8 py-3 rounded-lg font-bold hover:bg-blue-700 transition">
                        👤 حسابي الشخصي
                    </a>
                    <a href="track-request.php?tracking=<?= urlencode($request['tracking_number']) ?>" class="bg-green-600 text-white px-8 py-3 rounded-lg font-bold hover:bg-green-700 transition">
                        🔍 تتبع الطلب
                    </a>
                    <button onclick="window.print()" class="bg-purple-600 text-white px-8 py-3 rounded-lg font-bold hover:bg-purple-700 transition">
                        🖨️ طباعة
                    </button>
                </div>
            </div>

        <?php endif; ?>

        <!-- Footer -->
        <div class="mt-8 text-center text-gray-600">
            <p class="font-bold">🏛️ بلدية تكريت - عكار، شمال لبنان</p>
            <p class="text-sm mt-1">في خدمة المواطن دائماً</p>
        </div>
    </div>
</body>
</html>

