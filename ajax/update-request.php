<?php
/**
 * ملف تحديث الطلب مع البيانات الديناميكية
 * يدعم تحديث الحالة، الأولوية، البيانات الإضافية، والملاحظات الإدارية
 */

header('Content-Type: application/json; charset=utf-8');

// تضمين ملف الاتصال بقاعدة البيانات
require_once '../config/database.php';

try {
    // التحقق من طريقة الطلب
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('طريقة الطلب غير مدعومة');
    }
    
    // التحقق من وجود معرف الطلب
    if (!isset($_POST['request_id']) || !is_numeric($_POST['request_id'])) {
        throw new Exception('معرف الطلب غير صحيح');
    }
    
    $requestId = (int)$_POST['request_id'];
    
    // التحقق من وجود الطلب
    $checkStmt = $db->prepare("SELECT id FROM citizen_requests WHERE id = ?");
    $checkStmt->execute([$requestId]);
    if (!$checkStmt->fetch()) {
        throw new Exception('الطلب غير موجود');
    }
    
    // بدء المعاملة
    $db->beginTransaction();
    
    // تحديث البيانات الأساسية للطلب
    $updateFields = [];
    $updateValues = [];
    
    if (isset($_POST['status']) && !empty($_POST['status'])) {
        $updateFields[] = "status = ?";
        $updateValues[] = $_POST['status'];
    }
    
    if (isset($_POST['priority_level']) && !empty($_POST['priority_level'])) {
        $updateFields[] = "priority_level = ?";
        $updateValues[] = $_POST['priority_level'];
    }
    
    if (isset($_POST['estimated_completion_date'])) {
        $updateFields[] = "estimated_completion_date = ?";
        $updateValues[] = !empty($_POST['estimated_completion_date']) ? $_POST['estimated_completion_date'] : null;
    }
    
    if (isset($_POST['admin_notes'])) {
        $updateFields[] = "admin_notes = ?";
        $updateValues[] = $_POST['admin_notes'];
    }
    
    // إضافة تاريخ التحديث
    $updateFields[] = "updated_at = NOW()";
    $updateValues[] = $requestId;
    
    if (!empty($updateFields)) {
        $updateSql = "UPDATE citizen_requests SET " . implode(", ", $updateFields) . " WHERE id = ?";
        $updateStmt = $db->prepare($updateSql);
        $updateStmt->execute($updateValues);
    }
    
    // تحديث البيانات الديناميكية إذا تم إرسالها
    if (isset($_POST['form_data']) && is_array($_POST['form_data'])) {
        foreach ($_POST['form_data'] as $fieldName => $fieldValue) {
            // التحقق من وجود الحقل
            $fieldCheckStmt = $db->prepare("
                SELECT id FROM request_form_data 
                WHERE request_id = ? AND field_name = ?
            ");
            $fieldCheckStmt->execute([$requestId, $fieldName]);
            
            if ($fieldCheckStmt->fetch()) {
                // تحديث الحقل الموجود
                $fieldUpdateStmt = $db->prepare("
                    UPDATE request_form_data 
                    SET field_value = ? 
                    WHERE request_id = ? AND field_name = ?
                ");
                $fieldUpdateStmt->execute([$fieldValue, $requestId, $fieldName]);
            } else {
                // إضافة حقل جديد
                $fieldInsertStmt = $db->prepare("
                    INSERT INTO request_form_data (request_id, field_name, field_value, field_type, created_at)
                    VALUES (?, ?, ?, 'text', NOW())
                ");
                $fieldInsertStmt->execute([$requestId, $fieldName, $fieldValue]);
            }
        }
    }
    
    // إضافة تحديث في جدول request_updates
    $updateText = "تم تحديث الطلب";
    $updateDetails = [];
    
    if (isset($_POST['status'])) {
        $updateDetails[] = "الحالة: " . $_POST['status'];
    }
    if (isset($_POST['priority_level'])) {
        $updateDetails[] = "الأولوية: " . $_POST['priority_level'];
    }
    if (isset($_POST['estimated_completion_date']) && !empty($_POST['estimated_completion_date'])) {
        $updateDetails[] = "التاريخ المتوقع: " . $_POST['estimated_completion_date'];
    }
    
    if (!empty($updateDetails)) {
        $updateText .= " - " . implode(", ", $updateDetails);
    }
    
    $updateInsertStmt = $db->prepare("
        INSERT INTO request_updates (request_id, update_type, update_text, updated_by, is_visible_to_citizen, created_at)
        VALUES (?, 'تحديث إداري', ?, 'النظام الإداري', 1, NOW())
    ");
    $updateInsertStmt->execute([$requestId, $updateText]);
    
    // تأكيد المعاملة
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'تم تحديث الطلب بنجاح'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // التراجع عن المعاملة في حالة الخطأ
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    // التراجع عن المعاملة في حالة خطأ قاعدة البيانات
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في قاعدة البيانات: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

