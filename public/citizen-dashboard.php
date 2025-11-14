<?php
/**
 * لوحة تحكم المواطن الشخصية
 * بلدية تكريت - عكار، شمال لبنان
 */

header('Content-Type: text/html; charset=utf-8');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$error_message = '';
$citizen = null;
$requests = [];
$messages = [];
$stats = [];

// تحميل مساعد حسابات المواطنين
require_once '../includes/CitizenAccountHelper.php';
$accountHelper = new CitizenAccountHelper($db);

// التحقق من رمز الدخول الثابت
if (isset($_GET['code'])) {
    $accessCode = trim($_GET['code']);
    
    // مسح الـ Session القديمة أولاً
    session_start();
    session_unset();
    
    try {
        // الحصول على الحساب برمز الدخول
        $accountResult = $accountHelper->getAccountByAccessCode($accessCode);
        
        if ($accountResult['success']) {
            $citizen = $accountResult['account'];
            
            // حفظ معلومات المواطن في الجلسة
            $_SESSION['citizen_id'] = $citizen['id'];
            $_SESSION['citizen_phone'] = $citizen['phone'];
            $_SESSION['citizen_name'] = $citizen['name'];
            $_SESSION['access_code'] = $accessCode;
            
            // جلب طلبات المواطن
            $requests = $accountHelper->getCitizenRequests($citizen['phone']);
            
            // DEBUG: عرض معلومات التصحيح
            error_log("=== DEBUG citizen-dashboard.php ===");
            error_log("Citizen Phone: " . $citizen['phone']);
            error_log("Requests Count: " . count($requests));
            error_log("Requests Data: " . print_r($requests, true));
            
            // إذا لم يجد طلبات، جرّب البحث المباشر في قاعدة البيانات
            if (empty($requests)) {
                error_log("Trying direct database query...");
                $directStmt = $db->query("SELECT COUNT(*) as total FROM citizen_requests");
                $totalRequests = $directStmt->fetch(PDO::FETCH_ASSOC)['total'];
                error_log("Total requests in database: " . $totalRequests);
                
                // جلب جميع أرقام الهواتف
                $phonesStmt = $db->query("SELECT DISTINCT citizen_phone FROM citizen_requests LIMIT 10");
                $allPhones = $phonesStmt->fetchAll(PDO::FETCH_COLUMN);
                error_log("Sample phones in database: " . print_r($allPhones, true));
            }
            
            // جلب رسائل المواطن
            $messages = $accountHelper->getCitizenMessages($citizen['id']);
            
            // جلب الإحصائيات
            $stats = $accountHelper->getCitizenStats($citizen['phone']);
            
        } else {
            $error_message = $accountResult['error'] ?? "رمز الدخول غير صحيح";
        }
        
    } catch (Exception $e) {
        $error_message = "خطأ في التحقق من رمز الدخول: " . $e->getMessage();
    }
} elseif (isset($_SESSION['access_code'])) {
    // إذا كان المواطن مسجل دخول بالفعل
    $accessCode = $_SESSION['access_code'];
    $accountResult = $accountHelper->getAccountByAccessCode($accessCode);
    
    if ($accountResult['success']) {
        $citizen = $accountResult['account'];
        $requests = $accountHelper->getCitizenRequests($citizen['phone']);
        $messages = $accountHelper->getCitizenMessages($citizen['id']);
        $stats = $accountHelper->getCitizenStats($citizen['phone']);
    } else {
        session_destroy();
        $error_message = "انتهت صلاحية الجلسة. يرجى إدخال رمز الدخول مرة أخرى.";
    }
} else {
    $error_message = "يرجى إدخال رمز الدخول الخاص بك";
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حسابي الشخصي - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header with Back Button -->
    <div class="bg-white shadow-md mb-6">
        <div class="container mx-auto px-4 py-4 max-w-6xl">
            <div class="flex items-center justify-between">
                <a href="index.php" class="flex items-center gap-2 text-blue-600 hover:text-blue-800 font-bold transition group">
                    <svg class="w-6 h-6 transform group-hover:-translate-x-1 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    <span>العودة للصفحة الرئيسية</span>
                </a>
                <div class="flex items-center gap-2">
                    <span class="text-2xl">🏛️</span>
                    <span class="font-bold text-gray-800 hidden sm:inline">بلدية تكريت</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        
        <?php if ($error_message && !isset($_GET['code'])): ?>
            <!-- نموذج إدخال رمز الدخول -->
            <div class="bg-white rounded-2xl shadow-xl p-8 mb-8">
                <div class="text-center mb-6">
                    <div class="text-6xl mb-4">🔐</div>
                    <h2 class="text-3xl font-bold text-gray-800 mb-2">الدخول للحساب الشخصي</h2>
                    <p class="text-gray-600">أدخل رمز الدخول الخاص بك</p>
                </div>
                
                <?php if ($error_message != "يرجى إدخال رمز الدخول الخاص بك"): ?>
                    <div class="bg-red-50 border-2 border-red-300 rounded-lg p-4 mb-6 text-center">
                        <p class="text-red-700"><?= htmlspecialchars($error_message) ?></p>
                    </div>
                <?php endif; ?>
                
                <form method="GET" class="max-w-md mx-auto">
                    <div class="mb-6">
                        <label class="block text-gray-700 font-bold mb-2">رمز الدخول (مثال: TKT-12345)</label>
                        <input type="text" 
                               name="code" 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none text-center text-xl font-bold tracking-wider uppercase"
                               placeholder="TKT-12345"
                               required
                               pattern="TKT-[0-9]{5}"
                               title="الرمز يجب أن يبدأ بـ TKT- متبوعاً بـ 5 أرقام">
                    </div>
                    
                    <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-lg font-bold hover:bg-blue-700 transition text-lg">
                        🔓 دخول
                    </button>
                </form>
                
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <p class="text-center text-gray-600 mb-4">لا تملك رمز دخول؟</p>
                    <div class="flex gap-3 justify-center flex-wrap">
                        <a href="citizen-requests.php" class="bg-green-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-green-700 transition">
                            📝 تقديم طلب جديد
                        </a>
                        <a href="track-request.php" class="bg-purple-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-purple-700 transition">
                            🔍 تتبع طلب
                        </a>
                    </div>
                </div>
            </div>
        <?php elseif ($citizen): ?>
            <!-- لوحة التحكم -->
            <div class="bg-white rounded-2xl shadow-xl p-8 mb-8">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800 mb-2">
                            مرحباً، <?= htmlspecialchars($citizen['name']) ?> 👋
                        </h1>
                        <p class="text-gray-600">📱 <?= htmlspecialchars($citizen['phone']) ?></p>
                    </div>
                    <div class="text-5xl">👤</div>
                </div>
                
                <!-- الإحصائيات -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-blue-50 rounded-lg p-4 text-center">
                        <div class="text-3xl font-bold text-blue-600"><?= $stats['total_requests'] ?? count($requests) ?></div>
                        <div class="text-sm text-blue-800">إجمالي الطلبات</div>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4 text-center">
                        <div class="text-3xl font-bold text-green-600"><?= $stats['active_requests'] ?? 0 ?></div>
                        <div class="text-sm text-green-800">طلبات نشطة</div>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-4 text-center">
                        <div class="text-3xl font-bold text-yellow-600"><?= $stats['completed_requests'] ?? 0 ?></div>
                        <div class="text-sm text-yellow-800">مكتملة</div>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-4 text-center">
                        <div class="text-3xl font-bold text-purple-600"><?= count($messages) ?></div>
                        <div class="text-sm text-purple-800">الرسائل</div>
                    </div>
                </div>
                
                <!-- رمز الدخول الثابت -->
                <div class="bg-gradient-to-r from-blue-50 to-purple-50 rounded-lg p-4 mb-6 border-2 border-blue-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">🔐 رمز الدخول الثابت</p>
                            <p class="text-2xl font-bold text-blue-800 tracking-wider"><?= htmlspecialchars($citizen['permanent_access_code']) ?></p>
                        </div>
                        <button onclick="copyAccessCode('<?= htmlspecialchars($citizen['permanent_access_code']) ?>')" 
                                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                            📋 نسخ
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">احتفظ بهذا الرمز للدخول لحسابك في أي وقت</p>
                </div>
            </div>

           

            <!-- الطلبات -->
            <div class="bg-white rounded-2xl shadow-xl p-8 mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">📋 طلباتي</h2>
                
                <?php if (empty($requests)): ?>
                    <div class="text-center py-12">
                        <div class="text-6xl mb-4">📭</div>
                        <p class="text-xl text-gray-600">لا توجد طلبات حتى الآن</p>
                        <a href="citizen-requests.php" class="inline-block mt-4 bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition">
                            ➕ تقديم طلب جديد
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($requests as $request): ?>
                            <div class="border-2 border-gray-200 rounded-xl p-6 hover:border-blue-400 transition">
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <h3 class="text-lg font-bold text-gray-800 mb-1">
                                            <?= htmlspecialchars($request['request_title'] ?? $request['type_name']) ?>
                                        </h3>
                                        <p class="text-sm text-gray-600">
                                            🔢 <?= htmlspecialchars($request['tracking_number']) ?>
                                        </p>
                                    </div>
                                    <span class="px-3 py-1 rounded-full text-sm font-bold 
                                        <?php 
                                        switch($request['status']) {
                                            case 'جديد': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'قيد المراجعة': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'قيد التنفيذ': echo 'bg-purple-100 text-purple-800'; break;
                                            case 'مكتمل': echo 'bg-green-100 text-green-800'; break;
                                            case 'مرفوض': echo 'bg-red-100 text-red-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?= htmlspecialchars($request['status']) ?>
                                    </span>
                                </div>
                                <div class="text-sm text-gray-600 mb-3">
                                    📅 <?= date('Y-m-d', strtotime($request['created_at'])) ?>
                                </div>
                                <div class="flex gap-2">
                                    <a href="citizen-request-details.php?tracking=<?= urlencode($request['tracking_number']) ?>" 
                                       class="inline-block bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-700 transition">
                                        👁️ التفاصيل الكاملة
                                    </a>
                                    <a href="track-request.php?tracking=<?= urlencode($request['tracking_number']) ?>" 
                                       class="inline-block bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-green-700 transition">
                                        🔍 تتبع
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- الرسائل -->
            <?php if (!empty($messages)): ?>
                <div class="bg-white rounded-2xl shadow-xl p-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">💬 رسائل البلدية</h2>
                    <div class="space-y-3">
                        <?php foreach ($messages as $message): ?>
                            <div class="bg-blue-50 border-r-4 border-blue-500 rounded-lg p-4">
                                <div class="flex items-start justify-between mb-2">
                                    <h3 class="font-bold text-gray-800"><?= htmlspecialchars($message['title']) ?></h3>
                                    <span class="text-xs text-gray-500">
                                        <?= date('Y-m-d', strtotime($message['created_at'])) ?>
                                    </span>
                                </div>
                                <p class="text-gray-700 text-sm"><?= nl2br(htmlspecialchars($message['message'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

        <!-- Footer -->
        <div class="mt-8 text-center text-gray-600">
            <p class="font-bold">🏛️ بلدية تكريت - عكار، شمال لبنان</p>
            <p class="text-sm mt-1">في خدمة المواطن دائماً</p>
        </div>
    </div>
    
    <script>
        // نسخ رمز الدخول
        function copyAccessCode(code) {
            navigator.clipboard.writeText(code).then(() => {
                alert('✅ تم نسخ رمز الدخول!');
            }).catch(err => {
                console.error('خطأ في النسخ:', err);
                // طريقة بديلة للنسخ
                const textarea = document.createElement('textarea');
                textarea.value = code;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('✅ تم نسخ رمز الدخول!');
            });
        }
    </script>
</body>
</html>

