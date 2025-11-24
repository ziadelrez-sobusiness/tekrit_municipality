<?php
/**
 * API لجلب روابط مهمة
 * يستخدم في الصفحة العامة لجلب البيانات المحدثة
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// تحميل أنظمة الأمان
if (file_exists(__DIR__ . '/../../includes/auto_security.php')) {
    require_once __DIR__ . '/../../includes/auto_security.php';
}

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

try {
    // معلمات البحث والفلترة
    $category_id = isset($_GET['category']) ? intval($_GET['category']) : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $emergency_only = isset($_GET['emergency']) && $_GET['emergency'] == '1';

    // جلب الفئات النشطة
    $categories = [];
    $stmt = $db->query("
        SELECT * FROM important_link_categories
        WHERE is_active = 1
        ORDER BY display_order ASC, name_ar ASC
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // بناء استعلام الروابط
    $where_conditions = ["il.is_active = 1"];
    $params = [];

    if ($category_id) {
        $where_conditions[] = "il.category_id = ?";
        $params[] = $category_id;
    }

    if ($search) {
        $where_conditions[] = "(il.name_ar LIKE ? OR il.name_en LIKE ? OR il.description_ar LIKE ? OR il.phone LIKE ?)";
        $search_param = '%' . $search . '%';
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }

    if ($emergency_only) {
        $where_conditions[] = "il.is_emergency = 1";
    }

    $where_clause = implode(' AND ', $where_conditions);

    // جلب الروابط
    $query = "
        SELECT il.*,
               ilc.name_ar as category_name_ar,
               ilc.name_en as category_name_en,
               ilc.icon as category_icon,
               ilc.color as category_color
        FROM important_links il
        INNER JOIN important_link_categories ilc ON il.category_id = ilc.id
        WHERE $where_clause
        ORDER BY il.is_emergency DESC, il.display_order ASC, il.name_ar ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // تحويل البيانات للأرقام الصحيحة
    foreach ($links as &$link) {
        $link['id'] = (int)$link['id'];
        $link['category_id'] = (int)$link['category_id'];
        $link['is_government'] = (int)$link['is_government'];
        $link['is_emergency'] = (int)$link['is_emergency'];
        $link['is_active'] = (int)$link['is_active'];
        $link['display_order'] = (int)$link['display_order'];

        if ($link['location_lat']) {
            $link['location_lat'] = (float)$link['location_lat'];
        }
        if ($link['location_lng']) {
            $link['location_lng'] = (float)$link['location_lng'];
        }
    }

    // تحويل البيانات للفئات
    foreach ($categories as &$category) {
        $category['id'] = (int)$category['id'];
        $category['is_active'] = (int)$category['is_active'];
        $category['display_order'] = (int)$category['display_order'];
    }

    // إحصائيات
    $stats = [
        'total_links' => count($links),
        'active_links' => count(array_filter($links, function($l) { return $l['is_active'] == 1; })),
        'emergency_links' => count(array_filter($links, function($l) { return $l['is_emergency'] == 1; })),
        'categories_count' => count($categories)
    ];

    // إرجاع النتيجة
    echo json_encode([
        'success' => true,
        'data' => [
            'links' => $links,
            'categories' => $categories,
            'stats' => $stats
        ],
        'message' => 'تم جلب البيانات بنجاح',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'message' => 'حدث خطأ في جلب البيانات',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
