<?php
header('Content-Type: application/json');
require_once '../includes/auth.php';
require_once '../config/database.php';


if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'معرف المبادرة مطلوب']);
    exit;
}

$initiative_id = $_GET['id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        echo json_encode(['error' => 'فشل في الاتصال بقاعدة البيانات']);
        exit;
    }
    
    $stmt = $db->prepare("SELECT * FROM youth_environmental_initiatives WHERE id = ?");
    $stmt->execute([$initiative_id]);
    $initiative = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$initiative) {
        echo json_encode(['error' => 'المبادرة غير موجودة']);
        exit;
    }
    
    echo json_encode($initiative);
} catch (Exception $e) {
    echo json_encode(['error' => 'خطأ في جلب بيانات المبادرة: ' . $e->getMessage()]);
}
?> 