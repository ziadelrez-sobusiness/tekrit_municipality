<?php
// تحميل أنظمة الأمان
if (file_exists(__DIR__ . '/../includes/auto_security.php')) {
    require_once __DIR__ . '/../includes/auto_security.php';
}

require_once dirname(__DIR__) . '/config/database.php';

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

// إعداد المتغيرات الأساسية
$site_title = "بلدية تكريت";

// دالة للحصول على الإعدادات
function getSetting($key, $default = '') {
    global $db;
    try {
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_name = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// الحصول على عنوان الموقع من الإعدادات
$site_title = getSetting('site_title', 'بلدية تكريت');

// الحصول على اللغة من الرابط أو القيم المحفوظة
$lang = $_GET['lang'] ?? 'ar';
if (!in_array($lang, ['ar', 'en'])) {
    $lang = 'ar';
}

// جلب إعدادات الخريطة
$settings = [];
$settings_result = $db->query("SELECT setting_name, setting_value FROM map_settings WHERE is_public = 1");
while ($row = $settings_result->fetch()) {
    $settings[$row['setting_name']] = $row['setting_value'];
}

// جلب الفئات للفلاتر
$categories = $db->query("SELECT * FROM facility_categories WHERE is_active = 1 ORDER BY display_order, name_ar")->fetchAll();

// تجهيز النصوص حسب اللغة
$texts = [
    'ar' => [
        'title' => 'خريطة المرافق والخدمات',
        'subtitle' => 'اكتشف المحلات والمؤسسات والخدمات في لبنان',
        'search_placeholder' => 'البحث عن مرفق...',
        'all_categories' => 'جميع الفئات',
        'search' => 'بحث',
        'clear' => 'مسح',
        'get_directions' => 'الحصول على الاتجاهات',
        'call_now' => 'اتصل الآن',
        'website' => 'الموقع الإلكتروني',
        'working_hours' => 'ساعات العمل',
        'address' => 'العنوان',
        'contact_person' => 'جهة الاتصال',
        'no_results' => 'لم يتم العثور على نتائج',
        'loading' => 'جارٍ التحميل...',
        'error_location' => 'تعذر تحديد موقعك',
        'find_my_location' => 'تحديد موقعي',
        'close' => 'إغلاق',
        'phone' => 'الهاتف',
        'email' => 'البريد الإلكتروني'
    ],
    'en' => [
        'title' => 'Facilities & Services Map',
        'subtitle' => 'Discover shops, institutions and services in Lebanon',
        'search_placeholder' => 'Search for facility...',
        'all_categories' => 'All Categories',
        'search' => 'Search',
        'clear' => 'Clear',
        'get_directions' => 'Get Directions',
        'call_now' => 'Call Now',
        'website' => 'Website',
        'working_hours' => 'Working Hours',
        'address' => 'Address',
        'contact_person' => 'Contact Person',
        'no_results' => 'No results found',
        'loading' => 'Loading...',
        'error_location' => 'Unable to determine your location',
        'find_my_location' => 'Find My Location',
        'close' => 'Close',
        'phone' => 'Phone',
        'email' => 'Email'
    ]
];

$t = $texts[$lang];
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $lang == 'ar' ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $t['title'] ?> بلدية تكريت - عكار , شمال لبنان</title>
    <meta name="description" content="<?= $t['subtitle'] ?>">
    
    <!-- CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- Leaflet CSS & JS (خريطة مفتوحة المصدر كبديل لـ Google Maps) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // تعطيل source maps لتجنب أخطاء CSP
        if (typeof L !== 'undefined' && L.Icon && L.Icon.Default) {
            L.Icon.Default.prototype._getIconUrl = L.Icon.Default.prototype._getIconUrl || function(name) {
                return 'https://unpkg.com/leaflet@1.9.4/dist/images/' + name;
            };
        }
    </script>
    
    <!-- Leaflet MarkerCluster -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
    <script>
        // تعطيل source maps لتجنب أخطاء CSP
        if (typeof window !== 'undefined') {
            // منع محاولات تحميل source maps
            const originalFetch = window.fetch;
            window.fetch = function(...args) {
                if (args[0] && typeof args[0] === 'string' && args[0].includes('.map')) {
                    return Promise.reject(new Error('Source map loading disabled'));
                }
                return originalFetch.apply(this, args);
            };
        }
    </script>
    
    <style>
        body { font-family: 'Cairo', sans-serif; }
        #map { 
            height: 70vh; 
            min-height: 500px; 
            width: 100%;
            position: relative;
            z-index: 1;
        }
        .facility-popup {
            max-width: 300px;
            font-family: 'Cairo', sans-serif;
        }
        .facility-popup img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .search-results {
            max-height: 300px;
            overflow-y: auto;
        }
        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Custom marker styles */
        .custom-marker {
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            text-align: center;
            color: white;
            font-weight: bold;
            font-size: 12px;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Modal styles - ensure it appears above everything */
        #facilityModal {
            z-index: 99999 !important;
        }
        
        #facilityModal .bg-white {
            z-index: 99999 !important;
            position: relative !important;
        }
        
        /* Map should have lower z-index */
        .leaflet-container {
            z-index: 1 !important;
        }
        
        /* Ensure map container is visible */
        #map {
            background-color: #e5e7eb;
        }
        
        /* Loading state for map */
        .map-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 70vh;
            min-height: 500px;
            background-color: #f3f4f6;
            color: #6b7280;
            font-size: 18px;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php require_once 'includes/header.php'; ?>
    
    <!-- Page Header -->
    <div class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">🗺️ <?= $t['title'] ?></h1>
                    <p class="text-sm text-gray-600"><?= $t['subtitle'] ?></p>
                </div>
                <div class="flex items-center space-x-4 <?= $lang == 'ar' ? 'space-x-reverse' : '' ?>">
                    <!-- Language Toggle -->
                    <div class="flex bg-gray-100 rounded-lg p-1">
                        <a href="?lang=ar" class="px-3 py-1 rounded-md text-sm <?= $lang == 'ar' ? 'bg-white shadow' : 'text-gray-600' ?>">
                            عربي
                        </a>
                        <a href="?lang=en" class="px-3 py-1 rounded-md text-sm <?= $lang == 'en' ? 'bg-white shadow' : 'text-gray-600' ?>">
                            English
                        </a>
                    </div>
                    <a href="../index.php" class="text-gray-600 hover:text-gray-900">
                        🏠 <?= $lang == 'ar' ? 'الصفحة الرئيسية' : 'Home' ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Search and Filters -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Search Input -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        🔍 <?= $t['search'] ?>
                    </label>
                    <input type="text" 
                           id="searchInput" 
                           placeholder="<?= $t['search_placeholder'] ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <!-- Category Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        📂 <?= $lang == 'ar' ? 'الفئة' : 'Category' ?>
                    </label>
                    <select id="categoryFilter" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value=""><?= $t['all_categories'] ?></option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" data-color="<?= htmlspecialchars($category['color']) ?>">
                                <?= htmlspecialchars($lang == 'ar' ? $category['name_ar'] : $category['name_en']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex items-end space-x-2 <?= $lang == 'ar' ? 'space-x-reverse' : '' ?>">
                    <button onclick="searchFacilities()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 flex-1">
                        <?= $t['search'] ?>
                    </button>
                    <button onclick="clearSearch()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
                        <?= $t['clear'] ?>
                    </button>
                </div>
            </div>
            
            <!-- Quick Category Buttons -->
            <div class="mt-4 flex flex-wrap gap-2">
                <?php foreach ($categories as $category): ?>
                    <button onclick="filterByCategory(<?= $category['id'] ?>)" 
                            class="px-3 py-1 rounded-full text-sm text-white hover:opacity-80 transition-opacity"
                            style="background-color: <?= htmlspecialchars($category['color']) ?>">
                        <?= htmlspecialchars($lang == 'ar' ? $category['name_ar'] : $category['name_en']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Map Container -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <!-- Map Controls -->
            <div class="bg-gray-50 p-3 border-b flex justify-between items-center">
                <div class="text-sm text-gray-600">
                    <span id="facilityCount">0</span> <?= $lang == 'ar' ? 'مرفق' : 'facilities' ?>
                </div>
                <div class="flex items-center space-x-2 <?= $lang == 'ar' ? 'space-x-reverse' : '' ?>">
                    
                    <button onclick="resetMapView()" class="bg-gray-600 text-white px-3 py-1 rounded text-sm hover:bg-gray-700">
                        🎯 <?= $lang == 'ar' ? 'إعادة التوسيط' : 'Reset View' ?>
                    </button>
                </div>
            </div>
            
            <!-- Map -->
            <div id="map">
                <div class="map-loading" id="mapLoading">
                    <div class="text-center">
                        <div class="loading-spinner"></div>
                        <p class="mt-3"><?= $t['loading'] ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Results (Mobile) -->
        <div id="searchResults" class="mt-6 bg-white rounded-lg shadow-md hidden">
            <div class="p-4 border-b">
                <h3 class="text-lg font-semibold"><?= $lang == 'ar' ? 'نتائج البحث' : 'Search Results' ?></h3>
            </div>
            <div id="searchResultsList" class="search-results p-4">
                <!-- Results will be populated here -->
            </div>
        </div>
    </div>

    <!-- Facility Details Modal -->
    <div id="facilityModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden" style="z-index: 9999;">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full max-h-screen overflow-y-auto" style="z-index: 10000;">
                <div id="facilityModalContent" class="p-6">
                    <!-- Modal content will be populated here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-40">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white rounded-lg p-6 text-center">
                <div class="loading-spinner"></div>
                <p class="mt-3 text-gray-600"><?= $t['loading'] ?></p>
            </div>
        </div>
    </div>

    <script>
        // تكوين الخريطة
        const MAP_CONFIG = {
            center: [<?= $settings['map_center_lat'] ?? '33.8869' ?>, <?= $settings['map_center_lng'] ?? '35.5131' ?>],
            zoom: <?= $settings['map_zoom_level'] ?? '13' ?>,
            language: '<?= $lang ?>',
            enableClustering: <?= ($settings['enable_clustering'] ?? '1') == '1' ? 'true' : 'false' ?>
        };

        const TEXTS = <?= json_encode($t) ?>;
        
        let map;
        let markers = [];
        let markerClusterGroup;
        let userLocationMarker;
        let allFacilities = [];

        // تهيئة الخريطة
        function initMap() {
            try {
                // التحقق من وجود عنصر الخريطة
                const mapElement = document.getElementById('map');
                if (!mapElement) {
                    console.error('❌ عنصر الخريطة غير موجود!');
                    return;
                }

                // إخفاء رسالة التحميل
                const mapLoading = document.getElementById('mapLoading');
                if (mapLoading) {
                    mapLoading.style.display = 'none';
                }

                // إنشاء الخريطة
                map = L.map('map', {
                    center: MAP_CONFIG.center,
                    zoom: MAP_CONFIG.zoom,
                    zoomControl: true
                });

                // إضافة طبقة الخريطة (OpenStreetMap)
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors',
                    maxZoom: 19,
                }).addTo(map);

                // إعادة رسم الخريطة بعد تحميلها بالكامل
                setTimeout(() => {
                    if (map) {
                        map.invalidateSize();
                        console.log('✅ تم إعادة رسم الخريطة');
                    }
                }, 300);

                // إنشاء مجموعة تجميع النقاط
                if (MAP_CONFIG.enableClustering) {
                    markerClusterGroup = L.markerClusterGroup({
                        chunkedLoading: true,
                        spiderfyOnMaxZoom: true,
                        showCoverageOnHover: false,
                        zoomToBoundsOnClick: true
                    });
                    map.addLayer(markerClusterGroup);
                }

                console.log('✅ تم تهيئة الخريطة بنجاح');
                
                // تحميل المرافق بعد تهيئة الخريطة
                setTimeout(() => {
                    loadFacilities();
                }, 500);
            } catch (error) {
                console.error('❌ خطأ في تهيئة الخريطة:', error);
                const mapLoading = document.getElementById('mapLoading');
                if (mapLoading) {
                    mapLoading.innerHTML = `
                        <div class="text-center">
                            <p class="text-red-600 font-bold">❌ خطأ في تحميل الخريطة</p>
                            <p class="text-sm mt-2">${error.message}</p>
                            <button onclick="location.reload()" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded">
                                إعادة تحميل الصفحة
                            </button>
                        </div>
                    `;
                }
            }
        }

        // تحميل المرافق من قاعدة البيانات
        async function loadFacilities(search = '', categoryId = '') {
            showLoading(true);
            
            try {
                const params = new URLSearchParams({
                    action: 'get_facilities',
                    search: search,
                    category: categoryId,
                    lang: MAP_CONFIG.language
                });

                const apiUrl = '../modules/facilities_api.php?' + params;
                console.log('🔗 جاري الاتصال بـ API:', apiUrl);

                const response = await fetch(apiUrl);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                console.log('📦 البيانات المستلمة من API:', data);
                console.log('📊 تفاصيل البيانات:', {
                    success: data.success,
                    hasFacilities: !!(data.facilities || (data.data && data.data.facilities)),
                    facilitiesType: typeof (data.facilities || (data.data && data.data.facilities)),
                    facilitiesIsArray: Array.isArray(data.facilities || (data.data && data.data.facilities)),
                    facilitiesLength: (data.facilities || (data.data && data.data.facilities) || []).length,
                    error: data.error,
                    count: data.count || (data.data && data.data.count),
                    dataKeys: Object.keys(data)
                });

                if (data.success) {
                    // ApiSecurity قد يغلف البيانات في 'data'، لذا نتحقق من كلا المكانين
                    const facilities = data.facilities || (data.data && data.data.facilities) || [];
                    
                    if (Array.isArray(facilities) && facilities.length > 0) {
                        allFacilities = facilities;
                        console.log(`✅ تم تحميل ${facilities.length} مرفق`);
                        displayFacilitiesOnMap(facilities);
                        updateFacilityCount(facilities.length);
                    } else if (Array.isArray(facilities) && facilities.length === 0) {
                        console.log('⚠️ لا توجد مرافق في قاعدة البيانات');
                        allFacilities = [];
                        updateFacilityCount(0);
                        // عرض رسالة للمستخدم
                        if (MAP_CONFIG.language === 'ar') {
                            console.warn('لا توجد مرافق متاحة للعرض');
                        }
                    } else {
                        console.error('❌ البيانات المستلمة غير صحيحة:', data);
                        console.error('تفاصيل:', {
                            dataType: typeof data,
                            dataKeys: Object.keys(data),
                            dataData: data.data,
                            facilities: data.facilities
                        });
                        updateFacilityCount(0);
                        alert(MAP_CONFIG.language === 'ar' ? 
                            'خطأ في تنسيق البيانات المستلمة من API' : 
                            'Error in data format received from API');
                    }
                } else {
                    console.error('❌ خطأ في تحميل المرافق:', data.error || 'خطأ غير معروف');
                    updateFacilityCount(0);
                    
                    // عرض رسالة للمستخدم
                    const errorMessage = data.error || (MAP_CONFIG.language === 'ar' ? 'خطأ غير معروف' : 'Unknown error');
                    console.error('تفاصيل الخطأ:', {
                        error: data.error,
                        debug_info: data.debug_info,
                        fullResponse: data
                    });
                    
                    if (data.error) {
                        alert((MAP_CONFIG.language === 'ar' ? 'خطأ في تحميل المرافق: ' : 'Error loading facilities: ') + errorMessage);
                    }
                }
            } catch (error) {
                console.error('❌ خطأ في الاتصال بـ API:', error);
                updateFacilityCount(0);
                alert('حدث خطأ في تحميل المرافق. يرجى التحقق من اتصال الإنترنت.');
            } finally {
                showLoading(false);
            }
        }

        // عرض المرافق على الخريطة
        function displayFacilitiesOnMap(facilities) {
            if (!map) {
                console.error('❌ الخريطة غير مهيأة!');
                return;
            }

            // مسح النقاط السابقة
            clearMarkers();

            if (facilities.length === 0) {
                console.log('⚠️ لا توجد مرافق للعرض');
                return;
            }

            console.log(`📍 جاري عرض ${facilities.length} مرفق على الخريطة`);

            facilities.forEach((facility, index) => {
                try {
                    // التحقق من صحة الإحداثيات
                    if (!facility.latitude || !facility.longitude) {
                        console.warn(`⚠️ مرفق بدون إحداثيات: ${facility.name_ar || facility.id}`);
                        return;
                    }

                    const marker = createFacilityMarker(facility);
                    markers.push(marker);
                    
                    if (MAP_CONFIG.enableClustering && markerClusterGroup) {
                        markerClusterGroup.addLayer(marker);
                    } else {
                        marker.addTo(map);
                    }
                } catch (error) {
                    console.error(`❌ خطأ في إنشاء نقطة للمرفق ${facility.id}:`, error);
                }
            });

            // إعادة رسم الخريطة
            setTimeout(() => {
                map.invalidateSize();
            }, 100);

            // ضبط مركز الخريطة والزوم تلقائياً بناءً على المرافق
            autoFitMapToFacilities(facilities);
        }

        // ضبط مركز الخريطة تلقائياً بناءً على المرافق الموجودة
        function autoFitMapToFacilities(facilities) {
            if (!map || facilities.length === 0) return;

            try {
                if (facilities.length === 1) {
                    // إذا كان هناك مرفق واحد فقط، اعرضه بزوم 15
                    map.setView([facilities[0].latitude, facilities[0].longitude], 15);
                } else if (markers.length > 0) {
                    // إذا كان هناك أكثر من مرفق، احسب الحدود واعرض جميع المرافق
                    const group = L.featureGroup(markers);
                    const bounds = group.getBounds();
                    if (bounds.isValid()) {
                        map.fitBounds(bounds.pad(0.1)); // إضافة هامش 10%
                    } else {
                        // إذا فشل حساب الحدود، استخدم الإعدادات الافتراضية
                        map.setView(MAP_CONFIG.center, MAP_CONFIG.zoom);
                    }
                } else {
                    // إذا لم تكن هناك نقاط، استخدم الإعدادات الافتراضية
                    map.setView(MAP_CONFIG.center, MAP_CONFIG.zoom);
                }
            } catch (error) {
                console.error('❌ خطأ في ضبط عرض الخريطة:', error);
                map.setView(MAP_CONFIG.center, MAP_CONFIG.zoom);
            }
        }

        // إنشاء نقطة مرفق
        function createFacilityMarker(facility) {
            const categoryColor = facility.category_color || '#3498db';
            
            // إنشاء أيقونة مخصصة
            const customIcon = L.divIcon({
                html: `<div class="custom-marker" style="background-color: ${categoryColor}; width: 30px; height: 30px;">
                         ${getCategoryEmoji(facility.category_icon)}
                       </div>`,
                className: '',
                iconSize: [30, 30],
                iconAnchor: [15, 15],
                popupAnchor: [0, -15]
            });

            const marker = L.marker([facility.latitude, facility.longitude], {
                icon: customIcon
            });

            // إنشاء محتوى النافذة المنبثقة
            const popupContent = createPopupContent(facility);
            
            marker.bindPopup(popupContent, {
                className: 'facility-popup',
                maxWidth: 300
            });

            return marker;
        }

        // الحصول على الرمز التعبيري للفئة
        function getCategoryEmoji(icon) {
            const emojiMap = {
                'school': '🏫',
                'mosque': '🕌',
                'hospital': '🏥',
                'store': '🏪',
                'restaurant': '🍽️',
                'government': '🏛️',
                'bank': '🏦',
                'gas-station': '⛽',
                'park': '🌳',
                'sports': '⚽',
                'pharmacy': '💊',
                'hotel': '🏨',
                'service': '🔧',
                'parking': '🅿️',
                'market': '🛒'
            };
            return emojiMap[icon] || '📍';
        }

        // إنشاء محتوى النافذة المنبثقة
        function createPopupContent(facility) {
            const name = MAP_CONFIG.language === 'ar' ? facility.name_ar : (facility.name_en || facility.name_ar);
            const categoryName = MAP_CONFIG.language === 'ar' ? facility.category_name_ar : (facility.category_name_en || facility.category_name_ar);
            
            let content = `
                <div class="facility-popup">
                    ${facility.image_path ? 
                        `<img src="../uploads/facilities/${facility.image_path}" alt="${name}" class="w-20 h-20 object-cover rounded-lg mx-auto mb-2" onerror="this.style.display='none'">` 
                        : ''
                    }
                    <h3 class="font-bold text-lg mb-2 text-center">${name}</h3>
                    <p class="text-sm text-gray-600 mb-3 text-center">
                        <span class="inline-block px-2 py-1 rounded text-xs text-white" style="background-color: ${facility.category_color}">
                            ${categoryName}
                        </span>
                    </p>
                    
                    <div class="flex flex-wrap gap-2 justify-center mb-3">
                        <button onclick="getDirections(${facility.latitude}, ${facility.longitude})" 
                                class="bg-blue-500 text-white px-3 py-1 rounded text-xs hover:bg-blue-600">
                            🧭 ${TEXTS.get_directions}
                        </button>`;

            if (facility.phone) {
                content += `
                        <a href="tel:${facility.phone}" 
                           class="bg-green-500 text-white px-3 py-1 rounded text-xs hover:bg-green-600">
                            📞 ${TEXTS.call_now}
                        </a>`;
            }

            content += `
                    </div>
                    
                    <button onclick="showFacilityDetails(${JSON.stringify(facility).replace(/"/g, '&quot;')})" 
                            class="w-full bg-gray-800 text-white py-2 rounded text-sm hover:bg-gray-900">
                        📋 ${MAP_CONFIG.language === 'ar' ? 'عرض التفاصيل الكاملة' : 'View Full Details'}
                    </button>
                </div>
            `;

            return content;
        }

        // عرض تفاصيل المرفق في النافذة المنبثقة
        function showFacilityDetails(facility) {
            const name = MAP_CONFIG.language === 'ar' ? facility.name_ar : (facility.name_en || facility.name_ar);
            const description = MAP_CONFIG.language === 'ar' ? facility.description_ar : (facility.description_en || facility.description_ar);
            const categoryName = MAP_CONFIG.language === 'ar' ? facility.category_name_ar : (facility.category_name_en || facility.category_name_ar);
            const contactPerson = MAP_CONFIG.language === 'ar' ? facility.contact_person_ar : (facility.contact_person_en || facility.contact_person_ar);
            const address = MAP_CONFIG.language === 'ar' ? facility.address_ar : (facility.address_en || facility.address_ar);
            const workingHours = MAP_CONFIG.language === 'ar' ? facility.working_hours_ar : (facility.working_hours_en || facility.working_hours_ar);

            const modalContent = `
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-900">${name}</h3>
                    <button onclick="closeFacilityModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                ${facility.image_path ? 
                    `<img src="../uploads/facilities/${facility.image_path}" alt="${name}" class="w-full h-48 object-cover rounded-lg mb-4">` 
                    : ''
                }

                <div class="space-y-4">
                    <div>
                        <span class="inline-block px-3 py-1 rounded-full text-sm text-white" style="background-color: ${facility.category_color}">
                            ${categoryName}
                        </span>
                    </div>

                    ${description ? `
                        <div>
                            <h4 class="font-semibold text-gray-700 mb-2">${MAP_CONFIG.language === 'ar' ? 'الوصف' : 'Description'}</h4>
                            <p class="text-gray-600">${description}</p>
                        </div>
                    ` : ''}

                    ${address ? `
                        <div>
                            <h4 class="font-semibold text-gray-700 mb-2">📍 ${TEXTS.address}</h4>
                            <p class="text-gray-600">${address}</p>
                        </div>
                    ` : ''}

                    ${contactPerson ? `
                        <div>
                            <h4 class="font-semibold text-gray-700 mb-2">👤 ${TEXTS.contact_person}</h4>
                            <p class="text-gray-600">${contactPerson}</p>
                        </div>
                    ` : ''}

                    ${facility.phone || facility.email ? `
                        <div>
                            <h4 class="font-semibold text-gray-700 mb-2">📞 ${MAP_CONFIG.language === 'ar' ? 'معلومات الاتصال' : 'Contact Info'}</h4>
                            <div class="space-y-1">
                                ${facility.phone ? `<p class="text-gray-600">${TEXTS.phone}: <a href="tel:${facility.phone}" class="text-blue-600 hover:underline">${facility.phone}</a></p>` : ''}
                                ${facility.email ? `<p class="text-gray-600">${TEXTS.email}: <a href="mailto:${facility.email}" class="text-blue-600 hover:underline">${facility.email}</a></p>` : ''}
                            </div>
                        </div>
                    ` : ''}

                    ${workingHours ? `
                        <div>
                            <h4 class="font-semibold text-gray-700 mb-2">🕐 ${TEXTS.working_hours}</h4>
                            <p class="text-gray-600">${workingHours}</p>
                        </div>
                    ` : ''}

                    <div class="border-t pt-4">
                        <div class="flex flex-wrap gap-2">
                            <button onclick="getDirections(${facility.latitude}, ${facility.longitude})" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                🧭 ${TEXTS.get_directions}
                            </button>
                            
                            ${facility.phone ? `
                                <a href="tel:${facility.phone}" 
                                   class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                                    📞 ${TEXTS.call_now}
                                </a>
                            ` : ''}
                            
                            ${facility.website ? `
                                <a href="${facility.website}" target="_blank" 
                                   class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">
                                    🌐 ${TEXTS.website}
                                </a>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('facilityModalContent').innerHTML = modalContent;
            
            // إظهار النافذة مع التأكد من z-index صحيح
            const modal = document.getElementById('facilityModal');
            modal.classList.remove('hidden');
            
            // التأكد من أن النافذة تظهر أمام الخريطة
            modal.style.zIndex = '99999';
            modal.style.position = 'fixed';
            
            // التأكد من أن الخريطة خلف النافذة
            const mapElement = document.getElementById('map');
            if (mapElement) {
                mapElement.style.zIndex = '1';
            }
            
            // التأكد من أن عناصر Leaflet أيضاً خلف النافذة
            const leafletContainer = document.querySelector('.leaflet-container');
            if (leafletContainer) {
                leafletContainer.style.zIndex = '1';
            }
        }

        // إغلاق نافذة تفاصيل المرفق
        function closeFacilityModal() {
            document.getElementById('facilityModal').classList.add('hidden');
        }

        // البحث في المرافق
        function searchFacilities() {
            const searchTerm = document.getElementById('searchInput').value;
            const categoryId = document.getElementById('categoryFilter').value;
            loadFacilities(searchTerm, categoryId);
        }

        // مسح البحث
        function clearSearch() {
            document.getElementById('searchInput').value = '';
            document.getElementById('categoryFilter').value = '';
            loadFacilities();
        }

        // التصفية حسب الفئة
        function filterByCategory(categoryId) {
            document.getElementById('categoryFilter').value = categoryId;
            loadFacilities('', categoryId);
        }

        // مسح النقاط
        function clearMarkers() {
            if (!map) return;
            
            markers.forEach(marker => {
                try {
                    if (MAP_CONFIG.enableClustering && markerClusterGroup) {
                        markerClusterGroup.removeLayer(marker);
                    } else {
                        map.removeLayer(marker);
                    }
                } catch (error) {
                    console.warn('خطأ في إزالة نقطة:', error);
                }
            });
            markers = [];
        }

        // تحديث عداد المرافق
        function updateFacilityCount(count) {
            document.getElementById('facilityCount').textContent = count;
        }

        // إظهار/إخفاء شاشة التحميل
        function showLoading(show) {
            const overlay = document.getElementById('loadingOverlay');
            if (show) {
                overlay.classList.remove('hidden');
            } else {
                overlay.classList.add('hidden');
            }
        }

        // العثور على موقع المستخدم
        function findMyLocation() {
            if (!navigator.geolocation) {
                alert(MAP_CONFIG.language === 'ar' ? 
                    'المتصفح لا يدعم تحديد الموقع الجغرافي' : 
                    'Geolocation is not supported by this browser');
                return;
            }
            
            showLoading(true);
            
            const options = {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 60000
            };
            
            navigator.geolocation.getCurrentPosition(
                position => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    console.log('User location found:', lat, lng);
                    
                    // إضافة نقطة موقع المستخدم
                    if (userLocationMarker) {
                        map.removeLayer(userLocationMarker);
                    }
                    
                    userLocationMarker = L.marker([lat, lng], {
                        icon: L.divIcon({
                            html: '<div style="background-color: #4285f4; border: 3px solid white; border-radius: 50%; width: 24px; height: 24px; box-shadow: 0 2px 5px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">📍</div>',
                            className: '',
                            iconSize: [24, 24],
                            iconAnchor: [12, 12]
                        })
                    }).addTo(map);
                    
                    userLocationMarker.bindPopup(MAP_CONFIG.language === 'ar' ? 
                        `<b>موقعك الحالي</b><br>خط العرض: ${lat.toFixed(6)}<br>خط الطول: ${lng.toFixed(6)}` : 
                        `<b>Your current location</b><br>Latitude: ${lat.toFixed(6)}<br>Longitude: ${lng.toFixed(6)}`
                    );
                    
                    // التوجه إلى موقع المستخدم بزوم مناسب
                    map.setView([lat, lng], 16);
                    
                    // إظهار النافذة المنبثقة
                    userLocationMarker.openPopup();
                    
                    showLoading(false);
                },
                error => {
                    showLoading(false);
                    
                    let errorMessage = '';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage = MAP_CONFIG.language === 'ar' ? 
                                'تم رفض طلب تحديد الموقع. يرجى السماح بالوصول للموقع من إعدادات المتصفح.' : 
                                'Location access denied. Please allow location access in browser settings.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage = MAP_CONFIG.language === 'ar' ? 
                                'معلومات الموقع غير متوفرة.' : 
                                'Location information is unavailable.';
                            break;
                        case error.TIMEOUT:
                            errorMessage = MAP_CONFIG.language === 'ar' ? 
                                'انتهت مهلة طلب تحديد الموقع.' : 
                                'Location request timed out.';
                            break;
                        default:
                            errorMessage = MAP_CONFIG.language === 'ar' ? 
                                'حدث خطأ غير معروف أثناء تحديد الموقع.' : 
                                'An unknown error occurred while retrieving location.';
                            break;
                    }
                    
                    console.error('Geolocation error:', error);
                    alert(errorMessage);
                },
                options
            );
        }

        // إعادة تعيين عرض الخريطة
        function resetMapView() {
            // إذا كانت هناك مرافق محملة، اعرضها
            if (allFacilities && allFacilities.length > 0) {
                autoFitMapToFacilities(allFacilities);
            } else {
                // إذا لم تكن هناك مرافق، حمل المرافق أولاً
                loadFacilities().then(() => {
                    if (allFacilities && allFacilities.length > 0) {
                        autoFitMapToFacilities(allFacilities);
                    } else {
                        // كحل أخير، استخدم الإعدادات الافتراضية
                        map.setView(MAP_CONFIG.center, MAP_CONFIG.zoom);
                    }
                });
            }
        }

        // الحصول على الاتجاهات
        function getDirections(lat, lng) {
            const url = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
            window.open(url, '_blank');
        }

        // تهيئة الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 بدء تهيئة الصفحة...');
            
            // التحقق من تحميل Leaflet
            if (typeof L === 'undefined') {
                console.error('❌ Leaflet لم يتم تحميله!');
                alert('خطأ في تحميل مكتبة الخريطة. يرجى تحديث الصفحة.');
                return;
            }
            
            console.log('✅ Leaflet محمّل بنجاح');
            
            // تهيئة الخريطة
            try {
                initMap();
            } catch (error) {
                console.error('❌ خطأ في تهيئة الخريطة:', error);
                alert('حدث خطأ في تحميل الخريطة. يرجى تحديث الصفحة.');
            }
            
            // ربط البحث بالضغط على Enter
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        searchFacilities();
                    }
                });
            }

            // إغلاق النافذة المنبثقة عند النقر خارجها
            const facilityModal = document.getElementById('facilityModal');
            if (facilityModal) {
                facilityModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeFacilityModal();
                    }
                });
            }
        });
    </script>

    <?php require_once 'includes/footer.php'; ?>
</body>
</html> 