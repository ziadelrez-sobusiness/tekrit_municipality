<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

// الصفحة الحالية والفلترة
$page = $_GET['page'] ?? 1;
$type_filter = $_GET['type'] ?? '';
$per_page = 12;
$offset = ($page - 1) * $per_page;

// بناء الاستعلام
$where_clause = "WHERE is_published = 1";
$params = [];

if (!empty($type_filter)) {
    $where_clause .= " AND news_type = ?";
    $params[] = $type_filter;
}

// جلب الأخبار
$news_query = "
    SELECT n.*, u.full_name as creator_name 
    FROM news_activities n 
    LEFT JOIN users u ON n.created_by = u.id 
    $where_clause 
    ORDER BY n.publish_date DESC, n.created_at DESC 
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($news_query);
$stmt->execute($params);
$news = $stmt->fetchAll();

// إجمالي عدد الأخبار للترقيم
$count_query = "SELECT COUNT(*) as total FROM news_activities n $where_clause";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_news = $count_stmt->fetch()['total'];
$total_pages = ceil($total_news / $per_page);

// جلب إعدادات الموقع
function getSetting($key, $default = '') {
    global $db;
    $stmt = $db->prepare("SELECT setting_value FROM website_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : $default;
}

$site_title = getSetting('site_title', 'بلدية تكريت');

// أنواع الأخبار
$news_types = ['رسمية', 'مناسبات محلية', 'أنشطة اجتماعية', 'إعلام رسمي'];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site_title) ?> - الأخبار والأنشطة</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/tekrit-theme.css" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .card-hover { transition: transform 0.3s ease; }
        .card-hover:hover { transform: translateY(-5px); }
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
                    <a href="#" class="text-blue-600 font-medium">الأخبار</a>
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
                    <a href="citizen-requests.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium">طلبات المواطنين</a>
                    <a href="projects.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium">المشاريع</a>
                    <a href="initiatives.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium">المبادرات</a>
                    <a href="#" class="block px-3 py-2 text-blue-600 bg-blue-50 rounded-md font-medium">الأخبار</a>
                    
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

    <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">📰 الأخبار والأنشطة</h1>
            <p class="text-xl text-gray-600">
                آخر أخبار وأنشطة بلدية تكريت
            </p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap gap-2">
                    <a href="?" class="px-4 py-2 rounded-lg font-medium transition-colors <?= empty($type_filter) ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                        جميع الأخبار
                    </a>
                    <?php foreach ($news_types as $type): ?>
                        <a href="?type=<?= urlencode($type) ?>" 
                           class="px-4 py-2 rounded-lg font-medium transition-colors <?= $type_filter == $type ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                            <?= $type ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="text-sm text-gray-600">
                    إجمالي: <?= $total_news ?> خبر
                </div>
            </div>
        </div>

        <!-- News Grid -->
        <?php if (!empty($news)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
                <?php foreach ($news as $item): ?>
                    <article class="card-hover bg-white rounded-lg shadow-md overflow-hidden">
                        <?php if ($item['featured_image']): ?>
                            <img src="../uploads/news/<?= htmlspecialchars($item['featured_image']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-full h-48 object-cover">
                        <?php else: ?>
                            <div class="w-full h-48 bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                                <span class="text-white text-6xl">
                                    <?php 
                                        switch($item['news_type']) {
                                            case 'رسمية': echo '📋'; break;
                                            case 'مناسبات محلية': echo '🎉'; break;
                                            case 'أنشطة اجتماعية': echo '🤝'; break;
                                            case 'إعلام رسمي': echo '📢'; break;
                                            default: echo '📰';
                                        }
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                    <?= $item['news_type'] ?>
                                </span>
                                <?php if ($item['is_featured']): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        ⭐ مميز
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <h3 class="text-lg font-semibold text-gray-900 mb-3 leading-tight">
                                <?= htmlspecialchars($item['title']) ?>
                            </h3>
                            
                            <p class="text-gray-600 text-sm mb-4 leading-relaxed">
                                <?= htmlspecialchars(substr($item['content'], 0, 150)) ?>...
                            </p>
                            
                            <div class="flex justify-between items-center text-sm text-gray-500 mb-4">
                                <span>📅 <?= date('Y/m/d', strtotime($item['publish_date'])) ?></span>
                                <span>👁️ <?= number_format($item['views_count']) ?> مشاهدة</span>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-gray-400">
                                    بواسطة: <?= htmlspecialchars($item['creator_name'] ?: 'الإدارة') ?>
                                </span>
                                <a href="news-detail.php?id=<?= $item['id'] ?>" 
                                   class="inline-flex items-center px-3 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 transition-colors">
                                    قراءة المزيد ←
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center">
                    <nav class="flex items-center space-x-2 space-x-reverse">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?><?= $type_filter ? '&type=' . urlencode($type_filter) : '' ?>" 
                               class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                ← السابق
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?><?= $type_filter ? '&type=' . urlencode($type_filter) : '' ?>" 
                               class="px-3 py-2 border rounded-md <?= $i == $page ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white border-gray-300 hover:bg-gray-50' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?><?= $type_filter ? '&type=' . urlencode($type_filter) : '' ?>" 
                               class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                التالي →
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- No News -->
            <div class="text-center py-12">
                <div class="text-6xl mb-4">📰</div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">لا توجد أخبار</h3>
                <p class="text-gray-600">لم يتم نشر أي أخبار بعد في هذا القسم</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <div class="flex items-center mb-4">
                        <div class="bg-indigo-600 text-white p-2 rounded-lg ml-3">🏛️</div>
                        <h3 class="text-lg font-bold"><?= htmlspecialchars($site_title) ?></h3>
                    </div>
                    <p class="text-gray-300">متابعة دائمة لآخر أخبار وأنشطة البلدية</p>
                </div>
                <div>
                    <h4 class="text-lg font-semibold mb-4">الأقسام</h4>
                    <ul class="space-y-2">
                        <li><a href="index.php" class="text-gray-300 hover:text-white">الرئيسية</a></li>
                        <li><a href="citizen-requests.php" class="text-gray-300 hover:text-white">طلبات المواطنين</a></li>
                        <li><a href="projects.php" class="text-gray-300 hover:text-white">المشاريع</a></li>
                        <li><a href="contact.php" class="text-gray-300 hover:text-white">اتصل بنا</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-lg font-semibold mb-4">أنواع الأخبار</h4>
                    <ul class="space-y-2">
                        <?php foreach ($news_types as $type): ?>
                            <li><a href="?type=<?= urlencode($type) ?>" class="text-gray-300 hover:text-white"><?= $type ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div>
                    <h4 class="text-lg font-semibold mb-4">تواصل معنا</h4>
                    <div class="space-y-2">
                        <p class="text-gray-300">📞 <?= htmlspecialchars(getSetting('contact_phone')) ?></p>
                        <p class="text-gray-300">✉️ <?= htmlspecialchars(getSetting('contact_email')) ?></p>
                    </div>
                </div>
            </div>
            <hr class="my-8 border-gray-700">
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
