<?php
header('Content-Type: application/json');
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();

if (isset($_GET['username'])) {
    $username = trim($_GET['username']);
    
    if (empty($username)) {
        echo json_encode(['available' => false, 'message' => 'اسم المستخدم مطلوب']);
        exit;
    }
    
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            echo json_encode([
                'available' => false, 
                'message' => 'اسم المستخدم موجود مسبقاً'
            ]);
        } else {
            echo json_encode([
                'available' => true, 
                'message' => 'اسم المستخدم متاح'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'available' => false, 
            'message' => 'خطأ في التحقق من اسم المستخدم'
        ]);
    }
} else {
    echo json_encode(['available' => false, 'message' => 'معطى غير صحيح']);
}
?> 
