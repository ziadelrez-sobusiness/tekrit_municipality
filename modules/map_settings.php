<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// التحقق من الصلاحيات
$auth->requireLogin();
if (!$auth->checkPermission('admin')) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

$success_message = '';
$error_message = '';

// معالجة حفظ الإعدادات
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
    $settings_to_update = [
        'map_center_lat',
        'map_center_lng', 
        'map_zoom_level',
        'google_maps_api_key',
        'enable_user_location',
        'show_directions',
        'enable_clustering',
        'max_facilities_per_page',
        'enable_ratings',
        'auto_approve_ratings',
        'map_style',
        'enable_search',
        'enable_filters',
        'default_language'
    ];
    
    try {
        $stmt = $db->prepare("UPDATE map_settings SET setting_value = ? WHERE setting_name = ?");
        
        foreach ($settings_to_update as $setting_name) {
            $setting_value = $_POST[$setting_name] ?? '';
            
            // معالجة القيم البوليانية
            if (in_array($setting_name, ['enable_user_location', 'show_directions', 'enable_clustering', 'enable_ratings', 'auto_approve_ratings', 'enable_search', 'enable_filters'])) {
                $setting_value = isset($_POST[$setting_name]) ? '1' : '0';
            }
            
            $stmt->execute([$setting_value, $setting_name]);
        }
        
        $success_message = "تم حفظ الإعدادات بنجاح";
    } catch (Exception $e) {
        $error_message = "خطأ في حفظ الإعدادات: " . $e->getMessage();
    }
}

// جلب الإعدادات الحالية
$current_settings = [];
$settings_query = $db->query("SELECT setting_name, setting_value, setting_description, data_type FROM map_settings ORDER BY setting_name");
while ($row = $settings_query->fetch()) {
    $current_settings[$row['setting_name']] = $row;
}

// جلب إحصائيات النظام
$stats = [
    'total_facilities' => $db->query("SELECT COUNT(*) FROM facilities WHERE is_active = 1")->fetchColumn(),
    'total_categories' => $db->query("SELECT COUNT(*) FROM facility_categories WHERE is_active = 1")->fetchColumn(),
    'total_ratings' => $db->query("SELECT COUNT(*) FROM facility_ratings")->fetchColumn(),
    'pending_ratings' => $db->query("SELECT COUNT(*) FROM facility_ratings WHERE is_approved = 0")->fetchColumn()
];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعدادات خريطة المرافق</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-lg border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">⚙️ إعدادات خريطة المرافق</h1>
                    <p class="text-sm text-gray-500">تخصيص إعدادات النظام والخريطة</p>
                </div>
                <div class="flex items-center space-x-4 space-x-reverse">
                    <a href="facilities_management.php" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                        🏢 إدارة المرافق
                    </a>
                    <a href="facilities_categories.php" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">
                        📂 إدارة الفئات
                    </a>
                    <a href="../comprehensive_dashboard.php" class="text-gray-600 hover:text-gray-900">
                        🏠 العودة للوحة التحكم
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Dashboard -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="text-3xl text-blue-500 ml-3">🏢</div>
                    <div>
                        <p class="text-sm text-gray-600">إجمالي المرافق</p>
                        <p class="text-2xl font-bold"><?= $stats['total_facilities'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="text-3xl text-green-500 ml-3">📂</div>
                    <div>
                        <p class="text-sm text-gray-600">الفئات</p>
                        <p class="text-2xl font-bold"><?= $stats['total_categories'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="text-3xl text-yellow-500 ml-3">⭐</div>
                    <div>
                        <p class="text-sm text-gray-600">التقييمات</p>
                        <p class="text-2xl font-bold"><?= $stats['total_ratings'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="text-3xl text-red-500 ml-3">⏳</div>
                    <div>
                        <p class="text-sm text-gray-600">تقييمات معلقة</p>
                        <p class="text-2xl font-bold"><?= $stats['pending_ratings'] ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Form -->
        <div class="bg-white rounded-lg shadow">
            <form method="POST">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">إعدادات النظام</h3>
                </div>

                <div class="p-6 space-y-8">
                    
                    <!-- إعدادات الخريطة الأساسية -->
                    <div>
                        <h4 class="text-md font-semibold text-gray-800 mb-4">🗺️ إعدادات الخريطة الأساسية</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">خط العرض للمركز</label>
                                <input type="number" 
                                       name="map_center_lat" 
                                       step="any" 
                                       value="<?= htmlspecialchars($current_settings['map_center_lat']['setting_value'] ?? '33.8869') ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                                <p class="text-xs text-gray-500 mt-1">مركز لبنان: 33.8869</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">خط الطول للمركز</label>
                                <input type="number" 
                                       name="map_center_lng" 
                                       step="any" 
                                       value="<?= htmlspecialchars($current_settings['map_center_lng']['setting_value'] ?? '35.5131') ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                                <p class="text-xs text-gray-500 mt-1">مركز لبنان: 35.5131</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">مستوى التكبير</label>
                                <select name="map_zoom_level" 
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                                    <?php 
                                    $current_zoom = $current_settings['map_zoom_level']['setting_value'] ?? '13';
                                    for ($i = 10; $i <= 18; $i++): 
                                    ?>
                                        <option value="<?= $i ?>" <?= $current_zoom == $i ? 'selected' : '' ?>>
                                            مستوى <?= $i ?> <?= $i <= 12 ? '(عام)' : ($i <= 15 ? '(متوسط)' : '(مفصل)') ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- إعدادات عامة -->
                    <div>
                        <h4 class="text-md font-semibold text-gray-800 mb-4">🔧 الإعدادات العامة</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">اللغة الافتراضية</label>
                                <select name="default_language" 
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                                    <?php $current_lang = $current_settings['default_language']['setting_value'] ?? 'ar'; ?>
                                    <option value="ar" <?= $current_lang == 'ar' ? 'selected' : '' ?>>العربية</option>
                                    <option value="en" <?= $current_lang == 'en' ? 'selected' : '' ?>>الإنجليزية</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">نمط الخريطة</label>
                                <select name="map_style" 
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                                    <?php $current_style = $current_settings['map_style']['setting_value'] ?? 'default'; ?>
                                    <option value="default" <?= $current_style == 'default' ? 'selected' : '' ?>>افتراضي</option>
                                    <option value="satellite" <?= $current_style == 'satellite' ? 'selected' : '' ?>>أقمار صناعية</option>
                                    <option value="terrain" <?= $current_style == 'terrain' ? 'selected' : '' ?>>تضاريس</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- ميزات الخريطة -->
                    <div>
                        <h4 class="text-md font-semibold text-gray-800 mb-4">🎯 ميزات الخريطة</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       name="enable_user_location" 
                                       id="enable_user_location" 
                                       <?= ($current_settings['enable_user_location']['setting_value'] ?? '1') == '1' ? 'checked' : '' ?>
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="enable_user_location" class="mr-3 block text-sm text-gray-900">
                                    تفعيل تحديد موقع المستخدم
                                </label>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       name="show_directions" 
                                       id="show_directions" 
                                       <?= ($current_settings['show_directions']['setting_value'] ?? '1') == '1' ? 'checked' : '' ?>
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="show_directions" class="mr-3 block text-sm text-gray-900">
                                    عرض زر الاتجاهات
                                </label>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       name="enable_clustering" 
                                       id="enable_clustering" 
                                       <?= ($current_settings['enable_clustering']['setting_value'] ?? '1') == '1' ? 'checked' : '' ?>
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="enable_clustering" class="mr-3 block text-sm text-gray-900">
                                    تجميع النقاط المتقاربة
                                </label>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       name="enable_search" 
                                       id="enable_search" 
                                       <?= ($current_settings['enable_search']['setting_value'] ?? '1') == '1' ? 'checked' : '' ?>
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="enable_search" class="mr-3 block text-sm text-gray-900">
                                    تفعيل البحث
                                </label>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       name="enable_filters" 
                                       id="enable_filters" 
                                       <?= ($current_settings['enable_filters']['setting_value'] ?? '1') == '1' ? 'checked' : '' ?>
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="enable_filters" class="mr-3 block text-sm text-gray-900">
                                    تفعيل فلاتر الفئات
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- إعدادات التقييمات -->
                    <div>
                        <h4 class="text-md font-semibold text-gray-800 mb-4">⭐ إعدادات التقييمات</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       name="enable_ratings" 
                                       id="enable_ratings" 
                                       <?= ($current_settings['enable_ratings']['setting_value'] ?? '1') == '1' ? 'checked' : '' ?>
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="enable_ratings" class="mr-3 block text-sm text-gray-900">
                                    تفعيل نظام التقييمات
                                </label>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       name="auto_approve_ratings" 
                                       id="auto_approve_ratings" 
                                       <?= ($current_settings['auto_approve_ratings']['setting_value'] ?? '0') == '1' ? 'checked' : '' ?>
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="auto_approve_ratings" class="mr-3 block text-sm text-gray-900">
                                    الموافقة التلقائية على التقييمات
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- إعدادات الأداء -->
                    <div>
                        <h4 class="text-md font-semibold text-gray-800 mb-4">⚡ إعدادات الأداء</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">الحد الأقصى للمرافق</label>
                                <select name="max_facilities_per_page" 
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                                    <?php 
                                    $current_max = $current_settings['max_facilities_per_page']['setting_value'] ?? '50';
                                    $limits = [25 => '25 مرفق', 50 => '50 مرفق', 100 => '100 مرفق', 200 => '200 مرفق', 500 => '500 مرفق'];
                                    foreach ($limits as $value => $label): 
                                    ?>
                                        <option value="<?= $value ?>" <?= $current_max == $value ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">عدد أقل = أداء أفضل</p>
                            </div>
                        </div>
                    </div>

                    <!-- إعدادات متقدمة -->
                    <div>
                        <h4 class="text-md font-semibold text-gray-800 mb-4">🔑 إعدادات متقدمة</h4>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">مفتاح Google Maps API (اختياري)</label>
                            <input type="text" 
                                   name="google_maps_api_key" 
                                   value="<?= htmlspecialchars($current_settings['google_maps_api_key']['setting_value'] ?? '') ?>"
                                   placeholder="AIzaSyC..."
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                            <p class="text-xs text-gray-500 mt-1">
                                للحصول على ميزات إضافية مثل الاتجاهات المتقدمة. 
                                <a href="https://developers.google.com/maps/documentation/javascript/get-api-key" target="_blank" class="text-blue-600 hover:underline">
                                    احصل على مفتاح API
                                </a>
                            </p>
                        </div>
                    </div>

                </div>

                <!-- حفظ الإعدادات -->
                <div class="px-6 py-4 border-t bg-gray-50 flex justify-between">
                    <div class="flex space-x-3 space-x-reverse">
                        <button type="submit" 
                                name="save_settings"
                                class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 focus:ring-2 focus:ring-blue-500">
                            💾 حفظ الإعدادات
                        </button>
                        <a href="../public/facilities-map.php" 
                           target="_blank"
                           class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700">
                            🗺️ معاينة الخريطة
                        </a>
                    </div>
                    
                    <div class="text-sm text-gray-500">
                        آخر تحديث: <?= date('Y-m-d H:i:s') ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Quick Actions -->
        <div class="mt-8 bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">⚡ إجراءات سريعة</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="facilities_management.php" 
                   class="bg-blue-50 border border-blue-200 rounded-lg p-4 hover:bg-blue-100 transition-colors">
                    <div class="text-2xl text-blue-600 mb-2">🏢</div>
                    <h4 class="font-medium text-gray-900">إدارة المرافق</h4>
                    <p class="text-sm text-gray-600">إضافة وتعديل المرافق والخدمات</p>
                </a>
                
                <a href="facilities_categories.php" 
                   class="bg-purple-50 border border-purple-200 rounded-lg p-4 hover:bg-purple-100 transition-colors">
                    <div class="text-2xl text-purple-600 mb-2">📂</div>
                    <h4 class="font-medium text-gray-900">إدارة الفئات</h4>
                    <p class="text-sm text-gray-600">تخصيص أنواع المرافق والألوان</p>
                </a>
                
                <a href="../modules/facilities_api.php?action=get_statistics" 
                   target="_blank"
                   class="bg-green-50 border border-green-200 rounded-lg p-4 hover:bg-green-100 transition-colors">
                    <div class="text-2xl text-green-600 mb-2">📊</div>
                    <h4 class="font-medium text-gray-900">إحصائيات النظام</h4>
                    <p class="text-sm text-gray-600">عرض إحصائيات مفصلة</p>
                </a>
            </div>
        </div>
    </div>
</body>
</html> 