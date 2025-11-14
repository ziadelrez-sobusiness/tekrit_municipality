<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->exec("SET NAMES utf8mb4");
    
    if (!isset($_GET['type_id'])) {
        throw new Exception('معرف نوع الطلب مطلوب');
    }
    
    $type_id = $_GET['type_id'];
    
    // جلب معلومات نوع الطلب
    $stmt = $db->prepare("SELECT * FROM request_types WHERE id = ? AND is_active = 1");
    $stmt->execute([$type_id]);
    $request_type = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request_type) {
        throw new Exception('نوع الطلب غير موجود أو غير نشط');
    }
    
    $response = [
        'success' => true,
        'type_info' => $request_type,
        'form_fields' => [],
        'required_documents' => []
    ];
    
    // معالجة حقول النموذج
    if (!empty($request_type['form_fields'])) {
        $form_fields = json_decode($request_type['form_fields'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($form_fields)) {
            $response['form_fields'] = $form_fields;
        }
    }
    
    // معالجة المستندات المطلوبة
    if (!empty($request_type['required_documents'])) {
        $documents = explode("\n", $request_type['required_documents']);
        $response['required_documents'] = array_filter(array_map('trim', $documents));
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

