<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

// معالجة الفلترة
$filter_type = $_GET['type'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// بناء الاستعلام
$where_conditions = ["1=1"];
$params = [];

if (!empty($filter_type)) {
    $where_conditions[] = "i.initiative_type = ?";
    $params[] = $filter_type;
}

if (!empty($filter_status)) {
    if ($filter_status === 'active') {
        $where_conditions[] = "i.is_active = 1";
    } elseif ($filter_status === 'inactive') {
        $where_conditions[] = "i.is_active = 0";
    }
}

if (!empty($search)) {
    $where_conditions[] = "(i.initiative_name LIKE ? OR i.initiative_description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = implode(" AND ", $where_conditions);

// جلب المبادرات مع الصور وعدد المتطوعين
$stmt = $db->prepare("
    SELECT i.*, 
           i.main_image,
           (SELECT COUNT(*) FROM initiative_volunteers WHERE initiative_id = i.id AND registration_status = 'مقبول') as registered_volunteers,
           (SELECT COUNT(*) FROM initiative_images WHERE initiative_id = i.id AND is_active = 1) as image_count
    FROM youth_environmental_initiatives i
    WHERE $where_clause
    ORDER BY i.is_active DESC, i.created_at DESC
");
$stmt->execute($params);
$initiatives = $stmt->fetchAll();

// جلب أنواع المبادرات للفلترة
$types = $db->query("SELECT DISTINCT initiative_type FROM youth_environmental_initiatives ORDER BY initiative_type")->fetchAll();

// جلب إعدادات الموقع
function getSetting($key, $default = '') {
    global $db;
    $stmt = $db->prepare("SELECT setting_value FROM website_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : $default;
}

$site_title = getSetting('site_title', 'بلدية تكريت');

// دالة لتنسيق التاريخ
function formatDate($date) {
    return date('Y/m/d', strtotime($date));
}

// دالة لحالة المبادرة
function getStatusBadge($status) {
    switch($status) {
        case 'مخطط': return '<span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">📋 مخطط</span>';
        case 'قيد التنفيذ': return '<span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">⚙️ قيد التنفيذ</span>';
        case 'مكتمل': return '<span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm">✅ مكتمل</span>';
        case 'متوقف': return '<span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm">⏸️ متوقف</span>';
        default: return '<span class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-sm">📋 غير محدد</span>';
    }
}

// دالة لأيقونة نوع المبادرة
function getInitiativeIcon($type) {
    switch($type) {
        case 'شبابية': return '👥';
        case 'بيئية': return '🌱';
        case 'تطوعية': return '🤝';
        case 'تعليمية': return '📚';
        case 'رياضية': return '⚽';
        case 'ثقافية': return '🎭';
        default: return '🎯';
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المبادرات - <?= htmlspecialchars($site_title) ?></title>
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
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex items-center">
                    <img src="assets/images/Tekrit_LOGO.png" alt="شعار بلدية تكريت" class="tekrit-logo ml-4">
                    <div>
                        <h1 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($site_title) ?></h1>
                        <p class="text-sm text-gray-600 hidden sm:block">خدمات إلكترونية للمواطنين</p>
                    </div>
                </div>
                <nav class="hidden lg:flex space-x-8 space-x-reverse">
                    <a href="index.php" class="text-gray-700 hover:text-blue-600 font-medium">الرئيسية</a>
                    <a href="initiatives.php" class="text-blue-600 font-medium">المبادرات</a>
                    <a href="projects.php" class="text-gray-700 hover:text-blue-600 font-medium">المشاريع</a>
                    <a href="news.php" class="text-gray-700 hover:text-blue-600 font-medium">الأخبار</a>
                    <a href="contact.php" class="text-gray-700 hover:text-blue-600 font-medium">اتصل بنا</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Page Header -->
    <div class="bg-gradient-to-r from-green-600 to-blue-600 text-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl font-bold mb-4">المبادرات البيئية والشبابية</h1>
            <p class="text-xl">انضم إلى مبادراتنا وكن جزءاً من التغيير الإيجابي في مجتمعك</p>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">البحث</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="ابحث في المبادرات..." 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">نوع المبادرة</label>
                    <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">جميع الأنواع</option>
                        <?php foreach ($types as $type): ?>
                            <option value="<?= htmlspecialchars($type['initiative_type']) ?>" 
                                    <?= $filter_type === $type['initiative_type'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['initiative_type']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">الحالة</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">جميع الحالات</option>
                        <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>نشطة</option>
                        <option value="inactive" <?= $filter_status === 'inactive' ? 'selected' : '' ?>>غير نشطة</option>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-300">
                        بحث
                    </button>
                </div>
            </form>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <div class="text-3xl font-bold text-blue-600"><?= count($initiatives) ?></div>
                <div class="text-gray-600">إجمالي المبادرات</div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <div class="text-3xl font-bold text-green-600">
                    <?= count(array_filter($initiatives, function($i) { return $i['is_active']; })) ?>
                </div>
                <div class="text-gray-600">المبادرات النشطة</div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <div class="text-3xl font-bold text-orange-600">
                    <?= array_sum(array_column($initiatives, 'registered_volunteers')) ?>
                </div>
                <div class="text-gray-600">إجمالي المتطوعين</div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <div class="text-3xl font-bold text-purple-600">
                    <?= array_sum(array_column($initiatives, 'image_count')) ?>
                </div>
                <div class="text-gray-600">إجمالي الصور</div>
            </div>
        </div>

        <!-- Initiatives Grid -->
        <?php if (empty($initiatives)): ?>
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <div class="text-6xl mb-4">🌱</div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">لا توجد مبادرات</h3>
                <p class="text-gray-600">لم يتم العثور على مبادرات تطابق معايير البحث المحددة.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($initiatives as $initiative): ?>
                    <div class="card-hover bg-white rounded-lg shadow-md overflow-hidden border-l-4 <?= $initiative['is_active'] ? 'border-green-500' : 'border-gray-400' ?>">
                        <!-- Initiative Image -->
                        <?php if ($initiative['main_image']): ?>
                            <img src="../uploads/initiatives/<?= htmlspecialchars($initiative['main_image']) ?>" 
                                 alt="<?= htmlspecialchars($initiative['initiative_name']) ?>" 
                                 class="w-full h-48 object-cover">
                        <?php else: ?>
                            <div class="w-full h-48 bg-gradient-to-br from-green-500 to-blue-600 flex items-center justify-center">
                                <span class="text-white text-4xl">🌱</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="p-6">
                            <!-- Header -->
                            <div class="flex justify-between items-start mb-3">
                                <h3 class="text-lg font-semibold text-gray-900 flex-1">
                                    <?= htmlspecialchars($initiative['initiative_name']) ?>
                                </h3>
                                <div class="flex flex-col items-end space-y-1">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $initiative['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                        <?= $initiative['is_active'] ? 'نشطة' : 'غير نشطة' ?>
                                    </span>
                                    <?php if ($initiative['image_count'] > 0): ?>
                                        <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                                            📷 <?= $initiative['image_count'] ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Type Badge -->
                            <div class="mb-3">
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                    <?= htmlspecialchars($initiative['initiative_type']) ?>
                                </span>
                            </div>
                            
                            <!-- Description -->
                            <p class="text-gray-600 text-sm mb-4 line-clamp-3">
                                <?= htmlspecialchars(mb_substr($initiative['initiative_description'], 0, 120)) ?>...
                            </p>
                            
                            <!-- Progress -->
                            <div class="mb-4">
                                <div class="flex justify-between text-sm text-gray-600 mb-1">
                                    <span>المتطوعين: <?= $initiative['registered_volunteers'] ?>/<?= $initiative['max_volunteers'] ?></span>
                                    <span><?= $initiative['max_volunteers'] > 0 ? round(($initiative['registered_volunteers'] / $initiative['max_volunteers']) * 100) : 0 ?>%</span>
                                </div>
                                <?php if ($initiative['max_volunteers'] > 0): ?>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-green-600 h-2 rounded-full" 
                                             style="width: <?= ($initiative['registered_volunteers'] / $initiative['max_volunteers']) * 100 ?>%"></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Details -->
                            <div class="space-y-1 text-xs text-gray-500 mb-4">
                                <?php if ($initiative['location']): ?>
                                    <div>📍 <?= htmlspecialchars($initiative['location']) ?></div>
                                <?php endif; ?>
                                <?php if ($initiative['registration_deadline']): ?>
                                    <div>📅 آخر موعد: <?= date('Y/m/d', strtotime($initiative['registration_deadline'])) ?></div>
                                <?php endif; ?>
                                <div>📊 تاريخ الإنشاء: <?= date('Y/m/d', strtotime($initiative['created_at'])) ?></div>
                            </div>
                            
                            <!-- Actions -->
                            <div class="flex space-x-2 space-x-reverse">
                                <a href="initiative-detail.php?id=<?= $initiative['id'] ?>" 
                                   class="flex-1 bg-blue-600 text-white text-center py-2 px-4 rounded-md text-sm hover:bg-blue-700 transition duration-300">
                                    تفاصيل المبادرة
                                </a>
                                <?php if ($initiative['is_active'] && $initiative['registered_volunteers'] < $initiative['max_volunteers']): ?>
                                    <a href="initiative-detail.php?id=<?= $initiative['id'] ?>#register" 
                                       class="bg-green-600 text-white py-2 px-4 rounded-md text-sm hover:bg-green-700 transition duration-300">
                                        انضم
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
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
        </div>
    </footer>
</body>
</html> 
