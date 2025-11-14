<?php
/**
 * ملف جلب تفاصيل الطلب مع جميع البيانات المرتبطة
 * يتضمن: معلومات الطلب، نوع الطلب، المستندات، البيانات الإضافية، التحديثات
 */

header('Content-Type: application/json; charset=utf-8');

// تضمين ملف الاتصال بقاعدة البيانات
require_once '../config/database.php';

try {
    // التحقق من وجود معرف الطلب
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('معرف الطلب غير صحيح');
    }
    
    $requestId = (int)$_GET['id'];
    
    // جلب معلومات الطلب الأساسية مع نوع الطلب
    $stmt = $db->prepare("
        SELECT 
            cr.*,
            rt.type_name,
            rt.type_description,
            rt.required_documents,
            rt.fees,
            DATEDIFF(NOW(), cr.created_at) as days_since_created
        FROM citizen_requests cr
        LEFT JOIN request_types rt ON cr.request_type_id = rt.id
        WHERE cr.id = ?
    ");
    
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        throw new Exception('الطلب غير موجود');
    }
    
    // جلب البيانات الإضافية من النموذج الديناميكي
    $formDataStmt = $db->prepare("
        SELECT field_name, field_value, field_type, created_at
        FROM request_form_data 
        WHERE request_id = ?
        ORDER BY created_at ASC
    ");
    $formDataStmt->execute([$requestId]);
    $formData = $formDataStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب المستندات المرفقة
    $documentsStmt = $db->prepare("
        SELECT 
            id,
            document_name,
            original_filename,
            file_path,
            file_size,
            file_type,
            is_required,
            uploaded_at
        FROM request_documents 
        WHERE request_id = ?
        ORDER BY uploaded_at ASC
    ");
    $documentsStmt->execute([$requestId]);
    $documents = $documentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب تحديثات الطلب
    $updatesStmt = $db->prepare("
        SELECT 
            id,
            update_type,
            update_text,
            updated_by,
            is_visible_to_citizen,
            created_at
        FROM request_updates 
        WHERE request_id = ?
        ORDER BY created_at DESC
    ");
    $updatesStmt->execute([$requestId]);
    $updates = $updatesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تجميع جميع البيانات
    $response = [
        'success' => true,
        'request' => [
            'id' => $request['id'],
            'tracking_number' => $request['tracking_number'],
            'citizen_name' => $request['citizen_name'],
            'citizen_phone' => $request['citizen_phone'],
            'citizen_email' => $request['citizen_email'],
            'citizen_address' => $request['citizen_address'],
            'national_id' => $request['national_id'],
            'request_type_id' => $request['request_type_id'],
            'request_title' => $request['request_title'],
            'request_description' => $request['request_description'],
            'status' => $request['status'],
            'priority_level' => $request['priority_level'],
            'estimated_completion_date' => $request['estimated_completion_date'],
            'admin_notes' => $request['admin_notes'],
            'created_at' => $request['created_at'],
            'updated_at' => $request['updated_at'],
            'days_since_created' => $request['days_since_created'],
            
            // معلومات نوع الطلب
            'type_name' => $request['type_name'],
            'type_description' => $request['type_description'],
            'required_documents' => $request['required_documents'],
            'fees' => $request['fees'],
            
            // البيانات المرتبطة
            'form_data' => $formData,
            'documents' => $documents,
            'updates' => $updates
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في قاعدة البيانات: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

