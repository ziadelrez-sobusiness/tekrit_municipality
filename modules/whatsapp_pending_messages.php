<?php
/**
 * صفحة رسائل WhatsApp المعلقة
 * بلدية تكريت - عكار، شمال لبنان
 */

session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// تحديث حالة الرسالة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $log_id = $_POST['log_id'];
    $new_status = $_POST['new_status'];
    
    try {
        $stmt = $db->prepare("
            UPDATE whatsapp_log 
            SET status = ?,
                sent_at = CASE WHEN ? = 'sent' THEN NOW() ELSE sent_at END,
                delivered_at = CASE WHEN ? = 'delivered' THEN NOW() ELSE delivered_at END,
                read_at = CASE WHEN ? = 'read' THEN NOW() ELSE read_at END
            WHERE id = ?
        ");
        
        $stmt->execute([$new_status, $new_status, $new_status, $new_status, $log_id]);
        $success_message = "تم تحديث حالة الرسالة بنجاح";
        
    } catch (Exception $e) {
        $error_message = "خطأ في تحديث الحالة: " . $e->getMessage();
    }
}

// حذف رسالة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_message'])) {
    $log_id = $_POST['log_id'];
    
    try {
        $stmt = $db->prepare("DELETE FROM whatsapp_log WHERE id = ?");
        $stmt->execute([$log_id]);
        $success_message = "تم حذف الرسالة بنجاح";
        
    } catch (Exception $e) {
        $error_message = "خطأ في الحذف: " . $e->getMessage();
    }
}

// جلب الرسائل المعلقة
$pending_messages = [];
$sent_messages = [];
$failed_messages = [];

try {
    // الرسائل المعلقة
    $stmt = $db->query("
        SELECT 
            wl.*,
            cr.tracking_number,
            cr.request_title,
            cr.status as request_status,
            ca.name as citizen_name
        FROM whatsapp_log wl
        LEFT JOIN citizen_requests cr ON wl.request_id = cr.id
        LEFT JOIN citizens_accounts ca ON wl.citizen_id = ca.id
        WHERE wl.status = 'pending'
        ORDER BY wl.created_at DESC
    ");
    $pending_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // الرسائل المُرسلة (آخر 20)
    $stmt = $db->query("
        SELECT 
            wl.*,
            cr.tracking_number,
            cr.request_title,
            ca.name as citizen_name
        FROM whatsapp_log wl
        LEFT JOIN citizen_requests cr ON wl.request_id = cr.id
        LEFT JOIN citizens_accounts ca ON wl.citizen_id = ca.id
        WHERE wl.status IN ('sent', 'delivered', 'read')
        ORDER BY wl.sent_at DESC
        LIMIT 20
    ");
    $sent_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // الرسائل الفاشلة
    $stmt = $db->query("
        SELECT 
            wl.*,
            cr.tracking_number,
            cr.request_title,
            ca.name as citizen_name
        FROM whatsapp_log wl
        LEFT JOIN citizen_requests cr ON wl.request_id = cr.id
        LEFT JOIN citizens_accounts ca ON wl.citizen_id = ca.id
        WHERE wl.status = 'failed'
        ORDER BY wl.created_at DESC
        LIMIT 10
    ");
    $failed_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "خطأ في جلب الرسائل: " . $e->getMessage();
}

// إحصائيات
$stats = [
    'pending' => count($pending_messages),
    'sent' => 0,
    'failed' => count($failed_messages)
];

try {
    $stmt = $db->query("SELECT COUNT(*) FROM whatsapp_log WHERE status IN ('sent', 'delivered', 'read')");
    $stats['sent'] = $stmt->fetchColumn();
} catch (Exception $e) {
    // ignore
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>رسائل WhatsApp المعلقة - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .message-box {
            transition: all 0.3s ease;
        }
        .message-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <!-- العنوان -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">
                        📱 رسائل WhatsApp المعلقة
                    </h1>
                    <p class="text-gray-600">إدارة رسائل WhatsApp للمواطنين</p>
                </div>
                <a href="../comprehensive_dashboard.php" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition">
                    🏠 العودة للوحة التحكم
                </a>
            </div>
        </div>

        <!-- الرسائل -->
        <?php if ($success_message): ?>
            <div class="bg-green-100 border-r-4 border-green-500 text-green-700 p-4 rounded-lg mb-6">
                ✅ <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border-r-4 border-red-500 text-red-700 p-4 rounded-lg mb-6">
                ❌ <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- الإحصائيات -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-yellow-50 rounded-lg shadow p-6 border-r-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-yellow-600 text-sm font-bold mb-1">معلقة</p>
                        <p class="text-3xl font-bold text-yellow-700"><?= $stats['pending'] ?></p>
                    </div>
                    <div class="text-5xl">⏳</div>
                </div>
            </div>
            
            <div class="bg-green-50 rounded-lg shadow p-6 border-r-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-600 text-sm font-bold mb-1">مُرسلة</p>
                        <p class="text-3xl font-bold text-green-700"><?= $stats['sent'] ?></p>
                    </div>
                    <div class="text-5xl">✅</div>
                </div>
            </div>
            
            <div class="bg-red-50 rounded-lg shadow p-6 border-r-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-red-600 text-sm font-bold mb-1">فاشلة</p>
                        <p class="text-3xl font-bold text-red-700"><?= $stats['failed'] ?></p>
                    </div>
                    <div class="text-5xl">❌</div>
                </div>
            </div>
        </div>

        <!-- التبويبات -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-6">
            <div class="border-b border-gray-200">
                <nav class="flex">
                    <button onclick="showTab('pending')" id="tab-pending" class="tab-button px-6 py-4 text-sm font-bold border-b-2 border-yellow-500 text-yellow-700 bg-yellow-50">
                        ⏳ معلقة (<?= $stats['pending'] ?>)
                    </button>
                    <button onclick="showTab('sent')" id="tab-sent" class="tab-button px-6 py-4 text-sm font-bold border-b-2 border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-50">
                        ✅ مُرسلة
                    </button>
                    <button onclick="showTab('failed')" id="tab-failed" class="tab-button px-6 py-4 text-sm font-bold border-b-2 border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-50">
                        ❌ فاشلة
                    </button>
                </nav>
            </div>

            <!-- محتوى التبويبات -->
            <div class="p-6">
                <!-- الرسائل المعلقة -->
                <div id="content-pending" class="tab-content">
                    <?php if (empty($pending_messages)): ?>
                        <div class="text-center py-12">
                            <div class="text-6xl mb-4">🎉</div>
                            <p class="text-xl text-gray-600 font-bold">لا توجد رسائل معلقة!</p>
                            <p class="text-gray-500 mt-2">جميع الرسائل تم إرسالها</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($pending_messages as $msg): ?>
                                <div class="message-box bg-yellow-50 border-2 border-yellow-200 rounded-lg p-6">
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-3 mb-2">
                                                <span class="bg-yellow-500 text-white px-3 py-1 rounded-full text-sm font-bold">
                                                    <?= htmlspecialchars($msg['message_type']) ?>
                                                </span>
                                                <?php if ($msg['tracking_number']): ?>
                                                    <span class="text-gray-600 text-sm">
                                                        📋 <?= htmlspecialchars($msg['tracking_number']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-lg font-bold text-gray-800 mb-1">
                                                📱 <?= htmlspecialchars($msg['phone']) ?>
                                                <?php if ($msg['citizen_name']): ?>
                                                    <span class="text-gray-600 text-base font-normal">
                                                        - <?= htmlspecialchars($msg['citizen_name']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                🕐 <?= date('Y-m-d H:i', strtotime($msg['created_at'])) ?>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- الرسالة -->
                                    <div class="bg-white rounded-lg p-4 mb-4 border-2 border-gray-200">
                                        <pre class="whitespace-pre-wrap text-sm text-gray-800 font-sans" id="message-<?= $msg['id'] ?>"><?= htmlspecialchars($msg['message']) ?></pre>
                                    </div>

                                    <!-- الأزرار -->
                                    <div class="flex gap-3 flex-wrap">
                                        <!-- إرسال سريع (WhatsApp Web + تحديث تلقائي) -->
                                        <button onclick="quickSend(<?= $msg['id'] ?>, '<?= urlencode($msg['phone']) ?>', '<?= urlencode($msg['message']) ?>')" 
                                                class="bg-gradient-to-r from-green-600 to-green-500 text-white px-6 py-3 rounded-lg hover:from-green-700 hover:to-green-600 transition font-bold shadow-lg flex items-center gap-2">
                                            ⚡ إرسال سريع
                                        </button>

                                        <!-- نسخ الرسالة -->
                                        <button onclick="copyMessage(<?= $msg['id'] ?>)" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
                                            📋 نسخ
                                        </button>

                                        <!-- فتح WhatsApp Web فقط -->
                                        <a href="https://web.whatsapp.com/send?phone=<?= urlencode($msg['phone']) ?>&text=<?= urlencode($msg['message']) ?>" 
                                           target="_blank" 
                                           class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition flex items-center gap-2">
                                            💬 WhatsApp
                                        </a>

                                        <!-- تحديث الحالة -->
                                        <form method="POST" class="inline" id="form-sent-<?= $msg['id'] ?>">
                                            <input type="hidden" name="log_id" value="<?= $msg['id'] ?>">
                                            <input type="hidden" name="new_status" value="sent">
                                            <button type="submit" name="update_status" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition">
                                                ✅ تم
                                            </button>
                                        </form>

                                        <!-- فشل -->
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="log_id" value="<?= $msg['id'] ?>">
                                            <input type="hidden" name="new_status" value="failed">
                                            <button type="submit" name="update_status" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition">
                                                ❌ فشل
                                            </button>
                                        </form>

                                        <!-- حذف -->
                                        <form method="POST" class="inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                            <input type="hidden" name="log_id" value="<?= $msg['id'] ?>">
                                            <button type="submit" name="delete_message" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
                                                🗑️ حذف
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- الرسائل المُرسلة -->
                <div id="content-sent" class="tab-content hidden">
                    <?php if (empty($sent_messages)): ?>
                        <div class="text-center py-12">
                            <div class="text-6xl mb-4">📭</div>
                            <p class="text-xl text-gray-600 font-bold">لا توجد رسائل مُرسلة</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($sent_messages as $msg): ?>
                                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="font-bold text-gray-800">
                                                📱 <?= htmlspecialchars($msg['phone']) ?>
                                                <?php if ($msg['citizen_name']): ?>
                                                    - <?= htmlspecialchars($msg['citizen_name']) ?>
                                                <?php endif; ?>
                                            </p>
                                            <p class="text-sm text-gray-600">
                                                <?= htmlspecialchars($msg['message_type']) ?>
                                                <?php if ($msg['tracking_number']): ?>
                                                    | <?= htmlspecialchars($msg['tracking_number']) ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="text-left">
                                            <p class="text-sm text-green-700 font-bold">✅ تم الإرسال</p>
                                            <p class="text-xs text-gray-500">
                                                <?= date('Y-m-d H:i', strtotime($msg['sent_at'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- الرسائل الفاشلة -->
                <div id="content-failed" class="tab-content hidden">
                    <?php if (empty($failed_messages)): ?>
                        <div class="text-center py-12">
                            <div class="text-6xl mb-4">✅</div>
                            <p class="text-xl text-gray-600 font-bold">لا توجد رسائل فاشلة</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($failed_messages as $msg): ?>
                                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <div>
                                            <p class="font-bold text-gray-800">
                                                📱 <?= htmlspecialchars($msg['phone']) ?>
                                                <?php if ($msg['citizen_name']): ?>
                                                    - <?= htmlspecialchars($msg['citizen_name']) ?>
                                                <?php endif; ?>
                                            </p>
                                            <p class="text-sm text-gray-600">
                                                <?= htmlspecialchars($msg['message_type']) ?>
                                            </p>
                                        </div>
                                        <p class="text-sm text-red-700 font-bold">❌ فشل</p>
                                    </div>
                                    <?php if ($msg['error_message']): ?>
                                        <p class="text-xs text-red-600 bg-red-100 p-2 rounded">
                                            <?= htmlspecialchars($msg['error_message']) ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <!-- إعادة المحاولة -->
                                    <form method="POST" class="mt-2">
                                        <input type="hidden" name="log_id" value="<?= $msg['id'] ?>">
                                        <input type="hidden" name="new_status" value="pending">
                                        <button type="submit" name="update_status" class="bg-yellow-500 text-white px-3 py-1 rounded text-sm hover:bg-yellow-600 transition">
                                            🔄 إعادة المحاولة
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- تعليمات -->
        <div class="bg-gradient-to-r from-blue-50 to-green-50 border-2 border-blue-300 rounded-lg p-6">
            <h3 class="text-xl font-bold text-blue-900 mb-4 flex items-center gap-2">
                <span class="text-2xl">📖</span>
                <span>كيفية الاستخدام</span>
            </h3>
            
            <!-- الطريقة الموصى بها -->
            <div class="bg-green-100 border-2 border-green-400 rounded-lg p-4 mb-4">
                <h4 class="font-bold text-green-900 mb-2 flex items-center gap-2">
                    <span class="text-xl">⚡</span>
                    <span>الطريقة السريعة (موصى بها):</span>
                </h4>
                <ol class="space-y-2 text-green-800 text-sm">
                    <li><strong>1.</strong> اضغط على زر <strong>"⚡ إرسال سريع"</strong></li>
                    <li><strong>2.</strong> سيفتح WhatsApp Web (أو يستخدم النافذة المفتوحة)</li>
                    <li><strong>3.</strong> انتظر تحميل الصفحة وظهور الرسالة</li>
                    <li><strong>4.</strong> اضغط إرسال في WhatsApp</li>
                    <li><strong>5.</strong> اضغط "موافق" في الرسالة المنبثقة → تحديث تلقائي! ✅</li>
                </ol>
                <p class="text-green-700 text-xs mt-2">
                    💡 <strong>ميزة:</strong> إذا كان WhatsApp Web مفتوح مسبقاً، سيستخدم نفس النافذة!
                </p>
            </div>
            
            <!-- الطريقة اليدوية -->
            <div class="bg-blue-100 border-2 border-blue-300 rounded-lg p-4">
                <h4 class="font-bold text-blue-900 mb-2 flex items-center gap-2">
                    <span class="text-xl">👨‍💼</span>
                    <span>الطريقة اليدوية (بديلة):</span>
                </h4>
                <ol class="space-y-2 text-blue-800 text-sm">
                    <li><strong>1.</strong> اضغط "📋 نسخ" لنسخ الرسالة</li>
                    <li><strong>2.</strong> اضغط "💬 WhatsApp" لفتح WhatsApp Web</li>
                    <li><strong>3.</strong> الصق الرسالة يدوياً وأرسلها</li>
                    <li><strong>4.</strong> ارجع لهذه الصفحة واضغط "✅ تم"</li>
                </ol>
            </div>
            
            <!-- نصائح -->
            <div class="mt-4 bg-yellow-50 border border-yellow-300 rounded-lg p-3">
                <p class="text-yellow-800 text-sm font-bold mb-1">⚠️ ملاحظات مهمة:</p>
                <ul class="text-yellow-700 text-xs space-y-1">
                    <li>• يجب أن تكون مسجل دخولك في WhatsApp Web</li>
                    <li>• إذا لم تظهر الرسالة تلقائياً، انسخها يدوياً</li>
                    <li>• الصفحة تتحدث تلقائياً كل 30 ثانية</li>
                    <li>• يمكنك إبقاء نافذة WhatsApp Web مفتوحة وإرسال عدة رسائل</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // نسخ الرسالة
        function copyMessage(id) {
            const messageElement = document.getElementById('message-' + id);
            const text = messageElement.textContent;
            
            navigator.clipboard.writeText(text).then(() => {
                alert('✅ تم نسخ الرسالة!');
            }).catch(err => {
                console.error('خطأ في النسخ:', err);
                alert('❌ فشل في نسخ الرسالة');
            });
        }

        // إرسال سريع
        function quickSend(id, phone, message) {
            // تنظيف رقم الهاتف (إزالة المسافات والرموز الخاصة)
            const cleanPhone = phone.replace(/\s+/g, '').replace(/[^0-9+]/g, '');
            
            // إنشاء رابط WhatsApp
            const whatsappUrl = `https://web.whatsapp.com/send?phone=${cleanPhone}&text=${encodeURIComponent(decodeURIComponent(message))}`;
            
            // محاولة فتح في نفس النافذة إذا كانت موجودة
            let whatsappWindow;
            
            // التحقق من وجود نافذة WhatsApp مفتوحة مسبقاً
            if (window.whatsappTab && !window.whatsappTab.closed) {
                // استخدام النافذة الموجودة
                window.whatsappTab.location.href = whatsappUrl;
                window.whatsappTab.focus();
                whatsappWindow = window.whatsappTab;
            } else {
                // فتح نافذة جديدة
                window.whatsappTab = window.open(whatsappUrl, 'WhatsAppWindow', 'width=1000,height=800,scrollbars=yes,resizable=yes');
                whatsappWindow = window.whatsappTab;
            }
            
            // التحقق من نجاح فتح النافذة
            if (!whatsappWindow) {
                alert('⚠️ تم حظر النافذة المنبثقة!\n\nيرجى السماح بالنوافذ المنبثقة لهذا الموقع.');
                return;
            }
            
            // الانتظار قليلاً ثم إظهار رسالة التأكيد
            setTimeout(() => {
                const confirmed = confirm(
                    '📱 تم فتح WhatsApp Web.\n\n' +
                    '✅ الخطوات:\n' +
                    '1️⃣ انتظر تحميل WhatsApp Web\n' +
                    '2️⃣ تأكد من ظهور الرسالة في حقل الكتابة\n' +
                    '3️⃣ اضغط إرسال في WhatsApp\n' +
                    '4️⃣ ثم اضغط "موافق" هنا\n\n' +
                    '⚠️ ملاحظة: إذا لم تظهر الرسالة، انسخها يدوياً\n\n' +
                    'هل تم إرسال الرسالة بنجاح؟'
                );
                
                if (confirmed) {
                    // تحديث الحالة تلقائياً
                    document.getElementById('form-sent-' + id).submit();
                }
            }, 1500); // انتظار 1.5 ثانية
        }

        // التبديل بين التبويبات
        function showTab(tabName) {
            // إخفاء جميع المحتويات
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // إزالة التنسيق من جميع الأزرار
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('border-yellow-500', 'text-yellow-700', 'bg-yellow-50');
                button.classList.remove('border-green-500', 'text-green-700', 'bg-green-50');
                button.classList.remove('border-red-500', 'text-red-700', 'bg-red-50');
                button.classList.add('border-transparent', 'text-gray-600');
            });
            
            // إظهار المحتوى المطلوب
            document.getElementById('content-' + tabName).classList.remove('hidden');
            
            // تنسيق الزر النشط
            const activeButton = document.getElementById('tab-' + tabName);
            activeButton.classList.remove('border-transparent', 'text-gray-600');
            
            if (tabName === 'pending') {
                activeButton.classList.add('border-yellow-500', 'text-yellow-700', 'bg-yellow-50');
            } else if (tabName === 'sent') {
                activeButton.classList.add('border-green-500', 'text-green-700', 'bg-green-50');
            } else if (tabName === 'failed') {
                activeButton.classList.add('border-red-500', 'text-red-700', 'bg-red-50');
            }
        }

        // تحديث تلقائي كل 30 ثانية
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>

