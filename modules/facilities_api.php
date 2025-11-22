<?php
// تحميل ApiSecurity إذا كان موجوداً
$useApiSecurity = file_exists(__DIR__ . '/../includes/ApiSecurity.php');
if ($useApiSecurity) {
    require_once __DIR__ . '/../includes/ApiSecurity.php';
    $configFile = __DIR__ . '/../config/api_config.php';
    ApiSecurity::init(file_exists($configFile) ? $configFile : null);
    
    // التحقق من الأمان (API Key اختياري، Rate Limiting مفعّل)
    if (!ApiSecurity::validate(['require_api_key' => false, 'rate_limit' => true])) {
        exit; // ApiSecurity::validate() يرسل الاستجابة و يخرج
    }
} else {
    // Fallback للكود القديم
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST');
    header('Access-Control-Allow-Headers: Content-Type');
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

$response = ['success' => false, 'data' => null, 'error' => null];

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_facilities':
            $search = $_GET['search'] ?? '';
            $category_id = $_GET['category'] ?? '';
            $lang = $_GET['lang'] ?? 'ar';
            $limit = (int) min(500, max(1, $_GET['limit'] ?? 100));
            
            // بناء الاستعلام
            $where_conditions = ['f.is_active = 1'];
            $params = [];
            
            // فلتر البحث
            if (!empty($search)) {
                if ($lang == 'en') {
                    $where_conditions[] = "(f.name_en LIKE ? OR f.description_en LIKE ? OR f.contact_person_en LIKE ? OR f.address_en LIKE ?)";
                } else {
                    $where_conditions[] = "(f.name_ar LIKE ? OR f.description_ar LIKE ? OR f.contact_person_ar LIKE ? OR f.address_ar LIKE ?)";
                }
                $search_param = "%$search%";
                $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
            }
            
            // فلتر الفئة
            if (!empty($category_id) && is_numeric($category_id)) {
                $where_conditions[] = "f.category_id = ?";
                $params[] = (int) $category_id;
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            $query = "
                SELECT 
                    f.id,
                    f.name_ar,
                    f.name_en,
                    f.category_id,
                    f.description_ar,
                    f.description_en,
                    f.latitude,
                    f.longitude,
                    f.contact_person_ar,
                    f.contact_person_en,
                    f.phone,
                    f.email,
                    f.address_ar,
                    f.address_en,
                    f.working_hours_ar,
                    f.working_hours_en,
                    f.website,
                    f.image_path,
                    f.is_featured,
                    f.views_count,
                    COALESCE(fc.name_ar, 'غير محدد') as category_name_ar,
                    COALESCE(fc.name_en, 'Unspecified') as category_name_en,
                    COALESCE(fc.icon, '📍') as category_icon,
                    COALESCE(fc.color, '#666666') as category_color
                FROM facilities f
                LEFT JOIN facility_categories fc ON f.category_id = fc.id AND fc.is_active = 1
                WHERE $where_clause
                ORDER BY f.is_featured DESC, f.name_ar ASC
                LIMIT $limit
            ";
            
            $stmt = $db->prepare($query);
            
            if (!$stmt) {
                throw new Exception('خطأ في تحضير الاستعلام: ' . implode(', ', $db->errorInfo()));
            }
            
            $result = $stmt->execute($params);
            
            if (!$result) {
                throw new Exception('خطأ في تنفيذ الاستعلام: ' . implode(', ', $stmt->errorInfo()));
            }
            
            $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // تحويل البيانات للتنسيق المطلوب
            $formatted_facilities = [];
            foreach ($facilities as $facility) {
                $formatted_facilities[] = [
                    'id' => (int) $facility['id'],
                    'name_ar' => $facility['name_ar'] ?: '',
                    'name_en' => $facility['name_en'] ?: '',
                    'category_id' => (int) $facility['category_id'],
                    'description_ar' => $facility['description_ar'] ?: '',
                    'description_en' => $facility['description_en'] ?: '',
                    'latitude' => (float) $facility['latitude'],
                    'longitude' => (float) $facility['longitude'],
                    'contact_person_ar' => $facility['contact_person_ar'] ?: '',
                    'contact_person_en' => $facility['contact_person_en'] ?: '',
                    'phone' => $facility['phone'] ?: '',
                    'email' => $facility['email'] ?: '',
                    'address_ar' => $facility['address_ar'] ?: '',
                    'address_en' => $facility['address_en'] ?: '',
                    'working_hours_ar' => $facility['working_hours_ar'] ?: '',
                    'working_hours_en' => $facility['working_hours_en'] ?: '',
                    'website' => $facility['website'] ?: '',
                    'image_path' => $facility['image_path'] ?: '',
                    'is_featured' => (int) $facility['is_featured'],
                    'views_count' => (int) $facility['views_count'],
                    'category_name_ar' => $facility['category_name_ar'],
                    'category_name_en' => $facility['category_name_en'],
                    'category_icon' => $facility['category_icon'],
                    'category_color' => $facility['category_color']
                ];
            }
            
            $response['success'] = true;
            $response['facilities'] = $formatted_facilities;
            $response['count'] = count($formatted_facilities);
            break;
            
        case 'get_categories':
            $lang = $_GET['lang'] ?? 'ar';
            
            $query = "SELECT * FROM facility_categories WHERE is_active = 1 ORDER BY display_order ASC, name_ar ASC";
            $stmt = $db->query($query);
            
            if (!$stmt) {
                throw new Exception('خطأ في استعلام الفئات: ' . implode(', ', $db->errorInfo()));
            }
            
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['categories'] = $categories;
            break;
            
        case 'get_facility_details':
            $facility_id = (int) ($_GET['facility_id'] ?? $_POST['facility_id'] ?? 0);
            
            if (!$facility_id) {
                throw new Exception('معرف المرفق مطلوب');
            }
            
            $query = "
                SELECT 
                    f.*,
                    COALESCE(fc.name_ar, 'غير محدد') as category_name_ar,
                    COALESCE(fc.name_en, 'Unspecified') as category_name_en,
                    COALESCE(fc.icon, '📍') as category_icon,
                    COALESCE(fc.color, '#666666') as category_color
                FROM facilities f
                LEFT JOIN facility_categories fc ON f.category_id = fc.id AND fc.is_active = 1
                WHERE f.id = ? AND f.is_active = 1
            ";
            
            $stmt = $db->prepare($query);
            
            if (!$stmt) {
                throw new Exception('خطأ في تحضير استعلام تفاصيل المرفق');
            }
            
            $stmt->execute([$facility_id]);
            $facility = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$facility) {
                throw new Exception('المرفق غير موجود أو غير نشط');
            }
            
            // تحديث عداد المشاهدات
            $update_views = $db->prepare("UPDATE facilities SET views_count = views_count + 1 WHERE id = ?");
            $update_views->execute([$facility_id]);
            
            $response['success'] = true;
            $response['facility'] = $facility;
            break;
            
        case 'search_nearby':
            $lat = (float) ($_GET['lat'] ?? 0);
            $lng = (float) ($_GET['lng'] ?? 0);
            $radius = (float) min(50, max(1, $_GET['radius'] ?? 5));
            $category_id = $_GET['category'] ?? '';
            $limit = (int) min(50, max(1, $_GET['limit'] ?? 20));
            
            if (!$lat || !$lng) {
                throw new Exception('الإحداثيات مطلوبة للبحث القريب');
            }
            
            // حساب المرافق القريبة باستخدام Haversine formula
            $where_conditions = ['f.is_active = 1'];
            $params = [$lat, $lng, $lat];
            
            if (!empty($category_id) && is_numeric($category_id)) {
                $where_conditions[] = "f.category_id = ?";
                $params[] = (int) $category_id;
            }
            
            $params[] = $radius; // إضافة radius فقط
            
            $where_clause = implode(' AND ', $where_conditions);
            
            $query = "
                SELECT 
                    f.*,
                    COALESCE(fc.name_ar, 'غير محدد') as category_name_ar,
                    COALESCE(fc.name_en, 'Unspecified') as category_name_en,
                    COALESCE(fc.icon, '📍') as category_icon,
                    COALESCE(fc.color, '#666666') as category_color,
                    (6371 * acos(cos(radians(?)) * cos(radians(f.latitude)) * cos(radians(f.longitude) - radians(?)) + sin(radians(?)) * sin(radians(f.latitude)))) AS distance
                FROM facilities f
                LEFT JOIN facility_categories fc ON f.category_id = fc.id AND fc.is_active = 1
                WHERE $where_clause
                HAVING distance < ?
                ORDER BY distance ASC
                LIMIT $limit
            ";
            
            $stmt = $db->prepare($query);
            
            if (!$stmt) {
                throw new Exception('خطأ في تحضير استعلام البحث القريب');
            }
            
            $stmt->execute($params);
            $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['facilities'] = $facilities;
            $response['count'] = count($facilities);
            $response['search_params'] = [
                'latitude' => $lat,
                'longitude' => $lng,
                'radius' => $radius,
                'category_id' => $category_id
            ];
            break;
            
        case 'get_statistics':
            // إحصائيات المرافق
            $stats = [];
            
            // إجمالي المرافق
            $stmt = $db->query("SELECT COUNT(*) as total FROM facilities WHERE is_active = 1");
            $stats['total_facilities'] = (int) $stmt->fetch()['total'];
            
            // المرافق المميزة
            $stmt = $db->query("SELECT COUNT(*) as featured FROM facilities WHERE is_active = 1 AND is_featured = 1");
            $stats['featured_facilities'] = (int) $stmt->fetch()['featured'];
            
            // إحصائيات حسب الفئة
            $stmt = $db->query("
                SELECT 
                    fc.name_ar, fc.name_en, fc.icon, fc.color,
                    COUNT(f.id) as count
                FROM facility_categories fc
                LEFT JOIN facilities f ON fc.id = f.category_id AND f.is_active = 1
                WHERE fc.is_active = 1
                GROUP BY fc.id, fc.name_ar, fc.name_en, fc.icon, fc.color
                ORDER BY count DESC, fc.name_ar ASC
            ");
            $stats['by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['statistics'] = $stats;
            break;
            
        default:
            throw new Exception('إجراء غير صحيح أو غير مدعوم');
    }
    
} catch (Exception $e) {
    // استخدام ApiSecurity لإرسال الخطأ إذا كان متاحاً
    if ($useApiSecurity && class_exists('ApiSecurity')) {
        ApiSecurity::sendError($e->getMessage(), 500);
    } else {
        // Fallback للكود القديم
        $response['success'] = false;
        $response['error'] = $e->getMessage();
        $response['debug_info'] = [
            'action' => $action ?? 'غير محدد',
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'php_version' => PHP_VERSION
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    exit;
}

// إرسال الاستجابة
if ($useApiSecurity && class_exists('ApiSecurity')) {
    // ApiSecurity::sendSuccess() يغلف البيانات في 'data'، لكن نحتاج للبنية الأصلية
    // الحل: نمرر البيانات في additional parameter للحفاظ على البنية
    $dataToSend = $response;
    unset($dataToSend['success']); // إزالة success لأن ApiSecurity يضيفها تلقائياً
    
    // إرسال البيانات - additional parameters ستدمج مع response
    // النتيجة: {success: true, data: {...}, facilities: [...], count: ...}
    ApiSecurity::sendSuccess([], 200, $dataToSend);
} else {
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?> 