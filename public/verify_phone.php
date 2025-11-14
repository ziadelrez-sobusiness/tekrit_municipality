<?php
/**
 * التحقق من رقم الهاتف
 * يتحقق من وجود الرقم في قاعدة البيانات وما إذا كان مربوطاً بـ Telegram
 */

header('Content-Type: application/json');

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // الحصول على رقم الهاتف من الطلب
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    
    if (empty($phone)) {
        echo json_encode(['exists' => false, 'linked' => false]);
        exit;
    }
    
    // البحث عن الرقم في قاعدة البيانات
    $stmt = $db->prepare("
        SELECT 
            id,
            name,
            telegram_chat_id,
            telegram_username,
            permanent_access_code,
            (SELECT COUNT(*) FROM citizen_requests WHERE citizen_phone = ?) as total_requests
        FROM citizens_accounts 
        WHERE phone = ?
    ");
    
    $stmt->execute([$phone, $phone]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($account) {
        // الرقم موجود
        $isLinked = !empty($account['telegram_chat_id']);
        
        echo json_encode([
            'exists' => true,
            'linked' => $isLinked,
            'name' => $account['name'],
            'telegram_username' => $account['telegram_username'],
            'access_code' => $account['permanent_access_code'],
            'total_requests' => (int)$account['total_requests']
        ]);
    } else {
        // الرقم غير موجود
        echo json_encode(['exists' => false, 'linked' => false]);
    }
    
} catch (Exception $e) {
    error_log("Phone Verification Error: " . $e->getMessage());
    echo json_encode(['exists' => false, 'linked' => false, 'error' => $e->getMessage()]);
}
?>

