<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/auth.php';
require_once '../config/database.php';

// التحقق من تسجيل الدخول
$auth->requireLogin();
if (!$auth->checkPermission('employee')) {
    http_response_code(403);
    echo json_encode(['error' => 'غير مصرح لك بالوصول']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'معرف المبادرة مطلوب']);
    exit();
}

$initiative_id = (int)$_GET['id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->exec('SET NAMES utf8mb4');
    
    // التحقق من وجود جدول الصور
    $table_check = $db->query("SHOW TABLES LIKE 'initiative_images'")->fetch();
    
    if (!$table_check) {
        // إذا لم يكن الجدول موجوداً، إرجاع مصفوفة فارغة
        echo json_encode([]);
        exit();
    }
    
    $stmt = $db->prepare("
        SELECT 
            id,
            image_path,
            image_name,
            image_description,
            image_type,
            display_order
        FROM initiative_images 
        WHERE initiative_id = ? AND is_active = 1
        ORDER BY 
            CASE image_type 
                WHEN 'رئيسية' THEN 1 
                ELSE 2 
            END,
            display_order ASC, 
            created_at ASC
    ");
    
    $stmt->execute([$initiative_id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تحويل المسارات النسبية إلى مسارات كاملة
    foreach ($images as &$image) {
        if (!str_starts_with($image['image_path'], 'http')) {
            // إضافة المسار الأساسي للصور - تصحيح للمسار الصحيح
            if (!str_starts_with($image['image_path'], 'uploads/')) {
                $image['image_path'] = 'uploads/initiatives/' . $image['image_path'];
            }
        }
    }
    
    echo json_encode($images);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'خطأ في الخادم: ' . $e->getMessage()]);
}
?>