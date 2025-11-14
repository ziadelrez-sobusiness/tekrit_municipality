<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

$success_message = '';
$error_message = '';

// معالجة تقديم الطلب
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_request'])) {
    $citizen_name = trim($_POST['citizen_name']);
    $citizen_phone = trim($_POST['citizen_phone']);
    $citizen_email = trim($_POST['citizen_email']);
    $citizen_address = trim($_POST['citizen_address']);
    $national_id = trim($_POST['national_id']);
    $request_type_id = $_POST['request_type_id'];
    $request_title = trim($_POST['request_title']);
    $request_description = trim($_POST['request_description']);
    $priority_level = $_POST['priority_level'] ?? 'عادي';
    
    if (!empty($citizen_name) && !empty($citizen_phone) && !empty($request_type_id) && !empty($request_title)) {
        try {
            $db->beginTransaction();
            
            // إنشاء رقم تتبع فريد
            $tracking_number = 'REQ-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            
            // التحقق من عدم تكرار رقم التتبع
            $check_stmt = $db->prepare("SELECT COUNT(*) FROM citizen_requests WHERE tracking_number = ?");
            $check_stmt->execute([$tracking_number]);
            while ($check_stmt->fetchColumn() > 0) {
                $tracking_number = 'REQ-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
                $check_stmt->execute([$tracking_number]);
            }
            
            // إدراج الطلب الأساسي
            $stmt = $db->prepare("
                INSERT INTO citizen_requests 
                (citizen_name, citizen_phone, citizen_email, citizen_address, national_id, 
                 request_type_id, request_title, request_description, priority_level, 
                 tracking_number, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'جديد', NOW(), NOW())
            ");
            
            $stmt->execute([
                $citizen_name, $citizen_phone, $citizen_email, $citizen_address, $national_id,
                $request_type_id, $request_title, $request_description, $priority_level, $tracking_number
            ]);
            
            $request_id = $db->lastInsertId();
            
            // حفظ البيانات الإضافية للنموذج
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'field_') === 0 && !empty($value)) {
                    $field_name = substr($key, 6); // إزالة 'field_' من البداية
                    $field_type = $_POST['fieldtype_' . $field_name] ?? 'text';
                    
                    $form_stmt = $db->prepare("
                        INSERT INTO request_form_data 
                        (request_id, field_name, field_value, field_type, created_at) 
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $form_stmt->execute([$request_id, $field_name, $value, $field_type]);
                }
            }
            
            // معالجة رفع الملفات
            if (!empty($_FILES['documents']['name'][0])) {
                $upload_dir = '../uploads/requests/' . $request_id . '/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                for ($i = 0; $i < count($_FILES['documents']['name']); $i++) {
                    if ($_FILES['documents']['error'][$i] == 0) {
                        $file_name = $_FILES['documents']['name'][$i];
                        $file_tmp = $_FILES['documents']['tmp_name'][$i];
                        $file_size = $_FILES['documents']['size'][$i];
                        $file_type = $_FILES['documents']['type'][$i];
                        
                        // التحقق من نوع الملف
                        $allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        
                        if (in_array($file_ext, $allowed_types) && $file_size <= 5000000) { // 5MB max
                            $new_filename = time() . '_' . $i . '.' . $file_ext;
                            $file_path = $upload_dir . $new_filename;
                            
                            if (move_uploaded_file($file_tmp, $file_path)) {
                                $doc_stmt = $db->prepare("
                                    INSERT INTO request_documents 
                                    (request_id, document_name, original_filename, file_path, file_size, file_type, uploaded_at) 
                                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                                ");
                                $doc_stmt->execute([
                                    $request_id, 
                                    'مستند مرفق', 
                                    $file_name, 
                                    $file_path, 
                                    $file_size, 
                                    $file_type
                                ]);
                            }
                        }
                    }
                }
            }
            
            // إضافة تحديث أولي
            $update_stmt = $db->prepare("
                INSERT INTO request_updates 
                (request_id, update_text, update_type, updated_by, is_visible_to_citizen, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $update_stmt->execute([
                $request_id, 
                'تم استلام الطلب وهو قيد المراجعة', 
                'تحديث حالة', 
                'النظام', 
                1
            ]);
            
            $db->commit();
            $success_message = "تم تقديم طلبكم بنجاح! رقم التتبع: " . $tracking_number;
            
            // ========================================
            // إرسال رسالة Telegram للمواطن
            // ========================================
            try {
                // تحميل المكتبات المطلوبة
                require_once '../includes/CitizenAccountHelper.php';
                require_once '../includes/TelegramService.php';
                
                // إنشاء/جلب حساب المواطن
                $accountHelper = new CitizenAccountHelper($db);
                $accountResult = $accountHelper->getOrCreateAccount(
                    $citizen_phone,
                    $citizen_name,
                    $citizen_email,
                    $national_id,
                    null, // telegram_chat_id (سيتم ربطه لاحقاً)
                    null  // telegram_username
                );
                
                $accessCode = $accountResult['access_code'] ?? null;
                $citizenId = $accountResult['citizen_id'] ?? null;
                
                // جلب telegram_chat_id من حساب المواطن إذا كان موجوداً
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
                
                // الحصول على اسم نوع الطلب
                $typeStmt = $db->prepare("SELECT type_name FROM request_types WHERE id = ?");
                $typeStmt->execute([$request_type_id]);
                $typeData = $typeStmt->fetch(PDO::FETCH_ASSOC);
                $requestTypeName = $typeData['type_name'] ?? 'طلب';
                
                // إرسال رسالة Telegram
                $telegramService = new TelegramService($db);
                
                // تسجيل البيانات للتصحيح
                error_log("=== TELEGRAM DEBUG ===");
                error_log("Citizen ID: " . $citizenId);
                error_log("Telegram Chat ID: " . ($telegramChatId ?? 'NULL'));
                error_log("Access Code: " . $accessCode);
                error_log("Request ID: " . $request_id);
                error_log("Tracking Number: " . $tracking_number);
                
                $telegramResult = $telegramService->sendWelcomeMessage(
                    [
                        'name' => $citizen_name,
                        'phone' => $citizen_phone,
                        'citizen_id' => $citizenId,
                        'telegram_chat_id' => $telegramChatId,
                        'telegram_username' => $telegramUsername
                    ],
                    [
                        'request_id' => $request_id,
                        'type_name' => $requestTypeName,
                        'tracking_number' => $tracking_number,
                        'request_title' => $request_title
                    ],
                    $accessCode
                );
                
                error_log("Telegram Result: " . json_encode($telegramResult));
                
                // تحديث رسالة النجاح
                if ($accessCode) {
                    $success_message .= "<div class='mt-4 pt-4 border-t-2 border-green-300'>";
                    $success_message .= "<p class='font-bold text-green-900 mb-3 text-xl'>🔐 رمز الدخول الثابت</p>";
                    $success_message .= "<p class='text-green-700 text-sm mb-2'>يمكنك الدخول لحسابك الشخصي في أي وقت باستخدام:</p>";
                    $success_message .= "<div class='bg-white rounded-lg p-4 border-2 border-green-400 text-center mb-3'>";
                    $success_message .= "<p class='text-3xl font-bold text-green-800 tracking-wider mb-2'>" . htmlspecialchars($accessCode) . "</p>";
                    $success_message .= "<button onclick=\"copyCode('" . htmlspecialchars($accessCode) . "')\" class='bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm font-bold'>📋 نسخ الرمز</button>";
                    $success_message .= "</div>";
                    $success_message .= "<p class='text-green-600 text-xs mb-3'>💡 احتفظ بهذا الرمز للدخول لحسابك ومتابعة طلباتك</p>";
                    
                    // رابط مباشر للحساب الشخصي
                    $dashboardUrl = 'citizen-dashboard.php?code=' . urlencode($accessCode);
                    $success_message .= "<a href='" . $dashboardUrl . "' class='inline-block bg-purple-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-purple-700 transition mb-3'>👤 دخول للحساب الشخصي</a>";
                    
                    // إضافة معلومات Telegram Bot - تحسين التصميم
                    $success_message .= "<div class='mt-6 pt-6 border-t-4 border-blue-400'>";
                    
                    // عنوان رئيسي جذاب
                    $success_message .= "<div class='bg-gradient-to-r from-blue-600 to-blue-800 rounded-xl p-6 mb-4 text-center shadow-lg'>";
                    $success_message .= "<p class='text-white text-2xl font-bold mb-2'>📱 ربط حسابك مع Telegram</p>";
                    $success_message .= "<p class='text-blue-100 text-sm'>احصل على إشعارات فورية بجميع تحديثات طلبك!</p>";
                    $success_message .= "</div>";
                    
                    // الخطوات بتصميم محسّن
                    $success_message .= "<div class='bg-white rounded-xl shadow-md p-6 mb-4 border-2 border-blue-200'>";
                    $success_message .= "<p class='text-gray-800 font-bold mb-4 text-lg flex items-center'>";
                    $success_message .= "<span class='bg-yellow-400 text-yellow-900 rounded-full w-8 h-8 flex items-center justify-center ml-2 text-sm'>!</span>";
                    $success_message .= "اتبع الخطوات التالية:";
                    $success_message .= "</p>";
                    
                    $success_message .= "<div class='space-y-4'>";
                    
                    // الخطوة 1
                    $success_message .= "<div class='flex items-start bg-blue-50 rounded-lg p-4 border-r-4 border-blue-500'>";
                    $success_message .= "<div class='bg-blue-600 text-white rounded-full w-10 h-10 flex items-center justify-center font-bold text-lg ml-3 flex-shrink-0'>1</div>";
                    $success_message .= "<div>";
                    $success_message .= "<p class='font-bold text-blue-900 mb-1'>افتح تطبيق Telegram</p>";
                    $success_message .= "<p class='text-blue-700 text-sm'>على هاتفك المحمول أو الكمبيوتر</p>";
                    $success_message .= "</div>";
                    $success_message .= "</div>";
                    
                    // الخطوة 2
                    $success_message .= "<div class='flex items-start bg-green-50 rounded-lg p-4 border-r-4 border-green-500'>";
                    $success_message .= "<div class='bg-green-600 text-white rounded-full w-10 h-10 flex items-center justify-center font-bold text-lg ml-3 flex-shrink-0'>2</div>";
                    $success_message .= "<div class='flex-1'>";
                    $success_message .= "<p class='font-bold text-green-900 mb-2'>ابحث عن بوت البلدية</p>";
                    $success_message .= "<div class='bg-white rounded-lg p-3 border-2 border-green-400'>";
                    $success_message .= "<p class='text-green-700 text-xs mb-1'>اكتب في خانة البحث:</p>";
                    $success_message .= "<p class='text-2xl font-bold text-green-900 text-center tracking-wider' dir='ltr'>@TekritAkkarBot</p>";
                    $success_message .= "<button onclick=\"copyText('@TekritAkkarBot')\" class='mt-2 w-full bg-green-600 text-white px-3 py-2 rounded-lg hover:bg-green-700 transition text-xs font-bold'>📋 نسخ اسم البوت</button>";
                    $success_message .= "</div>";
                    $success_message .= "</div>";
                    $success_message .= "</div>";
                    
                    // الخطوة 3
                    $success_message .= "<div class='flex items-start bg-purple-50 rounded-lg p-4 border-r-4 border-purple-500'>";
                    $success_message .= "<div class='bg-purple-600 text-white rounded-full w-10 h-10 flex items-center justify-center font-bold text-lg ml-3 flex-shrink-0'>3</div>";
                    $success_message .= "<div>";
                    $success_message .= "<p class='font-bold text-purple-900 mb-1'>ابدأ المحادثة</p>";
                    $success_message .= "<p class='text-purple-700 text-sm'>اضغط على زر <span class='bg-purple-200 px-2 py-1 rounded font-bold'>Start</span> أو <span class='bg-purple-200 px-2 py-1 rounded font-bold'>ابدأ</span></p>";
                    $success_message .= "</div>";
                    $success_message .= "</div>";
                    
                    // الخطوة 4
                    $success_message .= "<div class='flex items-start bg-orange-50 rounded-lg p-4 border-r-4 border-orange-500'>";
                    $success_message .= "<div class='bg-orange-600 text-white rounded-full w-10 h-10 flex items-center justify-center font-bold text-lg ml-3 flex-shrink-0'>4</div>";
                    $success_message .= "<div class='flex-1'>";
                    $success_message .= "<p class='font-bold text-orange-900 mb-2'>أرسل رمز الدخول</p>";
                    $success_message .= "<div class='bg-white rounded-lg p-4 border-2 border-orange-400'>";
                    $success_message .= "<p class='text-orange-700 text-xs mb-2'>انسخ والصق هذا الرمز في المحادثة:</p>";
                    $success_message .= "<div class='flex items-center justify-center gap-2'>";
                    $success_message .= "<p class='text-3xl font-bold text-orange-900 tracking-wider'>" . htmlspecialchars($accessCode) . "</p>";
                    $success_message .= "<button onclick=\"copyCode('" . htmlspecialchars($accessCode) . "')\" class='bg-orange-600 text-white px-3 py-2 rounded-lg hover:bg-orange-700 transition text-xs font-bold'>📋 نسخ</button>";
                    $success_message .= "</div>";
                    $success_message .= "</div>";
                    $success_message .= "</div>";
                    $success_message .= "</div>";
                    
                    $success_message .= "</div>"; // نهاية space-y-4
                    $success_message .= "</div>"; // نهاية bg-white
                    
                    // زر فتح البوت
                    $success_message .= "<div class='text-center mb-4'>";
                    $success_message .= "<a href='https://t.me/TekritAkkarBot' target='_blank' class='inline-block bg-gradient-to-r from-blue-600 to-blue-800 text-white px-8 py-4 rounded-xl font-bold hover:from-blue-700 hover:to-blue-900 transition shadow-xl text-lg transform hover:scale-105'>";
                    $success_message .= "✈️ فتح البوت الآن";
                    $success_message .= "</a>";
                    $success_message .= "</div>";
                    
                    // ملاحظة نهائية
                    $success_message .= "<div class='bg-green-50 border-2 border-green-300 rounded-lg p-4 text-center'>";
                    $success_message .= "<p class='text-green-800 text-sm'><strong>✅ بعد إرسال الرمز:</strong></p>";
                    $success_message .= "<p class='text-green-700 text-sm mt-1'>ستصلك رسالة تأكيد وجميع التحديثات المستقبلية تلقائياً!</p>";
                    $success_message .= "</div>";
                    
                    $success_message .= "</div>"; // نهاية القسم الرئيسي
                    $success_message .= "</div>";
                }
                
            } catch (Exception $e) {
                // تسجيل الخطأ لكن لا نعرض للمستخدم (حتى لا نربك المواطن)
                error_log("Telegram Integration Error: " . $e->getMessage());
                // يمكن إضافة ملاحظة بسيطة للمواطن
                $success_message .= "<div class='mt-4 pt-4 border-t-2 border-yellow-300'>";
                $success_message .= "<p class='text-yellow-700 text-sm'>📝 ملاحظة: سيتم التواصل معك قريباً</p>";
                $success_message .= "</div>";
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = "حدث خطأ أثناء تقديم الطلب: " . $e->getMessage();
        }
    } else {
        $error_message = "يرجى ملء جميع الحقول المطلوبة";
    }
}

// جلب أنواع الطلبات مع تفاصيل العملة
$request_types = [];
try {
    $stmt = $db->query("
        SELECT rt.*, c.currency_symbol, c.currency_code 
        FROM request_types rt 
        LEFT JOIN currencies c ON rt.cost_currency_id = c.id 
        WHERE rt.is_active = 1 
        ORDER BY rt.display_order, rt.type_name
    ");
    $request_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تحويل البيانات للتأكد من صحة JSON
    foreach ($request_types as &$type) {
        if (empty($type['cost'])) {
            $type['cost'] = 0;
        }
        if (empty($type['currency_symbol'])) {
            $type['currency_symbol'] = 'د.ع';
        }
        
        // معالجة required_documents
        if (!empty($type['required_documents'])) {
            if (is_string($type['required_documents'])) {
                $decoded = json_decode($type['required_documents'], true);
                if ($decoded && is_array($decoded)) {
                    $type['required_documents_array'] = $decoded;
                } else {
                    // إذا كان النص عادي، نقسمه على الأسطر
                    $type['required_documents_array'] = array_filter(explode("\n", $type['required_documents']));
                }
            }
        } else {
            $type['required_documents_array'] = [];
        }
        
        // معالجة form_fields
        if (!empty($type['form_fields'])) {
            $decoded = json_decode($type['form_fields'], true);
            $type['form_fields_array'] = $decoded ?: [];
        } else {
            $type['form_fields_array'] = [];
        }
    }
} catch (Exception $e) {
    $error_message = "خطأ في جلب أنواع الطلبات: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقديم طلب - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .step { display: none; }
        .step.active { display: block; }
        .step-indicator { 
            background: #e5e7eb; 
            color: #6b7280; 
            transition: all 0.3s ease;
        }
        .step-indicator.active { 
            background: #3b82f6; 
            color: white; 
        }
        .step-indicator.completed { 
            background: #10b981; 
            color: white; 
        }
        .form-field {
            margin-bottom: 1rem;
            opacity: 0;
            transform: translateY(20px);
            animation: slideIn 0.5s ease forwards;
        }
        @keyframes slideIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .required-docs {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-left: 4px solid #f59e0b;
        }
        .dynamic-field {
            animation: fadeIn 0.3s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-green-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-800 mb-2">تقديم طلب جديد</h1>
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
                    <a href="track-request.php" class="bg-green-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-green-700 transition inline-flex items-center gap-2">
                        🔍 تتبع طلبك
                    </a>
                    <a href="citizen-requests.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition inline-flex items-center gap-2">
                        ➕ طلب جديد
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

        <!-- مؤشر الخطوات -->
        <div class="flex justify-center mb-8">
            <div class="flex items-center space-x-4 space-x-reverse">
                <div class="step-indicator active flex items-center justify-center w-10 h-10 rounded-full font-bold" id="step-indicator-1">1</div>
                <div class="w-16 h-1 bg-gray-300" id="line-1"></div>
                <div class="step-indicator flex items-center justify-center w-10 h-10 rounded-full font-bold" id="step-indicator-2">2</div>
                <div class="w-16 h-1 bg-gray-300" id="line-2"></div>
                <div class="step-indicator flex items-center justify-center w-10 h-10 rounded-full font-bold" id="step-indicator-3">3</div>
                <div class="w-16 h-1 bg-gray-300" id="line-3"></div>
                <div class="step-indicator flex items-center justify-center w-10 h-10 rounded-full font-bold" id="step-indicator-4">4</div>
            </div>
        </div>

        <!-- عناوين الخطوات -->
        <div class="flex justify-center mb-8">
            <div class="grid grid-cols-4 gap-4 text-center text-sm">
                <div class="text-blue-600 font-semibold" id="step-title-1">المعلومات الشخصية</div>
                <div class="text-gray-500" id="step-title-2">نوع الطلب</div>
                <div class="text-gray-500" id="step-title-3">تفاصيل الطلب</div>
                <div class="text-gray-500" id="step-title-4">المراجعة والإرسال</div>
            </div>
        </div>

        <!-- نموذج الطلب -->
        <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-xl overflow-hidden">
            <form method="POST" enctype="multipart/form-data" id="requestForm">
                
                <!-- الخطوة 1: المعلومات الشخصية -->
                <div class="step active p-8" id="step-1">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">المعلومات الشخصية</h2>
                    
                    <!-- قسم إدخال رمز الدخول للمواطنين العائدين -->
                    <div id="access-code-section" class="mb-6">
                        <div class="bg-gradient-to-r from-blue-50 to-purple-50 border-2 border-blue-300 rounded-xl p-6">
                            <div class="text-center mb-4">
                                <span class="text-5xl mb-3 inline-block">🔑</span>
                                <h3 class="text-xl font-bold text-gray-800 mb-2">هل لديك رمز دخول؟</h3>
                                <p class="text-gray-600 text-sm">إذا كنت قدمت طلباً سابقاً، أدخل رمز الدخول الخاص بك</p>
                            </div>
                            
                            <div class="max-w-md mx-auto">
                                <div class="flex gap-3 items-center">
                                    <div class="flex-1 flex items-center border-2 border-blue-300 rounded-lg focus-within:ring-2 focus-within:ring-blue-500 bg-white" style="direction: ltr;">
                                        <div class="px-4 py-3 text-lg font-bold text-gray-500 flex items-center">
                                            <span>TKT-</span>
                                        </div>
                                        <input type="text" id="access-code-input" 
                                               class="flex-1 px-4 py-3 border-0 focus:ring-0 focus:outline-none text-center font-bold text-lg tracking-wider"
                                               placeholder="12345"
                                               maxlength="5"
                                               pattern="[0-9]{5}"
                                               inputmode="numeric">
                                    </div>
                                    <button type="button" onclick="loadDataByAccessCode()" 
                                            class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition font-bold whitespace-nowrap">
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
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-field">
                            <label class="block text-sm font-medium text-gray-700 mb-2">الاسم الكامل *</label>
                            <input type="text" id="citizen_name" name="citizen_name" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-300"
                                   placeholder="أدخل اسمك الكامل">
                        </div>
                        
                        <div class="form-field">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                رقم الهاتف *
                                <span id="phone-verification-badge" class="hidden"></span>
                            </label>
                            <div class="relative">
                                <input type="tel" id="citizen_phone" name="citizen_phone" required 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-300"
                                       placeholder="مثال: 03123456">
                                <div id="phone-check-icon" class="absolute left-3 top-1/2 transform -translate-y-1/2 hidden">
                                    <!-- سيتم إضافة أيقونة هنا -->
                                </div>
                            </div>
                            <p id="phone-verification-message" class="text-xs mt-1 hidden"></p>
                        </div>
                        
                        <div class="form-field">
                            <label class="block text-sm font-medium text-gray-700 mb-2">البريد الإلكتروني</label>
                            <input type="email" id="citizen_email" name="citizen_email" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-300"
                                   placeholder="example@email.com">
                        </div>
                        
                        <div class="form-field">
                            <label class="block text-sm font-medium text-gray-700 mb-2">رقم الهوية</label>
                            <input type="text" id="national_id" name="national_id" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-300"
                                   placeholder="رقم الهوية الوطنية">
                        </div>
                        
                        <div class="form-field md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">العنوان</label>
                            <textarea id="citizen_address" name="citizen_address" rows="3" 
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-300"
                                      placeholder="أدخل عنوانك بالتفصيل"></textarea>
                        </div>
                    </div>
                    
                    <div class="flex justify-end mt-8">
                        <button type="button" onclick="nextStep()" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-300 font-semibold">
                            التالي ←
                        </button>
                    </div>
                    
                    </div> <!-- نهاية personal-info-form -->
                </div>

                <!-- الخطوة 2: نوع الطلب -->
                <div class="step p-8" id="step-2">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">اختيار نوع الطلب</h2>
                    
                    <div class="form-field">
                        <label class="block text-sm font-medium text-gray-700 mb-4">نوع الطلب *</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($request_types as $type): ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-500 hover:bg-blue-50 transition duration-300 cursor-pointer request-type-option" 
                                     onclick="selectRequestType(<?= $type['id'] ?>, '<?= htmlspecialchars($type['type_name']) ?>')">
                                    <input type="radio" name="request_type_id" value="<?= $type['id'] ?>" class="hidden" id="type-<?= $type['id'] ?>">
                                    <div class="flex items-center">
                                        <div class="w-4 h-4 border-2 border-gray-300 rounded-full mr-3 radio-indicator"></div>
                                        <div>
                                            <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($type['type_name']) ?></h3>
                                            <?php if ($type['type_description']): ?>
                                                <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($type['type_description']) ?></p>
                                            <?php endif; ?>
                                            <?php if ($type['cost'] > 0): ?>
                                                <p class="text-sm text-green-600 font-semibold mt-1">
                                                    الرسوم: <?= number_format($type['cost'], 2) ?> <?= htmlspecialchars($type['currency_symbol']) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- المستندات المطلوبة -->
                    <div id="required-documents" class="required-docs p-4 rounded-lg mt-6" style="display: none;">
                        <h3 class="font-bold text-amber-800 mb-3">📋 المستندات المطلوبة:</h3>
                        <div id="documents-list" class="text-sm text-amber-700"></div>
                    </div>

                    <div class="flex justify-between mt-8">
                        <button type="button" onclick="prevStep()" class="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition duration-300 font-semibold">
                            ← السابق
                        </button>
                        <button type="button" onclick="nextStep()" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-300 font-semibold" id="step2-next" disabled>
                            التالي ←
                        </button>
                    </div>
                </div>

                <!-- الخطوة 3: تفاصيل الطلب -->
                <div class="step p-8" id="step-3">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">تفاصيل الطلب</h2>
                    
                    <div class="grid grid-cols-1 gap-6">
                        <div class="form-field">
                            <label class="block text-sm font-medium text-gray-700 mb-2">عنوان الطلب *</label>
                            <input type="text" name="request_title" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-300"
                                   placeholder="أدخل عنواناً مختصراً للطلب">
                        </div>
                        
                        <div class="form-field">
                            <label class="block text-sm font-medium text-gray-700 mb-2">وصف الطلب *</label>
                            <textarea name="request_description" rows="4" required 
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-300"
                                      placeholder="اشرح طلبك بالتفصيل..."></textarea>
                        </div>
                        
                        <div class="form-field">
                            <label class="block text-sm font-medium text-gray-700 mb-2">أولوية الطلب</label>
                            <select name="priority_level" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-300">
                                <option value="عادي">عادي</option>
                                <option value="مهم">مهم</option>
                                <option value="عاجل">عاجل</option>
                            </select>
                        </div>

                        <!-- الحقول الديناميكية -->
                        <div id="dynamic-fields" class="space-y-4"></div>

                        <!-- رفع المستندات -->
                        <div class="form-field">
                            <label class="block text-sm font-medium text-gray-700 mb-2">المستندات المرفقة</label>
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-500 transition duration-300">
                                <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" 
                                       class="hidden" id="file-input" onchange="handleFileSelect(this)">
                                <label for="file-input" class="cursor-pointer">
                                    <div class="text-gray-600">
                                        <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <p class="text-lg font-medium">اضغط لاختيار الملفات</p>
                                        <p class="text-sm text-gray-500">أو اسحب الملفات هنا</p>
                                        <p class="text-xs text-gray-400 mt-2">PDF, JPG, PNG, DOC, DOCX (حد أقصى 5MB لكل ملف)</p>
                                    </div>
                                </label>
                            </div>
                            <div id="file-list" class="mt-4"></div>
                        </div>
                    </div>

                    <div class="flex justify-between mt-8">
                        <button type="button" onclick="prevStep()" class="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition duration-300 font-semibold">
                            ← السابق
                        </button>
                        <button type="button" onclick="nextStep()" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-300 font-semibold">
                            التالي ←
                        </button>
                    </div>
                </div>

                <!-- الخطوة 4: المراجعة والإرسال -->
                <div class="step p-8" id="step-4">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">مراجعة الطلب</h2>
                    
                    <div class="bg-gray-50 rounded-lg p-6 mb-6">
                        <h3 class="font-bold text-gray-800 mb-4">ملخص الطلب:</h3>
                        <div id="request-summary" class="space-y-2 text-sm"></div>
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
                                        <li>سيتم إرسال رقم تتبع الطلب إليك بعد التقديم</li>
                                        <li>يمكنك متابعة حالة طلبك من خلال صفحة التتبع</li>
                                        <li>ستصلك تحديثات عبر الهاتف أو البريد الإلكتروني</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-between">
                        <button type="button" onclick="prevStep()" class="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition duration-300 font-semibold">
                            ← السابق
                        </button>
                        <button type="submit" name="submit_request" class="bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 transition duration-300 font-semibold">
                            🚀 تقديم الطلب
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentStep = 1;
        const totalSteps = 4;
        let selectedRequestType = null;
        let loadedAccessCode = null; // رمز الدخول المحمّل (إذا جلب المواطن بياناته)
        let originalPhone = null; // رقم الهاتف الأصلي (قبل التعديل)

        // بيانات أنواع الطلبات مع الحقول المطلوبة
        const requestTypesData = {
            <?php foreach ($request_types as $type): ?>
            <?= $type['id'] ?>: {
                name: '<?= htmlspecialchars($type['type_name']) ?>',
                description: '<?= htmlspecialchars($type['type_description'] ?? '') ?>',
                required_documents: <?= json_encode($type['required_documents_array']) ?>,
                form_fields: <?= json_encode($type['form_fields_array']) ?>,
                cost: <?= $type['cost'] ?? 0 ?>,
                currency_symbol: '<?= htmlspecialchars($type['currency_symbol']) ?>'
            },
            <?php endforeach; ?>
        };

        async function nextStep() {
            if (validateCurrentStep()) {
                // If moving from step 1 and we have citizen data loaded, update it
                if (currentStep === 1 && loadedAccessCode) {
                    const updateSuccess = await updateCitizenData();
                    if (!updateSuccess) {
                        alert('تحذير: حدث خطأ أثناء حفظ التعديلات. سيتم المتابعة على أي حال.');
                    }
                }
                
                if (currentStep < totalSteps) {
                    document.getElementById('step-' + currentStep).classList.remove('active');
                    document.getElementById('step-indicator-' + currentStep).classList.remove('active');
                    document.getElementById('step-indicator-' + currentStep).classList.add('completed');
                    document.getElementById('step-title-' + currentStep).classList.remove('text-blue-600');
                    document.getElementById('step-title-' + currentStep).classList.add('text-green-600');
                    
                    currentStep++;
                    
                    document.getElementById('step-' + currentStep).classList.add('active');
                    document.getElementById('step-indicator-' + currentStep).classList.add('active');
                    document.getElementById('step-title-' + currentStep).classList.remove('text-gray-500');
                    document.getElementById('step-title-' + currentStep).classList.add('text-blue-600');
                    
                    updateProgressLines();
                    
                    if (currentStep === 4) {
                        generateSummary();
                    }
                }
            }
        }

        function prevStep() {
            if (currentStep > 1) {
                document.getElementById('step-' + currentStep).classList.remove('active');
                document.getElementById('step-indicator-' + currentStep).classList.remove('active');
                document.getElementById('step-title-' + currentStep).classList.remove('text-blue-600');
                document.getElementById('step-title-' + currentStep).classList.add('text-gray-500');
                
                currentStep--;
                
                document.getElementById('step-' + currentStep).classList.add('active');
                document.getElementById('step-indicator-' + currentStep).classList.remove('completed');
                document.getElementById('step-indicator-' + currentStep).classList.add('active');
                document.getElementById('step-title-' + currentStep).classList.remove('text-green-600');
                document.getElementById('step-title-' + currentStep).classList.add('text-blue-600');
                
                updateProgressLines();
            }
        }

        function updateProgressLines() {
            for (let i = 1; i < totalSteps; i++) {
                const line = document.getElementById('line-' + i);
                if (i < currentStep) {
                    line.classList.remove('bg-gray-300');
                    line.classList.add('bg-green-500');
                } else {
                    line.classList.remove('bg-green-500');
                    line.classList.add('bg-gray-300');
                }
            }
        }

        function validateCurrentStep() {
            const step = document.getElementById('step-' + currentStep);
            const requiredFields = step.querySelectorAll('input[required], textarea[required], select[required]');
            
            for (let field of requiredFields) {
                if (!field.value.trim()) {
                    field.focus();
                    field.classList.add('border-red-500');
                    setTimeout(() => field.classList.remove('border-red-500'), 3000);
                    return false;
                }
            }
            
            if (currentStep === 2 && !selectedRequestType) {
                alert('يرجى اختيار نوع الطلب');
                return false;
            }
            
            return true;
        }

        function selectRequestType(typeId, typeName) {
            console.log('selectRequestType called with:', { typeId, typeName });
            console.log('Available request types data:', requestTypesData);
            
            try {
                // إزالة التحديد السابق
                document.querySelectorAll('.request-type-option').forEach(option => {
                    option.classList.remove('border-blue-500', 'bg-blue-50');
                    option.querySelector('.radio-indicator').classList.remove('bg-blue-500', 'border-blue-500');
                });
                
                // تحديد النوع الجديد
                const selectedOption = document.querySelector(`[onclick="selectRequestType(${typeId}, '${typeName}')"]`);
                if (selectedOption) {
                    selectedOption.classList.add('border-blue-500', 'bg-blue-50');
                    const radioIndicator = selectedOption.querySelector('.radio-indicator');
                    if (radioIndicator) {
                        radioIndicator.classList.add('bg-blue-500', 'border-blue-500');
                    }
                } else {
                    console.error('Could not find selected option element');
                }
                
                // تحديد radio button
                const radioButton = document.getElementById('type-' + typeId);
                if (radioButton) {
                    radioButton.checked = true;
                } else {
                    console.error('Could not find radio button for type:', typeId);
                }
                
                selectedRequestType = typeId;
                console.log('Selected request type set to:', selectedRequestType);
                
                // التحقق من وجود بيانات النوع
                if (requestTypesData[typeId]) {
                    console.log('Request type data found:', requestTypesData[typeId]);
                    
                    // إظهار المستندات المطلوبة
                    showRequiredDocuments(typeId);
                    
                    // إنشاء الحقول الديناميكية
                    generateDynamicFields(typeId);
                } else {
                    console.error('No data found for request type:', typeId);
                }
                
                // تفعيل زر التالي
                const nextButton = document.getElementById('step2-next');
                if (nextButton) {
                    nextButton.disabled = false;
                    nextButton.classList.remove('opacity-50', 'cursor-not-allowed');
                    console.log('Next button enabled');
                } else {
                    console.error('Could not find next button');
                }
                
            } catch (error) {
                console.error('Error in selectRequestType:', error);
            }
        }

        function showRequiredDocuments(typeId) {
            const typeData = requestTypesData[typeId];
            console.log('Showing required documents for type:', typeId, typeData);
            
            if (typeData && typeData.required_documents && typeData.required_documents.length > 0) {
                const documentsDiv = document.getElementById('required-documents');
                const documentsList = document.getElementById('documents-list');
                
                // التعامل مع المستندات كمصفوفة
                let docs = typeData.required_documents;
                if (typeof docs === 'string') {
                    docs = docs.split('\n').filter(doc => doc.trim());
                }
                
                let documentsHTML = '';
                docs.forEach(doc => {
                    if (doc && doc.trim()) {
                        documentsHTML += `<div class="flex items-center mb-2">
                            <span class="text-amber-600 mr-2">📄</span>
                            <span>${doc.trim()}</span>
                        </div>`;
                    }
                });
                
                if (documentsHTML) {
                    documentsList.innerHTML = documentsHTML;
                    documentsDiv.style.display = 'block';
                } else {
                    documentsDiv.style.display = 'none';
                }
            } else {
                document.getElementById('required-documents').style.display = 'none';
            }
            
            // إظهار معلومات التكلفة
            showCostInfo(typeId);
        }
        
        function showCostInfo(typeId) {
            const typeData = requestTypesData[typeId];
            
            // البحث عن منطقة لعرض معلومات التكلفة أو إنشاؤها
            let costInfoDiv = document.getElementById('cost-info');
            if (!costInfoDiv) {
                costInfoDiv = document.createElement('div');
                costInfoDiv.id = 'cost-info';
                costInfoDiv.className = 'bg-green-50 border border-green-200 rounded-lg p-4 mt-4';
                
                // إدراج div التكلفة بعد المستندات المطلوبة
                const requiredDocsDiv = document.getElementById('required-documents');
                requiredDocsDiv.parentNode.insertBefore(costInfoDiv, requiredDocsDiv.nextSibling);
            }
            
            if (typeData && typeData.cost > 0) {
                costInfoDiv.innerHTML = `
                    <h3 class="font-bold text-green-800 mb-2">💰 معلومات التكلفة:</h3>
                    <div class="text-green-700">
                        <p class="text-lg font-semibold">التكلفة: ${parseFloat(typeData.cost).toLocaleString()} ${typeData.currency_symbol}</p>
                        <p class="text-sm mt-1">يجب دفع الرسوم عند تقديم الطلب أو حسب تعليمات البلدية</p>
                    </div>
                `;
                costInfoDiv.style.display = 'block';
            } else {
                costInfoDiv.style.display = 'none';
            }
        }

        function generateDynamicFields(typeId) {
            const typeData = requestTypesData[typeId];
            const dynamicFieldsDiv = document.getElementById('dynamic-fields');
            
            if (typeData && typeData.form_fields && typeData.form_fields.length > 0) {
                let fieldsHTML = '<h3 class="font-bold text-gray-800 mb-4">معلومات إضافية مطلوبة:</h3>';
                
                typeData.form_fields.forEach((field, index) => {
                    fieldsHTML += `<div class="dynamic-field form-field">`;
                    fieldsHTML += `<label class="block text-sm font-medium text-gray-700 mb-2">${field.label}${field.required ? ' *' : ''}</label>`;
                    
                    switch (field.type) {
                        case 'text':
                        case 'email':
                        case 'tel':
                        case 'number':
                            fieldsHTML += `<input type="${field.type}" name="field_${field.name}" ${field.required ? 'required' : ''} 
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-300"
                                          placeholder="${field.placeholder || ''}">`;
                            break;
                        case 'textarea':
                            fieldsHTML += `<textarea name="field_${field.name}" ${field.required ? 'required' : ''} rows="3"
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-300"
                                          placeholder="${field.placeholder || ''}"></textarea>`;
                            break;
                        case 'select':
                            fieldsHTML += `<select name="field_${field.name}" ${field.required ? 'required' : ''}
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-300">
                                          <option value="">اختر...</option>`;
                            if (field.options) {
                                field.options.forEach(option => {
                                    fieldsHTML += `<option value="${option}">${option}</option>`;
                                });
                            }
                            fieldsHTML += `</select>`;
                            break;
                        case 'checkbox':
                            fieldsHTML += `<div class="flex items-center">
                                          <input type="checkbox" name="field_${field.name}" value="نعم" id="field_${field.name}"
                                                 class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                          <label for="field_${field.name}" class="mr-2 text-sm text-gray-700">${field.label}</label>
                                          </div>`;
                            break;
                        case 'date':
                            fieldsHTML += `<input type="date" name="field_${field.name}" ${field.required ? 'required' : ''}
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-300">`;
                            break;
                    }
                    
                    fieldsHTML += `<input type="hidden" name="fieldtype_${field.name}" value="${field.type}">`;
                    fieldsHTML += `</div>`;
                });
                
                dynamicFieldsDiv.innerHTML = fieldsHTML;
            } else {
                dynamicFieldsDiv.innerHTML = '';
            }
        }

        function handleFileSelect(input) {
            const fileList = document.getElementById('file-list');
            fileList.innerHTML = '';
            
            if (input.files.length > 0) {
                const filesArray = Array.from(input.files);
                filesArray.forEach((file, index) => {
                    const fileDiv = document.createElement('div');
                    fileDiv.className = 'flex items-center justify-between bg-gray-50 p-3 rounded-lg mb-2';
                    fileDiv.innerHTML = `
                        <div class="flex items-center">
                            <svg class="h-5 w-5 text-gray-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-sm text-gray-700">${file.name}</span>
                            <span class="text-xs text-gray-500 mr-2">(${(file.size / 1024 / 1024).toFixed(2)} MB)</span>
                        </div>
                        <button type="button" onclick="removeFile(${index})" class="text-red-500 hover:text-red-700">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                        </button>
                    `;
                    fileList.appendChild(fileDiv);
                });
            }
        }

        function removeFile(index) {
            const input = document.getElementById('file-input');
            const dt = new DataTransfer();
            const files = Array.from(input.files);
            
            files.forEach((file, i) => {
                if (i !== index) {
                    dt.items.add(file);
                }
            });
            
            input.files = dt.files;
            handleFileSelect(input);
        }

        function generateSummary() {
            const form = document.getElementById('requestForm');
            const formData = new FormData(form);
            const summaryDiv = document.getElementById('request-summary');
            
            let summaryHTML = '';
            
            // المعلومات الشخصية
            summaryHTML += `<div class="mb-4">
                <h4 class="font-semibold text-gray-800 mb-2">المعلومات الشخصية:</h4>
                <p><strong>الاسم:</strong> ${formData.get('citizen_name') || 'غير محدد'}</p>
                <p><strong>الهاتف:</strong> ${formData.get('citizen_phone') || 'غير محدد'}</p>
                <p><strong>البريد الإلكتروني:</strong> ${formData.get('citizen_email') || 'غير محدد'}</p>
                <p><strong>العنوان:</strong> ${formData.get('citizen_address') || 'غير محدد'}</p>
            </div>`;
            
            // نوع الطلب
            if (selectedRequestType) {
                const typeData = requestTypesData[selectedRequestType];
                summaryHTML += `<div class="mb-4">
                    <h4 class="font-semibold text-gray-800 mb-2">نوع الطلب:</h4>
                    <p><strong>${typeData.name}</strong></p>
                    ${typeData.cost > 0 ? `<p class="text-green-600"><strong>التكلفة:</strong> ${parseFloat(typeData.cost).toLocaleString()} ${typeData.currency_symbol}</p>` : ''}
                </div>`;
            }
            
            // تفاصيل الطلب
            summaryHTML += `<div class="mb-4">
                <h4 class="font-semibold text-gray-800 mb-2">تفاصيل الطلب:</h4>
                <p><strong>العنوان:</strong> ${formData.get('request_title') || 'غير محدد'}</p>
                <p><strong>الوصف:</strong> ${formData.get('request_description') || 'غير محدد'}</p>
                <p><strong>الأولوية:</strong> ${formData.get('priority_level') || 'عادي'}</p>
            </div>`;
            
            // الملفات المرفقة
            const fileInput = document.getElementById('file-input');
            if (fileInput.files.length > 0) {
                summaryHTML += `<div class="mb-4">
                    <h4 class="font-semibold text-gray-800 mb-2">الملفات المرفقة:</h4>
                    <ul class="list-disc list-inside">`;
                Array.from(fileInput.files).forEach(file => {
                    summaryHTML += `<li>${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)</li>`;
                });
                summaryHTML += `</ul></div>`;
            }
            
            summaryDiv.innerHTML = summaryHTML;
        }

        // تهيئة الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            // تعطيل زر التالي في الخطوة 2 في البداية
            document.getElementById('step2-next').disabled = true;
            document.getElementById('step2-next').classList.add('opacity-50', 'cursor-not-allowed');
        });
        
        // نسخ رمز الدخول
        function copyCode(code) {
            navigator.clipboard.writeText(code).then(() => {
                alert('✅ تم نسخ رمز الدخول!');
            }).catch(err => {
                // طريقة بديلة
                const textarea = document.createElement('textarea');
                textarea.value = code;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('✅ تم نسخ رمز الدخول!');
            });
        }
        
        function copyText(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('✅ تم النسخ: ' + text);
            }).catch(err => {
                // طريقة بديلة
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('✅ تم النسخ: ' + text);
            });
        }
        
        // ===================================
        // نظام جلب البيانات برمز الدخول
        // ===================================
        
        window.addEventListener('DOMContentLoaded', function() {
            // إضافة Enter key للبحث برمز الدخول
            document.getElementById('access-code-input').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    loadDataByAccessCode();
                }
            });
        });
        
        function loadDataByAccessCode() {
            let codeInput = document.getElementById('access-code-input').value.trim();
            codeInput = codeInput.replace(/\D/g, '');
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
                    document.getElementById('national_id').value = data.national_id || '';
                    document.getElementById('citizen_address').value = data.address || '';
                    
                    // إظهار رسالة النجاح
                    document.getElementById('loaded-citizen-name').textContent = data.name;
                    document.getElementById('access-code-success').classList.remove('hidden');
                    
                    // إخفاء قسم رمز الدخول وإظهار النموذج
                    setTimeout(() => {
                        // حفظ رمز الدخول ورقم الهاتف الأصلي (قبل التحقق من الهاتف!)
                        loadedAccessCode = fullAccessCode;
                        originalPhone = data.phone; // حفظ رقم الهاتف الأصلي
                        
                        document.getElementById('access-code-section').style.display = 'none';
                        document.getElementById('personal-info-form').classList.remove('hidden');
                        
                        // التحقق من رقم الهاتف (الآن loadedAccessCode محدد)
                        if (data.phone) {
                            verifyPhoneNumber(data.phone);
                        }
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
            originalPhone = null;
            
            // إخفاء قسم رمز الدخول وإظهار النموذج
            document.getElementById('access-code-section').style.display = 'none';
            document.getElementById('personal-info-form').classList.remove('hidden');
        }
        
        function showAccessCodeError(message) {
            const errorDiv = document.getElementById('access-code-error');
            errorDiv.querySelector('p').textContent = message;
            errorDiv.classList.remove('hidden');
        }
        
        // ===================================
        // نظام التحقق من رقم الهاتف
        // ===================================
        
        function verifyPhoneNumber(phone) {
            if (!phone || phone.length < 6) {
                hidePhoneVerification();
                enableNextButton();
                return;
            }
            
            // إرسال طلب للتحقق من ملكية رقم الهاتف
            const currentAccessCode = loadedAccessCode || '';
            
            // Debug logging
            console.log('=== VERIFY PHONE DEBUG ===');
            console.log('Phone:', phone);
            console.log('loadedAccessCode:', loadedAccessCode);
            console.log('currentAccessCode:', currentAccessCode);
            console.log('originalPhone:', originalPhone);
            
            fetch('check_phone_ownership.php?phone=' + encodeURIComponent(phone) + '&current_access_code=' + encodeURIComponent(currentAccessCode))
            .then(response => response.json())
            .then(data => {
                console.log('Response:', data);
                showPhoneVerification(data, phone);
            })
            .catch(error => {
                console.error('Error verifying phone:', error);
                hidePhoneVerification();
                enableNextButton();
            });
        }
        
        function showPhoneVerification(data, phone) {
            const badge = document.getElementById('phone-verification-badge');
            const message = document.getElementById('phone-verification-message');
            const icon = document.getElementById('phone-check-icon');
            const phoneInput = document.getElementById('citizen_phone');
            
            if (!data.available) {
                // الرقم تابع لمواطن آخر - ممنوع ❌
                badge.className = 'mr-2 bg-red-100 text-red-800 text-xs px-2 py-1 rounded-full font-bold';
                badge.textContent = '❌ محجوز';
                badge.classList.remove('hidden');
                
                message.className = 'text-xs mt-1 text-red-700 font-bold';
                message.textContent = '❌ هذا الرقم مسجّل مسبقاً لمواطن آخر. لا يمكن استخدامه.';
                message.classList.remove('hidden');
                
                icon.innerHTML = '<span class="text-red-600 text-2xl">✕</span>';
                icon.classList.remove('hidden');
                
                phoneInput.classList.remove('border-gray-300', 'border-yellow-300', 'border-green-500');
                phoneInput.classList.add('border-red-500');
                
                // تعطيل زر "التالي"
                disableNextButton();
                
            } else if (data.is_owner) {
                // نفس المواطن - رقمه الحالي ✅
                badge.className = 'mr-2 bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full font-bold';
                badge.textContent = '✅ رقمك';
                badge.classList.remove('hidden');
                
                message.className = 'text-xs mt-1 text-green-700';
                // تحقق إذا كان الرقم تم تعديله أم لا
                if (originalPhone && phone !== originalPhone) {
                    message.textContent = '✅ رقم هاتفك الحالي (لم تقم بتغييره)';
                } else {
                    message.textContent = '✅ رقم هاتفك الحالي';
                }
                message.classList.remove('hidden');
                
                icon.innerHTML = '<span class="text-green-600 text-2xl">✓</span>';
                icon.classList.remove('hidden');
                
                phoneInput.classList.remove('border-gray-300', 'border-yellow-300', 'border-red-500', 'border-blue-500');
                phoneInput.classList.add('border-green-500');
                
                // تمكين زر "التالي"
                enableNextButton();
                
            } else {
                // الرقم غير موجود - متاح للاستخدام
                if (loadedAccessCode && originalPhone && phone !== originalPhone) {
                    // المواطن المسجّل يريد تغيير رقمه إلى رقم جديد متاح
                    badge.className = 'mr-2 bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full font-bold';
                    badge.textContent = '🔄 متاح';
                    badge.classList.remove('hidden');
                    
                    message.className = 'text-xs mt-1 text-blue-700';
                    message.textContent = '🔄 رقم جديد متاح - سيتم تحديث رقمك';
                    message.classList.remove('hidden');
                } else {
                    // مواطن جديد تماماً
                    badge.className = 'mr-2 bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full font-bold';
                    badge.textContent = '🆕 جديد';
                    badge.classList.remove('hidden');
                    
                    message.className = 'text-xs mt-1 text-blue-700';
                    message.textContent = '🆕 رقم جديد - سيتم إنشاء حساب لك';
                    message.classList.remove('hidden');
                }
                
                icon.innerHTML = '<span class="text-blue-600 text-2xl">+</span>';
                icon.classList.remove('hidden');
                
                phoneInput.classList.remove('border-gray-300', 'border-yellow-300', 'border-red-500');
                phoneInput.classList.add('border-blue-500');
                
                // تمكين زر "التالي"
                enableNextButton();
            }
        }
        
        function hidePhoneVerification() {
            document.getElementById('phone-verification-badge').classList.add('hidden');
            document.getElementById('phone-verification-message').classList.add('hidden');
            document.getElementById('phone-check-icon').classList.add('hidden');
            document.getElementById('citizen_phone').classList.remove('border-green-500', 'border-yellow-300', 'border-red-500', 'border-blue-500');
            document.getElementById('citizen_phone').classList.add('border-gray-300');
        }
        
        function disableNextButton() {
            const nextButtons = document.querySelectorAll('button[onclick="nextStep()"]');
            nextButtons.forEach(btn => {
                if (btn.closest('#step-1')) {
                    btn.disabled = true;
                    btn.classList.add('opacity-50', 'cursor-not-allowed');
                    btn.classList.remove('hover:bg-blue-700');
                }
            });
        }
        
        function enableNextButton() {
            const nextButtons = document.querySelectorAll('button[onclick="nextStep()"]');
            nextButtons.forEach(btn => {
                if (btn.closest('#step-1')) {
                    btn.disabled = false;
                    btn.classList.remove('opacity-50', 'cursor-not-allowed');
                    btn.classList.add('hover:bg-blue-700');
                }
            });
        }
        
        // دالة مساعدة لتأخير التنفيذ (debounce)
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        // تحديث بيانات المواطن عند الانتقال من الخطوة الأولى
        async function updateCitizenData() {
            if (!loadedAccessCode) {
                return true; // لا حاجة للتحديث إذا لم يتم تحميل بيانات
            }
            
            const fullName = document.getElementById('citizen_name').value.trim();
            const phone = document.getElementById('citizen_phone').value.trim();
            const email = document.getElementById('citizen_email').value.trim();
            const nationalId = document.getElementById('national_id').value.trim();
            const address = document.getElementById('citizen_address').value.trim();
            
            if (!fullName || !phone) {
                return false;
            }
            
            try {
                const response = await fetch('update_citizen_data.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        access_code: loadedAccessCode,
                        full_name: fullName,
                        phone: phone,
                        email: email,
                        national_id: nationalId,
                        address: address
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    console.log('✅ تم تحديث بيانات المواطن بنجاح');
                    // تحديث localStorage أيضاً
                    localStorage.setItem('citizen_name', fullName);
                    localStorage.setItem('citizen_phone', phone);
                    localStorage.setItem('citizen_email', email);
                    localStorage.setItem('citizen_national_id', nationalId);
                    localStorage.setItem('citizen_address', address);
                    return true;
                } else {
                    console.error('❌ فشل تحديث البيانات:', result.message);
                    return false;
                }
            } catch (error) {
                console.error('❌ خطأ في الاتصال:', error);
                return false;
            }
        }
    </script>
</body>
</html>

