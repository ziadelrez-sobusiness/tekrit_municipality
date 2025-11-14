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
    echo json_encode(['error' => 'معرف الصورة مطلوب']);
    exit();
}

$image_id = (int)$_GET['id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->exec('SET NAMES utf8mb4');
    
    // التحقق من وجود جدول الصور
    $table_check = $db->query("SHOW TABLES LIKE 'initiative_images'")->fetch();
    
    if (!$table_check) {
        echo json_encode(['error' => 'جدول الصور غير موجود']);
        exit();
    }
    
    // جلب معلومات الصورة
    $stmt = $db->prepare("SELECT image_path FROM initiative_images WHERE id = ?");
    $stmt->execute([$image_id]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$image) {
        http_response_code(404);
        echo json_encode(['error' => 'الصورة غير موجودة']);
        exit();
    }
    
    // حذف الصورة من قاعدة البيانات
    $delete_stmt = $db->prepare("DELETE FROM initiative_images WHERE id = ?");
    $delete_stmt->execute([$image_id]);
    
    // حذف الملف من الخادم
    $file_path = '../public/' . $image['image_path'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    echo json_encode(['success' => true, 'message' => 'تم حذف الصورة بنجاح']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'خطأ في الخادم: ' . $e->getMessage()]);
}
?>