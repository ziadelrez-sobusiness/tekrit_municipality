<?php
/**
 * ملف إضافة تعليق على الطلب
 * يدعم إضافة تعليقات مرئية أو غير مرئية للمواطن
 */

header('Content-Type: application/json; charset=utf-8');

// تضمين ملف الاتصال بقاعدة البيانات
require_once '../config/database.php';

try {
    // التحقق من طريقة الطلب
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('طريقة الطلب غير مدعومة');
    }
    
    // التحقق من البيانات المطلوبة
    if (!isset($_POST['request_id']) || !is_numeric($_POST['request_id'])) {
        throw new Exception('معرف الطلب غير صحيح');
    }
    
    if (!isset($_POST['comment_text']) || empty(trim($_POST['comment_text']))) {
        throw new Exception('نص التعليق مطلوب');
    }
    
    $requestId = (int)$_POST['request_id'];
    $commentText = trim($_POST['comment_text']);
    $isVisibleToCitizen = isset($_POST['is_visible_to_citizen']) ? 1 : 0;
    $updatedBy = $_POST['updated_by'] ?? 'موظف البلدية';
    
    // التحقق من وجود الطلب
    $checkStmt = $db->prepare("SELECT id, citizen_name FROM citizen_requests WHERE id = ?");
    $checkStmt->execute([$requestId]);
    $request = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        throw new Exception('الطلب غير موجود');
    }
    
    // إضافة التعليق في جدول request_updates
    $insertStmt = $db->prepare("
        INSERT INTO request_updates (
            request_id, 
            update_type, 
            update_text, 
            updated_by, 
            is_visible_to_citizen, 
            created_at
        ) VALUES (?, 'تعليق', ?, ?, ?, NOW())
    ");
    
    $insertStmt->execute([
        $requestId,
        $commentText,
        $updatedBy,
        $isVisibleToCitizen
    ]);
    
    // الحصول على معرف التحديث المُدرج
    $updateId = $db->lastInsertId();
    
    // تحديث تاريخ آخر تحديث للطلب
    $updateRequestStmt = $db->prepare("UPDATE citizen_requests SET updated_at = NOW() WHERE id = ?");
    $updateRequestStmt->execute([$requestId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'تم إضافة التعليق بنجاح',
        'update_id' => $updateId,
        'data' => [
            'request_id' => $requestId,
            'update_type' => 'تعليق',
            'update_text' => $commentText,
            'updated_by' => $updatedBy,
            'is_visible_to_citizen' => $isVisibleToCitizen,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ], JSON_UNESCAPED_UNICODE);
    
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

