<?php
/**
 * جلب بيانات المواطن برمز الدخول
 */

header('Content-Type: application/json');

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // الحصول على رمز الدخول
    $accessCode = isset($_POST['access_code']) ? trim($_POST['access_code']) : '';
    
    if (empty($accessCode)) {
        echo json_encode([
            'success' => false,
            'message' => 'الرجاء إدخال رمز الدخول'
        ]);
        exit;
    }
    
    // البحث عن المواطن برمز الدخول
    $stmt = $db->prepare("
        SELECT 
            id,
            name,
            phone,
            email,
            national_id,
            address,
            telegram_chat_id,
            telegram_username,
            permanent_access_code,
            created_at
        FROM citizens_accounts 
        WHERE permanent_access_code = ?
    ");
    
    $stmt->execute([$accessCode]);
    $citizen = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($citizen) {
        // تم العثور على المواطن
        echo json_encode([
            'success' => true,
            'name' => $citizen['name'],
            'phone' => $citizen['phone'],
            'email' => $citizen['email'],
            'national_id' => $citizen['national_id'],
            'address' => $citizen['address'],
            'telegram_linked' => !empty($citizen['telegram_chat_id']),
            'telegram_username' => $citizen['telegram_username'],
            'access_code' => $citizen['permanent_access_code'],
            'created_at' => $citizen['created_at']
        ]);
    } else {
        // رمز الدخول غير صحيح
        echo json_encode([
            'success' => false,
            'message' => 'رمز الدخول غير صحيح. الرجاء التأكد من الرمز أو الضغط على "تخطى" إذا كانت هذه أول مرة.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Get Citizen By Code Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ، الرجاء المحاولة مرة أخرى'
    ]);
}
?>

