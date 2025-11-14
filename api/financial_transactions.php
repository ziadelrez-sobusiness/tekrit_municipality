<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';
require_once '../includes/auth.php';

// التحقق من المصادقة
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'غير مصرح بالوصول']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$response = [];

try {
    switch ($method) {
        case 'GET':
            handleGet($db, $response);
            break;
        case 'POST':
            handlePost($db, $response);
            break;
        case 'PUT':
            handlePut($db, $response);
            break;
        case 'DELETE':
            handleDelete($db, $response);
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

function handleGet($db, &$response) {
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 50;
    $type = $_GET['type'] ?? null;
    $department_id = $_GET['department_id'] ?? null;
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;
    
    $offset = ($page - 1) * $limit;
    
    // بناء الاستعلام مع التصفية
    $sql = "
        SELECT 
            ft.id,
            ft.type,
            ft.amount,
            c.code as currency_code,
            c.symbol as currency_symbol,
            ft.transaction_date,
            ft.description,
            rd.value as category_name,
            d.name as department_name,
            u.full_name as recorded_by,
            ft.created_at
        FROM financial_transactions ft
        LEFT JOIN currencies c ON ft.currency_id = c.id
        LEFT JOIN reference_data rd ON ft.category_id = rd.id
        LEFT JOIN departments d ON ft.department_id = d.id
        LEFT JOIN users u ON ft.recorded_by_user_id = u.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($type) {
        $sql .= " AND ft.type = ?";
        $params[] = $type;
    }
    
    if ($department_id) {
        $sql .= " AND ft.department_id = ?";
        $params[] = $department_id;
    }
    
    if ($start_date) {
        $sql .= " AND DATE(ft.transaction_date) >= ?";
        $params[] = $start_date;
    }
    
    if ($end_date) {
        $sql .= " AND DATE(ft.transaction_date) <= ?";
        $params[] = $end_date;
    }
    
    $sql .= " ORDER BY ft.transaction_date DESC, ft.id DESC LIMIT ? OFFSET ?";
    $params[] = (int)$limit;
    $params[] = (int)$offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
    
    // حساب إجمالي السجلات
    $countSql = "SELECT COUNT(*) as total FROM financial_transactions ft WHERE 1=1";
    $countParams = [];
    
    if ($type) {
        $countSql .= " AND ft.type = ?";
        $countParams[] = $type;
    }
    
    if ($department_id) {
        $countSql .= " AND ft.department_id = ?";
        $countParams[] = $department_id;
    }
    
    if ($start_date) {
        $countSql .= " AND DATE(ft.transaction_date) >= ?";
        $countParams[] = $start_date;
    }
    
    if ($end_date) {
        $countSql .= " AND DATE(ft.transaction_date) <= ?";
        $countParams[] = $end_date;
    }
    
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($countParams);
    $total = $countStmt->fetch()['total'];
    
    // إحصائيات مالية سريعة
    $statsSql = "
        SELECT 
            SUM(CASE WHEN ft.type = 'revenue' THEN ft.amount * c.exchange_rate_to_lbp ELSE 0 END) as total_revenue_lbp,
            SUM(CASE WHEN ft.type = 'expense' THEN ft.amount * c.exchange_rate_to_lbp ELSE 0 END) as total_expense_lbp,
            COUNT(CASE WHEN ft.type = 'revenue' THEN 1 END) as revenue_count,
            COUNT(CASE WHEN ft.type = 'expense' THEN 1 END) as expense_count
        FROM financial_transactions ft
        LEFT JOIN currencies c ON ft.currency_id = c.id
        WHERE 1=1
    ";
    
    $statsParams = [];
    if ($start_date) {
        $statsSql .= " AND DATE(ft.transaction_date) >= ?";
        $statsParams[] = $start_date;
    }
    
    if ($end_date) {
        $statsSql .= " AND DATE(ft.transaction_date) <= ?";
        $statsParams[] = $end_date;
    }
    
    $statsStmt = $db->prepare($statsSql);
    $statsStmt->execute($statsParams);
    $stats = $statsStmt->fetch();
    
    $response = [
        'success' => true,
        'data' => $transactions,
        'pagination' => [
            'current_page' => (int)$page,
            'total_records' => (int)$total,
            'records_per_page' => (int)$limit,
            'total_pages' => ceil($total / $limit)
        ],
        'statistics' => [
            'total_revenue_lbp' => number_format($stats['total_revenue_lbp'] ?? 0, 2),
            'total_expense_lbp' => number_format($stats['total_expense_lbp'] ?? 0, 2),
            'net_balance_lbp' => number_format(($stats['total_revenue_lbp'] ?? 0) - ($stats['total_expense_lbp'] ?? 0), 2),
            'revenue_count' => (int)($stats['revenue_count'] ?? 0),
            'expense_count' => (int)($stats['expense_count'] ?? 0)
        ]
    ];
}

function handlePost($db, &$response) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // التحقق من صحة البيانات
    $required_fields = ['type', 'amount', 'currency_id', 'transaction_date', 'category_id'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            http_response_code(400);
            $response = ['error' => "الحقل '$field' مطلوب"];
            return;
        }
    }
    
    // التحقق من نوع المعاملة
    if (!in_array($input['type'], ['revenue', 'expense'])) {
        http_response_code(400);
        $response = ['error' => 'نوع المعاملة يجب أن يكون إيراد أو مصروف'];
        return;
    }
    
    // التحقق من صحة المبلغ
    if (!is_numeric($input['amount']) || $input['amount'] <= 0) {
        http_response_code(400);
        $response = ['error' => 'المبلغ يجب أن يكون رقماً موجباً'];
        return;
    }
    
    // التحقق من صحة العملة
    $currencyStmt = $db->prepare("SELECT id FROM currencies WHERE id = ? AND is_active = 1");
    $currencyStmt->execute([$input['currency_id']]);
    if (!$currencyStmt->fetch()) {
        http_response_code(400);
        $response = ['error' => 'العملة المحددة غير صالحة'];
        return;
    }
    
    // التحقق من صحة الفئة
    $categoryStmt = $db->prepare("SELECT id FROM reference_data WHERE id = ? AND type LIKE '%_category' AND is_active = 1");
    $categoryStmt->execute([$input['category_id']]);
    if (!$categoryStmt->fetch()) {
        http_response_code(400);
        $response = ['error' => 'فئة المعاملة غير صالحة'];
        return;
    }
    
    // الحصول على معرف المستخدم الحالي
    $auth = new Auth();
    $current_user = $auth->getCurrentUser();
    
    // إدراج القيد المالي
    $sql = "
        INSERT INTO financial_transactions 
        (type, amount, currency_id, transaction_date, description, category_id, department_id, recorded_by_user_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ";
    
    $stmt = $db->prepare($sql);
    $success = $stmt->execute([
        $input['type'],
        $input['amount'],
        $input['currency_id'],
        $input['transaction_date'],
        $input['description'] ?? '',
        $input['category_id'],
        $input['department_id'] ?? null,
        $current_user['id']
    ]);
    
    if ($success) {
        $transaction_id = $db->lastInsertId();
        
        // جلب تفاصيل المعاملة المضافة
        $getStmt = $db->prepare("
            SELECT 
                ft.id,
                ft.type,
                ft.amount,
                c.code as currency_code,
                c.symbol as currency_symbol,
                ft.transaction_date,
                ft.description,
                rd.value as category_name,
                d.name as department_name,
                u.full_name as recorded_by,
                ft.created_at
            FROM financial_transactions ft
            LEFT JOIN currencies c ON ft.currency_id = c.id
            LEFT JOIN reference_data rd ON ft.category_id = rd.id
            LEFT JOIN departments d ON ft.department_id = d.id
            LEFT JOIN users u ON ft.recorded_by_user_id = u.id
            WHERE ft.id = ?
        ");
        $getStmt->execute([$transaction_id]);
        $transaction = $getStmt->fetch();
        
        $response = [
            'success' => true,
            'message' => 'تم إضافة القيد المالي بنجاح',
            'data' => $transaction
        ];
    } else {
        http_response_code(500);
        $response = ['error' => 'فشل في إضافة القيد المالي'];
    }
}

function handlePut($db, &$response) {
    // استخراج ID من URL
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $pathParts = explode('/', $path);
    $id = end($pathParts);
    
    if (!is_numeric($id)) {
        http_response_code(400);
        $response = ['error' => 'معرف المعاملة غير صالح'];
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // التحقق من وجود المعاملة
    $checkStmt = $db->prepare("SELECT id FROM financial_transactions WHERE id = ?");
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        http_response_code(404);
        $response = ['error' => 'المعاملة المالية غير موجودة'];
        return;
    }
    
    // بناء استعلام التحديث
    $updateFields = [];
    $params = [];
    
    if (isset($input['amount']) && is_numeric($input['amount']) && $input['amount'] > 0) {
        $updateFields[] = "amount = ?";
        $params[] = $input['amount'];
    }
    
    if (isset($input['description'])) {
        $updateFields[] = "description = ?";
        $params[] = $input['description'];
    }
    
    if (isset($input['transaction_date'])) {
        $updateFields[] = "transaction_date = ?";
        $params[] = $input['transaction_date'];
    }
    
    if (empty($updateFields)) {
        http_response_code(400);
        $response = ['error' => 'لا توجد حقول للتحديث'];
        return;
    }
    
    $sql = "UPDATE financial_transactions SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $params[] = $id;
    
    $stmt = $db->prepare($sql);
    $success = $stmt->execute($params);
    
    if ($success) {
        $response = [
            'success' => true,
            'message' => 'تم تحديث القيد المالي بنجاح'
        ];
    } else {
        http_response_code(500);
        $response = ['error' => 'فشل في تحديث القيد المالي'];
    }
}

function handleDelete($db, &$response) {
    // استخراج ID من URL
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $pathParts = explode('/', $path);
    $id = end($pathParts);
    
    if (!is_numeric($id)) {
        http_response_code(400);
        $response = ['error' => 'معرف المعاملة غير صالح'];
        return;
    }
    
    // التحقق من وجود المعاملة
    $checkStmt = $db->prepare("SELECT id FROM financial_transactions WHERE id = ?");
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        http_response_code(404);
        $response = ['error' => 'المعاملة المالية غير موجودة'];
        return;
    }
    
    // حذف المعاملة
    $stmt = $db->prepare("DELETE FROM financial_transactions WHERE id = ?");
    $success = $stmt->execute([$id]);
    
    if ($success) {
        $response = [
            'success' => true,
            'message' => 'تم حذف القيد المالي بنجاح'
        ];
    } else {
        http_response_code(500);
        $response = ['error' => 'فشل في حذف القيد المالي'];
    }
}
?> 
