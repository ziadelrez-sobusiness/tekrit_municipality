<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../config/database.php';
require_once '../includes/currency_formatter.php';

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

// الصفحة الحالية والفلترة
$page = $_GET['page'] ?? 1;
$status_filter = $_GET['status'] ?? '';
$per_page = 9;
$offset = ($page - 1) * $per_page;

// بناء الاستعلام
$where_clause = "WHERE 1=1";
$params = [];

if (!empty($status_filter)) {
    $where_clause .= " AND p.project_status = ?";
    $params[] = $status_filter;
}

// جلب المشاريع من الجدول الموحد
$where_clause .= " AND p.is_public = 1"; // فقط المشاريع العامة

$projects_query = "
    SELECT p.*, 
           p.project_name as name,
           p.status as project_status,
           p.description as project_description,
           p.location as project_location,
           p.budget as project_cost,
           bc.currency_symbol as budget_currency_symbol,
           p.contributions_target,
           p.contributions_collected,
           cc.currency_symbol as contributions_currency_symbol,
           p.beneficiaries_count,
           p.beneficiaries_description,
           p.main_image,
           p.gallery_images,
           p.project_goal,
           p.progress_percentage as completion_percentage,
           '' as department_name,
           a.name as association_name
    FROM projects p 
    LEFT JOIN currencies bc ON p.budget_currency_id = bc.id
    LEFT JOIN currencies cc ON p.contributions_currency_id = cc.id
    LEFT JOIN associations a ON p.association_id = a.id
    $where_clause 
    ORDER BY p.is_featured DESC, p.created_at DESC 
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($projects_query);
$stmt->execute($params);
$projects = $stmt->fetchAll();

// إجمالي عدد المشاريع للترقيم
$count_query = "SELECT COUNT(*) as total FROM projects p WHERE p.is_public = 1";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute();
$total_projects = $count_stmt->fetch()['total'];
$total_pages = ceil($total_projects / $per_page);

// جلب إعدادات الموقع
function getSetting($key, $default = '') {
    global $db;
    $stmt = $db->prepare("SELECT setting_value FROM website_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : $default;
}

$site_title = getSetting('site_title', 'بلدية تكريت');

// حالات المشاريع
$project_statuses = ['مطروح', 'قيد التنفيذ', 'منفذ', 'متوقف', 'ملغي'];

// دالة حساب إجمالي الميزانية من جدول projects الموحد
function calculateTotalBudgetFromProjects($db) {
    try {
        // جلب إجمالي الميزانيات حسب العملة
        $stmt = $db->query("
            SELECT 
                c.currency_symbol,
                c.currency_code,
                SUM(p.budget) as total
            FROM projects p
            INNER JOIN currencies c ON p.budget_currency_id = c.id
            WHERE p.is_public = 1
            GROUP BY c.currency_symbol, c.currency_code
            ORDER BY total DESC
        ");
        $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($budgets)) {
            return '0';
        }
        
        // إذا كان هناك عملة واحدة فقط
        if (count($budgets) == 1) {
            return number_format($budgets[0]['total'], 0) . ' ' . $budgets[0]['currency_symbol'];
        }
        
        // إذا كان هناك أكثر من عملة، عرض الأكبر + عدد العملات الأخرى
        $main = $budgets[0];
        $others_count = count($budgets) - 1;
        return number_format($main['total'], 0) . ' ' . $main['currency_symbol'] . ' + ' . $others_count . ' عملات أخرى';
        
    } catch (PDOException $e) {
        return '0';
    }
}

// إحصائيات سريعة من جدول projects الموحد
$stats = [
    'total' => $db->query("SELECT COUNT(*) as count FROM projects WHERE is_public = 1")->fetch()['count'],
    'ongoing' => $db->query("SELECT COUNT(*) as count FROM projects WHERE is_public = 1 AND status = 'قيد التنفيذ'")->fetch()['count'],
    'completed' => $db->query("SELECT COUNT(*) as count FROM projects WHERE is_public = 1 AND status = 'مكتمل'")->fetch()['count'],
    'total_budget' => calculateTotalBudgetFromProjects($db)
];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site_title) ?> - المشاريع الإنمائية</title>
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
                    <a href="#" class="text-blue-600 font-medium">المشاريع</a>
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
                    <a href="citizen-requests.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600 hover:bg-gray-50 rounded-md font-medium">طلبات المواطنين</a>
                    <a href="#" class="block px-3 py-2 text-blue-600 bg-blue-50 rounded-md font-medium">المشاريع</a>
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

    <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">🏗️ المشاريع الإنمائية</h1>
            <p class="text-xl text-gray-600">
                تطوير وتحسين البنية التحتية وخدمات المدينة
            </p>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <div class="text-3xl font-bold text-blue-600"><?= $stats['total'] ?></div>
                <div class="text-sm text-gray-600">إجمالي المشاريع</div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <div class="text-3xl font-bold text-yellow-600"><?= $stats['ongoing'] ?></div>
                <div class="text-sm text-gray-600">قيد التنفيذ</div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <div class="text-3xl font-bold text-green-600"><?= $stats['completed'] ?></div>
                <div class="text-sm text-gray-600">مكتملة</div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <div class="text-2xl font-bold text-purple-600"><?= $stats['total_budget'] ?></div>
                <div class="text-sm text-gray-600">إجمالي الميزانية</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap gap-2">
                    <a href="?" class="px-4 py-2 rounded-lg font-medium transition-colors <?= empty($status_filter) ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                        جميع المشاريع
                    </a>
                    <?php foreach ($project_statuses as $status): ?>
                        <a href="?status=<?= urlencode($status) ?>" 
                           class="px-4 py-2 rounded-lg font-medium transition-colors <?= $status_filter == $status ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                            <?= $status ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="text-sm text-gray-600">
                    إجمالي: <?= $total_projects ?> مشروع
                </div>
            </div>
        </div>

        <!-- Projects Grid -->
        <?php if (!empty($projects)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
                <?php foreach ($projects as $project): ?>
                    <div class="card-hover bg-white rounded-lg shadow-md overflow-hidden">
                        <!-- Project Header -->
                        <div class="p-6">
                            <div class="flex justify-between items-start mb-4">
                                <h3 class="text-xl font-semibold text-gray-900 leading-tight">
                                    <?= htmlspecialchars($project['project_name']) ?>
                                </h3>
                                <?php if ($project['is_featured']): ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        ⭐ مميز
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Status Badge -->
                            <div class="mb-4">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                                    <?php 
                                        switch($project['project_status']) {
                                            case 'مطروح': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'قيد التنفيذ': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'منفذ': echo 'bg-green-100 text-green-800'; break;
                                            case 'متوقف': echo 'bg-orange-100 text-orange-800'; break;
                                            case 'ملغي': echo 'bg-red-100 text-red-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                    ?>">
                                    <?= $project['project_status'] ?>
                                </span>
                            </div>
                            
                            <!-- Project Description -->
                            <p class="text-gray-600 text-sm mb-4 leading-relaxed">
                                <?= htmlspecialchars(substr($project['project_description'], 0, 120)) ?>...
                            </p>
                            
                            <!-- Project Details -->
                            <div class="space-y-2 mb-4">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">📍 الموقع:</span>
                                    <span class="font-medium"><?= htmlspecialchars($project['project_location']) ?></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">💰 التكلفة:</span>
                                    <span class="font-medium"><?= formatProjectCost($project, $db) ?></span>
                                </div>
                                <?php if (!empty($project['project_duration'])): ?>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">⏰ المدة:</span>
                                    <span class="font-medium"><?= htmlspecialchars($project['project_duration']) ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($project['beneficiaries_count']): ?>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">👥 المستفيدون:</span>
                                    <span class="font-medium"><?= number_format($project['beneficiaries_count']) ?> شخص</span>
                                </div>
                                <?php endif; ?>
                                <?php if ($project['department_name']): ?>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">🏢 القسم المسؤول:</span>
                                    <span class="font-medium"><?= htmlspecialchars($project['department_name']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Progress Bar -->
                            <?php if ($project['completion_percentage'] > 0): ?>
                                <div class="mb-4">
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-500">نسبة الإنجاز:</span>
                                        <span class="font-medium"><?= $project['completion_percentage'] ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-green-600 h-2 rounded-full transition-all duration-300" 
                                             style="width: <?= $project['completion_percentage'] ?>%"></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Action Buttons -->
                            <div class="flex justify-between items-center pt-4 border-t border-gray-200">
                                <a href="project-detail.php?id=<?= $project['id'] ?>" 
                                   class="inline-flex items-center px-3 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 transition-colors">
                                    📄 التفاصيل
                                </a>
                                
                                <?php if (!empty($project['allow_public_contributions']) && $project['project_status'] != 'منفذ' && $project['project_status'] != 'مكتمل'): ?>
                                    <a href="citizen-requests.php?type=المساهمة في المشروع&project_id=<?= $project['id'] ?>" 
                                       class="inline-flex items-center px-3 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700 transition-colors">
                                        💝 ساهم في المشروع
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center">
                    <nav class="flex items-center space-x-2 space-x-reverse">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>" 
                               class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                ← السابق
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>" 
                               class="px-3 py-2 border rounded-md <?= $i == $page ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white border-gray-300 hover:bg-gray-50' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>" 
                               class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                التالي →
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- No Projects -->
            <div class="text-center py-12">
                <div class="text-6xl mb-4">🏗️</div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">لا توجد مشاريع</h3>
                <p class="text-gray-600">لم يتم إضافة أي مشاريع بعد في هذا القسم</p>
            </div>
        <?php endif; ?>

        <!-- Call to Action -->
        <div class="bg-indigo-600 rounded-lg p-8 text-center text-white mt-12">
            <h2 class="text-2xl font-bold mb-4">هل لديك فكرة مشروع؟</h2>
            <p class="text-indigo-100 mb-6">شاركنا أفكارك واقتراحاتك لتطوير المدينة</p>
            <a href="citizen-requests.php?type=اقتراح" 
               class="inline-flex items-center px-6 py-3 bg-white text-indigo-600 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                💡 اقترح مشروعاً
            </a>
        </div>
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
                    <p class="text-gray-300">تطوير مستمر لخدمات ومرافق المدينة</p>
                </div>
                <div>
                    <h4 class="text-lg font-semibold mb-4">الأقسام</h4>
                    <ul class="space-y-2">
                        <li><a href="index.php" class="text-gray-300 hover:text-white">الرئيسية</a></li>
                        <li><a href="news.php" class="text-gray-300 hover:text-white">الأخبار</a></li>
                        <li><a href="citizen-requests.php" class="text-gray-300 hover:text-white">طلبات المواطنين</a></li>
                        <li><a href="contact.php" class="text-gray-300 hover:text-white">اتصل بنا</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-lg font-semibold mb-4">حالات المشاريع</h4>
                    <ul class="space-y-2">
                        <?php foreach ($project_statuses as $status): ?>
                            <li><a href="?status=<?= urlencode($status) ?>" class="text-gray-300 hover:text-white"><?= $status ?></a></li>
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
