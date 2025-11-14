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

    // Get input
    $phone = trim($_GET['phone'] ?? '');
    $currentAccessCode = trim($_GET['current_access_code'] ?? '');

    if (empty($phone)) {
        throw new Exception('رقم الهاتف مطلوب');
    }

    // البحث عن رقم الهاتف في قاعدة البيانات
    $stmt = $pdo->prepare("
        SELECT id, permanent_access_code, name 
        FROM citizens_accounts 
        WHERE phone = ?
    ");
    $stmt->execute([$phone]);
    $existingCitizen = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingCitizen) {
        // رقم الهاتف غير موجود - مسموح باستخدامه
        echo json_encode([
            'success' => true,
            'available' => true,
            'message' => 'رقم الهاتف متاح'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Debug logging
    error_log("=== CHECK PHONE OWNERSHIP DEBUG ===");
    error_log("Phone: " . $phone);
    error_log("Current Access Code (from request): '" . $currentAccessCode . "'");
    error_log("Existing Access Code (from DB): '" . $existingCitizen['permanent_access_code'] . "'");
    error_log("Citizen Name: " . $existingCitizen['name']);
    error_log("Empty check: " . (empty($currentAccessCode) ? 'YES' : 'NO'));
    error_log("Match check: " . ($existingCitizen['permanent_access_code'] === $currentAccessCode ? 'YES' : 'NO'));
    
    // رقم الهاتف موجود - نتحقق إذا كان للمواطن نفسه أم لا
    if (!empty($currentAccessCode) && $existingCitizen['permanent_access_code'] === $currentAccessCode) {
        // نفس المواطن - مسموح بالتعديل
        error_log("✅ Result: IS OWNER");
        echo json_encode([
            'success' => true,
            'available' => true,
            'is_owner' => true,
            'message' => 'رقم هاتفك الحالي'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    error_log("❌ Result: NOT OWNER (blocked)");

    // رقم الهاتف تابع لمواطن آخر - ممنوع استخدامه
    echo json_encode([
        'success' => true,
        'available' => false,
        'is_owner' => false,
        'message' => 'هذا الرقم مسجّل مسبقاً لمواطن آخر',
        'owner_name' => $existingCitizen['name']
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("❌ Check phone ownership error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

