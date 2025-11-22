<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/auth.php';
require_once '../config/database.php';

// التأكد من تسجيل الدخول
$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();

$response = ['success' => false, 'complaint' => null, 'updates' => [], 'error' => null];

try {
    $complaint_id = intval($_GET['id'] ?? 0);
    
    if ($complaint_id <= 0) {
        $response['error'] = 'معرف الشكوى غير صحيح';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // فحص الأعمدة الفعلية في الجدول
    try {
        $columnsStmt = $db->query("SHOW COLUMNS FROM complaints");
        $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
        $hasCitizenName = in_array('citizen_name', $columns);
        $hasComplainantName = in_array('complainant_name', $columns);
        $hasCitizenPhone = in_array('citizen_phone', $columns);
        $hasComplainantPhone = in_array('complainant_phone', $columns);
        $hasCategory = in_array('category', $columns);
        $hasComplaintType = in_array('complaint_type', $columns);
        $hasDescription = in_array('description', $columns);
        $hasDetails = in_array('details', $columns);
    } catch (Exception $e) {
        error_log("Error checking columns: " . $e->getMessage());
        // افتراض وجود الأعمدة الأساسية
        $hasCitizenName = true;
        $hasComplainantName = false;
        $hasCitizenPhone = true;
        $hasComplainantPhone = false;
        $hasCategory = false;
        $hasComplaintType = true;
        $hasDescription = true;
        $hasDetails = false;
    }
    
    // بناء SELECT clause ديناميكياً
    $selectFields = ["c.*"];
    $selectFields[] = "ca.phone as citizen_phone_from_account";
    $selectFields[] = "ca.name as citizen_name_from_account";
    $selectFields[] = "u.full_name as assigned_user_name";
    
    // اسم المشتكي
    $nameFields = [];
    if ($hasCitizenName) $nameFields[] = "c.citizen_name";
    if ($hasComplainantName) $nameFields[] = "c.complainant_name";
    $nameFields[] = "ca.name";
    $nameFields[] = "'غير محدد'";
    if (!empty($nameFields)) {
        $selectFields[] = "COALESCE(" . implode(", ", $nameFields) . ") as complainant_name_display";
    } else {
        $selectFields[] = "'غير محدد' as complainant_name_display";
    }
    
    // رقم الهاتف
    $phoneFields = [];
    if ($hasCitizenPhone) $phoneFields[] = "c.citizen_phone";
    if ($hasComplainantPhone) $phoneFields[] = "c.complainant_phone";
    $phoneFields[] = "ca.phone";
    $phoneFields[] = "'غير محدد'";
    if (!empty($phoneFields)) {
        $selectFields[] = "COALESCE(" . implode(", ", $phoneFields) . ") as complainant_phone_display";
    } else {
        $selectFields[] = "'غير محدد' as complainant_phone_display";
    }
    
    // الفئة
    $categoryFields = [];
    if ($hasCategory) $categoryFields[] = "c.category";
    if ($hasComplaintType) $categoryFields[] = "c.complaint_type";
    $categoryFields[] = "'غير محدد'";
    if (!empty($categoryFields)) {
        $selectFields[] = "COALESCE(" . implode(", ", $categoryFields) . ") as category_display";
    } else {
        $selectFields[] = "'غير محدد' as category_display";
    }
    
    // الوصف
    $descriptionFields = [];
    if ($hasDescription) $descriptionFields[] = "c.description";
    if ($hasDetails) $descriptionFields[] = "c.details";
    $descriptionFields[] = "''";
    if (!empty($descriptionFields)) {
        $selectFields[] = "COALESCE(" . implode(", ", $descriptionFields) . ") as description_display";
    } else {
        $selectFields[] = "'' as description_display";
    }
    
    // جلب تفاصيل الشكوى
    $sql = "
        SELECT " . implode(", ", $selectFields) . "
        FROM complaints c
        LEFT JOIN citizens_accounts ca ON c.citizen_id = ca.id
        LEFT JOIN users u ON c.assigned_to = u.id
        WHERE c.id = ?
    ";
    
    error_log("Complaint Details Query: " . $sql);
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([$complaint_id]);
        $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$complaint) {
            $response['error'] = 'الشكوى غير موجودة';
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // إضافة الحقول المحسوبة للتوافق مع JavaScript
        $complaint['complainant_name'] = $complaint['complainant_name_display'] ?? 
                                         ($hasCitizenName ? ($complaint['citizen_name'] ?? 'غير محدد') : 
                                          ($hasComplainantName ? ($complaint['complainant_name'] ?? 'غير محدد') : 'غير محدد'));
        $complaint['complainant_phone'] = $complaint['complainant_phone_display'] ?? 
                                          ($hasCitizenPhone ? ($complaint['citizen_phone'] ?? 'غير محدد') : 
                                           ($hasComplainantPhone ? ($complaint['complainant_phone'] ?? 'غير محدد') : 'غير محدد'));
        $complaint['category'] = $complaint['category_display'] ?? 
                                 ($hasCategory ? ($complaint['category'] ?? 'غير محدد') : 
                                  ($hasComplaintType ? ($complaint['complaint_type'] ?? 'غير محدد') : 'غير محدد'));
        $complaint['description'] = $complaint['description_display'] ?? 
                                    ($hasDescription ? ($complaint['description'] ?? '') : 
                                     ($hasDetails ? ($complaint['details'] ?? '') : ''));
    } catch (PDOException $e) {
        error_log("Error fetching complaint details: " . $e->getMessage());
        error_log("SQL: " . $sql);
        $response['error'] = 'خطأ في جلب البيانات: ' . $e->getMessage();
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if (!$complaint) {
        $response['error'] = 'الشكوى غير موجودة';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // جلب التحديثات
    $updatesStmt = $db->prepare("
        SELECT cu.*, u.full_name as updated_by_name
        FROM complaint_updates cu
        LEFT JOIN users u ON cu.updated_by = u.id
        WHERE cu.complaint_id = ?
        ORDER BY cu.created_at DESC
    ");
    $updatesStmt->execute([$complaint_id]);
    $updates = $updatesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response['success'] = true;
    $response['complaint'] = $complaint;
    $response['updates'] = $updates;
    
} catch (Exception $e) {
    $response['error'] = 'خطأ في جلب البيانات: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

