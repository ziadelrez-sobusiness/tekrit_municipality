<?php
// بدء session قبل أي output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: text/html; charset=utf-8');

// تحميل CSRF Protection
if (file_exists(__DIR__ . '/../includes/csrf_middleware.php')) {
    require_once __DIR__ . '/../includes/csrf_middleware.php';
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    ?>
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>خطأ في الاتصال - بلدية تكريت</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    </head>
    <body class="bg-gray-50 font-['Cairo']">
        <div class="min-h-screen flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full text-center">
                <div class="text-6xl mb-4">⚠️</div>
                <h1 class="text-2xl font-bold text-red-600 mb-4">خطأ في الاتصال بقاعدة البيانات</h1>
                <p class="text-gray-700 mb-6">
                    يرجى التحقق من:
                </p>
                <ul class="text-right text-gray-600 mb-6 space-y-2">
                    <li>✅ أن MySQL مشغل في XAMPP</li>
                    <li>✅ أن قاعدة البيانات موجودة</li>
                    <li>✅ أن إعدادات الاتصال صحيحة</li>
                </ul>
                <a href="index.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition">
                    العودة للصفحة الرئيسية
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$db->exec("SET NAMES utf8mb4");

$success_message = '';
$error_message = '';

// معالجة تقديم الشكوى
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_complaint'])) {
    $csrfResult = csrf_protect(false);
    
    if (!$csrfResult) {
        $error_message = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        $citizen_name = trim($_POST['citizen_name']);
        $citizen_phone = trim($_POST['citizen_phone']);
        $citizen_email = trim($_POST['citizen_email'] ?? '');
        $citizen_address = trim($_POST['citizen_address'] ?? '');
        $subject = trim($_POST['subject']);
        $description = trim($_POST['description']);
        $category = $_POST['category'] ?? 'أخرى';
        $priority = $_POST['priority'] ?? 'متوسطة';
        
        if (!empty($citizen_name) && !empty($citizen_phone) && !empty($subject) && !empty($description)) {
            try {
                $db->beginTransaction();
                
                // إنشاء/جلب حساب المواطن
                require_once '../includes/CitizenAccountHelper.php';
                $accountHelper = new CitizenAccountHelper($db);
                $accountResult = $accountHelper->getOrCreateAccount(
                    $citizen_phone,
                    $citizen_name,
                    $citizen_email,
                    null, // national_id
                    null, // telegram_chat_id
                    null  // telegram_username
                );
                
                $citizenId = $accountResult['citizen_id'] ?? null;
                $accessCode = $accountResult['access_code'] ?? null;
                
                // إدراج الشكوى (سيتم توليد complaint_number تلقائياً بواسطة trigger)
                // التحقق من أسماء الأعمدة الفعلية في الجدول
                $columnsStmt = $db->query("SHOW COLUMNS FROM complaints");
                $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
                
                // تحديد اسم عمود الوصف
                $descriptionColumn = 'description';
                if (in_array('details', $columns) && !in_array('description', $columns)) {
                    $descriptionColumn = 'details';
                } elseif (in_array('description', $columns) && !in_array('details', $columns)) {
                    $descriptionColumn = 'description';
                } elseif (in_array('details', $columns)) {
                    $descriptionColumn = 'details'; // الأفضلية لـ details
                }
                
                // تحديد أسماء أعمدة المواطن
                $nameColumn = in_array('citizen_name', $columns) ? 'citizen_name' : 'complainant_name';
                $phoneColumn = in_array('citizen_phone', $columns) ? 'citizen_phone' : 'complainant_phone';
                $emailColumn = in_array('citizen_email', $columns) ? 'citizen_email' : 'complainant_email';
                $addressColumn = in_array('citizen_address', $columns) ? 'citizen_address' : 'complainant_address';
                
                // بناء الاستعلام ديناميكياً
                $columnsList = [];
                $valuesList = [];
                $params = [];
                
                // إدراج citizen_id دائماً إذا كان موجوداً في الجدول (حتى لو كان null)
                if (in_array('citizen_id', $columns)) {
                    $columnsList[] = 'citizen_id';
                    $valuesList[] = '?';
                    $params[] = $citizenId; // قد يكون null، وهذا مقبول
                }
                
                $columnsList[] = $nameColumn;
                $valuesList[] = '?';
                $params[] = $citizen_name;
                
                $columnsList[] = $phoneColumn;
                $valuesList[] = '?';
                $params[] = $citizen_phone;
                
                if (in_array($emailColumn, $columns)) {
                    $columnsList[] = $emailColumn;
                    $valuesList[] = '?';
                    $params[] = $citizen_email;
                }
                
                if (in_array($addressColumn, $columns)) {
                    $columnsList[] = $addressColumn;
                    $valuesList[] = '?';
                    $params[] = $citizen_address;
                }
                
                $columnsList[] = 'subject';
                $valuesList[] = '?';
                $params[] = $subject;
                
                $columnsList[] = $descriptionColumn;
                $valuesList[] = '?';
                $params[] = $description;
                
                if (in_array('category', $columns)) {
                    $columnsList[] = 'category';
                    $valuesList[] = '?';
                    $params[] = $category;
                }
                
                if (in_array('priority', $columns)) {
                    $columnsList[] = 'priority';
                    $valuesList[] = '?';
                    $params[] = $priority;
                }
                
                $columnsList[] = 'status';
                $valuesList[] = "'جديدة'";
                
                if (in_array('created_at', $columns)) {
                    $columnsList[] = 'created_at';
                    $valuesList[] = 'NOW()';
                }
                
                if (in_array('updated_at', $columns)) {
                    $columnsList[] = 'updated_at';
                    $valuesList[] = 'NOW()';
                }
                
                $query = "INSERT INTO complaints (" . implode(', ', $columnsList) . ") VALUES (" . implode(', ', $valuesList) . ")";
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                
                $complaint_id = $db->lastInsertId();
                
                // جلب رقم الشكوى
                $stmt = $db->prepare("SELECT complaint_number FROM complaints WHERE id = ?");
                $stmt->execute([$complaint_id]);
                $complaint_data = $stmt->fetch(PDO::FETCH_ASSOC);
                $complaint_number = $complaint_data['complaint_number'] ?? 'SHK-' . date('Y') . '-' . str_pad($complaint_id, 5, '0', STR_PAD_LEFT);
                
                // إضافة تحديث أولي
                $update_stmt = $db->prepare("
                    INSERT INTO complaint_updates 
                    (complaint_id, update_type, update_text, is_visible_to_citizen, created_at) 
                    VALUES (?, 'status_change', 'تم استلام الشكوى وهي قيد المراجعة', 1, NOW())
                ");
                $update_stmt->execute([$complaint_id]);
                
                $db->commit();
                
                $success_message = "تم تقديم شكواك بنجاح! رقم الشكوى: " . $complaint_number;
                
                // إرسال إشعار Telegram
                try {
                    require_once '../includes/TelegramService.php';
                    
                    // جلب telegram_chat_id من حساب المواطن
                    $telegramChatId = null;
                    $telegramUsername = null;
                    if ($citizenId) {
                        $accountStmt = $db->prepare("SELECT telegram_chat_id, telegram_username FROM citizens_accounts WHERE id = ?");
                        $accountStmt->execute([$citizenId]);
                        $accountData = $accountStmt->fetch(PDO::FETCH_ASSOC);
                        if ($accountData) {
                            $telegramChatId = $accountData['telegram_chat_id'];
                            $telegramUsername = $accountData['telegram_username'];
                        }
                    }
                    
                    $telegramService = new TelegramService($db);
                    
                    // إرسال رسالة ترحيب للشكوى
                    $telegramResult = $telegramService->sendComplaintNotification(
                        [
                            'name' => $citizen_name,
                            'phone' => $citizen_phone,
                            'citizen_id' => $citizenId,
                            'telegram_chat_id' => $telegramChatId,
                            'telegram_username' => $telegramUsername
                        ],
                        [
                            'complaint_id' => $complaint_id,
                            'complaint_number' => $complaint_number,
                            'subject' => $subject,
                            'category' => $category
                        ],
                        $accessCode
                    );
                    
                } catch (Exception $e) {
                    // لا نعرض الخطأ للمواطن
                    error_log("Telegram notification error: " . $e->getMessage());
                }
                
                // تحديث رسالة النجاح
                if ($accessCode) {
                    $success_message .= "<div class='mt-4 pt-4 border-t-2 border-green-300'>";
                    $success_message .= "<p class='font-bold text-green-900 mb-3 text-xl'>🔐 رمز الدخول الثابت</p>";
                    $success_message .= "<p class='text-green-700 text-sm mb-2'>يمكنك الدخول لحسابك الشخصي في أي وقت باستخدام:</p>";
                    $success_message .= "<div class='bg-white rounded-lg p-4 border-2 border-green-400 text-center mb-3'>";
                    $success_message .= "<p class='text-3xl font-bold text-green-800 tracking-wider mb-2'>" . htmlspecialchars($accessCode) . "</p>";
                    $success_message .= "<button onclick=\"copyCode('" . htmlspecialchars($accessCode) . "')\" class='bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm font-bold'>📋 نسخ الرمز</button>";
                    $success_message .= "</div>";
                    $success_message .= "<a href='citizen-dashboard.php?code=" . urlencode($accessCode) . "' class='inline-block bg-purple-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-purple-700 transition mb-3'>👤 دخول للحساب الشخصي</a>";
                    $success_message .= "</div>";
                }
                
            } catch (Exception $e) {
                $db->rollBack();
                $error_message = "حدث خطأ أثناء تقديم الشكوى: " . $e->getMessage();
            }
        } else {
            $error_message = "يرجى ملء جميع الحقول المطلوبة";
        }
    }
}

// جلب إعدادات الموقع
function getSetting($key, $default = '') {
    global $db;
    if (!$db) return $default;
    try {
        $stmt = $db->prepare("SELECT setting_value FROM website_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

$site_title = getSetting('site_title', 'بلدية تكريت');
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقديم شكوى - <?= htmlspecialchars($site_title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-green-50 min-h-screen">
    <?php require_once 'includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <!-- Back Button -->
        <div class="mb-6">
            <a href="index.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium transition">
                <svg class="ml-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                الرجوع إلى الصفحة الرئيسية
            </a>
        </div>
        
        <!-- Page Title -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-800 mb-2">📢 تقديم شكوى</h1>
            <p class="text-gray-600">بلدية تكريت - عكار</p>
        </div>

        <!-- رسائل النجاح والخطأ -->
        <?php if ($success_message): ?>
            <div class="bg-green-50 border-2 border-green-400 rounded-xl shadow-lg p-6 mb-6">
                <div class="text-center mb-4">
                    <div class="text-5xl mb-3">✅</div>
                    <div class="text-green-800 text-lg leading-relaxed">
                        <?= $success_message ?>
                    </div>
                </div>
                <div class="flex gap-3 justify-center flex-wrap">
                    <a href="citizen-dashboard.php<?= isset($accessCode) ? '?code=' . urlencode($accessCode) : '' ?>" class="bg-green-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-green-700 transition inline-flex items-center gap-2">
                        👤 حسابي الشخصي
                    </a>
                    <a href="citizen-complaints.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition inline-flex items-center gap-2">
                        ➕ شكوى جديدة
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="bg-red-50 border-2 border-red-400 rounded-xl shadow-lg p-6 mb-6 text-center">
                <div class="text-5xl mb-3">❌</div>
                <div class="text-red-800 text-lg">
                    <strong><?= htmlspecialchars($error_message) ?></strong>
                </div>
            </div>
        <?php endif; ?>

        <!-- نموذج الشكوى -->
        <div class="bg-white rounded-lg shadow-xl overflow-hidden">
            <form method="POST" id="complaintForm" class="p-6 md:p-8">
                <?php echo csrf_input('csrf_token'); ?>
                
                <h2 class="text-2xl font-bold text-gray-800 mb-6">المعلومات الشخصية</h2>
                
                <!-- قسم إدخال رمز الدخول للمواطنين العائدين -->
                <div id="access-code-section" class="mb-6">
                    <div class="bg-gradient-to-r from-blue-50 to-purple-50 border-2 border-blue-300 rounded-xl p-6">
                        <div class="text-center mb-4">
                            <span class="text-4xl mb-3 inline-block">🔑</span>
                            <h3 class="text-lg font-bold text-gray-800 mb-2">هل لديك رمز دخول؟</h3>
                            <p class="text-gray-600 text-sm">إذا كنت قدمت طلباً أو شكوى سابقاً، أدخل رمز الدخول الخاص بك</p>
                        </div>

                        <div class="max-w-md mx-auto w-full px-2">
                            <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center w-full">
                                <div class="flex-1 flex items-center border-2 border-blue-300 rounded-lg focus-within:ring-2 focus-within:ring-blue-500 bg-white min-w-0 w-full sm:w-auto" style="direction: ltr; box-sizing: border-box;">
                                    <div class="px-2 sm:px-4 py-2 sm:py-3 text-base sm:text-lg font-bold text-gray-500 flex items-center flex-shrink-0">
                                        <span>TKT-</span>
                                    </div>
                                    <input type="text" id="access-code-input"
                                           class="flex-1 min-w-0 px-2 sm:px-4 py-2 sm:py-3 border-0 focus:ring-0 focus:outline-none text-center font-bold text-base sm:text-lg tracking-wider w-full"
                                           placeholder="12345"
                                           maxlength="5"
                                           pattern="[0-9]{5}"
                                           inputmode="numeric"
                                           style="box-sizing: border-box;">
                                </div>
                                <button type="button" onclick="loadDataByAccessCode()"
                                        class="bg-blue-600 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg hover:bg-blue-700 transition font-bold whitespace-nowrap w-full sm:w-auto text-sm sm:text-base">
                                    🔍 جلب البيانات
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 text-center mt-2">أدخل 5 أرقام فقط</p>
                            <p class="text-xs text-gray-500 text-center mt-1">أو <button type="button" onclick="skipAccessCode()" class="text-blue-600 hover:text-blue-800 font-bold underline">تخطى</button> إذا كانت هذه أول مرة</p>
                        </div>
                        
                        <div id="access-code-loading" class="hidden text-center mt-4">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                            <p class="text-blue-700 text-sm mt-2">جاري جلب بياناتك...</p>
                        </div>
                        
                        <div id="access-code-error" class="hidden bg-red-50 border-2 border-red-300 rounded-lg p-4 mt-4">
                            <p class="text-red-800 text-sm text-center"></p>
                        </div>
                        
                        <div id="access-code-success" class="hidden bg-green-50 border-2 border-green-400 rounded-lg p-4 mt-4">
                            <div class="flex items-center justify-center">
                                <span class="text-3xl ml-3">👋</span>
                                <div>
                                    <p class="font-bold text-green-900">مرحباً بعودتك <span id="loaded-citizen-name"></span>!</p>
                                    <p class="text-green-700 text-sm">تم تعبئة بياناتك تلقائياً</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- نموذج البيانات الشخصية (مخفي في البداية) -->
                <div id="personal-info-form" class="hidden">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">الاسم الكامل *</label>
                        <input type="text" id="citizen_name" name="citizen_name" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="أدخل اسمك الكامل">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">رقم الهاتف *</label>
                        <input type="tel" id="citizen_phone" name="citizen_phone" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="مثال: 03123456">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">البريد الإلكتروني</label>
                        <input type="email" id="citizen_email" name="citizen_email" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="example@email.com">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">الفئة *</label>
                        <select name="category" required 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="نفايات">نفايات</option>
                            <option value="طرق">طرق</option>
                            <option value="مياه">مياه</option>
                            <option value="إنارة">إنارة</option>
                            <option value="صيانة">صيانة</option>
                            <option value="أخرى" selected>أخرى</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">العنوان</label>
                    <textarea id="citizen_address" name="citizen_address" rows="2" 
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="أدخل عنوانك بالتفصيل"></textarea>
                </div>
                
                </div> <!-- نهاية personal-info-form -->
                
                <h2 class="text-2xl font-bold text-gray-800 mb-6 mt-8">تفاصيل الشكوى</h2>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">موضوع الشكوى *</label>
                    <input type="text" name="subject" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="أدخل موضوع الشكوى">
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">وصف الشكوى *</label>
                    <textarea name="description" rows="6" required 
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="اشرح شكواك بالتفصيل..."></textarea>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">الأولوية</label>
                    <select name="priority" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="منخفضة">منخفضة</option>
                        <option value="متوسطة" selected>متوسطة</option>
                        <option value="عالية">عالية</option>
                    </select>
                </div>
                
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="mr-3">
                            <h3 class="text-sm font-medium text-blue-800">معلومات مهمة</h3>
                            <div class="mt-2 text-sm text-blue-700">
                                <ul class="list-disc list-inside space-y-1">
                                    <li>سيتم إرسال رقم الشكوى إليك بعد التقديم</li>
                                    <li>يمكنك متابعة حالة شكواك من خلال حسابك الشخصي</li>
                                    <li>ستصلك تحديثات عبر Telegram إذا كان حسابك مربوطاً</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3">
                    <a href="index.php" class="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition font-semibold">
                        إلغاء
                    </a>
                    <button type="submit" name="submit_complaint" 
                            class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition font-semibold">
                        🚀 تقديم الشكوى
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let loadedAccessCode = null;
        
        function copyCode(code) {
            navigator.clipboard.writeText(code).then(function() {
                alert('تم نسخ الرمز: ' + code);
            });
        }
        
        // إضافة event listener لإدخال Enter في حقل رمز الدخول
        document.addEventListener('DOMContentLoaded', function() {
            const accessCodeInput = document.getElementById('access-code-input');
            if (accessCodeInput) {
                accessCodeInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        loadDataByAccessCode();
                    }
                });
            }
        });
        
        function loadDataByAccessCode() {
            let codeInput = document.getElementById('access-code-input').value.trim();
            codeInput = codeInput.replace(/\D/g, ''); // إزالة أي شيء غير أرقام
            document.getElementById('access-code-input').value = codeInput;
            
            if (!codeInput || codeInput.length !== 5) {
                showAccessCodeError('الرجاء إدخال 5 أرقام');
                return;
            }
            
            // إضافة TKT- تلقائياً
            const fullAccessCode = 'TKT-' + codeInput;
            
            // إخفاء الرسائل السابقة
            document.getElementById('access-code-error').classList.add('hidden');
            document.getElementById('access-code-success').classList.add('hidden');
            
            // إظهار Loading
            document.getElementById('access-code-loading').classList.remove('hidden');
            
            // إرسال طلب للحصول على البيانات
            fetch('get_citizen_by_code.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'access_code=' + encodeURIComponent(fullAccessCode)
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('access-code-loading').classList.add('hidden');
                
                if (data.success) {
                    // تعبئة البيانات
                    document.getElementById('citizen_name').value = data.name || '';
                    document.getElementById('citizen_phone').value = data.phone || '';
                    document.getElementById('citizen_email').value = data.email || '';
                    document.getElementById('citizen_address').value = data.address || '';
                    
                    // إظهار رسالة النجاح
                    document.getElementById('loaded-citizen-name').textContent = data.name;
                    document.getElementById('access-code-success').classList.remove('hidden');
                    
                    // إخفاء قسم رمز الدخول وإظهار النموذج
                    setTimeout(() => {
                        loadedAccessCode = fullAccessCode;
                        document.getElementById('access-code-section').style.display = 'none';
                        document.getElementById('personal-info-form').classList.remove('hidden');
                    }, 1500);
                } else {
                    showAccessCodeError(data.message || 'رمز الدخول غير صحيح');
                }
            })
            .catch(error => {
                document.getElementById('access-code-loading').classList.add('hidden');
                showAccessCodeError('حدث خطأ في الاتصال، الرجاء المحاولة مرة أخرى');
                console.error('Error:', error);
            });
        }
        
        function skipAccessCode() {
            // إعادة تعيين رمز الدخول (مواطن جديد)
            loadedAccessCode = null;
            
            // إخفاء قسم رمز الدخول وإظهار النموذج
            document.getElementById('access-code-section').style.display = 'none';
            document.getElementById('personal-info-form').classList.remove('hidden');
        }
        
        function showAccessCodeError(message) {
            const errorDiv = document.getElementById('access-code-error');
            errorDiv.querySelector('p').textContent = message;
            errorDiv.classList.remove('hidden');
        }
    </script>
</body>
</html>

