<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$response = [];

try {
    switch ($method) {
        case 'GET':
            // جلب المعاملات المالية
            $sql = "
                SELECT 
                    ft.id,
                    ft.type,
                    ft.amount,
                    c.symbol as currency_symbol,
                    ft.transaction_date,
                    ft.description,
                    rd.value as category_name,
                    d.name as department_name
                FROM financial_transactions ft
                LEFT JOIN currencies c ON ft.currency_id = c.id
                LEFT JOIN reference_data rd ON ft.category_id = rd.id
                LEFT JOIN departments d ON ft.department_id = d.id
                ORDER BY ft.transaction_date DESC
                LIMIT 50
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $transactions = $stmt->fetchAll();
            
            $response = [
                'success' => true,
                'data' => $transactions
            ];
            break;
            
        case 'POST':
            // إضافة معاملة مالية جديدة
            $input = json_decode(file_get_contents('php://input'), true);
            
            $sql = "
                INSERT INTO financial_transactions 
                (type, amount, currency_id, transaction_date, description, category_id, department_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $db->prepare($sql);
            $success = $stmt->execute([
                $input['type'],
                $input['amount'],
                $input['currency_id'],
                $input['transaction_date'],
                $input['description'],
                $input['category_id'],
                $input['department_id'] ?? null
            ]);
            
            if ($success) {
                $response = [
                    'success' => true,
                    'message' => 'تم إضافة القيد المالي بنجاح'
                ];
            } else {
                $response = ['error' => 'فشل في إضافة القيد المالي'];
            }
            break;
            
        default:
            http_response_code(405);
            $response = ['error' => 'طريقة غير مدعومة'];
    }
} catch (Exception $e) {
    http_response_code(500);
    $response = ['error' => 'خطأ في الخادم: ' . $e->getMessage()];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?> 
