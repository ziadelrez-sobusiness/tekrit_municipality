<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/currency_helper.php';

$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

$employee_id = intval($_GET['id'] ?? 0);
$full_data = isset($_GET['full_data']);

if ($employee_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'معرف الموظف غير صحيح']);
    exit();
}

try {
    if ($full_data) {
        $stmt = $db->prepare("
            SELECT u.*, 
                   COALESCE(c.currency_symbol, 'ل.ل') as currency_symbol, 
                   COALESCE(c.currency_name, 'ليرة لبنانية') as currency_name,
                   COALESCE(d.department_name, 'غير محدد') as department_name,
                   COALESCE(p.position_name, 'غير محدد') as position_name,
                   COALESCE(ut.type_name, 'غير محدد') as user_type_name,
                   COALESCE(ct.type_name, 'غير محدد') as contract_type_name
            FROM users u
            LEFT JOIN currencies c ON u.salary_currency_id = c.id
            LEFT JOIN departments d ON u.department_id = d.id  
            LEFT JOIN positions p ON u.position_id = p.id
            LEFT JOIN user_types ut ON u.user_type_id = ut.id
            LEFT JOIN contract_types ct ON u.contract_type_id = ct.id
            WHERE u.id = ?
        ");
    } else {
        $stmt = $db->prepare("
            SELECT u.full_name, u.salary, u.is_active,
                   COALESCE(c.currency_symbol, 'ل.ل') as currency_symbol,
                   COALESCE(d.department_name, 'غير محدد') as department,
                   COALESCE(p.position_name, 'غير محدد') as position,
                   COALESCE(ct.type_name, 'غير محدد') as contract_type
            FROM users u
            LEFT JOIN currencies c ON u.salary_currency_id = c.id
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
    
    if (!$full_data) {
        $employee['salary'] = formatCurrency($employee['salary'], null, $employee['currency_symbol']);
    }
    
    echo json_encode([
        'success' => true, 
        'employee' => $employee
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_employee.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'خطأ في جلب البيانات: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General error in get_employee.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'خطأ عام: ' . $e->getMessage()
    ]);
}
?>
