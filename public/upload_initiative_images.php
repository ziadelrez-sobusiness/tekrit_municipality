<?php
header('Content-Type: application/json');
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth->requireLogin();
if (!$auth->checkPermission('employee')) {
    echo json_encode(['success' => false, 'message' => 'لا تملك الصلاحيات الكافية']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$initiative_id = $_POST['initiative_id'] ?? 0;
$uploaded_files = $_FILES['images'] ?? [];

if (empty($initiative_id) {
    echo json_encode(['success' => false, 'message' => 'معرف المبادرة غير صالح']);
    exit();
}

$results = [];
$upload_dir = '../uploads/initiatives/' . $initiative_id . '/';

if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

foreach ($uploaded_files['tmp_name'] as $key => $tmp_name) {
    $file_name = basename($uploaded_files['name'][$key]);
    $file_path = $upload_dir . $file_name;
    $file_size = $uploaded_files['size'][$key];
    
    if (move_uploaded_file($tmp_name, $file_path)) {
        try {
            $stmt = $db->prepare("INSERT INTO initiative_images 
                                (initiative_id, image_path, image_name, file_size, uploaded_by) 
                                VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $initiative_id,
                $file_path,
                $file_name,
                $file_size,
                $auth->getCurrentUser()['id']
            ]);
            
            $results[] = [
                'success' => true,
                'file_name' => $file_name,
                'file_path' => $file_path
            ];
        } catch (Exception $e) {
            $results[] = [
                'success' => false,
                'file_name' => $file_name,
                'error' => $e->getMessage()
            ];
        }
    } else {
        $results[] = [
            'success' => false,
            'file_name' => $file_name,
            'error' => 'فشل في رفع الملف'
        ];
    }
}

echo json_encode(['success' => true, 'results' => $results]);
?>