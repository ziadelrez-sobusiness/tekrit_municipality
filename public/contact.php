<?php
require_once '../config/database.php';
require_once '../includes/recaptcha_helper.php';

// Database connection
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    $error_message = "فشل الاتصال بقاعدة البيانات";
}

// Form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sender_name = trim($_POST['sender_name'] ?? '');
    $sender_email = trim($_POST['sender_email'] ?? '');
    $sender_phone = trim($_POST['sender_phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // التحقق من reCAPTCHA أولاً
    $recaptcha_result = verify_recaptcha($_POST, $_SERVER['REMOTE_ADDR'] ?? null);
    
    if (!$recaptcha_result['success']) {
        $error_message = $recaptcha_result['error'];
    } elseif ($sender_name && $sender_email && $subject && $message) {
        try {
            $stmt = $db->prepare("INSERT INTO contact_messages (sender_name, sender_email, sender_phone, subject, message, created_at, status) VALUES (?, ?, ?, ?, ?, NOW(), 'جديد')");
            $stmt->execute([$sender_name, $sender_email, $sender_phone, $subject, $message]);
            $success_message = "تم إرسال رسالتك بنجاح! سنقوم بالرد عليك في أقرب وقت ممكن.";
            
            // Clear form data
            $_POST = [];
        } catch(PDOException $e) {
            $error_message = "حدث خطأ أثناء إرسال الرسالة، يرجى المحاولة لاحقاً";
        }
    } else {
        $error_message = "يرجى ملء جميع الحقول المطلوبة";
    }
}

function getSetting($key, $default = '') {
    global $db;
    try {
        if ($db) {
            $stmt = $db->prepare("SELECT setting_value FROM website_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            return $result ? $result['setting_value'] : $default;
        }
        return $default;
    } catch(PDOException $e) {
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
    <title><?= htmlspecialchars($site_title) ?> - اتصل بنا</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/tekrit-theme.css" rel="stylesheet">
    <?= RecaptchaHelper::renderScript() ?>
    <?= RecaptchaHelper::renderCSS() ?>
    <style>
        body { font-family: 'Cairo', sans-serif; }
        #map { height: 400px; width: 100%; }
        .map-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 400px;
            background-color: #f3f4f6;
            border-radius: 0.5rem;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="tekrit-header sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-0 ">
            <div class="flex justify-between items-center h-24">
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
                    <a href="citizen-requests.php" class="text-gray-700 hover:text-blue-600 font-medium">طلبات المواطنين</a>
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
                    <a href="#" class="text-blue-600 font-medium">اتصل بنا</a>
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
                    <a href="citizen-requests.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium">طلبات المواطنين</a>
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
                    <a href="#" class="block px-3 py-2 text-blue-600 bg-blue-50 rounded-md font-medium">اتصل بنا</a>
                    
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

    <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">📞 اتصل بنا</h1>
            <p class="text-xl text-gray-600">
                نحن هنا لخدمتك! تواصل معنا في أي وقت
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <!-- Contact Information -->
            <div class="space-y-8">
                <div class="bg-white rounded-lg shadow-lg p-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">معلومات الاتصال</h2>
                    
                    <div class="space-y-6">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="bg-blue-100 p-3 rounded-lg">
                                    <span class="text-blue-600 text-xl">📍</span>
                                </div>
                            </div>
                            <div class="mr-4">
                                <h3 class="text-lg font-semibold text-gray-900">العنوان</h3>
                                <p class="text-gray-600"><?= htmlspecialchars(getSetting('contact_address', 'تكريت، العراق')) ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="bg-blue-100 p-3 rounded-lg">
                                    <span class="text-blue-600 text-xl">📞</span>
                                </div>
                            </div>
                            <div class="mr-4">
                                <h3 class="text-lg font-semibold text-gray-900">الهاتف</h3>
                                <p class="text-gray-600" dir="ltr"><?= htmlspecialchars(getSetting('contact_phone', '+964 XXX XXX XXXX')) ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="bg-blue-100 p-3 rounded-lg">
                                    <span class="text-blue-600 text-xl">✉️</span>
                                </div>
                            </div>
                            <div class="mr-4">
                                <h3 class="text-lg font-semibold text-gray-900">البريد الإلكتروني</h3>
                                <p class="text-gray-600" dir="ltr"><?= htmlspecialchars(getSetting('contact_email', 'info@tekrit-municipality.gov.iq')) ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="bg-blue-100 p-3 rounded-lg">
                                    <span class="text-blue-600 text-xl">🕒</span>
                                </div>
                            </div>
                            <div class="mr-4">
                                <h3 class="text-lg font-semibold text-gray-900">ساعات العمل</h3>
                                <p class="text-gray-600">الأحد - الخميس: 8:00 ص - 3:00 م</p>
                                <p class="text-gray-600">الجمعة - السبت: مغلق</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Emergency Contact -->
                <div class="bg-red-50 rounded-lg p-6 border border-red-200">
                    <h3 class="text-lg font-bold text-red-900 mb-4">🚨 في حالات الطوارئ</h3>
                    <div class="space-y-2">
                        <p class="text-red-800">
                            <span class="font-semibold">الطوارئ العامة:</span>
                            <span dir="ltr" class="mr-2">911</span>
                        </p>
                        <p class="text-red-800">
                            <span class="font-semibold">طوارئ البلدية:</span>
                            <span dir="ltr" class="mr-2"><?= htmlspecialchars(getSetting('emergency_phone', '+964 XXX XXX XXXX')) ?></span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Contact Form -->
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">أرسل لنا رسالة</h2>
                
                <!-- Messages -->
                <?php if ($success_message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                        <div class="flex items-center">
                            <span class="text-green-500 text-xl ml-3">✅</span>
                            <p class="font-bold"><?= $success_message ?></p>
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

                <form method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">الاسم الكامل *</label>
                            <input type="text" name="sender_name" value="<?= htmlspecialchars($_POST['sender_name'] ?? '') ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                   required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">رقم الهاتف</label>
                            <input type="tel" name="sender_phone" value="<?= htmlspecialchars($_POST['sender_phone'] ?? '') ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">البريد الإلكتروني *</label>
                        <input type="email" name="sender_email" value="<?= htmlspecialchars($_POST['sender_email'] ?? '') ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">الموضوع *</label>
                        <input type="text" name="subject" value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">الرسالة *</label>
                        <textarea name="message" rows="6" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                  placeholder="اكتب رسالتك هنا..." required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    </div>
                    
                    <!-- reCAPTCHA v3 -->
                    <div class="recaptcha-container">
                        <?= RecaptchaHelper::renderWidget('contact') ?>
                    </div>
                    
                    <div class="flex justify-center">
                        <button type="submit" 
                                class="px-8 py-3 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 transition duration-300">
                            📤 إرسال الرسالة
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Location Map Section -->
        <div class="mt-16 bg-white rounded-lg shadow-lg p-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-8 text-center">📍 موقع البلدية</h2>
            
            <!-- Embedded Map (Primary - Default) -->
            <div id="embedded-map" class="h-96 rounded-lg overflow-hidden border border-gray-300 mb-4">
                <?php 
                $lat = getSetting('contact_location_lat', '33.4384');
                $lng = getSetting('contact_location_lng', '43.6793');
                // Use standard Google Maps embed without API key
                $embedUrl = "https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d1000!2d" . $lng . "!3d" . $lat . "!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e0!3m2!1sar!2siq!4v1640000000000!5m2!1sar!2siq";
                ?>
                <iframe 
                    src="<?= $embedUrl ?>"
                    width="100%" 
                    height="100%" 
                    style="border:0;" 
                    allowfullscreen="" 
                    loading="lazy" 
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
            
            <!-- Alternative Map Options -->
            <div id="alternative-maps" class="h-96 rounded-lg overflow-hidden border border-gray-300 mb-4" style="display: none;">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 h-full">
                    <!-- OpenStreetMap -->
                    <div class="bg-gray-100 rounded-lg p-4 flex flex-col items-center justify-center">
                        <div class="text-4xl mb-4">🗺️</div>
                        <h3 class="font-bold text-lg mb-2">OpenStreetMap</h3>
                        <p class="text-sm text-gray-600 mb-4 text-center">خريطة مفتوحة المصدر</p>
                        <a href="https://www.openstreetmap.org/?mlat=<?= $lat ?>&mlon=<?= $lng ?>&zoom=15" 
                           target="_blank" 
                           class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            فتح الخريطة
                        </a>
                    </div>
                    
                    <!-- Bing Maps -->
                    <div class="bg-gray-100 rounded-lg p-4 flex flex-col items-center justify-center">
                        <div class="text-4xl mb-4">🌐</div>
                        <h3 class="font-bold text-lg mb-2">Bing Maps</h3>
                        <p class="text-sm text-gray-600 mb-4 text-center">خرائط مايكروسوفت</p>
                        <a href="https://www.bing.com/maps?cp=<?= $lat ?>~<?= $lng ?>&lvl=15" 
                           target="_blank" 
                           class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            فتح الخريطة
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Simple Location Display -->
            <div id="simple-location" class="h-96 rounded-lg overflow-hidden border border-gray-300 mb-4" style="display: none;">
                <div class="flex items-center justify-center h-full bg-gradient-to-br from-blue-50 to-green-50">
                    <div class="text-center p-8">
                        <div class="text-6xl mb-6">📍</div>
                        <h3 class="text-2xl font-bold text-gray-800 mb-4"><?= htmlspecialchars(getSetting('contact_location_name', 'بلدية تكريت')) ?></h3>
                        <p class="text-lg text-gray-600 mb-6"><?= htmlspecialchars(getSetting('contact_address', 'تكريت، العراق')) ?></p>
                        <div class="bg-white rounded-lg p-4 shadow-md">
                            <p class="text-sm text-gray-500 mb-2">الإحداثيات:</p>
                            <p class="font-mono text-lg" dir="ltr"><?= $lat ?>, <?= $lng ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center">
                <p class="text-gray-600 mb-4"><?= htmlspecialchars(getSetting('contact_location_name', 'بلدية تكريت')) ?></p>
                <div class="flex flex-wrap justify-center gap-3">
                    <a href="https://www.google.com/maps?q=<?= urlencode($lat) ?>,<?= urlencode($lng) ?>" 
                       target="_blank" 
                       class="inline-block px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-300">
                        🗺️ فتح في خرائط جوجل
                    </a>
                    <button onclick="toggleMapType()" 
                            class="inline-block px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition duration-300">
                        🔄 خرائط بديلة
                    </button>
                    <button onclick="showSimpleLocation()" 
                            class="inline-block px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition duration-300">
                        📍 عرض الموقع
                    </button>
                    <a href="https://maps.apple.com/?q=<?= urlencode($lat) ?>,<?= urlencode($lng) ?>" 
                       target="_blank" 
                       class="inline-block px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition duration-300">
                        🍎 خرائط أبل
                    </a>
                </div>
                
                <!-- Location Details -->
                <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="font-semibold">الإحداثيات:</span>
                            <span dir="ltr"><?= $lat ?>, <?= $lng ?></span>
                        </div>
                        <div>
                            <span class="font-semibold">العنوان:</span>
                            <?= htmlspecialchars(getSetting('contact_address', 'تكريت، العراق')) ?>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <p class="text-sm font-semibold text-gray-700 mb-2">إجراءات سريعة:</p>
                        <div class="flex flex-wrap gap-2">
                            <a href="https://www.google.com/maps/dir/?api=1&destination=<?= urlencode($lat) ?>,<?= urlencode($lng) ?>" 
                               target="_blank" 
                               class="text-xs px-3 py-1 bg-blue-100 text-blue-700 rounded-full hover:bg-blue-200">
                                🧭 الحصول على الاتجاهات
                            </a>
                            <button onclick="copyCoordinates()" 
                                    class="text-xs px-3 py-1 bg-green-100 text-green-700 rounded-full hover:bg-green-200">
                                📋 نسخ الإحداثيات
                            </button>
                            <a href="https://www.google.com/maps/search/restaurants+near+<?= urlencode($lat) ?>,<?= urlencode($lng) ?>" 
                               target="_blank" 
                               class="text-xs px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full hover:bg-yellow-200">
                                🍽️ مطاعم قريبة
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Services Section -->
        <div class="mt-16 bg-white rounded-lg shadow-lg p-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-8 text-center">🏛️ خدماتنا</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="text-center p-6 bg-blue-50 rounded-lg">
                    <div class="text-4xl mb-4">📝</div>
                    <h3 class="font-semibold text-gray-900 mb-2">طلبات المواطنين</h3>
                    <p class="text-sm text-gray-600">تقديم الطلبات والشكاوى إلكترونياً</p>
                    <a href="citizen-requests.php" class="inline-block mt-3 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        ابدأ الآن
                    </a>
                </div>
                
                <div class="text-center p-6 bg-green-50 rounded-lg">
                    <div class="text-4xl mb-4">🏗️</div>
                    <h3 class="font-semibold text-gray-900 mb-2">المشاريع الإنمائية</h3>
                    <p class="text-sm text-gray-600">تابع تقدم مشاريع التطوير</p>
                    <a href="projects.php" class="inline-block mt-3 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        استعرض
                    </a>
                </div>
                
                <div class="text-center p-6 bg-yellow-50 rounded-lg">
                    <div class="text-4xl mb-4">🌱</div>
                    <h3 class="font-semibold text-gray-900 mb-2">المبادرات</h3>
                    <p class="text-sm text-gray-600">شارك في المبادرات البيئية والاجتماعية</p>
                    <a href="initiatives.php" class="inline-block mt-3 px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700">
                        شارك
                    </a>
                </div>
                
                <div class="text-center p-6 bg-purple-50 rounded-lg">
                    <div class="text-4xl mb-4">📰</div>
                    <h3 class="font-semibold text-gray-900 mb-2">الأخبار</h3>
                    <p class="text-sm text-gray-600">آخر أخبار وأنشطة البلدية</p>
                    <a href="news.php" class="inline-block mt-3 px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                        اقرأ
                    </a>
                </div>
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

    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Google Maps
            function initMap() {
                try {
                    // Check if Google Maps API is loaded
                    if (!window.google || !window.google.maps) {
                        throw new Error('Google Maps API not loaded');
                    }

                    const lat = parseFloat(<?= getSetting('contact_location_lat', '33.4384') ?>);
                    const lng = parseFloat(<?= getSetting('contact_location_lng', '43.6793') ?>);
                    
                    // Validate coordinates
                    if (isNaN(lat) || isNaN(lng)) {
                        throw new Error('Invalid coordinates');
                    }

                    const location = { lat: lat, lng: lng };

                    const map = new google.maps.Map(document.getElementById('map'), {
                        zoom: 15,
                        center: location,
                        mapTypeId: google.maps.MapTypeId.ROADMAP,
                        gestureHandling: 'cooperative'
                    });

                    const marker = new google.maps.Marker({
                        position: location,
                        map: map,
                        title: '<?= htmlspecialchars(getSetting('contact_location_name', 'بلدية تكريت')) ?>',
                        animation: google.maps.Animation.DROP
                    });

                    // Add info window
                    const infoWindow = new google.maps.InfoWindow({
                        content: '<div style="text-align: center; font-family: Cairo, sans-serif;">' +
                                '<h3><?= htmlspecialchars(getSetting('contact_location_name', 'بلدية تكريت')) ?></h3>' +
                                '<p><?= htmlspecialchars(getSetting('contact_address', 'تكريت، العراق')) ?></p>' +
                                '</div>'
                    });

                    marker.addListener('click', function() {
                        infoWindow.open(map, marker);
                    });

                    console.log('تم تحميل الخريطة بنجاح');
                } catch (error) {
                    console.error('خطأ في تحميل الخريطة:', error);
                    showMapError();
                }
            }

            function showMapError() {
                document.getElementById('map').innerHTML = `
                    <div class="flex items-center justify-center h-full bg-gray-100 rounded">
                        <div class="text-center">
                            <div class="text-4xl mb-4">🗺️</div>
                            <p class="text-gray-600">عذراً، لا يمكن تحميل الخريطة حالياً</p>
                            <p class="text-sm text-gray-500 mt-2">يرجى التحقق من اتصال الإنترنت أو المحاولة لاحقاً</p>
                            <a href="https://www.google.com/maps?q=<?= urlencode(getSetting('contact_location_lat', '33.4384')) ?>,<?= urlencode(getSetting('contact_location_lng', '43.6793')) ?>" 
                               target="_blank" 
                               class="inline-block mt-3 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                فتح في خرائط جوجل
                            </a>
                        </div>
                    </div>
                `;
            }

            // Load Google Maps API dynamically with better error handling
            function loadGoogleMaps() {
                // Check if Google Maps is already loaded
                if (window.google && window.google.maps) {
                    initMap();
                    return;
                }

                const script = document.createElement('script');
                script.src = 'https://maps.googleapis.com/maps/api/js?key=AIzaSyBOti4mM-6x9WDnZIjIeyEU21OpBXqWBgw&callback=initMap';
                script.async = true;
                script.defer = true;
                
                script.onerror = function() {
                    console.error('فشل في تحميل Google Maps API');
                    showMapError();
                };

                // Set a timeout for loading
                setTimeout(function() {
                    if (!window.google || !window.google.maps) {
                        console.error('انتهت مهلة تحميل Google Maps API');
                        showMapError();
                    }
                }, 10000); // 10 seconds timeout

                document.head.appendChild(script);
            }

            // Toggle map type function
            window.toggleMapType = function() {
                const embeddedMap = document.getElementById('embedded-map');
                const alternativeMaps = document.getElementById('alternative-maps');
                const simpleLocation = document.getElementById('simple-location');
                const button = document.querySelector('button[onclick="toggleMapType()"]');
                
                if (alternativeMaps.style.display === 'none') {
                    // Show alternative maps
                    embeddedMap.style.display = 'none';
                    simpleLocation.style.display = 'none';
                    alternativeMaps.style.display = 'block';
                    button.innerHTML = '🗺️ خريطة جوجل';
                } else {
                    // Show Google Maps embed
                    alternativeMaps.style.display = 'none';
                    simpleLocation.style.display = 'none';
                    embeddedMap.style.display = 'block';
                    button.innerHTML = '🔄 خرائط بديلة';
                }
            };

            // Show simple location function
            window.showSimpleLocation = function() {
                const embeddedMap = document.getElementById('embedded-map');
                const alternativeMaps = document.getElementById('alternative-maps');
                const simpleLocation = document.getElementById('simple-location');
                const toggleButton = document.querySelector('button[onclick="toggleMapType()"]');
                const locationButton = document.querySelector('button[onclick="showSimpleLocation()"]');
                
                if (simpleLocation.style.display === 'none') {
                    // Show simple location
                    embeddedMap.style.display = 'none';
                    alternativeMaps.style.display = 'none';
                    simpleLocation.style.display = 'block';
                    locationButton.innerHTML = '🗺️ عرض الخريطة';
                    toggleButton.innerHTML = '🔄 خرائط بديلة';
                } else {
                    // Show Google Maps embed
                    simpleLocation.style.display = 'none';
                    alternativeMaps.style.display = 'none';
                    embeddedMap.style.display = 'block';
                    locationButton.innerHTML = '📍 عرض الموقع';
                    toggleButton.innerHTML = '🔄 خرائط بديلة';
                }
            };

            // Copy coordinates function
            window.copyCoordinates = function() {
                const lat = <?= json_encode(getSetting('contact_location_lat', '33.4384')) ?>;
                const lng = <?= json_encode(getSetting('contact_location_lng', '43.6793')) ?>;
                const coordinates = lat + ', ' + lng;
                
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(coordinates).then(function() {
                        // Show success message
                        const button = document.querySelector('button[onclick="copyCoordinates()"]');
                        const originalText = button.innerHTML;
                        button.innerHTML = '✅ تم النسخ';
                        button.classList.remove('bg-green-100', 'text-green-700', 'hover:bg-green-200');
                        button.classList.add('bg-green-200', 'text-green-800');
                        
                        setTimeout(function() {
                            button.innerHTML = originalText;
                            button.classList.remove('bg-green-200', 'text-green-800');
                            button.classList.add('bg-green-100', 'text-green-700', 'hover:bg-green-200');
                        }, 2000);
                    });
                } else {
                    // Fallback for older browsers
                    const textArea = document.createElement('textarea');
                    textArea.value = coordinates;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    
                    alert('تم نسخ الإحداثيات: ' + coordinates);
                }
            };

            // Mobile menu functionality
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