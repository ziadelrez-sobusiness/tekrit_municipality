<?php
header('Content-Type: text/html; charset=utf-8');

// تحميل أنظمة الأمان (Security Headers و دوال مساعدة)
if (file_exists(__DIR__ . '/../includes/init_security.php')) {
    require_once __DIR__ . '/../includes/init_security.php';
}

require_once '../config/database.php';
require_once '../includes/currency_formatter.php';

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

// جلب إعدادات الموقع
function getSetting($key, $default = '') {
    global $db;
    $stmt = $db->prepare("SELECT setting_value FROM website_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : $default;
}

// جلب الأخبار المميزة
$featured_news = $db->query("
    SELECT * FROM news_activities 
    WHERE is_published = 1 AND is_featured = 1 
    ORDER BY publish_date DESC 
    LIMIT 3
")->fetchAll();

// جلب آخر الأخبار
$latest_news = $db->query("
    SELECT * FROM news_activities 
    WHERE is_published = 1 
    ORDER BY publish_date DESC 
    LIMIT 6
")->fetchAll();

// جلب المشاريع المميزة
$featured_projects = $db->query("
    SELECT * FROM projects 
    WHERE is_featured = 1 AND is_public = 1
    ORDER BY created_at DESC 
    LIMIT 3
")->fetchAll();

// جلب المبادرات النشطة مع الصور وعدد المتطوعين
$active_initiatives = $db->query("
    SELECT i.*, 
           i.main_image,
           (SELECT COUNT(*) FROM initiative_volunteers WHERE initiative_id = i.id AND status = 'مقبول') as registered_volunteers
    FROM youth_environmental_initiatives i
    WHERE i.is_active = 1
    ORDER BY i.created_at DESC 
    LIMIT 3
")->fetchAll();

// إحصائيات
$stats = [
    'total_projects' => $db->query("SELECT COUNT(*) as count FROM projects WHERE is_public = 1")->fetch()['count'],
    'completed_projects' => $db->query("SELECT COUNT(*) as count FROM projects WHERE is_public = 1 AND status = 'مكتمل'")->fetch()['count'],
    'total_requests' => $db->query("SELECT COUNT(*) as count FROM citizen_requests")->fetch()['count'],
    'completed_requests' => $db->query("SELECT COUNT(*) as count FROM citizen_requests WHERE status = 'مكتمل'")->fetch()['count']
];

$site_title = getSetting('site_title', 'بلدية تكريت');
$site_description = getSetting('site_description', 'الموقع الرسمي لبلدية تكريت');
$welcome_message = getSetting('welcome_message', 'أهلاً وسهلاً بكم في الموقع الرسمي لبلدية تكريت');
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site_title) ?> - الصفحة الرئيسية</title>
    <meta name="description" content="<?= htmlspecialchars($site_description) ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/tekrit-theme.css" rel="stylesheet">
    <link href="assets/css/loading-screen.css" rel="stylesheet">
    <link href="assets/css/footer-enhancements.css" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .card-hover { transition: transform 0.3s ease; }
        .card-hover:hover { transform: translateY(-5px); }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Loading Screen -->
    <div class="loading-overlay" id="loadingScreen">
        <div class="loading-container">
            <div class="city-icon">
                <div class="city-circle">
                    <div class="city-buildings">
                        <div class="building building-1"></div>
                        <div class="building building-2"></div>
                        <div class="building building-3"></div>
                        <div class="building building-4"></div>
                        <div class="building building-5"></div>
                    </div>
                </div>
            </div>
            
            <h1 class="loading-text">بلدية تكريت</h1>
            <p class="loading-subtext">جاري التحميل
                <span class="loading-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </span>
            </p>
            
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
        </div>
    </div>

    <!-- Header -->
    <header class="tekrit-header sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20 lg:h-24">
                <!-- Logo and Title -->
                <div class="flex items-center flex-shrink-0">
                    <img 
                        src="assets/images/Tekrit_LOGO.png" 
                        alt="شعار بلدية تكريت" 
                        class="tekrit-logo ml-3 w-16 h-20 sm:w-20 sm:h-24 md:w-24 md:h-28 object-contain"
                    >
                    <div class="hidden sm:block">
                        <h1 class="text-lg md:text-xl font-bold text-gray-800 leading-tight"><?= htmlspecialchars($site_title) ?></h1>
                        <p class="text-xs md:text-sm text-gray-600">خدمات إلكترونية للمواطنين</p>
                    </div>
                </div>

                <!-- Desktop Navigation -->
                <nav class="hidden lg:flex items-center space-x-6 space-x-reverse">
                    <a href="#" class="text-gray-700 hover:text-blue-600 font-medium text-sm whitespace-nowrap transition">الرئيسية</a>
                    <div class="relative group">
                        <button class="text-gray-700 hover:text-blue-600 font-medium text-sm whitespace-nowrap flex items-center transition">
                            المواطنين
                            <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                            <div class="py-1">
                                <a href="citizen-dashboard.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">👤 حساب المواطنين</a>
                                <a href="citizen-requests.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">📝 طلبات المواطنين</a>
                                <a href="citizen-complaints.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">📢 شكاوى المواطنين</a>
                            </div>
                        </div>
                    </div>
                    <a href="projects.php" class="text-gray-700 hover:text-blue-600 font-medium text-sm whitespace-nowrap transition">المشاريع</a>
                    <a href="initiatives.php" class="text-gray-700 hover:text-blue-600 font-medium text-sm whitespace-nowrap transition">المبادرات</a>
                    <a href="news.php" class="text-gray-700 hover:text-blue-600 font-medium text-sm whitespace-nowrap transition">الأخبار</a>
                    <a href="important-links.php" class="text-gray-700 hover:text-blue-600 font-medium text-sm whitespace-nowrap transition">🔗 روابط مهمة</a>
                    <div class="relative group">
                        <button class="text-gray-700 hover:text-blue-600 font-medium text-sm whitespace-nowrap flex items-center transition">
                            البلدية
                            <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    <a href="facilities-map.php" class="text-gray-700 hover:text-blue-600 font-medium text-sm whitespace-nowrap transition">🗺️ المرافق</a>
                    <a href="contact.php" class="text-gray-700 hover:text-blue-600 font-medium text-sm whitespace-nowrap transition">اتصل بنا</a>
                </nav>
                
                <!-- Desktop Login Buttons -->
                <div class="hidden lg:flex items-center space-x-3 space-x-reverse flex-shrink-0">
                    <a href="../login.php" class="px-4 py-2 bg-gradient-to-r from-orange-500 to-orange-600 text-white rounded-lg font-bold text-sm hover:from-orange-600 hover:to-orange-700 transition duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-0.5 flex items-center whitespace-nowrap">
                        <span class="ml-2">🔐</span>
                        الموظفين
                    </a>
                </div>

                <!-- Mobile menu button -->
                <div class="lg:hidden flex-shrink-0">
                    <button id="mobile-menu-btn" class="text-gray-700 hover:text-blue-600 focus:outline-none focus:text-blue-600 p-2">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile Navigation -->
            <div id="mobile-menu" class="lg:hidden hidden">
                <div class="px-2 pt-2 pb-3 space-y-1 bg-white border-t border-gray-200">
                    <a href="#" class="block px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium">الرئيسية</a>
                    
                    <!-- Mobile Citizens Submenu -->
                    <div class="space-y-1">
                        <button id="mobile-citizens-btn" class="w-full text-right px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium flex items-center justify-between">
                            المواطنين
                            <svg class="h-4 w-4 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div id="mobile-citizens-menu" class="hidden pr-4 space-y-1">
                            <a href="citizen-dashboard.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-blue-600 hover:bg-gray-50 rounded-md">👤 حساب المواطنين</a>
                            <a href="citizen-requests.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-blue-600 hover:bg-gray-50 rounded-md">📝 طلبات المواطنين</a>
                            <a href="citizen-complaints.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-blue-600 hover:bg-gray-50 rounded-md">📢 شكاوى المواطنين</a>
                        </div>
                    </div>
                    
                    <a href="projects.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium">المشاريع</a>
                    <a href="initiatives.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium">المبادرات</a>
                    <a href="news.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium">الأخبار</a>
                    <a href="important-links.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium">🔗 روابط مهمة</a>
                    
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
                    
                    <!-- Mobile Login Buttons -->
                    <div class="pt-4 border-t border-gray-200 space-y-3">
                        <a href="../login.php" class="block w-full text-center btn-primary-orange">
                            🔐 دخول الموظفين
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section with Landscape Image -->
    <section class="relative">
        <!-- Background Image -->
        <div class="relative h-96 md:h-[500px] lg:h-[600px] overflow-hidden">
            <img 
                src="assets/images/hero/tekrit-landscape.jpg" 
                alt="منظر طبيعي لمدينة تكريت" 
                class="w-full h-full object-cover"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
            >
            <!-- Fallback gradient if image fails to load -->
            <div class="w-full h-full bg-gradient-to-br from-blue-600 via-blue-700 to-green-600 hidden"></div>
            
            <!-- Overlay for better text readability -->
            <div class="absolute inset-0 bg-black bg-opacity-40"></div>
        </div>
        
        <!-- Content Overlay -->
        <div class="absolute inset-0 flex items-center justify-center">
            <div class="text-center text-white px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto">
                <h1 class="text-3xl md:text-5xl lg:text-6xl font-bold mb-4 md:mb-6 drop-shadow-lg">
                    <?= htmlspecialchars($welcome_message) ?>
                </h1>
                <p class="text-lg md:text-xl lg:text-2xl mb-8 md:mb-12 text-gray-100 drop-shadow-md">
                    موقعكم الرسمي للخدمات الإلكترونية والمعلومات البلدية
                </p>
                
                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row justify-center items-center space-y-4 sm:space-y-0 sm:space-x-6 sm:space-x-reverse">
                    <a href="citizen-requests.php" 
                       class="hero-button w-full sm:w-auto px-8 py-4 bg-white text-blue-600 rounded-lg font-bold text-lg hover:bg-gray-100 transition duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1 flex items-center justify-center">
                        <span class="ml-2">📝</span>
                        تقديم طلب جديد
                    </a>
                    <a href="track-request.php" 
                       class="hero-button w-full sm:w-auto px-8 py-4 bg-transparent border-3 border-white text-white rounded-lg font-bold text-lg hover:bg-white hover:text-blue-600 transition duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1 flex items-center justify-center">
                        <span class="ml-2">🔍</span>
                        متابعة الطلب
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Quick Access Buttons - Mobile Friendly -->
        <div class="absolute bottom-4 left-4 right-4 md:bottom-8 md:left-8 md:right-8">
            <div class="flex justify-center space-x-4 space-x-reverse">
                <a href="contact.php" 
                   class="quick-access-btn bg-white bg-opacity-90 text-blue-600 px-4 py-2 rounded-full font-medium text-sm hover:bg-opacity-100 transition duration-300 shadow-md flex items-center">
                    <span class="ml-1">📞</span>
                    اتصل بنا
                </a>
                <a href="news.php" 
                   class="quick-access-btn bg-white bg-opacity-90 text-blue-600 px-4 py-2 rounded-full font-medium text-sm hover:bg-opacity-100 transition duration-300 shadow-md flex items-center">
                    <span class="ml-1">📰</span>
                    الأخبار
                </a>
                <a href="projects.php" 
                   class="quick-access-btn bg-white bg-opacity-90 text-blue-600 px-4 py-2 rounded-full font-medium text-sm hover:bg-opacity-100 transition duration-300 shadow-md flex items-center">
                    <span class="ml-1">🏗️</span>
                    المشاريع
                </a>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="stat-card text-white rounded-lg p-6 text-center">
                    <div class="text-3xl font-bold"><?= $stats['total_projects'] ?></div>
                    <div class="mt-2">إجمالي المشاريع</div>
                </div>
                <div class="stat-card text-white rounded-lg p-6 text-center">
                    <div class="text-3xl font-bold"><?= $stats['completed_projects'] ?></div>
                    <div class="mt-2">مشاريع منجزة</div>
                </div>
                <div class="stat-card text-white rounded-lg p-6 text-center">
                    <div class="text-3xl font-bold"><?= $stats['total_requests'] ?></div>
                    <div class="mt-2">طلبات المواطنين</div>
                </div>
                <div class="stat-card text-white rounded-lg p-6 text-center">
                    <div class="text-3xl font-bold"><?= $stats['completed_requests'] ?></div>
                    <div class="mt-2">طلبات منجزة</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Services -->
    <section class="py-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-center text-gray-900 mb-12">الخدمات السريعة</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <a href="citizen-requests.php?type=إفادة سكن" class="card-hover bg-white rounded-lg p-6 shadow-md text-center">
                    <div class="text-4xl mb-4">🏠</div>
                    <h3 class="text-lg font-semibold mb-2">إفادة سكن</h3>
                    <p class="text-gray-600">احصل على إفادة سكن رسمية</p>
                </a>
                <a href="citizen-requests.php?type=بلاغ أعطال" class="card-hover bg-white rounded-lg p-6 shadow-md text-center">
                    <div class="text-4xl mb-4">⚠️</div>
                    <h3 class="text-lg font-semibold mb-2">بلاغ أعطال</h3>
                    <p class="text-gray-600">أبلغ عن الأعطال والمشاكل</p>
                </a>
                <a href="citizen-requests.php?type=استشارة هندسية" class="card-hover bg-white rounded-lg p-6 shadow-md text-center">
                    <div class="text-4xl mb-4">📐</div>
                    <h3 class="text-lg font-semibold mb-2">استشارة هندسية</h3>
                    <p class="text-gray-600">احصل على استشارة هندسية</p>
                </a>
                <a href="citizen-requests.php?type=شكوى" class="card-hover bg-white rounded-lg p-6 shadow-md text-center">
                    <div class="text-4xl mb-4">📢</div>
                    <h3 class="text-lg font-semibold mb-2">تقديم شكوى</h3>
                    <p class="text-gray-600">قدم شكواك أو اقتراحك</p>
                </a>
            </div>
        </div>
    </section>

    <!-- Featured News -->
    <?php if (!empty($latest_news)): ?>
    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900">آخر الأخبار والأنشطة</h2>
                <a href="news.php" class="text-indigo-600 hover:text-indigo-800 font-medium">عرض جميع الأخبار ←</a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach (array_slice($latest_news, 0, 3) as $news): ?>
                    <article class="card-hover bg-white rounded-lg shadow-md overflow-hidden">
                        <?php if ($news['featured_image']): ?>
                            <?php 
                                $imagePath = $news['featured_image'];
                                // إصلاح المسار - التحقق من وجود الصورة في عدة مواقع محتملة
                                if (strpos($imagePath, 'http') === 0) {
                                    // URL كامل - لا تغيير
                                } elseif (strpos($imagePath, 'assets/') === 0 || strpos($imagePath, 'uploads/') === 0) {
                                    $imagePath = '../' . $imagePath;
                                } elseif (strpos($imagePath, '../') !== 0 && strpos($imagePath, '/') !== 0) {
                                    // إذا كان اسم الملف فقط (مثل news_featured_xxx.png)
                                    // جرب في uploads/ أولاً
                                    $possiblePaths = [
                                        '../uploads/news/' . $imagePath,
                                        '../assets/images/news/' . $imagePath,
                                        '../' . $imagePath
                                    ];
                                    // استخدام أول مسار موجود (سيتم التحقق في onerror)
                                    $imagePath = $possiblePaths[0];
                                }
                            ?>
                            <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($news['title']) ?>" class="w-full h-48 object-cover" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'400\' height=\'200\'%3E%3Crect fill=\'%23ddd\' width=\'400\' height=\'200\'/%3E%3Ctext fill=\'%23999\' font-family=\'sans-serif\' font-size=\'20\' dy=\'10.5\' font-weight=\'bold\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\'%3E%F0%9F%93%B0%3C/text%3E%3C/svg%3E'; this.nextElementSibling.style.display='flex';">
                        <?php else: ?>
                            <div class="w-full h-48 bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                                <span class="text-white text-4xl">📰</span>
                            </div>
                        <?php endif; ?>
                        <div class="p-6">
                            <div class="text-sm text-indigo-600 mb-2"><?= $news['news_type'] ?></div>
                            <h3 class="text-lg font-semibold mb-2"><?= htmlspecialchars($news['title']) ?></h3>
                            <p class="text-gray-600 mb-4"><?= htmlspecialchars(substr($news['content'], 0, 120)) ?>...</p>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-500"><?= date('Y/m/d', strtotime($news['publish_date'])) ?></span>
                                <a href="news-detail.php?id=<?= $news['id'] ?>" class="text-indigo-600 hover:text-indigo-800">قراءة المزيد</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Featured Projects -->
    <?php if (!empty($featured_projects)): ?>
    <section class="py-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900">المشاريع المميزة</h2>
                <a href="projects.php" class="text-indigo-600 hover:text-indigo-800 font-medium">عرض جميع المشاريع ←</a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($featured_projects as $project): ?>
                    <div class="card-hover bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="p-6">
                            <div class="flex justify-between items-start mb-4">
                                <h3 class="text-lg font-semibold"><?= htmlspecialchars($project['project_name']) ?></h3>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php 
                                        switch($project['status']) {
                                            case 'مخطط': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'قيد التنفيذ': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'مكتمل': echo 'bg-green-100 text-green-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                    ?>">
                                    <?= $project['status'] ?>
                                </span>
                            </div>
                            <p class="text-gray-600 mb-4"><?= htmlspecialchars(substr($project['description'], 0, 100)) ?>...</p>
                            <div class="space-y-2 mb-4">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-500">📍 الموقع:</span>
                                    <span class="text-sm"><?= htmlspecialchars($project['location']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-500">💰 التكلفة:</span>
                                    <span class="text-sm"><?= formatProjectCost($project, $db) ?></span>
                                </div>
                                <?php if ($project['progress_percentage'] > 0): ?>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-600 h-2 rounded-full" style="width: <?= $project['progress_percentage'] ?>%"></div>
                                </div>
                                <span class="text-xs text-gray-600"><?= $project['progress_percentage'] ?>% مكتمل</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex justify-between items-center">
                                <a href="project-detail.php?id=<?= $project['id'] ?>" class="text-indigo-600 hover:text-indigo-800">تفاصيل المشروع</a>
                                <?php if ($project['allow_public_contributions']): ?>
                                    <button class="px-3 py-1 bg-green-600 text-white rounded-md text-sm hover:bg-green-700">ساهم</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Active Initiatives -->
    <?php if (!empty($active_initiatives)): ?>
    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900">المبادرات النشطة</h2>
                <a href="initiatives.php" class="text-indigo-600 hover:text-indigo-800 font-medium">عرض جميع المبادرات ←</a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($active_initiatives as $initiative): ?>
                    <div class="card-hover bg-white rounded-lg shadow-md overflow-hidden border-l-4 border-green-500">
                        <?php if ($initiative['main_image']): ?>
                            <?php 
                                $imagePath = $initiative['main_image'];
                                // إصلاح المسار - التحقق من وجود الصورة في عدة مواقع محتملة
                                if (strpos($imagePath, 'http') === 0) {
                                    // URL كامل - لا تغيير
                                } elseif (strpos($imagePath, 'assets/') === 0 || strpos($imagePath, 'uploads/') === 0) {
                                    $imagePath = '../' . $imagePath;
                                } elseif (strpos($imagePath, '../') !== 0 && strpos($imagePath, '/') !== 0) {
                                    // إذا كان اسم الملف فقط (مثل initiative_main_xxx.png)
                                    // جرب في uploads/ أولاً
                                    $possiblePaths = [
                                        '../uploads/initiatives/' . $imagePath,
                                        '../assets/images/initiatives/' . $imagePath,
                                        '../' . $imagePath
                                    ];
                                    // استخدام أول مسار موجود (سيتم التحقق في onerror)
                                    $imagePath = $possiblePaths[0];
                                }
                            ?>
                            <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($initiative['initiative_name']) ?>" class="w-full h-48 object-cover" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'400\' height=\'200\'%3E%3Crect fill=\'%23ddd\' width=\'400\' height=\'200\'/%3E%3Ctext fill=\'%23999\' font-family=\'sans-serif\' font-size=\'20\' dy=\'10.5\' font-weight=\'bold\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\'%3E%F0%9F%8C%B1%3C/text%3E%3C/svg%3E'; this.nextElementSibling.style.display='flex';">
                        <?php else: ?>
                            <div class="w-full h-48 bg-gradient-to-br from-green-500 to-blue-600 flex items-center justify-center">
                                <span class="text-white text-4xl">🌱</span>
                            </div>
                        <?php endif; ?>
                        <div class="p-6">
                            <div class="flex justify-between items-start mb-4">
                                <h3 class="text-lg font-semibold"><?= htmlspecialchars($initiative['initiative_name']) ?></h3>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                    <?= $initiative['initiative_type'] ?>
                                </span>
                            </div>
                            <p class="text-gray-600 mb-4"><?= htmlspecialchars(substr($initiative['initiative_description'], 0, 100)) ?>...</p>
                            <div class="space-y-2 mb-4">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-500">👥 المطلوب:</span>
                                    <span class="text-sm"><?= $initiative['max_volunteers'] ?> متطوع</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-500">✅ المسجلون:</span>
                                    <span class="text-sm"><?= $initiative['registered_volunteers'] ?> متطوع</span>
                                </div>
                                <?php if ($initiative['max_volunteers'] > 0): ?>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-600 h-2 rounded-full" style="width: <?= ($initiative['registered_volunteers'] / $initiative['max_volunteers']) * 100 ?>%"></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex justify-between items-center">
                                <a href="initiative-detail.php?id=<?= $initiative['id'] ?>" class="text-indigo-600 hover:text-indigo-800">تفاصيل المبادرة</a>
                                <button class="px-3 py-1 bg-green-600 text-white rounded-md text-sm hover:bg-green-700">انضم إلينا</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php require_once 'includes/footer.php'; ?>

    <!-- Mobile Menu JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobile-menu-btn');
            const mobileMenu = document.getElementById('mobile-menu');
            const municipalityBtn = document.getElementById('mobile-municipality-btn');
            const municipalityMenu = document.getElementById('mobile-municipality-menu');

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

            // Toggle citizens submenu in mobile
            const citizensBtn = document.getElementById('mobile-citizens-btn');
            const citizensMenu = document.getElementById('mobile-citizens-menu');
            if (citizensBtn && citizensMenu) {
                citizensBtn.addEventListener('click', function() {
                    citizensMenu.classList.toggle('hidden');
                    
                    // Rotate arrow
                    const arrow = citizensBtn.querySelector('svg');
                    arrow.classList.toggle('rotate-180');
                });
            }

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
        });
    </script>

    <!-- Loading Screen JavaScript -->
    <script>
        // إدارة شاشة التحميل
        class LoadingScreen {
            constructor() {
                this.loadingOverlay = document.getElementById('loadingScreen');
                this.minimumLoadTime = 2500; // حد أدنى 2.5 ثانية
                this.startTime = Date.now();
                this.isComplete = false;
                
                this.init();
            }

            init() {
                // إخفاء محتوى الصفحة مؤقتاً
                document.body.style.overflow = 'hidden';
                
                // انتظار تحميل كامل للصفحة
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', () => {
                        this.onDOMReady();
                    });
                } else {
                    this.onDOMReady();
                }
                
                // انتظار تحميل جميع الموارد
                window.addEventListener('load', () => {
                    this.onPageFullyLoaded();
                });
            }

            onDOMReady() {
                console.log('DOM محمل بالكامل');
            }

            onPageFullyLoaded() {
                // حساب الوقت المنقضي
                const elapsedTime = Date.now() - this.startTime;
                const remainingTime = Math.max(0, this.minimumLoadTime - elapsedTime);
                
                // انتظار الحد الأدنى للوقت ثم إخفاء شاشة التحميل
                setTimeout(() => {
                    this.hideLoadingScreen();
                }, remainingTime);
            }

            hideLoadingScreen() {
                if (this.loadingOverlay && !this.isComplete) {
                    this.isComplete = true;
                    
                    // إضافة كلاس الإخفاء
                    this.loadingOverlay.classList.add('fade-out');
                    
                    // إعادة تفعيل التمرير
                    document.body.style.overflow = '';
                    
                    // إزالة العنصر من DOM بعد انتهاء الأنيميشن
                    setTimeout(() => {
                        if (this.loadingOverlay && this.loadingOverlay.parentNode) {
                            this.loadingOverlay.remove();
                        }
                    }, 500);
                    
                    // تشغيل أنيميشن ظهور المحتوى
                    this.animatePageContent();
                }
            }

            animatePageContent() {
                // إضافة أنيميشن ظهور للمحتوى
                const elementsToAnimate = document.querySelectorAll('.card-hover, .hero-section, section');
                
                elementsToAnimate.forEach((element, index) => {
                    element.style.opacity = '0';
                    element.style.transform = 'translateY(20px)';
                    element.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
                    
                    setTimeout(() => {
                        element.style.opacity = '1';
                        element.style.transform = 'translateY(0)';
                    }, 100 + (index * 50));
                });
            }
        }

        // تشغيل شاشة التحميل
        const loadingScreen = new LoadingScreen();
    </script>
</body>
</html> 
