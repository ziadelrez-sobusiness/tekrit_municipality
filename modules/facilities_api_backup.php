<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

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
            $limit = min(500, max(1, $_GET['limit'] ?? 100)); // حد أقصى 500 مرفق
            
            // بناء الاستعلام
            $where_conditions = ['f.is_active = 1', 'fc.is_active = 1'];
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
            if (!empty($category_id)) {
                $where_conditions[] = "f.category_id = ?";
                $params[] = $category_id;
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
                    fc.name_ar as category_name_ar,
                    fc.name_en as category_name_en,
                    fc.icon as category_icon,
                    fc.color as category_color
                FROM facilities f
                LEFT JOIN facility_categories fc ON f.category_id = fc.id
                WHERE $where_clause
                ORDER BY f.is_featured DESC, f.name_ar ASC
                LIMIT ?
            ";
            
            $params[] = $limit;
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // تحويل البيانات للتنسيق المطلوب
            $formatted_facilities = [];
            foreach ($facilities as $facility) {
                $formatted_facilities[] = [
                    'id' => intval($facility['id']),
                    'name_ar' => $facility['name_ar'],
                    'name_en' => $facility['name_en'],
                    'category_id' => intval($facility['category_id']),
                    'description_ar' => $facility['description_ar'],
                    'description_en' => $facility['description_en'],
                    'latitude' => floatval($facility['latitude']),
                    'longitude' => floatval($facility['longitude']),
                    'contact_person_ar' => $facility['contact_person_ar'],
                    'contact_person_en' => $facility['contact_person_en'],
                    'phone' => $facility['phone'],
                    'email' => $facility['email'],
                    'address_ar' => $facility['address_ar'],
                    'address_en' => $facility['address_en'],
                    'working_hours_ar' => $facility['working_hours_ar'],
                    'working_hours_en' => $facility['working_hours_en'],
                    'website' => $facility['website'],
                    'image_path' => $facility['image_path'],
                    'is_featured' => intval($facility['is_featured']),
                    'views_count' => intval($facility['views_count']),
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
            
            $query = "SELECT * FROM facility_categories WHERE is_active = 1 ORDER BY display_order, name_ar";
            $stmt = $db->query($query);
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['categories'] = $categories;
            break;
            
        case 'get_facility_details':
            $facility_id = $_GET['facility_id'] ?? $_POST['facility_id'] ?? 0;
            
            if (!$facility_id) {
                throw new Exception('معرف المرفق مطلوب');
            }
            
            $query = "
                SELECT 
                    f.*,
                    fc.name_ar as category_name_ar,
                    fc.name_en as category_name_en,
                    fc.icon as category_icon,
                    fc.color as category_color
                FROM facilities f
                LEFT JOIN facility_categories fc ON f.category_id = fc.id
                WHERE f.id = ? AND f.is_active = 1
            ";
            
            $stmt = $db->prepare($query);
            $stmt->execute([$facility_id]);
            $facility = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$facility) {
                throw new Exception('المرفق غير موجود');
            }
            
            // تحديث عداد المشاهدات
            $update_views = $db->prepare("UPDATE facilities SET views_count = views_count + 1 WHERE id = ?");
            $update_views->execute([$facility_id]);
            
            $response['success'] = true;
            $response['facility'] = $facility;
            break;
            
        case 'search_nearby':
            $lat = floatval($_GET['lat'] ?? 0);
            $lng = floatval($_GET['lng'] ?? 0);
            $radius = min(50, max(1, floatval($_GET['radius'] ?? 5))); // نطاق من 1 إلى 50 كم
            $category_id = $_GET['category'] ?? '';
            $limit = min(50, max(1, $_GET['limit'] ?? 20));
            
            if (!$lat || !$lng) {
                throw new Exception('الإحداثيات مطلوبة');
            }
            
            // حساب المرافق القريبة باستخدام Haversine formula
            $where_conditions = ['f.is_active = 1', 'fc.is_active = 1'];
            $params = [$lat, $lng, $lat];
            
            if (!empty($category_id)) {
                $where_conditions[] = "f.category_id = ?";
                $params[] = $category_id;
            }
            
            $params[] = $radius;
            $params[] = $limit;
            
            $where_clause = implode(' AND ', $where_conditions);
            
            $query = "
                SELECT 
                    f.*,
                    fc.name_ar as category_name_ar,
                    fc.name_en as category_name_en,
                    fc.icon as category_icon,
                    fc.color as category_color,
                    (6371 * acos(cos(radians(?)) * cos(radians(f.latitude)) * cos(radians(f.longitude) - radians(?)) + sin(radians(?)) * sin(radians(f.latitude)))) AS distance
                FROM facilities f
                LEFT JOIN facility_categories fc ON f.category_id = fc.id
                WHERE $where_clause
                HAVING distance <= ?
                ORDER BY distance ASC, f.is_featured DESC
                LIMIT ?
            ";
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['facilities'] = $facilities;
            $response['count'] = count($facilities);
            break;
            
        case 'get_map_settings':
            $query = "SELECT setting_name, setting_value FROM map_settings WHERE is_public = 1";
            $stmt = $db->query($query);
            $settings_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $settings = [];
            foreach ($settings_result as $setting) {
                $settings[$setting['setting_name']] = $setting['setting_value'];
            }
            
            $response['success'] = true;
            $response['settings'] = $settings;
            break;
            
        case 'add_rating':
            $facility_id = $_POST['facility_id'] ?? 0;
            $rating = intval($_POST['rating'] ?? 0);
            $comment = trim($_POST['comment'] ?? '');
            $user_name = trim($_POST['user_name'] ?? '');
            $user_email = trim($_POST['user_email'] ?? '');
            
            // التحقق من صحة البيانات
            if (!$facility_id || $rating < 1 || $rating > 5) {
                throw new Exception('بيانات التقييم غير صحيحة');
            }
            
            // التحقق من وجود المرفق
            $facility_check = $db->prepare("SELECT id FROM facilities WHERE id = ? AND is_active = 1");
            $facility_check->execute([$facility_id]);
            if (!$facility_check->fetch()) {
                throw new Exception('المرفق غير موجود');
            }
            
            // إضافة التقييم
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $auto_approve = $db->query("SELECT setting_value FROM map_settings WHERE setting_name = 'auto_approve_ratings'")->fetchColumn();
            $is_approved = $auto_approve == '1' ? 1 : 0;
            
            $insert_rating = $db->prepare("
                INSERT INTO facility_ratings (facility_id, user_name, user_email, rating, comment, is_approved, ip_address)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $insert_rating->execute([
                $facility_id, 
                $user_name, 
                $user_email, 
                $rating, 
                $comment, 
                $is_approved, 
                $ip_address
            ]);
            
            $response['success'] = true;
            $response['message'] = $is_approved ? 'تم إضافة التقييم بنجاح' : 'تم إرسال التقييم وهو في انتظار المراجعة';
            break;
            
        case 'get_statistics':
            $stats = [];
            
            // إحصائيات عامة
            $stats['total_facilities'] = $db->query("SELECT COUNT(*) FROM facilities WHERE is_active = 1")->fetchColumn();
            $stats['total_categories'] = $db->query("SELECT COUNT(*) FROM facility_categories WHERE is_active = 1")->fetchColumn();
            $stats['featured_facilities'] = $db->query("SELECT COUNT(*) FROM facilities WHERE is_featured = 1 AND is_active = 1")->fetchColumn();
            $stats['total_views'] = $db->query("SELECT SUM(views_count) FROM facilities WHERE is_active = 1")->fetchColumn() ?: 0;
            
            // إحصائيات الفئات
            $category_stats = $db->query("
                SELECT 
                    fc.name_ar,
                    fc.name_en,
                    fc.color,
                    COUNT(f.id) as count
                FROM facility_categories fc
                LEFT JOIN facilities f ON fc.id = f.category_id AND f.is_active = 1
                WHERE fc.is_active = 1
                GROUP BY fc.id
                ORDER BY count DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            $stats['categories'] = $category_stats;
            
            $response['success'] = true;
            $response['statistics'] = $stats;
            break;
            
        default:
            throw new Exception('إجراء غير مدعوم');
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
    
    // تسجيل الخطأ
    error_log("Facilities API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}

// إرسال الاستجابة
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?> 