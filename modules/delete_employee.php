<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../config/database.php';
    
    // قراءة البيانات من JSON
    $input = json_decode(file_get_contents('php://input'), true);
    $employee_id = intval($input['employee_id'] ?? 0);
    
    if ($employee_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'معرف الموظف مطلوب']);
        exit();
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    // التحقق من وجود الموظف
    $check_stmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
    $check_stmt->execute([$employee_id]);
    $employee = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        echo json_encode(['success' => false, 'message' => 'الموظف غير موجود']);
        exit();
    }
    
    // حذف الموظف
    $delete_stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $delete_stmt->execute([$employee_id]);
    
    if ($delete_stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true, 
            'message' => 'تم حذف الموظف بنجاح',
            'employee_name' => $employee['full_name']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل في حذف الموظف']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
}
?>
