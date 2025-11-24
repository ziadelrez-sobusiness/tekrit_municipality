<?php
header('Content-Type: text/html; charset=utf-8');

// تحميل أنظمة الأمان
if (file_exists(__DIR__ . '/../includes/auto_security.php')) {
    require_once __DIR__ . '/../includes/auto_security.php';
}

require_once '../config/database.php';

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

$site_title = getSetting('site_title', 'بلدية تكريت');

// جلب الفئات النشطة
$categories = [];
try {
    $stmt = $db->query("
        SELECT * FROM important_link_categories 
        WHERE is_active = 1 
        ORDER BY display_order ASC, name_ar ASC
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching categories: " . $e->getMessage());
}

// جلب المرافق حسب الفئة المحددة
$selected_category = $_GET['category'] ?? null;
$search_query = $_GET['search'] ?? '';

$links = [];
$where_clause = "WHERE il.is_active = 1";
$params = [];

if ($selected_category) {
    $where_clause .= " AND il.category_id = ?";
    $params[] = $selected_category;
}

if ($search_query) {
    $where_clause .= " AND (il.name_ar LIKE ? OR il.name_en LIKE ? OR il.description_ar LIKE ? OR il.phone LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

try {
    $stmt = $db->prepare("
        SELECT il.*, 
               ilc.name_ar as category_name_ar,
               ilc.name_en as category_name_en,
               ilc.icon as category_icon,
               ilc.color as category_color
        FROM important_links il
        INNER JOIN important_link_categories ilc ON il.category_id = ilc.id
        $where_clause
        ORDER BY il.is_emergency DESC, il.display_order ASC, il.name_ar ASC
    ");
    $stmt->execute($params);
    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching links: " . $e->getMessage());
}

// تجميع المرافق حسب الفئة
$links_by_category = [];
foreach ($links as $link) {
    $cat_id = $link['category_id'];
    if (!isset($links_by_category[$cat_id])) {
        $links_by_category[$cat_id] = [
            'category' => [
                'id' => $link['category_id'],
                'name_ar' => $link['category_name_ar'],
                'name_en' => $link['category_name_en'],
                'icon' => $link['category_icon'],
                'color' => $link['category_color']
            ],
            'links' => []
        ];
    }
    $links_by_category[$cat_id]['links'][] = $link;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>روابط مهمة - <?= htmlspecialchars($site_title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/tekrit-theme.css" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .card-hover { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .card-hover:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="bg-gray-50">
    <?php require_once 'includes/header.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">🔗 روابط مهمة</h1>
            <p class="text-lg text-gray-600">جميع المرافق العامة والخدمات المهمة في مكان واحد</p>
        </div>

        <!-- Search and Filter -->
        <div class="bg-white rounded-lg shadow-md p-4 md:p-6 mb-8">
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Search -->
                    <div class="md:col-span-2">
                        <input type="text" 
                               name="search" 
                               value="<?= htmlspecialchars($search_query) ?>"
                               placeholder="🔍 ابحث عن مرفق أو خدمة..."
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <!-- Category Filter -->
                    <div>
                        <select name="category" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">جميع الفئات</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $selected_category == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['icon'] . ' ' . $cat['name_ar']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-2">
                    <button type="submit" class="flex-1 bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition">
                        🔍 بحث
                    </button>
                    <button type="button" onclick="loadLinksFromAPI()" 
                            class="bg-green-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-green-700 transition">
                        ⚡ جلب البيانات
                    </button>
                    <a href="important-links.php" class="bg-gray-500 text-white px-6 py-3 rounded-lg font-bold hover:bg-gray-600 transition text-center">
                        🔄 إعادة تعيين
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Loading Indicator -->
        <div id="loadingIndicator" class="hidden bg-blue-50 border-2 border-blue-300 rounded-lg p-4 mb-8 text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <p class="text-blue-700 mt-2">جاري جلب البيانات...</p>
        </div>
        
        <!-- API Results Container -->
        <div id="apiResults" class="hidden"></div>

        <!-- Quick Access Buttons (Emergency Services) -->
        <?php
        $emergency_links = array_filter($links, function($link) {
            return $link['is_emergency'] == 1;
        });
        if (!empty($emergency_links)):
        ?>
        <div class="bg-red-50 border-2 border-red-300 rounded-lg p-4 md:p-6 mb-8">
            <h2 class="text-xl md:text-2xl font-bold text-red-800 mb-4 text-center">🚨 خدمات الطوارئ</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                <?php foreach (array_slice($emergency_links, 0, 6) as $emergency): ?>
                    <a href="tel:<?= htmlspecialchars($emergency['phone']) ?>" 
                       class="bg-white border-2 border-red-400 rounded-lg p-3 text-center hover:bg-red-100 transition transform hover:scale-105">
                        <div class="text-2xl md:text-3xl mb-2"><?= htmlspecialchars($emergency['category_icon']) ?></div>
                        <div class="font-bold text-sm text-red-800"><?= htmlspecialchars($emergency['name_ar']) ?></div>
                        <div class="text-xs text-red-600 mt-1"><?= htmlspecialchars($emergency['phone']) ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Links by Category -->
        <?php if (empty($links_by_category)): ?>
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <div class="text-6xl mb-4">📭</div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">لا توجد روابط</h3>
                <p class="text-gray-600">لم يتم إضافة أي روابط مهمة بعد</p>
            </div>
        <?php else: ?>
            <div class="space-y-8">
                <?php foreach ($links_by_category as $category_data): 
                    $category = $category_data['category'];
                    $category_links = $category_data['links'];
                ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <!-- Category Header -->
                        <div class="bg-gradient-to-r p-4 md:p-6" style="background: linear-gradient(135deg, <?= htmlspecialchars($category['color']) ?> 0%, <?= htmlspecialchars($category['color']) ?>dd 100%);">
                            <h2 class="text-xl md:text-2xl font-bold text-white flex items-center gap-3">
                                <span class="text-3xl md:text-4xl"><?= htmlspecialchars($category['icon']) ?></span>
                                <span><?= htmlspecialchars($category['name_ar']) ?></span>
                                <span class="text-sm md:text-base font-normal opacity-90">(<?= count($category_links) ?>)</span>
                            </h2>
                        </div>
                        
                        <!-- Links Grid -->
                        <div class="p-4 md:p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
                                <?php foreach ($category_links as $link): ?>
                                    <div class="card-hover bg-gray-50 rounded-lg p-4 md:p-6 border-2 border-gray-200">
                                        <div class="flex items-start justify-between mb-3">
                                            <h3 class="text-lg md:text-xl font-bold text-gray-800 flex-1">
                                                <?= htmlspecialchars($link['name_ar']) ?>
                                            </h3>
                                            <?php if ($link['is_emergency']): ?>
                                                <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs font-bold">🚨 طوارئ</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($link['description_ar']): ?>
                                            <p class="text-sm text-gray-600 mb-4 line-clamp-2">
                                                <?= htmlspecialchars($link['description_ar']) ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <div class="space-y-2">
                                            <?php if ($link['phone']): ?>
                                                <div class="flex items-center gap-2 text-sm">
                                                    <span class="text-gray-500">📞</span>
                                                    <a href="tel:<?= htmlspecialchars($link['phone']) ?>" 
                                                       class="text-blue-600 hover:text-blue-800 font-semibold">
                                                        <?= htmlspecialchars($link['phone']) ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($link['phone_2']): ?>
                                                <div class="flex items-center gap-2 text-sm">
                                                    <span class="text-gray-500">📞</span>
                                                    <a href="tel:<?= htmlspecialchars($link['phone_2']) ?>" 
                                                       class="text-blue-600 hover:text-blue-800 font-semibold">
                                                        <?= htmlspecialchars($link['phone_2']) ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($link['email']): ?>
                                                <div class="flex items-center gap-2 text-sm">
                                                    <span class="text-gray-500">✉️</span>
                                                    <a href="mailto:<?= htmlspecialchars($link['email']) ?>" 
                                                       class="text-blue-600 hover:text-blue-800 font-semibold break-all">
                                                        <?= htmlspecialchars($link['email']) ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($link['website']): ?>
                                                <div class="flex items-center gap-2 text-sm">
                                                    <span class="text-gray-500">🌐</span>
                                                    <a href="<?= htmlspecialchars($link['website']) ?>" 
                                                       target="_blank"
                                                       class="text-blue-600 hover:text-blue-800 font-semibold break-all">
                                                        <?= htmlspecialchars(parse_url($link['website'], PHP_URL_HOST) ?: $link['website']) ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($link['address_ar']): ?>
                                                <div class="flex items-start gap-2 text-sm text-gray-600">
                                                    <span class="text-gray-500 mt-1">📍</span>
                                                    <span><?= htmlspecialchars($link['address_ar']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($link['working_hours_ar']): ?>
                                                <div class="flex items-center gap-2 text-sm text-gray-600">
                                                    <span class="text-gray-500">🕐</span>
                                                    <span><?= htmlspecialchars($link['working_hours_ar']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Action Buttons -->
                                        <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-gray-200">
                                            <?php if ($link['phone']): ?>
                                                <a href="tel:<?= htmlspecialchars($link['phone']) ?>" 
                                                   class="flex-1 bg-green-600 text-white px-3 py-2 rounded-lg text-sm font-bold hover:bg-green-700 transition text-center">
                                                    📞 اتصل
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($link['website']): ?>
                                                <a href="<?= htmlspecialchars($link['website']) ?>" 
                                                   target="_blank"
                                                   class="flex-1 bg-blue-600 text-white px-3 py-2 rounded-lg text-sm font-bold hover:bg-blue-700 transition text-center">
                                                    🌐 الموقع
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($link['location_lat'] && $link['location_lng']): ?>
                                                <a href="https://www.google.com/maps?q=<?= $link['location_lat'] ?>,<?= $link['location_lng'] ?>" 
                                                   target="_blank"
                                                   class="flex-1 bg-purple-600 text-white px-3 py-2 rounded-lg text-sm font-bold hover:bg-purple-700 transition text-center">
                                                    🗺️ الموقع
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php require_once 'includes/footer.php'; ?>
    
    <script>
        // دالة لجلب البيانات من API
        async function loadLinksFromAPI() {
            const loadingIndicator = document.getElementById('loadingIndicator');
            const apiResults = document.getElementById('apiResults');
            const mainContent = document.querySelector('.space-y-8');
            
            // إظهار مؤشر التحميل
            loadingIndicator.classList.remove('hidden');
            apiResults.classList.add('hidden');
            
            try {
                // جلب البيانات من API
                const response = await fetch('api/get_important_links.php');
                const data = await response.json();
                
                loadingIndicator.classList.add('hidden');
                
                if (data.success && data.data.links.length > 0) {
                    // إخفاء المحتوى الأصلي
                    if (mainContent) {
                        mainContent.style.display = 'none';
                    }
                    
                    // عرض البيانات من API
                    displayAPILinks(data.data.links, data.data.categories);
                    apiResults.classList.remove('hidden');
                } else {
                    alert('لا توجد بيانات متاحة');
                }
            } catch (error) {
                loadingIndicator.classList.add('hidden');
                console.error('Error loading links:', error);
                alert('حدث خطأ في جلب البيانات. يرجى المحاولة مرة أخرى.');
            }
        }
        
        // دالة لعرض الروابط من API
        function displayAPILinks(links, categories) {
            const container = document.getElementById('apiResults');
            
            // تجميع الروابط حسب الفئة
            const linksByCategory = {};
            links.forEach(link => {
                const catId = link.category_id;
                if (!linksByCategory[catId]) {
                    linksByCategory[catId] = {
                        category: categories.find(c => c.id == catId) || link,
                        links: []
                    };
                }
                linksByCategory[catId].links.push(link);
            });
            
            let html = '<div class="space-y-8">';
            
            Object.values(linksByCategory).forEach(categoryData => {
                const category = categoryData.category;
                const categoryLinks = categoryData.links;
                
                html += `
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="bg-gradient-to-r p-4 md:p-6" style="background: linear-gradient(135deg, ${category.color} 0%, ${category.color}dd 100%);">
                            <h2 class="text-xl md:text-2xl font-bold text-white flex items-center gap-3">
                                <span class="text-3xl md:text-4xl">${category.icon}</span>
                                <span>${category.name_ar}</span>
                                <span class="text-sm md:text-base font-normal opacity-90">(${categoryLinks.length})</span>
                            </h2>
                        </div>
                        <div class="p-4 md:p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
                `;
                
                categoryLinks.forEach(link => {
                    html += `
                        <div class="card-hover bg-gray-50 rounded-lg p-4 md:p-6 border-2 border-gray-200">
                            <div class="flex items-start justify-between mb-3">
                                <h3 class="text-lg md:text-xl font-bold text-gray-800 flex-1">
                                    ${link.name_ar}
                                </h3>
                                ${link.is_emergency == 1 ? '<span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs font-bold">🚨 طوارئ</span>' : ''}
                            </div>
                            ${link.description_ar ? `<p class="text-sm text-gray-600 mb-4 line-clamp-2">${link.description_ar}</p>` : ''}
                            <div class="space-y-2">
                                ${link.phone ? `<div class="flex items-center gap-2 text-sm">
                                    <span class="text-gray-500">📞</span>
                                    <a href="tel:${link.phone}" class="text-blue-600 hover:text-blue-800 font-semibold">${link.phone}</a>
                                </div>` : ''}
                                ${link.email ? `<div class="flex items-center gap-2 text-sm">
                                    <span class="text-gray-500">✉️</span>
                                    <a href="mailto:${link.email}" class="text-blue-600 hover:text-blue-800 font-semibold break-all">${link.email}</a>
                                </div>` : ''}
                                ${link.website ? `<div class="flex items-center gap-2 text-sm">
                                    <span class="text-gray-500">🌐</span>
                                    <a href="${link.website}" target="_blank" class="text-blue-600 hover:text-blue-800 font-semibold break-all">${link.website}</a>
                                </div>` : ''}
                                ${link.address_ar ? `<div class="flex items-start gap-2 text-sm text-gray-600">
                                    <span class="text-gray-500 mt-1">📍</span>
                                    <span>${link.address_ar}</span>
                                </div>` : ''}
                            </div>
                            <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-gray-200">
                                ${link.phone ? `<a href="tel:${link.phone}" class="flex-1 bg-green-600 text-white px-3 py-2 rounded-lg text-sm font-bold hover:bg-green-700 transition text-center">📞 اتصل</a>` : ''}
                                ${link.website ? `<a href="${link.website}" target="_blank" class="flex-1 bg-blue-600 text-white px-3 py-2 rounded-lg text-sm font-bold hover:bg-blue-700 transition text-center">🌐 الموقع</a>` : ''}
                            </div>
                        </div>
                    `;
                });
                
                html += `
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }
    </script>
</body>
</html>

