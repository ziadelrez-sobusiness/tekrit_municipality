<?php
/**
 * صفحة رسائل Telegram المعلقة والمرسلة
 * بلدية تكريت - عكار، شمال لبنان
 */

session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// جلب الرسائل
$pending = [];
$sent = [];
$failed = [];

try {
    // الرسائل المعلقة
    $stmt = $db->query("
        SELECT tl.*, ca.name as citizen_name, ca.phone, cr.tracking_number
        FROM telegram_log tl
        LEFT JOIN citizens_accounts ca ON tl.citizen_id = ca.id
        LEFT JOIN citizen_requests cr ON tl.request_id = cr.id
        WHERE tl.status = 'pending'
        ORDER BY tl.created_at DESC
    ");
    $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // الرسائل المرسلة
    $stmt = $db->query("
        SELECT tl.*, ca.name as citizen_name, ca.phone, cr.tracking_number
        FROM telegram_log tl
        LEFT JOIN citizens_accounts ca ON tl.citizen_id = ca.id
        LEFT JOIN citizen_requests cr ON tl.request_id = cr.id
        WHERE tl.status = 'sent'
        ORDER BY tl.sent_at DESC
        LIMIT 50
    ");
    $sent = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // الرسائل الفاشلة
    $stmt = $db->query("
        SELECT tl.*, ca.name as citizen_name, ca.phone, cr.tracking_number
        FROM telegram_log tl
        LEFT JOIN citizens_accounts ca ON tl.citizen_id = ca.id
        LEFT JOIN citizen_requests cr ON tl.request_id = cr.id
        WHERE tl.status = 'failed'
        ORDER BY tl.created_at DESC
        LIMIT 50
    ");
    $failed = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>رسائل Telegram - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        
        <!-- رأس الصفحة -->
        <div class="bg-white rounded-2xl shadow-xl p-8 mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-4xl font-bold text-gray-800 mb-2">📱 رسائل Telegram</h1>
                    <p class="text-gray-600">متابعة حالة الرسائل المرسلة للمواطنين</p>
                </div>
                <a href="../comprehensive_dashboard.php" class="bg-gray-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-gray-700 transition">
                    ↩️ رجوع
                </a>
            </div>
        </div>

        <!-- الإحصائيات -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-yellow-50 border-2 border-yellow-400 rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-yellow-600 text-sm font-bold">معلقة</p>
                        <p class="text-4xl font-bold text-yellow-800"><?= count($pending) ?></p>
                    </div>
                    <div class="text-5xl">⏳</div>
                </div>
            </div>
            
            <div class="bg-green-50 border-2 border-green-400 rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-600 text-sm font-bold">مرسلة</p>
                        <p class="text-4xl font-bold text-green-800"><?= count($sent) ?></p>
                    </div>
                    <div class="text-5xl">✅</div>
                </div>
            </div>
            
            <div class="bg-red-50 border-2 border-red-400 rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-red-600 text-sm font-bold">فاشلة</p>
                        <p class="text-4xl font-bold text-red-800"><?= count($failed) ?></p>
                    </div>
                    <div class="text-5xl">❌</div>
                </div>
            </div>
        </div>

        <!-- التبويبات -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <div class="flex border-b">
                <button onclick="showTab('pending')" class="tab-btn flex-1 px-6 py-4 font-bold hover:bg-gray-50 border-b-4 border-yellow-500 bg-yellow-50 text-yellow-800" id="tab-pending">
                    ⏳ معلقة (<?= count($pending) ?>)
                </button>
                <button onclick="showTab('sent')" class="tab-btn flex-1 px-6 py-4 font-bold hover:bg-gray-50 border-b-4 border-transparent text-gray-600" id="tab-sent">
                    ✅ مرسلة (<?= count($sent) ?>)
                </button>
                <button onclick="showTab('failed')" class="tab-btn flex-1 px-6 py-4 font-bold hover:bg-gray-50 border-b-4 border-transparent text-gray-600" id="tab-failed">
                    ❌ فاشلة (<?= count($failed) ?>)
                </button>
            </div>

            <!-- محتوى التبويبات -->
            <div class="p-8">
                
                <!-- الرسائل المعلقة -->
                <div id="content-pending" class="tab-content">
                    <?php if (empty($pending)): ?>
                        <div class="text-center py-12">
                            <div class="text-6xl mb-4">✅</div>
                            <p class="text-xl text-gray-600">لا توجد رسائل معلقة</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($pending as $msg): ?>
                                <div class="bg-yellow-50 border-2 border-yellow-300 rounded-xl p-6">
                                    <div class="flex items-start justify-between mb-4">
                                        <div>
                                            <h3 class="text-lg font-bold text-gray-800 mb-1">
                                                <?= htmlspecialchars($msg['citizen_name'] ?? 'غير محدد') ?>
                                            </h3>
                                            <p class="text-sm text-gray-600">
                                                📱 <?= htmlspecialchars($msg['phone'] ?? 'N/A') ?>
                                                <?php if ($msg['tracking_number']): ?>
                                                    | 🔢 <?= htmlspecialchars($msg['tracking_number']) ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <span class="bg-yellow-500 text-white px-3 py-1 rounded-full text-sm font-bold">
                                            <?= $msg['message_type'] ?>
                                        </span>
                                    </div>
                                    
                                    <div class="bg-white rounded-lg p-4 mb-4">
                                        <pre class="whitespace-pre-wrap text-sm text-gray-800"><?= htmlspecialchars($msg['message']) ?></pre>
                                    </div>
                                    
                                    <div class="flex items-center justify-between text-xs text-gray-500">
                                        <span>📅 <?= date('Y-m-d H:i', strtotime($msg['created_at'])) ?></span>
                                        <?php if ($msg['telegram_chat_id']): ?>
                                            <span class="text-green-600">✅ Chat ID موجود</span>
                                        <?php else: ?>
                                            <span class="text-red-600">❌ Chat ID مفقود</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- الرسائل المرسلة -->
                <div id="content-sent" class="tab-content hidden">
                    <?php if (empty($sent)): ?>
                        <div class="text-center py-12">
                            <div class="text-6xl mb-4">📭</div>
                            <p class="text-xl text-gray-600">لا توجد رسائل مرسلة</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($sent as $msg): ?>
                                <div class="bg-green-50 border-2 border-green-300 rounded-xl p-6">
                                    <div class="flex items-start justify-between mb-4">
                                        <div>
                                            <h3 class="text-lg font-bold text-gray-800 mb-1">
                                                <?= htmlspecialchars($msg['citizen_name'] ?? 'غير محدد') ?>
                                            </h3>
                                            <p class="text-sm text-gray-600">
                                                📱 <?= htmlspecialchars($msg['phone'] ?? 'N/A') ?>
                                                <?php if ($msg['tracking_number']): ?>
                                                    | 🔢 <?= htmlspecialchars($msg['tracking_number']) ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <span class="bg-green-500 text-white px-3 py-1 rounded-full text-sm font-bold">
                                            ✅ مرسلة
                                        </span>
                                    </div>
                                    
                                    <div class="bg-white rounded-lg p-4 mb-4">
                                        <pre class="whitespace-pre-wrap text-sm text-gray-800"><?= htmlspecialchars($msg['message']) ?></pre>
                                    </div>
                                    
                                    <div class="text-xs text-gray-500">
                                        📅 تم الإرسال: <?= date('Y-m-d H:i', strtotime($msg['sent_at'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- الرسائل الفاشلة -->
                <div id="content-failed" class="tab-content hidden">
                    <?php if (empty($failed)): ?>
                        <div class="text-center py-12">
                            <div class="text-6xl mb-4">✅</div>
                            <p class="text-xl text-gray-600">لا توجد رسائل فاشلة</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($failed as $msg): ?>
                                <div class="bg-red-50 border-2 border-red-300 rounded-xl p-6">
                                    <div class="flex items-start justify-between mb-4">
                                        <div>
                                            <h3 class="text-lg font-bold text-gray-800 mb-1">
                                                <?= htmlspecialchars($msg['citizen_name'] ?? 'غير محدد') ?>
                                            </h3>
                                            <p class="text-sm text-gray-600">
                                                📱 <?= htmlspecialchars($msg['phone'] ?? 'N/A') ?>
                                            </p>
                                        </div>
                                        <span class="bg-red-500 text-white px-3 py-1 rounded-full text-sm font-bold">
                                            ❌ فشل
                                        </span>
                                    </div>
                                    
                                    <?php if ($msg['error_message']): ?>
                                        <div class="bg-red-100 rounded-lg p-4 mb-4">
                                            <p class="text-sm text-red-800">
                                                <strong>الخطأ:</strong> <?= htmlspecialchars($msg['error_message']) ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="text-xs text-gray-500">
                                        📅 <?= date('Y-m-d H:i', strtotime($msg['created_at'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

    </div>

    <script>
        function showTab(tabName) {
            // إخفاء جميع المحتويات
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // إزالة التنسيق من جميع الأزرار
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('border-yellow-500', 'border-green-500', 'border-red-500', 'bg-yellow-50', 'bg-green-50', 'bg-red-50', 'text-yellow-800', 'text-green-800', 'text-red-800');
                btn.classList.add('border-transparent', 'text-gray-600');
            });
            
            // عرض المحتوى المطلوب
            document.getElementById('content-' + tabName).classList.remove('hidden');
            
            // تنسيق الزر النشط
            const activeBtn = document.getElementById('tab-' + tabName);
            activeBtn.classList.remove('border-transparent', 'text-gray-600');
            
            if (tabName === 'pending') {
                activeBtn.classList.add('border-yellow-500', 'bg-yellow-50', 'text-yellow-800');
            } else if (tabName === 'sent') {
                activeBtn.classList.add('border-green-500', 'bg-green-50', 'text-green-800');
            } else if (tabName === 'failed') {
                activeBtn.classList.add('border-red-500', 'bg-red-50', 'text-red-800');
            }
        }
    </script>
</body>
</html>

