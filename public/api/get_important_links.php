<?php
/**
 * API لجلب روابط مهمة
 * يمكن استخدامها لجلب البيانات بنقرة زر
 */

header('Content-Type: application/json; charset=utf-8');

// CORS headers للسماح بالوصول من أي مصدر
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// معالجة OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

$response = ['success' => false, 'data' => [], 'message' => ''];

try {
    $category_id = $_GET['category'] ?? null;
    $search = $_GET['search'] ?? '';
    $emergency_only = isset($_GET['emergency']) && $_GET['emergency'] == '1';
    
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
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    $query = "
        SELECT 
            il.*,
            ilc.name_ar as category_name_ar,
            ilc.name_en as category_name_en,
            ilc.icon as category_icon,
            ilc.color as category_color
        FROM important_links il
        INNER JOIN important_link_categories ilc ON il.category_id = ilc.id
        $where_clause
        ORDER BY il.is_emergency DESC, il.display_order ASC, il.name_ar ASC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب الفئات أيضاً
    $categories_query = "
        SELECT * FROM important_link_categories 
        WHERE is_active = 1 
        ORDER BY display_order ASC, name_ar ASC
    ";
    $categories_stmt = $db->query($categories_query);
    $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response = [
        'success' => true,
        'data' => [
            'links' => $links,
            'categories' => $categories
        ],
        'count' => count($links),
        'message' => 'تم جلب البيانات بنجاح'
    ];
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'data' => [],
        'message' => 'خطأ في جلب البيانات: ' . $e->getMessage()
    ];
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>

