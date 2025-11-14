<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../config/database.php';
require_once '../includes/recaptcha_helper.php';

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

$success_message = '';
$error_message = '';
$tracking_number = '';

// معالجة تقديم الطلب
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_request'])) {
    // تشخيص: طباعة البيانات المُرسلة
    error_log('Form submitted with data: ' . print_r($_POST, true));
    
    $citizen_name = trim($_POST['citizen_name']);
    $citizen_phone = trim($_POST['citizen_phone']);
    $citizen_email = trim($_POST['citizen_email']);
    $citizen_address = trim($_POST['citizen_address']);
    $national_id = trim($_POST['national_id']);
    $request_type = $_POST['request_type'];
    $request_title = trim($_POST['request_title']);
    $request_description = trim($_POST['request_description']);
    $priority_level = $_POST['priority_level'] ?? 'عادي';
    $project_id = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
    
    // التحقق من reCAPTCHA مع إعدادات مرنة للاختبار المحلي
    $min_score = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') ? 0.3 : 0.5;
    $recaptcha_result = verify_recaptcha($_POST, $_SERVER['REMOTE_ADDR'] ?? null, $min_score);
    
    // متغير لتتبع حالة reCAPTCHA
    $recaptcha_warning = '';
    if (!$recaptcha_result['success']) {
        // للاختبار المحلي: تسجيل الخطأ ولكن السماح بالمتابعة
        if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
            error_log('reCAPTCHA warning (localhost): ' . $recaptcha_result['error'] . ' - Score: ' . ($recaptcha_result['score'] ?? 'unknown'));
            $recaptcha_warning = '⚠️ تحذير الأمان (اختبار محلي): ' . $recaptcha_result['error'];
        } else {
            $error_message = 'فشل التحقق الأمني: ' . $recaptcha_result['error'];
            error_log('reCAPTCHA failed for citizen request from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        }
    }
    
    if (empty($citizen_name) || empty($citizen_phone) || empty($request_type) || empty($request_title) || empty($request_description)) {
        $error_message = "جميع الحقول المطلوبة يجب ملؤها";
    } elseif ($request_type == 'المساهمة في المشروع' && empty($project_id)) {
        $error_message = "يجب اختيار المشروع المراد المساهمة فيه";
    } else {
        try {
            // إنشاء رقم تتبع فريد
            $tracking_number = 'REQ' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // التحقق من عدم تكرار رقم التتبع
            $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM citizen_requests WHERE tracking_number = ?");
            $check_stmt->execute([$tracking_number]);
            if ($check_stmt->fetch()['count'] > 0) {
                $tracking_number = 'REQ' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            }
            
            // إدراج الطلب في قاعدة البيانات
            $stmt = $db->prepare("
                INSERT INTO citizen_requests 
                (tracking_number, citizen_name, citizen_phone, citizen_email, citizen_address, national_id, 
                 request_type, project_id, request_title, request_description, priority_level) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $tracking_number, $citizen_name, $citizen_phone, $citizen_email, $citizen_address, 
                $national_id, $request_type, $project_id, $request_title, $request_description, $priority_level
            ]);
            
            $success_message = "تم تقديم طلبك بنجاح! رقم التتبع الخاص بك هو: " . $tracking_number;
            
            // إضافة تحذير reCAPTCHA للاختبار المحلي إذا وجد
            if ($recaptcha_warning) {
                $success_message .= "<br><br>" . $recaptcha_warning;
            }
            
            // إعادة تعيين النموذج
            $_POST = array();
            
        } catch (Exception $e) {
            $error_message = "حدث خطأ أثناء تقديم الطلب: " . $e->getMessage();
            error_log('Citizen Request Error: ' . $e->getMessage());
            // تسجيل الخطأ للتشخيص
            error_log('Database Error in citizen-requests: ' . $e->getMessage());
        }
    }
}

// جلب إعدادات الموقع
function getSetting($key, $default = '') {
    global $db;
    $stmt = $db->prepare("SELECT setting_value FROM website_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : $default;
}

$site_title = getSetting('site_title', 'بلدية تكريت');

// جلب نوع الطلب من الرابط
$selected_type = $_GET['type'] ?? '';

// جلب معرف المشروع من الرابط
$selected_project = $_GET['project_id'] ?? '';

// جلب المشاريع التي تسمح بالمساهمة
$projects = [];
try {
    $projects_stmt = $db->query("
        SELECT id, project_name 
        FROM development_projects 
        WHERE allow_contributions = 1 AND project_status != 'منفذ' 
        ORDER BY project_name
    ");
    $projects = $projects_stmt->fetchAll();
} catch (Exception $e) {
    // في حالة عدم وجود جدول أو خطأ
    $projects = [];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site_title) ?> - طلبات المواطنين</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/tekrit-theme.css" rel="stylesheet">
    <?= RecaptchaHelper::renderScript() ?>
    <?= RecaptchaHelper::renderCSS() ?>
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="tekrit-header sticky top-0 z-50">
        <div class="container mx-auto px-0">
            <div class="flex items-center justify-between mb-4">
                <!-- Logo and Title -->
               <div class="flex items-center">
				  <img 
					src="assets/images/Tekrit_LOGO.png" 
					alt="شعار بلدية تكريت" 
					class="tekrit-logo ml-4 w-20 h-24 sm:w-24 sm:h-28 md:w-28 md:h-32 object-contain border-0"
				  >
				  <div>
					<h1 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($site_title) ?></h1>
					<p class="text-sm text-gray-600 hidden sm:block">خدمات إلكترونية للمواطنين</p>
				  </div>
				</div>

                <!-- Desktop Navigation -->
                <nav class="hidden lg:flex space-x-8 space-x-reverse">
                    <a href="index.php" class="text-gray-700 hover:text-blue-600 font-medium">الرئيسية</a>
                    <a href="#" class="text-blue-600 font-medium">طلبات المواطنين</a>
                    <a href="projects.php" class="text-gray-700 hover:text-blue-600 font-medium">المشاريع</a>
                    <a href="initiatives.php" class="text-gray-700 hover:text-blue-600 font-medium">المبادرات</a>
                    <a href="news.php" class="text-gray-700 hover:text-blue-600 font-medium">الأخبار</a>
                    <div class="relative group">
                        <button class="text-gray-700 hover:text-blue-600 font-medium flex items-center">
                            البلدية
                            <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                            <div class="py-1">
                                <a href="council.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">👥 المجلس البلدي</a>
                                <a href="committees.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">📋 اللجان البلدية</a>
                            </div>
                        </div>
                    </div>
                    <a href="facilities-map.php" class="text-gray-700 hover:text-blue-600 font-medium">🗺️ خريطة المرافق</a>
                    <a href="contact.php" class="text-gray-700 hover:text-blue-600 font-medium">اتصل بنا</a>
                </nav>
                
                <!-- Desktop Login Button -->
                <div class="hidden lg:flex items-center space-x-4 space-x-reverse">
                    <a href="../login.php" class="btn-primary-orange">
                        🔐 دخول الموظفين
                    </a>
                </div>

                <!-- Mobile menu button -->
                <div class="lg:hidden">
                    <button id="mobile-menu-btn" class="text-gray-700 hover:text-blue-600 focus:outline-none focus:text-blue-600">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile Navigation -->
            <div id="mobile-menu" class="lg:hidden hidden">
                <div class="px-2 pt-2 pb-3 space-y-1 bg-white border-t border-gray-200">
                    <a href="index.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium">الرئيسية</a>
                    <a href="#" class="block px-3 py-2 text-blue-600 bg-blue-50 rounded-md font-medium">طلبات المواطنين</a>
                    <a href="projects.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium">المشاريع</a>
                    <a href="initiatives.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium">المبادرات</a>
                    <a href="news.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium">الأخبار</a>
                    
                    <!-- Mobile Municipality Submenu -->
                    <div class="space-y-1">
                        <button id="mobile-municipality-btn" class="w-full text-right px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium flex items-center justify-between">
                            البلدية
                            <svg class="h-4 w-4 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div id="mobile-municipality-menu" class="hidden pr-4 space-y-1">
                            <a href="council.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-blue-600 hover:bg-gray-50 rounded-md">👥 المجلس البلدي</a>
                            <a href="committees.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-blue-600 hover:bg-gray-50 rounded-md">📋 اللجان البلدية</a>
                        </div>
                    </div>
                    
                    <a href="facilities-map.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium">🗺️ خريطة المرافق</a>
                    <a href="contact.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium">اتصل بنا</a>
                    
                    <!-- Mobile Login Button -->
                    <div class="pt-4 border-t border-gray-200">
                        <a href="../login.php" class="block w-full text-center btn-primary-orange">
                            🔐 دخول الموظفين
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">📝 تقديم طلب جديد</h1>
            <p class="text-xl text-gray-600">
                قدم طلبك إلكترونياً واحصل على رقم تتبع لمتابعة حالة طلبك
            </p>
        </div>

        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                <div class="flex items-center">
                    <span class="text-green-500 text-xl ml-3">✅</span>
                    <div>
                        <p class="font-bold"><?= $success_message ?></p>
                        <p class="text-sm mt-1">احفظ رقم التتبع لمتابعة طلبك لاحقاً</p>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="track-request.php?tracking=<?= $tracking_number ?>" 
                       class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        تتبع الطلب الآن
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                <div class="flex items-center">
                    <span class="text-red-500 text-xl ml-3">❌</span>
                    <p class="font-bold"><?= $error_message ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Request Form -->
        <div class="bg-white shadow-lg rounded-lg p-8">
            <form method="POST" class="space-y-6">
                <!-- Personal Information -->
                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">المعلومات الشخصية</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">الاسم الكامل *</label>
                            <input type="text" name="citizen_name" value="<?= htmlspecialchars($_POST['citizen_name'] ?? '') ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" 
                                   required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">رقم الهاتف *</label>
                            <input type="tel" name="citizen_phone" value="<?= htmlspecialchars($_POST['citizen_phone'] ?? '') ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" 
                                   required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">البريد الإلكتروني</label>
                            <input type="email" name="citizen_email" value="<?= htmlspecialchars($_POST['citizen_email'] ?? '') ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">رقم البطاقة الوطنية</label>
                            <input type="text" name="national_id" value="<?= htmlspecialchars($_POST['national_id'] ?? '') ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                    <div class="mt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">العنوان الكامل</label>
                        <textarea name="citizen_address" rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"><?= htmlspecialchars($_POST['citizen_address'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Request Information -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">تفاصيل الطلب</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">نوع الطلب *</label>
                            <select name="request_type" id="request_type"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" 
                                    required onchange="toggleProjectField()">
                                <option value="">اختر نوع الطلب</option>
                                <option value="إفادة سكن" <?= ($selected_type == 'إفادة سكن' || ($_POST['request_type'] ?? '') == 'إفادة سكن') ? 'selected' : '' ?>>إفادة سكن</option>
                                <option value="شكوى" <?= ($selected_type == 'شكوى' || ($_POST['request_type'] ?? '') == 'شكوى') ? 'selected' : '' ?>>شكوى</option>
                                <option value="بلاغ أعطال" <?= ($selected_type == 'بلاغ أعطال' || ($_POST['request_type'] ?? '') == 'بلاغ أعطال') ? 'selected' : '' ?>>بلاغ أعطال</option>
                                <option value="استشارة هندسية" <?= ($selected_type == 'استشارة هندسية' || ($_POST['request_type'] ?? '') == 'استشارة هندسية') ? 'selected' : '' ?>>استشارة هندسية</option>
                                <option value="طلب خدمة" <?= ($_POST['request_type'] ?? '') == 'طلب خدمة' ? 'selected' : '' ?>>طلب خدمة</option>
                                <option value="اقتراح" <?= ($_POST['request_type'] ?? '') == 'اقتراح' ? 'selected' : '' ?>>اقتراح</option>
                                <option value="المساهمة في المشروع" <?= ($selected_type == 'المساهمة في المشروع' || ($_POST['request_type'] ?? '') == 'المساهمة في المشروع') ? 'selected' : '' ?>>المساهمة في المشروع</option>
                                <option value="أخرى" <?= ($_POST['request_type'] ?? '') == 'أخرى' ? 'selected' : '' ?>>أخرى</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">مستوى الأولوية</label>
                            <select name="priority_level" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="عادي" <?= ($_POST['priority_level'] ?? '') == 'عادي' ? 'selected' : '' ?>>عادي</option>
                                <option value="مهم" <?= ($_POST['priority_level'] ?? '') == 'مهم' ? 'selected' : '' ?>>مهم</option>
                                <option value="عاجل" <?= ($_POST['priority_level'] ?? '') == 'عاجل' ? 'selected' : '' ?>>عاجل</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Project Selection - Only show for contribution requests -->
                    <div id="project_selection" class="mt-6" style="display: <?= ($selected_type == 'المساهمة في المشروع' || ($_POST['request_type'] ?? '') == 'المساهمة في المشروع') ? 'block' : 'none' ?>;">
                        <label class="block text-sm font-medium text-gray-700 mb-2">اختر المشروع *</label>
                        <select name="project_id" id="project_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">اختر المشروع الذي تريد المساهمة فيه</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?= $project['id'] ?>" 
                                        <?= ($selected_project == $project['id'] || ($_POST['project_id'] ?? '') == $project['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($project['project_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($projects)): ?>
                            <p class="text-sm text-gray-500 mt-1">لا توجد مشاريع متاحة للمساهمة حالياً</p>
                        <?php endif; ?>
                    </div>
                    <div class="mt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">عنوان الطلب *</label>
                        <input type="text" name="request_title" value="<?= htmlspecialchars($_POST['request_title'] ?? '') ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" 
                               placeholder="اكتب عنواناً مختصراً لطلبك" required>
                    </div>
                    <div class="mt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">تفاصيل الطلب *</label>
                        <textarea name="request_description" rows="6" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" 
                                  placeholder="اشرح طلبك بالتفصيل..." required><?= htmlspecialchars($_POST['request_description'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- reCAPTCHA v3 -->
                <div class="recaptcha-container">
                    <?= RecaptchaHelper::renderWidget('citizen_request') ?>
                    <div class="text-center text-sm text-gray-500 mb-4">
                        🛡️ محمي بواسطة reCAPTCHA
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-center pt-6">
                    <button type="submit" name="submit_request" 
                            class="px-8 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition duration-300">
                        📤 تقديم الطلب
                    </button>
                </div>
            </form>
        </div>

        <!-- Help Section -->
        <div class="mt-12 bg-blue-50 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-blue-900 mb-4">💡 نصائح مهمة</h3>
            <ul class="space-y-2 text-blue-800">
                <li>• تأكد من صحة رقم الهاتف للتواصل معك</li>
                <li>• اكتب تفاصيل الطلب بوضوح ليتم التعامل معه بسرعة</li>
                <li>• احفظ رقم التتبع الذي ستحصل عليه لمتابعة طلبك</li>
                <li>• يمكنك تتبع حالة طلبك في أي وقت من صفحة "تتبع الطلب"</li>
                <li>• في حالة الطوارئ، يرجى الاتصال بنا مباشرة</li>
            </ul>
        </div>

        <!-- Quick Actions -->
        <div class="mt-8 text-center">
            <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-4 sm:space-x-reverse">
                <a href="track-request.php" class="px-6 py-3 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700 transition duration-300">
                    🔍 تتبع طلب موجود
                </a>

                <a href="contact.php" class="px-6 py-3 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 transition duration-300">
                    📞 اتصل بنا
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center">
                 <div class="text-center md:text-left mb-4 md:mb-0">
                    <p class="text-gray-400">© <?= date('Y') ?> جميع الحقوق محفوظة - <?= htmlspecialchars($site_title) ?></p>
                </div>
                <div class="flex items-center text-center md:text-right">
                    <a href="https://www.sobusiness.group/" target="_blank" class="hover:opacity-80 transition-opacity">
                        <img src="assets/images/sobusiness-logo.png" alt="SoBusiness Group" class="h-8 w-auto">
                    </a>
					<span class="text-gray-400 text-sm mr-2">Development and Designed By</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Project Selection JavaScript -->
    <script>
        function toggleProjectField() {
            const requestType = document.getElementById('request_type').value;
            const projectSelection = document.getElementById('project_selection');
            const projectId = document.getElementById('project_id');
            
            if (requestType === 'المساهمة في المشروع') {
                projectSelection.style.display = 'block';
                projectId.required = true;
            } else {
                projectSelection.style.display = 'none';
                projectId.required = false;
                projectId.value = '';
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleProjectField();
        });
    </script>

    <!-- Mobile Menu JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobile-menu-btn');
            const mobileMenu = document.getElementById('mobile-menu');
            const municipalityBtn = document.getElementById('mobile-municipality-btn');
            const municipalityMenu = document.getElementById('mobile-municipality-menu');

            if (mobileMenuBtn && mobileMenu) {
                // Toggle mobile menu
                mobileMenuBtn.addEventListener('click', function() {
                    mobileMenu.classList.toggle('hidden');
                    
                    // Toggle hamburger to X icon
                    const icon = mobileMenuBtn.querySelector('svg');
                    if (mobileMenu.classList.contains('hidden')) {
                        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />';
                    } else {
                        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />';
                    }
                });

                // Toggle municipality submenu in mobile
                if (municipalityBtn && municipalityMenu) {
                    municipalityBtn.addEventListener('click', function() {
                        municipalityMenu.classList.toggle('hidden');
                        
                        // Rotate arrow
                        const arrow = municipalityBtn.querySelector('svg');
                        arrow.classList.toggle('rotate-180');
                    });
                }

                // Close mobile menu when clicking outside
                document.addEventListener('click', function(event) {
                    if (!mobileMenuBtn.contains(event.target) && !mobileMenu.contains(event.target)) {
                        mobileMenu.classList.add('hidden');
                        const icon = mobileMenuBtn.querySelector('svg');
                        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />';
                    }
                });

                // Close mobile menu on window resize to desktop
                window.addEventListener('resize', function() {
                    if (window.innerWidth >= 1024) {
                        mobileMenu.classList.add('hidden');
                        const icon = mobileMenuBtn.querySelector('svg');
                        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />';
                    }
                });
            }
        });
    </script>
</body>
</html> 
