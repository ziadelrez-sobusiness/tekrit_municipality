<?php
/**
 * Header مشترك لجميع صفحات الموقع العام
 * 
 * الاستخدام:
 * require_once 'includes/header.php';
 */

// جلب إعدادات الموقع إذا لم تكن موجودة
if (!function_exists('getSetting')) {
    function getSetting($key, $default = '') {
        global $db;
        if (!$db) {
            return $default;
        }
        try {
            $stmt = $db->prepare("SELECT setting_value FROM website_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            return $result ? $result['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

$site_title = isset($site_title) ? $site_title : getSetting('site_title', 'بلدية تكريت');
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Header -->
<header class="tekrit-header sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-20 lg:h-24">
            <!-- Logo and Title -->
            <div class="flex items-center flex-shrink-0">
                <a href="index.php" class="flex items-center">
                    <img 
                        src="assets/images/Tekrit_LOGO.png" 
                        alt="شعار بلدية تكريت" 
                        class="tekrit-logo ml-3 w-16 h-20 sm:w-20 sm:h-24 md:w-24 md:h-28 object-contain"
                    >
                    <div class="hidden sm:block">
                        <h1 class="text-lg md:text-xl font-bold text-gray-800 leading-tight"><?= htmlspecialchars($site_title) ?></h1>
                        <p class="text-xs md:text-sm text-gray-600">خدمات إلكترونية للمواطنين</p>
                    </div>
                </a>
            </div>

            <!-- Desktop Navigation -->
            <nav class="hidden lg:flex items-center space-x-6 space-x-reverse">
                <a href="index.php" class="text-gray-700 hover:text-blue-600 font-medium text-sm whitespace-nowrap transition <?= $current_page == 'index.php' ? 'text-blue-600 font-semibold' : '' ?>">الرئيسية</a>
                <div class="relative group">
                    <button class="text-gray-700 hover:text-blue-600 font-medium text-sm whitespace-nowrap flex items-center transition <?= in_array($current_page, ['citizen-dashboard.php', 'citizen-requests.php', 'citizen-complaints.php']) ? 'text-blue-600 font-semibold' : '' ?>">
                        المواطنين
                        <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                        <div class="py-1">
                            <a href="citizen-dashboard.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 <?= $current_page == 'citizen-dashboard.php' ? 'bg-blue-50 text-blue-600' : '' ?>">👤 حساب المواطنين</a>
                            <a href="citizen-requests.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 <?= $current_page == 'citizen-requests.php' ? 'bg-blue-50 text-blue-600' : '' ?>">📝 طلبات المواطنين</a>
                            <a href="citizen-complaints.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 <?= $current_page == 'citizen-complaints.php' ? 'bg-blue-50 text-blue-600' : '' ?>">📢 شكاوى المواطنين</a>
                        </div>
                    </div>
                </div>
                <a href="projects.php" class="text-gray-700 hover:text-blue-600 font-medium text-sm whitespace-nowrap transition <?= $current_page == 'projects.php' ? 'text-blue-600 font-semibold' : '' ?>">المشاريع</a>
                <a href="initiatives.php" class="text-gray-700 hover:text-blue-600 font-medium text-sm whitespace-nowrap transition <?= $current_page == 'initiatives.php' ? 'text-blue-600 font-semibold' : '' ?>">المبادرات</a>
                <a href="news.php" class="text-gray-700 hover:text-blue-600 font-medium text-sm whitespace-nowrap transition <?= $current_page == 'news.php' ? 'text-blue-600 font-semibold' : '' ?>">الأخبار</a>
                <a href="important-links.php" class="text-gray-700 hover:text-blue-600 font-medium text-sm whitespace-nowrap transition <?= $current_page == 'important-links.php' ? 'text-blue-600 font-semibold' : '' ?>">🔗 روابط مهمة</a>
                <div class="relative group">
                    <button class="text-gray-700 hover:text-blue-600 font-medium text-sm whitespace-nowrap flex items-center transition <?= in_array($current_page, ['council.php', 'committees.php']) ? 'text-blue-600 font-semibold' : '' ?>">
                        البلدية
                        <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                        <div class="py-1">
                            <a href="council.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 <?= $current_page == 'council.php' ? 'bg-blue-50 text-blue-600' : '' ?>">👥 المجلس البلدي</a>
                            <a href="committees.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 <?= $current_page == 'committees.php' ? 'bg-blue-50 text-blue-600' : '' ?>">📋 اللجان البلدية</a>
                        </div>
                    </div>
                </div>
                <a href="facilities-map.php" class="text-gray-700 hover:text-blue-600 font-medium text-sm whitespace-nowrap transition <?= $current_page == 'facilities-map.php' ? 'text-blue-600 font-semibold' : '' ?>">🗺️ المرافق</a>
                <a href="contact.php" class="text-gray-700 hover:text-blue-600 font-medium text-sm whitespace-nowrap transition <?= $current_page == 'contact.php' ? 'text-blue-600 font-semibold' : '' ?>">اتصل بنا</a>
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
                <a href="index.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium <?= $current_page == 'index.php' ? 'bg-blue-50 text-blue-600' : '' ?>">الرئيسية</a>
                
                <!-- Mobile Citizens Submenu -->
                <div class="space-y-1">
                    <button id="mobile-citizens-btn" class="w-full text-right px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium flex items-center justify-between <?= in_array($current_page, ['citizen-dashboard.php', 'citizen-requests.php', 'citizen-complaints.php']) ? 'bg-blue-50 text-blue-600' : '' ?>">
                        المواطنين
                        <svg class="h-4 w-4 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div id="mobile-citizens-menu" class="hidden pr-4 space-y-1">
                        <a href="citizen-dashboard.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-blue-600 hover:bg-gray-50 rounded-md <?= $current_page == 'citizen-dashboard.php' ? 'bg-blue-50 text-blue-600' : '' ?>">👤 حساب المواطنين</a>
                        <a href="citizen-requests.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-blue-600 hover:bg-gray-50 rounded-md <?= $current_page == 'citizen-requests.php' ? 'bg-blue-50 text-blue-600' : '' ?>">📝 طلبات المواطنين</a>
                        <a href="citizen-complaints.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-blue-600 hover:bg-gray-50 rounded-md <?= $current_page == 'citizen-complaints.php' ? 'bg-blue-50 text-blue-600' : '' ?>">📢 شكاوى المواطنين</a>
                    </div>
                </div>
                
                <a href="projects.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium <?= $current_page == 'projects.php' ? 'bg-blue-50 text-blue-600' : '' ?>">المشاريع</a>
                <a href="initiatives.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium <?= $current_page == 'initiatives.php' ? 'bg-blue-50 text-blue-600' : '' ?>">المبادرات</a>
                <a href="news.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium <?= $current_page == 'news.php' ? 'bg-blue-50 text-blue-600' : '' ?>">الأخبار</a>
                <a href="important-links.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium <?= $current_page == 'important-links.php' ? 'bg-blue-50 text-blue-600' : '' ?>">🔗 روابط مهمة</a>
                
                <!-- Mobile Municipality Submenu -->
                <div class="space-y-1">
                    <button id="mobile-municipality-btn" class="w-full text-right px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium flex items-center justify-between <?= in_array($current_page, ['council.php', 'committees.php']) ? 'bg-blue-50 text-blue-600' : '' ?>">
                        البلدية
                        <svg class="h-4 w-4 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div id="mobile-municipality-menu" class="hidden pr-4 space-y-1">
                        <a href="council.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-blue-600 hover:bg-gray-50 rounded-md <?= $current_page == 'council.php' ? 'bg-blue-50 text-blue-600' : '' ?>">👥 المجلس البلدي</a>
                        <a href="committees.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-blue-600 hover:bg-gray-50 rounded-md <?= $current_page == 'committees.php' ? 'bg-blue-50 text-blue-600' : '' ?>">📋 اللجان البلدية</a>
                    </div>
                </div>
                
                <a href="facilities-map.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium <?= $current_page == 'facilities-map.php' ? 'bg-blue-50 text-blue-600' : '' ?>">🗺️ خريطة المرافق</a>
                <a href="contact.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium <?= $current_page == 'contact.php' ? 'bg-blue-50 text-blue-600' : '' ?>">اتصل بنا</a>
                
                <!-- Mobile Login Buttons -->
                <div class="pt-4 border-t border-gray-200 space-y-3">
                    <a href="../login.php" class="block w-full text-center px-6 py-3 bg-gradient-to-r from-orange-500 to-orange-600 text-white rounded-lg font-bold hover:from-orange-600 hover:to-orange-700 transition duration-300 shadow-md">
                        🔐 دخول الموظفين
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

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
        }
    });
</script>

