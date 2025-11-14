<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../config/database.php';
    
    $employee_id = intval($_GET['id'] ?? 0);
    $full_data = isset($_GET['full_data']);
    
    if ($employee_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'معرف الموظف مطلوب']);
        exit();
    }
    
    $database = new Database();
    $db = $database->getConnection();
    $db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    if ($full_data) {
        // جلب البيانات الكاملة للتعديل
        $stmt = $db->prepare("
            SELECT u.*, 
                   COALESCE(d.department_name, 'غير محدد') as department_name,
                   COALESCE(p.position_name, 'غير محدد') as position_name,
                   COALESCE(ut.type_name, 'غير محدد') as user_type_name,
                   COALESCE(ct.type_name, 'غير محدد') as contract_type_name
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id  
            LEFT JOIN positions p ON u.position_id = p.id
            LEFT JOIN user_types ut ON u.user_type_id = ut.id
            LEFT JOIN contract_types ct ON u.contract_type_id = ct.id
            WHERE u.id = ?
        ");
    } else {
        // جلب البيانات للعرض فقط  
        $stmt = $db->prepare("
            SELECT u.full_name, u.salary, u.is_active,
                   'ل.ل' as currency_symbol,
                   COALESCE(d.department_name, 'غير محدد') as department,
                   COALESCE(p.position_name, 'غير محدد') as position,
                   COALESCE(ct.type_name, 'غير محدد') as contract_type
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN positions p ON u.position_id = p.id
            LEFT JOIN contract_types ct ON u.contract_type_id = ct.id
            WHERE u.id = ?
        ");
    }
    
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        echo json_encode(['success' => false, 'message' => 'الموظف غير موجود']);
        exit();
    }
    
    // تنسيق الراتب للعرض إذا لم تكن بيانات كاملة
    if (!$full_data && $employee['salary']) {
        $employee['salary'] = number_format($employee['salary'], 0) . ' ' . $employee['currency_symbol'];
    }
    
    echo json_encode([
        'success' => true, 
        'employee' => $employee
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_employee.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'خطأ في جلب البيانات: ' . $e->getMessage()
    ]);
}
?> 
