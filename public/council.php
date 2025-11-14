<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

// جلب إعدادات الموقع
if (!function_exists('getSetting')) {
    function getSetting($key, $default = '', $db = null) {
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

$site_title = getSetting('site_title', 'بلدية تكريت', $db);

// جلب أعضاء المجلس البلدي
$council_query = $db->query("
    SELECT * FROM council_members 
    WHERE is_active = 1 
    ORDER BY display_order, 
        CASE position 
            WHEN 'رئيس البلدية' THEN 1 
            WHEN 'نائب رئيس البلدية' THEN 2 
            WHEN 'أمين المال' THEN 3 
            WHEN 'سكرتير المجلس' THEN 4 
            WHEN 'عضو مجلس' THEN 5 
            ELSE 6 
        END, full_name
");
$council_members = $council_query->fetchAll();

function getPositionIcon($position) {
    switch($position) {
        case 'رئيس البلدية': return '👑';
        case 'نائب رئيس البلدية': return '🎖️';
        case 'أمين المال': return '💰';
        case 'سكرتير المجلس': return '📝';
        case 'عضو مجلس': return '👤';
        default: return '👥';
    }
}

function getProfilePicture($member) {
    // تحقق من وجود صورة مرفوعة
     if (!empty($member['profile_picture']) && trim($member['profile_picture']) !== '') {
        $image_path = $member['profile_picture'];
        
        // تنظيف المسار
        $image_path = str_replace(['../', './'], '', $image_path);
        $image_path = ltrim($image_path, '/');
        
        // المسار الكامل للتحقق
        $full_path = '../' . $image_path;
        
        // سجل للمسار لأغراض التشخيص
        error_log("Checking image path: " . $full_path);
        
        if (file_exists($full_path)) {
            error_log("Image found: " . $image_path);
            return $image_path;
        } else {
            error_log("Image not found: " . $full_path);
        }
    }
    
    // في حالة عدم وجود صورة، استخدم avatar تلقائي
    $name = $member['full_name'];
    $is_female = false;
    
    // تحديد الجنس بناءً على الاسم
    $female_names = ['فاطمة', 'مريم', 'عائشة', 'زينب', 'ليلى', 'نور', 'هدى', 'أسماء', 'خديجة', 'سارة'];
    foreach ($female_names as $female_name) {
        if (strpos($name, $female_name) !== false) {
            $is_female = true;
            break;
        }
    }
    
    $bg_color = $is_female ? 'ec4899' : '3b82f6';
    return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=' . $bg_color . '&color=fff&size=200&font-size=0.6';
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المجلس البلدي - <?= htmlspecialchars($site_title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/tekrit-theme.css" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .member-card { transition: all 0.3s ease; }
        .member-card:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .member-detail { display: none; }
        .member-detail.active { display: block; }
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
                        <button class="text-blue-600 font-medium flex items-center">
                            البلدية
                            <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                            <div class="py-1">
                                <a href="#" class="block px-4 py-2 text-sm text-blue-600 bg-blue-50 hover:bg-blue-100">👥 المجلس البلدي</a>
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
                    <a href="news.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium">الأخبار</a>
                    
                    <!-- Mobile Municipality Submenu -->
                    <div class="space-y-1">
                        <button id="mobile-municipality-btn" class="w-full text-right px-3 py-2 text-blue-600 bg-blue-50 rounded-md font-medium flex items-center justify-between">
                            البلدية
                            <svg class="h-4 w-4 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div id="mobile-municipality-menu" class="pr-4 space-y-1">
                            <a href="#" class="block px-3 py-2 text-sm text-blue-600 bg-blue-100 rounded-md">👥 المجلس البلدي</a>
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

    <main class="container mx-auto px-4 py-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-800 mb-4">👥 أعضاء المجلس البلدي</h2>
            <p class="text-gray-600 max-w-2xl mx-auto text-lg">
                تعرف على أعضاء المجلس البلدي ومناصبهم واختصاصاتهم وخبراتهم
            </p>
            <div class="w-24 h-1 bg-blue-500 mx-auto mt-6"></div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($council_members as $member): ?>
                <div class="member-card bg-white rounded-2xl shadow-lg overflow-hidden">
                    <div class="relative bg-gradient-to-br from-blue-500 to-purple-600 px-6 pt-6 pb-20">
                        <span class="px-3 py-1 bg-white/20 rounded-full text-white text-xs font-medium">
                            <?= getPositionIcon($member['position']) ?> <?= $member['position'] ?>
                        </span>
                        
                        <div class="absolute left-1/2 transform -translate-x-1/2 -bottom-16">
                            <img 
								src="<?= '../' . getProfilePicture($member) ?>" 
								alt="<?= htmlspecialchars($member['full_name']) ?>"
								class="w-32 h-32 rounded-full border-4 border-white object-cover"
								onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($member['full_name']) ?>&background=3b82f6&color=fff&size=200';"
							>
                        </div>
                    </div>

                    <div class="px-6 pt-20 pb-6">
                        <div class="text-center mb-4">
                            <h3 class="text-xl font-bold text-gray-800 mb-1">
                                <?= htmlspecialchars($member['full_name']) ?>
                            </h3>
                            <p class="text-blue-600 font-medium">
                                <?= htmlspecialchars($member['specialization']) ?>
                            </p>
                        </div>

                        <?php if ($member['biography']): ?>
                            <p class="text-gray-600 text-sm mb-4">
                                <?= htmlspecialchars(mb_substr($member['biography'], 0, 100)) ?>...
                            </p>
                        <?php endif; ?>

                        <button 
                            onclick="toggleMemberDetails(<?= $member['id'] ?>)"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg font-medium transition-colors"
                        >
                            عرض التفاصيل
                        </button>

                        <div id="member-details-<?= $member['id'] ?>" class="member-detail mt-6 pt-6 border-t">
                            <?php if ($member['biography']): ?>
                                <div class="mb-4">
                                    <h4 class="font-semibold mb-2 text-gray-800">📋 نبذة تعريفية</h4>
                                    <p class="text-gray-700 text-sm"><?= nl2br(htmlspecialchars($member['biography'])) ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($member['education']): ?>
                                <div class="mb-4">
                                    <h4 class="font-semibold mb-2 text-gray-800">🎓 المؤهلات العلمية</h4>
                                    <p class="text-gray-700 text-sm"><?= nl2br(htmlspecialchars($member['education'])) ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($member['experience']): ?>
                                <div class="mb-4">
                                    <h4 class="font-semibold mb-2 text-gray-800">💼 الخبرة العملية</h4>
                                    <p class="text-gray-700 text-sm"><?= nl2br(htmlspecialchars($member['experience'])) ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($member['phone'] || $member['email']): ?>
                                <div class="mb-4">
                                    <h4 class="font-semibold mb-2 text-gray-800">📞 معلومات الاتصال</h4>
                                    <?php if ($member['phone']): ?>
                                        <p class="text-gray-700 text-sm">📱 الهاتف: <?= htmlspecialchars($member['phone']) ?></p>
                                    <?php endif; ?>
                                    <?php if ($member['email']): ?>
                                        <p class="text-gray-700 text-sm">📧 البريد: <?= htmlspecialchars($member['email']) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($council_members)): ?>
            <div class="text-center py-16">
                <div class="text-6xl mb-4">👥</div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">لا توجد أعضاء مجلس متاحون حالياً</h3>
                <p class="text-gray-500">سيتم إضافة معلومات أعضاء المجلس البلدي قريباً</p>
            </div>
        <?php endif; ?>
    </main>

    <footer class="bg-gray-800 text-white py-8 mt-16">
        <div class="container mx-auto px-4">
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
    </footer>

    <script>
        function toggleMemberDetails(memberId) {
            const details = document.getElementById(`member-details-${memberId}`);
            const button = event.target;
            
            if (details.classList.contains('active')) {
                details.classList.remove('active');
                button.textContent = 'عرض التفاصيل';
            } else {
                details.classList.add('active');
                button.textContent = 'إخفاء التفاصيل';
            }
        }

        // Mobile menu functionality
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobile-menu-btn');
            const mobileMenu = document.getElementById('mobile-menu');

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

                // Close mobile menu when clicking outside
                document.addEventListener('click', function(event) {
                    if (!mobileMenuBtn.contains(event.target) && !mobileMenu.contains(event.target)) {
                        mobileMenu.classList.add('hidden');
                        const icon = mobileMenuBtn.querySelector('svg');
                        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />';
                    }
                });

                // Close mobile menu on window resize
                window.addEventListener('resize', function() {
                    if (window.innerWidth >= 768) {
                        mobileMenu.classList.add('hidden');
                        const icon = mobileMenuBtn.querySelector('svg');
                        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />';
                    }
                });
            }
        });

        // إضافة console للتشخيص
        console.log('✅ تم تحميل صفحة المجلس البلدي العامة بنجاح');
        
        // التحقق من تحميل الصور
        document.addEventListener('DOMContentLoaded', function() {
            const images = document.querySelectorAll('img[alt]');
            images.forEach(img => {
                img.addEventListener('load', function() {
                    console.log('✅ تم تحميل صورة:', this.alt);
                });
                img.addEventListener('error', function() {
                    console.log('❌ فشل تحميل صورة:', this.alt, 'المسار:', this.src);
                });
            });
        });
    </script>
</body>
</html> 
