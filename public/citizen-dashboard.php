<?php
/**
 * لوحة تحكم المواطن الشخصية
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
            <p class="text-gray-700 mb-4">يرجى التحقق من أن MySQL مشغل في XAMPP</p>
            <a href="index.php" class="inline-block bg-blue-600 text-white px-4 py-2 rounded">العودة</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$error_message = '';
$citizen = null;
$requests = [];
$complaints = [];
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
            
            // جلب شكاوى المواطن
            try {
                $originalPhone = $citizen['phone'] ?? '';
                
                error_log("=== Fetching Complaints Debug ===");
                error_log("Citizen ID: " . ($citizen['id'] ?? 'NULL'));
                error_log("Citizen Phone: " . $originalPhone);
                
                // استخدام استعلام بسيط جداً أولاً (بدون subquery)
                $sql = "SELECT * FROM complaints WHERE citizen_id = ? OR citizen_phone = ? ORDER BY created_at DESC LIMIT 50";
                
                error_log("SQL Query (Simple): " . $sql);
                error_log("Params: citizen_id=" . ($citizen['id'] ?? 'NULL') . ", citizen_phone=" . $originalPhone);
                
                $complaintsStmt = $db->prepare($sql);
                $complaintsStmt->execute([$citizen['id'], $originalPhone]);
                $complaints = $complaintsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("Found " . count($complaints) . " complaints (simple query)");
                
                // إضافة category_display و updates_count يدوياً
                foreach ($complaints as &$complaint) {
                    $complaint['category_display'] = $complaint['category'] ?? $complaint['complaint_type'] ?? 'غير محدد';
                    
                    // جلب عدد التحديثات
                    try {
                        $updatesStmt = $db->prepare("SELECT COUNT(*) as count FROM complaint_updates WHERE complaint_id = ? AND is_visible_to_citizen = 1");
                        $updatesStmt->execute([$complaint['id']]);
                        $updatesResult = $updatesStmt->fetch(PDO::FETCH_ASSOC);
                        $complaint['updates_count'] = $updatesResult['count'] ?? 0;
                    } catch (Exception $e) {
                        $complaint['updates_count'] = 0;
                    }
                }
                unset($complaint); // إزالة المرجع
                
                if (!empty($complaints)) {
                    error_log("First complaint ID: " . $complaints[0]['id']);
                }
            } catch (Exception $e) {
                error_log("Error fetching complaints: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                $complaints = [];
            }
            
            // جلب رسائل المواطن
            $messages = $accountHelper->getCitizenMessages($citizen['id']);
            
            // جلب الإحصائيات
            $stats = $accountHelper->getCitizenStats($citizen['phone']);
            
            // إضافة إحصائيات الشكاوى
            $stats['total_complaints'] = count($complaints);
            $stats['active_complaints'] = count(array_filter($complaints, function($c) {
                return in_array($c['status'], ['جديدة', 'قيد المراجعة', 'قيد المعالجة']);
            }));
            $stats['completed_complaints'] = count(array_filter($complaints, function($c) {
                return $c['status'] === 'مكتملة';
            }));
            
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
        
        // جلب شكاوى المواطن
        try {
            $originalPhone = $citizen['phone'] ?? '';
            
            error_log("=== Fetching Complaints Debug (Session) ===");
            error_log("Citizen ID: " . ($citizen['id'] ?? 'NULL'));
            error_log("Citizen Phone: " . $originalPhone);
            
            // استخدام استعلام بسيط جداً أولاً (بدون subquery)
            $sql = "SELECT * FROM complaints WHERE citizen_id = ? OR citizen_phone = ? ORDER BY created_at DESC LIMIT 50";
            
            error_log("SQL Query (Session, Simple): " . $sql);
            error_log("Params (Session): citizen_id=" . ($citizen['id'] ?? 'NULL') . ", citizen_phone=" . $originalPhone);
            
            $complaintsStmt = $db->prepare($sql);
            $complaintsStmt->execute([$citizen['id'], $originalPhone]);
            $complaints = $complaintsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Found " . count($complaints) . " complaints (Session, simple query)");
            
            // إضافة category_display و updates_count يدوياً
            foreach ($complaints as &$complaint) {
                $complaint['category_display'] = $complaint['category'] ?? $complaint['complaint_type'] ?? 'غير محدد';
                
                // جلب عدد التحديثات
                try {
                    $updatesStmt = $db->prepare("SELECT COUNT(*) as count FROM complaint_updates WHERE complaint_id = ? AND is_visible_to_citizen = 1");
                    $updatesStmt->execute([$complaint['id']]);
                    $updatesResult = $updatesStmt->fetch(PDO::FETCH_ASSOC);
                    $complaint['updates_count'] = $updatesResult['count'] ?? 0;
                } catch (Exception $e) {
                    $complaint['updates_count'] = 0;
                }
            }
            unset($complaint); // إزالة المرجع
            
            if (!empty($complaints)) {
                error_log("First complaint ID (Session): " . $complaints[0]['id']);
            }
        } catch (Exception $e) {
            error_log("Error fetching complaints: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            $complaints = [];
        }
        
        $messages = $accountHelper->getCitizenMessages($citizen['id']);
        $stats = $accountHelper->getCitizenStats($citizen['phone']);
        
        // إضافة إحصائيات الشكاوى
        $stats['total_complaints'] = count($complaints);
        $stats['active_complaints'] = count(array_filter($complaints, function($c) {
            return in_array($c['status'], ['جديدة', 'قيد المراجعة', 'قيد المعالجة']);
        }));
        $stats['completed_complaints'] = count(array_filter($complaints, function($c) {
            return $c['status'] === 'مكتملة';
        }));
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
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-blue-50 rounded-lg p-4 text-center">
                        <div class="text-3xl font-bold text-blue-600"><?= $stats['total_requests'] ?? count($requests) ?></div>
                        <div class="text-sm text-blue-800">إجمالي الطلبات</div>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4 text-center">
                        <div class="text-3xl font-bold text-green-600"><?= $stats['active_requests'] ?? 0 ?></div>
                        <div class="text-sm text-green-800">طلبات نشطة</div>
                    </div>
                    <div class="bg-red-50 rounded-lg p-4 text-center">
                        <div class="text-3xl font-bold text-red-600"><?= $stats['total_complaints'] ?? count($complaints) ?></div>
                        <div class="text-sm text-red-800">إجمالي الشكاوى</div>
                    </div>
                    <div class="bg-orange-50 rounded-lg p-4 text-center">
                        <div class="text-3xl font-bold text-orange-600"><?= $stats['active_complaints'] ?? 0 ?></div>
                        <div class="text-sm text-orange-800">شكاوى نشطة</div>
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

            <!-- تبويبات الطلبات والشكاوى -->
            <div class="bg-white rounded-2xl shadow-xl p-4 md:p-8 mb-8">
                <!-- أزرار التبويبات -->
                <div class="flex flex-col sm:flex-row gap-3 mb-6 border-b-2 border-gray-200 pb-4">
                    <button id="tab-requests" 
                            onclick="switchTab('requests')" 
                            class="tab-button flex-1 px-4 md:px-6 py-3 md:py-4 rounded-lg font-bold text-base md:text-lg transition-all duration-300 transform hover:scale-105 active-tab">
                        <div class="flex items-center justify-center gap-2 md:gap-3">
                            <span class="text-2xl md:text-3xl">📋</span>
                            <div class="text-right">
                                <div class="font-bold">طلباتي</div>
                                <div class="text-xs md:text-sm font-normal opacity-75"><?= count($requests) ?> طلب</div>
                            </div>
                        </div>
                    </button>
                    
                    <button id="tab-complaints" 
                            onclick="switchTab('complaints')" 
                            class="tab-button flex-1 px-4 md:px-6 py-3 md:py-4 rounded-lg font-bold text-base md:text-lg transition-all duration-300 transform hover:scale-105">
                        <div class="flex items-center justify-center gap-2 md:gap-3">
                            <span class="text-2xl md:text-3xl">📢</span>
                            <div class="text-right">
                                <div class="font-bold">شكواي</div>
                                <div class="text-xs md:text-sm font-normal opacity-75"><?= count($complaints) ?> شكوى</div>
                            </div>
                        </div>
                    </button>
                </div>
                
                <!-- محتوى الطلبات -->
                <div id="content-requests" class="tab-content">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl md:text-2xl font-bold text-gray-800">📋 طلباتي</h2>
                        <a href="citizen-requests.php" class="bg-blue-600 text-white px-3 md:px-4 py-2 rounded-lg font-bold hover:bg-blue-700 transition text-sm md:text-base">
                            ➕ طلب جديد
                        </a>
                    </div>
                    
                    <?php if (empty($requests)): ?>
                        <div class="text-center py-12">
                            <div class="text-6xl mb-4">📭</div>
                            <p class="text-lg md:text-xl text-gray-600 mb-4">لا توجد طلبات حتى الآن</p>
                            <a href="citizen-requests.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition">
                                ➕ تقديم طلب جديد
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($requests as $request): ?>
                                <div class="border-2 border-gray-200 rounded-xl p-4 md:p-6 hover:border-blue-400 transition">
                                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-3">
                                        <div class="flex-1">
                                            <h3 class="text-base md:text-lg font-bold text-gray-800 mb-1">
                                                <?= htmlspecialchars($request['request_title'] ?? $request['type_name']) ?>
                                            </h3>
                                            <p class="text-sm text-gray-600">
                                                🔢 <?= htmlspecialchars($request['tracking_number']) ?>
                                            </p>
                                        </div>
                                        <span class="px-3 py-1 rounded-full text-xs md:text-sm font-bold self-start
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
                                    <div class="flex flex-col sm:flex-row gap-2">
                                        <a href="citizen-request-details.php?tracking=<?= urlencode($request['tracking_number']) ?>" 
                                           class="inline-block bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-700 transition text-center">
                                            👁️ التفاصيل الكاملة
                                        </a>
                                        <a href="track-request.php?tracking=<?= urlencode($request['tracking_number']) ?>" 
                                           class="inline-block bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-green-700 transition text-center">
                                            🔍 تتبع
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- محتوى الشكاوى -->
                <div id="content-complaints" class="tab-content hidden">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl md:text-2xl font-bold text-gray-800">📢 شكواي</h2>
                        <a href="citizen-complaints.php" class="bg-red-600 text-white px-3 md:px-4 py-2 rounded-lg font-bold hover:bg-red-700 transition text-sm md:text-base">
                            ➕ شكوى جديدة
                        </a>
                    </div>
                    
                    <?php 
                    // Debug: عرض معلومات التشخيص (يمكن إزالة true لإخفاءه)
                    if (isset($_GET['debug'])) {
                        echo "<div class='bg-yellow-50 border border-yellow-400 rounded p-4 mb-4 text-sm'>";
                        echo "<strong>🔍 Debug Info:</strong><br>";
                        echo "عدد الشكاوى في المتغير: " . count($complaints) . "<br>";
                        echo "Citizen ID: " . ($citizen['id'] ?? 'N/A') . "<br>";
                        echo "Citizen Phone: " . ($citizen['phone'] ?? 'N/A') . "<br>";
                        echo "Is empty: " . (empty($complaints) ? 'YES' : 'NO') . "<br>";
                        if (!empty($complaints)) {
                            echo "<strong>الشكاوى:</strong><br>";
                            echo "<pre class='mt-2 text-xs bg-white p-2 rounded overflow-auto max-h-64'>";
                            print_r($complaints);
                            echo "</pre>";
                        } else {
                            echo "<strong class='text-red-600'>⚠️ لا توجد شكاوى في المتغير \$complaints</strong><br>";
                            // محاولة جلب مباشرة
                            try {
                                $testStmt = $db->prepare("SELECT * FROM complaints WHERE citizen_id = ? OR citizen_phone = ? LIMIT 5");
                                $testStmt->execute([$citizen['id'], $citizen['phone']]);
                                $testComplaints = $testStmt->fetchAll(PDO::FETCH_ASSOC);
                                echo "<strong>اختبار مباشر:</strong> وجد " . count($testComplaints) . " شكوى<br>";
                                if (!empty($testComplaints)) {
                                    echo "<pre class='mt-2 text-xs bg-white p-2 rounded overflow-auto max-h-64'>";
                                    print_r($testComplaints);
                                    echo "</pre>";
                                }
                            } catch (Exception $e) {
                                echo "خطأ في الاختبار المباشر: " . $e->getMessage();
                            }
                        }
                        echo "</div>";
                    }
                    ?>
                    
                    <?php if (empty($complaints)): ?>
                        <div class="text-center py-12">
                            <div class="text-6xl mb-4">📭</div>
                            <p class="text-lg md:text-xl text-gray-600 mb-4">لا توجد شكاوى حتى الآن</p>
                            <a href="citizen-complaints.php" class="inline-block mt-4 bg-red-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-red-700 transition">
                                ➕ تقديم شكوى جديدة
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($complaints as $complaint): ?>
                                <div class="border-2 border-gray-200 rounded-xl p-4 md:p-6 hover:border-red-400 transition">
                                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-3">
                                        <div class="flex-1">
                                            <h3 class="text-base md:text-lg font-bold text-gray-800 mb-1">
                                                <?= htmlspecialchars($complaint['subject']) ?>
                                            </h3>
                                            <p class="text-sm text-gray-600">
                                                🔢 <?= htmlspecialchars($complaint['complaint_number'] ?? '#' . $complaint['id']) ?>
                                            </p>
                                        </div>
                                        <span class="px-3 py-1 rounded-full text-xs md:text-sm font-bold self-start
                                            <?php 
                                            switch($complaint['status']) {
                                                case 'جديدة': echo 'bg-red-100 text-red-800'; break;
                                                case 'قيد المراجعة': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'قيد المعالجة': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'مكتملة': echo 'bg-green-100 text-green-800'; break;
                                                case 'مؤجلة': echo 'bg-gray-100 text-gray-800'; break;
                                                case 'مرفوضة': echo 'bg-red-100 text-red-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?= htmlspecialchars($complaint['status']) ?>
                                        </span>
                                    </div>
                                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 text-sm text-gray-600 mb-3">
                                        <?php 
                                        // دعم أسماء الأعمدة المختلفة: category, complaint_type, category_display
                                        $category = $complaint['category_display'] ?? $complaint['category'] ?? $complaint['complaint_type'] ?? 'غير محدد';
                                        $dateField = $complaint['created_at'] ?? $complaint['date_submitted'] ?? 'now';
                                        ?>
                                        <span>📂 <?= htmlspecialchars($category) ?></span>
                                        <span>📅 <?= date('Y-m-d', strtotime($dateField)) ?></span>
                                        <?php if (isset($complaint['updates_count']) && $complaint['updates_count'] > 0): ?>
                                            <span>💬 <?= $complaint['updates_count'] ?> تحديث</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex gap-2">
                                        <a href="citizen-complaint-details.php?number=<?= urlencode($complaint['complaint_number'] ?? $complaint['id']) ?>" 
                                           class="inline-block bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-red-700 transition text-center">
                                            👁️ التفاصيل الكاملة
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
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
    
    <style>
        /* تنسيق التبويبات */
        .tab-button {
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            color: #6b7280;
            border: 2px solid transparent;
        }
        
        .tab-button.active-tab {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border-color: #1d4ed8;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .tab-button:hover:not(.active-tab) {
            background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);
        }
        
        .tab-content {
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* تحسينات للشاشات الصغيرة */
        @media (max-width: 640px) {
            .tab-button {
                padding: 0.75rem 1rem;
                font-size: 0.875rem;
            }
        }
    </style>
    
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
        
        // التبديل بين التبويبات
        function switchTab(tabName) {
            // إخفاء جميع المحتويات
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // إزالة حالة النشاط من جميع الأزرار
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active-tab');
            });
            
            // إظهار المحتوى المحدد
            const content = document.getElementById('content-' + tabName);
            if (content) {
                content.classList.remove('hidden');
            }
            
            // إضافة حالة النشاط للزر المحدد
            const button = document.getElementById('tab-' + tabName);
            if (button) {
                button.classList.add('active-tab');
            }
            
            // حفظ التبويب النشط في localStorage
            localStorage.setItem('activeTab', tabName);
        }
        
        // استعادة التبويب النشط عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            const savedTab = localStorage.getItem('activeTab');
            if (savedTab && (savedTab === 'requests' || savedTab === 'complaints')) {
                switchTab(savedTab);
            } else {
                // افتراضياً، عرض تبويب الطلبات
                switchTab('requests');
            }
        });
    </script>
</body>
</html>

