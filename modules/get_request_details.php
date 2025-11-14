<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../includes/Database.php';
require_once '../includes/CitizenRequest.php';
require_once '../includes/Utils.php';

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    Utils::sendJsonResponse(['success' => false, 'error' => 'غير مصرح'], 401);
}

$citizenRequest = new CitizenRequest();

$request_id = $_GET['id'] ?? 0;

if (!$request_id) {
    Utils::sendJsonResponse(['success' => false, 'error' => 'معرف الطلب مطلوب'], 400);
}

try {
    $request = $citizenRequest->getById($request_id);
    
    if (!$request) {
        Utils::sendJsonResponse(['success' => false, 'error' => 'الطلب غير موجود'], 404);
    }
    
    Utils::sendJsonResponse([
        'success' => true,
        'request' => $request
    ]);
    
} catch (Exception $e) {
    Utils::sendJsonResponse(['success' => false, 'error' => 'خطأ في جلب البيانات: ' . $e->getMessage()], 500);
}
?> 