<?php
require_once '../config/database.php';

// إنشاء الاتصال بقاعدة البيانات
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
$site_description = getSetting('site_description', 'خدمات البلدية الإلكترونية', $db);

// جلب اللجان البلدية النشطة
$committees_query = $db->query("
    SELECT c.*, 
           d.department_name,
           ch.full_name as chairman_name,
           s.full_name as secretary_name,
           COUNT(cm.id) as members_count
    FROM municipal_committees c 
    LEFT JOIN departments d ON c.department_id = d.id 
    LEFT JOIN users ch ON c.chairman_id = ch.id
    LEFT JOIN users s ON c.secretary_id = s.id
    LEFT JOIN committee_members cm ON c.id = cm.committee_id AND cm.is_active = 1
    WHERE c.is_active = 1
    GROUP BY c.id 
    ORDER BY c.committee_type, c.committee_name
");
$committees = $committees_query->fetchAll();

// دالة للحصول على أعضاء لجنة محددة
function getCommitteeMembers($committee_id, $db) {
    $stmt = $db->prepare("
        SELECT cm.*, u.full_name, d.department_name, u.position
        FROM committee_members cm 
        JOIN users u ON cm.user_id = u.id 
        LEFT JOIN departments d ON u.department_id = d.id 
        WHERE cm.committee_id = ? AND cm.is_active = 1
        ORDER BY 
            CASE cm.member_role 
                WHEN 'رئيس' THEN 1 
                WHEN 'نائب الرئيس' THEN 2 
                WHEN 'سكرتير' THEN 3 
                WHEN 'مقرر' THEN 4 
                WHEN 'عضو' THEN 5 
                ELSE 6 
            END, u.full_name
    ");
    $stmt->execute([$committee_id]);
    return $stmt->fetchAll();
}

// دالة لتحديد أيقونة اللجنة
function getCommitteeIcon($type) {
    switch($type) {
        case 'دائمة': return '🏛️';
        case 'مؤقتة': return '⏰';
        case 'استشارية': return '💡';
        case 'تنفيذية': return '⚡';
        default: return '📋';
    }
}

// دالة لتحديد لون نوع اللجنة
function getCommitteeTypeColor($type) {
    switch($type) {
        case 'دائمة': return 'bg-blue-100 text-blue-800';
        case 'مؤقتة': return 'bg-yellow-100 text-yellow-800';
        case 'استشارية': return 'bg-green-100 text-green-800';
        case 'تنفيذية': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اللجان البلدية - <?= htmlspecialchars($site_title) ?></title>
    <meta name="description" content="اللجان البلدية وأعضاؤها ومهامها - <?= htmlspecialchars($site_description) ?>">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Cairo Font -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/tekrit-theme.css" rel="stylesheet">
    
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .committee-card {
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        .committee-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .member-card {
            transition: all 0.2s ease;
        }
        .member-card:hover {
            transform: scale(1.02);
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .committee-detail {
            display: none;
        }
        .committee-detail.active {
            display: block;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="tekrit-header sticky top-0 z-50">
        <div class="container mx-auto px-0">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                    <img src="assets/images/Tekrit_LOGO.png" alt="شعار بلدية تكريت" class="tekrit-logo ml-4 w-20 h-24 sm:w-24 sm:h-28 md:w-28 md:h-32 object-contain border-0"
				  >
                    <div>
                        <h1 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($site_title) ?></h1>
                        <p class="text-sm text-gray-600 hidden sm:block"">اللجان البلدية</p>
                    </div>
                </div>
				
				 <!-- Desktop Navigation -->
                <nav class="hidden lg:flex space-x-8 space-x-reverse">
                    <a href="index.php" class="text-gray-700 hover:text-blue-600 font-medium">الرئيسية</a>
                    <a href="citizen-requests.php" class="text-blue-600 font-medium">طلبات المواطنين</a>
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
                    <a href="citizen-requests.php" class="block px-3 py-2 text-blue-600 bg-blue-50 rounded-md font-medium">طلبات المواطنين</a>
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

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <!-- Page Header -->
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-800 mb-4">🏛️ اللجان البلدية</h2>
            <p class="text-gray-600 max-w-2xl mx-auto text-lg">
                تعرف على اللجان البلدية المختلفة وأعضائها والمهام والمسؤوليات الموكلة إليها
            </p>
            <div class="w-24 h-1 bg-blue-500 mx-auto mt-6"></div>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-12">
            <div class="bg-white rounded-xl p-6 shadow-lg text-center">
                <div class="text-3xl mb-2">📊</div>
                <div class="text-2xl font-bold text-blue-600"><?= count($committees) ?></div>
                <div class="text-gray-600">إجمالي اللجان</div>
            </div>
            <div class="bg-white rounded-xl p-6 shadow-lg text-center">
                <div class="text-3xl mb-2">🏛️</div>
                <div class="text-2xl font-bold text-green-600">
                    <?= count(array_filter($committees, fn($c) => $c['committee_type'] === 'دائمة')) ?>
                </div>
                <div class="text-gray-600">لجان دائمة</div>
            </div>
            <div class="bg-white rounded-xl p-6 shadow-lg text-center">
                <div class="text-3xl mb-2">⏰</div>
                <div class="text-2xl font-bold text-yellow-600">
                    <?= count(array_filter($committees, fn($c) => $c['committee_type'] === 'مؤقتة')) ?>
                </div>
                <div class="text-gray-600">لجان مؤقتة</div>
            </div>
            <div class="bg-white rounded-xl p-6 shadow-lg text-center">
                <div class="text-3xl mb-2">👥</div>
                <div class="text-2xl font-bold text-purple-600">
                    <?= array_sum(array_column($committees, 'members_count')) ?>
                </div>
                <div class="text-gray-600">إجمالي الأعضاء</div>
            </div>
        </div>

        <!-- Committees Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <?php foreach ($committees as $committee): ?>
                <div class="committee-card bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                    <!-- Committee Header -->
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center">
                            <span class="text-4xl ml-4"><?= getCommitteeIcon($committee['committee_type']) ?></span>
                            <div>
                                <h3 class="text-xl font-bold text-gray-800 mb-1">
                                    <?= htmlspecialchars($committee['committee_name']) ?>
                                </h3>
                                <span class="px-3 py-1 text-xs font-semibold rounded-full <?= getCommitteeTypeColor($committee['committee_type']) ?>">
                                    <?= $committee['committee_type'] ?>
                                </span>
                            </div>
                        </div>
                        <button 
                            onclick="toggleCommitteeDetails(<?= $committee['id'] ?>)"
                            class="text-blue-600 hover:text-blue-800 text-sm font-medium transition-colors"
                        >
                            عرض التفاصيل
                        </button>
                    </div>

                    <!-- Committee Description -->
                    <p class="text-gray-600 mb-4 leading-relaxed">
                        <?= htmlspecialchars($committee['committee_description']) ?>
                    </p>

                    <!-- Committee Info -->
                    <div class="grid grid-cols-2 gap-4 mb-4 text-sm">
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <div class="text-gray-500 mb-1">رئيس اللجنة</div>
                            <div class="font-semibold text-gray-800">
                                <?= htmlspecialchars($committee['chairman_name'] ?: 'غير محدد') ?>
                            </div>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <div class="text-gray-500 mb-1">عدد الأعضاء</div>
                            <div class="font-semibold text-gray-800">
                                👥 <?= $committee['members_count'] ?> عضو
                            </div>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <div class="text-gray-500 mb-1">تكرار الاجتماعات</div>
                            <div class="font-semibold text-gray-800">
                                📅 <?= $committee['meeting_frequency'] ?>
                            </div>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <div class="text-gray-500 mb-1">القسم المختص</div>
                            <div class="font-semibold text-gray-800">
                                🏢 <?= htmlspecialchars($committee['department_name'] ?: 'عام') ?>
                            </div>
                        </div>
                    </div>

                    <!-- Committee Details (Hidden by default) -->
                    <div id="committee-details-<?= $committee['id'] ?>" class="committee-detail">
                        <div class="border-t pt-4 mt-4">
                            <!-- Responsibilities -->
                            <?php if ($committee['responsibilities']): ?>
                                <div class="mb-6">
                                    <h4 class="text-lg font-semibold text-gray-800 mb-3 flex items-center">
                                        📋 المهام والمسؤوليات
                                    </h4>
                                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded">
                                        <p class="text-gray-700 leading-relaxed">
                                            <?= nl2br(htmlspecialchars($committee['responsibilities'])) ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Committee Members -->
                            <div>
                                <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                    👥 أعضاء اللجنة
                                </h4>
                                
                                <?php 
                                $members = getCommitteeMembers($committee['id'], $db);
                                if ($members): 
                                ?>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <?php foreach ($members as $member): ?>
                                            <div class="member-card bg-gradient-to-r from-blue-50 to-purple-50 p-4 rounded-lg border border-blue-100">
                                                <div class="flex items-center">
                                                    <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold text-lg ml-3">
                                                        <?= mb_substr($member['full_name'], 0, 1) ?>
                                                    </div>
                                                    <div class="flex-1">
                                                        <h5 class="font-semibold text-gray-800">
                                                            <?= htmlspecialchars($member['full_name']) ?>
                                                        </h5>
                                                        <p class="text-sm text-blue-600 font-medium">
                                                            <?= $member['member_role'] ?>
                                                        </p>
                                                        <?php if ($member['department_name']): ?>
                                                            <p class="text-xs text-gray-500">
                                                                <?= htmlspecialchars($member['department_name']) ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <?php if ($member['join_date']): ?>
                                                    <div class="mt-2 text-xs text-gray-500 text-left">
                                                        انضم في: <?= date('Y/m/d', strtotime($member['join_date'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-8 text-gray-500">
                                        <div class="text-4xl mb-2">👥</div>
                                        <p>لا توجد معلومات عن أعضاء هذه اللجنة حالياً</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($committees)): ?>
            <div class="text-center py-16">
                <div class="text-6xl mb-4">🏛️</div>
                <h3 class="text-xl font-semibold text-gray-600 mb-2">لا توجد لجان متاحة حالياً</h3>
                <p class="text-gray-500">سيتم إضافة معلومات اللجان البلدية قريباً</p>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-16">
        <div class="container mx-auto px-4">
            <div class="mb-4 text-center">
                <h3 class="text-xl font-bold mb-2"><?= htmlspecialchars($site_title) ?></h3>
                <p class="text-gray-300"><?= htmlspecialchars($site_description) ?></p>
            </div>
            
            <div class="border-t border-gray-700 pt-4">
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
        </div>
    </footer>

    <script>
        function toggleCommitteeDetails(committeeId) {
            const details = document.getElementById(`committee-details-${committeeId}`);
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

        console.log('✅ تم تحميل صفحة اللجان البلدية بنجاح');
    </script>
</body>
</html>
