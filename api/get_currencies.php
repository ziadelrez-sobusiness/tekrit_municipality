<?php
/**
 * API endpoint to get active currencies
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->query("SELECT id, currency_name as name, currency_symbol as symbol, currency_code as code FROM currencies WHERE is_active = 1 ORDER BY currency_code");
    $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'currencies' => $currencies
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

