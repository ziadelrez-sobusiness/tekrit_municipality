<?php
header('Content-Type: application/json; charset=utf-8');

try {
    // Database connection
    $db_host = 'localhost';
    $db_name = 'tekrit_municipality';
    $db_user = 'root';
    $db_pass = '';

    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    $accessCode = $input['access_code'] ?? '';
    $fullName = trim($input['full_name'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $email = trim($input['email'] ?? '');
    $nationalId = trim($input['national_id'] ?? '');
    $address = trim($input['address'] ?? '');

    if (empty($accessCode)) {
        throw new Exception('رمز الدخول مطلوب');
    }

    if (empty($fullName) || empty($phone)) {
        throw new Exception('الاسم ورقم الهاتف مطلوبان');
    }

    // Find citizen by access code
    $stmt = $pdo->prepare("
        SELECT id, phone 
        FROM citizens_accounts 
        WHERE permanent_access_code = ?
    ");
    $stmt->execute([$accessCode]);
    $citizen = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$citizen) {
        throw new Exception('رمز الدخول غير صحيح');
    }

    // التحقق من أن رقم الهاتف الجديد غير محجوز لمواطن آخر
    if ($phone !== $citizen['phone']) {
        $checkStmt = $pdo->prepare("
            SELECT id, name 
            FROM citizens_accounts 
            WHERE phone = ? AND id != ?
        ");
        $checkStmt->execute([$phone, $citizen['id']]);
        $existingPhone = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingPhone) {
            throw new Exception('رقم الهاتف مسجّل مسبقاً لمواطن آخر (' . $existingPhone['name'] . ')');
        }
    }

    // Update citizen data
    $updateStmt = $pdo->prepare("
        UPDATE citizens_accounts 
        SET 
            name = ?,
            phone = ?,
            email = ?,
            national_id = ?,
            address = ?
        WHERE id = ?
    ");

    $updateStmt->execute([
        $fullName,
        $phone,
        $email,
        $nationalId,
        $address,
        $citizen['id']
    ]);

    // Log the update
    error_log("✅ Citizen data updated - ID: {$citizen['id']}, Phone: {$phone}");

    echo json_encode([
        'success' => true,
        'message' => 'تم تحديث بياناتك بنجاح',
        'data' => [
            'full_name' => $fullName,
            'phone' => $phone,
            'email' => $email,
            'national_id' => $nationalId,
            'address' => $address
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("❌ Update citizen data error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

